#bowtie2-build --threads 120 Acropora_digitifera.fa bowtie2_Acropora_digitifera
bowtie2 -x bowtie2_Acropora_digitifera -1 DRR608907_1.fastq.gz -2 DRR608907_2.fastq.gz -S DRR608907.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> DRR608907_align.log
samtools view -@ 120 -bS DRR608907.sam | samtools sort -o DRR608907_sorted.bam
samtools index DRR608907_sorted.bam
rm DRR608907.sam

macs2 callpeak -t DRR608907_sorted.bam  -n peaks/Acropora_digitifera_DHS_consensus -f BAM -g 4.38e8 2> Acropora_digitifera_DHS_macs2.log
