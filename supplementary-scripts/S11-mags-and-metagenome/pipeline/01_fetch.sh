#!/usr/bin/env bash
# Stage 01 -- fetch the sequence data each MAG's annotation will be built from.
#
# Two sources, because NCBI does not treat the two accession classes the same:
#
#   GCA_* (109 of the 115): NCBI publishes the assembled genome but NO protein
#       set -- neither PGAP nor submitter annotation.  We download GENOME_FASTA
#       and call genes ourselves in stage 02.  Requesting PROT_FASTA for these
#       returns a valid zip that simply contains no .faa, so a successful HTTP
#       status is not evidence that proteins exist.
#
#   GCF_* (6 of the 115): RefSeq assemblies with a real NCBI protein set.  We
#       download PROT_FASTA and skip gene prediction for them.
#
# The distinction is recorded per MAG in fetch_status.tsv so stage 02 knows
# which MAGs still need genes called, and so the web page can state the
# provenance of each protein set honestly.
set -uo pipefail

source "$(dirname "${BASH_SOURCE[0]}")/env.sh"

manifest="$MAG_ROOT/work/mags_manifest.tsv"
raw="$MAG_ROOT/work/raw"
status="$MAG_ROOT/work/fetch_status.tsv"
log="$MAG_ROOT/work/01_fetch.log"

mkdir -p "$raw"
: > "$log"
printf 'accession\tsource\tprotein_source\tgenome_file\tprotein_file\tn_genome_seqs\tn_proteins\thttp\n' > "$status"

api="https://api.ncbi.nlm.nih.gov/datasets/v2alpha/genome/accession"

fetch() {  # fetch <acc> <annotation_type> <dest.zip>  -> prints "http size"
    curl -sS -L --max-time 300 \
        "$api/$1/download?include_annotation_type=$2" \
        -o "$3" -w "%{http_code} %{size_download}" 2>/dev/null
}

# Snapshot the manifest so a concurrent edit cannot change what this run means.
tail -n +2 "$manifest" | cut -f4 | grep -v '^$' > "$raw/accessions.txt"
n_total=$(wc -l < "$raw/accessions.txt")
echo "[01] $n_total MAG accessions" | tee -a "$log"

i=0
while read -r acc; do
    i=$((i + 1))
    zip="$raw/$acc.zip"
    prot_faa="$raw/$acc.proteins.faa"
    genome_fna="$raw/$acc.genome.fna"

    if [ "${acc%%_*}" = "GCF" ]; then
        src="NCBI RefSeq protein set"
        read -r http _size < <(fetch "$acc" PROT_FASTA "$zip")
        rm -f "$prot_faa"
        unzip -p "$zip" '*.faa' > "$prot_faa" 2>/dev/null || true
        np=$(grep -c '^>' "$prot_faa" 2>/dev/null || echo 0)
        if [ "$np" -gt 0 ]; then
            echo -e "$acc\tGCF\tNCBI\t-\t$acc.proteins.faa\t0\t$np\t$http" >> "$status"
        else
            # A GCF accession with no protein set would otherwise be dropped
            # silently and simply never appear on the site.
            echo -e "$acc\tGCF\tFAILED\t-\t-\t0\t0\t$http" >> "$status"
            echo "[01] WARN $acc GCF but no proteins (http=$http)" | tee -a "$log"
        fi
        echo "[01] $i/$n_total $acc GCF proteins=$np" | tee -a "$log"
    else
        src="Prodigal"
        read -r http _size < <(fetch "$acc" GENOME_FASTA "$zip")
        rm -f "$genome_fna"
        unzip -p "$zip" '*.fna' > "$genome_fna" 2>/dev/null || true
        ng=$(grep -c '^>' "$genome_fna" 2>/dev/null || echo 0)
        if [ "$ng" -gt 0 ]; then
            echo -e "$acc\tGCA\tProdigal\t$acc.genome.fna\t$acc.proteins.faa\t$ng\t0\t$http" >> "$status"
        else
            echo -e "$acc\tGCA\tFAILED\t-\t-\t0\t0\t$http" >> "$status"
            echo "[01] WARN $acc no genome sequence (http=$http)" | tee -a "$log"
        fi
        echo "[01] $i/$n_total $acc GCA contigs=$ng" | tee -a "$log"
    fi
done < "$raw/accessions.txt"

# Fail loudly rather than let a partial download look like a complete run.
n_ok=$(awk -F'\t' 'NR>1 && $3!="FAILED"' "$status" | wc -l)
n_bad=$(awk -F'\t' 'NR>1 && $3=="FAILED"' "$status" | wc -l)
echo "[01] done: $n_ok ok, $n_bad failed" | tee -a "$log"
[ "$n_bad" -eq 0 ] || exit 1
