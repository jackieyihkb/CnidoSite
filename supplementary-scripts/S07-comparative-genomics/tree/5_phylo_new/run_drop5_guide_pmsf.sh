#!/usr/bin/env bash
# New unconstrained LG+G guide, followed by LG+C60+G PMSF.
set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
cd -- "$SCRIPT_DIR"

ALIGNMENT="all_OG_bmge_concat_drop5.phylip"
GUIDE_PREFIX="LG_G_guide_drop5"
PMSF_PREFIX="LG_C60_PMSF_G_drop5_newguide"

[[ -x ./iqtree3 ]] || { echo 'ERROR: ./iqtree3 is missing or not executable.' >&2; exit 1; }
[[ -s "$ALIGNMENT" ]] || { echo "ERROR: Missing alignment: $ALIGNMENT" >&2; exit 1; }

# Foreground execution: any failure stops this script before PMSF.
./iqtree3 \
  -s "$ALIGNMENT" \
  -st AA \
  -m LG+G \
  -T 40 \
  --prefix "$GUIDE_PREFIX"

[[ -s "${GUIDE_PREFIX}.treefile" ]] || { echo 'ERROR: Guide tree was not produced.' >&2; exit 1; }

exec ./iqtree3 \
  -s "$ALIGNMENT" \
  -st AA \
  -m LG+C60+G \
  -ft "${GUIDE_PREFIX}.treefile" \
  -bb 1000 --alrt 1000 --bnni \
  -T 40 \
  --prefix "$PMSF_PREFIX"
