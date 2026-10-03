#!/usr/bin/env bash
# Worker: hmmscan one chunk of proteins against the Kofam HMM database.
#
#   _kofam_chunk.sh <chunk.faa>
#
# Writes <chunk>.tbl next to the input and skips the chunk if that file is
# already non-empty, so an interrupted stage 05 resumes instead of restarting.
#
# `--cpu 1` is deliberate, not a placeholder.  hmmscan's internal threading
# scales *negatively* on this database: measured on 500 proteins against the
# 27,262-model Kofam set, one process with --cpu 8 took 244 s (123 proteins/min)
# while eight processes with --cpu 1 took 47 s for the same work (638/min, 5.2x),
# producing byte-identical hits.  The threads spend their time parked in
# futex_wait on an internal lock -- 191 of 193 threads idle, ~3 cores of work on
# a 768-core box -- so the parallelism has to come from processes, not threads.
#
# Split into its own file rather than an exported function so env.sh is
# re-sourced in each worker.
set -uo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/env.sh"

faa="$1"
out="${faa%.faa}.tbl"

[ -s "$faa" ] || exit 0
[ -s "$out" ] && exit 0     # already done

tmp="$(mktemp "${out}.XXXXXX")"
if "$HMMER_BIN/hmmscan" --noali --domtblout "$tmp" --cpu 1 \
        "$MAG_ROOT/work/refs/kofam/kofam.hmm" "$faa" >/dev/null 2>&1; then
    mv "$tmp" "$out"
else
    rm -f "$tmp"
    exit 1
fi
