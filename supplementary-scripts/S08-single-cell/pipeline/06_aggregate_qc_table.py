#!/usr/bin/env python3
"""
06_aggregate_qc_table.py -- build the dataset-level QC table for the CnidoSite
single-cell module.

Why this exists
---------------
Referee 2 (Major #3) asks, of the 33 datasets on sn_data.php, for:

  * "cells before/after filtering"
  * "library type"
  * "mitochondrial distributions"
  * "doublet-removal procedures"
  * "batch/integration procedures"
  * whether all 33 datasets are in fact 10x-compatible

and says the fixed 600-5000 UMI window cannot be right across species.  The
answer has to be a per-dataset table, not a sentence in the methods.  This
script assembles that table from the run records written by 03_qc.py and
04_cluster_annotate.py, joined to the platform classification harvested in
01_harvest_sra_metadata.py.

Every number here is read from a run record, never transcribed by hand.  For a
dataset that has not been re-processed, the row is emitted with its status
recorded as such -- an incomplete table that says so is honest, whereas one
that quietly omits the unprocessed datasets is not.

Outputs
-------
  meta/qc_dataset_table.tsv    one row per dataset, machine-readable
  meta/qc_dataset_table.md     the same table, for pasting into a response
  sql/load_singlecell.sql      INSERTs for the website's MySQL tables

Usage
-----
    python 06_aggregate_qc_table.py
"""

from __future__ import annotations

import argparse
import json
import os
import re
import sys
from datetime import datetime, timezone
from pathlib import Path

import pandas as pd

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from lib import io as cnio  # noqa: E402

PIPELINE_VERSION = "cnidosite-sc-1.0.0"

#: The columns of the QC table, in the order the referee asked for them.
#: (column, human heading) -- kept together so the TSV and the Markdown cannot
#: drift apart.
COLUMNS: list[tuple[str, str]] = [
    ("dataset_id", "Dataset"),
    ("cnidosite_row", "sn_data.php row"),
    ("species", "Species"),
    ("class", "Class"),
    ("tissue_organ", "Tissue / organ"),
    ("stage", "Stage"),
    ("bioproject", "BioProject"),
    ("sra_study", "SRA study"),
    ("geo_series", "GEO series"),
    ("platform_class", "Library type"),
    ("platform_confidence", "Platform call"),
    ("n_samples", "Libraries"),
    ("n_cells_reported", "Cells reported by source"),
    ("n_cells_raw", "Cells before filtering"),
    ("n_cells_after_cell_qc", "Cells after cell filtering"),
    ("n_doublets_removed", "Doublets removed"),
    ("doublet_rate", "Doublet rate"),
    ("n_cells_final", "Cells after filtering (final)"),
    ("pct_cells_retained", "Percent retained"),
    ("threshold_mode", "Threshold strategy"),
    ("threshold_detail", "Thresholds applied"),
    ("mito_genes_n", "Mitochondrial genes found"),
    ("mito_filtering_available", "MT% filter applied"),
    ("mito_pct_median_raw", "MT% median (before)"),
    ("mito_pct_p95_raw", "MT% p95 (before)"),
    ("mito_pct_median_final", "MT% median (after)"),
    ("mito_pct_p95_final", "MT% p95 (after)"),
    ("doublet_method", "Doublet method"),
    ("integration_method", "Integration method"),
    ("n_clusters", "Clusters"),
    ("n_cell_types", "Cell types"),
    ("annotation_provenance", "Annotation source"),
    # provenance, for Referee 2 Major 9: what the reads were mapped to, which
    # code produced the atlas, and which published labels it inherited.
    ("reference_genome", "Reference genome"),
    ("genome_version", "Genome version"),
    ("pipeline_version", "Pipeline version"),
    ("cell_type_source", "Cell-type labels from"),
    ("analysis_status", "Status"),
]


def _get(d: dict, key: str, default="") -> object:
    v = d.get(key)
    return default if v is None else v


