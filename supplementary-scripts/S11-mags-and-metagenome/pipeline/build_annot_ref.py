#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
建两张 MAG 注释要用的「字典」表。InterProScan 的 TSV 里没有 InterPro 条目类型，
GO 的 TSV 里只有号没有名字和类别，都要外部字典补。

输出（供 load_iprscan.php 读）：
  ref/ipr_type.tsv   IPRxxxxxx \t ENTRY_TYPE \t ENTRY_NAME      （来自 EBI entry.list）
  ref/go_term.tsv    GO:xxxxxxx \t Category \t Name             （来自 go-basic.obo）

口径与站内 <ABBR>_ipr / <ABBR>_go 逐字一致（实测 2026-09-27）：
  - Type 直接用 entry.list 的 ENTRY_TYPE 原文，**不做下划线转空格** ——
    站内实测值就是 Domain / Homologous_superfamily / Conserved_site / Active_site
    这种带下划线的写法，转成空格反而和 148 个物种不一致。
  - Category 用 obo 的 namespace 转成站内的三档写法（Molecular Function /
    Biological Process / Cellular Component），站内 148 个物种也都是这三个值。
  - go_term.tsv 收 **obsolete 项**（name 自带 "obsolete " 前缀，站内也是这么存的）
    与 **alt_id**（被合并掉的旧号）。不收的话这些 GO 行会印成空白列。

**ref/entry.list 必须锁在 InterPro release 109.0，不能用 current_release。**
InterProScan 5.78-109.0 的离线 `-iprlookup` 数据是 release 109 的快照，它会报出
**110 已经删掉的条目**；拿 current_release(110) 的 entry.list 当字典，这些条目的
Type 就是空的（实测首次跑 GCA_017743735.1 有 15 个 IPR / 17 行为空，站内 148 个
物种的 `_ipr` 表一个空 Type 都没有，是明显的不一致）。这 15 个在 109 里全都有、
全都是 `Family`，名字与 TSV 第 13 列逐字一致。109 与 110 的差集（110 多出 732 条）
是引擎报不出来的，所以**用 109 就是完备的**。
下载：curl -o ref/entry.list \
  https://ftp.ebi.ac.uk/pub/databases/interpro/releases/109.0/entry.list
升级 InterProScan 时这个版本号要跟着换（脚本里没有下载代码，是手工放的）。

同理 `go-basic.obo` 也别追新：它决定 GO 的 Category/Description。

用法： python3 build_annot_ref.py [ref_dir]
"""
import io
import os
import re
import sys

REF = sys.argv[1] if len(sys.argv) > 1 else os.path.join(os.path.dirname(os.path.abspath(__file__)), 'ref')

NS = {
    'molecular_function': 'Molecular Function',
    'biological_process': 'Biological Process',
    'cellular_component': 'Cellular Component',
}


def build_ipr_type(ref):
    src = os.path.join(ref, 'entry.list')
    dst = os.path.join(ref, 'ipr_type.tsv')
    n = 0
    types = {}
    with io.open(src, encoding='utf-8') as fh, io.open(dst, 'w', encoding='utf-8') as out:
        head = fh.readline().rstrip('\n').split('\t')
        if head[:3] != ['ENTRY_AC', 'ENTRY_TYPE', 'ENTRY_NAME']:
            sys.exit('entry.list 表头不是预期的三列，实际：%r' % (head,))
        for line in fh:
            f = line.rstrip('\n').split('\t')
            if len(f) < 3 or not re.match(r'^IPR\d{6}$', f[0]):
                continue
            out.write('%s\t%s\t%s\n' % (f[0], f[1], f[2]))
            types[f[1]] = types.get(f[1], 0) + 1
            n += 1
    print('ipr_type.tsv   %6d entries   types: %s' % (n, dict(sorted(types.items(), key=lambda x: -x[1]))))


def build_go_term(ref):
    src = os.path.join(ref, 'go-basic.obo')
    dst = os.path.join(ref, 'go_term.tsv')
    n = 0
    obsolete = 0
    alt = 0
    cats = {}
    # obo 是纯文本分块格式，[Term] 起一块，字段是 `key: value`。
    #
    # **obsolete 项也要收**（早期版本是整块丢掉的，那是个 bug）：GO 的命名约定是
    # obsolete 项的 name 自己就带 "obsolete " 前缀
    # （`name: obsolete histone arginine methylation`），与站内 NVECT_go 存的值
    # 一字不差。跳过它们的后果是这些行在页面上变成**空白的 Category/Description**
    # ——实测 GCA_017743735.1 有 151 行（1.6%）中招，而站内 NVECT_go 383,707 行里
    # 一个空 Category 都没有，说明站内的字典本来就是含 obsolete 的。
    #
    # **alt_id 也要收**：被合并掉的旧号（`alt_id: GO:0016470` → GO:0005753）在
    # InterProScan 输出里照样会出现，收进来才有 Category/Description 可填。
    # 存的号仍是 InterProScan 报的那个（站内也是这个口径，不改成主号）。
    cur = None
    with io.open(src, encoding='utf-8') as fh, io.open(dst, 'w', encoding='utf-8') as out:
        def flush(t):
            nonlocal n, obsolete, alt
            if not t or 'id' not in t or 'name' not in t:
                return
            ns = t.get('namespace', '')
            cat = NS.get(ns)
            if cat is None:
                return
            ids = [t['id']] + t.get('alt', [])
            for i, gid in enumerate(ids):
                out.write('%s\t%s\t%s\n' % (gid, cat, t['name']))
                if i:
                    alt += 1
                else:
                    n += 1
                    cats[cat] = cats.get(cat, 0) + 1
            if t.get('is_obsolete') == 'true':
                obsolete += 1

        for line in fh:
            line = line.rstrip('\n')
            if line.startswith('['):
                flush(cur)
                cur = {} if line == '[Term]' else None
                continue
            if cur is None or not line or line.startswith('!'):
                continue
            if line.startswith('id: '):
                cur['id'] = line[4:].strip()
            elif line.startswith('name: '):
                cur['name'] = line[6:].strip()
            elif line.startswith('namespace: '):
                cur['namespace'] = line[11:].strip()
            elif line.startswith('is_obsolete: '):
                cur['is_obsolete'] = line[13:].strip()
            elif line.startswith('alt_id: '):
                cur.setdefault('alt', []).append(line[8:].strip())
        flush(cur)
    print('go_term.tsv    %6d terms (%d obsolete kept) + %d alt_id   categories: %s'
          % (n, obsolete, alt, dict(sorted(cats.items(), key=lambda x: -x[1]))))


if __name__ == '__main__':
    if not os.path.isdir(REF):
        sys.exit('ref dir not found: %s' % REF)
    missing = [f for f in ('entry.list', 'go-basic.obo') if not os.path.isfile(os.path.join(REF, f))]
    if missing:
        sys.exit('missing in %s: %s' % (REF, ', '.join(missing)))
    build_ipr_type(REF)
    build_go_term(REF)
