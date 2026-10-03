#!/usr/bin/env python3
"""
Build a per-species TE information table from an EDTA TE annotation GFF3
and the species' gene annotation GFF3.

Output columns (tab separated):
    species  TE_id  scaffold  TE_start  TE_end  related_gene  region  TE_type

`region` is one of, in decreasing priority:
    exon       TE overlaps >=1 exon of a gene
    intron     TE lies inside a gene span and touches no exon
    promoter   TE lies within --promoter bp upstream of a gene TSS (strand aware)
               and does not overlap the gene body
    intergenic none of the above; the nearest gene by distance is reported

`related_gene` is the gene ID of the assigned gene. For intergenic TEs it is the
closest gene, so every row carries a gene and a distance-annotated region.

EDTA truncates sequence IDs longer than 13 characters down to their first 13
characters (EDTA.pl `id_mode 1`). Those truncated names are what appear in
*.TEanno.gff3, while the gene GFF3 keeps the original names. This script
reconstructs the EDTA name transform from the ORIGINAL genome FASTA
(--genome) and translates TE scaffold names back to the originals.
"""

import argparse
import glob
import gzip
import os
import re
import sys
from bisect import bisect_left, bisect_right
from collections import defaultdict

ID_LEN_MAX = 13


# ---------------------------------------------------------------- I/O helpers

def fopen(path):
    """Open plain or gzipped text."""
    if path.endswith(".gz"):
        return gzip.open(path, "rt")
    return open(path, "rt")


def parse_attrs(field):
    """Parse a GFF3 attribute column into a dict, keyed in lower case.

    EDTA writes `classification=` (lower case) while other tools write
    `Classification=`, so lookups are made case insensitive."""
    attrs = {}
    if not field or field == ".":
        return attrs
    for item in field.strip().rstrip(";").split(";"):
        item = item.strip()
        if not item:
            continue
        if "=" in item:
            k, v = item.split("=", 1)
        elif " " in item:
            k, v = item.split(" ", 1)
        else:
            continue
        attrs[k.strip().lower()] = v.strip()
    return attrs


# ------------------------------------------------- EDTA seq-ID reconstruction

# Exactly the character class EDTA.pl substitutes with "_"
_SPECIAL = re.compile(r'[\~!@#\$%\^&\*\(\)\+\-\=\?\[\]\{\}\:;",<\/\\\|]+')


def edta_seqid(header):
    """Replicate EDTA.pl's sequence-ID transform: take the first whitespace
    token, replace special characters with _, collapse repeats, then truncate
    to ID_LEN_MAX characters."""
    name = header.split()[0]
    name = _SPECIAL.sub("_", name)
    name = re.sub(r"_+", "_", name)
    return name[:ID_LEN_MAX]


def build_seqid_map(genome_fa):
    """Return {edta_name: original_name} from the original genome FASTA."""
    m = {}
    dup = defaultdict(list)
    with fopen(genome_fa) as fh:
        for line in fh:
            if line.startswith(">"):
                orig = line[1:].strip().split()[0]
                m[edta_seqid(orig)] = orig
                dup[edta_seqid(orig)].append(orig)
    clashes = {k: v for k, v in dup.items() if len(v) > 1}
    if clashes:
        sys.stderr.write(
            "WARNING: %d EDTA names map to >1 original scaffold; "
            "keeping the first. Example: %s\n"
            % (len(clashes), list(clashes.items())[0]))
    return m


def load_id_map(path):
    """Return {renamed_id: original_id} from 02_rename_genome.py's TSV."""
    m = {}
    with open(path) as fh:
        for line in fh:
            if not line.strip() or line.startswith("#"):
                continue
            f = line.rstrip("\n").split("\t")
            if len(f) >= 2:
                m[f[0]] = f[1]
    return m


# --------------------------------------------------------- interval indexing

# Transcript-id suffixes that unambiguously mark a transcript within a gene.
# Deliberately conservative: a generic trailing ".<digits>" is NOT stripped,
# because EVM-style ids such as "evm.model.ptg000002l.1" and "...l.2" are
# separate single-transcript genes, not isoforms of one gene.
_TX_SUFFIX = (re.compile(r"[._-]t\d+$"),          # ...g24100.t1 -> ...g24100
              re.compile(r"[._-]mrna\d+$", re.I))


