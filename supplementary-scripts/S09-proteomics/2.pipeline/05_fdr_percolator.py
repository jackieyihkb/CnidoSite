#!/usr/bin/env python3
"""
05_fdr_percolator.py
--------------------
Merge the Comet PIN files of one dataset and run Percolator to assign
q-values, giving a **dataset-level** target-decoy FDR.

Merging is important: Percolator learns its score weights from the whole
dataset, so running it per file would give less reliable q-values than the
single, dataset-wide run done here.

FDR policy (uniform across every dataset -- this is what the manuscript states):
    PSM-level   q < 0.01
    peptide-level q < 0.01  (best PSM per unique peptide)
Decoys come from Comet (`decoy_search = 1`, reversed, `DECOY_` prefixed),
so the target:decoy ratio is exactly 1:1.

Outputs (4.results/<PXd>/):
    psms.tsv        every target PSM at q<=0.01
    peptides.tsv    best PSM per unique peptide
    fdr_summary.tsv target/decoy counts and surviving numbers

Usage:
    python3 05_fdr_percolator.py PXD009253
    python3 05_fdr_percolator.py --all-searched
"""
from __future__ import annotations

import argparse
import csv
import shutil
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SEARCH = ROOT / "3.work" / "search"
RESULTS = ROOT / "4.results"
CRUX = Path("/home/$USER/.local/share/mamba/envs/proteomics/bin/crux")
Q_THRESHOLD = 0.01


def merge_pins(pins, out_pin: Path):
    """Concatenate PIN files, rewriting SpecId to stay unique across files."""
    header = None
    n = 0
    with out_pin.open("w") as o:
        for p in sorted(pins):
            tag = p.stem
            with p.open() as fh:
                h = fh.readline()
                if header is None:
                    header = h
                    o.write(h)
                for line in fh:
                    f = line.rstrip("\n").split("\t", 1)
                    if len(f) == 2:
                        # prefix SpecId with the source file so scan numbers
                        # cannot collide between files
                        o.write(f"{tag}|{f[0]}\t{f[1]}\n")
                        n += 1
    return n


def run_percolator(pin: Path, out_dir: Path) -> bool:
    # crux percolator refuses to overwrite an existing output directory, which
    # made this stage silently non-re-runnable: the second run failed, left the
    # *previous* run's output in place, and the caller read it as if it were
    # fresh.  Clear the directory first -- the summary tables a stale run leaves
    # behind are worse than no output at all, because nothing downstream can
    # tell that they are out of date.
    if out_dir.exists():
        shutil.rmtree(out_dir)
    out_dir.mkdir(parents=True, exist_ok=True)
    cmd = [str(CRUX), "percolator", "--decoy-prefix", "DECOY_",
           "--output-dir", str(out_dir), str(pin)]
    r = subprocess.run(cmd, capture_output=True, text=True)
    (out_dir / "percolator.run.log").write_text(r.stdout + "\n" + r.stderr)
    return r.returncode == 0


def read_tsv(p: Path):
    if not p.exists():
        return []
    with p.open() as fh:
        return list(csv.DictReader(fh, delimiter="\t"))


def process(pxd: str) -> dict:
    sdir = SEARCH / pxd
    pins = sorted(sdir.glob("*.pin"))
    if not pins:
        print(f"[{pxd}] no PIN files", file=sys.stderr)
        return {}

    rdir = RESULTS / pxd
    rdir.mkdir(parents=True, exist_ok=True)
    merged = rdir / f"{pxd}.merged.pin"
    n_psm = merge_pins(pins, merged)

    if not run_percolator(merged, rdir / "percolator"):
        print(f"[{pxd}] percolator FAILED (see percolator/percolator.run.log)",
              file=sys.stderr)
        return {}

    pdir = rdir / "percolator"
    psms = read_tsv(pdir / "percolator.target.psms.txt")
    peps = read_tsv(pdir / "percolator.target.peptides.txt")

    def keep_q(rows):
        out = []
        for r in rows:
            try:
                if float(r.get("q-value", "1")) <= Q_THRESHOLD:
                    out.append(r)
            except (TypeError, ValueError):
                continue
        return out

    psms_ok, peps_ok = keep_q(psms), keep_q(peps)

    with (rdir / "psms.tsv").open("w", newline="") as fh:
        w = csv.writer(fh, delimiter="\t", lineterminator="\n")
        w.writerow(["psm_id", "peptide", "q_value", "posterior_error_prob",
                    "score", "proteins"])
        for r in psms_ok:
            w.writerow([r.get("PSMId", ""), r.get("peptide", ""),
                        r.get("q-value", ""), r.get("posterior_error_prob", ""),
                        r.get("score", ""), r.get("proteinIds", "")])

    with (rdir / "peptides.tsv").open("w", newline="") as fh:
        w = csv.writer(fh, delimiter="\t", lineterminator="\n")
        w.writerow(["peptide", "q_value", "posterior_error_prob", "score",
                    "proteins"])
        for r in peps_ok:
            w.writerow([r.get("peptide", ""), r.get("q-value", ""),
                        r.get("posterior_error_prob", ""), r.get("score", ""),
                        r.get("proteinIds", "")])

    # The best (lowest) peptide-level q value the search could reach.  Percolator
    # estimates the q value of a peptide as the target-decoy ratio at its score,
    # with a +1 correction on the decoy count, so the very best peptides cannot
    # score below 1/(number of peptides in the top stratum).  On a small
    # identification set that floor lands just above 1% even when hundreds of
    # PSMs pass at 1%, and we then report *zero* peptides.  Recording the floor
    # lets the dataset page say why, instead of implying nothing was identified.
    pep_q_min = ""
    if peps:
        try:
            pep_q_min = min(float(r.get("q-value", "1")) for r in peps)
        except (TypeError, ValueError):
            pep_q_min = ""

    summary = {
        "pxd": pxd, "files": len(pins), "psms_searched": n_psm,
        "psms_q01": len(psms_ok), "peptides_q01": len(peps_ok),
        "peptide_q_min": pep_q_min,
    }
    with (rdir / "fdr_summary.tsv").open("w") as fh:
        fh.write("metric\tvalue\n")
        for k, v in summary.items():
            fh.write(f"{k}\t{v}\n")

    print(f"[{pxd}] {len(pins)} files, {n_psm} PSMs -> "
          f"{len(psms_ok)} PSMs / {len(peps_ok)} peptides at q<={Q_THRESHOLD}",
          file=sys.stderr)
    return summary


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("pxds", nargs="*")
    ap.add_argument("--all-searched", action="store_true")
    a = ap.parse_args()
    want = list(a.pxds)
    if a.all_searched:
        want += [d.name for d in sorted(SEARCH.glob("PXD*")) if d.is_dir()]
    if not want:
        ap.error("give a PXD accession or --all-searched")
    for pxd in sorted(set(want)):
        process(pxd)


if __name__ == "__main__":
    main()
