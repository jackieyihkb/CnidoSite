#!/usr/bin/python
import os, sys, re, threading, urllib2
from random import uniform

session = sys.argv[1]
species = sys.argv[2]
query = []
selected_set = []
locus2set = {}
gene_set = {}

for line in open('tmp/%s.query' % session):
	query.append(line.strip())
	
for line in open('tmp/%s.selected_geneset' % session):
	selected_set.append(line.strip())
	
for line in open('database/%s.DetailInfo' % species):
	list = line.strip().split('\t')
	if list[0] in selected_set:
		gene_set[list[0]] = list[10]

for qu in query:
	input = '%s\t' % qu
	open('tmp/%s.overlap_matrix' % session, 'a+').write(input)
	for set in selected_set:
		locus2set = {}
		for locus in gene_set[set].split(','):
			locus2set[locus.upper()] = set
		if qu.upper() in locus2set.keys():
			match = 1
			input = '#FF7800||'
			open('tmp/%s.overlap_matrix' % session, 'a+').write(input)
		else:
			input = '#CCCCCC||'
			open('tmp/%s.overlap_matrix' % session, 'a+').write(input)
	input = '\n'
	open('tmp/%s.overlap_matrix' % session, 'a+').write(input)
