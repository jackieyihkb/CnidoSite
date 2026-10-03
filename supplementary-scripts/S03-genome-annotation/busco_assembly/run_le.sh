#!/bin/bash
# BUSCO **genome** mode for every assembly under in/, against both lineage datasets
# -- the leviathan port of /home/jackie/cnidosite-work/busco/assembly/run_genome.sh.
#
#   usage: run_le.sh [threads] [parallel] [mem_budget_gb] [lineage ...]
#   default:           16          7        1024
#
# Same flags as the site-host script, so the figures are the same measurement:
#   busco -i in/<ABBR>.fna -m genome --lineage_dataset <ld> --offline -o <tag> --out_path <OUT> -c <N>
# Genome mode predicts gene models with miniprot from the lineage's own refseq
# proteins, and those predictions are lineage-specific, so a species has to be run
# once per lineage; the two cannot share work.
#
# Resumable: a species+lineage that already has a short_summary*.json is skipped, so
# an interruption costs at most the runs in flight and the job can just be re-launched.
#
# Order is metazoa first then cnidaria, largest genome first within each.  metazoa_odb12.2
# is the long pole by far (its refseq holds 2,038,580 proteins against cnidaria's 78,015,
# ~26x the miniprot work), so it has to start first or it alone decides the finish time;
# largest-first keeps its long jobs off the tail of the schedule.
#
# 2026-10-02: metazoa's miniprot_align is ~38 CPU-hours per big genome, so at -c 4 a giant
# takes 9.6 h of wall clock.  With $PAR=11 the ten giants held ten of eleven slots for the
# whole night and only one slot was left to work through the other 340 runs -- 2 metazoa
# finished in 9.5 h while 700 cores sat idle.  Threads per job, not job count, is what buys
# throughput here: the slice's task budget is fixed, so cores ~= budget x T/(20+T), and
# T=16 nearly triples the cores for the same budget.
#
# Concurrency is capped twice: by $PAR jobs and by a memory budget, because a genome-mode
# run holds the miniprot index of the whole assembly and that scales with genome size --
# the two >3 GB genomes would otherwise be dispatched alongside everything else.  Peak
# RSS for a 0.4 GB genome measured 2.6 GB, so est = 2 GB + 6x genome-GB is a working
# over-estimate.
WORK=/home/$USER/busco_assembly
OUT=/mnt/sdb/busco_assembly_out
# busco's shebang is `#!/usr/bin/env python3`: the env's bin must come first or it picks
# up a python without the busco module ("No module named 'busco'")
export PATH=/home/$USER/.local/share/mamba/envs/busco6/bin:$PATH
export BUSCO_LINEAGE_SETS="$WORK/lineages"
# Pin every OpenMP/BLAS pool in BUSCO's python stack to one thread.  The numpy in this
# env is OpenBLAS, which by default starts a thread per core -- 128 of them inside each
# multiprocessing worker -- and this user slice is capped at 512 tasks, so those
# pthread_create calls fail with EAGAIN, burying the log in "blas_thread_init:
# pthread_create failed" and leaving jobs half-initialised.  BUSCO's numbers come from
# miniprot and hmmsearch, not from BLAS, so single-threaded BLAS costs nothing
# scientifically while cutting a job's task footprint by more than an order of magnitude.
export OMP_NUM_THREADS=1 OPENBLAS_NUM_THREADS=1 MKL_NUM_THREADS=1 NUMEXPR_NUM_THREADS=1 GOTO_NUM_THREADS=1
THREADS=${1:-16}      # BUSCO scores are thread-count independent; fewer threads per
PAR=${2:-7}         # run just means more genomes in flight inside the same CPU envelope
MEMBUDGET=${3:-1024}          # GB cap on OUR resident set (user-set ceiling)
# Soft ceiling on the whole host's 1-minute load average, as an env var rather than a
# 4th positional so `run_le.sh 4 96 900 <lineage>` keeps its meaning.  $PAR alone caps
# how many jobs *we* hold; this caps how loaded the *machine* may get before we add
# more, which matters because leviathan is shared and a burst launch of a few hundred
# jobs spikes load above the core count for a minute or more.  Set 0 to disable.
# 700 of 768 cores is a congestion brake, not a throttle: $PAR is what keeps us
# polite day to day, this only stops us piling on when someone else is already heavy.
MAXLOAD=${MAXLOAD:-0}
# Hard ceiling on the *machine's* CPU utilisation, as a percentage, measured from
# /proc/stat.  This is the brake that matters on a shared box: leviathan has 768
# cores and another user routinely holds 480+ of them, so the question is never "how
# many jobs do we want" but "how much of the machine is still free".  iowait counts
# as idle here -- it is another tenant's disk queue, not our CPU -- while a host that
# is genuinely 70% busy on CPU stops us adding more.  Set 0 to disable.
MAXCPU=${MAXCPU:-70}
# A task brake, in case the batch is ever launched from a capped slice.  Launched from cron
# (the normal path) it sits in /system.slice/cron.service -- pids.max 629145 for a batch that
# uses ~25 tasks per run -- so this brake simply never fires, which is the correct answer
# there.  It matters only for a session-launched dispatcher, which lands in the user slice
# the admin caps (512, then 1024): "fork: retry: Resource temporarily unavailable" when 60
# jobs were asked for at once, and hmmsearch's "thread creation failed" (SIGABRT, five runs
# lost 2026-10-02 12:01) when the fork storm of the hmmsearch stage filled it.
TASKRESERVE=${TASKRESERVE:-28}
# A job needs ~30 s to reach its full task count (~21-25 tasks at -c 16) and the brake cannot
# see a job it has just launched, so two things are required: the brake must keep room for
# one *full* job above the reserve, and the initial fill must be staggered.  On 2026-10-02
# the stagger existed but counted job-list *positions*: the ~11 skipped/in-flight tags at the
# head of the list expired it before the first job ever started, twelve jobs came up in ten
# seconds, pids.current reached 511/512, and the kernel began refusing forks for every
# process in the slice -- which fails the runs already in flight, not just the new ones.
TASKNEED=$(( TASKRESERVE + THREADS + 6 ))
# Only a few seconds are needed now: the stagger exists to stop a burst, and the CPU brake
# re-samples every 5 s inside its hold loop.  25 s x a wide $PAR would spend longer filling
# the queue than the cnidaria runs take to finish (39 x 25 s = 16 min).
STAGGER=${STAGGER:-10}
shift 3 2>/dev/null || true
# Only the 4th argument onward are lineages.  Two wrong ways, both tried here 2026-10-02:
# plain ${*} swept the three numeric arguments in (LINS always contained a space, so the
# single-lineage form silently ran both lineages), and ${*:4} is NOT positional slicing --
# bash substring-expands the *joined* $* instead, so it also kept a space and also ran both.
# shift is the reliable form: after the three numeric args, "$*" is exactly the lineages.
shift 3 2>/dev/null || :
LINS="$*"
[ -z "$LINS" ] && LINS="metazoa_odb12.2 cnidaria_odb12"

