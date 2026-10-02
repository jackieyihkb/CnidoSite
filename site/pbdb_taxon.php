<?php
/* =====================================================================
 * pbdb_taxon.php —— 站内「Taxon 卡片」
 *
 * 为什么需要这个页面
 * -----------------
 * paleobiology.php 表格里 58 条链接原先直接指向 PBDB 的 classic 路由
 * basicTaxonInfo / checkTaxonInfo。2026-09-26 起这两个路由对「不带 Referer
 * 的请求」一律回 403（同一个 URL 随便带个 Referer 就是 200，与来源站无关，
 * 也与 taxon_no=txn: 前缀无关）。页面上的属性已经按那次发现改成
 * rel="noopener" referrerpolicy="origin"，但用户侧只要浏览器、扩展或代理
 * 把 Referer 抹掉、或者本地网络到 paleobiodb.org 不通，链接依然打不开 ——
 * 这两种情况我们无法从服务器端修好。
 *
 * 所以改成：链接指向本站的这张卡片，由**我们的服务器**去取数，再在站内渲染。
 * PBDB 的 data1.2 数据 API **不需要 Referer**（实测无 Referer 也 200，约 1 秒
 * 冷启动，主要是 TLS 握手），于是取数这一步不再受浏览器 Referer 策略影响；
 * 卡片数据全部落在我们自己的 HTML 里，即使 PBDB 临时不通也照样能看。
 * 卡片底部再给一个「View on PBDB」出口，把想看完整分类树 / 采集记录的人送出去。
 *
 * 取数方式
 * ------
 * 本机 PHP 没有 curl 扩展，但 openssl 是静态编译进去的，allow_url_fopen=1，
 * 所以用 file_get_contents + stream context 走 HTTPS（本项目里这是唯一一处
 * 对外发起 HTTP 请求的代码，这里把约定写死为：带可识别的 User-Agent、12 秒
 * 超时、校验对端证书）。
 *
 * 字段口径（照抄 PBDB 自己的定义，别改写）
 * ------------------------------------
 *   noc  化石产出条数，**含该分类单元的全部子单元**（Cnidaria 有 7 万条正是
 *        因为把水母、珊瑚全算了进去），所以标签里必须写清「or any of its subtaxa」
 *   siz  数据库里属于该单元的分类单元数，**含它自己**；exs 是其中现生的数量
 *   fea/fla  首次出现的**早限 / 晚限**（Ma），tei 是早限所在的时间区间名
 *   lea/lla  最后出现的早限 / 晚限（Ma），tli 是晚限所在的时间区间名
 *   att  学名的命名引证，如 (Hatschek 1888)；与 aut/pby（**参考文献**的作者和
 *        年份，即 rid 指向的那篇）不是一回事，两者不要混
 *   ext  1 = 现生，0 = 已灭绝；rnk 阶元；prl/par 父单元名 / 父单元 id
 *   0 Ma = 现在。区间一律按「早限–晚限」写，与地质学惯用的老→新方向一致。
 *
 * 缓存
 * ---
 * 走 includes/cache.php 的 cnido_cache_path()，落在站点 tmp/（777，CLI 与
 * www-data 唯一共用的一份；见该文件里的说明）。成功记录 7 天；明确的
 * 「查无此号」（PBDB 回 404）负缓存 1 小时，免得一个不存在的 id 被反复请求时
 * 每次都去敲 PBDB。网络故障（连不上 / 超时 / 非 JSON）一律**不缓存**，并且
 * 如果有过期但可用的旧记录就拿旧的顶上 —— 数据陈旧好过白屏。
 * 写盘用「临时文件 + rename」，避免并发下读到写了一半的文件。
 *
 * 安全
 * ---
 * 只接受 ^txn:?[0-9]{1,12}$，取出纯整数后拼进 PBDB 的 URL，所以不存在 SSRF：
 * 请求目标永远只有 paleobiodb.org 一个。非法的 id 直接 400，不发起任何请求。
 * ===================================================================== */

require_once __DIR__ . '/includes/cache.php';

$PBDB_API  = 'https://paleobiodb.org/data1.2/taxa/single.json';
$PBDB_SHOW = 'attr,app,size,parent,refattr';
/* 出口链接：classic 的 basicTaxonInfo 对 phylum / subclass / species 三种阶元
 * 都返回 200（带 Referer 时），所以卡片里只需要这一种路由。 */
$PBDB_PAGE = 'https://paleobiodb.org/classic/basicTaxonInfo';

$TTL_FRESH = 7 * 24 * 3600;   /* 成功记录 7 天 */
$TTL_NEG   = 3600;            /* 查无此号负缓存 1 小时 */

/* ---------------------------------------------------------------------
 * 取数：返回 array(状态码, 响应体, 传输层错误)
 * 状态码 0 表示根本没连上（DNS / 超时 / TLS / 网络不可达）。
 * ------------------------------------------------------------------- */
if (!function_exists('cnido_pbdb_http')) {
    function cnido_pbdb_http($url)
    {
        $ctx = stream_context_create(array(
            'http' => array(
                'method'        => 'GET',
                'timeout'       => 12,          /* 含连接与读取 */
                /* 4xx/5xx 也要拿到 body：PBDB 把「Unknown taxon id」这类
                 * 明确的答复放在 JSON body 的 errors 里，光看状态码会丢掉它。 */
                'ignore_errors' => true,
                'header'        => "User-Agent: CnidoSite/1.0 (+https://cnidosite.org/)\r\n"
                                 . "Accept: application/json\r\n",
            ),
            'ssl' => array(
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ),
        ));

        $body = @file_get_contents($url, false, $ctx);

        /* 连不上就直接返回，不去读 $http_response_header —— 它在上一次调用的
         * 同一作用域里会留着旧值，会把失败误判成成功。 */
        if ($body === false) {
            return array(0, '', 'transport');
        }

        $code = 0;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $h) {
                if (preg_match('~^HTTP/\S+\s+(\d{3})~', $h, $m)) {
                    $code = (int)$m[1];   /* 有重定向时取最后一个 */
                }
            }
        }
        return array($code, $body, '');
    }
}

/* 把 PBDB 的 Ma 数值原样转成文本。
 * 这里**不能**用 number_format($v, 3)：PBDB 的界值是标准 ICS 年代，小数位比
 * 三位多（例如最后出现的晚限 0.0117 Ma），固定三位会四舍五入成 0.012，把
 * 数据说错。PHP 的 (string) 转换用的是 precision=14，会给出去掉多余零的紧凑
 * 写法（1000 就是 "1000"、538.8 就是 "538.8"、0 就是 "0"），正合适。 */
if (!function_exists('cnido_pbdb_ma')) {
    function cnido_pbdb_ma($v)
    {
        if ($v === null || $v === '' || !is_numeric($v)) { return ''; }
        $s = (string)(float)$v;
        /* 数值大到 PHP 改用科学计数法时兜一下底（Ma 不可能这么大，防御性写法） */
        if (stripos($s, 'e') !== false) { $s = rtrim(rtrim(sprintf('%.4f', (float)$v), '0'), '.'); }
        return ($s === '' || $s === '-' || $s === '.') ? '0' : $s;
    }
}

/* 早限–晚限；两端相同时只写一个 */
if (!function_exists('cnido_pbdb_range')) {
    function cnido_pbdb_range($early, $late)
    {
        $a = cnido_pbdb_ma($early);
        $b = cnido_pbdb_ma($late);
        if ($a === '' && $b === '') { return ''; }
        if ($a === '') { return $b . '&nbsp;Ma'; }
        if ($b === '' || $a === $b) { return $a . '&nbsp;Ma'; }
        return $a . '&ndash;' . $b . '&nbsp;Ma';
    }
}

/* =====================================================================
 * 地层时间轴：把首现 / 末现的四个界线值画成一条按比例的时间条。
 *
 * 界线数值用 ICS 的（PBDB 也是这套），**只用来画背景刻度**，不参与任何
 * 字段口径 —— 卡片上的数字全部来自 PBDB 的记录本身。
 * 每条写成 array(名称, 底界 Ma(老), 顶界 Ma(新))，底界 > 顶界。
 * 分三档，画的时候按这个单元的跨度自动挑一档，粗的一档画在上面作背景。
 * =================================================================== */
