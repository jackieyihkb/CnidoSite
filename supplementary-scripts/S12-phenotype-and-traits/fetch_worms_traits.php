<?php
/* =====================================================================
 * fetch_worms_traits.php —— 从 WoRMS / Marine Species Traits 抓刺胞动物的性状
 *
 * WoRMS 这一家没有批量文件（DAS 记录 dasid=8131 只有落地页），只有 REST：
 *   · 解析物种名： /AphiaRecordsByNames?scientificnames[]=A&scientificnames[]=B
 *                  一次最多 500 个名字
 *   · 取性状：     /AphiaAttributesByAphiaID/{AphiaID}?include_inherited=true
 *                  一次只能一个 id（逗号塞多个会 400）
 * 所以流程只能是「先解析、再逐个物种抓」，约 4,000 次请求、十几分钟。
 * 因此这个脚本按阶段拆开、且【可断点续跑】—— 原始 JSON 全部落盘在 docroot 外，
 * 重跑只补缺的，抓过的不会重抓（WoRMS 是别人的公共服务，别重复打）。
 *
 * 阶段：
 *   --resolve          从主表取物种名 → 批量解析 → data/worms_resolve.json
 *   --attributes       对解析出的刺胞动物逐个抓性状 → data/worms_attr/<AphiaID>.json
 *   --load --apply     把缓存解析成长表，写进主表（默认 phenotype）等表
 *   可选 --table NAME  目标表，默认 phenotype。2026-09-27 换表之后主表就叫
 *                      phenotype：装载只删自己 source 的行（在事务里）、
 *                      别家的行一概不碰，所以直接写主表是安全的；
 *                      要按 B 方案整体重建时用 --table phenotype_v2 落到中转表。
 *        --limit N     本次最多发 N 个性状请求（便于分几次跑）
 *        --delay 秒    每个请求之间的间隔，默认 0.12
 *        --shard k/n   只做第 k 片（0 起）。3,881 个 id 逐个抓要一个多小时，而瓶颈
 *                      是每个请求的往返（~2 s），不是延迟 —— 所以并行几片是唯一
 *                      的提速手段。分片按 crc32(AphiaID) % n，**不是**按下标：
 *                      下标分片时每片自己的待抓列表会随着缓存变长而错位，边界上
 *                      的 id 会被两片都跳过或都抓；按 id 哈希则与缓存状态无关。
 *                      并发别超过 3–4 片（别人家的公共服务，UA 里已经写了本站）。
 *
 * 许可与署名：WoRMS 数据是 CC BY 4.0，但站点明确写着「不允许整库再分发，
 * 除非事先书面同意」。所以这里【只取刺胞动物这一小部分】、逐条记来源、
 * 页面署名并回链 —— 不是把整个库镜像过来。写进库的 source 字段带出处，
 * 便于随时撤下。
 * ===================================================================== */

$opts = array('resolve' => false, 'attributes' => false, 'load' => false,
              'apply' => false, 'limit' => 0, 'delay' => 0.12, 'shard' => 0, 'shards' => 1,
              'table' => 'phenotype');
for ($i = 1; $i < $argc; $i++) {
    $a = $argv[$i];
    if ($a === '--resolve')         { $opts['resolve'] = true; }
    elseif ($a === '--attributes')  { $opts['attributes'] = true; }
    elseif ($a === '--load')        { $opts['load'] = true; }
    elseif ($a === '--apply')       { $opts['apply'] = true; }
    elseif ($a === '--table')       { $opts['table'] = (string)$argv[++$i]; }
    elseif ($a === '--limit')       { $opts['limit'] = (int)$argv[++$i]; }
    elseif ($a === '--delay')       { $opts['delay'] = (float)$argv[++$i]; }
    elseif ($a === '--shard')       { $opts['shard'] = (int)$argv[++$i]; }
    elseif ($a === '--shards')      { $opts['shards'] = max(1, (int)$argv[++$i]); }
    else { fwrite(STDERR, "未知参数: {$a}\n"); exit(2); }
}
if ($opts['shard'] >= $opts['shards']) { fwrite(STDERR, "--shard 必须小于 --shards\n"); exit(2); }
if (!$opts['resolve'] && !$opts['attributes'] && !$opts['load']) {
    fwrite(STDERR, "要指定阶段：--resolve / --attributes / --load\n"); exit(2);
}
/* 表名会拼进 SQL，只允许标识符字符（不是引号包裹的标识符，所以这里必须拦住）。 */
if (!preg_match('/^[A-Za-z0-9_]+$/', $opts['table'])) {
    fwrite(STDERR, "--table 只允许字母数字下划线\n"); exit(2);
}