def derive_gene_id(tid):
    """Derive a gene id from a transcript id, for annotations that carry no
    gene feature and no Parent/geneID attribute.  Falls back to the transcript
    id itself, which is always a valid (if not merged) gene identifier."""
    for pat in _TX_SUFFIX:
        m = pat.search(tid)
        if m and m.start() > 0:
            return tid[:m.start()]
    return tid


class Gene:
    __slots__ = ("gid", "chrom", "start", "end", "strand", "exons")

    def __init__(self, gid, chrom, start, end, strand):
        self.gid = gid
        self.chrom = chrom
        self.start = start
        self.end = end
        self.strand = strand
        self.exons = []


class GeneIndex:
    """Per-chromosome array of genes sorted by start, augmented with a prefix
    maximum of gene ends so overlap queries prune correctly."""

    def __init__(self):
        self.by_chrom = defaultdict(list)   # chrom -> [Gene] sorted by start
        self._starts = {}                   # chrom -> [start]
        self._maxend = {}                   # chrom -> prefix max of ends

    def load(self, gff_path):
        """Load genes from a gene annotation GFF3.

        Handles the formats present in this dataset:

          * NCBI / Ensembl  : gene > mRNA/transcript > exon, CDS
          * AUGUSTUS-MAKER  : gene > transcript > exon, CDS, intron, UTR
          * Hydractinia     : transcript only, gene id in `geneID=`
          * Pocillopora     : transcript only, no gene id -> derived from the
                              transcript id (".g24100.t1" -> ".g24100")
          * Aurelia/EVM     : mRNA + CDS only, NO exon features -> CDS are used
                              as the exon proxy
        """
        gene_feat = {}      # gid -> (chrom, start, end, strand)
        tx = {}             # tid -> (chrom, start, end, strand, parent, geneid)
        tx_exons = defaultdict(list)
        tx_cds = defaultdict(list)

        with fopen(gff_path) as fh:
            for line in fh:
                if not line or line[0] == "#":
                    continue
                f = line.rstrip("\n").split("\t")
                if len(f) < 9:
                    continue
                chrom, _, ftype, start, end, _, strand, _, attr = f
                ft = ftype.lower()

                if ft in ("gene", "pseudogene"):
                    a = parse_attrs(attr)
                    gid = a.get("id") or a.get("name") or a.get("gene_id")
                    if gid and gid not in gene_feat:
                        gene_feat[gid.split(":")[-1]] = (
                            chrom, int(start), int(end), strand)

                elif ft in ("mrna", "transcript", "lnc_rna", "ncrna", "trna",
                            "rrna", "snrna", "mirna", "scrna", "cdna"):
                    a = parse_attrs(attr)
                    tid = a.get("id")
                    if not tid:
                        continue
                    tx[tid] = (chrom, int(start), int(end), strand,
                               (a.get("parent") or "").split(",")[0],
                               a.get("geneid") or a.get("gene_id") or "")

                elif ft == "exon":
                    a = parse_attrs(attr)
                    p = a.get("parent")
                    if p:
                        tx_exons[p.split(",")[0]].append(
                            (int(start), int(end)))

                elif ft == "cds":
                    a = parse_attrs(attr)
                    p = a.get("parent")
                    if p:
                        tx_cds[p.split(",")[0]].append(
                            (int(start), int(end)))

        # --- build the gene set -------------------------------------------
        genes = {}
        for gid, (chrom, s, e, strand) in gene_feat.items():
            genes[gid] = Gene(gid, chrom, s, e, strand)
        synthesized = set()

        tx2gid = {}
        for tid, (chrom, s, e, strand, parent, geneid) in tx.items():
            gid = parent or geneid or derive_gene_id(tid)
            g = genes.get(gid)
            if g is not None and g.chrom != chrom:
                # Some annotations number genes per scaffold ("g1", "g2", ...).
                # Qualify the id so genes on different scaffolds never merge.
                gid = "%s:%s" % (chrom, gid)
                g = genes.get(gid)
            tx2gid[tid] = gid
            if g is not None and gid not in synthesized:
                continue                     # explicit gene span wins
            if g is None:
                genes[gid] = Gene(gid, chrom, s, e, strand)
                synthesized.add(gid)
            else:                            # widen a synthesized span
                g.start = min(g.start, s)
                g.end = max(g.end, e)

        # --- attach exons, falling back to CDS ----------------------------
        # Every isoform's exons are attached, so a TE in a region that is
        # intronic in one isoform but exonic in another is reported as exonic.
        n_exon_tx = 0
        has_exon = set()
        for tid, xl in tx_exons.items():
            gid = tx2gid.get(tid)
            if gid is not None and gid in genes:
                genes[gid].exons.extend(xl)
                has_exon.add(gid)
                n_exon_tx += 1

        # Genes for which NO isoform provided exons fall back to CDS. All their
        # transcripts' CDS are added, not just the first one.
        n_cds_tx = 0
        for tid, cl in tx_cds.items():
            gid = tx2gid.get(tid)
            if gid is not None and gid in genes and gid not in has_exon:
                genes[gid].exons.extend(cl)
                n_cds_tx += 1

        for g in genes.values():
            g.exons.sort()

        if n_exon_tx == 0 and n_cds_tx:
            sys.stderr.write("  no exon features in this GFF3; used CDS of "
                             "%d transcripts as the exon proxy\n" % n_cds_tx)
        elif n_cds_tx:
            sys.stderr.write("  %d transcripts had no exon, used their CDS\n"
                             % n_cds_tx)

        n_no_span = sum(1 for g in genes.values() if not g.exons)
        if n_no_span:
            sys.stderr.write("  NOTE: %d genes have no exon/CDS annotation\n"
                             % n_no_span)

        for g in genes.values():
            self.by_chrom[g.chrom].append(g)

        for chrom, glist in self.by_chrom.items():
            glist.sort(key=lambda x: (x.start, x.end))
            starts = [g.start for g in glist]
            mx, run = [], -1
            for g in glist:
                run = max(run, g.end)
                mx.append(run)
            self._starts[chrom] = starts
            self._maxend[chrom] = mx
        return self

    # -- queries -----------------------------------------------------------
    def overlaps(self, chrom, s, e):
        glist = self.by_chrom.get(chrom)
        if not glist:
            return []
        starts, mx = self._starts[chrom], self._maxend[chrom]
        out = []
        i = bisect_right(starts, e) - 1
        while i >= 0 and mx[i] >= s:
            g = glist[i]
            if g.end >= s:
                out.append(g)
            i -= 1
        return out

    def nearest(self, chrom, s, e, exclude=frozenset()):
        """Gene with minimal distance to [s, e]. Genes are normally
        non-overlapping, so scanning a small window around the insertion point
        is sufficient; a wider window is used as a safety net."""
        glist = self.by_chrom.get(chrom)
        if not glist:
            return None, None
        starts = self._starts[chrom]
        i = bisect_left(starts, s)
        cand = []
        for w in (4, 40):
            lo, hi = max(0, i - w), min(len(glist), bisect_right(starts, e) + w)
            cand = glist[lo:hi]
            if any(g.end >= s for g in cand) or len(cand) == len(glist):
                break
        best, best_d = None, None
        for g in cand:
            if g.gid in exclude:
                continue
            if g.end < s:
                d = s - g.end
            elif g.start > e:
                d = g.start - e
            else:
                d = 0
            if best_d is None or d < best_d:
                best, best_d = g, d
        return best, best_d


