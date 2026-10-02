<?php
/* =====================================================================
 * 单物种「这个物种到底有哪些数据」的卡片网格
 *
 * 审稿意见 Referee 2 major 1：
 *   "When a species is selected in the Taxonomy module, users should be able to
 *    directly access all available genome, bulk transcriptome, single-cell,
 *    proteome, epigenome, metagenome, phenotype and palaeobiology resources for
 *    that species."
 *
 * 这段标记原先只长在 species_portal.php 里。现在 speciesinfo.php 成了物种的着陆页
 * （About / Basic Information / Genome Assembly Information / References 之后接这一块），
 * 两处必须给出**同一份**东西，所以抽成共享 include，而不是各写一遍 —— 各写一遍的下场
 * 是「加了一个模块，只有其中一页出现」。
 *
 * 数据可用性来自 includes/coverage.php 的 cnido_coverage()，与 coverage_matrix.php、
 * speciesinfo.php 覆盖清单同源（1 小时缓存），三处的数字永远一致。
 * 深链的拼装交给 includes/modlinks.php —— 各模块接受物种参数的「值域」并不一致
 * （有的要 abbr1 短码，有的要拉丁名），那个差异不该外泄到调用方。
 * ===================================================================== */
require_once __DIR__ . '/coverage.php';
require_once __DIR__ . '/modlinks.php';

/**
 * 卡片网格的样式。由调用页在 <head> 里 echo 出来。
 *
 * 不做成独立 .css 文件是有意的：站内静态资源只有 Last-Modified、没有
 * Cache-Control，浏览器按启发式缓存几个小时，改了样式用户可能看不到。
 * 规则很少，内联最省事。
 */
