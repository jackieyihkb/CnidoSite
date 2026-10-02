#!/usr/bin/python
import os, sys
session = sys.argv[1]
if os.path.isfile('tmp/%s.file' % session):
	print "Yahoo!"