def _threshold_detail(th: dict) -> str:
    """One readable sentence for how this dataset was filtered."""
    if not th:
        return ""
    mode = (th.get("mode") or "").lower()
    if mode == "published":
        parts = []
        if th.get("min_genes") is not None:
            parts.append(f"min {th['min_genes']:g} genes")
        if th.get("min_counts") is not None and th.get("max_counts") is not None:
            parts.append(f"{th['min_counts']:g}-{th['max_counts']:g} UMI")
        elif th.get("min_counts") is not None:
            parts.append(f"min {th['min_counts']:g} UMI")
        if th.get("max_pct_mt") is not None:
            parts.append(f"max {th['max_pct_mt']:g}% MT")
        return "published: " + ", ".join(parts) if parts else "published"
    if mode == "adaptive":
        parts = [f"per-library MAD ({th.get('nmads', '?')} MADs) on UMI, genes and MT%"]
        if th.get("min_genes") is not None:
            parts.append(f"floor {th['min_genes']:g} genes")
        if th.get("min_counts") is not None:
            parts.append(f"floor {th['min_counts']:g} UMI")
        return "adaptive: " + ", ".join(parts)
    return mode


def build(registry_path: str, qc_dir: str, annotated_dir: str,
          sra_meta_path: str) -> pd.DataFrame:
    reg = cnio.read_registry(registry_path)

    # Join the harvested SRA metadata onto the registry.  Neither sra_study nor
    # bioproject is unique -- a single study can back several website rows
    # (SRP199550 covers three Nematostella datasets) -- so the join key is the
    # sn_data.php row number the metadata was harvested for.  Matching on the
    # study accession instead would silently attach the wrong library type to
    # two thirds of the table.
    sra: dict[str, dict] = {}
    if os.path.exists(sra_meta_path):
        with open(sra_meta_path) as fh:
            raw = json.load(fh)
        rows = raw if isinstance(raw, list) else raw.get("datasets", [])
        by_row: dict[int, dict] = {}
        for r in rows:
            try:
                by_row[int(r["cnidosite_row"])] = r
            except (KeyError, TypeError, ValueError):
                continue
        has_row_col = "cnidosite_row" in reg.columns
        for i, (_, r) in enumerate(reg.iterrows(), start=1):
            # prefer the declared sn_data.php row, same reason as below: a
            # dataset appended to the registry must not shift the rest.
            key = i
            if has_row_col:
                declared = str(r.get("cnidosite_row") or "").strip()
                if declared.isdigit():
                    key = int(declared)
                elif declared == "":
                    key = None      # not on sn_data.php; no metadata harvested
            hit = by_row.get(key) if key is not None else None
            if hit is not None:
                sra[str(r["dataset_id"])] = hit

    out = []
    for row_no, (_, r) in enumerate(reg.iterrows(), start=1):
        did = r["dataset_id"]
        row = {k: "" for k, _ in COLUMNS}
        row["dataset_id"] = did
        # The link to sn_data.php is positional unless the registry declares a
        # row explicitly.  Positional mapping is fragile: appending a dataset --
        # Xenia, say -- would silently renumber everything after it and
        # mis-attribute the whole QC table.  So a registry that HAS the column
        # is taken at its word, and an empty value means the dataset is not yet
        # on sn_data.php and gets no map row.  A registry without the column at
        # all keeps the old positional behaviour.
        if "cnidosite_row" in reg.columns:
            declared = str(r.get("cnidosite_row") or "").strip()
            row["cnidosite_row"] = int(declared) if declared.isdigit() else ""
        else:
            row["cnidosite_row"] = row_no
        row["species"] = r.get("species", "")
        row["class"] = r.get("class", "")
        row["tissue_organ"] = r.get("tissue_organ", "")
        row["stage"] = r.get("stage", "")
        row["bioproject"] = r.get("bioproject", "")
        row["sra_study"] = r.get("sra_study", "")
        row["geo_series"] = r.get("geo_series", "")
        row["n_cells_reported"] = r.get("cells_reported", "")
        row["reference_genome"] = r.get("reference_genome", "")
        row["genome_version"] = r.get("genome_version", "")
        # Which figure under images/ the source study published for this dataset
        # (`AMILL` for images/AMILL_UMAP_1.png, `AMURI_RegenerationStage`, ...).
        # cell_atlas.php synthesises a `pub:<abbr1>:<tissue>` option per figure
        # it finds there, and this is the only thing that lets such an option
        # find the dataset re-analysed from it.  It is carried on the atlas row
        # rather than added to COLUMNS (see the DataFrame below), because the QC
        # table is a table of QC figures and this is not one.
        row["published_figure"] = r.get("published_figure", "")

        sm = sra.get(did, {})
        row["platform_class"] = sm.get("platform_class") or r.get("platform_class", "")
        # Harvested metadata only exists for datasets that are on sn_data.php,
        # because it is joined on the row number.  A dataset added to the module
        # before the site lists it -- the Hydra atlas is the first -- has a
        # registry-declared platform and no harvest, and leaving the confidence
        # blank there made the one column the referee's library-chemistry
        # question turns on read as if it had not been checked.  A registry
        # value is only ever written after inspecting the deposit, so it is
        # labelled `curated` rather than borrowing the harvester's scale.
        row["platform_confidence"] = (
            sm.get("platform_confidence")
            or ("curated" if r.get("platform_class") else "")
        )

        qc_p = os.path.join(qc_dir, f"{did}.qc_stats.json")
        an_p = os.path.join(annotated_dir, f"{did}.annotate_stats.json")

        if not os.path.exists(qc_p):
            # "not processed" is the fact; *why* is not always the same fact, and
            # the difference matters to a reader deciding whether the gap is the
            # deposit's or ours.  A dataset whose matrix was downloaded and found
            # to hold normalised values, or whose deposit is scATAC, is not in
            # the same position as one that deposited no matrix at all, and
            # saying "not retrieved" of the first two would be untrue.  The
            # registry records the verified reason; without one the default
            # stands, because that is what an un-inspected series means.
            reason = (r.get("not_processed_reason") or "").strip()
            row["analysis_status"] = (
                "not processed (" + reason + ")" if reason
                else "not processed (count matrix not retrieved)"
            )
            out.append(row)
            continue

        with open(qc_p) as fh:
            qc = json.load(fh)

        for src, dst in (
            ("n_samples", "n_samples"), ("n_cells_raw", "n_cells_raw"),
            ("n_cells_after_cell_qc", "n_cells_after_cell_qc"),
            ("n_doublets_removed", "n_doublets_removed"),
            ("n_cells_final", "n_cells_final"), ("doublet_method", "doublet_method"),
            ("mito_genes_n", "mito_genes_n"),
            ("mito_filtering_available", "mito_filtering_available"),
        ):
            if src in qc:
                row[dst] = qc[src]

        # The QC stage records the integration it was *told* to use, not one it
        # ran (`03_qc.py` never integrates).  It is written into the table only
        # for a dataset the annotation stage has not reached yet, and prefixed
        # so a reader cannot take the plan for a result -- and the two values do
        # differ: five single-library datasets declare `Harmony` here while `04`
        # records `none (single library)` for them.
        if qc.get("integration_declared"):
            row["integration_method"] = f"declared: {qc['integration_declared']}"

        if qc.get("doublet_rate") is not None:
            row["doublet_rate"] = f"{float(qc['doublet_rate']) * 100:.2f}%"
        if qc.get("pct_cells_retained") is not None:
            row["pct_cells_retained"] = f"{float(qc['pct_cells_retained']):.1f}%"

        th = qc.get("thresholds") or {}
        row["threshold_mode"] = th.get("mode", "")
        row["threshold_detail"] = _threshold_detail(th)

        for key, dst in (("mito_distribution_raw", "raw"),
                         ("mito_distribution_final", "final")):
            dist = qc.get(key) or {}
            if dist:
                row[f"mito_pct_median_{dst}"] = round(float(dist.get("median", 0)), 2)
                row[f"mito_pct_p95_{dst}"] = round(float(dist.get("p95", 0)), 2)

        if os.path.exists(an_p):
            with open(an_p) as fh:
                an = json.load(fh)
            row["n_clusters"] = an.get("n_clusters", "")
            row["n_cell_types"] = an.get("n_cell_types", "")
            row["annotation_provenance"] = an.get("annotation_provenance", "")
            # provenance for Referee 2 (Major 9): what code produced this, from
            # which published labels, and when.
            row["pipeline_version"] = an.get("pipeline_version", "")
            # the published label table is recorded by the QC stage, not the
            # annotation stage, so it is read from the QC record.
            pub = qc.get("published_annotation") or {}
            if pub.get("source"):
                row["cell_type_source"] = pub["source"]
                if pub.get("match_rate") is not None:
                    row["cell_type_source"] += (
                        " (%.0f%% of cells matched)" % (float(pub["match_rate"]) * 100)
                    )
            elif an.get("annotation_provenance") == "cluster_only":
                # say what the labels actually are, so a reader of the QC table
                # does not read "42 cell types" as 42 identified cell types
                note = (an.get("annotation_note")
                        or "marker panel matched no cluster")
                # cell_type_source is VARCHAR(255) and MySQL in strict mode
                # rejects an over-long value outright, so cap it here rather
                # than let one verbose note fail the whole load
                row["cell_type_source"] = ("none - Leiden clusters; " + note)[:255]
            elif an.get("marker_panel"):
                # A dataset annotated from its own panel (04's `marker_panel`
                # registry column) is de novo, and until now that left this
                # column empty -- the QC table said which labels a dataset has
                # but not where they came from, which is the question Referee 2
                # actually asks.  Gated on `marker_panel` rather than on
                # `de_novo` so that datasets whose labels were transferred by a
                # separate script keep the value they already have in the live
                # table instead of drifting on the next full load.
                note = an.get("annotation_note") or ""
                text = "de novo - dataset marker panel; " + note
                if len(text) > 255:
                    # Cut at a sentence end rather than at the character that
                    # happens to be 255th: this string is shown on the atlas
                    # page, and a caveat that stops mid-clause ("...The panel
                    # held 44") reads as a page defect.  Falls back to a hard
                    # cap if no sentence boundary is available in the second
                    # half, so the result is always <= 255.
                    cut = text[:255]
                    end = cut.rfind(". ")
                    text = cut[:end + 1] if end > 127 else cut
                row["cell_type_source"] = text
            # a dataset whose integration silently fell back is worth flagging
            if an.get("integration_method"):
                row["integration_method"] = an["integration_method"]
            row["analysis_status"] = "processed"
        else:
            row["analysis_status"] = "QC complete; clustering pending"

        out.append(row)

    # `published_figure` rides along outside COLUMNS: it is needed by the atlas
    # INSERT but is not a QC figure, and the QC table is the referee's.  Adding
    # it here rather than to COLUMNS also keeps it out of the TSV and Markdown
    # this module publishes.  A row without the key becomes NaN, which sqlv()
    # writes as NULL -- which is the honest value: no published figure.
    return pd.DataFrame(out, columns=[c for c, _ in COLUMNS] + ["published_figure"])


