#!/usr/bin/env python3
"""Emit the LOAD DATA files and schema for the site's core-ortholog tables.

Follows the same shape as the gene-family import: tables are created with a
_new suffix, loaded, indexed, and swapped in with one atomic RENAME, so the
live site never serves from a half-built table.

Writes load_species.tsv, load_core_og.tsv, load_core_member.tsv and
schema_core.sql next to this script.
"""
import os
import csv
import re
import sys
import collections

CORE = os.path.dirname(os.path.abspath(__file__))

SCHEMA = """-- CnidoSite core ortholog resource (CCO).
--
-- Built by work/core/{build_species,build_copynumber,score,build_resource,
-- build_load}.py from the OrthoFinder Results_Sep14 run over 153 proteomes.
-- Loaded under a _new suffix and swapped in atomically by import_core.sh.

DROP TABLE IF EXISTS core_species_new;
DROP TABLE IF EXISTS core_og_new;
DROP TABLE IF EXISTS core_member_new;

CREATE TABLE core_species_new (
  abbr1          VARCHAR(64)  NOT NULL,
  of_name        VARCHAR(190) NOT NULL DEFAULT '',
  latin          VARCHAR(190) NOT NULL DEFAULT '',
  phylum         VARCHAR(64)  NOT NULL DEFAULT '',
  class          VARCHAR(64)  NOT NULL DEFAULT '',
  order1         VARCHAR(64)  NOT NULL DEFAULT '',
  family         VARCHAR(64)  NOT NULL DEFAULT '',
  genus          VARCHAR(64)  NOT NULL DEFAULT '',
  ncbi           VARCHAR(32)  NOT NULL DEFAULT '',
  cnidarian      TINYINT      NOT NULL DEFAULT 0,
  -- BUSCO cnidaria_odb12 complete >= 90% on this proteome.  Deliberately NOT
  -- named high_quality: busco_summary already has a high_quality column with a
  -- different and much stricter meaning (15 genomes, all >=91%), and two columns
  -- of the same name holding different sets is a trap for anyone joining them.
  -- The five outgroup proteomes and the two with no BUSCO run are 0 here.
  busco90        TINYINT      NOT NULL DEFAULT 0,
  n_buscos       INT          NOT NULL DEFAULT 0,
  n_single       INT          NOT NULL DEFAULT 0,
  n_duplicated   INT          NOT NULL DEFAULT 0,
  n_fragmented   INT          NOT NULL DEFAULT 0,
  n_missing      INT          NOT NULL DEFAULT 0,
  pct_complete   DECIMAL(5,2) NOT NULL DEFAULT 0,
  pct_single     DECIMAL(5,2) NOT NULL DEFAULT 0,
  pct_duplicated DECIMAL(5,2) NOT NULL DEFAULT 0,
  n_core_og      INT          NOT NULL DEFAULT 0,   -- CCO orthogroups present
  n_core_single  INT          NOT NULL DEFAULT 0,   -- ... of which single-copy
  PRIMARY KEY (abbr1),
  KEY idx_busco90 (busco90),
  KEY idx_class (class)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE core_og_new (
  og              VARCHAR(16)  NOT NULL,
  tiers           VARCHAR(32)  NOT NULL DEFAULT '',  -- strict,core,extended
  -- occupancy among the 60 high-quality cnidarian genomes
  hq90_n          INT          NOT NULL DEFAULT 0,
  hq90_present    INT          NOT NULL DEFAULT 0,
  hq90_single     INT          NOT NULL DEFAULT 0,
  hq90_occ        DECIMAL(6,4) NOT NULL DEFAULT 0,
  hq90_sc         DECIMAL(6,4) NOT NULL DEFAULT 0,
  -- ... among all 148 cnidarian proteomes
  all_n           INT          NOT NULL DEFAULT 0,
  all_present     INT          NOT NULL DEFAULT 0,
  all_single      INT          NOT NULL DEFAULT 0,
  all_occ         DECIMAL(6,4) NOT NULL DEFAULT 0,
  all_sc          DECIMAL(6,4) NOT NULL DEFAULT 0,
  -- the five non-cnidarian outgroups, the only way to root a matrix
  outgroup_present TINYINT     NOT NULL DEFAULT 0,
  outgroup_single  TINYINT     NOT NULL DEFAULT 0,
  n_members       INT          NOT NULL DEFAULT 0,
  best_source     VARCHAR(16)  NOT NULL DEFAULT '',
  best_term       VARCHAR(64)  NOT NULL DEFAULT '',
  best_name       VARCHAR(255) NOT NULL DEFAULT '',
  best_desc       VARCHAR(512) NOT NULL DEFAULT '',
  best_cat        VARCHAR(64)  NOT NULL DEFAULT '',
  best_support    INT          NOT NULL DEFAULT 0,
  best_tier       VARCHAR(8)   NOT NULL DEFAULT '',
  search_text     TEXT,
  PRIMARY KEY (og),
  KEY idx_tiers (tiers),
  KEY idx_hq90sc (hq90_sc),
  FULLTEXT KEY ft_search (search_text)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE core_member_new (
  og        VARCHAR(16)  NOT NULL,
  -- 64, not 24: the outgroup codes are full species names (OUT_Corticium_
  -- candelabrum is 25 chars) and a narrower column truncates them silently,
  -- which then breaks the join against core_species.
  abbr      VARCHAR(64)  NOT NULL,
  gene      VARCHAR(191) NOT NULL,
  n_copies  INT          NOT NULL DEFAULT 0,
  is_single TINYINT      NOT NULL DEFAULT 0,
  KEY idx_og (og),
  KEY idx_abbr (abbr),
  KEY idx_abbr_gene (abbr, gene)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
"""


