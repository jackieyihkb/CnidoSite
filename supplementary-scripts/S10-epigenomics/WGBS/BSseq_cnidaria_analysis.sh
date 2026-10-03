#!/bin/bash
#!/usr/bin/env bash
# generate_commands.sh
# 生成所有需要运行的命令行
# 使用: bash generate_commands.sh

# 读取样本文件
while IFS= read -r line; do
    # 跳过注释和空行
    if [[ -z "$line" || "$line" == \#* ]]; then
        continue
    fi
    
    # 解析样本信息
    read -r species run layout tissue dev_stage treatment <<< "$line"
    
    echo "# ========================================="
    echo "# 样本: $species - $run (布局: $layout)"
    echo "# ========================================="
    
    if [ "$layout" = "PAIRED" ]; then
        # Trimmomatic命令
        echo "echo '开始处理样本: $run'"
        echo "trimmomatic PE -threads 180 -phred33 \\"
        echo "  rawdata/${run}_1.fastq.gz rawdata/${run}_2.fastq.gz \\"
        echo "  trimmed/${run}_R1_trimmed.fq.gz trimmed/${run}_R1_unpaired.fq.gz \\"
        echo "  trimmed/${run}_R2_trimmed.fq.gz trimmed/${run}_R2_unpaired.fq.gz \\"
        echo "  ILLUMINACLIP:/home/$USER/.local/share/mamba/envs/bs-seq/share/trimmomatic-0.40-0/adapters/TruSeq3-PE.fa:2:30:10 \\"
        echo "  LEADING:3 TRAILING:3 SLIDINGWINDOW:4:15 MINLEN:36 2> ${run}_trimmomatic.log"
        echo "if [ \$? -ne 0 ]; then echo 'Trimmomatic报错，跳过样本: $run'; continue; fi"
        echo ""
        
        # Bismark比对命令
        echo "bismark --bowtie2 -N 1 -L 20 --parallel 180 \\"
        echo "  -o alignment --prefix '${run}_' \\"
        echo "  Ref/${species}/bismark_index \\"
        echo "  -1 trimmed/${run}_R1_trimmed.fq.gz \\"
        echo "  -2 trimmed/${run}_R2_trimmed.fq.gz 2> ${run}_bismark.log"
        echo "if [ \$? -ne 0 ]; then echo 'Bismark比对报错，跳过样本: $run'; continue; fi"
        echo ""
        
    else
        # Trimmomatic命令（单端）
        echo "echo '开始处理样本: $run'"
        echo "trimmomatic SE -threads 180 -phred33 \\"
        echo "  rawdata/${run}.fastq.gz \\"
        echo "  trimmed/${run}_trimmed.fq.gz \\"
        echo "  ILLUMINACLIP:/home/$USER/.local/share/mamba/envs/bs-seq/share/trimmomatic-0.40-0/adapters/TruSeq3-SE.fa:2:30:10 \\"
        echo "  LEADING:3 TRAILING:3 SLIDINGWINDOW:4:15 MINLEN:36 2> ${run}_trimmomatic.log"
        echo "if [ \$? -ne 0 ]; then echo 'Trimmomatic报错，跳过样本: $run'; continue; fi"
        echo ""
        
        # Bismark比对命令（单端）
        echo "bismark --bowtie2 -N 1 -L 20 --parallel 180 \\"
        echo "  -o alignment --prefix '${run}_' \\"
        echo "  Ref/${species}/bismark_index \\"
        echo "  trimmed/${run}_trimmed.fq.gz 2> ${run}_bismark.log"
        echo "if [ \$? -ne 0 ]; then echo 'Bismark比对报错，跳过样本: $run'; continue; fi"
        echo ""
    fi
    
    # 甲基化提取命令
    echo "# 甲基化提取"
    echo "bismark_methylation_extractor --bedGraph --counts --report \\"
    echo "  --buffer_size 10G --multicore 180 --gzip --cytosine_report \\"
    echo "  --genome_folder Ref/${species}/bismark_index --CX_context \\"
    echo "  -o alignment alignment/${run}_bismark_bt2.bam 2> ${run}_meth_extract.log"
    echo "if [ \$? -ne 0 ]; then echo '甲基化提取报错，跳过样本: $run'; continue; fi"
    echo ""
    echo "echo '样本 $run 处理完成'"
    echo ""
    
done < "samples.txt"

echo "echo '所有样本处理完成'"