def to_markdown(df: pd.DataFrame, max_cols: int | None = None) -> str:
    """A GitHub-flavoured table with the referee-facing headings.

    Only COLUMNS are published.  `build()` carries `published_figure` on the
    frame for the atlas INSERT; it is not a QC figure and has no heading, so
    without this filter it would appear in the published table under its own
    Python name.
    """
    heads = dict(COLUMNS)
    cols = [c for c in df.columns if c in heads]
    if max_cols:
        cols = cols[:max_cols]
    lines = ["| " + " | ".join(heads.get(c, c) for c in cols) + " |",
             "|" + "|".join("---" for _ in cols) + "|"]
    for _, r in df.iterrows():
        cells = []
        for c in cols:
            v = r[c]
            if v is None or (isinstance(v, float) and pd.isna(v)):
                v = ""
            cells.append(str(v).replace("|", "\\|"))
        lines.append("| " + " | ".join(cells) + " |")
    return "\n".join(lines)


# Columns whose values must reach MySQL as literals rather than quoted strings.
# Quoting them hides type errors until load time, and a Python bool becomes the
# string 'False', which MariaDB rejects for a TINYINT column in strict mode.
_INT_COLS = frozenset({
    "cnidosite_row", "n_cells_final", "n_cell_types", "n_clusters", "n_samples",
    "n_cells", "n_cells_raw", "n_cells_after_cell_qc", "n_doublets_removed",
    "mito_genes_n", "rank",
})
_FLOAT_COLS = frozenset({
    "pct_cells", "log2fc", "padj", "score", "pct_in",
    "mito_pct_median_raw", "mito_pct_p95_raw",
    "mito_pct_median_final", "mito_pct_p95_final",
})
_BOOL_COLS = frozenset({"mito_filtering_available"})
_TRUE_WORDS = frozenset({"true", "t", "yes", "y", "1"})
_FALSE_WORDS = frozenset({"false", "f", "no", "n", "0"})


