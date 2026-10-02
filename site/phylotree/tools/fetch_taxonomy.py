#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
从 WoRMS 刷新 data/taxonomy.json 的 class/order/family（GBIF 兜底）。
部署到服务器后运行（需要外网）：

    python3 tools/fetch_taxonomy.py

只覆盖能查到的条目，查不到的保留原值；结果写入 data/taxonomy.json。
"""
import json, re, os, sys, time, urllib.request, urllib.parse

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(HERE)
NWK  = os.path.join(ROOT, 'data', 'cnidaria.nwk')
TAX  = os.path.join(ROOT, 'data', 'taxonomy.json')
UA   = {'User-Agent': 'CnidoSite/1.0 (phylogeny taxonomy sync)'}
RANKS = ('class', 'order', 'family')


def get_json(url, timeout=25):
    req = urllib.request.Request(url, headers=UA)
    with urllib.request.urlopen(req, timeout=timeout) as r:
        return json.loads(r.read().decode('utf-8'))


def tip_names(nwk_path):
    s = open(nwk_path).read().strip().rstrip(';')
    return [m for m in re.findall(r'[(,]\s*([^(),:;]+)\s*(?=[,)])', s) if m]


def from_worms(name):
    """返回 (class, order, family) 或 None"""
    sp = name.replace('_', ' ')
    genus = sp.split(' ')[0]
    for q in (sp, genus):   # 种查不到就退回属
        if not q:
            continue
        try:
            url = ('https://www.marinespecies.org/rest/AphiaRecordsByName/'
                   + urllib.parse.quote(q) + '?like=false&offset=1')
            recs = get_json(url)
        except Exception:
            continue
        if not recs:
            continue
        # 优先取 status 为 accepted 且 rank 合理者
        best = None
        for r in recs:
            if r.get('class') and (r.get('family') or r.get('order')):
                if r.get('status') == 'accepted':
                    best = r
                    break
                if best is None:
                    best = r
        if best:
            return (best.get('class'), best.get('order'), best.get('family'))
        if q == genus:
            return None
        time.sleep(0.3)
    return None


def from_gbif(name):
    sp = name.replace('_', ' ')
    try:
        url = 'https://api.gbif.org/v1/species/match?name=' + urllib.parse.quote(sp)
        d = get_json(url)
        if d.get('matchType') in ('EXACT', 'FUZZY'):
            return (d.get('class'), d.get('order'), d.get('family'))
    except Exception:
        pass
    return None


def main():
    tax = json.load(open(TAX, encoding='utf-8'))
    taxa = tax.get('taxa', tax)
    names = tip_names(NWK)
    upd = miss = 0
    for i, nm in enumerate(names, 1):
        got = from_worms(nm) or from_gbif(nm)
        if got and all(got):
            cls, order, fam = got
            e = taxa.setdefault(nm, {'genus': nm.split('_')[0], 'class': '', 'order': '',
                                     'family': '', 'display': nm.replace('_', ' '),
                                     'worms': 'https://www.marinespecies.org/aphia.php?p=taxdetails&tName='
                                              + nm.split('_')[0]})
            if (e.get('class'), e.get('order'), e.get('family')) != (cls, order, fam):
                upd += 1
            e['class'], e['order'], e['family'] = cls, order, fam
        else:
            miss += 1
        if i % 20 == 0:
            print('  %d/%d ...' % (i, len(names)), flush=True)
        time.sleep(0.35)          # 对 WoRMS 友好
    tax['_note'] = ('class/order/family refreshed from WoRMS (GBIF fallback) by '
                    'tools/fetch_taxonomy.py')
    tax['taxa'] = taxa
    json.dump(tax, open(TAX, 'w', encoding='utf-8'), indent=1, ensure_ascii=False)
    print('完成：更新 %d 条，未匹配 %d 条 -> %s' % (upd, miss, TAX))


if __name__ == '__main__':
    main()
