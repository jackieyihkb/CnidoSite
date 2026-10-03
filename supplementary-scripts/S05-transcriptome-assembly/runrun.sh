fastp -w 16 -i rawdata/SRR19977425.fastq.gz -o ./results/trimmed/SRR19977425_trimmed.fastq.gz
hisat2 -x ./results/db/Paramuricea_clavata -U ./results/trimmed/SRR19977425_trimmed.fastq.gz -S ./results/aligned/SRR19977425.sam --dta --rna-strandness RF --threads 30 2> results/aligned/SRR19977425_hisat2.log
samtools view -@ 30 -bS ./results/aligned/SRR19977425.sam | samtools sort -@ 30 -o ./results/aligned/SRR19977425_sorted.bam
samtools index ./results/aligned/SRR19977425_sorted.bam
rm results/aligned/SRR19977425.sam
stringtie -p 30 -e -B -G Ref/Paramuricea_clavata.gff3 -o ./results/stringtie_assembly/SRR19977425.gtf -l SRR19977425 ./results/aligned/SRR19977425_sorted.bam
bamCoverage --bam results/aligned/SRR19977425_sorted.bam --outFileName results/bw/Paramuricea_clavata_SRR19977425_apical_branchlet_Temperature_treatment_at_T25.bw --outFileFormat bigwig --normalizeUsing RPKM --ignoreDuplicates --numberOfProcessors max  --verbose

fastp -w 16 -i rawdata/SRR19977426.fastq.gz -o ./results/trimmed/SRR19977426_trimmed.fastq.gz
hisat2 -x ./results/db/Paramuricea_clavata -U ./results/trimmed/SRR19977426_trimmed.fastq.gz -S ./results/aligned/SRR19977426.sam --dta --rna-strandness RF --threads 30 2> results/aligned/SRR19977426_hisat2.log
samtools view -@ 30 -bS ./results/aligned/SRR19977426.sam | samtools sort -@ 30 -o ./results/aligned/SRR19977426_sorted.bam
samtools index ./results/aligned/SRR19977426_sorted.bam
rm results/aligned/SRR19977426.sam
stringtie -p 30 -e -B -G Ref/Paramuricea_clavata.gff3 -o ./results/stringtie_assembly/SRR19977426.gtf -l SRR19977426 ./results/aligned/SRR19977426_sorted.bam
bamCoverage --bam results/aligned/SRR19977426_sorted.bam --outFileName results/bw/Paramuricea_clavata_SRR19977426_apical_branchlet_Temperature_treatment_at_T0.bw --outFileFormat bigwig --normalizeUsing RPKM --ignoreDuplicates --numberOfProcessors max  --verbose

fastp -w 16 -i rawdata/SRR19977427.fastq.gz -o ./results/trimmed/SRR19977427_trimmed.fastq.gz
hisat2 -x ./results/db/Paramuricea_clavata -U ./results/trimmed/SRR19977427_trimmed.fastq.gz -S ./results/aligned/SRR19977427.sam --dta --rna-strandness RF --threads 30 2> results/aligned/SRR19977427_hisat2.log
samtools view -@ 30 -bS ./results/aligned/SRR19977427.sam | samtools sort -@ 30 -o ./results/aligned/SRR19977427_sorted.bam
samtools index ./results/aligned/SRR19977427_sorted.bam
rm results/aligned/SRR19977427.sam
stringtie -p 30 -e -B -G Ref/Paramuricea_clavata.gff3 -o ./results/stringtie_assembly/SRR19977427.gtf -l SRR19977427 ./results/aligned/SRR19977427_sorted.bam
bamCoverage --bam results/aligned/SRR19977427_sorted.bam --outFileName results/bw/Paramuricea_clavata_SRR19977427_apical_branchlet_Temperature_treatment_at_T0.bw --outFileFormat bigwig --normalizeUsing RPKM --ignoreDuplicates --numberOfProcessors max  --verbose

