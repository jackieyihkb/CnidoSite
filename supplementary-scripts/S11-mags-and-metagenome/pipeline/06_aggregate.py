#!/usr/bin/env python3
"""Stage 06 -- merge the four searches into one annotation record per protein.

Reads
    results/interproscan/<acc>.tsv   Pfam, PANTHER, InterPro, GO
    results/uniprot_hits.tsv         UniProt Swiss-Prot best hit
    results/kegg_ko.tsv              Kofam KO assignments
    work/refs/protein2mag.tsv        which MAG each protein belongs to
    work/refs/go-basic.obo           GO id -> name
    work/refs/ko_list_names.tsv      KO id -> name
    work/refs/ko2pathway.tsv         KO -> KEGG pathway
    work/refs/pathway_names.tsv      pathway id -> name
    work/fetch_status.tsv            protein-set provenance per MAG

Writes
    results/mag_annotation/<acc>.tsv  flat, one row per protein (traceability)
    web/data/mags_summary.json        per-MAG counts for the catalog/summary
    web/data/mag_prot/<acc>.json      per-MAG protein records for MAG_detail.php

Design notes
    * The web payload is per-MAG rather than one big file so MAG_detail.php loads
      one MAG's proteins and nothing else.  The largest MAG is ~5k proteins, so
      the JSON stays in the hundreds of KB.
    * InterProScan reports one row per signature match, so a single protein can
      have many Pfam rows.  Those are collapsed to unique accessions, keeping the
      first description seen, because the page shows each family once.
    * Provenance is carried through: a MAG annotated from NCBI RefSeq proteins and
      one annotated from our own Prodigal calls must not look identical on the
      page, since the reader is being asked to trust a different thing.
"""
import glob

import json
import os
import re
import sys
from collections import defaultdict

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
RES = os.path.join(ROOT, "results")
REF = os.path.join(ROOT, "work", "refs")
WEB = os.path.join(ROOT, "web", "data")


def log(msg):
    print(f"[06] {msg}", flush=True)


def _natural(s):
    """Sort key that orders the trailing number numerically.

    Prodigal ids are <acc>_<n>, so a plain string sort gives 1, 10, 100, 2, ...
    """
    head, _, tail = s.rpartition("_")
    return (head, int(tail)) if tail.isdigit() else (s, -1)


# --- reference lookups --------------------------------------------------------
def load_go_names(path):
    """GO id -> name, from the OBO [Term] stanzas."""
    names, cur = {}, None
    with open(path) as fh:
        for line in fh:
            if line.startswith("id: GO:"):
                cur = line.split(":", 1)[1].strip()
            elif line.startswith("name: ") and cur:
                names[cur] = line[6:].strip()
                cur = None
    return names


def load_ko_names(path):
    out = {}
    with open(path) as fh:
        for line in fh:
            f = line.rstrip("\n").split("\t")
            if len(f) >= 2:
                out[f[0]] = f[1]
    return out


def load_ko_pathways(link_path, name_path):
    """KO -> [(pathway_id, pathway_name)] using the reference (map) pathways.

    KEGG lists both `map*` (reference) and `ko*` (KO-specific) pathways for the
    same KO.  They are the same pathway under two id schemes, so keeping both
    would double every count on the page; map* is kept and ko* dropped.
    """
    names = {}
    with open(name_path) as fh:
        for line in fh:
            f = line.rstrip("\n").split("\t")
            if len(f) >= 2:
                names[f[0]] = f[1]
    out = defaultdict(list)
    with open(link_path) as fh:
        for line in fh:
            f = line.rstrip("\n").split("\t")
            if len(f) != 2:
                continue
            ko = f[0].replace("ko:", "")
            path = f[1].replace("path:", "")
            if not path.startswith("map"):
                continue
            out[ko].append((path, names.get(path, path)))
    return out


def load_uniprot(path):
    """protein -> (accession, description).

    sseqid is `sp|P12345|NAME`; stitle is the full description.
    """
    out = {}
    with open(path) as fh:
        for line in fh:
            f = line.rstrip("\n").split("\t")
            if len(f) < 7:
                continue
            acc = f[1].split("|")[1] if "|" in f[1] else f[1]
            out[f[0]] = (acc, f[6])
    return out


def load_kegg(path):
    """protein -> (ko, [pathways])."""
    out = {}
    if not os.path.exists(path):
        return out
    with open(path) as fh:
        next(fh, None)
        for line in fh:
            f = line.rstrip("\n").split("\t")
            if len(f) >= 2:
                out[f[0]] = f[1]
    return out


