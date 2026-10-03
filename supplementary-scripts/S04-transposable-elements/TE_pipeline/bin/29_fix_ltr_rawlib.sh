#!/usr/bin/env bash
# 29_fix_ltr_rawlib.sh <species> [source_ltr_dir]
#
# Fill an EMPTY `EDTA.raw/<genome>.LTR.raw.fa` from a completed LTR_retriever run.
#
# Why this matters more than the intact-side repair
# -------------------------------------------------
# 20_edta.sh passes `--force 1`.  After EDTA_raw returns, EDTA.pl fills every
# empty raw library in from the RICE library (EDTA.pl:479-487):
#
#     `cp $rice_LTR $genome.LTR.raw.fa` unless -s "$genome.LTR.raw.fa";
#
# and the very next statement, the "Raw LTR results not found" die (:490), can
# therefore never fire: the file it checks was just made non-empty.  Downstream,
# EDTA.pl:522 hands `-ltr $genome.EDTA.raw/$genome.LTR.raw.fa` to
# EDTA_process.pl -- so the rice LTR library becomes the LTR candidate library
# for a cnidarian genome and its copies get annotated as TEs.  That is silent
# contamination of the deliverable, not a crash.
#
# How a species gets here
# -----------------------
# LTR_retriever wedges inside LTR.identifier.pl (README 8(4));
# 27_fix_ltr_identifier.sh installs a complete scn.adj/defalse into the scratch
# rerun dir made by 26_rerun_ltr_retriever.sh, and the resumed LTR_retriever then
# finishes there, writing LTRlib.fa.  The live LTR dir, however, still holds the
# pre-wedge state: a header-only scn.adj and a 0-byte LTRlib.fa.  At the end of
# the LTR module EDTA_raw does `cp $genome.LTRlib.fa $genome.LTR.raw.fa`, so the
# empty library is copied out -- and the module still reports success, because
# the resume branch only checks the *intact* files.
#
# What this does: copies the completed run's outputs into the live LTR dir and
# the raw level, so LTR.raw.fa holds this species' own LTRs.  Nothing is deleted
# (the empty originals are kept as *.was0.<timestamp>) and nothing outside the
# LTR slots is touched, so it is safe to run while the same species is in a later
# module: EDTA_raw only re-reads the LTR dir if it re-enters the LTR module, and
# that entry now finds a populated LTRlib.fa and skips LTR_retriever entirely.
#
# The intact gff3 is regenerated from the same pass.list.gff3.  Upstream's
# filter_gff3.pl call leaves the whole pass.list.gff3 in place for a healthy run
# (measured on Galaxea_fascicularis: 86 regions for a 73-sequence library), so
# copying it whole reproduces normal output rather than inventing a subset.
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

sp="${1:?usage: 29_fix_ltr_rawlib.sh <species> [source_ltr_dir]}"
g="$sp.renamed.fa.mod"
raw="$TE_WORK/$sp/$g.EDTA.raw"
ltr="$raw/LTR"
src="${2:-$TE_WORK/$sp/.ltr_retriever_rerun}"
ts=$(date +%s)

activate_env "$sp" || exit 1

exec 8>"$TE_WORK/$sp/.lock.ltr_rawlib"
flock -n 8 || { log "$sp" "ltr-rawlib-fix: another fix holds the lock (exit 75)"; exit 75; }

[[ -s "$src/$g.LTRlib.fa" ]] || { log "$sp" "ltr-rawlib-fix: no non-empty LTRlib.fa in $src - nothing to install from"; exit 1; }
[[ -s "$src/$g.pass.list.gff3" ]] || { log "$sp" "ltr-rawlib-fix: no pass.list.gff3 in $src"; exit 1; }

if [[ -s "$raw/$g.LTR.raw.fa" ]]; then
  log "$sp" "ltr-rawlib-fix: LTR.raw.fa already non-empty ($(stat -Lc%s "$raw/$g.LTR.raw.fa") B) - nothing to do"
  exit 0
fi

log "$sp" "ltr-rawlib-fix: LTR.raw.fa is empty and --force 1 is in use, so EDTA.pl:481 would take the rice library; installing $src/$g.LTRlib.fa ($(grep -c '^>' "$src/$g.LTRlib.fa") seqs / $(stat -Lc%s "$src/$g.LTRlib.fa") B)"

# Keep the empty/original files as evidence rather than overwriting silently.
for f in "$raw/$g.LTR.raw.fa" "$raw/$g.LTR.intact.raw.gff3" "$ltr/$g.LTRlib.fa" \
         "$ltr/$g.retriever.scn.adj" "$ltr/$g.defalse" "$ltr/$g.pass.list"; do
  [[ -e "$f" ]] && cp -p "$f" "$f.was0.$ts"
done

cp "$src/$g.LTRlib.fa"       "$ltr/$g.LTRlib.fa"
cp "$src/$g.LTRlib.fa"       "$raw/$g.LTR.raw.fa"
cp "$src/$g.pass.list"       "$ltr/$g.pass.list"
cp "$src/$g.pass.list.gff3"  "$ltr/$g.pass.list.gff3"
cp "$src/$g.defalse"         "$ltr/$g.defalse"
# LTR_retriever renames $idx.retriever.scn.adj to $idx.retriever.all.scn as it
# finishes, so neither name may be present in the source.  Nothing downstream
# reads it once the module has returned, so this is best-effort.
for a in "$g.retriever.scn.adj" "$g.retriever.all.scn"; do
  [[ -s "$src/$a" ]] && cp "$src/$a" "$ltr/$a" && log "$sp" "ltr-rawlib-fix: also copied $a"
done

# EDTA_raw.pl:462 pipes the LTR_retriever gff3 through the source rename.
perl -nle 's/LTR_retriever/EDTA/gi; print $_' "$ltr/$g.pass.list.gff3" > "$raw/$g.LTR.intact.raw.gff3"
cp "$raw/$g.LTR.intact.raw.gff3" "$ltr/$g.LTR.intact.raw.gff3"

n_raw=$(grep -c '^>' "$raw/$g.LTR.raw.fa")
n_gff=$(grep -vc '^#' "$raw/$g.LTR.intact.raw.gff3")
log "$sp" "ltr-rawlib-fix: installed LTR.raw.fa $n_raw seqs / $(stat -Lc%s "$raw/$g.LTR.raw.fa") B, intact gff3 $n_gff records (was empty)"
log "$sp" "ltr-rawlib-fix: the intact library for this species is already genuine ($(grep -c '^>' "$raw/$g.LTR.intact.raw.fa") seqs) - the rice fallback at EDTA.pl:481-486 can no longer fire on either side"
