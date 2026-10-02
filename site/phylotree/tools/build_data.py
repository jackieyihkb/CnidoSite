# -*- coding: utf-8 -*-
"""解析 newick -> ladderize -> 输出 cnidaria.nwk + taxonomy.json"""
import re, json, sys

class N:
    __slots__=('name','length','children','parent','id')
    def __init__(self): self.name=None; self.length=None; self.children=[]; self.parent=None; self.id=None

def parse(s):
    s=s.strip().rstrip(';')
    pos=[0]
    def node():
        n=N()
        if s[pos[0]]=='(':
            pos[0]+=1
            while True:
                c=node(); c.parent=n; n.children.append(c)
                if pos[0]>=len(s): break
                if s[pos[0]]==',': pos[0]+=1; continue
                if s[pos[0]]==')': pos[0]+=1; break
                raise ValueError('bad char %r at %d'%(s[pos[0]],pos[0]))
        st=pos[0]
        while pos[0]<len(s) and s[pos[0]] not in '(),:;': pos[0]+=1
        lab=s[st:pos[0]].strip()
        if lab: n.name=lab
        if pos[0]<len(s) and s[pos[0]]==':':
            pos[0]+=1; st=pos[0]
            while pos[0]<len(s) and s[pos[0]] not in '(),;': pos[0]+=1
            n.length=s[st:pos[0]].strip()
        return n
    return node()

def tips(n):
    if not n.children: return [n]
    out=[]
    for c in n.children: out.extend(tips(c))
    return out

def ntips(n):
    if not n.children: return 1
    return sum(ntips(c) for c in n.children)

def ladderize(n):
    if n.children:
        for c in n.children: ladderize(c)
        n.children.sort(key=ntips, reverse=True)

def ser(n):
    if n.children:
        out='('+','.join(ser(c) for c in n.children)+')'+(n.name or '')
    else:
        out=(n.name or '')
    if n.length: out+=':'+n.length
    return out

