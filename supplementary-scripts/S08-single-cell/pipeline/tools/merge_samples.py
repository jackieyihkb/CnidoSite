#!/usr/bin/env python3
"""Merge a multi-sample GEO deposit into the single mtx triplet this pipeline reads.

Why this exists
---------------
Several of the datasets in the registry deposit **one matrix per sample**, not
one per study.  GSE218419 and GSE288936 ship complete per-sample 10x triplets;
GSE154105 and GSE242144 ship a per-sample matrix and barcode list with the gene
list hoisted to the series level.  The loader (`lib/io.load_10x_dir`,
`_locate_mtx_triplet`) reads exactly one triplet per directory, because that is
what every other deposit here provides -- and `_locate_mtx_triplet` resolves
ambiguity by taking `sorted(p.iterdir())[0]`, which means pointing it at a
directory holding four samples does not fail.  It silently analyses whichever
sample sorts first and reports it as the study.  That is the failure this tool
exists to prevent, and it is why the output goes to a *separate* prepared
directory with exactly one triplet in it, one directory per dataset.

What it does
------------
    read each sample's matrix
    align the samples on a common gene space (union, zero-filled)
    stack them side by side
    prefix every barcode with "<sample>_" so the library is recoverable

The prefix matters: `lib/io.infer_sample_from_barcode` detects libraries by the
`<sample>_<barcode>` form, and per-library doublet detection plus Harmony batch
correction both key off it.  Without the prefix the samples pool into one
"sample1" and the QC table reports integration that never happened.

Usage
-----
    python merge_samples.py --input DIR --outdir DIR --name PREFIX \
        [--genes PATH] [--pattern GLOB] [--dry-run]

`--genes` supplies a series-level gene list for deposits that do not ship one
per sample.  When absent, each sample's own features/genes file is used.
"""
from __future__ import annotations

import argparse
import glob
import gzip
import os
import re
import sys

import numpy as np
import scipy.io
import scipy.sparse as sp


def _open(path: str):
    return gzip.open(path, "rt") if str(path).endswith(".gz") else open(path)


def read_barcodes(path: str) -> list[str]:
    """Barcodes from whichever shape the submitter used.

    Three are in the deposits this handles:
      one barcode per line, no header            (Barcodes.tsv / barcodes.tsv.gz)
      a two-column index+barcode table           (GSE242144 *.samples.csv.gz)
      a 10x features.tsv-style row with extras

    A leading index column is detected by shape rather than by the header's
    name, because the header is variously absent, `""`/`x`, or `barcode`.
    """
    out = []
    with _open(path) as fh:
        for i, line in enumerate(fh):
            line = line.rstrip("\n").rstrip("\r")
            if not line:
                continue
            # CSV quoting: "1","AAACCC-1"  ->  ['1', 'AAACCC-1']
            parts = [p.strip().strip('"') for p in re.split(r"[\t,]", line)]
            if i == 0 and parts and parts[0].lower() in ("", "x", "barcode", "barcodes"):
                # a header row, not a barcode
                if len(parts) == 1 or parts[1].lower() in ("x", "barcode", "barcodes"):
                    continue
            if len(parts) >= 2 and parts[0].isdigit():
                out.append(parts[1])
            elif len(parts) >= 2 and parts[0] == "":
                out.append(parts[1])
            else:
                out.append(parts[0])
    return out


def read_genes(path: str) -> list[str]:
    """Gene identifiers, taking the id column of a features.tsv if there is one."""
    out = []
    with _open(path) as fh:
        for i, line in enumerate(fh):
            line = line.rstrip("\n").rstrip("\r")
            if not line:
                continue
            parts = [p.strip().strip('"') for p in re.split(r"[\t,]", line)]
            if i == 0 and parts and parts[0].lower() in ("", "x", "gene", "genes", "gene_id"):
                if len(parts) == 1 or parts[1].lower() in ("x", "gene", "genes"):
                    continue
            if len(parts) >= 2 and parts[0].isdigit():
                out.append(parts[1])
            elif len(parts) >= 2 and parts[0] == "":
                out.append(parts[1])
            else:
                out.append(parts[0])
    return out


