#!/usr/bin/env bash
# 20_edta.sh <species> [threads]
#
# De-novo TE library + whole-genome annotation with EDTA v2.2.2.
#
#   --step all      EDTA_raw (structure-based: LTRharvest/LTR_FINDER, AnnoSINE,
#                   TIR-Learner, HelitronScanner) + RepeatModeler for the
#                   remaining LINEs + EDTA_filter/final (RepeatMasker/TEsorter
#                   based de-duplication and classification of the library)
#   --anno 1        whole-genome RepeatMasker run against the curated EDTA
#                   library.  This IS the RepeatMasker step; running the
#                   RepeatMasker command separately afterwards would just
#                   repeat identical work, so we do not.
#
# Produces, under work/<sp>/:
#   <sp>.renamed.fa.mod.EDTA.TElib.fa          curated TE library  (FASTA, classified)
#   <sp>.renamed.fa.mod.EDTA.TEanno.gff3       every TE in the genome (structural+homology)
#   <sp>.renamed.fa.mod.EDTA.TEanno.split.gff3 same, split into non-overlapping pieces
#   <sp>.renamed.fa.mod.EDTA.TEanno.sum        summary / divergence stats
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

sp="${1:?usage: 20_edta.sh <species> [threads]}"
d="$(sp_dir "$sp")"
genome="$(sp_genome "$sp")"
bp="$(sp_genome_bp "$sp")"
thr="${2:-$(threads_for_bp "${bp:-200000000}")}"

[[ -s "$genome" ]] || { log "$sp" "ERROR: run 10_prepare.sh first"; exit 1; }
activate_env "$sp" || exit 1

gff="$(sp_edta_gff "$sp")"
lib="$(sp_edta_lib "$sp")"
if [[ -s "$gff" && -s "$lib" ]]; then
  log "$sp" "EDTA already finished (lib + annotation present)"
  exit 0
fi

mkdir -p "$d/logs"
logf="$d/logs/$sp.edta.log"
log "$sp" "EDTA: de-novo library + annotation, ${thr} threads  (log: $logf)"
log "$sp" "full command:"
log "$sp" "  EDTA.pl --genome $genome --species $EDTA_SPECIES --sensitive $EDTA_SENSITIVE \\"
log "$sp" "          --anno $EDTA_ANNO --evaluate $EDTA_EVALUATE --step all \\"
log "$sp" "          --maxdiv $EDTA_MAXDIV --overwrite $EDTA_OVERWRITE --force 1 -t $thr"
# --repeatmodeler points at rmwrap/, whose RepeatModeler shim adds -recoverDir so
# a restarted species resumes from the last complete round instead of silently
# starting over at round 1 (see the header of rmwrap/RepeatModeler).  Inert
# unless TE_RM_RESUME=1, which common.sh sets.
log "$sp" "          --repeatmodeler $TE_PIPE/rmwrap/   (resume-capable shim)"

start=$(date +%s)
( cd "$d" && EDTA.pl \
    --genome "$genome" \
    --species "$EDTA_SPECIES" \
    --sensitive "$EDTA_SENSITIVE" \
    --anno "$EDTA_ANNO" \
    --evaluate "$EDTA_EVALUATE" \
    --step all \
    --maxdiv "$EDTA_MAXDIV" \
    --overwrite "$EDTA_OVERWRITE" \
    --repeatmodeler "$TE_PIPE/rmwrap/" \
    --force 1 \
    -t "$thr" ) >> "$logf" 2>&1
rc=$?
elapsed=$(( $(date +%s) - start ))

if [[ -s "$gff" && -s "$lib" ]]; then
  log "$sp" "EDTA OK in $(( elapsed / 3600 ))h$(( (elapsed % 3600) / 60 ))m"
else
  log "$sp" "ERROR: EDTA exited rc=$rc after ${elapsed}s without producing $gff"
  [[ -f "$gff" ]] && log "$sp" "  annotation file present but empty"
  exit 1
fi
