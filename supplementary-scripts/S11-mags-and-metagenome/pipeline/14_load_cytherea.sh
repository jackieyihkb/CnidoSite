#!/bin/bash
# Load the 14 Acropora cytherea MAGs' annotation into the six shared cnidosite
# tables.  Runs on the server, from the directory holding dbload_cytherea/.
#
# Two differences from 09_load_kenti_dbload.sh, both deliberate:
#
#   * No mag_annot load.  These MAGs already have their inventory rows, keyed by
#     bare RefSeq accession with PGAP locus tags, and replacing them would
#     discard curated data to gain nothing.  Only the two annotation loaders run.
#
#   * A hard id gate before anything is written.  The six tables join on
#     `protein`, so if the ids staged here do not match the ids in mag_annot the
#     load succeeds, the counts look right, and the page renders empty cells --
#     no error anywhere.  That is exactly the failure that hit the six NCBI MAGs
#     in the main deployment.  The gate compares the staged ids against
#     mag_annot for every MAG and refuses to run if any id is unmatched.
#
# Both loaders DELETE the MAG's existing rows for that MAG before inserting, so
# re-running is idempotent.
#
# Usage:  nohup bash 14_load_cytherea.sh > /dev/null 2>&1 &
set -u

DIR="$(cd "$(dirname "$0")" && pwd)"
STAGE="$DIR/dbload_cytherea"
LOG="$DIR/load_cytherea.log"
DONE="$DIR/load_cytherea.done"
PHP=(env PHP_INI_SCAN_DIR=/etc/php/7.4/apache2/conf.d php -c /etc/php/7.4/apache2/php.ini)
KOREF=/mnt/sda/jackie/tools/kofam-db/ko_ref.tsv
MYSQL=(mysql -ujackie "-p<REDACTED>" cnidaria -N -B)

touch "$DONE"
say() { printf '%s %s\n' "$(date '+%F %T')" "$*" >> "$LOG"; }

if [ ! -d "$STAGE" ]; then say "ABORT: no staging dir $STAGE"; exit 1; fi
if [ ! -s "$STAGE/staged_ids.tsv" ]; then say "ABORT: $STAGE/staged_ids.tsv missing"; exit 1; fi

# --- gate: every staged id must exist in mag_annot ----------------------------
# Pulled once and diffed locally; calling mysql per id would be 60k round trips.
"${MYSQL[@]}" -e "
    SELECT mag, protein FROM mag_annot
    WHERE mag IN ($(cut -f1 "$STAGE/staged_ids.tsv" | sort -u | sed "s/.*/'&'/" | paste -sd,))
" 2>/dev/null | tr -d '\r' | sort > "$DIR/.db_ids.tsv"
sort -u "$STAGE/staged_ids.tsv" > "$DIR/.staged_ids.sorted"
n_db=$(wc -l < "$DIR/.db_ids.tsv")
n_st=$(wc -l < "$DIR/.staged_ids.sorted")
n_orphan=$(comm -13 "$DIR/.db_ids.tsv" "$DIR/.staged_ids.sorted" | wc -l)
say "gate: $n_db ids in mag_annot, $n_st staged, $n_orphan unmatched"
if [ "$n_orphan" -ne 0 ]; then
    say "ABORT: $n_orphan staged ids have no mag_annot row; the page would render empty"
    comm -13 "$DIR/.db_ids.tsv" "$DIR/.staged_ids.sorted" | head -20 >> "$LOG"
    exit 1
fi

total=0; loaded=0; skipped=0; failed=0
for ko in "$STAGE"/*.kofam.tsv; do
    acc="$(basename "$ko" .kofam.tsv)"
    total=$((total + 1))
    if grep -qxF "$acc" "$DONE"; then skipped=$((skipped + 1)); continue; fi

    tsv="$STAGE/$acc.iprscan.tsv"
    for f in "$tsv" "$ko"; do
        if [ ! -s "$f" ]; then say "FAIL $acc: missing or empty $f"; failed=$((failed + 1)); continue 2; fi
    done

    out="$("${PHP[@]}" "$DIR/load_iprscan.php" --mag "$acc" --tsv "$tsv" --apply 2>&1)"; rc=$?
    if [ $rc -ne 0 ]; then say "FAIL $acc (iprscan)"; printf '%s\n' "$out" >> "$LOG"; failed=$((failed + 1)); continue; fi

    # Some KOs are absent from ko_ref.tsv (K18707 among them) and make this
    # loader emit PHP notices with blank Abbreviation/Pathway fields.  The KO
    # still lands, which is the part that matters, so the notices are filtered
    # out of the log rather than treated as failure.  The exit status must be
    # taken from PHP before the grep -- piping straight into grep reports grep's
    # status and swallows a real failure.
    raw="$("${PHP[@]}" "$DIR/load_kofam.php" --mag "$acc" --out "$ko" --koref "$KOREF" --apply 2>&1)"; rc=$?
    if [ $rc -ne 0 ]; then say "FAIL $acc (kofam)"; printf '%s\n' "$raw" >> "$LOG"; failed=$((failed + 1)); continue; fi
    out="$(printf '%s\n' "$raw" | grep -v '^PHP Notice:')"
    n_kegg="$(printf '%s' "$out" | sed -n 's/.*wrote mag_kegg_terms \([0-9]*\).*/\1/p')"

    echo "$acc" >> "$DONE"
    loaded=$((loaded + 1))
    say "OK   $acc  kegg=$n_kegg"
done

rm -f "$DIR/.db_ids.tsv" "$DIR/.staged_ids.sorted"
say "done: total=$total loaded=$loaded skipped=$skipped failed=$failed"
