#!/usr/bin/env python3
"""33_drop_foreign_entries.py <species> [--k 50] [--apply]

Remove rows/records that came from the rice filler library, for a species whose
de-novo modules found nothing.

Why this exists
---------------
`20_edta.sh` runs with `--force 1`.  When a module returns an empty raw library,
EDTA.pl:479-487 copies the RICE library in as a "don't crash" filler before the
`die` that would have reported the empty library can fire (see README 8(8)).
The filler is a *candidate* library, so EDTA's filter step decides what survives
-- and for a TE-depleted genome, enough rice sequences find partial homology to
survive into the curated library.

Measured 2026-09-21 on the two species that had finished:

    species                  curated lib  rice-derived (shared k-mer)   table rows
    Myxobolus_squamalis      2687 seqs    2079 (77.4%), 80.4% of bp    6 of 20106
    Thelohanellus_kitauei    1282 seqs     992 (77.4%), 71.6% of bp    2 of  2293

Both are myxozoans (Myxozoa, the most reduced cnidarian genomes, 43.7 Mb and
~50 Mb) whose LTR/LTR.intact/LINE/SINE/TIR/Helitron slots were ALL empty -- all
six were filled from rice (the helitron rice library exists: rice7.0.0.liban
.Helitron, 759,653 B).  The other 62 species are unaffected: their slots hold
their own sequences (verified by md5 against every rice library).

Where the contamination actually lands
--------------------------------------
In the LIBRARY, not in the delivered tables.  Of the 330 curated library entries
Myxobolus's table actually refers to, only 3 are rice-derived (6 rows); for
Thelohanellus it is 1 entry (2 rows).  The 327 / 14 entries that carry the
annotation share no 30-mer with rice on either strand, so they are this species'
own repeats -- RepeatModeler families the modules did find and classify.  A
myxozoan genome simply does not contain rice sequence, so the rice entries sit in
TElib.fa as members that produce no genome hits.  Re-measured with k=30 and with
the reverse-complemented rice library included, to rule out a trimmed or
flipped rice entry hiding: the counts do not move (2086/2687 and 999/1282
foreign, same 3 and 1 entries used).

So the repair is mostly a library cleanup -- the tables lose 6 and 2 rows -- and
the earlier reading that "the delivered tables are largely rice annotations" was
wrong.  The measured numbers are what this script reports.

What this does
--------------
An entry is called foreign when it shares at least one exact k-mer (default 50)
with the rice filler library.  A 50 bp exact match is not chance; the only source
of rice sequence in this run is that file.  The set is then used to drop

    results/TE_info/<sp>/<sp>.TE_info.tsv              (8 columns, by TE_id)
    results/TE_info/<sp>/<sp>.TE_info.extended.tsv.gz  (15 columns, by TE_id)
    results/TE_lib/<sp>/<sp>.*.EDTA.TEanno.gff3        (records + their children)
    results/TE_lib/<sp>/<sp>.*.EDTA.TEanno.split.gff3
    results/TE_lib/<sp>/<sp>.*.EDTA.TElib.fa           (rewritten in place)
    results/TE_info/<sp>/<sp>.TE_summary.tsv           (rebuilt from what is left)

Originals are kept: every rewritten file is copied to `<name>.with_foreign`
first, so the removal is auditable and reversible.  Run without --apply to only
report.

After applying, re-run `90_merge.py` so results/cnidaria_TE.db and
results/all_species.TE_info.tsv.gz pick up the corrected tables.
"""
import gzip
import os
import re
import sys
from collections import Counter

ROOT = os.environ.get("TE_ROOT", "/mnt/sda/jackie/cnidaria/codex/genome_TE")
RES = os.path.join(ROOT, "results")
EDTA_ENV = os.environ.get("EDTA_ENV", os.path.expanduser("~/miniconda3/envs/EDTA"))
RICE = [os.path.join(EDTA_ENV, "share/EDTA/database", f"rice7.0.0.liban.{x}")
        for x in ("LTR", "LINE", "SINE", "TIR", "Helitron")]

K = 50


def fa_records(path):
    """Yield (name, sequence) for a FASTA file; name is cut at the first # or space."""
    name, seq = None, []
    with open(path, errors="replace") as fh:
        for line in fh:
            if line.startswith(">"):
                if name is not None:
                    yield name, "".join(seq)
                name = line[1:].split("#")[0].split()[0]
                seq = []
            else:
                seq.append(line.strip())
    if name is not None:
        yield name, "".join(seq)


_COMP = str.maketrans("ACGTN", "TGCAN")


def revcomp(s):
    return s.translate(_COMP)[::-1]


