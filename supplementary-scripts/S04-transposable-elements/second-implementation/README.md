# Cnidaria 基因组 TE 从头预测与注释流程

对 `genome_TE/` 下每个物种做 **de novo TE 预测**，并整理出每个物种的 TE 查询表：

```
species | TE_id | scaffold | TE_start | TE_end | related_gene | region | TE_type
```

---

## 1. 运行环境（所有程序在同一个 conda 环境里跑）

整套流程只用 **一个** 环境：`te_anno`。EDTA、RepeatMasker、RepeatModeler、
rmblast、GenomeTools/LTRharvest、LTR_Retriever、TEtrimmer、bedtools、AGAT
都装在里面，互相不冲突。

```bash
# 创建环境（只需跑一次，约 10-20 分钟）
bash /mnt/sda/jackie/cnidaria_omics/genome_TE/TE_pipeline/00_setup_env.sh

# 以后每次使用
conda activate te_anno
```

> **注意**：TEtrimmer 1.7.2 要求 `python <3.11`，所以环境用 `python=3.10`。
> 如果用 `python=3.11`，mamba 会因为依赖冲突直接求解失败。

环境里各程序版本：

| 程序 | 用途 |
|---|---|
| `EDTA.pl` 2.2.2 | 从头预测 TE（主力） |
| `RepeatMasker` / `rmblastn` | 用 EDTA 库做全基因组注释 |
| `RepeatModeler` | EDTA `--sensitive 1` 时补充剩余 TE |
| `gt` (GenomeTools) | EDTA 的 LTRharvest 结构化预测 |
| `LTR_Retriever` | LTR 非冗余化 |
| `TEtrimmer` | （可选）人工审编 TE 一致性序列 |
| `bedtools` / `seqkit` / `agat` | 表格与注释处理 |

---

## 2. 运行命令

### 2.0 一键跑全部物种

```bash
conda activate te_anno
cd /mnt/sda/jackie/cnidaria_omics/genome_TE/TE_pipeline

# 先看进度（不跑任何计算）
bash run_all.sh --status

# 全部物种（默认 3 并发 × 5 线程 = 15 线程；机器是共用的，别占满）
bash run_all.sh --jobs 3 --threads 5

# 只跑指定物种
bash run_all.sh --species Nematostella_vectensis Acropora_millepora
```

调度方式：**先跑池子（<1.2 Gb 的 138 个物种，3 并发 × 5 线程），再串行跑
7 个 ≥1.2 Gb 的巨型基因组**（各 12 线程）。

为什么池子优先：巨型基因组无论如何都得串行（EDTA 对多 Gb 基因组可能要
30 GB 以上内存，不能和池子抢内存），所以**先跑哪个总工期都一样**；但先跑
池子的话，大部分物种几天内就能拿到结果表，而不是等最后一个 3 Gb 基因组
跑完才开始。

可重复运行，已完成的物种自动跳过。

### 2.1 单个物种（`01_per_species.sh` 内部做的事）

每个物种依次执行 4 步。下面是与命令行等价的写法，方便你手动跑或改成集群任务：

```bash
conda activate te_anno
cd /mnt/sda/jackie/cnidaria_omics/genome_TE

SPECIES=Nematostella_vectensis
mkdir -p work/$SPECIES && cd work/$SPECIES

# --- 第 1 步：解压基因组 ---
zcat ../../${SPECIES}.fa.gz > ${SPECIES}.fa

# --- 第 2 步：序列改名为 EDTA 安全 ID（否则长 ID 撞车会让 EDTA 直接失败）---
#     产出 ${SPECIES}.renamed.fa 和 ${SPECIES}.idmap.tsv
python ../../TE_pipeline/02_rename_genome.py \
    --genome ${SPECIES}.fa \
    --out    ${SPECIES}.renamed.fa \
    --map    ${SPECIES}.idmap.tsv

# --- 第 3 步：EDTA 从头预测 TE + 全基因组注释（跑改名后的基因组）---
#     --sensitive 0 : 不额外跑 RepeatModeler（本数据集采用，见下方说明）
#     --anno 1      : 建库完成后用该库做全基因组注释（内部会调用 RepeatMasker）
#     --step all    : 从 raw TE 一直做到最终库 + 注释
#     --force 1     : 没有 LTR 结果时也继续，不中止（见坑 4.5）
#     -t 6          : 线程数
EDTA.pl --genome ${SPECIES}.renamed.fa \
        --species others \
        --sensitive 0 \
        --anno 1 \
        --step all \
        --force 1 \
        -t 6

# --- 第 4 步：生成 TE 信息表 ---
#     --id-map 把 EDTA 用的合成 ID 换回原始 scaffold 名
python ../../TE_pipeline/03_te_annotation_table.py \
    --species   ${SPECIES} \
    --te-gff    ${SPECIES}.renamed.fa.mod.EDTA.TEanno.gff3 \
    --gene-gff  ../../${SPECIES}.gff3.gz \
    --genome    ${SPECIES}.fa \
    --id-map    ${SPECIES}.idmap.tsv \
    --promoter  2000 \
    --out       ../../results/TE_tables/${SPECIES}.TE_info.tsv
```