# --- InterProScan -------------------------------------------------------------
def load_interproscan(path):
    """protein -> dict(pfam=[], panther=[], interpro=[], go=set(), length=int).

    One TSV row per signature match, so accessions repeat; they are deduplicated
    per source and ordered as first seen, which keeps the display stable between
    runs (a set would reorder them).
    """
    per = defaultdict(lambda: {"pfam": [], "panther": [], "interpro": [],
                               "go": [], "length": 0})
    seen = defaultdict(lambda: defaultdict(set))
    if not os.path.exists(path):
        return per
    with open(path) as fh:
        for line in fh:
            f = line.rstrip("\n").split("\t")
            if len(f) < 15:
                continue
            pid = f[0]
            rec = per[pid]
            try:
                rec["length"] = int(f[2])
            except ValueError:
                pass
            analysis, sig_acc, sig_desc = f[3], f[4], f[5]
            ipr, ipr_desc = f[11], f[12]
            go_field = f[13]

            if analysis == "Pfam" and sig_acc != "-":
                if sig_acc not in seen[pid]["pfam"]:
                    seen[pid]["pfam"].add(sig_acc)
                    rec["pfam"].append([sig_acc, sig_desc])
            elif analysis == "PANTHER" and sig_acc != "-":
                if sig_acc not in seen[pid]["panther"]:
                    seen[pid]["panther"].add(sig_acc)
                    rec["panther"].append([sig_acc, sig_desc])
            if ipr not in ("-", "", None):
                if ipr not in seen[pid]["interpro"]:
                    seen[pid]["interpro"].add(ipr)
                    rec["interpro"].append([ipr, ipr_desc])
            if go_field not in ("-", "", None):
                # "GO:0004332(InterPro)|GO:0016829(Pfam)" -> ids only.  The
                # bracketed source is InterProScan's evidence code, not part of
                # the term, and the page shows the term's own name.
                for m in re.finditer(r"(GO:\d{7})", go_field):
                    g = m.group(1)
                    if g not in seen[pid]["go"]:
                        seen[pid]["go"].add(g)
                        rec["go"].append(g)
    return per


