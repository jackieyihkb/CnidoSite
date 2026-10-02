#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
CnidoSite —— 物种树数据重建工具
对应审稿意见：
  Referee 1 (major 5)  : 树的可读性 / 分类注释 / 多层级树
  Referee 3 (point 5)  : 无外群、错误定根 (Acropora)、分类注释缺失
  Referee 3 (point 3)  : WoRMS / GBIF 引用与 ID 溯源

功能
----
1. 读取 tools/cache/taxonomy.tsv（由 MySQL `cnidaria` 库 abbr ⋈ classfy 导出），
   为每个 tip 补齐 phylum / class / order / family / genus / abbr1 / NCBI / WoRMS / GBIF。
   —— 分类信息与站点其它页面 (browse.php, speciesinfo.php) 使用同一数据源，
      避免此前 taxonomy.json 与 classfy 不一致（13 个科冲突）的问题。
2. 从 tools/cache/cnidaria.nwk.orig-backup（分析用的原始 newick）出发，
   输出 ladderized newick 到 data/cnidaria.nwk，并写 data/taxonomy.json。
   —— 定根：若输入树自带非刺胞动物外群（见 OUTGROUPS），则以它定根，不再动人；
      若没有外群，才退回按 Anthozoa | (Medusozoa + Myxozoa) 人为重定根 ——
      后者是系统性推断，解读时应视为无根树。这条分支是给旧的 148-tip 树留的。
3. 额外导出各个分类层级的子树 newick 到 data/trees/ ，供网页"分分类层级提供子树"下载。

输入树上的 tip 名是分析时的标签，常比库里的物种名短（'Xenia_sp' vs
'Xenia sp. Carnegie-2017'）。resolve() 会按「树名是库名的词前缀」兜底匹配，
但要求唯一命中，并把结果打出来供核对；有歧义就宁可不注释。

用法
----
    mysql -u"$CNIDO_DB_USER" -p"$CNIDO_DB_PASS" -N --batch cnidaria -e "..." > tools/cache/taxonomy.tsv
    python3 tools/rebuild_tree_data.py --check          # 只做一致性体检，不写文件
    python3 tools/rebuild_tree_data.py                  # 重建 cnidaria.nwk + taxonomy.json + 子树
