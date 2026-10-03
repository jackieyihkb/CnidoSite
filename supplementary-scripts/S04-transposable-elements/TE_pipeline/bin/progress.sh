#!/usr/bin/env bash
# progress.sh - compact status of the TE run, cheap enough to poll.
#
#   bash TE_pipeline/bin/progress.sh
#
# Reports, in order: how many species have produced their final TE table, which
# EDTA phase each unfinished species is in, and whether the supervisor is alive.
# Phase is read from the last "Start to find X" line in each species' EDTA log --
# that is the module currently executing, so it is a real signal, not a guess.
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

total=0
while IFS=$'\t' read -r sp g _; do
  [[ -s "$TE_ROOT/$g" ]] && total=$((total + 1))
done < <(tail -n +2 "$TE_PIPE/config/species_manifest.tsv")

# "finished" must mean "delivers something".  A table holding only its header
# happens when the ANNO stage ran with a TMPDIR that had been deleted: `sort`
# fails, the gff3 keeps just the header block (1,781 B -- still non-empty, so
# 20_edta.sh's `-s` test passes), and every downstream file is 0 bytes.  Count
# those separately so they can never hide inside a healthy-looking number.
done_n=0; empty_list=""
for f in "$TE_RESULTS"/TE_info/*/*.TE_info.tsv; do
  [[ -s "$f" ]] || continue
  if (( $(wc -l < "$f") > 1 )); then done_n=$((done_n + 1))
  else empty_list+=" $(basename "$(dirname "$f")")"; fi
done
printf 'finished: %s/%s\n' "$done_n" "$total"
[[ -n "$empty_list" ]] && printf 'EMPTY(0 rows, needs 34_redo_anno.sh):%s\n' "$empty_list"

declare -A phase
for f in "$TE_WORK"/*/logs/*.edta.log; do
  [[ -e "$f" ]] || continue
  sp=$(basename "$f" .edta.log)
  [[ -s "$TE_RESULTS/TE_info/$sp/$sp.TE_info.tsv" ]] && { phase[DONE]=$(( ${phase[DONE]:-0} + 1 )); continue; }
  last=$(grep -oE 'Start to find [A-Z]+ candidates' "$f" 2>/dev/null | tail -1 | awk '{print $4}')
  phase[${last:-starting}]=$(( ${phase[${last:-starting}]:-0} + 1 ))
done
for k in LTR SINE LINE starting DONE; do
  # `:-` matters: common.sh sets -u, and an empty phase must not abort the script
  [[ -n "${phase[$k]:-}" ]] && printf 'phase %-9s %s\n' "$k" "${phase[$k]}"
done

# "phase LINE 61" says nothing about whether those 61 are moving.  RepeatModeler
# runs a hard 5 rounds (RepeatModeler:79-80, no round options are passed by
# EDTA_raw.pl:576), so counting completed rounds per species turns the phase line
# into a progress bar.  Cheap: one grep over 61 small logs.
declare -A rounds
longest=""; longest_s=0
for d in "$TE_WORK"/*/; do
  sp=$(basename "$d"); g="$sp.renamed.fa.mod"
  rm=$(ls -d "$d/$g.EDTA.raw/LINE/RM_"* 2>/dev/null | head -1)
  [[ -n "$rm" && -f "$rm/rmod.log" ]] || continue
  n=$(grep -cE '^Round Time' "$rm/rmod.log" 2>/dev/null || echo 0)
  rounds[$n]=$(( ${rounds[$n]:-0} + 1 ))
  # Which species is currently in its longest round -- the critical path.
  t=$(grep -E '^Round Time' "$rm/rmod.log" 2>/dev/null | tail -1 | awk '{print $3}')
  [[ -n "$t" ]] || continue
  s=$(echo "$t" | awk -F: '{print $1*3600+$2*60+$3}')
  if (( s > longest_s )); then longest_s=$s; longest="$sp in round $(grep -cE '^RepeatModeler Round' "$rm/rmod.log")/5, previous round took $t"; fi
done
if (( ${#rounds[@]} )); then
  line="LINE rounds:"
  for n in 1 2 3 4 5; do
    [[ -n "${rounds[$n]:-}" ]] && line+=" $n/5 done:${rounds[$n]}"
  done
  echo "$line"
  [[ -n "$longest" ]] && printf 'critical path: %s\n' "$longest"
fi

if pgrep -f 'bin/watchdog.sh' > /dev/null 2>&1; then echo "supervisor: running"; else echo "supervisor: NOT RUNNING"; fi

# Newest completed species, so a change is visible at a glance.
newest=$(ls -t "$TE_RESULTS"/TE_info/*/*.TE_info.tsv 2>/dev/null | head -3)
if [[ -n "$newest" ]]; then
  printf 'latest: %s\n' "$(echo "$newest" | xargs -n1 dirname | xargs -n1 basename | tr '\n' ' ')"
fi

exit 0
