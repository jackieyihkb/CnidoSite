#!/usr/bin/env bash
# Worker: annotate one MAG's protein set with InterProScan.
#
#   _annotate_one.sh <accession> <proteins.faa>
#
# Output and status paths come from the environment ($ips_out_dir, $ips_status)
# so that xargs can supply just the two per-MAG arguments with `-n 2`.
#
# Split into its own file rather than an exported bash function so that env.sh
# -- specifically the Java 11 JAVA_HOME that InterProScan refuses to start
# without -- is re-established in every child process.
set -uo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/env.sh"

acc="$1"; faa="$2"
out="$ips_out_dir/$acc.tsv"
st="$ips_status"

# A MAG with no proteins is a real outcome (a very small or failed bin), not an
# error to swallow silently.
np=$(grep -c '^>' "$faa" 2>/dev/null || echo 0)
if [ "$np" -eq 0 ]; then
    printf '%s\t0\t0\t0\tEMPTY_INPUT\n' "$acc" >> "$st"
    exit 0
fi

# Write to a sibling temp file and move into place, so an interrupted run can
# never leave a half-written TSV that the resume logic would treat as done.
tmp="$(mktemp "${out}.XXXXXX")"
if ! "$IPS" -i "$faa" -f TSV -dp -goterms -pa \
        -cpu "${IPS_CPU_PER_JOB:-4}" -o "$tmp" >/dev/null 2>&1; then
    rm -f "$tmp"
    printf '%s\t%s\t0\t0\tIPS_FAILED\n' "$acc" "$np" >> "$st"
    exit 0
fi
mv "$tmp" "$out"

nr=$(wc -l < "$out" 2>/dev/null || echo 0)
ngo=$(awk -F'\t' '$14!="-"{c++} END{print c+0}' "$out")

# A run that completed but produced no match rows is a real failure mode for a
# fragmentary MAG, so it is recorded as NO_MATCHES rather than as success.  The
# two statuses are mutually exclusive -- the site's coverage figure counts only
# OK, and double-counting would inflate it.
if [ "$nr" -eq 0 ]; then
    printf '%s\t%s\t0\t0\tNO_MATCHES\n' "$acc" "$np" >> "$st"
else
    printf '%s\t%s\t%s\t%s\tOK\n' "$acc" "$np" "$nr" "$ngo" >> "$st"
fi
