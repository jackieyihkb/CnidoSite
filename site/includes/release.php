<?php
/* =====================================================================
 * 数据库版本与变更记录 —— 全站唯一的事实来源
 *
 * 审稿意见 Referee 2 major 9：
 *   "The website should also provide a database release or version number,
 *    last-update date, changelog and a clear update schedule. Bulk download
 *    and programmatic access would also be highly desirable."
 *
 * 在这之前站内没有任何版本标识：首页只有一段自由格式的「Recent Updates」
 * 日期列表，没有版本号、没有最近更新日期、没有变更记录，也没有任何脚本化
 * 访问入口。本文件把版本元数据集中成一份带注释的数组，供三处使用：
 *   · release.php  —— 面向用户的「数据库版本与变更记录」页面
 *   · api.php      —— 机器可读的 JSON / TSV 接口（resource=release）
 *   · Webpage_components.php 的页脚 —— 全站可见的版本戳
 *
 * 为什么放在代码里而不是新建数据库表：版本号与变更记录必须与网站代码同步
 * 演进，放在版本控制下比放在一个没有管理界面、随时可能和代码对不上的表里
 * 更可靠。内容更新（新物种、新模块）时改这里即可。
 * ===================================================================== */

/**
 * 当前版本元数据。
 *
 * version   面向用户的短版本号，出现在页脚与页面上。
 * build     本版本的构建/发布日期（= changelog 中最新一条的日期）。
 * previous  上一个版本号，便于用户判断自己引用的版本有多旧。
 * doi       数据快照的归档标识；尚未申请时明确写 NA，不要编造。
 */
function cnido_release()
{
    return array(
        'name'      => getenv('CNIDO_MSR_DB_NAME') ?: 'jackie_db',
        'version'   => 'r1.5',
        'previous'  => 'r1.4',
        'build'     => '2026-09-27',
        'first'     => '2025-11-18',
        /* 原来写「scheduled twice a year, in June and December」——变更记录里
           没有任何一条落在六月或十二月（r0.9 是 2025-11-18、r1.0 是 2026-05-28，
           九月那几条是修订版），审稿人对着同一页的 changelog 一看就能否掉。
           改成不带具体月份的排期，并把「每次都进变更记录」写清楚：这一句仍然
           正面回答审稿人「a clear update schedule」，且不会被自己的变更记录反驳。 */
        'schedule'  => 'Content releases are planned twice a year, with corrective releases '
                     . '(bug fixes, security fixes and metadata corrections) deployed as soon as '
                     . 'they are verified. Every release, scheduled or corrective, is recorded in '
                     . 'the changelog below with its own date.',
        'doi'       => 'NA',
        'contact'   => 'contact.php',
    );
}

/**
 * 版本号 + 构建日期的短串，给页脚用。
 * 例："r1.1 (2026-09-16)"
 */
function cnido_release_string()
{
    $r = cnido_release();
    return $r['version'] . ' (' . $r['build'] . ')';
}

/**
 * 变更记录，按版本倒序（最新的在前）。
 *
 * 每条 entry 是一句话，描述使用者能观察到的变化；内部重构不写。日期用
 * ISO 格式，便于脚本解析。r1.1 是本次针对审稿意见的修订版，记录得最细。
 */
