#!/usr/bin/env python3
"""
07_build_tables.py
------------------
Assemble the per-dataset pipeline output into the flat tables that CnidoSite
loads, and emit a ready-to-run SQL file.

Inputs
   1.metadata/datasets.tsv        dataset listing scraped from the live site
   1.metadata/search_params.tsv   per-dataset search parameters (both the
                                  original study's and CnidoSite's)
   4.results/<PXd>/proteins.tsv   gene-level evidence
   4.results/<PXd>/contaminants.tsv
   4.results/<PXd>/fdr_summary.tsv

Outputs (4.results/load/)
   proteomic_datasets.tsv    one row per dataset, incl. full provenance
   proteomic_proteins.tsv    one row per (dataset, protein)
   proteomic_peptides.tsv    one row per (dataset, peptide)
   proteomics_load.sql       INSERT statements for the schema in
                             5.web/sql/proteomics_schema.sql
   coverage_report.tsv       per-dataset status (for the manuscript + website)

Usage:
    python3 07_build_tables.py --release 1.1
"""
from __future__ import annotations

import argparse
import csv
import datetime as dt
import re
import sys
from pathlib import Path

from cnido_common import db_key_for_field, resolve_proteome

ROOT = Path(__file__).resolve().parent.parent
META = ROOT / "1.metadata"
RESULTS = ROOT / "4.results"

# Precursor-tolerance unit spellings accepted in search_params.tsv, rendered for
# the website.  Kept as a map rather than string mangling: the three spellings of
# the dalton all mean the same thing and must not appear as three different
# units on three different dataset pages.
PREC_UNIT_LABEL = {"ppm": "ppm", "da": "Da", "amu": "Da", "daltons": "Da", "mmu": "mmu"}
LOAD = RESULTS / "load"

# provenance of the reference proteomes used for searching
PROTEOME_SOURCE = "CnidoSite reference proteome (2.anno), BUSCO-validated"


def provenance_for(species: str) -> dict:
    """Provenance of the search space actually used for a dataset's species.

    Read from the sidecar stage 03 (or 04, for a combined multi-species
    database) wrote next to the built database, so the website reports what was
    *really* searched rather than what would be chosen today.  A species with no
    reference proteome is searched against a transcriptome-derived proteome, or
    -- where even that does not exist -- a congener surrogate, and both must be
    stated on the page.

    Uses `db_key_for_field`, the same derivation stage 04 builds with, so a
    multi-species dataset resolves to its combined key rather than silently
    falling back to the reference-proteome label.
    """
    from cnido_common import db_key_for_field, read_provenance
    key = db_key_for_field(species)
    return read_provenance(key) if key else {}

STATUS_LABEL = {
    "reprocessed": "reprocessed against the CnidoSite reference proteome",
    "no_reference_proteome": "no CnidoSite reference proteome available for this species",
    "bespoke_database_required": "requires the bespoke database of the original study",
    "pending": "queued for re-processing",
    # A dataset that was searched and yielded nothing is a *result*, not an
    # outstanding task.  Collapsing it into "pending" would tell the reader we
    # had not got round to it, which is the opposite of what happened.
    "no_identifications_at_fdr":
        "searched; no peptide passed the 1% FDR threshold",
    # PSMs passed but peptides did not.  See the note built in `main()`: this is
    # almost always the peptide-level q-value floor, not a failed search, and
    # calling it "no identifications" would understate what was found.
    "psms_below_peptide_fdr": "searched; PSMs passed the 1% FDR, peptides did not",
}

