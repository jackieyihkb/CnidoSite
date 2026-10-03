#!/usr/bin/env python3
"""90_merge.py - combine every species into one queryable dataset.

Outputs, under results/:

    all_species.TE_info.tsv.gz      concatenation of the per-species tables
    cnidaria_TE.db                  SQLite database
        te_info            species, TE_id, scaffold, TE_start, TE_end,
                           related_gene, region, TE_type
        te_info_extended   + strand, length, TE_family, gene distance, ...
        te_summary         per species/type counts
        species            one row per species with genome + annotation info
    README.txt                      what the tables mean

Query examples are printed at the end.
"""
import fcntl
import gzip
import os
import sqlite3
import sys
import time
from datetime import datetime

ROOT = os.environ.get("TE_ROOT", "/mnt/sda/jackie/cnidaria/codex/genome_TE")
RES = os.path.join(ROOT, "results")
INFO = os.path.join(RES, "TE_info")
DB = os.path.join(RES, "cnidaria_TE.db")
COMBINED = os.path.join(RES, "all_species.TE_info.tsv.gz")

DDL = """
CREATE TABLE te_info (
    species      TEXT NOT NULL,
    TE_id        TEXT NOT NULL,
    scaffold     TEXT NOT NULL,
    TE_start     INTEGER NOT NULL,
    TE_end       INTEGER NOT NULL,
    related_gene TEXT,
    region       TEXT,
    TE_type      TEXT,
    PRIMARY KEY (species, TE_id)
);
CREATE TABLE te_info_extended (
    species       TEXT NOT NULL,
    TE_id         TEXT NOT NULL,
    scaffold      TEXT NOT NULL,
    TE_start      INTEGER NOT NULL,
    TE_end        INTEGER NOT NULL,
    strand        TEXT,
    TE_length     INTEGER,
    related_gene  TEXT,
    region        TEXT,
    gene_distance INTEGER,
    gene_strand   TEXT,
    gene_biotype  TEXT,
    TE_type       TEXT,
    TE_family     TEXT,
    repeat_name   TEXT
);
CREATE TABLE te_summary (
    species TEXT, TE_type TEXT, n_TE INTEGER
);
CREATE TABLE species (
    species TEXT PRIMARY KEY, genome TEXT, gff3 TEXT, gff3_kind TEXT,
    n_gene INTEGER, display_name TEXT, n_TE INTEGER, TE_bp INTEGER, genome_bp INTEGER
);
"""


def species_with_results():
    if not os.path.isdir(INFO):
        return []
    return sorted(d for d in os.listdir(INFO)
                  if os.path.exists(os.path.join(INFO, d, f"{d}.TE_info.tsv")))


def manifest_rows():
    out = {}
    with open(os.path.join(ROOT, "TE_pipeline", "config", "species_manifest.tsv")) as fh:
        next(fh)
        for line in fh:
            p = line.rstrip("\n").split("\t")
            if len(p) >= 7:
                out[p[0]] = p
    return out


def genome_bp():
    sizes, path = {}, os.path.join(ROOT, "TE_pipeline", "config", "genome_scan.tsv")
    if os.path.exists(path):
        with open(path) as fh:
            for line in fh:
                p = line.rstrip("\n").split("\t")
                if len(p) >= 3:
                    sizes[p[0][:-6] if p[0].endswith(".fa.gz") else p[0]] = int(p[2])
    return sizes


def take_lock():
    """Serialise concurrent merges.

    The database is rebuilt by `os.remove(DB)` followed by a fresh connect: it
    is NOT atomic, so two mergers overlapping would leave a half-written db at
    exactly the path the README tells users to query.  That is reachable now
    that merges are triggered from two places (the watchdog at end of round, and
    bin/merge_daemon.sh whenever a species finishes).  Bounded wait so a wedged
    holder can never stall the watchdog's loop forever.

    The caller must KEEP the returned object alive.  flock lives on the open file
    description, so a handle that gets garbage collected closes the fd and drops
    the lock -- the first version of this returned the handle and discarded it,
    and three concurrent merges all reached the rebuild, leaving an empty db.
    """
    fh = open(os.path.join(RES, ".merge.lock"), "w")
    for _ in range(300):
        try:
            fcntl.flock(fh, fcntl.LOCK_EX | fcntl.LOCK_NB)
            return fh
        except OSError:
            time.sleep(1)
    sys.exit("another merge still holds the lock after 300s — giving up")