# ---------- 分类阶元（刺胞动物系统分类；部署前请用 tools/fetch_taxonomy.py 以 WoRMS 覆盖） ----------
G = {
 'Acropora':('Anthozoa','Scleractinia','Acroporidae'),
 'Montipora':('Anthozoa','Scleractinia','Acroporidae'),
 'Astreopora':('Anthozoa','Scleractinia','Acroporidae'),
 'Actinernus':('Anthozoa','Actiniaria','Actinernidae'),
 'Edwardsia':('Anthozoa','Actiniaria','Edwardsiidae'),
 'Scolanthus':('Anthozoa','Actiniaria','Edwardsiidae'),
 'Nematostella':('Anthozoa','Actiniaria','Edwardsiidae'),
 'Actinia':('Anthozoa','Actiniaria','Actiniidae'),
 'Anthopleura':('Anthozoa','Actiniaria','Actiniidae'),
 'Condylactis':('Anthozoa','Actiniaria','Actiniidae'),
 'Paracondylactis':('Anthozoa','Actiniaria','Actiniidae'),
 'Actinoscyphia':('Anthozoa','Actiniaria','Actinoscyphiidae'),
 'Actinostola':('Anthozoa','Actiniaria','Actinostolidae'),
 'Alvinactis':('Anthozoa','Actiniaria','Actinostolidae'),
 'Exaiptasia':('Anthozoa','Actiniaria','Aiptasiidae'),
 'Diadumene':('Anthozoa','Actiniaria','Diadumenidae'),
 'Paraphelliactis':('Anthozoa','Actiniaria','Hormathiidae'),
 'Telmatactis':('Anthozoa','Actiniaria','Hormathiidae'),
 'Metridium':('Anthozoa','Actiniaria','Metridiidae'),
 'Alatina':('Cubozoa','Carybdeida','Alatinidae'),
 'Tripedalia':('Cubozoa','Carybdeida','Tripedaliidae'),
 'Morbakka':('Cubozoa','Carybdeida','Carukiidae'),
 'Aurelia':('Scyphozoa','Semaeostomeae','Ulmaridae'),
 'Cassiopea':('Scyphozoa','Rhizostomeae','Cassiopeidae'),
 'Catostylus':('Scyphozoa','Rhizostomeae','Catostylidae'),
 'Mastigias':('Scyphozoa','Rhizostomeae','Mastigiidae'),
 'Nemopilema':('Scyphozoa','Rhizostomeae','Rhizostomatidae'),
 'Rhopilema':('Scyphozoa','Rhizostomeae','Rhizostomatidae'),
 'Chrysaora':('Scyphozoa','Semaeostomeae','Pelagiidae'),
 'Sanderia':('Scyphozoa','Semaeostomeae','Pelagiidae'),
 'Pelagia':('Scyphozoa','Semaeostomeae','Pelagiidae'),
 'Bougainvillia':('Hydrozoa','Anthoathecata','Bougainvilliidae'),
 'Nanomia':('Hydrozoa','Siphonophorae','Agalmatidae'),
 'Hydractinia':('Hydrozoa','Anthoathecata','Hydractiniidae'),
 'Turritopsis':('Hydrozoa','Anthoathecata','Oceaniidae'),
 'Clytia':('Hydrozoa','Leptothecata','Campanulariidae'),
 'Candelabrum':('Hydrozoa','Anthoathecata','Candelabridae'),
 'Hydra':('Hydrozoa','Anthoathecata','Hydridae'),
 'Millepora':('Hydrozoa','Anthoathecata','Milleporidae'),
 'Calvadosia':('Staurozoa','Stauromedusae','Kishinouyeidae'),
 'Haliclystus':('Staurozoa','Stauromedusae','Haliclystidae'),
 'Henneguya':('Myxozoa','Bivalvulida','Myxobolidae'),
 'Myxobolus':('Myxozoa','Bivalvulida','Myxobolidae'),
 'Thelohanellus':('Myxozoa','Bivalvulida','Myxobolidae'),
 'Callogorgia':('Anthozoa','Malacalcyonacea','Primnoidae'),
 'Chrysogorgia':('Anthozoa','Malacalcyonacea','Chrysogorgiidae'),
 'Dendronephthya':('Anthozoa','Malacalcyonacea','Nephtheidae'),
 'Eunicella':('Anthozoa','Malacalcyonacea','Gorgoniidae'),
 'Trachythela':('Anthozoa','Malacalcyonacea','Alcyoniidae'),
 'Leptogorgia':('Anthozoa','Malacalcyonacea','Gorgoniidae'),
 'Paramuricea':('Anthozoa','Malacalcyonacea','Paramuriceidae'),
 'Muricea':('Anthozoa','Malacalcyonacea','Plexauridae'),
 'Xenia':('Anthozoa','Malacalcyonacea','Xeniidae'),
 'Hemicorallium':('Anthozoa','Scleralcyonacea','Coralliidae'),
 'Paragorgia':('Anthozoa','Scleralcyonacea','Paragorgiidae'),
 'Pteroeides':('Anthozoa','Pennatulacea','Pennatulidae'),
 'Heliopora':('Anthozoa','Scleralcyonacea','Helioporidae'),
 'Palythoa':('Anthozoa','Zoantharia','Sphenopidae'),
 'Plumapathes':('Anthozoa','Antipatharia','Myriopathidae'),
 'Catalaphyllia':('Anthozoa','Scleractinia','Merulinidae'),
 'Orbicella':('Anthozoa','Scleractinia','Merulinidae'),
 'Platygyra':('Anthozoa','Scleractinia','Merulinidae'),
 'Echinopora':('Anthozoa','Scleractinia','Merulinidae'),
 'Micromussa':('Anthozoa','Scleractinia','Lobophylliidae'),
 'Cyphastrea':('Anthozoa','Scleractinia','Merulinidae'),
 'Meandrina':('Anthozoa','Scleractinia','Meandrinidae'),
 'Oculina':('Anthozoa','Scleractinia','Oculinidae'),
 'Madracis':('Anthozoa','Scleractinia','Pocilloporidae'),
 'Astrangia':('Anthozoa','Scleractinia','Rhizangiidae'),
 'Podabacia':('Anthozoa','Scleractinia','Fungiidae'),
 'Colpophyllia':('Anthozoa','Scleractinia','Mussidae'),
 'Dendrogyra':('Anthozoa','Scleractinia','Meandrinidae'),
 'Blastomussa':('Anthozoa','Scleractinia','Mussidae'),
 'Pocillopora':('Anthozoa','Scleractinia','Pocilloporidae'),
 'Fimbriaphyllia':('Anthozoa','Scleractinia','Merulinidae'),
 'Siderastrea':('Anthozoa','Scleractinia','Siderastreidae'),
 'Cladopsammia':('Anthozoa','Scleractinia','Dendrophylliidae'),
 'Dendrophyllia':('Anthozoa','Scleractinia','Dendrophylliidae'),
 'Porites':('Anthozoa','Scleractinia','Poritidae'),
 'Desmophyllum':('Anthozoa','Scleractinia','Caryophylliidae'),
 'Lophelia':('Anthozoa','Scleractinia','Caryophylliidae'),
 'Stylophora':('Anthozoa','Scleractinia','Pocilloporidae'),
 'Rhodactis':('Anthozoa','Corallimorpharia','Discosomidae'),
 'Ricordea':('Anthozoa','Corallimorpharia','Ricordeidae'),
 'Stephanocoenia':('Anthozoa','Scleractinia','Astrocoeniidae'),
 'Duncanopsammia':('Anthozoa','Scleractinia','Dendrophylliidae'),
 'Tubastraea':('Anthozoa','Scleractinia','Dendrophylliidae'),
 'Turbinaria':('Anthozoa','Scleractinia','Dendrophylliidae'),
 'Leptoseris':('Anthozoa','Scleractinia','Agariciidae'),
 'Galaxea':('Anthozoa','Scleractinia','Euphylliidae'),
 'Pachyseris':('Anthozoa','Scleractinia','Agariciidae'),
}