# ---------------------------------------------------------------- TE parsing

# Feature types that are TE records even when they are not a known SO name
# (RepeatMasker output, hand-written GFF3, older EDTA builds).
_GENERIC_TE_TYPES = {
    "te", "transposable_element", "transposon", "retrotransposon",
    "repeat_region", "repeat_fragment", "match", "similarity",
}

# Last-resort shape test on the feature type.
_TE_FTYPE_RE = re.compile(
    r"(transposon|retrotransposon|helitron|^line|^sine|^mite|^trim|^rc_)", re.I)


def parse_te_gff(path, idmap, so_names=frozenset()):
    """Yield (chrom, start, end, te_id, classification, so_id, ftype) from an
    EDTA *.TEanno.gff3 (or a RepeatMasker .gff).

    EDTA puts the **canonical SO name itself** in column 3, not a generic
    "TE_homo"/"TE_intact" marker:

        Chr2  EDTA  CACTA_TIR_transposon  39311  39451  .  .  .  \\
              ID=TE_struc_6;Name=TE_00000010;classification=MITE/DTM;\\
              sequence_ontology=SO:0002280;identity=0.821;method=structural

        Chr2  EDTA  Copia_LTR_retrotransposon  ...  \\
              ID=TE_homo_0;Name=TE_00000123_LTR;classification=LTR/Copia;\\
              sequence_ontology=SO:0002264;method=homology

    so the column-3 value is accepted when it is a known SO name from EDTA's
    TE_Sequence_Ontology.txt, with a generic fallback for other producers.

    Scaffold names are translated back to the original genome names via idmap
    because EDTA truncates sequence IDs to 13 characters.
    """
    unknown = defaultdict(int)
    seen_types = defaultdict(int)
    with fopen(path) as fh:
        for line in fh:
            if not line or line[0] == "#":
                continue
            f = line.rstrip("\n").split("\t")
            if len(f) < 9:
                continue
            chrom, _, ftype, start, end, _, _, _, attr = f
            ft = ftype.strip()
            ftl = ft.lower()
            if not (ftl in so_names or ftl.startswith("te_") or
                    ftl in _GENERIC_TE_TYPES or _TE_FTYPE_RE.search(ftl)):
                continue
            seen_types[ft] += 1
            a = parse_attrs(attr)

            cls = (a.get("classification") or a.get("class") or
                   a.get("type") or "")
            if not cls and "target" in a:
                cls = a["target"].split()[0].split(":")[-1]
            so_id = a.get("sequence_ontology", "")

            tid = (a.get("id") or a.get("name") or
                   "%s:%s-%s" % (chrom, start, end))

            orig = idmap.get(chrom)
            if orig is None:
                unknown[chrom] += 1
                orig = chrom          # already original (short IDs)
            yield orig, int(start), int(end), tid, cls, so_id, ft

    if seen_types:
        top = sorted(seen_types.items(), key=lambda kv: -kv[1])
        sys.stderr.write("  GFF feature types: %s\n"
                         % ", ".join("%s=%d" % kv for kv in top[:8]))

    if unknown:
        sys.stderr.write("  WARNING: %d TE records on %d scaffolds had no "
                         "original-name mapping (kept as-is)\n"
                         % (sum(unknown.values()), len(unknown)))