if (!function_exists('cnido_tx_tscale')) {
    function cnido_tx_tscale($level)
    {
        static $t = array(
            'eras' => array(
                array('Hadean',       4600,  4000),
                array('Archean',      4000,  2500),
                array('Proterozoic',  2500,  538.8),
                array('Paleozoic',    538.8, 251.9),
                array('Mesozoic',     251.9, 66),
                array('Cenozoic',     66,    0),
            ),
            'periods' => array(
                array('Tonian',        1000,  720),
                array('Cryogenian',    720,   635),
                array('Ediacaran',     635,   538.8),
                array('Cambrian',      538.8, 485.4),
                array('Ordovician',    485.4, 443.8),
                array('Silurian',      443.8, 419.2),
                array('Devonian',      419.2, 358.9),
                array('Carboniferous', 358.9, 298.9),
                array('Permian',       298.9, 251.9),
                array('Triassic',      251.9, 201.4),
                array('Jurassic',      201.4, 145),
                array('Cretaceous',    145,   66),
                array('Paleogene',     66,    23.03),
                array('Neogene',       23.03, 2.58),
                array('Quaternary',    2.58,  0),
            ),
            'epochs' => array(
                array('Paleocene',    66,     56),
                array('Eocene',       56,     33.9),
                array('Oligocene',    33.9,   23.03),
                array('Miocene',      23.03,  5.333),
                array('Pliocene',     5.333,  2.58),
                array('Pleistocene',  2.58,   0.0117),
                array('Holocene',     0.0117, 0),
            ),
        );
        return isset($t[$level]) ? $t[$level] : array();
    }
}

/* 把一档条带裁到 [0, $axis] 这根轴上：比轴的左端还老的整条丢掉，
 * 与左端相交的那条截短到轴端。 */
if (!function_exists('cnido_tx_clip')) {
    function cnido_tx_clip($level, $axis)
    {
        $out = array();
        foreach (cnido_tx_tscale($level) as $b) {
            $base = (float)$b[1];
            $top  = (float)$b[2];
            if ($top >= $axis) { continue; }
            if ($base > $axis) { $base = (float)$axis; }
            $out[] = array($b[0], $base, $top);
        }
        return $out;
    }
}

/* 轴的左端取整到一个「好看」的岁数，免得出现 66 这种贴边的轴 */
if (!function_exists('cnido_tx_axis_ladder')) {
    function cnido_tx_axis_ladder($t)
    {
        $l = array(5, 10, 25, 50, 70, 100, 150, 250, 300, 500, 550, 600, 700, 1000,
                   1100, 1500, 2000, 2500, 3000, 4000, 4600);
        foreach ($l as $v) { if ($v >= $t) { return (float)$v; } }
        return ceil($t / 500) * 500;
    }
}

/* 这一档里哪条界线把 $t 包在中间 —— 用来把轴对齐到真实的地质界线，
 * 这样轴最左那条永远是完整的一条，不会出现画了一半的条带。 */
if (!function_exists('cnido_tx_band_base')) {
    function cnido_tx_band_base($level, $t)
    {
        foreach (cnido_tx_tscale($level) as $b) {
            if ($t <= (float)$b[1] && $t > (float)$b[2]) { return (float)$b[1]; }
        }
        return null;
    }
}

/* 挑一档来画：从细到粗，第一条「能盖住这根轴、条数又不超过 14 条」的。
 * 14 条是为了窄条上还能留下名字；盖不住就说明这一档根本铺不满这根轴
 * （比如用「世」去画 100 Ma 的属，66 Ma 以前没有世可画）。 */
if (!function_exists('cnido_tx_pick')) {
    function cnido_tx_pick($t, $axis)
    {
        foreach (array('epochs', 'periods', 'eras') as $lv) {
            $b = cnido_tx_clip($lv, $axis);
            if (!$b || count($b) > 14) { continue; }
            $oldest = 0;
            foreach ($b as $x) { if ($x[1] > $oldest) { $oldest = $x[1]; } }
            if ($oldest >= $axis * 0.85) { return $lv; }
        }
        return 'eras';
    }
}

/* 时间条的 HTML。四个界线一个都没有就返回空串，让调用方不画这一块。
 * 条带的几何全部在服务端算成百分比，页面里不放 JS。 */
