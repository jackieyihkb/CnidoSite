#!/usr/bin/env python3
"""把没有放进本仓库的大数据集取回来。

清单来自 manifests/large-files.tsv（由 make_large_manifest.py 生成，逐条记过
字节数与 HTTP 可达性）。三种取法：

  --list                 按目录归类看有哪些、多大、能不能直接取
  --fetch                取所有 HTTP 可达的文件（可断点续传，curl -C -）
  --only download/       只取某一类（匹配清单里的相对路径前缀）

**为什么有一批清单项标着 403**
    cnidosite.org 的 /data/ 目录挂了一条 `Require all denied`
    （原因见 site/../data/.htaccess：那是入库过程的中间产物，不对外）。
    这些文件不在 HTTP 上，所以脚本**不会**给它们编一个取不到的 URL，而是
    列出来并说明它属于哪一类：
      · 各物种的原始注释表（go / ipr / nr / pfam / seq …）—— 数据库就是从这批
        文件建起来的；重建数据库不需要它们，需要的是 schema + curated 数据。
      · 需要原件的话，从 scripts/pipelines/ 里对应的分析重新生成，或向作者索取。
"""
import argparse
import os
import subprocess
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(os.path.dirname(HERE))
MANIFEST = os.path.join(ROOT, "manifests", "large-files.tsv")
DEST = os.path.join(ROOT, "data", "large")


def rows():
    with open(MANIFEST) as fh:
        for line in fh:
            if line.startswith("#") or not line.strip():
                continue
            parts = line.rstrip("\n").split("\t")
            if len(parts) < 4:
                continue
            yield int(parts[0]), parts[1], parts[2], parts[3]


def humansize(n):
    for u in ("B", "KiB", "MiB", "GiB", "TiB"):
        if n < 1024 or u == "TiB":
            return "%.1f %s" % (n, u)
        n /= 1024.0


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--list", action="store_true")
    ap.add_argument("--fetch", action="store_true")
    ap.add_argument("--only", default=None,
                    help="只处理相对路径以此开头的项，例如 download/ 或 singlecell_data/")
    ap.add_argument("--dest", default=DEST)
    ap.add_argument("--base", default="https://cnidosite.org",
                    help="站点根；换成镜像地址即可从别处取")
    a = ap.parse_args()

    items = list(rows())
    if a.only:
        items = [r for r in items if r[1].startswith(a.only)]
    if not items:
        sys.exit("清单里没有匹配的项：%s" % (a.only or "(全部)"))

    if a.list or not a.fetch:
        group = {}
        for size, rel, code, how in items:
            top = rel.split(os.sep)[0]
            g = group.setdefault(top, [0, 0, set()])
            g[0] += 1
            g[1] += size
            g[2].add(code)
        print("%-22s %8s %12s  %s" % ("目录", "文件数", "合计", "HTTP"))
        for top in sorted(group):
            n, sz, codes = group[top]
            print("%-22s %8d %12s  %s" % (top, n, humansize(sz), ",".join(sorted(codes))))
        reach = [r for r in items if r[2] == "200"]
        print("\n可直接取: %d 个 / %s" % (len(reach), humansize(sum(r[0] for r in reach))))
        print("不可达  : %d 个 / %s（见脚本头部说明）"
              % (len(items) - len(reach),
                 humansize(sum(r[0] for r in items if r[2] != "200"))))
        if not a.fetch:
            print("\n加 --fetch 开始下载（--only 可只取一类）。")
        return

    todo = [r for r in items if r[2] == "200"]
    if not todo:
        sys.exit("没有 HTTP 可达的项可取；先用 --list 看看分布。")
    os.makedirs(a.dest, exist_ok=True)
    total = sum(r[0] for r in todo)
    done = 0
    print("要取 %d 个文件 / %s -> %s" % (len(todo), humansize(total), a.dest))
    for i, (size, rel, _code, how) in enumerate(todo, 1):
        url = how.split()[0] if how.startswith("http") else \
            a.base + "/" + rel.replace(os.sep, "/")
        if not url.startswith("http"):
            url = a.base + "/" + rel.replace(os.sep, "/")
        out = os.path.join(a.dest, rel)
        os.makedirs(os.path.dirname(out), exist_ok=True)
        if os.path.exists(out) and os.path.getsize(out) == size:
            done += size
            continue
        # -C - 续传：站点的下载链接文件名在查询串里，curl -O 会取错名字（见
        # site/includes/downloads.php 的说明），所以这里显式指定 -o。
        cmd = ["curl", "-fL", "-C", "-", "--retry", "3", "--retry-delay", "5",
               "-o", out, url]
        r = subprocess.run(cmd)
        if r.returncode != 0:
            print("  !! 失败 (%d): %s" % (r.returncode, rel), file=sys.stderr)
            continue
        done += size
        if i % 50 == 0 or i == len(todo):
            print("  %d/%d  %s / %s" % (i, len(todo), humansize(done), humansize(total)))
    print("完成，落在 %s" % a.dest)


if __name__ == "__main__":
    main()
