#!/usr/bin/env bash
# 61_publish_watch.sh - one publishing pass.  Run from cron; see below.
#
# The pipeline writes a species' TE table the moment its annotation finishes;
# this puts that table on the website.  Together they answer "as soon as a
# species is done, it is on https://cnidosite.org/TE.php".
#
#   */5 * * * *  bash .../61_publish_watch.sh >> .../logs/publish.log 2>&1
#
# Deliberately NOT a hook inside watchdog.sh:
#   * a separate loop cannot stall the annotation pipeline if the site or the
#     network is down -- watchdog's job is to finish 65 species, and publishing
#     is not allowed to interfere with that;
#   * cron survives a reboot.  This host has already lost a multi-day run to one
#     (2026-09-26), and a nohup'd publisher would have to be restarted by hand.
#
# A pass is cheap when nothing changed: 60_publish_to_site.sh skips any species
# whose source file is unchanged, so an idle tick is two queries and ~1 second.
# It is silent on an idle tick, so the log stays readable over a multi-day run;
# an hourly heartbeat still proves the timer is alive.
set -euo pipefail
export PATH="/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin"
export HOME="${HOME:-/home/$USER}"

source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

LOCK="$TE_ROOT/tmp/publish.lock"
mkdir -p "$TE_ROOT/tmp"

# A first publish loads millions of rows and can outlast the 5-minute tick.
# Rather than queue up behind it, a tick that finds the lock held just leaves.
exec 9>"$LOCK"
if ! flock -n 9; then
  log publish "previous pass still running, skipping this tick"
  exit 0
fi

# Only report ticks that did something.
out=$(bash "$TE_PIPE/bin/60_publish_to_site.sh" 2>&1) || rc=$?
rc="${rc:-0}"
if [ -n "$out" ] && ! { [ "$rc" = 0 ] && [[ "$out" == *"nothing to publish"* ]]; }; then
  printf '%s\n' "$out"
fi

# Hourly heartbeat: without it, a dead timer and an idle timer look the same.
if (( 10#$(date +%M) < 5 )); then
  done_n=0
  for sp in $(cut -f1 "$TE_PIPE/config/species_manifest.tsv" | tail -n +2); do
    f="$TE_RESULTS/TE_info/$sp/$sp.TE_info.tsv"
    [ -s "$f" ] || continue
    (( $(wc -l < "$f") > 1 )) && done_n=$((done_n + 1))
  done
  log publish "heartbeat: $done_n species annotated, publication in sync (rc=$rc)"
fi

exit "$rc"
