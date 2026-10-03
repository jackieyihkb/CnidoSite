#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# Build the single conda environment used for the whole TE annotation pipeline.
# Run this ONCE.  Everything (EDTA, RepeatMasker, RepeatModeler, rmblast,
# GenomeTools/LTRharvest, LTR_Retriever, TEtrimmer, bedtools, AGAT) lives in
# this one environment:  te_anno
#
#   bash 00_setup_env.sh
# ---------------------------------------------------------------------------
set -euo pipefail

ENV_NAME=te_anno
PIPELINE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo ">>> creating conda env '${ENV_NAME}' (this takes 10-20 min)"

mamba create -y -n "${ENV_NAME}" \
    -c conda-forge -c bioconda \
    edta=2.2.2 \
    repeatmasker \
    repeatmodeler \
    rmblast \
    genometools-genometools \
    ltr_retriever \
    tetrimmer=1.7.2 \
    bedtools \
    samtools \
    seqkit \
    agat \
    python=3.10 \
    pandas
# NOTE: python must be 3.10 -- TEtrimmer 1.7.2 requires python <3.11 and
# mamba fails to solve the environment outright if 3.11 is requested.

echo ">>> exporting the environment definition"
mamba env export -n "${ENV_NAME}" --no-builds > "${PIPELINE_DIR}/te_anno.yml"

echo ">>> checking the required executables"
for tool in EDTA.pl RepeatMasker RepeatModeler rmblastn gt trf LTR_FINDER_parallel \
            TEtrimmer bedtools seqkit agat_sp_extract_sequences.pl; do
    if conda run -n "${ENV_NAME}" command -v "${tool}" >/dev/null 2>&1; then
        printf '  OK       %s\n' "${tool}"
    else
        printf '  MISSING  %s\n' "${tool}"
    fi
done

echo
echo "Environment '${ENV_NAME}' is ready.  Activate it with:"
echo "    conda activate ${ENV_NAME}"
