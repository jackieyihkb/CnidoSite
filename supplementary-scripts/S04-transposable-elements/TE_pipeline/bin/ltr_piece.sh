#!/usr/bin/env bash
# ltr_piece.sh <harvest|finder> <ltr_dir> <genome_bn> <piece>
#
# Process ONE sequence piece for one of the two LTR scanners, using that
# scanner's own "-threads 1" code path.
#
# Why this exists
# ---------------
# LTR_HARVEST_parallel and LTR_FINDER_parallel spawn $threads Perl ithreads, and
# when a piece exceeds the per-piece timeout each worker forks a nested copy of
# the scanner (salvage mode).  Forking inside a Perl ithread can deadlock
# threads->join(), which leaves the parent process alive but frozen forever: the
# thread pool is done, the process never exits, and EDTA never advances.
# Catalaphyllia_jardinei and Paramuricea_clavata both wedged this way, burning
# ~9 h of wall-clock while doing nothing.
#
# The scanner's `-threads 1` branch is a completely different, thread-free code
# path (LTR_HARVEST_parallel:108-113, LTR_FINDER_parallel:126-133): it runs the
# scanner over one input and writes <piece>.<kind>.combine.scn directly, with no
# threads and therefore no way to deadlock.  It is also the branch the scanners'
# own salvage mode uses (LTR_HARVEST_parallel:174, LTR_FINDER_parallel:178), so
# the output format is exactly what the combine step expects.
#
# The caller runs this in parallel across pieces with xargs -P and then invokes
# the scanner's own "-next 1" combine, so the assembled result is identical to
# what an uninterrupted run would have produced.
set -u

kind="${1:?usage: ltr_piece.sh <harvest|finder> <ltr_dir> <genome_bn> <piece>}"
ltr="${2:?}"; g="${3:?}"; piece="${4:?}"

case "$kind" in
  harvest)
    prog="$EDTA_ENV/share/LTR_HARVEST_parallel/LTR_HARVEST_parallel"
    sub="$g.harvest"
    # same salvage parameters the scanner uses for a timed-out piece.
    # -gt is passed explicitly: otherwise the scanner resolves genometools with
    # `which gt` and dies with an empty $genometools if PATH lacks the env, which
    # is a silent no-output failure (it writes nothing and the piece just looks
    # "not done").  Pointing at the directory keeps this independent of PATH.
    extra=(-size 50000 -time 30 -try1 0 -gt "$EDTA_ENV/bin/")
    ;;
  finder)
    prog="$EDTA_ENV/share/LTR_FINDER_parallel/LTR_FINDER_parallel"
    sub="$g.finder"
    extra=(-size 50000 -overlap 5000 -time 10 -try1 0)
    ;;
  *) echo "unknown kind: $kind" >&2; exit 2 ;;
esac

cd "$ltr/$sub" || { echo "no such dir: $ltr/$sub" >&2; exit 1; }

# Already done?  Note the test is -e, not -s: a piece that genuinely contains no
# LTR produces an EMPTY (but valid) .scn, and the upstream combine also resumes
# with -e (LTR_HARVEST_parallel:227, LTR_FINDER_parallel:234).  Using -s here
# would retry such pieces forever.
[[ -e "$piece.$kind.scn" ]] && exit 0

# -time is not a global cap in the threads==1 path, so wrap it ourselves: a
# single pathological piece must not be able to stall the whole repair.
timeout -s KILL 1800 perl "$prog" -seq "$piece" "${extra[@]}" -threads 1 \
        > /dev/null 2>&1

# threads==1 writes .combine.scn; upstream's caller then renames it to .scn
# (LTR_HARVEST_parallel:175, LTR_FINDER_parallel:179).
[[ -e "$piece.$kind.combine.scn" ]] && mv -f "$piece.$kind.combine.scn" "$piece.$kind.scn"

[[ -e "$piece.$kind.scn" ]]