# Per-dataset notes where the generic label would be misleading or too terse to
# be useful.  Kept here rather than in search_params.tsv because these describe
# the outcome of our search, not the parameters we searched with.
STATUS_NOTE_OVERRIDE = {
    "PXD027774": (
        "Searched with Comet + Percolator against the Hydra vulgaris reference "
        "proteome; no peptide reached q<=0.01. The deposited MGF carries an "
        "unreliable CHARGE field (5,567 of 9,374 distinct precursor m/z values "
        "appear with more than one charge state, some with 1+, 2+, 3+ and 4+ for "
        "the same m/z), so the precursor mass Comet derives is frequently wrong. "
        "Fifteen parameter combinations, including charge-agnostic searching, "
        "were tested; none separated targets from decoys. The original Mascot "
        "result deposited with the dataset shows a median ion score of 2.54 and "
        "only 91 of 7,533 PSMs at expectation <0.05, so the absence of confident "
        "identifications is a property of the deposited data rather than of the "
        "re-processing."
    ),
}

# Per-dataset provenance notes where the *search space* needs saying in words
# the generic label cannot carry.  A reference-proteome dataset normally gets no
# note, but these two are depositions whose species name is not the name of any
# reference proteome: `PROTEOME_ALIAS` resolves Aiptasia pulchella, Aiptasia sp.
# and Exaiptasia pallida all to Exaiptasia diaphana, so the page would otherwise
# show "Aiptasia pulchella" against "CnidoSite reference proteome" with nothing
# saying which reference that is.  Without a note the synonymy is visible only in
# 1.metadata/search_params.tsv, which no page reads.
PROTEOME_NOTE_OVERRIDE = {
    "PXD003202": (
        "Deposited as Aiptasia pulchella. Exaiptasia pallida, E. diaphana and "
        "Aiptasia pulchella are one organism here (see PROTEOME_ALIAS), so the "
        "search space is the CnidoSite Exaiptasia diaphana reference proteome. "
        "No reference proteome exists under the deposited name."
    ),
    "PXD004257": (
        "Deposited as Aiptasia sp.; the model anemone in this literature is "
        "Exaiptasia diaphana, so the search space is the CnidoSite E. diaphana "
        "reference proteome. No reference proteome exists under the deposited "
        "name."
    ),
}


def peptide_floor_note(fdr: dict, n_psm01: int) -> str:
    """Explain a `psms_below_peptide_fdr` outcome in the dataset's own numbers.

    Returns '' for every other outcome, so callers can just `or` it into the
    generic status label.
    """
    if n_psm01 <= 0 or int(fdr.get("peptides_q01") or 0) > 0:
        return ""
    q = fdr.get("peptide_q_min") or ""
    tail = ""
    if q:
        try:
            pct = float(q) * 100
            tail = (f" The best-scoring peptide carries a peptide-level q value "
                    f"of {pct:.4g}%, just above the 1% cut-off: Percolator's "
                    f"peptide-level estimate applies a +1 correction to the "
                    f"decoy count, so with this few peptides it cannot fall "
                    f"below that value even though the PSMs separate cleanly.")
        except ValueError:
            tail = ""
    return (f"{n_psm01} peptide-spectrum match(es) passed the 1% PSM-level FDR, "
            f"but no peptide passed the 1% peptide-level FDR, so no protein is "
            f"reported.{tail}")


def read_tsv(p: Path):
    """Read a TSV, ignoring leading '#' comment lines (search_params.tsv has a
    long commented header block before the real column header)."""
    if not p.exists():
        return []
    with p.open() as fh:
        lines = [l for l in fh if not l.startswith("#") and l.strip()]
    return list(csv.DictReader(lines, delimiter="\t"))


def split_proteins(field: str):
    """Percolator joins protein IDs with whitespace and/or ';'."""
    if not field:
        return []
    return [p for p in re.split(r"[;\s]+", field.strip()) if p]


def strip_abbr(pid: str) -> str:
    """`EDIAP_KXJ04192.1` -> `KXJ04192.1`  (the ID gene_detail.php resolves).
    Kept in sync with 06_map_to_genes.py:strip_abbr and the SQL schema."""
    if pid.startswith("CRAP_"):
        return pid
    return pid.split("_", 1)[1] if "_" in pid else pid