function cnido_spcards_css()
{
    return <<<CSS
.sp-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(310px,1fr));gap:14px;margin-bottom:22px}
.sp-card{border:1px solid #e2e8f0;border-radius:10px;padding:14px 16px;background:#fff}
.sp-card.has{border-left:4px solid #16a34a}
.sp-card.hasnt{border-left:4px solid #e2e8f0;background:#fbfcfd}
.sp-card h3{margin:0 0 6px;font-size:16px;color:#1e293b}
.sp-card .sp-cnt{font-size:15px;color:#15803d;font-weight:600;margin-bottom:6px}
.sp-card.hasnt .sp-cnt{color:#64748b;font-weight:400}
.sp-card p{margin:0 0 10px;font-size:16px;color:#64748b;line-height:1.6}
.sp-card .sp-links a{display:inline-block;font-size:15px;font-weight:600;color:#1d4ed8;
  text-decoration:none;margin:0 12px 4px 0}
.sp-card .sp-links a:hover{text-decoration:underline}
.sp-sub-link{font-size:15px;color:#64748b;margin:-4px 0 10px}
.sp-sub-link a{color:#1d4ed8;text-decoration:none;margin-right:10px}
CSS;
}

/**
 * 一个模块「有多少条记录」的措辞。
 *
 * 有些模块的计数格子只是「存在性占位」（值为 1、含义只是「有这个模块」），
 * 直接印成 "1 record" 会让人以为库里只有一条，所以那种情况下改说 available。
 *
 * 单位默认是 record，但模块可以自带 count_word 覆盖（cnido_modules() 里配）。
 * phenotype 就是这种情况：2026-09-27 从三家源库重建后，它那个数仍是**折叠后的
 * 行数**（内容完全相同的源记录已并成一行，源记录总数只写在 phenotype.php 页脚），
 * 所以用 rows 而不是 records —— 说成 "944 records" 会被读成「944 次表型观测」，
 * 而每一行只有源库自己声明的那个值，其中还有一部分是继承自上级分类单元的
 * （不是这个物种实测的）。
 */
function cnido_spcards_count_label($m, $n)
{
    if (!cnido_module_count_is_meaningful($m)) { return 'available'; }
    $mods = cnido_modules();
    $word = (isset($mods[$m]['count_word']) && $mods[$m]['count_word'] !== '')
          ? $mods[$m]['count_word'] : 'record';
    return number_format($n) . ' ' . $word . ($n === 1 ? '' : ($word === 'record' ? 's' : ''));
}

/**
 * 同一个生物在目录里被登了两次时，两句说明中的后一句。
 *
 * 目录里的 326 行是 326 个**名字**，不是 326 个生物：有两对是同一生物的两个名字，
 * 因此这两对各自共享一个 NCBI Taxonomy ID。读者在物种页上看到外链指向同一条 NCBI
 * 记录、或在校对物种数时撞见同一套组装号，会以为站点登重了；这里把话说明白，
 * 并把另一半的入口给出来。物种页（speciesinfo.php / species_portal.php）都调用它。
 *
 * 五个字段（两个名字、taxid、组装号、WoRMS 状态）都实地核过（2026-09-29）：
 *   - Desmophyllum pertusum ↔ Lophelia pertusa：共享 NCBI taxid 174260、共享组装
 *     GCA_029204205.1。WoRMS 的 135161（Lophelia pertusa）状态是 "superseded
 *     combination"、valid_AphiaID 指向 1245747（Desmophyllum pertusum）。
 *   - Actinoscyphia liui ↔ Actinoscyphia sp. m1220：共享 NCBI taxid 2928332、共享组装
 *     GCA_041296415.1 与 BioProject PRJNA817838。两者在 NCBI 里都还挂在
 *     "unclassified Actinoscyphia" 下；liui 是 2024 年那篇基因组文章（PMID 39166453）
 *     用的名字，正式描述当时尚未发表。
 *     **ALIUI 行原来记的 taxid 是 3256304，那是另一个分类单元**
 *     （Actinoscyphia sp. l JL-2024，名下没有任何组装），已改正为 2928332。
 *
 * @param string $abbr 规范 abbr1
 * @return string HTML；该物种不在任何一对里时返回空串
 */
function cnido_species_synonym($abbr)
{
    $map = array(
        'DPERT' => array(
            'other_abbr' => 'LPERT',
            'other_name' => 'Lophelia pertusa',
            'relation'   => 'the same species under the older of its two names',
            'taxid'      => '174260',
            'assembly'   => 'GCA_029204205.1',
        ),
        'LPERT' => array(
            'other_abbr' => 'DPERT',
            'other_name' => 'Desmophyllum pertusum',
            'relation'   => 'the same species under the name WoRMS currently accepts '
                          . '(<i>Lophelia pertusa</i> is recorded there as a superseded combination)',
            'taxid'      => '174260',
            'assembly'   => 'GCA_029204205.1',
        ),
        'ALIUI' => array(
            'other_abbr' => 'Actinoscyphia_sp_m1220',
            'other_name' => 'Actinoscyphia sp. m1220',
            'relation'   => 'the same still-undescribed species under the provisional name NCBI uses',
            'taxid'      => '2928332',
            'assembly'   => 'GCA_041296415.1',
        ),
        'Actinoscyphia_sp_m1220' => array(
            'other_abbr' => 'ALIUI',
            'other_name' => 'Actinoscyphia liui',
            'relation'   => 'the same still-undescribed species under the manuscript name of its genome paper',
            'taxid'      => '2928332',
            'assembly'   => 'GCA_041296415.1',
        ),
    );
    if (!isset($map[$abbr])) { return ''; }

    $e = $map[$abbr];
    return '<b>Catalogued twice under two names.</b> This entry and <i>'
         . htmlspecialchars($e['other_name']) . '</i> are ' . $e['relation'] . '. '
         . 'They therefore carry the same NCBI Taxonomy ID (' . $e['taxid']
         . ') and the same assembly (' . htmlspecialchars($e['assembly']) . '), and any count of '
         . '&ldquo;species&rdquo; on this site counts this organism once per name. '
         . '<a href="species_portal.php?species=' . urlencode($e['other_abbr']) . '">Open the other entry &rarr;</a>';
}

/**
 * 这个链接是不是指向「当前正在渲染的页面」。
 *
 * 只比路径部分（去掉 ?query 与 .../ 前缀），因为站点里同一个页面的写法不止一种
 * （`speciesinfo.php?species=A …` 与 `/speciesinfo.php?species=A …`）。查询串不参与
 * 比较：同一页换个物种参数仍是同一页，点了还是刷新当前页。
 */
function cnido_spcards_is_self($url, $self)
{
    $path = strtok($url, '?');
    $path = preg_replace('#^.*/#', '', $path);
    return strcasecmp($path, $self) === 0;
}

/**
 * 渲染卡片网格。
 *
 * @param array  $cov   cnido_coverage() 的返回值
 * @param string $abbr  规范 abbr1（必须是 $cov['species'] 的键）
 * @param array  $info  array('species' => 拉丁名, 'abbr' => 下划线名, 'class' => ...)
 * @param string $self  「当前页面的文件名」。给了就把指向自己的深链去掉 —— 例如
 *                      genome 模块的子链接「Full assembly record」指向 speciesinfo.php，
 *                      在本页渲染时会变成一个点了只是刷新当前页的死链。
 * @return string HTML
 */
function cnido_spcards_html($cov, $abbr, $info, $self = '')
{
    $modules = cnido_modules();
    $data = isset($cov['cov'][$abbr]) ? $cov['cov'][$abbr] : array();

    $h = '<div class="sp-grid">';
    foreach ($modules as $m => $cfg) {
        $n    = isset($data[$m]) ? (int)$data[$m] : 0;
        $has  = ($n > 0);
        $link = cnido_module_link($m, $abbr, $info);
        $subs = cnido_module_subpages($m, $abbr, $info);

        $h .= '<div class="sp-card ' . ($has ? 'has' : 'hasnt') . '">'
            . '<h3>' . htmlspecialchars($cfg['label']) . '</h3>'
            . '<div class="sp-cnt">'
            . ($has ? '&#10003; ' . htmlspecialchars(cnido_spcards_count_label($m, $n))
                    : '&mdash; not available')
            . '</div>'
            . '<p>' . htmlspecialchars($cfg['desc']) . '</p>'
            . '<div class="sp-links">';
        if ($has && $link !== '') {
            $h .= '<a href="' . htmlspecialchars($link) . '">Open '
                . htmlspecialchars($cfg['short']) . ' &rarr;</a>';
        } elseif ($link !== '') {
            $h .= '<a href="' . htmlspecialchars($link) . '" style="color:#64748b;font-weight:400">Open '
                . htmlspecialchars($cfg['short']) . ' anyway &rarr;</a>';
        }
        $h .= '</div>';

        if ($has && !empty($subs)) {
            $inner = '';
            foreach ($subs as $lbl => $u) {
                if ($self !== '' && cnido_spcards_is_self($u, $self)) { continue; }
                $inner .= ($inner === '' ? '' : ' &middot; ')
                    . '<a href="' . htmlspecialchars($u) . '">' . htmlspecialchars($lbl) . '</a>';
            }
            /* 整行子链接都被滤掉时不留空 div（只剩一条且它指向本页的情况）。 */
            if ($inner !== '') {
                $h .= '<div class="sp-sub-link">' . $inner . '</div>';
            }
        }
        $h .= '</div>';
    }
    $h .= '</div>';

    return $h;
}
