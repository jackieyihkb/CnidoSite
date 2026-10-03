#!/usr/bin/env python3
"""
01_parse_dataset_table.py
-------------------------
Parse the CnidoSite `proteomic_data.php` dataset table into a structured TSV,
and cross-check every species against the local CnidoSite reference proteomes
(`/mnt/sda/jackie/cnidaria/2.anno/<Species>.pep`).

This produces the backbone provenance table used by the whole proteomics
re-processing pipeline (download -> search -> FDR -> gene mapping -> web).

Usage:
    python3 01_parse_dataset_table.py <proteomic_data.html> > datasets.tsv
"""
import re
import sys
import json
import html as ihtml
import unicodedata
from pathlib import Path

PROTEOME_DIR = Path("/mnt/sda/jackie/cnidaria/2.anno")
OUT_TSV = Path(__file__).resolve().parent.parent / "1.metadata" / "datasets.tsv"
OUT_JSON = Path(__file__).resolve().parent.parent / "1.metadata" / "datasets.json"


def clean(s: str) -> str:
    """Strip tags, unescape entities, collapse whitespace."""
    s = re.sub(r"<[^>]+>", " ", s)
    s = ihtml.unescape(s)
    s = s.replace(" ", " ")
    return re.sub(r"\s+", " ", s).strip()


def parse_table(path: Path):
    x = path.read_text(encoding="utf-8", errors="replace")
    i = x.find("<table")
    j = x.find("</table>", i)
    tbl = x[i:j]
    rows = re.findall(r"<tr[^>]*>(.*?)</tr>", tbl, re.S)

    header, out = None, []
    for r in rows:
        cells = re.findall(r"<t[dh][^>]*>(.*?)</t[dh]>", r, re.S)
        vals = [clean(c) for c in cells]
        if not vals:
            continue
        if header is None:
            header = vals
            continue
        # keep only rows with the expected column count
        if len(vals) != len(header):
            continue
        out.append(dict(zip(header, vals)))
    return header, out


def proteome_for(species: str):
    """Locate the CnidoSite reference proteome for a species name."""
    cands = [
        species,
        species.replace(" ", "_"),
        species.replace(" sp.", "_sp").replace(" ", "_"),
    ]
    for c in cands:
        p = PROTEOME_DIR / f"{c}.pep"
        if p.exists():
            return p
    # fallback: normalised match
    target = re.sub(r"[^a-z]", "", species.lower().replace("sp.", "sp"))
    for p in sorted(PROTEOME_DIR.glob("*.pep")):
        stem = re.sub(r"[^a-z]", "", p.stem.lower())
        if stem == target:
            return p
    return None


def count_proteins(p: Path) -> int:
    n = 0
    with p.open("rb") as fh:
        for line in fh:
            if line.startswith(b">"):
                n += 1
    return n


def main():
    src = Path(sys.argv[1] if len(sys.argv) > 1 else "/tmp/prot.html")
    header, rows = parse_table(src)
    print(f"# parsed {len(rows)} datasets; columns = {header}", file=sys.stderr)

    for r in rows:
        sp = r.get("Species", "")
        p = proteome_for(sp)
        r["proteome_file"] = str(p) if p else ""
        r["proteome_proteins"] = count_proteins(p) if p else 0
        # normalise the PXD accession(s) in the Data Sources column
        r["pxd"] = ";".join(re.findall(r"PXD\d{6}", r.get("Data Sources", "")))
        # normalise PubMed id / Link
        ref = r.get("Reference", "")
        r["pubmed"] = ref if re.fullmatch(r"\d+", ref) else ""

    cols = header + ["pxd", "pubmed", "proteome_file", "proteome_proteins"]
    with OUT_TSV.open("w") as fh:
        fh.write("\t".join(cols) + "\n")
        for r in rows:
            fh.write("\t".join(str(r.get(c, "")) for c in cols) + "\n")
    OUT_JSON.write_text(json.dumps(rows, indent=2))

    miss = [r for r in rows if not r["proteome_file"]]
    print(f"# wrote {OUT_TSV}", file=sys.stderr)
    print(f"# species WITHOUT a reference proteome: {len(miss)}", file=sys.stderr)
    for r in miss:
        print(f"#   - {r['Species']:35} {r.get('pxd','')}", file=sys.stderr)


if __name__ == "__main__":
    main()
