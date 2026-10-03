#!/usr/bin/env bash
# 27_fix_ltr_identifier.sh <species> <ltr_dir> [threads] [stall_minutes]
#
# Recover a species whose LTR module is wedged inside bin/LTR.identifier.pl.
#
# The failure
# -----------
# LTR.identifier.pl (LTR_retriever, called at LTR_retriever:815) spawns $threads
# Perl ithreads and every candidate is processed with
#
#     @Blast=qx(bash -c '$exec')          # LTR.identifier.pl:203
#
# i.e. a fork+exec from inside a Perl ithread -- the same unsupported pattern
# that wedges the two LTR scanners (README 8(4)).  Here it deadlocks the final
# `$_->join()` (LTR.identifier.pl:166-168): every thread, including the main
# one, ends up parked in futex_do_wait and the process never exits.
#
# Measured on this host (2026-09-21), same inputs, three processes:
#
#     -threads 30  (live Paramuricea)  defalse 24,801 lines, then frozen >9 h
#     -threads 24  (live Actinernus)   frozen, 7 unreaped zombies
#     -threads  4  (this script)       COMPLETED, defalse 50,792 lines,
#                                      scn.adj 9,189 entries
#
# so it is a stochastic race, not a deterministic property of a thread count:
# 63 other species got through this step.  A fresh process usually finishes.
#
# Two things NOT to do
# --------------------
# 1. Upstream documents a resume that skips this step: uncomment the
#    defalse2scn_adj.pl lines at LTR_retriever:849 and add `-step Trunc`.  It
#    says outright "the risk is that the $index.defalse file may not contain all
#    LTR candidates", and here that risk is not hypothetical: the frozen
#    Paramuricea defalse holds 24,801 of the 50,792 lines a completed run
#    produces, so that route would silently discard ~half the LTR candidates and
#    hand back a library that looks fine.
# 2. Killing the wedged identifier on its own.  LTR_retriever does NOT check its
#    exit status (LTR_retriever:815 is a bare backtick), so it just carries on
#    with whatever scn.adj is on disk -- a header-only file, i.e. "this genome
#    has no LTRs".  So the complete scn.adj must be installed FIRST and only
#    then may the wedged process be killed.
#
# LTR.identifier.pl's output is order-independent (scn.adj is printed from
# `sort keys %scn` and defalse is one record per candidate), and the caller
# de-duplicates with `sort -u`, so a complete re-run replaces a partial one
# exactly.
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

sp="${1:?usage: 27_fix_ltr_identifier.sh <species> <ltr_dir> [threads] [stall_min]}"
dir="${2:?usage: 27_fix_ltr_identifier.sh <species> <ltr_dir> [threads] [stall_min]}"
threads="${3:-8}"
stall_min="${4:-10}"

idx="$sp.renamed.fa.mod"
ID="$EDTA_ENV/share/LTR_retriever/bin/LTR.identifier.pl"

# Absolute, always.  The input files are handed to the identifier as symlinks
# from the scratch dir, and `ln -s` stores the target VERBATIM: a relative $dir
# yields links like `.identifier_fix/p.scn -> te_work/.../x.scn`, which resolve
# against the scratch dir and dangle.  LTR.identifier.pl then dies instantly with
# "No candidate list file!" and the retry loop would just repeat it.
dir=$(readlink -f "$dir") || { echo "no such dir"; exit 1; }
work="$dir/.identifier_fix"

activate_env "$sp" || exit 1

# Same reason as 26_rerun_ltr_retriever.sh: the species lock is held by the
# in-flight chain, so this needs a lock of its own.
mkdir -p "$TE_WORK/$sp"
exec 8>"$TE_WORK/$sp/.lock.ltr_identifier"
flock -n 8 || { log "$sp" "identifier-fix: another fix holds the lock (exit 75)"; exit 75; }