fastp -w 16 -i rawdata/SRR19977428.fastq.gz -o ./results/trimmed/SRR19977428_trimmed.fastq.gz
hisat2 -x ./results/db/Paramuricea_clavata -U ./results/trimmed/SRR19977428_trimmed.fastq.gz -S ./results/aligned/SRR19977428.sam --dta --rna-strandness RF --threads 30 2> results/aligned/SRR19977428_hisat2.log
samtools view -@ 30 -bS ./results/aligned/SRR19977428.sam | samtools sort -@ 30 -o ./results/aligned/SRR19977428_sorted.bam
samtools index ./results/aligned/SRR19977428_sorted.bam
rm results/aligned/SRR19977428.sam
stringtie -p 30 -e -B -G Ref/Paramuricea_clavata.gff3 -o ./results/stringtie_assembly/SRR19977428.gtf -l SRR19977428 ./results/aligned/SRR19977428_sorted.bam
bamCoverage --bam results/aligned/SRR19977428_sorted.bam --outFileName results/bw/Paramuricea_clavata_SRR19977428_apical_branchlet_Temperature_treatment_at_T0.bw --outFileFormat bigwig --normalizeUsing RPKM --ignoreDuplicates --numberOfProcessors max  --verbose

fastp -w 16 -i rawdata/SRR19977432.fastq.gz -o ./results/trimmed/SRR19977432_trimmed.fastq.gz
hisat2 -x ./results/db/Paramuricea_clavata -U ./results/trimmed/SRR19977432_trimmed.fastq.gz -S ./results/aligned/SRR19977432.sam --dta --rna-strandness RF --threads 30 2> results/aligned/SRR19977432_hisat2.log
samtools view -@ 30 -bS ./results/aligned/SRR19977432.sam | samtools sort -@ 30 -o ./results/aligned/SRR19977432_sorted.bam
samtools index ./results/aligned/SRR19977432_sorted.bam
rm results/aligned/SRR19977432.sam
stringtie -p 30 -e -B -G Ref/Paramuricea_clavata.gff3 -o ./results/stringtie_assembly/SRR19977432.gtf -l SRR19977432 ./results/aligned/SRR19977432_sorted.bam
bamCoverage --bam results/aligned/SRR19977432_sorted.bam --outFileName results/bw/Paramuricea_clavata_SRR19977432_apical_branchlet_Temperature_treatment_at_T25.bw --outFileFormat bigwig --normalizeUsing RPKM --ignoreDuplicates --numberOfProcessors max  --verbose

fastp -w 16 -i rawdata/SRR19977433.fastq.gz -o ./results/trimmed/SRR19977433_trimmed.fastq.gz
hisat2 -x ./results/db/Paramuricea_clavata -U ./results/trimmed/SRR19977433_trimmed.fastq.gz -S ./results/aligned/SRR19977433.sam --dta --rna-strandness RF --threads 30 2> results/aligned/SRR19977433_hisat2.log
samtools view -@ 30 -bS ./results/aligned/SRR19977433.sam | samtools sort -@ 30 -o ./results/aligned/SRR19977433_sorted.bam
samtools index ./results/aligned/SRR19977433_sorted.bam
rm results/aligned/SRR19977433.sam
stringtie -p 30 -e -B -G Ref/Paramuricea_clavata.gff3 -o ./results/stringtie_assembly/SRR19977433.gtf -l SRR19977433 ./results/aligned/SRR19977433_sorted.bam
bamCoverage --bam results/aligned/SRR19977433_sorted.bam --outFileName results/bw/Paramuricea_clavata_SRR19977433_apical_branchlet_Control_at_T25.bw --outFileFormat bigwig --normalizeUsing RPKM --ignoreDuplicates --numberOfProcessors max  --verbose

fastp -w 16 -i rawdata/SRR19977434.fastq.gz -o ./results/trimmed/SRR19977434_trimmed.fastq.gz
hisat2 -x ./results/db/Paramuricea_clavata -U ./results/trimmed/SRR19977434_trimmed.fastq.gz -S ./results/aligned/SRR19977434.sam --dta --rna-strandness RF --threads 30 2> results/aligned/SRR19977434_hisat2.log
samtools view -@ 30 -bS ./results/aligned/SRR19977434.sam | samtools sort -@ 30 -o ./results/aligned/SRR19977434_sorted.bam
samtools index ./results/aligned/SRR19977434_sorted.bam
rm results/aligned/SRR19977434.sam
stringtie -p 30 -e -B -G Ref/Paramuricea_clavata.gff3 -o ./results/stringtie_assembly/SRR19977434.gtf -l SRR19977434 ./results/aligned/SRR19977434_sorted.bam
bamCoverage --bam results/aligned/SRR19977434_sorted.bam --outFileName results/bw/Paramuricea_clavata_SRR19977434_apical_branchlet_Temperature_treatment_at_T25.bw --outFileFormat bigwig --normalizeUsing RPKM --ignoreDuplicates --numberOfProcessors max  --verbose

