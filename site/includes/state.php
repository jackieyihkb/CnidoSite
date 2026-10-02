<?php
/* =====================================================================
 * 各分析模块的选择状态（species / hisType / hisMark / gene）
 *
 * 审稿意见 Referee 1 minor 1 & 3、Referee 2 major 1：
 *   "The species selector appears not to work / is not synchronised with the
 *    search box."
 * 审稿意见 Referee 2 major 6 / 10(ii)：表观组页面只能选到 miRNA-seq。
 *
 * 三个根因，本文件统一处理：
 *   1. 多个页面共用同一组 $_SESSION 键（'species' / 'hisType' / 'hisMark'），
 *      但各自的值域不同（有的存拉丁名，有的存 EDIAP 这样的缩写）。在 A 模块
 *      选完物种，跳到 B 模块会继承一个 B 根本认不出的值。→ 按键命名空间隔离。
 *   2. 大多数页面只读 $_POST，从别的模块深链过来（GET）时选择被忽略，
 *      页面显示默认值。→ GET 优先。
 *   3. 下拉框的候选项完全依赖 DynamicOptionList 这个 1990 年代的 JS 库
 *      （printOptions() 在现代浏览器里是空操作）。一旦 JS 没跑起来，
 *      <select> 里一个 option 都没有，表单提交的是空值。→ 由调用方用
 *      cnido_options() 在服务端渲染真正的 option。
 * ===================================================================== */

/* ---------------------------------------------------------------------
 * 数据库连接
 *
 * 本站没有公共连库函数，每个页面自己 new mysqli，于是连接参数被复制到
 * 全站每一个文件里（core/index.php 等页面就是这么来的）。这里放一份，
 * 新页面用 cnido_conn() 取，别再抄。
 *
 * Archived copy: the four arguments below are read from the environment
 * (CNIDO_DB_HOST / CNIDO_DB_USER / CNIDO_DB_PASS / CNIDO_DB_NAME), so this
 * repository stores no credential. In the production file on the authors'
 * server the same four values appear as string literals instead, because the
 * CLI helper there parses them out of this line by regular expression. The
 * helper archived here (scripts/setup/dbq.php) reads the environment first and
 * only falls back to parsing this file, so it works with either form.
 *
 * 本机 CLI 的 php.ini 是坏的，要在命令行里试连接请用：
 *   PHP_INI_SCAN_DIR=/etc/php/7.4/apache2/conf.d php -c /etc/php/7.4/apache2/php.ini …
 * --------------------------------------------------------------------- */

/** 建一条本站数据库连接（失败时返回 connect_error 非空的 mysqli，不抛异常、不 die）。 */
function cnido_conn()
{
    $conn = @new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
    return $conn;
}

/** 确保会话已启动，且不会因「头部已发送」而静默失败。 */
function cnido_session()
{
    if (session_status() === PHP_SESSION_NONE) {
        if (!headers_sent()) { @session_start(); }
    }
}

/**
 * 解析一个模块的若干状态字段。
 *
 * @param string $module  模块前缀，用于命名空间，例如 'atac'、'proteomic'
 * @param array  $fields  array(变量名 => array('get'=>'species','post'=>'species','default'=>'EDIAP'))
 * @return array          变量名 => 取值
 */
function cnido_state($module, $fields)
{
    cnido_session();
    $out = array();
    /* 本次请求是不是「裸访问」：查询串和 POST 都为空。
       见循环里 (b) 的说明。 */
    $__cnidoFresh = (empty($_GET) && empty($_POST));
    foreach ($fields as $var => $cfg) {
        $getKey  = isset($cfg['get'])  ? $cfg['get']  : $var;
        $postKey = isset($cfg['post']) ? $cfg['post'] : $getKey;
        $skey    = $module . '_' . $var;          // 命名空间化的 session 键

        /* 区分三种情况：「参数没出现」/「参数出现但是空串」/「参数不在，但会话里有上次的选择」。
           以前用 isset(...) && !== '' 判断，空串会被当成「没出现」，
           于是会话里上一次的值继续生效 —— 效果是筛选一旦选过就再也清不掉：
           sn_data.php 的「-- all species --」选项和「show all species」链接都失效，
           站点导航上的 /sn_data.php 也会一直停在上次选的那个物种；
           mitdata.php 则会把深链失败时的警告横幅粘在后续所有访问上。

           两处修正：
           (a) 空串 = 显式清除，回落默认值，不再写回会话；
           (b) 裸访问（$__cnidoFresh：查询串与 POST 都为空，即点顶部导航的
               /mitdata.php、书签、直接输网址）视为「重新进入模块」，同样
               不套用会话，并顺手把会话清掉。带参数（?class=X、?page=2）或
               表单提交仍读会话，模块内切换类群/翻页不会丢选择。 */
        $raw = null;
        $present = false;
        $from = '';
        if (array_key_exists($getKey, $_GET)) {                          // 1. 深链
            $raw = (string)$_GET[$getKey];  $present = true;  $from = 'get';
        } elseif (array_key_exists($postKey, $_POST)) {                  // 2. 表单提交
            $raw = (string)$_POST[$postKey]; $present = true; $from = 'post';
        } elseif (!$__cnidoFresh && isset($_SESSION[$skey]) && $_SESSION[$skey] !== '') {  // 3. 会话
            $raw = $_SESSION[$skey];  $from = 'session';
        }

        if ($present && $raw === '') {
            unset($_SESSION[$skey]);                                     // 显式清除
            $val  = isset($cfg['default']) ? $cfg['default'] : '';
            $from = 'default';
        } elseif ($raw === null) {                                       // 4. 默认值
            unset($_SESSION[$skey]);                                     // 裸访问 = 重置
            $val  = isset($cfg['default']) ? $cfg['default'] : '';
            $from = 'default';
        } else {
            $val = $raw;
            // 表单提交或深链时写回会话，方便后续翻页保持
            if ($from === 'get' || $from === 'post') { $_SESSION[$skey] = $val; }
        }

        $out[$var] = $val;
        $out[$var . '__from'] = $from;
    }
    return $out;
}

/**
 * 在服务端渲染 <select> 的 option（不依赖 JS）。
 *
 * @param array  $values    可选值列表
 * @param string $selected  当前选中值
 * @param bool   $keepFirst 是否保留一个空的「请选择」项
 * @param array  $labels    可选：值 => 显示文字。某些模块的 option 值必须是
 *                          数据表前缀（ADIGI、EDIAP…），但把这些五字母代码直接
 *                          显示给使用者是站内长期被诟病的一点 —— 传了 $labels
 *                          就按拉丁学名显示、按代码提交，两者各自正确。
 */
