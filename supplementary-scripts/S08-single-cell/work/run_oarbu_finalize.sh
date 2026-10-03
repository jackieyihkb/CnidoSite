#!/usr/bin/env bash
# B, stages 8-9: figures + registry, then STOP before any deploy.
#
# Separate from run_oarbu_post.sh on purpose: that script is running, and bash
# reads a script lazily by byte offset, so editing it mid-run can make it execute
# garbage.  This one only waits on its outputs.
#
# The lesson from NVECT_whole_adult: the data files alone are not enough.  That
# deploy shipped manifest/cellmeta/embedding/qc/genes/markers but not the two
# figures from 07_render_umap.py, so cell_atlas.php dropped its
# "UMAP -- this re-analysis" section and the page read as a published-figure-only
# entry.  Stage 07 is therefore NOT optional here.
set -u

ROOT=/mnt/sda/jackie/cnidaria/codex/singlecell
W=$ROOT/work
PY=/mnt/sda/Yan/Anaconda3/envs/SAMap/bin/python
ID=OARBU_symbiotic
LOG=$W/oarbu_finalize.log

log() { echo "[$(date -u +%H:%M:%S)] $*" | tee -a "$LOG"; }
export PYTHONPATH=$ROOT/.pylibs
cd "$ROOT" || exit 1

m=$ROOT/web/singlecell_data/$ID/manifest.json
while [ ! -s "$m" ]; do
  if ! pgrep -f "run_oarbu_post.sh" >/dev/null; then
    log "FATAL: the export never appeared and run_oarbu_post.sh is not running"
    tail -25 "$W/oarbu_post.log" 2>/dev/null
    exit 2
  fi
  sleep 60
done
log "export present"

# ---- 07 static figures ----------------------------------------------------
if [ ! -s "$ROOT/web/singlecell_data/$ID/umap_celltype.png" ] || \
   [ ! -s "$ROOT/web/singlecell_data/$ID/umap_cluster.png" ]; then
  log "07_render_umap (the step NVECT_whole_adult was missing)"
  "$PY" -u pipeline/07_render_umap.py --dataset "$ID" >>"$LOG" 2>&1 \
    || log "WARN: 07_render_umap failed -- page would lose its re-analysis section"
else
  log "figures already present"
fi

# ---- registry: the dataset is no longer unprocessed ----------------------
# The reason string is only consulted on the "no qc_stats" branch, but leaving it
# would make the registry contradict the qc table.
if grep -qP "^$ID\t" meta/dataset_registry.tsv && \
   grep -qP "^$ID\t.*\tdeposit holds raw reads only" meta/dataset_registry.tsv; then
  cp meta/dataset_registry.tsv "$W/dataset_registry.tsv.bak_oarbu_predeploy"
  "$PY" - <<PY
hdr = open("meta/dataset_registry.tsv").readline().rstrip("\n").split("\t")
i = hdr.index("not_processed_reason")
out = []
for ln in open("meta/dataset_registry.tsv").read().split("\n"):
    if ln.strip():
        f = ln.split("\t")
        if f[0] == "$ID":
            while len(f) < len(hdr): f.append("")
            f[i] = ""
        ln = "\t".join(f)
    out.append(ln)
open("meta/dataset_registry.tsv", "w").write("\n".join(out))
print("cleared not_processed_reason for $ID")
PY
fi

# ---- 06 registry + SQL ----------------------------------------------------
log "06_aggregate_qc_table"
"$PY" -u pipeline/06_aggregate_qc_table.py >>"$LOG" 2>&1 || log "WARN: 06 failed"

# ---- report ---------------------------------------------------------------
log "=== preflight report (nothing deployed) ==="
"$PY" - <<PY 2>&1 | tee -a "$LOG"
import json, os
b = "$ROOT"
m = json.load(open(f"{b}/web/singlecell_data/$ID/manifest.json"))
print("manifest:")
for k in ("dataset_id","species","n_cells","n_genes","n_clusters","n_cell_types",
          "n_samples","annotation_provenance","cell_type_mode"):
    print("   %-22s %s" % (k, m.get(k)))
print("   cell_types:", m.get("cell_types"))
print("   note:", (m.get("annotation_note") or "")[:240])
print("files:")
d = f"{b}/web/singlecell_data/$ID"
for f in sorted(os.listdir(d)):
    p = os.path.join(d, f)
    print("   %-22s %s" % (f, ("%d B" % os.path.getsize(p)) if os.path.isfile(p) else "<dir>"))
print("expression files:", len(os.listdir(f"{d}/expression")))
PY
grep -P "^$ID\t" meta/qc_dataset_table.tsv | cut -f1,2,4,15,18,26,29,33,34 | head -1
log "=== ready to deploy: web/singlecell_data/$ID/ + sql/load_singlecell.sql ==="
