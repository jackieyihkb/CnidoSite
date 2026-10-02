<?php
/* =====================================================================
 * 表型数据的共用判定：词表归一、占位值识别、来源属性分级、来源署名
 *
 * 为什么要有这个 include：phenotype.php（列表页）、phenotype_species.php（单物种页）
 * 和后面的可视化都要回答同样几个问题 —— 「这个 trait 属于哪个类别」「这个值能不能当
 * 数据看」「这条是测出来的还是从高阶元继承的」「它出自哪一家」。各页各写一遍必然分叉
 * （本站已有先例：busco 的物种表曾各写一份），所以判定集中在这里，页面只负责排版。
 *
 * ===== 2026-09-27：数据重建（本文件的口径也跟着换了） =====
 *
 * 旧表 phenotype 的 value 列装的不是测量值，而是源库 OCTD 的 standard_id 编号 ——
 * 导入时把 `standard_id` 写进了 value、把 `standard_unit` 写进了 traitunit，真正的
 * `value` 列整列丢掉。证据是「一个单位下只有一个值」：cat 95,815 行全是 '10'
 * （字典里 10 = Category）、m 10,281 行全是 '9'、cm 7,771 行全是 '29'，
 * 而同一个单位在源 CSV 里的真实取值有 676 / 2,839 / 853 种。
 *
 * 已按用户选定的方式（执行方式 B）重建为一张新表，再整表切换：
 *   build_phenotype_v2.php   OCTD 那 148,492 行按源 CSV 整块重建（value ← value），
 *                            非 Octocorallia 的 2,554 行原样搬为 legacy 行，
 *                            内容完全相同的行折叠成一行并记 n_records。
 *   build_phenotype_refs.php 建 trait 字典与文献表，并把第三家 Pelagic 并进来。
 *   fetch_worms_traits.php   补齐 WoRMS Marine Species Traits（REST 逐个抓）。
 *   pheno_v2_verify.php        独立核对 OCTD/legacy：逐物种的元组集合与源 CSV 相等。
 *   pheno_worms_verify.php     独立核对 WoRMS：从缓存 JSON 重算行数与 n_records 之和。
 * 重建后（2026-09-27 实测）：180,652 行 / 217,672 条源记录 / 3,949 个物种 / 292 个
 * trait 名 / 16 个非空类别 / 四家来源。来源属性按 n_records 加权：measured 37.5%、
 * inherited 49.2%、expert 8.9%、derived 2.6%、unknown 1.8%。inherited 这一档里
 * 58.7% 来自 WoRMS —— 那家的性状多记在属/科/纲一级、再逐级继承给物种，它自己
 * 的条目里 95.8% 都带继承标记（按继承自哪个阶元核过：29,631 条来自 Octocorallia
 * 这个纲，其余是 Animalia、科、目，都不是物种自己）。
 *
 * trait_category 这一列有两处是**本站的判断**，不是源库自带的，页面上说过：
 *   · WoRMS 侧根本没有类别字段（它的 CategoryID 与 measurementType 一对一），
 *     48,607 行原先全是空串，在覆盖矩阵里挤成一个 "(no category)" 列、物种页
 *     也没有归处。2026-09-27 按 src/worms_trait_category.php 的对照表归到本站
 *     已有的类别上（每一条都对着 OCTD 的先例挑的：骨骼与矿化 → Biomechanical，
 *     生活史/命名法规/取值统计性质 → Contextual，IUCN/CITES/OSPAR → Conservation…）。
 *     装载器写库时就归好，不是事后补的 —— 重灌一遍得到同一张表（已按 11 列内容
 *     逐行比对，对称差 0）。
 *   · 拼写统一：Pelagic 的小写 'morphological'、OCTD 源文件里的错拼
 *     'Stoichometric' 都归一成多数写法（见 cnido_pheno_category_normalize()）。
 *     MySQL 的排序规则本来就会把大小写变体合成一列，但**列名取谁是不确定的**。
 *
 * 新增的列（页面依赖它们，别再按旧的十列写查询）：
 *   value_type          源库自己给的取值性质（raw_value / expert_opinion / mid_range /
 *                       mean / median / maximum / minimum / model_derived）—— 比拿
 *                       methodology 猜准得多，见 cnido_pheno_provenance()
 *   context             Pelagic 的生活史等限定词（adult / juvenile / larva），
 *                       OCTD 一律空
 *   source              'OCTD v2.2' / 'WoRMS (Marine Species Traits)' /
 *                       'Pelagic v3.1' / 'legacy (unattributed)'
 *   source_record_id    源库自己的观测号（可回溯）
 *   source_resource_id  指向 phenotype_source_resource 的文献号（署名要用的）
 *   n_records           折叠前的行数（内容完全相同的源记录有几条）
 *   id                  自增主键 —— 分页排序的最终并列键（下面 cnido_pheno_rows_by_trait
 *                       与 phenotype.php 的 ORDER BY 都靠它构成全序）
 *
 * 仍然保留的两个旧判定，它们现在是**回归护栏**而不是主判据：
 *   cnido_pheno_is_coded() / cnido_pheno_coded_sql()  字典判「值是不是源库编号」。
 *   新表里只剩 16 行命中，全是 Veron 珊瑚名录那批（`IUCN Red List category 10`、
 *   `Veron species id 41`），出自重建前的导入；页面只在真有命中时才提。留着是因为
 *   **下一版数据如果又把编号导进来，它会自己说出来**，而不是静默当真值显示。
 *   判定**必须带来源**（cnido_pheno_source_has_codes）：重建后 OCTD/WoRMS 的真读数
 *   （`Water depth 9 m`、`Colony height 29 cm`、`Polyp fecundity 6 units`）与字典里的
 *   编号 (m,9)/(cm,29)/(units,6) 撞键，只看 (单位,值) 会把 78 行真读数错判成编号。
 *   字典里有 108 个编号，不能删。
 *
 * 已知的粗糙处，下一版要解决：methodology 一列混着「怎么得到的」（measured /
 * derived）和「数据出自哪里」（Extracted from OBIS_Accessed in November_2023）。
 * 前者现在有 value_type 兜着，后者本该是独立的来源列 —— 还没做。
 * ===================================================================== */

