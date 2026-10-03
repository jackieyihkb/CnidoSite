#!/usr/bin/env python
"""Build the new live `_export_index.json` by merging, not replacing.

`web/viewer/index.html` fetches `singlecell_data/_export_index.json` and lists
whatever datasets it names, so this file is the viewer's table of contents.

Our local copy holds 16 entries; live holds 17, because `ACOER_lifecycle` exists
only on the server.  Copying ours over theirs would therefore delete that
dataset from the viewer's list -- the same class of mistake as loading 06's
full-reload SQL, and just as silent.

05_export_web.py already solves it: `write_index(..., replace=False)` merges by
dataset_id, refreshing only the entries passed in and carrying the rest over
verbatim.  So this does not reimplement the merge -- it calls that function,
seeded with the *live* file, passing only the five datasets being deployed.

Usage:
    python work/merge_export_index.py --datasets A,B,C --stage work/idx_stage
"""
import argparse
import importlib.util
import json
import os
import shutil

ROOT = "/mnt/sda/jackie/cnidaria/codex/singlecell"


def load_05():
    spec = importlib.util.spec_from_file_location(
        "exp05", os.path.join(ROOT, "pipeline", "05_export_web.py"))
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    return mod


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--datasets", required=True)
    ap.add_argument("--live-index", required=True,
                    help="a copy of the server's current _export_index.json")
    ap.add_argument("--local-index",
                    default=os.path.join(ROOT, "web", "singlecell_data",
                                         "_export_index.json"))
    ap.add_argument("--stage", required=True,
                    help="directory to write the merged index into")
    a = ap.parse_args()

    want = [d.strip() for d in a.datasets.split(",") if d.strip()]
    wset = set(want)

    live = json.load(open(a.live_index))
    local = json.load(open(a.local_index))
    live_ids = {e["dataset_id"] for e in live}
    mine = [e for e in local if e["dataset_id"] in wset]

    missing = wset - {e["dataset_id"] for e in mine}
    if missing:
        raise SystemExit(f"refusing: no local index entry for {sorted(missing)}")
    carried = sorted(live_ids - wset)
    print(f"live index: {len(live)} entries; refreshing {len(mine)}; "
          f"carrying over {len(carried)}: {', '.join(carried)}")

    # Seed the staging dir with the live file, then let 05's own merge run.
    os.makedirs(a.stage, exist_ok=True)
    dst = os.path.join(a.stage, "_export_index.json")
    shutil.copyfile(a.live_index, dst)
    n = load_05().write_index(a.stage, mine, replace=False)
    print(f"merged write_index -> {n} entries")

    out = json.load(open(dst))
    out_ids = {e["dataset_id"] for e in out}
    if live_ids - out_ids:
        raise SystemExit(f"refusing: merge LOST {sorted(live_ids - out_ids)}")
    for e in out:
        if e["dataset_id"] in wset:
            print(f"  {e['dataset_id']:<18} "
                  + ", ".join(f"{k}={e.get(k)}" for k in
                              ("n_cells", "n_clusters", "n_cell_types",
                               "cell_type_mode", "annotation_provenance")
                              if k in e))
    print(f"OK: {len(out)} entries, nothing lost")


if __name__ == "__main__":
    main()
