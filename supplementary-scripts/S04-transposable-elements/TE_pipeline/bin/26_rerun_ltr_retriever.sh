#!/usr/bin/env bash
# 26_rerun_ltr_retriever.sh <species> [threads]
#
# Re-run ONLY LTR_retriever for a species whose LTR module aborted inside it.
#
# Why this is needed
# ------------------
# EDTA_raw.pl runs the LTR module as: ltrharvest + LTR_FINDER -> cat the two
# combine.scn files into <g>.rawLTR.scn -> LTR_retriever -> intact extraction +
# TEsorter -> copy out.  For Actinernus_sp the scanners and the `cat` were fine
# (<g>.rawLTR.scn is the largest of all 64 species, 1,817,562 B = harvest
# 1,371,835 + finder 445,727 exactly), but LTR_retriever aborted partway and
# everything after it is empty.
#
# The signature is an INVERTED file set.  A healthy species has
#     LTRlib.redundant.fa, pass.list.gff3, retriever.all.scn   present & large
#     LTRlib.raw, LTRlib.exclude.tgt                           absent
# Actinernus has the exact opposite: the empty intermediates exist (0 bytes,
# written by the miu-filtering step with nothing to filter) and the three real
# outputs are missing.  Its LTRlib.fa is 0 bytes, which is also what EDTA
# `touch`es when LTR_retriever returns nothing -- so the failure is silent
# unless you compare the file set against a healthy run.
#
# Why a scratch directory
# -----------------------
# LTR_retriever writes ~50 fixed filenames into its cwd.  Running it in the live
# LTR/ dir would overwrite the intermediates of the aborted run while the
# species' EDTA_raw.pl is still executing.  Instead the inputs are symlinked /
# copied into a scratch dir and the heavy run happens there; installing the
# result is a separate, deliberate step (see install note at the end).
#
# LTR_retriever's own entry point is upstream's, and the parameters are exactly
# what EDTA_raw.pl:440 passes, so the output is what EDTA would have produced:
#     -u 1.3e-8 is EDTA_raw.pl:63 ($miu, a constant -- not genome-scaled)
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

sp="${1:?usage: 26_rerun_ltr_retriever.sh <species> [threads]}"
threads="${2:-16}"
g="$sp.renamed.fa.mod"
raw="$TE_WORK/$sp/$g.EDTA.raw"
ltr="$raw/LTR"
scratch="$TE_WORK/$sp/.ltr_retriever_rerun"
miu=1.3e-8

activate_env "$sp" || exit 1

# A lock of our own: the species lock is held by the in-flight chain for the
# whole EDTA run, so it cannot be reused here.  This one only keeps two copies
# of *this* script apart.
mkdir -p "$TE_WORK/$sp"
exec 8>"$TE_WORK/$sp/.lock.ltr_retriever"
if ! flock -n 8; then
  log "$sp" "ltr_retriever-rerun: another rerun holds the lock (exit 75)"
  exit 75
fi

[[ -s "$ltr/$g.rawLTR.scn" ]] || { log "$sp" "ltr_retriever-rerun: no $g.rawLTR.scn"; exit 1; }

mkdir -p "$scratch"
cd "$scratch" || exit 1
# EDTA_raw.pl:411 leaves a symlink to the renamed genome in the LTR dir because
# LTR_retriever resolves -genome relative to cwd; reproduce that here.  The
# target is the species' own masked genome, not anything under EDTA.raw -- and
# note EDTA *deletes* its symlink at the end of the module (`rm $genome`), so
# the live LTR dir no longer has one to copy.
[[ -s "$TE_WORK/$sp/$g" ]] || { log "$sp" "ltr_retriever-rerun: no genome at $TE_WORK/$sp/$g"; exit 1; }
ln -sf "$TE_WORK/$sp/$g" "$g"
ln -sf "$ltr/$g.rawLTR.scn" "$g.rawLTR.scn"

# -L: without it stat reports the size of the symlink itself (the target path
# length, ~144 B), which reads exactly like a truncated input.
log "$sp" "ltr_retriever-rerun: input $(stat -Lc%s "$g.rawLTR.scn") B, genome $(stat -Lc%s "$g") B, threads=$threads, -u $miu"
log "$sp" "ltr_retriever-rerun: scratch $scratch"

# Same command as EDTA_raw.pl:440, minus the -trf_path/-blastplus/-repeatmasker
# arguments: our EDTA.pl passes those empty (README 4 lists no tool paths), and
# every tool is on PATH via activate_env, which is how the 61 healthy species
# resolved them too.
LTR_retriever -genome "$g" -inharvest "$g.rawLTR.scn" -u "$miu" \
              -threads "$threads" -noanno
rc=$?
log "$sp" "ltr_retriever-rerun: LTR_retriever rc=$rc"

# ---------------------------------------------------------------- verify ----
# Do not call this a success on the strength of the exit code alone: the whole
# point of the exercise is that a 0-byte LTRlib.fa can coexist with rc=0.
fail=0
for f in "$g.LTRlib.fa" "$g.LTRlib.redundant.fa" "$g.pass.list.gff3" "$g.pass.list"; do
  if [[ -s "$scratch/$f" ]]; then
    log "$sp" "ltr_retriever-rerun: $(printf '%11s' "$(stat -c%s "$scratch/$f")")  $f"
  else
    log "$sp" "ltr_retriever-rerun: MISSING/EMPTY  $f"
    fail=1
  fi
done

if (( fail )); then
  log "$sp" "ltr_retriever-rerun: FAILED - left everything in $scratch for inspection"
  exit 1
fi
log "$sp" "ltr_retriever-rerun: OK - scratch holds a real library; install it deliberately"
log "$sp" "ltr_retriever-rerun: nothing in the live LTR dir was modified"
