#!/usr/bin/env bash
# 60_publish_to_site.sh [--dry-run] [--force] [--list] [species ...]
#
# Publish finished species' TE tables to the CnidoSite database so they appear in
# https://cnidosite.org/TE.php.
#
# The website reads TE data from MySQL, not from files: TE_gene.php /
# TE_region.php look the species up in `abbr` and query the table `<abbr1>_TE`.
# Publishing a species therefore means creating that table and loading the
# 8-column table from results/TE_info/<sp>/, with the `species` column rewritten
# to the Latin name the site keys on.  TE.php discovers its species list from the
# database, so a published species appears there with no further edit.
#
# INCREMENTAL, because this now runs unattended (cron, see 61_publish_watch.sh):
# a species is loaded only when its table is missing, when it is not ours to
# manage, or when its on-disk table changed since the publish recorded in the
# site's TE_publish_log.  An unchanged species costs one row of one query and is
# never dropped and reloaded -- reloading a 2.6M-row table blanks it for the
# length of the load, and a reviewer may be reading it at that moment.
#
#   --dry-run   report what would happen, touch nothing
#   --force     reload regardless of the log (use after correcting an annotation)
#   --list      print the published state and exit
#
# A species is only ever a candidate once its .TE_summary.tsv exists (written
# after the .TE_info.tsv handle is closed) and that file is a little stale, so a
# table that is still being written is never read half-finished.
#
# Guard: a species whose site table already exists and does NOT belong to this
# project (Actinernus sp. WN-2022 = ASP1_TE, from an older homology-based
# annotation) is skipped unless --force is given -- overwriting it would silently
# replace that dataset rather than add to it.
set -euo pipefail

SITE_HOST="${SITE_HOST:-<SITE-HOST>}"
SITE_USER="${SITE_USER:-<REDACTED>}"
SITE_DB="${SITE_DB:-cnidaria}"
STAGE="${STAGE:-/home/jackie/te_publish_staging}"

TE_ROOT="${TE_ROOT:-/mnt/sda/jackie/cnidaria/codex/genome_TE}"
MANIFEST="$TE_ROOT/TE_pipeline/config/species_manifest.tsv"
INFO="$TE_ROOT/results/TE_info"
# A table younger than this is still being written by 30_te_table.py.
MIN_AGE="${MIN_AGE:-120}"

DRY=0; FORCE=0; LIST=0; WANT=()
for a in "$@"; do
  case "$a" in
    --dry-run) DRY=1 ;;
    --force)   FORCE=1 ;;
    --list)    LIST=1 ;;
    -h|--help) sed -n '2,30p' "$0"; exit 0 ;;
    *) WANT+=("$a") ;;
  esac
done

say() { printf '[%s] %s\n' "$(date '+%F %T')" "$*"; }
ssh_site() { ssh -o BatchMode=yes -o ConnectTimeout=20 "$SITE_USER@$SITE_HOST" "$@"; }

tmp=$(mktemp -d); trap 'rm -rf "$tmp"' EXIT

# ---------------------------------------------------------------- site state
# One round trip gets everything needed to decide, so the common "nothing new"
# poll costs a single query and no per-species traffic.
#
#   LOG  published by us (or deliberately skipped): size/mtime of the source file
#   TBL  every %_TE table that exists, whoever created it
cat > "$tmp/state.sql" <<'SQL'
CREATE TABLE IF NOT EXISTS TE_publish_log (
  table_name   VARCHAR(64)     NOT NULL,
  species      VARCHAR(255)    NOT NULL,
  rows_loaded  BIGINT UNSIGNED NOT NULL,
  src_size     BIGINT UNSIGNED NOT NULL,
  src_mtime    BIGINT UNSIGNED NOT NULL,
  dataset      VARCHAR(32)     NOT NULL,
  published_at DATETIME        NOT NULL,
  PRIMARY KEY (table_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
SELECT 'LOG', table_name, species, rows_loaded, src_size, src_mtime, dataset
  FROM TE_publish_log
UNION ALL
SELECT 'TBL', table_name, '', 0, 0, 0, ''
  FROM information_schema.tables
 WHERE table_schema = DATABASE() AND table_name LIKE '%\_TE';
SQL

mapfile -t STATE < <(ssh_site "mysql -u$SITE_USER -p$SITE_USER -N -B $SITE_DB" \
                      < "$tmp/state.sql" 2>/dev/null)

declare -A LOGGED EXISTS LOGROWS LOGDATA
for r in "${STATE[@]}"; do
  IFS=$'\t' read -r tag tn sp nrows sz mt data <<< "$r"
  case "$tag" in
    LOG) LOGGED["$tn"]="$sz:$mt"; LOGROWS["$tn"]="$nrows"; LOGDATA["$tn"]="$data" ;;
    TBL) EXISTS["$tn"]=1 ;;
  esac
