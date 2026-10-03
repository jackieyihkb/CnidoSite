#!/usr/bin/env python3
"""
apl2mgf.py
----------
Convert Applied Biosystems / Sciex **.apl** peak lists to MGF for Comet.

Why this exists
~~~~~~~~~~~~~~~
PXD045585 and PXD045587 deposited their spectra as `.apl` rather than MGF or
RAW.  PRIDE classifies `.apl` as a PEAK file, and it is a plain text peak list,
so the data is fully usable -- it simply needs a format change before Comet can
read it.  Writing the converter here means those two projects (1450 + 579 files,
75 GB + 32 GB) can be re-processed **without downloading anything**.

The grammar
~~~~~~~~~~~
    peaklist start
    mz=375.18407570953
    fragmentation=HCD
    charge=1
    header=RawFile: Amir_B144 Index: 16157 Precursor: 0 _multi_
    110.28606	18437.56
    111.22901	2630.863
    ...
    peaklist end

Notes that matter for the search
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
* There is no retention time anywhere in the format.  RTINSECONDS is therefore
  omitted rather than emitted as 0, so downstream tools cannot mistake a
  placeholder for a real value.
* The precursor m/z is the **monoisotopic-free** value the depositor wrote;
  `charge` is the value the instrument assigned.  Both are passed through
  unchanged -- inferring charge here would silently override the acquisition
  software.
* `header` carries the source RAW file and scan index.  It is preserved
  verbatim as TITLE so every PSM can be traced back to the originating run.

Usage
~~~~~
    python3 apl2mgf.py SHARD.apl                 # -> SHARD.mgf
    python3 apl2mgf.py -o out/ a/ b/ c/          # many files into a directory
    python3 apl2mgf.py --probe SHARD.apl         # stats only, no output
    python3 apl2mgf.py --min-charge 2 SHARD.apl  # drop charge-1 noise
"""
from __future__ import annotations

import argparse
import gzip
import io
import re
import sys
from pathlib import Path

# header=RawFile: Amir_B144 Index: 16157 Precursor: 0 _multi_
_HDR_RAWFILE = re.compile(r"RawFile:\s*(\S+)")
_HDR_INDEX = re.compile(r"Index:\s*(\d+)")

# Keys we lift out of the block; anything else met before the first peak is
# ignored rather than treated as a peak (defensive against future key names).
_KNOWN = {"mz", "charge", "fragmentation", "header", "peaklist"}


def _open(path: Path, mode: str = "rt"):
    """Open plain or gzipped text transparently."""
    if path.suffix.lower() == ".gz":
        return gzip.open(path, mode, encoding="utf-8", errors="replace")
    return path.open(mode, encoding="utf-8", errors="replace")


def iter_spectra(path: Path):
    """Yield (meta, peaks) for every block in an .apl file.

    meta  -- dict with keys mz / charge / fragmentation / header (missing -> "")
    peaks -- list of (mz: float, intensity: float)
    """
    meta: dict = {}
    peaks: list = []
    inside = False
    bad_peaks = 0

    with _open(path) as fh:
        for line in fh:
            line = line.rstrip("\r\n")
            if not line:
                continue

            if line == "peaklist start":
                meta, peaks, inside = {}, [], True
                continue
            if line == "peaklist end":
                if inside:
                    meta["_bad_peaks"] = bad_peaks
                    yield meta, peaks
                inside = False
                continue
            if not inside:
                # tolerate anything outside a block (BOM, blank header lines)
                continue

            # A peak row starts with a digit or sign; a key row has "=" before
            # any tab.  Testing for the digit is cheaper than splitting twice.
            c = line[0]
            if c.isdigit() or c in "+-.":
                parts = line.split()
                if len(parts) >= 2:
                    try:
                        peaks.append((float(parts[0]), float(parts[1])))
                    except ValueError:
                        bad_peaks += 1
                else:
                    bad_peaks += 1
                continue

            key, sep, val = line.partition("=")
            if sep and key in _KNOWN:
                meta[key] = val.strip()
            # unknown keys are dropped on purpose


def _title(meta: dict, fallback: str) -> str:
    """Build a stable, traceable spectrum title."""
    hdr = meta.get("header", "")
    if not hdr:
        return fallback
    raw = _HDR_RAWFILE.search(hdr)
    idx = _HDR_INDEX.search(hdr)
    if raw and idx:
        return f"{raw.group(1)}.{idx.group(1)}"
    # header present but in an unexpected shape -- keep it whole rather than
    # dropping the only provenance the spectrum has
    return re.sub(r"\s+", "_", hdr.strip())[:120]


