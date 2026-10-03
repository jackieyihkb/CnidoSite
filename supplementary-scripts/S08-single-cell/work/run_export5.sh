#!/usr/bin/env bash
set -u
cd /mnt/sda/jackie/cnidaria/codex/singlecell
PY=/mnt/sda/Yan/Anaconda3/envs/SAMap/bin/python
export PYTHONPATH=$PWD/.pylibs
L=work/export5.log
for d in NVECT_2month NVECT_neoplasm NVECT_tentacle NVECT_nervous AMURI_regen; do
  echo "[$(date -u +%H:%M:%S)] export $d" >>$L
  $PY pipeline/05_export_web.py --dataset $d >>$L 2>&1
  echo "  rc=$?" >>$L
  echo "[$(date -u +%H:%M:%S)] render $d" >>$L
  $PY pipeline/07_render_umap.py --dataset $d >>$L 2>&1
  echo "  rc=$?" >>$L
done
echo "[$(date -u +%H:%M:%S)] === export5 complete ===" >>$L