def sql_escape(v) -> str:
    """A quoted text literal, or NULL.  Use sqlv() for typed columns."""
    if v is None or (isinstance(v, float) and pd.isna(v)):
        return "NULL"
    s = str(v)
    if s == "":
        return "NULL"
    return "'" + s.replace("\\", "\\\\").replace("'", "''") + "'"


def sqlv(v, col: str) -> str:
    """One SQL literal, typed by the column it is destined for.

    Text columns are quoted; integer, float and boolean columns are not.  An
    empty value becomes NULL in every case, so a missing measurement is never
    mistaken for zero.
    """
    if v is None or (isinstance(v, float) and pd.isna(v)):
        return "NULL"
    if isinstance(v, str) and v.strip() == "":
        return "NULL"

    if col in _BOOL_COLS:
        if isinstance(v, bool):
            return "1" if v else "0"
        s = str(v).strip().lower()
        if s in _TRUE_WORDS:
            return "1"
        if s in _FALSE_WORDS:
            return "0"
        return "NULL"
    if col in _INT_COLS:
        try:
            return str(int(round(float(v))))
        except (TypeError, ValueError):
            return "NULL"
    if col in _FLOAT_COLS:
        try:
            f = float(v)
        except (TypeError, ValueError):
            return "NULL"
        if pd.isna(f) or f != f:
            return "NULL"
        return repr(f)
    return sql_escape(v)


