bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR30660605_1.fastq.gz -2 SRR30660605_2.fastq.gz -S SRR30660605.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR30660605_align.log
samtools view -@ 120 -bS SRR30660605.sam | samtools sort -o SRR30660605_sorted.bam
 samtools index SRR30660605_sorted.bam
rm SRR30660605.sam
bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR30660606_1.fastq.gz -2 SRR30660606_2.fastq.gz -S SRR30660606.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR30660606_align.log
samtools view -@ 120 -bS SRR30660606.sam | samtools sort -o SRR30660606_sorted.bam
samtools index SRR30660606_sorted.bam
rm SRR30660606.sam
bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR30660607_1.fastq.gz -2 SRR30660607_2.fastq.gz -S SRR30660607.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR30660607_align.log
samtools view -@ 120 -bS SRR30660607.sam | samtools sort -o SRR30660607_sorted.bam
 samtools index SRR30660607_sorted.bam
rm SRR30660607.sam
bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR30660608_1.fastq.gz -2 SRR30660608_2.fastq.gz -S SRR30660608.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR30660608_align.log
samtools view -@ 120 -bS SRR30660608.sam | samtools sort -o SRR30660608_sorted.bam
 samtools index SRR30660608_sorted.bam
rm SRR30660608.sam
bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR30660609_1.fastq.gz -2 SRR30660609_2.fastq.gz -S SRR30660609.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR30660609_align.log
samtools view -@ 120 -bS SRR30660609.sam | samtools sort -o SRR30660609_sorted.bam
 samtools index SRR30660609_sorted.bam
rm SRR30660609.sam
bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR30660610_1.fastq.gz -2 SRR30660610_2.fastq.gz -S SRR30660610.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR30660610_align.log
samtools view -@ 120 -bS SRR30660610.sam | samtools sort -o SRR30660610_sorted.bam
 samtools index SRR30660610_sorted.bam
rm SRR30660610.sam
bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR30660611_1.fastq.gz -2 SRR30660611_2.fastq.gz -S SRR30660611.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR30660611_align.log
samtools view -@ 120 -bS SRR30660611.sam | samtools sort -o SRR30660611_sorted.bam
 samtools index SRR30660611_sorted.bam
rm SRR30660611.sam
bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR30660612_1.fastq.gz -2 SRR30660612_2.fastq.gz -S SRR30660612.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR30660612_align.log
samtools view -@ 120 -bS SRR30660612.sam | samtools sort -o SRR30660612_sorted.bam
 samtools index SRR30660612_sorted.bam
rm SRR30660612.sam
macs2 callpeak -t SRR30660605_sorted.bam -c SRR30660608_sorted.bam SRR30660609_sorted.bam -n peaks/Nematostella_vectensis_H3K4me3_neuronal_ELAV_cells_consensus -f BAM -g 2.61e8 2> logs/Nematostella_vectensis_H3K4me3_neuronal_ELAV_cells_macs2.log
macs2 callpeak -t SRR30660606_sorted.bam SRR30660606_sorted.bam -c SRR30660608_sorted.bam SRR30660609_sorted.bam -n peaks/Nematostella_vectensis_H3K4me2_neuronal_ELAV_cells_consensus -f BAM -g 2.61e8 2> logs/Nematostella_vectensis_H3K4me2_neuronal_ELAV_cells_macs2.log
macs2 callpeak -t SRR30660610_sorted.bam SRR30660611_sorted.bam SRR30660612_sorted.bam -c SRR30660608_sorted.bam SRR30660609_sorted.bam -n peaks/Nematostella_vectensis_H3K4me1_neuronal_ELAV_cells_consensus -f BAM -g 2.61e8 2> logs/Nematostella_vectensis_H3K4me1_neuronal_ELAV_cells_macs2.log
bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR35569752_1.fastq.gz -2 SRR35569752_2.fastq.gz -S SRR35569752.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR35569752_align.log
samtools view -@ 120 -bS SRR35569752.sam | samtools sort -o SRR35569752_sorted.bam
 samtools index SRR35569752_sorted.bam
rm SRR35569752.sam
bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR35569753_1.fastq.gz -2 SRR35569753_2.fastq.gz -S SRR35569753.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR35569753_align.log
samtools view -@ 120 -bS SRR35569753.sam | samtools sort -o SRR35569753_sorted.bam
 samtools index SRR35569753_sorted.bam
rm SRR35569753.sam
bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR35569754_1.fastq.gz -2 SRR35569754_2.fastq.gz -S SRR35569754.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR35569754_align.log
samtools view -@ 120 -bS SRR35569754.sam | samtools sort -o SRR35569754_sorted.bam
 samtools index SRR35569754_sorted.bam
rm SRR35569754.sam
bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR35569755_1.fastq.gz -2 SRR35569755_2.fastq.gz -S SRR35569755.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR35569755_align.log
samtools view -@ 120 -bS SRR35569755.sam | samtools sort -o SRR35569755_sorted.bam
 samtools index SRR35569755_sorted.bam
rm SRR35569755.sam
bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR35569756_1.fastq.gz -2 SRR35569756_2.fastq.gz -S SRR35569756.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR35569756_align.log
samtools view -@ 120 -bS SRR35569756.sam | samtools sort -o SRR35569756_sorted.bam
 samtools index SRR35569756_sorted.bam
