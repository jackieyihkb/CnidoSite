<?php
/* =====================================================================
 * 数据模块 → 详情页深链
 *
 * 审稿意见 Referee 2 major 1：
 *   "When a species is selected in the Taxonomy module, users should be able to
 *    directly access all available genome, bulk transcriptome, single-cell,
 *    proteome, epigenome, metagenome, phenotype and palaeobiology resources for
 *    that species."
 *
 * 各模块是多年间分别开发的，接受物种参数的「值域」并不一致：
 *   - 基因组 / 注释 / 单细胞 / 蛋白组 / 线粒体 / TE / JBrowse 用 abbr1 短码（NVECT）
 *   - 转录组 / 表型 / 古生物 / 宏基因组 / 表观组用拉丁名（Nematostella vectensis）
 * 这个差异不应该外泄到调用方（species_portal.php、coverage_matrix.php），
 * 所以统一在这里按模块拼 URL。
 * ===================================================================== */

/**
 * 生成某个物种在某个模块下的深链。
 *
 * @param string $mod   cnido_modules() 里的模块键
 * @param string $abbr  abbr1 短码
 * @param array  $info  array('species' => 拉丁名, 'abbr' => 下划线名, 'class' => ...)
 * @return string       URL；模块无对应页面时返回空串
 */
function cnido_module_link($mod, $abbr, $info = array())
{
    $latin = isset($info['species']) ? $info['species'] : '';
    $cls   = isset($info['class'])   ? $info['class']   : '';
    $a = urlencode($abbr);
    $s = urlencode($latin);
    switch ($mod) {
        case 'genome':        return 'genomeinfo.php?species=' . $a;
        case 'annotation':    return 'search.php?species=' . $a;
        case 'function':      return 'domain_search.php?sp=' . $a;
        /* genefamily.php 只有全站 orthogroup 计数，没有物种维度（加上物种过滤
           会退化成一个 2 秒以上的全表扫描），所以物种级入口指向 gene_family.php
           的 TF / 泛素家族，orthogroup 浏览器放在子链接里。 */
        case 'genefamily':    return 'gene_family.php?species=' . $a;
        case 'transcriptome': return '/cytoscape/trans_data.php?species=' . $s;
        /* trans_assembly_species.php 的物种参数就是它自己那张物种表的 abbr1
           （见该页的 $filter 定义与 `WHERE abbr1 = ?`），所以传 $a。 */
        case 'trans_assembly': return 'trans_assembly_species.php?species=' . $a;
        case 'coexpression':  return '/cytoscape/network.php?species=' . $a;
        case 'singlecell':    return 'sn_data.php?species=' . $a;
        case 'proteome':      return 'proteomic_data.php?species=' . $a;
        /* epigenomic_data.php 先按 class 过滤出物种列表，再在列表里核对 species；
           不带 class 时它会用默认的 Hexacorallia，于是 Hydrozoa / Myxozoa 的深链
           会被静默回退到该类的第一个物种（Hydra vulgaris 的链接会打开
           Acropora cervicornis）。类群必须一起传。 */
        case 'epigenome':     return 'epigenomic_data.php?class=' . urlencode($cls) . '&species=' . $s;
        case 'metagenome':    return 'metagenomic_data.php?species=' . $s;
        case 'phenotype':     return 'phenotype.php?class=all&species=' . $s;
        case 'paleobiology':  return 'paleobiology.php?species=' . $s;
        case 'mitogenome':    return 'mitdata.php?species=' . $a;
        case 'TE':            return 'TE.php?species=' . $a;
        case 'jbrowse':       return 'jbrowse.php?species=' . $a;
    }
    return '';
}

/**
 * 某个物种在某个模块下「还能点进哪些更细的功能页」。
 * 例如单细胞有 Cell Atlas / Cell Marker / Gene Expression 三个子页，
 * 矩阵里只点得到一个，门户页则把它们都列出来。
 *
 * @return array  array(标签 => URL)
 */
