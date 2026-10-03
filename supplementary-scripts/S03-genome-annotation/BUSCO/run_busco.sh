#!/bin/bash

# --- 配置部分 ---
# 设置你的数据库路径或名称
LINEAGE_DB="cnidaria_odb12" 
# 设置数据库模式: 蛋白为 protein, 基因组为 genome
MODE="protein"
# 设置CPU核心数
THREADS=120
# 存放结果的根目录
OUTPUT_DIR="busco_results"
# 存放蛋白文件(.faa)的目录
INPUT_DIR="./"

# --- 循环运行部分 ---
mkdir -p $OUTPUT_DIR

for file in ${INPUT_DIR}/*.pep; do
    # 获取文件名（不带路径和扩展名）
    filename=$(basename "$file" .pep)
    
    echo "正在运行 BUSCO: $filename ..."
    
    # 运行 BUSCO
    busco -i "$file" \
          -l "$LINEAGE_DB" \
          -o "${filename}_results" \
          -m "$MODE" \
          -c "$THREADS" \
          --out_path "$OUTPUT_DIR"
          
done

echo "所有任务已完成！"