rm SRR35569756.sam
bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR35569757_1.fastq.gz -2 SRR35569757_2.fastq.gz -S SRR35569757.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR35569757_align.log
samtools view -@ 120 -bS SRR35569757.sam | samtools sort -o SRR35569757_sorted.bam
 samtools index SRR35569757_sorted.bam
rm SRR35569757.sam
bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR35569758_1.fastq.gz -2 SRR35569758_2.fastq.gz -S SRR35569758.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR35569758_align.log
samtools view -@ 120 -bS SRR35569758.sam | samtools sort -o SRR35569758_sorted.bam
 samtools index SRR35569758_sorted.bam
rm SRR35569758.sam
bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR35569759_1.fastq.gz -2 SRR35569759_2.fastq.gz -S SRR35569759.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR35569759_align.log
samtools view -@ 120 -bS SRR35569759.sam | samtools sort -o SRR35569759_sorted.bam
 samtools index SRR35569759_sorted.bam
rm SRR35569759.sam
bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR35569760_1.fastq.gz -2 SRR35569760_2.fastq.gz -S SRR35569760.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR35569760_align.log
samtools view -@ 120 -bS SRR35569760.sam | samtools sort -o SRR35569760_sorted.bam
 samtools index SRR35569760_sorted.bam
rm SRR35569760.sam
bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR35569761_1.fastq.gz -2 SRR35569761_2.fastq.gz -S SRR35569761.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR35569761_align.log
samtools view -@ 120 -bS SRR35569761.sam | samtools sort -o SRR35569761_sorted.bam
 samtools index SRR35569761_sorted.bam
rm SRR35569761.sam
bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR35569762_1.fastq.gz -2 SRR35569762_2.fastq.gz -S SRR35569762.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR35569762_align.log
samtools view -@ 120 -bS SRR35569762.sam | samtools sort -o SRR35569762_sorted.bam
 samtools index SRR35569762_sorted.bam
rm SRR35569762.sam
bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR35569763_1.fastq.gz -2 SRR35569763_2.fastq.gz -S SRR35569763.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR35569763_align.log
samtools view -@ 120 -bS SRR35569763.sam | samtools sort -o SRR35569763_sorted.bam
 samtools index SRR35569763_sorted.bam
rm SRR35569763.sam
bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR35569764_1.fastq.gz -2 SRR35569764_2.fastq.gz -S SRR35569764.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR35569764_align.log
samtools view -@ 120 -bS SRR35569764.sam | samtools sort -o SRR35569764_sorted.bam
 samtools index SRR35569764_sorted.bam
rm SRR35569764.sam
bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR35569765_1.fastq.gz -2 SRR35569765_2.fastq.gz -S SRR35569765.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR35569765_align.log
samtools view -@ 120 -bS SRR35569765.sam | samtools sort -o SRR35569765_sorted.bam
 samtools index SRR35569765_sorted.bam
rm SRR35569765.sam
bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR35569766_1.fastq.gz -2 SRR35569766_2.fastq.gz -S SRR35569766.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR35569766_align.log
samtools view -@ 120 -bS SRR35569766.sam | samtools sort -o SRR35569766_sorted.bam
 samtools index SRR35569766_sorted.bam
rm SRR35569766.sam
bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR35569767_1.fastq.gz -2 SRR35569767_2.fastq.gz -S SRR35569767.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR35569767_align.log
samtools view -@ 120 -bS SRR35569767.sam | samtools sort -o SRR35569767_sorted.bam
 samtools index SRR35569767_sorted.bam
rm SRR35569767.sam
bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR35569768_1.fastq.gz -2 SRR35569768_2.fastq.gz -S SRR35569768.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR35569768_align.log
samtools view -@ 120 -bS SRR35569768.sam | samtools sort -o SRR35569768_sorted.bam
 samtools index SRR35569768_sorted.bam
rm SRR35569768.sam
bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR35569769_1.fastq.gz -2 SRR35569769_2.fastq.gz -S SRR35569769.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR35569769_align.log
samtools view -@ 120 -bS SRR35569769.sam | samtools sort -o SRR35569769_sorted.bam
 samtools index SRR35569769_sorted.bam