fastp -w 16 -i rawdata/SRR19977435.fastq.gz -o ./results/trimmed/SRR19977435_trimmed.fastq.gz
hisat2 -x ./results/db/Paramuricea_clavata -U ./results/trimmed/SRR19977435_trimmed.fastq.gz -S ./results/aligned/SRR19977435.sam --dta --rna-strandness RF --threads 30 2> results/aligned/SRR19977435_hisat2.log
samtools view -@ 30 -bS ./results/aligned/SRR19977435.sam | samtools sort -@ 30 -o ./results/aligned/SRR19977435_sorted.bam
samtools index ./results/aligned/SRR19977435_sorted.bam
rm results/aligned/SRR19977435.sam
stringtie -p 30 -e -B -G Ref/Paramuricea_clavata.gff3 -o ./results/stringtie_assembly/SRR19977435.gtf -l SRR19977435 ./results/aligned/SRR19977435_sorted.bam
bamCoverage --bam results/aligned/SRR19977435_sorted.bam --outFileName results/bw/Paramuricea_clavata_SRR19977435_apical_branchlet_Temperature_treatment_at_T25.bw --outFileFormat bigwig --normalizeUsing RPKM --ignoreDuplicates --numberOfProcessors max  --verbose

fastp -w 16 -i rawdata/SRR19977436.fastq.gz -o ./results/trimmed/SRR19977436_trimmed.fastq.gz
hisat2 -x ./results/db/Paramuricea_clavata -U ./results/trimmed/SRR19977436_trimmed.fastq.gz -S ./results/aligned/SRR19977436.sam --dta --rna-strandness RF --threads 30 2> results/aligned/SRR19977436_hisat2.log
samtools view -@ 30 -bS ./results/aligned/SRR19977436.sam | samtools sort -@ 30 -o ./results/aligned/SRR19977436_sorted.bam
samtools index ./results/aligned/SRR19977436_sorted.bam
rm results/aligned/SRR19977436.sam
stringtie -p 30 -e -B -G Ref/Paramuricea_clavata.gff3 -o ./results/stringtie_assembly/SRR19977436.gtf -l SRR19977436 ./results/aligned/SRR19977436_sorted.bam
bamCoverage --bam results/aligned/SRR19977436_sorted.bam --outFileName results/bw/Paramuricea_clavata_SRR19977436_apical_branchlet_Temperature_treatment_at_T0.bw --outFileFormat bigwig --normalizeUsing RPKM --ignoreDuplicates --numberOfProcessors max  --verbose

fastp -w 16 -i rawdata/SRR19977437.fastq.gz -o ./results/trimmed/SRR19977437_trimmed.fastq.gz
hisat2 -x ./results/db/Paramuricea_clavata -U ./results/trimmed/SRR19977437_trimmed.fastq.gz -S ./results/aligned/SRR19977437.sam --dta --rna-strandness RF --threads 30 2> results/aligned/SRR19977437_hisat2.log
samtools view -@ 30 -bS ./results/aligned/SRR19977437.sam | samtools sort -@ 30 -o ./results/aligned/SRR19977437_sorted.bam
samtools index ./results/aligned/SRR19977437_sorted.bam
rm results/aligned/SRR19977437.sam
stringtie -p 30 -e -B -G Ref/Paramuricea_clavata.gff3 -o ./results/stringtie_assembly/SRR19977437.gtf -l SRR19977437 ./results/aligned/SRR19977437_sorted.bam
bamCoverage --bam results/aligned/SRR19977437_sorted.bam --outFileName results/bw/Paramuricea_clavata_SRR19977437_apical_branchlet_Temperature_treatment_at_T0.bw --outFileFormat bigwig --normalizeUsing RPKM --ignoreDuplicates --numberOfProcessors max  --verbose

