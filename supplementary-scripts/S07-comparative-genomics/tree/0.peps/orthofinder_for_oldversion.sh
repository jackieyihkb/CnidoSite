#!/usr/bin/env bash

set -o pipefail

INPUT_DIR="/mnt/sda/jackie/cnidaria/0.tree/0.peps"
LOG_DIR="/mnt/sda/jackie/cnidaria/0.tree/OrthoFinder_Logs"
CONDA_ENV="orthofinder_old"

SEARCH_THREADS=256
ORTHOFINDER_THREADS=64
TARGET_NOFILE=10000

RUN_ID=$(date +"%Y%m%d_%H%M%S")

PROCESS_LOG="${LOG_DIR}/orthofinder_process_${RUN_ID}.log"
PROGRESS_LOG="${LOG_DIR}/orthofinder_progress_${RUN_ID}.log"

mkdir -p "$LOG_DIR"

# Make conda environments available in non-interactive shells, then activate
# the environment containing OrthoFinder 2.5.5.
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
            echo "[$(date '+%Y-%m-%d %H:%M:%S')] ❌ ERROR DETECTED: $clean_line" >> "$PROGRESS_LOG"

        elif [[ "$clean_line" =~ Starting|Running|Done|Completed|completed|Inferring|Calculating|Assigning|Writing|Results|Orthogroups|orthogroups|Gene\ tree|gene\ tree|Species\ tree|species\ tree|reconciliation|orthologues|orthologs|DIAMOND|diamond|MCL|msa|MSA|finished|Finished ]]; then
            echo "[$(date '+%Y-%m-%d %H:%M:%S')] $clean_line" >> "$PROGRESS_LOG"
        fi
    done
}

if [ ! -d "$INPUT_DIR" ]; then
    write_progress "❌ Error: Input directory does not exist -> $INPUT_DIR"
    exit 1
fi

if [ -z "$(ls -A "$INPUT_DIR")" ]; then
    write_progress "❌ Error: Input directory $INPUT_DIR is empty."
    exit 1
fi

CURRENT_SOFT=$(ulimit -Sn)
CURRENT_HARD=$(ulimit -Hn)

if [ "$CURRENT_SOFT" -lt "$TARGET_NOFILE" ]; then
    ulimit -n "$TARGET_NOFILE" 2>/dev/null

    if [ "$(ulimit -Sn)" -lt "$TARGET_NOFILE" ]; then
        write_progress "⚠️ Warning: Failed to set ulimit -n to $TARGET_NOFILE. Current soft limit: $(ulimit -Sn), hard limit: $(ulimit -Hn)"
    fi
fi

{
    echo "============================================================"
    echo "📂 Input: $INPUT_DIR"
    echo "🧪 Conda environment: $CONDA_ENV"
    echo "🔬 $ORTHOFINDER_VERSION_TEXT"
    echo "🧵 Search threads (-t): $SEARCH_THREADS"
    echo "🧠 OrthoFinder internal threads (-a): $ORTHOFINDER_THREADS"
    echo "📄 Process log: $PROCESS_LOG"
    echo "📄 Progress log: $PROGRESS_LOG"
    echo "🔓 ulimit soft nofile: $(ulimit -Sn)"
    echo "🔒 ulimit hard nofile: $(ulimit -Hn)"
    echo "⏰ Start Time: $(date)"
    echo "============================================================"
} | tee -a "$PROCESS_LOG" "$PROGRESS_LOG"

write_progress "🚀 Starting OrthoFinder"

stdbuf -oL -eL orthofinder \
    -f "$INPUT_DIR" \
    -t "$SEARCH_THREADS" \
    -a "$ORTHOFINDER_THREADS" \
    2>&1 \
    | tee -a "$PROCESS_LOG" \
    | progress_filter

ORTHOFINDER_EXIT=${PIPESTATUS[0]}

if [ "$ORTHOFINDER_EXIT" -eq 0 ]; then
    {
        echo "============================================================"
        echo "🎉 OrthoFinder completed successfully!"
        echo "📂 Results inside: $INPUT_DIR/OrthoFinder/"
        echo "📄 Full process log: $PROCESS_LOG"
        echo "📄 Progress log: $PROGRESS_LOG"
        echo "⏰ End Time: $(date)"
        echo "============================================================"
    } | tee -a "$PROCESS_LOG" "$PROGRESS_LOG"
else
    {
        echo "============================================================"
        echo "❌ OrthoFinder failed!"
        echo "Exit code: $ORTHOFINDER_EXIT"
        echo "📄 Full process log: $PROCESS_LOG"
        echo "📄 Progress log: $PROGRESS_LOG"
        echo "⏰ Failed Time: $(date)"
        echo "============================================================"
        echo ""
        echo "Last 50 error-related lines from process log:"
        grep -Ei "ERROR|Error|error|Traceback|Killed|killed|MemoryError|vector::reserve|Segmentation fault|No space left|Too many open files|cannot open|failed|Failed" "$PROCESS_LOG" | tail -n 50
    } | tee -a "$PROGRESS_LOG"

    exit 1
fi
