#!/usr/bin/env bash
# B, stages 2-7: wait for STARsolo, then merge -> QC -> cluster -> annotate -> export.
#
# `run_oarbu_full.sh` is ALREADY RUNNING and owns the fastq dump + STARsolo.  It
# is not edited here: bash reads a script lazily by byte offset, so rewriting a
# running script can make it execute garbage.  This is a second, separate script
# that only waits on that one's outputs.
#
# Every stage is skipped if its output already exists, so re-running this after a
# failure resumes instead of redoing hours of work.
set -u

ROOT=/mnt/sda/jackie/cnidaria/codex/singlecell
W=$ROOT/work
PY=/mnt/sda/Yan/Anaconda3/envs/SAMap/bin/python
ID=OARBU_symbiotic
LOG=$W/oarbu_post.log

log() { echo "[$(date -u +%H:%M:%S)] $*" | tee -a "$LOG"; }

export PYTHONPATH=$ROOT/.pylibs
cd "$ROOT" || exit 1

# ---- 1. wait for both STARsolo runs ---------------------------------------
for acc in SRR29367137 SRR29367138; do
  m=$W/solo/$acc/Solo.out/Gene/filtered/matrix.mtx
  waited=0
  while [ ! -s "$m" ]; do
    if ! pgrep -f "run_oarbu_full.sh" >/dev/null && [ ! -s "$m" ]; then
      if ! pgrep -f "STAR .*--soloType" >/dev/null; then
        log "FATAL: $acc has no count matrix and nothing is running to make one"
        exit 2
      fi
    fi
    sleep 60; waited=$((waited+60))
    [ $((waited % 900)) -eq 0 ] && log "still waiting for $acc ($((waited/60)) min)"
  done
  log "$acc counts present"
done

# The producer deletes each fastq after its count lands; wait for it to exit so
# we never read a directory it is still rewriting.
while pgrep -f "run_oarbu_full.sh" >/dev/null; do sleep 30; done
log "run_oarbu_full.sh has exited"

# ---- 2. merge the two libraries ------------------------------------------
merged=$ROOT/data/raw/$ID/$ID.h5ad
if [ ! -s "$merged" ]; then
  log "merge STARsolo runs"
  "$PY" -u "$W/merge_oarbu_solo.py" >>"$LOG" 2>&1 || { log "FATAL merge failed"; exit 3; }
else
  log "merge already present"
fi

# ---- 3. QC ----------------------------------------------------------------
if [ ! -s "$ROOT/data/qc/$ID.qc.h5ad" ]; then
  log "03_qc"
  "$PY" -u pipeline/03_qc.py --dataset "$ID" --input "$merged" --format h5ad \
    >>"$LOG" 2>&1 || { log "FATAL 03_qc failed"; exit 4; }
else
  log "qc already present"
fi

# ---- 4. cluster -----------------------------------------------------------
if [ ! -s "$ROOT/data/annotated/$ID.annotated.h5ad" ]; then
  log "04_cluster_annotate"
  "$PY" -u pipeline/04_cluster_annotate.py --dataset "$ID" \
    >>"$LOG" 2>&1 || { log "FATAL 04 failed"; exit 5; }
else
  log "annotation present"
fi

# ---- 5. label transfer ----------------------------------------------------
if [ ! -s "$ROOT/data/annotated/$ID.label_transfer.tsv" ]; then
  log "label transfer from the study's published markers"
  "$PY" -u "$W/oarbu_label_transfer.py" --dataset "$ID" \
    >>"$LOG" 2>&1 || { log "FATAL label transfer failed"; exit 6; }
else
  log "label transfer already present"
fi

# ---- 6. export ------------------------------------------------------------
if [ ! -s "$ROOT/web/singlecell_data/$ID/manifest.json" ]; then
  log "05_export_web"
  "$PY" -u pipeline/05_export_web.py --dataset "$ID" \
    >>"$LOG" 2>&1 || { log "FATAL 05_export_web failed"; exit 7; }
else
  log "export already present"
fi

log "=== B local pipeline complete; NOT deployed ==="
