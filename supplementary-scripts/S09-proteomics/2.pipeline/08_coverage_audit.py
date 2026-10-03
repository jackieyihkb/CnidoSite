#!/usr/bin/env python3
"""
08_coverage_audit.py
--------------------
Answer one question, for the referee response: *for every dataset in the
catalogue, is there a search space, and what kind is it?*

This is deliberately computed through the same resolution functions the search
stages use (`db_key_for_field` -> `resolve_proteome`), rather than by joining
tables in SQL or counting by hand.  The numbers quoted in the response letter
are therefore the numbers the pipeline actually acts on, and they cannot drift
apart from it.

The interesting output is the last group: species for which no search space
exists at all.  Those are not "queued" -- nothing will ever process them unless
a proteome or a transcriptome assembly is produced first, so they are the list
the authors have to act on.

Usage:
    python3 08_coverage_audit.py                 # summary + blockers
    python3 08_coverage_audit.py --tsv out.tsv   # machine-readable per dataset
"""
from __future__ import annotations

import argparse
import csv
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from cnido_common import db_key_for_field, resolve_proteome  # noqa: E402

ROOT = Path(__file__).resolve().parent.parent
META = ROOT / "1.metadata"

# Species assembled by the CnidoSite transcriptome-annotation programme, read
# from a plain list so the audit can say *why* a dataset is still blocked:
# "assembly in progress" reads very differently to the authors than "nobody has
# ever sequenced this species".
def _load(path: Path) -> set[str]:
    if not path.exists():
        return set()
    return {l.strip().replace("_", " ").lower()
            for l in path.read_text().splitlines() if l.strip() and not l.startswith("#")}


def read_tsv(p: Path):
    if not p.exists():
        return []
    with p.open() as fh:
        lines = [l for l in fh if not l.startswith("#") and l.strip()]
    return list(csv.DictReader(lines, delimiter="\t"))


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--tsv", help="write the per-dataset audit here")
    ap.add_argument("--assembling", default=str(META / "assembly_in_progress.txt"),
                    help="species being assembled by the transcriptome programme")
    ap.add_argument("--assembled", default=str(META / "assembly_done.txt"),
                    help="species whose assembly has finished")
    a = ap.parse_args()

    assembling = _load(Path(a.assembling))
    assembled = _load(Path(a.assembled))

    datasets = [r for r in read_tsv(META / "datasets.tsv") if r.get("pxd")]
    params = {r["pxd"]: r for r in read_tsv(META / "search_params.tsv") if r.get("pxd")}

    rows, blockers = [], {}
    for d in datasets:
        pxd = d["pxd"]
        p = params.get(pxd, {})
        # search_params.tsv wins: it is where a multi-species deposit has been
        # joined into one combined search space.
        species = p.get("species") or d.get("Species", "")
        key = db_key_for_field(species)
        ref = None if ";" in species else resolve_proteome(species)
        kind = "none"
        if key is not None and ref is not None:
            kind = ref.source_type
        elif key is not None:
            kind = "combined"

        rows.append({"pxd": pxd, "species": species, "class": d.get("Class", ""),
                     "search_space": kind, "db_key": key or "",
                     "source_species": ref.source_species if ref else ""})

        if kind == "none":
            blockers.setdefault(species, []).append(pxd)

    n = len(rows)
    counts = {}
    for r in rows:
        counts[r["search_space"]] = counts.get(r["search_space"], 0) + 1

    print(f"catalogue rows        {n}")
    print(f"distinct PRIDE accessions {len({r['pxd'] for r in rows})}")
    print()
    for k in ("reference", "transcriptome", "congener", "combined"):
        print(f"  {k:<16} {counts.get(k, 0):>3}")
    print(f"  {'no search space':<16} {counts.get('none', 0):>3}")
    print()

    if blockers:
        print("datasets with NO search space (nothing will process these):")
        for sp in sorted(blockers):
            low = sp.lower()
            if sp in assembled or low in assembled:
                state = "assembly DONE - wire it in"
            elif sp in assembling or low in assembling:
                state = "assembly IN PROGRESS - wait"
            else:
                state = "not in the assembly programme - needs a decision"
            print(f"  {sp:<32} {','.join(sorted(set(blockers[sp]))):<24} {state}")

    if a.tsv:
        with open(a.tsv, "w", newline="") as fh:
            w = csv.DictWriter(fh, fieldnames=list(rows[0].keys()), delimiter="\t",
                               lineterminator="\n")
            w.writeheader()
            w.writerows(rows)
        print(f"\n-> {a.tsv}", file=sys.stderr)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