fastp -w 16 -i rawdata/SRR19977438.fastq.gz -o ./results/trimmed/SRR19977438_trimmed.fastq.gz
hisat2 -x ./results/db/Paramuricea_clavata -U ./results/trimmed/SRR19977438_trimmed.fastq.gz -S ./results/aligned/SRR19977438.sam --dta --rna-strandness RF --threads 30 2> results/aligned/SRR19977438_hisat2.log
samtools view -@ 30 -bS ./results/aligned/SRR19977438.sam | samtools sort -@ 30 -o ./results/aligned/SRR19977438_sorted.bam
samtools index ./results/aligned/SRR19977438_sorted.bam
rm results/aligned/SRR19977438.sam
stringtie -p 30 -e -B -G Ref/Paramuricea_clavata.gff3 -o ./results/stringtie_assembly/SRR19977438.gtf -l SRR19977438 ./results/aligned/SRR19977438_sorted.bam
bamCoverage --bam results/aligned/SRR19977438_sorted.bam --outFileName results/bw/Paramuricea_clavata_SRR19977438_apical_branchlet_Temperature_treatment_at_T0.bw --outFileFormat bigwig --normalizeUsing RPKM --ignoreDuplicates --numberOfProcessors max  --verbose

fastp -w 16 -i rawdata/SRR19977439.fastq.gz -o ./results/trimmed/SRR19977439_trimmed.fastq.gz
hisat2 -x ./results/db/Paramuricea_clavata -U ./results/trimmed/SRR19977439_trimmed.fastq.gz -S ./results/aligned/SRR19977439.sam --dta --rna-strandness RF --threads 30 2> results/aligned/SRR19977439_hisat2.log
samtools view -@ 30 -bS ./results/aligned/SRR19977439.sam | samtools sort -@ 30 -o ./results/aligned/SRR19977439_sorted.bam
samtools index ./results/aligned/SRR19977439_sorted.bam
rm results/aligned/SRR19977439.sam
stringtie -p 30 -e -B -G Ref/Paramuricea_clavata.gff3 -o ./results/stringtie_assembly/SRR19977439.gtf -l SRR19977439 ./results/aligned/SRR19977439_sorted.bam
bamCoverage --bam results/aligned/SRR19977439_sorted.bam --outFileName results/bw/Paramuricea_clavata_SRR19977439_apical_branchlet_Control_at_T25.bw --outFileFormat bigwig --normalizeUsing RPKM --ignoreDuplicates --numberOfProcessors max  --verbose

fastp -w 16 -i rawdata/SRR19977440.fastq.gz -o ./results/trimmed/SRR19977440_trimmed.fastq.gz
hisat2 -x ./results/db/Paramuricea_clavata -U ./results/trimmed/SRR19977440_trimmed.fastq.gz -S ./results/aligned/SRR19977440.sam --dta --rna-strandness RF --threads 30 2> results/aligned/SRR19977440_hisat2.log
samtools view -@ 30 -bS ./results/aligned/SRR19977440.sam | samtools sort -@ 30 -o ./results/aligned/SRR19977440_sorted.bam
samtools index ./results/aligned/SRR19977440_sorted.bam
rm results/aligned/SRR19977440.sam
stringtie -p 30 -e -B -G Ref/Paramuricea_clavata.gff3 -o ./results/stringtie_assembly/SRR19977440.gtf -l SRR19977440 ./results/aligned/SRR19977440_sorted.bam
bamCoverage --bam results/aligned/SRR19977440_sorted.bam --outFileName results/bw/Paramuricea_clavata_SRR19977440_apical_branchlet_Control_at_T25.bw --outFileFormat bigwig --normalizeUsing RPKM --ignoreDuplicates --numberOfProcessors max  --verbose

fastp -w 16 -i rawdata/SRR19977441.fastq.gz -o ./results/trimmed/SRR19977441_trimmed.fastq.gz
hisat2 -x ./results/db/Paramuricea_clavata -U ./results/trimmed/SRR19977441_trimmed.fastq.gz -S ./results/aligned/SRR19977441.sam --dta --rna-strandness RF --threads 30 2> results/aligned/SRR19977441_hisat2.log
samtools view -@ 30 -bS ./results/aligned/SRR19977441.sam | samtools sort -@ 30 -o ./results/aligned/SRR19977441_sorted.bam
samtools index ./results/aligned/SRR19977441_sorted.bam
rm results/aligned/SRR19977441.sam
stringtie -p 30 -e -B -G Ref/Paramuricea_clavata.gff3 -o ./results/stringtie_assembly/SRR19977441.gtf -l SRR19977441 ./results/aligned/SRR19977441_sorted.bam
bamCoverage --bam results/aligned/SRR19977441_sorted.bam --outFileName results/bw/Paramuricea_clavata_SRR19977441_apical_branchlet_Control_at_T25.bw --outFileFormat bigwig --normalizeUsing RPKM --ignoreDuplicates --numberOfProcessors max  --verbose