rm SRR35569769.sam
macs2 callpeak -t SRR35569758_sorted.bam SRR35569759_sorted.bam SRR35569768_sorted.bam SRR35569769_sorted.bam -c SRR35569760_sorted.bam SRR35569761_sorted.bam -n peaks/Nematostella_vectensis_H3K27ac_Ncol3_negative_consensus -f BAM -g 2.61e8 2> logs/Nematostella_vectensis_H3K27ac_Ncol3_negative_macs2.log
macs2 callpeak -t SRR35569754_sorted.bam SRR35569755_sorted.bam SRR35569756_sorted.bam SRR35569757_sorted.bam -c SRR35569760_sorted.bam SRR35569761_sorted.bam -n peaks/Nematostella_vectensis_H3K27ac_Ncol3_positive_consensus -f BAM -g 2.61e8 2> logs/Nematostella_vectensis_H3K27ac_Ncol3_positive_macs2.log
macs2 callpeak -t SRR35569752_sorted.bam SRR35569753_sorted.bam SRR35569766_sorted.bam SRR35569767_sorted.bam -c SRR35569760_sorted.bam SRR35569761_sorted.bam -n peaks/Nematostella_vectensis_H3K27ac_Nep3_negative_consensus -f BAM -g 2.61e8 2> logs/Nematostella_vectensis_H3K27ac_Nep3_negative_macs2.log
macs2 callpeak -t SRR35569762_sorted.bam SRR35569763_sorted.bam SRR35569764_sorted.bam SRR35569765_sorted.bam -c SRR35569760_sorted.bam SRR35569761_sorted.bam -n peaks/Nematostella_vectensis_H3K27ac_Nep3_positive_consensus -f BAM -g 2.61e8 2> logs/Nematostella_vectensis_H3K27ac_Nep3_positive_macs2.log

bowtie2 -x bowtie2_Nematostella_vectensis -U SRR836031.fastq.gz -S SRR836031.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR836031_align.log
samtools view -@ 120 -bS SRR836031.sam | samtools sort -o SRR836031_sorted.bam
 samtools index SRR836031_sorted.bam
rm SRR836031.sam

bowtie2 -x bowtie2_Nematostella_vectensis -U SRR836032.fastq.gz -S SRR836032.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR836032_align.log
samtools view -@ 120 -bS SRR836032.sam | samtools sort -o SRR836032_sorted.bam
 samtools index SRR836032_sorted.bam
rm SRR836032.sam

bowtie2 -x bowtie2_Nematostella_vectensis -U SRR836033.fastq.gz -S SRR836033.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR836033_align.log
samtools view -@ 120 -bS SRR836033.sam | samtools sort -o SRR836033_sorted.bam
 samtools index SRR836033_sorted.bam
rm SRR836033.sam

bowtie2 -x bowtie2_Nematostella_vectensis -U SRR836035.fastq.gz -S SRR836035.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR836035_align.log
samtools view -@ 120 -bS SRR836035.sam | samtools sort -o SRR836035_sorted.bam
 samtools index SRR836035_sorted.bam
rm SRR836035.sam

bowtie2 -x bowtie2_Nematostella_vectensis -U SRR836036.fastq.gz -S SRR836036.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR836036_align.log
samtools view -@ 120 -bS SRR836036.sam | samtools sort -o SRR836036_sorted.bam
 samtools index SRR836036_sorted.bam
rm SRR836036.sam

bowtie2 -x bowtie2_Nematostella_vectensis -U SRR836037.fastq.gz -S SRR836037.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR836037_align.log
samtools view -@ 120 -bS SRR836037.sam | samtools sort -o SRR836037_sorted.bam
 samtools index SRR836037_sorted.bam
rm SRR836037.sam

bowtie2 -x bowtie2_Nematostella_vectensis -U SRR837783.fastq.gz -S SRR837783.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR837783_align.log
samtools view -@ 120 -bS SRR837783.sam | samtools sort -o SRR837783_sorted.bam
 samtools index SRR837783_sorted.bam
rm SRR837783.sam

bowtie2 -x bowtie2_Nematostella_vectensis -U SRR836038.fastq.gz -S SRR836038.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR836038_align.log
samtools view -@ 120 -bS SRR836038.sam | samtools sort -o SRR836038_sorted.bam
 samtools index SRR836038_sorted.bam
rm SRR836038.sam

bowtie2 -x bowtie2_Nematostella_vectensis -U SRR836039.fastq.gz -S SRR836039.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR836039_align.log
samtools view -@ 120 -bS SRR836039.sam | samtools sort -o SRR836039_sorted.bam
 samtools index SRR836039_sorted.bam
rm SRR836039.sam

bowtie2 -x bowtie2_Nematostella_vectensis -U SRR836040.fastq.gz -S SRR836040.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR836040_align.log
samtools view -@ 120 -bS SRR836040.sam | samtools sort -o SRR836040_sorted.bam
 samtools index SRR836040_sorted.bam
rm SRR836040.sam

bowtie2 -x bowtie2_Nematostella_vectensis -U SRR836003.fastq.gz -S SRR836003.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR836003_align.log
samtools view -@ 120 -bS SRR836003.sam | samtools sort -o SRR836003_sorted.bam
 samtools index SRR836003_sorted.bam
rm SRR836003.sam

bowtie2 -x bowtie2_Nematostella_vectensis -U SRR836004.fastq.gz -S SRR836004.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR836004_align.log
samtools view -@ 120 -bS SRR836004.sam | samtools sort -o SRR836004_sorted.bam
 samtools index SRR836004_sorted.bam
rm SRR836004.sam

bowtie2 -x bowtie2_Nematostella_vectensis -U SRR836005.fastq.gz -S SRR836005.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR836005_align.log
samtools view -@ 120 -bS SRR836005.sam | samtools sort -o SRR836005_sorted.bam
 samtools index SRR836005_sorted.bam
rm SRR836005.sam