把上面整段包起来的就是 `01_per_species.sh`：

```bash
bash 01_per_species.sh Nematostella_vectensis 6 --cleanup
bash 01_per_species.sh Nematostella_vectensis 6 --sensitive 1   # 更灵敏，约慢一倍
```

**关于 `--sensitive`（本数据集采用 0）**

`--sensitive 1` 会额外调用 RepeatModeler 去找回剩余 TE（主要影响 LINE 的
召回），但运行时间大约翻倍。80.2 Gb 基因组在 20 核机器上跑 `--sensitive 1`
需要数周到一两个月，所以本流程默认用 `--sensitive 0`。
如果之后想对**少数重点物种**单独提高灵敏度，直接加 `--sensitive 1` 重跑即可。

`--cleanup` 会删掉 EDTA 的中间文件（`*.EDTA.raw/`、`*.EDTA.anno/` 等），
只保留 `TElib.fa`、`TEanno.gff3` 和结果表 —— 建议开着，否则 145 个物种的
中间文件会占掉好几个 TB。

### 2.2 合并所有物种的结果

```bash
bash 04_combine_tables.sh
```

产出：

| 文件 | 内容 |
|---|---|
| `results/All_species.TE_info.tsv.gz` | 所有物种的 TE 总表 |
| `results/TE_summary_by_species.tsv` | 每个物种的 TE 数 / 总 bp / 各区域计数 |
| `results/TE_summary_by_type.tsv` | 每种 TE 类型的数量与总 bp |

---

## 3. 输出表格说明

每行一个 TE。`related_gene` 一列**每行都有值**，`region` 说明这个 TE 相对
该基因处在什么位置。

| 列 | 含义 |
|---|---|
| `species` | 物种名（与文件名一致） |
| `TE_id` | EDTA 的 TE 注释编号，如 `TE_homo_0`（同源方法）、`TE_struc_6`（结构方法） |
| `scaffold` | 染色体 / contig / scaffold **原始名称** |
| `TE_start` | TE 起始位点（1-based，闭区间） |
| `TE_end` | TE 终止位点（1-based，闭区间） |
| `related_gene` | 最近的基因 ID |
| `region` | 见下 |
| `TE_type` | TE 类型，如 `CACTA_TIR_transposon` |

### region 判定规则（按优先级从高到低）

| 值 | 判定条件 |
|---|---|
| `exon` | TE 与外显子有重叠（取重叠长度最大的那个基因） |
| `intron` | TE 完全落在某个基因范围内，且不碰任何外显子 |
| `promoter` | TE 在某个基因 TSS 上游 2000 bp 内，不压到任何基因本体 |
| `intergenic` | 以上都不是；`related_gene` 填**距离最近**的基因 |

启动子窗口用 `--promoter` 调整，例如 `--promoter 3000` 改成 3 kb。

**链方向很重要**：+ 链基因的 TSS 在 `gene.start`，启动子在基因**前面**；
− 链基因的 TSS 在 `gene.end`，启动子在基因**后面**。两者按各自方向判断。

**双向启动子**：如果两个基因头对头转录，中间那段区域会同时是两个基因的
启动子（例如 Nematostella 里 `gene-LOC116620378`(−) 和
`gene-LOC5516299`(+) 之间 943174-943824 这段）。这种情况脚本会选**距离最近**
的那个基因作为 `related_gene`，并在日志里记录。

**多isoform**：一个基因常有多个转录本、外显子结构不同。判定 `exon` 时用的是
**所有 isoform 的全部外显子**，只要 TE 压到其中任意一个外显子就算 `exon`。
所以看起来像「内含子」的位置，如果另一个 isoform 在那里有外显子，会被判成
`exon` —— 这是符合生物学定义的。

判定逻辑有自带测试可以验证：

```bash
bash test_table_logic.sh
```

### TE_type 的来源

`*.TEanno.gff3` 的第 3 列（feature type）**本身就是标准 SO 名称**，脚本直接
用它作为 `TE_type`：

```
Chr2  EDTA  CACTA_TIR_transposon  39311  39451  .  .  .  \
      ID=TE_struc_6;Name=TE_00000010;classification=MITE/DTM;sequence_ontology=SO:0002280;...
Chr2  EDTA  Copia_LTR_retrotransposon  ...  \
      ID=TE_homo_0;Name=TE_00000123_LTR;classification=LTR/Copia;sequence_ontology=SO:0002264;...
```