done

# species -> abbr1, straight from the site's own mapping table so the site stays
# the authority on how a species is spelled there.
mapfile -t SITEMAP < <(ssh_site "mysql -u$SITE_USER -p$SITE_USER -N -B $SITE_DB \
  -e \"SELECT species, abbr1 FROM abbr;\"" 2>/dev/null)

declare -A ABBR1 LATIN
while IFS=$'\t' read -r sp a1; do
  [ -n "${sp:-}" ] && { ABBR1["$sp"]="$a1"; LATIN["$sp"]="$sp"; }
done <<< "$(printf '%s\n' "${SITEMAP[@]}")"

# on-disk name -> Latin name (the abbr table spells it the way the site shows it)
declare -A DISK2LATIN
while IFS=$'\t' read -r sp _g _f _k _n _s disp; do
  [ "$sp" = "species" ] && continue
  [ -n "${disp:-}" ] || continue
  [ -n "${ABBR1[$disp]:-}" ] && DISK2LATIN["$sp"]="$disp"
done < "$MANIFEST"

if [ "$LIST" = 1 ]; then
  printf '%-30s %-10s %-9s %-12s %s\n' SPECIES TABLE ROWS DATASET SOURCE
  for sp in $(cut -f1 "$MANIFEST" | tail -n +2); do
    latin="${DISK2LATIN[$sp]:-}"; [ -n "$latin" ] || continue
    tbl="${ABBR1[$latin]}_TE"
    printf '%-30s %-10s %-9s %-12s %s\n' "$sp" "${tbl:-?}" \
      "${LOGROWS[$tbl]:-${EXISTS[$tbl]:+unlogged}}" "${LOGDATA[$tbl]:-none}" \
      "$( [ -s "$INFO/$sp/$sp.TE_info.tsv" ] && echo "$(( $(wc -l < "$INFO/$sp/$sp.TE_info.tsv") - 1 )) rows on disk" || echo 'no table on disk')"
  done
  exit 0
fi

