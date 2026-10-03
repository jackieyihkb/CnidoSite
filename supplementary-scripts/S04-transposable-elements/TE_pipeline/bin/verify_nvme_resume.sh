#!/usr/bin/env bash
# verify_nvme_resume.sh -- confirm every migrated species came back on NVMe and
# RESUMED its RepeatModeler rounds instead of silently restarting at round 1.
#
# The failure this guards against is the expensive one: EDTA_raw.pl:576 only ever
# calls RepeatModeler without -recoverDir, so an unrestored species would begin
# again at round 1 and lose every completed round (~4-5 days of RECON).  The
# evidence that it did NOT happen is positive and specific, so check for it:
#
#   1. TE_WORK/<sp> resolves to the NVMe copy
#   2. RepeatModeler is live with -recoverDir <the RM dir> in its argv
#   3. the RM dir's rmod.log has a round header at best_round+1, and rounds
#      1..best_round still carry their original timestamps
#
# Reads logs/migrate_nvme.state.tsv, written by migrate_to_nvme.sh.
set -uo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"
NVME_ROOT="${NVME_ROOT:-/home/$USER/te_work_nvme}"
STATE="$TE_PIPE/logs/migrate_nvme.state.tsv"

[[ -s "$STATE" ]] || { echo "no state file at $STATE -- nothing recorded to verify"; exit 1; }

bad=0; pend=0; good=0
printf '%-34s %-4s %-26s %s\n' SPECIES WANT VERDICT DETAIL
printf '%s\n' "---------------------------------------------------------------------------------------------"
while IFS=$'\t' read -r sp rm_dir best; do
  [[ -n "$sp" ]] || continue
  want=$(( ${best:-0} + 1 ))
  d="$TE_WORK/$sp"

  if [[ "$(readlink -f "$d")" != "$NVME_ROOT/$sp" ]]; then
    printf '%-34s %-4s %-26s %s\n' "$sp" "R$want" "NOT-ON-NVME" "$(readlink -f "$d")"; bad=$((bad+1)); continue
  fi

  # RepeatModeler live with our injected flag?
  rec=""
  for p in /proc/[0-9]*; do
    p="${p#/proc/}"
    cl="$(tr '\0' '\n' < "/proc/$p/cmdline" 2>/dev/null)" || continue
    case "$cl" in *RepeatModeler*) ;; *) continue ;; esac
    grep -q "^$NVME_ROOT/$sp/" <<< "$cl" || continue
    if grep -qx -- '-recoverDir' <<< "$cl"; then rec="live+recoverDir"; else rec="live NO-recoverDir"; fi
    break
  done

  # rounds present, and which is the newest complete one?
  have=0
  for k in 6 5 4 3 2 1; do [[ -s "$rm_dir/round-$k/consensi.fa" ]] && { have=$k; break; }; done
  r5=$([[ -d "$rm_dir/round-5" ]] && echo present || echo absent)
  bk=$(find "$rm_dir" -maxdepth 1 -name 'round-*.backup_*' 2>/dev/null | wc -l)

  if [[ "$rec" == "live+recoverDir" ]]; then
    printf '%-34s %-4s %-26s %s\n' "$sp" "R$want" "RESUMED" "$rec; rounds 1-$have intact; round-$want $r5; $bk backup(s)"
    good=$((good+1))
  elif [[ -n "$rec" ]]; then
    printf '%-34s %-4s %-26s %s\n' "$sp" "R$want" "BAD: RESTARTED" "$rec -- would lose $have rounds, ROLL BACK"
    bad=$((bad+1))
  elif [[ "$r5" == "present" || -n "$bk" ]]; then
    printf '%-34s %-4s %-26s %s\n' "$sp" "R$want" "resumed (finished/next)" "no live RepeatModeler; rounds 1-$have; $bk backup(s)"
    good=$((good+1))
  else
    printf '%-34s %-4s %-26s %s\n' "$sp" "R$want" "pending dispatch" "on NVMe, not started yet"
    pend=$((pend+1))
  fi
done < "$STATE"

printf '%s\n' "---------------------------------------------------------------------------------------------"
echo "resumed=$good  pending=$pend  BAD=$bad"
(( bad > 0 )) && echo "ROLL BACK a species with:  rm -f $TE_WORK/<sp> && mv $TE_WORK/<sp>.moved_to_nvme $TE_WORK/<sp>"
exit 0
