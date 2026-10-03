#!/usr/bin/env bash
# 28_fix_ltr_intact.sh <species> [threads]
#
# Rebuild a missing or empty `EDTA.raw/<genome>.LTR.intact.raw.fa`.
#
# Why this is needed
# ------------------
# EDTA_raw.pl derives the intact LTR set from LTR_retriever's pass.list in a
# block (EDTA_raw.pl:442-465) that lives inside the `else` branch of the
# LTR_retriever resume test and ends with `rm $genome` -- it deletes the genome
# symlink it was reading from, so the block is not re-entrant.  When the LTR
# module is re-entered (a scanner restart / salvage pass, which this host needs:
# see README 8(4)) that block runs a SECOND time with no genome present, and the
# whole chain then "succeeds" on empty input:
#
#     call_seq_by_list.pl ....  Error: Could not load sequence. Empty file or bad format.
#     LTR.intact.fa.ori               0 bytes
#     TEsorter ...                     Error: Could not load sequence. ...
#     cleanup_misclas.pl               Usage: perl cleanup_misclas.pl   (no args -> dies)
#     mv ....cln.cln LTR.intact.raw.fa  No such file or directory
#
# `LTR.intact.raw.fa` is then never created and `LTR.intact.raw.gff3` is
# regenerated UNFILTERED (output_by_list.pl fails, the empty rmlist makes
# filter_gff3.pl pass everything through).  Nothing reports a failure: EDTA_raw
# only checks the *intact* files in the resume branch, and the module still
# prints "Finish finding LTR candidates."
#
# Why it must be fixed before the final stage
# -------------------------------------------
# 20_edta.sh passes `--force 1`, and with force on EDTA first fills any empty raw
# library in from the RICE library (EDTA.pl:479-487):
#
#     `cp $rice_LTR $genome.LTR.intact.raw.fa` unless -s "$genome.LTR.intact.raw.fa";
#
# That `cp` runs BEFORE the "Raw LTR results not found" check on the next line,
# so the check can never fire: an empty file is not an error, it is silent
# substitution of rice LTRs into a cnidarian TE library.  Measured 2026-09-21,
# 6 of 64 species were in this state.
#
# Three routes, tried in order
# ----------------------------
# 1. defalse   -- exactly what EDTA_raw.pl:444-462 does (rename_LTR_skim.pl,
#                 which keeps only candidates whose defalse record has both
#                 LTRs, lLTR/rLTR > 0).
# 2. pass.list -- rename_LTR_skim.pl keeps nothing for a species whose
#                 LTR_retriever run was starved of structural input, and it does
#                 so silently (`next` with no warning).  LTR_retriever only
#                 writes a pass.list row for a candidate it called full-length
#                 itself, and column 10 carries the SuperFamily: the same two
#                 things rename_LTR_skim.pl contributes.  Species' own sequences,
#                 looser filter.
# 3. unfiltered-- if even the filtered pass.list set comes out empty (a single
#                 candidate that cleanup_tandem then rejects as tandem, as in
#                 Hydra_vulgaris), install the classified set unfiltered rather
#                 than leave the file empty and hand EDTA a reason to use rice.
#
# This reproduces EDTA's own quality steps (mdust -> cleanup_tandem -> TEsorter
# -> cleanup_misclas) on every route, so the result is directly comparable with a
# normal run's output.  Everything happens in a scratch directory; the live LTR
# dir is only touched after the result is verified.
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

sp="${1:?usage: 28_fix_ltr_intact.sh <species> [threads]}"
threads="${2:-16}"

g="$sp.renamed.fa.mod"
EDTA_DIR="$EDTA_ENV/share/EDTA"
BIN="$EDTA_DIR/bin"
genome="$TE_WORK/$sp/$sp.renamed.fa"
raw="$TE_WORK/$sp/$g.EDTA.raw"
ltr="$raw/LTR"
work="$TE_WORK/$sp/.ltr_intact_fix"

