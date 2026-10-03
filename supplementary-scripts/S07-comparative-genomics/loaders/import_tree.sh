#!/bin/bash
# Load the gene trees onto the web server, same shape as import.sh.
#
# Built under a _new suffix and swapped in with one atomic RENAME, so the gene-family
# pages never see the table missing.  local_infile is switched on only for the bulk load
# and put back to 0 at the end -- it is a server-wide setting and should not be left open.
set -e
DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$DIR"
MY="mysql -ujackie -p<REDACTED> cnidaria"
q() { $MY "$@" 2>&1 | grep -v 'Using a password' || true; }

echo "== creating table"
q < schema_tree.sql

# LOAD DATA LOCAL INFILE needs local_infile on *both* sides.  The client flag below is
# only half of it: the server's global is off, and without this the load dies with
# "Loading local data is disabled".  It is server-wide, so put back whatever was there
# before -- including when the load fails -- rather than assuming it was 0.
PREV_LI=$(q -N -e "SELECT @@global.local_infile;")
q -e "SET GLOBAL local_infile=1;"
restore_li() { $MY -e "SET GLOBAL local_infile=${PREV_LI:-0};" 2>/dev/null || true; }
trap restore_li EXIT

echo "== loading og_family_tree_new"
$MY --local-infile=1 -e "
LOAD DATA LOCAL INFILE 'load_trees.tsv' INTO TABLE og_family_tree_new
  FIELDS TERMINATED BY '\t' LINES TERMINATED BY '\n'
  (og,n_tips,n_species,n_outgroup,tree_gz,tree_col_gz,n_dup,n_dup_terminal,dup_gz);" 2>&1 | grep -v 'Using a password' || true

echo "== row counts (before swap)"
q -e "SELECT COUNT(*) AS rows_loaded, SUM(n_tips) AS tips, SUM(n_outgroup) AS outgroup_tips,
             SUM(n_dup) AS dup_nodes, SUM(n_dup > 0) AS families_with_dups
      FROM og_family_tree_new;"

# Never swap on an empty table.  A failed load leaves og_family_tree_new at 0 rows, and
# the RENAME below would then publish that emptiness as the live table -- which is how a
# "successful" import silently blanks the feature.
LOADED=$(q -N -e "SELECT COUNT(*) FROM og_family_tree_new;")
if [ "${LOADED:-0}" -lt 60000 ]; then
  echo "ABORT: only ${LOADED:-0} rows loaded (expected ~67795) -- not swapping." >&2
  exit 1
fi

echo "== swapping table in (atomic)"
# On a first run there is no og_family_tree to preserve, so the two-way rename would fail
# on a missing table and leave the new one unreachable.
HAVE_OLD=$(q -N -e "SELECT COUNT(*) FROM information_schema.tables
                    WHERE table_schema=DATABASE() AND table_name='og_family_tree';")
if [ "$HAVE_OLD" = "1" ]; then
  q -e "DROP TABLE IF EXISTS og_family_tree_prev;"
  q -e "RENAME TABLE og_family_tree     TO og_family_tree_prev,
                    og_family_tree_new TO og_family_tree;"
else
  q -e "RENAME TABLE og_family_tree_new TO og_family_tree;"
fi

echo "== final state"
q -e "SELECT COUNT(*) AS rows_now, SUM(n_tips) AS tips,
             SUM(n_tips > 3000) AS families_needing_collapse
      FROM og_family_tree;"
echo "== done (previous table kept as og_family_tree_prev)"
