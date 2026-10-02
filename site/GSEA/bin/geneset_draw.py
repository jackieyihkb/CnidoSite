# This module is used to draw geneset images.
# Required: gdmodule --- python module; GD -- gdlib
# Optional: xxx.ttf --- customized font

'''This module is used to draw images.
''' 

import gd, os 
os.environ["GDFONTPATH"] = "."
FONT = "verdana"
FONT_BOLD = "verdanab"
setlist = []
num = 1

for line in open('set.txt'):
 setlist.append(line.strip())

for Setname in setlist:
 im = gd.image((800, 25))
 white = im.colorAllocate((255, 255, 255))
 blue = im.colorAllocate((0, 0, 255))
 im.string_ttf(FONT_BOLD, 11.0, 0.0, (2, 17), Setname, blue)

 f=open('img/%s.png' % num, 'w')
 im.writePng(f)
 f.close()
 num += 1