function cnido_changelog()
{
    return array(

        array(
            'version' => 'r1.5',
            'date'    => '2026-09-27',
            'title'   => 'Corrective release — classification of Pachycerianthus multiplicatus corrected to the WoRMS placement, a search box for the taxonomy table, and a transcriptome assembly column in the coverage matrix',
            /* 首页「Recent Updates」用的一句话概括，写成与其他版本一致的名词短语。 */
            'summary' => 'Pachycerianthus multiplicatus reclassified to the WoRMS placement; a search box for the taxonomy table; a transcriptome assembly column in the coverage matrix.',
            'entries' => array(

                '<i>Pachycerianthus multiplicatus</i> is now recorded as <b>Hexacorallia / '
                    . 'Ceriantharia</b> instead of in a class of its own. Taxonomic authorities '
                    . 'place the ceriantharians differently &mdash; WoRMS (AphiaID 101013, the '
                    . 'record this site links to for the species) treats Ceriantharia as an order '
                    . 'within the class Hexacorallia, whereas NCBI (taxid 1531352) ranks it as a '
                    . 'subclass alongside Hexacorallia and Octocorallia &mdash; and the site had '
                    . 'followed NCBI, which gave the catalogue an eighth class that no filter, '
                    . 'drop-down or count on any other page acknowledged. It now follows WoRMS: '
                    . 'the species is listed under <b>Hexacorallia</b> in the taxonomy table and '
                    . 'under the <b>Hexacorallia</b> filter alike, and <b>Ceriantharia</b> appears '
                    . 'in its <i>Order</i> column. <code>Spirularia</code>, the suborder it belongs '
                    . 'to within Ceriantharia, has no column of its own in the taxonomy table and '
                    . 'is named on the species page instead.',

                'The catalogue therefore holds <b>seven</b> classes again. The other three counts '
                    . 'are unchanged at <b>326</b> species, <b>23</b> orders and <b>109</b> '
                    . 'families: Ceriantharia replaces Spirularia as the order this species '
                    . 'contributes, so the order count is unaffected by the correction. The '
                    . 'Taxonomy, Statistics and BUSCO modules, the coverage matrix and the user '
                    . 'manual all read these figures from the database, so they follow the '
                    . 'correction rather than having to be edited separately.',

                'A link of the form <code>browse.php?class=Ceriantharia</code> &mdash; which the '
                    . 'previous release offered, in the user manual, as one of the two ways to '
                    . 'reach this species &mdash; no longer returns anything: Ceriantharia is not '
                    . 'a class, and the taxonomy view lists classes. Reach the species from '
                    . '<em>All</em>, from the <em>Hexacorallia</em> filter, or on its own page at '
                    . '<code>speciesinfo.php?species=PMULT</code>.',

                'The taxonomy table gained a <b>search box</b>. It narrows the rows to those '
                    . 'containing a text fragment, matching case-insensitively across all nine '
                    . 'columns &mdash; the Linnaean path from phylum to species and the three '
                    . 'external identifiers. It is a plain server-side filter and works with '
                    . 'JavaScript disabled: the term travels in the URL as <code>q</code>, '
                    . 'combines with the class filter, and is preserved by the page-size and '
                    . 'paging controls, so a search can be bookmarked, sent to a colleague or '
                    . 'scripted like any other view &mdash; '
                    . '<code>browse.php?class=Hexacorallia&amp;q=cerianth</code> finds '
                    . '<i>Pachycerianthus multiplicatus</i>. The table is now sorted by class, '
                    . 'then order, then species name, so its order is fixed and a page number is '
                    . 'a reproducible citation when quoted together with the filter, the search '
                    . 'term and the page size.',

                'The <b>data coverage matrix</b> now reports <b>16</b> data types rather than 15, '
                    . 'and the three transcriptome kinds are grouped under one <em>Transcriptome</em> '
                    . 'heading labelled <em>Data</em>, <em>Assembly</em> and <em>Network</em>. The new '
                    . '<b>Transcriptome assembly</b> column is a tick rather than a count: it marks '
                    . 'the <b>157</b> of 326 species for which an assembled transcriptome is held '
                    . 'here, whose predicted proteins carry functional annotation. It is not implied '
                    . 'by the <em>Data</em> column beside it &mdash; <b>185</b> species have RNA-seq '
                    . 'samples and <b>53</b> of those have no assembly, while <b>25</b> have an '
                    . 'assembly but no sample records &mdash; and the exports follow the same '
                    . 'presence-only convention: the column is headed <code>(1/0)</code> in the TSV, '
                    . 'like <i>Genome</i> and <i>JBrowse</i>, rather than <code>(n)</code>. The legend under '
                    . 'the table states those figures together with the <b>31</b> species that have a '
                    . 'co-expression network. All three places the matrix appears &mdash; the '
                    . 'standalone page, the Statistics page and the foot of the Genomic Data page '
                    . '&mdash; and both export formats carry the new column.',
            ),
        ),

        array(
            'version' => 'r1.4',
            'date'    => '2026-09-24',
            'title'   => 'Content update — epigenome re-analysis and species-tree rebuild, with text and statistics corrections',
            /* 首页「Recent Updates」用的一句话概括，只列到首页为止 —— 逐条明细在下面的
               entries 里，release.php 页面与 api.php 读的都是它。这一栏是首页列表的一行，
               所以写成与其他版本一致的名词短语，不写整句（r1.3/r1.4 原来是两个长句，
               首页上比别的版本高出一倍）。 */
            'summary' => 'Epigenome re-analysis and species-tree rebuild, with text and statistics corrections.',
            'entries' => array(

                'The <b>epigenomic datasets have been re-analysed</b>. The peak calling behind '
                    . 'the ATAC-seq, ChIP-seq and DNase-seq results had three defects. Every '
                    . 'ChIP-seq set had been called with one fixed effective genome size, '
                    . '<code>-g 2.61e8</code> &mdash; the value for <i>Nematostella vectensis</i> '
                    . '&mdash; regardless of the species it came from; the genome size is part of '
                    . 'the background model MACS2 fits, so a value far larger than a species\' own '
                    . 'genome makes the model expect more background than the data contain and the '
                    . 'peaks are under-called (in <i>Hydra vulgaris</i> it suppressed them '
                    . 'severely). Paired-end libraries had been read in single-end mode '
                    . '(<code>-f BAM</code> instead of <code>-f BAMPE</code>), which discards the '
                    . 'fragment-length information that the same model is built on. And the four '
                    . 'broad histone marks &mdash; H3K27me3, H3K36me3, H3K4me1 and H4K20me1 '
                    . '&mdash; had been called in narrow-peak mode, the wrong peak shape for those '
                    . 'marks. All affected ChIP-seq sets and the affected '
                    . '<i>Hydra vulgaris</i> ATAC-seq sets have been re-called with the '
                    . 'species-specific genome size, <code>-f BAMPE</code> where the library is '
                    . 'paired-end, and <code>--broad --broad-cutoff 0.1</code> for the four broad '
                    . 'marks, and the peak tables, the per-sample analysis pages and the '
                    . 'genome-wide figures have all been regenerated. The module now serves '
                    . '<b>52</b> ChIP-seq peak sets &mdash; 2 in <i>Exaiptasia diaphana</i>, '
                    . '28 in <i>Hydra vulgaris</i> and 22 in <i>Nematostella vectensis</i> '
                    . '&mdash; and <b>40</b> ATAC-seq peak sets (5, 15 and 20 in the same three '
                    . 'species). The datasets, their samples and their accessions are unchanged, '
                    . 'and the module still covers <b>723</b> datasets '
                    . '(266 Bisulfite-Seq, 210 ATAC-seq, 193 ChIP-seq, 53 miRNA-seq, 1 '
                    . 'DNase-seq).',

                'The <b>species tree has been rebuilt with its non-cnidarian outgroups</b>. It '
                    . 'carries <b>153</b> tips &mdash; the 148 cnidarian species plus five '
                    . 'outgroups from the two phyla that sit outside Cnidaria (<i>Bolinopsis '
                    . 'microptera</i>, Ctenophora; <i>Corticium candelabrum</i>, <i>Oscarella '
                    . 'lobularis</i>, <i>Halichondria panicea</i> and <i>Sycon ciliatum</i>, '
                    . 'Porifera) &mdash; and it is rooted on those outgroups, so the root follows '
                    . 'from the data rather than being placed by hand on a tree that cannot '
                    . 'determine it. Branch support is reported as SH-aLRT and ultrafast bootstrap '
                    . 'values. Because the outgroups are not part of the catalogue, the page links '
                    . 'them to their NCBI records rather than to species pages, states where they '
                    . 'come from in a panel on the rooting, and counts the tree as 148 cnidarian '
                    . 'species plus 5 outgroups rather than as 153 cnidarian species. Subtree '
                    . 'files are offered at class, order and family level only.',

                'The <b>species counts quoted in several page descriptions have been '
                    . 'corrected</b>. Three pages &mdash; Taxonomy, Phenotype and Gene Family '
                    . '&mdash; carried a hard-coded "324 Cnidaria" in their '
                    . '<code>meta description</code>, the text a search engine or a link preview '
                    . 'shows and that no visitor sees on the page itself. The figure is now read '
                    . 'from the database instead of being written into the text, so it cannot fall '
                    . 'out of date again. On two of those pages the number is the size of the '
                    . 'catalogue the module browses; the Gene Family page covers the species that '
                    . 'have transcription-factor and ubiquitin-family assignments rather than the '
                    . 'whole catalogue, and its description now says so. The Phenotype page had '
                    . 'also inherited the Taxonomy page\'s wording and described itself as a '
                    . 'taxonomic overview, which it is not. On the BUSCO page, a sentence stated '
                    . 'that the core ortholog resource is built from the high-quality genomes '
                    . 'listed on that page; the resource uses its own, looser completeness cutoff '
                    . 'and is built from a larger and different set of genomes, and the sentence '
                    . 'now states the criterion and links to the resource.',

                'One row in each of the three <b>transposable-element</b> tables was the source '
                    . 'file\'s header line, imported as if it were a record: every column of it '
                    . 'held that column\'s own name, so a search filtering on region could return '
                    . 'a TE whose region was the literal string "Region". The three rows have been '
                    . 'deleted. No real record was affected, and the tables hold some 3.7&nbsp;'
                    . 'million rows between them.',

                'The <b>macrosynteny grid carries its own zoom controls</b> in the top right '
                    . 'corner of the figure: <b>&minus;</b>, <b>+</b> and <b>Reset</b>, with the '
                    . 'current zoom level between them (100% = the whole grid fits the panel). They '
                    . 'sit on the figure rather than in the toolbar above it, so they stay within '
                    . 'reach while you are looking at a zoomed-in block instead of having to scroll '
                    . 'back up to the toolbar; <b>Reset</b> is the toolbar\'s <b>Fit</b>, i.e. back '
                    . 'to the whole grid at 100%. The two buttons grey out at their limits &mdash; '
                    . '<b>&minus;</b> at the fitted view, past which zooming out only shrinks the '
                    . 'figure &mdash; and the readout, the toolbar readout and the buttons all '
                    . 'follow the same zoom, whichever one you use (buttons, Ctrl + mouse wheel or '
                    . 'double-click). In full screen the same controls move to the top right corner '
                    . 'of the window.',
            ),
        ),

        array(
            'version' => 'r1.3',
            'date'    => '2026-09-23',
            'title'   => 'Content update — BUSCO assessment completed for every species with a protein set; single-cell gene identifiers cross-referenced; macrosynteny plots redrawn',
            /* 首页「Recent Updates」用的一句话概括。首页只列这一句，不再展开逐条明细
               —— 明细在下面的 entries 里，release.php 页面与 api.php 读的都是它。 */
            'summary' => 'BUSCO completeness extended to every species with a protein set, single-cell gene IDs cross-referenced, and the macrosynteny plots redrawn.',
            'entries' => array(
                'The BUSCO completeness table now assesses <b>every species that has a deposited '
                    . 'protein set</b>. Two species that had a protein set but had never been '
                    . 'assessed &mdash; <i>Alatina alata</i> (C:6.4% [S:6.1%, D:0.2%], F:6.2%, '
                    . 'M:87.5%) and <i>Calvadosia cruxmelitensis</i> (C:40.5% [S:38.7%, D:1.7%], '
                    . 'F:15.6%, M:43.9%) &mdash; have now been scored against the same '
                    . '<b>cnidaria_odb12</b> lineage (3,203 BUSCOs) as the rest of the table. The '
                    . 'table\'s 148 rows and the 148 species that have a deposited protein set are '
                    . 'therefore the same set. Before this, these two species had no completeness '
                    . 'figure anywhere on the site, and the table held 146 rows.',

                'Four Myxozoa that have a gene-model table but had no BUSCO assessment '
                    . '(<i>Henneguya salminicola</i>, <i>Myxobolus honghuensis</i>, <i>Myxobolus '
                    . 'squamalis</i> and <i>Thelohanellus kitauei</i>) have been scored on the same '
                    . 'lineage. They are the most genome-reduced animals known and measure '
                    . 'C = 3.6&ndash;7.6%, far below the high-quality threshold; that is the '
                    . 'expected biology of the group rather than a problem with the assemblies, and '
                    . 'their result pages say so.',

                'Both of these protein sets are deposited with their gene identifiers repeated '
                    . 'many times over &mdash; <i>Alatina alata</i> carries 66,156 protein records '
                    . 'under 2,795 distinct identifiers, <i>Calvadosia cruxmelitensis</i> 26,258 '
                    . 'under 2,078 &mdash; and the repeated records are different proteins, not '
                    . 'copies of one. A file like this cannot be given to BUSCO as it stands, '
                    . 'because BUSCO rejects any repeated sequence name, and keeping a single record '
                    . 'per identifier would discard about 96% of the deposited set. Every record was '
                    . 'therefore kept and the repeated names were made unique for the run only; the '
                    . 'sequences themselves are untouched, so these two figures describe each '
                    . 'protein set as deposited.',

                'The BUSCO <b>hit-level</b> table has been rebuilt for the nine species whose '
                    . 'protein sets are deposited with every protein listed twice. Those nine had '
                    . 'been re-scored on the same lineage earlier in September 2026 and their '
                    . 'completeness figures updated, but the per-hit rows underneath them were '
                    . 'never replaced and still came from the original run, in which the doubled '
                    . 'input had made BUSCO mark essentially every hit as <i>Duplicated</i>. '
                    . 'Across the nine, all 56,690 rows were Duplicated or Fragmented and '
                    . '<b>not one</b> was Complete, so a result page could set a 95% complete '
                    . 'panel directly above a table containing no complete hit at all '
                    . '(<i>Actinia mediterranea</i> had 9,548 rows and none of them Complete). '
                    . 'The rows now come from the same re-run as the figures, so the table and '
                    . 'the panel agree for all nine. The gene named on each row is also the '
                    . 'accession this site uses everywhere else, so those rows resolve to gene '
                    . 'pages; the identifiers they carried before resolved to no page here.',

                'A header line from the source file behind the BUSCO hit table had been imported '
                    . 'as if it were a hit; it has been deleted from the table. It was never '
                    . 'visible in the downloads, which have always filtered it out, so nothing '
                    . 'that can be downloaded changes.',

                'In the BUSCO summary, the <code>n_buscos</code> column carried two different '
                    . 'meanings: in most rows the number of BUSCOs with a hit, but in the rows '
                    . 'added by the later re-scorings the lineage size (3,203) instead. It now '
                    . 'holds the number recovered in every row, so '
                    . '<code>n_buscos + n_missing = lineage_size</code> throughout, and the '
                    . 'column description in the download notes now says so.',

                'The BUSCO versions listed in the database statistics table now say which '
                    . 'protocol each one covers rather than crediting the whole assessment to a '
                    . 'single version: protein-mode runs under v.5.8.2 and v.6.0.0 (27 species '
                    . 're-scored under the newer one) and genome-mode runs under v.6.0.0.',

                'Database content statistics have been recomputed (326 species; 321 with an INSDC '
                    . 'assembly accession; 173 species with a mitochondrial genome; 515,427 BUSCO '
                    . 'hit records).',

                'Single-cell gene identifiers are now <b>cross-referenced with the rest of the '
                    . 'site</b>. A dataset is deposited with its own identifiers &mdash; '
                    . '<code>NV2.8285</code> in <i>Nematostella vectensis</i>, '
                    . '<code>Amil_Amillepora19313</code> in <i>Acropora millepora</i> &mdash; while '
                    . 'every other page here names a gene by the accession in that species\' '
                    . '<code>&lt;ABBR&gt;_locus.mRNA</code> column, so the same gene had one name '
                    . 'in the single-cell module and another everywhere else and the two could not '
                    . 'be looked up against each other. The accession is now the label shown on the '
                    . 'marker table and the gene pages, with the dataset\'s own identifier in '
                    . 'parentheses; links still carry that identifier, because it is what the '
                    . 'expression files are keyed by, and the gene search box accepts either '
                    . 'spelling. Identifiers for which no correspondence could be evidenced are '
                    . 'marked <b>not mapped</b> rather than given a best guess, and the two '
                    . 'catalogues that have no cross-reference at all say so in words instead.',

                'The single-cell manual has a new section, <i>4. Gene identifiers in this '
                    . 'module</i>, explaining the two naming systems, the evidence a '
                    . 'cross-reference has to meet (a reciprocal best BLAST hit, kept only where '
                    . 'the proteins align over at least half of the shorter one at 50% identity or '
                    . 'better, or an NCBI/submitter correspondence table), and why an unmapped '
                    . 'identifier is left as it is. It prints the coverage per dataset, computed '
                    . 'from the mapping at the moment the page is opened: 5,504 of the 8,594 '
                    . 'identifiers used by the 12 interactive datasets, and 8,158 of the 10,794 '
                    . 'in the largest published marker table.',

                'The <b>macrosynteny plots have been redrawn</b>. At the zoom the page opens '
                    . 'with, the Oxford grid now shows <b>one cell per pair of chromosomes</b>, '
                    . 'tinted by its linkage group and labelled with the number of anchors the two '
                    . 'chromosomes share, so which blocks are conserved and how big they are can '
                    . 'be read without zooming. The previous rendering drew every anchor as a dot '
                    . 'at that zoom, where on a large comparison several thousand of them fell on '
                    . 'top of one another and the grid was a grey smudge. Zooming past about '
                    . '5&nbsp;pixels per anchor switches to the individual dots, which is where a '
                    . 'single gene can be looked up. Chromosome names are now drawn at a fixed '
                    . 'screen size &mdash; previously every one of them, including the two species '
                    . 'names, was hidden as soon as the fitted view was small enough &mdash; names '
                    . 'that will not fit are dropped rather than overprinted, and long identifiers '
                    . 'are abbreviated in the middle so that they stay distinguishable. The '
                    . 'alternating grey shading that covered the whole plot as a chequerboard has '
                    . 'been replaced by a ruler and pale boundary lines along the two axes. On the '
                    . 'highly fragmented genomes, where an axis carries a thousand or more contigs, '
                    . 'the stretch holding the smallest ones is now shaded and bounded by a dashed '
                    . 'line, so that the empty half of such a plot reads as "the rest of the '
                    . 'contigs" rather than as a figure that failed to draw. Cells '
                    . 'can be hovered for the pair\'s significance, linkage group and &rho;, and '
                    . 'clicked to zoom into that block; the chromosome-pair table below the plot '
                    . 'selects and highlights the same blocks.',

                'Zooming the macrosynteny grid now keeps the point under the pointer fixed in '
                    . 'every case. It was applied relative to the plot\'s outer frame, which is '
                    . 'not where the scrollable area starts once the figure is panned, so the view '
                    . 'could slide sideways on a zoom step instead of staying where it was aimed.',
            ),
        ),

        array(
            'version' => 'r1.2',
            'date'    => '2026-09-21',
            'title'   => 'Content update — one new species, a BUSCO re-scoring, and coverage/stats corrections',
            /* 首页侧栏「Recent Updates」用的一行式摘要：从下面 entries 里压缩出来的，
               只讲使用者能观察到的事，不做 entries 之外的新陈述。首页不再自己写一份
               日期列表 —— 那份停在 2026-05-28，把 r1.1 和 r1.2 整个漏掉了。 */
            /* 首页「Recent Updates」用的一句话概括。首页只列这一句，不再展开逐条明细
               —— 明细在下面的 entries 里，release.php 页面与 api.php 读的都是它。 */
            'summary' => 'New species, a BUSCO re-scoring, coverage and statistics corrections, and BLAST fixes.',
            'entries' => array(
                'The catalogue now includes <i>Pachycerianthus multiplicatus</i> (assembly '
                    . 'GCA_984695265.1, chromosome level, 18 chromosomes, 602.64 Mb; BioProject '
                    . 'PRJEB114125). It was registered at the time in a class of its own, which '
                    . 'took the catalogue to 326 species in 8 classes. That classification was '
                    . 'corrected in <b>r1.5</b> to the WoRMS placement, class Hexacorallia with '
                    . 'order Ceriantharia, so the catalogue covers 326 species in <b>7</b> classes.',

                'This species is registered at genome level, and its mitochondrial genome '
                    . '(OZ478272.1, 28,394 bp) has been added to the Mitogenomic Data module. No '
                    . 'gene annotation has been released for either of the two assemblies available '
                    . 'for this species, so no gene-level modules (gene models, functional '
                    . 'annotation, gene families, expression) are shown for it; the coverage matrix '
                    . 'states this per cell rather than leaving the row blank.',

                'The coverage matrix now counts a mitochondrial genome from <b>both</b> of its '
                    . 'sources — the gene-feature table and the genome-level record table — exactly '
                    . 'as the Mitogenomic Data module does. Six species that have a genome-level '
                    . 'record but no gene features had previously been shown as having no '
                    . 'mitogenome, and their cells were empty. The database-wide count of species '
                    . 'with a mitochondrial genome is 173, not 167.',

                'Species totals quoted in the text of the taxonomy module, the genome page and the '
                    . 'user manual are now read from the database instead of being written into the '
                    . 'text, so they cannot fall out of date when the catalogue grows.',

                'Fixed a defect in the page caches that made updates applied from the command line '
                    . 'invisible on the website: the web process and the maintenance scripts were '
                    . 'writing their caches to two different locations, so a newly added species '
                    . 'could be present in the database and still absent from every page for up to '
                    . 'an hour. Both now share a single cache.',

                'A BUSCO completeness assessment has been added for <i>Pachycerianthus multiplicatus</i> '
                    . '(C:92.8% [S:91.8%, D:1.0%], F:2.8%, M:4.4% against the 3,203 BUSCOs of the '
                    . 'cnidaria_odb12 lineage). It is shown in the BUSCO completeness column of the '
                    . 'species page and in the BUSCO module. Because no annotation exists for this '
                    . 'species, the assessment was run in <b>genome mode</b> with genes predicted by '
                    . 'miniprot during the run, rather than on a deposited protein set as for every '
                    . 'other species, and 30.1% of its complete matches contain internal stop codons. '
                    . 'Its completeness figure is therefore an optimistic upper bound and is not '
                    . 'directly interchangeable with the others. Rows scored this way are labelled '
                    . '<b>genome mode</b> in the BUSCO module, and they are excluded from the set of '
                    . 'genomes used to build the core ortholog resource.',

                'Six rows of the BUSCO module have been <b>split into the species they actually '
                    . 'described</b>. The source file those figures came from had truncated species '
                    . 'codes to five characters, so six codes each stood for two or three species '
                    . '(for example <code>AAURI</code> held both <i>Aurelia aurita</i> and '
                    . '<i>Aurelia aurita</i> complex sp. Pacific, and <code>ASP</code> held three '
                    . 'species). The module showed those twelve species as six merged rows and could '
                    . 'not attribute a class to any of them. Each species has now been re-assessed '
                    . 'individually against the same cnidaria_odb12 lineage, so the twelve rows are '
                    . 'directly comparable with the rest of the table. The merge had also made the '
                    . 'completeness figures wrong in a specific direction: a BUSCO matched by both '
                    . 'species of a pair was counted as duplicated, so the six rows reported a '
                    . 'duplicated fraction of 65–87% where the species actually measure about 1%. '
                    . 'With the split, <i>Callogorgia gracilis</i> (C:91.6%, D:0.7%, F:4.7%) newly '
                    . 'meets the high-quality threshold.',

                'The BUSCO completeness table now lists <b>one row per species, and only species '
                    . 'whose genome has a deposited protein sequence set</b>. The figures in that '
                    . 'table are meant to be read against each other, and a species whose genes had '
                    . 'to be predicted during the assessment is not measured on the same footing: '
                    . 'its completeness is an optimistic upper bound. <i>Pachycerianthus multiplicatus</i> '
                    . 'is the one such species here, and it is no longer listed in the comparison '
                    . 'table &mdash; its assessment is unchanged and is still shown on its species '
                    . 'page. The table therefore covers 142 species.',

                'Database content statistics have been recomputed (326 species; 321 with an INSDC '
                    . 'assembly accession; 173 species with a mitochondrial genome; 541,746 BUSCO '
                    . 'hit records).',

                'BLAST: the "all databases" search option has been <b>repaired</b>. It had been '
                    . 'searching only the first database in the list rather than the whole '
                    . 'collection, so every search run that way returned hits from a single '
                    . 'species (<i>Acropora acuminata</i>) and a query with no match in that one '
                    . 'species was reported as having no matches at all. Selecting a single '
                    . 'species was unaffected. The collection now also includes the two protein '
                    . 'datasets that were missing from it, <i>Acropora microphthalma</i> and '
                    . '<i>Porites australiensis</i>, so a protein search can reach all '
                    . '<b>148</b> annotated genomes. Because a whole-collection search scans every '
                    . 'database, it takes one to two minutes; a single-species search returns in a '
                    . 'second or two and the search form now says so.',
            ),
        ),

        array(
            'version' => 'r1.1',
            'date'    => '2026-09-16',
            'title'   => 'Revision release — peer-review response',
            /* 首页「Recent Updates」用的一句话概括。首页只列这一句，不再展开逐条明细
               —— 明细在下面的 entries 里，release.php 页面与 api.php 读的都是它。 */
            'summary' => 'Peer-review revision: server-rendered selectors, security fixes, and new pages.',
            'entries' => array(
                'All species and taxonomic-class selectors are now rendered by the server instead '
                    . 'of being built in JavaScript. Species lists are populated from the database '
                    . 'in every module, so the selectors work with JavaScript disabled and no longer '
                    . 'show an empty or stale list.',

                'The deprecated JavaScript option-list library was removed from all 43 pages that '
                    . 'still loaded it. It rebuilt every dependent drop-down on page load from '
                    . 'hard-coded arrays, which is why child selectors could empty themselves or '
                    . 'disagree with the species actually selected.',

                'Proteomic Analysis: the species selector submitted a short code (for example '
                    . '"SPIST") while the underlying data tables are keyed by the full species name '
                    . '("Stylophora_pistillata"), so those selections produced an empty table and a '
                    . 'raw database error. The page now resolves either form through the species '
                    . 'table, only queries a table that exists, and states plainly which dataset it '
                    . 'is showing when the requested one is unavailable.',

                'The duplicate copy of the Proteomic Analysis page under /submit/ was replaced by a '
                    . 'permanent redirect to the live tool. It was reachable but not linked, used a '
                    . 'separate and equally stale code path, and discarded the user\'s input.',

                'Pfam and BUSCO result pages: the species parameter is now validated and escaped '
                    . 'before being used in a query, and the pfam accession likewise. Both pages '
                    . 'previously interpolated these values into SQL unescaped, which allowed an '
                    . 'arbitrary-table read, and echoed them into the page unescaped, which allowed '
                    . 'script injection. A malformed link on the Pfam table was also corrected.',

                'Gene-set enrichment result pages: the job identifier, species code and gene-set '
                    . 'name are now validated against their real value ranges before any use. The '
                    . 'job identifier is restricted to digits and the species and gene-set names to '
                    . 'their permitted character sets, closing a path-traversal and an injection '
                    . 'route reachable from a crafted result URL.',

                'Download handler hardened: the file parameter is reduced to a base name, resolved '
                    . 'with realpath() and refused unless it lies inside the download directory. '
                    . 'Requests for files that do not exist now return 404 instead of a silent '
                    . 'zero-byte download, and large files are streamed rather than read into memory.',

                'New Data Coverage Matrix page: a species-by-data-type availability table covering '
                    . 'all species and all 15 data types, with each populated cell linking directly '
                    . 'to the corresponding module for that species. Downloadable as tab-separated '
                    . 'text.',

                'New Functional Domain Search page and new per-species portal pages.',

                'New release and changelog page (release.php) with the database version number, '
                    . 'last-update date, update schedule and this changelog, plus a machine-readable '
                    . 'metadata interface (api.php) and a downloadable file manifest for scripted '
                    . 'access. The version stamp now appears in the footer of every page.',
            ),
        ),

        array(
            'version' => 'r1.0',
            'date'    => '2026-05-28',
            'title'   => 'Content release',
            /* 首页「Recent Updates」用的一句话概括。首页只列这一句，不再展开逐条明细
               —— 明细在下面的 entries 里，release.php 页面与 api.php 读的都是它。 */
            'summary' => 'Content release: new datasets, analysis tools and multi-omics pages.',
            'entries' => array(
                'Mitochondrial datasets added to the Mitogenomic Data module.',
                'Pan-geneset analysis added.',
                'Species description pages added.',
                'Cnidarian phylogeny made available in the Species Tree module.',
                'Phenotype datasets added.',
                'Multi-omics pages brought online (genome, transcriptome, single-cell, proteome, '
                    . 'epigenome, metagenome).',
                'Dynamic expression view added to the network module.',
                'Gene-set enrichment analysis made available.',
                'BLAST search tool integrated.',
                'Taxonomic browsing implemented across the catalogue.',
                'Co-expression networks added.',
                'Primer design tool added.',
                'JBrowse genome browser deployed.',
            ),
        ),

        array(
            'version' => 'r0.9',
            'date'    => '2025-11-18',
            'title'   => 'Initial public release',
            /* 首页「Recent Updates」用的一句话概括。首页只列这一句，不再展开逐条明细
               —— 明细在下面的 entries 里，release.php 页面与 api.php 读的都是它。 */
            'summary' => 'Initial public release.',
            'entries' => array(
                'CnidoSite first released, with the cnidarian genome and fossil-record modules.',
            ),
        ),
    );
}

