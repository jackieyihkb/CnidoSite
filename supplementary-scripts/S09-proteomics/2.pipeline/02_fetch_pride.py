#!/usr/bin/env python3
"""
02_fetch_pride.py
-----------------
Download mass-spectrometry files for the CnidoSite proteomics re-processing.

Strategy
~~~~~~~~
For every PRIDE project we prefer the **peak list** (MGF) over the raw
instrument file.  MGF is already centroided and is accepted directly by Comet;
it is typically 20-50x smaller than the matching RAW.  RAW is downloaded only
when a project ships no MGF at all.

Every downloaded file is recorded in `1.metadata/download_manifest.tsv` with its
size and MD5 so the whole step is reproducible and re-runnable (already-present
files with the right size are skipped).

Usage
~~~~~
    python3 02_fetch_pride.py PXD041235 PXD033068 ...      # explicit projects
    python3 02_fetch_pride.py --pilot                       # the built-in pilot set
    python3 02_fetch_pride.py --all                         # every project in datasets.tsv
"""
from __future__ import annotations

import argparse
import gzip
import hashlib
import json
import os
import sys
import time
import urllib.request
from concurrent.futures import ThreadPoolExecutor, as_completed
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
WORK = ROOT / "3.work" / "pride"
META = ROOT / "1.metadata"
EXCLUDE_META = META / "excluded_files.tsv"
MANIFEST = META / "download_manifest.tsv"

# Paginated: PRIDE returns at most 100 records in one response and does not
# say when it has truncated.  The v2 listing has no page parameter, so ask v3,
# which does.
API = ("https://www.ebi.ac.uk/pride/ws/archive/v3/projects/{}/files"
       "?pageSize=100&page={}")
UA = {"User-Agent": "CnidoSite-proteomics/1.0", "Accept": "application/json"}

# Pilot: 4 projects chosen to exercise every branch of the pipeline.
#   PXD041235  Nematostella  -> reference proteome present (NVECT)  [MGF available]
#   PXD033068  Nematostella  -> reference proteome present (NVECT)  [RAW only]
#   PXD018851  myxozoans     -> MHONG/TKITA present, M. wulii absent
#   PXD020332  Corallium     -> NO reference proteome (tests graceful handling)
PILOT = ["PXD041235", "PXD033068", "PXD018851", "PXD020332"]


def api_files(pxd: str, retries: int = 3):
    """Every file record for a project, following PRIDE's pagination.

    One listing response holds at most 100 records and advertises nothing
    about the rest, so reading a single page silently truncates any project
    with more than 100 files.  PXD004257 deposits 145 and we saw 100;
    PXD045585 deposits 1448 and we saw 100.  A file that never appears in the
    listing is never downloaded, and because the search step can only see what
    is on disk, nothing downstream could tell that runs were missing.  Keep
    asking until a short page comes back.
    """
    out: list = []
    page = 0
    while True:
        url = API.format(pxd, page)
        last, batch = None, None
        for a in range(retries):
            try:
                req = urllib.request.Request(url, headers=UA)
                with urllib.request.urlopen(req, timeout=120) as r:
                    batch = json.load(r)
                break
            except Exception as e:                               # noqa: BLE001
                last = e
                time.sleep(2 * (a + 1))
        if batch is None:
            print(f"  !! {pxd}: PRIDE API failed on page {page}: {last}",
                  file=sys.stderr)
            return out
        if not batch:
            return out
        out += batch
        if len(batch) < 100:
            return out
        page += 1
        if page > 200:
            print(f"  !! {pxd}: giving up after 200 pages", file=sys.stderr)
            return out


def https_url(rec) -> str | None:
    """Convert the FTP public location into an HTTPS URL (FTP is often blocked)."""
    for loc in rec.get("publicFileLocations", []):
        v = loc.get("value", "")
        if v.startswith("ftp://ftp.pride.ebi.ac.uk/"):
            return "https://ftp.pride.ebi.ac.uk/" + v[len("ftp://ftp.pride.ebi.ac.uk/"):]
    return None


def md5_of(path: Path, chunk: int = 1 << 20) -> str:
    h = hashlib.md5()
    with path.open("rb") as fh:
        while True:
            b = fh.read(chunk)
            if not b:
                break
            h.update(b)
    return h.hexdigest()


