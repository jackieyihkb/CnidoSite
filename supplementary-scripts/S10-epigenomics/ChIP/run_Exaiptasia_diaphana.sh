bowtie2 -x bowtie2_Exaiptasia_diaphana -1 SRR6202311_1.fastq.gz -2 SRR6202311_2.fastq.gz -S SRR6202311.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR6202311_align.log
samtools view -@ 120 -bS SRR6202311.sam | samtools sort -o SRR6202311_sorted.bam
samtools index SRR6202311_sorted.bam
rm SRR6202311.sam
bowtie2 -x bowtie2_Exaiptasia_diaphana -1 SRR6202312_1.fastq.gz -2 SRR6202312_2.fastq.gz -S SRR6202312.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR6202312_align.log
samtools view -@ 120 -bS SRR6202312.sam | samtools sort -o SRR6202312_sorted.bam
samtools index SRR6202312_sorted.bam
rm SRR6202312.sam
bowtie2 -x bowtie2_Exaiptasia_diaphana -1 SRR6202323_1.fastq.gz -2 SRR6202323_2.fastq.gz -S SRR6202323.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR6202323_align.log
samtools view -@ 120 -bS SRR6202323.sam | samtools sort -o SRR6202323_sorted.bam
samtools index SRR6202323_sorted.bam
rm SRR6202323.sam
bowtie2 -x bowtie2_Exaiptasia_diaphana -1 SRR6202324_1.fastq.gz -2 SRR6202324_2.fastq.gz -S SRR6202324.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR6202324_align.log
samtools view -@ 120 -bS SRR6202324.sam | samtools sort -o SRR6202324_sorted.bam
samtools index SRR6202324_sorted.bam
rm SRR6202324.sam
bowtie2 -x bowtie2_Exaiptasia_diaphana -1 SRR6202325_1.fastq.gz -2 SRR6202325_2.fastq.gz -S SRR6202325.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR6202325_align.log
samtools view -@ 120 -bS SRR6202325.sam | samtools sort -o SRR6202325_sorted.bam
samtools index SRR6202325_sorted.bam
rm SRR6202325.sam
bowtie2 -x bowtie2_Exaiptasia_diaphana -1 SRR6202326_1.fastq.gz -2 SRR6202326_2.fastq.gz -S SRR6202326.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR6202326_align.log
samtools view -@ 120 -bS SRR6202326.sam | samtools sort -o SRR6202326_sorted.bam
samtools index SRR6202326_sorted.bam
rm SRR6202326.sam
macs2 callpeak -t SRR6202323_sorted.bam SRR6202324_sorted.bam SRR6202325_sorted.bam -c SRR6202326_sorted.bam SRR6202311_sorted.bam SRR6202312_sorted.bam -n peaks/Exaiptasia_diaphana_H3K36me3_wholeAnimal_consensus -f BAM -g 2.61e8 2> logs/Exaiptasia_diaphana_H3K36me3_wholeAnimal_macs2.log

