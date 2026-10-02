<?php
/* =====================================================================
 * BUSCO 完整度：取数与呈现
 *
 * 背景（审稿意见 Referee 2 major 7「核心直系同源基因集」）：
 * busco.php / busco_result.php 这两页原先只显示 busco 表的原始命中行，
 * 并把行数标成 "Total BUSCO Genes"。但那是一张**命中清单** —— 同一物种里
 * 一个 BUSCO 可能有多条命中（同源基因的多个转录本），行数与 BUSCO 数不是
 * 一回事。按行数算完整度会得出 297% 这种数字；按行数当「基因数」展示，
 * 读者也无从判断这个基因组的完整度到底如何。
 *
 * 真正的完整度在 busco_summary 表里（由 cnidosite-work/busco/import_summary.php
 * 按 (物种, BUSCO_ID) 归并生成，分母取 cnidaria_odb12 的 3203 个 BUSCO）。
 * 本文件把那张表读出来，供两个页面共用。
 *
 * 另一处必须说明的坑（**2026-09-21 已解决，见下**）：源数据里 6 个物种码被截断
 * 了末位数字 —— abbr 表里是 ASP1/ASP2/ASP3，busco 表里却是合并的 ASP。于是按
 * 物种名查 abbr1 再查 busco 会一行都查不到，页面显示 "Total BUSCO Genes: 0"。
 * 当年的权宜之计是在精确码查不到时回退到合并码，并在页面上标注「该行是多个物种
 * 合并统计」。但合并行本身错得很厉害：同一条 BUSCO_ID 会各收到来自两个真实物种
 * 的一条 Complete 命中，归并规则判为 Duplicated，于是这 6 行显示 D=65~87 %、
 * 全部 HQ=0，而各物种真实的 D 只有 ~1 %。
 * ===================================================================== */

/**
 * 合并码 => 它实际覆盖的物种。
 *
 * **现在恒为空数组。** 6 个合并码（AAURI/ASP/BCF/CGRAC/CSP/PSINE，共 12 个真实
 * 物种）已于 2026-09-21 逐物种重跑 BUSCO 拆开：命中行按各自的 abbr1 重新落库，
 * busco_summary 里 6 个合并行换成 12 个物种行（ambiguous=0），合并码已从库中
 * 消失。重跑前后做过对账 —— 各物种 Complete 集合的并集与合并行的 C 相差 ≤6 个
 * BUSCO（3203 中，即 ≤0.2 %），AAURI、PSINE 完全相等 —— 证明这次拆分是原数据的
 * 忠实分解，而不是换了一套数。
 *
 * 函数保留为空实现而不是删掉，是因为它有三个调用点（cnido_busco_summary_one 的
 * 回退、cnido_busco_species_list 的候选并集、cnido_busco_table 的成员展开）。
 * 返回空数组让这三处自然退化为无操作，不必改动它们的控制流；将来若又出现被截断
 * 的物种码，把映射填回这里就能恢复整套回退逻辑。
 *
 * 注：BUSCO 的 `Length` 列不是蛋白长度，靠磁盘上的 .pep 反查只能对上 1.7 % 的
 * 合并行（两个 Aurelia 都用 scaffold<NNN>.g<NN>.t<N> 命名，15,634 个 ID 重名），
 * 所以拆分只能靠重跑，回退映射本身无法胜任。
 */
function cnido_busco_ambiguous_map()
{
    return array();
}

/**
 * 已按「每基因一条代表序列」重跑 BUSCO 的物种。
 *
 * 这 9 个物种原先的完整度表现为 S = 0.0 %、D = 74~97 % —— 对真实基因组而言
 * 不可能的数值，成因是**被搜索的蛋白组里有冗余**，而不是组装质量差：
 *
 * 成因一（8 个物种，见 cnido_busco_dual_annotation_species）：蛋白组是同一个
 *   基因组的**两套注释拼在一起** —— 一套旧注释（基因 ID 形如 `g12345.t1`）
 *   加一套 BRAKER 注释。同一个 BUSCO 在两套里各命中一次，Score 与 Length
 *   完全相同，于是被判 Duplicated。实测 82~92 % 的 BUSCO 含这种命中对，
 *   正是「同一段序列被数了两遍」的指纹，不是真的发生了基因复制。
 *
 * 成因二（只有 AMEDI，见 cnido_busco_isoform_species）：蛋白组是**转录本级**
 *   的 —— 79,828 条蛋白只对应 50,248 个基因（1.58 条/基因），一个基因的多个
 *   isoform 全进了搜索，同一 BUSCO 因此被同一基因命中多次。
 *
 * 2026-09-18 已按蛋白序列去重、每个基因座只保留一条代表序列重跑全部 9 个物种，
 * 结果写回 busco_summary。表里的 S/D/F/M 现在与其余物种**直接可比**，
 * 不再需要「run not comparable」的标注；下面两个函数保留下来，是为了在物种
 * 详情页说明这段数据历史（审稿人问起时用得上），而不是继续排除这些物种。
 */