[[ -s "$ID" ]] || { log "$sp" "identifier-fix: no $ID"; exit 1; }
[[ -s "$dir/$idx.retriever.scn" ]] || { log "$sp" "identifier-fix: no $idx.retriever.scn in $dir"; exit 1; }
[[ -s "$dir/$idx.retriever.scn.extend.fa.remained" ]] || { log "$sp" "identifier-fix: no .remained"; exit 1; }
[[ -s "$dir/$idx.retriever.scn.extend.fa.aa.anno" ]] || { log "$sp" "identifier-fix: no .aa.anno"; exit 1; }

# Already complete?  scn.adj always begins with the multi-line header copied
# from the candidate list (~29 lines here), so a header-only file means the
# identifier never reached its final print loop.
# The body (non-'#' lines) is the real signal: LTR.identifier.pl prints it in one
# loop AFTER every thread has joined, so a body means that loop ran.  A
# header-only file is what a wedged or killed run leaves behind.
# `grep -vc` both prints 0 and exits 1 for an all-header file, so capture the
# count and default it rather than letting `|| echo 0` append a second line.
body() { local c; c=$(grep -vc '^#' "$1" 2>/dev/null); echo "${c:-0}"; }

adj="$dir/$idx.retriever.scn.adj"
have=$(body "$adj")
if (( have > 0 )); then
  log "$sp" "identifier-fix: scn.adj already has $have entries - nothing to do"
  exit 0
fi
log "$sp" "identifier-fix: scn.adj has no body yet ($(wc -l < "$adj" 2>/dev/null || echo 0) header lines) - the identifier step never finished"

# The wedged process, identified by its working directory.  Several species run
# at once, so matching on the script name alone would be ambiguous.
wedged_pids() {
  local p
  for p in $(pgrep -f 'LTR[.]identifier[.]pl' 2>/dev/null); do
    [[ "$(readlink -f /proc/$p/cwd 2>/dev/null)" == "$(readlink -f "$dir")" ]] && echo "$p"
  done
}

# ------------------------------------------------------------------ run -----
rm -rf "$work"; mkdir -p "$work"
ln -sf "$dir/$idx.retriever.scn"                     "$work/p.scn"
ln -sf "$dir/$idx.retriever.scn.extend.fa.remained"  "$work/p.remained"
ln -sf "$dir/$idx.retriever.scn.extend.fa.aa.anno"   "$work/p.anno"