fastp -w 16 -i rawdata/SRR19977442.fastq.gz -o ./results/trimmed/SRR19977442_trimmed.fastq.gz
hisat2 -x ./results/db/Paramuricea_clavata -U ./results/trimmed/SRR19977442_trimmed.fastq.gz -S ./results/aligned/SRR19977442.sam --dta --rna-strandness RF --threads 30 2> results/aligned/SRR19977442_hisat2.log
samtools view -@ 30 -bS ./results/aligned/SRR19977442.sam | samtools sort -@ 30 -o ./results/aligned/SRR19977442_sorted.bam
samtools index ./results/aligned/SRR19977442_sorted.bam
rm results/aligned/SRR19977442.sam
stringtie -p 30 -e -B -G Ref/Paramuricea_clavata.gff3 -o ./results/stringtie_assembly/SRR19977442.gtf -l SRR19977442 ./results/aligned/SRR19977442_sorted.bam
bamCoverage --bam results/aligned/SRR19977442_sorted.bam --outFileName results/bw/Paramuricea_clavata_SRR19977442_apical_branchlet_Control_at_T0.bw --outFileFormat bigwig --normalizeUsing RPKM --ignoreDuplicates --numberOfProcessors max  --verbose

fastp -w 16 -i rawdata/SRR19977443.fastq.gz -o ./results/trimmed/SRR19977443_trimmed.fastq.gz
hisat2 -x ./results/db/Paramuricea_clavata -U ./results/trimmed/SRR19977443_trimmed.fastq.gz -S ./results/aligned/SRR19977443.sam --dta --rna-strandness RF --threads 30 2> results/aligned/SRR19977443_hisat2.log
samtools view -@ 30 -bS ./results/aligned/SRR19977443.sam | samtools sort -@ 30 -o ./results/aligned/SRR19977443_sorted.bam
samtools index ./results/aligned/SRR19977443_sorted.bam
rm results/aligned/SRR19977443.sam
stringtie -p 30 -e -B -G Ref/Paramuricea_clavata.gff3 -o ./results/stringtie_assembly/SRR19977443.gtf -l SRR19977443 ./results/aligned/SRR19977443_sorted.bam
bamCoverage --bam results/aligned/SRR19977443_sorted.bam --outFileName results/bw/Paramuricea_clavata_SRR19977443_apical_branchlet_Control_at_T0.bw --outFileFormat bigwig --normalizeUsing RPKM --ignoreDuplicates --numberOfProcessors max  --verbose

fastp -w 16 -i rawdata/SRR19977444.fastq.gz -o ./results/trimmed/SRR19977444_trimmed.fastq.gz
hisat2 -x ./results/db/Paramuricea_clavata -U ./results/trimmed/SRR19977444_trimmed.fastq.gz -S ./results/aligned/SRR19977444.sam --dta --rna-strandness RF --threads 30 2> results/aligned/SRR19977444_hisat2.log
samtools view -@ 30 -bS ./results/aligned/SRR19977444.sam | samtools sort -@ 30 -o ./results/aligned/SRR19977444_sorted.bam
samtools index ./results/aligned/SRR19977444_sorted.bam
rm results/aligned/SRR19977444.sam
stringtie -p 30 -e -B -G Ref/Paramuricea_clavata.gff3 -o ./results/stringtie_assembly/SRR19977444.gtf -l SRR19977444 ./results/aligned/SRR19977444_sorted.bam
bamCoverage --bam results/aligned/SRR19977444_sorted.bam --outFileName results/bw/Paramuricea_clavata_SRR19977444_apical_branchlet_Control_at_T25.bw --outFileFormat bigwig --normalizeUsing RPKM --ignoreDuplicates --numberOfProcessors max  --verbose