/**
 * trait_category 的词表归一。
 *
 * 源库里同时存在 `Stoichiometric`（555 行）和 `Stoichometric`（11 行，少一个 i）
 * —— 那是**源数据自己的**拼写错误，重建时原样带过来了，所以这里还得归一，
 * 否则图表上会多出一根只有 11 行的柱子。归一表只列已确认的同义项，不做模糊匹配：
 * 拼错的类别名以后可能再出现一个，靠编辑距离去猜会把真的类别合并掉。
 *
 * 2026-09-27 起库里存的已经是规范过的值（装载器与重建脚本都调同一个
 * cnido_pheno_category_normalize()），所以这个函数现在是**回归护栏**：
 * 下一版数据如果又把错拼带进来，页面照样不会多出一根柱子。
 */
function cnido_pheno_category($cat)
{
    static $alias = array(
        'stoichometric' => 'Stoichiometric',   // 少一个 i 的拼写
    );
    $key = strtolower(trim((string)$cat));
    if (isset($alias[$key])) { return $alias[$key]; }
    return trim((string)$cat);
}

/**
 * traitunit 的词表归一（**只用于显示**）。
 *
 * 注意两件事：
 *   1. 判「值是不是编号」必须用**原始**单位（traitunit 列的原样值），不能拿这里的
 *      返回值去查字典 —— 见 cnido_pheno_is_coded()。
 *   2. `cat` / `bin` 不是单位，是源库的数据类型标记（类别名 / 二值），在「Unit」列里
 *      印出来只会让人以为有个叫 cat 的单位，所以归一成空。真单位一个都不动：
 *      µm(U+00B5) 与 μm(U+03BC) 是两个字符串，`cm yr^-1`、`°C` 原样保留。
 */
function cnido_pheno_unit($unit)
{
    static $alias = array(
        'years'    => 'year',
        'months'   => 'month',
        'days'     => 'day',
        'unitless' => '',
        'units'    => '',
        'cat'      => '',      // 数据类型标记，不是单位
        'bin'      => '',
        '-'        => '',
    );
    $u = trim((string)$unit);
    $key = strtolower($u);
    return isset($alias[$key]) ? $alias[$key] : $u;
}

/** 这个值能不能当数字用（画分布图、算区间都要）。是则返回 float，否则 null。 */
function cnido_pheno_numeric($value)
{
    $v = trim((string)$value);
    /* 只认**整串**是数字的：'10' / '-3.5' / '.5' 算，'10-20'（区间）、'12 cm'、
       'mostly' 不算 —— 那些是别的形态，硬转成数字会得出假的分布。 */
    if ($v === '' || !preg_match('/^[+-]?(\d+(\.\d*)?|\.\d+)([eE][+-]?\d+)?$/', $v)) { return null; }
    return (float)$v;
}

/* ---------------------------------------------------------------------
 * value 列里的编号：判据来自 OCTD 自己的 standard_id 字典
 *
 * ★ 2026-09-27 重建后这套判定的适用范围**只剩重建前导入的那批行**（source 为空或
 *   legacy）：那时 value 列装的是 OCTD 的 standard_id，148,492 行里 148,510 行命中
 *   字典，真正的读数整列没进来。重建后每家源库都从自己的 value 列取读数，于是
 *   字典命中就变成了**巧合**：`Water depth 9 m`、`Colony height 29 cm`、`Polyp
 *   fecundity 6 units` —— 这些是读数，(m,9)/(cm,29)/(units,6) 只是恰好也是字典里的
 *   编号（字典名 Length/Length/Count）。全表 94 行命中里 78 行是这种巧合，剩下 16 行
 *   是 Veron 珊瑚名录那批（`IUCN Red List category 10`、`Veron species id 41`），
 *   那才是真编号。
 *
 *   所以判定必须【按来源分开】：拿 (unit,value) 命中字典去指控一条 OCTD 的行「值
 *   不是读数」是错的 —— 会把真读数标成「not a value」并排除出图表。
 *
 *   字典仍在 includes/phenotype_standard_ids.php，由
 *   /var/www/cnidosite-tools/src/build_pheno_standard_ids.php 从 Zenodo 导出包生成。
 * ------------------------------------------------------------------- */

/** OCTD 的 standard_id 字典（unit => id => name），只读一次。 */
function cnido_pheno_standard_ids()
{
    static $d = null;
    if ($d === null) { $d = require __DIR__ . '/phenotype_standard_ids.php'; }
    return $d;
}

/**
 * 这个来源的 value 列里可能装着编号（而不是读数）吗。
 *
 * 只有重建前导入、来源未署名的那些行可能是编号 —— 那时 value 取的是 OCTD 的
 * standard_id 列。重建后（OCTD v2.2 / WoRMS / Pelagic v3.1）每一行都是读数。
 * 空 source 按「可能是编号」处理：来源不明时保守地保留检查，宁可标出来。
 */
function cnido_pheno_source_has_codes($source)
{
    $s = trim((string)$source);
    return ($s === '' || strcasecmp($s, 'legacy (unattributed)') === 0);
}

