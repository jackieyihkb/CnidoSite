#!/bin/bash
# 把 59,830 条蛋白切成小块，串行跑 InterProScan，每块一个独立产物。
#
# 为什么要分块：**InterProScan 没有断点续跑**。实测 -cpu 16 时边际速率 ~1.19 seq/s，
# 全量 ~14-16 小时；跑十四个小时之后崩一次就是全部重来。切成 10 块后，最坏情况
# 只丢一块（~1.5 小时），而且已完成的块直接跳过（幂等、可中断续跑）。
#
# 块大小是权衡出来的：-cpu 16、499 条时总耗时 488 s，其中固定开销（读模型进 JVM、
# 建索引）约 70 s，边际 1.19 seq/s。块越小固定开销占比越高（10 块共 ~12 分钟），
# 但每一块的 checkpoint 粒度越细。10 × 6,000 是这两者的折中。
#
# 为什么 -cpu 16 而不是 8：同一份 bench500，-cpu 8 是 873 s、-cpu 16 是 488 s
# （1.79 倍），两边结果完全一致（434 条有 IPR、各 analysis 行数逐项相同）。
# 机器是 20 线程（i7-12700），留 4 个线程给站点侧（mysqld 常吃 3-4 核）。
# nice -n 10：站点请求永远优先。
#
# 为什么不要 -appl 收窄：实测默认跑的 18 个 analysis 里，13 个都真的贡献了
# InterPro 条目（Pfam/SUPERFAMILY/Gene3D/PRINTS/PANTHER/ProSiteProfiles/
# NCBIfam/SMART/ProSitePatterns/CDD/Hamap/PIRSF/SFLD），只有 AntiFam/Coils/
# FunFam/MobiDBLite 不贡献（它们的 TSV 第 12 列是 '-'，load_iprscan.php 会丢掉）。
# 砍掉它们省不了多少时间，却要冒漏注释的风险。Phobius/SignalP/TMHMM 的模型
# 本就不随包分发（data/ 下是空目录），所以那三个不在列表里是正常的。
#
# 用法：run_iprscan_chunked.sh [chunks] [cpu]
set -u
CHUNKS=${1:-10}
CPU=${2:-16}

BASE=/mnt/sda/jackie/tools/mag_pipeline
WORK=$BASE/work
IPR=/mnt/sda/jackie/tools/iprscan/interproscan-5.78-109.0
IN="$WORK/pilot_all.faa"
CHUNKDIR="$WORK/ipr_chunks"
FINAL="$WORK/pilot_iprscan.tsv"
LOG="$WORK/iprscan_chunked.log"

export PATH=/home/jackie/miniconda3/bin:$PATH
mkdir -p "$CHUNKDIR"

say() { echo "$(date '+%F %T')  $*" | tee -a "$LOG"; }

# ---- 切块（按序列数均分；每块自带自己的头行，行数在报告里核对总量） ----
if [ ! -s "$CHUNKDIR/.split_done" ]; then
  say "splitting $IN into $CHUNKS chunks"
  total=$(grep -c '^>' "$IN")
  per=$(( (total + CHUNKS - 1) / CHUNKS ))
  awk -v per="$per" -v dir="$CHUNKDIR" '
    /^>/ { n++; c = int((n-1)/per) }
    { print > (dir "/chunk_" sprintf("%02d", c) ".faa") }
  ' "$IN"
  touch "$CHUNKDIR/.split_done"
  say "chunks: $(ls "$CHUNKDIR"/*.faa | wc -l)  (per-chunk max $per seqs)"
fi

# ---- 逐块跑，已有的跳过 ----
fail=0
for f in "$CHUNKDIR"/chunk_*.faa; do
  b=$(basename "$f" .faa)
  out="$CHUNKDIR/$b.tsv"
  if [ -s "$out" ]; then
    say "SKIP $b (already done: $(wc -l < "$out") lines)"
    continue
  fi
  n=$(grep -c '^>' "$f")
  say "RUN  $b ($n seqs, cpu=$CPU)"
  t0=$(date +%s)
  nice -n 10 "$IPR/interproscan.sh" -i "$f" -f TSV -o "$out" \
       -iprlookup -goterms -dp -cpu "$CPU" >>"$LOG" 2>&1
  rc=$?
  t1=$(date +%s)
  if [ $rc -ne 0 ] || [ ! -s "$out" ]; then
    say "FAIL $b exit=$rc elapsed=$((t1-t0))s -- stopping (rerun to resume)"
    [ -f "$out" ] && mv "$out" "$out.partial"
    fail=1
    break
  fi
  say "OK   $b elapsed=$((t1-t0))s  rows=$(wc -l < "$out")"
done

[ $fail -ne 0 ] && exit 1

# ---- 合并（只有全部块都在才合并，避免把半份数据当成品交出去） ----
missing=0
for f in "$CHUNKDIR"/chunk_*.faa; do
  b=$(basename "$f" .faa)
  [ -s "$CHUNKDIR/$b.tsv" ] || { say "MISSING $b.tsv"; missing=1; }
done
if [ $missing -ne 0 ]; then say "NOT merging: some chunks incomplete"; exit 1; fi

cat "$CHUNKDIR"/chunk_*.tsv > "$FINAL.tmp" && mv "$FINAL.tmp" "$FINAL"
say "merged into $FINAL  ($(wc -l < "$FINAL") lines)"
awk -F'\t' '!/^#/ && NF>=15 && $12 ~ /^IPR[0-9]{6}$/ {print $1}' "$FINAL" | sort -u | \
  awk -v f="$FINAL" '{n++} END {printf "  distinct proteins with an InterPro entry: %d\n", n+0}' | tee -a "$LOG"
say "ALL CHUNKS DONE"
