#!/usr/bin/python
from __future__ import division
import os, sys, re ,random
from random import uniform

class Graph_generation:
	'''The class is used to do the Graph generation
	'''

	def __init__(self, session):
		""" Grab sessionID and generate related file names """
		self.session = session
		self.desc = {} # key: GO, value: description
		self.GO2stat = {}   # key: GO, value: [pvalue, qvalue] (geneset in on category)
		self.nodes = {} # geneset which is significant and have relationship.
		self.path = {}
		self.paraList = {}
		self.GO2geneset = {} # key:GO id, value:gene_set name

	def fetchparam(self, aspect):
		for line in open('database/go.rel'):   # fetch relationship between GO
			list = line.split()
			if list[1] =='is_a':
				path = '%s %s' % (list[2], list[0])   # 'parentterm childterm' 
				self.path[path] = 1
			
		for line in open('tmp/%s.conf' % self.session):   # fetch para
			list = line.split()
			if len(list) == 2:
				self.paraList[list[0]] = list[1]

		for line in open('database/gene_ontology.obo'):   # fetch relationship between GO id and Geneset Name
			list = line.strip().split(': ')
			if list[0] == 'id':
				GOid = list[1]
			elif list[0] == 'name':
				self.GO2geneset[GOid] = "_".join(list[1].upper().split())
				geneset = "_".join(list[1].upper().split())
			elif list[0] == 'alt_id':
                                self.GO2geneset[list[1]] = geneset
		
		for line in open('database/%s_%s' % (self.paraList['species'], aspect)):
			list = line.split('\t')
			self.desc[list[0]] = list[1]
			

	def color(self, pv):
		""" This function is used to set the color in the graphic image based on the Pvalue
		"""
		cf = float(self.paraList['cutoff'])
		if pv > cf:
			color = '#FFFFFF'
		elif pv <= cf and pv >= cf/10:
			color = '#FFFF1A'
		elif pv < cf/10 and pv >= cf/100:
			color = '#FFD200'
		elif pv < cf/100 and pv >= cf/1000:
			color = '#FFB400'
		elif pv < cf/1000 and pv >= cf/1e4:
			color = '#FF9600'
		elif pv < cf/1e4 and pv >= cf/1e5:
			color = '#FF7800'
		elif pv < cf/1e5 and pv >= cf/1e6:
			color = '#FF5A00'
		elif pv < cf/1e6 and pv >= cf/1e7:
			color = '#FF3C00'
		elif pv < cf/1e7 and pv >= cf/1e8:
			color = '#FF1E00'
		else:
			color = '#FF0000'
		return color


	def generalpic(self, aspect, randID, set):
		flag = 0
		query_tmp1 = self.desc[set].split(',    GOslim:')
		query_tmp2 = query_tmp1[0].split('   ')
		query = query_tmp2[0]
		for line in open('tmp/%s_%s_%s.Routput' % (self.session, self.paraList['species'], aspect)):
			list = line.strip().split('\t')
			if float(list[2]) < float(self.paraList['cutoff']):
				self.GO2stat[list[0]] = [list[1], list[2]] #self.GO2stat, key: GO, value: [pvalue, qvalue]
		
		for set_tmp in self.GO2stat:
			db_1 = self.desc[set_tmp].split(',    GOslim:')
			db_2 = db_1[0].split('   ')
			db = db_2[0]
			str1 = '%s %s' % (query, db)
			str2 = '%s %s' % (db, query)
			if (str1 in self.path or str2 in self.path):
				self.nodes[set] = 1
				self.nodes[set_tmp] = 1
				flag += 1
		
		if flag == 0:
			ferr = open('tmp/%s.%s.Nograph' % (self.session, randID), 'w')
			ferr.close()
			sys.exit()

		str = ''
		fout = open('tmp/%s.%s_%s.dot' % (self.session, randID, aspect), 'w')
		str += 'digraph{\ngraph[rankdir = "T"];\n'	
		for GO in self.nodes:
			words1 = self.desc[GO].split(',    GOslim:')
			words2 = words1[0].split('   ')
			str += '"%s (%.3g)\\n%s" [shape=box,fontname=Helvetica,fontsize=10,color="#000000",fillcolor="%s",style=filled];\n' % (words2[0], float(self.GO2stat[GO][1]), words2[1], self.color(float(self.GO2stat[GO][1])))
		
		for GO2 in self.nodes:
			db_1 = self.desc[GO2].split(',    GOslim:')
			db_2 = db_1[0].split('   ')
			db = db_2[0]
			str1 = '%s %s' % (query, db)
			str2 = '%s %s' % (db, query)
			if str1 in self.path:
				pav = float(self.GO2stat[set][1])
				cav = float(self.GO2stat[GO2][1])
				if pav <= float(self.paraList['cutoff']) and cav <= float(self.paraList['cutoff']): edgestyle = 'solid'
				elif pav > float(self.paraList['cutoff']) and cav > float(self.paraList['cutoff']): edgestyle = 'dotted'
				else: edgestyle = 'dashed'
				str += '"%s (%.3g)\\n%s" -> "%s (%.3g)\\n%s"[color="black",style="%s"];\n' % (query,pav,query_tmp2[1],db,cav,db_2[1],edgestyle)
			elif str2 in self.path:
				pav = float(self.GO2stat[GO2][1])
				cav = float(self.GO2stat[set][1])
				if pav <= float(self.paraList['cutoff']) and cav <= float(self.paraList['cutoff']): edgestyle = 'solid'
				elif pav > float(self.paraList['cutoff']) and cav > float(self.paraList['cutoff']): edgestyle = 'dotted'
				else: edgestyle = 'dashed'
				str += '"%s (%.3g)\\n%s" -> "%s (%.3g)\\n%s"[color="black",style="%s"];\n' % (db,pav,db_2[1],query,cav,query_tmp2[1],edgestyle)

		str += '}\n'
		fout.write(str)
		fout.close()
		self.path = {}
				