function cnido_busco_rerun_species()
{
    return array_merge(cnido_busco_dual_annotation_species(), cnido_busco_isoform_species());
}

/** 重跑日期，显示在说明文字里。 */
function cnido_busco_rerun_date()
{
    return '2026-09-18';
}

/**
 * 成因一：被搜索的蛋白组是两套注释（旧 `g*t1` + BRAKER）的拼接。
 *
 * 判断依据是 busco 表本身：一个 BUSCO 下同时出现 `g\d+.t` 与 `BRAKER` 两种
 * 基因 ID，且两者 Score 与 Length 完全相同。实测比例 82~92 %。
 */
function cnido_busco_dual_annotation_species()
{
    return array('AMYRI', 'BWELL', 'DCRIB', 'HOCTO', 'HVIRI', 'MCACT', 'NNOMU', 'TRUBR');
}

/**
 * 成因二：蛋白组是转录本级（一个基因多条 isoform 都进了搜索）。
 */
function cnido_busco_isoform_species()
{
    return array('AMEDI');
}

/**
 * 用 **genome 模式**（现场预测基因）而不是 -m proteins 跑的物种。
 *
 * Pachycerianthus multiplicatus：NCBI 对它的两个组装都没有发布任何基因注释，
 * 站内也没有 <ABBR>_locus 等基因级表，所以没有蛋白集可搜。2026-09-21 直接拿
 * 组装序列（GCA_984695265.1）跑了 BUSCO 6.0.0 genome 模式，基因预测器
 * miniprot，谱系仍是 cnidaria_odb12（3203 个 BUSCO，与全站同源）。
 *
 * 为什么必须单独标注、不能与其余物种并列比较：
 *   · 其余物种搜的是**已发布的蛋白集**，这里搜的是**评估过程中现场预测出来的
 *     基因**，两者不是同一把尺子 —— 预测器漏掉的基因会被记成 Missing，预测
 *     错误又可能被记成 Complete。
 *   · 本次 2972 个 Complete 中有 896 个（30.1 %）**含内部终止密码子**
 *     （short_summary 的 E:30.1 %）。这是 miniprot 预测的已知偏差，会把完整度
 *     往高里抬 —— C:92.8 % 因此是一个偏乐观的上界，不是一个可与
 *     -m proteins 结果互换的数字。
 *   · 该物种**不在**核心直系同源基因集里（genefamily 的资源是在 9 个有注释的
 *     高质量基因组上建的），所以它出现在高质量列表里也不参与那个资源。
 *
 * 因此这一行照常参与阈值判定（C ≥ 90 / D ≤ 10 / F ≤ 5 的机械判定结果就是达标），
 * 但在总表里打 flag、在详情页给出上面这段说明，读者一眼能看出它与别的行不可
 * 直接比较。
 */
function cnido_busco_genome_mode_species()
{
    return array('PMULT');
}

/** genome 模式那一次的运行信息，供说明文字引用。 */
function cnido_busco_genome_mode_note($abbr1)
{
    $notes = array(
        'PMULT' => array(
            'assembly' => 'GCA_984695265.1',
            'date'     => '2026-09-21',
            'tool'     => 'BUSCO 6.0.0, genome mode, miniprot',
            'estop'    => '30.1',
        ),
    );
    return isset($notes[$abbr1]) ? $notes[$abbr1] : null;
}

/**
 * 四个**已注释的黏体动物（Myxozoa）**，2026-09-23 补测 BUSCO。
 *
 * 背景：这 4 个是 speciesinfo 里 17 个 Myxozoa 中唯一带 `<ABBR>_locus` 表的物种，
 * 因此算进全站「145 个有基因模型的物种」，但 `busco` / `busco_summary` 里一行都
 * 没有 —— busco.php 的完整度总表因此只列 142 行，读者拿 145 去对就少 3 个。
 * 2026-09-23 用与其余物种**完全相同**的引擎、谱系与参数（BUSCO 6.0.0、
 * cnidaria_odb12 / 3203、-m proteins、输入按序列 MD5 去重后每基因取一条代表
 * 序列）补跑并落库，现在 145 个有基因模型的物种全部在表内。
 *
 * 为什么必须在结果页单独说明：这 4 行的 C 只有 3.6~7.6 %，是**生物本身的属性**，
 * 不是组装质量问题。黏体动物是后生动物里基因组退化最严重的一类，大量祖先基因
 * 真的丢了（Henneguya salminicola 连线粒体基因组都丢了），所以 3,203 个刺胞动物
 * BUSCO 里有一大半是**真的不存在**，而不是没组装出来。不说明的话，读者会把这
 * 几个基因组读成「组装得很差」，或反过来怀疑本站的评估口径。
 *
 * 旁证：speciesinfo.BUSCO 里原本就有一组来自其它来源的完整率（C=5.5~9.4 %），
 * 与本次重跑同量级且排序一致（MHONG > HSALM > TKITA > MSQUA），说明这是同一件
 * 事的两种测法，不是本站算错。它们远低于 90 % 阈值，不进核心直系同源基因集，
 * 也不改变 high-quality 计数（仍为 14）。
 *
 * 按用户对总表的要求，这 4 行在 cnido_busco_table() 里**不打任何旗标** ——
 * 说明只留在该物种自己的结果页上。
 */