activate_env "$sp" || exit 1

exec 8>"$TE_WORK/$sp/.lock.ltr_intact"
flock -n 8 || { log "$sp" "ltr-intact-fix: another fix holds the lock (exit 75)"; exit 75; }

# ------------------------------------------------------------- guards --------
[[ -s "$raw/$g.LTR.intact.raw.fa" ]] && { log "$sp" "ltr-intact-fix: intact file already present, nothing to do"; exit 0; }
[[ -s "$ltr/$g.LTRlib.fa" ]] || { log "$sp" "ltr-intact-fix: no $ltr/$g.LTRlib.fa - the whole LTR module needs a rerun, not this"; exit 1; }
[[ -s "$ltr/$g.pass.list" ]] || { log "$sp" "ltr-intact-fix: no pass.list"; exit 1; }
[[ -s "$ltr/$g.defalse" ]] || { log "$sp" "ltr-intact-fix: no defalse (needed by rename_LTR_skim.pl)"; exit 1; }
[[ -s "$ltr/$g.pass.list.gff3" ]] || { log "$sp" "ltr-intact-fix: no pass.list.gff3"; exit 1; }
[[ -s "$genome" ]] || { log "$sp" "ltr-intact-fix: no genome $genome"; exit 1; }

log "$sp" "ltr-intact-fix: rebuilding intact LTRs ($(grep -vc '^#' "$ltr/$g.pass.list") pass.list entries, LTRlib.fa $(stat -Lc%s "$ltr/$g.LTRlib.fa") B / $(grep -c '^>' "$ltr/$g.LTRlib.fa") seqs)"

# Restore the genome symlink inside the LTR dir.  EDTA_raw.pl:465 `rm $genome`
# deletes it at the end of the module, so any re-entry reads an absent genome --
# the root cause here.  Putting it back makes the module re-entrant: a later
# restart will regenerate the intact set by itself instead of silently writing
# 0-byte intermediates again.
if [[ ! -s "$ltr/$g" ]]; then
  ln -sf "$genome" "$ltr/$g"
  log "$sp" "ltr-intact-fix: restored the genome symlink the LTR module deleted ($ltr/$g)"
fi

rm -rf "$work"; mkdir -p "$work"
ln -sf "$genome"                "$work/$g"
ln -sf "$ltr/$g.pass.list"      "$work/$g.pass.list"
ln -sf "$ltr/$g.defalse"        "$work/$g.defalse"
ln -sf "$ltr/$g.pass.list.gff3" "$work/$g.pass.list.gff3"
cd "$work" || exit 1

# EDTA_raw.pl:444-445 -- every full-length candidate's sequence, from the genome.
# Then kept pristine: each route gets its own copy.
awk '{if ($1 !~ /#/) print $1"\t"$1}' "$g.pass.list" \
  | perl "$BIN/call_seq_by_list.pl" - -C "$g" > ori.raw 2> ori.err
[[ -s ori.raw ]] || {
  log "$sp" "ltr-intact-fix: FAILED - call_seq_by_list.pl produced nothing"
  head -3 ori.err | sed 's/^/    /'
  exit 1
}
perl -i -nle 's/\|.*//; print $_' ori.raw
n_raw=$(grep -c '^>' ori.raw)
log "$sp" "ltr-intact-fix: extracted $n_raw full-length candidates from the genome"

# Route 1: EDTA's own classifier.  Route 2: pass.list column 10.
perl "$BIN/rename_LTR_skim.pl" ori.raw "$g.defalse" > "ori.defalse" 2> ren.err
n_def=$(grep -c '^>' "ori.defalse" 2>/dev/null || echo 0)
awk -F'\t' '!/^#/ && $2=="pass" {print $1"\t"($10=="" || $10=="NA" ? "unknown" : $10)}' \
    "$g.pass.list" > passmap.tsv
