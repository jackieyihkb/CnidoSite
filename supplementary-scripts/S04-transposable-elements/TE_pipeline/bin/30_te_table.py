#!/usr/bin/env python3
"""30_te_table.py <species>

Turn one species' EDTA whole-genome TE annotation into a queryable table:

    species | TE_id | scaffold | TE_start | TE_end | related_gene | region | TE_type

`region` is where the TE sits relative to the closest gene:

    exon | intron | 5UTR | 3UTR | promoter | intergenic region | gene_body

Everything is reported in the coordinate space of the *original* assembly.
EDTA is run on renamed sequences (seq0000001...), so the idmap written by
10_prepare.sh translates back; the gene GFF, which may use a third naming
scheme (NCBI accessions vs chrN), is reconciled to the genome by sequence name
and then by sequence length.

Also writes
    <species>.TE_info.extended.tsv.gz  strand, length, family, gene distance, ...
    <species>.TE_summary.tsv           per-class counts and genome fraction
"""
import gzip
import os
import sys
from bisect import bisect_right, bisect_left
from collections import defaultdict

TE_ROOT = os.environ.get("TE_ROOT", "/mnt/sda/jackie/cnidaria/codex/genome_TE")
# Scratch lives in te_work/, not work/ -- work/ holds a partial copy from another
# server that must not be trusted (see README 8(3)).  Honour the same TE_WORK the
# shell side uses, with the same default.
TE_WORK = os.environ.get("TE_WORK", os.path.join(TE_ROOT, "te_work"))
PROM_UP = int(os.environ.get("PROMOTER_UP", 2000))
PROM_DOWN = int(os.environ.get("PROMOTER_DOWN", 0))

TRANSCRIPT_TYPES = {
    "mRNA", "transcript", "lnc_RNA", "ncRNA", "tRNA", "rRNA", "snRNA",
    "snoRNA", "miRNA", "guide_RNA", "RNase_P_RNA", "SRP_RNA", "telomerase_RNA",
    "pseudogenic_transcript", "V_gene_segment", "C_gene_segment", "misc_RNA",
}
CLASS_KEYS = ("Classification", "Class", "TE_type", "Type", "Superfamily", "Family")
FAMILY_KEYS = ("Superfamily", "Family", "Classification")

# EDTA's whole-genome annotation also carries features it hit by homology to the
# library that are not transposons at all -- rRNA genes above all.  A TE table
# must not claim them, so they are dropped rather than labelled.
NON_TE_TYPES = {"rRNA_gene", "tRNA_gene", "snRNA_gene", "ncRNA_gene", "gene", "mRNA"}

# precedence when several genes/features overlap the same TE
CAT_RANK = {"exon": 0, "5UTR": 1, "3UTR": 2, "intron": 3, "gene_body": 4}


def opener(path):
    return gzip.open(path, "rt") if path.endswith(".gz") else open(path)


def iter_gff(path):
    with opener(path) as fh:
        for line in fh:
            if not line or line[0] == "#":
                continue
            f = line.rstrip("\n").split("\t")
            if len(f) >= 9:
                yield f


def attrs(field):
    d = {}
    for kv in field.split(";"):
        k, _, v = kv.strip().partition("=")
        if k:
            d[k] = v
    return d


# --------------------------------------------------------------------------
class IntervalIndex:
    """Intervals sorted by start with a prefix-max of end.

    Correct for overlapping intervals (a naive 'nearest start' lookup is not):
    query() walks back only while some interval in the prefix still reaches the
    query, so it never misses a long interval that starts far to the left.
    """

    __slots__ = ("rows", "starts", "maxend", "argmax")

    def __init__(self, rows):
        rows = sorted(rows, key=lambda r: r[0])
        self.rows, starts, maxend, argmax = rows, [], [], []
        m, best, bi = float("-inf"), float("-inf"), -1
        for i, r in enumerate(rows):
            starts.append(r[0])
            if r[1] > m:
                m = r[1]
            maxend.append(m)
            if r[1] > best:
                best, bi = r[1], i
            argmax.append(bi)
        self.starts, self.maxend, self.argmax = starts, maxend, argmax

    def overlap(self, s, e):
        rows, starts, maxend = self.rows, self.starts, self.maxend
        out = []
        i = bisect_right(starts, e) - 1
        while i >= 0 and maxend[i] >= s:
            if rows[i][1] >= s:
                out.append(rows[i])
            i -= 1
        return out

    def nearest(self, s, e):
        """(row, distance) — distance 0 when the intervals overlap."""
        rows, starts = self.rows, self.starts
        n = len(rows)
        if n == 0:
            return None, None
        j = bisect_left(starts, s)
        if j < n and starts[j] <= e:
            return rows[j], 0
        best, bd = None, None
        if j > 0:
            k = self.argmax[j - 1]
            bd = max(0, s - rows[k][1])
            best = rows[k]
        if j < n:
            d = max(0, starts[j] - e)
            if bd is None or d < bd:
                best, bd = rows[j], d
        return best, bd


