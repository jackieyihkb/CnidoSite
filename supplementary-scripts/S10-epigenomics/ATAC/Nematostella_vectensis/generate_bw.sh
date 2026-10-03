#!/bin/bash
# 確保激活環境
# 建立專門存放分組 BAM 和最終 .bw 的目錄
mkdir -p merged_bams bw_out logs

# ================= 配置分組參數 (已完全對齊 macs2.sh 的 Treatment 樣本) =================
declare -A groups
groups["ELAV_positive_cells"]="SRR6502906 SRR6502907 SRR6502908 SRR6502909"
groups["DD_CT_13h"]="SRR10034533 SRR10034534"
groups["DD_CT_21h"]="SRR10034531 SRR10034532"
groups["DD_CT_37h"]="SRR10034527 SRR10034528"
groups["DD_CT_45h"]="SRR10034529 SRR10034530"
groups["LD_CT_13h"]="SRR10034525 SRR10034526"
groups["LD_CT_21h"]="SRR10034539 SRR10034540"
groups["LD_CT_29h"]="SRR10034524 SRR10034541"
groups["LD_CT_37h"]="SRR10034535 SRR10034536"
groups["LD_CT_45h"]="SRR10034537 SRR10034538"
groups["prestress_GSF"]="SRR10822224 SRR10822213 SRR10822209"
groups["re24h_GSF"]="SRR10822237 SRR10822236 SRR10822234"
groups["re24h_GSL"]="SRR10822233 SRR10822232 SRR10822231"
groups["re48h_GSF"]="SRR10822227 SRR10822226"
groups["re48h_GSL"]="SRR10822225 SRR10822223 SRR10822222"
groups["stress_GSF"]="SRR10822205 SRR10822245 SRR10822244"
groups["stress_GSL"]="SRR10822241 SRR10822243 SRR10822242"

# ================= 核心批次處理流程 =================
for group_name in "${!groups[@]}"; do
    echo ">>> 正在處理分組: $group_name"
    
    # 1. 收集該組內所有實際存在的排序 BAM 檔案
    bam_files=""
    for id in ${groups[$group_name]}; do
        if [ -f "bam/${id}_sorted.bam" ]; then
            bam_files="$bam_files bam/${id}_sorted.bam"
        else
            echo "⚠️  警告：找不到檔案 bam/${id}_sorted.bam，已跳過。"
        fi
    done
    
    # 2. 安全檢查：如果組內沒有找到任何 BAM，則跳過該組
    if [ -z "$bam_files" ]; then
        echo "❌ 錯誤：分組 $group_name 內無有效 BAM 檔案，跳過。"
        continue
    fi
    
    # 3. 將組內的所有 BAM 合併為一個總 BAM
    echo "   📦 正在合併 BAM 檔案..."
    merged_bam="merged_bams/${group_name}_merged.bam"
    samtools merge -@ 120 "$merged_bam" $bam_files
    
    # 4. 為合併後的總 BAM 建立空間索引（deepTools 的硬性要求）
    echo "   🔍 正在建立 BAM 索引..."
    samtools index -@ 120 "$merged_bam"
    
    # 5. 使用 deepTools 產生高解析度、歸一化的 BigWig 檔案
    echo "   📈 正在生成分組 BigWig (.bw) 檔案..."
    # 💡 參數優化：
    # --binSize 15: 每 15bp 一個滑動窗口，完美保留 ATAC-seq 波峰特徵
    # --normalizeUsing RPKM: 消除分組間因測序量不同帶來的影響，使 Y 軸高度具備直接對比性
    bamCoverage -b "$merged_bam" \
        -o "bw_out/${group_name}_consensus.bw" \
        --binSize 15 \
        --normalizeUsing RPKM \
        -p 120 \
        2> "logs/${group_name}_bamCoverage.log"
        
    echo "✅ 分組【$group_name】處理完成！已生成：bw_out/${group_name}_consensus.bw"
    echo "------------------------------------------------------------"
done

echo "🎉 所有分組的 .bw 檔案已成功基於 BAM 生成，存放在 'bw_out' 目錄下！"