"""
import argparse
import json
import os
import re
import sys
from collections import Counter, defaultdict

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(HERE)
DATA = os.path.join(ROOT, 'data')
CACHE = os.path.join(HERE, 'cache')

# 重定根：这些 Class 归为新根的一侧，其余归另一侧
ROOT_CLADE_CLASSES = {'Hydrozoa', 'Scyphozoa', 'Cubozoa', 'Staurozoa', 'Myxozoa'}

# 非刺胞动物外群。分类表只收刺胞动物，所以这几条在这里手工补齐 —— 它们不参与
# 「重定根」（新树正是靠它们定根，见 main 里的检测），但要出现在 taxonomy.json 里，
# 否则页面上的标签会是带下划线的原始 tip 名，也没有门级注释。
# 只写到门：Porifera / Ctenophora 是门，硬塞一个 class/order 只会和颜色图例的
# 含义打架；留空即按「未分类」上色，正好把外群与刺胞动物区分开。
OUTGROUPS = {
    'Bolinopsis_microptera': ('Bolinopsis microptera', 'Ctenophora'),
    'Corticium_candelabrum': ('Corticium candelabrum', 'Porifera'),
    'Oscarella_lobularis':   ('Oscarella lobularis',   'Porifera'),
    'Halichondria_panicea':  ('Halichondria panicea',  'Porifera'),
    'Sycon_ciliatum':        ('Sycon ciliatum',        'Porifera'),
}


# --------------------------------------------------------------------------
# newick 解析 / 序列化（支持率写在内部节点名上，枝长写在 : 后面）
# --------------------------------------------------------------------------
class N:
    __slots__ = ('name', 'children', 'parent', 'len')

    def __init__(self):
        self.name = None
        self.children = []
        self.parent = None
        self.len = None


def parse(s):
    s = s.strip().rstrip(';')
    pos = [0]

    def node():
        n = N()
        if s[pos[0]] == '(':
            pos[0] += 1
            while True:
                c = node()
                c.parent = n
                n.children.append(c)
                if pos[0] >= len(s):
                    break
                if s[pos[0]] == ',':
                    pos[0] += 1
                    continue
                if s[pos[0]] == ')':
                    pos[0] += 1
                    break
                pos[0] += 1
        st = pos[0]
        while pos[0] < len(s) and s[pos[0]] not in '(),:;':
            pos[0] += 1
        lab = s[st:pos[0]].strip()
        if lab:
            n.name = lab
        if pos[0] < len(s) and s[pos[0]] == ':':          # 枝长（保留）
            pos[0] += 1
            st2 = pos[0]
            while pos[0] < len(s) and s[pos[0]] not in '(),;':
                pos[0] += 1
            n.len = s[st2:pos[0]].strip() or None
        return n

    return node()


def tips(n, out=None):
    out = [] if out is None else out
    if n.children:
        for c in n.children:
            tips(c, out)
    else:
        out.append(n)
    return out


def ntips(n):
    return 1 if not n.children else sum(ntips(c) for c in n.children)


def ladderize(n):
    if n.children:
        for c in n.children:
            ladderize(c)
        n.children.sort(key=ntips, reverse=True)


def ser(n):
    """序列化。枝长保留（新树带枝长，页面是 cladogram 布局不看它，但下载的
    Newick 里应当留全）；重定根时会被 strip_lengths() 清掉 —— 见 reroot_on。"""
    lab = (n.name or '') + ((':' + n.len) if n.len else '')
    if n.children:
        return '(' + ','.join(ser(c) for c in n.children) + ')' + lab
    return lab


def strip_lengths(n):
    n.len = None
    for c in n.children:
        strip_lengths(c)


def clone(n, parent=None):
    m = N()
    m.name = n.name
    m.parent = parent
    for c in n.children:
        m.children.append(clone(c, m))
    return m


def reroot_on(root, node):
    """把 `node` 所在的枝重定为新的根（node 与"其余所有"成为根的两个子支）。"""
    if node is root:
        return root
    chain = []
    cur = node
    while cur.parent is not None:
        p = cur.parent
        chain.append((p, [c for c in p.children if c is not cur]))
        cur = p
    rest = None
    for _p, sibs in reversed(chain):
        if rest is not None:
            sibs = sibs + [rest]
        if len(sibs) == 1:
            rest = sibs[0]
        else:
            nn = N()
            nn.children = sibs
            for s in sibs:
                s.parent = nn
            rest = nn
    nr = N()
    nr.children = [node, rest]
    node.parent = nr
    rest.parent = nr
    return nr


def find_node(root, predicate):
    if predicate(root):
        return root
    for c in root.children:
        r = find_node(c, predicate)
        if r is not None:
            return r
    return None


def edges(root):
    """返回 [(tipset, node)]，含根节点与所有内部/叶节点。"""
    out = []

    def w(n):
        out.append((frozenset(t.name for t in tips(n)), n))
        for c in n.children:
            w(c)

    w(root)
    return out


def monophyly_report(root, tax):
    """检查 class/order/family 是否为树上的单系（edge split）。"""
    splits = {s for s, _ in edges(root)}
    rep = {}
    for lvl, key in (('class', 'class'), ('order', 'order'), ('family', 'family')):
        groups = defaultdict(set)
        for tip, v in tax.items():
            if v.get(key):
                groups[v[key]].add(tip)
        bad = sorted(g for g, m in groups.items() if len(m) >= 2 and frozenset(m) not in splits)
        rep[lvl] = bad
    return rep


# --------------------------------------------------------------------------
def load_taxonomy_tsv(path):
    """species, abbr, abbr1, phylum, class, order, family, genus, ncbi, worms, gbif"""
    rows = []
    with open(path, encoding='utf-8') as fh:
        for line in fh:
            f = line.rstrip('\n').split('\t')
            if len(f) < 12:
                continue
            rows.append(dict(zip(
                ('species', 'abbr', 'abbr1', 'phylum', 'cls', 'order', 'family',
                 'genus', 'binomial', 'ncbi', 'worms', 'gbif'), f)))
    return rows


def clean(v):
    v = (v or '').strip()
    return '' if v in ('', '-', 'NA', 'N/A', 'None') else v


def build_lookup(rows):
    """tip 标签（下划线名）-> 记录。同时兼容连字符/下划线差异。"""
    by_abbr, by_spaced = {}, {}
    for r in rows:
        if clean(r['abbr']):
            by_abbr[r['abbr']] = r
        if clean(r['species']):
            by_spaced[r['species'].replace(' ', '_')] = r
    by_tokens = [(tokens(r['species']), r) for r in rows if clean(r['species'])]
    return by_abbr, by_spaced, by_tokens


def tokens(s):
    """比较用的词序列：小写、下划线当空格、去掉标点。'sp.'/'cf.'/'complex' 这些
    词是有意义的，不能丢 —— 库里的 'Aurelia aurita' 与 'Aurelia aurita complex
    sp. Pacific' 正是靠 'complex' 才分得开。"""
    s = re.sub(r'[^A-Za-z0-9]+', ' ', s.lower())
    return s.split()


