#!/usr/bin/env python3
"""把「多个 MAG 合并跑」出来的注释 TSV 按 MAG 拆成每 MAG 一份。

为什么需要它：InterProScan / KofamScan 都有很重的固定开销（InterProScan 要先把
5.2 GB 的 Gene3D 模型读进 JVM，KofamScan 是 27,864 次 hmmsearch、每次都要重新
加载），所以 14 个 MAG 是**合成一个 fasta 跑一次**的
（work/pilot_all.faa = 59,830 条）。但站点上的 load_iprscan.php / load_kofam.php
是 `--mag <acc>` 的接口：传哪个 MAG，整份 TSV 就都算在那个 MAG 名下。直接喂合并
的 TSV 会把 59,830 条注释全挂到第一个 MAG 上（其他 13 个详情页则是空的）。

安全前提（已实测，2026-09-27）：合并池里蛋白号全局唯一，14 个 MAG 之间
**0 个重号**，所以「蛋白号 → MAG」是函数，可以无损拆分。

映射不是从库里查的，而是**从源 fasta 的文件名推**（`<AssemblyAccession>.faa` 里
每条序列的头就是那个 MAG 的蛋白号）——这样拆分用的是和造池子完全相同的输入，
不依赖 mag_annot 是否已经灌过，后面 Prokka/Bakta 那条路产出的 fasta 也能直接复用。

用法：
  split_by_mag.py --fasta-dir DIR --in IN.tsv --outdir OUTDIR --suffix iprscan
产出 OUTDIR/<AssemblyAccession>.<suffix>.tsv，另写 OUTDIR/_split_report.txt。
映射不到的蛋白号一律**报错退出**（不静默丢行）——那说明池子和 fasta 不是同一批。
"""
import argparse
import os
import sys
from collections import defaultdict


def build_map(fasta_dir):
    """蛋白号 -> MAG。头只取到第一个空白前的部分（和 load_* 里的 $c[0] 一致）。"""
    m = {}
    dup = []
    files = sorted(f for f in os.listdir(fasta_dir) if f.endswith(('.faa', '.fa', '.fasta')))
    if not files:
        sys.exit('no fasta files in %s' % fasta_dir)
    for fn in files:
        mag = os.path.basename(fn).rsplit('.', 1)[0]
        n = 0
        with open(os.path.join(fasta_dir, fn)) as fh:
            for line in fh:
                if not line.startswith('>'):
                    continue
                pid = line[1:].split(None, 1)[0].strip()
                if not pid:
                    continue
                if pid in m and m[pid] != mag:
                    dup.append((pid, m[pid], mag))
                m[pid] = mag
                n += 1
        print('  %-28s %6d proteins' % (mag, n))
    if dup:
        # 池子里重号的话「蛋白号 -> MAG」就不是函数了，拆出来的行会归错物种。
        sys.exit('ABORT: %d protein IDs appear in more than one MAG (e.g. %s); '
                 'the pooled TSV cannot be split unambiguously'
                 % (len(dup), ', '.join('%s:%s/%s' % d for d in dup[:5])))
    return m


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--fasta-dir', required=True)
    ap.add_argument('--in', dest='inp', required=True, help='pooled TSV')
    ap.add_argument('--outdir', required=True)
    ap.add_argument('--suffix', required=True, help='e.g. iprscan / kofam')
    a = ap.parse_args()

    print('protein -> MAG map:')
    pm = build_map(a.fasta_dir)
    print('  total %d proteins across %d MAGs' % (len(pm), len(set(pm.values()))))

    os.makedirs(a.outdir, exist_ok=True)
    handles = {}
    counts = defaultdict(int)
    unmapped = {}
    n_line = 0

    with open(a.inp) as fh:
        for line in fh:
            if line.startswith('#') or not line.strip():
                continue
            n_line += 1
            pid = line.split('\t', 1)[0].strip()
            mag = pm.get(pid)
            if mag is None:
                unmapped[pid] = unmapped.get(pid, 0) + 1
                continue
            h = handles.get(mag)
            if h is None:
                h = handles[mag] = open(
                    os.path.join(a.outdir, '%s.%s.tsv' % (mag, a.suffix)), 'w')
            h.write(line if line.endswith('\n') else line + '\n')
            counts[mag] += 1

    for h in handles.values():
        h.close()

    print('\ninput lines: %d   written: %d   unmapped: %d'
          % (n_line, sum(counts.values()), sum(unmapped.values())))
    for mag in sorted(counts):
        print('  %-28s %7d rows -> %s.%s.tsv' % (mag, counts[mag], mag, a.suffix))

    rep = os.path.join(a.outdir, '_split_report.txt')
    with open(rep, 'w') as f:
        f.write('input: %s\nlines: %d\nwritten: %d\nunmapped: %d\n'
                % (a.inp, n_line, sum(counts.values()), sum(unmapped.values())))
        for mag in sorted(counts):
            f.write('%s\t%d\n' % (mag, counts[mag]))
    print('report: %s' % rep)

    if unmapped:
        # 静默丢行的话，页面会显示「这个基因没注释」，而真相是它根本没被拆出来。
        print('ABORT: %d rows reference proteins not present in %s (e.g. %s)'
              % (sum(unmapped.values()), a.fasta_dir,
                 ', '.join(list(unmapped)[:5])), file=sys.stderr)
        sys.exit(1)


if __name__ == '__main__':
    main()
