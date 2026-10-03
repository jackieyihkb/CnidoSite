#!/bin/bash
# Acropora cytherea 试点：把两个引擎的合并产物拆回每个 MAG，再逐 MAG 灌库。
#
# 为什么要拆：InterProScan/KofamScan 的固定开销都很重（前者要先读 5.2 GB 的
# Gene3D 模型进 JVM，后者是 27,864 次 hmmsearch），所以 14 个 MAG 是合成一个
# fasta（work/pilot_all.faa，59,830 条）跑一次的；而 load_iprscan.php /
# load_kofam.php 是 `--mag <acc>` 接口，把整份 TSV 都算在那个 MAG 名下。
# 见 split_by_mag.py 的说明。池子里蛋白号全局唯一（实测 0 重号），拆分无损。
#
# 先干跑、后写库：不带 --apply 时两个 loader 都只统计不写，写完会打印每张表的
# 行数预览。**写盘一律压在 --apply 后面**（2026-09-22 那次"dry run 实际写盘 89
# 个文件"的教训）。单个 MAG 的 loader 自己会先 DELETE 该 mag 的旧行再灌，
# 所以对同一个 MAG 重跑是幂等的。
#
# 用法：load_pilot.sh            # 干跑
#       load_pilot.sh --apply    # 真写
set -u
APPLY=""
[ "${1:-}" = "--apply" ] && APPLY="--apply"

BASE=/mnt/sda/jackie/tools/mag_pipeline
WORK=$BASE/work
SPLIT=$WORK/split
FASTA=/tmp/magpilot/proteins          # 源 fasta 文件名 = AssemblyAccession
IPR_TSV=$WORK/pilot_iprscan.tsv
KOF_TSV=$WORK/pilot_kofam.tsv

# CLI 必须带这个 ini，否则 mysqli 都加载不了（见 memory: 本机 CLI 的 php.ini 是坏的）
PHP=(env PHP_INI_SCAN_DIR=/etc/php/7.4/apache2/conf.d php -c /etc/php/7.4/apache2/php.ini)

for f in "$IPR_TSV" "$KOF_TSV"; do
  [ -s "$f" ] || { echo "ABORT: missing/empty $f" >&2; exit 1; }
done

echo "=== split $(date '+%F %T') ==="
python3 "$BASE/split_by_mag.py" --fasta-dir "$FASTA" --in "$IPR_TSV" \
        --outdir "$SPLIT" --suffix iprscan || exit 1
python3 "$BASE/split_by_mag.py" --fasta-dir "$FASTA" --in "$KOF_TSV" \
        --outdir "$SPLIT" --suffix kofam   || exit 1

echo
echo "=== load $(date '+%F %T')  apply=${APPLY:-no} ==="
fail=0
for f in "$SPLIT"/*.iprscan.tsv; do
  [ -e "$f" ] || { echo "no split files in $SPLIT"; exit 1; }
  mag=$(basename "$f" .iprscan.tsv)
  echo "--- $mag"
  "${PHP[@]}" "$BASE/load_iprscan.php" --mag "$mag" --tsv "$f" \
      --ref "$BASE/ref" $APPLY || { echo "  iprscan load FAILED for $mag" >&2; fail=1; }
  kf="$SPLIT/$mag.kofam.tsv"
  if [ -s "$kf" ]; then
    "${PHP[@]}" "$BASE/load_kofam.php" --mag "$mag" --out "$kf" \
        --koref /mnt/sda/jackie/tools/kofam-db/ko_ref.tsv $APPLY \
        || { echo "  kofam load FAILED for $mag" >&2; fail=1; }
  else
    # 一个 KO 都没有是可能的（大量 hypothetical protein），说明而不是当失败。
    echo "  (no KofamScan hits for $mag)"
  fi
done

echo
echo "=== done $(date '+%F %T')  fail=$fail ==="
[ -z "$APPLY" ] && echo "DRY RUN — rerun with --apply to write"
exit $fail