def resolve(tip, by_abbr, by_spaced, by_tokens=None):
    if tip in by_abbr:
        return by_abbr[tip]
    if tip in by_spaced:
        return by_spaced[tip]
    alt = tip.replace('-', '_')
    if alt in by_abbr:
        return by_abbr[alt]
    if alt in by_spaced:
        return by_spaced[alt]
    # 树里的标签是分析时的原始名，比库里的物种名短：库里是
    # 'Paraphelliactis xishaensis sp. nov.'、'Xenia sp. Carnegie-2017'、
    # 'Aurelia sp. 4 Dawson et al 2005'，树上只写 'Paraphelliactis_xishaensis'、
    # 'Xenia_sp'、'Aurelia_sp_4'。按「树名是库名的词前缀」再匹配一次，但要求
    # 唯一命中 —— 有歧义就当没匹配上（宁可少注释，也不能张冠李戴）。
    if by_tokens:
        want = tokens(tip)
        hits = [r for tk, r in by_tokens if tk[:len(want)] == want]
        if len(hits) == 1:
            return hits[0]
    return None


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--in', dest='inp', default=os.path.join(CACHE, 'cnidaria.nwk.orig-backup'))
    ap.add_argument('--tax', default=os.path.join(CACHE, 'taxonomy.tsv'))
    ap.add_argument('--check', action='store_true', help='只做体检，不写文件')
    ap.add_argument('--no-reroot', action='store_true')
    a = ap.parse_args()

    rows = load_taxonomy_tsv(a.tax)
    by_abbr, by_spaced, by_tokens = build_lookup(rows)
    print('分类表记录数: %d' % len(rows))

    root = parse(open(a.inp, encoding='utf-8').read())
    alltips = [t.name for t in tips(root)]
    print('输入树 tips: %d' % len(alltips))

    # ---- tip -> taxonomy ----
    tax = {}
    unmatched = []
    short = []                       # 靠「词前缀」匹配上的，打出来供人核对
    for t in alltips:
        r = resolve(t, by_abbr, by_spaced, by_tokens)
        if r is None:
            if t in OUTGROUPS:       # 非刺胞动物外群：手工补门级注释
                disp, phylum = OUTGROUPS[t]
                tax[t] = {
                    'display': disp, 'abbr': '', 'abbr1': '',
                    'phylum': phylum,
                    'class': '', 'order': '', 'family': '',
                    'genus': disp.split()[0],
                    'ncbi': '', 'worms_id': '', 'gbif_id': '',
                    'ncbi_url': '', 'worms': '', 'gbif_url': '',
                    'outgroup': True,
                }
                continue
            unmatched.append(t)
            continue
        if (t not in by_abbr and t not in by_spaced
                and t.replace('-', '_') not in by_abbr and t.replace('-', '_') not in by_spaced):
            short.append((t, r['species']))
        worms_id = clean(r['worms'])
        ncbi_id = clean(r['ncbi'])
        gbif_id = clean(r['gbif'])
        tax[t] = {
            'display': clean(r['species']) or t.replace('_', ' '),
            'abbr': clean(r['abbr']),
            'abbr1': clean(r['abbr1']),
            'phylum': clean(r['phylum']) or 'Cnidaria',
            'class': clean(r['cls']),
            'order': clean(r['order']),
            'family': clean(r['family']),
            'genus': clean(r['genus']),
            'ncbi': ncbi_id,
            'worms_id': worms_id,
            'gbif_id': gbif_id,
            'ncbi_url': ('https://www.ncbi.nlm.nih.gov/Taxonomy/Browser/wwwtax.cgi?id=' + ncbi_id) if ncbi_id else '',
            # 与站点其它页面一致，使用物种级 WoRMS taxdetails 链接（此前是属级搜索链接）
            'worms': ('https://www.marinespecies.org/aphia.php?p=taxdetails&id=' + worms_id) if worms_id else '',
            'gbif_url': ('https://www.gbif.org/species/' + gbif_id) if gbif_id else '',
        }
    if unmatched:
        print('!! 未匹配到分类信息的 tip (%d): %s' % (len(unmatched), ', '.join(unmatched[:10])))
    if short:
        print('树上的短标签按词前缀匹配到库中物种 (%d) —— 请核对：' % len(short))
        for t, sp in short:
            print('   %-30s -> %s' % (t, sp))
    # 外群本来就没有 abbr1（它们不在 cnidaria 库里），不算异常。
    missing_abbr1 = [t for t, v in tax.items() if not v['abbr1'] and not v.get('outgroup')]
    if missing_abbr1:
        print('!! 缺少 abbr1（无法生成物种页链接）: %d 个' % len(missing_abbr1))
    print('成功注释 %d / %d tips' % (len(tax), len(alltips)))

    # ---- 定根 ----
    # 输入树自带外群时**不能**再按「刺胞动物分界」重定根：那一步是为了在没有外群
    # 的情况下人为挑一个根位置，而外群已经给出了数据支持的根，再重定一次会把外群
    # 折进树里、根也跟着错位。
    outgroup_tips = [t for t in alltips if t in OUTGROUPS]
    do_reroot = (not a.no_reroot) and (not outgroup_tips)
    if outgroup_tips and not a.no_reroot:
        print('检测到 %d 个非刺胞动物外群（%s）：根由外群定出，跳过人为重定根。'
              % (len(outgroup_tips), ', '.join(sorted(outgroup_tips))))

    before = monophyly_report(root, tax)
    print('\n[%s] 非单系类群: class=%s order=%s family=%s'
          % ('重定根前' if do_reroot else '输入的树', before['class'], before['order'], before['family']))
    print('  根的两个子支: %s' % [repr(c.name) for c in root.children])

    if do_reroot:
        target = find_node(root, lambda n: (
            len(tips(n)) > 1 and
            {tax[t.name]['class'] for t in tips(n) if t.name in tax} == ROOT_CLADE_CLASSES
        ))
        if target is None:
            print('!! 未找到用于重定根的 clade，保持原树')
        else:
            newroot = reroot_on(root, target)
            newroot.name = None
            # 重定根会造出新的根节点与其两侧的枝，原有枝长不再对应任何真实枝 ——
            # 全部丢掉，免得导出带枝长的 Newick 时给出错的数。
            strip_lengths(newroot)
            ladderize(newroot)
            after = monophyly_report(newroot, tax)
            print('[重定根后] 非单系类群: class=%s order=%s family=%s'
                  % (after['class'], after['order'], after['family']))
            print('  根的两个子支 tip 数: %s -> %s'
                  % ([len(tips(c)) for c in newroot.children],
                     [sorted({tax[t.name]['class'] for t in tips(c) if t.name in tax})
                      for c in newroot.children]))
            print('  （重定根后枝长已清除）')
            root = newroot
    else:
        ladderize(root)

    out_nwk = ser(root) + ';\n'

    if a.check:
        print('\n--check 模式：未写入任何文件')
        return

    if outgroup_tips:
        og_phyla = sorted({OUTGROUPS[t][1] for t in outgroup_tips})
        _rooting = ('由非刺胞动物外群定根：%s（%s）共 %d 个 tip 位于根的另一侧，'
                    '根的位置由数据给出，无需人为指定。'
                    % ('、'.join(og_phyla), '、'.join(sorted(outgroup_tips)), len(outgroup_tips)))
    else:
        _rooting = ('以 Anthozoa | (Medusozoa + Myxozoa) 分界定根（无可用外群，'
                    '根位置为系统性推断，解读时应视为无根树）。')

    with open(os.path.join(DATA, 'cnidaria.nwk'), 'w', encoding='utf-8') as fh:
        fh.write(out_nwk)
    with open(os.path.join(DATA, 'taxonomy.json'), 'w', encoding='utf-8') as fh:
        json.dump({
            '_note': ('class/order/family/phylum 与 NCBI/WoRMS/GBIF ID 均取自 CnidoSite '
                      'MySQL `cnidaria` 库的 abbr ⋈ classfy 表，与 browse.php / speciesinfo.php 同源；'
                      '由 tools/rebuild_tree_data.py 生成。'),
            '_rooting': _rooting,
            'taxa': tax,
        }, fh, ensure_ascii=False, indent=1)
    print('\n已写出 data/cnidaria.nwk 与 data/taxonomy.json')

    # ---- 分分类层级子树 ----
    trees_dir = os.path.join(DATA, 'trees')
    os.makedirs(trees_dir, exist_ok=True)
    for f in os.listdir(trees_dir):
        os.remove(os.path.join(trees_dir, f))
    manifest = {}
    for lvl, key in (('class', 'class'), ('order', 'order'), ('family', 'family')):
        groups = defaultdict(list)
        for t in tips(root):
            v = tax.get(t.name)
            if v and v.get(key):
                groups[v[key]].append(t.name)
        for name, members in sorted(groups.items()):
            if len(members) < 2:
                continue
            sub = find_node(root, lambda n, mem=set(members): set(t.name for t in tips(n)) == mem)
            if sub is None:
                continue                       # 该分类阶元在树上非单系，跳过
            fname = '%s__%s.nwk' % (lvl, name.replace(' ', '_').replace('/', '_'))
            with open(os.path.join(trees_dir, fname), 'w', encoding='utf-8') as fh:
                fh.write(ser(sub) + ';\n')
            manifest.setdefault(lvl, []).append(
                {'name': name, 'file': fname, 'ntips': len(members)})
    with open(os.path.join(DATA, 'trees', 'manifest.json'), 'w', encoding='utf-8') as fh:
        json.dump(manifest, fh, ensure_ascii=False, indent=1)
    n = sum(len(v) for v in manifest.values())
    print('已写出 data/trees/ 下 %d 个分类层级子树' % n)
    for lvl in manifest:
        print('   %-7s %d 个' % (lvl, len(manifest[lvl])))


if __name__ == '__main__':
    main()