bowtie2 -x bowtie2_Nematostella_vectensis -U SRR836006.fastq.gz -S SRR836006.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR836006_align.log
samtools view -@ 120 -bS SRR836006.sam | samtools sort -o SRR836006_sorted.bam
 samtools index SRR836006_sorted.bam
rm SRR836006.sam

bowtie2 -x bowtie2_Nematostella_vectensis -U SRR836007.fastq.gz -S SRR836007.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR836007_align.log
samtools view -@ 120 -bS SRR836007.sam | samtools sort -o SRR836007_sorted.bam
 samtools index SRR836007_sorted.bam
rm SRR836007.sam

bowtie2 -x bowtie2_Nematostella_vectensis -U SRR837781.fastq.gz -S SRR837781.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR837781_align.log
samtools view -@ 120 -bS SRR837781.sam | samtools sort -o SRR837781_sorted.bam
 samtools index SRR837781_sorted.bam
rm SRR837781.sam

bowtie2 -x bowtie2_Nematostella_vectensis -U SRR836009.fastq.gz -S SRR836009.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR836009_align.log
samtools view -@ 120 -bS SRR836009.sam | samtools sort -o SRR836009_sorted.bam
 samtools index SRR836009_sorted.bam
rm SRR836009.sam

bowtie2 -x bowtie2_Nematostella_vectensis -U SRR837782.fastq.gz -S SRR837782.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR837782_align.log
samtools view -@ 120 -bS SRR837782.sam | samtools sort -o SRR837782_sorted.bam
 samtools index SRR837782_sorted.bam
rm SRR837782.sam

bowtie2 -x bowtie2_Nematostella_vectensis -U SRR836011.fastq.gz -S SRR836011.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR836011_align.log
samtools view -@ 120 -bS SRR836011.sam | samtools sort -o SRR836011_sorted.bam
 samtools index SRR836011_sorted.bam
rm SRR836011.sam

bowtie2 -x bowtie2_Nematostella_vectensis -U SRR836012.fastq.gz -S SRR836012.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR836012_align.log
samtools view -@ 120 -bS SRR836012.sam | samtools sort -o SRR836012_sorted.bam
 samtools index SRR836012_sorted.bam
rm SRR836012.sam

bowtie2 -x bowtie2_Nematostella_vectensis -U SRR836013.fastq.gz -S SRR836013.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR836013_align.log
samtools view -@ 120 -bS SRR836013.sam | samtools sort -o SRR836013_sorted.bam
 samtools index SRR836013_sorted.bam
rm SRR836013.sam

bowtie2 -x bowtie2_Nematostella_vectensis -U SRR836014.fastq.gz -S SRR836014.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR836014_align.log
samtools view -@ 120 -bS SRR836014.sam | samtools sort -o SRR836014_sorted.bam
 samtools index SRR836014_sorted.bam
rm SRR836014.sam

bowtie2 -x bowtie2_Nematostella_vectensis -U SRR836015.fastq.gz -S SRR836015.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR836015_align.log
samtools view -@ 120 -bS SRR836015.sam | samtools sort -o SRR836015_sorted.bam
 samtools index SRR836015_sorted.bam
rm SRR836015.sam

bowtie2 -x bowtie2_Nematostella_vectensis -U SRR836016.fastq.gz -S SRR836016.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR836016_align.log
samtools view -@ 120 -bS SRR836016.sam | samtools sort -o SRR836016_sorted.bam
 samtools index SRR836016_sorted.bam
rm SRR836016.sam

bowtie2 -x bowtie2_Nematostella_vectensis -U SRR836017.fastq.gz -S SRR836017.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR836017_align.log
samtools view -@ 120 -bS SRR836017.sam | samtools sort -o SRR836017_sorted.bam
 samtools index SRR836017_sorted.bam
rm SRR836017.sam

bowtie2 -x bowtie2_Nematostella_vectensis -U SRR836018.fastq.gz -S SRR836018.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR836018_align.log
samtools view -@ 120 -bS SRR836018.sam | samtools sort -o SRR836018_sorted.bam
 samtools index SRR836018_sorted.bam
rm SRR836018.sam

bowtie2 -x bowtie2_Nematostella_vectensis -U SRR836019.fastq.gz -S SRR836019.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR836019_align.log
samtools view -@ 120 -bS SRR836019.sam | samtools sort -o SRR836019_sorted.bam
 samtools index SRR836019_sorted.bam
rm SRR836019.sam

bowtie2 -x bowtie2_Nematostella_vectensis -U SRR836020.fastq.gz -S SRR836020.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR836020_align.log
samtools view -@ 120 -bS SRR836020.sam | samtools sort -o SRR836020_sorted.bam
 samtools index SRR836020_sorted.bam
rm SRR836020.sam

bowtie2 -x bowtie2_Nematostella_vectensis -U SRR836021.fastq.gz -S SRR836021.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR836021_align.log
samtools view -@ 120 -bS SRR836021.sam | samtools sort -o SRR836021_sorted.bam
 samtools index SRR836021_sorted.bam
rm SRR836021.sam

bowtie2 -x bowtie2_Nematostella_vectensis -U SRR836022.fastq.gz -S SRR836022.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR836022_align.log
samtools view -@ 120 -bS SRR836022.sam | samtools sort -o SRR836022_sorted.bam
 samtools index SRR836022_sorted.bam
