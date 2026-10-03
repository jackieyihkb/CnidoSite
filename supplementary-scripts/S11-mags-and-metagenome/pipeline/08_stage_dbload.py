#!/usr/bin/env python3
"""Stage 08 -- turn this pipeline's results into the per-MAG inputs the
database loaders on cnidosite.org expect.

The live MAG_detail.php does not read a JSON payload.  It reads six MySQL
tables (mag_annot, mag_interpro, mag_go_terms, mag_kegg_terms, mag_pfam_hits,
mag_panther_hits) and the loaders for them live in
/mnt/sda/jackie/tools/mag_pipeline/ on the server:

    load_iprscan.php   --mag <acc> --tsv <iprscan.tsv>       --ref <refdir>
    load_kofam.php     --mag <acc> --out <kofam.tsv>         --koref <ko_ref>
    load_mag_annot.php --mag <acc> --faa <acc.faa>

This script produces those three inputs per MAG, in work/dbload/.

Two things it does deliberately, both to keep the transfer small:

  * Drops column 15 of the InterProScan TSV (the pathway column).  It is the
    bulk of the 8 GB -- 7,297 of 13,404 rows per MAG carry it -- and the
    loader never reads it (it parses columns 0,3,4,5,11,12,13 only).  Verified
    lossless: every tuple over the loader's columns is identical before and
    after, for all 13,404 rows.
  * Emits Kofam as two columns (protein, KO) rather than the five-column
    thresholded TSV.  load_kofam.php only keys on columns 1 and 2.

Protein ids are forced into this pipeline's normalised form (<acc>_<n> for
Prodigal, <acc>_<NCBI accession> for RefSeq) because the six tables join on
`protein`.  Prodigal MAGs already look like that and kegg_ko.tsv was built from
the same norm FASTA, but the six NCBI MAGs were InterProScan'd with bare RefSeq
accessions, so this script prefixes them -- see the note in the loop.  MAG_detail
links a protein to NCBI only when the id matches an accession pattern and
prints it as plain text otherwise, so the prefixed ids degrade safely.

Descriptions: for NCBI-sourced MAGs the product name comes from NCBI's own
FASTA header.  For Prodigal MAGs there is no NCBI product, so the description
is the best Swiss-Prot hit's description (already E <= 1e-5 from stage 04),
with the OS=/OX=/GN= metadata trimmed off.  It is empty when nothing hit --
left empty rather than written as "hypothetical protein", which would be a
claim this pipeline never established.
"""
import os
import re

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
RES = os.path.join(ROOT, "results")
WORK = os.path.join(ROOT, "work")
OUT = os.path.join(WORK, "dbload")


def log(m):
    print(f"[08] {m}", flush=True)


def load_uniprot_desc(path):
    """protein -> cleaned Swiss-Prot description."""
    out = {}
    if not os.path.exists(path):
        return out
    with open(path) as fh:
        for line in fh:
            f = line.rstrip("\n").split("\t")
            if len(f) < 7:
                continue
            s = f[6]
            # "sp|Q3ZXR7|NUOH_DEHMC NADH-quinone oxidoreductase subunit H OS=..."
            sp = s.find(" ")
            if sp > 0 and s.startswith("sp|"):
                s = s[sp + 1:]
            for cut in (" OS=", " OX=", " GN=", " PE=", " SV="):
                i = s.find(cut)
                if i > 0:
                    s = s[:i]
            out[f[0]] = s.strip()
    return out


LOCUS_LIKE = re.compile(r"(?:^|\s)([A-Za-z][A-Za-z0-9]*_\d+)(?=$|\s|,)")


def strip_locus_like(desc):
    """Remove gene-identifier-shaped tokens from a hit-derived description.

    load_mag_annot.php treats any `ABC_123` token in the header as this MAG's
    locus_tag and prints it on its own line under the protein id.  That is
    right for an NCBI header, where the tag really is this assembly's.  It is
    wrong for a Swiss-Prot description: "UPF0246 protein VFMJ11_2214" carries
    *Vibrio fischeri*'s locus tag, and rendering it there would attribute a
    foreign identifier to this MAG's protein.  5,313 staged descriptions end in
    such a token.  Dropping the token leaves the name readable and keeps the
    column honest.
    """
    return re.sub(r"\s+", " ", LOCUS_LIKE.sub(" ", desc)).strip(" ,")


def read_fasta_headers(path):
    """[(pid, description)] in file order."""
    recs = []
    with open(path) as fh:
        for line in fh:
            if line.startswith(">"):
                h = line[1:].rstrip("\n")
                sp = h.find(" ")
                pid = h if sp < 0 else h[:sp]
                rest = "" if sp < 0 else h[sp + 1:].strip()
                recs.append((pid, rest))
    return recs