def fetch_to(url: str, tmp: Path, size: int) -> None:
    """Download `url` into `tmp`, resuming until it holds `size` bytes.

    PRIDE drops long transfers part-way through, and a dropped socket is
    indistinguishable from a clean EOF at this level: `read()` returns b"" in
    both cases, so the copy loop below would end "successfully" holding a short
    file.  That is how eight .raw files came to be recorded as `status=ok` while
    being 129 MB to 1.58 GB short, and a truncated .raw that still carries a
    readable header converts and searches without complaint -- a silently
    incomplete result rather than an error.  So: ask for the remainder with a
    Range header, and refuse to return until the byte count matches what PRIDE
    says the file is.
    """
    headers_base = {"User-Agent": UA["User-Agent"]}
    for _ in range(60):
        have = tmp.stat().st_size if tmp.exists() else 0
        if size and have >= size:
            return
        headers = dict(headers_base)
        if have:
            headers["Range"] = f"bytes={have}-"
        req = urllib.request.Request(url, headers=headers)
        with urllib.request.urlopen(req, timeout=600) as r:
            if not have:
                # What the server is about to send is the authority on
                # completeness, and it is not always `fileSizeBytes`.  For a
                # compressed member PRIDE indexes the size *inside* the gzip:
                # `....pride.mgf.gz` is advertised as 22,592,448 while the
                # deposited file is 7,879,700 bytes.  Verifying a .gz against
                # the API number would re-download a complete file 60 times and
                # then fail it, so prefer the response's own Content-Length.
                cl = r.headers.get("Content-Length")
                if cl and cl.isdigit():
                    size = int(cl)
            # A server that ignores Range answers 200 with the whole file; that
            # has to overwrite, not append, or the result is corrupt.
            append = have > 0 and r.status == 206
            if not append:
                have = 0
            with tmp.open("ab" if append else "wb") as fh:
                while True:
                    b = r.read(1 << 20)
                    if not b:
                        break
                    fh.write(b)
        if not size:
            return                       # nothing to verify the transfer against
    got = tmp.stat().st_size if tmp.exists() else 0
    raise IOError(f"incomplete after 60 attempts: {got} of {size} bytes")


def gz_size(path: Path) -> int:
    """Number of bytes inside a gzip member, or -1 if the stream is damaged."""
    n = 0
    try:
        with gzip.open(path, "rb") as fh:
            while True:
                b = fh.read(1 << 20)
                if not b:
                    break
                n += len(b)
    except Exception:                                            # noqa: BLE001
        return -1
    return n


def complete(out: Path, size: int) -> bool:
    """Is `out` already the whole file?

    A plain file is complete when its length matches `size`.  A `.gz` cannot be
    judged that way, because PRIDE reports the size of the decompressed member:
    compare against that, and let gzip's own end-of-stream check reject a
    damaged archive.  Doing it by decompression rather than by asking the server
    keeps the cache path network-free.
    """
    if size == 0:
        return True
    if out.name.lower().endswith(".gz"):
        return gz_size(out) == size
    return out.stat().st_size == size


def download(pxd: str, rec: dict, dest: Path) -> dict:
    """Stream one file to disk; skip when already complete."""
    name = rec["fileName"]
    size = int(rec.get("fileSizeBytes") or 0)
    out = dest / name
    url = https_url(rec)

    if out.exists() and complete(out, size):
        return {"pxd": pxd, "file": name, "category": cat(rec), "bytes": out.stat().st_size,
                "md5": md5_of(out), "status": "cached", "url": url or ""}

    if url is None:
        return {"pxd": pxd, "file": name, "category": cat(rec), "bytes": 0,
                "md5": "", "status": "no-https-location", "url": ""}

    tmp = out.with_suffix(out.suffix + ".part")
    # PRIDE drops connections occasionally (`RemoteDisconnected`).  Without a
    # retry one such drop ends that file's turn for good: the failure is
    # printed, the pipeline moves on to the search, and the dataset ships a run
    # short with nothing gating on it.  Three PXD004257 runs were lost that way
    # and the search reported "45 files" against a 48-run deposit.  Retry the
    # transfer, since the failure is transient; a real refusal still fails.
    last = None
    for attempt in range(4):
        try:
            fetch_to(url, tmp, size)
            tmp.rename(out)
            break
        except Exception as e:                                   # noqa: BLE001
            last = e
            if tmp.exists():
                tmp.unlink()
            if attempt < 3:
                print(f"    retry {attempt + 1}/3 for {name}: "
                      f"{type(e).__name__}", file=sys.stderr)
                time.sleep(3 * (attempt + 1))
    else:
        return {"pxd": pxd, "file": name, "category": cat(rec), "bytes": 0,
                "md5": "", "status": f"error:{type(last).__name__}", "url": url}

    return {"pxd": pxd, "file": name, "category": cat(rec),
            "bytes": out.stat().st_size, "md5": md5_of(out), "status": "ok",
            "url": url}