perl -e '
  my ($fa, $map, $out) = @ARGV; my %m;
  open my $M, "<", $map or die "$map: $!";
  while (<$M>) { chomp; my ($l, $s) = split /\t/; $m{$l} = $s }
  close $M;
  open my $F, "<", $fa or die "$fa: $!";
  open my $O, ">", $out or die "$out: $!";
  local $/ = "\n>"; my $n = 0;
  while (<$F>) {
    s/^>//;
    my ($id, $seq) = split /\n/, $_, 2;
    $id =~ s/\s+.*//; $id =~ s/#.*//;
    next unless exists $m{$id};
    # With a multi-char $/ the separator is KEPT at the end of the record, so
    # every record but the last carries a greater-than sign glued to its
    # sequence (the last one has no terminator).  rename_LTR_skim.pl has the same
    # trap.  mdust refuses any sequence containing one -- "Error: Not a FastaSeq
    # file." -- which silently yields an empty dusted file.  Keep bases only.
    # (No apostrophes in this program: it is inside a single-quoted shell word.)
    $seq =~ s/[^ACGTNacgtn]//g;
    next unless length $seq;
    print $O ">$id#LTR/$m{$id}\n$seq\n"; $n++;
  }
  warn "classified $n sequences from pass.list\n";
' ori.raw passmap.tsv "ori.pass.list" 2> fallback.log
n_pl=$(grep -c '^>' "ori.pass.list" 2>/dev/null || echo 0)
log "$sp" "ltr-intact-fix: candidates kept - defalse $n_def/$n_raw (needs lLTR/rLTR>0 in defalse), pass.list $n_pl/$n_raw"

# ---------------------------------------------------------------- chains -----
# EDTA_raw.pl:450-457 for one candidate set.  Echoes the surviving count.
run_chain() {  # run_chain <route>
  local r="$1" f="ori.$1"
  # rename_LTR_skim.pl output keeps the record separator (see above).
  # The character class must EXCLUDE the newline.  `[^ACGTNacgtn]` also matches
  # "\n", so -p printed every non-header line without its terminator and glued it
  # to the following header: a 302-record file became 1 header on 302 lines, and
  # mdust answered "Error: Not a FastaSeq file."  (Measured on Hydra_vulgaris
  # 2026-09-21; the four species repaired before this line was added are valid.)
  perl -i -pe 'if (!/^>/) { s/[^ACGTNacgtn\n]//g }' "$f"
  mdust "$f" > "$f.dusted" 2> mdust.err            # mask simple repeats
  [[ -s "$f.dusted" ]] || { echo 0; return; }
  perl "$BIN/cleanup_tandem.pl" -misschar N -nc 50000 -nr 0.9 -minlen 100 -minscore 3000 \
       -trf 1 -cleanN 1 -cleanT 1 -f "$f.dusted" > "$f.dusted.cln" 2> cln.err
  [[ -s "$f.dusted.cln" ]] || { echo 0; return; }
  TEsorter "$f.dusted.cln" --disable-pass2 -p "$threads" > tesorter.log 2>&1
  local tsv="$f.dusted.cln.rexdb.cls.tsv"
  [[ -s "$tsv" ]] || { echo 0; return; }
  perl "$BIN/cleanup_misclas.pl" "$tsv" > cls.log 2>&1
  [[ -s "$f.dusted.cln.cln" ]] || { echo 0; return; }
  grep -c '^>' "$f.dusted.cln.cln"
}

route=""; final=""
for r in defalse pass.list; do
  n=$(grep -c '^>' "ori.$r" 2>/dev/null || echo 0)
  (( n )) || { log "$sp" "ltr-intact-fix: route $r - no candidates"; continue; }
  kept=$(run_chain "$r")
  log "$sp" "ltr-intact-fix: route $r - $n candidates -> $kept after mdust/cleanup_tandem/TEsorter"
  if (( ${kept:-0} > 0 )); then
    route="$r"; final="ori.$r.dusted.cln.cln"; break
  fi
done

