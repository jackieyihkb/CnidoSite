"""给「只有序列、没有注释」的线粒体记录找一份带注释的替代记录。

背景：Dendrogyra cylindrus、Siderastrea siderea 等 6 个物种，第一次匹配到的是
GenBank 里以 WGS 方式提交的 "mitochondrion, complete sequence" 记录 —— 标题是
线粒体没错，长度和 GC 有效，但记录里没有 FEATURES 段，于是 mitochondrion 表里
一个基因都没有，mitdata.php 上既画不出环形图、也没有基因表。
这些物种在 NCBI 上另有带注释的记录，这里逐个找回来。

匹配从严：esummary 的 organism 必须与站内学名完全一致，避免属种相同但株系不同的
记录混进来（AAURI2 就是这么误配的）。
"""
import json, re, subprocess, time, sys
from urllib.parse import quote

E = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils"
HERE = "/home/jackie/cnidosite-work/mito"

def curl(url):
    r = subprocess.run(["curl", "-s", "--max-time", "60", url], capture_output=True, text=True)
    return r.stdout or ""

def esearch(term, n=20):
    # 方括号必须百分号编码：NCBI 对字面量的 [ ] 解析不正确，会返回 0 条
    t = curl(f"{E}/esearch.fcgi?db=nuccore&retmax={n}&term={quote(term)}")
    return re.findall(r'<Id>(\d+)</Id>', t)

def esummary_org(uid):
    t = curl(f"{E}/esummary.fcgi?db=nuccore&retmode=json&id={uid}")
    try:
        d = json.loads(t)["result"]
    except Exception:
        return None, None, None
    for k, v in d.items():
        if k == "uids":
            continue
        return v.get("accessionversion"), v.get("organism") or "", v.get("title") or ""
    return None, None, None

def parse_features(txt):
    """只数 gene/tRNA/rRNA 特征，与 fetch.py 同规则。"""
    infeat = False
    n = {"gene": 0, "tRNA": 0, "rRNA": 0}
    for line in txt.split("\n"):
        if line.startswith("FEATURES"):
            infeat = True; continue
        if infeat and (line.startswith("ORIGIN") or line.startswith("BASE COUNT") or line.startswith("CONTIG")):
            break
        if not infeat:
            continue
        m = re.match(r'^ {5}(\S+)\s+', line)
        if m and m.group(1) in n:
            n[m.group(1)] += 1
    return n["gene"] + n["tRNA"] + n["rRNA"]

targets = json.load(open(sys.argv[1]))          # {abbr1: {"species":..., "acc":...}}
out = {}
for ab, v in sorted(targets.items()):
    sp = v["species"]
    q = f'"{sp}"[Organism] AND mitochondrion[Title]'
    uids = esearch(q)
    best = None
    for uid in uids[:12]:
        acc, org, title = esummary_org(uid)
        if not acc:
            continue
        # 学名必须完全一致（忽略大小写与多余空格）
        if org.strip().lower() != sp.strip().lower():
            continue
        txt = curl(f"{E}/efetch.fcgi?db=nuccore&id={acc}&rettype=gb&retmode=text")
        if "LOCUS" not in txt:
            continue
        nf = parse_features(txt)
        if nf > 0:
            best = {"acc": acc, "n_feat": nf, "title": title}
            break
        time.sleep(0.34)
    out[ab] = {"species": sp, "old_acc": v["acc"], "found": best}
    print(f"  {ab:8s} {sp:34s} {v['acc']:14s} -> " +
          (f"{best['acc']} ({best['n_feat']} 特征)" if best else "未找到带注释的记录"), flush=True)
    time.sleep(0.34)

json.dump(out, open(f"{HERE}/annotated_alt.json", "w"), indent=1, ensure_ascii=False)
print("候选写入 annotated_alt.json")
