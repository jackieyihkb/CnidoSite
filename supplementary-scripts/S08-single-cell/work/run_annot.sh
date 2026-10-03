#!/usr/bin/env bash
# Cell-type annotation pipeline for one dataset: 03 -> 04 -> 05 -> 07.
#
# Usage: work/run_annot.sh <dataset_id> [stages...]
#
# With no stages named, the whole chain runs.  Naming them is how a restart
# after a late-stage failure skips 03 -- its output is already correct and its
# input is the deposit, the one part of this worth not reprocessing.
set -u
cd /mnt/sda/jackie/cnidaria/codex/singlecell
PY=/mnt/sda/Yan/Anaconda3/envs/SAMap/bin/python
export PYTHONPATH=$PWD/.pylibs
DS=${1:?dataset_id required}; shift
L=work/${DS}_annot.log
log(){ echo "[$(date -u +%H:%M:%S)] $*" | tee -a "$L"; }
for stage in ${*:-03_qc 04_cluster_annotate 05_export_web 07_render_umap}; do
  log "=== $stage ==="
  $PY pipeline/$stage.py --dataset "$DS" >>"$L" 2>&1
  log "  rc=$?"
done
log "=== $DS complete ==="