function cnido_module_subpages($mod, $abbr, $info = array())
{
    $latin = isset($info['species']) ? $info['species'] : '';
    $a = urlencode($abbr);
    $s = urlencode($latin);
    switch ($mod) {
        case 'function':
            /* 这四页现在都接受 ?species=<拉丁名> 深链（下拉框改为服务端渲染后
               才做到的），所以门户页可以直接带着物种跳过去。 */
            return array(
                'InterPro by gene ID' => 'interpro.php?species=' . $s,
                'Pfam by gene ID'     => 'proteindomain.php?species=' . $s,
                'GO by gene ID'       => 'go.php?species=' . $s,
                'KEGG by gene ID'     => 'kegg.php?species=' . $s,
            );
        case 'singlecell':
            return array(
                'Cell Atlas'       => 'cell_atlas.php?species=' . $a,
                'Cell Marker'      => 'cell_marker.php?species=' . $a,
                'Gene Expression'  => 'gene_exp.php?species=' . $a,
            );
        case 'proteome':
            return array('Proteomic Analysis' => 'proteomic_analysis.php?species=' . $a);
        case 'epigenome':
            /* 这几页的 cnido_state 默认值是 NVECT / EDIAP / ADIGI 这类 abbr1 短码，
               必须传 $a；epigenomic_data.php 本身收拉丁名，两者不要混。
               miRNA_analysis.php 目前不接受物种参数，故不列出。 */
            return array(
                'DNA Methylation' => 'DNA_methylation.php?species=' . $a,
                'ATAC-seq'        => 'ATAC_analysis.php?species=' . $a,
                'DNase-seq (DHS)' => 'DHS_analysis.php?species=' . $a,
                'ChIP-seq'        => 'ChIP_analysis.php?species=' . $a,
            );
        case 'genome':
            return array(
                'Full assembly record' => 'speciesinfo.php?species=' . $a,
                'BUSCO'           => 'busco.php?species=' . $a,
                'TFs / Ubs'       => 'gene_family.php?species=' . $a,
                /* pan-geneset.php 不读任何 URL 参数：它是**跨物种**的泛基因组/
                   基因家族扩张收缩分析，按物种过滤没有意义。原来这里传
                   ?species=$a，而 species_portal.php 的引言写着「每个入口都直接
                   带着本物种的筛选打开对应模块」—— 这一条是假的。标签里写明它
                   是全物种视图，参数去掉。 */
                'Pan-geneset (all species)' => 'pan-geneset.php',
            );
        case 'genefamily':
            return array('Orthogroup browser' => 'genefamily.php');
        case 'transcriptome':
            return array('Dynamic Expression View' => '/cytoscape/network_expression.php?species=' . $a);
        case 'metagenome':
            return array('MAGs Catalog' => 'MAGs.php?species=' . $s);
        case 'phenotype':
            /* phenotype_species.php 收拉丁名（也认下划线形式和 abbr1），所以传 $s。
               它是「这个物种的全部表型记录，按 trait 类别分组、每条标注来源属性」的
               那一页；主链接仍指向平表视图。 */
            return array('Grouped by trait category' => 'phenotype_species.php?species=' . $s);
    }
    return array();
}

/**
 * 哪些模块的 $cov[$abbr][$mod] 是「真实记录条数」，哪些只是「有/没有」的 1。
 *
 * 多数模块的记录数在覆盖表里是真的（基因模型数、样本数、峰数、线粒体基因数……），
 * 但基因组 / 功能注释 / 基因家族 / 浏览器轨道 / TE 这几类是按「内容表是否存在」
 * 判定的，值恒为 1 —— 把 1 当成「有 1 条记录」展示出来会让读者以为每个物种
 * 只有一条组装，所以要区分对待：这类显示对勾，值只用来判断「有没有」。
 *
 * 共表达网络同理（值同样是存在性 1），一并列为存在性。
 * 古生物产出（paleobiology）也放这一组：库里它的值恒为 1（71 个物种有、255 个
 * 没有），既不是「化石产出条数」也不是别的可加总的量，显示成「1」同样会被
 * 误读成「这个物种只记录了一条产出」，因此改显示对勾。
 * 转录组组装（trans_assembly）同样是整列存在性：判据就是「这个物种在
 * trans_assembly_species 里有没有一行」，所以这里的 1 也只是「有」，不给计数。
 *
 * 这里判的是「整个模块」的口径。个别物种的格子还可能带着存在性占位（值为 1
 * 却没有计数，例如只有基因组级线粒体记录、没有基因级注释的物种），那种情况由
 * coverage 缓存的 $flag 逐格标注，调用方必须一并看 —— 见 coverage_matrix_view.php。
 */
function cnido_module_count_is_meaningful($mod)
{
    static $real = array(
        'annotation'    => true,   // <ABBR>_locus 行数 = 蛋白编码基因模型数
        'transcriptome' => true,   // sample 表按物种计数的 RNA-seq 样本数
        'singlecell'    => true,
        'epigenome'     => true,
        'metagenome'    => true,
        'phenotype'     => true,
        'mitogenome'    => true,   // 线粒体基因数，单元格里带单位 genes
        'proteome'      => true,
    );
    return isset($real[$mod]);
}
