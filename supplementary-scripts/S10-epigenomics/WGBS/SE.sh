trimmomatic SE -threads 180 -phred33   rawdata/SRR10901710.fastq.gz trimmed/SRR10901710_trimmed.fq.gz ILLUMINACLIP:/home/$USER/.local/share/mamba/envs/bs-seq/share/trimmomatic-0.40-0/adapters/TruSeq3-PE.fa:2:30:10 LEADING:3 TRAILING:3 SLIDINGWINDOW:4:15 MINLEN:36 2> trimmed/SRR10901710_trimmomatic.log
bismark --bowtie2 -N 1 -L 20 --parallel 120  -o alignment --prefix 'SRR10901710_' Ref/Acropora_millepora/ trimmed/SRR10901710_trimmed.fq.gz 2> alignment/SRR10901710_bismark.log
deduplicate_bismark -bam -p alignment/SRR10901710_.SRR10901710_trimmed_bismark_bt2_pe.bam --output_dir duplicate
bismark_methylation_extractor --multicore 120 -p --gzip --no_overlap --comprehensive --bedGraph --count --CX_context --cytosine_report --buffer_size 20G --genome_folder Ref/Acropora_millepora/ -o report duplicate/SRR10901710_.SRR10901710_trimmed_bismark_bt2_pe.deduplicated.bam
zcat report/SRR10901710_*.deduplicated.bedGraph.gz | grep -v "track" | sort -k1,1 -k2,2n > report/SRR10901710_sorted.bedGraph
bedGraphToBigWig report/SRR10901710_sorted.bedGraph Ref/Acropora_millepora/chrom.sizes bw/Acropora_millepora_SRR10901710_axial_polyps_pool_of_4_tips_N12_rep3.bw
trimmomatic SE -threads 180 -phred33   rawdata/SRR10901711.fastq.gz trimmed/SRR10901711_trimmed.fq.gz ILLUMINACLIP:/home/$USER/.local/share/mamba/envs/bs-seq/share/trimmomatic-0.40-0/adapters/TruSeq3-PE.fa:2:30:10 LEADING:3 TRAILING:3 SLIDINGWINDOW:4:15 MINLEN:36 2> trimmed/SRR10901711_trimmomatic.log
bismark --bowtie2 -N 1 -L 20 --parallel 120  -o alignment --prefix 'SRR10901711_' Ref/Acropora_millepora/ trimmed/SRR10901711_trimmed.fq.gz 2> alignment/SRR10901711_bismark.log
deduplicate_bismark -bam -p alignment/SRR10901711_.SRR10901711_trimmed_bismark_bt2_pe.bam --output_dir duplicate
bismark_methylation_extractor --multicore 120 -p --gzip --no_overlap --comprehensive --bedGraph --count --CX_context --cytosine_report --buffer_size 20G --genome_folder Ref/Acropora_millepora/ -o report duplicate/SRR10901711_.SRR10901711_trimmed_bismark_bt2_pe.deduplicated.bam
zcat report/SRR10901711_*.deduplicated.bedGraph.gz | grep -v "track" | sort -k1,1 -k2,2n > report/SRR10901711_sorted.bedGraph
bedGraphToBigWig report/SRR10901711_sorted.bedGraph Ref/Acropora_millepora/chrom.sizes bw/Acropora_millepora_SRR10901711_axial_polyps_pool_of_4_tips_N12_rep1.bw
trimmomatic SE -threads 180 -phred33   rawdata/SRR10901712.fastq.gz trimmed/SRR10901712_trimmed.fq.gz ILLUMINACLIP:/home/$USER/.local/share/mamba/envs/bs-seq/share/trimmomatic-0.40-0/adapters/TruSeq3-PE.fa:2:30:10 LEADING:3 TRAILING:3 SLIDINGWINDOW:4:15 MINLEN:36 2> trimmed/SRR10901712_trimmomatic.log
bismark --bowtie2 -N 1 -L 20 --parallel 120  -o alignment --prefix 'SRR10901712_' Ref/Acropora_millepora/ trimmed/SRR10901712_trimmed.fq.gz 2> alignment/SRR10901712_bismark.log
deduplicate_bismark -bam -p alignment/SRR10901712_.SRR10901712_trimmed_bismark_bt2_pe.bam --output_dir duplicate
bismark_methylation_extractor --multicore 120 -p --gzip --no_overlap --comprehensive --bedGraph --count --CX_context --cytosine_report --buffer_size 20G --genome_folder Ref/Acropora_millepora/ -o report duplicate/SRR10901712_.SRR10901712_trimmed_bismark_bt2_pe.deduplicated.bam
zcat report/SRR10901712_*.deduplicated.bedGraph.gz | grep -v "track" | sort -k1,1 -k2,2n > report/SRR10901712_sorted.bedGraph
bedGraphToBigWig report/SRR10901712_sorted.bedGraph Ref/Acropora_millepora/chrom.sizes bw/Acropora_millepora_SRR10901712_radial_polyps_pool_of_4_side_scrapings_from_side_of_branch_N12_rep3.bw
trimmomatic SE -threads 180 -phred33   rawdata/SRR10901713.fastq.gz trimmed/SRR10901713_trimmed.fq.gz ILLUMINACLIP:/home/$USER/.local/share/mamba/envs/bs-seq/share/trimmomatic-0.40-0/adapters/TruSeq3-PE.fa:2:30:10 LEADING:3 TRAILING:3 SLIDINGWINDOW:4:15 MINLEN:36 2> trimmed/SRR10901713_trimmomatic.log
bismark --bowtie2 -N 1 -L 20 --parallel 120  -o alignment --prefix 'SRR10901713_' Ref/Acropora_millepora/ trimmed/SRR10901713_trimmed.fq.gz 2> alignment/SRR10901713_bismark.log
deduplicate_bismark -bam -p alignment/SRR10901713_.SRR10901713_trimmed_bismark_bt2_pe.bam --output_dir duplicate
bismark_methylation_extractor --multicore 120 -p --gzip --no_overlap --comprehensive --bedGraph --count --CX_context --cytosine_report --buffer_size 20G --genome_folder Ref/Acropora_millepora/ -o report duplicate/SRR10901713_.SRR10901713_trimmed_bismark_bt2_pe.deduplicated.bam
zcat report/SRR10901713_*.deduplicated.bedGraph.gz | grep -v "track" | sort -k1,1 -k2,2n > report/SRR10901713_sorted.bedGraph
bedGraphToBigWig report/SRR10901713_sorted.bedGraph Ref/Acropora_millepora/chrom.sizes bw/Acropora_millepora_SRR10901713_radial_polyps_pool_of_4_side_scrapings_from_side_of_branch_N12_rep2.bw
trimmomatic SE -threads 180 -phred33   rawdata/SRR10901714.fastq.gz trimmed/SRR10901714_trimmed.fq.gz ILLUMINACLIP:/home/$USER/.local/share/mamba/envs/bs-seq/share/trimmomatic-0.40-0/adapters/TruSeq3-PE.fa:2:30:10 LEADING:3 TRAILING:3 SLIDINGWINDOW:4:15 MINLEN:36 2> trimmed/SRR10901714_trimmomatic.log
bismark --bowtie2 -N 1 -L 20 --parallel 120  -o alignment --prefix 'SRR10901714_' Ref/Acropora_millepora/ trimmed/SRR10901714_trimmed.fq.gz 2> alignment/SRR10901714_bismark.log
deduplicate_bismark -bam -p alignment/SRR10901714_.SRR10901714_trimmed_bismark_bt2_pe.bam --output_dir duplicate
bismark_methylation_extractor --multicore 120 -p --gzip --no_overlap --comprehensive --bedGraph --count --CX_context --cytosine_report --buffer_size 20G --genome_folder Ref/Acropora_millepora/ -o report duplicate/SRR10901714_.SRR10901714_trimmed_bismark_bt2_pe.deduplicated.bam
zcat report/SRR10901714_*.deduplicated.bedGraph.gz | grep -v "track" | sort -k1,1 -k2,2n > report/SRR10901714_sorted.bedGraph
bedGraphToBigWig report/SRR10901714_sorted.bedGraph Ref/Acropora_millepora/chrom.sizes bw/Acropora_millepora_SRR10901714_axial_polyps_pool_of_4_tips_L5_rep3.bw
trimmomatic SE -threads 180 -phred33   rawdata/SRR10901715.fastq.gz trimmed/SRR10901715_trimmed.fq.gz ILLUMINACLIP:/home/$USER/.local/share/mamba/envs/bs-seq/share/trimmomatic-0.40-0/adapters/TruSeq3-PE.fa:2:30:10 LEADING:3 TRAILING:3 SLIDINGWINDOW:4:15 MINLEN:36 2> trimmed/SRR10901715_trimmomatic.log
bismark --bowtie2 -N 1 -L 20 --parallel 120  -o alignment --prefix 'SRR10901715_' Ref/Acropora_millepora/ trimmed/SRR10901715_trimmed.fq.gz 2> alignment/SRR10901715_bismark.log
deduplicate_bismark -bam -p alignment/SRR10901715_.SRR10901715_trimmed_bismark_bt2_pe.bam --output_dir duplicate
bismark_methylation_extractor --multicore 120 -p --gzip --no_overlap --comprehensive --bedGraph --count --CX_context --cytosine_report --buffer_size 20G --genome_folder Ref/Acropora_millepora/ -o report duplicate/SRR10901715_.SRR10901715_trimmed_bismark_bt2_pe.deduplicated.bam
zcat report/SRR10901715_*.deduplicated.bedGraph.gz | grep -v "track" | sort -k1,1 -k2,2n > report/SRR10901715_sorted.bedGraph
bedGraphToBigWig report/SRR10901715_sorted.bedGraph Ref/Acropora_millepora/chrom.sizes bw/Acropora_millepora_SRR10901715_axial_polyps_pool_of_4_tips_L5_rep1.bw
trimmomatic SE -threads 180 -phred33   rawdata/SRR10901716.fastq.gz trimmed/SRR10901716_trimmed.fq.gz ILLUMINACLIP:/home/$USER/.local/share/mamba/envs/bs-seq/share/trimmomatic-0.40-0/adapters/TruSeq3-PE.fa:2:30:10 LEADING:3 TRAILING:3 SLIDINGWINDOW:4:15 MINLEN:36 2> trimmed/SRR10901716_trimmomatic.log
bismark --bowtie2 -N 1 -L 20 --parallel 120  -o alignment --prefix 'SRR10901716_' Ref/Acropora_millepora/ trimmed/SRR10901716_trimmed.fq.gz 2> alignment/SRR10901716_bismark.log
deduplicate_bismark -bam -p alignment/SRR10901716_.SRR10901716_trimmed_bismark_bt2_pe.bam --output_dir duplicate
bismark_methylation_extractor --multicore 120 -p --gzip --no_overlap --comprehensive --bedGraph --count --CX_context --cytosine_report --buffer_size 20G --genome_folder Ref/Acropora_millepora/ -o report duplicate/SRR10901716_.SRR10901716_trimmed_bismark_bt2_pe.deduplicated.bam
zcat report/SRR10901716_*.deduplicated.bedGraph.gz | grep -v "track" | sort -k1,1 -k2,2n > report/SRR10901716_sorted.bedGraph
bedGraphToBigWig report/SRR10901716_sorted.bedGraph Ref/Acropora_millepora/chrom.sizes bw/Acropora_millepora_SRR10901716_radial_polyps_pool_of_4_side_scrapings_from_side_of_branch_L5_rep3.bw
trimmomatic SE -threads 180 -phred33   rawdata/SRR10901717.fastq.gz trimmed/SRR10901717_trimmed.fq.gz ILLUMINACLIP:/home/$USER/.local/share/mamba/envs/bs-seq/share/trimmomatic-0.40-0/adapters/TruSeq3-PE.fa:2:30:10 LEADING:3 TRAILING:3 SLIDINGWINDOW:4:15 MINLEN:36 2> trimmed/SRR10901717_trimmomatic.log
bismark --bowtie2 -N 1 -L 20 --parallel 120  -o alignment --prefix 'SRR10901717_' Ref/Acropora_millepora/ trimmed/SRR10901717_trimmed.fq.gz 2> alignment/SRR10901717_bismark.log
deduplicate_bismark -bam -p alignment/SRR10901717_.SRR10901717_trimmed_bismark_bt2_pe.bam --output_dir duplicate
bismark_methylation_extractor --multicore 120 -p --gzip --no_overlap --comprehensive --bedGraph --count --CX_context --cytosine_report --buffer_size 20G --genome_folder Ref/Acropora_millepora/ -o report duplicate/SRR10901717_.SRR10901717_trimmed_bismark_bt2_pe.deduplicated.bam
zcat report/SRR10901717_*.deduplicated.bedGraph.gz | grep -v "track" | sort -k1,1 -k2,2n > report/SRR10901717_sorted.bedGraph
bedGraphToBigWig report/SRR10901717_sorted.bedGraph Ref/Acropora_millepora/chrom.sizes bw/Acropora_millepora_SRR10901717_radial_polyps_pool_of_4_side_scrapings_from_side_of_branch_L5_rep2.bw
trimmomatic SE -threads 180 -phred33   rawdata/SRR8346017.fastq.gz trimmed/SRR8346017_trimmed.fq.gz ILLUMINACLIP:/home/$USER/.local/share/mamba/envs/bs-seq/share/trimmomatic-0.40-0/adapters/TruSeq3-PE.fa:2:30:10 LEADING:3 TRAILING:3 SLIDINGWINDOW:4:15 MINLEN:36 2> trimmed/SRR8346017_trimmomatic.log
bismark --bowtie2 -N 1 -L 20 --parallel 120  -o alignment --prefix 'SRR8346017_' Ref/Nematostella_vectensis/ trimmed/SRR8346017_trimmed.fq.gz 2> alignment/SRR8346017_bismark.log
deduplicate_bismark -bam -p alignment/SRR8346017_.SRR8346017_trimmed_bismark_bt2_pe.bam --output_dir duplicate
bismark_methylation_extractor --multicore 120 -p --gzip --no_overlap --comprehensive --bedGraph --count --CX_context --cytosine_report --buffer_size 20G --genome_folder Ref/Nematostella_vectensis/ -o report duplicate/SRR8346017_.SRR8346017_trimmed_bismark_bt2_pe.deduplicated.bam
zcat report/SRR8346017_*.deduplicated.bedGraph.gz | grep -v "track" | sort -k1,1 -k2,2n > report/SRR8346017_sorted.bedGraph
bedGraphToBigWig report/SRR8346017_sorted.bedGraph Ref/Nematostella_vectensis/chrom.sizes bw/Nematostella_vectensis_SRR8346017_Vienna_strain_9_hours_post_fertilization_.bw
trimmomatic SE -threads 180 -phred33   rawdata/SRR8346018.fastq.gz trimmed/SRR8346018_trimmed.fq.gz ILLUMINACLIP:/home/$USER/.local/share/mamba/envs/bs-seq/share/trimmomatic-0.40-0/adapters/TruSeq3-PE.fa:2:30:10 LEADING:3 TRAILING:3 SLIDINGWINDOW:4:15 MINLEN:36 2> trimmed/SRR8346018_trimmomatic.log
bismark --bowtie2 -N 1 -L 20 --parallel 120  -o alignment --prefix 'SRR8346018_' Ref/Nematostella_vectensis/ trimmed/SRR8346018_trimmed.fq.gz 2> alignment/SRR8346018_bismark.log
deduplicate_bismark -bam -p alignment/SRR8346018_.SRR8346018_trimmed_bismark_bt2_pe.bam --output_dir duplicate
bismark_methylation_extractor --multicore 120 -p --gzip --no_overlap --comprehensive --bedGraph --count --CX_context --cytosine_report --buffer_size 20G --genome_folder Ref/Nematostella_vectensis/ -o report duplicate/SRR8346018_.SRR8346018_trimmed_bismark_bt2_pe.deduplicated.bam
zcat report/SRR8346018_*.deduplicated.bedGraph.gz | grep -v "track" | sort -k1,1 -k2,2n > report/SRR8346018_sorted.bedGraph
bedGraphToBigWig report/SRR8346018_sorted.bedGraph Ref/Nematostella_vectensis/chrom.sizes bw/Nematostella_vectensis_SRR8346018_Vienna_strain_24_hours_post_fertilization_.bw
trimmomatic SE -threads 180 -phred33   rawdata/SRR8346019.fastq.gz trimmed/SRR8346019_trimmed.fq.gz ILLUMINACLIP:/home/$USER/.local/share/mamba/envs/bs-seq/share/trimmomatic-0.40-0/adapters/TruSeq3-PE.fa:2:30:10 LEADING:3 TRAILING:3 SLIDINGWINDOW:4:15 MINLEN:36 2> trimmed/SRR8346019_trimmomatic.log
bismark --bowtie2 -N 1 -L 20 --parallel 120  -o alignment --prefix 'SRR8346019_' Ref/Nematostella_vectensis/ trimmed/SRR8346019_trimmed.fq.gz 2> alignment/SRR8346019_bismark.log
deduplicate_bismark -bam -p alignment/SRR8346019_.SRR8346019_trimmed_bismark_bt2_pe.bam --output_dir duplicate
bismark_methylation_extractor --multicore 120 -p --gzip --no_overlap --comprehensive --bedGraph --count --CX_context --cytosine_report --buffer_size 20G --genome_folder Ref/Nematostella_vectensis/ -o report duplicate/SRR8346019_.SRR8346019_trimmed_bismark_bt2_pe.deduplicated.bam
zcat report/SRR8346019_*.deduplicated.bedGraph.gz | grep -v "track" | sort -k1,1 -k2,2n > report/SRR8346019_sorted.bedGraph
bedGraphToBigWig report/SRR8346019_sorted.bedGraph Ref/Nematostella_vectensis/chrom.sizes bw/Nematostella_vectensis_SRR8346019_Vienna_strain_72_hours_post_fertilization_.bw
trimmomatic SE -threads 180 -phred33   rawdata/SRR15101714.fastq.gz trimmed/SRR15101714_trimmed.fq.gz ILLUMINACLIP:/home/$USER/.local/share/mamba/envs/bs-seq/share/trimmomatic-0.40-0/adapters/TruSeq3-PE.fa:2:30:10 LEADING:3 TRAILING:3 SLIDINGWINDOW:4:15 MINLEN:36 2> trimmed/SRR15101714_trimmomatic.log
bismark --bowtie2 -N 1 -L 20 --parallel 120  -o alignment --prefix 'SRR15101714_' Ref/Pocillopora_meandrina/ trimmed/SRR15101714_trimmed.fq.gz 2> alignment/SRR15101714_bismark.log
deduplicate_bismark -bam -p alignment/SRR15101714_.SRR15101714_trimmed_bismark_bt2_pe.bam --output_dir duplicate
bismark_methylation_extractor --multicore 120 -p --gzip --no_overlap --comprehensive --bedGraph --count --CX_context --cytosine_report --buffer_size 20G --genome_folder Ref/Pocillopora_meandrina/ -o report duplicate/SRR15101714_.SRR15101714_trimmed_bismark_bt2_pe.deduplicated.bam
zcat report/SRR15101714_*.deduplicated.bedGraph.gz | grep -v "track" | sort -k1,1 -k2,2n > report/SRR15101714_sorted.bedGraph
bedGraphToBigWig report/SRR15101714_sorted.bedGraph Ref/Pocillopora_meandrina/chrom.sizes bw/Pocillopora_meandrina_SRR15101714_coral_host_tissue_nutrient_enriched.bw
trimmomatic SE -threads 180 -phred33   rawdata/SRR15101734.fastq.gz trimmed/SRR15101734_trimmed.fq.gz ILLUMINACLIP:/home/$USER/.local/share/mamba/envs/bs-seq/share/trimmomatic-0.40-0/adapters/TruSeq3-PE.fa:2:30:10 LEADING:3 TRAILING:3 SLIDINGWINDOW:4:15 MINLEN:36 2> trimmed/SRR15101734_trimmomatic.log
bismark --bowtie2 -N 1 -L 20 --parallel 120  -o alignment --prefix 'SRR15101734_' Ref/Pocillopora_meandrina/ trimmed/SRR15101734_trimmed.fq.gz 2> alignment/SRR15101734_bismark.log
deduplicate_bismark -bam -p alignment/SRR15101734_.SRR15101734_trimmed_bismark_bt2_pe.bam --output_dir duplicate
bismark_methylation_extractor --multicore 120 -p --gzip --no_overlap --comprehensive --bedGraph --count --CX_context --cytosine_report --buffer_size 20G --genome_folder Ref/Pocillopora_meandrina/ -o report duplicate/SRR15101734_.SRR15101734_trimmed_bismark_bt2_pe.deduplicated.bam
zcat report/SRR15101734_*.deduplicated.bedGraph.gz | grep -v "track" | sort -k1,1 -k2,2n > report/SRR15101734_sorted.bedGraph
bedGraphToBigWig report/SRR15101734_sorted.bedGraph Ref/Pocillopora_meandrina/chrom.sizes bw/Pocillopora_meandrina_SRR15101734_coral_host_tissue_control.bw
