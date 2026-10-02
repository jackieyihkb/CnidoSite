<?php
/* ---------------------------------------------------------------------------
 * macrosynteny_db.php -- 宏共线性分析页面共用的数据库配置与连接
 *
 * 部署：把本文件与 macrosynteny*.php、css/、js/、data/ 一起放到网站根目录，
 *       然后把下面的配置改成你站点的真实值即可。数据由
 *       macrosyntR/web/sql/load_data.sh 一次性导入（4 张 macrosynteny_* 表）。
 *
 * 若你的站点已有公共的连接文件，也可以把 $MSR_DB['reuse'] 设成连接变量名，
 * 例如已有 `$link = mysqli_connect(...)`，则设 $MSR_DB['reuse'] = '$link';
 * 这样就不会重复建连接。
 * ------------------------------------------------------------------------- */

$MSR_DB = array(
    'host'    => 'localhost',      // 数据库主机
    'port'    => 3306,
    'user'    => getenv('CNIDO_MSR_DB_USER') ?: 'cnidosite',      // 数据库用户
    'pass'    => getenv('CNIDO_MSR_DB_PASS') ?: '',      // 密码
    'name'    => getenv('CNIDO_MSR_DB_NAME') ?: 'jackie_db',      // 库名（数据导入到哪个库就填哪个）
    'charset' => 'utf8mb4',
    'reuse'   => '',               // 可选：复用已有连接，如 '$link'；留空则自行连接
);

/* 一对物种要被拿去做两两比较，至少需要的共有锚点数。
 * 必须与 02_run_macrosyntR.R 的 --min-anchors 默认值一致：低于它的物种对不会写入
 * macrosynteny_pair，页面只能提示"没有结果"，所以提示语里的数字要从这里取。 */
define('MSR_MIN_ANCH_PAIR', 30);

/* 取得一个 mysqli 连接。失败时返回 null，调用方用 msr_db_error() 取原因。 */
function msr_db($cfg = null) {
    global $MSR_DB;
    if ($cfg === null) { $cfg = $MSR_DB; }

    if ($cfg['reuse'] !== '') {
        $var = ltrim($cfg['reuse'], '$');
        if (isset($GLOBALS[$var]) && $GLOBALS[$var] instanceof mysqli) {
            return $GLOBALS[$var];
        }
    }
    if (!function_exists('mysqli_connect')) {
        $GLOBALS['MSR_DB_ERROR'] = 'the PHP mysqli extension is not enabled (install php-mysql)';
        return null;
    }
    mysqli_report(MYSQLI_REPORT_OFF);
    $link = @mysqli_connect($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['name'],
                           (int)$cfg['port']);
    if (!$link) {
        $GLOBALS['MSR_DB_ERROR'] = 'database connection failed: ' . mysqli_connect_error();
        return null;
    }
    @mysqli_set_charset($link, $cfg['charset']);
    return $link;
}

function msr_db_error() {
    return isset($GLOBALS['MSR_DB_ERROR']) ? $GLOBALS['MSR_DB_ERROR'] : 'unknown error';
}

/* 转义（没有连接时的兜底，等价于 mysqli_real_escape_string 的常用子集） */
function msr_esc($link, $s) {
    if ($link) { return mysqli_real_escape_string($link, (string)$s); }
    return str_replace(array('\\', "'", '"', "\n", "\r", "\x1a"),
                       array('\\\\', "\\'", '\\"', '\\n', '\\r', '\\Z'), (string)$s);
}

/* 一次性取回结果集，返回关联数组列表 */
function msr_query($link, $sql) {
    $rows = array();
    $res = mysqli_query($link, $sql);
    if (!$res) {
        $GLOBALS['MSR_DB_ERROR'] = 'SQL query failed: ' . mysqli_error($link) . ' -- ' . $sql;
        return $rows;
    }
    while ($r = mysqli_fetch_assoc($res)) { $rows[] = $r; }
    mysqli_free_result($res);
    return $rows;
}

/* 把 "Acropora_acuminata" / "Acropora acuminata" 都归一到显示名 */
function msr_display_name($sp) {
    return str_replace('_', ' ', trim($sp));
}

/* 按显示名或内部 id 取物种记录，取不到返回 null */
function msr_species_row($link, $name) {
    $esc = msr_esc($link, msr_display_name($name));
    $esc_raw = msr_esc($link, trim($name));
    $r = msr_query($link, "SELECT * FROM macrosynteny_species
                            WHERE display_name = '$esc' OR species_id = '$esc_raw' LIMIT 1");
    return $r ? $r[0] : null;
}

/* pair_id 的生成规则与 01_prepare_inputs.py 一致：两个物种名按字典序拼接 */
function msr_pair_id($sp_a, $sp_b) {
    $x = array($sp_a, $sp_b);
    sort($x, SORT_STRING);
    return $x[0] . '__' . $x[1];
}

/* 生成 htmlspecialchars 后的安全输出 */
function msr_h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/* 把 PHP 数组以 JSON 形式输出到 <script> 里。
 * json_encode 默认把 "/" 转义成 "\/"，因此 "</script>" 不会截断脚本块，可安全内嵌。 */
function msr_json($v) {
    return json_encode($v, JSON_UNESCAPED_UNICODE);
}
?>