def main():
    os.makedirs(OUT, exist_ok=True)

    # Which MAGs this pipeline covered, and where their proteins came from.
    # fetch_status.tsv is accession / source(GCA|GCF) / protein_source(Prodigal|NCBI);
    # it carries no status column, so the inputs on disk are what says a MAG is ready.
    provenance = {}
    with open(os.path.join(WORK, "fetch_status.tsv")) as fh:
        next(fh, None)
        for line in fh:
            f = line.rstrip("\n").split("\t")
            if len(f) >= 3:
                provenance[f[0]] = f[2]
    missing = [a for a in provenance
               if not (os.path.exists(os.path.join(RES, "interproscan", f"{a}.tsv"))
                       and os.path.exists(os.path.join(WORK, "norm", f"{a}.faa")))]
    for a in missing:
        del provenance[a]
    accs = sorted(provenance)
    log(f"{len(accs)} MAGs to stage ({len(missing)} skipped, inputs missing)")
    if missing:
        log(f"  skipped: {', '.join(missing[:8])}{' ...' if len(missing) > 8 else ''}")

    uniprot = load_uniprot_desc(os.path.join(RES, "uniprot_hits.tsv"))
    log(f"{len(uniprot):,} UniProt best hits loaded")

    # --- Kofam: split the combined thresholded TSV by MAG ---------------------
    kegg = {}
    with open(os.path.join(RES, "kegg_ko.tsv")) as fh:
        next(fh, None)
        for line in fh:
            f = line.rstrip("\n").split("\t")
            if len(f) >= 2 and f[1].startswith("K"):
                kegg.setdefault(f[0], f[1])
    log(f"{len(kegg):,} KO assignments")

    kegg_by_mag = {a: [] for a in accs}
    unmatched = 0
    for pid, ko in kegg.items():
        for a in accs:
            if pid.startswith(a + "_"):
                kegg_by_mag[a].append((pid, ko))
                break
        else:
            unmatched += 1
    if unmatched:
        log(f"WARNING: {unmatched} KO rows did not map to a MAG")

    # --- per-MAG staging ------------------------------------------------------
    stats = {"ipr_rows": 0, "ipr_bytes": 0, "faa": 0, "ko": 0, "no_desc": 0,
             "ipr_prefixed": 0}
    for acc in accs:
        # 1. InterProScan TSV, column 15 dropped (loader never reads it).
        src = os.path.join(RES, "interproscan", f"{acc}.tsv")
        dst = os.path.join(OUT, f"{acc}.iprscan.tsv")
        if os.path.exists(src):
            with open(src) as fi, open(dst, "w") as fo:
                for line in fi:
                    f = line.rstrip("\n").split("\t")
                    if len(f) >= 15:
                        f = f[:14]          # 15th (pathway) is the 8 GB bulk
                    # The six NCBI MAGs were scanned with bare RefSeq accessions
                    # in the headers (WP_002978317.1), while mag_annot, the KEGG
                    # split and the norm FASTA all use <acc>_<accession>.  The
                    # tables join on `protein`, so leaving these bare means the
                    # detail page silently renders no InterPro/Pfam/PANTHER/GO
                    # for those MAGs -- no error, just empty cells.  Prefixing is
                    # exact here (every prefixed id matches a norm id, and 17,481
                    # accessions are shared between these MAGs, so bare ids were
                    # ambiguous to begin with).
                    if f and f[0] and not f[0].startswith(acc + "_"):
                        f[0] = f"{acc}_{f[0]}"
                        stats["ipr_prefixed"] += 1
                    fo.write("\t".join(f) + "\n")
                    stats["ipr_rows"] += 1
            stats["ipr_bytes"] += os.path.getsize(dst)

        # 2. Kofam, two columns.
        rows = kegg_by_mag.get(acc, [])
        with open(os.path.join(OUT, f"{acc}.kofam.tsv"), "w") as fo:
            for pid, ko in sorted(rows):
                fo.write(f"{pid}\t{ko}\n")
        stats["ko"] += len(rows)

        # 3. FASTA for mag_annot: ">pid description".
        norm = os.path.join(WORK, "norm", f"{acc}.faa")
        with open(os.path.join(OUT, f"{acc}.faa"), "w") as fo:
            for pid, rest in read_fasta_headers(norm):
                # NCBI headers already carry the product name; Prodigal headers
                # are ">pid #" so the description has to come from the best hit.
                if rest in ("", "#"):
                    desc = strip_locus_like(uniprot.get(pid, ""))
                    if not desc:
                        stats["no_desc"] += 1
                else:
                    desc = rest          # NCBI: real product name, real locus tag
                fo.write(f">{pid} {desc}\n" if desc else f">{pid}\n")
                stats["faa"] += 1

    log(f"proteins staged        {stats['faa']:,}  (no description: {stats['no_desc']:,})")
    log(f"InterProScan rows      {stats['ipr_rows']:,}")
    log(f"KO rows                {stats['ko']:,}")
    log(f"prefixed protein ids   {stats['ipr_prefixed']:,}")
    log(f"staged size            {stats['ipr_bytes'] / 1e6:.0f} MB of InterProScan TSV")
    log(f"output                 {OUT}")


if __name__ == "__main__":
    main()