# The column lists the generated INSERTs write.  Kept at module level so the
# emitter and the schema check below cannot drift apart; check_against_schema()
# exists precisely because a column missing from these lists is NULL forever
# and nothing else complains.
ATLAS_COLS = [
    "dataset_id", "cnidosite_row", "species", "class", "tissue_organ", "stage",
    "platform_class", "n_cells_final", "n_cell_types", "n_clusters", "n_samples",
    "annotation_provenance", "bioproject", "sra_study", "geo_series",
    "reference_genome", "genome_version", "pipeline_version", "cell_type_source",
    "asset_dir", "published_figure", "updated_utc",
]
QC_COLS = [
    "dataset_id", "platform_class", "platform_confidence", "n_cells_reported",
    "n_cells_raw", "n_cells_after_cell_qc", "n_doublets_removed", "doublet_rate",
    "n_cells_final", "pct_cells_retained", "threshold_mode", "threshold_detail",
    "mito_genes_n", "mito_filtering_available", "mito_pct_median_raw",
    "mito_pct_p95_raw", "mito_pct_median_final", "mito_pct_p95_final",
    "doublet_method", "integration_method", "n_samples", "analysis_status",
    # Not a QC figure, but it is written here as well as to singlecell_atlas
    # because the two tables answer different questions about one figure: the
    # atlas row says which dataset was re-analysed FROM it, and this row says
    # which deposit IS it when nothing was.  The reason a figure has no
    # re-analysis lives in `analysis_status` on this very row, so without the
    # stem here a published-figure page has no way to reach its own reason --
    # a `pub:` entry is synthesised from an image filename and carries no
    # dataset_id.  It stays out of COLUMNS, so it is still not published in the
    # TSV or the Markdown.
    "published_figure",
]
CELLTYPE_COLS = ["dataset_id", "cell_type", "n_cells", "pct_cells"]
MARKER_COLS = ["dataset_id", "cell_type", "gene", "rank", "log2fc", "padj",
               "score", "pct_in"]