function cnido_options($values, $selected, $keepFirst = false, $labels = array())
{
    $html = '';
    if ($keepFirst) { $html .= '<option value="">-- please select --</option>'; }
    $seen = array();
    foreach ($values as $v) {
        $v = (string)$v;
        if ($v === '' || isset($seen[$v])) { continue; }
        $seen[$v] = true;
        $lbl = (isset($labels[$v]) && $labels[$v] !== '') ? $labels[$v] : $v;
        $sel = ((string)$selected === $v) ? ' selected="selected"' : '';
        $html .= '<option value="' . htmlspecialchars($v) . '"' . $sel . '>'
               . htmlspecialchars($lbl) . '</option>';
    }
    // 当前值不在候选列表里时补进去，避免下拉框显示空白、与服务器状态不一致
    if ($selected !== '' && !isset($seen[(string)$selected])) {
        $lbl = '';
        if (isset($labels[$selected]) && $labels[$selected] !== '') {
            $lbl = $labels[$selected];
        } else {
            /* 当前值不在候选列表里，映射里自然也没有它。这时也去 abbr 表问一次：
               深链常把下划线形式（Nematostella_vectensis）或短码（NVECT）塞进来，
               原样显示出去就是把物种代码可视化给读者看了。
               cnido_latin() 查不到时原样返回，所以对组织/阶段这类非物种下拉框
               没有任何影响（整张映射表按请求缓存，只多一条查询）。 */
            $lbl = cnido_latin($selected);
        }
        if ($lbl === '') { $lbl = $selected; }
        $html = '<option value="' . htmlspecialchars($selected) . '" selected="selected">'
              . htmlspecialchars($lbl) . ' (unavailable)</option>' . $html;
    }
    return $html;
}

/**
 * abbr1 短码 => 拉丁学名。
 *
 * 站内有一批模块用五字母短码做数据表前缀和 option 的 value（ADIGI_DHS、
 * EDIAP_ATAC…），这些值不能改（改了几十个模块的表名拼装和深链会一起断），
 * 但显示给使用者的必须是拉丁学名（审稿意见：物种缩写名应更正为拉丁学名）。
 * 本函数只做显示层映射。
 *
 * @param array       $codes 需要翻译的短码；留空则返回全表
 * @param mysqli|null $conn  可复用已有连接
 * @return array              短码/下划线形式/拉丁学名 => 拉丁学名（查不到的键不存在）
 */
function cnido_latin_map($codes = array(), $conn = null)
{
    $out = array();
    $own = false;
    if (!$conn) {
        $conn = cnido_conn();
        $own  = true;
    }
    if (!$conn || $conn->connect_error) { return $out; }

    $where = '';
    if ($codes) {
        $esc = array();
        foreach ($codes as $c) { $esc[] = "'" . mysqli_real_escape_string($conn, (string)$c) . "'"; }
        $where = ' WHERE abbr1 IN (' . implode(',', $esc) . ')';
    }
    $sql = "SELECT abbr1, species, abbr FROM abbr" . $where;
    $q   = mysqli_query($conn, $sql);

    /* 调用方传进来的可能是已经 close() 过的连接（页面上很常见：查询段用完就关，
       后面渲染下拉框时才需要翻译）。查询失败时自建一条连接重试一次，
       否则这里会静默返回空映射，调用方只好把短码当学名显示出去。 */
    if (!$q && !$own) {
        $fresh = cnido_conn();
        if ($fresh && !$fresh->connect_error) {
            $conn = $fresh;
            $own  = true;
            $q    = mysqli_query($conn, $sql);
        }
    }
    /* 同一物种在站内有三套写法，页与页之间传参时经常混用：
     *   abbr1   "NVECT"                   五字母短码
     *   abbr    "Nematostella_vectensis"  下划线形式
     *   species "Nematostella vectensis"  拉丁学名（带空格）
     * 只按 abbr1 建索引的话，拿到下划线或带空格形式的调用方查不到学名，
     * 只好把代码原样显示给读者。三种写法都指向同一个学名，一并建索引，
     * 于是 cnido_latin() 对任何形式都能给出拉丁学名。 */
    while ($q && ($r = mysqli_fetch_row($q))) {
        $latin = $r[1];
        if ($latin === null || $latin === '') { continue; }
        if ($r[0] !== null && $r[0] !== '') { $out[$r[0]] = $latin; }
        if ($r[2] !== null && $r[2] !== '') { $out[$r[2]] = $latin; }
        $out[$latin] = $latin;
    }
    if ($own) { $conn->close(); }
    return $out;
}

/**
 * 单个短码 => 拉丁学名；查不到时原样返回（宁可显示代码，也不要空字符串）。
 * 便捷包装，内部按请求缓存整张映射表。
 */
function cnido_latin($code, $conn = null)
{
    static $map = null;
    $code = (string)$code;
    if ($code === '') { return ''; }
    if ($map === null) { $map = cnido_latin_map(array(), $conn); }
    return isset($map[$code]) ? $map[$code] : $code;
}

/**
 * 把页面上收到的「物种标识」统一解析成拉丁学名。
 *
 * 站内同一物种有三套写法，页与页之间传参时经常混用，接收端只按其中一种去比对
 * 就会查空、把物种名渲染成空白：
 *   - species  "Nematostella vectensis"      拉丁学名（带空格）
 *   - abbr     "Nematostella_vectensis"      下划线形式，早期页面之间传的就是它
 *   - abbr1    "NVECT"                       五字母短码，物种门户与导航用
 * family_member.php / family_member_detail.php 原来只按 species 列精确匹配，
 * 而链接传过来的是下划线形式，于是标题渲染成 "Ubiquitin Family in "（名字空缺）。
 *
 * 命中不了就原样返回，宁可显示原始代码也不要空字符串。
 */
