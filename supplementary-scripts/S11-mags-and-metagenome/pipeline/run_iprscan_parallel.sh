#!/bin/bash
# 并行版分块跑 InterProScan。目录、产物、合并逻辑与 run_iprscan_chunked.sh 完全一致，
# 只改两件事：(1) 不再一块跑完再跑下一块，而是同时跑 N 块；(2) 收窄 -appl。
# 产物路径一样，所以两版可以互相接力（已完成的块都会被跳过）。
#
# ---- 为什么要并行（2026-09-27 实测，不是推的）----
# 跑 chunk_01 时读两次 /proc/stat（相隔 8 秒窗口）：整机只忙 **3.8~4.5 核 / 20 核**
# （idle 77~81%）。同一时刻 top 的第二采样里 mysqld 占 338~343%（3.4 核），
# 而 InterProScan 的 JVM 只有 **18.7%（不到 0.2 核）** —— 工具链在自己的串行阶段
# （FunFam 的 python search.py）几乎只用一个核，15 个核在空转。
# **别拿 `ps -o pcpu` 当瞬时值**：那是进程生命期均值，同一个 java 它报 26%、
# top 第二采样报 18.7%；把「整棵树」按 pcpu 加起来还会得到自相矛盾的数（见过 650%），
# 因为长命进程的均值被严重稀释。
#
# ---- 为什么每块只给 -cpu 5 ----
# 3 块 × 5 = 15 线程，加上 mysqld 常吃 3.4 核 ≈ 18.4 / 20，留一点余量。
# 单块的边际速率与线程数基本线性（bench500：-cpu 8 → 0.572 seq/s，-cpu 16 → 1.023 seq/s，
# 1.79 倍），所以「一块 16 线程」和「三块各 5 线程」总吞吐接近，但后者能把
# 串行阶段空转的核填上。nice -n 10：站点请求永远优先。
#
# ---- 为什么可以收窄 ----
# chunk_00 实测（真数据，不是 bench 抽样）四个 analysis 对我们存的五列是 **0 贡献**：
#   FunFam 1584 行 / Coils 608 行 / MobiDBLite 1277 行 —— 第 12 列(IPR)与第 14 列(GO)全 0；
#   AntiFam 无命中。load_iprscan.php 本来就把这些行丢掉，跑了纯属白花时间。
# FunFam 尤其贵：chunk_01 末尾它一个人跑了 15+ 分钟（约占整块三成）却只用 1 个核。
# 保留下来的 14 个都是实测真的产出 IPR/GO 的（见 run_iprscan_chunked.sh 的注释）。
#
# 用法：run_iprscan_parallel.sh [concurrency] [cpu] [appl]
set -u
CONC=${1:-3}
CPU=${2:-5}

BASE=/mnt/sda/jackie/tools/mag_pipeline
WORK=$BASE/work
IPR=/mnt/sda/jackie/tools/iprscan/interproscan-5.78-109.0
CHUNKDIR="$WORK/ipr_chunks"
FINAL="$WORK/pilot_iprscan.tsv"
LOG="$WORK/iprscan_parallel.log"

APPL=${3:-CDD-3.21,Gene3D-4.3.0,Hamap-2026_01,NCBIFam-19.0,PANTHER-19.0,Pfam-38.2,PIRSF-3.10,PIRSR-2025_05,PRINTS-42.0,ProSitePatterns-2026_01,ProSiteProfiles-2026_01,SFLD-4,SMART-9.0,SUPERFAMILY-1.75}

export PATH=/home/jackie/miniconda3/bin:$PATH
mkdir -p "$CHUNKDIR"

say() { printf '%s  %s\n' "$(date '+%F %T')" "$*" >> "$LOG"; }

# 别拿 .split_done 当判据：那是 `touch` 建的 **0 字节**文件，`[ -s ]` 对它永远为假
# （原脚本用的是 `[ ! -s ]`，所以它每次启动都会重切一遍，只是切得一样所以没人发现）。
# 直接看切块产物在不在，这才是真正要保证的前提。
ls "$CHUNKDIR"/chunk_*.faa >/dev/null 2>&1 || { echo "chunks not split yet; run run_iprscan_chunked.sh first"; exit 1; }

say "PARALLEL start: concurrency=$CONC cpu/block=$CPU"

# ---- 单个块：成功返回 0，失败把半成品挪开（不能留个 .tsv 让下次误当已完成） ----
run_one() {
  local f=$1 b out n t0 t1 rc
  b=$(basename "$f" .faa)
  out="$CHUNKDIR/$b.tsv"
  n=$(grep -c '^>' "$f")
  t0=$(date +%s)
  say "RUN  $b ($n seqs, cpu=$CPU)"
  nice -n 10 "$IPR/interproscan.sh" -i "$f" -f TSV -o "$out" \
       -iprlookup -goterms -dp -cpu "$CPU" -appl "$APPL" \
       >"$CHUNKDIR/$b.log" 2>&1
  rc=$?
  t1=$(date +%s)
  if [ $rc -ne 0 ] || [ ! -s "$out" ]; then
    say "FAIL $b exit=$rc elapsed=$((t1-t0))s  (partial moved aside; rerun to resume)"
    [ -f "$out" ] && mv "$out" "$out.partial"
    return 1
  fi
  say "OK   $b elapsed=$((t1-t0))s  rows=$(wc -l < "$out")"
  return 0
}

# ---- 线程池：最多 CONC 块同时在跑；已完成块（.tsv 非空）直接跳过，天然可中断续跑 ----
for f in "$CHUNKDIR"/chunk_*.faa; do
  [ -e "$f" ] || continue
  b=$(basename "$f" .faa)
  if [ -s "$CHUNKDIR/$b.tsv" ]; then
    say "SKIP $b (already done: $(wc -l < "$CHUNKDIR/$b.tsv") lines)"
    continue
  fi
  while [ "$(jobs -rp | wc -l)" -ge "$CONC" ]; do sleep 15; done
  run_one "$f" &
done
wait

# ---- 合并：任何一块缺失都不合并，避免把半份数据当成品交出去 ----
fail=0
for f in "$CHUNKDIR"/chunk_*.faa; do
  b=$(basename "$f" .faa)
  [ -s "$CHUNKDIR/$b.tsv" ] || { say "MISSING $b.tsv"; fail=1; }
done
[ $fail -ne 0 ] && { say "NOT merging: some chunks incomplete (rerun to resume)"; exit 1; }

cat "$CHUNKDIR"/chunk_*.tsv > "$FINAL.tmp" && mv "$FINAL.tmp" "$FINAL"
say "merged into $FINAL  ($(wc -l < "$FINAL") lines)"
awk -F'\t' '!/^#/ && NF>=15 && $12 ~ /^IPR[0-9]{6}$/ {print $1}' "$FINAL" | sort -u | \
  awk '{n++} END {printf "  distinct proteins with an InterPro entry: %d\n", n+0}' >> "$LOG"
say "ALL CHUNKS DONE"
