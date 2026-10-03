#!/bin/bash
# Load the gene-family consensus annotation on the web server.
#
# Everything is built under a _new suffix and swapped in with one atomic RENAME
# at the end, so the live site keeps serving from the old tables until the new
# ones are complete.
set -e
DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$DIR"
MY="mysql -ujackie -p<REDACTED> cnidaria"
q() { $MY "$@" 2>&1 | grep -v 'Using a password' || true; }

echo "== creating tables"
q < schema.sql

# LOAD DATA LOCAL INFILE needs local_infile on *both* sides.  The client flag below is only
# half of it: without the global, the load dies with "Loading local data is disabled" -- and
# turning the global on and leaving it on is a server-wide setting left open, which is how
# this script used to work.  Set it for the load, put it back on the way out.  import_tree.sh
# does the same thing for its own load.
PREV_LI=$(q -N -e "SELECT @@global.local_infile;")
q -e "SET GLOBAL local_infile=1;"
restore_li() { $MY -e "SET GLOBAL local_infile=${PREV_LI:-0};" 2>/dev/null || true; }
trap restore_li EXIT

echo "== loading og_family_new"
$MY --local-infile=1 -e "
LOAD DATA LOCAL INFILE 'load_family.tsv' INTO TABLE og_family_new
  FIELDS TERMINATED BY '\t' LINES TERMINATED BY '\n'
  (og,n_genes,n_seqs,n_species,best_source,best_term,best_name,best_desc,best_cat,
   best_support,best_pct,best_tier,n_terms,n_terms_all,search_text);" 2>&1 | grep -v 'Using a password' || true

echo "== loading og_family_term_new"
$MY --local-infile=1 -e "
LOAD DATA LOCAL INFILE 'load_terms.tsv' INTO TABLE og_family_term_new
  FIELDS TERMINATED BY '\t' LINES TERMINATED BY '\n'
  (og,source,term,term_name,term_desc,category,support,n_genes,n_annot,pct,pct_annot,tier);" 2>&1 | grep -v 'Using a password' || true

echo "== loading og_family_member_new"
# no PRIMARY KEY here, so drop the secondary indexes during the bulk load
q -e "ALTER TABLE og_family_member_new DROP INDEX idx_og, DROP INDEX idx_abbr_gene;"
$MY --local-infile=1 -e "
LOAD DATA LOCAL INFILE 'load_members.tsv' INTO TABLE og_family_member_new
  FIELDS TERMINATED BY '\t' LINES TERMINATED BY '\n'
  (og,abbr,gene);" 2>&1 | grep -v 'Using a password' || true

echo "== building indexes"
q -e "ALTER TABLE og_family_member_new ADD KEY idx_og (og), ADD KEY idx_abbr_gene (abbr,gene);"
q -e "ALTER TABLE og_family_new ADD KEY idx_tier (best_tier), ADD KEY idx_nspecies (n_species), ADD KEY idx_ngenes (n_genes);"
q -e "ALTER TABLE og_family_new ADD FULLTEXT KEY ft_search (search_text);"

echo "== row counts (before swap)"
q -e "SELECT 'og_family' tbl, COUNT(*) n FROM og_family_new
      UNION ALL SELECT 'og_family_term', COUNT(*) FROM og_family_term_new
      UNION ALL SELECT 'og_family_member', COUNT(*) FROM og_family_member_new;"

echo "== enriching members with NR / UniProt hits"
bash enrich.sh

echo "== swapping tables in (atomic)"
q -e "RENAME TABLE og_family        TO og_family_prev,
               og_family_new    TO og_family,
               og_family_term   TO og_family_term_prev,
               og_family_term_new TO og_family_term,
               og_family_member TO og_family_member_prev,
               og_family_member_new TO og_family_member;"

echo "== final state"
q -e "SELECT 'og_family' tbl, COUNT(*) n FROM og_family
      UNION ALL SELECT 'og_family_term', COUNT(*) FROM og_family_term
      UNION ALL SELECT 'og_family_member', COUNT(*) FROM og_family_member;"
echo "== done (previous tables kept as *_prev)"