EMPTY = IntervalIndex([])


# --------------------------------------------------------------------------
def load_idmap(path):
    m = {}
    with open(path) as fh:
        for line in fh:
            a, _, b = line.rstrip("\n").partition("\t")
            if a and b:
                m[a] = b
    return m


def genome_lengths(fasta):
    lens, name, n = {}, None, 0
    with open(fasta) as fh:
        for line in fh:
            if line[0] == ">":
                if name is not None:
                    lens[name] = n
                name, n = line[1:].split()[0], 0
            else:
                n += len(line) - 1
    if name is not None:
        lens[name] = n
    return lens


def reconcile(gff_len, assembly_len):
    """GFF sequence ID -> assembly sequence name.

    Exact name first, then unique sequence length: an NCBI GFF may label a
    chromosome NC_138145.1 while the FASTA calls it chr1.
    """
    out, used = {}, set()
    for g in gff_len:
        if g in assembly_len:
            out[g] = g
            used.add(g)
    by_len = defaultdict(list)
    for s, L in assembly_len.items():
        by_len[L].append(s)
    for g, L in gff_len.items():
        # L is None for a GFF with no `region` features: that seqid can only be
        # matched by name, never by length.
        if g in out or L is None:
            continue
        cand = [s for s in by_len.get(L, []) if s not in used]
        if len(cand) == 1:
            out[g] = cand[0]
            used.add(cand[0])
    return out


# --------------------------------------------------------------------------
def build_gene_model(gff, seqmap):
    """(genes, biotype, gene_ix, cds_ix, utr5_ix, utr3_ix, intron_ix)."""
    genes, biotype = {}, {}
    tx_parent, tx_seq, tx_span = {}, {}, {}
    exons_by_tx, cds_by_tx = defaultdict(list), defaultdict(list)
    utr5, utr3 = [], []

    for f in iter_gff(gff):
        t, a = f[2], attrs(f[8])
        if t == "gene":
            gid = a.get("ID") or f"{f[0]}:{f[3]}-{f[4]}"
            genes[gid] = (f[0], int(f[3]), int(f[4]), f[6])
            biotype[gid] = a.get("gene_biotype", a.get("biotype", ""))
        elif t in TRANSCRIPT_TYPES:
            tid = a.get("ID")
            if tid:
                tx_parent[tid] = a.get("Parent", a.get("gene_id", ""))
                tx_seq[tid] = f[0]
                tx_span[tid] = (int(f[3]), int(f[4]), f[6])
        elif t == "exon":
            tid = a.get("Parent", "").split(",")[0]
            exons_by_tx[tid].append((int(f[3]), int(f[4])))
        elif t == "CDS":
            tid = a.get("Parent", "").split(",")[0]
            cds_by_tx[tid].append((int(f[3]), int(f[4])))
        elif t in ("five_prime_UTR", "5'-UTR", "5UTR"):
            tid = a.get("Parent", "").split(",")[0]
            utr5.append((f[0], int(f[3]), int(f[4]), tid))
        elif t in ("three_prime_UTR", "3'-UTR", "3UTR"):
            tid = a.get("Parent", "").split(",")[0]
            utr3.append((f[0], int(f[3]), int(f[4]), tid))

    # GFFs with no `gene` feature (some MAKER/EVM output): the transcript's
    # Parent is the gene, so synthesise the gene span from its transcripts.
    parent_ids = {p for p in tx_parent.values() if p}
    missing = parent_ids - set(genes)
    if missing:
        span = {}
        for tid, p in tx_parent.items():
            if p in missing:
                s, e, strand = tx_span[tid]
                v = span.setdefault(p, [tx_seq[tid], s, e, strand])
                v[1], v[2] = min(v[1], s), max(v[2], e)
        genes.update({g: tuple(v) for g, v in span.items()})

    # EVM output (Aurelia coerulea) is flatter still: `mRNA ... ID=evm.model.
    # ptg000002l.1` with no `gene` feature and no Parent either, so the block
    # above finds nothing to synthesise and the species loads zero genes --
    # every TE then reads as region=NA.  With no gene feature anywhere, the
    # transcript is its own gene.
    if not genes:
        for tid, (s, e, strand) in tx_span.items():
            genes[tid] = (tx_seq[tid], s, e, strand)

    def gene_of(tid):
        p = tx_parent.get(tid, "")
        seen = 0
        while p and p in tx_parent and seen < 12:
            p = tx_parent[p]
            seen += 1
        if p:
            return p
        # Parentless transcript: its own gene, if that is how `genes` was built.
        return tid if tid in genes else None

    gene_exons, gene_cds = defaultdict(list), defaultdict(list)
    for tid, ex in exons_by_tx.items():
        g = gene_of(tid)
        if g:
            gene_exons[g].extend(ex)
    for tid, cd in cds_by_tx.items():
        g = gene_of(tid)
        if g:
            gene_cds[g].extend(cd)

    gene_ix = defaultdict(list)
    cds_ix = defaultdict(list)
    intron_ix = defaultdict(list)
    utr5_ix = defaultdict(list)
    utr3_ix = defaultdict(list)

    for gid, (seq, s, e, strand) in genes.items():
        asm = seqmap.get(seq)
        if not asm:
            continue
        gene_ix[asm].append((s, e, gid))
        for cs, ce in gene_cds.get(gid, ()):
            cds_ix[asm].append((cs, ce, gid))
        ex = sorted(set(gene_exons.get(gid, ())))
        for (s1, e1), (s2, _) in zip(ex, ex[1:]):
            if s2 - e1 > 1:
                intron_ix[asm].append((e1 + 1, s2 - 1, gid))
    for rec, ix in ((utr5, utr5_ix), (utr3, utr3_ix)):
        for seq, s, e, tid in rec:
            asm = seqmap.get(seq)
            g = gene_of(tid)
            if asm and g:
                ix[asm].append((s, e, g))

    mk = lambda d: {k: IntervalIndex(v) for k, v in d.items()}
    return genes, biotype, mk(gene_ix), mk(cds_ix), mk(utr5_ix), mk(utr3_ix), mk(intron_ix)


