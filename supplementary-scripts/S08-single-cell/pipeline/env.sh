#!/usr/bin/env bash
# Environment for the CnidoSite single-cell pipeline.
#
# The pipeline needs scanpy, scrublet, harmonypy and leidenalg.  On this
# machine those are vendored into <project>/.pylibs rather than installed into
# the conda environment, so PYTHONPATH has to point there.  Forgetting it does
# not fail loudly: scrublet and harmonypy silently fall back, and the run
# records "no doublets removed" and "integration: none" while still producing a
# complete-looking atlas.  Always source this file first.
#
#   source pipeline/env.sh
#   $PY pipeline/03_qc.py --dataset AMILL_whole_adult
#
# To use a different interpreter, set CNIDO_PY before sourcing.

_cnido_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

export CNIDO_ROOT="$_cnido_root"
export PYTHONPATH="$_cnido_root/.pylibs${PYTHONPATH:+:$PYTHONPATH}"
export PY="${CNIDO_PY:-/mnt/sda/Yan/Anaconda3/envs/SAMap/bin/python}"

# Verify the optional-but-recorded dependencies are reachable, because a
# missing one changes what the pipeline writes rather than making it crash.
"$PY" - <<'EOF'
import importlib
missing = []
for mod in ("scanpy", "anndata", "scrublet", "harmonypy", "leidenalg", "igraph"):
    try:
        importlib.import_module(mod)
    except Exception:
        missing.append(mod)
if missing:
    import sys
    print("WARNING: not importable: " + ", ".join(missing), file=sys.stderr)
    print("         datasets processed now will record these steps as "
          "unavailable.", file=sys.stderr)
else:
    print("pipeline environment OK")
EOF
