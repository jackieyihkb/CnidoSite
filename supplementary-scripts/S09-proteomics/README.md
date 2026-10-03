# CnidoSite 蛋白组模块重构 —— 交付说明

*Rebuilt proteomics module for CnidoSite: deliverables, and how to deploy them.*

---

## 这次交付解决了什么 / What this delivers

| 审稿意见 | 位置 |
|---|---|
| R2 #5 — 蛋白组流程描述不清，把 PSM 和 BLAST 混为一谈 | 全流程重建 + `6.response/Response_to_Reviewers_Proteomics.md` |
| R2 #2 — *Stylophora pistillata* 页面打不开 | 13 分支 `if/elseif` 改为通用表驱动，`5.web/proteomic_analysis.php` |
| R2 #1 / R3 #10(j) — 基因页应显示蛋白组证据 | `5.web/includes/gene_proteomics_panel.php` |
| R2 #9 — 数据来源、可复现性、版本 | 新建 `proteomic_datasets` 表 + `5.web/proteomic_dataset.php` |
| R2 #10(i) — 页脚写成 "Total Epigenomic Samples" | 标签改为按查询动态生成 |
| R2 #10(iv) / R3 #10(i) — 翻页链接失效、链接需系统测试 | 分页重写 + `7.tests/run_tests.sh` |
| R3 #1 — 数据库快照归档 | 见回复信（待确认 Zenodo DOI） |
| R3 #2 — 提供命令行 | 每个数据集页面打印其复现命令 |

数据管线全流程已跑通并验证：**PXD009253**（*Exaiptasia pallida*）38,428 张谱图 →
2,523 个 PSM / 1,673 条肽段（q ≤ 0.01）→ **559 个可链接到基因的蛋白** + 5 个 cRAP 污染蛋白。

---

## 目录结构 / Layout

```
1.metadata/      数据集清单、每个数据集的搜索参数（原始研究与本站各一套）
2.pipeline/      处理流程，7 个可独立运行的脚本（01…07）+ cnido_common.py
3.work/          Comet/Percolator 环境、搜索库、中间文件
4.results/
  PXD*/          每个数据集的结果（PSM、肽段、蛋白、污染蛋白）
  load/          ★ 可直接入库的成品表 + proteomics_load.sql
5.web/           ★ 网页文件
  sql/           建表脚本 + 带备份的导入脚本
  includes/      基因页面板组件
6.response/      ★ 给审稿人的逐条回复
7.tests/         自动化渲染测试（29 条断言）
```

★ = 你直接要用的东西。

---

## 部署三步 / Deploy in three steps

**1. 上传网页文件**（详见 `5.web/INSTALL.md`）

```
proteomic_data.php       → 网站根目录（替换原文件）
proteomic_analysis.php   → 网站根目录（替换原文件）
proteomic_dataset.php    → 网站根目录（新页面）
includes/gene_proteomics_panel.php → includes/ 目录（新文件）
```

**2. 导入数据库**

```bash
./5.web/sql/load_proteomics.sh --dry-run   # 先检查账号路径
./5.web/sql/load_proteomics.sh             # 自动备份旧表后再导入
```

导入脚本会先 `mysqldump` 备份现有的三张表，**不会动**原有的
`<物种>_<组织>_proteomics` 表 —— 那些可以等你确认无误后再手动删。

**3. 基因页显示蛋白组证据**（可选，但强烈建议）

在 `gene_detail.php` 的注释模块之后加两行：

```php
require_once __DIR__ . '/includes/gene_proteomics_panel.php';
render_gene_proteomics_panel($gene, $species);
```

该组件**在没有证据时什么都不输出**，所以不可能弄坏任何现有基因页。

---

## 重新处理更多数据集 / Scaling beyond the pilot

```bash
python3 2.pipeline/02_fetch_pride.py PXD033068          # 从 PRIDE 下载
python3 2.pipeline/03_build_search_db.py "Nematostella vectensis"
python3 2.pipeline/04_run_search.py PXD033068           # 生成 comet.params 并跑 Comet
python3 2.pipeline/05_fdr_percolator.py PXD033068       # Percolator，q ≤ 0.01
python3 2.pipeline/06_map_to_genes.py PXD033068         # 肽段 → 基因
python3 2.pipeline/07_build_tables.py --release 1.1     # 汇总成入库表
```

46 个数据集中，**18 个**有对应的 CnidoSite 参考蛋白组，**28 个没有**（*Corallium
rubrum*、*Antipathes griggi*、*Orbicella annularis* 等）。没有参考蛋白组的会明确
标记为 “No reference proteome”，**不会**拿近缘物种去凑 —— 那会产生误导性匹配。
这是回复信里对 R2 #2 的关键论点。

---

## 测试 / Tests

```bash
cd 7.tests && ./run_tests.sh
```

需要 PATH 上有 PHP 8，且**不能**加载 mysqli 扩展（测试自带一个用真实结果 TSV
驱动的 mysqli 替身）。当前：**29 条断言全部通过**。

它覆盖了正常、空结果、无数据库表、污染蛋白开关、翻页第 1/2 页、基因页有/无证据、
未知数据集编号等状态 —— 并且会捕获 PHP notice/warning。

---

## 需要你确认的事项 / Open items

1. 本次发布的 **重新处理数据集数量 [N]** 和剩余数据的计划时间
2. 基因页面板是否已上线（回复信中需要一个示例 URL）
3. 全站版本号 / 更新日期 / changelog 页面（R2 #9，非蛋白组专属）
4. Zenodo 归档的 DOI（R3 #1）
5. cRAP 数据库版本、ThermoRawFileParser 版本（写进 Methods）
6. Data Coverage Matrix 请核对 *Hydra*（有）与 *Xenia*（无蛋白组数据集）两个例子

详见 `6.response/Response_to_Reviewers_Proteomics.md` 末尾。