function cnido_latin_of($token, $conn = null)
{
    $token = trim((string)$token);
    if ($token === '') { return ''; }

    static $cache = array();
    if (isset($cache[$token])) { return $cache[$token]; }

    $own = false;
    if (!$conn) {
        $conn = cnido_conn();
        $own  = true;
    }
    $resolved = $token;
    $answered = false;   // 查询是否真的跑过（区别于「连接坏了所以查不了」）
    if ($conn && !$conn->connect_error) {
        $e   = mysqli_real_escape_string($conn, $token);
        $u   = mysqli_real_escape_string($conn, str_replace(' ', '_', $token));
        $sql = "SELECT species FROM abbr
                 WHERE species = '$e' OR abbr = '$e' OR abbr1 = '$e'
                    OR abbr = '$u' OR abbr1 = '$u'
                 LIMIT 1";
        $q = mysqli_query($conn, $sql);
        if (!$q && !$own) {
            $fresh = cnido_conn();
            if ($fresh && !$fresh->connect_error) {
                $conn = $fresh;
                $own  = true;
                $q    = mysqli_query($conn, $sql);
            }
        }
        if ($q) {
            $answered = true;
            if (($r = mysqli_fetch_row($q)) && $r[0] !== null && $r[0] !== '') {
                $resolved = $r[0];
            }
        }
    }
    if ($own) { $conn->close(); }

    /* 只在查询确实执行过时才写缓存。传进来的连接可能已经被调用方 close() 了
       （页面上很常见：查询段用完就关），那时查不了、$resolved 还是原值；
       若把这种「没查到」也缓存下来，本次请求里后续所有翻译都会拿到原始代码。 */
    if ($answered) { $cache[$token] = $resolved; }
    return $resolved;
}

/**
 * 宽容版翻译：先精确查，查不到再按「归一化前缀」猜，只在候选唯一时才采用。
 *
 * BLAST 的数据库文件名是学名被压缩后的写法（Actinernus_sp、Aurelia_sp_4），
 * 跟 abbr 表里的学名、下划线形式都对不上，于是下拉框会把这些文件名原样显示 ——
 * 又是一处下划线形式被可视化。把两边的非字母数字字符全部去掉再做前缀匹配即可，
 * 但必须要求候选唯一：有歧义时宁可回退成原串，也不能把库标成另一个物种。
 *
 * @return string 拉丁学名；无法唯一确定时原样返回 $token
 */
function cnido_latin_loose($token, $conn = null)
{
    $token = trim((string)$token);
    if ($token === '') { return ''; }

    $exact = cnido_latin_of($token, $conn);
    if ($exact !== $token) { return $exact; }     // 精确命中（短码 / 下划线形式 / 学名）

    static $norm = null;
    if ($norm === null) {
        $norm = array();
        foreach (cnido_latin_map(array(), $conn) as $k => $latin) {
            $n = strtolower(preg_replace('/[^a-z0-9]/i', '', (string)$k));
            if ($n === '') { continue; }
            /* 同一个归一化写法可能指向多个物种，全部记下来以便识别歧义 */
            $norm[$n][$latin] = true;
        }
    }

    $needle = strtolower(preg_replace('/[^a-z0-9]/i', '', $token));
    if ($needle === '') { return $token; }

    $hit = array();
    foreach ($norm as $n => $latins) {
        if (strpos($n, $needle) === 0) {
            foreach ($latins as $l => $_) { $hit[$l] = true; }
            if (count($hit) > 1) { return $token; }   // 已经有歧义，不必再找
        }
    }
    return count($hit) === 1 ? key($hit) : $token;
}

/**
 * 任意写法 => abbr1 五字母短码；查不到时返回 ''。
 *
 * 有些模块（ATAC / DHS / Metagenome 等）整页都以短码为键：静态 <option> 写的是
 * EDIAP、ADIGI，数据表前缀也是短码。深链或旧链接传进来的却可能是下划线形式或
 * 拉丁学名，于是「候选里没有它」——下拉框多出一个 (unavailable) 项，和列表里
 * 同一个物种的学名重复。先用这个函数把入参归一化到短码再比对。
 */
function cnido_code_of($token, $conn = null)
{
    $token = trim((string)$token);
    if ($token === '') { return ''; }

    static $rev = null;
    if ($rev === null) {
        $rev = array();
        /* 这条连接只用来建映射表，建完即关。注意不能把 $conn 传下去给
           cnido_latin_of() —— 那时它已经 close()，查询失败又会被缓存成
           「查不到」，本次请求里后续所有物种翻译就全部退回显示原始代码。 */
        $revConn = $conn ? $conn : cnido_conn();
        $revOwn  = !$conn;
        if ($revConn && !$revConn->connect_error) {
            $q = mysqli_query($revConn, "SELECT abbr1, species FROM abbr");
            while ($q && ($r = mysqli_fetch_row($q))) {
                if ($r[0] !== null && $r[0] !== '' && $r[1] !== null && $r[1] !== '') {
                    $rev[$r[1]] = $r[0];   // 拉丁学名 => 短码
                }
            }
        }
        if ($revOwn && $revConn) { $revConn->close(); }
    }

    if (isset($rev[$token])) { return $rev[$token]; }            // 已经是拉丁学名
    $latin = cnido_latin_of($token);                              // 短码 / 下划线形式
    return isset($rev[$latin]) ? $rev[$latin] : '';
}

/** 当前查询串（把当前状态拼成 URL 参数，用于分页/深链）。 */
function cnido_qs($pairs)
{
    $q = array();
    foreach ($pairs as $k => $v) { if ($v !== '' && $v !== null) { $q[$k] = $v; } }
    return http_build_query($q);
}

/* ===========================================================================
 * 分页表上的搜索框 —— 搜索词归一化 + LIKE 模式串
 *
 * 全站的搜索框都是「GET 里收一个词，然后拼 LIKE」。两件小事各有各的坑，之前各处
 * 各写一遍、写法还不一致（有的先转义再替换通配符、有的一处都不转义），于是同一个
 * 词在不同页面上命中的行不一样。集中在这里。
 * ===========================================================================
 */

/** 搜索词归一化：去首尾空白 + 按**字符**截断。 */
function cnido_search_term($raw, $max = 100)
{
    $t = trim((string)$raw);
    if ($t === '') { return ''; }
    /* 必须按字符截断：substr 会从一个 UTF-8 字符中间切断，切出来的半个字符拼进
       SQL 是无效字节序列（用户搜中文、重音拉丁文时就是这个坏法）。 */
    if (function_exists('mb_substr')) {
        return mb_substr($t, 0, $max, 'UTF-8');
    }
    return substr($t, 0, $max);
}

