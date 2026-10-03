#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# Self-test for 03_te_annotation_table.py.
#
# Instead of guessing coordinates, this test DERIVES four windows from the real
# annotation that are guaranteed to be unambiguous:
#
#   exon       inside an annotated exon of one gene, and outside every other
#              exon of every isoform
#   intron     inside a single gene's span, overlapping no exon of ANY isoform
#   promoter   1..2000 bp upstream of a gene's TSS, overlapping no gene at all
#   intergenic >10 kb away from every gene, overlapping nothing
#
# Then it checks that the table builder reports exactly those regions.
#
#   bash test_table_logic.sh
# ---------------------------------------------------------------------------
set -euo pipefail

ROOT=/mnt/sda/jackie/cnidaria_omics/genome_TE
PIPE="${ROOT}/TE_pipeline"
SPECIES=Nematostella_vectensis
TMP=$(mktemp -d)
trap 'rm -rf "${TMP}"' EXIT

echo ">>> deriving unambiguous test windows from ${SPECIES}"

python3 - "${ROOT}/${SPECIES}.gff3.gz" "${TMP}/te.gff3" "${TMP}/expect.tsv" <<'PY'
import gzip, re, sys

src, te_out, exp_out = sys.argv[1], sys.argv[2], sys.argv[3]
WIN = 50

genes = {}          # gid -> (chrom, start, end, strand)
mrna = {}           # tid -> gid
exons = {}          # tid -> [(s, e)]
with gzip.open(src, "rt") as fh:
    for line in fh:
        if line[0] == "#":
            continue
        f = line.rstrip("\n").split("\t")
        if len(f) < 9:
            continue
        if f[2] == "gene":
            m = re.search(r"ID=([^;]+)", f[8])
            if m:
                genes[m.group(1)] = (f[0], int(f[3]), int(f[4]), f[6])
        elif f[2] in ("mRNA", "transcript"):
            m = re.search(r"ID=([^;]+)", f[8])
            p = re.search(r"Parent=([^;]+)", f[8])
            if m and p:
                mrna[m.group(1)] = p.group(1)
        elif f[2] == "exon":
            p = re.search(r"Parent=([^;]+)", f[8])
            if p:
                exons.setdefault(p.group(1), []).append((int(f[3]), int(f[4])))

# flatten: every exon interval with its gene, and every gene span
all_exons = []      # (chrom, s, e, gid)
gene_spans = []     # (chrom, s, e, gid, strand)
for tid, xl in exons.items():
    gid = mrna.get(tid)
    if gid not in genes:
        continue
    chrom = genes[gid][0]
    for s, e in xl:
        all_exons.append((chrom, s, e, gid))
for gid, (chrom, s, e, strand) in genes.items():
    gene_spans.append((chrom, s, e, gid, strand))

def hits(pool, chrom, s, e, skip=None):
    return [x for x in pool
            if x[0] == chrom and x[2] >= s and x[1] <= e
            and not (skip is not None and x[3] == skip)]

# ---- exon ---------------------------------------------------------------
# an exon that is not inside any other gene's exon and lies outside other genes
exon_pick = None
for chrom, s, e, gid in all_exons:
    if e - s + 1 < WIN + 20:
        continue
    ws, we = s + 10, s + 10 + WIN - 1
    if hits(all_exons, chrom, ws, we, skip=gid):
        continue
    if hits(gene_spans, chrom, ws, we, skip=gid):
        continue
    exon_pick = (chrom, ws, we, gid)
    break

# ---- intron -------------------------------------------------------------
# inside one gene's span, overlapping no exon of any isoform of any gene
intron_pick = None
for chrom, s, e, gid, strand in gene_spans:
    if e - s < 2000:
        continue
    for ws in range(s + 200, e - WIN - 200, 500):
        we = ws + WIN - 1
        if hits(all_exons, chrom, ws, we):
            continue
        if hits(gene_spans, chrom, ws, we, skip=gid):
            continue
        intron_pick = (chrom, ws, we, gid)
        break
    if intron_pick:
        break

# ---- promoter (+ strand) ------------------------------------------------
# 1..2000 bp upstream of a + strand TSS (i.e. before the gene start), and
# unambiguous: no other gene may be within 2000 bp on its own promoter side.
def unambig(chrom, ws, we, gid):
    """True if no gene other than gid can legitimately claim this window as
    its promoter -- i.e. the window is not a bidirectional promoter shared
    with a divergently transcribed neighbour."""
    for c2, s2, e2, g2, st2 in gene_spans:
        if c2 != chrom or g2 == gid:
            continue
        if st2 == "-":
            if we > e2 and we - e2 <= 2000:      # - gene's promoter is after it
                return False
        else:
            if ws < s2 and s2 - ws <= 2000:      # + gene's promoter is before it
                return False
    return True

prom_pick = None
for chrom, s, e, gid, strand in gene_spans:
    if strand != "+":
        continue
    for off in (500, 900, 1300, 1700, 1900):
        we = s - off
        ws = we - WIN + 1
        if ws < 1 or hits(gene_spans, chrom, ws, we) or not unambig(chrom, ws, we, gid):
            continue
        prom_pick = (chrom, ws, we, gid)
        break
    if prom_pick:
        break

