#!/bin/bash

# 激活环境
conda activate atac_seq
mkdir -p logs fastqc_out bam merged_bams peaks

# ================= 配置参数 =================
GENOME_FA="Nematostella_vectensis.fa"
GENE_GFF="Nematostella_vectensis.gff3"

# 创建 Bowtie2 索引 (只需运行一次)
if [ ! -f "${GENOME_FA}.1.bt2" ]; then
    echo ">>> Building Bowtie2 index..."
    bowtie2-build --threads 120 $GENOME_FA genome_index
fi

# ================= 1. 质控 (FastQC) =================
echo ">>> Running FastQC..."
fastqc -o fastqc_out *.fastq.gz > logs/fastqc.log 2>&1

# ================= 2. 批量比对与排序 (单端数据) =================
echo ">>> Running Alignment..."
for fastq in *.fastq.gz; do
    sample=$(basename $fastq _1.fastq.gz)
    
    echo "Processing $sample..."
    
    # Bowtie2 单端比对
    bowtie2 -x genome_index \
        -U ${sample}.fastq.gz\
        -S ${sample}.sam \
        -p 120 \
        --very-sensitive \
        --dovetail \
        --no-mixed \
        --no-discordant \
        2> logs/${sample}_align.log
        
    # SAM to BAM & Sort by coordinate (单端数据按坐标排序)
    samtools view -@ 120 -bS ${sample}.sam | samtools sort -o bam/${sample}_sorted.bam
    
    # 建立索引
    samtools index bam/${sample}_sorted.bam
    
    # Clean up
    rm ${sample}.sam
done

# ================= 3. 批量 Peak Calling (MACS2) =================
echo ">>> Running MACS2 Peak Calling..."

# 按照你的8个分组进行批量 Call Peak
# 单端数据使用 -f BAM 参数
declare -A groups
groups["Control_Apo"]="SRR8536110 SRR8536142 SRR8536109 SRR8536136 SRR8536112 SRR8536135 SRR8536108 SRR8536141 SRR8536107 SRR8536140 SRR8536104 SRR8536122"
groups["Control_Sym"]="SRR8536117 SRR8536126 SRR8536124 SRR8536113 SRR8536125 SRR8536130 SRR8536123 SRR8536129 SRR8536120 SRR8536114 SRR8536121 SRR8536115"
groups["Mild_Apo"]="SRR8536111 SRR8536106 SRR8536105"
groups["Mild_Sym"]="SRR8536118 SRR8536119 SRR8536116"
groups["Stress21_Apo"]="SRR8536103 SRR8536144 SRR8536143"
groups["Stress21_Sym"]="SRR8536134 SRR8536133 SRR8536127"
groups["Stress28_Apo"]="SRR8536139 SRR8536138 SRR8536137"
groups["Stress28_Sym"]="SRR8536132 SRR8536131 SRR8536128"

for group_name in "${!groups[@]}"; do
    echo ">>> Calling peaks for Group: $group_name"
    
    # 收集组内所有 BAM 文件
    bam_files=""
    for id in ${groups[$group_name]}; do
        if [ -f "bam/${id}_sorted.bam" ]; then
            bam_files="$bam_files bam/${id}_sorted.bam"
        fi
    done
    
    # MACS2 合并 Call Peak (单端数据使用 -f BAM)
 #   macs callpeak \
 #       -t $bam_files \
 #       -n peaks/${group_name}_consensus \
 #       -f BAM \
 #       -g 1.98e8 \
 #       --nomodel \
 #       --shift -37 \
 #       --extsize 73 \
 #       -B \
 #       --SPMR \
 #       --keep-dup all \
 #       -q 0.05 \
 #       2> logs/${group_name}_macs2.log
        
    # 转换为 Bed 格式供 R 语言使用
#    if [ -f "peaks/${group_name}_consensus_peaks.narrowPeak" ]; then
#        grep -v "^#" peaks/${group_name}_consensus_peaks.narrowPeak | cut -f 1-6 > peaks/${group_name}_peaks.bed
#    fi
done

# ================= 4. 生成汇总统计 =================
echo ">>> Generating summary statistics..."
echo "Group,Number_of_Peaks" > peaks/summary_stats.csv
for peak_file in peaks/*_consensus_peaks.narrowPeak; do
    group=$(basename $peak_file _consensus_peaks.narrowPeak)
    count=$(wc -l < $peak_file)
    echo "$group,$count" >> peaks/summary_stats.csv
done

echo ">>> Pipeline finished!"
echo "Results are in the 'peaks' directory."
