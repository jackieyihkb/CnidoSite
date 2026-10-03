#!/usr/bin/env python3
"""throttle.py - hold how many species run at once, without throwing work away.

Why this exists
---------------
On 2026-09-27 00:10 the host was at load average 3238 with 791 cores of demand
on 768 cores and ~3000 runnable threads, and not one species had finished for
24 h.  The queue was not short of CPU because it was short of species; it was
short of CPU because 53 species were competing for it.  Cutting the number that
run at once is what makes the survivors go faster.

Why SIGSTOP and not kill
------------------------
Every unfinished species is inside the LINE stage, i.e. inside RepeatModeler,
where rounds 4 and 5 are >95% of the cost (measured: 14-32 h and 23-31 h per
round).  Killing one throws the in-flight round away.  Stopping it does not: the
process tree is simply taken off the run queue and put back later, mid-round.
Stopping is also self-enforcing -- a stopped process still holds its species
flock, so run_all.py classifies it as owned by another job and will not
re-dispatch it while it is paused.

The whole species is signalled via its process GROUP.  run_species.sh is started
with start_new_session=True, so pgid == its pid and every child (EDTA, EDTA_raw,
RepeatModeler, rmblastn, ...) inherits it.  Stopping only the top process would
leave RepeatModeler running.

Who gets to keep running
------------------------
The species closest to finishing, and the first thing that decides that is the
STAGE, not the round.  A species whose LINE module is done (`consensi.fa.classified`
is present) is in TIR/LTR/anno and is hours from a deliverable; a species with
round 4 banked but round 5 still to run is a day and a half away.  Ranking on the
round alone gets this backwards, and did: the first version of this file kept six
species that were still inside LINE and paused nine that had already left it,
purely because the six had a smaller -threads value and the tie-break was
cheapest-first.  So the key is

    (stage rank desc, rounds banked desc, threads asc, name)

where stage rank is 3 = whole-genome anno present, 2 = LINE done / downstream
module running, 1 = still inside LINE.  Round count is the same test the
RepeatModeler recovery block uses (highest round-N/consensi.fa that is non-empty,
`RepeatModeler:675-790`), so it agrees with what the pipeline will resume from
rather than with a directory listing.  Threads break ties so a fixed budget
covers more species.

Nothing here is destructive: every state it can reach is one `--resume-all`
undoes.

    python3 TE_pipeline/bin/throttle.py status
    python3 TE_pipeline/bin/throttle.py apply --dry-run
    python3 TE_pipeline/bin/throttle.py apply
    python3 TE_pipeline/bin/throttle.py watch
    python3 TE_pipeline/bin/throttle.py --resume-all
"""
import argparse
import glob
import os
import signal
import sys
import time
from datetime import datetime

ROOT = os.environ.get("TE_ROOT", "/mnt/sda/jackie/cnidaria/codex/genome_TE")
PIPE = os.path.join(ROOT, "TE_pipeline")
WORK = os.environ.get("TE_WORK", os.path.join(ROOT, "te_work"))
RESULTS = os.path.join(ROOT, "results")

# Sum of the per-species -threads the scheduler handed out.  The pipeline was
# configured for TE_MAX_JOBS=64 ("all 64 at once"), which is more than this host
# can actually run: measured demand was 791 cores on 768 cores with load 3238.
#
# 700 is chosen to just cover the 28 species that have finished LINE (their
# -threads sum to 666), because those are the only ones that can still reach a
# deliverable before the 2026-09-30 deadline -- the 24 still inside LINE need
# rounds 4 and 5, i.e. 1.5-2 more days of RepeatModeler, before the same 8-12 h
# of downstream work starts.  Spending the budget on them instead would finish
# nothing extra.
BUDGET = int(os.environ.get("THROTTLE_THREADS", 700))
MAX_SPECIES = int(os.environ.get("THROTTLE_MAX_SPECIES", 30))
INTERVAL = int(os.environ.get("THROTTLE_INTERVAL", 300))
LOGF = os.path.join(PIPE, "logs", "throttle.log")