# ------------------------------------------------------------ type normalise

def find_so_file():
    """Locate EDTA's TE_Sequence_Ontology.txt inside the active environment."""
    pats = [
        os.path.join(sys.prefix, "share/EDTA/bin/TE_Sequence_Ontology.txt"),
        os.path.join(os.path.dirname(os.path.abspath(__file__)),
                     "TE_Sequence_Ontology.txt"),
    ]
    for p in pats:
        if os.path.exists(p):
            return p
    for p in glob.glob("/opt/*/envs/*/share/EDTA/bin/TE_Sequence_Ontology.txt"):
        return p
    for p in glob.glob(os.path.expanduser(
            "~/miniconda3/envs/*/share/EDTA/bin/TE_Sequence_Ontology.txt")):
        return p
    return None


def load_so_ontology(path):
    """Parse EDTA's sequence-ontology table into {alias_lower: SO_name}.

    The file is  SO_name <tab> SO_id <tab> alias1,alias2,...  and lets us turn
    EDTA's raw class (e.g. "TIR/CACTA", "LINE/L2") into the canonical SO name
    ("CACTA_TIR_transposon", "L2_LINE_retrotransposon") -- the same mapping
    EDTA itself uses when writing its GFF3."""
    alias = {}
    soid = {}
    names = set()
    if not path or not os.path.exists(path):
        return alias, soid, names
    with open(path) as fh:
        for line in fh:
            if line.startswith("#") or not line.strip():
                continue
            parts = line.rstrip("\n").split("\t")
            if len(parts) < 2:
                parts = line.split(None, 2)
            if len(parts) < 2:
                continue
            so_name, so_id = parts[0].strip(), parts[1].strip()
            soid[so_id] = so_name
            alias[so_name.lower()] = so_name
            names.add(so_name.lower())
            if len(parts) > 2:
                for a in parts[2].split(","):
                    a = a.strip()
                    if a:
                        alias[a.lower()] = so_name
    return alias, soid, names


