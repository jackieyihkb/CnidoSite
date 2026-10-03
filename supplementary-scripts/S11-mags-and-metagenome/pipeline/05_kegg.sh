#!/usr/bin/env bash
# Stage 05 -- KEGG Orthology assignment with Kofam, and KO -> pathway.
#
# InterProScan has no KEGG resource, so KEGG comes from Kofam: an HMM search of
# every protein against the 27k Kofam profiles, scored against the per-KO
# thresholds Kofam curates.
#
# Parallelism is by PROCESS, not by thread.  hmmscan's own threading scales
# negatively here -- measured on this database, one process with --cpu 8 did 123
# proteins/min and eight processes with --cpu 1 did 638/min on the same 500
# proteins, for byte-identical hits.  The threads sit in futex_wait on an
# internal lock (191 of 193 idle, ~3 cores of work on a 768-core box).  So the
# protein set is split into chunks, each scanned by its own --cpu 1 process, and
# the domtblouts concatenated.  Doing it the obvious way (one hmmscan with a
# large --cpu) would take ~46 hours instead of ~1.
#
# The threshold step is the part that is easy to get wrong.  Kofam assigns a KO
# only when a hit's score clears that KO's own threshold, and the threshold
# applies to a *different column* depending on the KO's profile_type:
#
#   score_type = domain  -> the domain score (domtblout col 14)
#   score_type = full    -> the full-sequence score (col 8)
#
# Using one column for both, or skipping the threshold entirely, is the
# difference between a defensible KO set and a flood of weak assignments to
# broad enzyme families.  ko_list carries the threshold and the column to use.
set -uo pipefail

source "$(dirname "${BASH_SOURCE[0]}")/env.sh"

refs="$MAG_ROOT/work/refs"
res="$MAG_ROOT/results"
log="$MAG_ROOT/work/05_kegg.log"
kdb="$refs/kofam"
all_faa="$refs/all_mag_proteins.faa"
domtbl="$refs/kofam.domtblout"
chunk_dir="$MAG_ROOT/work/kofam_chunks"
# Default width, so a retry started without KOFAM_CHUNKS does not silently fall
# back to a quarter of the machine.  run_remaining.sh retries this stage with no
# environment of its own, and a hard-coded 128 turns a one-chunk retry into a
# 10-hour one on a 768-core box.
#
# One process per core is the optimum, but hmmscan burns ~1.28 *logical* CPUs per
# process (measured: median 128%, min 118% over 206 processes), so defaulting to
# nproc oversubscribes an SMT box.  Three quarters of the logical CPUs is 576
# here -- near the 512 this pipeline was tuned at -- and each process still gets
# a full core.
_cores=$(nproc 2>/dev/null || echo 4)
CHUNKS="${KOFAM_CHUNKS:-$(( _cores * 3 / 4 ))}"
[ "$CHUNKS" -ge 1 ] 2>/dev/null || CHUNKS=1
mkdir -p "$res" "$chunk_dir"

[ -s "$kdb/kofam.hmm.h3m" ] || { echo "[05] ERROR: Kofam DB not pressed; run stage 00" >&2; exit 1; }
[ -s "$all_faa" ] || { echo "[05] ERROR: $all_faa missing; run stage 04 first" >&2; exit 1; }
[ -x "$HMMER_BIN/hmmscan" ] || { echo "[05] ERROR: hmmscan not found at $HMMER_BIN" >&2; exit 1; }

n_prot=$(grep -c '^>' "$all_faa")
n_ko_db=$(grep -c '^K' "$kdb/ko_list")
echo "[05] hmmscan $n_prot proteins vs Kofam ($n_ko_db KOs), $CHUNKS chunks x --cpu 1" | tee "$log"

# --- split --------------------------------------------------------------------
# Chunk files are kept between runs so a restart re-uses them; only the .tbl
# files decide what is left to do.
if [ -z "$(ls -A "$chunk_dir"/chunk_*.faa 2>/dev/null)" ]; then
    size=$(( (n_prot + CHUNKS - 1) / CHUNKS ))
    awk -v d="$chunk_dir" -v s="$size" '
        /^>/ { n++ }
        { print > (d "/chunk_" int((n - 1) / s) ".faa") }
    ' "$all_faa"
    echo "[05] split into $(ls "$chunk_dir"/chunk_*.faa | wc -l) chunks of <= $size proteins" | tee -a "$log"
