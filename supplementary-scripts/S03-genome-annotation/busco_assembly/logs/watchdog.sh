#!/bin/bash
# Keep the batch alive without a session attached.
#
# run_le.sh is normally launched from a terminal session, so if that session goes away the
# dispatcher can be signalled and the queue stops with hundreds of runs left.  This
# watchdog is detached (cron every 7 min, plus a systemd user unit) and only ever acts when
# no dispatcher is running, so it cannot collide with a healthy one.  run_le.sh itself is
# resumable -- finished tags are skipped -- so a relaunch costs only whatever was in flight.
#
# The flock means two watchdogs can never double-launch, and run_le.sh takes the same kind
# of lock on its own behalf.
W=/home/$USER/busco_assembly
OUT=/mnt/sdb/busco_assembly_out
TOTAL=640                       # 320 genomes x 2 lineages
# Operating point, re-derived 2026-10-02 12:15 after measuring what actually binds.
#
# What does NOT bind: tasks.  Launched from cron (this file's only launcher), the batch runs
# in /system.slice/cron.service -- pids.max 629145, measured pids.current 255 with 14 runs
# live -- while user-1018.slice holds only the codeg harness.  A run costs ~25 tasks (16
# miniprot threads + a few) and ~3.3 GB RSS, so PAR=40 is ~1000 tasks and ~170 GB.
#
# What DOES bind: the machine's CPU.  One cnidaria run holds ~6 cores (measured 0.38 cores
# per thread at -c 16), so PAR=40 is ~240 cores = 31% of leviathan's 768; adding the ~24%
# other tenants were using puts us near the ceiling the user set, and $MAXCPU is the
# brake that enforces it from here on.  RAM is not close: the user's ceiling is 900 GB and
# PAR=40 x ~3.3 GB is ~170 GB.
#
# $PAR is what finishes the cnidaria column in one wave: 39 runs were outstanding at 12:15,
# so all of them (plus the 13 already in flight) go at once instead of 13 at a time.
# These must match whatever run_le.sh was last launched with.
PAR=40; THREADS=16; MEMBUDGET=900; export MAXCPU=70; export MAXLOAD=0; export STAGGER=10
# Phase 1 (user priority 2026-10-02): drain the cnidaria_odb12 column before spending slots
# on metazoa, which is 26x more miniprot work per genome.  run_le.sh takes an optional
# lineage argument; without one it runs both.  cnidaria is done when all 320 summaries exist.
CNID_TOTAL=320

exec 8>"$W/.watchdog.lock"
flock -n 8 || { echo "another watchdog is running"; exit 3; }

exec 7>"$W/.run.lock"
# Detect the dispatcher by its lock, never by `pgrep -f run_le.sh`: that pattern also
# matches any shell whose command line merely *mentions* the script -- a heredoc editing it,
# say.  On 2026-10-02 that false positive made every watchdog iteration believe a dispatcher
# was alive, so nothing was relaunched for hours while the batch sat idle and looked fine.
dispatcher_alive() { if flock -n 7; then flock -u 7; return 1; else return 0; fi; }

for i in $(seq 1 2880); do          # 2880 x 60s = 2 days of cover
  done_n=$(ls "$OUT"/*/short_summary*.json 2>/dev/null | wc -l)
  if [ "$done_n" -ge "$TOTAL" ]; then
    echo "$(date '+%F %T') all $done_n summaries present, watchdog exit"
    break
  fi
  if ! dispatcher_alive; then
    # Orphaned busco children used to block this relaunch: the new dispatcher would have
    # `rm -rf`'d a tag directory out from under a live run.  run_le.sh now checks for a live
    # busco owning each tag before it touches it, and releases the dispatcher's lock fds so
    # a dead dispatcher's orphans cannot hold .run.lock either -- so relaunching immediately
    # is safe and the slots their orphans occupy are respected by the task brake.
    cnid_done=$(ls "$OUT"/*__cnidaria_odb12/short_summary*.json 2>/dev/null | wc -l)
    if [ "$cnid_done" -lt "$CNID_TOTAL" ]; then LINARG=cnidaria_odb12; else LINARG=""; fi
    echo "$(date '+%F %T') no dispatcher and only $done_n/$TOTAL done (cnidaria $cnid_done/$CNID_TOTAL) -- relaunching${LINARG:+ phase=cnidaria}"
    # 8>&- and 9>&- keep this process's locks out of the dispatcher's tree: an inherited
    # lock fd is shared, so a leaked one would keep both locks "held" by orphaned busco
    # children long after the dispatcher they belong to is gone.
    cd "$W" && bash run_le.sh "$THREADS" "$PAR" "$MEMBUDGET" $LINARG 8>&- 9>&- >> "$W/logs/watchdog.relaunch.log" 2>&1
    echo "$(date '+%F %T') relaunch returned rc=$? at $done_n/$TOTAL"
  fi
  sleep 60
done