# Fallback rules, used only if the SO ontology file cannot be found.
_CLASS_RULES = [
    (re.compile(r"^LTR[/_](Gypsy|Copia)", re.I),
     lambda m: "LTR/%s_retrotransposon" % m.group(1).capitalize()),
    (re.compile(r"^LTR", re.I), lambda m: "LTR_retrotransposon"),
    (re.compile(r"^LINE[/_]([A-Za-z0-9]+)", re.I),
     lambda m: "%s_LINE_retrotransposon" % m.group(1).upper()),
    (re.compile(r"^LINE", re.I), lambda m: "LINE_retrotransposon"),
    (re.compile(r"^SINE", re.I), lambda m: "SINE_retrotransposon"),
    (re.compile(r"^TIR[/_]([A-Za-z0-9]+)", re.I),
     lambda m: "%s_TIR_transposon" % m.group(1)),
    (re.compile(r"^MITE", re.I), lambda m: "MITE"),
    (re.compile(r"^TIR", re.I), lambda m: "TIR_transposon"),
    (re.compile(r"helitron", re.I), lambda m: "helitron"),
    (re.compile(r"^RC", re.I), lambda m: "RC_helitron"),
    (re.compile(r"^DNA", re.I), lambda m: "DNA_transposon"),
    (re.compile(r"^satellite", re.I), lambda m: "satellite"),
    (re.compile(r"^(tRNA|rRNA|snRNA|srpRNA|RNA)", re.I), lambda m: "RNA"),
]


def normalise_type(cls, so_id, alias_map, soid_map, ftype="", so_names=frozenset()):
    """Map an EDTA raw classification to its canonical TE type name.

    EDTA's column-3 feature type is already the canonical SO name, so it wins
    whenever it is a real ontology term -- except `repeat_fragment`, which is
    a catch-all and is better described by the `classification` attribute.
    """
    if ftype:
        ftl = ftype.strip().lower()
        if ftl in so_names and ftl != "repeat_fragment":
            return ftype.strip()
    if cls:
        hit = alias_map.get(cls.lower())
        if hit:
            return hit
    if so_id:
        hit = soid_map.get(so_id)
        if hit:
            return hit
    if not cls:
        return "unknown"
    for pat, fn in _CLASS_RULES:
        m = pat.search(cls)
        if m:
            return fn(m)
    return cls


# ------------------------------------------------------------------ classify

def classify(te_s, te_e, ov, near, promoter):
    """Return (gene_id, region) for a TE.

    `ov`   genes whose span overlaps the TE
    `near` genes within `promoter` bp of the TE (a superset of `ov`) -- a
           promoter TE does not overlap its gene, so the promoter test has to
           look at the surrounding genes, not just the overlapping ones.
    """
    best_gene, best_d = None, None
    for g in near:
        if g.end < te_s:
            d = te_s - g.end
        elif g.start > te_e:
            d = g.start - te_e
        else:
            d = 0
        if best_d is None or d < best_d:
            best_gene, best_d = g, d

    if ov:
        # 1. exon overlap -- pick the gene with the largest exonic overlap
        exon_gene, exon_ov = None, 0
        for g in ov:
            for xs, xe in g.exons:
                o = min(xe, te_e) - max(xs, te_s) + 1
                if o > exon_ov:
                    exon_ov, exon_gene = o, g
        if exon_gene is not None:
            return exon_gene.gid, "exon"

        # 2. fully inside a gene body, touching no exon -> intron
        inside = [g for g in ov if g.start <= te_s and te_e <= g.end]
        if inside:
            inside.sort(key=lambda g: g.end - g.start)
            return inside[0].gid, "intron"

    # 3. promoter: within `promoter` bp upstream of a TSS, outside the body.
    #    The TSS is the gene start on the + strand and the gene end on the
    #    - strand, so the promoter lies BEFORE the gene on + and AFTER it on -.
    #    Choose the closest such gene.
    best_prom, best_prom_d = None, None
    for g in near:
        if g.strand == "-":
            if te_s > g.end:                 # TE sits past the gene end
                d = te_s - g.end
            else:
                continue
        else:                                # + or unknown strand
            if te_e < g.start:               # TE sits before the gene start
                d = g.start - te_e
            else:
                continue
        if d <= promoter and (best_prom_d is None or d < best_prom_d):
            best_prom, best_prom_d = g, d
    if best_prom is not None:
        return best_prom.gid, "promoter"

    # 4. otherwise intergenic, reported against the nearest gene
    if best_gene is not None:
        return best_gene.gid, "intergenic"
    return None, "intergenic"


