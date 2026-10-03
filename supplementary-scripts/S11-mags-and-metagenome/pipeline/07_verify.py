#!/usr/bin/env python3
"""Check the generated web payload for internal consistency.

Stage 06 writes two files that must agree with each other: a per-MAG summary of
counts, and a per-MAG list of protein records.  Nothing downstream re-derives
one from the other, so a bug in the counting loop would show up on the site as
a coverage bar that simply does not match the table underneath it -- plausible
enough that nobody would notice.

This recomputes every count from the protein records and compares.  It reads
only web/data/ and exits non-zero on any mismatch, so it is safe to run as a
gate before deploying.

    python3 pipeline/07_verify.py [data_dir]

The optional argument points at a different payload directory, which is what
lets this be tested against a fixture rather than only against real output.
"""
import json
import os
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DATA = sys.argv[1] if len(sys.argv) > 1 else os.path.join(ROOT, "web", "data")

# The six annotation sources, and the JSON key each is stored under.
SOURCES = {
    "uniprot": "u", "pfam": "f", "panther": "t",
    "interpro": "i", "go": "g", "kegg": "k",
}

errors = []
warnings = []


def fail(msg):
    errors.append(msg)


def main():
    summary_path = os.path.join(DATA, "mags_summary.json")
    if not os.path.isfile(summary_path):
        print(f"FAIL: {summary_path} missing -- run stage 06 first", file=sys.stderr)
        return 1

    with open(summary_path) as fh:
        summary = json.load(fh)
    if not isinstance(summary, list) or not summary:
        print("FAIL: mags_summary.json is not a non-empty array", file=sys.stderr)
        return 1

    print(f"{len(summary)} MAGs in mags_summary.json")

    seen_acc = set()
    tot_prot = 0
    tot_hits = {k: 0 for k in SOURCES}
    tot_prot_source = {}

    for rec in summary:
        acc = rec.get("accession")
        if not acc:
            fail("summary record without an accession")
            continue
        if acc in seen_acc:
            fail(f"{acc}: duplicate in mags_summary.json")
        seen_acc.add(acc)

        tot_prot_source[rec.get("protein_source", "?")] = \
            tot_prot_source.get(rec.get("protein_source", "?"), 0) + 1

        prot_path = os.path.join(DATA, "mag_prot", f"{acc}.json")
        if not os.path.isfile(prot_path):
            fail(f"{acc}: summary present but mag_prot/{acc}.json missing")
            continue
        with open(prot_path) as fh:
            doc = json.load(fh)

        prots = doc.get("proteins")
        if not isinstance(prots, list):
            fail(f"{acc}: no 'proteins' list")
            continue
        if doc.get("accession") != acc:
            fail(f"{acc}: file says accession={doc.get('accession')!r}")

        # 1. the headline protein count must match the records actually shipped
        n = rec.get("n_proteins")
        if n != len(prots):
            fail(f"{acc}: summary n_proteins={n} but {len(prots)} records")

        # 2. every per-source count must be recomputable from the records
        counts = rec.get("counts", {})
        recomputed = {}
        for label, key in SOURCES.items():
            c = sum(1 for p in prots if p.get(key))
            recomputed[label] = c
            if counts.get(label) != c:
                fail(f"{acc}: counts.{label}={counts.get(label)} but records give {c}")

        # 3. 'any' is the union, not the sum -- a protein hitting three sources
        #    counts once
        any_c = sum(1 for p in prots
                    if any(p.get(k) for k in SOURCES.values()))
        recomputed["any"] = any_c
        if counts.get("any") != any_c:
            fail(f"{acc}: counts.any={counts.get('any')} but records give {any_c}")

        # 4. protein ids must be unique within a MAG, or the table double-counts
        ids = [p.get("id") for p in prots]
        if len(set(ids)) != len(ids):
            fail(f"{acc}: duplicate protein ids ({len(ids) - len(set(ids))} collisions)")
        if not all(ids):
            fail(f"{acc}: some protein records have no id")

        # 5. UniProt is a single pair; the others are lists
        for p in prots:
            u = p.get("u")
            if u and not (isinstance(u, list) and len(u) == 2 and isinstance(u[0], str)):
                fail(f"{acc}/{p.get('id')}: 'u' is not a single [acc, desc] pair")
                break
            for k in ("f", "t", "i", "g", "k"):
                v = p.get(k)
                if v and not isinstance(v, list):
                    fail(f"{acc}/{p.get('id')}: '{k}' is not a list")
                    break

        tot_prot += len(prots)
        for label in SOURCES:
            tot_hits[label] += recomputed[label]

    # every shipped protein file should be referenced by the summary
    prot_dir = os.path.join(DATA, "mag_prot")
    if os.path.isdir(prot_dir):
        on_disk = {f[:-5] for f in os.listdir(prot_dir) if f.endswith(".json")}
        orphan = on_disk - seen_acc
        if orphan:
            warnings.append(f"{len(orphan)} protein file(s) not in the summary, "
                            f"e.g. {sorted(orphan)[:3]}")

    print(f"{tot_prot:,} proteins total")
    for label in SOURCES:
        pct = 100.0 * tot_hits[label] / tot_prot if tot_prot else 0.0
        print(f"  {label:9s} {tot_hits[label]:>9,}  {pct:5.1f}% of proteins")
    print(f"  protein_source: " + ", ".join(f"{k}={v}" for k, v in sorted(tot_prot_source.items())))

    for w in warnings:
        print(f"WARN: {w}", file=sys.stderr)
    if errors:
        print(f"\n{len(errors)} consistency error(s):", file=sys.stderr)
        for e in errors[:40]:
            print(f"  {e}", file=sys.stderr)
        if len(errors) > 40:
            print(f"  ... and {len(errors) - 40} more", file=sys.stderr)
        return 1
    print("\nOK: summary and per-protein files agree")
    return 0


if __name__ == "__main__":
    sys.exit(main())
