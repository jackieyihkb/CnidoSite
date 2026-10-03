#!/usr/bin/env python3
"""
06_map_to_genes.py
------------------
Turn Percolator output into **gene-level proteomic evidence** that CnidoSite can
display and link.

The bridge
~~~~~~~~~~
CnidoSite reference proteomes use headers of the form

    >EDIAP_KXJ04192.1 hypothetical protein ... [Exaiptasia diaphana]

and the website's gene pages (`gene_detail.php`) resolve the accession
**without** the `<ABBR>_` prefix:

    gene=KXJ04192.1&species=Exaiptasia diaphana   ->  OK (genomic location + NR)
    gene=EDIAP_KXJ04192.1&...                     ->  no data

So the mapping applied here is simply: strip the leading `<ABBR>_`.
This was verified against the live site for NVECT, SPIST and EDIAP.

Outputs (4.results/<PXd>/):
    proteins.tsv      gene-level evidence: peptides, coverage, q-value, intensity
    contaminants.tsv  cRAP hits, reported separately (never mixed into results)

Usage:
    python3 06_map_to_genes.py PXD009253
    python3 06_map_to_genes.py --all-searched
"""
from __future__ import annotations

import argparse
import csv
import re
import sys
from collections import defaultdict
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
RESULTS = ROOT / "4.results"
DB = ROOT / "3.work" / "db"


def proteome_sequences(db_tsv: Path):
    """protein_id -> (length, description, is_contaminant) from the DB manifest."""
    info = {}
    seqs = {}
    if not db_tsv.exists():
        return info, seqs
    with db_tsv.open() as fh:
        for r in csv.DictReader(fh, delimiter="\t"):
            pid = r["protein_id"]
            info[pid] = (int(r["length"]), r["description"], r["is_contaminant"] == "1")
    return info, seqs


def find_db_tsv(pxd: str) -> Path | None:
    """Locate the DB manifest for the species searched in this dataset."""
    sdir = ROOT / "3.work" / "search" / pxd / "comet.params"
    db_fasta = None
    if sdir.exists():
        for line in sdir.read_text().splitlines():
            if line.startswith("database_name"):
                db_fasta = Path(line.split("=", 1)[1].strip())
                break
    if db_fasta is None:
        return None
    # <Stem>.search.fasta -> <Stem>.db.tsv
    name = db_fasta.name.replace(".search.fasta", ".db.tsv")
    cand = DB / name
    return cand if cand.exists() else None


def split_proteins(field: str):
    """Percolator joins protein IDs with whitespace and/or ';'."""
    if not field:
        return []
    return [p for p in re.split(r"[;\s]+", field.strip()) if p]


def load_provenance(db_tsv: Path | None) -> dict:
    """Read the `<db_key>.provenance.tsv` sidecar written by stage 03.

    Tells us whether the search space was the CnidoSite reference proteome (in
    which case protein IDs are CnidoSite accessions that gene pages resolve) or
    a transcriptome-derived / congener proteome (in which case they are Trinity
    contigs, which have no gene page).
    """
    if db_tsv is None:
        return {}
    p = db_tsv.with_name(db_tsv.name.replace(".db.tsv", ".provenance.tsv"))
    if not p.exists():
        return {}
    out = {}
    with p.open() as fh:
        for line in fh:
            k, _, v = line.rstrip("\n").partition("\t")
            if k and k != "key":
                out[k] = v
    return out


def load_gene_map(path: str | None) -> dict:
    """protein_id -> Trinity gene ID, from one or more `trans2gene.tsv` files.

    Accepts a ';'-separated list because a combined multi-species database can
    bring together several transcriptome assemblies at once.
    """
    m = {}
    for one in (path or "").split(";"):
        one = one.strip()
        if not one:
            continue
        p = Path(one)
        if not p.exists():
            continue
        with p.open() as fh:
            for line in fh:
                f = line.rstrip("\n").split("\t")
                if len(f) >= 2:
                    m[f[0]] = f[1]
    return m


def is_assembly_id(pid: str) -> bool:
    """True for a Trinity/TransDecoder contig rather than a CnidoSite accession.

    Tested on the ID itself rather than on a whole-database flag: a combined
    multi-species search space mixes both kinds of sequence, and each protein
    has to be judged on its own.
    """
    return pid.startswith("TRINITY_") or pid.endswith(".p1")


def strip_abbr(pid: str) -> str:
    """`EDIAP_KXJ04192.1` -> `KXJ04192.1`  (CnidoSite gene-page ID).

    Only valid for reference-proteome hits.  Trinity contigs
    (`TRINITY_DN145148_c0_g1_i2.p1`) must NOT go through this: it would yield
    `DN145148_c0_g1_i2.p1`, which looks like a CnidoSite accession and would be
    linked to a gene page that does not exist.
    """
    if pid.startswith("CRAP_"):
        return pid
    return pid.split("_", 1)[1] if "_" in pid else pid


def read_fasta_seqs(fasta: Path):
    """Minimal FASTA reader for coverage computation."""
    seqs, hdr, buf = {}, None, []
    with fasta.open(errors="replace") as fh:
        for line in fh:
            if line.startswith(">"):
                if hdr:
                    seqs[hdr] = "".join(buf)
                hdr = line[1:].split()[0]
                buf = []
            else:
                buf.append(line.strip())
    if hdr:
        seqs[hdr] = "".join(buf)
    return seqs