/**
 * 把搜索词变成可以直接拼进 SQL 的 LIKE 模式串（模式串本身已完整转义）。
 *
 *   $like = cnido_like($conn, $q);            // %词%  任意位置
 *   $like = cnido_like($conn, $q, 'prefix');  // 词%   只有这一种能吃前缀索引
 *
 * 转义顺序是要命的地方：**先** addcslashes 把 % _ \ 变成字面量，**再**交给
 * mysqli_real_escape_string 做 SQL 层转义。反过来的话 mysqli 会先把反斜杠变成
 * `\\`，用户自己输入的反斜杠就躲过了 addcslashes 这一层 —— 搜索 `a\b` 会命中
 * `ab`（LIKE 把 `\b` 读成转义过的 b）。% 和 _ 两种顺序结果相同，所以这个错法
 * 平时看不出来，只在用户输入含反斜杠时露头。
 *
 * 词为空时返回 '%'（不过滤）。要不要加这个条件请用 cnido_search_term() 的返回值
 * 判断，别拿这个串判空。
 */
function cnido_like($conn, $term, $mode = 'contains')
{
    $t = addcslashes((string)$term, '%_\\');
    if ($mode === 'prefix') {
        $t .= '%';
    } elseif ($mode === 'suffix') {
        $t = '%' . $t;
    } else {
        $t = '%' . $t . '%';
    }
    return mysqli_real_escape_string($conn, $t);
}

/**
 * 「这几列里任意一列包含该词」的 SQL 片段（含外层括号，可直接 AND 进 WHERE）。
 *
 *   $where[] = cnido_like_any($conn, $q, array('trait_name', 'region'));
 *   → (trait_name LIKE '%词%' OR region LIKE '%词%')
 *
 * $cols 必须是页面写死的列名 —— 这里只转义词，不校验列名，别把请求里的值传进来。
 */
function cnido_like_any($conn, $term, $cols, $mode = 'contains')
{
    $like  = cnido_like($conn, $term, $mode);
    $parts = array();
    foreach ($cols as $c) { $parts[] = $c . " LIKE '" . $like . "'"; }
    return '(' . implode(' OR ', $parts) . ')';
}

/* ===========================================================================
 * 基因号归一化 —— 同一个基因的两种写法都必须能检出
 *
 * 同一个基因在库里有两种写法：注释表（<ABBR>_go / _ipr / _pfam / _panther /
 * _KEGG / _nr / _uniprot）存的是带版本后缀的 mRNA 级 ID，而 <ABBR>_locus 存的是
 * 不带后缀的形式。实测（HVIRI）：_go 里是 BRAKERKREP00000015160.1，locus 里是
 * BRAKERKREP00000015160。147 个有注释的物种里 78 个两处写法一致，53 个差一个
 * `.1` 后缀。
 *
 * 所以查询一律用候选写法 IN (...)，不要用 = ：
 *   - 带后缀输入：原来只查注释表命中，基因模型/JBrowse 整块不出现；
 *   - 不带后缀输入：原来只查 locus 命中，注释区显示「No … signature was
 *     detected … the search simply returned no hit」—— 把 ID 写法不匹配
 *     伪装成了「该基因没有注释」，这是会误导用户的错误陈述。
 *
 * 用法：WHERE gene IN (cnido_gene_in($conn, $gene))
 * ===========================================================================
 */

/** 一个基因号的全部候选写法。
 *
 *  维度一：`.1` 版本后缀的有无（见上面的说明）；
 *  维度二：NCBI GFF3 的**特征类型前缀** `gene-` / `rna-` / `cds-` 的有无
 *          （见下面的说明）。 */
function cnido_gene_id_forms($gene)
{
    $gene = trim((string)$gene);
    if ($gene === '') { return array(); }

    /* 同一个基因还有第三种写法差异：从 NCBI 下载的 GFF3 里，ID 一律带特征类型
       前缀 —— `ID=gene-LOC116613690;Name=LOC116613690;gene=LOC116613690`。TE 流程
       取的是 `ID`（原样入库，符合「不改写本物种 GFF 原名」的约定），站点
       <ABBR>_locus 取的是同一行不带前缀的写法，于是：
           NVECT_TE.related_gene = `gene-LOC116613690`
           NVECT_locus.gene      = `LOC116613690`     （带前缀的 0 行）
       两边对不上 —— 用站点自己的基因号搜 TE 得到「Sorry, no transposable
       elements」，而该基因实际有 100 条。受影响：NVECT 214,449 行、HSALM 20,366、
       MSQUA 2,754、TKITA 2,100；我们新跑的 Acropora/Millepora/Montipora 是
       BRAKER 式 ID，不受影响，但**每个 NCBI 来源的基因组都会中招**。
       这里把带前缀与不带前缀两种写法都并进候选列表，两个方向都能搜到。 */
    $bases = array($gene);
    foreach (array('gene-', 'rna-', 'cds-') as $p) {
        if (strncmp($gene, $p, strlen($p)) === 0) {
            $bases[] = substr($gene, strlen($p));   // 带前缀 → 也试去掉的
        } else {
            $bases[] = $p . $gene;                  // 不带 → 也试加上的
        }
    }

    $forms = array();
    foreach ($bases as $b) {
        if ($b === '') { continue; }
        $forms[] = $b;
        /* 只在这一处做后缀加减：`aacu_s0003.g197.t1` 这类 ID 结尾是 `t1` 而不是
           `.1`，所以不会被误删成 `…g197.t`。 */
        if (substr($b, -2) === '.1') {
            $forms[] = substr($b, 0, -2);
        } else {
            $forms[] = $b . '.1';
        }
    }
    return array_values(array_unique($forms));
}

/** 一个基因号的规范写法：去掉 GFF3 的特征类型前缀（`gene-` / `rna-` / `cds-`）。
 *
 *  TE 表里的 related_gene 存的是本物种 GFF 的原始 ID，NCBI 来源的基因组带前缀。
 *  解析不到对应基因时，页面就用这个规范写法显示 —— 与 <ABBR>_locus.gene 和站点
 *  的基因号约定保持一致，不会把 `gene-LOC…` 这种 GFF 内部写法直接摆在表格里。 */
function cnido_gene_id_canon($gene)
{
    $gene = trim((string)$gene);
    foreach (array('gene-', 'rna-', 'cds-') as $p) {
        if (strncmp($gene, $p, strlen($p)) === 0) {
            return substr($gene, strlen($p));
        }
    }
    return $gene;
}

/** 把候选写法拼成 SQL 的 IN 列表（已转义），供 `WHERE gene IN (...)` 使用。 */
function cnido_gene_in($conn, $gene)
{
    $parts = array();
    foreach (cnido_gene_id_forms($gene) as $v) {
        $parts[] = "'" . mysqli_real_escape_string($conn, $v) . "'";
    }
    return $parts ? implode(',', $parts) : "''";
}

