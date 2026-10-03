#!/usr/bin/env bash
# Shared helpers. Source this, do not execute it.

set -uo pipefail

_here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$_here/../config/pipeline.conf"

mkdir -p "$TE_WORK" "$TE_RESULTS" "$TE_PIPE/logs" \
         "$TE_RESULTS/TE_lib" "$TE_RESULTS/TE_info" "$TE_RESULTS/genomes"

# TMPDIR hygiene -- this one is not cosmetic, it silently empties deliverables.
#
# The EDTA stages call `sort` (and RepeatMasker calls mktemp/temp files) and those
# honour TMPDIR.  A session launched from an agent shell inherits
# TMPDIR=/tmp/codeg-acp/<session-id>, which is scratch owned by that session and
# is deleted when the session ends.  Every later `sort` in the still-running
# chain then dies with
#
#     sort: cannot create temporary file in '/tmp/codeg-acp/<id>': No such file
#
# and, because the shell reports nothing upstream, the anno stage keeps going
# with an EMPTY intermediate: RepeatMasker's own output was 7.3 MB (repeats were
# found) but the post-processing wrote a 0-byte TE.fa, so the delivered
# TEanno.gff3 had 0 records and the log read "total TE: 0.00%".
# Measured on Nemopilema_nomurai 2026-09-21; 359 live processes carried the dead
# path.  A per-project directory can never be reaped by that cleanup, so pin it.
export TE_TMP="${TE_TMP:-$TE_ROOT/tmp}"
mkdir -p "$TE_TMP"
export TMPDIR="$TE_TMP" TEMP="$TE_TMP" TMP="$TE_TMP"
# NOTE: this covers processes started FROM HERE ON.  Chains already running keep
# the dead path in their environ and cannot be re-env'd -- watchdog.sh recreates
# the path they hold instead (see ensure_live_tmpdir there).

# Make a restarted species RESUME its RepeatModeler rounds instead of starting
# over at round 1.  EDTA never passes -recoverDir (EDTA_raw.pl:576), so without
# the shim in rmwrap/ a restart silently discards every completed round.  The
# shim is inserted via --repeatmodeler in 20_edta.sh and is inert unless this is
# set -- which matters, because `--overwrite 1` means "deliberately rerun" and
# must not be turned into a resume.
export TE_RM_RESUME=1

log() {  # log <species> <message...>
  local sp="$1"; shift
  printf '[%s] %s: %s\n' "$(date '+%F %T')" "$sp" "$*"
}

# Manifest lookups -----------------------------------------------------------
manifest_field() {  # manifest_field <species> <column-name>
  awk -F'\t' -v s="$1" -v c="$2" \
    'NR==1{for(i=1;i<=NF;i++) if($i==c) col=i; next} $1==s{print $col; exit}' \
    "$TE_PIPE/config/species_manifest.tsv"
}

# All species whose assembly is actually on disk.
#
# This used to test `$4!="none"`, but column 4 is gff3_kind, not the genome: the
# manifest is species/genome/gff3/gff3_kind/n_gene/seqid_match/display_name, so
# the old version silently returned only the 33 species that happen to have a
# gene annotation and dropped the other 31 -- the exact species most likely to be
# the ones you were asking about.  The genome column is 2.
species_list() {  # all species with a genome FASTA, longest genome first
  awk -F'\t' 'NR>1 && $2!="" && $2!="none"{print $1}' "$TE_PIPE/config/species_manifest.tsv"
}

# Paths for one species ------------------------------------------------------
sp_dir()      { echo "$TE_WORK/$1"; }
sp_genome()   { echo "$TE_WORK/$1/$1.renamed.fa"; }
sp_idmap()    { echo "$TE_WORK/$1/$1.idmap.tsv"; }
sp_edta_lib() { echo "$TE_WORK/$1/$1.renamed.fa.mod.EDTA.TElib.fa"; }
sp_result()   { echo "$TE_RESULTS/TE_info/$1/$1.TE_info.tsv"; }

# Where the --anno stage puts its outputs.
#
# NOT next to the genome.  EDTA.pl does `mkdir $genome.EDTA.anno; chdir` into it
# at the --anno stage and produces TEanno.gff3 / .sum / .split.gff3 in there; the
# only thing it copies back up to the parent is the masked genome (EDTA.pl:808).
# Assuming the parent path is why the whole pipeline used to fail its own
# success check: `20_edta.sh` tested for a file that can never exist, declared
# "ERROR: EDTA exited without producing ...", and no species ever reached the TE
# table.  Resolve against the subdir first, and keep the parent as a fallback so
# an EDTA that does copy it up still works.
edta_anno_file() {  # edta_anno_file <species> <filename>
  local sub="$TE_WORK/$1/$1.renamed.fa.mod.EDTA.anno/$2"
  local top="$TE_WORK/$1/$2"
  if [[ -s "$sub" ]]; then echo "$sub"
  elif [[ -s "$top" ]]; then echo "$top"
  else echo "$sub"          # canonical location, so callers' -s tests read false
  fi
}
sp_edta_anno_dir() { echo "$TE_WORK/$1/$1.renamed.fa.mod.EDTA.anno"; }
sp_edta_gff()      { edta_anno_file "$1" "$1.renamed.fa.mod.EDTA.TEanno.gff3"; }
sp_edta_sum()      { edta_anno_file "$1" "$1.renamed.fa.mod.EDTA.TEanno.sum"; }
sp_edta_split()    { edta_anno_file "$1" "$1.renamed.fa.mod.EDTA.TEanno.split.gff3"; }

# Put the EDTA env on PATH.  genometools must stay unwrapped here -- see the
# GT_TIMEOUT note in config/pipeline.conf for why the old gtwrap shim was
# removed (it made EDTA's per-call timeouts orphan the real gt).
activate_env() {
  if [[ -x "$EDTA_ENV/bin/EDTA.pl" ]]; then
    export PATH="$EDTA_ENV/bin:$PATH"
    export PATH="${PATH//$TE_PIPE\/gtwrap:/}"     # belt and braces
  else
    log "$1" "ERROR: EDTA env not found at $EDTA_ENV"; return 1
  fi
}

threads_for_bp() {  # scale thread count with genome size, capped
  local bp="$1" max="${2:-$EDTA_THREADS}"
  local t=$(( bp / 20000000 ))          # 1 thread per 20 Mb
  (( t < 8 )) && t=8
  (( t > max )) && t=$max
  echo "$t"
}

# Assembly size in bp, from config/genome_scan.tsv (the manifest has no size
# column; asking manifest_field for one returns empty and silently falls back
# to the smallest thread allocation).
sp_genome_bp() {  # sp_genome_bp <species>
  awk -F'\t' -v s="$1.fa.gz" '$1==s{print $3; exit}' \
    "$TE_PIPE/config/genome_scan.tsv" 2>/dev/null
}