bowtie2 -x bowtie2_Exaiptasia_diaphana -1 SRR18749530_1.fastq.gz -2 SRR18749530_2.fastq.gz -S SRR18749530.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR18749530_align.log
samtools view -@ 120 -bS SRR18749530.sam | samtools sort -o SRR18749530_sorted.bam
samtools index SRR18749530_sorted.bam
rm SRR18749530.sam
bowtie2 -x bowtie2_Exaiptasia_diaphana -1 SRR18749531_1.fastq.gz -2 SRR18749531_2.fastq.gz -S SRR18749531.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR18749531_align.log
samtools view -@ 120 -bS SRR18749531.sam | samtools sort -o SRR18749531_sorted.bam
samtools index SRR18749531_sorted.bam
rm SRR18749531.sam
bowtie2 -x bowtie2_Exaiptasia_diaphana -1 SRR18749532_1.fastq.gz -2 SRR18749532_2.fastq.gz -S SRR18749532.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR18749532_align.log
samtools view -@ 120 -bS SRR18749532.sam | samtools sort -o SRR18749532_sorted.bam
samtools index SRR18749532_sorted.bam
rm SRR18749532.sam
bowtie2 -x bowtie2_Exaiptasia_diaphana -1 SRR18749533_1.fastq.gz -2 SRR18749533_2.fastq.gz -S SRR18749533.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR18749533_align.log
samtools view -@ 120 -bS SRR18749533.sam | samtools sort -o SRR18749533_sorted.bam
samtools index SRR18749533_sorted.bam
rm SRR18749533.sam
bowtie2 -x bowtie2_Exaiptasia_diaphana -1 SRR18749534_1.fastq.gz -2 SRR18749534_2.fastq.gz -S SRR18749534.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR18749534_align.log
samtools view -@ 120 -bS SRR18749534.sam | samtools sort -o SRR18749534_sorted.bam
samtools index SRR18749534_sorted.bam
rm SRR18749534.sam
bowtie2 -x bowtie2_Exaiptasia_diaphana -1 SRR18749535_1.fastq.gz -2 SRR18749535_2.fastq.gz -S SRR18749535.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR18749535_align.log
samtools view -@ 120 -bS SRR18749535.sam | samtools sort -o SRR18749535_sorted.bam
samtools index SRR18749535_sorted.bam
rm SRR18749535.sam
bowtie2 -x bowtie2_Exaiptasia_diaphana -1 SRR18749536_1.fastq.gz -2 SRR18749536_2.fastq.gz -S SRR18749536.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR18749536_align.log
samtools view -@ 120 -bS SRR18749536.sam | samtools sort -o SRR18749536_sorted.bam
samtools index SRR18749536_sorted.bam
rm SRR18749536.sam
bowtie2 -x bowtie2_Exaiptasia_diaphana -U SRR18749537.fastq.gz -S SRR18749537.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR18749537_align.log
samtools view -@ 120 -bS SRR18749537.sam | samtools sort -o SRR18749537_sorted.bam
samtools index SRR18749537_sorted.bam
rm SRR18749537.sam
bowtie2 -x bowtie2_Exaiptasia_diaphana -U SRR18749538.fastq.gz -S SRR18749538.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR18749538_align.log
samtools view -@ 120 -bS SRR18749538.sam | samtools sort -o SRR18749538_sorted.bam
samtools index SRR18749538_sorted.bam
rm SRR18749538.sam
bowtie2 -x bowtie2_Exaiptasia_diaphana -U SRR18749539.fastq.gz -S SRR18749539.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR18749539_align.log
samtools view -@ 120 -bS SRR18749539.sam | samtools sort -o SRR18749539_sorted.bam
samtools index SRR18749539_sorted.bam
rm SRR18749539.sam
bowtie2 -x bowtie2_Exaiptasia_diaphana -U SRR18749540.fastq.gz -S SRR18749540.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR18749540_align.log
samtools view -@ 120 -bS SRR18749540.sam | samtools sort -o SRR18749540_sorted.bam
samtools index SRR18749540_sorted.bam
rm SRR18749540.sam
bowtie2 -x bowtie2_Exaiptasia_diaphana -1 SRR18749541_1.fastq.gz -2 SRR18749541_2.fastq.gz -S SRR18749541.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR18749541_align.log
samtools view -@ 120 -bS SRR18749541.sam | samtools sort -o SRR18749541_sorted.bam
samtools index SRR18749541_sorted.bam
rm SRR18749541.sam
bowtie2 -x bowtie2_Exaiptasia_diaphana -U SRR18749542.fastq.gz -S SRR18749542.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR18749542_align.log
samtools view -@ 120 -bS SRR18749542.sam | samtools sort -o SRR18749542_sorted.bam
samtools index SRR18749542_sorted.bam
rm SRR18749542.sam
bowtie2 -x bowtie2_Exaiptasia_diaphana -U SRR18749543.fastq.gz -S SRR18749543.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR18749543_align.log
samtools view -@ 120 -bS SRR18749543.sam | samtools sort -o SRR18749543_sorted.bam
samtools index SRR18749543_sorted.bam
rm SRR18749543.sam
bowtie2 -x bowtie2_Exaiptasia_diaphana -1 SRR18749544_1.fastq.gz -2 SRR18749544_2.fastq.gz -S SRR18749544.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR18749544_align.log
samtools view -@ 120 -bS SRR18749544.sam | samtools sort -o SRR18749544_sorted.bam
samtools index SRR18749544_sorted.bam
rm SRR18749544.sam
bowtie2 -x bowtie2_Exaiptasia_diaphana -1 SRR18749545_1.fastq.gz -2 SRR18749545_2.fastq.gz -S SRR18749545.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR18749545_align.log
samtools view -@ 120 -bS SRR18749545.sam | samtools sort -o SRR18749545_sorted.bam
samtools index SRR18749545_sorted.bam
rm SRR18749545.sam
bowtie2 -x bowtie2_Exaiptasia_diaphana -1 SRR18749546_1.fastq.gz -2 SRR18749546_2.fastq.gz -S SRR18749546.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR18749546_align.log
samtools view -@ 120 -bS SRR18749546.sam | samtools sort -o SRR18749546_sorted.bam
samtools index SRR18749546_sorted.bam
rm SRR18749546.sam
bowtie2 -x bowtie2_Exaiptasia_diaphana -1 SRR18749547_1.fastq.gz -2 SRR18749547_2.fastq.gz -S SRR18749547.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR18749547_align.log
samtools view -@ 120 -bS SRR18749547.sam | samtools sort -o SRR18749547_sorted.bam
samtools index SRR18749547_sorted.bam
rm SRR18749547.sam
bowtie2 -x bowtie2_Exaiptasia_diaphana -1 SRR18749548_1.fastq.gz -2 SRR18749548_2.fastq.gz -S SRR18749548.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR18749548_align.log
samtools view -@ 120 -bS SRR18749548.sam | samtools sort -o SRR18749548_sorted.bam
samtools index SRR18749548_sorted.bam
rm SRR18749548.sam
bowtie2 -x bowtie2_Exaiptasia_diaphana -1 SRR18749549_1.fastq.gz -2 SRR18749549_2.fastq.gz -S SRR18749549.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR18749549_align.log
samtools view -@ 120 -bS SRR18749549.sam | samtools sort -o SRR18749549_sorted.bam
samtools index SRR18749549_sorted.bam
rm SRR18749549.sam
bowtie2 -x bowtie2_Exaiptasia_diaphana -1 SRR18749550_1.fastq.gz -2 SRR18749550_2.fastq.gz -S SRR18749550.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR18749550_align.log
samtools view -@ 120 -bS SRR18749550.sam | samtools sort -o SRR18749550_sorted.bam
samtools index SRR18749550_sorted.bam
rm SRR18749550.sam
bowtie2 -x bowtie2_Exaiptasia_diaphana -1 SRR18749551_1.fastq.gz -2 SRR18749551_2.fastq.gz -S SRR18749551.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR18749551_align.log
samtools view -@ 120 -bS SRR18749551.sam | samtools sort -o SRR18749551_sorted.bam
samtools index SRR18749551_sorted.bam
rm SRR18749551.sam
bowtie2 -x bowtie2_Exaiptasia_diaphana -1 SRR18749552_1.fastq.gz -2 SRR18749552_2.fastq.gz -S SRR18749552.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR18749552_align.log
samtools view -@ 120 -bS SRR18749552.sam | samtools sort -o SRR18749552_sorted.bam
samtools index SRR18749552_sorted.bam
rm SRR18749552.sam
bowtie2 -x bowtie2_Exaiptasia_diaphana -1 SRR18749553_1.fastq.gz -2 SRR18749553_2.fastq.gz -S SRR18749553.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR18749553_align.log
samtools view -@ 120 -bS SRR18749553.sam | samtools sort -o SRR18749553_sorted.bam
samtools index SRR18749553_sorted.bam
rm SRR18749553.sam
macs2 callpeak -t SRR18749544_sorted.bam SRR18749545_sorted.bam SRR18749546_sorted.bam -c SRR18749534_sorted.bam SRR18749535_sorted.bam SRR18749536_sorted.bam -n peaks/Exaiptasia_diaphana_H3K27ac_consensus -f BAM -g 2.61e8 2> logs/Exaiptasia_diaphana_H3K27ac_macs2.log
macs2 callpeak -t SRR18749541_sorted.bam SRR18749552_sorted.bam SRR18749553_sorted.bam -c SRR18749534_sorted.bam SRR18749535_sorted.bam SRR18749536_sorted.bam -n peaks/Exaiptasia_diaphana_H3K27me3_consensus -f BAM -g 2.61e8 2> logs/Exaiptasia_diaphana_H3K27me3_macs2.log
macs2 callpeak -t SRR18749547_sorted.bam SRR18749548_sorted.bam SRR18749549_sorted.bam -c SRR18749534_sorted.bam SRR18749535_sorted.bam SRR18749536_sorted.bam -n peaks/Exaiptasia_diaphana_H3K4me3_consensus -f BAM -g 2.61e8 2> logs/Exaiptasia_diaphana_H3K4me3_macs2.log
macs2 callpeak -t SRR18749531_sorted.bam SRR18749532_sorted.bam SRR18749533_sorted.bam -c SRR18749530_sorted.bam SRR18749550_sorted.bam SRR18749551_sorted.bam -n peaks/Exaiptasia_diaphana_H3K36me3_consensus -f BAM -g 2.61e8 2> logs/Exaiptasia_diaphana_H3K36me3_macs2.log
macs2 callpeak -t SRR18749540_sorted.bam SRR18749542_sorted.bam SRR18749543_sorted.bam -c SRR18749537_sorted.bam SRR18749538_sorted.bam SRR18749539_sorted.bam -n peaks/Exaiptasia_diaphana_H3K9ac_consensus -f BAM -g 2.61e8 2> logs/Exaiptasia_diaphana_H3K9ac_macs2.log

bowtie2 -x bowtie2_Exaiptasia_diaphana -1 SRR23343224_1.fastq.gz -2 SRR23343224_2.fastq.gz -S SRR23343224.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR23343224_align.log
samtools view -@ 120 -bS SRR23343224.sam | samtools sort -o SRR23343224_sorted.bam
samtools index SRR23343224_sorted.bam
rm SRR23343224.sam
bowtie2 -x bowtie2_Exaiptasia_diaphana -1 SRR23343225_1.fastq.gz -2 SRR23343225_2.fastq.gz -S SRR23343225.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR23343225_align.log
samtools view -@ 120 -bS SRR23343225.sam | samtools sort -o SRR23343225_sorted.bam
samtools index SRR23343225_sorted.bam
rm SRR23343225.sam
macs2 callpeak -t SRR23343225_sorted.bam -c SRR23343224_sorted.bam -n peaks/Exaiptasia_diaphana_H3K4me3_consensus -f BAM -g 2.61e8 2> logs/Exaiptasia_diaphana_H3K4me3_WholeAnimal_macs2.log