MAP_COLS = ["cnidosite_row", "dataset_id"]

EMITTED_COLUMNS = {
    "singlecell_atlas": ATLAS_COLS,
    "singlecell_qc": QC_COLS,
    "singlecell_celltype": CELLTYPE_COLS,
    "singlecell_markers": MARKER_COLS,
    "singlecell_atlas_map": MAP_COLS,
}


def schema_columns(schema_path: str) -> dict:
    """table name -> set of column names, parsed from CREATE TABLE statements."""
    if not os.path.exists(schema_path):
        return {}
    with open(schema_path) as fh:
        text = fh.read()
    out = {}
    for m in re.finditer(
        r"CREATE TABLE IF NOT EXISTS\s+(\w+)\s*\((.*?)\n\)\s*ENGINE", text, re.S
    ):
        table, body = m.group(1), m.group(2)
        cols = []
        for raw in body.split("\n"):
            line = raw.strip().rstrip(",")
            if not line or line.startswith("--"):
                continue
            first = line.split()[0].strip("`")
            if first.upper() in {"PRIMARY", "KEY", "UNIQUE", "CONSTRAINT",
                                 "INDEX", "FOREIGN", "REFERENCES", "ON"}:
                continue
            cols.append(first)
        out[table] = set(cols)
    return out


def check_against_schema(schema_path: str, emitted: dict) -> list:
    """Column-list disagreements between the schema and the generated INSERTs.

    Written because two bugs in this file were only caught by actually loading
    the generated SQL into a server: a boolean reaching MySQL as the string
    'False' for a TINYINT column, and a foreign key onto a table whose parent
    rows had not been written yet.  A column that exists in the schema but is
    absent from the emitted list is the same failure one step quieter -- it is
    NULL forever and nothing complains.
    """
    schema = schema_columns(schema_path)
    problems = []
    for table, cols in sorted(emitted.items()):
        if table not in schema:
            problems.append(f"{table}: no such table in the schema")
            continue
        missing = sorted(schema[table] - set(cols))
        extra = sorted(set(cols) - schema[table])
        if missing:
            problems.append(f"{table}: in the schema but never written -> {missing}")
        if extra:
            problems.append(f"{table}: written but absent from the schema -> {extra}")
    return problems