/**
 * 「这一行的 value 是源库的编号，不是测量值」。
 *
 * 判据 = 该来源仍可能是编号（cnido_pheno_source_has_codes）且 (traitunit, value)
 * 逐字出现在 OCTD 的字典里。判据必须**同时看单位和值**：单位 mm 下 20 是真实测量值，
 * 28 才是编号。
 *
 * 单位只做 trim，**不做大小写归一**：字典里 `Larvae polyp^-1` 与 `larvae ...` 是不同
 * 的字符串，µm(U+00B5) 与 μm(U+03BC) 也是两个字符。库里确实存在带首尾空格的单位
 * （` µgC polyp^-1 d^-1` 等），所以 trim 是必需的。
 *
 * $source 传 null 表示「来源未知」—— 与传空串同义，保留字典检查。
 */
function cnido_pheno_is_coded($value, $unit, $source = null)
{
    if ($source !== null && !cnido_pheno_source_has_codes($source)) { return false; }
    $d = cnido_pheno_standard_ids();
    $u = trim((string)$unit);
    return isset($d[$u]) && isset($d[$u][trim((string)$value)]);
}

/** 编号对应的标准名（`10` → `Category`）；不是编号或字典无名时返回 ''。 */
function cnido_pheno_coded_name($value, $unit, $source = null)
{
    if ($source !== null && !cnido_pheno_source_has_codes($source)) { return ''; }
    $d = cnido_pheno_standard_ids();
    $u = trim((string)$unit);
    $v = trim((string)$value);
    return (isset($d[$u]) && isset($d[$u][$v]) && $d[$u][$v] !== '') ? (string)$d[$u][$v] : '';
}

/**
 * 与 cnido_pheno_is_coded() 等价的 SQL 谓词，供整表统计用。
 *
 * 形状是「先限定来源，再把 (单位, 值) 拼成一个键，再 IN 一张常量表」：
 *   TRIM(COALESCE(source,'')) IN ('', 'legacy (unattributed)')
 *     AND CONCAT(TRIM(COALESCE(traitunit,'')), CHAR(31), TRIM(COALESCE(value,'')))
 *         IN (CONCAT('cat',CHAR(31),'10'), CONCAT('m',CHAR(31),'9'), …)
 * 分隔符用 0x1F（US，控制字符）——单位与编号里都不可能出现它，拼键不会歧义。
 * 一条「一个单位一条 OR 子句」的写法实测 0.47 s，这种写法 0.12 s，答案逐位相同。
 *
 * 来源那一段不能省（见上面那段注释）：重建后 OCTD/WoRMS 的真实读数会与字典里的
 * 编号撞键，只用 (单位,值) 判会把真读数标成「不是值」。
 *
 * $sourceExpr 是来源列名/表达式，默认 'source'。表别名不同就得传对应的限定名。
 * 改了字典（OCTD 出新版）这里不用动：谓词是从同一份字典现拼的；改了来源名则要
 * 同步 cnido_pheno_source_has_codes()。
 */