function cnido_busco_myxozoa_species()
{
    return array('HSALM', 'MHONG', 'MSQUA', 'TKITA');
}

/** 补测那一次的运行信息，供说明文字引用。 */
function cnido_busco_myxozoa_note()
{
    return array(
        'date'   => '2026-09-23',
        'tool'   => 'BUSCO 6.0.0, proteins mode',
        'lineage'=> 'cnidaria_odb12',
        'low'    => '3.6',
        'high'   => '7.6',
    );
}

/**
 * 取一个物种的 BUSCO 完整度。
 *
 * **这里刻意不加 protein_set 过滤**，与 cnido_busco_summary_all() 不同：这张表要求
 * 同尺可比，而本函数服务的是单个物种的结果页 —— 访问者从 speciesinfo 点进
 * Pachycerianthus multiplicatus，就该看到它的完整度面板和那句 genome 模式说明，
 * 而不是一片空白。所以「表里不收」和「查不到」是两回事，不要为了「一致」给这里
 * 也加上过滤。
 *
 * @param  mysqli $conn
 * @param  string $abbr1      abbr 表里的物种码
 * @param  string $species    拉丁名；合并码回退用（见下），现在恒不触发
 * @return array|null         busco_summary 的一行；没有记录时 null
 */
function cnido_busco_summary_one($conn, $abbr1, $species = '')
{
    $sql = "SELECT abbr1, species, lineage, lineage_size, n_buscos, n_single, n_duplicated,
                   n_fragmented, n_missing, pct_complete, pct_single, pct_duplicated,
                   pct_fragmented, pct_missing, high_quality, ambiguous, protein_set
            FROM busco_summary WHERE abbr1 = ? LIMIT 1";
    if ($st = @$conn->prepare($sql)) {
        $st->bind_param('s', $abbr1);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if ($row) { return $row; }
    }
    /* 精确码查不到时的合并码回退。映射已清空（6 个截断码 2026-09-21 拆开重跑），
       这段循环因此不再命中；保留是为了将来若又出现截断的物种码，填回映射即可复活。 */
    foreach (cnido_busco_ambiguous_map() as $code => $members) {
        if ($species !== '' && in_array($species, $members, true)) {
            if ($st = @$conn->prepare($sql)) {
                $st->bind_param('s', $code);
                $st->execute();
                $row = $st->get_result()->fetch_assoc();
                $st->close();
                if ($row) { return $row; }
            }
        }
    }
    return null;
}

/**
 * 全站物种完整度总表的数据源，按完整度降序。
 *
 * **只返回基于已发布蛋白序列集评估、且有拉丁名的行。** 这两条 WHERE 就是这张表的
 * 定义，不是可选的过滤，所以写死在这里而不是让调用方传参：
 *
 *   · `species <> ''` —— 第一列必须是物种名。源文件当年把物种码截断到 5 个字符，
 *     合并行没有对应学名，那一格只能退化成显示缩写（AAURI / ASP / …）。合并码
 *     2026-09-21 已拆开重跑，库里没有这种行了；留着这条是防止再出现时第一列又变成
 *     缩写 —— 用户明确要求第一列只放物种名。
 *   · `protein_set = 1` —— 只放有蛋白序列注释的物种。基因组的完整度要在同一把尺子
 *     下比较，而 protein_set=0 的行（Pachycerianthus multiplicatus）是用评估时现场
 *     预测的基因打的分，是乐观上界，与其余行不可比；把它们排进同一张表就是在暗示
 *     可比。这类物种的数值仍在 speciesinfo 与它自己的结果页上，只是不进这张表。
 *
 * 排序：达到阈值的排前面（这是本表的用途 —— 找出可用于核心直系同源集的物种），
 * 组内再按完整度降序。若不这样排，D 很高但 C 也高的物种会顶在最前面，读者容易把
 * 「完整度高」误当成「质量合格」。
 */
