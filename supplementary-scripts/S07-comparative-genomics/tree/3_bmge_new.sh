#!/usr/bin/env bash

set -euo pipefail

input_dir="/mnt/sda/jackie/cnidaria/0.tree/mafft_res_new"
output_dir="/mnt/sda/jackie/cnidaria/0.tree/bmge_res_new"
parallel_jobs=80

mkdir -p "$output_dir"

run_bmge() {
    local file="$1"
    local filename
    filename=$(basename "$file")

    # -t AA指定蛋白质数据；其余筛选参数使用BMGE 1.12默认值。
    bmge -i "$file" -t AA -of "$output_dir/$filename"
}

export -f run_bmge
export output_dir

# BMGE本身按单核任务运行，同时处理40个OG。
find "$input_dir" -maxdepth 1 -type f -name "*.fa" -print0 |
    parallel -0 --halt soon,fail=1 -j "$parallel_jobs" run_bmge {}