# --------------------------------------------------------------------------
def edta_anno_file(work, sp, name):
    """Resolve an --anno-stage output.

    EDTA.pl chdir's into <genome>.EDTA.anno/ for the annotation stage and leaves
    TEanno.gff3 in there; only the masked genome is copied back to the parent.
    Falling back to the parent keeps this working if a future EDTA copies it up.
    """
    sub = os.path.join(work, f"{sp}.renamed.fa.mod.EDTA.anno", name)
    top = os.path.join(work, name)
    if os.path.exists(sub):
        return sub
    return top if os.path.exists(top) else sub


def main():
    sp = sys.argv[1]
    work = os.path.join(TE_WORK, sp)
    outdir = os.path.join(TE_ROOT, "results", "TE_info", sp)
    os.makedirs(outdir, exist_ok=True)

    te_gff = edta_anno_file(work, sp, f"{sp}.renamed.fa.mod.EDTA.TEanno.gff3")
    if not os.path.exists(te_gff):
        sys.exit(f"missing {te_gff}")
    idmap = load_idmap(os.path.join(work, f"{sp}.idmap.tsv"))

    # renamed-space and original-space lengths
    ren_len = genome_lengths(os.path.join(work, f"{sp}.renamed.fa"))
    assembly_len = {idmap[k]: v for k, v in ren_len.items() if k in idmap}

    gff_file, gff_kind = "", "none"
    with open(os.path.join(TE_ROOT, "TE_pipeline", "config", "species_manifest.tsv")) as fh:
        next(fh)
        for line in fh:
            p = line.rstrip("\n").split("\t")
            if p[0] == sp:
                gff_file, gff_kind = p[2], p[3]
                break

    if gff_kind == "genomic" and gff_file:
        gff_path = os.path.join(TE_ROOT, gff_file)
        # `region` features carry an authoritative sequence length.  Some GFFs
        # carry none at all -- the Acropora, Actinoscyphia, Aurelia and
        # Siderastrea gene models among them.  Keying off `region` alone left
        # this dict empty, so reconcile() mapped nothing, build_gene_model()
        # then dropped every gene for want of a seqmap entry, and the whole
        # species came out with related_gene empty and region="intergenic
        # region" for 100% of its TEs (30 of the 65 species, measured
        # 2026-09-26).  Fall back to the seqids the features themselves use.
        # Their length is deliberately None: a feature's max end is a lower
        # bound, not the sequence length, and a wrong length would let
        # reconcile()'s length fallback mis-map a gene onto another scaffold.
        gff_len = {f[0]: int(f[4]) for f in iter_gff(gff_path) if f[2] == "region"}
        if not gff_len:
            for f in iter_gff(gff_path):
                gff_len.setdefault(f[0], None)
        seqmap = reconcile(gff_len, assembly_len)
        genes, biotype, gene_ix, cds_ix, utr5_ix, utr3_ix, intron_ix = \
            build_gene_model(gff_path, seqmap)
    else:
        genes, biotype = {}, {}
        gene_ix = cds_ix = utr5_ix = utr3_ix = intron_ix = {}

    # genes[g] is (seq, start, end, strand).  On the minus strand the transcript
    # starts at the HIGH coordinate, so the TSS is `end`, not `start`.
    tss = {g: (v[2] if v[3] == "-" else v[1]) for g, v in genes.items()}

    counts = defaultdict(int)
    n = 0
    with open(os.path.join(outdir, f"{sp}.TE_info.tsv"), "w") as fo, \
         gzip.open(os.path.join(outdir, f"{sp}.TE_info.extended.tsv.gz"), "wt") as fe:
        fo.write("species\tTE_id\tscaffold\tTE_start\tTE_end\trelated_gene\tregion\tTE_type\n")
        fe.write("species\tTE_id\tscaffold\tTE_start\tTE_end\tstrand\tTE_length\t"
                 "related_gene\tregion\tgene_distance\tgene_strand\tgene_biotype\t"
                 "TE_type\tTE_family\trepeat_name\tedta_id\n")

        for f in iter_gff(te_gff):
            seq = idmap.get(f[0])
            if not seq:
                continue
            a = attrs(f[8])
            if f[2] in NON_TE_TYPES or a.get("classification", "").startswith("rRNA"):
                continue
            s, e = int(f[3]), int(f[4])
            n += 1
            teid = f"TE_{n:08d}"
            # EDTA writes its classification as the gff3 *feature type*
            # ("hAT_TIR_transposon", "CACTA_TIR_transposon", ...) -- the same
            # style the deliverable asks for -- and repeats it as a lowercase
            # `classification=` attribute in a different vocabulary
            # ("DNA/hAT-Charlie").  Prefer the feature type.  Never fall back to
            # Name= : that is the library sequence id (TE_00002079), not a class.
            ttype = (f[2] or next((a[k] for k in CLASS_KEYS if a.get(k)), None)
                     or a.get("classification") or "unknown")
            tfamily = (next((a[k] for k in FAMILY_KEYS if a.get(k)), "")
                       or a.get("classification", ""))
            counts[ttype] += 1

            gi = gene_ix.get(seq, EMPTY)
            # Without a gene annotation we cannot say a TE is intergenic -- that
            # would assert there is no gene there.  Say NA instead, so the
            # column never claims more than we know.
            related, region, dist = None, ("intergenic region" if genes else "NA"), None
            hit = gi.overlap(s, e)

            if hit:
                ov = {g for _, _, g in hit}
                cat = {g: "gene_body" for g in ov}   # default for un-annotated genes
                # applied lowest -> highest precedence, so the last write wins
                for key, ix in (("intron", intron_ix), ("3UTR", utr3_ix),
                                ("5UTR", utr5_ix), ("exon", cds_ix)):
                    for _, _, g in ix.get(seq, EMPTY).overlap(s, e):
                        if g in ov:
                            cat[g] = key
                related = min(cat, key=lambda g: (CAT_RANK[cat[g]], -(genes[g][2] - genes[g][1])))
                region, dist = cat[related], 0
            else:
                row, d = gi.nearest(s, e)
                if row is not None:
                    related, dist = row[2], d
                    t, gstrand = tss.get(related), genes[related][3]
                    if t is not None:
                        lo, hi = (t - PROM_DOWN, t + PROM_UP) if gstrand == "-" \
                            else (t - PROM_UP, t + PROM_DOWN)
                        if e >= lo and s <= hi:
                            region = "promoter"

            fo.write(f"{sp}\t{teid}\t{seq}\t{s}\t{e}\t{related or ''}\t{region}\t{ttype}\n")
            fe.write(f"{sp}\t{teid}\t{seq}\t{s}\t{e}\t{f[6]}\t{e - s + 1}\t"
                     f"{related or ''}\t{region}\t{dist if dist is not None else ''}\t"
                     f"{genes[related][3] if related else ''}\t"
                     f"{biotype.get(related, '') if related else ''}\t"
                     f"{ttype}\t{tfamily}\t{a.get('Name', '')}\t{a.get('ID', '')}\n")

    with open(os.path.join(outdir, f"{sp}.TE_summary.tsv"), "w") as fs:
        fs.write("species\tTE_type\tn_TE\n")
        for k, v in sorted(counts.items(), key=lambda x: -x[1]):
            fs.write(f"{sp}\t{k}\t{v}\n")
    print(f"{sp}: {n} TEs, {len(genes)} genes  (gene annotation: {gff_file or 'NONE'})")


if __name__ == "__main__":
    main()