function cnido_busco_summary_all($conn)
{
    $out = array();
    $q = @mysqli_query($conn, "SELECT abbr1, species, lineage, lineage_size, n_buscos, n_single,
                                      n_duplicated, n_fragmented, n_missing, pct_complete, pct_single,
                                      pct_duplicated, pct_fragmented, pct_missing, high_quality,
                                      ambiguous, protein_set
                               FROM busco_summary
                               WHERE protein_set = 1 AND species <> ''
                               ORDER BY high_quality DESC, pct_complete DESC, species ASC");
    while ($q && ($r = mysqli_fetch_assoc($q))) { $out[] = $r; }
    return $out;
}

/**
 * 总表的搜索：从 cnido_busco_summary_all() 的结果里挑出含该词的行。
 *
 * 匹配三处 —— 拉丁学名、物种短码（abbr1）、类群名。搜索框就摆在这张表的上方，
 * 而表里看得见的三列文字正好是它们：Species / Class / （短码只在结果页出现，
 * 但站内很多入口是按短码组织的，搜 NVECT 应该也能落到 Nematostella vectensis）。
 * 数值列（C/S/D/F/M）**不参与**：搜 "90" 会命中一大片，那不是「搜索」而是排序
 * 该做的事。
 *
 * 词为空时原样返回（不过滤）。大小写不敏感；mbstring 缺失时退回字节级比较 ——
 * 拉丁学名基本是 ASCII，两者差别只可能在非 ASCII 的搜索词上。
 *
 * @param array  $rows    cnido_busco_summary_all() 的结果
 * @param array  $classOf abbr1 => class（可为空数组）
 * @param string $q       搜索词，已由 cnido_search_term() 归一化
 * @return array          命中的行，保持原次序
 */
function cnido_busco_filter($rows, $classOf, $q)
{
    $q = trim((string)$q);
    if ($q === '') { return $rows; }
    $mb  = function_exists('mb_stripos');
    $out = array();
    foreach ($rows as $r) {
        $cls = isset($classOf[$r['abbr1']]) ? (string)$classOf[$r['abbr1']] : '';
        $hay = (isset($r['species']) ? $r['species'] : '') . ' '
             . (isset($r['abbr1'])   ? $r['abbr1']   : '') . ' ' . $cls;
        $hit = $mb ? (mb_stripos($hay, $q, 0, 'UTF-8') !== false) : (stripos($hay, $q) !== false);
        if ($hit) { $out[] = $r; }
    }
    return $out;
}

/**
 * busco.php 物种下拉框的候选名单：**凡是有 BUSCO 数据、且基于已发布蛋白序列集评估的
 * 物种**，从库里取。过滤条件与 cnido_busco_summary_all() 完全一致 —— 下拉框和总表
 * 是同一页的两半，能选到却不在表里（或反过来）都会让读者以为漏了数据。
 *
 * 原先这份名单是照抄旧 JS 的一份手写数组，数据一变名单就烂，而且同时烂在两个
 * 方向上：
 *   · Pachycerianthus multiplicatus、Desmophyllum pertusum 已经有完整数据却
 *     不在名单里 —— 既选不到，也在完整度总表的 Class 一列显示成「—」，因为
 *     那一列的类群是从这份名单取的映射；
 *   · 另有一批物种留在名单里，但一条 BUSCO 记录都没有，选中后结果页是空的。
 *
 * 因此名单改为从数据反推，取两个来源的并集：
 *   · busco_summary 里带拉丁名、protein_set=1 的行 —— 现在就是全部；
 *   · cnido_busco_ambiguous_map() 的成员 —— 该映射自 2026-09-21 起为空，
 *     这一项今天贡献不出物种，留着只为配合上面那处回退一起复活。
 *
 * @param  mysqli $conn
 * @return array        物种拉丁名 => class；键序 = 下拉框顺序
 *                      （先按 cnido_class_order() 的类群顺序，类内按学名排）
 */
function cnido_busco_species_list($conn)
{
    /* state.php 在 busco_result.php 里没有被 require，所以在这里按需引入，
       不能放到文件顶部 —— 那会给那个页面平白带上 state.php 的副作用。 */
    require_once __DIR__ . '/state.php';

    $names = array();
    $q = @mysqli_query($conn, "SELECT DISTINCT species FROM busco_summary
                                WHERE species <> '' AND protein_set = 1");
    while ($q && ($r = mysqli_fetch_row($q))) { $names[] = $r[0]; }
    foreach (cnido_busco_ambiguous_map() as $members) {
        foreach ($members as $n) { $names[] = $n; }
    }
    $names = array_values(array_unique($names));
    if (!$names) { return array(); }

    $classOf = cnido_species_class_map($names, $conn);

    /* 类群顺序表里没有的类群排在最后，与 cnido_classes_in() 的处理一致，不丢物种。
       （2026-09-27 之前这里的例子是 Ceriantharia —— 它当时被当作第八个 Class；现按
       WoRMS 归入 Hexacorallia / Ceriantharia，七个类群与顺序表完全一致，这个兜底
       分支目前没有数据走到，但机制保留：将来新增类群时不至于把物种丢掉。） */
    $rank = array_flip(cnido_class_order());
    $rows = array();
    foreach ($names as $n) {
        $c = isset($classOf[$n]) ? (string)$classOf[$n] : '';
        $rows[] = array('name' => $n, 'cls' => $c,
                        'rank' => isset($rank[$c]) ? $rank[$c] : count($rank));
    }
    usort($rows, function ($a, $b) {
        if ($a['rank'] !== $b['rank']) { return $a['rank'] - $b['rank']; }
        return strcasecmp($a['name'], $b['name']);
    });

    $out = array();
    foreach ($rows as $r) { $out[$r['name']] = $r['cls']; }
    return $out;
}

/** 一句话说明某个物种的 BUSCO 为什么要重跑，供表格里的标记 tooltip 使用。 */
function cnido_busco_rerun_reason($abbr1)
{
    if (in_array($abbr1, cnido_busco_dual_annotation_species(), true)) {
        return 'BUSCO was re-run on ' . cnido_busco_rerun_date() . '. The original run searched a protein set '
             . 'in which every protein was present twice (the same gene models are deposited under both a '
             . 'legacy g*t1 name and a BRAKER name), so each BUSCO was hit twice at identical score and '
             . 'length and was scored Duplicated. The figures shown are from the de-duplicated re-run.';
    }
    if (in_array($abbr1, cnido_busco_isoform_species(), true)) {
        return 'BUSCO was re-run on ' . cnido_busco_rerun_date() . '. The original run searched every '
             . 'transcript isoform instead of one representative sequence per gene, so a single gene could '
             . 'hit the same BUSCO more than once and inflate the duplicated fraction. The figures shown are '
             . 'from the one-sequence-per-gene re-run.';
    }
    return 'BUSCO was re-run on ' . cnido_busco_rerun_date() . ' on a de-duplicated protein set.';
}

/** 数字格式化，空值给破折号。 */
function cnido_busco_num($v, $dec = 1)
{
    if ($v === null || $v === '') { return '&mdash;'; }
    return number_format((float)$v, $dec);
}

/**
 * 完整度面板：S/D/F/M 四段堆叠条 + 计数表 + 高质量判定。
 *
 * @param array  $r      busco_summary 的一行
 * @param string $title  面板标题（物种名）
 */
function cnido_busco_panel($r, $title = '')
{
    if (!is_array($r)) { return ''; }
    $E = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
    $size = (int)$r['lineage_size'];
    if ($size <= 0) { return ''; }

    /* 四段条的宽度按占 lineage 的比例算。F 与 M 往往很小，给个最小宽度
       免得在页面上完全看不见，但计数与百分比一律照实显示。 */
    $seg = array(
        array('S', (float)$r['pct_single'],     '#2e8b57', 'Single-copy complete'),
        array('D', (float)$r['pct_duplicated'], '#c9a227', 'Duplicated'),
        array('F', (float)$r['pct_fragmented'], '#d97706', 'Fragmented'),
        array('M', (float)$r['pct_missing'],    '#94a3b8', 'Missing'),
    );
    $bar = '';
    foreach ($seg as $s) {
        $pct = $s[1];
        if ($pct <= 0) { continue; }
        $w = max($pct, 0.6);
        $bar .= '<span class="bs-seg" style="width:' . round($w, 3) . '%;background:' . $s[2] . '"'
              . ' title="' . $E($s[3] . ': ' . cnido_busco_num($pct) . '%') . '"></span>';
    }

    $hq    = (int)$r['high_quality'] === 1;
    $amb   = (int)$r['ambiguous'] === 1;
    $C     = (float)$r['pct_complete'];
    $D     = (float)$r['pct_duplicated'];
    $F     = (float)$r['pct_fragmented'];

    $h  = '<div class="bs-panel">';
    $h .= '<div class="bs-head">';
    $h .= '<span class="bs-title">Genome completeness (BUSCO)</span>';
    if ($title !== '') { $h .= ' <span class="bs-subject"><i>' . $E($title) . '</i></span>'; }
    $h .= '<span class="bs-badge ' . ($hq ? 'bs-badge-hq' : 'bs-badge-no') . '">'
        . ($hq ? '&#10003; High-quality genome' : 'Below quality threshold') . '</span>';
    $h .= '</div>';

    /* 一句话结论，格式照 BUSCO 官方 short_summary 的写法 */
    $h .= '<p class="bs-line">'
        . 'Lineage <b>' . $E($r['lineage']) . '</b> &middot; ' . number_format($size) . ' BUSCOs &middot; '
        . '<b>C: ' . cnido_busco_num($C) . '%</b> '
        . '[S: ' . cnido_busco_num($r['pct_single']) . '%, D: ' . cnido_busco_num($D) . '%] '
        . 'F: ' . cnido_busco_num($F) . '%, M: ' . cnido_busco_num($r['pct_missing']) . '%'
        . '</p>';

    $h .= '<div class="bs-bar">' . $bar . '</div>';
    $h .= '<div class="bs-legend">'
        . '<span><i style="background:#2e8b57"></i>Single-copy complete (S)</span>'
        . '<span><i style="background:#c9a227"></i>Duplicated (D)</span>'
        . '<span><i style="background:#d97706"></i>Fragmented (F)</span>'
        . '<span><i style="background:#94a3b8"></i>Missing (M)</span>'
        . '</div>';

    $h .= '<table class="gridtable bs-counts"><tr>'
        . '<th>Complete (C = S + D)</th><th>Single-copy (S)</th><th>Duplicated (D)</th>'
        . '<th>Fragmented (F)</th><th>Missing (M)</th></tr><tr>'
        . '<td>' . number_format((int)$r['n_single'] + (int)$r['n_duplicated'])
        . ' <span>(' . cnido_busco_num($C) . '%)</span></td>'
        . '<td>' . number_format((int)$r['n_single']) . ' <span>(' . cnido_busco_num($r['pct_single']) . '%)</span></td>'
        . '<td>' . number_format((int)$r['n_duplicated']) . ' <span>(' . cnido_busco_num($D) . '%)</span></td>'
        . '<td>' . number_format((int)$r['n_fragmented']) . ' <span>(' . cnido_busco_num($F) . '%)</span></td>'
        . '<td>' . number_format((int)$r['n_missing']) . ' <span>(' . cnido_busco_num($r['pct_missing']) . '%)</span></td>'
        . '</tr></table>';

    $h .= '<p class="bs-note">A genome is counted as <b>high-quality</b> when '
        . 'C &ge; 90&nbsp;%, D &le; 10&nbsp;% and F &le; 5&nbsp;%. '
        . 'Completeness is measured against the ' . number_format($size)
        . ' BUSCOs of the <b>' . $E($r['lineage']) . '</b> lineage, not against the number of '
        . 'records held for the species.</p>';

    if (!$amb && in_array($r['abbr1'], cnido_busco_rerun_species(), true)) {
        if (in_array($r['abbr1'], cnido_busco_dual_annotation_species(), true)) {
            $why = 'the protein set searched contained <b>every protein twice</b> &mdash; the same gene '
                 . 'models are deposited under two naming schemes at once, a legacy one (IDs of the form '
                 . '<code>g12345.t1</code>) and a BRAKER one. Each BUSCO was therefore matched once under '
                 . 'each name, at identical alignment score and length, and scored Duplicated, which is the '
                 . 'signature of one sequence being counted twice rather than of a real gene duplication.';
        } else {
            $why = 'the protein set searched contained <b>every transcript isoform</b> instead of one '
                 . 'representative sequence per gene (79,828 proteins over 50,248 genes), so a single gene '
                 . 'could hit the same BUSCO more than once and be scored Duplicated.';
        }
        $h .= '<p class="bs-info"><b>Re-run on ' . cnido_busco_rerun_date()
            . ' &mdash; these figures are comparable with the other species.</b> '
            . 'In the original run, ' . $why . ' BUSCO was re-run for this species on a de-duplicated set '
            . 'holding one sequence per gene locus, and the figures above come from that run. The original '
            . 'near-zero single-copy fraction was an artefact of the redundancy in the searched set, not a '
            . 'property of the assembly.</p>';
    }

    if (!$amb && in_array($r['abbr1'], cnido_busco_genome_mode_species(), true)) {
        $gm = cnido_busco_genome_mode_note($r['abbr1']);
        $h .= '<p class="bs-info"><b>Scored in genome mode &mdash; not directly comparable with the '
            . 'other species.</b> ';
        if ($gm !== null) {
            $h .= 'No gene annotation has been released for this species, so there was no protein set to '
                . 'search: BUSCO was run on the assembly (' . $E($gm['assembly']) . ') on ' . $E($gm['date'])
                . ' with ' . $E($gm['tool']) . ', i.e. the genes were <b>predicted during the '
                . 'assessment</b> rather than taken from a deposited annotation. ';
        } else {
            $h .= 'This figure comes from genes predicted during the assessment rather than from a '
                . 'deposited protein set. ';
        }
        $h .= 'The same lineage is used as everywhere else, so the arithmetic is the same, but the two '
            . 'are not interchangeable measurements: genes the predictor missed count as missing, and '
            . 'spurious predictions count as complete. ';
        if ($gm !== null) {
            $h .= 'In this run <b>' . $E($gm['estop']) . '%</b> of the complete matches contain internal '
                . 'stop codons, a known artefact of miniprot prediction that makes the completeness figure '
                . 'an <b>optimistic upper bound</b>. ';
        }
        $h .= 'This species is also not part of the core ortholog resource, which was built from the '
            . 'annotated genomes.</p>';
    }

    /* 2026-09-23 补测的四个黏体动物。与上面两段（重跑 / genome 模式）不同，这 4 行
       的数值是**可比**的 —— 引擎、谱系、参数、输入处理都与其余物种一致 —— 所以要
       解释的不是可比性，而是**为什么这么低**，免得被读成组装质量差。 */
    if (!$amb && in_array($r['abbr1'], cnido_busco_myxozoa_species(), true)) {
        $mx = cnido_busco_myxozoa_note();
        $h .= '<p class="bs-info"><b>Assessed on ' . $E($mx['date']) . ' &mdash; the low completeness '
            . 'reflects the organism, not the assembly.</b> '
            . 'This species was added to this module on ' . $E($mx['date']) . ' and scored on its '
            . 'deposited protein set with ' . $E($mx['tool']) . ' against <b>'
            . $E($mx['lineage']) . '</b> &mdash; the same lineage and the same proteins-mode protocol as '
            . 'the species re-scored here, so the figures above are directly comparable with them. '
            . 'They are, however, '
            . 'low (C = ' . $mx['low'] . '&ndash;' . $mx['high'] . '&nbsp;%): the Myxozoa are the most '
            . 'genome-reduced animals known, having lost much of the ancestral metazoan gene complement '
            . '(<i>Henneguya salminicola</i>, for one, has lost its mitochondrial genome entirely), so a '
            . 'large share of the 3,203 cnidarian BUSCOs is <b>genuinely absent</b> from these genomes '
            . 'rather than unassembled. These values should not be read as an assembly-quality failure. '
            . 'Being far below the 90&nbsp;% threshold they do not qualify for the core ortholog '
            . 'resource.</p>';
    }

    /* ambiguous=1 的行：库里现在一行都没有（6 个合并码已拆），但列还在，
       将来若有导入再把合并行写进来，这段说明会照旧打出来。 */
    if ($amb) {
        $members = cnido_busco_ambiguous_map();
        $list = isset($members[$r['abbr1']]) ? $members[$r['abbr1']] : array();
        $h .= '<p class="bs-warn"><b>Merged species code.</b> This row is filed under the code '
            . '<code>' . $E($r['abbr1']) . '</code>, which in the source BUSCO run covers '
            . count($list) . ' species: ' . $E(implode('; ', $list)) . '. '
            . 'The figures above therefore describe them <b>together</b>, not this species alone &mdash; '
            . 'which is why the duplicated fraction is inflated and no single species can be called '
            . 'high-quality from it.</p>';
    }
    $h .= '</div>';
    return $h;
}

/**
 * 全站物种完整度总表。
 *
 * @param array $rows   cnido_busco_summary_all() 的结果
 * @param array $classOf abbr1 => class，用于显示类群列（可为空数组）
 */
function cnido_busco_table($rows, $classOf = array())
{
    if (!$rows) { return ''; }
    $E = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
    $h  = '<div class="bs-tablewrap"><table class="gridtable bs-table">';
    $h .= '<thead><tr>'
        . '<th>Species</th><th>Class</th><th>Lineage</th>'
        . '<th title="Complete = single-copy + duplicated, as a share of the lineage BUSCOs">C (%)</th>'
        . '<th title="Single-copy complete">S (%)</th>'
        . '<th title="Duplicated">D (%)</th>'
        . '<th title="Fragmented">F (%)</th>'
        . '<th title="Missing">M (%)</th>'
        . '<th title="C &ge; 90 %, D &le; 10 %, F &le; 5 %">Quality</th>'
        . '<th>BUSCO details</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        $hq  = (int)$r['high_quality'] === 1;
        $cls = isset($classOf[$r['abbr1']]) ? $classOf[$r['abbr1']] : '';
        /* 第一列只放物种名 —— 一个词、不带任何徽标。这里曾经挂三种旗标
           （merged code / BUSCO re-run / genome mode），用户明确要求去掉：
           合并码已拆，genome 模式的物种根本不进这张表（见
           cnido_busco_summary_all() 的 WHERE），而「重跑过」是本次评估的来龙
           去脉、不是这一行的属性，说明留在该物种自己的结果页上。
           species 由上面的 WHERE 保证非空，所以不再有退化成显示缩写（AAURI）的
           情况；那正是用户不要的。 */
        $nm  = $r['species'];
        $linkName = $nm;
        $h .= '<tr>'
            . '<td style="text-align:left"><i>' . $E($nm) . '</i></td>'
            . '<td>' . ($cls !== '' ? $E($cls) : '&mdash;') . '</td>'
            . '<td>' . $E($r['lineage']) . '</td>'
            . '<td><b>' . cnido_busco_num($r['pct_complete']) . '</b></td>'
            . '<td>' . cnido_busco_num($r['pct_single']) . '</td>'
            . '<td>' . cnido_busco_num($r['pct_duplicated']) . '</td>'
            . '<td>' . cnido_busco_num($r['pct_fragmented']) . '</td>'
            . '<td>' . cnido_busco_num($r['pct_missing']) . '</td>'
            . '<td>' . ($hq ? '<span class="bs-badge bs-badge-hq">&#10003;</span>'
                            : '<span class="bs-badge bs-badge-no">&middot;</span>') . '</td>'
            /* 链接要落在 busco_result.php 认得的物种名上：万一遇到合并码行，码本身
               不是物种名，得用该组第一个成员，页面才回退得到数并打出说明。 */
            . '<td><a href="busco_result.php?species=' . urlencode($linkName) . '">view</a></td>'
            . '</tr>';
    }
    $h .= '</tbody></table></div>';
    return $h;
}

/** 两个页面共用的样式。 */
function cnido_busco_css()
{
    /* 这个「无数据」小圆点/「assessed」标签压在自己的 #f1f5f9 浅底上，原来的
   #64748b 只有 4.34，差一点点；#475569 是 6.92，且与表头文字同色。 */
    /* 表本身挂 gridtable：表头浅灰底 + 细下边框、行分隔线、斑马纹、悬停、字号全部由
   templatemo_style.css 的共用样式提供（与 core 的 table.cc 一致）。
   原来这里是另一套观感 —— 每格都描一圈 1px #e6ecf2 的方框、表头是偏蓝的
   #f4f8fc/#2a5298 —— 和站里其它表都不一样，统一后删掉。 */
    /* 第一列是物种拉丁学名，必须整行不折。templatemo_style.css 给 table.gridtable 定了
   table-layout:fixed + white-space:pre-wrap，十列平分宽度，学名会被折成两三行。
   下面两条要盖过它：选择器都得带 `table.` 前缀凑到 (0,1,1)，靠 `<style>` 块在
   templatemo_style.css 之后生效取胜（templatemo_style.css 是外链，在 <head> 更早）。
   table-layout:auto 让列宽按内容分配；第一列 nowrap 后其 min-content 就等于
   max-content，浏览器不能再压它，学名列于是固定成最长学名的宽度，其余各列分剩下的。
   窄屏下压不动就由 .bs-tablewrap 横向滚动，好过把学名折断。 */
    return <<<CSS
.bs-panel{background:#fff;border:1px solid #e6ecf2;border-radius:14px;padding:18px 20px;margin:14px 0 22px;
  box-shadow:0 2px 12px rgba(0,0,0,.04)}
.bs-head{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:6px}
.bs-title{font-weight:700;color:#1f3b57;font-size:18px}
.bs-subject{color:#16324f;font-size:20px;font-weight:600;line-height:1.3}
.bs-badge{display:inline-block;padding:2px 10px;border-radius:999px;font-size:15px;font-weight:600;
  white-space:nowrap}
.bs-badge-hq{background:#e7f6ec;color:#1c7a3e;border:1px solid #b7e2c6}
 
.bs-badge-no{background:#f1f5f9;color:#475569;border:1px solid #dde3ea}
.bs-line{margin:2px 0 10px;font-size:16px;color:#425466}
.bs-bar{display:flex;width:100%;height:16px;border-radius:8px;overflow:hidden;background:#f1f5f9}
.bs-seg{display:block;height:100%}
.bs-legend{display:flex;gap:16px;flex-wrap:wrap;font-size:15px;color:#5b6b7c;margin:8px 0 12px}
.bs-legend i{display:inline-block;width:10px;height:10px;border-radius:2px;margin-right:5px;vertical-align:middle}
 
.bs-counts td span{color:#64748b;font-size:15px}
.bs-note{font-size:15px;color:#64748b;margin:10px 0 0;line-height:1.7}
.bs-warn{font-size:15px;color:#92400e;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;
  padding:10px 14px;margin:12px 0 0;line-height:1.7}
.bs-warn code{background:#fff;border:1px solid #fde68a;border-radius:4px;padding:0 4px}
.bs-info{font-size:15px;color:#1e4e79;background:#f2f8fd;border:1px solid #cfe3f5;border-radius:10px;
  padding:10px 14px;margin:12px 0 0;line-height:1.7}
.bs-info code{background:#fff;border:1px solid #cfe3f5;border-radius:4px;padding:0 4px}
.bs-tablewrap{overflow-x:auto;margin:12px 0}
.bs-table{width:100%;min-width:820px;font-size:15px}
.bs-table th{white-space:nowrap}
.bs-table td{text-align:center;padding:6px 8px}
 
table.bs-table{table-layout:auto}
table.bs-table th:first-child,table.bs-table td:first-child{white-space:nowrap}
CSS;
}
