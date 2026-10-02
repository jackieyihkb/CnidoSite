#!/usr/bin/python
import os, sys, re, threading, urllib2
from random import uniform

session = sys.argv[1]
species = sys.argv[2]

affy2locus = {}
query_array = {}
paraList = {}
redundancy =  {}

for line in open('/home/xlma/out/0/0.conf'):   # fetch para
	list = line.split()
	if len(list) == 2:
		paraList[list[0]] = list[1]

if os.path.isfile('database/%s.affy2locus' % species):
	for line in open('database/%s.affy2locus' % species):
		list = line.strip().split('\t')
		affy2locus[list[0]] = list[1]

for line in open('/home/xlma/out/file/%s.file' % session):
	list = line.strip().split()
	m = re.match('.*\_at', str(list))
	if len(list) != 1:
		ferr = open('/home/xlma/out/1/%s.FormatError' % session, 'w')
		ferr.close()
		sys.exit()
	elif m is not None:
		if list[0] in affy2locus:
			fin = open('/home/xlma/out/1/%s.tmp.query' % session, 'a')
			array = affy2locus[list[0]].split('#')
			for locus in array:
				fin.write('%s\n' % locus)
			fin.close()
	else:
		fin = open('/home/xlma/out/1/%s.tmp.query' % session, 'a')
		for item in list:
			fin.write('%s\n' % item)
		fin.close()

# remove query redundancy
for line2 in open('/home/xlma/out/1/%s.tmp.query' % session):
	query_array[line2.strip()] = 1

fin = open('/home/xlma/out/1/%s.query' % session, 'a')
for key in query_array:
	fin.write('%s\n' % key)
fin.close()

# remove bgList redundancy
if paraList["bgtype"] == "customized":
	for line3 in open('/home/xlma/out/1/%s.bgfile' % session):
		query_array[line3.strip()] = 1
	fin = open('/home/xlma/out/1/%s.background' % session, 'a')
	for key in query_array:
		fin.write('%s\n' % key)
	fin.close()

# create file with redundant list
for line in open('/home/xlma/out/file/%s.file' % session):
	if line.strip() not in redundancy:
		redundancy[line.strip()] = 1
	else:
		redundancy[line.strip()] += 1
fin = open('/home/xlma/out/1/%s.redu.query' % session, 'a')
for key in redundancy:
	if redundancy[key] > 1:
		fin.write('%s\n' % key)
fin.close()


os.system("python bin/compute_xlma.py %s " % session)
