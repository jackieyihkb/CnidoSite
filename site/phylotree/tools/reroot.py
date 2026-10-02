#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
对 newick 重定根（把指定类群作为外群），并可选 ladderize。
重建好树之后用它把根放到 Anthozoa 干支 / 指定外群。

用法：
    python3 tools/reroot.py in.nwk out.nwk --outgroup Nematostella_vectensis Acropora_digitifera
    python3 tools/reroot.py in.nwk out.nwk --outgroup-file og.txt --ladderize

说明：取"包含全部外群物种的最小 clade"作为外群支，重定根后其姊妹支为其余全部物种。
"""
import argparse


class N:
    __slots__ = ('name', 'length', 'children', 'parent')

    def __init__(self):
        self.name = None; self.length = None; self.children = []; self.parent = None


def parse(s):
    s = s.strip().rstrip(';')
    pos = [0]

    def node():
        n = N()
        if s[pos[0]] == '(':
            pos[0] += 1
            while True:
                c = node(); c.parent = n; n.children.append(c)
                if pos[0] >= len(s):
                    break
                if s[pos[0]] == ',':
                    pos[0] += 1; continue
                if s[pos[0]] == ')':
                    pos[0] += 1; break
                pos[0] += 1
        st = pos[0]
        while pos[0] < len(s) and s[pos[0]] not in '(),:;':
            pos[0] += 1
        lab = s[st:pos[0]].strip()
        if lab:
            n.name = lab
        if pos[0] < len(s) and s[pos[0]] == ':':
            pos[0] += 1; st = pos[0]
            while pos[0] < len(s) and s[pos[0]] not in '(),;':
                pos[0] += 1
            n.length = s[st:pos[0]].strip()
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
    if n.children:
        s = '(' + ','.join(ser(c) for c in n.children) + ')' + (n.name or '')
    else:
        s = (n.name or '')
    return s + (':' + n.length if n.length else '')


def smallest_containing(root, want):
    best = [None]

    def walk(n):
        ts = {t.name for t in tips(n)}
        if want <= ts:
            if best[0] is None or len(ts) < len({t.name for t in tips(best[0])}):
                best[0] = n
            for c in n.children:
                walk(c)

    walk(root)
    return best[0]


def reroot(root, og_node):
    """把 og_node 从树上摘下，作为新根的一个子支"""
    if og_node is root:
        return root
    # 自下而上重建：把 og_node 的兄弟合并为"其余"支
    chain = []            # [(node, siblings...)] 从 og 到 root
    cur = og_node
    while cur.parent is not None:
        p = cur.parent
        chain.append((p, [c for c in p.children if c is not cur]))
        cur = p
    # 构造"其余"部分：从最靠近根的一层往下拼回去
    rest = None
    for p, sibs in reversed(chain):
        if rest is not None:
            sibs = sibs + [rest]
        if len(sibs) == 1:
            rest = sibs[0]
        else:
            node = N()
            node.children = sibs
            for s in sibs:
                s.parent = node
            rest = node
    new_root = N()
    new_root.children = [og_node, rest]
    og_node.parent = new_root
    rest.parent = new_root
    return new_root


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('input')
    ap.add_argument('output')
    ap.add_argument('--outgroup', nargs='*', default=[])
    ap.add_argument('--outgroup-file', default=None)
    ap.add_argument('--ladderize', action='store_true')
    a = ap.parse_args()

    og = list(a.outgroup)
    if a.outgroup_file:
        og += [l.strip() for l in open(a.outgroup_file) if l.strip() and not l.startswith('#')]
    root = parse(open(a.input).read())

    if og:
        want = set(og)
        have = {t.name for t in tips(root)}
        unknown = want - have
        if unknown:
            print('警告：树中不存在 %s' % ', '.join(sorted(unknown)), file=__import__('sys').stderr)
            want &= have
        node = smallest_containing(root, want)
        if node is None:
            raise SystemExit('未找到包含外群的 clade')
        n_contain = len({t.name for t in tips(node)})
        if n_contain != len(want):
            print('提示：外群在当前拓扑下非单系（最小包含 clade 有 %d 个 tip，外群 %d 个），'
                  '已按最小包含 clade 重定根。' % (n_contain, len(want)), file=__import__('sys').stderr)
        root = reroot(root, node)
    if a.ladderize:
        ladderize(root)
    open(a.output, 'w').write(ser(root) + ';\n')
    print('已写出 %s（%d tips）' % (a.output, len(tips(root))))


if __name__ == '__main__':
    main()