root=parse(open('data/raw.nwk').read())
tipnames=[t.name for t in tips(root)]
missing=[t for t in tipnames if t.split('_')[0] not in G]
if missing:
    print('!! 未匹配属:', missing); 

tax={}
for t in tipnames:
    g=t.split('_')[0]
    cls,order,fam=G.get(g,('Unassigned','Unassigned','Unassigned'))
    disp=t.replace('_',' ')
    tax[t]={'genus':g,'class':cls,'order':order,'family':fam,
            'display':disp,
            'worms':'https://www.marinespecies.org/aphia.php?p=taxdetails&searchpar=0&tNameselect=on&tName='+g}

ladderize(root)
open('data/cnidaria.nwk','w').write(ser(root)+';\n')
json.dump({'_note':'generated by tools/build_data.py; run tools/fetch_taxonomy.py to refresh from WoRMS',
           'taxa':tax}, open('data/taxonomy.json','w'), indent=1, ensure_ascii=False)

# 报告：Acropora 在原始顺序与 ladderize 后的分布
def order_list(n,out):
    if not n.children: out.append(n.name)
    else:
        for c in n.children: order_list(c,out)
    return out
r2=parse(open('data/raw.nwk').read()); a=order_list(r2,[])
idx1=[i for i,x in enumerate(a) if x.startswith('Acropora')]
r3=parse(open('data/cnidaria.nwk').read()); b=order_list(r3,[])
idx2=[i for i,x in enumerate(b) if x.startswith('Acropora')]
print('原始顺序 Acropora 位置:', idx1)
print('ladderize 后位置     :', idx2)
def blocks(ix):
    bl=1
    for i in range(1,len(ix)):
        if ix[i]!=ix[i-1]+1: bl+=1
    return bl
print('Acropora 分散块数 原始=%d  ladderize后=%d  (tip总数=%d)'%(blocks(idx1),blocks(idx2),len(a)))
from collections import Counter
print('纲分布:', dict(Counter(v['class'] for v in tax.values())))
print('目数:', len(set(v['order'] for v in tax.values())), ' 科数:', len(set(v['family'] for v in tax.values())))
