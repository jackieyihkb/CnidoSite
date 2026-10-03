my $in = @ARGV[0]; 

my $out = @ARGV[1];
#Scietific name	anno	Project ID	Study Accession	Experiment Accession	Run	Layout	tissue/organ	dev_stage	Treatment	Description
#Acropora austera	Acropora_austera	PRJEB77014	ERP161497	ERX13780073	ERR14379125	PAIRED				

open (IN,$in) or die;  
open (OUT,">$out") or die;  #KEGG_final.txt

while(<IN>){
chomp;
@a = split /\t/,$_;
if ($a[6] eq "PAIRED"){
	print OUT "trimmomatic PE -threads 30 -phred33 $a[5]_1.fastq.gz $a[5]_2.fastq.gz ./results/trimmed/$a[5]_trimmed_1.fastq.gz ./results/trimmed/$a[5]_unpaired_1.fastq.gz ./results/trimmed/$a[5]_trimmed_2.fastq.gz ./results/trimmed/$a[5]_unpaired_2.fastq.gz ILLUMINACLIP:/home/jackie/miniconda3/envs/rna/share/trimmomatic-0.40-0/adapters/TruSeq3-PE.fa:2:30:10 LEADING:3 TRAILING:3 SLIDINGWINDOW:4:15 MINLEN:36\n";
	print OUT "hisat2 -x ./results/db/$a[1] -1 ./results/trimmed/$a[5]_trimmed_1.fastq.gz -2 ./results/trimmed/$a[5]_trimmed_2.fastq.gz -S ./results/aligned/$a[5].sam --dta --rna-strandness RF --threads 30 2> results/aligned/$a[5]_hisat2.log\n";
	}else{
	print OUT "trimmomatic SE -threads 30 -phred33 $a[5].fastq.gz ./results/trimmed/$a[5]_trimmed.fastq.gz ILLUMINACLIP:/home/jackie/miniconda3/envs/rna/share/trimmomatic-0.40-0/adapters/TruSeq3-SE.fa:2:30:10 LEADING:3 TRAILING:3 SLIDINGWINDOW:4:15 MINLEN:36\n";
	print OUT "hisat2 -x ./results/db/$a[1] -U ./results/trimmed/$a[5]_trimmed.fastq.gz -S ./results/aligned/$a[5].sam --dta --rna-strandness RF --threads 30 2> results/aligned/$a[5]_hisat2.log\n\n";
	}
print OUT "samtools view -@ 30 -bS ./results/aligned/$a[5].sam | samtools sort -@ 30 -o ./results/aligned/$a[5]_sorted.bam\n";
print OUT "samtools index ./results/aligned/$a[5]_sorted.bam\n";
print OUT "rm results/aligned/$a[5].sam\n";
print OUT "stringtie -p 30 -e -B -G $a[1].gff3 -o ./results/stringtie_assembly/$a[5].gtf -l $a[5] ./results/aligned/$a[5]_sorted.bam\n";
if($a[7]){$a[7].="_";}
if($a[8]){$a[8].="_";}
if($a[9]){$a[9].="_";}
$bw=$a[1]."_".$a[5]."_".$a[7].$a[8].$a[9].$a[10].".bw";
print OUT "bamCoverage --bam results/aligned/$a[5]_sorted.bam --outFileName $bw --outFileFormat bigwig --normalizeUsing RPKM --ignoreDuplicates --numberOfProcessors max  --verbose\n";

}

close in;
close out;