attempt=0
while (( attempt < 4 )); do
  attempt=$((attempt + 1))
  log "$sp" "identifier-fix: attempt $attempt, -threads $threads (-u 1.3e-8, same as LTR_retriever:815)"

  # Exactly one identifier may ever touch $work/p.defalse.  A previous attempt's
  # process can outlive its parent (see the `exec` note below), and two writers
  # on one file would interleave records, so clear any stragglers first.
  for p in $(pgrep -f 'LTR[.]identifier[.]pl' 2>/dev/null); do
    [[ "$(readlink -f /proc/$p/cwd 2>/dev/null)" == "$work" ]] || continue
    log "$sp" "identifier-fix: killing leftover identifier $p from an earlier attempt"
    kill -9 "$p" 2>/dev/null
  done
  sleep 1
  rm -f "$work/p.scn.adj" "$work/p.defalse" "$work/p.err"

  start=$(date +%s)
  # `exec` matters: without it bash forks a subshell that then runs perl as a
  # CHILD, so $! is the subshell.  A subshell waits in the kernel and its CPU
  # counters never move, so the freeze detector below would read a constant
  # "0 0" and kill the wrong process after stall_min -- while the real perl
  # worker kept running as an orphan.  With exec, $! IS perl.
  ( cd "$work" && exec perl "$ID" "$idx" -list p.scn -seq p.remained -anno p.anno \
      -flanksim 60 -flankmiss 25 -flankaln 0.6 -minlen 100 \
      -u 1.3e-8 -model K2P -threads "$threads" \
      -blastplus "$EDTA_ENV/bin/" \
      -motif TCCA TGCT TACA TACT TGGA TATA TGTA TGCA \
      > p.defalse 2> p.err ) &
  pid=$!

  # A mis-specified input (a dangling symlink, a wrong path) kills the identifier
  # in under a second with a message on stderr.  Retrying that four times just
  # burns three minutes and hides the reason, so bail out immediately instead.
  sleep 5
  if ! kill -0 "$pid" 2>/dev/null; then
    wait "$pid" 2>/dev/null
    if (( $(date +%s) - start < 5 )); then
      log "$sp" "identifier-fix: died immediately - $(tail -2 "$work/p.err" 2>/dev/null | tr '\n' ' ')"
      log "$sp" "identifier-fix: inputs: $(ls -lL "$work"/p.scn "$work"/p.remained "$work"/p.anno 2>&1 | tr '\n' ' ')"
      exit 1
    fi
  fi

  # Freeze detector.  A wedged identifier still shows up in ps, so liveness has
  # to be measured as *progress*: sample the CPU time and stop calling it alive
  # once it has not moved for stall_min minutes.  (The process is in futex_wait
  # the whole time, so this catches it within one sample interval.)
  last=""; still=0; frozen=0
  while kill -0 "$pid" 2>/dev/null; do
    sleep 60
    # Field 14/15 of /proc/PID/stat are utime/stime.  Ignore an unreadable or
    # all-zero reading rather than counting it as "no progress": a zero reading
    # means we are looking at a process that never ran perl, not at a freeze.
    cur=$(cut -d' ' -f14,15 /proc/$pid/stat 2>/dev/null || echo "")
    [[ -z "$cur" || "$cur" == "0 0" ]] && continue
    if [[ "$cur" == "$last" ]]; then
      still=$((still + 1))
      if (( still >= stall_min )); then
        log "$sp" "identifier-fix: frozen (cpu stuck at '$cur' for ${stall_min}m) - killing and retrying"
        # Children first: once the parent dies they are reparented to init and
        # `pkill -P` can no longer find them, leaving blastn runs behind.
        pkill -9 -P "$pid" 2>/dev/null
        kill -9 "$pid" 2>/dev/null
        frozen=1
        break
      fi
    else
      still=0
    fi
    last="$cur"
  done
  wait "$pid" 2>/dev/null

  # ------------------------------------------------------------- verify ----
  # Success needs BOTH: the run reached its final print loop, and we did not kill
  # it.  Exit status is useless (a killed run and a clean run are both non-zero).
  n=$(body "$work/p.scn.adj")
  if (( !frozen && n > 0 )); then
    log "$sp" "identifier-fix: OK - scn.adj $n entries, defalse $(wc -l < "$work/p.defalse") lines, $(grep -c -v '^#' "$dir/$idx.retriever.scn") candidates in"
    break
  fi
  log "$sp" "identifier-fix: attempt $attempt unsuccessful (frozen=$frozen, $n scn.adj entries) - retrying"
done

if (( frozen || n <= 0 )); then
  log "$sp" "identifier-fix: FAILED after $attempt attempts; nothing installed, live dir untouched"
  exit 1
fi

# ---------------------------------------------------------------- install ---
# Back the partial defalse up rather than deleting it: it is the evidence for
# why this recovery was needed, and it is small.
if [[ -e "$dir/$idx.defalse" ]]; then
  mv "$dir/$idx.defalse" "$dir/$idx.defalse.partial.$(date +%s)"
fi
cp "$work/p.defalse"  "$dir/$idx.defalse"
cp "$work/p.scn.adj"  "$dir/$idx.retriever.scn.adj"
log "$sp" "identifier-fix: installed complete scn.adj ($(wc -l < "$dir/$idx.retriever.scn.adj") lines) + defalse"

# Only now is it safe to release the parent: LTR_retriever is blocked in the
# backtick that started the wedged process, and when it returns it reads the
# files installed above.
killed=0
for p in $(wedged_pids); do
  log "$sp" "identifier-fix: killing wedged identifier pid $p so LTR_retriever can continue"
  kill -9 "$p" 2>/dev/null && killed=$((killed + 1))
done
(( killed )) || log "$sp" "identifier-fix: WARNING - no wedged identifier found; the parent may still be waiting"
log "$sp" "identifier-fix: done - LTR_retriever should now resume and finish the LTR module"
