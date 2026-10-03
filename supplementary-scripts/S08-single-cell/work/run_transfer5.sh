#!/usr/bin/env bash
set -u
cd /mnt/sda/jackie/cnidaria/codex/singlecell
PY=/mnt/sda/Yan/Anaconda3/envs/SAMap/bin/python
export PYTHONPATH=$PWD/.pylibs
L=work/transfer5.log
run() { echo "[$(date -u +%H:%M:%S)] $1 $2" >>$L; shift 2; "$@" >>$L 2>&1; echo "  rc=$?" >>$L; }
run transfer AMURI_regen $PY work/label_transfer_published.py --dataset AMURI_regen --abbr AMURI --norm 'evm\.(model|TU)\.'
for d in NVECT_2month NVECT_neoplasm NVECT_tentacle NVECT_nervous; do
  run transfer $d $PY work/label_transfer_published.py --dataset $d --ref-dataset NVECT_bodywall --xp-bridge --desc-file work/NVECT_gene_desc.tsv
done
for d in NVECT_2month NVECT_neoplasm NVECT_tentacle NVECT_nervous AMURI_regen; do
  run export $d $PY pipeline/05_export_web.py --dataset $d
  run render $d $PY pipeline/07_render_umap.py --dataset $d
done
echo "[$(date -u +%H:%M:%S)] === done ===" >>$L