def sql_str(v) -> str:
    if v is None or v == "":
        return "NULL"
    return "'" + str(v).replace("\\", "\\\\").replace("'", "''") + "'"


def sql_num(v) -> str:
    if v is None or v == "":
        return "NULL"
    try:
        return str(float(v)) if "." in str(v) or "e" in str(v).lower() else str(int(float(v)))
    except ValueError:
        return "NULL"


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--release", default="1.1")
    ap.add_argument("--date", default=dt.date.today().isoformat())
    a = ap.parse_args()

    LOAD.mkdir(parents=True, exist_ok=True)
    datasets = {r["pxd"]: r for r in read_tsv(META / "datasets.tsv") if r.get("pxd")}
    params = {r["pxd"]: r for r in read_tsv(META / "search_params.tsv") if r.get("pxd")}

    # Incorporation date belongs to the dataset, not to the build.  This stage
    # re-derives every row from 4.results/, so without carrying the existing
    # dates forward a rebuild would re-date datasets already in the table to
    # today -- the table would assert they entered the database on a day they
    # did not, which is the same class of unsupported provenance claim the
    # reviewers objected to elsewhere.  Datasets not in the current table are
    # genuinely new and get `--date`.
    prior_dates = {}
    _prior = LOAD / "proteomic_datasets.tsv"
    if _prior.exists():
        for r in read_tsv(_prior):
            if r.get("dataset_id") and r.get("date_incorporated"):
                prior_dates[r["dataset_id"]] = r["date_incorporated"]

    ds_rows, pr_rows, pep_rows, coverage = [], [], [], []
    results_dirs = sorted(d for d in RESULTS.glob("PXD*") if d.is_dir())

    for rdir in results_dirs:
        pxd = rdir.name
        p = params.get(pxd, {})
        d = datasets.get(pxd, {})

        prot = read_tsv(rdir / "proteins.tsv")
        cont = read_tsv(rdir / "contaminants.tsv")
        fdr = {r["metric"]: r["value"] for r in read_tsv(rdir / "fdr_summary.tsv")}
        dataset_id = pxd
        tissue = p.get("tissue") or d.get("Tissue", "")
        species = p.get("species") or d.get("Species", "")

        # Provenance of the search space actually used, and whether *any*
        # proteome is resolvable for this species.  The two differ when a
        # proteome exists but the database has not been built yet, and the
        # distinction is what separates "queued" from "cannot be done".
        prov = provenance_for(species)
        # A multi-species field ("A; B; C") resolves to a combined database and
        # has no single `ProteomeRef`, so ask for the search-DB key instead.
        multi = ";" in species
        ref = None if multi else resolve_proteome(species)
        has_proteome = (db_key_for_field(species) is not None)

        # fdr_summary.tsv is written by stage 05, so its presence proves the
        # search actually ran -- as opposed to the dataset sitting in the queue.
        searched = (rdir / "fdr_summary.tsv").exists()
        n_psm01 = int(fdr.get("psms_q01") or 0)
        if prot or cont:
            status = "reprocessed"
        elif searched and n_psm01 > 0:
            # PSMs cleared 1% but no peptide did.  Percolator's peptide-level q
            # value carries a +1 correction on the decoy count, so the best
            # peptides in a small result set cannot score below
            # 1/(peptides in the top stratum) -- on the datasets this affects,
            # that floor is 1.01%, i.e. the threshold is missed by 0.0001 and the
            # dataset is a boundary case rather than a failure.  Report the PSMs
            # and say why there are no peptides.
            status = "psms_below_peptide_fdr"
        elif searched:
            status = "no_identifications_at_fdr"
        elif has_proteome:
            status = "pending"
        else:
            status = "no_reference_proteome"

        ds_rows.append({
            "dataset_id": dataset_id, "pxd": pxd, "species": species,
            "taxon_class": d.get("Class", ""), "tissue": tissue,
            "treatment": d.get("Treatment", ""), "instrument": p.get("instrument", ""),
            "pubmed": d.get("pubmed", "") or d.get("Reference", ""),
            "orig_engine": p.get("orig_engine", ""), "orig_database": p.get("orig_database", ""),
            "orig_precursor": p.get("orig_prec", ""), "orig_fragment": p.get("orig_frag", ""),
            "orig_fdr": p.get("orig_fdr", ""),
            "cnido_engine": "Comet 2026.01",
            "cnido_enzyme": p.get("cnido_enzyme", ""),
            "cnido_termini": p.get("cnido_termini", ""),
            "cnido_missed": p.get("cnido_missed", ""),
            # units are per-dataset: Orbitrap projects search in ppm, the
            # low-resolution ion-trap ones (Q TRAP) in Da.  Labelling a Da
            # tolerance as "ppm" on the dataset page would misstate the search.
            "cnido_precursor": (
                p.get("cnido_prec_ppm", "") + " " + PREC_UNIT_LABEL.get(
                    (p.get("cnido_prec_units") or "ppm").strip().lower(), "ppm")
            ) if p.get("cnido_prec_ppm") else "",
            "cnido_fragment": (p.get("cnido_frag_da", "") + " Da") if p.get("cnido_frag_da") else "",
            "cnido_fixed": p.get("cnido_fixed", ""), "cnido_variable": p.get("cnido_variable", ""),
            "cnido_fdr_psm": "0.01", "cnido_fdr_prot": "0.01",
            "cnido_decoy": "Comet internal reversed (1:1)",
            "cnido_quant": "spectral counting (PSMs)",
            "proteome_file": prov.get("proteome_path") or p.get("proteome_file", ""),
            # Only claim a search space when one exists.  Asserting the default
            # reference label for a species that has no proteome would be exactly
            # the kind of unsupported provenance claim the reviewers objected to.
            "proteome_source": (prov.get("label")
                                or (ref.label if ref else "")
                                or (PROTEOME_SOURCE if has_proteome else "")),
            # Machine-readable, so the pages do not have to guess the kind of
            # search space from the wording of `proteome_source`.
            "proteome_source_type": (prov.get("source_type")
                                     or ("reference" if has_proteome else "")),
            "proteome_note": (PROTEOME_NOTE_OVERRIDE.get(dataset_id)
                              or prov.get("note", "")),
            "proteome_is_surrogate": 1 if prov.get("is_surrogate") == "1" else 0,
            "status": status,
            "status_note": STATUS_NOTE_OVERRIDE.get(dataset_id)
                           or peptide_floor_note(fdr, n_psm01)
                           or STATUS_LABEL.get(status, ""),
            "pipeline_version": "cnidosite-proteomics 1.0",
            "release": a.release,
            "date_incorporated": prior_dates.get(dataset_id, a.date),
            "file_count": fdr.get("files", ""),
            "n_psms": fdr.get("psms_q01", ""), "n_peptides": fdr.get("peptides_q01", ""),
            "n_proteins": len(prot),
        })

        # protein_id -> gene_id, using the semantics stage 06 already settled:
        # a stripped CnidoSite accession for reference proteomes, a Trinity gene
        # for transcriptome/congener searches.  What matters here is only that
        # the peptide rows agree with the protein rows, so reuse 06's answer
        # rather than re-deriving it.
        gene_of = {}
        for kind, rows in (("target", prot), ("contaminant", cont)):
            for r in rows:
                gene_of[r["protein_id"]] = r["gene_id"]
                pr_rows.append({
                    "dataset_id": dataset_id, "gene_id": r["gene_id"],
                    "protein_id": r["protein_id"],
                    # absent in results built before this column existed, where
                    # the search space was always the reference proteome
                    "links_gene": r.get("links_gene", 1),
                    "n_psms": r["n_psms"],
                    "n_unique_peptides": r["n_unique_peptides"],
                    "coverage_pct": r["coverage_pct"], "length": r["length"],
                    "best_q": r["best_q"], "description": r["description"],
                    "is_contaminant": r["is_contaminant"],
                })

        # peptides.tsv carries one row per peptide with a ';'/' '-joined protein
        # list.  The website looks peptides up by gene_id (the gene page panel
        # does `WHERE gene_id = ...`), so explode the list into one row per
        # (peptide, protein) pair and de-duplicate.
        seen_pep = set()
        for r in read_tsv(rdir / "peptides.tsv"):
            peptide = (r.get("peptide") or "").strip()
            if not peptide:
                continue
            for pid in split_proteins(r.get("proteins", "")):
                key = (dataset_id, pid, peptide)
                if key in seen_pep:
                    continue
                seen_pep.add(key)
                pep_rows.append({
                    "dataset_id": dataset_id,
                    "gene_id": gene_of.get(pid, strip_abbr(pid)),
                    "protein_id": pid,
                    "peptide": peptide,
                    "q_value": r.get("q_value", ""),
                })

        coverage.append({"dataset_id": dataset_id, "pxd": pxd, "species": species,
                         "status": status,
                         "files": fdr.get("files", 0), "psms_q01": fdr.get("psms_q01", 0),
                         "peptides_q01": fdr.get("peptides_q01", 0),
                         "proteins": len(prot), "contaminants": len(cont)})

    # ---- write TSVs ------------------------------------------------------
    def write_tsv(path, rows, cols):
        with path.open("w", newline="") as fh:
            w = csv.DictWriter(fh, fieldnames=cols, delimiter="\t",
                               extrasaction="ignore", lineterminator="\n")
            w.writeheader()
            w.writerows(rows)

    ds_cols = list(ds_rows[0].keys()) if ds_rows else []
    write_tsv(LOAD / "proteomic_datasets.tsv", ds_rows, ds_cols)
    pr_cols = ["dataset_id", "gene_id", "protein_id", "links_gene", "n_psms",
               "n_unique_peptides",
               "coverage_pct", "length", "best_q", "description", "is_contaminant"]
    write_tsv(LOAD / "proteomic_proteins.tsv", pr_rows, pr_cols)
    write_tsv(LOAD / "proteomic_peptides.tsv", pep_rows,
              ["dataset_id", "gene_id", "protein_id", "peptide", "q_value"])
    write_tsv(LOAD / "coverage_report.tsv", coverage,
              ["dataset_id", "pxd", "species", "status", "files", "psms_q01",
               "peptides_q01", "proteins", "contaminants"])

    # ---- write SQL -------------------------------------------------------
    # Columns are split into "write as a quoted string" and "write as a number".
    # The second branch is not a fallback: sql_num() returns NULL for any value
    # that will not parse as a number, so a *string* column left out of this list
    # is written as NULL rather than as itself.  `pxd` was omitted exactly this
    # way and every row loaded as NULL, which the NOT NULL in the schema then
    # rejected (ERROR 1048) -- the failure surfaced only at load time, on the
    # live database, with no warning during generation.  Add new string columns
    # here when adding them to the schema.
    # Mirrors the varchar() widths of the live `cnidaria` schema.  A value longer
    # than its column is not silently clipped: MySQL rejects the whole statement
    # (ERROR 1406 "Data too long") *inside* the load transaction, so the run
    # aborts and nothing is written -- but only at load time, against production.
    # Two datasets carrying the 45-char "20 ppm (first search) / 4.5 ppm (main
    # search)" hit `orig_precursor` varchar(32) exactly that way.  Checking here
    # turns a production-only abort into a local error that names the column.
    # Update this when a column is added or widened.
    COL_WIDTH = {
        "dataset_id": 32, "pxd": 16, "species": 128, "taxon_class": 64,
        "tissue": 255, "treatment": 255, "instrument": 96, "pubmed": 16,
        "orig_engine": 128, "orig_database": 255, "orig_precursor": 32,
        "orig_fragment": 32, "orig_fdr": 96, "cnido_engine": 64,
        "cnido_enzyme": 32, "cnido_termini": 16, "cnido_missed": 8,
        "cnido_precursor": 32, "cnido_fragment": 32, "cnido_fixed": 96,
        "cnido_variable": 96, "cnido_fdr_psm": 16, "cnido_fdr_prot": 16,
        "cnido_decoy": 64, "cnido_quant": 96, "proteome_file": 128,
        "proteome_source": 255, "proteome_source_type": 16, "proteome_note": 600,
        "status": 32, "status_note": 1000, "pipeline_version": 32,
        "release": 32, "gene_id": 64, "protein_id": 96, "peptide": 160,
    }

    def insert(table, rows, cols):
        if not rows:
            return f"-- no rows for {table}\n"
        out = [f"DELETE FROM `{table}`;"]
        for r in rows:
            for c in cols:
                v = r.get(c)
                lim = COL_WIDTH.get(c)
                if lim and isinstance(v, str) and len(v) > lim:
                    raise SystemExit(
                        f"{table}.{c} is varchar({lim}) but row "
                        f"{r.get('dataset_id')!r} carries {len(v)} chars:\n"
                        f"    {v!r}\n"
                        f"Shorten the value (it usually comes from "
                        f"1.metadata/search_params.tsv) or widen the column.")
            vals = ", ".join(sql_str(r.get(c)) if c in
                             ("dataset_id", "pxd", "gene_id", "protein_id", "description",
                              "peptide", "species", "taxon_class", "tissue", "treatment",
                              "instrument", "pubmed", "orig_engine", "orig_database",
                              "orig_precursor", "orig_fragment", "orig_fdr", "cnido_engine",
                              "cnido_enzyme", "cnido_termini", "cnido_missed", "cnido_precursor",
                              "cnido_fragment", "cnido_fixed", "cnido_variable", "cnido_fdr_psm",
                              "cnido_fdr_prot", "cnido_decoy", "cnido_quant", "proteome_file",
                              "proteome_source", "proteome_source_type", "proteome_note", "status", "status_note",
                              "pipeline_version", "release", "date_incorporated")
                             else sql_num(r.get(c)) for c in cols)
            out.append(f"INSERT INTO `{table}` ({', '.join('`'+c+'`' for c in cols)}) VALUES ({vals});")
        return "\n".join(out) + "\n"

    # Assemble the whole script in memory *before* touching the file on disk.
    # Opening the destination with "w" truncates it immediately, so any failure
    # while generating -- a width violation from the COL_WIDTH guard above, say
    # -- used to leave a 92-byte stub where a loadable script had been, and the
    # damage is silent: the file exists and looks current.  Build, then rename.
    body = [f"-- CnidoSite proteomics load, release {a.release}, {a.date}\n",
            "SET NAMES utf8mb4;\nSTART TRANSACTION;\n",
            insert("proteomic_datasets", ds_rows, ds_cols),
            insert("proteomic_proteins", pr_rows, pr_cols),
            insert("proteomic_peptides", pep_rows,
                   ["dataset_id", "gene_id", "protein_id", "peptide", "q_value"]),
            "COMMIT;\n"]
    sql_tmp = LOAD / "proteomics_load.sql.tmp"
    sql_tmp.write_text("".join(body))
    sql_tmp.replace(LOAD / "proteomics_load.sql")

    n_t = sum(1 for r in pr_rows if r["is_contaminant"] == "0")
    n_c = len(pr_rows) - n_t
    print(f"datasets={len(ds_rows)}  proteins(target)={n_t}  "
          f"proteins(contaminant)={n_c}  peptides={len(pep_rows)}", file=sys.stderr)
    print(f"-> {LOAD}", file=sys.stderr)


if __name__ == "__main__":
    main()