# ----------------------------------------------------------------------- main

def main():
    ap = argparse.ArgumentParser(
        description=__doc__,
        formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--species", required=True)
    ap.add_argument("--te-gff", required=True, help="EDTA *.TEanno.gff3 (.gz ok)")
    ap.add_argument("--gene-gff", required=True, help="gene annotation GFF3 (.gz ok)")
    ap.add_argument("--genome", required=True,
                    help="ORIGINAL genome FASTA, used to rebuild EDTA's "
                         "truncated scaffold names")
    ap.add_argument("--out", required=True)
    ap.add_argument("--promoter", type=int, default=2000,
                    help="promoter window upstream of TSS (bp). default 2000")
    ap.add_argument("--min-te-len", type=int, default=0)
    ap.add_argument("--so-file", default=None,
                    help="path to EDTA's TE_Sequence_Ontology.txt "
                         "(auto-detected from the active environment)")
    ap.add_argument("--id-map", default=None,
                    help="TSV from 02_rename_genome.py mapping the genome's "
                         "EDTA-safe IDs back to the original scaffold names. "
                         "When given it replaces the name-truncation guesswork.")
    args = ap.parse_args()

    so_path = args.so_file or find_so_file()
    alias_map, soid_map, so_names = load_so_ontology(so_path)
    sys.stderr.write("[%s] TE ontology: %s (%d aliases, %d SO names)\n"
                     % (args.species, so_path or "NOT FOUND - using fallback",
                        len(alias_map), len(so_names)))

    if args.id_map:
        # Preferred path: the genome was renamed before EDTA, so the TE GFF3
        # carries the synthetic IDs and the map is exact.
        idmap = load_id_map(args.id_map)
        sys.stderr.write("[%s] scaffold name map: %d entries (from --id-map)\n"
                         % (args.species, len(idmap)))
    else:
        idmap = build_seqid_map(args.genome)
        sys.stderr.write("[%s] scaffold name map: %d entries "
                         "(rebuilt from EDTA truncation)\n"
                         % (args.species, len(idmap)))

    idx = GeneIndex().load(args.gene_gff)
    n_genes = sum(len(v) for v in idx.by_chrom.values())
    sys.stderr.write("[%s] %d genes loaded\n" % (args.species, n_genes))

    counts = defaultdict(int)
    types = defaultdict(int)
    n = 0
    with open(args.out, "w") as out:
        out.write("species\tTE_id\tscaffold\tTE_start\tTE_end\t"
                  "related_gene\tregion\tTE_type\n")
        for chrom, s, e, tid, cls, so_id, ftype in parse_te_gff(
                args.te_gff, idmap, so_names):
            if e - s + 1 < args.min_te_len:
                continue
            ov = idx.overlaps(chrom, s, e)
            near = idx.overlaps(chrom, s - args.promoter, e + args.promoter)
            gene, region = classify(s, e, ov, near, args.promoter)
            if gene is None:
                g, _d = idx.nearest(chrom, s, e)
                gene = g.gid if g is not None else "NA"
                region = "intergenic"
            te_type = normalise_type(cls, so_id, alias_map, soid_map,
                                      ftype, so_names)
            out.write("%s\t%s\t%s\t%d\t%d\t%s\t%s\t%s\n"
                      % (args.species, tid, chrom, s, e, gene, region,
                         te_type))
            counts[region] += 1
            types[te_type] += 1
            n += 1

    sys.stderr.write("[%s] wrote %d TEs -> %s\n" % (args.species, n, args.out))
    for k in ("exon", "intron", "promoter", "intergenic"):
        sys.stderr.write("    %-11s %d\n" % (k, counts.get(k, 0)))
    top = sorted(types.items(), key=lambda x: -x[1])[:8]
    if top:
        sys.stderr.write("    top TE types: %s\n"
                         % ", ".join("%s=%d" % t for t in top))


if __name__ == "__main__":
    main()