def main():
    os.makedirs(os.path.join(RES, "mag_annotation"), exist_ok=True)
    os.makedirs(os.path.join(WEB, "mag_prot"), exist_ok=True)

    log("loading reference mappings")
    go_names = load_go_names(os.path.join(REF, "go-basic.obo"))
    ko_names = load_ko_names(os.path.join(REF, "ko_list_names.tsv"))
    ko_paths = load_ko_pathways(os.path.join(REF, "ko2pathway.tsv"),
                                os.path.join(REF, "pathway_names.tsv"))
    uniprot = load_uniprot(os.path.join(RES, "uniprot_hits.tsv"))
    kegg = load_kegg(os.path.join(RES, "kegg_ko.tsv"))
    log(f"  GO terms {len(go_names)}, KOs {len(ko_names)}, "
        f"KO->pathway {len(ko_paths)}, UniProt hits {len(uniprot)}, "
        f"KO assignments {len(kegg)}")

    # Provenance per MAG, and the order the catalog lists them in.
    provenance, order = {}, []
    with open(os.path.join(ROOT, "work", "fetch_status.tsv")) as fh:
        next(fh, None)
        for line in fh:
            f = line.rstrip("\n").split("\t")
            if len(f) >= 7 and f[2] != "FAILED":
                provenance[f[0]] = {
                    "protein_source": f[2],
                    "n_proteins_fetched": int(f[6]) if f[6].isdigit() else 0,
                }
                order.append(f[0])

    log(f"collecting annotation for {len(order)} MAGs")

    # The protein list comes from the normalised FASTAs rather than from the
    # InterProScan results.  Driving it off InterProScan would drop every protein
    # of a MAG whose InterProScan run failed, and would also drop proteins that
    # matched nothing -- both of which must still appear on the page, with a
    # dash in each source column, because their absence would silently change
    # the denominator of every coverage figure.
    # Every result file names its protein with the normalised id
    # (<acc>_<n> for Prodigal, <acc>_<ncbi id> for RefSeq).
    prot_lists = {}   # acc -> [(pid, length)], straight from the FASTA
    for acc in order:
        norm = os.path.join(ROOT, "work", "norm", f"{acc}.faa")
        recs = []
        if os.path.exists(norm):
            pid, length = None, 0
            with open(norm) as fh:
                for line in fh:
                    if line.startswith(">"):
                        if pid is not None:
                            recs.append((pid, length))
                        pid = line[1:].split()[0]
                        length = 0
                    else:
                        length += len(line.strip())
                if pid is not None:
                    recs.append((pid, length))
        prot_lists[acc] = recs
    log(f"  {sum(len(v) for v in prot_lists.values())} proteins from the FASTAs")

    ipr_files = sorted(glob.glob(os.path.join(RES, "interproscan", "*.tsv")))
    log(f"  {len(ipr_files)} InterProScan result files")

    # Annotation is keyed by MAG and kept apart from the protein list above.  A
    # MAG whose InterProScan run failed leaves no result file at all, and it must
    # still contribute every one of its proteins -- with dashes in the source
    # columns -- rather than vanish from the page and silently change the
    # denominator of every coverage figure.
    annot = {}
    for path in ipr_files:
        acc = os.path.basename(path)[:-4]
        if acc in provenance:
            annot[acc] = load_interproscan(path)

    detailed = {}   # acc -> list of protein records
    for acc in order:
        ipr = annot.get(acc, {})
        out = []
        for pid, length in prot_lists.get(acc, []):
            rec = ipr.get(pid)
            orig = pid[len(acc) + 1:] if pid.startswith(acc + "_") else pid
            urec = uniprot.get(pid)
            ko = kegg.get(pid)
            go_terms = ([[g, go_names.get(g, g)] for g in rec["go"]]
                        if rec else [])
            kegg_terms = []
            if ko:
                kegg_terms.append([ko, ko_names.get(ko, ko)])
                for pth, pname in ko_paths.get(ko, []):
                    kegg_terms.append([pth, pname])
            out.append({
                "id": pid,
                "orig": orig,
                "len": (rec["length"] if rec and rec["length"] else length),
                # A single [accession, description] pair, not a list of pairs:
                # UniProt is a best hit, so there is exactly one.  The other
                # five sources are lists.  The panel accepts either shape, but
                # this is the one the data contract and 07_verify.py describe.
                "u": list(urec) if urec else [],
                "f": rec["pfam"] if rec else [],
                "t": rec["panther"] if rec else [],
                "i": rec["interpro"] if rec else [],
                "g": go_terms,
                "k": kegg_terms,
            })
        detailed[acc] = out

    # --- per-MAG outputs ------------------------------------------------------
    summary = []
    for acc in order:
        recs = detailed.get(acc, [])
        # FASTA order is kept, not sorted by id: Prodigal ids are <acc>_<n> and a
        # lexicographic sort would order them 1, 10, 100, 2, ... which reads as a
        # shuffle on the page.  FASTA order is already ascending by gene index.
        recs.sort(key=lambda r: _natural(r["id"]))
        n = len(recs)
        counts = {
            "uniprot": sum(1 for r in recs if r["u"]),
            "pfam": sum(1 for r in recs if r["f"]),
            "panther": sum(1 for r in recs if r["t"]),
            "interpro": sum(1 for r in recs if r["i"]),
            "go": sum(1 for r in recs if r["g"]),
            "kegg": sum(1 for r in recs if r["k"]),
        }
        counts["any"] = sum(
            1 for r in recs
            if r["u"] or r["f"] or r["t"] or r["i"] or r["g"] or r["k"])

        prov = provenance.get(acc, {})
        summary.append({
            "accession": acc,
            "n_proteins": n,
            "protein_source": prov.get("protein_source", "unknown"),
            "counts": counts,
        })

        # Flat TSV for traceability / download.
        with open(os.path.join(RES, "mag_annotation", f"{acc}.tsv"), "w") as fh:
            fh.write("protein_id\tlength\tuniprot\tuniprot_desc\tpfam\t"
                     "panther\tinterpro\tgo\tkegg\n")
            for r in recs:
                fh.write("\t".join([
                    r["id"], str(r["len"]),
                    r["u"][0] if r["u"] else "",
                    r["u"][1] if r["u"] else "",
                    ",".join(x[0] for x in r["f"]),
                    ",".join(x[0] for x in r["t"]),
                    ",".join(x[0] for x in r["i"]),
                    ",".join(x[0] for x in r["g"]),
                    ",".join(x[0] for x in r["k"]),
                ]) + "\n")

        # Web payload, plain JSON.  Deliberately not gzipped on disk: the panel
        # reads it with file_get_contents + json_decode, which has no extension
        # requirement, whereas reading a .gz would need zlib and would turn a
        # missing extension into a broken page.  Transfer compression is the web
        # server's job (mod_deflate on .json), and the on-disk cost is small.
        with open(os.path.join(WEB, "mag_prot", f"{acc}.json"), "w") as fh:
            json.dump({"accession": acc, "proteins": recs}, fh,
                      separators=(",", ":"))

    with open(os.path.join(WEB, "mags_summary.json"), "w") as fh:
        json.dump(summary, fh, separators=(",", ":"))

    # --- report ---------------------------------------------------------------
    tot = sum(s["n_proteins"] for s in summary)
    log(f"total proteins {tot}")
    for key in ("uniprot", "pfam", "panther", "interpro", "go", "kegg", "any"):
        c = sum(s["counts"][key] for s in summary)
        pct = 100.0 * c / tot if tot else 0
        log(f"  {key:9s} {c:7d}  {pct:5.1f}%")

    # A MAG with zero proteins in its web payload would render an empty table
    # with no explanation, so make that visible here rather than on the site.
    empty = [s["accession"] for s in summary if s["n_proteins"] == 0]
    if empty:
        log(f"WARNING: {len(empty)} MAGs have no protein records: "
            f"{', '.join(empty[:5])}{' ...' if len(empty) > 5 else ''}")


if __name__ == "__main__":
    main()
