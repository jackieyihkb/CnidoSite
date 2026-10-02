#!/usr/bin/env python

import sys
import re

gene_set = {}
for line in open(sys.argv[1]):
   list = line.strip()
   m = re.match('(.*)\tTAIR10\tgene\t\d+\t\d+\t\.\t.\t\.\t.*Name=(.*)',list)
   if m is not None:
    name = m.group(1)
    locus = m.group(2)
    if name not in gene_set:
     gene_set[name]= [locus]
    else:
     gene_set[name].append(locus)

for item in gene_set:
  print '%s\t%s' % (item, gene_set[item])	   