/* WoRMS 的 measurementType → 本站 trait_category 的对照表。
   源库自己没有类别字段，装载时按这张表归类 —— 不写的话这一列是空的，
   48,607 行会全部落进矩阵的 "(no category)" 那一列。 */
require_once __DIR__ . '/worms_trait_category.php';
define('WORMS_TABLE', $opts['table']);

const WORMS_SRC   = 'WoRMS (Marine Species Traits)';
const WORMS_DATA  = '/var/www/cnidosite-tools/data';
const WORMS_ATTR  = '/var/www/cnidosite-tools/data/worms_attr';
const UA          = 'CnidoSite/1.0 (trait integration; +https://cnidosite.org)';

if (!is_dir(WORMS_DATA)) { @mkdir(WORMS_DATA, 0775, true); }
if (!is_dir(WORMS_ATTR)) { @mkdir(WORMS_ATTR, 0775, true); }

/** 出站 HTTPS 用 file_get_contents：本机 PHP 没有 curl 扩展（openssl 是静态编的）。
 *  $http_response_header 在同作用域会留旧值，所以这里只看返回值，别信它。 */
function wget($url)
{
    $ctx = stream_context_create(array('http' => array('method' => 'GET', 'timeout' => 40,
        'header' => 'User-Agent: ' . UA . "\r\nAccept: application/json\r\n",
        'ignore_errors' => true)));
    $b = @file_get_contents($url, false, $ctx);
    return $b === false ? null : $b;
}

function db()
{
    $src = @file_get_contents('/var/www/html/CnidoSite/includes/state.php');
    preg_match("/\\\$conn = @new mysqli\('([^']*)',\s*'([^']*)',\s*'([^']*)',\s*'([^']*)'\)/", $src, $m);
    $c = @new mysqli($m[1], $m[2], $m[3], $m[4]);
    if ($c->connect_errno) { fwrite(STDERR, "连接失败 ({$c->connect_errno})\n"); exit(2); }
    $c->set_charset('utf8mb4');
    return $c;
}

/* ---------------------------------------------------------------------
 * 阶段 A：物种名 → AphiaID
 * ------------------------------------------------------------------- */
