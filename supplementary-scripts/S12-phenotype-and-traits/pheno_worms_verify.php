<?php
/* =====================================================================
 * pheno_worms_verify.php —— 独立核对 WoRMS 那一块（只读）
 *
 * 与 fetch_worms_traits.php --load 一样从缓存 JSON 出发，但【不调用它的任何函数】：
 * 自己重走一遍树、自己重算折叠键，再与库里的行数、n_records 之和比。
 * 折叠后行数必须相等、n_records 之和必须相等，少一条就说明写漏了。
 *
 * 为什么值得单独写：加载器同时做了三件容易出错的事 —— 同物异名按名字展开、
 * 按内容折叠、inherited 判定（AphiaID_Inherited ≠ 本节点 AphiaID）。这三件事
 * 只要有一件写反，页面上的数就会变，而页面上看不出来。
 *
 * 用法：php pheno_worms_verify.php [物种名 …]   （默认抽查三个物种）
 * ===================================================================== */
const W_SRC  = 'WoRMS (Marine Species Traits)';
const W_DATA = '/var/www/cnidosite-tools/data';
const W_ATTR = '/var/www/cnidosite-tools/data/worms_attr';

$src = @file_get_contents('/var/www/html/CnidoSite/includes/state.php');
preg_match("/\\\$conn = @new mysqli\('([^']*)',\s*'([^']*)',\s*'([^']*)',\s*'([^']*)'\)/", $src, $m);
$conn = @new mysqli($m[1], $m[2], $m[3], $m[4]);
if ($conn->connect_errno) { fwrite(STDERR, "连接失败\n"); exit(2); }
$conn->set_charset('utf8mb4');

$res = json_decode((string)file_get_contents(W_DATA . '/worms_resolve.json'), true);
if (!is_array($res)) { fwrite(STDERR, "读不到 worms_resolve.json\n"); exit(2); }

/* 有效 AphiaID => 我们表里挂在它下面的那些写法（与加载器同一份依据，
   依据来自 resolve 文件本身，不是加载器的中间结果）。 */
$byAphia = array();
foreach ($res as $nm => $d) {
    if (strcasecmp((string)$d['phylum'], 'Cnidaria') !== 0) { continue; }
    $id = (string)$d['valid_aphia'];
    if ($id === '') { continue; }
    if (!isset($byAphia[$id])) { $byAphia[$id] = array('cls' => (string)$d['class'], 'names' => array()); }
    $byAphia[$id]['names'][] = (string)$nm;
}

$agg = array();        /* 折叠键 => n_records */
$srcEntries = 0;       /* 按名字展开后的条目数 */
$bySpecies = array();  /* 物种名 => array(行数, n_records 之和) */
$nFiles = 0; $nAttr = 0;

$walk = function ($list, $id, $cls, $names) use (&$walk, &$agg, &$srcEntries, &$bySpecies, &$nAttr) {
    foreach ($list as $x) {
        if (!is_array($x)) { continue; }
        if (isset($x['measurementType'])) {
            $nAttr++;
            $inh = (string)(isset($x['AphiaID_Inherited']) ? $x['AphiaID_Inherited'] : '');
            $meth = ($inh !== '' && $inh !== $id) ? 'inherited from a higher taxon' : '';
            foreach ($names as $nm) {
                $srcEntries++;
                if (!isset($bySpecies[$nm])) { $bySpecies[$nm] = array(0, 0); }
                $bySpecies[$nm][1]++;
                $k = md5(implode("\x1f", array($cls, $nm, (string)$x['measurementType'], '', (string)$x['measurementValue'],
                            '', '', '', '', $meth, (string)(isset($x['qualitystatus']) ? $x['qualitystatus'] : ''), '')));
                if (!isset($agg[$k])) { $agg[$k] = 0; $bySpecies[$nm][0]++; }
                $agg[$k]++;
            }
        }
        if (!empty($x['children'])) { $walk($x['children'], $id, $cls, $names); }
    }
};

foreach (glob(W_ATTR . '/*.json') as $f) {
    $id = (string)basename($f, '.json');
    if (!isset($byAphia[$id])) { continue; }
    $a = json_decode((string)file_get_contents($f), true);
    if (!is_array($a)) { continue; }
    $nFiles++;
    $walk($a, $id, $byAphia[$id]['cls'], $byAphia[$id]['names']);
}

$folded = count($agg);
$srSum  = 0;
foreach ($agg as $n) { $srSum += $n; }

$r = $conn->query("SELECT COUNT(*) c, SUM(n_records) s FROM phenotype WHERE source='" . W_SRC . "'")->fetch_row();
$dbRows = (int)$r[0]; $dbSr = (int)$r[1];

printf("缓存 %s 个物种文件 / %s 条属性\n", number_format($nFiles), number_format($nAttr));
printf("按物种名展开 %s 条；折叠后 %s 行；n_records 之和 %s\n",
       number_format($srcEntries), number_format($folded), number_format($srSum));
printf("库中 %s 行 / n_records 之和 %s\n", number_format($dbRows), number_format($dbSr));
$fail = 0;
printf("行数        %s\n", $folded === $dbRows ? '一致' : '**不一致**');
printf("n_records   %s\n", $srSum  === $dbSr  ? '一致' : '**不一致**');
if ($folded !== $dbRows || $srSum !== $dbSr) { $fail++; }

/* 抽查：库里的行数与 n_records 之和，按物种逐一比对（名字写法可能不同，
   所以两边都按 resolve 里的物种名取）。 */
$want = array_slice($argv, 1);
if (!$want) {
    $want = array();
    foreach ($bySpecies as $nm => $v) { $want[] = $nm; if (count($want) >= 3) { break; } }
}
foreach ($want as $nm) {
    if (!isset($bySpecies[$nm])) { printf("%-30s 缓存里没有这个物种\n", $nm); continue; }
    $e = $conn->real_escape_string($nm);
    $r = $conn->query("SELECT COUNT(*) c, SUM(n_records) s FROM phenotype
                       WHERE source='" . W_SRC . "' AND species='$e'")->fetch_row();
    $ok = ((int)$r[0] === $bySpecies[$nm][0] && (int)$r[1] === $bySpecies[$nm][1]);
    if (!$ok) { $fail++; }
    printf("%-30s 缓存 %5s 行 / %6s 条   库 %5s 行 / %6s 条   %s\n", $nm,
           number_format($bySpecies[$nm][0]), number_format($bySpecies[$nm][1]),
           number_format((int)$r[0]), number_format((int)$r[1]), $ok ? '一致' : '**不一致**');
}

echo $fail ? "\n有 {$fail} 项对不上。\n" : "\n全部一致。\n";
exit($fail ? 1 : 0);