def declared_widths():
    """Column -> declared VARCHAR width, parsed straight out of SCHEMA.

    MySQL truncates an over-long value on LOAD DATA rather than failing, so a
    column narrower than its data loses characters silently.  That is exactly
    how OUT_Corticium_candelabrum (25 chars) was cut to 24 by a VARCHAR(24),
    which then stopped the member table joining to the species table -- with no
    error anywhere.  check_widths() below turns that into a loud failure.
    """
    w = {}
    for line in SCHEMA.split("\n"):
        m = re.match(r"\s*(\w+)\s+VARCHAR\((\d+)\)", line)
        if m:
            w[m.group(1)] = int(m.group(2))
    return w


# file -> columns, in the order the LOAD DATA statement names them
LAYOUT = {
    "load_species.tsv": ["abbr1", "of_name", "latin", "phylum", "class", "order1",
                         "family", "genus", "ncbi", "cnidarian", "busco90",
                         "n_buscos", "n_single", "n_duplicated", "n_fragmented",
                         "n_missing", "pct_complete", "pct_single",
                         "pct_duplicated", "n_core_og", "n_core_single"],
    "load_core_og.tsv": ["og", "tiers", "hq90_n", "hq90_present", "hq90_single",
                         "hq90_occ", "hq90_sc", "all_n", "all_present",
                         "all_single", "all_occ", "all_sc", "outgroup_present",
                         "outgroup_single", "n_members", "best_source",
                         "best_term", "best_name", "best_desc", "best_cat",
                         "best_support", "best_tier", "search_text"],
    "load_core_member.tsv": ["og", "abbr", "gene", "n_copies", "is_single"],
}


def check_widths():
    """Refuse to emit a load file any of whose fields would be truncated."""
    w = declared_widths()
    bad = 0
    for fn, cols in LAYOUT.items():
        widest = {}
        for line in open(os.path.join(CORE, fn)):
            f = line.rstrip("\n").split("\t")
            for i, c in enumerate(cols):
                if i < len(f) and len(f[i]) > widest.get(c, 0):
                    widest[c] = len(f[i])
        for c, got in sorted(widest.items()):
            lim = w.get(c)
            if lim is not None and got > lim:
                print("!! %s: %s is %d chars, column is VARCHAR(%d)"
                      % (fn, c, got, lim), file=sys.stderr)
                bad += 1
    if bad:
        sys.exit("refusing to write load files: %d column(s) would be truncated"
                 % bad)
    print("width check ok (no field exceeds its declared column)")