if (!function_exists('cnido_tx_chart')) {
    function cnido_tx_chart($fea, $fla, $lea, $lla, $tei, $tli, $ext)
    {
        $e   = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
        $num = function ($x) { return ($x === null || $x === '' || !is_numeric($x)) ? null : (float)$x; };
        $fea = $num($fea); $fla = $num($fla); $lea = $num($lea); $lla = $num($lla);

        $old = null;
        foreach (array($fea, $fla, $lea, $lla) as $x) {
            if ($x !== null && ($old === null || $x > $old)) { $old = $x; }
        }
        if ($old === null || $old <= 0) { return ''; }

        $axis = cnido_tx_axis_ladder($old);
        $lvl  = cnido_tx_pick($old, $axis);
        $snap = cnido_tx_band_base($lvl, $old);
        if ($lvl !== 'eras' && $snap !== null && $snap <= $old * 2) { $axis = $snap; }

        /* 一律「左老右新」，右端是 0 Ma（现在） */
        $at = function ($ma) use ($axis) {
            $p = ($axis - $ma) / $axis * 100.0;
            return ($p < 0) ? 0.0 : (($p > 100) ? 100.0 : $p);
        };
        $pc = function ($ma) use ($at) { return rtrim(rtrim(sprintf('%.3f', $at($ma)), '0'), '.'); };

        $html = '<div class="tx-scale">' . "\n";

        /* --- 背景条带：粗档在上，细档在下（挨着时间条） --- */
        $up   = array('epochs' => 'periods', 'periods' => 'eras', 'eras' => '');
        $rows = array();
        if ($up[$lvl] !== '') {
            $b = cnido_tx_clip($up[$lvl], $axis);
            if (count($b) >= 2) { $rows[] = $b; }
        }
        $rows[] = cnido_tx_clip($lvl, $axis);

        foreach ($rows as $bands) {
            $html .= '<div class="tx-bands">' . "\n";
            $i = 0;
            foreach ($bands as $b) {
                $l = $at($b[1]);
                $w = $at($b[2]) - $at($b[1]);
                /* 名字按约 6.6px/字符 估宽，放不下就不写名字（条带本身还在）。
                   15.0 = 卡片铺满 #column（1590px）后时间条的真实像素宽 1540 ÷ 100，
                   留约 3% 余量。 */
                $fit = ((strlen($b[0]) * 6.6 + 16) <= $w * 15.0);
                $html .= '<div class="tx-band tx-band-' . (($i % 2) ? 'b' : 'a') . '" style="left:' . $pc($b[1]) . '%;width:' . rtrim(rtrim(sprintf('%.3f', $w), '0'), '.') . '%">';
                if ($fit) { $html .= '<span>' . $e($b[0]) . '</span>'; }
                $html .= '</div>' . "\n";
                $i++;
            }
            $html .= '</div>' . "\n";
        }

        /* --- 刻度：细档每条界线的岁数，离得太近的跳过。
         * 右端的 0 Ma（现在）一定要写，挤不下时让前一个刻度让位。 --- */
        $out  = array();
        $last = -100.0;
        foreach (cnido_tx_clip($lvl, $axis) as $b) {
            $val = (float)$b[1];
            $p   = $at($val);
            if (($p - $last) < 6.0) { continue; }
            $out[] = array($val, $p);
            $last  = $p;
        }
        while ($out && (100.0 - $out[count($out) - 1][1]) < 6.0) { array_pop($out); }
        $out[] = array(0, 100.0);

        $html .= '<div class="tx-ticks">' . "\n";
        foreach ($out as $t) {
            $cls = 'tx-tick';
            $pos = '';
            if ($t[1] < 0.5)       { $cls .= ' tx-tick-first'; }
            elseif ($t[1] > 99.5)  { $cls .= ' tx-tick-last'; }   /* 靠右对齐，不给 left */
            else                   { $pos = ' style="left:' . $pc($t[0]) . '%"'; }
            $html .= '<div class="' . $cls . '"' . $pos . '>'
                   . $e(($t[0] == 0) ? '0' : cnido_pbdb_ma($t[0])) . '</div>' . "\n";
        }
        $html .= '</div>' . "\n";

        /* --- 时间条本体：深色 = 有化石确认的区间，浅色 = 界线本身不确定的那一段 --- */
        $segs = array();
        if ($fea !== null && $fla !== null && $fla < $fea) { $segs[] = array('unc', $fea, $fla); }
        $cs = ($fla !== null) ? $fla : $fea;
        $ce = ($lea !== null) ? $lea : $fla;
        if ($cs !== null && $ce !== null && $ce < $cs) { $segs[] = array('core', $cs, $ce); }
        if ($lea !== null && $lla !== null && $lla < $lea) { $segs[] = array('unc', $lea, $lla); }

        $html .= '<div class="tx-track">' . "\n";
        /* 灭绝的：末现之后到「现在」这一段没有任何记录，先铺斜纹，
         * 后面画的色块会盖在它上面。起点用末现最年轻的界线（lla），
         * 因为那才是「最晚可能还活着」的时刻。
         * 只在 PBDB 明说这个单元已灭绝时画 —— 现存的单元 lla 常常是
         * 0.0117（全新世底界）而不是 0，照着画会在右端多出一小块斜纹，
         * 那等于把「还活着」说成「已经没了」。 */
        $end = ($lla !== null) ? $lla : $lea;
        if ($end !== null && $end > 0 && !($ext !== null && (string)$ext === '1')) {
            $html .= '<div class="tx-gone" style="left:' . $pc($end) . '%;width:'
                   . rtrim(rtrim(sprintf('%.3f', 100.0 - $at($end)), '0'), '.') . '%"></div>' . "\n";
        }
        foreach ($segs as $s) {
            $l = $at($s[1]);
            $w = $at($s[2]) - $at($s[1]);
            if ($w <= 0.05) { $w = 0.05; }
            $html .= '<div class="tx-seg tx-seg-' . $s[0] . '" style="left:' . rtrim(rtrim(sprintf('%.3f', $l), '0'), '.') . '%;width:' . rtrim(rtrim(sprintf('%.3f', $w), '0'), '.') . '%"></div>' . "\n";
        }
        if ($cs !== null) { $html .= '<div class="tx-mk" style="left:' . $pc($cs) . '%"></div>' . "\n"; }
        if ($ce !== null) { $html .= '<div class="tx-mk" style="left:' . $pc($ce) . '%"></div>' . "\n"; }
        $html .= '</div>' . "\n";

        $html .= '</div>' . "\n";   /* /tx-scale */

        /* --- 两端各一句话，颜色和时间条上的标记对应 --- */
        $fa = cnido_pbdb_range($fea, $fla);
        $la = cnido_pbdb_range($lea, $lla);
        $html .= '<div class="tx-ends">' . "\n";
        $html .= '<div class="tx-end"><i class="tx-end-mk tx-end-mk-l"></i><b>First appearance</b>';
        $html .= ($tei !== null && $tei !== '' ? ' ' . $e($tei) : '');
        $html .= ($fa !== '' ? ' &middot; ' . $fa : '') . '<span>oldest fossil in the record</span></div>' . "\n";
        $html .= '<div class="tx-end tx-end-r"><i class="tx-end-mk ' . (($ext !== null && (string)$ext === '1') ? 'tx-end-mk-l' : 'tx-end-mk-x') . '"></i><b>Last appearance</b>';
        $html .= ($tli !== null && $tli !== '' ? ' ' . $e($tli) : '');
        $html .= ($la !== '' ? ' &middot; ' . $la : '') . '<span>' . (($ext !== null && (string)$ext === '1') ? 'still living &mdash; 0 Ma is today' : 'youngest fossil in the record') . '</span></div>' . "\n";
        $html .= '</div>' . "\n";

        $html .= '<div class="tx-legend">'
               . '<span><i class="tx-lg-core"></i>range confirmed by fossils</span>'
               . '<span><i class="tx-lg-unc"></i>boundary age uncertain</span>'
               . '</div>' . "\n";

        return $html;
    }
}

/* 原子写：临时文件 + rename，并发下不会读到写了一半的文件。
 * 必须定义在第一次调用之前 —— 它被包在 if (!function_exists()) 里，
 * 属于条件声明，PHP 不会像顶层函数那样提前挂上，放到后面调用就是致命错误。 */