def log(msg):
    line = f"[{datetime.now():%F %T}] {msg}"
    # Only echo to stdout on a terminal.  `watch` is normally started with its
    # stdout redirected into this same file, which doubled every line.
    if sys.stdout.isatty():
        print(line, flush=True)
    try:
        os.makedirs(os.path.dirname(LOGF), exist_ok=True)
        with open(LOGF, "a") as fh:
            fh.write(line + "\n")
    except OSError:
        pass


def manifest():
    path = os.path.join(PIPE, "config", "species_manifest.tsv")
    names = []
    with open(path) as fh:
        next(fh)
        for line in fh:
            p = line.rstrip("\n").split("\t")
            if p and p[0]:
                names.append(p[0])
    return set(names)


def is_done(sp):
    """A table with only its header is NOT done -- see watchdog.sh:count_done."""
    f = os.path.join(RESULTS, "TE_info", sp, f"{sp}.TE_info.tsv")
    if not os.path.exists(f):
        return False
    with open(f) as fh:
        return sum(1 for _ in fh) > 1


def species_procs():
    """species -> (pid, pgid, stopped, threads) for each live run_species.sh.

    Scans /proc rather than shelling out to pgrep: `pgrep -f <pattern>` matches
    the invoking shell's own command line, which has caused a phantom extra
    "running species" every time it has been used in this project.
    """
    found = {}
    for name in os.listdir("/proc"):
        if not name.isdigit():
            continue
        pid = int(name)
        try:
            with open(f"/proc/{pid}/cmdline", "rb") as fh:
                argv = [a.decode("utf-8", "replace")
                        for a in fh.read().split(b"\0") if a]
        except OSError:
            continue
        sp = None
        for i, a in enumerate(argv):
            if a.endswith("bin/run_species.sh") and i + 1 < len(argv):
                sp = argv[i + 1]
                break
        if not sp:
            continue
        try:
            with open(f"/proc/{pid}/stat") as fh:
                # comm can contain spaces and parens; everything after the last
                # ')' is positional: state, ppid, pgrp, ...
                rest = fh.read().rsplit(")", 1)[1].split()
            state, pgid = rest[0], int(rest[2])
        except (OSError, IndexError, ValueError):
            continue
        # pid is the session leader run_species.sh created for itself, so the
        # process group is the pid; trust /proc over that assumption anyway.
        try:
            threads = int(argv[-1])
        except (ValueError, IndexError):
            threads = 0
        found[sp] = (pid, pgid, state == "T", threads)
    return found


def raw_dir(sp):
    d = [r for r in glob.glob(os.path.join(WORK, sp, "*EDTA.raw"))
         if "moved_to_nvme" not in r]
    return d[0] if d else None


def good_round(sp):
    """Highest RepeatModeler round with a non-empty consensi.fa (0 if none).

    Same test the recovery block uses, so it agrees with what the pipeline will
    actually resume from rather than with a directory listing.
    """
    raw = raw_dir(sp)
    if not raw:
        return 0
    best = 0
    for k in range(1, 13):
        for c in glob.glob(os.path.join(raw, "LINE", "RM_*",
                                        f"round-{k}", "consensi.fa")):
            try:
                if os.path.getsize(c) > 0:
                    best = max(best, k)
            except OSError:
                pass
    return best


def stage_rank(sp):
    """How far past the LINE stage this species is.  Higher = closer to done.

    3  whole-genome annotation exists -- the deliverable itself is imminent
    2  LINE finished (consensi.fa.classified) and EDTA has moved on to TIR/LTR
    1  still inside the LINE module, i.e. still in RepeatModeler
    """
    raw = raw_dir(sp)
    if not raw:
        return 1
    if glob.glob(os.path.join(WORK, sp, "*EDTA.anno", "*TEanno.gff3")):
        return 3
    if glob.glob(os.path.join(raw, "LINE", "RM_*", "consensi.fa.classified")):
        return 2
    return 1


def signal_group(pgid, sig, sp, what):
    if pgid <= 1 or pgid == os.getpgrp():
        log(f"  REFUSING to {what} {sp}: pgid {pgid} is not a species group")
        return False
    try:
        os.killpg(pgid, sig)
        return True
    except ProcessLookupError:
        return False
    except OSError as e:
        log(f"  {what} {sp} failed: {e}")
        return False


