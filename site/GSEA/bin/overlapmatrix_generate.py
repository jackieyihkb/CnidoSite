#!/usr/bin/python
import os, sys, re, urllib2, operator
from GSEA import *
from operator import itemgetter, attrgetter

session = sys.argv[1]
paraList = {}

for line in open('tmp/%s.conf' % session):   # fetch para
	list = line.split()
	if len(list) == 2:
		paraList[list[0]] = list[1]

if os.path.isfile('tmp/%s.selected_geneset' % session):
	os.system("python bin/GOM.py %s %s" % (session, paraList['species']) )