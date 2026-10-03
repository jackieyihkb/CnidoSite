#!/usr/bin/env bash
# Stage 02 -- call genes on the MAGs NCBI ships without a protein set.
#
# Only the GCA_* MAGs reach this stage.  The GCF_* ones already have an NCBI
# RefSeq protein set from stage 01, and re-predicting them would replace curated
# PGAP calls with worse ones, so they are left alone.
#
# Prodigal runs in `-p meta` mode, not `-p single`.  These are metagenome-
# assembled genomes: bins of varying completeness, so the single-genome model
# (which assumes one closed, complete replicon and hunts for a single origin)
# is the wrong assumption and loses genes at bin edges and in low-coverage
# regions.  `meta` is what GTDB and CheckM use on MAGs for the same reason.
#
# Protein IDs are rewritten to <accession>_<n> because Prodigal's own IDs are
# only unique within one genome, and stage 06 has to merge every MAG's hits into
# a single table.
set -uo pipefail

source "$(dirname "${BASH_SOURCE[0]}")/env.sh"

raw="$MAG_ROOT/work/raw"
pred="$MAG_ROOT/work/predicted"
status="$MAG_ROOT/work/predict_status.tsv"
log="$MAG_ROOT/work/02_predict.log"
mkdir -p "$pred"
: > "$log"
printf 'accession\tn_proteins\tstatus\n' > "$status"

if [ ! -x "$PRODIGAL" ]; then
    echo "[02] ERROR: prodigal not found at $PRODIGAL" | tee -a "$log" >&2
    exit 1
fi

# One job per MAG; the box has 768 cores and each Prodigal run is single-threaded
# and short, so this is the cheapest way to get through 109 genomes.
run_one() {
    local acc="$1"
    local fna="$raw/$acc.genome.fna"
    local outdir="$pred/$acc"
    mkdir -p "$outdir"
    if [ ! -s "$fna" ]; then
        echo -e "$acc\t0\tNO_GENOME" >> "$status"
        return
    fi
    if ! "$PRODIGAL" -i "$fna" -p meta -q \
            -a "$outdir/proteins.faa" -d "$outdir/genes.fna" \
            -f gff -o "$outdir/genes.gff" >/dev/null 2>&1; then
        echo -e "$acc\t0\tPRODIGAL_FAILED" >> "$status"
        return
    fi
    # Rename to accession-scoped, collision-free IDs, and strip the stop codon.
    #
    # Prodigal's -a output terminates every sequence with '*'.  InterProScan
    # rejects the whole input file outright if any sequence contains one
    # ("* is not a valid IUPAC amino acid character"), so leaving it in fails
    # the entire stage rather than a single protein.  The trailing '*' is the
    # stop codon, not a residue, so removing it recovers the true protein
    # sequence.  Checked on this data: 295,679 of 323,116 sequences carry one
    # and none has an internal '*', so this is a pure trailing-stop strip.
    # Diamond and HMMER tolerate '*', but they are given the same cleaned files
    # so that every stage sees identical sequences and identical lengths.
    #
    # The GFF keeps Prodigal's numeric IDs and coordinates, so a protein is
    # still traceable back to its position on a contig.
    awk -v acc="$acc" '
        /^>/ {
            n++
            sub(/^>/, "", $0)
            split($0, a, " ")
            print ">" acc "_" n " " a[2]
            next
        }
        { gsub(/\*/, ""); print }
    ' "$outdir/proteins.faa" > "$outdir/proteins.renamed.faa"
    mv "$outdir/proteins.renamed.faa" "$outdir/proteins.faa"
    local np
    np=$(grep -c '^>' "$outdir/proteins.faa" 2>/dev/null || echo 0)
    echo -e "$acc\t$np\tOK" >> "$status"
}
export -f run_one
export raw pred status PRODIGAL

# Only GCA MAGs that fetched a genome in stage 01.
awk -F'\t' 'NR>1 && $2=="GCA" && $3!="FAILED" {print $1}' "$MAG_ROOT/work/fetch_status.tsv" > "$raw/gca_accessions.txt"
n=$(wc -l < "$raw/gca_accessions.txt")
echo "[02] predicting genes for $n GCA MAGs with -p meta" | tee -a "$log"

# Cap concurrency: 109 parallel JVMs is not the issue here (Prodigal is native),
# but the writes to one shared status file are.  Chunked so a crash mid-run
# still leaves earlier MAGs recorded.
split -n l/8 -d "$raw/gca_accessions.txt" "$raw/gca_chunk_"
for chunk in "$raw"/gca_chunk_*; do
    ( while read -r acc; do run_one "$acc"; done < "$chunk" ) &
done
wait

rm -f "$raw"/gca_chunk_*
sort -t$'\t' -k1,1 -o "$status" "$status"

n_ok=$(awk -F'\t' 'NR>1 && $3=="OK"' "$status" | wc -l)
n_bad=$(awk -F'\t' 'NR>1 && $3!="OK"' "$status" | wc -l)
echo "[02] done: $n_ok predicted, $n_bad failed" | tee -a "$log"
[ "$n_bad" -eq 0 ] || exit 1