if [[ -z "$final" ]]; then
  # Both filtered routes came out empty.  Take the better-classified unfiltered
  # set rather than leave the file empty: an empty intact library is exactly what
  # triggers EDTA.pl:482.  Recorded loudly.
  if (( ${n_pl:-0} > 0 )); then route="pass.list-unfiltered"; final="ori.pass.list"
  elif (( ${n_def:-0} > 0 )); then route="defalse-unfiltered"; final="ori.defalse"
  else
    log "$sp" "ltr-intact-fix: FAILED - neither route produced any sequence; LTRlib.fa may be stale"
    exit 1
  fi
  log "$sp" "ltr-intact-fix: WARNING - every candidate was removed by mdust/cleanup_tandem/TEsorter; installing the $route set so EDTA cannot fall back to rice"
fi

# -------------------------------------------------------------- validate -----
# Gate the install on the result being a real FASTA.  A broken library is worse
# than an empty one here: empty is caught by the guard above on the next run,
# while broken is silently handed to the filter/anno stage as the LTR library
# (that is how the glued Hydra file got installed).  Two invariants are enough --
# every non-header line is bare bases, and there is one sequence line per header.
validate() {  # validate <file>  -> prints "h=<n> bad=<n> lines=<n>"
  local f="$1" h bad l
  h=$(grep -c '^>' "$f" 2>/dev/null); l=$(wc -l < "$f")
  bad=$(awk '!/^>/ && $0 !~ /^[ACGTNacgtn]+$/' "$f" | wc -l)
  printf 'h=%s bad=%s lines=%s' "$h" "$bad" "$l"
}

v=$(validate "$final")
if [[ "$v" != h=*' bad=0 lines='* ]]; then
  log "$sp" "ltr-intact-fix: FAILED - $final is not valid FASTA ($v)"
  exit 1
