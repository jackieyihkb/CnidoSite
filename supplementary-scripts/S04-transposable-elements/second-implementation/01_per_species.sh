#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# De-novo TE annotation for ONE species, end to end:
#
#   1. decompress the genome
#   2. EDTA  : build a de-novo TE library and annotate the genome
#              (--anno 1 internally calls RepeatMasker with that library,
#               producing <genome>.mod.EDTA.TEanno.gff3)
#   3. (opt) RepeatMasker re-run against the EDTA library, GFF output
#   4. build the per-species TE information table
#
# Usage:  01_per_species.sh <Species_name> [threads] [--sensitive 1] [--with-rm] [--cleanup]
#
#   <Species_name>  basename of <Species>.fa.gz / <Species>.gff3.gz
#   threads         threads handed to EDTA / RepeatMasker (default 6)
#
# EDTA sensitivity defaults to 0 here: --sensitive 1 adds a RepeatModeler pass
# that roughly doubles the runtime, which is not affordable across all 145
# genomes on a 20-core machine.  Pass --sensitive 1 to enable it.
#
# Everything runs inside the single conda env `te_anno`.
# Re-running is safe: finished steps are skipped.
# ---------------------------------------------------------------------------
set -euo pipefail

ENV_NAME=te_anno
ROOT=/mnt/sda/jackie/cnidaria_omics/genome_TE
PIPELINE="${ROOT}/TE_pipeline"

SPECIES="${1:?usage: 01_per_species.sh <Species_name> [threads] [--sensitive 0|1] [--with-rm] [--cleanup] [--timeout-hours N]}"
THREADS="${2:-6}"
SENSITIVE=0
WITH_RM=0
CLEANUP=0
TIMEOUT_H=24
prev=""
for arg in "$@"; do
    case "$arg" in
        --with-rm) WITH_RM=1 ;;
        --cleanup) CLEANUP=1 ;;
    esac
    [ "$prev" = "--sensitive" ] && SENSITIVE="$arg"
    [ "$prev" = "--timeout-hours" ] && TIMEOUT_H="$arg"
    prev="$arg"
done

GENOME_GZ="${ROOT}/${SPECIES}.fa.gz"
GENE_GFF_GZ="${ROOT}/${SPECIES}.gff3.gz"
WORK="${ROOT}/work/${SPECIES}"
OUTDIR="${ROOT}/results/TE_tables"
LOG="${WORK}/${SPECIES}.pipeline.log"

mkdir -p "${WORK}" "${OUTDIR}"

# run a command inside the single pipeline environment
run() { conda run --no-capture-output -n "${ENV_NAME}" "$@"; }

log() { echo "[$(date '+%F %T')] ${SPECIES}: $*" | tee -a "${LOG}" >&2; }

log "=== start (threads=${THREADS}, sensitive=${SENSITIVE}, with_rm=${WITH_RM}, timeout=${TIMEOUT_H}h) ==="

# --- 0. sanity ------------------------------------------------------------
[ -s "${GENOME_GZ}" ]   || { log "ERROR: missing ${GENOME_GZ}";   exit 1; }
[ -s "${GENE_GFF_GZ}" ] || { log "ERROR: missing ${GENE_GFF_GZ}"; exit 1; }

# --- 1. decompress --------------------------------------------------------
GENOME="${WORK}/${SPECIES}.fa"
if [ ! -s "${GENOME}" ]; then
    log "decompressing genome"
    zcat "${GENOME_GZ}" > "${GENOME}"
else
    log "genome already decompressed"
fi

# --- 1b. rename sequences to EDTA-safe IDs --------------------------------
# EDTA aborts outright ("Fail to convert seq IDs to <= 13 characters!") when
# the first 13 characters of two sequence IDs collide, which they routinely do
# for names like HAP1_SCAFFOLD_3184 / HAP1_SCAFFOLD_3178.  Renaming up front
# makes EDTA's truncation a no-op and removes the whole failure mode.
# The map lets the TE table report the ORIGINAL scaffold names.
FASTA="${WORK}/${SPECIES}.renamed.fa"
IDMAP="${WORK}/${SPECIES}.idmap.tsv"
if [ ! -s "${FASTA}" ] || [ ! -s "${IDMAP}" ]; then
    log "renaming sequences to EDTA-safe IDs"
    run python "${PIPELINE}/02_rename_genome.py" \
        --genome "${GENOME}" --out "${FASTA}" --map "${IDMAP}" \
        >> "${LOG}" 2>&1
else
    log "renamed genome already present"
fi

# --- 2. EDTA --------------------------------------------------------------
# Outputs we care about:
#   ${GENOME}.mod.EDTA.TElib.fa       de-novo TE consensus library
#   ${GENOME}.mod.EDTA.TEanno.gff3    whole-genome TE annotation
TEANNO="${FASTA}.mod.EDTA.TEanno.gff3"
TELIB="${FASTA}.mod.EDTA.TElib.fa"

