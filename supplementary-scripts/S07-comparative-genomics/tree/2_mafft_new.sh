#!/usr/bin/env bash

set -euo pipefail

# 输入文件夹和输出文件夹
input_dir="/mnt/sda/jackie/cnidaria/0.tree/Filtered_Orthogroup_Sequences_new"
output_dir="/mnt/sda/jackie/cnidaria/0.tree/mafft_res_new/"
threads_per_job=4
parallel_jobs=40

mkdir -p "$output_dir"

# 定义一个函数来运行 mafft
run_mafft() {
    local file="$1"
    local filename
    filename=$(basename "$file")
    mafft --anysymbol --thread "$threads_per_job" "$file" > "$output_dir/$filename"
}

export -f run_mafft  # 导出函数以供 parallel 使用
export output_dir threads_per_job

# 同时运行40个OG，每个OG使用4线程，总计最多约160线程。
find "$input_dir" -maxdepth 1 -type f -name "*.fa" -print0 |
    parallel -0 --halt soon,fail=1 -j "$parallel_jobs" run_mafft {}