# ---------------------------------------------------------------- candidates
candidates=()
if [ ${#WANT[@]} -gt 0 ]; then
  candidates=("${WANT[@]}")
else
  for sp in $(cut -f1 "$MANIFEST" | tail -n +2); do
    f="$INFO/$sp/$sp.TE_info.tsv"
    [ -s "$f" ] || continue
    (( $(wc -l < "$f") > 1 )) || continue          # header-only = not really done
    # .TE_summary.tsv is written after the .TE_info.tsv handle is closed, so its
    # presence is what proves the table is complete rather than mid-write.
    [ -s "$INFO/$sp/$sp.TE_summary.tsv" ] || continue
    candidates+=("$sp")
  done
fi

# --------------------------------------------------- decide (no site writes yet)
declare -a PLAN=() NEED=()
for sp in "${candidates[@]}"; do
  f="$INFO/$sp/$sp.TE_info.tsv"
  latin="${DISK2LATIN[$sp]:-}"
  if [ ! -s "$f" ]; then                  say "SKIP  $sp: no table on disk"; continue; fi
  if [ -z "$latin" ]; then                say "SKIP  $sp: not in the site's abbr table"; continue; fi
  abbr1="${ABBR1[$latin]}"; tbl="${abbr1}_TE"
  n=$(( $(wc -l < "$f") - 1 ))
  sz=$(stat -c %s "$f"); mt=$(stat -c %Y "$f")
  age=$(( $(date +%s) - mt ))

  # fast path: unchanged since our last verified load, and the table is still
  # there.  Checked before the age gate so that a species published a moment ago
  # is skipped silently rather than reported as "still being written".
  if [ "$FORCE" != 1 ] && [ "${LOGGED[$tbl]:-}" = "$sz:$mt" ] && [ -n "${EXISTS[$tbl]:-}" ]; then
    if [ "${LOGDATA[$tbl]}" = "homology-external" ]; then
      say "SKIP  $sp -> $tbl: older homology dataset (not ours; --force to replace)"
    fi
    continue
  fi

  # Only now do we intend to READ the file, so this is where youth matters: a
  # table younger than MIN_AGE may still be mid-write by 30_te_table.py.
  if [ "$FORCE" != 1 ] && (( age < MIN_AGE )); then
    say "WAIT  $sp -> $tbl: source is ${age}s old (< ${MIN_AGE}s), still being written"
    continue
  fi

  if [ -n "${EXISTS[$tbl]:-}" ] && [ -z "${LOGGED[$tbl]:-}" ]; then
    # A table we have no record of.  It is either a dataset this project did not
    # create, or one of ours from before the log existed.  Ask who owns it.
    owner=$(ssh_site "mysql -u$SITE_USER -p$SITE_USER -N -B $SITE_DB \
      -e \"SELECT TE_id FROM $tbl LIMIT 1;\"" 2>/dev/null || true)
    if [[ "$owner" == TE_homo_* ]] && [ "$FORCE" != 1 ]; then
      say "SKIP  $sp -> $tbl: existing table is the older homology dataset (use --force to replace)"
      PLAN+=("skip-homology|$sp|$tbl|$latin|$sz|$mt")
      continue
    fi
  fi

  # Migration only: a table we have never logged that already holds exactly what
  # is on disk.  Adopt it rather than reload, so the first run after this script
  # gained the log did not rewrite 13 live tables.
  #
  # The `-z LOGGED` guard matters: once a table IS logged, a changed source must
  # always reload.  The row count cannot tell a corrected annotation from the one
  # already published, so adopting on a count match alone would leave the old
  # data on the site under a fresh log entry -- silently, and precisely when a
  # 34_redo_anno.sh correction is the reason the file changed.
  if [ "$FORCE" != 1 ] && [ -n "${EXISTS[$tbl]:-}" ] && [ -z "${LOGGED[$tbl]:-}" ]; then
    have=$(ssh_site "mysql -u$SITE_USER -p$SITE_USER -N -B $SITE_DB \
      -e \"SELECT COUNT(*) FROM $tbl;\"" 2>/dev/null || true)
    if [ "${have:-x}" = "$n" ]; then
      say "ADOPT $sp -> $tbl: already holds $n rows, recording it in the log"
      PLAN+=("adopt|$sp|$tbl|$latin|$sz|$mt")
      continue
    fi
    say "STALE $sp -> $tbl: holds ${have:-?} rows but $n are on disk"
  fi

  say "PUB   $sp -> $tbl ($latin), $n rows"
  PLAN+=("publish|$sp|$tbl|$latin|$sz|$mt")
  NEED+=("$sp")
done

if [ ${#PLAN[@]} -eq 0 ]; then say "nothing to publish"; exit 0; fi
if [ "$DRY" = 1 ]; then say "--dry-run: ${#NEED[@]} species would be loaded"; exit 0; fi

# ---------------------------------------------------------------- site writes
if [ ${#NEED[@]} -gt 0 ]; then
  ssh_site "mkdir -p '$STAGE'"
  # LOCAL INFILE is a global; enable it only when there is a load to do, and put
  # it back however this script exits.
  ssh_site "mysql -u$SITE_USER -p$SITE_USER -e 'SET GLOBAL local_infile=1;'" >/dev/null 2>&1
fi
restore_infile() {
  [ ${#NEED[@]} -gt 0 ] || return 0
  ssh_site "mysql -u$SITE_USER -p$SITE_USER -e 'SET GLOBAL local_infile=0;'" >/dev/null 2>&1 || true
}
trap 'restore_infile; rm -rf "$tmp"' EXIT

rc=0
for entry in "${PLAN[@]}"; do
  IFS='|' read -r act sp tbl latin sz mt <<< "$entry"
  f="$INFO/$sp/$sp.TE_info.tsv"
  n=$(( $(wc -l < "$f") - 1 ))

  if [ "$act" = "skip-homology" ] || [ "$act" = "adopt" ]; then
    # record the decision so the next poll takes the fast path
    # Both are bookkeeping, not loads: for adopt, $n already equals the row count
    # in the table (that is why it was adopted); for a guarded skip it means
    # nothing, so record it as 0 rather than claim a count we never verified.
    dataset="edta"; rows="$n"
    if [ "$act" = "skip-homology" ]; then dataset="homology-external"; rows=0; fi
    latin_sql=$(printf '%s' "$latin" | sed "s/'/''/g")
    ssh_site "mysql -u$SITE_USER -p$SITE_USER $SITE_DB -e \"
      REPLACE INTO TE_publish_log
        (table_name, species, rows_loaded, src_size, src_mtime, dataset, published_at)
      VALUES ('$tbl', '$latin_sql', ${rows:-0}, $sz, $mt, '$dataset', NOW());\"" >/dev/null 2>&1 \
      && say "LOGGED $tbl ($dataset)" || { say "WARN  could not log $tbl"; rc=1; }
    continue
  fi

  # The SQL travels as its own file rather than a heredoc: a nested unquoted
  # heredoc made the remote shell command-substitute the backticks around the
  # table name.  Latin name is single-quote-escaped for the SQL literal.
  gzip -c "$f" > "$tmp/$sp.tsv.gz"
  latin_sql=$(printf '%s' "$latin" | sed "s/'/''/g")
  cat > "$tmp/$sp.sql" <<SQL
DROP TABLE IF EXISTS $tbl;
CREATE TABLE $tbl (
  species TEXT, TE_id TEXT, scaffold TEXT, TE_start TEXT, TE_end TEXT,
  related_gene TEXT, region TEXT, TE_type TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
LOAD DATA LOCAL INFILE '$STAGE/$sp.tsv'
INTO TABLE $tbl
CHARACTER SET utf8mb4
FIELDS TERMINATED BY '\t' ESCAPED BY ''
LINES TERMINATED BY '\n'
IGNORE 1 LINES
(species, TE_id, scaffold, TE_start, TE_end, related_gene, region, TE_type)
SET species = '$latin_sql';
SELECT CONCAT('VERIFY ', COUNT(*), ' ', MIN(species)) FROM $tbl;
SQL

  scp -q -o BatchMode=yes "$tmp/$sp.tsv.gz" "$tmp/$sp.sql" \
      "$SITE_USER@$SITE_HOST:$STAGE/"
  out=$(ssh_site "cd '$STAGE' && gunzip -f '$sp.tsv.gz' && \
       mysql --local-infile=1 -u$SITE_USER -p$SITE_USER $SITE_DB < '$sp.sql' 2>&1 | grep -v 'Using a password'; \
       rm -f '$sp.tsv' '$sp.sql'") || true
  echo "$out" | sed 's/^/      /'

  # Only record a load that verifiably landed: right row count, and the species
  # column rewritten to the Latin name the site filters on.
  if [[ "$out" == *"VERIFY $n $latin"* ]]; then
    ssh_site "mysql -u$SITE_USER -p$SITE_USER $SITE_DB -e \"
      REPLACE INTO TE_publish_log
        (table_name, species, rows_loaded, src_size, src_mtime, dataset, published_at)
      VALUES ('$tbl', '$latin_sql', $n, $sz, $mt, 'edta', NOW());\"" >/dev/null 2>&1 \
      && say "OK    $sp -> $tbl: $n rows live" \
      || { say "WARN  loaded $tbl but could not log it"; rc=1; }
  else
    say "FAIL  $sp -> $tbl: load did not verify (expected 'VERIFY $n $latin')"
    rc=1
  fi
done

exit $rc