如果第 3 列不是已知 SO 名称（例如 `repeat_fragment`，或别家软件产出的
`match` / `repeat_region`），则回退到用 `classification` 属性
（如 `TIR/CACTA`、`LINE/L2`）经 **EDTA 自带的 `TE_Sequence_Ontology.txt`**
映射成标准名：

| 来源 | 输出 TE_type |
|---|---|
| 第 3 列 `CACTA_TIR_transposon` | `CACTA_TIR_transposon` |
| 第 3 列 `helitron` | `helitron` |
| 回退：`classification=LTR/Copia` | `Copia_LTR_retrotransposon` |
| 回退：`classification=LINE/L2` | `L2_LINE_retrotransposon` |
| 回退：`classification=LTR/unknown` | `LTR_retrotransposon` |

脚本会在日志里打印实际见到的 feature type 统计，便于核对：

```
  GFF feature types: Tc1_Mariner_TIR_transposon=162, Gypsy_LTR_retrotransposon=56, ...
```

---

## 4. 几个必须知道的坑

### 4.0 EDTA 的第 3 列不是 `TE_homo`（**最容易踩**）

看 EDTA 源码 `bed2gff.pl` 很容易以为 feature type 是 `TE_homo` / `TE_intact`
（因为 `ID=` 长这样：`ID=TE_homo_0`、`ID=TE_struc_6`）。**实际不是**——
EDTA 把 **SO 名称本身**写在第 3 列：

```
Chr2  EDTA  CACTA_TIR_transposon  39311  39451  ...   ID=TE_struc_6;...
             ^^^^^^^^^^^^^^^^^^^ 第 3 列 = SO 名称
Chr2  EDTA  helitron  76566  77152  ...   ID=TE_homo_23;...
```

如果解析器按 `TE_*` 前缀过滤第 3 列，**会一条都匹配不上，静默产出空表**
（145 个物种全部为空，而且不报错）。`03_te_annotation_table.py` 现在同时接受：

1. `TE_Sequence_Ontology.txt` 第 1 列里的所有 SO 名称（98 个）
2. `TE_*` 前缀（兼容旧版 EDTA / 手工 GFF3）
3. 通用类型 `te` / `transposable_element` / `repeat_region` / `match` 等
4. 形状兜底：`*transposon`、`*retrotransposon`、`helitron`、`LINE*`、`SINE*`、`MITE`、`TRIM`

用 EDTA 自带的测试基因组实测：**365 条 TE 全部解析出来**，其中
`TE_homo_*` 300 条、`TE_struc_*` 65 条。

### 4.1 EDTA 会截断序列 ID（**最关键**）

当基因组里序列名超过 **13 个字符**时，EDTA 会把 ID 截成**前 13 个字符**
（`EDTA.pl` 的 `id_mode 1`），例如：

```
>JARQWQ010000001.1 Acropora cervicornis ...   →   >JARQWQ0100000
```

所以 `*.TEanno.gff3` 里的 scaffold 名是截断过的，而 `*.gff3.gz` 基因注释里
是原始名字 —— **直接 join 会全部对不上**。

`03_te_annotation_table.py` 通过 `--genome` 传入**原始基因组**，用和 EDTA
完全相同的规则（取第一个空格前的字段 → 特殊字符换 `_` → 合并连续 `_` →
截前 13 字符）重建映射表，把 TE 的 scaffold 名换回原始名。这个转换已经用
真实 EDTA 输出验证过。

### 4.2 内存与磁盘

| 项 | 量 |
|---|---|
| 基因组总量 | 145 个物种，共 **80.2 Gb**（均值 553 Mb，最大 3.07 Gb） |
| 机器 | 20 核 / 62 GB 内存 / 3.4 TB 可用 |
| EDTA 峰值内存 | 中等基因组约 8-20 GB；≥1.2 Gb 的基因组可能 >30 GB |

所以 `run_all.sh` 把大基因组单独串行跑。**不要**盲目把 `--jobs` 调大，
否则会 OOM。

### 4.3 关于 `--anno 1` 和单独跑 RepeatMasker（本数据集不单独跑）

你给的命令里第 2 步 `RepeatMasker` 用的输入是
`jellyfish_scaffolded.fa.mod.MAKER.masked`，那是 MAKER 传给它的软屏蔽基因组。
本流程里没有 MAKER，而且 **`EDTA.pl --anno 1` 内部已经用 EDTA 库跑过一遍
RepeatMasker**（结果就是 `*.TEanno.gff3`），再单独跑一次是重复劳动，
还会让总耗时翻倍。