fi
h=${v#h=}; h=${h%% *}
l=${v##*lines=}
if (( h != l - h )); then
  log "$sp" "ltr-intact-fix: FAILED - $final has $h headers on $l lines (a sequence line is glued to a header)"
  exit 1
fi
log "$sp" "ltr-intact-fix: validated $final - $h sequences, every line bare bases"

# ---------------------------------------------------------------- install ----
# Two different lists, as in EDTA_raw.pl:457-461 -- do not conflate them:
#
#   <g>.LTR.intact.raw.fa.anno.list : cleanup_misclas.pl's .cln.cln.list, i.e.
#       the classification of the sequences that SURVIVED.  Nothing in EDTA ever
#       reads it (verified: only the mv/cp that create it mention the name), but
#       upstream ships it, so ship the same thing.
#   <g>.LTR.intact.fa.ori.rmlist    : the candidates the filtering REMOVED, fed
#       to filter_gff3.pl, which keeps the records *not* in the list.
#
# The removed set is computed here instead of with `output_by_list.pl -ex`.
# Measured 2026-09-21: -ex returned ALL of ori for the pass.list route (both
# files are FASTA and its list lookup does not match them), and a list holding
# every candidate makes filter_gff3.pl drop every record -- Hydra's intact gff3
# came out with 0 records and the intact LTRs would have been missing from the
# final annotation.  A set difference cannot silently widen like that.
perl -e '
  # removed = in the candidate set (ori.raw) but not in the surviving set.
  # Both sides are cut at the first whitespace and at a hash: ori.raw headers are
  # bare coordinates while the surviving FASTA carries the #LTR/ family suffix
  # TEsorter added, so a raw string compare would find no name in common and
  # return the whole candidate set as removed.  filter_gff3.pl cuts the suffix
  # off its list ids the same way.
  my ($all, $keep_f, $out) = @ARGV; my %keep;
  open my $K, "<", $keep_f or die "$keep_f: $!";
  while (<$K>) { next unless s/^>//; s/\s.*//s; s/#.*//; $keep{$_} = 1 }
  close $K;
  open my $F, "<", $all or die "$all: $!";
  open my $O, ">", $out or die "$out: $!";
  my $n = 0;
  while (<$F>) { next unless s/^>//; s/\s.*//s; s/#.*//; next if $keep{$_}; print $O "Name\t$_\n"; $n++ }
  close $F; close $O;
  warn "removed-name list: $n removed, " . scalar(keys %keep) . " kept\n";
' ori.raw "$final" rmlist 2> rm.log
log "$sp" "ltr-intact-fix: removed-name list $(wc -l < rmlist) entries ($(cat rm.log))"

perl "$BIN/filter_gff3.pl" "$g.pass.list.gff3" rmlist \
  | perl -nle 's/LTR_retriever/EDTA/gi; print $_' > intact.gff3
n_gff=$(grep -vc '^#' intact.gff3)
if (( n_gff == 0 )); then
  # An over-broad list removes everything.  A healthy run leaves the whole
  # pass.list.gff3 in place (measured on Galaxea_fascicularis: its intact gff3
  # holds every pass.list region, not only the intact ones), so reproduce that
  # rather than ship an empty file that the filter step would read as "this
  # genome has no intact LTRs".
  perl "$BIN/filter_gff3.pl" "$g.pass.list.gff3" /dev/null \
    | perl -nle 's/LTR_retriever/EDTA/gi; print $_' > intact.gff3
  n_gff=$(grep -vc '^#' intact.gff3)
  log "$sp" "ltr-intact-fix: WARNING - the removed-name list matched every record; kept the whole pass.list.gff3 instead ($n_gff records)"
fi
# Cross-check: the library's own names must appear in the gff3 the filter step
# will read.  0 shared means the intact LTRs are invisible to the final anno.
grep '^>' "$final" | sed 's/^>//; s/#.*//' | sort -u > gffnames.a
grep -o 'Name=[^;]*' intact.gff3 | sed 's/Name=//' | sort -u > gffnames.b
n_shared=$(comm -12 gffnames.a gffnames.b | wc -l)
n_lib=$(wc -l < gffnames.a)
(( n_shared > 0 )) || log "$sp" "ltr-intact-fix: WARNING - none of the $n_lib library names appear in the intact gff3"
log "$sp" "ltr-intact-fix: intact gff3 $n_gff records, covers $n_shared/$n_lib library names"

# The old top-level gff3 was regenerated from an empty rmlist, i.e. it holds
# every pass.list LTR rather than only the intact ones.  Keep it as evidence.
[[ -e "$raw/$g.LTR.intact.raw.gff3" ]] \
  && mv "$raw/$g.LTR.intact.raw.gff3" "$raw/$g.LTR.intact.raw.gff3.unfiltered.$(date +%s)"

cp "$final"     "$ltr/$g.LTR.intact.raw.fa"
cp intact.gff3  "$ltr/$g.LTR.intact.raw.gff3"
cp "$final"     "$raw/$g.LTR.intact.raw.fa"
cp intact.gff3  "$raw/$g.LTR.intact.raw.gff3"
cp rmlist       "$ltr/$g.LTR.intact.fa.ori.rmlist"
# EDTA_raw.pl:457 -- the anno.list is cleanup_misclas's own .cln.cln.list.
# Never read back by EDTA, but keep the shipped file the same shape as upstream's.
if [[ -s "${final}.list" ]]; then
  cp "${final}.list" "$raw/$g.LTR.intact.raw.fa.anno.list"
else
  cp rmlist "$raw/$g.LTR.intact.raw.fa.anno.list"
fi

log "$sp" "ltr-intact-fix: OK - $route: $(grep -c '^>' "$raw/$g.LTR.intact.raw.fa") seqs / $(stat -Lc%s "$raw/$g.LTR.intact.raw.fa") B (was empty), gff3 $(stat -Lc%s "$raw/$g.LTR.intact.raw.gff3") B"
log "$sp" "ltr-intact-fix: the rice fallback at EDTA.pl:481-486 can no longer fire for this species"