def emit_sql(df: pd.DataFrame, path: str, web_dir: str,
             max_markers_per_type: int = 50) -> None:
    """INSERTs for the website tables the viewer pages read.

    The atlas assets themselves are static files; the database only carries
    what the PHP pages need in order to list datasets, link into the viewer,
    and render the QC column the referee asked for.
    """
    os.makedirs(os.path.dirname(path), exist_ok=True)
    ts = datetime.now(timezone.utc).isoformat(timespec="seconds")
    lines = [
        "-- Generated by pipeline/06_aggregate_qc_table.py -- do not edit by hand.",
        f"-- Generated {ts}",
        f"-- Pipeline {PIPELINE_VERSION}",
        "--",
        "-- Load with:  mysql -u jackie -p cnidaria < load_singlecell.sql",
        "-- after running singlecell_schema.sql once.",
        "",
        "START TRANSACTION;",
        "DELETE FROM singlecell_markers;",
        "DELETE FROM singlecell_celltype;",
        "DELETE FROM singlecell_qc;",
        "DELETE FROM singlecell_atlas_map;",
        "DELETE FROM singlecell_atlas;",
        "",
    ]

    atlas_cols = ATLAS_COLS
    qc_cols = QC_COLS

    # The map is written for EVERY row, processed or not: it is how sn_data.php
    # knows which of its rows has a viewer behind it.
    for _, r in df.iterrows():
        # skip datasets with no sn_data.php row: they have a viewer but no row
        # on the index page to link from, and a NULL key would collide.
        if not str(r["cnidosite_row"]).strip().isdigit():
            continue
        lines.append(
            f"INSERT INTO singlecell_atlas_map ({', '.join(MAP_COLS)}) "
            f"VALUES ({int(r['cnidosite_row'])}, {sql_escape(r['dataset_id'])});"
        )
    lines.append("")

    # QC rows likewise cover every dataset in the registry, so the table's
    # coverage is not overstated by omission.
    processed = []
    for _, r in df.iterrows():
        vals = ", ".join(sqlv(r[c], c) for c in qc_cols)
        lines.append(
            f"INSERT INTO singlecell_qc ({', '.join(qc_cols)}) VALUES ({vals});"
        )
        if r["analysis_status"] == "processed":
            processed.append(r)

    lines.append("")
    for r in processed:
        did = r["dataset_id"]
        row = dict(r)
        row["updated_utc"] = ts
        row["asset_dir"] = f"/singlecell_data/{did}/"
        vals = ", ".join(sqlv(row.get(c, ""), c) for c in atlas_cols)
        lines.append(
            f"INSERT INTO singlecell_atlas ({', '.join(atlas_cols)}) "
            f"VALUES ({vals});"
        )

    # ---- composition and markers, from the exported web assets -------------
    lines.append("")
    n_ct = n_mk = 0
    for r in processed:
        did = r["dataset_id"]
        dest = os.path.join(web_dir, did)

        comp_p = os.path.join(dest, "composition.json")
        if os.path.exists(comp_p):
            with open(comp_p) as fh:
                comp = json.load(fh)
            totals = comp.get("totals", {})
            grand = sum(totals.values()) or 1
            for ct, n in totals.items():
                pct = 100.0 * n / grand
                lines.append(
                    f"INSERT INTO singlecell_celltype "
                    f"({', '.join(CELLTYPE_COLS)}) VALUES ("
                    f"{sql_escape(did)}, {sql_escape(ct)}, {int(n)}, "
                    f"{sqlv(pct, 'pct_cells')});"
                )
                n_ct += 1

        mk_p = os.path.join(dest, "markers.json")
        if os.path.exists(mk_p):
            with open(mk_p) as fh:
                mk = json.load(fh)
            for ct, entries in mk.items():
                for rank, e in enumerate(entries[:max_markers_per_type], start=1):
                    lines.append(
                        f"INSERT INTO singlecell_markers "
                        f"({', '.join('`%s`' % c if c == 'rank' else c for c in MARKER_COLS)}) "
                        f"VALUES ("
                        f"{sql_escape(did)}, {sql_escape(ct)}, "
                        f"{sql_escape(e.get('gene'))}, {rank}, "
                        f"{sqlv(e.get('log2fc'), 'log2fc')}, "
                        f"{sqlv(e.get('padj'), 'padj')}, "
                        f"{sqlv(e.get('score'), 'score')}, "
                        f"{sqlv(e.get('pct_in'), 'pct_in')});"
                    )
                    n_mk += 1

    lines += ["", "COMMIT;", "",
              f"-- {len(df)} registry rows, {len(processed)} with a live atlas, "
              f"{n_ct} cell-type rows, {n_mk} marker rows.", ""]
    with open(path, "w") as fh:
        fh.write("\n".join(lines))


