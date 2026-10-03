#!/usr/bin/env python3
"""run_all.py - schedule the per-species TE pipeline.

Throughput comes from running several species at once, not from one species
with many threads: EDTA always runs RepeatModeler over the whole genome (the
"LINE candidates" module of EDTA_raw) and RepeatScout/RECON inside it are
largely single-threaded, so extra threads past a point buy nothing.

Two limits govern dispatch:
    --max-jobs N        concurrent species
    --mem-budget GB     sum of estimated peak RSS (RepeatModeler peaks at
                        roughly 24x the genome size)

Species closest to a deliverable are started first, and within a stage the big
genomes first (they dominate the makespan and must not be left until the end).

Longest-first alone was wrong once the queue was a *resumed* one.  A species
whose LINE module is already done is hours from a TE table; one still inside
RepeatModeler needs rounds 4 and 5 first, which measured 14-32 h and 23-31 h.
Ordering by genome size buried the finished ones: the six smallest species that
had already left LINE (Xenia_sp, Haliclystus_octoradiatus, Cassiopea_sp_PORT0000214,
Mastigias_papua, Pelagia_noctiluca, Aurelia_aurita) had -threads of only 11-18,
so they sorted to the very back of a 52-deep queue and would not have been
dispatched until the end -- despite being among the cheapest to finish.

    python3 run_all.py                       # everything in the manifest
    python3 run_all.py --only Nematostella_vectensis Actinernus_sp
    python3 run_all.py --dry-run
    python3 run_all.py --status
"""
import argparse
import glob
import os
import subprocess
import sys
import time
from datetime import datetime

ROOT = os.environ.get("TE_ROOT", "/mnt/sda/jackie/cnidaria/codex/genome_TE")
PIPE = os.path.join(ROOT, "TE_pipeline")
WORK = os.environ.get("TE_WORK", os.path.join(ROOT, "te_work"))


def load_conf():
    """Apply config/pipeline.conf so `python3 run_all.py` alone is correct.

    pipeline.conf is the single source of truth for the whole pipeline; the
    scheduler used to hard-code its own defaults and would silently disagree
    with it (e.g. max_jobs 12 vs the configured 16).  Real environment
    variables still win, so one-off overrides keep working.
    """
    import re
    path = os.path.join(PIPE, "config", "pipeline.conf")
    if not os.path.exists(path):
        return
    pat = re.compile(r'^\s*export\s+(\w+)=["\']?\$\{\w+:-([^}]*)\}["\']?\s*$')
    with open(path) as fh:
        for line in fh:
            line = line.split("#", 1)[0]      # every setting carries a trailing comment
            m = pat.match(line)
            if m and m.group(1) not in os.environ:
                os.environ[m.group(1)] = m.group(2)


def has_genome(sp, rec):
    """True when the assembly for this species is present on disk."""
    g = rec.get("genome", "")
    if not g:
        return False
    return os.path.exists(os.path.join(ROOT, g))


def load_manifest():
    rows = {}
    with open(os.path.join(PIPE, "config", "species_manifest.tsv")) as fh:
        next(fh)
        for line in fh:
            p = line.rstrip("\n").split("\t")
            if len(p) >= 7:
                rows[p[0]] = {"genome": p[1], "gff3": p[2], "kind": p[3],
                              "display": p[6]}
    return rows


def scan_sizes():
    sizes = {}
    path = os.path.join(PIPE, "config", "genome_scan.tsv")
    if os.path.exists(path):
        with open(path) as fh:
            for line in fh:
                p = line.rstrip("\n").split("\t")
                if len(p) >= 3:
                    sizes[p[0][:-6] if p[0].endswith(".fa.gz") else p[0]] = int(p[2])
    return sizes


def log(msg):
    print(f"[{datetime.now():%F %T}] {msg}", flush=True)


def stage_rank(sp):
    """How far past the LINE stage this species is; higher = closer to done.

    3  whole-genome annotation exists -- the deliverable itself is imminent
    2  LINE finished (consensi.fa.classified), EDTA is in TIR/LTR now
    1  still inside the LINE module, i.e. still in RepeatModeler

    Same test as throttle.py:stage_rank; that file is the global concurrency
    limit, this one only decides dispatch order, so a drift between them costs
    ordering rather than correctness.
    """
    d = [r for r in glob.glob(os.path.join(WORK, sp, "*EDTA.raw"))
         if "moved_to_nvme" not in r]
    if not d:
        return 1
    if glob.glob(os.path.join(WORK, sp, "*EDTA.anno", "*TEanno.gff3")):
        return 3
    if glob.glob(os.path.join(d[0], "LINE", "RM_*", "consensi.fa.classified")):
        return 2
    return 1


