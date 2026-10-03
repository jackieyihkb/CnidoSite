"""为每个 CnidoSite 物种取一份线粒体 GenBank 记录，解析出基因组级统计与特征表。

输出 mito_fetch.json：
  { abbr1: {species, acc, size, gc, n_gene, n_trna, n_rrna, features:[{name,type,start,end,strand,length}], retrieved} }

只落盘，不写数据库 —— 入库由随后的 PHP 脚本负责，便于先核对再导入。
"""
import json, re, subprocess, time, sys

E = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils"
plan = json.load(open('/home/jackie/cnidosite-work/mito/plan.json'))
plan.pop('AAURI2', None)          # 与 Aurelia aurita 同名冲突，AAURI1 才是该种
accs = {k: v for k, v in plan.items()}

# 已在 mitochondrion 表里的物种，用表里的 accession
import subprocess as sp
php = r'''
$c=new mysqli("localhost","jackie","jackie","cnidaria");
$q=$c->query("SELECT species, abbr, accession FROM mitochondrion GROUP BY species, abbr, accession");
while($r=$q->fetch_row()) echo $r[1],"\t",$r[2],"\t",$r[0],"\n";
'''
out = sp.run(["php","-d","extension=mysqlnd.so","-d","extension=mysqli.so","-r",php],
             capture_output=True, text=True).stdout
existing = {}
for line in out.strip().split("\n"):
    if not line.strip(): continue
    a, acc, species = line.split("\t")
    existing.setdefault(a, {"acc": acc, "species": species})

targets = {}
for k, v in accs.items():
    targets[k] = {"species": v["species"], "acc": v["acc"], "new": not v["already"]}
for k, v in existing.items():
    if k not in targets:
        targets[k] = {"species": v["species"], "acc": v["acc"], "new": False}
    elif not targets[k]["acc"]:
        targets[k]["acc"] = v["acc"]

print("待取物种数:", len(targets), flush=True)

def parse_gb(txt):
    size = None
    m = re.search(r'^LOCUS\s+\S+\s+(\d+)\s+bp', txt, re.M)
    if m: size = int(m.group(1))
    # 序列
    seq = []
    inorigin = False
    for line in txt.split("\n"):
        if line.startswith("ORIGIN"):
            inorigin = True; continue
        if inorigin:
            if line.startswith("//"): break
            seq.append(re.sub(r'[^acgtnACGTN]', '', line))
    seq = "".join(seq).upper()
    gc = None
    if seq:
        gc = round(100.0 * (seq.count("G") + seq.count("C")) / len(seq), 2)
        if size is None: size = len(seq)
    # FEATURES
    feats = []
    infeat = False
    cur = None
    for line in txt.split("\n"):
        if line.startswith("FEATURES"): infeat = True; continue
        if infeat and (line.startswith("ORIGIN") or line.startswith("BASE COUNT") or line.startswith("CONTIG")):
            break
        if not infeat: continue
        m = re.match(r'^ {5}(\S+)\s+(.+)$', line)
        if m:
            if cur: feats.append(cur)
            cur = {"kind": m.group(1), "loc": m.group(2).strip(), "name": "", "product": ""}
            continue
        if cur is not None:
            m = re.match(r'^\s+/(\w+)="?([^"]*)"?', line)
            if m:
                if m.group(1) == "gene": cur["name"] = m.group(2)
                elif m.group(1) == "product" and not cur["product"]: cur["product"] = m.group(2)
    if cur: feats.append(cur)

    def coord(loc):
        # complement(402..1541) / join(1..50,100..200) → 起止
        strand = "-" if "complement" in loc else "+"
        nums = [int(x) for x in re.findall(r'(\d+)', loc)]
        if not nums: return None
        return min(nums), max(nums), strand

    rows = []
    for f in feats:
        kind = f["kind"]
        if kind not in ("gene", "tRNA", "rRNA"): continue
        c = coord(f["loc"])
        if not c: continue
        s, e, strand = c
        nm = f["name"] or f["product"] or ""
        rows.append({"name": nm, "type": {"gene": "gene", "tRNA": "trna", "rRNA": "rrna"}[kind],
                     "start": s, "end": e, "strand": strand, "length": e - s + 1})
    return size, gc, rows

res = {}
fail = []
for i, (k, v) in enumerate(sorted(targets.items())):
    acc = re.sub(r'\.\d+$', '', v["acc"])
    txt = None
    for attempt in range(3):
        r = subprocess.run(["curl", "-s", "--max-time", "60",
                            f"{E}/efetch.fcgi?db=nuccore&id={acc}&rettype=gb&retmode=text"],
                           capture_output=True, text=True)
        if r.stdout and "LOCUS" in r.stdout: txt = r.stdout; break
        time.sleep(1.5)
    if txt is None:
        fail.append((k, acc)); continue
    size, gc, rows = parse_gb(txt)
    res[k] = {"species": v["species"], "acc": v["acc"], "size": size, "gc": gc,
              "n_gene": sum(1 for r in rows if r["type"] == "gene"),
              "n_trna": sum(1 for r in rows if r["type"] == "trna"),
              "n_rrna": sum(1 for r in rows if r["type"] == "rrna"),
              "new": v["new"], "features": rows,
              "retrieved": time.strftime("%Y-%m-%d")}
    if (i + 1) % 25 == 0:
        print(f"  {i+1}/{len(targets)}", flush=True)
    time.sleep(0.34)

json.dump(res, open('/home/jackie/cnidosite-work/mito/mito_fetch.json', 'w'), indent=1)
print("成功:", len(res), " 失败:", len(fail))
for k, a in fail: print("  失败:", k, a)