/**
 * 只给基因号、不给物种时，反查这个号出现在哪些物种里。
 *
 * gene_detail.php 原来只有一条反查路径：genefamily → busco。但 busco 表里每个
 * 物种只存几千条 BUSCO 命中的基因（TSTEP 是 4,240 / 52,425），genefamily 只覆盖
 * 参与直系同源分组的物种，于是**绝大多数基因号在没有 species 参数时一律显示
 * 「Gene not found」**——而 BLAST 结果页给出的链接恰恰不带 species
 * (blast/blast_result.php)，直接改地址栏输入基因号也一样。
 *
 * 这里直接拿候选写法去每张 <ABBR>_locus 表里探一次（mRNA 与 gene 两列都试，
 * 两列都有索引）。145 张表一次 UNION 查询，实测 20~80 ms，只在前面几条便宜
 * 路径都落空时才跑。
 *
 * 返回的是**全部**命中的物种，不是一个。基因号跨物种撞车是真实存在的：
 * FUN_000001-T1 同时存在于 Siderastrea siderea、Acropora pulchra 等 4 个物种。
 * 调用方拿到多个时必须让用户选，不能随便挑一个——挑错就是把另一个物种的
 * 坐标、注释、表达量当成这个基因的展示出去。
 *
 * @return array abbr1 短码列表（按表名排序，结果稳定）
 */
