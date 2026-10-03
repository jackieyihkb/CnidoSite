#!/usr/bin/env bash
# Print: filename  n_seqs  total_bp  first_seqid  median_len
f="$1"
gzip -dc "$f" | awk -v F="$(basename $f)" '
  /^>/ { if (n>0) { bp+=l; L[++n]=l } else { n=1; first=substr($0,2); first=first; sub(/ .*/,"",first) }
        l=0; next }
  { l+=length($0) }
  END { bp+=l; printf "%s\t%d\t%d\t%s\n", F, n, bp, first }'