if ($opts['resolve']) {
    $conn = db();
    $names = array();
    $q = $conn->query("SELECT DISTINCT species FROM `" . WORMS_TABLE . "`
                       WHERE TRIM(COALESCE(species,'')) <> '' ORDER BY species");
    while ($r = $q->fetch_row()) { $names[] = $r[0]; }
    printf("待解析物种名 %s 个\n", number_format(count($names)));

    /* 已经解析过的名字不重问（续跑时只补新的）。 */
    $have = array();
    if (is_file(WORMS_DATA . '/worms_resolve.json')) {
        $old = json_decode((string)file_get_contents(WORMS_DATA . '/worms_resolve.json'), true);
        if (is_array($old)) { $have = $old; }
    }
    $todo = array_values(array_filter($names, function ($n) use ($have) { return !isset($have[$n]); }));
    /* 每批 100 个，不是文档写的 500：这个名字塞在【路径的查询串】里，500 个名字
       URL 长到 2 万多字符，WoRMS 前面那层直接回 400「Your browser sent an invalid
       request」—— 那是 Apache 的 LimitRequestLine（默认 8190 字节）在拦，
       不是 API 的参数上限。100 个约 4.3 KB，稳。 */
    $batch = 100;
    printf("其中未解析 %s 个，分 %s 批（每批 %s）\n", number_format(count($todo)),
           number_format((int)ceil(count($todo) / $batch)), $batch);

    foreach (array_chunk($todo, $batch) as $i => $chunk) {
        $url = 'https://www.marinespecies.org/rest/AphiaRecordsByNames?'
             . implode('&', array_map(function ($n) { return 'scientificnames[]=' . rawurlencode($n); }, $chunk));
        $j = json_decode((string)wget($url), true);
        if (!is_array($j)) { fwrite(STDERR, "第 " . ($i + 1) . " 批失败，跳过（下次重跑会补）\n"); continue; }
        /* 返回是「每个请求名一个记录组」，顺序与请求一致 —— 所以按【位置】回填，
           不要按名字去猜：同物异名会让 scientificname 与请求名不同，靠名字匹配
           会配错行。只做一次校验，配不上就按位置算并把差异记下来。 */
        $miss = 0;
        foreach ($j as $k => $grp) {
            if (!isset($chunk[$k])) { break; }
            $key = $chunk[$k];
            $rec = (is_array($grp) && isset($grp[0])) ? $grp[0] : $grp;
            if (!is_array($rec) || empty($rec['AphiaID'])) { continue; }
            if (strcasecmp($key, trim((string)$rec['scientificname'])) !== 0) { $miss++; }
            $have[$key] = array('aphia' => (string)$rec['AphiaID'],
                                'valid_aphia' => (string)(isset($rec['valid_AphiaID']) ? $rec['valid_AphiaID'] : $rec['AphiaID']),
                                'valid_name' => (string)(isset($rec['valid_name']) ? $rec['valid_name'] : ''),
                                'status' => (string)(isset($rec['status']) ? $rec['status'] : ''),
                                'phylum' => (string)(isset($rec['phylum']) ? $rec['phylum'] : ''),
                                'class' => (string)(isset($rec['class']) ? $rec['class'] : ''),
                                'order' => (string)(isset($rec['order']) ? $rec['order'] : ''),
                                'family' => (string)(isset($rec['family']) ? $rec['family'] : ''));
        }
        printf("  第 %s 批完成，累计 %s 个%s\n", $i + 1, number_format(count($have)),
               $miss ? "（{$miss} 个返回名的写法与请求名不同，按位置采纳）" : '');
        $tmp = WORMS_DATA . '/worms_resolve.json.new';
        file_put_contents($tmp, json_encode($have));
        rename($tmp, WORMS_DATA . '/worms_resolve.json');
        usleep((int)($opts['delay'] * 1000000));
    }
    printf("解析结果：%s 个名字，写入 %s\n", number_format(count($have)), WORMS_DATA . '/worms_resolve.json');
    $cn = 0; $ids = array();
    foreach ($have as $n => $d) { if (strcasecmp($d['phylum'], 'Cnidaria') === 0) { $cn++; $ids[$d['valid_aphia']] = 1; } }
    printf("其中刺胞动物 %s 个名字 / %s 个不同的有效 AphiaID（要抓这么多次性状）\n",
           number_format($cn), number_format(count($ids)));
}

/* ---------------------------------------------------------------------
 * 阶段 B：逐个 AphiaID 抓性状（可 --limit 分批，可续跑）
 * ------------------------------------------------------------------- */
if ($opts['attributes']) {
    if (!is_file(WORMS_DATA . '/worms_resolve.json')) { fwrite(STDERR, "先跑 --resolve\n"); exit(2); }
    $res = json_decode((string)file_get_contents(WORMS_DATA . '/worms_resolve.json'), true);
    $ids = array();
    foreach ((array)$res as $d) {
        if (strcasecmp((string)$d['phylum'], 'Cnidaria') !== 0) { continue; }
        $id = (string)$d['valid_aphia'];
        if ($id === '' || is_file(WORMS_ATTR . '/' . $id . '.json')) { continue; }
        /* 分片按 id 的哈希，不按下标：下标会随着缓存变长而整体错位。 */
        if ($opts['shards'] > 1 && (crc32($id) % $opts['shards']) !== $opts['shard']) { continue; }
        $ids[$id] = 1;
    }
    $ids = array_keys($ids);
    printf("待抓 %s 个 AphiaID（已有缓存 %s 个）%s\n", number_format(count($ids)),
           number_format(count(glob(WORMS_ATTR . '/*.json'))),
           $opts['shards'] > 1 ? sprintf("（第 %d/%d 片）", $opts['shard'] + 1, $opts['shards']) : '');
    $n = 0; $ok = 0; $empty = 0; $fail = 0;
    foreach ($ids as $id) {
        if ($opts['limit'] > 0 && $n >= $opts['limit']) { break; }
        $n++;
        $b = wget("https://www.marinespecies.org/rest/AphiaAttributesByAphiaID/{$id}?include_inherited=true");
        if ($b === null) { $fail++; usleep(500000); continue; }
        if (trim($b) === '' || trim($b) === '[]') { $empty++; $b = '[]'; }
        /* 临时文件 + rename：中断时不会留下半截 JSON 让续跑误判为已完成 */
        $tmp = WORMS_ATTR . "/{$id}.json.new";
        file_put_contents($tmp, $b);
        if (rename($tmp, WORMS_ATTR . "/{$id}.json")) { $ok++; }
        if ($n % 200 === 0) { printf("  ... %s 个（空 %s / 失败 %s）\n", number_format($n), $empty, $fail); }
        usleep((int)($opts['delay'] * 1000000));
    }
    printf("本次抓取 %s 个：成功 %s / 无性状 %s / 失败 %s\n", number_format($n), $ok, $empty, $fail);
    printf("缓存总数 %s 个。还有剩就再跑一次 --attributes。\n", number_format(count(glob(WORMS_ATTR . '/*.json'))));
}

/* ---------------------------------------------------------------------
 * 阶段 C：缓存 → 长表 → 库
 * ------------------------------------------------------------------- */
if ($opts['load']) {
    if (!is_file(WORMS_DATA . '/worms_resolve.json')) { fwrite(STDERR, "先跑 --resolve\n"); exit(2); }
    $res = json_decode((string)file_get_contents(WORMS_DATA . '/worms_resolve.json'), true);
    /* 按【有效 AphiaID】归并：一个有效名下可能挂着我们表里的好几个写法（同物异名）。
       class/order/family 从这些记录里取（取哪条都一样，同物异名同类），
       不要拿 valid_name 回查 $res —— 同物异名的 valid_name 在 $res 里根本没有那个键，
       会静默拿到空 class。 */
    $byAphia = array();
    foreach ((array)$res as $nm => $d) {
        if (strcasecmp((string)$d['phylum'], 'Cnidaria') !== 0) { continue; }
        $id = (string)$d['valid_aphia'];
        if ($id === '') { continue; }
        if (!isset($byAphia[$id])) {
            $byAphia[$id] = array('cls' => (string)$d['class'], 'order' => (string)$d['order'],
                                  'family' => (string)$d['family'], 'status' => (string)$d['status'],
                                  'valid' => (string)$d['valid_name'], 'names' => array());
        }
        $byAphia[$id]['names'][] = $nm;
    }

    $rows = array(); $cites = array(); $maps = array(); $nAttr = 0; $noCache = 0;
    foreach (glob(WORMS_ATTR . '/*.json') as $f) {
        $id = (string)basename($f, '.json');
        if (!isset($byAphia[$id])) { $noCache++; continue; }
        $a = json_decode((string)file_get_contents($f), true);
        if (!is_array($a)) { continue; }
        $sci   = $byAphia[$id]['valid'] !== '' ? $byAphia[$id]['valid'] : $byAphia[$id]['names'][0];
        $cls   = $byAphia[$id]['cls'];
        /* 同一有效名下我们表里有多个写法时，每个写法都出一份 —— 物种页按写法查，
           同物异名各自都要能查到自己那份。 */
        $names = $byAphia[$id]['names'];
        $walk = function ($list) use (&$walk, &$rows, &$nAttr, $id, $names, $cls, &$cites) {
            foreach ($list as $x) {
                if (!is_array($x)) { continue; }
                if (isset($x['measurementType'])) {
                    $nAttr++;
                    $inhPid = (string)(isset($x['AphiaID_Inherited']) ? $x['AphiaID_Inherited'] : '');
                    $meth = ($inhPid !== '' && $inhPid !== $id) ? 'inherited from a higher taxon' : '';
                    $ref = trim((string)(isset($x['reference']) ? $x['reference'] : ''));
                    $sid = trim((string)(isset($x['source_id']) ? $x['source_id'] : ''));
                    if ($sid !== '') { $cites[$sid] = $ref; }
                    foreach ($names as $nm) {
                        /* 第 4 列是 trait_category：WoRMS 侧没有这一层，按
                           worms_trait_category.php 的对照表归到本站的类别。
                           没有对应项时留空 —— 宁可空着，也不猜一个类别。 */
                        $rows[] = array($cls, $nm, (string)$x['measurementType'],
                                        cnido_worms_trait_category((string)$x['measurementType']),
                                        (string)$x['measurementValue'], '',
                                        '', '', '', $meth,
                                        (string)(isset($x['qualitystatus']) ? $x['qualitystatus'] : ''),
                                        '', WORMS_SRC, $sid, $sid);
                    }
                }
                if (!empty($x['children'])) { $walk($x['children']); }
            }
        };
        $walk($a);
        $maps[$id] = array('names' => $names, 'sci' => $sci, 'cls' => $cls,
                           'status' => $byAphia[$id]['status'],
                           'order' => $byAphia[$id]['order'],
                           'family' => $byAphia[$id]['family']);
    }
    printf("缓存 %s 个物种 → %s 条性状 / %s 个物种名 / %s 条文献%s\n",
           number_format(count($maps)), number_format($nAttr), number_format(count($rows)),
           number_format(count($cites)),
           $noCache ? "（{$noCache} 个缓存文件没有对应的解析记录，已忽略）" : '');

    /* 折叠键与 build_phenotype_v2.php 完全一致：source + 12 个内容列
       （Class…context，即 r[0..12]），**不含** source_record_id / source_resource_id。
       同一个性状值在 WoRMS 里可能挂在两条 source 记录下 —— 那是同一个值，不该在表里
       占两行；而 species 或 context 不同就是不同的行，各自保留。n_records 记的是
       折叠掉的源条目数，与主表口径一致。
       早先这里按整条元组（含 source_id）去重、且写死 n_records=1，于是「内容相同、
       出处编号不同」的行会以 ×1 各占一行，同一张表里两套折叠口径。 */
    $agg = array();
    foreach ($rows as $r) {
        $k = md5(implode("\x1f", array_slice($r, 0, 13)));
        if (isset($agg[$k])) { $agg[$k][1]++; continue; }
        $agg[$k] = array($r, 1);
    }
    $uniq = array();
    foreach ($agg as $a) { $u = $a[0]; $u[] = $a[1]; $uniq[] = $u; }
    $srcN = 0; $multi = 0;
    foreach ($uniq as $u) { $srcN += $u[15]; if ($u[15] > 1) { $multi++; } }
    printf("折叠后 %s 行（n_records 合计 %s，其中 %s 行是折叠出来的）\n",
           number_format(count($uniq)), number_format($srcN), number_format($multi));
    /* 这两个数说明「n_records 合计」为什么比原始属性条数大：一个属性会按它所属的
       每个物种名各出一行（同物异名两边都要能查到），所以 19,477 条属性展开成
       19,606 行。差值就是同物异名的数量级，页脚那句「同物异名两边各记一次」
       指的就是它。 */
    printf("原始属性 %s 条，按物种名展开后 %s 行（同物异名多出 %s 行）\n",
           number_format($nAttr), number_format(count($rows)),
           number_format(count($rows) - $nAttr));

    if (!$opts['apply']) { echo "干跑，未写库。（--apply 才写）\n"; exit(0); }

    $conn = db();
    $conn->query("DROP TABLE IF EXISTS phenotype_species_map");
    if ($conn->query("CREATE TABLE phenotype_species_map (
            source varchar(40) NOT NULL DEFAULT '',
            species varchar(160) NOT NULL DEFAULT '',
            valid_name varchar(160) NOT NULL DEFAULT '',
            aphia_id varchar(16) NOT NULL DEFAULT '',
            status varchar(24) NOT NULL DEFAULT '',
            class varchar(40) NOT NULL DEFAULT '',
            order_name varchar(60) NOT NULL DEFAULT '',
            family varchar(60) NOT NULL DEFAULT '',
            UNIQUE KEY uq (source, species)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci") === false) {
        fwrite(STDERR, "建 map 表失败: {$conn->error}\n"); exit(1);
    }
    /* 目标表不存在时 DELETE 会返回 false、prepare 会返回 bool —— 以前到
       bind_param() 才炸成 "Call to a member function ... on bool" 的 PHP fatal，
       看不出是表名写错了（--table 拼错就是这个下场）。先查一次，说清楚。 */
    $chk = $conn->query("SELECT 1 FROM `" . WORMS_TABLE . "` LIMIT 0");
    if ($chk === false) {
        fwrite(STDERR, "目标表 `" . WORMS_TABLE . "` 用不了: {$conn->error}\n" .
                       "（--table 默认是主表 phenotype；要落中转表就先建好它）\n");
        exit(1);
    }
    $conn->query("DELETE FROM `" . WORMS_TABLE . "` WHERE source='" . WORMS_SRC . "'");

    $st = $conn->prepare("INSERT INTO `" . WORMS_TABLE . "`
        (Class, species, trait_name, trait_category, value, traitunit, region, latitude,
         longitude, methodology, value_type, context, source, source_record_id,
         source_resource_id, n_records)
        VALUES (?,?,?,?,?,?,?,'','',?,?,?,?,?,?,?)");
    if ($st === false) { fwrite(STDERR, "prepare 失败: {$conn->error}\n"); exit(1); }
    $conn->begin_transaction();
    foreach ($uniq as $r) {
        /* 14 个占位符：$r[7]/$r[8]（纬度/经度）与 region 一样是空串，由 SQL 里的
           字面量给，不占位；最后一个占位符是折叠出的 n_records（$r[15]）。
           类型串必须正好 14 个 s。 */
        $st->bind_param('ssssssssssssss', $r[0], $r[1], $r[2], $r[3], $r[4], $r[5], $r[6],
                        $r[9], $r[10], $r[11], $r[12], $r[13], $r[14], $r[15]);
        $st->execute();
    }
    $st->close();

    $st = $conn->prepare("INSERT INTO phenotype_species_map
        (source, species, valid_name, aphia_id, status, class, order_name, family)
        VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE valid_name=VALUES(valid_name)");
    foreach ($maps as $id => $d) {
        foreach ($d['names'] as $nm) {
            $s = WORMS_SRC;
            $st->bind_param('ssssssss', $s, $nm, $d['sci'], $id, $d['status'], $d['cls'], $d['order'], $d['family']);
            $st->execute();
        }
    }
    $st->close();

    if ($cites) {
        $st = $conn->prepare("INSERT INTO phenotype_source_resource
            (source, resource_id, authors, year, title, container, doi, resource_type)
            VALUES (?,?,?,'',?,'WoRMS source record','','')
            ON DUPLICATE KEY UPDATE title=VALUES(title)");
        foreach ($cites as $sid => $ref) {
            $s = WORMS_SRC; $t = $ref;
            $st->bind_param('ssss', $s, $sid, $t, $t);
            $st->execute();
        }
        $st->close();
    }
    $conn->commit();

    echo "\n--- " . WORMS_TABLE . " 按来源 ---\n";
    $q = $conn->query("SELECT source, COUNT(*) n, SUM(n_records) sr, COUNT(DISTINCT species) sp
                       FROM `" . WORMS_TABLE . "` GROUP BY source ORDER BY n DESC");
    while ($r = $q->fetch_row()) {
        printf("  %-32s %9s 行  物种 %6s\n", $r[0], number_format((int)$r[1]), number_format((int)$r[3]));
    }
    $q = $conn->query("SELECT COUNT(*) FROM phenotype_species_map");
    printf("species_map %s 行\n", number_format((int)$q->fetch_row()[0]));
}
