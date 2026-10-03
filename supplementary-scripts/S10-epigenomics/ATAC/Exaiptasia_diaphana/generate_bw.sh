#!/bin/bash
# 確保激活環境

# 1. 建立必要目錄
mkdir -p merged_bams bw_out logs

# ================= 2. 配置原始群組參數 =================
declare -A groups
groups["Control_Apo"]="SRR8536110 SRR8536142 SRR8536109 SRR8536136 SRR8536112 SRR8536135 SRR8536108 SRR8536141 SRR8536107 SRR8536140 SRR8536104 SRR8536122"
groups["Control_Sym"]="SRR8536117 SRR8536126 SRR8536124 SRR8536113 SRR8536125 SRR8536130 SRR8536123 SRR8536129 SRR8536120 SRR8536114 SRR8536121 SRR8536115"
groups["Mild_Apo"]="SRR8536111 SRR8536106 SRR8536105"
groups["Mild_Sym"]="SRR8536118 SRR8536119 SRR8536116"
groups["Stress21_Apo"]="SRR8536103 SRR8536144 SRR8536143"
groups["Stress21_Sym"]="SRR8536134 SRR8536133 SRR8536127"
groups["Stress28_Apo"]="SRR8536139 SRR8536138 SRR8536137"
groups["Stress28_Sym"]="SRR8536132 SRR8536131 SRR8536128"

# ================= 3. 配置名稱對照字典 (對齊 peaks 命名) =================
declare -A name_map
name_map["Control_Apo"]="control_aposymbiotic"
name_map["Control_Sym"]="control_symbiotic"
name_map["Mild_Apo"]="mild-stress_aposymbiotic"
name_map["Mild_Sym"]="mild-stress_symbiotic"
name_map["Stress21_Apo"]="stress_day21_aposymbiotic"
name_map["Stress21_Sym"]="stress_day21_symbiotic"
name_map["Stress28_Apo"]="stress_day28_aposymbiotic"
name_map["Stress28_Sym"]="stress_day28_symbiotic"

# ================= 4. 核心批次處理流程 =================
for key in "${!groups[@]}"; do
    # 獲取對齊 peaks 的正式輸出名稱
    bw_name="${name_map[$key]}"
    echo ">>> 正在處理原始分組: $key ➡️ 目標檔名: ${bw_name}.bw"
    
    # 4.1. 收集該組內所有實際存在的排序 BAM 檔案
    bam_files=""
    for id in ${groups[$key]}; do
        if [ -f "bam/${id}_sorted.bam" ]; then
            bam_files="$bam_files bam/${id}_sorted.bam"
        else
            echo "⚠️  警告：找不到檔案 bam/${id}_sorted.bam，已跳過。"
        fi
    done
    
    # 4.2. 安全檢查：如果組內沒有找到任何 BAM，則跳過該組
    if [ -z "$bam_files" ]; then
        echo "❌ 錯誤：分組 $key 內無有效 BAM 檔案，跳過。"
        echo "------------------------------------------------------------"
        continue
    fi
    
    # 4.3. 將組內的所有 BAM 合併為一個總 BAM
    echo "   📦 正在合併 BAM 檔案..."
    merged_bam="merged_bams/${bw_name}_merged.bam"
    samtools merge -@ 120 "$merged_bam" $bam_files
    
    # 4.4. 為合併後的總 BAM 建立空間索引（deepTools 要求）
    echo "   🔍 正在建立 BAM 索引..."
    samtools index -@ 120 "$merged_bam"
    
    # 4.5. 使用 deepTools 產生高解析度、歸一化的 BigWig 檔案
    echo "   📈 正在生成分組 BigWig (.bw) 檔案..."
    bamCoverage -b "$merged_bam" \
        -o "bw_out/${bw_name}.bw" \
        --binSize 15 \
        --normalizeUsing RPKM \
        -p 120 \
        2> "logs/${bw_name}_bamCoverage.log"
        
    echo "✅ 分組【$key】處理完成！已生成：bw_out/${bw_name}.bw"
    echo "------------------------------------------------------------"
done

echo "🎉 所有分組的 .bw 檔案已成功基於對應 Peaks 命名生成，存放在 'bw_out' 目錄下！"
