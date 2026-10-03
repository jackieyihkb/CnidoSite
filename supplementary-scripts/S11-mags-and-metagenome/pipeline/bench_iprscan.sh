#!/bin/bash
# 用 bench500.faa（499 条，14 个 MAG 等比例抽样）量 InterProScan 的吞吐，
# 并回答一个光看配置答不出来的问题：**默认到底跑了哪些 analysis**。
#
# 为什么必须实测：jar 内有个 application-default.properties，它配置了全部 analysis
# （CDD / HAMAP / PRINTS / PROSITE / SMART / Coils / MobiDBLite …），而安装目录里那份
# interproscan.properties 只覆盖其中 9 个 HMM 库（antifam/gene3d/ncbifam/panther/
# pfam/pirsf/pirsr/sfld/superfamily）。同时 `-incldepappl` 的说明写着「不带这个参数
# 时 deprecated 的 analysis 不跑」，但 deprecated 名单在 Java 里、资源文件里搜不到。
# 所以：**跑一遍看 TSV 第 4 列出现了哪些 analysis**，这是唯一的权威答案。
#
# 用法：bench_iprscan.sh <label> [appl]
#   bench_iprscan.sh default                  # 默认全集
#   bench_iprscan.sh restricted Pfam,Gene3D   # 限定集合
set -u
LABEL=${1:?usage: bench_iprscan.sh <label> [appl]}
APPL=${2:-}

BASE=/mnt/sda/jackie/tools/mag_pipeline/work
IPR=/mnt/sda/jackie/tools/iprscan/interproscan-5.78-109.0
IN="$BASE/bench500.faa"
OUT="$BASE/bench_${LABEL}.tsv"
LOG="$BASE/bench_${LABEL}.log"

export PATH=/home/jackie/miniconda3/bin:$PATH
rm -f "$OUT"

ARGS=(-i "$IN" -f TSV -o "$OUT" -iprlookup -goterms -dp -cpu 8)
[ -n "$APPL" ] && ARGS+=(-appl "$APPL")

{
  echo "=== bench '$LABEL' start $(date '+%F %T')  appl=${APPL:-ALL} ==="
  echo "input: $(grep -c '^>' "$IN") sequences"
} >"$LOG"

t0=$(date +%s)
nice -n 10 "$IPR/interproscan.sh" "${ARGS[@]}" >>"$LOG" 2>&1
rc=$?
t1=$(date +%s)

# 每秒多少条：拿它乘 59,830 就是全量 ETA。注意这是**线性外推**，
# 而 InterProScan 的启动开销（把 5 GB Gene3D 读进 JVM）是固定的，小样本会高估 ETA。
n=$(grep -c '^>' "$IN")
{
  echo "=== bench '$LABEL' exit=$rc  elapsed=$((t1-t0))s ==="
  echo "throughput: $(echo "$n $((t1-t0))" | awk '{printf "%.3f", $1/$2}') seq/s"
  echo "linear ETA for 59830: $(echo "$n $((t1-t0))" | awk '{printf "%.1f", 59830*$2/$1/3600}') h"
  if [ -s "$OUT" ]; then
    echo "--- analyses that actually produced output (TSV col 4) ---"
    awk -F'\t' '!/^#/ && NF>=15 {print $4}' "$OUT" | sort | uniq -c | sort -rn
    echo "--- rows with a real InterPro entry (col 12 = IPR\\d{6}) ---"
    awk -F'\t' '!/^#/ && $12 ~ /^IPR[0-9]{6}$/ {n++; k[$4]++} END {print "total:", n+0; for (a in k) printf "  %-22s %d\n", a, k[a]}' "$OUT" | sort -k2 -rn | head -30
    echo "--- distinct proteins with any IPR ---"
    awk -F'\t' '!/^#/ && $12 ~ /^IPR[0-9]{6}$/ {print $1}' "$OUT" | sort -u | wc -l
  else
    echo "NO OUTPUT FILE"
  fi
} >>"$LOG"
cat "$LOG"
exit $rc
