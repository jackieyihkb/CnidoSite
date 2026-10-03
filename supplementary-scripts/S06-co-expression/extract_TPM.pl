#!/usr/bin/perl

use strict;
use warnings;

# 输出文件名
my $output_file = 'bpl_expression_matrix.txt';

# 初始化存储 TPM 和基因数据的哈希表
my %tpm_data;
my %gene_data;
my %sample_data;

# 打开包含文件名列表的文本文档
open(my $fh, '<', 'gtf') or die "无法打开gtf.txt: $!";

# 逐行读取文件名
while (my $filename = <$fh>) {
    chomp $filename; 
	my ($sample) = $filename =~ /bpl_([^"]+)_trimmed_stringtie.gtf/;
    # 打开文件进行处理
    open(my $file, '<', $filename) or die "无法打开文件 $filename: $!";
    while (my $line = <$file>) {
        $line=~s/[\r\n]//g;
		# 跳过注释行
		next if $line =~ /^#/;
		my @a = ();
		@a=split /\t/,$line;
		#提取TPM值
		if( @a && $a[2] eq "transcript" && $a[8] =~ /TPM "([^"]+)";/){
			my $tpm = $1;
			my ($gene) = $a[8] =~ /gene_id "\d+:([^"]+)";/;
			#存取TPM数据
			$tpm_data{$gene}{$sample} = $tpm;
			#存取gene数据
			$gene_data{$gene} = 1;
			#存取样本数据
			$sample_data{$sample} = 1;
		}
    }
    close($file);
}

close($fh);

# 获取基因和样本的列表
my @genes = sort keys %gene_data;
my @samples = sort keys %sample_data;

# 打开输出文件
open(my $output_fh, '>', $output_file) or die "无法打开输出文件 $output_file: $!";

# 打印表头
print $output_fh "Gene\t", join("\t", @samples), "\n";

# 打印表达矩阵
foreach my $gene_id (@genes) {
    my $gene_name = $gene_id; # 基因名默认为基因 ID
    
    # 如果有基因名信息，则使用基因名
    if ($gene_data{$gene_id}) {
        $gene_name = $gene_id;
    }
    
    # 打印基因 ID、基因名和 TPM 值
    print $output_fh "$gene_name";
    
    foreach my $sample (@samples) {
        my $tpm = $tpm_data{$gene_id}{$sample} || 0; # 若不存在 TPM 值，默认为 0
        print $output_fh "\t$tpm";
    }
    
    print $output_fh "\n";
}

close($output_fh);