function cnido_species_by_gene($conn, $gene, $limit = 12)
{
    $gene = trim((string)$gene);
    if ($gene === '' || !$conn || $conn->connect_error) { return array(); }
    $in = cnido_gene_in($conn, $gene);

    $q = mysqli_query($conn, "SELECT table_name FROM information_schema.tables
                               WHERE table_schema = DATABASE()
                                 AND table_name LIKE '%\\_locus'
                               ORDER BY table_name");
    $sub = array();
    while ($q && ($r = mysqli_fetch_row($q))) {
        $t  = str_replace('`', '``', $r[0]);                       // 表名进反引号，先转义反引号
        $ab = mysqli_real_escape_string($conn, str_replace('_locus', '', $r[0]));
        $sub[] = "(SELECT '$ab' AS abbr FROM `$t` WHERE mRNA IN ($in) OR gene IN ($in) LIMIT 1)";
    }
    if (!$sub) { return array(); }

    $out = array();
    $q2  = mysqli_query($conn, "SELECT DISTINCT abbr FROM ("
                             . implode(' UNION ALL ', $sub) . ") z LIMIT " . (int)$limit);
    while ($q2 && ($r = mysqli_fetch_row($q2))) { $out[] = $r[0]; }
    return $out;
}

/**
 * 全站「序列号 → 物种」的取数表清单：哪些表能按序列号反查物种、用哪几列查。
 *
 * 站内有 145 个物种两张表可查：
 *   <ABBR>_locus  mRNA / gene        基因组注释的蛋白号
 *   <ABBR>_seq    protein / transcript  序列表（核苷酸库的号落在这里）
 * 这些列都有前缀索引，所以「一批号 × 全部物种」仍然是指数点查而不是全表扫。
 *
 * 按**列名**筛，不按表名后缀筛。`mirna_seq` 也以 `_seq` 结尾，但它是全库共用的
 * miRNA 字典（列是 MirGeneDB_ID/mature/pre…），没有 protein 列；只按名字筛就会
 * 把它拉进来，整条 UNION 报 1054 Unknown column，全批静默落空 ——
 * domain_search.php 里的 `trans_assembly_go` 已经用同一招让 28 个物种消失过。
 *
 * @return array array(array('t' => 表名, 'ab' => abbr1 短码, 'cols' => 列名数组), ...)
 */
function cnido_seq_tables($conn, $refresh = false)
{
    static $tabs = null;
    if ($tabs !== null && !$refresh) { return $tabs; }
    $tabs = array();
    if (!$conn || $conn->connect_error) { return $tabs; }

    $need = array('_locus' => array('mrna', 'gene'), '_seq' => array('protein', 'transcript'));

    $rows = array();
    $q = mysqli_query($conn, "SELECT table_name, column_name FROM information_schema.columns
                               WHERE table_schema = DATABASE()
                                 AND (table_name LIKE '%\\_locus' OR table_name LIKE '%\\_seq')");
    while ($q && ($r = mysqli_fetch_row($q))) { $rows[$r[0]][strtolower($r[1])] = true; }

    foreach ($rows as $name => $cols) {
        foreach ($need as $suf => $req) {
            if (substr($name, -strlen($suf)) !== $suf) { continue; }
            $ab = substr($name, 0, -strlen($suf));
            /* 表名拼不出占位符，只能用白名单卡形状 */
            if ($ab === '' || !preg_match('/^[A-Za-z0-9]+$/', $ab)) { continue; }
            $ok = true;
            foreach ($req as $c) { if (!isset($cols[$c])) { $ok = false; break; } }
            if (!$ok) { continue; }
            $tabs[] = array('t' => $name, 'ab' => $ab, 'cols' => $req);
            break;
        }
    }
    /* 表名排序 = 结果稳定：同一个号命中多个物种时，候选顺序每次都一样 */
    usort($tabs, function ($a, $b) { return strcmp($a['t'], $b['t']); });
    return $tabs;
}

/**
 * 批量反查：一批序列号各自出现在哪些物种里。
 *
 * 与 cnido_species_by_gene() 的分工：那个函数一次问一个号、只要物种清单，给
 * gene_detail.php 的「这个号在哪些物种里」分支用；这个函数要的是「号 => 物种」
 * 的对应关系，而且一次处理一整批。
 *
 * 为什么非批量不可：BLAST 结果页「全部数据库」模式下每条命中的物种都不同，而
 * blastall 的 tabular 输出（-m 8）**不带库名**，只能拿 subject ID 反查。逐条
 * 调用是 50 次 145 表 UNION（每次 20~60 ms），一页白加两三秒；整批并进一条
 * 查询后 50 个号 0.1 秒、2000 个写法 1 秒出头，随号数线性。
 *
 * 号先展开成候选写法：BLAST 库是用蛋白组/转录组 FASTA 建的，号多半带 `.1`
 * 后缀，而 _locus.mRNA 多数不带 —— cnido_gene_id_forms() 两种都给。
 *
 * 一个号命中多个物种是真实存在的（基因号跨物种撞名，如 FUN_002499-T1 同时
 * 存在于四个物种）。这里原样返回全部候选，**调用方不得挑一个**：挑错就是把
 * 另一个物种的坐标、注释、表达量当成这个基因展示出去。
 *
 * @param  array $ids    序列号（BLAST 的 subject id 等）
 * @param  int   $chunk  每批多少种候选写法；调小只会多跑几轮，不影响正确性
 * @return array         号 => abbr1 列表；查不到的号不出现在结果里
 */
function cnido_species_map_seqs($conn, $ids, $chunk = 300)
{
    $out = array();
    if (!$conn || $conn->connect_error) { return $out; }

    $forms = array();                                   // 候选写法 => 原号
    foreach ((array)$ids as $id) {
        $id = trim((string)$id);
        if ($id === '') { continue; }
        foreach (cnido_gene_id_forms($id) as $v) { $forms[$v] = $id; }
    }
    if (!$forms) { return $out; }

    $tabs = cnido_seq_tables($conn);
    if (!$tabs) { return $out; }

    foreach (array_chunk(array_keys($forms), max(1, (int)$chunk)) as $part) {
        $esc = array();
        foreach ($part as $v) { $esc[] = "'" . mysqli_real_escape_string($conn, $v) . "'"; }
        $in  = implode(',', $esc);
        $lim = count($part);
        $sub = array();
        foreach ($tabs as $t) {
            $tn = str_replace('`', '``', $t['t']);
            $ab = mysqli_real_escape_string($conn, $t['ab']);
            foreach ($t['cols'] as $col) {
                $sub[] = "(SELECT '$ab' AS abbr, `$col` AS sid FROM `$tn`"
                       . " WHERE `$col` IN ($in) LIMIT $lim)";
            }
        }
        if (!$sub) { break; }
        $q = mysqli_query($conn, "SELECT abbr, sid FROM (" . implode(' UNION ALL ', $sub) . ") z");
        /* 某一张表出问题（列被改名等）会让整条 UNION 失败；这时只丢这一批，
           其余批次照常，不把整页拖成空白。 */
        if (!$q) { continue; }
        while ($r = mysqli_fetch_row($q)) {
            if (!isset($forms[$r[1]])) { continue; }
            $out[$forms[$r[1]]][$r[0]] = true;
        }
    }

    foreach ($out as $k => $v) { $out[$k] = array_keys($v); }
    return $out;
}

/* ===========================================================================
 * TE 表里的 related_gene → 蛋白（mRNA）号
 *
 * <ABBR>_TE 的 related_gene 存的是**基因级**号（TE 注释是在基因模型上做的），
 * 而 gene_detail.php 和所有注释表（_nr / _uniprot / _go / _seq …）都是按
 * **蛋白（mRNA）级**号取数的，两种写法对不上：
 *
 *   TE_gene.php?gene=aacu_s0340.g1   → 「no NCBI-NR hit recorded」（19 KB 页面）
 *   TE_gene.php?gene=aacu_s0340.g1.t1→ 正常显示 NR 蛋白链接（44 KB 页面）
 *
 * 这不是该基因没有注释，而是链接指错了号。这里把 TE 的 gene 级号翻成
 * <ABBR>_locus 里的 mRNA 号，供链接使用。四种写法都覆盖：
 *   1) 本来就是 mRNA 号          → 原样（ASP1 的 Acti_000001-T1、AIDSS 的 alvinactis_v1_g10988）
 *   2) 命中 _locus.gene          → 取同行的 mRNA（AACUM aacu_s0340.g1 → aacu_s0340.g1.t1）
 *   3) 去掉开头的 `gene-` 再试 1)/2)（HSALM gene-HZS_5567 → KAF0993424.1、
 *      NVECT gene-LOC116613690 → XP_048580524.1）
 *   4) 都落空（_locus 里根本没有这个号，如 NNOMU g13632）→ 不进映射表，
 *      调用方退回原号，链接照旧指向 gene_detail.php。
 * 实测映射率：13/16 个物种 100%，CGIGA/EHORR/CMOSA/NNOMU/SMALA 74~93%（其余是
 * _locus 里没有的号），NVECT 47%（它的 TE 表引用的基因数比 _locus 多）。
 *
 * @param  array $vals TE 表 related_gene 列的若干取值（一页即可）
 * @return array 原号 => 蛋白号；只含成功换算的项
 * =========================================================================== */
function cnido_te_gene_proteins($conn, $abbr, $vals)
{
    $map = array();
    $abbr = preg_replace('/[^A-Za-z0-9_]/', '', (string)$abbr);
    if ($abbr === '' || !$conn || $conn->connect_error || !is_array($vals) || !$vals) {
        return $map;
    }

    /* 库里这些值表示「这个 TE 没有关联基因」（ASP1 有 12 万行是 `.`），
       它们不该变成链接，也不需要去查。`Protein_ID` 是 _locus 的表头行。 */
    $junk = array('', 'NA', '-', '.', 'None', 'unknown', 'null', 'Protein_ID');

    $want = array();
    foreach ($vals as $v) {
        $v = trim((string)$v);
        if (in_array($v, $junk, true)) { continue; }
        $want[$v] = true;
        if (strncmp($v, 'gene-', 5) === 0) { $want[substr($v, 5)] = true; }
    }
    if (!$want) { return $map; }

    $t = mysqli_query($conn, "SHOW TABLES LIKE '"
                           . mysqli_real_escape_string($conn, $abbr . '\\_locus') . "'");
    if (!$t || !mysqli_num_rows($t)) { return $map; }

    $esc = array();
    foreach (array_keys($want) as $w) {
        $esc[] = "'" . mysqli_real_escape_string($conn, $w) . "'";
    }
    $in = implode(',', $esc);

    $byP = array();   // mRNA 号直接命中
    $byG = array();   // gene 号 → 同行 mRNA
    $q = mysqli_query($conn, "SELECT mRNA, gene FROM `{$abbr}_locus` WHERE mRNA IN ($in)");
    while ($q && ($r = mysqli_fetch_row($q))) {
        if (!in_array(trim((string)$r[0]), $junk, true)) { $byP[$r[0]] = true; }
    }
    $q = mysqli_query($conn, "SELECT mRNA, gene FROM `{$abbr}_locus` WHERE gene IN ($in)");
    while ($q && ($r = mysqli_fetch_row($q))) {
        if (!in_array(trim((string)$r[0]), $junk, true) && !isset($byG[$r[1]])) { $byG[$r[1]] = $r[0]; }
    }

    foreach ($vals as $v) {
        $v = trim((string)$v);
        if ($v === '' || isset($map[$v]) || in_array($v, $junk, true)) { continue; }
        $cands = array($v);
        if (strncmp($v, 'gene-', 5) === 0) { $cands[] = substr($v, 5); }
        foreach ($cands as $c) {
            if (isset($byP[$c])) { $map[$v] = $c; break; }
            if (isset($byG[$c])) { $map[$v] = $byG[$c]; break; }
        }
    }
    return $map;
}

/* ===========================================================================
 * TE 结果表 Distribution 列的值 → 给人看的写法
 *
 * 这一列是 TE 注释流程写进 <ABBR>_TE.region 的原样字符串，直接印出来有三处
 * 不像给人看的：
 *   · `gene_body` 带下划线（SSIDE 16,051 行、CGIGA 1,354 行 …）
 *   · `3UTR` / `5UTR` 挤在一起，逗号/撇号都没有
 *   · 同一件事有两套写法：15 个物种写 `intron`/`exon`/`promoter`，
 *     APALM 与 MGRIS 写 `intron region`/`exon region`/`promoter region`，
 *     同列并排出现时会被当成两种不同的分布
 * 这里只改**显示**：把下划线还原成空格、UTR 写成 3′/5′ UTR、去掉 intron/
 * exon/promoter 后面多余的 " region"（那是列名本身，不是值的一部分；
 * `intergenic region` 是例外，region 是那个术语的组成部分，保持原样）。
 * 单元格带 title 显示库里存的原字符串，排序、筛选、下载都仍按原值走 ——
 * 换掉的是标签不是数据。
 *
 * @param  string $v TE 表的 region 值
 * @return string     HTML（已转义）
 * =========================================================================== */
function cnido_te_region_label($v)
{
    $raw = trim((string)$v);
    if ($raw === '') { return ''; }

    $label = str_replace('_', ' ', $raw);
    $label = preg_replace('/^([35])UTR$/i', "$1\u{2032} UTR", $label);
    $label = preg_replace('/^(intron|exon|promoter) region$/i', '$1', $label);

    if ($label === $raw) {
        return htmlspecialchars($raw, ENT_QUOTES, 'UTF-8');
    }
    return '<span title="' . htmlspecialchars($raw, ENT_QUOTES, 'UTF-8') . '">'
         . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
}

/* ===========================================================================
 * 类群（Class）与物种下拉框
 *
 * 站内类群名统一用 speciesinfo / classfy 里的写法 Hexacorallia。
 * 历史上若干页面把它写成 Hexactiniaria（数据库里并不存在这个值），
 * 既让同一个类群在站内有两个名字，也让 browse.php?class=Hexactiniaria
 * 这类链接查不到任何物种（Referee 1 minor 1）。
 * =========================================================================== */

/**
 * 站内统一的类群顺序。
 *
 * 只列七个类群，与 `classfy` / `speciesinfo` 里的取值一致，顺序与「按 Class 名
 * 排序」的结果相同（这也是 browse.php 只写 ORDER BY Class 就够用的原因）。
 *
 * 2026-09-27：Ceriantharia 不在表里，是有意的 —— 它不是一个类群。Pachycerianthus
 * multiplicatus 原先被登记成第八个 Class（第八个类群，任何筛选器、下拉框和计数都
 * 不认它），r1.5 起按 WoRMS 改记 Hexacorallia / Ceriantharia（WoRMS 把 Ceriantharia
 * 当 Hexacorallia 下的一个目；NCBI 则把它当作与 Hexacorallia、Octocorallia 并列的
 * 亚纲，本站不再采用后者）。所以这里既不要加 Ceriantharia，也不要拿它当「将来新增
 * 的类群」的例子。
 */
function cnido_class_order()
{
    return array('Cubozoa', 'Hexacorallia', 'Hydrozoa', 'Myxozoa', 'Octocorallia', 'Scyphozoa', 'Staurozoa');
}

/**
 * 历史拼写归一。站内曾把 Hexacorallia 写作 Hexactiniaria，数据库里没有这个值；
 * 旧书签 / 旧链接仍带着它，归一后照常可用，页面上只出现 Hexacorallia。
 */
function cnido_class_canon($c)
{
    $alias = array('Hexactiniaria' => 'Hexacorallia');
    return isset($alias[$c]) ? $alias[$c] : $c;
}

/**
 * 由「物种 => class」映射取出其中实际出现的类群，按站内统一顺序排列，
 * 顺序表里没有的类群（数据里将来新增的）追加在后面，不丢。
 */
function cnido_classes_in($classMap)
{
    $have = array();
    foreach ($classMap as $c) { if ($c !== '' && $c !== null) { $have[$c] = true; } }
    $out = array();
    foreach (cnido_class_order() as $c) { if (isset($have[$c])) { $out[] = $c; } }
    foreach (array_keys($have) as $c) { if (!in_array($c, $out, true)) { $out[] = $c; } }
    return $out;
}

/**
 * 由一组物种拉丁名查出各自的 class（speciesinfo.Class）。
 *
 * 用于替代页面里手写的「按注释分组」的物种列表：gene_family.php 等页原先把
 * Aurelia coerulea、Aurelia sp. 4 排在 "// Hexactiniaria" 段落下，实际是 Scyphozoa，
 * 于是类群下拉框与物种对不上。改成从库里取，分类以数据库为准。
 *
 * @param array      $names 物种拉丁名列表
 * @param mysqli|null $conn 可复用已有连接；为 null 时自建并关闭
 * @return array            物种拉丁名 => class（查不到的为 ''）
 */
function cnido_species_class_map($names, $conn = null)
{
    $out = array();
    if (!$names) { return $out; }
    $own = false;
    if (!$conn) {
        $conn = cnido_conn();
        $own  = true;
    }
    if (!$conn || $conn->connect_error) { return $out; }

    $esc = array();
    foreach ($names as $n) { $esc[] = "'" . mysqli_real_escape_string($conn, $n) . "'"; }
    $q = mysqli_query($conn,
        "SELECT a.species, s.`Class` FROM abbr a
           LEFT JOIN speciesinfo s ON s.abbr = a.abbr1
          WHERE a.species IN (" . implode(',', $esc) . ")");
    while ($q && ($r = mysqli_fetch_row($q))) { $out[$r[0]] = (string)$r[1]; }

    if ($own) { $conn->close(); }
    return $out;
}

/**
 * 渲染 class 下拉框。
 *
 * data-cnido-class / data-cnido-species 是给 js/cnido-species-filter.js 用的配对标记：
 * 一个表单里可能有多组 class/species（search.php 有 4 组），靠 $pair 区分，
 * 否则改一个类群会把其它组的物种下拉框一起过滤。
 *
 * @param array  $classes  候选类群
 * @param string $selected 当前值（不在候选里时补一个，避免下拉框与服务器状态不一致）
 * @param string $name     表单字段名
 * @param string $pair     配对编号
 * @param string $cls      CSS class
 * @param string $onchange onchange 处理代码。默认是纯前端过滤（结果在新窗口计算的
 *                         页面）；结果由服务端渲染的页面（mitdata.php）应传一个
 *                         带 ?class= 的跳转，让服务端重建物种列表与结果表
 */
function cnido_class_select($classes, $selected, $name, $pair = '1', $cls = 'form-select',
                            $onchange = 'cnidoFilterPair(this);')
{
    $classes = array_values(array_filter($classes, 'strlen'));
    if ($selected !== '' && !in_array($selected, $classes, true)) { array_unshift($classes, $selected); }
    $h = '<select name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '"'
       . ' class="' . htmlspecialchars($cls, ENT_QUOTES, 'UTF-8') . '"'
       . ' data-cnido-class="' . htmlspecialchars($pair, ENT_QUOTES, 'UTF-8') . '"'
       /* $onchange 是开发者写死的处理器，不是用户输入，原样输出（转义会把
          JS 里的引号变成 &#039;，虽然浏览器会还原，但没必要） */
       . ' onchange="' . $onchange . '">' . "\n";
    foreach ($classes as $c) {
        $h .= '<option value="' . htmlspecialchars($c, ENT_QUOTES, 'UTF-8') . '"'
            . ($c === $selected ? ' selected="selected"' : '') . '>'
            . htmlspecialchars($c, ENT_QUOTES, 'UTF-8') . '</option>' . "\n";
    }
    return $h . '</select>';
}

/**
 * 渲染物种下拉框：每个 option 带 data-class，供前端按类群过滤，
 * 服务端渲染保证禁用 JS 时下拉框也不是空的。
 */
function cnido_species_select($list, $classMap, $selected, $name = 'species', $pair = '1', $cls = 'form-select')
{
    $h = '<select name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '"'
       . ' class="' . htmlspecialchars($cls, ENT_QUOTES, 'UTF-8') . '"'
       . ' data-cnido-species="' . htmlspecialchars($pair, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    $seen = array();
    foreach ($list as $sp) {
        $sp = (string)$sp;
        if ($sp === '' || isset($seen[$sp])) { continue; }
        $seen[$sp] = true;
        $c = isset($classMap[$sp]) ? $classMap[$sp] : '';
        /* option 的 value 是调用方传进来的物种标识，提交回去查数据用；显示给读者的是
           经 cnido_latin() 转换后的拉丁学名。调用方（gene_family / TE / mitdata 等）
           传的本来就是学名，所以实际渲染出来 value 与显示文字逐字相同 —— 不是
           「value 是表前缀、显示是学名」。 */
        $h .= '<option value="' . htmlspecialchars($sp, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-class="' . htmlspecialchars($c, ENT_QUOTES, 'UTF-8') . '"'
            . ($sp === (string)$selected ? ' selected="selected"' : '') . '>'
            . htmlspecialchars(cnido_latin($sp), ENT_QUOTES, 'UTF-8') . '</option>' . "\n";
    }
    // 深链给的物种不在候选里：补一项并标出，避免下拉框空白、与服务器状态不一致
    if ($selected !== '' && !isset($seen[(string)$selected])) {
        $h .= '<option value="' . htmlspecialchars($selected, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-class="" selected="selected">'
            . htmlspecialchars(cnido_latin($selected), ENT_QUOTES, 'UTF-8') . ' (unavailable)</option>' . "\n";
    }
    return $h . '</select>';
}

/* KEGG 的 Map ID（ko#####）分两类，入口不同，坏的方式也不只 404 一种：
   · 真·通路图（ko00010 糖酵解、ko04072 磷脂酶 D 信号…）在 kegg.jp/pathway/ 下正常；
   · BRITE 层次表（ko02000 Transporters、ko03036 Chromosome and Associated Proteins、
     ko02022 Two-component system…）在 /pathway/ 下只回一句 27 字节的
     "map not found. (No = 02022)"，在 /entry/ 下回一张 527 字节的
     "No such data was found." 空壳 —— **两种的状态码都是 200**，所以只看状态码会
     漏掉，正解在 kegg.jp/brite/ 下。
   这份名单是实测出来的，不是照号段猜的：把站内 148 张 <ABBR>_KEGG 加 mag_kegg_terms
   里所有不同的 Pathway_ID 并起来共 406 个（2026-09-29），逐个请求 kegg.jp/pathway/<id>
   看返回里有没有 "map not found"，判出 54 个 BRITE、352 个通路图。名单对「站内出现过
   的值」完备；表里新出现 ko 号时重跑一遍这个分类即可（406 个请求，约一分钟）。
   返回的是入口前缀，调用方自行 urlencode 并拼上 ID。 */
function cnido_kegg_map_url($mapid)
{
    static $brite = null;
    if ($brite === null) {
        $brite = array_flip(array(
            'ko00194','ko00199','ko00535','ko00536','ko00537','ko01001',
            'ko01002','ko01003','ko01004','ko01005','ko01006','ko01007',
            'ko01008','ko01009','ko01011','ko01504','ko02000','ko02022',
            'ko02035','ko02042','ko02044','ko02048','ko03000','ko03009',
            'ko03011','ko03012','ko03016','ko03019','ko03021','ko03029',
            'ko03032','ko03036','ko03037','ko03041','ko03051','ko03110',
            'ko03200','ko03210','ko03310','ko03400','ko04030','ko04031',
            'ko04040','ko04050','ko04052','ko04054','ko04090','ko04091',
            'ko04121','ko04131','ko04147','ko04515','ko04812','ko04990',
        ));
    }
    $mapid = (string)$mapid;
    return (isset($brite[$mapid]) ? 'https://www.kegg.jp/brite/' : 'https://www.kegg.jp/pathway/')
         . urlencode($mapid);
}
