#!/usr/bin/env python3
"""query_te.py - query the TE database without needing a sqlite3 binary.

    # free-form SQL
    query_te.py "SELECT region, COUNT(*) FROM te_info GROUP BY 1;"

    # helpers for the common questions
    query_te.py --species Actinernus_sp --type LTR_retrotransposon
    query_te.py --gene Acti_000001-T1
    query_te.py --summary                      # per-species TE counts

    # TSV instead of the aligned table
    query_te.py --species Hydra_vulgaris --export > hydra_TE.tsv

    # a different database (e.g. a test fixture)
    query_te.py --db /tmp/tetest/results/cnidaria_TE.db "SELECT * FROM te_info LIMIT 5;"
"""
import argparse
import sqlite3
import sys

DEFAULT_DB = "/mnt/sda/jackie/cnidaria/codex/genome_TE/results/cnidaria_TE.db"
COLUMNS = ["species", "TE_id", "scaffold", "TE_start", "TE_end",
           "related_gene", "region", "TE_type"]


def show(rows, cols, as_tsv):
    if as_tsv:
        print("\t".join(cols))
        for r in rows:
            print("\t".join("" if v is None else str(v) for v in r))
        return
    if not rows:
        print("(no rows)")
        return
    body = [["" if v is None else str(v) for v in r] for r in rows]
    w = [max(len(cols[i]), *(len(r[i]) for r in body)) for i in range(len(cols))]
    print("  ".join(cols[i].ljust(w[i]) for i in range(len(cols))))
    print("  ".join("-" * w[i] for i in range(len(cols))))
    for r in body:
        print("  ".join(r[i].ljust(w[i]) for i in range(len(cols))))


def main():
    ap = argparse.ArgumentParser(add_help=True)
    ap.add_argument("sql", nargs="*", help="SQL to run (default: SELECT * FROM te_info)")
    ap.add_argument("--db", default=DEFAULT_DB)
    ap.add_argument("--species")
    ap.add_argument("--type", dest="te_type")
    ap.add_argument("--region")
    ap.add_argument("--gene")
    ap.add_argument("--scaffold")
    ap.add_argument("--limit", type=int, default=50)
    ap.add_argument("--summary", action="store_true",
                    help="TE counts per species and type")
    ap.add_argument("--export", metavar="SPECIES",
                    help="dump one species as TSV on stdout (no limit)")
    ap.add_argument("--extended", action="store_true",
                    help="query te_info_extended (more columns)")
    a = ap.parse_args()

    try:
        con = sqlite3.connect(f"file:{a.db}?mode=ro", uri=True)
    except sqlite3.OperationalError as e:
        sys.exit(f"cannot open {a.db}: {e}\n(the pipeline has not been run yet?)")

    # ---- shortcuts -------------------------------------------------------
    if a.summary:
        show(con.execute("SELECT sp.species, te.TE_type, COUNT(*) n_TE "
                         "FROM te_info te JOIN species sp USING (species) "
                         "GROUP BY 1,2 ORDER BY 1,3 DESC"),
             ["species", "TE_type", "n_TE"], False)
        return

    if a.export:
        cur = con.execute(f"SELECT {','.join(COLUMNS)} FROM te_info WHERE species=? "
                          "ORDER BY scaffold, TE_start", (a.export,))
        show(cur, COLUMNS, True)
        return

    if a.sql:
        sql = " ".join(a.sql)
    else:
        table = "te_info_extended" if a.extended else "te_info"
        cols = "*"
        where, vals = [], []
        for col, val in (("species", a.species), ("TE_type", a.te_type),
                         ("region", a.region), ("related_gene", a.gene),
                         ("scaffold", a.scaffold)):
            if val:
                where.append(f"{col}=?")
                vals.append(val)
        sql = (f"SELECT {cols} FROM {table}"
               + (" WHERE " + " AND ".join(where) if where else "")
               + f" LIMIT {a.limit}")
        cur = con.execute(sql, vals)
        cols = [d[0] for d in cur.description]
        rows = cur.fetchall()
        show(rows, cols, False)
        print(f"\n({len(rows)} rows shown — use --limit to change)")
        return

    cur = con.execute(sql)
    cols = [d[0] for d in cur.description] if cur.description else ["result"]
    show(cur.fetchall(), cols, False)


if __name__ == "__main__":
    main()