因此**本数据集不单独跑 RepeatMasker**，TE 表直接基于 `*.TEanno.gff3` 生成。
如果以后需要给 MAKER 等基因预测软件提供软屏蔽基因组，用 `--with-rm` 打开即可。

### 4.4 EDTA 会卡死，必须挂看门狗

`gt ltrharvest` 偶尔会在某个高重复的 chunk 上**几乎无限循环**：单个进程
99.9% CPU 跑十几分钟不结束，而机器其余核心全闲。更糟的是
`LTR_HARVEST_parallel` 的 `-time` 只是**建议值，不是操作系统超时**，
它拦不住这种情况 —— 一个卡住的 chunk 会让整个队列停摆。

所以 `01_per_species.sh` 用 `timeout` 把 EDTA 整个包起来：

```bash
timeout --signal=TERM --kill-after=300 "$(( TIMEOUT_H * 3600 ))s" EDTA.pl ...
```

默认 `--timeout-hours 24`。超时后 EDTA 被杀掉，但**原始结果保留**，
配合 `--overwrite 0` 重跑可以从中断处继续，不会从头再来。

### 4.5 没有 LTR 结果时 EDTA 会中止，需要 `--force 1`

某些基因组的 LTRharvest 结果为空，EDTA 会直接报错退出：

```
ERROR: Raw LTR results not found ... Consider to use the --force 1 parameter
```

所以流水线固定加了 `--force 1`，让它跳过 LTR 阶段继续跑完其余部分。
（代价是这类物种的 LTR 类 TE 召回偏低，但总比整个物种失败好。）

### 4.6 序列 ID 截断会撞车，EDTA 直接拒绝运行（**会让整个物种失败**）

4.1 说的是「EDTA 会截断 ID」，这里说的是更严重的一层：**截断后如果不唯一，
EDTA 会直接报错退出**，一个物种白跑：

```
The longest sequence ID in the genome contains 114 characters, which is longer than the limit (13)
Trying to reformat seq IDs...
    Attempt 1...
    Attempt 2...
ERROR: Fail to convert seq IDs to <= 13 characters! Please provide a genome with shorter seq IDs.
```

以 `Millepora_alcicornis` 为例，两个 scaffold 的前 13 个字符完全相同：

```
>HAP1_SCAFFOLD_3184   ->  HAP1_SCAFFOL
>HAP1_SCAFFOLD_3178   ->  HAP1_SCAFFOL    ← 撞车
```

这类命名（`HAP1_SCAFFOLD_*`、`SUPER_*_unloc`、`scaffold_*`）在刺胞动物组装里
非常常见，所以**不能靠运气**。

**解决办法：在 EDTA 之前先把序列改名。** `02_rename_genome.py` 把每条序列
改写成 10 个字符的合成 ID（`seq0000001`、`seq0000002` …），全局唯一、只含
`[A-Za-z0-9_]`，于是 EDTA 的截断逻辑变成空操作，这类失败彻底消失。同时输出
映射表 `work/<species>/<species>.idmap.tsv`：

```
seq0000001	chr1
seq0000444	HAP1_SCAFFOLD_3184
```

建表时用 `--id-map` 传进去，`scaffold` 一列输出的仍然是**原始名字**
（`chr1`、`HAP1_SCAFFOLD_3184`），与基因注释的 GFF3 对得上。

每个物种的处理顺序因此是：

```
解压 → 改名(02_rename_genome.py) → EDTA → 建表(--id-map) → 清理
```

> 注意：`>itochondrion` 这种**原始数据里就少字母**的 header 会被原样保留，
> 不是脚本的 bug —— 映射表忠实反映输入。

---

## 5. 目录结构

```
genome_TE/
├── <Species>.fa.gz            输入：基因组（145 个）
├── <Species>.gff3.gz          输入：基因注释
├── TE_pipeline/
│   ├── 00_setup_env.sh        建 conda 环境（只跑一次）
│   ├── 01_per_species.sh      单物种：改名 → EDTA → RepeatMasker(可选) → 建表
│   ├── 02_rename_genome.py    序列改名为 EDTA 安全 ID，输出 idmap
│   ├── 03_te_annotation_table.py   核心：TE 定位 + 区域判定 + 类型映射
│   ├── 04_combine_tables.sh   合并 + 汇总
│   ├── run_all.sh             批量调度（可断点续跑）
│   └── README.md              本文件
├── work/<Species>/            每物种的工作目录与日志
└── results/
    ├── TE_tables/<Species>.TE_info.tsv    每物种的 TE 表
    ├── All_species.TE_info.tsv.gz         总表
    └── TE_summary_*.tsv                   汇总
```
