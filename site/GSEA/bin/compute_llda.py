#!/usr/bin/python
import os, sys, re, urllib2, operator
from GSEA import *
from operator import itemgetter, attrgetter

session = sys.argv[1]
group = []
paraList = {}
array = {}
array_s = {}
query = []

for line in open('/home/llda/out/0/0.conf'):   # fetch para
	list = line.split()
	if len(list) == 2:
		paraList[list[0]] = list[1]
	
job = GSEA_analysis(session)
job.fetchparam()
flag = 0

category = 0
for line in open('/home/llda/out/0/0.category'):
    group.append(line.strip())
    category +=1

# Begin enrichment computing
if paraList["bgtype"] == "suggested":
	for grp_type in group:
		job.OverlapAnalysis(grp_type)
else:
	for grp_type in group:
		job.CustomizedOverlapAnalysis(grp_type)
# Processing R Scripts and create output file
job.RstatisticAnalysis()
for grp_type in group:
	job.detail(grp_type)

# To find if analysis has no results.
for grp_type in group:
	if os.path.exists('/home/llda/out/1/%s_%s.NullResult' % (session,grp_type)):
		flag +=1

if category == flag:
	ferr = open('/home/llda/out/1/%s.NoResult' % session, 'w')
	ferr.close()
	sys.exit()

# Create overlap analysis tables.	
for line in open('/home/llda/out/1/%s.detail' % session):
	list = line.split('\t')
	array[line] = float(list[5])

array_s = sorted(array.iteritems(), key=operator.itemgetter(1))

fout = open('/home/llda/out/1/%s.sorted_detail' % session, 'a')
for val in array_s:
	fout.write(val[0])
fout.close()

#create overlap matrix
for line in open('/home/llda/out/1/%s.sorted_detail' % session):
	list = line.split('\t')
	if float(list[5]) >= float(paraList['cutoff']):
		break
	else:
		str = '%s\n' % list[0]
		open('/home/llda/out/1/%s.selected_geneset' % session, 'a').write(str)

# do overlap matrix selectively
count = len(open('/home/llda/out/1/%s.query' % session).readlines())
if count <= 500:
	if os.path.isfile('/home/llda/out/1/%s.selected_geneset' % session):
		os.system("python bin/GOM_llda.py %s %s" % (session, paraList['species']) )

#Done with all the analysis
fdone = open('/home/llda/out/1/%s.allDone' % session, 'w')
fdone.close()
