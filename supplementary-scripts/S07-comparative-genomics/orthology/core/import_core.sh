#!/bin/bash
# Load the CnidoSite core-ortholog resource (CCO).
#
# Same shape as work/new/import.sh: everything is built under a _new suffix and
# swapped in with one atomic RENAME, so the live site never sees a missing
# table.  Run from the directory holding the load_*.tsv files, on the server.
#
# Two things this script has to get right that the earlier imports did not:
#
#   * The swap must work on a FIRST deployment.  MySQL refuses a multi-table
#     RENAME outright if any source table is missing, so naming core_species TO
#     core_species_prev unconditionally means the very first import never swaps.
#     The pairs are assembled from information_schema instead.
#   * A failing mysql call must actually stop the script.  The old q() ended in
#     `|| true` to hide the "Using a password" warning, which also hid real
#     errors -- in particular the index ALTERs, which now collide with the
#     indexes the schema already declares.
set -e
DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$DIR"
MY="mysql -ujackie -p<REDACTED> cnidaria"

# mysql warns about the password on stderr on every single call.  Filter it, but
# let the exit status through so `set -e` can do its job.
q() { $MY "$@" 2> >(grep -v 'Using a password' >&2); }

# local_infile is OFF by default and does not survive a server restart, so set it
# here rather than relying on it having been turned on by hand once.
q -e "SET GLOBAL local_infile=1;"

echo "== creating tables"
q < schema_core.sql

# The schema declares every index inline, so there is nothing to add here.  LOAD
# DATA with secondary indexes present is slower, but at 136k rows that is noise
# and it removes a step that can silently disagree with the schema.
echo "== loading core_member_new ($(wc -l < load_core_member.tsv) rows)"
$MY --local-infile=1 -e "
LOAD DATA LOCAL INFILE 'load_core_member.tsv' INTO TABLE core_member_new
  FIELDS TERMINATED BY '\t' LINES TERMINATED BY '\n'
  (og,abbr,gene,n_copies,is_single);" 2> >(grep -v 'Using a password' >&2)

echo "== loading core_og_new ($(wc -l < load_core_og.tsv) rows)"
$MY --local-infile=1 -e "
LOAD DATA LOCAL INFILE 'load_core_og.tsv' INTO TABLE core_og_new
  FIELDS TERMINATED BY '\t' LINES TERMINATED BY '\n'
  (og,tiers,hq90_n,hq90_present,hq90_single,hq90_occ,hq90_sc,
   all_n,all_present,all_single,all_occ,all_sc,
   outgroup_present,outgroup_single,n_members,
   best_source,best_term,best_name,best_desc,best_cat,best_support,best_tier,
   search_text);" 2> >(grep -v 'Using a password' >&2)

echo "== loading core_species_new ($(wc -l < load_species.tsv) rows)"
$MY --local-infile=1 -e "
LOAD DATA LOCAL INFILE 'load_species.tsv' INTO TABLE core_species_new
  FIELDS TERMINATED BY '\t' LINES TERMINATED BY '\n'
  (abbr1,of_name,latin,phylum,class,order1,family,genus,ncbi,
   cnidarian,busco90,n_buscos,n_single,n_duplicated,n_fragmented,n_missing,
   pct_complete,pct_single,pct_duplicated,n_core_og,n_core_single);" 2> >(grep -v 'Using a password' >&2)

echo "== row counts (before swap)"
q -e "SELECT 'core_species' t, COUNT(*) n FROM core_species_new
      UNION ALL SELECT 'core_og', COUNT(*) FROM core_og_new
      UNION ALL SELECT 'core_member', COUNT(*) FROM core_member_new;"

# Refuse the swap if any table came out short -- an empty table was once
# renamed over a good one and the page served nothing for hours.
N_SPECIES=$(q -N -e "SELECT COUNT(*) FROM core_species_new;")
N_OG=$(q -N -e "SELECT COUNT(*) FROM core_og_new;")
N_MEM=$(q -N -e "SELECT COUNT(*) FROM core_member_new;")
if [ "$N_SPECIES" -lt 150 ] || [ "$N_OG" -lt 1000 ] || [ "$N_MEM" -lt 130000 ]; then
  echo "!! counts look wrong (species=$N_SPECIES og=$N_OG members=$N_MEM) -- not swapping" >&2
  exit 1
fi

# Build the RENAME list from what is actually on disk, so the same statement works
# on a first deployment (no old tables) and on a re-import (old tables retired to
# _prev).  A stale _prev from an earlier run has to go first or the rename collides.
PAIRS=""
for T in core_species core_og core_member; do
  HAS=$(q -N -e "SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema='cnidaria' AND table_name='$T';")
  if [ "$HAS" = "1" ]; then
    q -e "DROP TABLE IF EXISTS ${T}_prev;"
    PAIRS="$PAIRS \`$T\` TO \`${T}_prev\`,"
  fi
  PAIRS="$PAIRS \`${T}_new\` TO \`$T\`,"
done
PAIRS="${PAIRS%,}"

echo "== swapping tables in (atomic)"
q -e "RENAME TABLE $PAIRS;"

echo "== final state"
q -e "SELECT 'core_species' t, COUNT(*) n FROM core_species
      UNION ALL SELECT 'core_og', COUNT(*) FROM core_og
      UNION ALL SELECT 'core_member', COUNT(*) FROM core_member;"
echo "== done (any previous tables kept as *_prev)"