def rice_kmers(k):
    """All exact k-mers of the rice filler libraries, as a set of ints.

    Both strands: a candidate library entry that is the reverse complement of a
    rice entry would otherwise read as "own", and revcomp is exactly what
    structural pipelines do to orient candidates before writing them out.

    Stored as hashes rather than strings: ~4.1 M k-mers of 50 letters would be
    ~0.5 GB of Python strings, and this runs while 60+ EDTA chains are live.
    """
    out = set()
    for p in RICE:
        if not os.path.exists(p):
            sys.exit(f"missing rice library: {p}")
        for _, s in fa_records(p):
            for t in (s.upper(), revcomp(s.upper())):
                for i in range(len(t) - k + 1):
                    out.add(hash(t[i:i + k]))
    return out


def foreign_names(kmer_set, lib, k):
    names, detail = set(), []
    for name, seq in fa_records(lib):
        s = seq.upper()
        hit = False
        for i in range(len(s) - k + 1):
            if hash(s[i:i + k]) in kmer_set:
                hit = True
                break
        if hit:
            names.add(name)
            detail.append((name, len(s)))
    return names, detail


def gff_keep(path, foreign, out_suffix):
    """Rewrite a gff3, dropping records whose Name/Target names a foreign entry.

    Children (LTR/gag/pol/TSD ...) carry the same Name as their parent and are
    matched by it; ID= is deliberately not used because IDs are per-record.
    """
    kept, dropped = [], 0
    with open(path, errors="replace") as fh:
        for line in fh:
            if line.startswith("#"):
                kept.append(line)
                continue
            attrs = line.rstrip("\n").split("\t")[8] if line.count("\t") >= 8 else ""
            m = re.search(r"(?:Name|Target)=([^;]*)", attrs)
            nm = m.group(1).split("#")[0].split()[0] if m else ""
            if nm and nm in foreign:
                dropped += 1
            else:
                kept.append(line)
    out = path + out_suffix
    with open(out, "w") as fh:
        fh.writelines(kept)
    return out, len(kept), dropped