def lock_held(sp):
    """True when another run_species.sh already owns this species.

    `flock -n` in a throwaway subprocess: if the lock is free we take it and the
    child exits immediately, releasing it; if it is held we learn that without
    disturbing the holder.  run_species.sh still does its own flock -- this is
    only a pre-check.

    Without it the scheduler spawns a process per queued species every poll just
    to watch it exit 75, which buries the log in "dispatched / held by another
    job" pairs and makes a healthy run look broken.
    """
    d = os.path.join(WORK, sp)
    if not os.path.isdir(d):
        return False
    try:
        r = subprocess.run(["flock", "-n", os.path.join(d, ".lock"), "true"],
                           stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    except OSError:
        return False                   # no flock(1): fall back to spawning
    return r.returncode != 0


def est_mem_gb(bp, factor):
    return max(6, int(bp / 1e9 * factor))


def threads_for(bp, cap):
    return max(8, min(cap, bp // 20_000_000))


def status(man):
    print(f"{'SPECIES':<26} {'STATE':<10} {'N_TE':>10}  LOG")
    for sp in sorted(man):
        res = os.path.join(ROOT, "results", "TE_info", sp, f"{sp}.TE_info.tsv")
        n = "-"
        if os.path.exists(res):
            with open(res, "rb") as fh:
                n = sum(1 for _ in fh) - 1
        # TEanno.gff3 lives in <genome>.EDTA.anno/, not next to the genome
        gff = os.path.join(WORK, sp, f"{sp}.renamed.fa.mod.EDTA.anno",
                           f"{sp}.renamed.fa.mod.EDTA.TEanno.gff3")
        if os.path.exists(gff) and os.path.getsize(gff) > 0:
            st = "annotated"
        elif os.path.isdir(os.path.join(WORK, sp)):
            st = "in-progress"
        else:
            st = "queued"
        if n != "-" and os.path.exists(res):
            st = "DONE"
        print(f"{sp:<26} {st:<10} {n:>10}  te_work/{sp}/logs/{sp}.pipeline.log")


def main():
    load_conf()
    ap = argparse.ArgumentParser()
    ap.add_argument("--only", nargs="*", default=None)
    ap.add_argument("--dry-run", action="store_true")
    ap.add_argument("--status", action="store_true")
    ap.add_argument("--max-jobs", type=int, default=int(os.environ.get("TE_MAX_JOBS", 12)))
    ap.add_argument("--mem-budget", type=float,
                    default=float(os.environ.get("TE_MEM_BUDGET_GB", 900)))
    ap.add_argument("--mem-factor", type=float,
                    default=float(os.environ.get("MEM_GB_PER_GENOME_GB", 24)))
    ap.add_argument("--thread-cap", type=int,
                    default=int(os.environ.get("EDTA_THREADS", 32)))
    ap.add_argument("--poll", type=int, default=30)
    ap.add_argument("--max-attempts", type=int, default=4,
                    help="rounds a species may fail before being reported as failed")
    a = ap.parse_args()

    man = load_manifest()
    sizes = scan_sizes()
    if a.status:
        return status(man)

    # Every species with an assembly gets a TE library, whether or not a gene
    # annotation has arrived.  The expensive half of the pipeline (EDTA) needs
    # only the genome; the gene join in 30_te_table.py is cheap and can be
    # re-run for a species the moment its GFF lands.  Gating EDTA on the GFF
    # would have left the 31 species without one -- Actinernus_sp among them --
    # with no TE data at all, and thrown away weeks of compute.
    todo = [sp for sp in man if has_genome(sp, man[sp])]
    if a.only:
        unknown = set(a.only) - set(man)
        if unknown:
            sys.exit(f"unknown species: {sorted(unknown)}")
        todo = list(a.only)

    done = lambda sp: os.path.exists(
        os.path.join(ROOT, "results", "TE_info", sp, f"{sp}.TE_info.tsv"))
    todo = [sp for sp in todo if not done(sp)]
    # Closest to a deliverable first; longest genome first within a stage.  Both
    # the initial dispatch and the requeue path sort this way, so a species that
    # fails and comes back keeps its place at the front.
    todo.sort(key=lambda sp: (-stage_rank(sp), -sizes.get(sp, 0)))

    log(f"queue: {len(todo)} species, max_jobs={a.max_jobs}, "
        f"mem_budget={a.mem_budget:.0f}GB, thread_cap={a.thread_cap}")

    if a.dry_run:
        total = 0
        for sp in todo:
            bp = sizes.get(sp, 0)
            m = est_mem_gb(bp, a.mem_factor)
            total += m
            log(f"  would run {sp:<26} {bp/1e6:7.0f}Mb  threads={threads_for(bp, a.thread_cap):<3} "
                f"est_peak={m}GB")
        log(f"total estimated peak if all ran at once: {total}GB")
        return

    running = {}                       # pid -> (species, mem_gb)
    mem_use = 0.0
    failed = []
    requeue = []
    attempts = {}                      # species -> failed rounds so far
    max_attempts = a.max_attempts

    def reap():
        """Decide each finished job's fate from its OUTPUT, not its exit code.

        Exit codes are advisory here: a job can exit 0 after being blocked by a
        lock, and a lost child cannot be classified at all.  The one fact that
        cannot be faked is whether results/TE_info/<sp>/<sp>.TE_info.tsv exists,
        so that is what "done" means.  Anything else is retried a bounded number
        of times and then reported as failed, so a persistently broken species
        cannot spin forever.
        """
        nonlocal mem_use
        for pid in list(running):
            try:
                wpid, status = os.waitpid(pid, os.WNOHANG)
            except ChildProcessError:
                wpid, status = pid, None   # outcome unknowable; fall through to artifacts
            if wpid == 0:
                continue
            sp, m = running.pop(pid)
            mem_use -= m
            rc = None if status is None else (
                os.waitstatus_to_exitcode(status) if hasattr(os, "waitstatus_to_exitcode")
                else (status >> 8))

            if done(sp):
                log(f"{sp}: finished")
                attempts.pop(sp, None)
            elif rc == 75:                 # EX_TEMPFAIL: another job owns it right now
                log(f"{sp}: held by another job — will retry")
                requeue.append(sp)
            else:
                n = attempts[sp] = attempts.get(sp, 0) + 1
                if n >= max_attempts:
                    log(f"{sp}: FAILED after {n} attempts (rc={rc}), no TE table")
                    failed.append(sp)
                else:
                    log(f"{sp}: rc={rc}, no TE table yet — attempt {n}/{max_attempts}")
                    requeue.append(sp)
        return len(running)

    def launch(sp):
        nonlocal mem_use
        bp = sizes.get(sp, 0)
        m = est_mem_gb(bp, a.mem_factor)
        thr = threads_for(bp, a.thread_cap)
        logf = open(os.path.join(PIPE, "logs", f"{sp}.out"), "a")
        p = subprocess.Popen(
            ["bash", os.path.join(PIPE, "bin", "run_species.sh"), sp, str(thr)],
            stdout=logf, stderr=subprocess.STDOUT, start_new_session=True)
        running[p.pid] = (sp, m)
        mem_use += m
        log(f"{sp}: dispatched pid={p.pid} threads={thr} est_peak={m}GB "
            f"(jobs={len(running)}, mem={mem_use:.0f}GB)")

    try:
        while todo or running:
            reap()
            # A species held by another job never gets launched here, so nothing
            # else would ever notice it finished.  Retire it as soon as its TE
            # table appears -- otherwise the queue never drains and this process
            # never exits, which is what the watchdog waits on to merge.
            still = []
            for sp in todo:
                if done(sp):
                    log(f"{sp}: finished (TE table appeared)")
                    attempts.pop(sp, None)
                else:
                    still.append(sp)
            todo = still
            n_requeued = len(requeue)
            if requeue:
                # put them back, keeping longest-genome-first order
                todo.extend(requeue)
                requeue.clear()
                todo.sort(key=lambda sp: -sizes.get(sp, 0))
            progressed = False
            held = []
            while todo:
                nxt = todo[0]
                m = est_mem_gb(sizes.get(nxt, 0), a.mem_factor)
                if len(running) >= a.max_jobs:
                    break
                if running and mem_use + m > a.mem_budget:
                    log(f"{nxt}: holding, {mem_use:.0f}+{m}GB > {a.mem_budget:.0f}GB budget")
                    break
                if lock_held(nxt):
                    # Already being worked on elsewhere (an earlier scheduler
                    # round, a manual run_species.sh, ...).  Leave it queued and
                    # let its TE table appearance end it, rather than spawning a
                    # process that is guaranteed to exit 75.
                    held.append(todo.pop(0))
                    continue
                todo.pop(0)
                launch(nxt)
                progressed = True
            if held:
                todo.extend(held)
                todo.sort(key=lambda sp: -sizes.get(sp, 0))
            # Sleep the long poll whenever nothing could be started, or when the
            # only thing that happened was a lock conflict -- otherwise we would
            # spin on species an existing job already owns.
            if not progressed or n_requeued:
                time.sleep(a.poll)
            else:
                time.sleep(5)
    except KeyboardInterrupt:
        log("interrupted — waiting for running jobs to finish (Ctrl-C again to orphan them)")
    finally:
        for pid in list(running):
            try:
                os.waitpid(pid, 0)
            except ChildProcessError:
                pass
        rc_file = os.path.join(PIPE, "logs", "run_all.rc")
        with open(rc_file, "w") as fh:
            fh.write("\n".join(failed) + "\n" if failed else "")
        log(f"scheduler finished. failed species: {failed or 'none'}")

    if not failed:
        subprocess.run(["python3", os.path.join(PIPE, "bin", "90_merge.py")])


if __name__ == "__main__":
    main()
