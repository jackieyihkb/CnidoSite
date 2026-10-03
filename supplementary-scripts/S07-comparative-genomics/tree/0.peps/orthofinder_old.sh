#!/usr/bin/env bash

set -o pipefail

# Continue the failed OrthoFinder 2.5.5 run from its completed orthogroups.
# Using -M msa produces MultipleSequenceAlignments with MAFFT and gene trees
# with FastTree, which are the OrthoFinder 2.5.5 defaults within MSA mode.
ORTHOGROUPS_RESULTS="/mnt/sda/jackie/cnidaria/0.tree/0.peps/OrthoFinder/Results_Sep13"
LOG_DIR="/mnt/sda/jackie/cnidaria/0.tree/OrthoFinder_Logs"
CONDA_ENV="orthofinder_old"

SEARCH_THREADS=256
ORTHOFINDER_THREADS=64
TARGET_NOFILE=1048576

RUN_ID=$(date +"%Y%m%d_%H%M%S")
PROCESS_LOG="${LOG_DIR}/orthofinder_old_msa_process_${RUN_ID}.log"
PROGRESS_LOG="${LOG_DIR}/orthofinder_old_msa_progress_${RUN_ID}.log"

mkdir -p "$LOG_DIR"

write_progress() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*" | tee -a "$PROGRESS_LOG"
}

strip_ansi() {
    sed -E 's/\x1B\[[0-9;]*[[:alpha:]]//g'
}

progress_filter() {
    while IFS= read -r line; do
        clean_line=$(printf '%s\n' "$line" | strip_ansi)

        if [[ "$clean_line" =~ ERROR|Error|error|Traceback|Killed|killed|MemoryError|vector::reserve|Segmentation\ fault|No\ space\ left|Too\ many\ open\ files|cannot\ open|failed|Failed ]]; then
            echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR DETECTED: $clean_line" >> "$PROGRESS_LOG"
        elif [[ "$clean_line" =~ Starting|Running|Done|Completed|completed|Inferring|Calculating|Assigning|Writing|Results|Orthogroups|orthogroups|Gene\ tree|gene\ tree|Species\ tree|species\ tree|reconciliation|orthologues|orthologs|DIAMOND|diamond|MCL|MAFFT|mafft|FastTree|fasttree|msa|MSA|finished|Finished ]]; then
            echo "[$(date '+%Y-%m-%d %H:%M:%S')] $clean_line" >> "$PROGRESS_LOG"
        fi
    done
}

if ! command -v conda >/dev/null 2>&1; then
    echo "Error: conda was not found in PATH." >&2
    exit 1
fi

CONDA_BASE=$(conda info --base)
source "${CONDA_BASE}/etc/profile.d/conda.sh"
conda activate "$CONDA_ENV"

if ! command -v orthofinder >/dev/null 2>&1; then
    echo "Error: orthofinder was not found in conda environment: $CONDA_ENV" >&2
    exit 1
fi

ORTHOFINDER_VERSION_TEXT=$(orthofinder -h 2>&1 | grep -m1 'OrthoFinder version' || true)

if [[ "$ORTHOFINDER_VERSION_TEXT" != *"2.5.5"* ]]; then
    echo "Error: expected OrthoFinder 2.5.5 in $CONDA_ENV." >&2
    echo "Detected: ${ORTHOFINDER_VERSION_TEXT:-unknown version}" >&2
    exit 1
fi

if [[ ! -d "$ORTHOGROUPS_RESULTS" ]]; then
    write_progress "Error: previous results directory does not exist -> $ORTHOGROUPS_RESULTS"
    exit 1
fi

if [[ ! -f "$ORTHOGROUPS_RESULTS/Log.txt" || ! -d "$ORTHOGROUPS_RESULTS/Orthogroups" || ! -d "$ORTHOGROUPS_RESULTS/WorkingDirectory" ]]; then
    write_progress "Error: $ORTHOGROUPS_RESULTS is not a usable OrthoFinder results directory for -fg."
    exit 1
fi

CURRENT_SOFT=$(ulimit -Sn)
CURRENT_HARD=$(ulimit -Hn)
EFFECTIVE_TARGET=$TARGET_NOFILE

if [[ "$CURRENT_HARD" != "unlimited" ]] && (( EFFECTIVE_TARGET > CURRENT_HARD )); then
    EFFECTIVE_TARGET=$CURRENT_HARD
fi

if [[ "$CURRENT_SOFT" != "unlimited" ]] && (( CURRENT_SOFT < EFFECTIVE_TARGET )); then
    # Set only the soft limit. Omitting -S would also lower the hard limit.
    if ! ulimit -Sn "$EFFECTIVE_TARGET"; then
        write_progress "Error: failed to set soft nofile limit to $EFFECTIVE_TARGET."
        exit 1
    fi
fi

{
    echo "============================================================"
    echo "Previous results (-fg): $ORTHOGROUPS_RESULTS"
    echo "Conda environment: $CONDA_ENV"
    echo "$ORTHOFINDER_VERSION_TEXT"
    echo "Gene-tree method: MSA (-M msa; MAFFT + FastTree defaults)"
    echo "Parallel task threads (-t): $SEARCH_THREADS"
    echo "OrthoFinder analysis threads (-a): $ORTHOFINDER_THREADS"
    echo "Process log: $PROCESS_LOG"
    echo "Progress log: $PROGRESS_LOG"
    echo "nofile soft limit: $(ulimit -Sn)"
    echo "nofile hard limit: $(ulimit -Hn)"
    echo "Start time: $(date)"
    echo "============================================================"
} | tee -a "$PROCESS_LOG" "$PROGRESS_LOG"

write_progress "Starting OrthoFinder 2.5.5 from existing orthogroups with MSA gene trees"

stdbuf -oL -eL orthofinder \
    -fg "$ORTHOGROUPS_RESULTS" \
    -t "$SEARCH_THREADS" \
    -a "$ORTHOFINDER_THREADS" \
    -M msa \
    2>&1 \
    | tee -a "$PROCESS_LOG" \
    | progress_filter

ORTHOFINDER_EXIT=${PIPESTATUS[0]}

if [[ "$ORTHOFINDER_EXIT" -eq 0 ]]; then
    {
        echo "============================================================"
        echo "OrthoFinder completed successfully."
        echo "The continuation results path is reported near the end of:"
        echo "$PROCESS_LOG"
        echo "End time: $(date)"
        echo "============================================================"
    } | tee -a "$PROCESS_LOG" "$PROGRESS_LOG"
else
    {
        echo "============================================================"
        echo "OrthoFinder failed."
        echo "Exit code: $ORTHOFINDER_EXIT"
        echo "Full process log: $PROCESS_LOG"
        echo "Progress log: $PROGRESS_LOG"
        echo "Failure time: $(date)"
        echo "============================================================"
        echo "Last 50 error-related lines from process log:"
        grep -Ei "ERROR|Error|error|Traceback|Killed|killed|MemoryError|vector::reserve|Segmentation fault|No space left|Too many open files|cannot open|failed|Failed" "$PROCESS_LOG" | tail -n 50
    } | tee -a "$PROGRESS_LOG"

    exit 1
fi
