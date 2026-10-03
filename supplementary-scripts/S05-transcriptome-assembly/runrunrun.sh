fastp -w 16 -i rawdata/SRR12963483.fastq.gz -o ./results/trimmed/SRR12963483_trimmed.fastq.gz
hisat2 -x ./results/db/Montipora_foliosa -U ./results/trimmed/SRR12963483_trimmed.fastq.gz -S ./results/aligned/SRR12963483.sam --dta --rna-strandness RF --threads 30 2> results/aligned/SRR12963483_hisat2.log
samtools view -@ 30 -bS ./results/aligned/SRR12963483.sam | samtools sort -@ 30 -o ./results/aligned/SRR12963483_sorted.bam
samtools index ./results/aligned/SRR12963483_sorted.bam
rm results/aligned/SRR12963483.sam
stringtie -p 30 -e -B -G Ref/Montipora_foliosa.gff3 -o ./results/stringtie_assembly/SRR12963483.gtf -l SRR12963483 ./results/aligned/SRR12963483_sorted.bam
bamCoverage --bam results/aligned/SRR12963483_sorted.bam --outFileName results/bw/Montipora_foliosa_SRR12963483_Polyps_E4_day0.bw --outFileFormat bigwig --normalizeUsing RPKM --ignoreDuplicates --numberOfProcessors max  --verbose

fastp -w 16 -i rawdata/SRR12963484.fastq.gz -o ./results/trimmed/SRR12963484_trimmed.fastq.gz
hisat2 -x ./results/db/Montipora_capricornis -U ./results/trimmed/SRR12963484_trimmed.fastq.gz -S ./results/aligned/SRR12963484.sam --dta --rna-strandness RF --threads 30 2> results/aligned/SRR12963484_hisat2.log
samtools view -@ 30 -bS ./results/aligned/SRR12963484.sam | samtools sort -@ 30 -o ./results/aligned/SRR12963484_sorted.bam
samtools index ./results/aligned/SRR12963484_sorted.bam
rm results/aligned/SRR12963484.sam
stringtie -p 30 -e -B -G Ref/Montipora_capricornis.gff3 -o ./results/stringtie_assembly/SRR12963484.gtf -l SRR12963484 ./results/aligned/SRR12963484_sorted.bam
bamCoverage --bam results/aligned/SRR12963484_sorted.bam --outFileName results/bw/Montipora_capricornis_SRR12963484_Polyps_E3_day0.bw --outFileFormat bigwig --normalizeUsing RPKM --ignoreDuplicates --numberOfProcessors max  --verbose