mkdir -p "$OUT" "$WORK/logs"
cd "$WORK" || exit 1

exec 9>"$WORK/.run.lock"
if ! flock -n 9; then echo "another run is already going -- exiting"; exit 3; fi

one() {
  abbr=$1; lin=$2; ld=$3
  tag="${abbr}__${lin}"
  if ls "$OUT/$tag"/short_summary*.json >/dev/null 2>&1; then echo "SKIP $tag"; return 0; fi
  # Release the dispatcher's lock fds before doing anything.  A job subshell has no use for
  # them, and an inherited flock fd shares the parent's open file description, so an orphaned
  # job keeps .run.lock held after the dispatcher dies -- which blocks the watchdog's relaunch
  # for as long as that job runs (measured 2026-10-02: a restart was refused for >30 min, and
  # the only way out was to wait for the orphans to drain).  Closing it here makes every
  # restart immediate.  The group keeps `exec`'s stderr out of the job's own log.
  { exec 9>&-; } 2>/dev/null || :
  { exec 7>&-; } 2>/dev/null || :
  { exec 8>&-; } 2>/dev/null || :
  # A tag that a live busco already owns must be left alone: this dispatcher may be a
  # watchdog relaunch starting on top of orphaned jobs from a killed one, and `rm -rf` here
  # would delete the output directory out from under that run.  A live busco, not a lock, is
  # the right test because orphans from a dispatcher without this check hold no lock at all.
  # The tag never appears in a dispatcher's own command line, so this cannot match ourselves.
  if pgrep -f -- "busco -i .* -o ${tag} " >/dev/null 2>&1; then
    echo "IN-FLIGHT $tag -- a live busco already owns this tag, skipping"
    return 0
  fi
  rm -rf "$OUT/$tag"
  s=$(date +%s)
  echo "=== $(date '+%F %T') START $tag"
  busco -i "$WORK/in/$abbr.fna" -m genome \
        --lineage_dataset "$ld" --offline \
        -o "$tag" --out_path "$OUT" -c "$THREADS" > "$WORK/logs/$tag.log" 2>&1
  rc=$?
  echo "=== $(date '+%F %T') END $tag rc=$rc elapsed=$(( $(date +%s) - s ))s"
  [ $rc -ne 0 ] && tail -3 "$WORK/logs/$tag.log"
  # BUSCO keeps a bbtools index and the raw hmmer dumps; both are big and useless here
  rm -rf "$OUT/$tag"/*/.bbtools_output "$OUT/$tag"/tmp 2>/dev/null
  return 0
}

ld_of() {
  case "$1" in
    cnidaria_odb12)  echo "$WORK/lineages/cnidaria_odb12" ;;
    metazoa_odb12.2) echo "$WORK/lineages/metazoa_odb12.2" ;;
    *) echo "" ;;
  esac
}

# ---- job list: abbr <TAB> lineage <TAB> lineage_dir, in dispatch order ----
#
# metazoa first, largest genome first -- it is ~26x the miniprot work of cnidaria,
# so its long jobs must not sit at the tail of the schedule.
#
# cnidaria is interleaved into that, smallest genome first, rather than queued behind
# all of metazoa: a cnidaria run is ~9 minutes against metazoa's hours, so interleaving
# drains the whole cnidaria column -- the first BUSCO column of genomeinfo.php -- within
# the first hour while the metazoa runs are already deep in flight.
build_list() {   # $1 lineage, $2 sort key (n = ascending size, nr = descending)
  local lin=$1 key=$2 ld
  ld=$(ld_of "$lin")
  [ -n "$ld" ] && [ -d "$ld" ] || { echo "no lineage dataset for $lin" >&2; return; }
  # the size column must survive: the dispatch loop reads it to estimate memory, and
  # dropping it (cut -f1,2,3) makes every job look like 2 GB, which silently disables
  # the budget for exactly the >3 GB genomes it exists to stagger
  for f in "$WORK"/in/*.fna; do
    [ -s "$f" ] || continue
    printf '%s\t%s\t%s\t%s\n' "$(basename "$f" .fna)" "$lin" "$ld" "$(stat -c %s "$f")"
  done | sort -k4,4"$key"
}

: > "$WORK/logs/jobs.list"
case "$LINS" in
  *" "*)
    build_list metazoa_odb12.2 nr > "$WORK/logs/.j.metazoa"
    build_list cnidaria_odb12  n  > "$WORK/logs/.j.cnidaria"
    awk -F'\t' 'NR==FNR{a[FNR]=$0; n=FNR; next}
                {if (FNR<=n) print a[FNR]; print $0}
                END{for(i=FNR+1;i<=n;i++) print a[i]}' \
        "$WORK/logs/.j.metazoa" "$WORK/logs/.j.cnidaria" >> "$WORK/logs/jobs.list"
    ;;
  *)
    build_list "$LINS" nr >> "$WORK/logs/jobs.list"
    ;;
esac

total=$(wc -l < "$WORK/logs/jobs.list")
# One glob, not one `ls` per job: $OUT is on the spinning array, and a 640-entry walk
# of it blocks the dispatcher for minutes when another user is hammering that disk
# (each `ls` is a separate readdir + stat).  BUSCO writes exactly one short_summary*.json
# per tag, so the file count is the tag count.
# ---- dispatch: keep $PAR jobs, the memory budget, the CPU ceiling,
# ---- and the slice's own task budget satisfied ----
# The brake probes are defined *before* the queue echo that reports them.  bash only
# resolves a function once it has executed the defining line, so with the definition
# below the echo, the echo printed a blank slot count and -- far worse -- every brake
# test ran `[ "" -lt 40 ]`, which is false, so the task brake never fired at all.
tasks_left() {
  local cg pm pc
  # Read pids.max of the slice this dispatcher *actually* runs in, taken from our own cgroup.
  # Hardcoding user-$(id -u).slice was wrong twice over: the watchdog launches from cron, so
  # the whole batch sits in /system.slice/cron.service (pids.max 629145, measured 2026-10-02)
  # while the user slice holds only the codeg harness (~128 of its 1024); and a *session*-
  # launched dispatcher IS in that user slice but under a cap this function could no longer
  # see.  Reading the wrong slice can only ever report "999999 free" -- the same silent-dead-
  # dial failure as the definition-order bug below.  That distinction is not academic: five
  # runs died at 12:01:48-55 with hmmsearch's "thread creation failed" (esl_threads.c:139 ->
  # abort -> SIGABRT -> error code -6) because a session-launched dispatcher's hmmsearch fork
  # storm filled the 512-task user slice, while the same batch launched from cron is uncapped.
  cg=$(sed -n 's/^0:://p' /proc/self/cgroup 2>/dev/null)
  [ -z "$cg" ] && { echo 999999; return; }
  pm=$(cat "/sys/fs/cgroup$cg/pids.max" 2>/dev/null)
  pc=$(cat "/sys/fs/cgroup$cg/pids.current" 2>/dev/null)
  case "$pm" in ''|max) echo 999999; return ;; esac
  [ -z "$pc" ] && { echo 999999; return; }
  echo $(( pm - pc ))
}
load1() { local l; read -r l _ < /proc/loadavg; printf '%s' "${l%.*}"; }
# busy% of the whole machine over a short window, iowait excluded (see MAXCPU above)
cpu_busy_pct() {
  local u1 t1 u2 t2 d
  read -r u1 t1 < <(awk '/^cpu /{printf "%d %d", $2+$3+$4+$7+$8, $2+$3+$4+$5+$6+$7+$8}' /proc/stat)
  sleep 0.3
  read -r u2 t2 < <(awk '/^cpu /{printf "%d %d", $2+$3+$4+$7+$8, $2+$3+$4+$5+$6+$7+$8}' /proc/stat)
  d=$((t2 - t1))
  if [ "$d" -le 0 ]; then echo 0; else echo $(( 100 * (u2 - u1) / d )); fi
}
todo=$(( total - $(ls "$OUT"/*/short_summary*.json 2>/dev/null | wc -l) ))
echo "=== $(date '+%F %T') queue: $todo to run of $total, ${PAR} jobs x ${THREADS} threads, ${MEMBUDGET} GB budget, CPU cap ${MAXCPU}%, $(tasks_left) task slots free"

