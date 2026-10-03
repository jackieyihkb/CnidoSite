#!/usr/bin/env bash
# Run a NEW unconstrained LG+G guide, then LG+C60+G PMSF.
# Run in the directory containing this script, iqtree3 and the drop3 alignment.
set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
cd -- "$SCRIPT_DIR"

ALIGNMENT="all_OG_bmge_concat_drop3.phylip"
GUIDE_PREFIX="LG_G_guide_drop3"
PMSF_PREFIX="LG_C60_PMSF_G_drop3_newguide"

[[ -x ./iqtree3 ]] || { echo 'ERROR: ./iqtree3 is missing or not executable.' >&2; exit 1; }
[[ -s "$ALIGNMENT" ]] || { echo "ERROR: Missing alignment: $ALIGNMENT" >&2; exit 1; }

# No bootstrap needed for this guide-tree step. Failure stops the script.
./iqtree3 \
  -s "$ALIGNMENT" \
  -st AA \
  -m LG+G \
  -T 120 \
  --prefix "$GUIDE_PREFIX"

[[ -s "${GUIDE_PREFIX}.treefile" ]] || { echo 'ERROR: Guide tree was not produced.' >&2; exit 1; }

# Same PMSF model/support/thread options as the original analysis.
# A distinct prefix prevents reuse of checkpoints from an older-guide run.
exec ./iqtree3 \
  -s "$ALIGNMENT" \
  -st AA \
  -m LG+C60+G \
  -ft "${GUIDE_PREFIX}.treefile" \
  -bb 1000 --alrt 1000 --bnni \
  -T 120 \
  --prefix "$PMSF_PREFIX"
