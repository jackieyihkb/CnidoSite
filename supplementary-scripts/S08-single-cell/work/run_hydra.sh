#!/usr/bin/env bash
# Cell-type annotation for HVULG_siebert_atlas from the study's own published
# labels (SCP260 "Whole Genome Clustering").  03 joins the table into
# obs['cell_type']; 04 then records annotation_provenance='published'.
#
# Stages can be named as arguments; with none, the whole chain runs.  Naming
# them is how a restart after a late-stage failure skips 03 -- its output is
# already correct and its input is the deposit, which is the one part of this
# worth not reprocessing.
set -u
cd /mnt/sda/jackie/cnidaria/codex/singlecell
PY=/mnt/sda/Yan/Anaconda3/envs/SAMap/bin/python
export PYTHONPATH=$PWD/.pylibs
L=work/hydra_annot.log
DS=HVULG_siebert_atlas
log(){ echo "[$(date -u +%H:%M:%S)] $*" | tee -a "$L"; }
for stage in ${*:-03_qc 04_cluster_annotate 05_export_web 07_render_umap}; do
  log "=== $stage ==="
  $PY pipeline/$stage.py --dataset $DS >>"$L" 2>&1
  log "  rc=$?"
done
log "=== hydra complete ==="
