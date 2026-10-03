#!/bin/bash

# 1. 建立必要目錄
mkdir -p merged_bams bw_out logs

# 2. 檢查 macs2.sh 是否存在
if [ ! -f "macs2.sh" ]; then
    echo "❌ 錯誤：在此目錄下找不到 macs2.sh 檔案！"
    exit 1
fi

echo "=============================================================="
echo "🎯 開始全自動解析 macs2.sh 並生成 BigWig 檔案..."
echo "=============================================================="

# 3. 逐行讀取 macs2.sh 進行正則表達式解析
while IFS= read -r line || [ -n "$line" ]; do
    # 跳過空行、註釋行或不包含 macs2 的行
    [[ -z "$line" || "$line" =~ ^# || ! "$line" =~ macs2 ]] && continue

    # 提取 -n 參數後的名稱 (支援帶連字號、底線和加減號的名稱)
    if [[ "$line" =~ -n[[:space:]]+peaks/([^[:space:]]+) ]]; then
        group_name="${BASH_REMATCH[1]}"
    else
        echo "⚠️  跳過無法解析名稱的行: $line"
        continue
    fi

    # 提取 -t 到下一個參數（-c 或 -n 或 -f）之間的所有內容
    if [[ "$line" =~ -t[[:space:]]+(.*)[[:space:]]+-(c|n|f) ]]; then
        t_content="${BASH_REMATCH[1]}"
        # 只保留其中的 bam 檔案路徑，去除可能殘留的其它參數
        t_content=$(echo "$t_content" | sed -E 's/-[a-zA-Z].*//g')
    else
        echo "⚠️  跳過無法解析 Treatment BAM 的行: $line"
        continue
    fi

    # 4. 收集並過濾出本機實際存在的 BAM 檔案
    bam_files=""
    for bam_path in $t_content; do
        if [ -f "$bam_path" ]; then
            bam_files="$bam_files $bam_path"
        else
            echo "⚠️  警告：[${group_name}] 找不到檔案 ${bam_path}，已跳過。"
        fi
    done

    # 5. 安全檢查：如果沒找到任何有效 BAM 則跳過
    if [ -z "$bam_files" ]; then
        echo "❌ 錯誤：分組 [${group_name}] 內無有效 BAM 檔案，跳過該組。"
        echo "------------------------------------------------------------"
        continue
    fi

    # ================= 核心批次處理流程 =================
    echo ">>> 正在處理分組: $group_name"
    
    # 6. 合併 BAM
    echo "   📦 正在合併 BAM 檔案..."
    merged_bam="merged_bams/${group_name}_merged.bam"
    samtools merge -@ 120 "$merged_bam" $bam_files
    
    # 7. 建立索引
    echo "   🔍 正在建立 BAM 索引..."
    samtools index -@ 120 "$merged_bam"
    
    # 8. 轉成 BigWig
    echo "   📈 正在生成分組 BigWig (.bw) 檔案..."
    bamCoverage -b "$merged_bam" \
        -o "bw_out/${group_name}.bw" \
        --binSize 15 \
        --normalizeUsing RPKM \
        -p 120 \
        2> "logs/${group_name}_bamCoverage.log"
        
    echo "✅ 分組【$group_name】處理完成！已生成：bw_out/${group_name}.bw"
    echo "------------------------------------------------------------"

done < macs2.sh

echo "🎉 任務全部結束！所有有效分組的 .bw 已成功存放在 'bw_out' 目錄下！"