def load_species():
    rows, hdr = [], None
    for i, line in enumerate(open(os.path.join(CORE, "species.tsv"))):
        f = line.rstrip("\n").split("\t")
        if i == 0:
            hdr = f
            continue
        rows.append(dict(zip(hdr, f)))
    return rows


def main():
    sp = load_species()

    # per-species tallies over the published core orthogroups
    core = {}
    for d in csv.DictReader(open(os.path.join(CORE, "core_og.tsv")), delimiter="\t"):
        core[d["og"]] = d
    n_core = collections.Counter()
    n_single = collections.Counter()
    for line in open(os.path.join(CORE, "core_members.tsv")):
        if line.startswith("og\t"):
            continue
        og, a, gene, ncop = line.rstrip("\n").split("\t")
        n_core[a] += 1
        if ncop == "1":
            n_single[a] += 1

    with open(os.path.join(CORE, "load_species.tsv"), "w") as fh:
        for r in sp:
            a = r["abbr1"] or r["of_name"]
            fh.write("\t".join(str(x) for x in [
                a, r["of_name"], r["latin"], r["phylum"], r["class"],
                r["order"], r["family"], r["genus"], r["ncbi"],
                r["cnidarian"], r["busco90"],
                r["n_buscos"] or 0, r["n_single"] or 0, r["n_duplicated"] or 0,
                r["n_fragmented"] or 0, r["n_missing"] or 0,
                r["pct_complete"] or 0, r["pct_single"] or 0,
                r["pct_duplicated"] or 0,
                n_core.get(a, 0), n_single.get(a, 0),
            ]) + "\n")

    def clean(s):
        # LOAD DATA treats \N as SQL NULL and a literal backslash as an escape
        return (s or "").replace("\\", " ").replace("\t", " ").replace("\n", " ")

    with open(os.path.join(CORE, "load_core_og.tsv"), "w") as fh:
        for d in csv.DictReader(open(os.path.join(CORE, "core_og.tsv")), delimiter="\t"):
            search = " ".join([d["og"], d["best_term"], d["best_name"],
                               d["best_desc"], d["best_cat"]])
            fh.write("\t".join(str(x) for x in [
                d["og"], d["tiers"],
                d["hq90_n_species"], d["hq90_n_present"], d["hq90_n_single"],
                d["hq90_occ"], d["hq90_sc"],
                d["all_n_species"], d["all_n_present"], d["all_n_single"],
                d["all_occ"], d["all_sc"],
                d["outgroup_present"], d["outgroup_single"], d["og_n_members"],
                clean(d["best_source"]), clean(d["best_term"]),
                clean(d["best_name"]), clean(d["best_desc"]),
                clean(d["best_cat"]), d["best_support"] or 0,
                clean(d["best_tier"]), clean(search),
            ]) + "\n")

    with open(os.path.join(CORE, "load_core_member.tsv"), "w") as fh:
        for line in open(os.path.join(CORE, "core_members.tsv")):
            if line.startswith("og\t"):
                continue
            og, a, gene, ncop = line.rstrip("\n").split("\t")
            fh.write("%s\t%s\t%s\t%s\t%d\n"
                     % (og, a, gene, ncop, 1 if ncop == "1" else 0))

    with open(os.path.join(CORE, "schema_core.sql"), "w") as fh:
        fh.write(SCHEMA)

    check_widths()

    print("wrote load_species.tsv (%d), load_core_og.tsv (%d), "
          "load_core_member.tsv" % (len(sp), len(core)))


if __name__ == "__main__":
    main()
