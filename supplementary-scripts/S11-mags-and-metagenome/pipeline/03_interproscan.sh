#!/usr/bin/env bash
# Stage 03 -- InterProScan over every MAG's protein set.
#
# This is the source of four of the six annotation columns the page shows:
# InterPro, Pfam, PANTHER and Gene Ontology.  KEGG comes from Kofam (stage 05)
# and UniProt from a Swiss-Prot search (stage 04), because InterProScan does
# neither.
#
# Two flags are load-bearing and easy to omit:
#   -goterms  without it column 14 is "-" for every row and the Gene Ontology
#             column on the site would be silently empty while the run still
#             exits 0 and produces a full-looking TSV.
#   -pa       same for column 15 (pathway cross-references).
#
# Runs one InterProScan per MAG rather than one giant multi-FASTA run.  It costs
# a JVM + database load per MAG, but it keeps each MAG's output independently
# attributable and restartable, and with this many cores the startup cost
# disappears behind the parallelism.
set -uo pipefail

source "$(dirname "${BASH_SOURCE[0]}")/env.sh"

pred="$MAG_ROOT/work/predicted"
raw="$MAG_ROOT/work/raw"
ips_out="$MAG_ROOT/results/interproscan"
status="$MAG_ROOT/work/ips_status.tsv"
log="$MAG_ROOT/work/03_ips.log"
mkdir -p "$ips_out"

[ -x "$IPS" ] || { echo "[03] ERROR: interproscan.sh not found at $IPS" >&2; exit 1; }
JV="$(java -version 2>&1 | head -1)"
echo "[03] $JV" | tee "$log"
case "$JV" in
    *'"11'*|*'"17'*|*'"21'*|*'"22'*|*'"25'*) ;;
    *) echo "[03] ERROR: InterProScan needs Java 11+; got: $JV" >&2; exit 1 ;;
esac

CPU_PER_JOB="${IPS_CPU:-4}"
CONCURRENCY="${IPS_JOBS:-48}"

# Inputs: predicted proteins for GCA MAGs (stage 02), NCBI proteins for GCF MAGs
# (stage 01).  The two live at different paths, so the source column of
# fetch_status decides which one to use rather than guessing at the layout.
inputs="$MAG_ROOT/work/ips_inputs.tsv"
: > "$inputs"
while IFS=$'\t' read -r acc src psrc gfile pfile ngen nprot http; do
    [ "$acc" = "accession" ] && continue
    [ "$pfile" = "-" ] && continue
    case "$src" in
        GCA) faa="$pred/$acc/proteins.faa" ;;
        GCF) faa="$raw/$acc.proteins.faa" ;;
        *)   continue ;;
    esac
    [ -s "$faa" ] && printf '%s\t%s\n' "$acc" "$faa" >> "$inputs"
done < "$MAG_ROOT/work/fetch_status.tsv"

n=$(wc -l < "$inputs")
echo "[03] $n protein sets; ${CONCURRENCY} parallel x ${CPU_PER_JOB} cpu" | tee -a "$log"
[ "$n" -gt 0 ] || { echo "[03] ERROR: no protein sets to annotate" >&2; exit 1; }

# Resume: a re-run must not redo MAGs that already have output.  InterProScan is
# the most expensive stage, so restarting it from scratch after one failure is
# the difference between minutes and hours.
if [ -s "$status" ]; then
    awk -F'\t' 'NR>1 && $5=="OK"{print $1}' "$status" | sort -u > "$MAG_ROOT/work/.ips_done"
    awk -v done="$MAG_ROOT/work/.ips_done" 'BEGIN{while((getline l<done)>0) d[l]=1}
        !($1 in d)' "$inputs" > "$inputs.todo"
    echo "[03] resuming: $(wc -l < "$inputs.todo") of $n still to do" | tee -a "$log"
    mv "$inputs.todo" "$inputs"
else
    printf 'accession\tn_proteins\tn_rows\tn_with_go\tstatus\n' > "$status"
fi

[ "$(wc -l < "$inputs")" -gt 0 ] || { echo "[03] nothing to do" | tee -a "$log"; exit 0; }

export IPS_CPU_PER_JOB="$CPU_PER_JOB"
export ips_status="$status" ips_out_dir="$ips_out"

# `xargs -n 2` hands the worker each "acc faa" pair; -P keeps a fixed number in
# flight.  Paths under $MAG_ROOT contain no whitespace (checked), so splitting
# the two tab-separated fields on whitespace is safe here.  Each job appends one
# short line to $status, which is atomic at this size.
awk -F'\t' '{print $1" "$2}' "$inputs" | \
    xargs -r -n 2 -P "$CONCURRENCY" "$MAG_ROOT/pipeline/_annotate_one.sh"

sort -t$'\t' -k1,1 -o "$status" "$status"
n_ok=$(awk -F'\t' 'NR>1 && $5=="OK"' "$status" | wc -l)
n_bad=$(awk -F'\t' 'NR>1 && $5!="OK"' "$status" | wc -l)
tot_rows=$(awk -F'\t' 'NR>1{s+=$3} END{print s+0}' "$status")
tot_go=$(awk -F'\t' 'NR>1{s+=$4} END{print s+0}' "$status")
echo "[03] done: $n_ok ok, $n_bad not-ok; $tot_rows rows, $tot_go with GO" | tee -a "$log"
[ "$n_bad" -eq 0 ] || exit 1
