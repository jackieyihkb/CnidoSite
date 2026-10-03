#!/usr/bin/env bash
# run_species.sh <species> [threads]
#
# Run the whole per-species chain: prepare -> EDTA -> TE table.
# Every stage is idempotent and skips itself when its output already exists, so
# this is safe to re-run after a crash or after new data lands in the directory.
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

sp="${1:?usage: run_species.sh <species> [threads]}"
thr="${2:-$EDTA_THREADS}"
d="$(sp_dir "$sp")"
mkdir -p "$d/logs" "$TE_RESULTS/TE_info/$sp"

# Serialise per species so the scheduler and a manual re-run cannot collide.
#
# EX_TEMPFAIL (75) is deliberate: "someone else owns this species right now" is
# NOT success.  Exiting 0 here would let a scheduler that re-dispatches an
# already-running species conclude the whole queue was finished and merge an
# empty database.  run_all.py re-queues 75 instead of counting it as done.
exec 9>"$d/.lock"
if ! flock -n 9; then
  log "$sp" "another job already holds the lock — skipping"
  exit 75
fi

t0=$(date +%s)
mainlog="$d/logs/$sp.pipeline.log"
exec >> "$mainlog" 2>&1

log "$sp" "=== start (threads=$thr, sensitive=$EDTA_SENSITIVE, promoter=${PROMOTER_UP}bp) ==="

bash "$TE_PIPE/bin/10_prepare.sh" "$sp"   || { log "$sp" "FAILED at prepare"; exit 1; }
bash "$TE_PIPE/bin/20_edta.sh"    "$sp" "$thr" || { log "$sp" "FAILED at EDTA";  exit 1; }

# Copy the library and annotation out of the scratch dir into results/
# (the anno-stage files live in <genome>.EDTA.anno/ -- see edta_anno_file())
libdir="$TE_RESULTS/TE_lib/$sp"; mkdir -p "$libdir"
for f in "$(sp_edta_lib "$sp")" "$(sp_edta_gff "$sp")" \
         "$(sp_edta_split "$sp")" "$(sp_edta_sum "$sp")"; do
  [[ -s "$f" ]] && cp -f "$f" "$libdir/"
done

python3 "$TE_PIPE/bin/30_te_table.py" "$sp" || { log "$sp" "FAILED at TE table"; exit 1; }

# The renamed genome copy is the only large per-species artefact we can drop
# safely: the EDTA working dirs are what a resume needs, and results/ keeps the
# deliverables.  Comment out to keep it for debugging.
[[ -s "$d/$sp.renamed.fa.mod.MAKER.masked" ]] && rm -f "$d/$sp.renamed.fa.mod.MAKER.masked"

log "$sp" "=== done in $(( ($(date +%s) - t0) / 60 )) min ==="