else
    echo "[05] reusing $(ls "$chunk_dir"/chunk_*.faa | wc -l) existing chunks" | tee -a "$log"
fi

# --- scan ---------------------------------------------------------------------
todo=$(ls "$chunk_dir"/chunk_*.faa | while read -r f; do [ -s "${f%.faa}.tbl" ] || echo "$f"; done | wc -l)
echo "[05] $todo chunks to scan" | tee -a "$log"
if [ "$todo" -gt 0 ]; then
    ls "$chunk_dir"/chunk_*.faa | xargs -r -P "$CHUNKS" -n 1 "$MAG_ROOT/pipeline/_kofam_chunk.sh"
fi

n_failed=0
for f in "$chunk_dir"/chunk_*.faa; do
    [ -s "${f%.faa}.tbl" ] || { n_failed=$((n_failed + 1)); }
done
if [ "$n_failed" -gt 0 ]; then
    echo "[05] ERROR: $n_failed chunks produced no output; re-run to retry them" >&2
    exit 1
fi

# --- concatenate --------------------------------------------------------------
# Sorted so the combined file is deterministic across runs (the chunks finish in
# arbitrary order, and a stable file makes the stage-06 output reproducible).
cat $(ls "$chunk_dir"/chunk_*.tbl | sort -V) > "$domtbl"
echo "[05] $(grep -vc '^#' "$domtbl") hit lines from $(ls "$chunk_dir"/*.tbl | wc -l) chunks" | tee -a "$log"

# --- threshold + best KO per protein -----------------------------------------
# Emits: protein_id  KO  score  threshold  score_type  n_domains
python3 - "$kdb/ko_list" "$domtbl" "$res/kegg_ko.tsv" <<'PY'
import sys

ko_list, domtbl, out = sys.argv[1], sys.argv[2], sys.argv[3]

# ko_list: knum, threshold, score_type, profile_type, ...
thresh = {}
with open(ko_list) as fh:
    next(fh)
    for line in fh:
        f = line.rstrip("\n").split("\t")
        if len(f) < 3:
            continue
        try:
            thresh[f[0]] = (float(f[1]), f[2])   # (threshold, score_type)
        except ValueError:
            continue   # '-' thresholds exist for KOs with no curated cutoff

# hmmscan domtblout, 23 whitespace-separated fields:
#   1 target name (=KO)   2 target acc   3 tlen   4 query name (=protein)
#   5 query acc   6 qlen   7 full E-value 8 full score   9 full bias
#   10 domain #   11 of   12 c-Evalue   13 i-Evalue   14 domain score ...
# So 0-based [0]=KO, [3]=protein, [7]=full score, [13]=domain score.
best = {}   # protein -> (score, ko, threshold, score_type)
n_lines = 0
with open(domtbl) as fh:
    for line in fh:
        if line.startswith("#"):
            continue
        f = line.split()
        if len(f) < 22:
            continue
        n_lines += 1
        ko, prot = f[0], f[3]
        if ko not in thresh:
            continue           # no curated cutoff -> not assignable by Kofam
        t, stype = thresh[ko]
        score = float(f[13]) if stype == "domain" else float(f[7])
        if score < t:
            continue
        prev = best.get(prot)
        if prev is None or score > prev[0]:
            best[prot] = (score, ko, t, stype)

with open(out, "w") as fh:
    fh.write("protein_id\tko\tscore\tthreshold\tscore_type\n")
    for prot, (score, ko, t, stype) in sorted(best.items()):
        fh.write(f"{prot}\t{ko}\t{score:.1f}\t{t}\t{stype}\n")

print(f"parsed {n_lines} domtbl lines -> {len(best)} proteins with a KO", file=sys.stderr)
PY

n_ko=$(($(wc -l < "$res/kegg_ko.tsv") - 1))
pct=$(awk -v h="$n_ko" -v p="$n_prot" 'BEGIN{printf "%.1f", 100*h/p}')
echo "[05] done: $n_ko/$n_prot proteins with a KEGG KO ($pct%)" | tee -a "$log"
