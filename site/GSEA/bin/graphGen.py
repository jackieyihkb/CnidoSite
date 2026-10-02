#!/usr/bin/python
import os, sys, random
from graphGen_class import *

session = sys.argv[1]
randID = sys.argv[2]
aspect = sys.argv[3]
set = sys.argv[4]
job = Graph_generation(session)
job.fetchparam(aspect)
job.generalpic(aspect,randID,set)

os.system("/usr/local/bin/dot -T png tmp/%s.%s_%s.dot -o tmp/%s.%s.png" % (session,randID,aspect,session,randID) )

