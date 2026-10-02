#!/usr/bin/python
import os, sys, re, urllib2, threading, operator
from random import uniform

class GSEA_analysis:
	'''The class is used to do the GeneSet Enrichment Analysis
	'''

	def __init__(self, session):
		self.session = session
		self.query = []
		self.genes_Group = {}
		self.geneSets_Group = {}
		self.paraList = {}
		self.overlap_num = {}
		self.geneSet_geNum = {}
		self.geneSet_desp = {}
		self.overlap = {}
		self.customizedDB = []
		self.tmpGeneList = []
		self.locus2set = {}
		self.category = []

	def fetchparam(self):
		for line in open('tmp/%s.conf' % self.session):   # fetch para
			list = line.split()
			if len(list) == 2:
				self.paraList[list[0]] = list[1]

		for line in open('Category_Num.txt'):
			list = line.strip().split('\t')
			self.genes_Group[list[0]] = list[1]
			self.geneSets_Group[list[0]] = list[2]

		for line in open('tmp/%s.query' % self.session):
			self.query.append(line.strip())

	def OverlapAnalysis(self, grp_type):
		"""	compute overlap and create RInputFile file
		"""
		# 1) create RInputFile for each category
		flag = 0
		for line in open('database/%s' % grp_type):
			match = 0
			self.locus2set = {}
			list=line.strip().split('\t')
			self.geneSet_desp[list[0]] = list[1]
			for locus in list[2].split(','):
				self.locus2set[locus.upper()] = list[0]
			for li in self.query:
				if li.upper() in self.locus2set.keys():
					match += 1	
					if list[0] not in self.overlap:
						self.overlap[list[0]] = [li]
					else:
						self.overlap[list[0]].append(li)
			if match == 0:
				pass
			else:
				flag +=1
				Rinput = '%s\t%s\t%s\t%s\t%s\n' % (list[0],match,len(self.query),len(list[2].split(',')),self.genes_Group[grp_type] ) 
				open('tmp/%s_%s.RInputFile' % (self.session,grp_type), 'a+').write(Rinput)
				self.overlap_num[list[0]] = match
				self.geneSet_geNum[list[0]] = len(list[2].split(','))

		if flag == 0:
			ferr = open('tmp/%s_%s.NullResult' % (self.session,grp_type), 'w')
			ferr.close()

		# 2)create R command files
		self.Rcommandwrite(grp_type)

	def CustomizedOverlapAnalysis(self, grp_type):
		"""	compute overlap and create tmp database
		"""
		# create tmp database for each category
		for line in open('tmp/%s.background' % self.session):
			self.customizedDB.append(line.strip())

		flag = 0
		for line in open('database/%s' % grp_type):
			self.tmplocus2set = {}
			self.locus2set = {}
			match = 0
			list=line.strip().split('\t')
			self.geneSet_desp[list[0]] = list[1]
			for locus in list[2].split(','):
				self.locus2set[locus.upper()] = list[0]
			for li in self.customizedDB:
				if li.upper() in self.locus2set.keys():
					self.tmplocus2set[li.upper()] = list[0]
				
			for li2 in self.query:
				if li2.upper() in self.tmplocus2set.keys():
					match += 1
					if list[0] not in self.overlap:
						self.overlap[list[0]] = [li2]
					else:
						self.overlap[list[0]].append(li2)
			if match == 0:
				pass
			else:
				flag +=1
				Rinput = '%s\t%s\t%s\t%s\t%s\n' % (list[0],match,len(self.query),len(self.tmplocus2set.keys()),len(self.customizedDB) )
				open('tmp/%s_%s.RInputFile' % (self.session,grp_type), 'a+').write(Rinput)
				self.overlap_num[list[0]] = match
				self.geneSet_geNum[list[0]] = len(self.tmplocus2set.keys())

		if flag == 0:
			ferr = open('tmp/%s_%s.NullResult' % (self.session,grp_type), 'w')
			ferr.close()

		# 2)create R command files
		self.Rcommandwrite(grp_type)

	def Rcommandwrite(self, grp_type):
		fout = open('tmp/%s_%s.RcommandFile' % (self.session,grp_type), 'w')
		tm = self.paraList['testMethod']
		method = {'dhyper': 'mat2<-matrix(c(d[i,2],d[i,4],d[i,3]-d[i,2],d[i,5]-d[i,4]),nrow=2)\npv[i]<-fisher.test(mat2,alternative="greater")$p.value\n',
		'fisher': 'mat2<-matrix(c(d[i,2],d[i,4],d[i,3]-d[i,2],d[i,5]-d[i,4]),nrow=2)\npv[i]<-fisher.test(mat2,alternative="greater")$p.value\n',
		'chi2': 'mat2<-matrix(c(d[i,2],d[i,4],d[i,3]-d[i,2],d[i,5]-d[i,4]),nrow=2)\npv[i]<-chisq.test(mat2)$p.value\nif(d[i,2]/d[i,3]<d[i,4]/d[i,5]){pv[i]=1}\n',
		}
		str="""
		d<-read.delim("tmp/%s_%s.RInputFile", header=F)
		pv<-array(dim=dim(d)[1])
		for(i in 1:dim(d)[1]){
		%s
		}
		pa<-p.adjust(pv,"%s")
		output<-list(d[,1], pv, pa)
		write.table(output,file="tmp/%s_%s.Routput",col.names=FALSE,row.names=FALSE,sep="\\t",quote=F)
		""" % (self.session,grp_type,method[tm],self.paraList['mt'],self.session,grp_type)
		fout.write(str)
		fout.close()		

	def RstatisticAnalysis(self):
		# call R, using threads to shorten process time
		for line in open('tmp/%s.category' % self.session):
			self.category.append(line.strip())
		threads = []
		nloops = range(len(self.category))
		
		for i in self.category:
			t = threading.Thread(target=self.callR, args=(self.session, i))
			threads.append(t)
		
		for i in nloops:
			threads[i].start()
	
		for i in nloops:
			threads[i].join()
		
	def callR(self, session, grp_type):
		os.system('/usr/local/bin/R CMD BATCH tmp/%s_%s.RcommandFile tmp/%s_%s.Rout' % (self.session,grp_type,self.session,grp_type))

	def detail(self, grp_type):
		if os.path.exists('tmp/%s_%s.Routput' % (self.session,grp_type)):
			for line in open('tmp/%s_%s.Routput' % (self.session,grp_type) ):
				list=line.strip().split('\t')
				detail = '%s\t%s\t%s\t%s\t%s\t%s\t%s\n' % (list[0],self.geneSet_geNum[list[0]],self.geneSet_desp[list[0]],self.overlap_num[list[0]],list[1],list[2],self.overlap[list[0]] )
				open('tmp/%s.detail' % (self.session), 'a+').write(detail)