def main():
    _lock = take_lock()          # noqa: F841 -- must outlive the whole merge
    sps = species_with_results()
    if not sps:
        sys.exit("no per-species TE tables found — run the pipeline first")

    man, sizes = manifest_rows(), genome_bp()

    # ---- combined TSV ----------------------------------------------------
    n_total = 0
    with gzip.open(COMBINED, "wt", compresslevel=4) as out:
        out.write("species\tTE_id\tscaffold\tTE_start\tTE_end\trelated_gene\tregion\tTE_type\n")
        for sp in sps:
            with open(os.path.join(INFO, sp, f"{sp}.TE_info.tsv")) as fh:
                next(fh)
                for line in fh:
                    out.write(line)
                    n_total += 1
    print(f"combined TSV: {COMBINED}  ({n_total:,} TE records)")

    # ---- SQLite ----------------------------------------------------------
    if os.path.exists(DB):
        os.remove(DB)
    con = sqlite3.connect(DB)
    con.executescript(DDL)
    con.execute("PRAGMA journal_mode=OFF")
    con.execute("PRAGMA synchronous=OFF")

    stats = []
    for sp in sps:
        base = os.path.join(INFO, sp)
        rows, te_bp = [], 0
        with open(os.path.join(base, f"{sp}.TE_info.tsv")) as fh:
            next(fh)
            batch = []
            for line in fh:
                p = line.rstrip("\n").split("\t")
                if len(p) != 8:
                    continue
                batch.append(p)
                te_bp += int(p[4]) - int(p[3]) + 1
                if len(batch) >= 200_000:
                    con.executemany("INSERT INTO te_info VALUES (?,?,?,?,?,?,?,?)", batch)
                    batch = []
            if batch:
                con.executemany("INSERT INTO te_info VALUES (?,?,?,?,?,?,?,?)", batch)

        ext = os.path.join(base, f"{sp}.TE_info.extended.tsv.gz")
        if os.path.exists(ext):
            with gzip.open(ext, "rt") as fh:
                next(fh)
                batch = []
                for line in fh:
                    p = line.rstrip("\n").split("\t")
                    if len(p) != 15:
                        continue
                    for i in (3, 4, 6, 9):
                        p[i] = int(p[i]) if p[i] not in ("", "None") else None
                    batch.append(p)
                    if len(batch) >= 200_000:
                        con.executemany("INSERT INTO te_info_extended VALUES "
                                        "(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)", batch)
                        batch = []
                if batch:
                    con.executemany("INSERT INTO te_info_extended VALUES "
                                    "(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)", batch)

        summ = os.path.join(base, f"{sp}.TE_summary.tsv")
        if os.path.exists(summ):
            with open(summ) as fh:
                next(fh)
                con.executemany("INSERT INTO te_summary VALUES (?,?,?)",
                                [tuple(l.rstrip("\n").split("\t")) for l in fh if l.strip()])

        m = man.get(sp, [sp, "", "", "none", "0", "", sp])
        n = con.execute("SELECT COUNT(*) FROM te_info WHERE species=?", (sp,)).fetchone()[0]
        con.execute("INSERT OR REPLACE INTO species VALUES (?,?,?,?,?,?,?,?,?)",
                    (sp, m[1], m[2], m[3], int(m[4] or 0), m[6], n, te_bp,
                     sizes.get(sp, 0)))
        stats.append((sp, n, te_bp, sizes.get(sp, 0)))
        print(f"  {sp:<26} {n:>10,} TEs")

    print("building indices ...")
    con.executescript("""
        CREATE INDEX i_te_type   ON te_info(TE_type);
        CREATE INDEX i_region    ON te_info(region);
        CREATE INDEX i_gene      ON te_info(related_gene);
        CREATE INDEX i_scaf      ON te_info(scaffold);
        CREATE INDEX i_sp_type   ON te_info(species, TE_type);
        CREATE INDEX i_sp_reg    ON te_info(species, region);
        CREATE INDEX i_e_sp      ON te_info_extended(species);
        CREATE INDEX i_e_type    ON te_info_extended(TE_family);
    """)
    con.execute("ANALYZE")
    con.commit()
    con.close()

    with open(os.path.join(RES, "README.txt"), "w") as fh:
        fh.write(f"""Cnidaria de-novo TE annotation — {datetime.now():%F %T}

Files
  all_species.TE_info.tsv.gz   every TE in every species (8 columns, see below)
  cnidaria_TE.db               SQLite; same data plus extended columns and indices
  TE_info/<sp>/                per-species tables + TE class summary
  TE_lib/<sp>/                 EDTA curated TE library and genome annotation

Columns of te_info / the combined TSV
  species       species name
  TE_id         TE_00000001 ... unique within a species
  scaffold      chromosome / contig / scaffold name, as in the original assembly
  TE_start      1-based start
  TE_end        1-based inclusive end
  related_gene  gene the TE overlaps, else the closest gene (may be empty)
  region        exon | intron | 5UTR | 3UTR | promoter | intergenic region | gene_body
  TE_type       EDTA class, e.g. LTR_retrotransposon, CACTA_TIR_transposon,
                helitron, L2_LINE_retrotransposon

Region rules
  overlapping a coding exon                     -> exon
  overlapping a UTR                             -> 5UTR / 3UTR
  inside a gene but only in an intron           -> intron
  within {os.environ.get('PROMOTER_UP', 2000)} bp upstream of the TSS (strand aware) -> promoter
  otherwise                                     -> intergenic region
  gene body with no exon/intron annotation      -> gene_body

Example queries
  sqlite3 {DB} "SELECT TE_type, COUNT(*) FROM te_info
                WHERE species='Actinernus_sp' GROUP BY 1 ORDER BY 2 DESC LIMIT 20;"
  sqlite3 {DB} "SELECT region, COUNT(*) FROM te_info GROUP BY 1 ORDER BY 2 DESC;"
  sqlite3 {DB} "SELECT * FROM te_info WHERE related_gene='Acti_000001-T1';"
""")


if __name__ == "__main__":
    main()