if [ ! -s "${TEANNO}" ]; then
    log "EDTA: de-novo library + annotation (this is the long step)"
    cd "${WORK}"
    # Watchdog: gt ltrharvest occasionally spins essentially forever on a
    # repetitive chunk, and LTR_HARVEST_parallel's own -time is advisory only
    # (no hard OS timeout). Without this, one stuck genome blocks the queue.
    rc=0
    if [ "${TIMEOUT_H}" -gt 0 ]; then
        timeout --signal=TERM --kill-after=300 "$(( TIMEOUT_H * 3600 ))s" \
            conda run --no-capture-output -n "${ENV_NAME}" EDTA.pl \
                --genome "${FASTA}" \
                --species others \
                --sensitive "${SENSITIVE}" \
                --anno 1 \
                --step all \
                --overwrite 0 \
                --force 1 \
                -t "${THREADS}" \
                >> "${LOG}" 2>&1 || rc=$?
    else
        run EDTA.pl \
            --genome "${FASTA}" \
            --species others \
            --sensitive "${SENSITIVE}" \
            --anno 1 \
            --step all \
            --overwrite 0 \
            --force 1 \
            -t "${THREADS}" \
            >> "${LOG}" 2>&1 || rc=$?
    fi
    if [ "${rc}" -eq 124 ] || [ "${rc}" -eq 137 ]; then
        log "WARNING: EDTA exceeded the ${TIMEOUT_H}h watchdog and was killed"
        log "         raw results are kept; re-running may resume with --overwrite 0"
    elif [ "${rc}" -ne 0 ]; then
        log "WARNING: EDTA exited with status ${rc}"
    else
        log "EDTA finished"
    fi
else
    log "EDTA output already present, skipping"
fi

[ -s "${TEANNO}" ] || { log "ERROR: EDTA produced no ${TEANNO}"; exit 1; }

# --- 3. optional standalone RepeatMasker ---------------------------------
# EDTA --anno 1 already ran RepeatMasker with the EDTA library.  Run it again
# only if you want a repeat-masked genome for a downstream gene predictor
# (MAKER etc.) or an independent .gff for cross-checking.
if [ "${WITH_RM}" -eq 1 ]; then
    RM_DIR="${WORK}/RepeatMasker_EDTA"
    if [ ! -s "${RM_DIR}/${SPECIES}.fa.mod.EDTA.TEanno.gff" ]; then
        log "RepeatMasker against the EDTA library"
        cd "${WORK}"
        run RepeatMasker -pa "${THREADS}" \
            -lib "${TELIB}" \
            -gff -nolow -no_is -norna -e rmblast \
            "${FASTA}.mod" \
            -dir "${RM_DIR}" >> "${LOG}" 2>&1 || \
            log "WARNING: RepeatMasker step failed (EDTA annotation is unaffected)"
    fi
fi

# --- 4. TE information table ---------------------------------------------
TABLE="${OUTDIR}/${SPECIES}.TE_info.tsv"
if [ ! -s "${TABLE}" ]; then
    log "building TE information table"
    run python "${PIPELINE}/03_te_annotation_table.py" \
        --species "${SPECIES}" \
        --te-gff "${TEANNO}" \
        --gene-gff "${GENE_GFF_GZ}" \
        --genome "${GENOME}" \
        --id-map "${IDMAP}" \
        --promoter 2000 \
        --out "${TABLE}" >> "${LOG}" 2>&1
    log "table written: ${TABLE}  ($(wc -l < "${TABLE}") lines)"
else
    log "table already present, skipping"
fi

# --- 5. cleanup -----------------------------------------------------------
if [ "${CLEANUP}" -eq 1 ]; then
    log "cleaning EDTA intermediates (keeping TElib / TEanno / idmap / tables)"
    # Keep:   ${FASTA}.mod.EDTA.TElib.fa, ${FASTA}.mod.EDTA.TEanno.gff3, ${IDMAP}
    # Drop:   everything else, including the two full genome FASTAs
    rm -rf "${FASTA}.mod.EDTA.raw" \
           "${FASTA}.mod.EDTA.anno" \
           "${FASTA}.mod.EDTA.combine" \
           "${FASTA}.mod.EDTA.final" \
           "${FASTA}.mod.HL" \
           "${FASTA}.mod" \
           "${FASTA}.mod.MAKER.masked" \
           "${FASTA}.mod.RM2.raw.fa" \
           "${FASTA}.mod_divergence_plot.pdf" \
           "${FASTA}.mod.EDTA.TEanno.density_plots.pdf" \
           "${GENOME}" \
           "${FASTA}" 2>/dev/null || true
    find "${WORK}" -name '*.temp' -delete 2>/dev/null || true
fi

log "=== done ==="
