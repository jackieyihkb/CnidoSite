#!/bin/bash
# Load a staged batch of MAGs into the six shared cnidosite annotation tables,
# so MAG_detail.php renders them under "Functional Annotation of the Proteins".
#
# Runs on the server, from the directory holding the staged batch.  This is
# 09_load_kenti_dbload.sh with the batch made a parameter, so the 186-MAG
# extension does not need a second near-identical script; the A. cytherea batch
# uses 14_load_cytherea.sh instead, because it has no mag_annot to load and
# needs an id gate that this one does not (see that script's header).
#
#   bash 11_load_dbload.sh dbload_ext
#
# One MAG at a time in accession order; resumable via the per-batch .done
# marker, so a re-run after an interruption picks up where it stopped.
#
# Order is not negotiable: load_mag_annot.php must go first because
# load_iprscan.php and load_kofam.php both abort if mag_annot has no rows for
# the MAG -- the detail page is driven off mag_annot, so annotation rows with no
# inventory row behind them would be invisible anyway.
#
# Both annotation loaders DELETE the MAG's existing rows before inserting, so
# re-running a MAG is idempotent and a corrected input overwrites a bad load.
# (Plain INSERT IGNORE would silently keep the old rows -- that trap already bit
# once on the pilot data.)
set -u

DIR="$(cd "$(dirname "$0")" && pwd)"
BATCH="${1:-dbload_kenti}"
STAGE="$DIR/$BATCH"
LOG="$DIR/load_${BATCH}.log"
DONE="$DIR/load_${BATCH}.done"
PHP=(env PHP_INI_SCAN_DIR=/etc/php/7.4/apache2/conf.d php -c /etc/php/7.4/apache2/php.ini)
KOREF=/mnt/sda/jackie/tools/kofam-db/ko_ref.tsv

touch "$DONE"
say() { printf '%s %s\n' "$(date '+%F %T')" "$*" >> "$LOG"; }

if [ ! -d "$STAGE" ]; then say "ABORT: no staging dir $STAGE"; exit 1; fi
say "batch=$BATCH"

total=0; loaded=0; skipped=0; failed=0
for faa in "$STAGE"/*.faa; do
    acc="$(basename "$faa" .faa)"
    total=$((total + 1))
    if grep -qxF "$acc" "$DONE"; then skipped=$((skipped + 1)); continue; fi

    tsv="$STAGE/$acc.iprscan.tsv"
    ko="$STAGE/$acc.kofam.tsv"
    for f in "$faa" "$tsv" "$ko"; do
        if [ ! -s "$f" ]; then say "FAIL $acc: missing or empty $f"; failed=$((failed + 1)); continue 2; fi
    done

    out="$("${PHP[@]}" "$DIR/load_mag_annot.php" --mag "$acc" --faa "$faa" --apply 2>&1)"; rc=$?
    if [ $rc -ne 0 ]; then say "FAIL $acc (mag_annot)"; printf '%s\n' "$out" >> "$LOG"; failed=$((failed + 1)); continue; fi
    n_annot="$(printf '%s' "$out" | sed -n 's/.*WROTE \([0-9]*\) rows.*/\1/p')"

    out="$("${PHP[@]}" "$DIR/load_iprscan.php" --mag "$acc" --tsv "$tsv" --apply 2>&1)"; rc=$?
    if [ $rc -ne 0 ]; then say "FAIL $acc (iprscan)"; printf '%s\n' "$out" >> "$LOG"; failed=$((failed + 1)); continue; fi

    # The handful of KOs that ko_ref.tsv does not know (K18707 among them) make
    # this loader emit PHP notices and write blank Abbreviation/Pathway fields.
    # The KO itself still lands, which is the part that matters; keep the noise
    # out of the log but do not treat it as a failure.  The exit status has to be
    # taken from PHP, before the grep -- piping straight into grep would report
    # grep's status and swallow a real failure.
    raw="$("${PHP[@]}" "$DIR/load_kofam.php" --mag "$acc" --out "$ko" --koref "$KOREF" --apply 2>&1)"; rc=$?
    if [ $rc -ne 0 ]; then say "FAIL $acc (kofam)"; printf '%s\n' "$raw" >> "$LOG"; failed=$((failed + 1)); continue; fi
    out="$(printf '%s\n' "$raw" | grep -v '^PHP Notice:')"
    n_kegg="$(printf '%s' "$out" | sed -n 's/.*wrote mag_kegg_terms \([0-9]*\).*/\1/p')"

    echo "$acc" >> "$DONE"
    loaded=$((loaded + 1))
    say "OK   $acc  annot=$n_annot  kegg=$n_kegg"
done

say "done: total=$total loaded=$loaded skipped=$skipped failed=$failed"