def convert_file(src: Path, dst: Path, min_charge: int = 1,
                 min_peaks: int = 1, max_spectra: int = 0) -> dict:
    """Convert one .apl file to MGF.  Returns a small stats dict."""
    n_in = n_out = n_skip_charge = n_skip_peaks = 0
    charge_hist: dict = {}
    peak_total = 0

    dst.parent.mkdir(parents=True, exist_ok=True)
    with dst.open("w") as out:
        for meta, peaks in iter_spectra(src):
            n_in += 1
            if max_spectra and n_out >= max_spectra:
                continue

            ch = meta.get("charge", "")
            try:
                z = int(float(ch))
            except ValueError:
                z = 0
            charge_hist[z] = charge_hist.get(z, 0) + 1

            if z and z < min_charge:
                n_skip_charge += 1
                continue
            if len(peaks) < min_peaks:
                n_skip_peaks += 1
                continue

            mz = meta.get("mz", "")
            if not mz:
                n_skip_peaks += 1
                continue

            out.write("BEGIN IONS\n")
            out.write(f"TITLE={_title(meta, f'{src.stem}.{n_in}')}\n")
            out.write(f"PEPMASS={mz}\n")
            if z:
                out.write(f"CHARGE={z}+\n")
            # RT is deliberately absent in this format -- see module docstring
            if meta.get("fragmentation"):
                out.write(f"# fragmentation={meta['fragmentation']}\n")
            for m, i in peaks:
                out.write(f"{m:.6f} {i:.6f}\n")
            out.write("END IONS\n\n")

            n_out += 1
            peak_total += len(peaks)

    return {"file": src.name, "spectra_in": n_in, "spectra_out": n_out,
            "skipped_low_charge": n_skip_charge, "skipped_few_peaks": n_skip_peaks,
            "mean_peaks": (peak_total / n_out) if n_out else 0.0,
            "charges": charge_hist,
            "bytes_out": dst.stat().st_size if dst.exists() else 0}


def probe(src: Path, limit: int = 20000) -> dict:
    """Read-only stats, for checking a format before committing to a full run."""
    n = 0
    charge_hist: dict = {}
    npeaks: list = []
    titles = set()
    for meta, peaks in iter_spectra(src):
        n += 1
        try:
            z = int(float(meta.get("charge", "")))
        except ValueError:
            z = 0
        charge_hist[z] = charge_hist.get(z, 0) + 1
        npeaks.append(len(peaks))
        titles.add(_title(meta, ""))
        if n >= limit:
            break
    npeaks.sort()
    return {
        "spectra": n, "charges": charge_hist,
        "peaks_min": npeaks[0] if npeaks else 0,
        "peaks_median": npeaks[len(npeaks) // 2] if npeaks else 0,
        "peaks_max": npeaks[-1] if npeaks else 0,
        "unique_titles": len(titles),
        "sample": sorted(titles)[:3],
    }


def main():
    ap = argparse.ArgumentParser(description="Convert .apl peak lists to MGF")
    ap.add_argument("inputs", nargs="+", type=Path)
    ap.add_argument("-o", "--outdir", type=Path, default=None,
                    help="output directory (default: alongside each input)")
    ap.add_argument("--min-charge", type=int, default=1)
    ap.add_argument("--min-peaks", type=int, default=1)
    ap.add_argument("--max-spectra", type=int, default=0,
                    help="cap spectra per file (smoke test)")
    ap.add_argument("--probe", action="store_true",
                    help="print statistics only, write nothing")
    a = ap.parse_args()

    rc = 0
    for src in a.inputs:
        if not src.exists():
            print(f"!! missing: {src}", file=sys.stderr)
            rc = 1
            continue

        if a.probe:
            st = probe(src)
            print(f"{src.name}")
            print(f"    spectra        {st['spectra']}")
            print(f"    charges        " +
                  ", ".join(f"{k}+:{v}" for k, v in sorted(st["charges"].items())))
            print(f"    peaks/spectrum min {st['peaks_min']} "
                  f"median {st['peaks_median']} max {st['peaks_max']}")
            print(f"    unique titles  {st['unique_titles']}/{st['spectra']}")
            if st["sample"]:
                print(f"    e.g.           {st['sample']}")
            continue

        dst = (a.outdir / (src.stem + ".mgf")) if a.outdir else src.with_suffix(".mgf")
        st = convert_file(src, dst, a.min_charge, a.min_peaks, a.max_spectra)
        print(f"{src.name} -> {dst.name}: {st['spectra_out']}/{st['spectra_in']} spectra, "
              f"mean {st['mean_peaks']:.0f} peaks, {st['bytes_out']/1e6:.1f} MB",
              file=sys.stderr)

    sys.exit(rc)


if __name__ == "__main__":
    main()