def cat(rec) -> str:
    c = rec.get("fileCategory")
    if isinstance(c, dict):
        return c.get("value", "")
    return str(c or "")


def pick(records):
    """Prefer peak lists (MGF/APL); fall back to RAW if the project has none.

    `.apl` is the peak-list format the PXD045585/PXD045587 authors deposited;
    PRIDE classifies it as fileCategory PEAK, and it is a plain text peak list
    (`peaklist start` / `mz=` / `charge=` / mz-intensity pairs).  It is treated
    as a peak format here and converted to MGF by apl2mgf.py before searching.
    """
    peaks, raws = [], []
    for r in records:
        n = r["fileName"].lower()
        if n.endswith((".mgf", ".mgf.gz", ".apl")):
            peaks.append(r)
        elif n.endswith((".raw", ".mzml", ".mzxml", ".wiff", ".d", ".scan")):
            raws.append(r)
    if peaks:
        return peaks, "mgf"
    return raws, "raw"


def excludes_for(pxd: str) -> set:
    """Files deliberately kept out of the database search for this project.

    Read from `1.metadata/excluded_files.tsv`, the same list 04_run_search.py
    consults, so the two steps agree.  A file the search will not read -- a DIA
    acquisition handed to a DDA engine, say -- is a file there is no reason to
    spend bandwidth on.  Absence of the file means no exclusions.
    """
    if not EXCLUDE_META.exists():
        return set()
    out = set()
    for line in EXCLUDE_META.read_text().splitlines():
        if not line.strip() or line.startswith("#"):
            continue
        f = line.split("\t")
        if len(f) >= 2 and f[0].strip() == pxd:
            out.add(f[1].strip())
    return out


def fill_gaps(pxd: str, recs, dest: Path, staged, jobs: int):
    """Download deposit files that local staging did not supply.

    "A local file wins" is right for a single file and wrong for a *project*.
    Staging only asked whether anything was on disk, and answered yes, so a
    half-finished local copy ended the fetch silently: the project then looked
    complete to every later step.  PXD014076 sat in exactly that state, and its
    Aiptasia phosphoproteome was searched from 19 of the 44 deposited DDA runs
    with nothing anywhere recording the shortfall.

    Fill only gaps in a format already staged -- never introduce a second
    format for the same project -- and respect the exclusion list.
    """
    exts = {Path(r["file"]).suffix.lower() for r in staged}
    have = {r["file"] for r in staged}
    skip = excludes_for(pxd)
    todo = [r for r in recs
            if Path(r["fileName"]).suffix.lower() in exts
            and r["fileName"] not in have
            and r["fileName"] not in skip]
    for r in recs:
        if r["fileName"] in skip and Path(r["fileName"]).suffix.lower() in exts:
            print(f"    {'excluded':>10}  {r['fileName']}", file=sys.stderr)
    if not todo:
        print(f"    gap-fill: local copy is complete for {sorted(exts)}",
              file=sys.stderr)
        return []
    gb = sum(int(r.get("fileSizeBytes") or 0) for r in todo) / 1e9
    print(f"    gap-fill: {len(todo)} deposited file(s) absent locally, "
          f"{gb:.2f} GB to fetch", file=sys.stderr)
    out = []
    with ThreadPoolExecutor(jobs) as ex:
        futs = {ex.submit(download, pxd, r, dest): r for r in todo}
        for fu in as_completed(futs):
            row = fu.result()
            out.append(row)
            print(f"    {row['status']:>10}  {row['file']}  "
                  f"{row['bytes']/1e6:.1f} MB", file=sys.stderr)
    return out