def plan(target_budget, max_species):
    """Decide who runs and who pauses.  Pure: reads state, signals nothing."""
    procs = species_procs()
    known = manifest()
    live = {sp: v for sp, v in procs.items()
            if sp in known and not is_done(sp)}
    items = [{"sp": sp, "pid": pid, "pgid": pgid, "stopped": stopped,
              "threads": thr or 1, "round": good_round(sp),
              "stage": stage_rank(sp)}
             for sp, (pid, pgid, stopped, thr) in live.items()]
    # Closest to finishing first; among equals the cheaper species, so the
    # budget covers more of them.
    items.sort(key=lambda d: (-d["stage"], -d["round"], d["threads"], d["sp"]))

    keep, used = set(), 0
    for d in items:
        if len(keep) >= max_species:
            break
        if used + d["threads"] > target_budget and keep:
            continue                     # a cheaper later species may still fit
        keep.add(d["sp"])
        used += d["threads"]
    return items, keep, used


def apply(target_budget, max_species, dry_run=False):
    items, keep, used = plan(target_budget, max_species)
    n_run = sum(1 for d in items if not d["stopped"])
    log(f"throttle: {len(items)} live species, {n_run} running, "
        f"budget {used}/{target_budget} threads over {len(keep)} species"
        f"{' (dry-run)' if dry_run else ''}")

    paused = resumed = 0
    for d in items:
        want_run = d["sp"] in keep
        if want_run and d["stopped"]:
            if not dry_run and signal_group(d["pgid"], signal.SIGCONT, d["sp"], "resume"):
                log(f"  resume {d['sp']:<26} stage={d['stage']} round={d['round']}"
                    f" threads={d['threads']}")
            resumed += 1
        elif not want_run and not d["stopped"]:
            if not dry_run and signal_group(d["pgid"], signal.SIGSTOP, d["sp"], "pause"):
                log(f"  pause  {d['sp']:<26} stage={d['stage']} round={d['round']}"
                    f" threads={d['threads']} (banked work is kept; nothing lost)")
            paused += 1
    if not dry_run:
        log(f"throttle: {resumed} resumed, {paused} paused; "
            f"{len(keep)} species will be running")
    return items, keep


def status():
    items, keep, used = plan(BUDGET, MAX_SPECIES)
    stage_name = {3: "anno", 2: "post-LINE", 1: "in-LINE"}
    print(f"{'SPECIES':<28} {'STATE':<9} {'STAGE':<10} {'LINE':>5} {'THR':>4}  VERDICT")
    for d in items:
        st = "paused" if d["stopped"] else "running"
        verdict = "keep" if d["sp"] in keep else "pause"
        print(f"{d['sp']:<28} {st:<9} {stage_name[d['stage']]:<10} "
              f"{d['round']:>5} {d['threads']:>4}  {verdict}")
    n_run = sum(1 for d in items if not d["stopped"])
    print(f"\n{n_run} running / {len(items)} live; "
          f"target {len(keep)} species, {BUDGET} threads ({used} planned)")


def resume_all():
    n = 0
    for sp, (pid, pgid, stopped, thr) in species_procs().items():
        if stopped and signal_group(pgid, signal.SIGCONT, sp, "resume"):
            log(f"  resume {sp}")
            n += 1
    log(f"resume-all: {n} species resumed")
    return n


def watch():
    log(f"throttle watch: target {BUDGET} threads / {MAX_SPECIES} species, "
        f"checking every {INTERVAL}s")
    while True:
        try:
            apply(BUDGET, MAX_SPECIES)
        except Exception as e:                      # never let the loop die
            log(f"throttle pass failed: {type(e).__name__}: {e}")
        time.sleep(INTERVAL)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("mode", nargs="?", default="status",
                    choices=["status", "apply", "watch"])
    ap.add_argument("--dry-run", action="store_true")
    ap.add_argument("--threads", type=int, default=BUDGET)
    ap.add_argument("--max-species", type=int, default=MAX_SPECIES)
    ap.add_argument("--resume-all", action="store_true")
    a = ap.parse_args()

    if a.resume_all:
        return 0 if resume_all() >= 0 else 1
    if a.mode == "status":
        return status()
    if a.mode == "apply":
        apply(a.threads, a.max_species, dry_run=a.dry_run)
        return 0
    return watch()


if __name__ == "__main__":
    sys.exit(main())
