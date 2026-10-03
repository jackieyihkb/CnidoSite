#!/bin/bash

TMP_LIST="temp_sample_info.txt"

awk -F'\t' 'NR>1 {if($5=="PAIRED") print $0, "PE"; else print $0, "SE"}' sample.list >temp_sample_info.txt

while read -r ScientificName anno Run Layout tissue treatment description; do
echo "================================================="
echo "开始处理样本: $Run (${ScientificName})"
echo "================================================="

# --- A. 数据修剪 (Trimmomatic) ---
# 根据 Layout 动态生成 Trimmoatic 参数
if [ "$Layout" == "PE" ]; then
    # 双端数据
    trimmomatic PE -threads 60 -phred33 ${Run}_1.fastq.gz ${Run}_2.fastq.gz ./results/trimmed/${Run}_trimmed_1.fastq.gz ./results/trimmed/${Run}_unpaired_1.fastq.gz ./results/trimmed/${Run}_trimmed_2.fastq.gz ./results/trimmed/${Run}_unpaired_2.fastq.gz ILLUMINACLIP:/home/jackie/miniconda3/envs/rna/share/trimmomatic-0.40-0/adapters/TruSeq3-PE.fa:2:30:10 LEADING:3 TRAILING:3 SLIDINGWINDOW:4:15 MINLEN:36
else
    # 单端数据
    trimmomatic SE -threads 60 -phred33 ${Run}.fastq.gz ./results/trimmed/${Run}_trimmed.fastq.gz ILLUMINACLIP:/home/jackie/miniconda3/envs/rna/share/trimmomatic-0.40-0/adapters/TruSeq3-SE.fa:2:30:10 LEADING:3 TRAILING:3 SLIDINGWINDOW:4:15 MINLEN:36
fi

# --- B. 序列比对 (Hisat2) ---
echo "正在进行 Hisat2 比对..."
if [ "$Layout" == "PE" ]; then
    hisat2 -x $anno \
        -1 ./results/trimmed/${Run}_trimmed_1.fastq.gz \
        -2 ./results/trimmed/${Run}_trimmed_2.fastq.gz \
        -S ./results/aligned/${Run}.sam \
        --dta --rna-strandness RF --threads 60 2> results/aligned/${Run}_hisat2.log
else
    hisat2 -x $anno \
        -U ./results/trimmed/${Run}_trimmed.fastq.gz \
        -S ./results/aligned/${Run}.sam \
        --dta --rna-strandness F --threads 60 2> results/aligned/${Run}_hisat2.log
fi

# --- C. SAM 转 BAM 并排序 ---
echo "转换并排序 BAM..."
samtools view -@ 60 -bS ./results/aligned/${Run}.sam | samtools sort -@ 60 -o ./results/aligned/${Run}_sorted.bam
samtools index ./results/aligned/${Run}_sorted.bam
rm results/aligned/${Run}.sam # 删除中间 sam 文件节省空间

# --- D. 定量与组装 (StringTie) ---
echo "运行 StringTie..."
stringtie -p 60 -e -B -G ${anno}.gff3 -o ./results/stringtie_assembly/${Run}.gtf -l ${Run} ./results/aligned/${Run}_sorted.bam

# --- E. 生成 BigWig 文件 (bamCoverage) ---
echo "生成 BigWig 文件..."
# 提取 Mapping Rate 用于日志
MAPPING_RATE=$(grep "mapped (" results/aligned/${Run}_hisat2.log | head -1 | awk -F'_' '{print $6}' | tr -d '%(')

# 提取生物学重复编号 (这是关键步骤)
# 逻辑：按字母顺序排序所有 Run，找到当前 Run 的位置
REP_NUM=$(awk -F'\t' '$1=="'"$ScientificName"'" {print $5}' sample.list | sort | grep -n "^${Run}$" | cut -d: -f1)

# 构建复杂的输出文件名
OUTPUT_BW="results/bigwig/${ScientificName}_${tissue}_${dev}_${treatment}_rep${REP_NUM}_${Run}.bw"

bamCoverage --bam results/aligned/${Run}_sorted.bam \
    --outFileName $OUTPUT_BW \
    --outFileFormat bigwig \
    --normalizeUsing RPKM \
    --ignoreDuplicates \
    --numberOfProcessors max \
    --verbose

echo "样本 ${Run} 处理完成。BigWig 文件: $OUTPUT_BW"
done < $TMP_LIST

echo "================================================="

echo "开始生成表达矩阵..."

echo "================================================="
awk -F'\t' 'NR>1 {count[$1]++} END {for (sp in count) if(count[sp] > 30) print sp}' sample.list | while read TARGET_SPECIES; do

echo "检测到物种 ${TARGET_SPECIES} 样本数超过 30，开始生成表达矩阵..."
# 收集该物种所有的 BAM 文件路径
BAM_LIST=""
while read -r sname anno Run Layout tissue treatment description; do
    if [ "$sname" == "$TARGET_SPECIES" ]; then
        BAM_LIST="${BAM_LIST} results/aligned/${Run}_sorted.bam"
    fi
done < $TMP_LIST

# 运行 FeatureCounts
featureCounts -T 60  \
    -a ${TARGET_SPECIES}.gff3 \
    -o results/matrix/${TARGET_SPECIES}_featureCounts.txt \
    -R BAM $BAM_LIST

# 整理表达矩阵格式 (可选：提取原始 counts 矩阵用于下游 DESeq2/edgeR)
# 注意：StringTie 的输出 GTF 可能不完全兼容 FeatureCounts 的标准 exon 结构，
# 如果使用 StringTie 的 GTF，建议直接使用 prepDE.py 脚本 (StringTie 自带) 生成 counts。
# 这里提供标准的 FeatureCounts 提取方法：
cut -f1,7- results/matrix/${TARGET_SPECIES}_featureCounts.txt | tail -n +3 > results/matrix/${TARGET_SPECIES}_counts_matrix.txt

echo "物种 ${TARGET_SPECIES} 表达矩阵生成完毕。"
done

rm $TMP_LIST

echo "全部分析流程结束！"