if (!function_exists('cnido_cache_write')) {
    function cnido_cache_write($path, $payload)
    {
        $tmp = $path . '.' . getmypid() . '.tmp';
        $ok  = @file_put_contents($tmp, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        if ($ok === false) { return false; }
        if (!@rename($tmp, $path)) { @unlink($tmp); return false; }
        return true;
    }
}

/* ---------------------------------------------------------------------
 * 1. 校验 id
 * ------------------------------------------------------------------- */
$raw    = isset($_GET['id']) ? (string)$_GET['id'] : '';
$id     = trim(preg_replace('/^\s*txn:\s*/i', '', trim($raw)));
$id_ok  = ($id !== '' && preg_match('/^[0-9]{1,12}$/', $id) === 1);
$given  = ($raw !== '');

$rec      = null;   /* array：渲染用的记录 */
$fetched  = 0;      /* 该记录的取数时间戳 */
$err_kind = '';     /* 'unknown' = PBDB 明确说没这个号；'network' = 没问出来 */
$err_msg  = '';

/* ---------------------------------------------------------------------
 * 2. 查缓存 / 取数
 * ------------------------------------------------------------------- */
if ($id_ok) {
    $cache_f = cnido_cache_path('pbdb_taxon_' . $id . '.json');

    $fresh = null;    /* 未过期的缓存内容 */
    $stale = null;    /* 已过期的缓存内容，仅在取数失败时兜底 */
    if ($cache_f !== '' && is_file($cache_f)) {
        $j = json_decode((string)@file_get_contents($cache_f), true);
        if (is_array($j) && isset($j['kind'])) {
            $age = time() - (int)filemtime($cache_f);
            if ($j['kind'] === 'ok'       && $age <= $TTL_FRESH) { $fresh = $j; }
            elseif ($j['kind'] === 'unknown' && $age <= $TTL_NEG) { $fresh = $j; }
            else                                                { $stale = $j; }
        }
    }

    if (is_array($fresh)) {
        if ($fresh['kind'] === 'ok' && isset($fresh['rec']) && is_array($fresh['rec'])) {
            $rec     = $fresh['rec'];
            $fetched = isset($fresh['at']) ? (int)$fresh['at'] : 0;
        } else {
            $err_kind = 'unknown';
            $err_msg  = isset($fresh['msg']) ? (string)$fresh['msg'] : '';
        }
    } else {
        /* 缓存没有或已过期 —— 去 PBDB 取 */
        list($code, $body, $terr) = cnido_pbdb_http($PBDB_API . '?id=' . $id . '&show=' . $PBDB_SHOW);

        if ($terr !== '') {
            $err_kind = 'network';
            $err_msg  = 'The Paleobiology Database did not answer in time.';
        } elseif ($code === 404) {
            /* PBDB 明确答复：没有这个号。把它的原话取出来给用户看。 */
            $err_kind = 'unknown';
            $je = json_decode($body, true);
            if (is_array($je) && !empty($je['errors']) && is_array($je['errors'])) {
                $err_msg = implode(' ', array_map('strval', $je['errors']));
            }
            if ($err_msg === '') { $err_msg = "No taxon with id $id exists in the Paleobiology Database."; }
            if ($cache_f !== '') {
                cnido_cache_write($cache_f, array('kind' => 'unknown', 'at' => time(), 'msg' => $err_msg));
            }
        } elseif ($code !== 200) {
            $err_kind = 'network';
            $err_msg  = 'The Paleobiology Database answered with HTTP ' . $code . '.';
        } else {
            $j = json_decode($body, true);
            if (!is_array($j) || empty($j['records'][0]) || !is_array($j['records'][0])
                || !isset($j['records'][0]['nam'])) {
                /* 200 但内容不是我们能认的记录：当故障处理，不写缓存 */
                $err_kind = 'network';
                $err_msg  = 'The Paleobiology Database returned a record in an unexpected format.';
            } else {
                $rec     = $j['records'][0];
                $fetched = time();
                if ($cache_f !== '') {
                    cnido_cache_write($cache_f, array('kind' => 'ok', 'at' => $fetched, 'rec' => $rec));
                }
            }
        }

        /* 取数失败但有旧记录可用：拿旧的顶上，并在页面上注明数据时间 */
        if ($rec === null && $err_kind !== '' && is_array($stale)
            && $stale['kind'] === 'ok' && isset($stale['rec']) && is_array($stale['rec'])) {
            $rec      = $stale['rec'];
            $fetched  = isset($stale['at']) ? (int)$stale['at'] : 0;
            $err_kind = '';
            $err_msg  = '';
        }
    }
}

/* ---------------------------------------------------------------------
 * 3. 这个分类单元在本站 paleobiology 表里的位置
 *
 *    该表把「门 / 纲 / 目 / 科 / 属 / 种」六栏连同它们的 PBDB 链接一起存着，
 *    所以只要命中一行，整条谱系就到手了 —— 不用再联网，也不会因为 PBDB
 *    挂掉就没有。命中在哪一栏就画到哪一栏为止：再往下的是它的子单元，
 *    不是它的上级，画进去会说错话。
 *
 *    url 里的号既可能是 txn:6114 也可能是 150311&is_real_user=1，所以用
 *    REGEXP 锚住「taxon_no=(txn:)?<id>」后面紧跟 & 或串尾，免得 6114 命中
 *    61141。$id 已经过纯数字校验，拼进 SQL 是安全的。
 * ------------------------------------------------------------------- */
$local_path    = array();   /* array(array(名称, 阶元, PBDB id), …) 门 → 命中的那一级 */
$local_hits    = 0;         /* 表里落在该单元内的物种行数 */
$local_rank    = '';        /* 命中的那一级叫什么 */
$local_species = '';        /* 命中 Species 栏时，那一行的物种名 */
if ($rec !== null && $id_ok) {
    $conn = @new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
    if ($conn && !$conn->connect_error) {
        $cols = array(
            1 => array('Phylum',  'url1', 'phylum'),
            2 => array('Class',   'url2', 'class'),
            3 => array('Order1',  'url3', 'order'),
            4 => array('Family',  'url4', 'family'),
            5 => array('Genus',   'url5', 'genus'),
            6 => array('species', 'url6', 'species'),
        );
        $re  = "taxon_no=(txn:)?" . $id . "(&|$)";
        $hit = 0;
        $row = null;
        foreach ($cols as $i => $c) {
            $q = "SELECT * FROM paleobiology WHERE " . $c[1] . " REGEXP '" . $re . "' LIMIT 1";
            if (($r = @$conn->query($q)) && ($x = $r->fetch_assoc())) { $hit = $i; $row = $x; break; }
        }
        if ($hit > 0) {
            $local_rank = $cols[$hit][2];
            $q2 = "SELECT COUNT(*) FROM paleobiology WHERE " . $cols[$hit][1] . " REGEXP '" . $re . "'";
            if (($r2 = @$conn->query($q2)) && ($x2 = $r2->fetch_row())) { $local_hits = (int)$x2[0]; }
            for ($i = 1; $i <= $hit; $i++) {
                $nm = trim((string)$row[$cols[$i][0]]);
                if ($nm === '' || $nm === '-') { continue; }
                $cid = '';
                if (preg_match('/taxon_no=(?:txn:)?([0-9]{1,12})/', (string)$row[$cols[$i][1]], $m)) { $cid = $m[1]; }
                $local_path[] = array($nm, $cols[$i][2], $cid);
            }
            if ($hit === 6) { $local_species = trim((string)$row['species']); }
        }
        $conn->close();
    }
}

/* ---------------------------------------------------------------------
 * 4. 准备渲染用的值
 * ------------------------------------------------------------------- */
/* 转义。模板里一律用 $h(...)，不再逐处写 htmlspecialchars(..., ENT_QUOTES) */
$h = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };

$v = function ($k) use ($rec) {
    return ($rec !== null && isset($rec[$k]) && $rec[$k] !== '') ? $rec[$k] : null;
};

$nam  = $v('nam');
$att  = $v('att');
$rnk  = $v('rnk');
$prl  = $v('prl');
$par  = $v('par');
$ext  = $v('ext');
$noc  = $v('noc');
$siz  = $v('siz');
$exs  = $v('exs');
$tei  = $v('tei');
$tli  = $v('tli');
$aut  = $v('aut');
$pby  = $v('pby');
$rid  = $v('rid');

/* 阶元名：PBDB 按 id 查时给的是字面名（phylum / subclass / species），
 * 但按 name= 查会回数字编码。本页只按 id 查，仍然挡一道，免得将来有人
 * 改成按名查时页面上出现「阶元 3」。 */
$rnk_text = ($rnk !== null && !is_numeric($rnk)) ? ucfirst(strtolower((string)$rnk)) : '';

$ext_text = '';
if ($ext !== null) {
    $ext_text = ((string)$ext === '1') ? 'Extant' : 'Extinct';
}

/* 父单元 id：去掉 txn: 前缀，能解析才给链接 */
$par_id = '';
if ($par !== null && preg_match('/^txn:?([0-9]{1,12})$/', (string)$par, $m)) { $par_id = $m[1]; }

/* 出口链接：一律用校验过的整数 id 重建，不用 $_GET 里的原串 */
$pbdb_href = ($id_ok ? $PBDB_PAGE . '?taxon_no=txn:' . $id : 'https://paleobiodb.org/');
$out_a     = 'target="_blank" rel="noopener" referrerpolicy="origin"';

/* PBDB 的出错信息原样展示，但要转义 */
$err_msg_html = htmlspecialchars($err_msg, ENT_QUOTES, 'UTF-8');

/* 首现 / 末现的「早限–晚限」文本（数字条和统计块共用），以及时间条的 HTML */
$fa_txt     = cnido_pbdb_range($v('fea'), $v('fla'));
$la_txt     = cnido_pbdb_range($v('lea'), $v('lla'));
$chart_html = ($rec !== null)
    ? cnido_tx_chart($v('fea'), $v('fla'), $v('lea'), $v('lla'), $tei, $tli, $ext)
    : '';

/* 群内组成：PBDB 说这个单元里有 $siz 个分类单元，其中 $exs 个现存 */
$siz_n   = ($siz !== null && is_numeric($siz)) ? (float)$siz : null;
$exs_n   = ($exs !== null && is_numeric($exs)) ? (float)$exs : null;
$exs_pct = ($siz_n !== null && $siz_n > 0 && $exs_n !== null) ? ($exs_n / $siz_n * 100.0) : null;
$pct_txt = function ($p) { return rtrim(rtrim(sprintf('%.2f', $p), '0'), '.'); };

$page_title = ($nam !== null)
    ? ((string)$nam . ' &mdash; Paleobiology Database record')
    : 'Paleobiology Database record';

/* 状态码：id 非法是客户端的错，回 400；其余（含取数失败）都正常回 200，
 * 因为页面本身渲染成功了，只是内容取不到 —— 5xx 容易被代理换成别的页面。 */
