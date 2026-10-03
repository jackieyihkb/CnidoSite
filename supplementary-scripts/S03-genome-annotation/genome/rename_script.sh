#!/bin/bash

# ============================================================
# 配置区域
# ============================================================
# 物种前缀（用于输出文件名）
SPECIES_PREFIX="Acropora_austera"

# 输入文件名
INPUT_FA="${SPECIES_PREFIX}.fa"
INPUT_GFF="${SPECIES_PREFIX}.gff3"

# 输出文件名
OUTPUT_FA="${SPECIES_PREFIX}.renamed.fa"
OUTPUT_GFF="${SPECIES_PREFIX}.renamed.gff3"

# ============================================================
# 步骤 1: 提取 FASTA 的 ID 映射关系
# 逻辑: >OZ187076.1 ... -> OZ187076.1 chr1
# ============================================================
echo "正在提取 ID 映射关系..."

declare -A ID_MAP

while read -r line; do
    # 跳过空行
    if [[ -z "$line" ]]; then continue; fi
    
    # 检查是否是 FASTA 的头部 (>开头)
    if [[ "$line" =~ ^\> ]]; then
        # 提取完整的旧 ID (去掉 > 号)
        OLD_ID=${line#>}
        
        # 提取新 ID
        if [[ "$OLD_ID" =~ chromosome:\ ([0-9]+) ]]; then
            # 匹配 chromosome: 1 这种情况
            NEW_ID="chr${BASH_REMATCH[1]}"
        elif [[ "$OLD_ID" =~ contig:\ (SCAFFOLD_[0-9]+) ]]; then
            # 匹配 contig: SCAFFOLD_66 这种情况
            NEW_ID="${BASH_REMATCH[1]}"
        else
            # 如果没有匹配到预期格式，保留原样或跳过
            echo "警告: 未识别的格式 '$OLD_ID'，将跳过..."
            continue
        fi
        
        # 存入关联数组 (旧ID -> 新ID)
        ID_MAP["$OLD_ID"]="$NEW_ID"
    fi
done < "$INPUT_FA"

echo "提取完成。共找到 ${#ID_MAP[@]} 条染色体/Contig 记录。"

# ============================================================
# 步骤 2: 重写 FASTA 文件
# ============================================================
echo "正在重写 FASTA 文件 -> ${OUTPUT_FA} ..."

# 清空或创建输出文件
> "$OUTPUT_FA"

# 重新读取原FASTA，使用新的 ID 输出
while read -r line; do
    if [[ "$line" =~ ^\> ]]; then
        OLD_ID=${line#>}
        NEW_HEADER=">${ID_MAP[$OLD_ID]}"
        echo "$NEW_HEADER" >> "$OUTPUT_FA"
    else
        echo "$line" >> "$OUTPUT_FA"
    fi
done < "$INPUT_FA"

echo "FASTA 文件处理完毕。"

# ============================================================
# 步骤 3: 重写 GFF 文件
# ============================================================
echo "正在重写 GFF 文件 -> ${OUTPUT_GFF} ..."

# 清空或创建输出文件
> "$OUTPUT_GFF"

LINE_COUNT=0
REPLACED_COUNT=0

while IFS=$'\t' read -r col1 col2 col3 col4 col5 col6 col7 col8 col9; do
    ((LINE_COUNT++))
    
    # 只有当第一列存在于我们的映射表中时才替换
    if [[ -n "${ID_MAP[$col1]}" ]]; then
        NEW_COL1="${ID_MAP[$col1]}"
        # 使用制表符连接并打印
        echo -e "${NEW_COL1}\t${col2}\t${col3}\t${col4}\t${col5}\t${col6}\t${col7}\t${col8}\t${col9}" >> "$OUTPUT_GFF"
        ((REPLACED_COUNT++))
    else
        # 如果没找到（比如注释行或其他异常行），原样输出
        echo -e "${col1}\t${col2}\t${col3}\t${col4}\t${col5}\t${col6}\t${col7}\t${col8}\t${col9}" >> "$OUTPUT_GFF"
    fi
done < "$INPUT_GFF"

echo "GFF 文件处理完毕。共处理 $LINE_COUNT 行，成功替换 $REPLACED_COUNT 行 ID。"
echo "--------------------------------------------------------"
echo "处理完成！"
echo "请检查以下两个新文件："
ls -lh "${OUTPUT_FA}" "${OUTPUT_GFF}"
