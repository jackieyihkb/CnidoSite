#!/usr/bin/env bash
# ============================================================================
# load_proteomics.sh -- install / refresh the CnidoSite proteomics tables.
#
# Run this on the web server after copying the pipeline output across.
#
#   ./load_proteomics.sh                 # create schema + load the pilot data
#   ./load_proteomics.sh --schema-only   # create the tables, load no data
#   ./load_proteomics.sh --dry-run       # print what would run, change nothing
#
# Credentials default to the site's existing configuration.  Override with
# environment variables rather than editing this file:
#   CNIDO_DB=cnidaria CNIDO_USER=<SITE-ACCOUNT> CNIDO_PASS=<REDACTED> CNIDO_HOST=localhost
#
# Safety: the data file begins with DELETE FROM, so a reload REPLACES the
# contents of the proteomics tables.  It does not touch any other table.
# A dump of the current proteomics tables is taken before loading unless
# --no-backup is given.
# ============================================================================
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SCHEMA="$HERE/proteomics_schema.sql"

# pipeline output, relative to the repository layout
ROOT="$(cd "$HERE/../.." && pwd)"
DATA="$ROOT/4.results/load/proteomics_load.sql"

DB="${CNIDO_DB:-cnidaria}"
USER="${CNIDO_USER:-<REDACTED>}"
PASS="${CNIDO_PASS:-<REDACTED>}"
HOST="${CNIDO_HOST:-localhost}"

MODE="load"
BACKUP=1
for arg in "$@"; do
  case "$arg" in
    --schema-only) MODE="schema";;
    --dry-run)     MODE="dry";;
    --no-backup)   BACKUP=0;;
    -h|--help)     sed -n '2,20p' "$0"; exit 0;;
    *) echo "unknown option: $arg" >&2; exit 2;;
  esac
done

mysql_run() { mysql -h "$HOST" -u "$USER" -p"$PASS" "$@"; }

say() { printf '\n\033[1m==> %s\033[0m\n' "$*"; }

# ---------------------------------------------------------------------------
say "Checking the MySQL client and the database"
command -v mysql >/dev/null || { echo "!! mysql client not found" >&2; exit 1; }
mysql_run -e "SELECT VERSION() AS mysql_version;" >/dev/null \
  || { echo "!! cannot connect to $DB as $USER@$HOST" >&2; exit 1; }
echo "   connected to '$DB' on $HOST"

[ -f "$SCHEMA" ] || { echo "!! missing $SCHEMA" >&2; exit 1; }
echo "   schema : $SCHEMA"
if [ "$MODE" != "schema" ]; then
  [ -f "$DATA" ] || { echo "!! missing $DATA -- run 2.pipeline/07_build_tables.py first" >&2; exit 1; }
  echo "   data   : $DATA ($(wc -c <"$DATA") bytes)"
fi

if [ "$MODE" = "dry" ]; then
  say "Dry run -- the following would be executed"
  echo "   mysql $DB < $SCHEMA"
  [ "$MODE" != "schema" ] && echo "   mysql $DB < $DATA"
  exit 0
fi

# ---------------------------------------------------------------------------
# Back up whatever is currently in the proteomics tables, so a bad load can be
# undone.  Missing tables are not an error (first install).
TABLES="proteomic_datasets proteomic_proteins proteomic_peptides"
existing=""
for t in $TABLES; do
  if mysql_run -N -B -e "SHOW TABLES LIKE '$t'" "$DB" | grep -q .; then
    existing="$existing $t"
  fi
done

if [ "$BACKUP" = 1 ] && [ -n "$existing" ]; then
  STAMP="$(date +%Y%m%d-%H%M%S)"
  BK="$HERE/backup-proteomics-$STAMP.sql"
  say "Backing up the current proteomics tables"
  # shellcheck disable=SC2086
  mysqldump -h "$HOST" -u "$USER" -p"$PASS" "$DB" $existing >"$BK"
  echo "   -> $BK ($(wc -c <"$BK") bytes)"
else
  say "No existing proteomics tables to back up"
fi

# ---------------------------------------------------------------------------
say "Applying the schema"
mysql_run "$DB" < "$SCHEMA"
echo "   ok"

if [ "$MODE" = "load" ]; then
  say "Loading the pipeline output"
  # the file wraps its INSERTs in a single transaction
  mysql_run "$DB" < "$DATA"
  echo "   ok"
fi

# ---------------------------------------------------------------------------
say "Verifying"
mysql_run -t "$DB" -e "
  SELECT 'datasets'  AS table_name, COUNT(*) AS rows_loaded FROM proteomic_datasets
  UNION ALL SELECT 'proteins',  COUNT(*) FROM proteomic_proteins
  UNION ALL SELECT 'peptides',  COUNT(*) FROM proteomic_peptides;"

mysql_run -t "$DB" -e "
  SELECT species,
         COUNT(DISTINCT dataset_id) AS datasets,
         SUM(n_proteins)            AS proteins
    FROM proteomic_datasets
   GROUP BY species ORDER BY species;"

mysql_run -t "$DB" -e "
  SELECT status, COUNT(*) AS n FROM proteomic_datasets GROUP BY status;"

# a gene that the gene-page panel should now be able to render
say "Spot check: a gene with proteomic evidence"
mysql_run -t "$DB" -e "
  SELECT p.gene_id, p.dataset_id, p.n_unique_peptides, p.coverage_pct
    FROM proteomic_proteins p
   WHERE p.is_contaminant = 0
   ORDER BY p.n_unique_peptides DESC LIMIT 3;"

say "Done"
cat <<'EOF'
   The Proteome pages are now live:
       /proteomic_reprocessed.php  dataset list + processing status
       /proteomic_reanalysis.php   proteins, filterable, gene-linked
       /proteomic_dataset.php      per-dataset provenance and parameters

   (The site's own /proteomic_data.php and /proteomic_analysis.php are a
   different pair of pages and were deliberately left untouched.)

   To surface proteomic evidence on gene pages, add one line to
   gene_detail.php (see 5.web/INSTALL.md):

       require_once __DIR__ . '/includes/gene_proteomics_panel.php';
       render_gene_proteomics_panel($gene, $species);
EOF