# ---- promoter (- strand) ------------------------------------------------
# 1..2000 bp upstream of a - strand TSS (i.e. AFTER the gene end)
promm_pick = None
for chrom, s, e, gid, strand in gene_spans:
    if strand != "-":
        continue
    for off in (500, 900, 1300, 1700, 1900):
        ws = e + off
        we = ws + WIN - 1
        if hits(gene_spans, chrom, ws, we) or not unambig(chrom, ws, we, gid):
            continue
        promm_pick = (chrom, ws, we, gid)
        break
    if promm_pick:
        break

# ---- intergenic ---------------------------------------------------------
# anywhere at least 10 kb from every gene
inter_pick = None
chrom0 = all_exons[0][0]
spans0 = sorted([g for g in gene_spans if g[0] == chrom0], key=lambda x: x[1])
for i in range(len(spans0) - 1):
    gap_s, gap_e = spans0[i][2], spans0[i + 1][1]
    if gap_e - gap_s > 25000:
        ws = gap_s + 10000
        we = ws + WIN - 1
        if not hits(gene_spans, chrom0, ws, we):
            inter_pick = (chrom0, ws, we, None)
            break

picks = [("TE_homo_0", exon_pick, "exon", "TIR/CACTA"),
         ("TE_homo_1", intron_pick, "intron", "LTR/unknown"),
         ("TE_homo_2", prom_pick, "promoter", "LINE/L2"),
         ("TE_homo_3", promm_pick, "promoter", "TIR/Mutator"),
         ("TE_homo_4", inter_pick, "intergenic", "helitron")]

missing = [n for n, p, _r, _c in picks if p is None]
if missing:
    sys.exit("could not derive test windows for: %s" % ", ".join(missing))

with open(te_out, "w") as fh, open(exp_out, "w") as ex:
    fh.write("##gff-version 3\n")
    for tid, (chrom, s, e, gid), region, cls in picks:
        fh.write("%s\tEDTA\tTE_homo\t%d\t%d\t.\t+\t.\t"
                 "ID=%s;Name=TE_consensus#%s;classification=%s;"
                 "sequence_ontology=SO:0000000;method=homology\n"
                 % (chrom, s, e, tid, cls, cls))
        ex.write("%s\t%s\t%s\t%d\t%d\t%s\n"
                 % (tid, region, cls, s, e, gid or "NA"))
        print("    %-10s %-11s %s:%d-%d  gene=%s"
              % (tid, region, chrom, s, e, gid or "-"))
PY

# --- run the table builder -------------------------------------------------
python3 "${PIPE}/03_te_annotation_table.py" \
    --species "${SPECIES}" \
    --te-gff "${TMP}/te.gff3" \
    --gene-gff "${ROOT}/${SPECIES}.gff3.gz" \
    --genome "${ROOT}/${SPECIES}.fa.gz" \
    --out "${TMP}/out.tsv" 2>&1 | sed 's/^/    /'

# --- check -----------------------------------------------------------------
python3 - "${TMP}/out.tsv" "${TMP}/expect.tsv" "${PIPE}" <<'PY'
import importlib.util, sys

out, exp, pipe_dir = sys.argv[1], sys.argv[2], sys.argv[3]

# use the table builder's own ontology mapping so the expected TE_type is the
# canonical SO name (CACTA_TIR_transposon), not the raw EDTA class (TIR/CACTA)
spec = importlib.util.spec_from_file_location(
    "tbl", "%s/03_te_annotation_table.py" % pipe_dir)
tbl = importlib.util.module_from_spec(spec)
spec.loader.exec_module(tbl)
alias, soid, so_names = tbl.load_so_ontology(tbl.find_so_file())

want = {}
for line in open(exp):
    tid, region, ttype, s, e, gid = line.rstrip("\n").split("\t")
    want[tid] = (region, tbl.normalise_type(ttype, "", alias, soid),
                 int(s), int(e), gid)

hdr_want = ["species", "TE_id", "scaffold", "TE_start", "TE_end",
            "related_gene", "region", "TE_type"]
fails = 0
seen = set()
with open(out) as fh:
    hdr = fh.readline().rstrip("\n").split("\t")
    if hdr != hdr_want:
        print("  FAIL header: %s" % hdr); fails += 1
    for line in fh:
        f = line.rstrip("\n").split("\t")
        tid = f[1]
        seen.add(tid)
        region, ttype, s, e, gid = want[tid]
        ok = (f[6] == region and f[7] == ttype and
              int(f[3]) == s and int(f[4]) == e)
        if gid != "NA":
            ok = ok and f[5] == gid
        if not ok:
            fails += 1
        print("  %s %-10s region=%-11s type=%-24s gene=%s"
              % ("ok  " if ok else "FAIL", tid, f[6], f[7], f[5]))
        if not ok:
            print("       expected region=%s type=%s gene=%s"
                  % (region, ttype, gid))

missing = set(want) - seen
if missing:
    print("  FAIL missing rows: %s" % missing); fails += 1

print()
print("RESULT: %s" % ("PASS" if fails == 0 else "FAIL"))
sys.exit(1 if fails else 0)
PY