if ($given && !$id_ok) {
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: text/html; charset=utf-8');
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="renderer" content="webkit">
<title><?= $page_title ?> - CnidoSite</title>
<meta name="keywords" content="Cnidaria, paleobiology, fossil record, PBDB, <?= $nam !== null ? htmlspecialchars((string)$nam, ENT_QUOTES, 'UTF-8') : 'taxon' ?>" />
<meta name="description" content="<?= $nam !== null ? htmlspecialchars((string)$nam, ENT_QUOTES, 'UTF-8') . ' — ' : '' ?>fossil record summary from the Paleobiology Database: stratigraphic range, occurrences and classification." />
<?php /* noindex —— 2026-09-27 加。
   每一行数据的正文都来自 Paleobiology Database，本页只是把它重排成站内样式，
   换句话说这里的每个 id 都是 paleobiodb.org 对应页面的近似重复内容。让搜索引擎
   收录一批「同一份数据的另一份排版」没有意义：既是 thin content，又会让爬虫在
   paleobiology.php 的 2400 多条链接上跟着爬一遍。
   follow 而不是 nofollow：卡片上的出口只有两处 —— 指向 PBDB 原文的引证链接，
   和回本站 paleobiology.php 的详情链接 —— 都该被继续跟，只是这一页自己不必被收录。
   注意本页不是靠 robots.txt 挡的：robots.txt 里那几个 Disallow 只针对几个 AI 爬虫
   （见 robots.txt 与根 .htaccess 的注释），且被 Disallow 的 URL 读不到这条 meta，
   两者管的是不同的事。 */
?>
<meta name="robots" content="noindex, follow" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<script LANGUAGE="JavaScript" src="js/func.js?v=1790644208" type="text/javascript"></script>

<style>
<?php /* Taxon 卡片。配色沿用 paleobiology.php 那一套：#059669 / #047857 / #f0fdf4 /
   #e2e8f0 / #475569 —— 两页放在一起看是一套东西。
   标签 / 取值用 flex 行，不用 table —— 全站的 table 样式只挂在 table.gridtable
   上，而那条规则把 table-layout 钉成 fixed（列宽十等分），不适合 label 窄、
   value 宽的场合。 */ ?>
<?php /* 卡片铺满 #column 的内容宽度（1590px，与 paleobiology.php 的 table.gridtable 同宽）。
   原先把宽度钉在 980px，右边空着 610px。 */ ?>
.tx-wrap { width: 100%; }

.tx-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid #e2e8f0;
    overflow: hidden;
    margin: 18px 0 24px 0;
}

<?php /* 标题区也是白卡（不用深色横幅）：站内的信息卡都是白底，这里靠一条左侧绿边
   和极浅的绿底把标题和内容分开。 */ ?>
.tx-head {
    padding: 20px 26px 18px 21px;
    border-left: 5px solid #059669;
    border-bottom: 1px solid #d1fae5;
    background: #f0fdf4;
}

.tx-rank {
    font-size: 13px;
    font-weight: 700;
    color: #047857;
}

.tx-name {
    font-size: 32px;
    font-weight: 700;
    line-height: 1.2;
    color: #1e293b;
    margin-top: 3px;
    word-wrap: break-word;
}

.tx-name i { font-style: italic; }

