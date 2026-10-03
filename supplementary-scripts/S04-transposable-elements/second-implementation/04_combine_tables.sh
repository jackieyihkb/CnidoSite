#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# Merge the per-species TE tables into one master table and produce summaries.
#
#   bash 04_combine_tables.sh
#
# Outputs (in results/):
#   All_species.TE_info.tsv.gz        every TE of every species, one header
#   TE_summary_by_species.tsv         per-species counts
#   TE_summary_by_type.tsv            per TE-type counts
#
# Per-species tables are already in scaffold/position order, so the
# concatenation keeps a sensible order without a global sort.
# ---------------------------------------------------------------------------
set -euo pipefail

ROOT=/mnt/sda/jackie/cnidaria_omics/genome_TE
TABLES="${ROOT}/results/TE_tables"
OUT="${ROOT}/results"

mkdir -p "${OUT}"

shopt -s nullglob
FILES=("${TABLES}"/*.TE_info.tsv)
if [ ${#FILES[@]} -eq 0 ]; then
    echo "no per-species tables found in ${TABLES}" >&2
    exit 1
fi
echo ">>> merging ${#FILES[@]} per-species tables"

HEADER=$'species\tTE_id\tscaffold\tTE_start\tTE_end\trelated_gene\tregion\tTE_type'
{
    echo "${HEADER}"
    for f in "${FILES[@]}"; do
        tail -n +2 "$f"
    done
} | gzip -1 > "${OUT}/All_species.TE_info.tsv.gz"

echo ">>> ${OUT}/All_species.TE_info.tsv.gz  ($(zcat "${OUT}/All_species.TE_info.tsv.gz" | wc -l) rows)"

# --- per-species summary --------------------------------------------------
{
    printf 'species\tn_TE\ttotal_bp\texon\tintron\tpromoter\tintergenic\n'
    zcat "${OUT}/All_species.TE_info.tsv.gz" | tail -n +2 | awk -F'\t' '
        {
            sp=$1; n[sp]++
            bp[sp] += $5-$4+1
            r[sp SUBSEP $7]++
        }
        END {
            for (s in n)
                printf "%s\t%d\t%d\t%d\t%d\t%d\t%d\n", s, n[s], bp[s],
                       r[s SUBSEP "exon"], r[s SUBSEP "intron"],
                       r[s SUBSEP "promoter"], r[s SUBSEP "intergenic"]
        }' | sort
} > "${OUT}/TE_summary_by_species.tsv"

# --- per-type summary -----------------------------------------------------
{
    printf 'TE_type\tn_TE\ttotal_bp\n'
    zcat "${OUT}/All_species.TE_info.tsv.gz" | tail -n +2 | awk -F'\t' '
        { n[$8]++; bp[$8] += $5-$4+1 }
        END { for (t in n) printf "%s\t%d\t%d\n", t, n[t], bp[t] }' | sort -k2,2nr
} > "${OUT}/TE_summary_by_type.tsv"

echo ">>> ${OUT}/TE_summary_by_species.tsv"
echo ">>> ${OUT}/TE_summary_by_type.tsv"
echo
echo "Done. Example of the master table:"
zcat "${OUT}/All_species.TE_info.tsv.gz" | head -5 | column -t -s$'\t'