function cnido_pheno_coded_sql($sourceExpr = 'source')
{
    /* $sourceExpr 必须是列名（默认 'source'）。历史上调用方写成 cnido_pheno_coded_sql($conn)，
       传进来的连接对象在这里会被当成列名拼进 SQL —— 所以非字符串一律按默认列处理。 */
    if (!is_string($sourceExpr) || trim($sourceExpr) === '') { $sourceExpr = 'source'; }
    static $sql = array();
    if (isset($sql[$sourceExpr])) { return $sql[$sourceExpr]; }
    $d = cnido_pheno_standard_ids();
    $q = function ($s) { return "'" . str_replace("'", "''", (string)$s) . "'"; };
    $keys = array();
    foreach ($d as $unit => $ids) {
        foreach (array_keys($ids) as $id) {
            $keys[] = "CONCAT(" . $q($unit) . ",CHAR(31)," . $q($id) . ")";
        }
    }
    $sql[$sourceExpr] = "(TRIM(COALESCE($sourceExpr,'')) IN ('', 'legacy (unattributed)')
         AND CONCAT(TRIM(COALESCE(traitunit,'')),CHAR(31),TRIM(COALESCE(value,''))) IN ("
         . implode(',', $keys) . "))";
    return $sql[$sourceExpr];
}

/**
 * 「这个值不是数据」= 空值，或者是源库的编号。
 *
 * 返回 true 的行仍然会显示（隐藏数据比标出来更糟），但会被排除在统计和图表之外，
 * 并在页面上带一个可见的标记。
 */
function cnido_pheno_is_placeholder($value, $unit, $source = null)
{
    if (trim((string)$value) === '') { return true; }
    return cnido_pheno_is_coded($value, $unit, $source);
}

/* ---------------------------------------------------------------------
 * 来源属性：这条记录是怎么来的
 * ------------------------------------------------------------------- */

/**
 * 源库自己的 value_type 词表 → 本站的四个属性之一 + 一个「专家判断」。
 *
 * 为什么要以 value_type 为准：它来自 OCTD 自己的列，是**作者声明的**；而之前只能
 * 拿 methodology 那段自由文本去猜正则。新表的分布（2026-09-27 实测，含 WoRMS）：
 *   raw_value 101,736 / checked 46,680 / expert_opinion 19,595 / (空) 3,596 /
 *   mid_range 2,968 / maximum 2,735 / unreviewed 1,922 / mean 1,225 /
 *   model_derived 93 / minimum 84 / median 13 / trusted 5
 *   —— checked / unreviewed / trusted 是 WoRMS 的 qualitystatus（审核状态），
 *   空的是 Pelagic（那家没有这一列）。
 *
 * 归类理由：
 *   raw_value                      直接读数 → measured
 *   mean / median / maximum / minimum
 *                                  都是**对该物种自己的观测**做的统计（厘米的最大
 *                                  体长、°C 的耐受上限），仍然属于这个物种，
 *                                  只是汇总过。具体是哪个统计量页面上另有一列照印，
 *                                  不在这里替用户抹平。
 *   mid_range                      区间的中点 —— 不是观测到的值，是算出来的 → derived
 *   model_derived                  模型输出 → derived
 *   expert_opinion                 专家凭经验判断，既不是测量也不是从别的数据算出来的，
 *                                  所以单列一类 expert，混进 measured 会抬高「实测」
 *                                  的分量（19,595 行，占 15%）。
 */
function cnido_pheno_value_type_prov($value_type)
{
    static $map = array(
        'raw_value'     => 'measured',
        'mean'          => 'measured',
        'median'        => 'measured',
        'maximum'       => 'measured',
        'minimum'       => 'measured',
        'mid_range'     => 'derived',
        'model_derived' => 'derived',
        'expert_opinion'=> 'expert',
    );
    $k = strtolower(trim((string)$value_type));
    return isset($map[$k]) ? $map[$k] : '';
}

/** value_type 的显示名（照印源库的说法，不翻译）。 */
function cnido_pheno_value_type_label($value_type)
{
    $v = trim((string)$value_type);
    return $v === '' ? '' : str_replace('_', ' ', $v);
}

/**
 * 一条记录是怎么来的 —— measured / derived / inherited / expert / unknown。
 *
 * 判定次序（先匹配先赢）：
 *   1. 源库的 value_type（只有 OCTD 有这一列）—— 见 cnido_pheno_value_type_prov()。
 *      但 value_type 说「值是怎么算的」，说不说「值属于哪个阶元」：一行可以是
 *      raw_value **且** methodology 写着 inherited。所以继承要**先**判，
 *      否则 22,320 行属级继承会被 value_type 判成实测。
 *   2. methodology 里的继承：「inherited from genus / family / Class level」。
 *      WoRMS 的行由 fetch_worms_traits.php 明确写成 'inherited from a higher taxon'
 *      （它的 AphiaID_Inherited 不等于本条目的 AphiaID 时才写）。
 *   3. value_type 的结论。
 *   4. methodology 的推导关键词（Derived / Inferred / Estimated / calculated /
 *      converted / coded / 各种 model、equation、midpoint …）。
 *   5. 其余 → measured；methodology 为空或 '-' → unknown（不猜）。
 *
 * @param string $methodology 库里那一列的原样值
 * @param string $value_type  源库的取值性质；老数据/legacy 行是空串
 */
function cnido_pheno_provenance($methodology, $value_type = '')
{
    $m = trim((string)$methodology);

    // 1. 继承优先：指名了阶元的，不管值是怎么算出来的，它都不属于这个物种
    if (preg_match('/inherited/i', $m)) { return 'inherited'; }
    /* 「Inferred from …」要分两种，不能一律算继承：指名了阶元的（Inferred from genus-level
       information …）是继承，而 Inferred from volume displacement 是从测量值推算出来的，
       属于 derived。裸写一个 "inferred" 的没说是哪个阶元，也不替它猜，归 derived。 */
    if (preg_match('/[Ii]nferred from\s+(genus|family|class|order|phylum|higher|parent)/i', $m)) { return 'inherited'; }

    // 2. 源库自己声明的取值性质
    $vp = cnido_pheno_value_type_prov($value_type);
    if ($vp !== '') { return $vp; }

    // 3. 来源未记录
    if ($m === '' || $m === '-') { return 'unknown'; }

    // 4. 推导、估算、换算、由别的 trait 推出
    if (preg_match('/Derived from|derived from|derived using|^Trait value derived/i', $m)) { return 'derived'; }
    if (preg_match('/\bInferred\b|\binferred\b/', $m)) { return 'derived'; }
    if (preg_match('/^Estimated|estimated from|^Estimated from/i', $m)) { return 'derived'; }
    if (preg_match('/calculated|Calculated|computed/i', $m)) { return 'derived'; }
    if (preg_match('/converted from|Trait value coded|coded based on|coded using/i', $m)) { return 'derived'; }
    if (preg_match('/midpoint of range|related trait|growth function|Gompertz|Richards|logistic model/i', $m)) { return 'derived'; }
    if (preg_match('/matrix model|Q10 equation|equation|Extrapolat|extrapolat/i', $m)) { return 'derived'; }

    return 'measured';
}

/**
 * provenance 的显示名与配色。
 * 绿=实测 / 紫=专家判断 / 琥珀=推导 / 蓝=继承 / 灰=未记录。
 * 五个都必须在表里 —— 页面按这几个键取色，缺一个就会退化成灰色。
 */
function cnido_pheno_prov_meta($prov)
{
    static $meta = array(
        'measured'  => array('label' => 'measured',         'bg' => '#dcfce7', 'fg' => '#15803d',
                             'hint'  => 'recorded for the species named on the row'),
        'expert'    => array('label' => 'expert opinion',   'bg' => '#f5f3ff', 'fg' => '#6d28d9',
                             'hint'  => 'assessed by an expert rather than measured or computed'),
        'derived'   => array('label' => 'derived',          'bg' => '#fffbeb', 'fg' => '#92400e',
                             'hint'  => 'computed, converted or inferred from other data'),
        'inherited' => array('label' => 'inherited',        'bg' => '#eff6ff', 'fg' => '#1d4ed8',
                             'hint'  => 'taken from a higher taxon (genus, family or class), not observed for this species'),
        'unknown'   => array('label' => 'not recorded',     'bg' => '#f1f5f9', 'fg' => '#64748b',
                             'hint'  => 'the source does not record how this value was obtained'),
    );
    return isset($meta[$prov]) ? $meta[$prov] : $meta['unknown'];
}

/**
 * 三个源库的署名与配色。
 *
 * 三家都是 CC BY（OCTD 与 Pelagic 是论文附件，WoRMS 是 CC BY 4.0），页面必须署名
 * 并回链。**WoRMS 尤其不能少**：它的条款不允许把整库再分发，本站只取了刺胞动物
 * 这一小部分、逐条记了 source_id，署名回链是取用的条件。
 *
 * 认不出的 source 值（将来加了第四家而没登记）走中性灰，并把原样字符串当短标签印
 * —— 宁可印一个陌生名字，也不要静默显示成别的来源。
 */
function cnido_pheno_source_meta($source)
{
    static $meta = array(
        'OCTD v2.2' => array(
            'short' => 'OCTD', 'label' => 'The Octocoral Trait Database v2.2',
            'url'   => 'https://www.nature.com/articles/s41597-024-04307-8',
            'bg'    => '#ecfdf5', 'fg' => '#047857'),
        'WoRMS (Marine Species Traits)' => array(
            'short' => 'WoRMS', 'label' => 'WoRMS / Marine Species Traits',
            'url'   => 'https://www.marinespecies.org/',
            'bg'    => '#eff6ff', 'fg' => '#1d4ed8'),
        'Pelagic v3.1' => array(
            'short' => 'Pelagic', 'label' => 'The Pelagic Species Trait Database v3.1',
            'url'   => 'https://pmc.ncbi.nlm.nih.gov/articles/PMC10786825/',
            'bg'    => '#fef3c7', 'fg' => '#92400e'),
        'legacy (unattributed)' => array(
            'short' => 'unattributed', 'label' => 'source not identified (imported before 2026-09-27)',
            'url'   => '',
            'bg'    => '#f1f5f9', 'fg' => '#64748b'),
    );
    $s = trim((string)$source);
    if (isset($meta[$s])) { return $meta[$s]; }
    return array('short' => ($s === '' ? 'unknown' : $s), 'label' => ($s === '' ? 'source not recorded' : $s),
                 'url' => '', 'bg' => '#f1f5f9', 'fg' => '#64748b');
}

/**
 * 把一个 trait 的值列判定为「平的」—— 即整列几乎没有变化。
 *
 * 用于图表：一个只取两个值、却铺了 8,452 行的 trait，画成分布图是骗人的。
 * 判据是出现次数最多的值占该 trait 的比例。
 *
 * @param array $values 该 trait 的全部值（字符串数组，已去掉占位值）
 * @param float $cut    阈值，默认 0.95
 */
function cnido_pheno_is_flat(array $values, $cut = 0.95)
{
    $n = count($values);
    if ($n < 2) { return true; }
    $c = array_count_values($values);
    $max = max($c);
    return ($max / $n) >= $cut;
}

/**
 * 取一个物种的全部表型记录。
 *
 * 行的折叠已经在库里做完了（重建时把内容完全相同的源记录折成一行并记 n_records），
 * 所以这里**不再 GROUP BY + COUNT(\*)** —— 那会把「同一行来自不同源库」也合并掉
 * （同一个物种同一个值，OCTD 记一条、WoRMS 也记一条，是两条独立的记录，来源不同、
 * 该各自署名）。重复次数直接读 n_records。
 *
 * 排序用 ORDER BY … , id：前 12 列的比较规则是 utf8mb4_0900_ai_ci（大小写不敏感），
 * 单靠它们排不出全序，而物种页是按 trait 分组渲染的 —— 次序不稳会让同一行在两次
 * 请求里落在不同的分组里。id 是自增主键，补上就是严格全序。
 *
 * @param mysqli $conn
 * @param string $latin 拉丁名（必须已经和 phenotype.species 的值对得上）
 * @return array 分组后的结构：array(category => array(trait => array(rows, ...)))
 *               每行是 array('value','unit','unit_raw','value_type','vt_label','context',
 *                            'source','src','prov','placeholder','n','resource','rec_id',
 *                            'region','coords','method')
 */
function cnido_pheno_rows_by_trait($conn, $latin)
{
    $out = array();
    $e = mysqli_real_escape_string($conn, $latin);
    $q = mysqli_query($conn,
        "SELECT trait_category, trait_name, value, traitunit, region, latitude, longitude,
                methodology, value_type, context, source, source_resource_id, source_record_id,
                n_records
           FROM phenotype
          WHERE species = '$e'
          ORDER BY trait_category, trait_name, value, traitunit, region, latitude, longitude,
                   methodology, value_type, context, source, id");
    if (!$q) { return $out; }

    while ($r = mysqli_fetch_assoc($q)) {
        $cat = cnido_pheno_category($r['trait_category']);
        $tr  = trim((string)$r['trait_name']);
        $vt  = trim((string)$r['value_type']);
        $src = trim((string)$r['source']);
        $prov = cnido_pheno_provenance($r['methodology'], $vt);
        $ph  = cnido_pheno_is_placeholder($r['value'], $r['traitunit'], $src);

        $lat = trim((string)$r['latitude']);
        $lon = trim((string)$r['longitude']);
        $coords = ($lat !== '' && $lon !== '' && $lat !== '-' && $lon !== '-')
                ? ($lat . ', ' . $lon) : '';

        if (!isset($out[$cat]))            { $out[$cat] = array(); }
        if (!isset($out[$cat][$tr]))       { $out[$cat][$tr] = array(); }
        $out[$cat][$tr][] = array(
            'value'       => (string)$r['value'],
            'unit'        => cnido_pheno_unit($r['traitunit']),
            'unit_raw'    => (string)$r['traitunit'],
            'value_type'  => $vt,
            'vt_label'    => cnido_pheno_value_type_label($vt),
            'context'     => trim((string)$r['context']),
            'source'      => $src,
            'src'         => cnido_pheno_source_meta($src),
            'resource'    => trim((string)$r['source_resource_id']),
            'rec_id'      => trim((string)$r['source_record_id']),
            'region'      => trim((string)$r['region']),
            'coords'      => $coords,
            'method'      => trim((string)$r['methodology']),
            'prov'        => $prov,
            'placeholder' => $ph,
            'n'           => max(1, (int)$r['n_records']),
        );
    }
    return $out;
}

/**
 * 一个物种的表型小结：折叠后多少行、折叠前多少条源记录、能用多少、覆盖了多少
 * trait / 类别、按来源属性与来源库分开的计数。
 *
 * 「可用」= value 不是编号（也不是空）。重建之后全表只剩 16 行是编号（都是 Veron
 * 珊瑚名录那批：`IUCN Red List category 10`、`Veron species id 41`），但**不能因此
 * 删掉这几个数** —— 它们是下一版数据的护栏，而且判定按来源分开（见
 * cnido_pheno_source_has_codes），重建后的来源里 (单位,值) 撞上字典只是巧合。
 *
 * rows 与 raw 必须分开报：Keratoisis grayi 折叠后 2,097 行、折叠前 14,069 条源记录。
 * 只报一个数都会误导 —— 只报 rows 显得源库只有 2 千条，只报 raw 会把重复的同一行
 * 数成不同的记录。
 *
 * 注意 regions / coords 两个集合是**按全部行**收集的，不跟着「可用」走：值可能有
 * 问题，地理没有。别把它们理解成「可用数据覆盖的地域」。
 *
 * @param array $grouped cnido_pheno_rows_by_trait() 的返回值
 * @return array
 */
function cnido_pheno_summary(array $grouped)
{
    $s = array(
        'rows' => 0, 'raw' => 0, 'usable' => 0, 'placeholder' => 0, 'coded' => 0,
        'traits' => 0, 'traits_usable' => 0, 'categories' => 0,
        'measured' => 0, 'derived' => 0, 'inherited' => 0, 'expert' => 0, 'unknown' => 0,
        'regions' => array(), 'coords' => array(), 'sources' => array(),
    );
    foreach ($grouped as $cat => $traits) {
        if ($traits) { $s['categories']++; }
        foreach ($traits as $tr => $rows) {
            $s['traits']++;
            $any = false;
            foreach ($rows as $r) {
                $s['rows']  += 1;
                $s['raw']   += $r['n'];
                if ($r['source'] !== '') {
                    $k = $r['source'];
                    if (!isset($s['sources'][$k])) { $s['sources'][$k] = 0; }
                    $s['sources'][$k]++;
                }
                if ($r['region'] !== '') { $s['regions'][$r['region']] = true; }
                if ($r['coords'] !== '') { $s['coords'][$r['coords']] = true; }
                if ($r['placeholder']) {
                    $s['placeholder']++;
                    /* 空值不算「编号」，分开数：页面上说的是两件不同的事。 */
                    if (trim($r['value']) !== '') { $s['coded']++; }
                    continue;
                }
                $any = true;
                $s['usable'] += 1;
                if (isset($s[$r['prov']])) { $s[$r['prov']]++; }
            }
            if ($any) { $s['traits_usable']++; }
        }
    }
    return $s;
}

/* ---------------------------------------------------------------------
 * 取数时的物种名归位
 * ------------------------------------------------------------------- */

/**
 * 把一个请求里的物种 token 归位到 `phenotype.species` 里真正存在的那个写法。
 *
 * phenotype.species 存的是拉丁名（如 `Nematostella vectensis`），而站内深链可能给
 * abbr1 短码（CCRUB）、下划线形式（Corallium_rubrum）或带空格的拉丁名。先用 abbr
 * 表折算，再回表里确认 —— 折算出来的名字在 phenotype 里不一定有记录。
 *
 * @return string 命中返回 phenotype.species 里的原样写法；否则返回 ''。
 */
function cnido_pheno_species_of($conn, $token)
{
    $token = trim((string)$token);
    if ($token === '' || strcasecmp($token, 'all') === 0) { return ''; }

    // 1. 原样（大小写不敏感）—— 拉丁名和「已经归好位」的形式走这条
    $e = mysqli_real_escape_string($conn, $token);
    if ($q = mysqli_query($conn, "SELECT species FROM phenotype WHERE species = '$e' LIMIT 1")) {
        if ($r = mysqli_fetch_row($q)) { return $r[0]; }
    }

    // 2. 经 abbr 表折算一次（短码 / 下划线形式）
    require_once __DIR__ . '/state.php';
    $latin = cnido_latin_of($token, $conn);
    if ($latin !== '' && $latin !== $token) {
        $e2 = mysqli_real_escape_string($conn, $latin);
        if ($q = mysqli_query($conn, "SELECT species FROM phenotype WHERE species = '$e2' LIMIT 1")) {
            if ($r = mysqli_fetch_row($q)) { return $r[0]; }
        }
        // 3. 下划线 → 空格（abbr 表里存的就是下划线形式时）
        $sp = str_replace('_', ' ', $latin);
        $e3 = mysqli_real_escape_string($conn, $sp);
        if ($q = mysqli_query($conn, "SELECT species FROM phenotype WHERE species = '$e3' LIMIT 1")) {
            if ($r = mysqli_fetch_row($q)) { return $r[0]; }
        }
    }
    return '';
}

/* 曾经这里有一个 cnido_pheno_usable_count($conn, $latin)：给单个物种数「可用的
   记录数」。已删除 —— 单物种的可用数由 cnido_pheno_summary() 从已经取回来的
   分组行里算（物种页本来就要拉这些行，再查一遍是白扫一趟），而物种卡片上的数字
   来自 includes/coverage.php 的 COUNT(*)，是**折叠后的行数**：卡片报 Corallium
   rubrum 688 行（源记录 944 条），物种页把两个数都写出来。两个数都写在各页上
   说明清楚，不让它们互相冒充。 */

/* ---------------------------------------------------------------------
 * trait 字典与文献（phenotype_trait_dict / phenotype_source_resource）
 * ------------------------------------------------------------------- */

/**
 * trait 名字 → 这个 trait 是什么（说明 / 允许取值 / 数据类型 / 常用单位）。
 *
 * 两张字典表由 build_phenotype_refs.php 建：OCTD 的 trait_id.csv（127 条说明）
 * 与 Pelagic 的 metadata.csv（53 条）。同一个 trait 名在两家都出现时取 OCTD 的
 * 那条（它的说明更细）—— 表里按 (source, trait_name) 唯一，所以这里做一次覆盖式归并。
 *
 * @return array trait_name => array(desc, allowed, data_type, unit, source)
 */
function cnido_pheno_trait_dict($conn)
{
    static $memo = null;
    if ($memo !== null) { return $memo; }
    $memo = array();
    $q = mysqli_query($conn,
        "SELECT source, trait_name, trait_desc, allowed_values, data_type, traitunit
           FROM phenotype_trait_dict ORDER BY (source = 'OCTD v2.2') DESC, trait_name");
    while ($q && ($r = mysqli_fetch_assoc($q))) {
        $k = trim((string)$r['trait_name']);
        if ($k === '' || isset($memo[$k])) { continue; }
        $memo[$k] = array(
            'desc'      => trim((string)$r['trait_desc']),
            'allowed'   => trim((string)$r['allowed_values']),
            'data_type' => trim((string)$r['data_type']),
            'unit'      => trim((string)$r['traitunit']),
            'source'    => trim((string)$r['source']),
        );
    }
    return $memo;
}

/**
 * 一个物种的记录所引用的文献（phenotype_source_resource）。
 *
 * OCTD 的每一行都带 resource_id，WoRMS 的行带 source_id（已写成
 * 'WoRMS source record' 类型），这两家都能追到出处；Pelagic 与 legacy 没有。
 * 页面把这份清单印在物种页底部 —— 三家的许可都要求署名，而且用户要能自己核。
 *
 * @return array 每项 array(source, resource_id, authors, year, title, container, doi,
 *                        resource_type, rows_, src_rows)
 */
function cnido_pheno_species_refs($conn, $latin)
{
    $out = array();
    $e = mysqli_real_escape_string($conn, $latin);
    $q = mysqli_query($conn,
        "SELECT r.source, r.resource_id, r.authors, r.year, r.title, r.container, r.doi,
                r.resource_type, COUNT(*) AS rows_, SUM(p.n_records) AS src_rows
           FROM phenotype p
           JOIN phenotype_source_resource r
             ON r.source = p.source AND r.resource_id = p.source_resource_id
          WHERE p.species = '$e' AND p.source_resource_id <> ''
          GROUP BY r.source, r.resource_id, r.authors, r.year, r.title, r.container, r.doi,
                   r.resource_type
          ORDER BY r.year DESC, r.authors, r.resource_id");
    while ($q && ($r = mysqli_fetch_assoc($q))) { $out[] = $r; }
    return $out;
}

/* ---------------------------------------------------------------------
 * 全表统计（页面上那些「这张表有多可信」的数字都从这里取）
 * ------------------------------------------------------------------- */

/**
 * 返回 phenotype 全表的可用性统计，缓存 1 小时。
 *
 * 结构（**几个维度刻意不重叠**，各自的基数写在名字里）：
 *   tot          折叠后的行数
 *   raw          折叠前的源记录数（= SUM(n_records)）
 *   placeholder  value 不能用的行数 = coded + empty
 *   coded        value 是源库 standard_id 编号的行数（重建后剩 75 行）
 *   real         value 是真实取值的行数 = tot - placeholder
 *   u_meas / u_deriv / u_inherit / u_expert / u_unknown
 *                在**可用**的行里按来源属性分开计数
 *   species      表里出现过的不重复物种数
 *   sources      source => array(rows, raw, species)  按来源库分开
 *   built        生成时间戳
 *
 * 为什么 provenance 的分母是「可用的行」而不是全部行：一行可以**同时**是「值是
 * 编号」和「methodology 写着 inherited」—— 值不可用，来源恰好是继承来的。早先
 * 把「78.7% 是占位」和「52.4% 是继承/推导」并列写出来，两者相加超过 100%，是错的。
 * 正确的说法是「N 行里 M 行的值不可用；剩下 K 行可用，其中 X 行是实测的」。
 *
 * 代价：两条全表扫描（13 万行），所以缓存。缓存放站点 tmp/
 * （includes/cache.php 的 cnido_cache_path()）—— CLI 与 www-data 唯一共用的
 * 一份，写盘用临时文件 + rename，并发下不会读到写了一半的 JSON。
 * 缓存不可用时照常现算，只是慢一点，页面不需要知道差别。
 */
function cnido_pheno_global_stats($conn, $force = false)
{
    static $memo = null;
    if ($memo !== null && !$force) { return $memo; }

    require_once __DIR__ . '/cache.php';
    $f = function_exists('cnido_cache_path') ? cnido_cache_path('phenotype_stats.json') : '';
    if ($f !== '' && !$force && is_file($f)) {
        $j = json_decode((string)@file_get_contents($f), true);
        /* v 用来在统计口径或数据变化时作废旧缓存。
           v2：判据从「cat/bin/stNN 三个特例」换成 OCTD 的 standard_id 字典。
           v3：换到重建后的表（多了 value_type / source / n_records 三列，
               属性分级改成以源库的 value_type 为准，并加了 expert 一类）。
           v4：编号判定加了来源那一段（cnido_pheno_source_has_codes），并记下残留
               编号行落在哪些物种/纲上（coded_sp / coded_classes）—— 页面那句
               「这些行是哪些」不再写死。 */
        if (is_array($j) && isset($j['v'], $j['built'], $j['tot'], $j['coded'])
            && $j['v'] === 4 && (time() - $j['built']) < 3600) {
            $memo = $j;
            return $memo;
        }
    }

    $out = array('v' => 4, 'built' => time(),
                 'tot' => 0, 'raw' => 0, 'placeholder' => 0, 'coded' => 0, 'real' => 0, 'species' => 0,
                 'u_meas' => 0, 'u_deriv' => 0, 'u_inherit' => 0, 'u_expert' => 0, 'u_unknown' => 0,
                 'coded_sp' => array(), 'coded_classes' => array(),
                 'sources' => array());

    /* 与 cnido_pheno_is_placeholder() 同源：空值，或「来源仍可能是编号」的行里
       (unit,value) 命中字典。谓词从字典现拼（cnido_pheno_coded_sql()），所以字典
       更新后这里自动跟上；来源那一段（见该函数的注释）说明为什么不能只看字典。 */
    $coded_sql = cnido_pheno_coded_sql('source');
    $usable_sql = "(NOT ($coded_sql)
                   AND TRIM(COALESCE(value,'')) <> '')";

    if ($q = mysqli_query($conn,
        "SELECT COUNT(*) tot, SUM(n_records) raw, COUNT(DISTINCT species) sp,
                SUM($coded_sql) cd,
                SUM(NOT $usable_sql) ph
           FROM phenotype")) {
        if ($r = mysqli_fetch_assoc($q)) {
            $out['tot']         = (int)$r['tot'];
            $out['raw']         = (int)$r['raw'];
            $out['species']     = (int)$r['sp'];
            $out['coded']       = (int)$r['cd'];
            $out['placeholder'] = (int)$r['ph'];
            $out['real']        = (int)$r['tot'] - (int)$r['ph'];
        }
    }

    /* 残留的编号行落在谁身上。页面那句「这些行是哪几个物种」必须来自这里，
       不能在文案里写死物种名（重建前全表 148,510 行命中、重建后只剩 16 行且换了
       物种，写死的名单必然过期）。只在真有命中时查这两条。 */
    if ($out['coded'] > 0) {
        if ($q = mysqli_query($conn,
            "SELECT species, COUNT(*) n FROM phenotype WHERE $coded_sql GROUP BY species
              ORDER BY n DESC")) {
            while ($r = mysqli_fetch_assoc($q)) {
                $out['coded_sp'][(string)$r['species']] = (int)$r['n'];
            }
        }
        if ($q = mysqli_query($conn,
            "SELECT Class, COUNT(*) n FROM phenotype WHERE $coded_sql GROUP BY Class
              ORDER BY n DESC")) {
            while ($r = mysqli_fetch_assoc($q)) {
                $out['coded_classes'][(string)$r['Class']] = (int)$r['n'];
            }
        }
    }

    /* 可用行按来源属性分组。value_type × methodology 的组合只有几百种，所以
       分组之后在 PHP 里过分类器即可 —— 与页面上每条记录用的是同一个函数，
       汇总数不会和明细打架。 */
    if ($out['tot'] > 0) {
        if ($q = mysqli_query($conn,
            "SELECT methodology, value_type, COUNT(*) n FROM phenotype
              WHERE $usable_sql GROUP BY methodology, value_type")) {
            while ($r = mysqli_fetch_assoc($q)) {
                $p = cnido_pheno_provenance($r['methodology'], $r['value_type']);
                $k = 'u_' . ($p === 'measured' ? 'meas' : ($p === 'derived' ? 'deriv'
                            : ($p === 'inherited' ? 'inherit' : ($p === 'expert' ? 'expert' : 'unknown'))));
                $out[$k] += (int)$r['n'];
            }
        }
        if ($q = mysqli_query($conn,
            "SELECT source, COUNT(*) rows_, SUM(n_records) raw, COUNT(DISTINCT species) sp
               FROM phenotype GROUP BY source ORDER BY rows_ DESC")) {
            while ($r = mysqli_fetch_assoc($q)) {
                $out['sources'][(string)$r['source']] = array(
                    'rows' => (int)$r['rows_'], 'raw' => (int)$r['raw'], 'species' => (int)$r['sp']);
            }
        }
    }

    if ($f !== '') {
        $tmp = $f . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, json_encode($out), LOCK_EX) !== false) {
            if (!@rename($tmp, $f)) { @unlink($tmp); }
        }
    }
    $memo = $out;
    return $memo;
}
