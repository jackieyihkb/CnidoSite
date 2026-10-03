#!/usr/bin/env bash
# 34_redo_anno.sh <species> [threads]
#
# Rebuild a species' whole-genome TE annotation when the ANNO stage ran with a
# DELETED TMPDIR and wrote an empty result.
#
# The failure
# -----------
# The EDTA stages call `sort`, and `sort` creates its spill files in $TMPDIR.
# A chain started from a session shell inherits TMPDIR=/tmp/codeg-acp/<session-id>
# -- per-session scratch that is removed when that session ends.  Every later
# `sort` in the still-running chain then fails:
#
#     sort: cannot create temporary file in '/tmp/codeg-acp/<id>': No such file
#
# Nothing upstream notices, because each of these is a bare backtick in EDTA.pl.
# The damage lands at the very end of ANNO, in exactly two places that sort:
#
#   EDTA.pl:766  grep -v '^#' TEanno.gff3.raw | sort -sV -k1,1 -k4,4 | perl ... > TEanno.gff3
#   EDTA.pl:768  (the same file again, via format_gff3)
#
# A failed `sort` emits nothing, so the perl that adds the GFF header writes the
# header block and no records.  The delivered files are then:
#
#   TEanno.gff3        1,781 B of header, 0 records
#   TEanno.sum         "total interspersed 0 / 0.00%"
#   TEanno.bed, TE.fa, TE.fa.stat.*   all 0 bytes
#
# and 20_edta.sh's own success test (`[[ -s $gff && -s $lib ]]`) PASSES, because
# 1,781 B is non-empty.  So the species counts as finished everywhere and
# delivers an empty table.  Measured on Nemopilema_nomurai, 2026-09-21.
#
# Why the repair is cheap
# -----------------------
# Only the sorts at the END died.  Everything expensive before them survived:
# the RepeatMasker run (`EDTA.RM.out` 7.3 MB), the homology bed, and
# `EDTA.homo.gff3` (13 MB).  So this replays EDTA's ANNO stage with the existing
# RepeatMasker output handed back via --rmout: RepeatMasker does not re-run
# (that also means no new divergence values -- it is this species' own output,
# byte for byte) and only the ~2 min of post-processing is redone.
#
# Safety
# ------
# Nothing is deleted: the empty gff3 is kept as *.was0.<timestamp>, and the raw
# RepeatMasker output is copied to a stable path first so the stage's own
# `rm`/`mv` inside the anno directory cannot touch it.  `--step anno` jumps
# straight to the ANNO label (EDTA.pl:461 `goto $step`), so the de-novo modules
# -- and the rice fallback at EDTA.pl:479-487 -- are never reached.
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

sp="${1:?usage: 34_redo_anno.sh <species> [threads]}"
bp="$(sp_genome_bp "$sp")"
thr="${2:-$(threads_for_bp "${bp:-200000000}")}"

g="$sp.renamed.fa.mod"
d="$TE_WORK/$sp"
genome="$d/$sp.renamed.fa"
anno="$d/$g.EDTA.anno"
gff="$anno/$g.EDTA.TEanno.gff3"
final="$d/$g.EDTA.final"
rmout_raw="$anno/$g.out"
rmout="$d/$g.rerun.rmout"
ts=$(date +%s)

activate_env "$sp" || exit 1

exec 8>"$d/.lock.redo_anno"
flock -n 8 || { log "$sp" "redo-anno: another repair holds the lock (exit 75)"; exit 75; }

# --------------------------------------------------------------- guards -----
records() { local n; n=$(grep -vc '^#' "$1" 2>/dev/null); echo "${n:-0}"; }

if [[ ! -e "$gff" ]]; then
  log "$sp" "redo-anno: no $gff at all - this is not the empty-anno case, use run_species.sh"; exit 1
fi
n_now=$(records "$gff")
if (( n_now > 0 )); then
  log "$sp" "redo-anno: annotation already has $n_now records - nothing to do"; exit 0
fi
[[ -s "$rmout_raw" ]] || { log "$sp" "redo-anno: no non-empty $rmout_raw to reuse; the anno stage must re-run RepeatMasker, use run_species.sh"; exit 1; }
[[ -s "$final/$g.EDTA.TElib.fa" ]] || { log "$sp" "redo-anno: no $final/$g.EDTA.TElib.fa"; exit 1; }
[[ -s "$genome" ]] || { log "$sp" "redo-anno: no genome $genome"; exit 1; }
[[ -s "$anno/$g.EDTA.homo.gff3" ]] || log "$sp" "redo-anno: WARNING - $g.EDTA.homo.gff3 is empty/missing; the homology side may be incomplete"

log "$sp" "redo-anno: gff3 has 0 records but $(stat -Lc%s "$rmout_raw") B of RepeatMasker output survived; replaying the ANNO stage with --rmout (no RepeatMasker re-run), ${thr} threads"

# Stable copy: the ANNO stage renames/moves files inside $anno, and it must not
# be able to touch the input we are handing it.
cp -f "$rmout_raw" "$rmout"
cp -p "$gff" "$gff.was0.$ts"

# ----------------------------------------------------------------- run -----
logf="$d/logs/$sp.redo_anno.log"
( cd "$d" && EDTA.pl \
    --genome "$genome" \
    --species "$EDTA_SPECIES" \
    --sensitive "$EDTA_SENSITIVE" \
    --anno "$EDTA_ANNO" \
    --evaluate "$EDTA_EVALUATE" \
    --step anno \
    --maxdiv "$EDTA_MAXDIV" \
    --overwrite 0 \
    --rmout "$rmout" \
    --force 1 \
    -t "$thr" ) >> "$logf" 2>&1
rc=$?

# ----------------------------------------------------------------- verify ---
# The eval/plot section at the end of EDTA.pl can die even on success (upstream
# bug: it reads a .stat this route does not produce), and 20_edta.sh only tests
# for non-empty files.  So judge this on the record count, not on rc.
n_new=$(records "$gff")
if (( n_new <= 0 )); then
  log "$sp" "redo-anno: FAILED (rc=$rc) - still 0 records; see $logf"
  log "$sp" "redo-anno: check that \$TMPDIR is writable: TMPDIR=$TMPDIR"
  exit 1
fi
log "$sp" "redo-anno: OK - gff3 now has $n_new records (rc=$rc, log: $logf)"

# --------------------------------------------------------------- install ----
# Same copy-out as run_species.sh, so the delivered set is identical in shape.
libdir="$TE_RESULTS/TE_lib/$sp"; mkdir -p "$libdir"
for f in "$(sp_edta_lib "$sp")" "$(sp_edta_gff "$sp")" \
         "$(sp_edta_split "$sp")" "$(sp_edta_sum "$sp")"; do
  [[ -s "$f" ]] && cp -f "$f" "$libdir/"
done
n_sum=$(grep -c 'total interspersed' "$libdir/$(basename "$(sp_edta_sum "$sp")")" 2>/dev/null || echo 0)

python3 "$TE_PIPE/bin/30_te_table.py" "$sp" || { log "$sp" "redo-anno: FAILED at TE table"; exit 1; }
n_rows=$(( $(wc -l < "$(sp_result "$sp")") - 1 ))
log "$sp" "redo-anno: TE table rebuilt with $n_rows rows"

if (( n_rows <= 0 )); then
  log "$sp" "redo-anno: WARNING - the table is still empty although the gff3 has $n_new records; the fault is then downstream of EDTA (30_te_table.py)"
  exit 1
fi
log "$sp" "redo-anno: done - the empty-annotation failure is cleared for this species"
