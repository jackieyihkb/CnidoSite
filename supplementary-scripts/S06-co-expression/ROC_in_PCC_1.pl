#!perl
open(IN1,"bpl_go.txt")or die;
my $in=$ARGV[0];
open(IN2,$in)or die;
open(OUT,">ROC_in".$in)or die;
#open(OUT1,">tmp.out")or die;
while(<IN1>)
{
$_=~s/[\r\n]//g;
my @array=split(/\t/,$_);
$hash{$array[1]}.=$array[0]."\t";
}

while(<IN2>)
{
my @brray=split(/\t/,$_);
if($hash{$brray[0]} && $hash{$brray[1]})
{
#print OUT1 $_;
my @crray=split(/\t/,$hash{$brray[0]});
my @drray=split(/\t/,$hash{$brray[1]});
my $n=0;
my $m=0;
foreach my $go(@crray)
{
if(grep { $_ eq $go } @drray)
{
$n++;
last;
}
}
if($n==0)
{print OUT $brray[1]."\t".$brray[2]."\t0\n";}
else{print OUT $brray[1]."\t".$brray[2]."\t1\n";}


foreach my $go(@drray)
{
if(grep { $_ eq $go } @crray)
{
$m++;
last;
}
}
if($m==0)
{print OUT $brray[0]."\t".$brray[2]."\t0\n";}
else{print OUT $brray[0]."\t".$brray[2]."\t1\n";}


}

}
