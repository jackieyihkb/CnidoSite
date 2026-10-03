#!/usr/bin/env bash
# Stage 04 -- UniProt (Swiss-Prot) best hit per protein.
#
# This is the "UniProt (Swiss-Prot)" column of the transcriptome pages, which
# the MAG pages mirror.  InterProScan does not do this: its signature matches
# say which domain families a protein belongs to, not which characterised
# protein it most resembles, and the two answer different questions.
#
# Searches every MAG's proteins in a single Diamond run rather than 115 runs.
# Diamond's index load dominates a run this small, so one invocation over the
# ~0.4M combined proteins is much cheaper than 115 separate ones.
#
# Thresholds: E <= 1e-5 and a single best hit per query.  Swiss-Prot is a small,
# well-curated set, so an E-value this strict still recovers a hit for the large
# majority of bacterial proteins while keeping low-complexity noise that a
# looser cutoff would admit out of the table the site renders.
set -euo pipefail

source "$(dirname "${BASH_SOURCE[0]}")/env.sh"

pred="$MAG_ROOT/work/predicted"
raw="$MAG_ROOT/work/raw"
refs="$MAG_ROOT/work/refs"
res="$MAG_ROOT/results"
log="$MAG_ROOT/work/04_uniprot.log"
db="$refs/uniprot_sprot"
all_faa="$refs/all_mag_proteins.faa"
p2m="$refs/protein2mag.tsv"
hits="$res/uniprot_hits.tsv"
mkdir -p "$res"

[ -s "$db.dmnd" ] || { echo "[04] ERROR: $db.dmnd missing; run stage 00 first" >&2; exit 1; }

# --- one combined FASTA, with every protein attributable to its MAG -----------
# The two sources use incompatible ID schemes: stage 02 names Prodigal calls
# "<acc>_<n>" while NCBI ships bare accessions like "WP_012345678.1" that carry
# no trace of which MAG they came from.  An explicit protein2mag map is written
# instead of trying to parse the accession back out of the ID, because the GCF
# IDs cannot be parsed that way at all and a parsing bug would silently
# misattribute proteins between MAGs.
norm_dir="$MAG_ROOT/work/norm"
mkdir -p "$norm_dir"
: > "$all_faa"
: > "$p2m"
n_sets=0
while IFS=$'\t' read -r acc src psrc gfile pfile ngen nprot http; do
    [ "$acc" = "accession" ] && continue
    [ "$pfile" = "-" ] && continue
    case "$src" in
        GCA) faa="$pred/$acc/proteins.faa" ;;
        GCF) faa="$raw/$acc.proteins.faa" ;;
        *)   continue ;;
    esac
    [ -s "$faa" ] || continue
    # Prefix GCF ids so the FASTA stays self-describing for anyone reading it
    # without the map; the map remains the authority.  GCA ids already carry the
    # accession.  sed only touches the `>` line's first token, so any trailing
    # description survives.
    norm="$norm_dir/$acc.faa"
    if [ "$src" = "GCF" ]; then
        sed "s/^>/>${acc}_/" "$faa" > "$norm"
    else
        cp -f "$faa" "$norm"
    fi
    awk -v acc="$acc" '/^>/ {
            id = substr($1, 2)
            sub("^" acc "_", "", id)
            print acc "\t" id
        }' "$norm" >> "$p2m"
    cat "$norm" >> "$all_faa"
    n_sets=$((n_sets + 1))
done < "$MAG_ROOT/work/fetch_status.tsv"

n_prot=$(grep -c '^>' "$all_faa")
n_map=$(wc -l < "$p2m")
echo "[04] $n_prot proteins from $n_sets MAGs, $n_map id mappings" | tee "$log"
[ "$n_prot" -eq "$n_map" ] || {
    echo "[04] ERROR: protein2mag has $n_map rows for $n_prot proteins" >&2; exit 1; }
[ "$n_prot" -gt 0 ] || { echo "[04] ERROR: no proteins" >&2; exit 1; }

# --- search -------------------------------------------------------------------
echo "[04] diamond blastp vs Swiss-Prot ($(grep -c '^>' "$refs/uniprot_sprot.fasta") seqs)" | tee -a "$log"
"$DIAMOND" blastp \
    --db "$db" --query "$all_faa" --out "$hits" \
    --outfmt 6 qseqid sseqid pident length evalue bitscore stitle \
    --max-target-seqs 1 --evalue 1e-5 \
    --threads "${DIAMOND_THREADS:-128}" --sensitive --quiet

sort -k1,1 -o "$hits" "$hits"
n_hit=$(wc -l < "$hits")
pct=$(awk -v h="$n_hit" -v p="$n_prot" 'BEGIN{printf "%.1f", 100*h/p}')
echo "[04] done: $n_hit/$n_prot proteins hit Swiss-Prot ($pct%)" | tee -a "$log"