# ---------------------------------------------------------------------------
# Local sources
# ---------------------------------------------------------------------------
# Re-downloading data that is already on disk wastes hours of bandwidth.  Any
# directory listed here is searched for the project before PRIDE is contacted.
# Override with CNIDO_LOCAL_PRIDE (os.pathsep-separated) to point elsewhere.
LOCAL_ROOTS = [
    Path(p) for p in (
        os.environ.get("CNIDO_LOCAL_PRIDE", "").split(os.pathsep)
        if os.environ.get("CNIDO_LOCAL_PRIDE")
        else ["/mnt/sdb/jackie/cnidaria_omics/proteome"]
    ) if p
]

# Preference order for locally available files: already-parsed peak lists first,
# then raw that still needs converting.
LOCAL_RANK = {".mgf": 0, ".apl": 1, ".mzml": 2, ".raw": 3, ".wiff": 4}


def local_files(pxd: str):
    """Find on-disk files belonging to a project, best format first.

    Handles both layouts seen in practice:
        <root>/<PXD>/...            (one directory per project)
        <root>/<PXD>.<ext>          (loose files)
        <root>/<PXD>_<suffix>.<ext>
    """
    found = []
    for root in LOCAL_ROOTS:
        d = root / pxd
        if d.is_dir():
            found += [p for p in d.iterdir() if p.is_file()]
        for pat in (f"{pxd}.*", f"{pxd}_*"):
            found += [p for p in root.glob(pat) if p.is_file()]
    # keep only formats the pipeline knows how to read, drop sidecars
    keep = [p for p in found
            if p.suffix.lower() in LOCAL_RANK
            and not p.name.endswith((".scan", ".fai"))]
    # de-duplicate on the resolved path (a symlink may appear twice)
    seen, uniq = set(), []
    for p in sorted(keep, key=lambda x: (LOCAL_RANK[x.suffix.lower()], x.name)):
        rp = p.resolve()
        if rp not in seen:
            seen.add(rp)
            uniq.append(p)
    if not uniq:
        return [], None
    best = LOCAL_RANK[uniq[0].suffix.lower()]
    return [p for p in uniq if LOCAL_RANK[p.suffix.lower()] == best], uniq[0].suffix.lower().lstrip(".")