def main() -> int:
    here = Path(__file__).resolve().parent
    root = here.parent
    ap = argparse.ArgumentParser()
    ap.add_argument("--registry", default=str(root / "meta" / "dataset_registry.tsv"))
    ap.add_argument("--qc-dir", default=str(root / "data" / "qc"))
    ap.add_argument("--annotated-dir", default=str(root / "data" / "annotated"))
    ap.add_argument("--sra-metadata", default=str(root / "meta" / "sra_metadata.json"))
    ap.add_argument("--outdir", default=str(root / "meta"))
    ap.add_argument("--sql", default=str(root / "sql" / "load_singlecell.sql"))
    ap.add_argument("--schema", default=str(root / "sql" / "singlecell_schema.sql"),
                    help="checked against the emitted column lists; a mismatch "
                         "means the load script cannot populate every column")
    ap.add_argument("--web-dir", default=str(root / "web" / "singlecell_data"),
                    help="exported viewer assets, for the composition and "
                         "marker tables")
    args = ap.parse_args()

    df = build(args.registry, args.qc_dir, args.annotated_dir, args.sra_metadata)

    tsv = os.path.join(args.outdir, "qc_dataset_table.tsv")
    md = os.path.join(args.outdir, "qc_dataset_table.md")
    # as in to_markdown(): the TSV is the referee-facing table, so it carries
    # COLUMNS and not the atlas-only `published_figure` riding on the frame.
    df[[c for c, _ in COLUMNS]].to_csv(tsv, sep="\t", index=False)

    n_proc = int((df["analysis_status"] == "processed").sum())
    header = (
        f"# Dataset-level quality control, CnidoSite single-cell module\n\n"
        f"Generated {datetime.now(timezone.utc).isoformat(timespec='seconds')} "
        f"by pipeline {PIPELINE_VERSION}.\n\n"
        f"{len(df)} datasets in the registry; {n_proc} carried through to a "
        f"clustered, annotated atlas at the time of writing. Rows marked "
        f"\"not processed\" state in the parenthesis why, one dataset at a time: "
        f"most have no downloadable count matrix, but where the deposit was "
        f"inspected and the blocker is different -- normalised values instead of "
        f"counts, an scATAC deposit, a Seurat RDS, a merge this pipeline does not "
        f"yet do -- the row says so.  Both are listed so the coverage of this "
        f"table is not overstated in either direction: a gap we have not closed "
        f"is not presented as a gap in the deposit.\n\n"
    )
    full = header + "## Full table\n\n" + to_markdown(df) + "\n\n"
    full += ("## The columns the referee asked for, on their own\n\n"
             + to_markdown(df, max_cols=13)
             + "\n\n## Mitochondrial and doublet detail\n\n"
             + to_markdown(df[[
                 "dataset_id", "mito_genes_n", "mito_filtering_available",
                 "mito_pct_median_raw", "mito_pct_p95_raw",
                 "mito_pct_median_final", "mito_pct_p95_final",
                 "doublet_method", "n_doublets_removed", "doublet_rate",
                 "integration_method", "n_samples"]]))
    with open(md, "w") as fh:
        fh.write(full)

    problems = check_against_schema(args.schema, EMITTED_COLUMNS)
    if problems:
        print(f"\n!! sql/load_singlecell.sql disagrees with "
              f"{os.path.basename(args.schema)}:", file=sys.stderr)
        for p in problems:
            print(f"!!   {p}", file=sys.stderr)
        print("!! the generated script will not load cleanly; fix before shipping.",
              file=sys.stderr)
        return 1

    emit_sql(df, args.sql, args.web_dir)

    print(f"wrote {tsv}")
    print(f"wrote {md}")
    print(f"wrote {args.sql}")
    print(f"\n{len(df)} datasets, {n_proc} processed")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