.tx-att { font-size: 15px; font-weight: 400; color: #64748b; }

.tx-chips { margin-top: 11px; }

.tx-chip {
    display: inline-block;
    font-size: 12px;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: 999px;
    margin: 0 6px 4px 0;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    color: #475569;
    white-space: nowrap;
}

.tx-chip-on  { background:#f0fdf4; color: #065f46; border-color: #a7f3d0; }
.tx-chip-off { background: #fff7ed; color: #9a3412; border-color: #fed7aa; }

<?php /* 谱系面包屑：从门一路排到当前单元，点上级可以直接往上走 */ ?>
.tx-lin { margin-top: 11px; font-size: 15px; line-height: 1.75; color: #64748b; }
.tx-lin-rank { color:#64748b; font-size: 12px; }
.tx-lin-sep { color: #86efac; margin: 0 6px; }
.tx-lin-cur { color: #1e293b; font-weight: 700; font-style: italic; }
<?php /* 给 <a> 上色必须写成 a.类名：templatemo_style.css 的 a:link 是 (0,1,1)，
   单类名 (0,1,0) 会被它盖掉，链接会变成全站那个蓝。 */ ?>
a.tx-lin-a { color: #047857; text-decoration: none; border-bottom: 1px solid #a7f3d0; }
a.tx-lin-a:hover { color: #065f46; border-bottom-color:#047857; }

.tx-body { padding: 0 24px 4px 24px; }

<?php /* ---- 四个数字块 ---- */ ?>
.tx-stats { display: flex; flex-wrap: wrap; gap: 12px; margin: 18px 0 4px 0; }

.tx-stat {
    flex: 1 1 190px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 12px 14px 11px 14px;
}

.tx-stat-k {
    font-size: 13px;
    font-weight: 700;
    color: #64748b;
}

.tx-stat-v {
    font-size: 24px;
    font-weight: 700;
    line-height: 1.3;
    color: #1e293b;
    margin-top: 4px;
    font-variant-numeric: tabular-nums;
}

.tx-stat-v small { font-size: 15px; font-weight: 600; color: #64748b; }
.tx-stat-n { font-size: 12px; color:#64748b; margin-top: 3px; line-height: 1.5; }

.tx-sub {
    margin: 22px 0 10px 0;
    font-size: 15px;
    font-weight: 700;
    color: #047857;
}

.tx-sub span {
    font-weight: 400;
    font-size: 15px;
    color:#64748b;
}

<?php /* ---- 地层时间条 ---- */ ?>
.tx-scale {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
    background: #f8fafc;
}

.tx-bands { position: relative; height: 22px; border-bottom: 1px solid #e2e8f0; }
.tx-band { position: absolute; top: 0; bottom: 0; overflow: hidden; border-left: 1px solid #e2e8f0; }
.tx-band span {
    display: block;
    height: 22px;
    line-height: 22px;
    text-align: center;
    font-size: 12px;
    color:#64748b;
    white-space: nowrap;
}
.tx-band-a { background: transparent; }
.tx-band-b { background:#f1f5f9; }

<?php /* 岁数刻度。两端的两个不能居中（会跑出框外），各靠一边 */ ?>
.tx-ticks { position: relative; height: 20px; background: #ffffff; border-bottom: 1px solid #e2e8f0; }
.tx-tick {
    position: absolute;
    top: 0;
    height: 20px;
    line-height: 20px;
    font-size: 12px;
    color: #64748b;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
    -webkit-transform: translateX(-50%);
            transform: translateX(-50%);
}
.tx-tick-first { left: 0;    -webkit-transform: none; transform: none; }
.tx-tick-last  { right: 0;   -webkit-transform: none; transform: none; }

<?php /* 时间条本体：深绿 = 化石确认的区间，浅绿 = 界线本身不确定的那一段 */ ?>
.tx-track { position: relative; height: 26px; background: #ffffff; }
.tx-seg { position: absolute; top: 8px; height: 11px; }
.tx-seg-core { background:#047857; border-radius: 3px; }
.tx-seg-unc  { background: #a7f3d0; }
.tx-mk { position: absolute; top: 3px; bottom: 3px; width: 2px; margin-left: -1px; background: #065f46; }
<?php /* 灭绝的：末现之后到「现在」这一段什么都没有，用斜纹留白标出来 */ ?>
.tx-gone {
    position: absolute;
    top: 0;
    bottom: 0;
    background: repeating-linear-gradient(135deg,
                #f1f5f9 0, #f1f5f9 4px, #e2e8f0 4px, #e2e8f0 8px);
}

.tx-ends {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 10px;
}

.tx-end {
    flex: 1 1 260px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 9px 12px;
    font-size: 15px;
    color: #334155;
    line-height: 1.6;
}

.tx-end b { color: #1e293b; }
.tx-end span { display: block; font-size: 12px; color:#64748b; }
.tx-end-r { text-align: right; }

.tx-end-mk {
    display: inline-block;
    width: 9px;
    height: 9px;
    border-radius: 2px;
    margin-right: 6px;
}
.tx-end-mk-l { background: #065f46; }
.tx-end-mk-x { background: #94a3b8; }

.tx-legend { margin-top: 9px; font-size: 12px; color:#64748b; }
.tx-legend span { margin-right: 16px; white-space: nowrap; }
.tx-legend i {
    display: inline-block;
    width: 14px;
    height: 9px;
    border-radius: 2px;
    margin-right: 5px;
}
.tx-lg-core { background:#047857; }
.tx-lg-unc  { background: #a7f3d0; }

<?php /* ---- 群内组成：现存 / 非现存 的比例条 ---- */ ?>
.tx-compose { margin-top: 4px; }

.tx-compose-bar {
    display: flex;
    height: 22px;
    border-radius: 6px;
    overflow: hidden;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
}
.tx-compose-a { background:#047857; }
.tx-compose-b { background: #cbd5e1; }
<?php /* 百分比写在色带里：铺满后这条太宽，光看颜色分不出比例 */ ?>
.tx-compose-a, .tx-compose-b { overflow: hidden; }
.tx-compose-bar span {
    display: block;
    height: 22px;
    line-height: 21px;
    text-align: center;
    font-size: 12px;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}
.tx-compose-a span { color: #ffffff; }
.tx-compose-b span { color: #475569; }

.tx-compose-lab { margin-top: 8px; font-size: 15px; color: #475569; }
.tx-compose-lab span { margin-right: 18px; white-space: nowrap; }
.tx-compose-lab b { color: #1e293b; }
.tx-compose-lab i {
    display: inline-block;
    width: 9px;
    height: 9px;
    border-radius: 50%;
    margin-right: 5px;
}
.tx-dot-a { background:#047857; }
.tx-dot-b { background: #cbd5e1; }

.tx-note { display: block; font-size: 15px; color:#64748b; line-height: 1.6; margin-top: 6px; }

<?php /* ---- 标签 / 取值行 ---- */ ?>
.tx-row {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 10px 0;
    border-bottom: 1px solid #eef2f6;
}

.tx-row:last-child { border-bottom: 0; }

.tx-k {
    flex: 0 0 210px;
    font-size: 15px;
    color: #64748b;
    padding-top: 1px;
}

.tx-v {
    flex: 1 1 auto;
    min-width: 0;
    font-size: 15px;
    color: #1e293b;
    word-wrap: break-word;
}

.tx-v .tx-note {
    display: block;
    font-size: 15px;
    color:#64748b;
    margin-top: 3px;
    line-height: 1.5;
}

.tx-v a { color: #047857; text-decoration: none; border-bottom: 1px solid #a7f3d0; }
.tx-v a:hover { border-bottom-color: #047857; }

.tx-num { font-variant-numeric: tabular-nums; }

.tx-code {
    font-family: "Courier New", Courier, monospace;
    font-size: 15px;
    background: #f1f5f9;
    padding: 1px 6px;
    border-radius: 4px;
}

.tx-extant { color: #047857; font-weight: 600; }
.tx-extinct { color: #9a3412; font-weight: 600; }

<?php /* 口径说明：PBDB 的计数包含子单元这件事必须写在页面上 */ ?>
.tx-notes {
    margin: 16px 0 0 0;
    padding: 12px 14px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-left: 4px solid #a7f3d0;
    border-radius: 8px;
    font-size: 16px;
    color: #475569;
    line-height: 1.75;
}
.tx-notes b { color: #1e293b; }

.tx-foot {
    padding: 15px 24px;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    font-size: 15px;
    color: #475569;
    line-height: 1.7;
}

.tx-actions { margin: 0 0 22px 0; }

<?php /* 注意：这里的按钮选择器必须写成 a.tx-btn*，不能只写 .tx-btn*。
 * templatemo_style.css 里有一条 `a:link, a:visited { color: #1d4ed8 }`，特异性
 * (0,1,1) 高于单个类名 (0,1,0)，所以 `.tx-btn-go { color: #fff }` 会被它盖掉 ——
 * 白字变蓝字，落在绿色渐变上几乎看不见。凑平到 (0,1,1) 后靠「本 <style> 在
 * 那个样式表之后」取胜（同一份文件里 .gd-species-pick a / .guide a 也是这么写的）。 */ ?>
a.tx-btn {
    display: inline-block;
    padding: 10px 18px;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 600;
    text-decoration: none;
    margin-right: 10px;
    margin-bottom: 8px;
}

a.tx-btn-go {
    background:linear-gradient(135deg, #065f46 0%, #064e3b 100%);
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
}

a.tx-btn-go:hover { color: #ffffff; box-shadow: 0 6px 16px rgba(5, 150, 105, 0.42); }

a.tx-btn-back {
    background: white;
    color: #475569;
    border: 1px solid #cbd5e1;
}

a.tx-btn-back:hover { color: #047857; border-color:#047857; }

.tx-panel {
    background: white;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    border-left: 5px solid #f59e0b;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
    padding: 20px 24px;
    margin: 18px 0 24px 0;
    font-size: 16px;
    line-height: 1.75;
    color: #334155;
}

.tx-panel b { color: #1e293b; }

.tx-panel .tx-code {
    font-family: "Courier New", Courier, monospace;
    font-size: 15px;
    background: #f1f5f9;
    padding: 1px 6px;
    border-radius: 4px;
}

@media (max-width: 700px) {
    .tx-row { display: block; }
    .tx-k { flex: none; margin-bottom: 3px; }
    .tx-name { font-size: 24px; }
    .tx-head { padding: 16px 18px 14px 15px; }
    .tx-body { padding: 0 16px 4px 16px; }
    .tx-stat, .tx-end { flex: 1 1 100%; }
    .tx-end-r { text-align: left; }
    .tx-legend span { display: block; margin-right: 0; }
}
</style>
</head>

<body>
<div id="templatemo_header_wrapper">
    <div id="templatemo_header">
        <div id="site_logo"></div>
    </div>
</div>

<?php /* 导航栏与其它页面一致（全站每个页面各写一份，没有公共 include） */ ?>
<div id="templatemo_menu_wrapper">
    <div id="templatemo_menu">
        <ul>
            <li><a href="/index.php">Home</a></li>
            <li><a href="#">Taxonomy</a>
                <ul>
                    <li><a href="/browse.php?class=all">All</a></li>
                    <li><a href="/browse.php?class=Cubozoa">Cubozoa</a></li>
                    <li><a href="/browse.php?class=Hexacorallia">Hexacorallia</a></li>
                    <li><a href="/browse.php?class=Octocorallia">Octocorallia</a></li>
                    <li><a href="/browse.php?class=Hydrozoa">Hydrozoa</a></li>
                    <li><a href="/browse.php?class=Myxozoa">Myxozoa</a></li>
                    <li><a href="/browse.php?class=Scyphozoa">Scyphozoa</a></li>
                    <li><a href="/browse.php?class=Staurozoa">Staurozoa</a></li>
                </ul>
            </li>
            <li><a href="/paleobiology.php" class="current">Paleobiology</a></li>
            <li><a href="#">Genome</a>
                <ul>
                    <li><a href="/genomeinfo.php">Genomic Data</a></li>
                    <li><a href="/search.php">Gene Search</a></li>
                    <li><a href="/busco.php">BUSCO Genes</a></li>
                    <li><a href="/TE.php">Transposable Elements</a></li>
                    <li><a href="/gene_family.php">TFs/Ubs</a></li>
                    <li><a href="/proteindomain.php">Protein Domain</a></li>
                    <li><a href="/domain_search.php">Functional Domain Search</a></li>
                    <li><a href="/go.php">Gene Ontology</a></li>
                    <li><a href="/interpro.php">InterPro</a></li>
                    <li><a href="/kegg.php">KEGG Pathway</a></li>
                    <li><a href="/genefamily.php">Gene Family</a></li>
                    <li><a href="/pan-geneset.php">Pan-geneset</a></li>


                    <li><a href="/phylotree/">Species Tree</a></li><li><a href="/core/">Core Orthologs</a></li><li><a href="/microsynteny.php">Microsynteny Analysis</a></li><li><a href="/macrosynteny.php">Macrosynteny Analysis</a></li><li><a href="/mitdata.php">Mitogenomic Data</a></li>

                </ul>
            <li><a href="#">Transcriptome</a>
                <ul>
                    <li><a href="/cytoscape/trans_data.php">Transcriptomic Data</a></li>
                    <li><a href="/trans_assembly.php">Transcriptome Assembly</a></li>
                    <li><a href="/cytoscape/network.php">Network Analysis</a></li>
                    <li><a href="/cytoscape/network_expression.php">Dynamic Expression View</a></li>
                </ul>
            <li><a href="#">Single-cell</a>
                <ul>
                    <li><a href="/sn_data.php">Single-cell Data</a></li>
                    <li><a href="/cell_atlas.php">Cell Atlas</a></li>
                    <li><a href="/cell_marker.php">Cell Marker</a></li>
                    <li><a href="/gene_exp.php">Gene Expression</a></li>
                </ul>
            </li>
            <li><a href="#">Proteome</a>
                <ul>
                    <li><a href="/proteomic_reprocessed.php">Proteomic Data</a></li>
                    <li><a href="/proteomic_reanalysis.php">Proteomic Analysis</a></li>
                </ul>
            </li>
            <li><a href="#">Epigenome</a>
                <ul>
                    <li><a href="/epigenomic_data.php">Epigenomic Data</a></li>
                    <li><a href="/DNA_methylation.php">DNA Methylation</a></li>
                    <li><a href="/miRNA_analysis.php">miRNA-seq Analysis</a></li>
                    <li><a href="/ATAC_analysis.php">ATAC-seq Analysis</a></li>
                    <li><a href="/DHS_analysis.php">DNase-seq Analysis</a></li>
                    <li><a href="/ChIP_analysis.php">ChIP-seq Analysis</a></li>
                </ul>
            </li>
            <li><a href="#">Metagenome</a>
                <ul>
                    <li><a href="/metagenomic_data.php">Metagenomic Data</a></li>
                    <li><a href="/MAGs.php">MAGs Catalog</a></li>

                </ul>
            </li>
            <li><a href="#">Phenotype</a><ul><li><a href="/phenotype.php?class=all">All</a></li><li><a href="/phenotype.php?class=Cubozoa">Cubozoa</a></li><li><a href="/phenotype.php?class=Hexacorallia">Hexacorallia</a></li><li><a href="/phenotype.php?class=Octocorallia">Octocorallia</a></li><li><a href="/phenotype.php?class=Hydrozoa">Hydrozoa</a></li><li><a href="/phenotype.php?class=Scyphozoa">Scyphozoa</a></li></ul></li><li><a href="#">Tools</a>
                <ul>
                    <li><a href="/GSEA/GSEA.php">Gene Sets Analysis</a></li>
                    <li><a href="/blast/blast.php">BLAST</a></li>
                    <li><a href="/primer3plus/primer3.html">Primer Design</a></li>
                    <li><a href="/jbrowse.php">JBrowse</a></li>
                </ul>
            </li>
            <li><a href="/download.php">Download</a></li>
            <li><a href="#">Help</a>
                <ul>
                    <li><a href="/data_statistics.php">Statistics</a></li>
                    <li><a href="/tutorial.php">User Manual</a></li>
                    <li><a href="/submit_comments.php">Data Submit</a></li>
                    <li><a href="/contact.php" class="last">Contact Us</a></li>
                </ul>
            </li>
        </ul>
    </div>
</div>

<div id="tempatemo_content_wrapper">
<div id="templatemo_content">
<div id="column">

<legend><img src="/images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Paleobiology</b></legend>

<div class="tx-wrap">

<?php if ($rec !== null): ?>
<?php /* ---------- 成功：渲染卡片 ---------- */ ?>

    <div class="tx-card">
        <div class="tx-head">
            <?php if ($rnk_text !== ''): ?><div class="tx-rank"><?= $h($rnk_text) ?></div><?php endif; ?>
            <div class="tx-name"><i><?= $h($nam) ?></i><?php if ($att !== null): ?> <span class="tx-att"><?= $h($att) ?></span><?php endif; ?></div>
            <div class="tx-chips">
                <?php if ($ext_text !== ''): ?>
                <span class="tx-chip <?= $ext_text === 'Extant' ? 'tx-chip-on' : 'tx-chip-off' ?>"><?= $ext_text === 'Extant' ? 'Still living' : 'Extinct' ?></span>
                <?php endif; ?>
                <?php if ($siz_n !== null && $exs_n !== null): ?>
                <?php /* 两个数来自 PBDB，不是本站的表。原来的措辞是
                         「1,032 of 11,967 taxa still living」+「in our paleobiology table」，
                         两个 chip 并排读起来就是「本站表里有 11,967 个 taxon」—— 而本站
                         paleobiology 表一共只有 410 行，同一个页面往下两段的
                         「410 species in our paleobiology table fall within this phylum」
                         正好把这个说法否掉。这里把 PBDB 的来源写进 chip 本身，本站的
                         那一句改成独立陈述。 */ ?>
                <span class="tx-chip"><?= number_format($exs_n) ?> of <?= number_format($siz_n) ?> taxa in this group still living (PBDB)</span>
                <?php endif; ?>
                <?php if ($local_rank !== ''): ?>
                <span class="tx-chip">listed in our paleobiology table</span>
                <?php endif; ?>
                <span class="tx-chip">PBDB taxon txn:<?= $h($id) ?></span>
            </div>
            <?php if (count($local_path) > 1): ?>
            <?php /* 谱系来自本站 paleobiology 表的六栏，不用联网。
                     只有一级（比如门自己）时不画，那是把标题重说一遍。 */ ?>
            <div class="tx-lin">
                <?php
                $last_i = count($local_path) - 1;
                foreach ($local_path as $i => $p) {
                    if ($i > 0) { echo '<span class="tx-lin-sep">&#8250;</span>'; }
                    if ($i === $last_i) {
                        echo '<span class="tx-lin-cur">' . $h($p[0]) . '</span>';
                    } elseif ($p[2] !== '') {
                        echo '<a class="tx-lin-a" href="/pbdb_taxon.php?id=' . $h($p[2]) . '">' . $h($p[0]) . '</a>';
                    } else {
                        echo '<span>' . $h($p[0]) . '</span>';
                    }
                    echo '<span class="tx-lin-rank"> ' . $h($p[1]) . '</span>';
                }
                ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="tx-body">

            <div class="tx-stats">
                <?php if ($noc !== null && is_numeric($noc)): ?>
                <div class="tx-stat">
                    <div class="tx-stat-k">Fossil occurrences</div>
                    <div class="tx-stat-v"><?= number_format((float)$noc) ?></div>
                    <div class="tx-stat-n">counting every subtaxon</div>
                </div>
                <?php endif; ?>
                <?php if ($siz_n !== null): ?>
                <div class="tx-stat">
                    <div class="tx-stat-k">Taxa in this group</div>
                    <div class="tx-stat-v"><?= number_format($siz_n) ?></div>
                    <div class="tx-stat-n"><?= $exs_n !== null ? number_format($exs_n) . ' of them still living' : 'recorded in the database' ?></div>
                </div>
                <?php endif; ?>
                <?php if ($fa_txt !== ''): ?>
                <div class="tx-stat">
                    <div class="tx-stat-k">First appeared</div>
                    <div class="tx-stat-v"><?= $fa_txt ?></div>
                    <div class="tx-stat-n"><?= $tei !== null ? $h($tei) . ' &middot; ' : '' ?>oldest fossil</div>
                </div>
                <?php endif; ?>
                <?php if ($la_txt !== ''): ?>
                <div class="tx-stat">
                    <div class="tx-stat-k">Last appeared</div>
                    <div class="tx-stat-v"><?= $la_txt ?></div>
                    <div class="tx-stat-n"><?= $tli !== null ? $h($tli) . ' &middot; ' : '' ?><?= ($ext_text === 'Extant') ? '0&nbsp;Ma is today' : 'youngest fossil' ?></div>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($chart_html !== ''): ?>
            <p class="tx-sub">Stratigraphic range <span>&mdash; million years before present, older to the left</span></p>
            <?= $chart_html ?>
            <?php endif; ?>

            <?php if ($siz_n !== null): ?>
            <p class="tx-sub">Taxa inside this taxon</p>
            <div class="tx-compose">
                <?php if ($exs_pct !== null): ?>
                <div class="tx-compose-bar">
                    <?php /* 卡片铺满后这条有 1540px 宽，光是一条色带看不出比例，把百分比写进去 */ ?>
                    <div class="tx-compose-a" style="width:<?= $pct_txt($exs_pct) ?>%"><?php if ($exs_pct >= 6): ?><span><?= round($exs_pct) ?>%</span><?php endif; ?></div>
                    <div class="tx-compose-b" style="width:<?= $pct_txt(100 - $exs_pct) ?>%"><?php if ((100 - $exs_pct) >= 6): ?><span><?= round(100 - $exs_pct) ?>%</span><?php endif; ?></div>
                </div>
                <div class="tx-compose-lab">
                    <span><i class="tx-dot-a"></i><b><?= number_format($exs_n) ?></b> still living</span>
                    <span><i class="tx-dot-b"></i><b><?= number_format($siz_n - $exs_n) ?></b> not recorded as extant</span>
                </div>
                <?php endif; ?>
                <span class="tx-note">PBDB counts <?= number_format($siz_n) ?> <?= (int)$siz_n === 1 ? 'taxon' : 'taxa' ?> in the database inside <i><?= $h($nam) ?></i>, including this unit itself.</span>
            </div>
            <?php endif; ?>

            <p class="tx-sub">Name &amp; provenance</p>
            <div class="tx-row">
                <div class="tx-k">Author citation</div>
                <div class="tx-v"><?= $att !== null ? $h($att) : '<span class="gd-na">not recorded</span>' ?>
                    <span class="tx-note">The author and year attached to the name itself &mdash; the one printed after the name above.</span>
                </div>
            </div>
            <?php if (count($local_path) < 2 && $prl !== null): ?>
            <div class="tx-row">
                <div class="tx-k">Parent taxon</div>
                <div class="tx-v"><?php if ($par_id !== ''): ?><a href="/pbdb_taxon.php?id=<?= $h($par_id) ?>"><?php endif; ?><i><?= $h($prl) ?></i><?php if ($par_id !== ''): ?></a><?php endif; ?>
                    <span class="tx-note"><?php if ($local_path): ?>Our own paleobiology table starts at phylum level, so anything above that comes straight from PBDB.<?php else: ?>This taxon is not in our own paleobiology table, so the classification here stops at its parent.<?php endif; ?></span>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($local_hits > 0): ?>
            <div class="tx-row">
                <div class="tx-k">In our table</div>
                <div class="tx-v">
                    <?php if ($local_rank === 'species' && $local_species !== ''): ?>
                    This species is one of the 410 fossil records in our <a href="/paleobiology.php?species=<?= urlencode($local_species) ?>">paleobiology table</a>.
                    <?php else: ?>
                    <b class="tx-num"><?= number_format($local_hits) ?></b> species in our <a href="/paleobiology.php">paleobiology table</a> fall within this <?= $h($local_rank) ?>.
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($aut !== null || $rid !== null || $pby !== null): ?>
            <div class="tx-row">
                <div class="tx-k">Entered from</div>
                <div class="tx-v">
                    <?php if ($aut !== null): ?><?= $h($aut) ?><?php endif; ?><?php if ($pby !== null): ?><?= $aut !== null ? ' ' : '' ?><?= $h($pby) ?><?php endif; ?><?php if ($rid !== null): ?><?= ($aut !== null || $pby !== null) ? ' &middot; ' : '' ?><span class="tx-code"><?= $h($rid) ?></span><?php endif; ?>
                    <span class="tx-note">The publication this name was entered from, as recorded by PBDB &mdash; not the same thing as the author citation above.</span>
                </div>
            </div>
            <?php endif; ?>

            <div class="tx-notes">
                <b>How these numbers are counted.</b>
                <b>Fossil occurrences</b> are collections identified as belonging to this taxon <b>or any of its subtaxa</b> &mdash; the whole group, not this unit on its own.
                <b>Taxa in this group</b> counts taxa in the database inside this taxon, this one included.
                <b>First</b> and <b>last appearance</b> are each given as an early limit and a late limit, because the age of a boundary is never known to the year:
                the bar shows the range that fossils confirm in dark green, and the uncertain part of each end in light green.
                All ages are in millions of years before the present, so <b>0&nbsp;Ma</b> is today.
            </div>

        </div>

        <div class="tx-foot">
            Source: the <a href="https://paleobiodb.org/" <?= $out_a ?>>Paleobiology Database</a> (PBDB)
            data API, retrieved <?php if ($fetched > 0): ?><?= date('j M Y', $fetched) ?><?php else: ?>from this site's cache<?php endif; ?>.
            Please cite the original references behind these records; see the PBDB site for their terms of use.
            This card is a summary of one taxon record &mdash; the full classification, the list of collections
            and the complete reference list live on PBDB.
        </div>
    </div>

    <div class="tx-actions">
        <a class="tx-btn tx-btn-go" href="<?= htmlspecialchars($pbdb_href, ENT_QUOTES, 'UTF-8') ?>" <?= $out_a ?>>View on PBDB &raquo;</a>
        <?php if ($local_species !== ''): ?>
        <a class="tx-btn tx-btn-back" href="/paleobiology.php?species=<?= urlencode($local_species) ?>">Show this species' fossil records in our table (<?= htmlspecialchars($local_species, ENT_QUOTES, 'UTF-8') ?>)</a>
        <?php endif; ?>
        <a class="tx-btn tx-btn-back" href="/paleobiology.php">&laquo; Back to Paleobiology</a>
    </div>

<?php elseif (!$given): ?>
    <?php /* ---------- 没给 id ---------- */ ?>

    <div class="tx-panel">
        <b>No taxon was specified.</b>
        This page shows the Paleobiology Database record for one taxon. Open it from the
        <a href="/paleobiology.php">Paleobiology</a> table, or pass an id directly, for example
        <span class="tx-code">/pbdb_taxon.php?id=4524</span>.
    </div>
    <div class="tx-actions">
        <a class="tx-btn tx-btn-back" href="/paleobiology.php">&laquo; Back to Paleobiology</a>
    </div>

<?php elseif (!$id_ok): ?>
    <?php /* ---------- id 格式不对 ---------- */ ?>

    <div class="tx-panel">
        <b>That is not a valid taxon id.</b>
        PBDB taxon ids are plain numbers, optionally written with a <span class="tx-code">txn:</span> prefix
        &mdash; for example <span class="tx-code">4524</span> or <span class="tx-code">txn:4524</span>.
        The value we received was <span class="tx-code"><?= htmlspecialchars(substr($raw, 0, 60), ENT_QUOTES, 'UTF-8') ?></span>.
    </div>
    <div class="tx-actions">
        <a class="tx-btn tx-btn-back" href="/paleobiology.php">&laquo; Back to Paleobiology</a>
    </div>

<?php else: ?>
    <?php /* ---------- id 合法但取不到记录 ---------- */ ?>

    <div class="tx-panel">
        <?php if ($err_kind === 'unknown'): ?>
        <b>No such taxon in the Paleobiology Database.</b>
        <?= $err_msg_html ?>
        <?php else: ?>
        <b>The record could not be retrieved.</b>
        <?= $err_msg_html ?>
        The link is still valid &mdash; this is a problem reaching PBDB from our server, not a bad
        taxon id. You can open the record directly at PBDB:
        <?php endif; ?>
        <br /><br />
        <a class="tx-btn tx-btn-go" href="<?= htmlspecialchars($pbdb_href, ENT_QUOTES, 'UTF-8') ?>" <?= $out_a ?>>View taxon <?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?> on PBDB &raquo;</a>
    </div>
    <div class="tx-actions">
        <a class="tx-btn tx-btn-back" href="/paleobiology.php">&laquo; Back to Paleobiology</a>
    </div>

<?php endif; ?>

</div><!-- /tx-wrap -->

</div>
</div>
</div>

<?php
	include "Webpage_components.php";
	print $footer;
?>
</body>
</html>
