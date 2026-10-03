#!/usr/bin/env bash
# B: Oculina arbuscula (SRP513328) -> a merged 10x-format count dir.
#
# Parameters are not assumed.  fastq-dump measured the real structure:
#   R1 = exactly 28 bp  (16 CB + 12 UMI)   R2 = exactly 90 bp
# i.e. 10x Chromium v3, so the 3M-february-2018 whitelist and CB 1-16 / UMI 17-28.
# A 200k-read dry run on that basis gave 95.8% valid barcodes, 46.8% uniquely
# mapped, 42,439 features -- wrong offsets give ~0% valid barcodes, so this is
# verified rather than inferred.
#
# The two runs are quantified SEPARATELY and merged afterwards.  Barcodes are
# only unique within a library, so a single read-level concatenation would fuse
# unrelated cells that happen to share a barcode.
set -u

W=/mnt/sda/jackie/cnidaria/codex/singlecell/work
SRA=/mnt/sda/jackie/cnidaria/codex/singlecell/data/raw/OARBU_symbiotic_sra
FASTERQ=/home/$USER/.local/share/mamba/envs/sra/bin/fasterq-dump
STAR=/home/$USER/.local/share/mamba/envs/starsolo/bin/STAR
IDX=$W/oarbu_ref/star_index
WL=$W/3M-february-2018.txt
LOG=$W/oarbu_full.log
THREADS=${THREADS:-32}

log() { echo "[$(date -u +%H:%M:%S)] $*" | tee -a "$LOG"; }

for acc in SRR29367137 SRR29367138; do
  solo=$W/solo/$acc

  # ---- already counted? ----------------------------------------------------
  # Gate on the COUNT MATRIX first, not on the fastq.  The fastq is only a means
  # to the counts, and the deletion step at the bottom of this loop removes it
  # once they land -- so testing for the fastq on a re-run re-dumps a library
  # that is already counted.  That is exactly what happened on 2026-09-26:
  # restarting after the box rebooted started a fresh 1h47m dump of
  # SRR29367137, whose counts had been sitting on disk for twelve hours.
  if [ -s "$solo/Solo.out/Gene/filtered/matrix.mtx" ]; then
    log "$acc STARsolo already present"
    continue
  fi

  fq=$W/fastq/$acc
  mkdir -p "$fq"

  # ---- fastq ----
  # Trust a marker written only after fasterq-dump exits 0, not the .fastq's
  # mere existence: an interrupted dump leaves a truncated file, and STARsolo
  # would quantify it without complaint into a smaller library.  Run 2's dump
  # did exit 0 (rc=0 -> 239G in oarbu_full.log), so its marker is seeded by hand.
  if [ ! -s "$fq/.dump_ok" ]; then
    rm -f "$fq/${acc}_1.fastq" "$fq/${acc}_2.fastq"
    log "fasterq-dump $acc"
    "$FASTERQ" --split-files --threads "$THREADS" --outdir "$fq" \
      "$SRA/$acc/$acc.sra" >>"$LOG" 2>&1
    rc=$?
    log "  rc=$rc -> $(du -sh "$fq" 2>/dev/null | cut -f1)"
    [ "$rc" -eq 0 ] && du -sh "$fq" >"$fq/.dump_ok"
  else
    log "$acc fastq already present ($(cut -f1 "$fq/.dump_ok" 2>/dev/null))"
  fi

  # ---- STARsolo ----
  if [ ! -s "$solo/Solo.out/Gene/filtered/matrix.mtx" ]; then
    log "STARsolo $acc"
    mkdir -p "$solo"
    "$STAR" --runMode alignReads --runThreadN "$THREADS" \
      --genomeDir "$IDX" \
      --readFilesIn "$fq/${acc}_2.fastq" "$fq/${acc}_1.fastq" \
      --soloType CB_UMI_Simple \
      --soloCBwhitelist "$WL" \
      --soloCBstart 1 --soloCBlen 16 \
      --soloUMIstart 17 --soloUMIlen 12 \
      --soloBarcodeReadLength 0 \
      --soloFeatures Gene \
      --soloCellFilter EmptyDrops_CR \
      --soloMultiMappers EM \
      --outSAMtype None \
      --outFileNamePrefix "$solo/" >>"$LOG" 2>&1
    log "  rc=$?"
  else
    log "$acc STARsolo already present"
  fi
  # The fastq is ~3x the .sra and is not needed once counted -- but only drop it
  # if the count actually landed.  Deleting it unconditionally would turn any
  # STARsolo failure into a three-hour re-dump.
  if [ -s "$solo/Solo.out/Gene/filtered/matrix.mtx" ]; then
    rm -rf "$fq"
    log "  removed $fq"
  else
    log "  KEPT $fq (no count matrix -- STARsolo did not succeed)"
  fi
done
log "=== B quantification complete ==="
