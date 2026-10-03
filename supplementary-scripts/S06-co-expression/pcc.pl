#!/user/bin/perl
use strict;
use warnings;

use Statistics::Basic qw(:all);
#open(FROM,"test.fpkm.txt") or die();
open(FROM,"bpl_expression_matrix_no0.txt") or die();
open(TOP,">bpl_positive_result.pcc") or die();
open(TON,">bpl_negative_result.pcc") or die();
open(TO,">bpl_no_result.pcc") or die();
my $n=0;
my %hash;
while(my $line=<FROM>)
{
        chomp($line);
        my @array = split(/\t/,$line);
        my $key = shift(@array);
        my $value = join("\t",@array);
        $hash{$key} = $value;
}
close(FROM);

while(keys(%hash) >= 2)
{
        my ($key1,$value1) = each(%hash);
        delete($hash{$key1});

        while(my ($key2,$value2) = each(%hash))
        {
                my @array1 = split(/\t/,$value1);
                my @array2 = split(/\t/,$value2);
        my $v1 = vector(@array1);
        my $v2 = vector(@array2);

                my $correl = correlation($v1,$v2);
                if($correl>0)
{
                print TOP "$key1\t$key2\t$correl\n";
}
                elsif($correl<0)
{
                print TON "$key1\t$key2\t$correl\n";
}
else
{print TO "$key1\t$key2\t$correl\n";}

        }

}
print "done\n";
close(TO);
