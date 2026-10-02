#!/usr/bin/python
import os, sys, re, threading, urllib2
from random import uniform

session = sys.argv[1]
species = sys.argv[2]
type = sys.argv[3]
affy2locus = {}
locus2affy = {}
symbol2locus = {}
locus2symbol = {}
msu2rap = {}
rap2msu = {}

if type == 'affy2locus' or type == 'locus2affy':
	for line in open('database/%s.affy2locus' % species):
		list = line.strip().split('\t')
		if type == 'affy2locus':
			affy2locus[list[0]] = list[1]
		elif type == 'locus2affy':
			locus = list[1].split('#')
			for id in locus:
				if id not in locus2affy:
					locus2affy[id] = [list[0]]
				else:
					locus2affy[id].append(list[0])
	
	for line in open('tmp/%s.origFile' % session):
		list = line.strip().split()
		if len(list) != 1:
			ferr = open('tmp/%s.FormatError' % session, 'w')
			ferr.close()
			sys.exit()
		else:
			fin = open('tmp/%s.convertedFile' % session, 'a')
			if type == 'affy2locus':
				if affy2locus.has_key(list[0]):
					fin.write('%s\t%s\n' % (list[0], affy2locus[list[0]]))
				else:
					fin.write('%s\tNA\n' % list[0])
			elif type == 'locus2affy':
				if locus2affy.has_key(list[0]):
					fin.write('%s\t' % list[0])
					for probe in locus2affy[list[0]]:
						fin.write('%s#' % probe)
					fin.write('\n')
				else:
					fin.write('%s\tNA\n' % list[0])
			fin.close()
elif type == 'symbol2locus' or type == 'locus2symbol':
	# to warn user that database is under collection
	if species == 'Zma':
		ferr = open('tmp/%s.Unavailable' % session, 'w')
		ferr.close()
		sys.exit()
	else:
		for line in open('database/%s.GeneName2locus' % species):
			list = line.strip().split('\t')
			if type == 'locus2symbol':
				locus2symbol[list[1]] = list[0]
			elif type == 'symbol2locus':
				symbol = list[0].split('#')
				for id in symbol:
					symbol2locus[id] = list[1]
	
		for line in open('tmp/%s.origFile' % session):
			list = line.strip().split()
			if len(list) != 1:
				ferr = open('tmp/%s.FormatError' % session, 'w')
				ferr.close()
				sys.exit()
			else:
				fin = open('tmp/%s.convertedFile' % session, 'a')
				if type == 'symbol2locus':
					if symbol2locus.has_key(list[0]):
						fin.write('%s\t%s\n' % (list[0], symbol2locus[list[0]]))
					else:
						fin.write('%s\tNA\n' % list[0])
				elif type == 'locus2symbol':
					if locus2symbol.has_key(list[0]):
						fin.write('%s\t%s\n' % (list[0], locus2symbol[list[0]]))
					else:
						fin.write('%s\tNA\n' % list[0])
				fin.close()

elif type == 'msu2rap' or type == 'rap2msu':
	for line in open('database/%s.RAPandMSU' % species):
		list = line.strip().split('\t')
		if type == 'msu2rap':
			msu2rap[list[1].upper()] = list[0]
		elif type == 'rap2msu':
			rap2msu[list[0].upper()] = list[1]
	
	for line in open('tmp/%s.origFile' % session):
		list = line.strip().split()
		if len(list) != 1:
			ferr = open('tmp/%s.FormatError' % session, 'w')
			ferr.close()
			sys.exit()
		else:
			fin = open('tmp/%s.convertedFile' % session, 'a')
			if type == 'msu2rap':
				if msu2rap.has_key(list[0].upper()):
					fin.write('%s\t%s\n' % (list[0], msu2rap[list[0].upper()]))
				else:
					fin.write('%s\tNA\n' % list[0])
			elif type == 'rap2msu':
				if rap2msu.has_key(list[0].upper()):
					fin.write('%s\t%s\n' % (list[0], rap2msu[list[0].upper()]))
				else:
					fin.write('%s\tNA\n' % list[0])
			fin.close()