def discover(input_dir: str, pattern: str) -> list[tuple[str, str, str]]:
    """Find (sample, matrix, barcodes) for each sample in the directory.

    The sample name is the part of the filename before the first separator that
    precedes `matrix`, which handles `NvA1R1_matrix.mtx.gz`,
    `mT.gastrodermis.matrix.mtx.gz` and `GSE154105_TentacleMatrix.mtx.gz`.
    """
    found = []
    for mtx in sorted(glob.glob(os.path.join(input_dir, pattern))):
        base = os.path.basename(mtx)
        m = re.match(r"^(.*?)(?:[._-])?(?:matrix|Matrix)\.mtx(?:\.gz)?$", base)
        if not m:
            continue
        sample = m.group(1) or "sample"
        # the sibling barcode file keeps the sample stem and varies in the tail
        cands = []
        # The separator is optional, and glob cannot say "optional" -- its `?`
        # is a one-character wildcard, so the regex-style `[._-]?` demanded a
        # separator *and* another character after it.  It therefore matched
        # nothing any deposit ships, including `Control_barcodes.tsv.gz` and
        # `GSM6744435_NvA1R1_barcodes.tsv.gz`, the two shapes this tool was
        # written for.  Spell the four separators out instead.
        for tail in ("barcodes.tsv.gz", "barcodes.tsv", "Barcodes.tsv.gz"):
            for sep in (".", "_", "-", ""):
                cands += glob.glob(os.path.join(input_dir, sample + sep + tail))
        # the remaining forms keep a suffix between the stem and the tail
        for pat in (f"{sample}*.barcodes.tsv.gz", f"{sample}*.samples.csv.gz",
                    f"{sample}*Barcodes.tsv.gz"):
            cands += glob.glob(os.path.join(input_dir, pat))
        # also the forms with the separator dropped: GSE154105's TentacleBarcodes
        cands += glob.glob(os.path.join(input_dir, re.sub(r"[._-]+$", "", sample) + "*Barcodes.tsv.gz"))
        cands = [c for c in sorted(set(cands)) if c != mtx]
        if not cands:
            print(f"  ! no barcode file for {base}", file=sys.stderr)
            continue
        found.append((sample, mtx, cands[0]))
    return found


