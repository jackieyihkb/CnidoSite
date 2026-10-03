#!/usr/bin/env bash
# Environment for the CnidoSite MAG functional-annotation pipeline.
#
# Two things here are easy to get wrong and neither fails loudly, so they are
# set centrally rather than in each script:
#
#   1. InterProScan 5.78 needs Java 11+. The system `java` is 1.8.0_504 and
#      InterProScan refuses to start under it ("Java version 11 is required").
#      A conda OpenJDK 11 exists on this machine; JAVA_HOME must point at it.
#   2. The gene-prediction and search tools (prodigal, diamond, mmseqs) live in
#      another user's conda env, not on PATH. They are prefixed here by absolute
#      path so a missing one is a clear "not found" at run time rather than a
#      silently skipped stage.
#
#   source pipeline/env.sh
#   "$IPS" -i proteins.faa -f TSV -dp -cpu 8 -o out.tsv

_cnido_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
export MAG_ROOT="$_cnido_root"

export PY="${MAG_PY:-/home/$USER/miniconda3/bin/python}"

# --- Java 11 for InterProScan -------------------------------------------------
MAG_JAVA_HOME="${MAG_JAVA_HOME:-/home/$USER/.local/share/mamba/pkgs/openjdk-11.0.30-ha668962_0}"
if [ ! -x "$MAG_JAVA_HOME/bin/java" ]; then
    echo "WARNING: Java 11 not found at $MAG_JAVA_HOME; InterProScan will not run." >&2
    echo "         Set MAG_JAVA_HOME to a JDK 11+ installation." >&2
else
    export JAVA_HOME="$MAG_JAVA_HOME"
    export PATH="$JAVA_HOME/bin:$PATH"
fi

# --- Annotation / search tools ------------------------------------------------
MAG_TOOLS="${MAG_TOOLS:-/mnt/sda/lurui/software/miniconda3/envs/marine_sm/bin}"
export MAG_TOOLS
export IPS="${MAG_IPS:-/mnt/sda/databases/interproscan/interproscan-5.78-109.0/interproscan.sh}"
export PRODIGAL="${MAG_PRODIGAL:-$MAG_TOOLS/prodigal}"
export DIAMOND="${MAG_DIAMOND:-$MAG_TOOLS/diamond}"
export MMSEQS="${MAG_MMSEQS:-/mnt/sda/databases/eggnog/mmseqs}"
# HMMER for stage 05. Exported because the per-chunk workers are separate
# processes and must find it without inheriting this shell's variables.
export HMMER_BIN="${MAG_HMMER_BIN:-$MAG_TOOLS}"

# --- Reference databases ------------------------------------------------------
export SWISSPROT_DMND="${MAG_SWISSPROT_DMND:-}"
export SWISSPROT_FASTA="${MAG_SWISSPROT_FASTA:-/mnt/sda/databases/release226}"
export EGGNOG_DMND="${MAG_EGGNOG_DMND:-/mnt/sda/databases/eggnog/eggnog_proteins.dmnd}"
export KOFAM_DIR="${MAG_KOFAM_DIR:-/mnt/sda/databases/kofam}"
export EGGNOG_DB="${MAG_EGGNOG_DB:-/mnt/sda/databases/eggnog/eggnog.db}"