rm SRR836022.sam

bowtie2 -x bowtie2_Nematostella_vectensis -U SRR836023.fastq.gz -S SRR836023.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR836023_align.log
samtools view -@ 120 -bS SRR836023.sam | samtools sort -o SRR836023_sorted.bam
 samtools index SRR836023_sorted.bam
rm SRR836023.sam

bowtie2 -x bowtie2_Nematostella_vectensis -U SRR836024.fastq.gz -S SRR836024.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR836024_align.log
samtools view -@ 120 -bS SRR836024.sam | samtools sort -o SRR836024_sorted.bam
 samtools index SRR836024_sorted.bam
rm SRR836024.sam

bowtie2 -x bowtie2_Nematostella_vectensis -U SRR836025.fastq.gz -S SRR836025.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR836025_align.log
samtools view -@ 120 -bS SRR836025.sam | samtools sort -o SRR836025_sorted.bam
 samtools index SRR836025_sorted.bam
rm SRR836025.sam

bowtie2 -x bowtie2_Nematostella_vectensis -U SRR836026.fastq.gz -S SRR836026.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR836026_align.log
samtools view -@ 120 -bS SRR836026.sam | samtools sort -o SRR836026_sorted.bam
 samtools index SRR836026_sorted.bam
rm SRR836026.sam

bowtie2 -x bowtie2_Nematostella_vectensis -U SRR836027.fastq.gz -S SRR836027.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR836027_align.log
samtools view -@ 120 -bS SRR836027.sam | samtools sort -o SRR836027_sorted.bam
 samtools index SRR836027_sorted.bam
rm SRR836027.sam

bowtie2 -x bowtie2_Nematostella_vectensis -U SRR836028.fastq.gz -S SRR836028.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR836028_align.log
samtools view -@ 120 -bS SRR836028.sam | samtools sort -o SRR836028_sorted.bam
 samtools index SRR836028_sorted.bam
rm SRR836028.sam

bowtie2 -x bowtie2_Nematostella_vectensis -U SRR836029.fastq.gz -S SRR836029.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR836029_align.log
samtools view -@ 120 -bS SRR836029.sam | samtools sort -o SRR836029_sorted.bam
 samtools index SRR836029_sorted.bam
rm SRR836029.sam

bowtie2 -x bowtie2_Nematostella_vectensis -U SRR836030.fastq.gz -S SRR836030.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR836030_align.log
samtools view -@ 120 -bS SRR836030.sam | samtools sort -o SRR836030_sorted.bam
 samtools index SRR836030_sorted.bam
rm SRR836030.sam

bowtie2 -x bowtie2_Nematostella_vectensis -U SRR836041.fastq.gz -S SRR836041.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR836041_align.log
samtools view -@ 120 -bS SRR836041.sam | samtools sort -o SRR836041_sorted.bam
 samtools index SRR836041_sorted.bam
rm SRR836041.sam

bowtie2 -x bowtie2_Nematostella_vectensis -U SRR836042.fastq.gz -S SRR836042.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR836042_align.log
samtools view -@ 120 -bS SRR836042.sam | samtools sort -o SRR836042_sorted.bam
 samtools index SRR836042_sorted.bam
rm SRR836042.sam

bowtie2 -x bowtie2_Nematostella_vectensis -U SRR836043.fastq.gz -S SRR836043.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR836043_align.log
samtools view -@ 120 -bS SRR836043.sam | samtools sort -o SRR836043_sorted.bam
 samtools index SRR836043_sorted.bam
rm SRR836043.sam

bowtie2 -x bowtie2_Nematostella_vectensis -U SRR836044.fastq.gz -S SRR836044.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR836044_align.log
samtools view -@ 120 -bS SRR836044.sam | samtools sort -o SRR836044_sorted.bam
 samtools index SRR836044_sorted.bam
rm SRR836044.sam

bowtie2 -x bowtie2_Nematostella_vectensis -U SRR836045.fastq.gz -S SRR836045.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR836045_align.log
samtools view -@ 120 -bS SRR836045.sam | samtools sort -o SRR836045_sorted.bam
 samtools index SRR836045_sorted.bam
rm SRR836045.sam

bowtie2 -x bowtie2_Nematostella_vectensis -U SRR836046.fastq.gz -S SRR836046.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR836046_align.log
samtools view -@ 120 -bS SRR836046.sam | samtools sort -o SRR836046_sorted.bam
 samtools index SRR836046_sorted.bam
rm SRR836046.sam

bowtie2 -x bowtie2_Nematostella_vectensis -U SRR836047.fastq.gz -S SRR836047.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR836047_align.log
samtools view -@ 120 -bS SRR836047.sam | samtools sort -o SRR836047_sorted.bam
 samtools index SRR836047_sorted.bam
rm SRR836047.sam

bowtie2 -x bowtie2_Nematostella_vectensis -U SRR836048.fastq.gz -S SRR836048.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR836048_align.log
samtools view -@ 120 -bS SRR836048.sam | samtools sort -o SRR836048_sorted.bam
 samtools index SRR836048_sorted.bam
rm SRR836048.sam

