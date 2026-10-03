bismark_genome_preparation --parallel 120 --bowtie2 Nematostella_vectensis/

bismark --bowtie2 -N 1 -L 20 --parallel 120  -o alignment --prefix 'SRR042634_' Nematostella_vectensis/ -1 SRR042634_R1_trimmed.fq.gz -2 SRR042634_R2_trimmed.fq.gz 2> SRR042634_bismark.log

deduplicate_bismark -bam -p alignment/SRR042634_.SRR042634_R1_trimmed_bismark_bt2_pe.bam --output_dir duplicate

# 甲基化提取
bismark_methylation_extractor --multicore 120 -p --gzip --no_overlap --comprehensive --bedGraph --count --CX_context --cytosine_report --buffer_size 20G --genome_folder Nematostella_vectensis/ -o report duplicate/SRR042634_.SRR042634_R1_trimmed_bismark_bt2_pe.deduplicated.bam