/**
 * 渲染变更记录的一条正文，供 release.php 的 <li> 使用。
 *
 * 条目正文里一直用着 <b>/<i> 做强调（见上面每一条 entry），但 release.php 原来
 * 是 htmlspecialchars() 整条转义，于是页面上直接显示出 "<i>Pachycerianthus
 * multiplicatus</i>" 这样的字面量 —— 所有版本的条目都是如此，不只是新加的。
 *
 * 做法是先整条转义、再把白名单里的行内标签还原：只放行实际用到、且无脚本风险的
 * 那几个标签，其余（含任何属性写法）保持转义。这样既恢复排版，又不像直接输出
 * 原串那样把变更记录变成一个注入面。api.php 走的是 JSON，不经这里，仍是原文。
 */
function cnido_rel_entry_html($e)
{
    $s = htmlspecialchars((string)$e, ENT_QUOTES, 'UTF-8');
    $allow = array('b', 'i', 'code', 'em', 'strong', 'br');
    $s = preg_replace_callback(
        '#&lt;(/?)(' . implode('|', $allow) . ')(\s*/?)&gt;#i',
        function ($m) { return '<' . $m[1] . strtolower($m[2]) . $m[3] . '>'; },
        $s
    );
    /* 再还原白名单里的字符实体。整条先 htmlspecialchars() 会把 &mdash; 变成
       &amp;mdash;，而上面那一步只还原标签，于是页面上直接显示出 "&mdash;"
       这几个字符 —— 变更记录里用了 &mdash; / &nbsp; / &rho; 的条目全都如此，
       不只是新加的（不用数值实体：只放行下面这些有名字的，注入面最小）。 */
    $ent = array('mdash', 'ndash', 'nbsp', 'times', 'minus', 'plusmn', 'middot',
                 'le', 'ge', 'lt', 'gt', 'amp', 'rho', 'alpha', 'beta', 'gamma',
                 'mu', 'deg', 'hellip');
    return preg_replace_callback(
        '#&amp;(' . implode('|', $ent) . ');#i',
        function ($m) { return '&' . strtolower($m[1]) . ';'; },
        $s
    );
}

/**
 * 把变更记录压成一行行 "版本\t日期\t说明"，供 api.php 的 TSV 输出使用。
 * 只有条目标题，不含逐条明细 —— 明细请用 JSON 格式。
 */
function cnido_changelog_flat()
{
    $out = array();
    foreach (cnido_changelog() as $rel) {
        $out[] = array($rel['version'], $rel['date'], $rel['title'], count($rel['entries']));
    }
    return $out;
}