def strip_mods(pep: str) -> str:
    """Reduce a Percolator peptide string to a bare residue string.

    Handles the flanking-residue notation Comet/Percolator emit, e.g.
        R.HVGDLGNIVAGADKVAK.V      -> HVGDLGNIVAGADKVAK
        -.AEVTGDDATDSWYSEVK.K      -> AEVTGDDATDSWYSEVK
    as well as modification annotations such as ``M[15.9949]`` or ``M(ox)``.
    A plain ``.strip("-.")`` is not enough: it only trims the outer ends and
    leaves the flanking residues in place, which silently zeroes coverage.
    """
    pep = re.sub(r"\[[^\]]*\]", "", pep)          # [15.9949] / [ox]
    pep = re.sub(r"\([^)]*\)", "", pep)           # (ox)
    m = re.match(r"^[A-Za-z-]\.(.*)\.[A-Za-z-]$", pep)
    if m:
        pep = m.group(1)
    return pep.strip("-.")


def compute_coverage(seq: str, peptides) -> float:
    """Percent of residues covered by at least one identified peptide."""
    if not seq:
        return 0.0
    covered = bytearray(len(seq))
    for p in peptides:
        bare = strip_mods(p)
        if len(bare) < 4:
            continue
        start = 0
        while True:
            i = seq.find(bare, start)
            if i < 0:
                break
            for k in range(i, i + len(bare)):
                covered[k] = 1
            start = i + 1
    return 100.0 * sum(covered) / len(seq)


def process(pxd: str) -> dict:
    rdir = RESULTS / pxd
    pep_file = rdir / "peptides.tsv"
    psm_file = rdir / "psms.tsv"
    if not pep_file.exists():
        print(f"[{pxd}] no peptides.tsv -- run 05 first", file=sys.stderr)
        return {}

    db_tsv = find_db_tsv(pxd)
    info, _ = proteome_sequences(db_tsv) if db_tsv else ({}, {})

    # Provenance tells us which gene map to consult; the per-protein decision of
    # whether an ID is linkable is made below, because a combined search space
    # can contain both CnidoSite accessions and Trinity contigs.
    prov = load_provenance(db_tsv)
    is_reference = prov.get("source_type", "reference") == "reference"
    gene_map = load_gene_map(prov.get("gene_map"))

    fasta = None
    if db_tsv:
        cand = DB / db_tsv.name.replace(".db.tsv", ".search.fasta")
        if cand.exists():
            fasta = read_fasta_seqs(cand)

    # peptide -> proteins, and q-values
    pep_rows = list(csv.DictReader(pep_file.open(), delimiter="\t"))
    psm_rows = list(csv.DictReader(psm_file.open(), delimiter="\t")) if psm_file.exists() else []

    psm_count = defaultdict(int)
    for r in psm_rows:
        for pid in split_proteins(r.get("proteins", "")):
            psm_count[pid] += 1

    prot_peps = defaultdict(set)
    prot_q = {}
    for r in pep_rows:
        try:
            q = float(r.get("q_value", "1"))
        except (TypeError, ValueError):
            q = 1.0
        for pid in split_proteins(r.get("proteins", "")):
            prot_peps[pid].add(r["peptide"])
            prot_q[pid] = min(prot_q.get(pid, 1.0), q)

    targets, contams = [], []
    for pid, peps in prot_peps.items():
        is_c = pid.startswith("CRAP_")
        length, desc, _ = info.get(pid, (0, "", is_c))
        seq = (fasta or {}).get(pid, "")
        cov = compute_coverage(seq, peps) if seq else 0.0
        if is_c:
            gene_id, links = pid, 0
        elif is_assembly_id(pid):
            # Trinity gene behind this contig; real, but not a CnidoSite gene page.
            gene_id, links = gene_map.get(pid, ""), 0
        else:
            gene_id, links = strip_abbr(pid), 1
        rec = {
            "protein_id": pid,
            "gene_id": gene_id,
            "links_gene": links,
            "n_psms": psm_count.get(pid, 0),
            "n_unique_peptides": len(peps),
            "coverage_pct": round(cov, 1),
            "length": length or len(seq),
            "best_q": prot_q.get(pid, ""),
            "description": (desc or "")[:300],
            "is_contaminant": int(is_c),
        }
        (contams if is_c else targets).append(rec)

    # ranking used by the website: most evidence first
    targets.sort(key=lambda r: (-r["n_unique_peptides"], -r["n_psms"], r["gene_id"]))
    contams.sort(key=lambda r: -r["n_unique_peptides"])

    cols = ["gene_id", "protein_id", "links_gene", "n_psms", "n_unique_peptides",
            "coverage_pct", "length", "best_q", "description", "is_contaminant"]
    for path, rows in ((rdir / "proteins.tsv", targets),
                       (rdir / "contaminants.tsv", contams)):
        with path.open("w", newline="") as fh:
            w = csv.DictWriter(fh, fieldnames=cols, delimiter="\t",
                               extrasaction="ignore", lineterminator="\n")
            w.writeheader()
            w.writerows(rows)

    n_linked = sum(1 for r in targets if r["links_gene"])
    note = "" if is_reference else f" [{prov.get('source_type', '?')} proteome]"
    print(f"[{pxd}] {len(targets)} proteins ({n_linked} gene-linkable), "
          f"{len(contams)} contaminant proteins -> {rdir}{note}", file=sys.stderr)
    return {"pxd": pxd, "proteins": len(targets), "contaminants": len(contams)}


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("pxds", nargs="*")
    ap.add_argument("--all-searched", action="store_true")
    a = ap.parse_args()
    want = list(a.pxds)
    if a.all_searched:
        want += [d.name for d in sorted(RESULTS.glob("PXD*")) if d.is_dir()]
    if not want:
        ap.error("give a PXD accession or --all-searched")
    for pxd in sorted(set(want)):
        process(pxd)


if __name__ == "__main__":
    main()