fastp -w 16 -i rawdata/SRR19977445.fastq.gz -o ./results/trimmed/SRR19977445_trimmed.fastq.gz
hisat2 -x ./results/db/Paramuricea_clavata -U ./results/trimmed/SRR19977445_trimmed.fastq.gz -S ./results/aligned/SRR19977445.sam --dta --rna-strandness RF --threads 30 2> results/aligned/SRR19977445_hisat2.log
samtools view -@ 30 -bS ./results/aligned/SRR19977445.sam | samtools sort -@ 30 -o ./results/aligned/SRR19977445_sorted.bam
samtools index ./results/aligned/SRR19977445_sorted.bam
rm results/aligned/SRR19977445.sam
stringtie -p 30 -e -B -G Ref/Paramuricea_clavata.gff3 -o ./results/stringtie_assembly/SRR19977445.gtf -l SRR19977445 ./results/aligned/SRR19977445_sorted.bam
bamCoverage --bam results/aligned/SRR19977445_sorted.bam --outFileName results/bw/Paramuricea_clavata_SRR19977445_apical_branchlet_Control_at_T0.bw --outFileFormat bigwig --normalizeUsing RPKM --ignoreDuplicates --numberOfProcessors max  --verbose

fastp -w 16 -i rawdata/SRR19977446.fastq.gz -o ./results/trimmed/SRR19977446_trimmed.fastq.gz
hisat2 -x ./results/db/Paramuricea_clavata -U ./results/trimmed/SRR19977446_trimmed.fastq.gz -S ./results/aligned/SRR19977446.sam --dta --rna-strandness RF --threads 30 2> results/aligned/SRR19977446_hisat2.log
samtools view -@ 30 -bS ./results/aligned/SRR19977446.sam | samtools sort -@ 30 -o ./results/aligned/SRR19977446_sorted.bam
samtools index ./results/aligned/SRR19977446_sorted.bam
rm results/aligned/SRR19977446.sam
stringtie -p 30 -e -B -G Ref/Paramuricea_clavata.gff3 -o ./results/stringtie_assembly/SRR19977446.gtf -l SRR19977446 ./results/aligned/SRR19977446_sorted.bam
bamCoverage --bam results/aligned/SRR19977446_sorted.bam --outFileName results/bw/Paramuricea_clavata_SRR19977446_apical_branchlet_Control_at_T0.bw --outFileFormat bigwig --normalizeUsing RPKM --ignoreDuplicates --numberOfProcessors max  --verbose

fastp -w 16 -i rawdata/SRR19977455.fastq.gz -o ./results/trimmed/SRR19977455_trimmed.fastq.gz
hisat2 -x ./results/db/Paramuricea_clavata -U ./results/trimmed/SRR19977455_trimmed.fastq.gz -S ./results/aligned/SRR19977455.sam --dta --rna-strandness RF --threads 30 2> results/aligned/SRR19977455_hisat2.log
samtools view -@ 30 -bS ./results/aligned/SRR19977455.sam | samtools sort -@ 30 -o ./results/aligned/SRR19977455_sorted.bam
samtools index ./results/aligned/SRR19977455_sorted.bam
rm results/aligned/SRR19977455.sam
stringtie -p 30 -e -B -G Ref/Paramuricea_clavata.gff3 -o ./results/stringtie_assembly/SRR19977455.gtf -l SRR19977455 ./results/aligned/SRR19977455_sorted.bam
bamCoverage --bam results/aligned/SRR19977455_sorted.bam --outFileName results/bw/Paramuricea_clavata_SRR19977455_apical_branchlet_Control_at_T25.bw --outFileFormat bigwig --normalizeUsing RPKM --ignoreDuplicates --numberOfProcessors max  --verbose

fastp -w 16 -i rawdata/SRR19977463.fastq.gz -o ./results/trimmed/SRR19977463_trimmed.fastq.gz
hisat2 -x ./results/db/Paramuricea_clavata -U ./results/trimmed/SRR19977463_trimmed.fastq.gz -S ./results/aligned/SRR19977463.sam --dta --rna-strandness RF --threads 30 2> results/aligned/SRR19977463_hisat2.log
samtools view -@ 30 -bS ./results/aligned/SRR19977463.sam | samtools sort -@ 30 -o ./results/aligned/SRR19977463_sorted.bam
samtools index ./results/aligned/SRR19977463_sorted.bam
rm results/aligned/SRR19977463.sam
stringtie -p 30 -e -B -G Ref/Paramuricea_clavata.gff3 -o ./results/stringtie_assembly/SRR19977463.gtf -l SRR19977463 ./results/aligned/SRR19977463_sorted.bam
bamCoverage --bam results/aligned/SRR19977463_sorted.bam --outFileName results/bw/Paramuricea_clavata_SRR19977463_apical_branchlet_Temperature_treatment_at_T25.bw --outFileFormat bigwig --normalizeUsing RPKM --ignoreDuplicates --numberOfProcessors max  --verbose