def stage_local(pxd: str, dest: Path):
    """Symlink locally available files into the work directory.

    Symlinks rather than copies: these projects are tens of gigabytes and the
    pipeline only reads them.  Returns manifest rows, or None if nothing usable
    was found locally.
    """
    files, kind = local_files(pxd)
    if not files:
        return None
    rows = []
    for src in files:
        link = dest / src.name
        if link.is_symlink() or link.exists():
            link.unlink()
        link.symlink_to(src.resolve())
        rows.append({"pxd": pxd, "file": src.name, "category": kind.upper(),
                     "bytes": link.stat().st_size, "md5": "local",
                     "status": "local", "url": str(src.resolve())})
    return rows


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("pxds", nargs="*")
    ap.add_argument("--pilot", action="store_true", help="use the built-in pilot set")
    ap.add_argument("--all", action="store_true", help="every project in datasets.tsv")
    ap.add_argument("-j", "--jobs", type=int, default=6)
    ap.add_argument("--force-download", action="store_true",
                    help="ignore local copies and fetch from PRIDE anyway")
    ap.add_argument("--local-only", action="store_true",
                    help="fail instead of downloading when a project is not on disk")
    a = ap.parse_args()

    pxds = list(a.pxds)
    if a.pilot:
        pxds += PILOT
    if a.all:
        tsv = META / "datasets.tsv"
        if tsv.exists():
            for line in tsv.read_text().splitlines()[1:]:
                f = line.split("\t")
                if len(f) > 6 and f[6]:
                    pxds += [p for p in f[6].split(";") if p]
    pxds = sorted(set(pxds))
    if not pxds:
        sys.exit("no projects given; use --pilot / --all / explicit accessions")

    WORK.mkdir(parents=True, exist_ok=True)
    rows = []
    for pxd in pxds:
        dest = WORK / pxd
        dest.mkdir(exist_ok=True)

        # Local files win: they are already on disk, so downloading them again
        # costs hours and changes nothing.  Only fall through to PRIDE when the
        # project is genuinely absent locally.
        if not a.force_download:
            local = stage_local(pxd, dest)
            if local:
                gb = sum(r["bytes"] for r in local) / 1e9
                print(f"[{pxd}] {len(local)} local file(s), {local[0]['category']} "
                      f"({gb:.2f} GB) staged from disk", file=sys.stderr)
                # A local file is matched to a project by *name only*, and a
                # file named after an accession is not evidence about what is
                # inside it.  We were caught by this: a local PXD017813.raw was
                # a tryptic digest while the deposition is Glu-C, and searching
                # it with the declared specificity returned confident hits for
                # the wrong peptides.  Say so at the point of substitution, and
                # let 2.pipeline/09_enzyme_qc.py check the result.
                print(f"    note: local copies are not verified against the PRIDE "
                      f"inventory; run 09_enzyme_qc.py before releasing", file=sys.stderr)
                for r in local:
                    print(f"    {'local':>10}  {r['file']}", file=sys.stderr)
                rows += local
                # Snapshot the PRIDE inventory for provenance, and use it to
                # finish the job the local copy only partly did.  Local files
                # are still never re-downloaded: download() skips anything
                # already complete on disk.
                recs = api_files(pxd, retries=1)
                if recs:
                    (dest / "pride_files.json").write_text(json.dumps(recs, indent=2))
                    rows += fill_gaps(pxd, recs, dest, local, a.jobs)
                else:
                    print(f"    warning: PRIDE inventory unreachable, so the local "
                          f"copy could not be checked for completeness",
                          file=sys.stderr)
                continue

        if a.local_only:
            print(f"[{pxd}] not found locally (used --local-only, not downloading)",
                  file=sys.stderr)
            continue

        recs = api_files(pxd)
        if not recs:
            continue
        chosen, kind = pick(recs)
        # Never spend bandwidth on a file the search will refuse to read.  A
        # DIA acquisition handed to a DDA engine is the case that motivated
        # this: PXD014076 deposits 20 DIA runs beside its 44 DDA runs, and the
        # DIA half is ~11 GB that no step downstream would ever open.
        excl = excludes_for(pxd)
        kept = []
        for r in chosen:
            if r["fileName"] in excl:
                print(f"    {'excluded':>10}  {r['fileName']}", file=sys.stderr)
            else:
                kept.append(r)
        chosen = kept
        total = sum(int(r.get("fileSizeBytes") or 0) for r in chosen) / 1e9
        print(f"[{pxd}] {len(recs)} files -> using {len(chosen)} {kind.upper()} "
              f"({total:.2f} GB)", file=sys.stderr)
        # save the full file inventory for provenance
        (dest / "pride_files.json").write_text(json.dumps(recs, indent=2))
        with ThreadPoolExecutor(a.jobs) as ex:
            futs = {ex.submit(download, pxd, r, dest): r for r in chosen}
            for fu in as_completed(futs):
                row = fu.result()
                rows.append(row)
                print(f"    {row['status']:>10}  {row['file']}  "
                      f"{row['bytes']/1e6:.1f} MB", file=sys.stderr)

    # merge with any previous manifest
    prev = {}
    if MANIFEST.exists():
        for line in MANIFEST.read_text().splitlines()[1:]:
            f = line.split("\t")
            if len(f) >= 7:
                prev[(f[0], f[1])] = f
    for r in rows:
        prev[(r["pxd"], r["file"])] = [r["pxd"], r["file"], r["category"],
                                       str(r["bytes"]), r["md5"], r["status"], r["url"]]
    with MANIFEST.open("w") as fh:
        fh.write("pxd\tfile\tcategory\tbytes\tmd5\tstatus\turl\n")
        for k in sorted(prev):
            fh.write("\t".join(prev[k]) + "\n")
    # "local" counts as ready: the file is already on disk and staged.
    ok = sum(1 for r in rows if r["status"] in ("ok", "cached", "local"))
    print(f"\n# {ok}/{len(rows)} files ready; manifest -> {MANIFEST}", file=sys.stderr)


if __name__ == "__main__":
    main()