def main():
    if len(sys.argv) < 2:
        sys.exit(__doc__)
    sp = sys.argv[1]
    apply_ = "--apply" in sys.argv
    k = K
    if "--k" in sys.argv:
        k = int(sys.argv[sys.argv.index("--k") + 1])

    tsv = os.path.join(RES, "TE_info", sp, f"{sp}.TE_info.tsv")
    ext = os.path.join(RES, "TE_info", sp, f"{sp}.TE_info.extended.tsv.gz")
    summ = os.path.join(RES, "TE_info", sp, f"{sp}.TE_summary.tsv")
    libdir = os.path.join(RES, "TE_lib", sp)
    lib = None
    for f in sorted(os.listdir(libdir)) if os.path.isdir(libdir) else []:
        if f.endswith(".EDTA.TElib.fa"):
            lib = os.path.join(libdir, f)
    if lib is None:
        sys.exit(f"no curated library under {libdir}")

    print(f"{sp}: reading the rice filler libraries ...")
    kmers = rice_kmers(k)
    print(f"  {len(kmers):,} distinct {k}-mers from {len(RICE)} rice files")

    foreign, detail = foreign_names(kmers, lib, k)
    total = sum(1 for _ in fa_records(lib))
    fbp = sum(b for _, b in detail)
    tbp = sum(len(s) for _, s in fa_records(lib))
    print(f"  curated library {total:,} entries / {tbp:,} bp")
    print(f"  foreign        {len(foreign):,} entries ({100*len(foreign)/total:.1f}%) "
          f"/ {fbp:,} bp ({100*fbp/tbp:.1f}%)")
    if not foreign:
        print("  nothing to drop")
        return

    # ---- TE tables ------------------------------------------------------
    # TE_id is the shared key; the 8-column table carries no library name, so the
    # mapping comes from the extended table's repeat_name column.  Column
    # positions are read from the header rather than hardcoded: the extended
    # table has 16 columns (repeat_name 15th, edta_id 16th) and a fixed index
    # silently matched nothing.
    with gzip.open(ext, "rt", errors="replace") as fh:
        head_e = next(fh)
        hdr = head_e.rstrip("\n").split("\t")
        if "TE_id" not in hdr or "repeat_name" not in hdr:
            sys.exit(f"{ext}: header lacks TE_id/repeat_name: {hdr}")
        i_id, i_nm = hdr.index("TE_id"), hdr.index("repeat_name")
        tid2name = {}
        for line in fh:
            p = line.rstrip("\n").split("\t")
            if len(p) > max(i_id, i_nm):
                tid2name[p[i_id]] = p[i_nm]
    drop_ids = {t for t, n in tid2name.items() if n in foreign}
    print(f"  TE rows: {len(tid2name):,} annotated, {len(drop_ids):,} from foreign entries")

    with open(tsv) as fh:
        head = next(fh)
        hdr8 = head.rstrip("\n").split("\t")
        i_id8 = hdr8.index("TE_id")
        rows = [l for l in fh if l.split("\t")[i_id8] not in drop_ids]
    print(f"  TE_info.tsv          {len(rows):,} rows kept (was {len(tid2name):,})")

    ex_rows = []
    with gzip.open(ext, "rt", errors="replace") as fh:
        next(fh)
        for line in fh:
            p = line.rstrip("\n").split("\t")
            if len(p) > i_id and p[i_id] not in drop_ids:
                ex_rows.append(line)
    n_from_foreign = len(tid2name) - len(ex_rows)
    print(f"  TE_info.extended     {len(ex_rows):,} rows kept ({n_from_foreign:,} dropped)")

    if not apply_:
        print("\n(dry run -- pass --apply to rewrite)")
        return

    def backup(p):
        b = p + ".with_foreign"
        if not os.path.exists(b):
            if p.endswith(".gz"):
                with open(p, "rb") as a, gzip.open(b, "wb") as o:
                    o.write(a.read())
            else:
                with open(p, "rb") as a, open(b, "wb") as o:
                    o.write(a.read())
        return b

    backup(tsv)
    with open(tsv, "w") as fh:
        fh.write(head)
        fh.writelines(rows)

    backup(ext)
    with gzip.open(ext, "wt", compresslevel=4) as fh:
        fh.write(head_e)
        fh.writelines(ex_rows)

    # ---- library, annotation, summary -----------------------------------
    for f in sorted(os.listdir(libdir)):
        if f.endswith((".EDTA.TElib.fa", ".EDTA.TEanno.gff3", ".EDTA.TEanno.split.gff3")):
            p = os.path.join(libdir, f)
            if f.endswith(".fa"):
                backup(p)
                kept = [(n, s) for n, s in fa_records(p) if n not in foreign]
                with open(p, "w") as fh:
                    for n, s in kept:
                        fh.write(f">{n}\n")
                        for i in range(0, len(s), 60):
                            fh.write(s[i:i + 60] + "\n")
                print(f"  {f}: {len(kept):,} entries kept")
            else:
                backup(p)
                out, n_kept, n_drop = gff_keep(p, foreign, ".tmp")
                os.replace(out, p)
                print(f"  {f}: {n_kept:,} lines kept, {n_drop:,} records dropped")

    types = Counter(l.rstrip("\n").split("\t")[7] for l in rows)
    backup(summ)
    with open(summ, "w") as fh:
        fh.write("species\tTE_type\tn_TE\n")
        for t, n in types.most_common():
            fh.write(f"{sp}\t{t}\t{n}\n")
    print(f"  {os.path.basename(summ)}: rebuilt, {len(types)} types")

    # The RepeatMasker .sum cannot be filtered line-by-line (it aggregates by
    # class), so it is left as it was and flagged here instead of being silently
    # inconsistent with the tables.
    note = os.path.join(RES, "TE_info", sp, "FOREIGN_REMOVED.txt")
    with open(note, "w") as fh:
        fh.write(f"""Rice-filler entries removed from this species' TE data
=====================================================
Date            : {__import__('time').strftime('%Y-%m-%d %H:%M:%S')}
Species         : {sp}
Test            : exact {k}-mer shared with the EDTA rice filler libraries
                  (rice7.0.0.liban LTR/LINE/SINE/TIR/Helitron; RevComp scanned),
                  run by TE_pipeline/bin/33_drop_foreign_entries.py
Curated library : {total:,} entries -> {total - len(foreign):,} kept
                  ({len(foreign):,} rice-derived entries, {fbp:,} bp)
TE table        : {len(tid2name):,} rows -> {len(rows):,} kept
                  ({len(tid2name) - len(rows):,} rows from rice entries)
Annotation gff3 : records naming a removed entry were dropped
                  (originals kept as *.with_foreign)

Why: 20_edta.sh runs with `--force 1`, and EDTA.pl:479-487 copies the rice
library into any EMPTY raw library slot before the `die` that would have
reported the empty slot can fire.  For these TE-depleted genomes (Myxozoa) the
de-novo modules legitimately found nothing in some or all classes, so rice
sequences entered the candidate library as filler.  See README 8(8).

Note: <sp>.TEanno.sum is RepeatMasker's own summary and predates this removal;
it is kept as evidence but does not reflect the filtered tables.
""".replace("<sp>", sp))
    print(f"  {os.path.basename(note)}: written")

    print(f"\n{sp}: done.  Re-run `python3 TE_pipeline/bin/90_merge.py` to refresh "
          f"results/cnidaria_TE.db and the combined TSV.\n"
          f"Originals kept as *.with_foreign next to each rewritten file.")


if __name__ == "__main__":
    main()