governed=0
declare -A mem_of_pid
running=0; mem=0; launched=0; started=0
while IFS=$'\t' read -r abbr lin ld size; do
  est=$(awk -v b="$size" 'BEGIN{printf "%d", 2 + 6*b/1e9}')
  while [ "$running" -ge "$PAR" ] || [ $((mem + est)) -gt "$MEMBUDGET" ] \
        || { [ "$MAXLOAD" -gt 0 ] && [ "$(load1)" -ge "$MAXLOAD" ]; } \
        || { [ "$MAXCPU" -gt 0 ] && [ "$(cpu_busy_pct)" -ge "$MAXCPU" ]; } \
        || [ "$(tasks_left)" -lt "$TASKNEED" ]; do
    if [ "$governed" -eq 0 ]; then
      if [ "$(tasks_left)" -lt "$TASKNEED" ]; then
        echo "--- $(date '+%F %T') only $(tasks_left) task slots left in our slice ($(sed -n 's/^0:://p' /proc/self/cgroup)) (< ${TASKNEED} = reserve ${TASKRESERVE} + a full ${THREADS}+6 task ramp), holding at $running running"
      elif [ "$MAXCPU" -gt 0 ] && [ "$(cpu_busy_pct)" -ge "$MAXCPU" ]; then
        echo "--- $(date '+%F %T') machine $(cpu_busy_pct)% busy >= ${MAXCPU}%, holding at $running running"
      elif [ "$MAXLOAD" -gt 0 ] && [ "$(load1)" -ge "$MAXLOAD" ]; then
        echo "--- $(date '+%F %T') load $(load1) >= $MAXLOAD, holding at $running running"
      fi
      governed=1
    fi
    for p in "${!mem_of_pid[@]}"; do
      if ! kill -0 "$p" 2>/dev/null; then
        mem=$((mem - ${mem_of_pid[$p]})); unset "mem_of_pid[$p]"; running=$((running - 1))
      fi
    done
    sleep 5
  done
  if [ "$governed" -eq 1 ]; then
    echo "--- $(date '+%F %T') machine $(cpu_busy_pct)% busy, resuming dispatch at $running running"
    governed=0
  fi
  # Will `one` actually start this job, or skip it?  Only real launches count toward the
  # initial-fill stagger -- counting list positions instead is what let twelve jobs come up
  # together on 2026-10-02 (see TASKNEED).  The checks mirror one()'s; a wrong guess here
  # only costs a staggered sleep or a missing one, never a double launch.
  tag="${abbr}__${lin}"; real=1
  if ls "$OUT/$tag"/short_summary*.json >/dev/null 2>&1; then real=0
  elif pgrep -f -- "busco -i .* -o ${tag} " >/dev/null 2>&1; then real=0
  fi
  one "$abbr" "$lin" "$ld" >> "$WORK/logs/run.log" 2>&1 &
  jpid=$!
  if kill -0 "$jpid" 2>/dev/null; then
    mem_of_pid[$jpid]=$est; running=$((running + 1)); mem=$((mem + est))
  else
    echo "--- $(date '+%F %T') fork refused for $abbr ($lin); it will be retried next pass"
    real=0
  fi
  launched=$((launched + 1))
  if [ "$real" -eq 1 ]; then
    started=$((started + 1))
    if [ $((started % 25)) -eq 0 ]; then
      echo "--- $(date '+%F %T') started $started jobs this pass (of $todo pending), $running running, ~${mem} GB, load $(load1)"
    fi
    # Spread the initial fill so each job's ramp is visible to the brake before the next one
    # is weighed; only the first $PAR real launches pay this.
    [ "$started" -lt "$PAR" ] && sleep "$STAGGER"
  fi
done < "$WORK/logs/jobs.list"
wait

# Guard: only a run that actually had a queue may declare the batch finished.  An empty
# job list (from a bad lineage argument) drains instantly, and this marker tells
# collector_loop.sh to stop publishing -- a false one silently ends the site updates.
if [ "$total" -gt 0 ]; then
  echo "ALL GENOME RUNS DONE $(date '+%F %T')" >> "$WORK/logs/run.log"
  echo "ALL GENOME RUNS DONE $(date '+%F %T')  finished=$(ls "$OUT"/*/short_summary*.json 2>/dev/null | wc -l)/$total"
fi