macs2 callpeak -t SRR8360032_sorted.bam SRR836004_sorted.bam -c SRR836031_sorted.bam SRR836032_sorted.bam -n peaks/Nematostella_vectensis_H3K27ac_polyps_consensus -f BAM -g 2.61e8 2> logs/Nematostella_vectensis_H3K27ac_polyps_macs2.log
macs2 callpeak -t SRR836009_sorted.bam SRR837782_sorted.bam -c SRR836031_sorted.bam SRR836032_sorted.bam -n peaks/Nematostella_vectensis_H3K36me3_polyps_consensus -f BAM -g 2.61e8 2> logs/Nematostella_vectensis_H3K36me3_polyps_macs2.log
macs2 callpeak -t SRR836019_sorted.bam SRR836020_sorted.bam -c SRR836031_sorted.bam SRR836032_sorted.bam -n peaks/Nematostella_vectensis_H3K4me2_polyps_consensus -f BAM -g 2.61e8 2> logs/Nematostella_vectensis_H3K4me2_polyps_macs2.log
macs2 callpeak -t SRR836025_sorted.bam SRR836026_sorted.bam -c SRR836031_sorted.bam SRR836032_sorted.bam -n peaks/Nematostella_vectensis_H3K4me3_polyps_consensus -f BAM -g 2.61e8 2> logs/Nematostella_vectensis_H3K4me3_polyps_macs2.log
macs2 callpeak -t SRR836005_sorted.bam SRR836006_sorted.bam -c SRR836033_sorted.bam SRR836035_sorted.bam SRR836036_sorted.bam SRR836037_sorted.bam SRR837783_sorted.bam -n peaks/Nematostella_vectensis_H3K27ac_gastrulae_24h_consensus -f BAM -g 2.61e8 2> logs/Nematostella_vectensis_H3K27ac_gastrulae_24h_macs2.log
macs2 callpeak -t SRR836011_sorted.bam SRR836012_sorted.bam -c SRR836033_sorted.bam SRR836035_sorted.bam SRR836036_sorted.bam SRR836037_sorted.bam SRR837783_sorted.bam -n peaks/Nematostella_vectensis_H3K36me3_gastrulae_24h_consensus -f BAM -g 2.61e8 2> logs/Nematostella_vectensis_H3K36me3_gastrulae_24h_macs2.log
macs2 callpeak -t SRR836015_sorted.bam SRR836016_sorted.bam -c SRR836033_sorted.bam SRR836035_sorted.bam SRR836036_sorted.bam SRR836037_sorted.bam SRR837783_sorted.bam -n peaks/Nematostella_vectensis_H3K4me1_gastrulae_24h_consensus -f BAM -g 2.61e8 2> logs/Nematostella_vectensis_H3K4me1_gastrulae_24h_macs2.log
macs2 callpeak -t SRR836021_sorted.bam SRR836022_sorted.bam -c SRR836033_sorted.bam SRR836035_sorted.bam SRR836036_sorted.bam SRR836037_sorted.bam SRR837783_sorted.bam -n peaks/Nematostella_vectensis_H3K4me2_gastrulae_24h_consensus -f BAM -g 2.61e8 2> logs/Nematostella_vectensis_H3K4me2_gastrulae_24h_macs2.log
macs2 callpeak -t SRR836027_sorted.bam SRR836028_sorted.bam -c SRR836033_sorted.bam SRR836035_sorted.bam SRR836036_sorted.bam SRR836037_sorted.bam SRR837783_sorted.bam -n peaks/Nematostella_vectensis_H3K4me3_gastrulae_24h_consensus -f BAM -g 2.61e8 2> logs/Nematostella_vectensis_H3K4me3_gastrulae_24h_macs2.log
macs2 callpeak -t SRR836041_sorted.bam SRR836042_sorted.bam -c SRR836033_sorted.bam SRR836035_sorted.bam SRR836036_sorted.bam SRR836037_sorted.bam SRR837783_sorted.bam -n peaks/Nematostella_vectensis_p300_CBP_gastrulae_24h_consensus -f BAM -g 2.61e8 2> logs/Nematostella_vectensis_p300_CBP_gastrulae_24h_macs2.log
macs2 callpeak -t SRR836045_sorted.bam SRR836046_sorted.bam -c SRR836033_sorted.bam SRR836035_sorted.bam SRR836036_sorted.bam SRR836037_sorted.bam SRR837783_sorted.bam -n peaks/Nematostella_vectensis_unphosphorylated_CTD_gastrulae_24h_consensus -f BAM -g 2.61e8 2> logs/Nematostella_vectensis_unphosphorylated_CTD_gastrulae_24h_macs2.log
macs2 callpeak -t SRR836007_sorted.bam SRR837781_sorted.bam -c SRR836038_sorted.bam SRR836039_sorted.bam SRR836040_sorted.bam -n peaks/Nematostella_vectensis_H3K27ac_planulae_4days_consensus -f BAM -g 2.61e8 2> logs/Nematostella_vectensis_H3K27ac_planulae_4days_macs2.log
macs2 callpeak -t SRR836013_sorted.bam SRR836014_sorted.bam -c SRR836038_sorted.bam SRR836039_sorted.bam SRR836040_sorted.bam -n peaks/Nematostella_vectensis_H3K36me3_planulae_4days_consensus -f BAM -g 2.61e8 2> logs/Nematostella_vectensis_H3K36me3_planulae_4days_macs2.log
macs2 callpeak -t SRR836017_sorted.bam SRR836018_sorted.bam -c SRR836038_sorted.bam SRR836039_sorted.bam SRR836040_sorted.bam -n peaks/Nematostella_vectensis_H3K4me1_planulae_4days_consensus -f BAM -g 2.61e8 2> logs/Nematostella_vectensis_H3K4me1_planulae_4days_macs2.log
macs2 callpeak -t SRR836023_sorted.bam SRR836024_sorted.bam -c SRR836038_sorted.bam SRR836039_sorted.bam SRR836040_sorted.bam -n peaks/Nematostella_vectensis_H3K4me2_planulae_4days_consensus -f BAM -g 2.61e8 2> logs/Nematostella_vectensis_H3K4me2_planulae_4days_macs2.log
macs2 callpeak -t SRR836029_sorted.bam SRR836030_sorted.bam -c SRR836038_sorted.bam SRR836039_sorted.bam SRR836040_sorted.bam -n peaks/Nematostella_vectensis_H3K4me3_planulae_4days_consensus -f BAM -g 2.61e8 2> logs/Nematostella_vectensis_H3K4me3_planulae_4days_macs2.log
macs2 callpeak -t SRR836043_sorted.bam SRR836044_sorted.bam -c SRR836038_sorted.bam SRR836039_sorted.bam SRR836040_sorted.bam -n peaks/Nematostella_vectensis_p300_CBP_planulae_4days_consensus -f BAM -g 2.61e8 2> logs/Nematostella_vectensis_p300_CBP_planulae_4days_macs2.log
macs2 callpeak -t SRR836047_sorted.bam SRR836048_sorted.bam -c SRR836038_sorted.bam SRR836039_sorted.bam SRR836040_sorted.bam -n peaks/Nematostella_vectensis_unphosphorylated_CTD_planulae_4days_consensus -f BAM -g 2.61e8 2> logs/Nematostella_vectensis_unphosphorylated_CTD_planulae_4days_macs2.log

bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR16105557_1.fastq.gz -2 SRR16105557_2.fastq.gz -S SRR16105557.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR16105557_align.log
samtools view -@ 120 -bS SRR16105557.sam | samtools sort -o SRR16105557_sorted.bam
 samtools index SRR16105557_sorted.bam
rm SRR16105557.sam

bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR16105558_1.fastq.gz -2 SRR16105558_2.fastq.gz -S SRR16105558.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR16105558_align.log
samtools view -@ 120 -bS SRR16105558.sam | samtools sort -o SRR16105558_sorted.bam
 samtools index SRR16105558_sorted.bam
rm SRR16105558.sam

bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR16105559_1.fastq.gz -2 SRR16105559_2.fastq.gz -S SRR16105559.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR16105559_align.log
samtools view -@ 120 -bS SRR16105559.sam | samtools sort -o SRR16105559_sorted.bam
 samtools index SRR16105559_sorted.bam
rm SRR16105559.sam

bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR16105560_1.fastq.gz -2 SRR16105560_2.fastq.gz -S SRR16105560.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR16105560_align.log
samtools view -@ 120 -bS SRR16105560.sam | samtools sort -o SRR16105560_sorted.bam
 samtools index SRR16105560_sorted.bam
rm SRR16105560.sam

bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR16105561_1.fastq.gz -2 SRR16105561_2.fastq.gz -S SRR16105561.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR16105561_align.log
samtools view -@ 120 -bS SRR16105561.sam | samtools sort -o SRR16105561_sorted.bam
 samtools index SRR16105561_sorted.bam
rm SRR16105561.sam

bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR16105562_1.fastq.gz -2 SRR16105562_2.fastq.gz -S SRR16105562.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR16105562_align.log
samtools view -@ 120 -bS SRR16105562.sam | samtools sort -o SRR16105562_sorted.bam
 samtools index SRR16105562_sorted.bam
rm SRR16105562.sam

bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR18500250_1.fastq.gz -2 SRR18500250_2.fastq.gz -S SRR18500250.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR18500250_align.log
samtools view -@ 120 -bS SRR18500250.sam | samtools sort -o SRR18500250_sorted.bam
 samtools index SRR18500250_sorted.bam
rm SRR18500250.sam

bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR18500251_1.fastq.gz -2 SRR18500251_2.fastq.gz -S SRR18500251.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR18500251_align.log
samtools view -@ 120 -bS SRR18500251.sam | samtools sort -o SRR18500251_sorted.bam
 samtools index SRR18500251_sorted.bam
rm SRR18500251.sam

bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR18500252_1.fastq.gz -2 SRR18500252_2.fastq.gz -S SRR18500252.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR18500252_align.log
samtools view -@ 120 -bS SRR18500252.sam | samtools sort -o SRR18500252_sorted.bam
 samtools index SRR18500252_sorted.bam
rm SRR18500252.sam

bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR18500253_1.fastq.gz -2 SRR18500253_2.fastq.gz -S SRR18500253.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR18500253_align.log
samtools view -@ 120 -bS SRR18500253.sam | samtools sort -o SRR18500253_sorted.bam
 samtools index SRR18500253_sorted.bam
rm SRR18500253.sam

bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR18500254_1.fastq.gz -2 SRR18500254_2.fastq.gz -S SRR18500254.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR18500254_align.log
samtools view -@ 120 -bS SRR18500254.sam | samtools sort -o SRR18500254_sorted.bam
 samtools index SRR18500254_sorted.bam
rm SRR18500254.sam

bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR18500255_1.fastq.gz -2 SRR18500255_2.fastq.gz -S SRR18500255.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR18500255_align.log
samtools view -@ 120 -bS SRR18500255.sam | samtools sort -o SRR18500255_sorted.bam
 samtools index SRR18500255_sorted.bam
rm SRR18500255.sam

bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR18500256_1.fastq.gz -2 SRR18500256_2.fastq.gz -S SRR18500256.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR18500256_align.log
samtools view -@ 120 -bS SRR18500256.sam | samtools sort -o SRR18500256_sorted.bam
 samtools index SRR18500256_sorted.bam
rm SRR18500256.sam

bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR18500260_1.fastq.gz -2 SRR18500260_2.fastq.gz -S SRR18500260.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR18500260_align.log
samtools view -@ 120 -bS SRR18500260.sam | samtools sort -o SRR18500260_sorted.bam
 samtools index SRR18500260_sorted.bam
rm SRR18500260.sam

bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR18500265_1.fastq.gz -2 SRR18500265_2.fastq.gz -S SRR18500265.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR18500265_align.log
samtools view -@ 120 -bS SRR18500265.sam | samtools sort -o SRR18500265_sorted.bam
 samtools index SRR18500265_sorted.bam
rm SRR18500265.sam

bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR18500266_1.fastq.gz -2 SRR18500266_2.fastq.gz -S SRR18500266.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR18500266_align.log
samtools view -@ 120 -bS SRR18500266.sam | samtools sort -o SRR18500266_sorted.bam
 samtools index SRR18500266_sorted.bam
rm SRR18500266.sam

bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR18500267_1.fastq.gz -2 SRR18500267_2.fastq.gz -S SRR18500267.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR18500267_align.log
samtools view -@ 120 -bS SRR18500267.sam | samtools sort -o SRR18500267_sorted.bam
 samtools index SRR18500267_sorted.bam
rm SRR18500267.sam

bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR18500268_1.fastq.gz -2 SRR18500268_2.fastq.gz -S SRR18500268.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR18500268_align.log
samtools view -@ 120 -bS SRR18500268.sam | samtools sort -o SRR18500268_sorted.bam
 samtools index SRR18500268_sorted.bam
rm SRR18500268.sam

bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR18500269_1.fastq.gz -2 SRR18500269_2.fastq.gz -S SRR18500269.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR18500269_align.log
samtools view -@ 120 -bS SRR18500269.sam | samtools sort -o SRR18500269_sorted.bam
 samtools index SRR18500269_sorted.bam
rm SRR18500269.sam

bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR18500270_1.fastq.gz -2 SRR18500270_2.fastq.gz -S SRR18500270.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR18500270_align.log
samtools view -@ 120 -bS SRR18500270.sam | samtools sort -o SRR18500270_sorted.bam
 samtools index SRR18500270_sorted.bam
rm SRR18500270.sam

bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR18500271_1.fastq.gz -2 SRR18500271_2.fastq.gz -S SRR18500271.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR18500271_align.log
samtools view -@ 120 -bS SRR18500271.sam | samtools sort -o SRR18500271_sorted.bam
 samtools index SRR18500271_sorted.bam
rm SRR18500271.sam

bowtie2 -x bowtie2_Nematostella_vectensis -1 SRR18500272_1.fastq.gz -2 SRR18500272_2.fastq.gz -S SRR18500272.sam -p 120 --very-sensitive --dovetail --no-mixed --no-discordant 2> logs/SRR18500272_align.log
samtools view -@ 120 -bS SRR18500272.sam | samtools sort -o SRR18500272_sorted.bam
 samtools index SRR18500272_sorted.bam
rm SRR18500272.sam
macs2 callpeak -t SRR18500265_sorted.bam SRR18500260_sorted.bam SRR18500256_sorted.bam -c SRR18500266_sorted.bam SRR18500252_sorted.bam SRR18500250_sorted.bam SRR18500253_sorted.bam SRR18500251_sorted.bam -n peaks/Nematostella_vectensis_late_gastrula_consensus -f BAM -g 2.61e8 2> logs/Nematostella_vectensis_late_gastrula_macs2.log
macs2 callpeak -t SRR18500267_sorted.bam SRR18500272_sorted.bam SRR18500271_sorted.bam -c SRR18500268_sorted.bam SRR18500254_sorted.bam SRR18500270_sorted.bam SRR18500255_sorted.bam SRR18500269_sorted.bam -n peaks/Nematostella_vectensis_late_planula_4d_consensus -f BAM -g 2.61e8 2> logs/Nematostella_vectensis_late_planula_4d_macs2.log