def read_matrix(path: str) -> sp.csc_matrix:
    """A MatrixMarket matrix, as CSC, refusing anything that is not counts."""
    with _open(path) as fh:
        banner = fh.readline()
    if "integer" not in banner:
        raise SystemExit(
            f"ERROR: {os.path.basename(path)} is not an integer matrix "
            f"({banner.strip()!r}) -- normalised values cannot be re-clustered"
        )
    m = scipy.io.mmread(path)
    if isinstance(m, np.ndarray):
        m = sp.csr_matrix(m)
    m = m.tocsc()
    if m.nnz and (m.data < 0).any():
        raise SystemExit(f"ERROR: {os.path.basename(path)} has negative values")
    return m


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--input", required=True)
    ap.add_argument("--outdir", required=True)
    ap.add_argument("--name", required=True)
    ap.add_argument("--genes", help="series-level gene list, when samples have none")
    ap.add_argument("--pattern", default="*matrix*.mtx.gz")
    ap.add_argument("--dry-run", action="store_true")
    args = ap.parse_args()

    samples = discover(args.input, args.pattern)
    if not samples:
        print(f"no sample matrices found in {args.input}", file=sys.stderr)
        return 2
    print(f"found {len(samples)} sample(s):")
    for s, mtx, bc in samples:
        print(f"  {s:<28} {os.path.basename(mtx)}  +  {os.path.basename(bc)}")
    if args.dry_run:
        return 0

    series_genes = read_genes(args.genes) if args.genes else None
    per_sample = []
    for s, mtx_p, bc_p in samples:
        m = read_matrix(mtx_p)
        bc = read_barcodes(bc_p)
        if series_genes is not None:
            genes = series_genes
        else:
            gpath = None
            for pat in (re.sub(r"(?:matrix|Matrix)\.mtx(?:\.gz)?$", "features.tsv.gz",
                               os.path.basename(mtx_p)),
                        re.sub(r"(?:matrix|Matrix)\.mtx(?:\.gz)?$", "genes.tsv.gz",
                               os.path.basename(mtx_p)),
                        re.sub(r"(?:matrix|Matrix)\.mtx(?:\.gz)?$", "features.tsv",
                               os.path.basename(mtx_p)),
                        re.sub(r"(?:matrix|Matrix)\.mtx(?:\.gz)?$", "genes.tsv",
                               os.path.basename(mtx_p))):
                c = os.path.join(args.input, pat)
                if os.path.exists(c):
                    gpath = c
                    break
            if gpath is None:
                print(f"  ! no gene file for {os.path.basename(mtx_p)}; pass --genes",
                      file=sys.stderr)
                return 2
            genes = read_genes(gpath)
        # orientation: the deposits are genes x cells
        if m.shape == (len(genes), len(bc)):
            pass
        elif m.shape == (len(bc), len(genes)):
            m = m.T.tocsc()
        else:
            print(f"  ! {os.path.basename(mtx_p)}: shape {m.shape} matches neither "
                  f"genes({len(genes)}) x cells({len(bc)}) nor its transpose",
                  file=sys.stderr)
            return 2
        per_sample.append((s, m, genes, bc))
        print(f"  {s:<28} {m.shape[0]} genes x {m.shape[1]} cells")

    # ---- align on the union of the gene spaces -----------------------------
    all_genes = []
    seen = set()
    for _, _, genes, _ in per_sample:
        for g in genes:
            if g not in seen:
                seen.add(g)
                all_genes.append(g)
    gene_index = {g: i for i, g in enumerate(all_genes)}
    for s, m, genes, _ in per_sample:
        if genes != all_genes:
            print(f"  note: {s} shares {len(set(genes) & set(all_genes))} of "
                  f"{len(genes)} genes with the union; zero-filling the rest")

    blocks = []
    barcodes = []
    for s, m, genes, bc in per_sample:
        rows = np.array([gene_index[g] for g in genes], dtype=np.int64)
        m = m.tocoo()
        aligned = sp.coo_matrix(
            (m.data, (rows[m.row], m.col)),
            shape=(len(all_genes), m.shape[1]),
        ).tocsc()
        blocks.append(aligned)
        # the prefix is what makes the library recoverable downstream
        barcodes += [f"{s}_{b}" for b in bc]

    X = sp.hstack(blocks, format="csc")
    print(f"\nmerged: {X.shape[0]} genes x {X.shape[1]} cells; {X.nnz} non-zeros")
    if X.nnz and not np.all(X.data == np.round(X.data)):
        print("  ! merged matrix has fractional values", file=sys.stderr)
        return 2

    os.makedirs(args.outdir, exist_ok=True)
    mtx_out = os.path.join(args.outdir, f"{args.name}.counts.mtx.gz")
    # mmwrite takes a path and cannot compress, so write plain then gzip.  It
    # emits the `integer` banner itself when the array is integral, which the
    # loader's guard reads.
    tmp = mtx_out[:-3]
    scipy.io.mmwrite(tmp, X)
    with open(tmp, "rb") as src, gzip.open(mtx_out, "wb", compresslevel=6) as dst:
        while True:
            chunk = src.read(1 << 20)
            if not chunk:
                break
            dst.write(chunk)
    os.remove(tmp)
    with _open(mtx_out) as fh:
        print("banner:", fh.readline().strip())
    with open(os.path.join(args.outdir, f"{args.name}.genes.txt"), "w") as fh:
        fh.write("\n".join(all_genes) + "\n")
    with open(os.path.join(args.outdir, f"{args.name}.barcodes.tsv"), "w") as fh:
        fh.write("\n".join(barcodes) + "\n")
    print(f"wrote {args.outdir}/{args.name}.{{counts.mtx.gz,genes.txt,barcodes.tsv}}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
