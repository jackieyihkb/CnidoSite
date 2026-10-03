#!/usr/bin/env bash
# Stage 00 -- build the reference databases stages 04 and 05 search against.
#
# Kofam ships as 27k separate HMM files (one per KO).  hmmscan wants a single
# pressed database, so they are concatenated and pressed here.  Building this
# takes a few minutes and ~15 GB, which is why it is its own stage rather than
# being redone inside every search.
#
# Swiss-Prot is built from the UniProt release stage 04 downloads; the .dmnd is
# left alongside the FASTA so the two cannot drift apart.
set -euo pipefail

source "$(dirname "${BASH_SOURCE[0]}")/env.sh"

refs="$MAG_ROOT/work/refs"
kdb="$refs/kofam"
log="$MAG_ROOT/work/00_refs.log"
HMMER_BIN="${MAG_HMMER_BIN:-/mnt/sda/lurui/software/miniconda3/envs/marine_sm/bin}"
mkdir -p "$refs"

echo "[00] building reference databases" | tee "$log"

# --- Kofam --------------------------------------------------------------------
if [ -s "$kdb/kofam.hmm.h3m" ]; then
    echo "[00] kofam already pressed, skipping" | tee -a "$log"
else
    mkdir -p "$kdb"
    # Concatenate deterministically (sorted) so a rebuild is byte-identical and
    # the pressed database can be reused rather than recomputed on a re-run.
    echo "[00] concatenating $(ls "$KOFAM_DIR/profiles"/*.hmm | wc -l) Kofam profiles" | tee -a "$log"
    ls "$KOFAM_DIR/profiles"/*.hmm | sort | xargs cat > "$kdb/kofam.hmm"
    echo "[00] pressing $(du -h "$kdb/kofam.hmm" | cut -f1) HMM database (takes several minutes)" | tee -a "$log"
    "$HMMER_BIN/hmmpress" -f "$kdb/kofam.hmm" >> "$log" 2>&1
fi
cp -f "$KOFAM_DIR/ko_list" "$kdb/ko_list"
echo "[00] kofam: $(grep -c '^K' "$kdb/ko_list") KO thresholds" | tee -a "$log"

# --- KEGG KO -> pathway -------------------------------------------------------
# ko2pathway and the KO name list come from the KEGG REST API in stage 04's
# download; keep both next to the Kofam data because they are only ever used
# together.
for f in ko2pathway.tsv ko_list_names.tsv; do
    [ -s "$refs/$f" ] || { echo "[00] ERROR: missing $refs/$f" >&2; exit 1; }
done
echo "[00] kegg: $(wc -l < "$refs/ko2pathway.tsv") ko-pathway links" | tee -a "$log"

# --- InterProScan sanity ------------------------------------------------------
[ -d "$(dirname "$IPS")/data/pfam" ] || { echo "[00] ERROR: InterProScan data incomplete at $IPS" >&2; exit 1; }
echo "[00] interproscan data present" | tee -a "$log"

echo "[00] DONE" | tee -a "$log"
