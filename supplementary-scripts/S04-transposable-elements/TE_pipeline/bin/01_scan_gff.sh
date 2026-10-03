#!/usr/bin/env bash
# Print: file  n_gene  n_mRNA  n_exon  span_bp  n_seqid  first_seqid
f="$1"
case "$f" in
  *.gz) READ="gzip -dc" ;;
  *)    READ="cat" ;;
esac
$READ "$f" | awk -v F="$(basename $f)" '
  /^#/ {next}
  { n[$1]=1; if (first=="") first=$1;
    if ($3=="gene") {g++; if($5>max)max=$5}
    else if ($3=="mRNA"||$3=="transcript") m++;
    else if ($3=="exon") e++;
  }
  END { nseq=0; for (k in n) nseq++;
        printf "%s\t%d\t%d\t%d\t%d\t%d\t%s\n", F, g, m, e, max, nseq, first }'
