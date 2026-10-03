#!/usr/bin/env bash
# Unconstrained edge-proportional partitioned analysis; no guide required.
set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
cd -- "$SCRIPT_DIR"

[[ -x ./iqtree3 ]] || { echo 'ERROR: ./iqtree3 is missing or not executable.' >&2; exit 1; }
[[ -s all_OG_bmge_concat_drop5.phylip ]] || { echo 'ERROR: Missing drop5 alignment.' >&2; exit 1; }
[[ -s all_OG_bmge_partitions.nex ]] || { echo 'ERROR: Missing partitions.' >&2; exit 1; }

exec ./iqtree3 \
  -s all_OG_bmge_concat_drop5.phylip \
  -st AA \
  -p all_OG_bmge_partitions.nex \
  -m MFP \
  -bb 1000 --alrt 1000 --bnni \
  -T 70 \
  --prefix EdgeProp_MFP_drop5
