#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# Run the de-novo TE annotation for every species, in parallel, resumably.
#
#   bash run_all.sh                 # all species that have a matching gff3
#   bash run_all.sh --jobs 3 --threads 6
#   bash run_all.sh --status        # show progress only
#   bash run_all.sh --species Acropora_millepora Nematostella_vectensis
#
# Scheduling: genomes are processed largest-first so the long pole starts
# early.  Genomes >= 1.2 Gb are run strictly one at a time with more threads
# because EDTA can need >30 GB of RAM for them; everything else shares a
# pool of JOBS workers x THREADS threads.
#
# Re-running skips species whose output table already exists.
# ---------------------------------------------------------------------------
set -euo pipefail

ROOT=/mnt/sda/jackie/cnidaria_omics/genome_TE
PIPELINE="${ROOT}/TE_pipeline"
SIZES=/tmp/genome_sizes.tsv
JOBS=3            # 3 x 5 = 15 threads; the box is shared with other users
THREADS=5
GIANT_GB=1.2
GIANT_THREADS=12
TIMEOUT_H=24
STATUS_ONLY=0
ONLY=()

while [ $# -gt 0 ]; do
    case "$1" in
        --jobs)    JOBS="$2"; shift 2 ;;
        --threads) THREADS="$2"; shift 2 ;;
        --status)  STATUS_ONLY=1; shift ;;
        --species) shift; while [ $# -gt 0 ] && [[ "$1" != --* ]]; do ONLY+=("$1"); shift; done ;;
        *) echo "unknown option: $1" >&2; exit 1 ;;
    esac
done

OUTDIR="${ROOT}/results/TE_tables"

# --- genome size table (regenerate if missing) ----------------------------
if [ ! -s "${SIZES}" ]; then
    echo ">>> measuring genome sizes (one-off)"
    : > "${SIZES}"
    for gz in "${ROOT}"/*.fa.gz; do
        b=$(basename "$gz" .fa.gz)
        [ -s "${ROOT}/${b}.gff3.gz" ] || continue
        printf '%s\t%s\n' "$b" "$(zcat "$gz" | awk '/^>/{next}{n+=length($0)}END{print n+0}')" \
            >> "${SIZES}"
    done
fi

# --- build the species list ----------------------------------------------
declare -a ALL=()
if [ ${#ONLY[@]} -gt 0 ]; then
    ALL=("${ONLY[@]}")
else
    while IFS=$'\t' read -r name size; do
        [ -s "${ROOT}/${name}.gff3.gz" ] && ALL+=("${name}")
    done < <(sort -k2,2nr "${SIZES}")
fi

# --- status report --------------------------------------------------------
if [ "${STATUS_ONLY}" -eq 1 ]; then
    total=0; done_n=0; running=0
    printf '%-42s %10s  %s\n' SPECIES SIZE TABLE
    for name in "${ALL[@]}"; do
        total=$((total+1))
        size=$(awk -v k="$name" '$1==k{printf "%.0f Mb", $2/1e6}' "${SIZES}")
        if [ -s "${OUTDIR}/${name}.TE_info.tsv" ]; then
            state="done ($(wc -l < "${OUTDIR}/${name}.TE_info.tsv") rows)"
            done_n=$((done_n+1))
        elif pgrep -f "01_per_species.sh ${name}" >/dev/null 2>&1; then
            state="RUNNING"
            running=$((running+1))
        else
            state="pending"
        fi
        printf '%-42s %10s  %s\n' "$name" "${size:-?}" "$state"
    done
    echo
    echo "done ${done_n}/${total}, running ${running}, pending $((total-done_n-running))"
    exit 0
fi

echo ">>> ${#ALL[@]} species to process, ${JOBS} workers x ${THREADS} threads"
echo ">>> logs in ${ROOT}/work/<species>/<species>.pipeline.log"

# --- split into giants (serial, memory-hungry) and a pool -----------------
giant_names=()
pool_names=()
for name in "${ALL[@]}"; do
    [ -s "${OUTDIR}/${name}.TE_info.tsv" ] && continue
    gb=$(awk -v k="$name" '$1==k{print $2/1e9}' "${SIZES}")
    if awk -v v="${gb:-0}" -v t="${GIANT_GB}" 'BEGIN{exit !(v>=t)}'; then
        giant_names+=("${name}")
    else
        pool_names+=("${name}")
    fi
done

echo ">>> ${#pool_names[@]} pooled species, ${#giant_names[@]} giants (serial)"

# --- the pool runs FIRST --------------------------------------------------
# The giants are serialised either way, so running them first or last gives the
# same total makespan -- but running the pool first means most species have a
# finished table in hand within days instead of after the last 3 Gb genome.
if [ ${#pool_names[@]} -gt 0 ]; then
    printf '%s\n' "${pool_names[@]}" | \
    parallel --will-cite -j "${JOBS}" --joblog "${ROOT}/work/parallel.joblog" \
        --resume-failed --tag \
        "bash ${PIPELINE}/01_per_species.sh {} ${THREADS} --timeout-hours ${TIMEOUT_H} --cleanup" \
        || echo ">>> some workers failed; see ${ROOT}/work/parallel.joblog"
fi

# --- then the giants, strictly one at a time ------------------------------
# EDTA can need >30 GB on a multi-Gb genome, so these never overlap the pool.
for name in "${giant_names[@]:-}"; do
    [ -z "${name}" ] && continue
    echo ">>> [giant, serial] ${name}"
    bash "${PIPELINE}/01_per_species.sh" "${name}" "${GIANT_THREADS}" \
        --timeout-hours "${TIMEOUT_H}" --cleanup \
        || echo ">>> FAILED: ${name} (continuing)"
done

echo ">>> all workers finished"
bash "$0" --status
