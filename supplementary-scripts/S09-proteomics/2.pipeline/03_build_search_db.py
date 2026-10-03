#!/usr/bin/env python3
"""
03_build_search_db.py
---------------------
Build the Comet search database for one proteomics dataset.

The database is the **CnidoSite reference proteome** for the dataset's species,
so that every identified peptide maps directly onto a CnidoSite gene page.
cRAP contaminants are appended (prefixed `CRAP_`) so that laboratory
contaminants are identified and explicitly *removed* from the reported protein
list -- rather than shown as if they were cnidarian proteins, which is the
current behaviour that reviewers objected to.

Decoys are NOT added here: Comet generates them internally with
`decoy_search = 1` (reversed, `DECOY_` prefixed), which keeps the target:decoy
ratio exactly 1:1 as required for FDR estimation.

Outputs (into 3.work/db/):
    <species>.target.fasta   CnidoSite proteome
    <species>.search.fasta   proteome + cRAP  (this is what Comet is pointed at)
    <species>.db.tsv         protein_id -> is_contaminant, length, description

Usage:
    python3 03_build_search_db.py "Nematostella vectensis"
    python3 03_build_search_db.py --all-pilot
"""
from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
PROTEOME_DIR = Path("/mnt/sda/jackie/cnidaria/2.anno")
DB = ROOT / "3.work" / "db"
CRAP = DB / "cRAP_GPM.fasta"

def proteome_path(species: str) -> Path | None:
    """Resolve a website species name to the proteome it should be searched against.

    Delegates to `cnido_common.resolve_proteome()`, which prefers an explicit
    entry in `1.metadata/proteome_sources.tsv` (transcriptome-derived or
    congener-surrogate proteomes for species with no genome annotation) and
    otherwise falls back to the BUSCO-validated reference proteome.

    Returns None when nothing is available, so the caller reports "no proteome
    for X" rather than raising FileNotFoundError deep inside read_fasta().
    """
    from cnido_common import resolve_proteome
    ref = resolve_proteome(species)
    return ref.path if ref else None


def read_fasta(path: Path):
    """Yield (header_first_token, description, sequence)."""
    hdr, seq = None, []
    with path.open("r", errors="replace") as fh:
        for line in fh:
            line = line.rstrip("\n")
            if not line:
                continue
            if line[0] == ">":
                if hdr is not None:
                    yield hdr
                raw = line[1:]
                tok = raw.split()[0]
                hdr = (tok, raw[len(tok):].strip(), "".join(seq))
                seq = []
            else:
                seq.append(line)
    if hdr is not None:
        yield hdr


def build(species: str) -> int:
    from cnido_common import resolve_proteome
    ref = resolve_proteome(species)
    if ref is None:
        print(f"!! no reference proteome for {species}", file=sys.stderr)
        return 1
    pep = ref.path

    DB.mkdir(parents=True, exist_ok=True)
    # The DB is keyed on `db_key`, not on the proteome's own filename, so that a
    # congener surrogate cannot overwrite the same-genus species' own DB: Actinia
    # fragacea searched against Actinia tenebrosa must not land on
    # Actinia_tenebrosa.search.fasta, which PXD-for-tenebrosa itself uses.
    key = ref.db_key
    srch = DB / f"{key}.search.fasta"
    meta = DB / f"{key}.db.tsv"
    prov = DB / f"{key}.provenance.tsv"

    n_t = 0
    with srch.open("w") as ft, meta.open("w") as fm:
        fm.write("protein_id\tis_contaminant\tlength\tdescription\n")
        for tok, desc, seq in read_fasta(pep):
            if not seq:
                continue
            ft.write(f">{tok} {desc}\n")
            for i in range(0, len(seq), 60):
                ft.write(seq[i:i + 60] + "\n")
            fm.write(f"{tok}\t0\t{len(seq)}\t{desc}\n")
            n_t += 1

        n_c = 0
        if CRAP.exists():
            for tok, desc, seq in read_fasta(CRAP):
                if not seq:
                    continue
                cid = "CRAP_" + tok
                ft.write(f">{cid} {desc}\n")
                for i in range(0, len(seq), 60):
                    ft.write(seq[i:i + 60] + "\n")
                fm.write(f"{cid}\t1\t{len(seq)}\tcRAP contaminant {desc}\n")
                n_c += 1

    # Provenance travels with the DB so that stage 07 can state, on the dataset
    # page, exactly what search space produced these identifications.
    with prov.open("w") as fp:
        fp.write("key\tvalue\n")
        for k, v in (
            ("dataset_species", species),
            ("db_key", key),
            ("proteome_path", str(pep)),
            ("source_type", ref.source_type),
            ("source_species", ref.source_species),
            ("is_surrogate", "1" if ref.is_surrogate else "0"),
            ("n_target_proteins", n_t),
            ("gene_map", str(ref.gene_map) if ref.gene_map else ""),
            ("label", ref.label),
            ("note", ref.note),
        ):
            fp.write(f"{k}\t{v}\n")

    tag = "" if ref.is_reference else f"  [{ref.source_type}"
    tag += "" if ref.is_reference else (f": {ref.source_species}]" if ref.is_surrogate else "]")
    print(f"{species}: {n_t} target proteins + {n_c} cRAP contaminants -> {srch}{tag}",
          file=sys.stderr)
    return 0


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("species", nargs="*")
    ap.add_argument("--all-pilot", action="store_true")
    a = ap.parse_args()

    species = list(a.species)
    if a.all_pilot:
        species += ["Nematostella vectensis", "Myxobolus honghuensis",
                    "Thelohanellus kitauei", "Corallium rubrum"]
    if not species:
        ap.error("give a species name or --all-pilot")
    rc = 0
    for s in species:
        rc |= build(s)
    sys.exit(rc)


if __name__ == "__main__":
    main()
