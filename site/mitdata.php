<?php
session_start();
/* 审稿意见 Referee 2 major 1 / Referee 1 minor 1：物种门户（species_portal.php）以
 * ?species= 深链进本页，而本页此前只认 $_POST，且把物种写进全站共用的
 * $_SESSION['species'] —— 别的模块往那个键里存的是 abbr1 代码，互相覆盖后谁都认不出来。
 * 现改用 cnido_state()：GET 深链 > POST 提交 > 会话 > 默认值，会话键 mitdata_specie
 * 按模块命名空间隔离；本文件下方仍用原来的变量名 $species。 */
require_once __DIR__ . '/includes/state.php';
/* 环形图生成器：没有预制 PNG 的物种由它按特征坐标现画 */
require_once __DIR__ . '/includes/mitosvg.php';

$__st = cnido_state('mitdata', array(
    'specie' => array('get' => 'species', 'post' => 'species', 'default' => 'Porites lutea'),
    'clazz'  => array('get' => 'class',   'post' => 'Class',   'default' => ''),
));
$specie = $__st['specie'];

// ========== 安全改进②：SQL 预处理 ==========
/* 连接必须在下面的白名单之前建立：白名单现在直接从 mitochondrion 表现取。 */
$conn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
if ($conn->connect_error) {
    error_log('CnidoSite: database connection failed: ' . $conn->connect_error); 
    die('The database is temporarily unavailable. Please try again in a moment.');

}

// ========== 安全改进①：物种名白名单验证 ==========
/* 白名单曾经是一份手抄的清单，抄漏了 8 个在 mitochondrion 表里确有数据的物种
 * （Cassiopea xamachana、Chironex yamaguchii、Chrysaora quinquecirrha、
 * Hydra oligactis、Montipora efflorescens、Morbakka virulenta、Tamoya ohboya、
 * Turritopsis dohrnii）：这些物种用 ?species= 深链进来会被判为非法，
 * 页面显示「没有线粒体数据」，而数据其实在库里。同时首页把这个数报成 151、
 * Data Statistics 页报成 146、另一个标签又报成 78 —— 三处互相矛盾。
 *
 * 现在改为直接以数据库为准：凡 mitochondrion 表里出现过的物种即合法，
 * 数量与 includes/stats.php 的 mitochondrial 完全一致，也不会再随时间漂移。
 * 白名单的取值全部来自数据库本身，注入风险不变。 */
$allowed_species = [];
if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
    /* 两个来源取并集：mitochondrion 是基因级特征表；mito_genome 是基因组级统计表，
       里面有几个物种（NCBI 上只有全基因组散弹组装记录、没有单独的线粒体注释）
       只有长度和 GC、没有逐基因坐标，它们同样属于「本库有它的线粒体基因组」，
       必须能选，否则深链进来会被判非法、显示成「没有数据」。 */
    $__q = @mysqli_query($conn,
        "SELECT species FROM mitochondrion WHERE TRIM(COALESCE(species,'')) <> ''
         UNION
         SELECT species FROM mito_genome WHERE TRIM(COALESCE(species,'')) <> ''
         ORDER BY species");
    if ($__q) { while ($__r = mysqli_fetch_row($__q)) { $allowed_species[] = $__r[0]; } }
}
if (!$allowed_species) {
    /* 数据库不可用时退回原清单，保证页面还能用。 */
    $allowed_species = [
    "Alatina alata", "Acropora cervicornis", "Acropora digitifera", "Acropora florida", 
    "Acropora hyacinthus", "Acropora intermedia", "Acropora millepora", "Acropora muricata", 
    "Acropora nasuta", "Acropora palmata", "Acropora pulchra", "Acropora tenuis", 
    "Acropora yongei", "Actinia equina", "Actinia mediterranea", "Actinia tenebrosa", 
    "Actinodendron alcyonoideum", "Actinodendron arboreum", "Actinostella flosculifera", 
    "Anemonia sulcata", "Anemonia viridis", "Anthopleura artemisia", "Anthopleura sola", 
    "Anthopleura xanthogrammica", "Antipathozoanthus obscurus", "Antipathozoanthus remengesaui", 
    "Astreopora myriophthalma", "Bergia puertoricense", "Bunodosoma granuliferum", 
    "Churabana kuroshioae", "Colpophyllia natans", "Condylactis gigantea", "Dendrophyllia cribrosa", 
    "Desmophyllum pertusum", "Diadumene cincta", "Diadumene leucolena", "Diadumene lineata", 
    "Diploria labyrinthiformis", "Edwardsia elegans", "Entacmaea quadricolor", "Epizoanthus illoricatus", 
    "Epizoanthus ramosus", "Epizoanthus rinbou", "Epizoanthus scotinus", "Exaiptasia diaphana", 
    "Fimbriaphyllia ancora", "Fimbriaphyllia paradivisa", "Galaxea fascicularis", "Heteractis aurora", 
    "Heteractis magnifica", "Heteranthus verruculatus", "Hydrozoanthus antumbrosus", "Hydrozoanthus gracilis", 
    "Hydrozoanthus sils", "Hydrozoanthus tunicans", "Isopora palifera", "Madracis auretenra", 
    "Metridium farcimen", "Metridium senile", "Montipora cactus", "Oculina patagonica", "Orbicella annularis", 
    "Orbicella faveolata", "Orbicella franksi", "Palythoa caribaeorum", "Palythoa grandiflora", 
    "Palythoa grandis", "Palythoa heliodiscus", "Palythoa mizigama", "Palythoa mutuki", 
    "Paracondylactis sinensis", "Parazoanthus darwini", "Parazoanthus swiftii", "Pavona decussata", 
    "Phymanthus crucifer", "Phymanthus loligo", "Platygyra daedalea", "Pocillopora damicornis", 
    "Pocillopora grandis", "Pocillopora meandrina", "Podabacia crustacea", "Porites cylindrica", 
    "Porites harrisoni", "Porites lobata", "Porites lutea", "Porites rus", "Radianthus crispa", 
    "Ragactis hyalina", "Ricordea florida", "Scolanthus callimorphus", "Siderastrea radians", 
    "Sphenopus marsupialis", "Stephanocoenia intersepta", "Stichodactyla haddoni", "Stichodactyla helianthus", 
    "Stichodactyla mertensii", "Stichodactyla tapetum", "Stomphia didemon", "Stylophora pistillata", 
    "Thalassianthus aster", "Tubastraea coccinea", "Umimayanthus nakama", "Umimayanthus parasiticus", 
    "Urticina crassicornis", "Zoanthus gigantus", "Zoanthus pulchellus", "Zoanthus sansibaricus", 
    "Zoanthus sociatus", "Zoanthus solanderi", "Bythotiara depressa", "Cladonema radiatum", 
    "Clytia hemisphaerica", "Craspedacusta sowerbii", "Hydra vulgaris", "Nanomia bijuga", 
    "Scolionema suvaense", "Myxobolus squamalis", "Sphaerospora molnari", "Briareum asbestinum", 
    "Cavernularia obesa", "Corallium rubrum", "Dendronephthya gigantea", "Dichotella gemmacea", 
    "Erythropodium caribaeorum", "Eunicella cavolini", "Eunicella verrucosa", "Heliopora coerulea", 
    "Leptogorgia sarmentosa", "Paragorgia papillata", "Paramuricea clavata", "Phenganax marumi", 
    "Phenganax stokvisi", "Phenganax subtilis", "Stylatula elongata", "Aurelia aurita", "Aurelia coerulea", 
    "Aurelia sp. 3 sensu Dawson et al. (2005)", "Aurelia sp. 4 Dawson et al 2005", "Chrysaora achlyos", 
    "Mastigias albipunctata", "Nemopilema nomurai", "Pelagia noctiluca", "Phyllorhiza punctata", 
    "Rhopilema esculentum", "Sanderia malayensis", "Haliclystus inabai"
    ];
}

/* 物种已由文件顶部的 cnido_state() 解析，这里只做白名单校验（安全改进①）。 */
$species = $specie;
$mitoNotice = '';   // 传入物种没有线粒体数据时给出的提示

/* 深链可能给 abbr1 代码（如 NVECT）或下划线名，而本页查的是拉丁名：
 * 先翻译再校验，否则正常的深链会被白名单挡掉、退回默认物种。 */
if ($species !== '' && !in_array($species, $allowed_species, true)) {
    $__esc = mysqli_real_escape_string($conn, $species);
    $__q = mysqli_query($conn, "SELECT species FROM abbr WHERE abbr1 = '$__esc' OR abbr = '$__esc' OR species = '$__esc' LIMIT 1");
    if ($__q) { $__r = mysqli_fetch_row($__q); if ($__r) { $species = $__r[0]; } }
}
if ($species !== '' && !in_array($species, $allowed_species, true)) {
    $mitoNotice = $species;                 // 本页确实没有它的线粒体数据
    /* 退回本页数据最多的物种（Porites lutea，37 个线粒体基因 + 1 条基因组记录），
       而不是原来那个只有 23 个基因的 Acropora cervicornis —— 深链不到物种时
       给读者看到的应当是一张信息最全的图。Nematostella vectensis 在本页没有
       线粒体数据（mito_genome / mitochondrion 都是 0 行），故不能作默认。 */
    $species = 'Porites lutea';
}

/* ---------------------------------------------------------------
 * 类群下拉框
 *
 * 候选来自本页白名单里实际出现的类群（speciesinfo.Class），不再写死 7 项；
 * 尤其不再写 Hexactiniaria —— 数据库里没有这个类群名（speciesinfo / classfy
 * 用的是 Hexacorallia），站内因此有两个名字（Referee 1 minor 1）。
 *
 * 本页的结果表是服务端渲染的，所以类群切换走「带 ?class= 重新加载本页」，
 * 由服务端重建物种列表和结果表；否则下拉框换了、表格还是旧物种（Referee 2 major 1）。
 * --------------------------------------------------------------- */
$__allowedClass = cnido_species_class_map($allowed_species, $conn);
$__classes      = cnido_classes_in($__allowedClass);
/* 旧链接可能带 ?class=Hexactiniaria：归一成 Hexacorallia，不当作「没有这个类群」 */
$__reqClass     = cnido_class_canon($__st['clazz']);

/* 只给了类群（下拉框 onchange 重新加载）而没直接指定物种：落到该类群下
   白名单里的第一个物种，保证下拉框与结果表一致 */
if (in_array($__st['clazz__from'], array('get', 'post'), true)
    && !in_array($__st['specie__from'], array('get', 'post'), true)
    && in_array($__reqClass, $__classes, true)) {
    foreach ($allowed_species as $__sp) {
        if (isset($__allowedClass[$__sp]) && $__allowedClass[$__sp] === $__reqClass) {
            $species = $__sp; $specie = $__sp; break;
        }
    }
}

/* 请求的类群在本页白名单里一个物种都没有：说清楚，而不是默默留在默认类群 */
$mitoClassNotice = (in_array($__st['clazz__from'], array('get', 'post'), true)
                    && $__reqClass !== ''
                    && !in_array($__reqClass, $__classes, true))
                 ? $__reqClass : '';

/* 类群下拉框的选中项跟随最终物种 */
$__selClass = isset($__allowedClass[$species]) ? $__allowedClass[$species]
            : (isset($__classes[0]) ? $__classes[0] : '');

$stmt = $conn->prepare("SELECT * from mitochondrion where species = ?");
$stmt->bind_param("s", $species);
$stmt->execute();
$result = $stmt->get_result()->fetch_row();

/* ------------------------------------------------------------------
 * 线粒体基因组整合信息
 *
 * 原先这一页只有 mitochondrion 的逐基因坐标表，看不到「这个基因组有多大、
 * GC 多少、编码多少个基因」，也没有标明数据取自哪条 NCBI 记录、什么时候取的。
 * mito_genome 表（由 NCBI GenBank 记录解析而来）补齐了这些；下面还用它和
 * 特征表一起画一张环形图，使没有预置 PNG 的物种也有图可看。
 * ------------------------------------------------------------------ */
$mitoGenome = null;
$mitoFeats  = array();
if ($species !== '') {
    /* species / abbr1 必须一起取回来：下面 $dispName 和 $abbr 分别读
       $mitoGenome['species'] 与 $mitoGenome['abbr1']，而这一类物种
       （只在 mito_genome 里有、mitochondrion 里没有的那 6 个）$result 为空，
       两个值只能从这里来。漏取时 fetch_assoc() 里没有这两个键，
       $dispName 变成空串 —— 页面上就是 "No circular map is available for :"
       这种名字是空的句子，$abbr 也变空，顺带把下载探测整块跳过。 */
    if ($stmtG = @$conn->prepare("SELECT species, abbr1, accession, size_bp, gc_percent, n_genes, n_trna, n_rrna, source, retrieved
                                  FROM mito_genome WHERE species = ? LIMIT 1")) {
        $stmtG->bind_param('s', $species);
        $stmtG->execute();
        $mitoGenome = $stmtG->get_result()->fetch_assoc();
        $stmtG->close();
    }
    /* 逐特征表。两处与表里的内容有关，都写在这里而不是留给页面：
     *
     * ① 去重。GenBank 里每个 tRNA / rRNA 都同时挂一条 `gene` 特征（只有 /locus_tag、
     *    没有 /gene），解析后成了同坐标同名的一条 gene 行 —— 例如 Porites lutea 的
     *    `tRNA-Ile 1335..1402` 和 `A3267_gt01 1335..1402`。全库有 427 条这样的影子行，
     *    原样印出来每个 tRNA / rRNA 都出现两次，读者会以为重复录入。
     *    判据是「同物种、同起点、同终点、同链上另有一条 trna/rrna 行」—— 同名同坐标
     *    的两个特征本来就是同一个东西，丢掉的那条不带任何信息。
     *
     * ② 排序。Start / End 都是 TEXT 列，`ORDER BY Start` 是字典序：
     *    1, 1, 10197, 10885, 11846, 13348, 1335, 1335, 1632… —— 表里的坐标是乱的。
     *    改成按数值排。
     */
    if ($stmtF = @$conn->prepare(
            "SELECT Name, Type, Start, End, Length, Strand FROM mitochondrion
              WHERE species = ?
                AND NOT (Type = 'gene'
                         AND EXISTS (SELECT 1 FROM mitochondrion m2
                                      WHERE m2.species = mitochondrion.species
                                        AND m2.Start   = mitochondrion.Start
                                        AND m2.End     = mitochondrion.End
                                        AND m2.Strand  = mitochondrion.Strand
                                        AND m2.Type IN ('trna','rrna')))
              ORDER BY CAST(Start AS UNSIGNED), CAST(End AS UNSIGNED)")) {
        $stmtF->bind_param('s', $species);
        $stmtF->execute();
        $rs = $stmtF->get_result();
        while ($row = $rs->fetch_assoc()) { $mitoFeats[] = $row; }
        $stmtF->close();
    }
}

/* 被内含子切开的基因，GenBank 记成两条：一条 `gene` 覆盖整个跨度，一条
   `CDS join(外显子…)`。两条都被解析成 Type='gene' 的行，于是表里同一个基因出现
   两三次、Length 各不相同 —— Porites lutea 的 COX1 是 1..894 / 1..2543 / 1866..2543
   三行，ND5 是 5199..5921 / 5199..18167 / 17052..18167 三行。全库只有 COX1 和 ND5
   这两个基因会出现（它们是刺胞动物线粒体里仅有的两个反式剪接基因），已对着
   download/PLUTE.gb、AEQUI.gb、ACERV.gb 核对过 join() 的分段与这几行的坐标一致。
   这里不新增数据，只在显示时把「严格落在同物种同名的另一条 gene 行内部」的那些行
   标成 CDS（外显子），让读者一眼看出哪条是整基因、哪几条是它的外显子。
   顺便得到真正的蛋白编码基因数：不在别人内部、且名字只算一次的 gene 行数。 */
$nFeat = count($mitoFeats);
for ($i = 0; $i < $nFeat; $i++) {
    $mitoFeats[$i]['PartOf'] = false;
    if ($mitoFeats[$i]['Type'] !== 'gene') { continue; }
    for ($j = 0; $j < $nFeat; $j++) {
        if ($i === $j || $mitoFeats[$j]['Type'] !== 'gene') { continue; }
        if ($mitoFeats[$j]['Name'] !== $mitoFeats[$i]['Name'])      { continue; }
        if ($mitoFeats[$j]['Strand'] !== $mitoFeats[$i]['Strand'])  { continue; }
        $aS = (int)$mitoFeats[$i]['Start']; $aE = (int)$mitoFeats[$i]['End'];
        $bS = (int)$mitoFeats[$j]['Start']; $bE = (int)$mitoFeats[$j]['End'];
        if ($aS === $bS && $aE === $bE) { continue; }      // 同一条，不算「在里面」
        if ($aS >= $bS && $aE <= $bE) { $mitoFeats[$i]['PartOf'] = true; break; }
    }
}
/* 排一下序：整体那一行排在同一基因的外显子前面。COX1 和 ND5 的外显子与基因本身
   起点相同（COX1 的 1..894 和 1..2543），只按坐标排会让外显子跑在基因上面，
   读者先看到「一段」再看到「整体」。SQL 里已经按数值排过，这里只在这一种情况下调整。 */
usort($mitoFeats, function ($a, $b) {
    if (($c = (int)$a['Start'] - (int)$b['Start']) !== 0) { return $c; }
    if (($c = (int)$a['PartOf'] - (int)$b['PartOf']) !== 0) { return $c; }
    return (int)$a['End'] - (int)$b['End'];
});

/* 表头概况里的蛋白编码基因数。mito_genome.n_genes 是 NCBI 记录里 `gene` 特征的总数
   （等于 蛋白编码 + tRNA + rRNA），当作「蛋白编码基因」印出来会偏大 ——
   Porites lutea 是 23，而真实的蛋白编码基因是 13。有逐特征行时按上面的口径数出来，
   没有时保持 NULL 让 $cnt 显示破折号（那 6 个物种的 n_genes 本来就是 0 或 NULL）。 */
$nPCG = null;
if ($mitoFeats) {
    $pcg = array();
    foreach ($mitoFeats as $f) {
        if ($f['Type'] === 'gene' && !$f['PartOf']) { $pcg[(string)$f['Name']] = true; }
    }
    $nPCG = count($pcg);
}

/* tRNA / rRNA 数用同一口径从特征表数。mito_genome.n_trna / n_rrna 在 Madracis
   auretenra、Radianthus crispa、Alatina alata 这三个物种上是 0，而同一页下面的
   特征表里 trna / rrna 行是有的（前两个各 2 条 tRNA + 2 条 rRNA）—— 上面印 0、
   下面列着行，读者会以为表里那几行是多余的。有逐特征行时一律以特征表为准；
   没有时保持 null，交回给 mito_genome（$cnt 再按「没注释」显示破折号）。
   这里数的是行数不是去重名：同一种 tRNA 在环状基因组上出现两次是两个特征。 */
$nTRNA = null; $nRRNA = null;
if ($mitoFeats) {
    $nTRNA = 0; $nRRNA = 0;
    foreach ($mitoFeats as $f) {
        if ($f['Type'] === 'trna')      { $nTRNA++; }
        elseif ($f['Type'] === 'rrna')  { $nRRNA++; }
    }
}

/* 该物种的缩写：特征表有行就用特征表的，只有基因组统计的物种从 mito_genome 取。
   两个都没有时为空串，页面按「没有数据」处理。 */
$abbr = '';
if ($result)            { $abbr = (string)$result[1]; }
elseif ($mitoGenome)    { $abbr = (string)$mitoGenome['abbr1']; }

/* 下载文件名。改为逐个探测文件是否存在 —— 原来无条件列出五个文件名，
   文件不在时 download_fun.php 返回 404，读者点下去只会以为下载坏了
   （审稿意见 Referee 3 点 10「请确认所有数据都能正常访问与下载」）。
   mito_genome 新并入的物种没有逐基因注释，也就没有这些文件，此处自然为空。 */
$genome = '';
$gb     = '';
$cds    = '';
$pep    = '';
$gff    = '';
if ($abbr !== '' && strpbrk($abbr, '/\\') === false) {
    $cand = array(
        'genome'  => $abbr . '.fna',
        'gb'      => $abbr . '.gb',
        'cds'     => $abbr . '_cds.fna',
        'pep'     => $abbr . '_pep.faa',
        'gff'     => $abbr . '.gff3',
    );
    foreach ($cand as $k => $fn) {
        if (is_file(__DIR__ . '/download/' . $fn)) { $$k = $fn; }
    }
    unset($cand);
}

// 安全输出函数
function safe($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * 找某个物种的线粒体图。
 *
 * 只在 images/MT/ 里找（147 张，5000×5000 的环状图）。这里**曾经**还回退到
 * images/images/，理由是「那个目录包含 MT 的全部」—— 这个前提是错的：
 * images/images/ 是 speciesinfo.php 的物种照片集，305 张里 298 张正好是
 * 2408×2594 的活体照片，一张线粒体图都没有，两个目录同名的 143 个文件
 * 内容也不一样。回退的结果是 20 个没有线粒体图的物种被发出了一张活体照片，
 * 还带着 alt="Mitochondrial genome map of …" —— 读者会以为那就是它的线粒体图。
 * 真的没有就返回空串，让调用处走「按坐标现画」或「说明没有图」的分支；
 * 放一张不相干的图比不放图更糟。
 *
 * @return string 相对 URL；images/MT/ 里没有时返回空串
 */
function cnido_mito_map($abbr)
{
    $abbr = trim((string)$abbr);
    /* 文件名全部来自数据库，但仍挡一道目录分隔符，避免拼出越界路径 */
    if ($abbr === '' || strpbrk($abbr, '/\\') !== false) { return ''; }
    foreach (array('images/MT/') as $dir) {
        $f = __DIR__ . '/' . $dir . $abbr . '.png';
        if (is_file($f) && filesize($f) > 0) { return $dir . $abbr . '.png'; }
    }
    return '';
}
$mitoMap = ($abbr !== '') ? cnido_mito_map($abbr) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta charset="utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">  
<meta name="renderer" content="webkit">
<title>Mitogenomic Data - CnidoSite</title>
<meta name="keywords" content="" />
<meta name="description" content="Mitochondrial genomes of cnidarian species: an interactive map and detailed annotation, with downloads of the genome, CDS, protein and GFF files." />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<script LANGUAGE="JavaScript" src="js/func.js?v=1790644208" type="text/javascript"></script>
<script LANGUAGE="JavaScript" src="js/cnido-species-filter.js" type="text/javascript"></script>
<script>
    <?php /* 审稿意见 Referee 2 major 1：物种下拉框原来由 DynamicOptionList 按 class 生成，
     * printOptions() 在现代浏览器里是空操作；initDynamicOptionLists() 又会在 onLoad 时
     * 清空重建下拉框（js/DynamicOptionList.js 里 child.options.length=0），
     * 会抹掉服务端渲染好的 option。选项现已服务端渲染，故不再注册该组件。 */ ?>
</script>

<style>
<?php /* 表单容器美化 */ ?>
.mirna-form-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    padding: 35px;
    margin-top: 20px;
    border: 1px solid #e2e8f0;
    max-width: 800px;
    margin-left: auto;
    margin-right: auto;
}

.form-title {
    font-size: 20px;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-title::before {
    content: "🧬";
    font-size: 24px;
}

.form-group {
    margin-bottom: 25px;
}

.form-label {
    display: block;
    font-size: 16px;
    font-weight: 600;
    color: #475569;
    margin-bottom: 10px;
}

.form-select {
    width: 100%;
    padding: 14px 18px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    font-size: 16px;
    background: white;
    color: #1e293b;
    cursor: pointer;
    transition: all 0.3s ease;
    appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 12px center;
    background-repeat: no-repeat;
    background-size: 20px;
}

.form-select:focus {
    outline: none;
    border-color:#6d28d9;
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
}

.form-select option {
    padding: 10px;
}

.button-group {
    display: flex;
    gap: 15px;
    margin-top: 30px;
}

.btn-submit, .btn-reset {
    flex: 1;
    padding: 14px 30px;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-submit {
    background:linear-gradient(135deg, #6d28d9 0%, #5b21b6 100%);
    color: white;
    border: none;
    box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
}

.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(139, 92, 246, 0.4);
    background: linear-gradient(135deg, #6d28d9 0%, #5b21b6 100%);
}

.btn-reset {
    background: #f8fafc;
    color: #64748b;
    border: 2px solid #e2e8f0;
}

.btn-reset:hover {
    background: #f1f5f9;
    color: #475569;
    border-color: #cbd5e1;
}

/* 窄屏：两个按钮各自被文字卡在 min-content（166px / 135px），合起来 316px 装不进
   表单 246px 的内容盒，右边会顶出白色卡片（360px 下顶到 373）。改成竖排、各占
   一行；竖排里必须写 flex:0 0 auto —— flex:1 的 0% basis 在 column 方向是高度基准。 */
@media (max-width:480px) {
    .button-group {
        flex-direction: column;
        gap: 12px;
    }
    .btn-submit, .btn-reset {
        flex: 0 0 auto;
    }
}

    <?php /* 提交按钮 */ ?>
    input.button {
        background: linear-gradient(135deg, #2a5298, #1e3c72);
        color: white;
        border: none;
        padding: 10px 34px;
        border-radius: 24px;
        font-weight: 600;
        font-size: 16px;
        cursor: pointer;
        transition: all 0.25s ease;
        letter-spacing: 0.5px;
    }
    input.button:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 18px rgba(42,82,152,0.25);
    }
    input.button:active {
        transform: scale(0.96);
    }

    <?php /* 主内容卡片（左右两栏） */ ?>
    .mito-detail-card {
        display: flex;
        flex-wrap: wrap;
        gap: 30px;
        margin-top: 20px;
        background: white;
        border-radius: 16px;
        border: 1px solid #e6ecf2;
        padding: 24px;
        box-shadow: 0 2px 16px rgba(0,0,0,0.04);
    }
    .mito-left {
        flex: 1 1 380px;
        min-width: 300px;
    }
    .mito-left p {
        margin-bottom: 10px;
        font-size: 16px;
    }
    .mito-left p b {
        display: inline-block;
        width: 140px;
        color: #2a5298;
        font-weight: 600;
    }
    .mito-left img {
        width: 100%;
        max-width: 740px;
        border-radius: 12px;
        margin-top: 12px;
        box-shadow: 0 4px 16px rgba(0,0,0,0.07);
    }

    <?php /* 占位图说明：只在真的没有该物种线粒体图时出现 */ ?>
    .mito-nomap {
        font-size: 16px;
        color: #92400e;
        background: #fffbeb;
        border: 1px solid #fde68a;
        border-radius: 10px;
        padding: 10px 14px;
        margin-top: 10px;
    }

    <?php /* 基因组级概况表：长度 / GC / 基因数 / tRNA / rRNA / 来源
       表本身挂 gridtable：表头底色、行分隔线、留白、字号、斑马纹全部由
       templatemo_style.css 的共用样式提供（与 core 的 table.cc 一致）。
       原来这张表每格描一圈 1px #e6ecf2、表头刷成 #f4f8fc 底 + #2a5298 蓝字，
       是站里最后几处还带完整网格线的表之一；本页右栏的表格已经是共用观感，
       同一页上两套样式并存看着就不像一回事，所以把方框撤掉。
       这里只留外边距、宽度，以及左对齐 —— 这是一张「标签 / 值」表，值里既有
       数字也有 Source 这种文字，左对齐比居中好读。 */ ?>
    .mito-summary {
        width: 100%;
        max-width: 740px;
        margin: 6px 0 4px;
        font-size: 15px;
    }
    .mito-summary th { width: 168px; vertical-align: middle; }
    .mito-summary tr th,
    .mito-summary tr td { text-align: left; vertical-align: middle; }

    <?php /* 只有基因组统计、没有逐基因注释时的右栏说明 */ ?>
    .mito-nofeat {
        background: #f4f8fc;
        border: 1px solid #e6ecf2;
        border-radius: 12px;
        padding: 16px 18px;
        font-size: 16px;
        color: #425466;
    }
    .mito-nofeat h3 {
        margin: 0 0 8px;
        font-size: 1rem;
        color: #2a5298;
    }
    .mito-nofeat p { margin: 0; line-height: 1.6; }

    .mito-right {
        flex: 1 1 420px;
        min-width: 320px;
        display: flex;
        flex-direction: column;
        margin-top: 68px;
    }
    .mito-right .toolbar {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 10px;
        margin-bottom: 14px;
    }
    .mito-right .toolbar select {
        height: 38px;
        padding: 0 30px 0 12px;
        border: 1.5px solid #d0d7de;
        border-radius: 8px;
        font-size: 15px;
        background: white url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 448 512'%3E%3Cpath fill='%23666' d='M207.029 381.476L12.686 187.132c-9.373-9.373-9.373-24.569 0-33.941l22.667-22.667c9.357-9.357 24.522-9.375 33.901-.04L224 284.505l154.746-156.812c9.379-9.335 24.544-9.317 33.901.04l22.667 22.667c9.373 9.373 9.373 24.569 0 33.941L240.971 381.476c-9.372 9.371-24.568 9.37-33.942 0z'/%3E%3C/svg%3E") no-repeat right 12px center;
        appearance: none;
        cursor: pointer;
    }
    input.button2 {
        background: #2a5298;
        color: white;
        border: none;
        padding: 8px 22px;
        border-radius: 20px;
        font-weight: 500;
        font-size: 15px;
        cursor: pointer;
        transition: all 0.2s;
    }
    input.button2:hover {
        background: #1e3c72;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(42,82,152,0.2);
    }

    <?php /* 表格容器 */ ?>
    .scrollable-container {
        height: 750px;
        overflow-y: auto;
        border-radius: 12px;
        border: 1px solid #e6ecf2;
        background: white;
    }
    .scrollable-container::-webkit-scrollbar {
        width: 6px;
    }
    .scrollable-container::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 8px;
    }
    .scrollable-container::-webkit-scrollbar-track {
        background: transparent;
    }

    <?php /* 反式剪接基因的外显子行（见 $mitoFeats 上方 PartOf 的说明）。
       Type 那一格印 CDS 而不是 gene，用灰蓝色和真正的 gene 行区分开。 */ ?>
    .mito-part {
        color:#0369a1;
        font-weight: 600;
        cursor: help;
    }

    <?php /* 特征表下方的口径说明 */ ?>
    .mito-note {
        margin: 10px 0 0;
        padding: 10px 14px;
        background: #f8fafc;
        border-left: 3px solid #94a3b8;
        border-radius: 6px;
        color: #475569;
        font-size: 16px;
        line-height: 1.65;
    }
    .mito-note code { font-size: 16px; color:#334155; }

    <?php /* 表格：配色、留白、斑马纹、悬停全部交给 templatemo_style.css 的
       table.gridtable 那份共用样式（与 core 的 table.cc 一致）。这里只留
       本页特有的一条：表头吸附。

       原来这段把表头刷成蓝色渐变（#f0f4ff / #1e3c72）、td 加 10px 留白，
       是当年为了绕开旧的红框表格；现在共用样式已经是这个样子，留着只会
       让这页的表头和别的页不一样。注意原来的斑马纹写在 tr 上，而 td 有
       不透明底色会盖住它——那两条其实一直是死的。

       吸附的表头必须有不透明底色，否则滚动时能看见下面的行穿过去；底色
       由共用样式的 th 规则提供，这里不要重复声明 color/background。 */ ?>
    .gridtable thead th {
        position: sticky;
        top: 0;
        z-index: 2;
    }

    <?php /* 清除浮动 */ ?>
    .cleaner {
        clear: both;
    }

    <?php /* 响应式 */ ?>
    @media (max-width: 992px) {
        <?php /* 竖排之后必须同时收掉 flex-wrap。多行 flex 容器里 flex line 的
           交叉尺寸取的是**项目自己的 max-content**，不是容器宽，
           align-items:stretch 于是完全不起作用 —— 竖排的 .mito-left /
           .mito-right 会各自按内容撑到 664px，375px 屏上整页被顶到 710。
           单行 + nowrap 之后 line 的交叉尺寸就等于容器宽，两栏才会被拉到栏内。 */ ?>
        .mito-detail-card {
            flex-direction: column;
            flex-wrap: nowrap;
        }
        <?php /* flex:1 1 380px 里的 380 在竖排时是**高度**基准，留着会把两栏各撑成
           380/420 高；min-width:300/320 则是横向的硬下限，不清掉照样溢出。 */ ?>
        .mito-left, .mito-right {
            flex: 0 0 auto;
            min-width: 0;
        }
        .mito-left img {
            width: 100%;
        }
        form[name="atidsearch"] table {
            width: 100%;
        }
    }
</style>
</head>

<body onLoad="cnidoFilterAll();">
<div id="templatemo_header_wrapper">
    <div id="templatemo_header">
        <div id="site_logo"></div>
    </div>
</div>

<?php /*导航栏保持不变*/ ?>
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
            <li><a href="/paleobiology.php">Paleobiology</a></li>
            <li><a href="#" class="current">Genome</a>
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
                    <li><a href="/phylotree/">Species Tree</a></li><li><a href="/core/">Core Orthologs</a></li>
                    <li><a href="/microsynteny.php">Microsynteny Analysis</a></li>
                    <li><a href="/macrosynteny.php">Macrosynteny Analysis</a></li>
                    <li><a href="/mitdata.php">Mitogenomic Data</a></li>
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

<legend><img src="./images/header.jpg" height="35px" style="margin-bottom:-10px">&nbsp;<b>Mitogenomic Data</b></legend>
<p  class="paleo-intro">In this module, the mitochondrial map and the detailed annotation are provided for cnidarian species. Select a species to see its detailed information and click the button to download the corresponding data, including the genome, cds, pep and gff files.</p>

<?php if ($mitoNotice !== ''): ?>
    <?php /* 深链进来的物种本页没有线粒体数据时明确说明，避免使用者以为选择器没生效 */ ?>
    <div class="gd-notice gd-warn">
        No mitochondrial record for <i><?= safe($mitoNotice) ?></i> yet; showing
        <i><?= safe($species) ?></i> instead. There are
        <b><?= count($allowed_species) ?></b> species with a mitochondrial genome in CnidoSite.
    </div>
<?php endif; ?>

<?php if ($mitoClassNotice !== ''): ?>
    <?php /* 请求的类群在本页没有物种时说明清楚 */ ?>
    <div class="gd-notice gd-warn">
        No mitochondrial genome in the current release belongs to class
        <i><?= safe($mitoClassNotice) ?></i>; showing
        <i><?= safe($species) ?></i> (<?= safe($__selClass) ?>) instead.
    </div>
<?php endif; ?>

<?php /* 表单容器 */ ?>
<div class="mirna-form-container">
    <div class="form-title">Select Species for mitochondrial map and detailed annotation</div>
    
    <form name="atidsearch" method="post" onSubmit="return checkquery()" encType="multipart/form-data">
        <div class="form-group">
            <label class="form-label">Select Class</label>
            <?php /* 切换类群用 GET 重新加载本页：本页结果表是服务端渲染的，
                     纯前端过滤不会更新表格 */ ?>
            <?= cnido_class_select($__classes, $__selClass, 'Class', '1', 'form-select',
                  "window.location.href='mitdata.php?class='+encodeURIComponent(this.value)") ?>
        </div>
        
        <div class="form-group">
            <label class="form-label">Select Species</label>
            <?php /* cnido_species_select() 自己会输出完整的 <select>…</select>。
                     这里原来在外面又套了一层 <select name="species">，两个开标签一先一后——
                     HTML 解析器在 select 内部遇到新的 <select> 会把外层直接弹栈关掉，
                     于是外层变成一个 0 选项的空下拉框（页面上那个空白方框），
                     而里面的 154 个 <option> 全部掉到 select 之外，作为普通文本铺在
                     卡片里（截图中那串被裁掉的物种名）。同时带 data-cnido-species 的
                     元素不复存在，js/cnido-species-filter.js 的「选类群→过滤物种」也随之失效。
                     生成器已经带 name/class/data 属性，不要再包。 */ ?>
            <?= cnido_species_select($allowed_species, $__allowedClass, $species, 'species', '1', 'form-select') ?>
        </div>
        
        <div class="button-group">
            <button type="submit" class="btn-submit">
                🔍 View mitochondrial Details
            </button>
            <button type="reset" class="btn-reset">
                ↺ Reset Selection
            </button>
        </div>
    </form>
</div>

<?php /* 使用新的卡片容器替换原有的内联样式布局 */ ?>
<?php
/* 有特征行、或只有基因组级统计，都算「本库有这个物种的线粒体基因组」。
   后者（mito_genome 里有、mitochondrion 里没有的 6 个物种）此前完全进不来，
   读者深链过来只会看到一个空白页。 */
if ($species && ($result || $mitoGenome)):
    $dispName = $result ? $result[0] : $mitoGenome['species'];
    $dispAcc  = ($result && $result[2] !== '') ? $result[2] : ($mitoGenome ? $mitoGenome['accession'] : '');
    $svg      = $mitoFeats ? cnido_mito_svg($mitoFeats, $mitoGenome ? $mitoGenome['size_bp'] : 0, '') : '';
?>
<div class="mito-detail-card">
    <div class="mito-left">
        <p><b>Organism</b>:&nbsp;&nbsp;<a href="/speciesinfo.php?species=<?php echo safe($abbr); ?>"><?php echo safe($dispName); ?></a></p>
        <?php if ($dispAcc !== ''): ?>
        <p><b>Accession Number</b>:&nbsp;&nbsp;<a href="https://www.ncbi.nlm.nih.gov/nuccore/<?php echo safe($dispAcc); ?>" target="_blank"><?php echo safe($dispAcc); ?></a></p>
        <?php endif; ?>

        <?php if ($mitoGenome): ?>
        <?php /* 基因组级概况。原先页面上只有逐基因坐标，看不到这个基因组多大、
                 GC 多少、编码多少个基因，也没有标注数据取自哪条记录、何时取回。
                 Referee 2 major 9 要求数据可溯源，这几项即是出处。

                 计数列为 0 且本物种没有逐基因注释行时，0 的含义是「记录未做注释」
                 而不是「这个基因组没有基因」—— 后者会误导读者，改显示破折号，
                 由右栏的 Genome-level data only 说明情况。 */
              $cnt = function ($v) use ($mitoFeats) {
                  if ($v === null) { return '&mdash;'; }
                  if (!$mitoFeats && (int)$v === 0) { return '&mdash;'; }
                  return (string)(int)$v;
              }; ?>
        <table class="gridtable mito-summary">
            <tr><th>Genome size</th><td><?php echo $mitoGenome['size_bp'] !== null ? number_format((float)$mitoGenome['size_bp']) . ' bp' : '&mdash;'; ?></td>
                <th>GC content</th><td><?php echo $mitoGenome['gc_percent'] !== null ? safe($mitoGenome['gc_percent']) . ' %' : '&mdash;'; ?></td></tr>
            <tr><th>Protein-coding genes</th><td><?php echo $cnt($nPCG); ?></td>
                <th>tRNA genes</th><td><?php echo $cnt($nTRNA !== null ? $nTRNA : $mitoGenome['n_trna']); ?></td></tr>
            <tr><th>rRNA genes</th><td><?php echo $cnt($nRRNA !== null ? $nRRNA : $mitoGenome['n_rrna']); ?></td>
                <th>Source</th><td><?php echo safe($mitoGenome['source']); ?><?php echo $mitoGenome['retrieved'] ? ' (' . safe($mitoGenome['retrieved']) . ')' : ''; ?></td></tr>
        </table>
        <?php endif; ?>

        <?php if ($mitoMap !== ''): ?>
            <img src="<?php echo safe($mitoMap); ?>" width="800px" style="margin-bottom:-10px"
                 alt="Mitochondrial genome map of <?php echo safe($dispName); ?>">
        <?php elseif ($svg !== ''): ?>
            <?php /* 没有预制 PNG，但特征坐标齐全：按坐标现画一张，比放占位图有用得多 */ ?>
            <?php echo $svg; ?>
            <?php echo cnido_mito_svg_legend(); ?>
        <?php else: ?>
            <?php /* 特征坐标也没有（该物种在 NCBI 上只有全基因组散弹组装记录，
                     没有单独的线粒体注释）：连图都画不出来。
                     这里**不放** default.png —— 那会让人以为看到的是该物种的线粒体图，
                     而它其实只是一张通用示意图；只留一段说明，读者不会误解。 */ ?>
            <p class="mito-nomap">No circular map is available for
                <i><?php echo safe($dispName); ?></i>: the NCBI record for this species
                (<?php echo safe($dispAcc); ?>) reports the mitochondrial genome size and base
                composition, but contains no per-gene annotation to draw from.</p>
        <?php endif; ?>
    </div>
    <div class="mito-right">
        <?php if ($result): ?>
        <?php if ($genome !== '' || $gb !== '' || $cds !== '' || $pep !== '' || $gff !== ''): ?>
        <div class="toolbar">
            <select id="fileSelect">
                <?php if ($genome !== ''): ?><option value="<?php echo safe($genome);?>">genome</option><?php endif; ?>
                <?php if ($gb     !== ''): ?><option value="<?php echo safe($gb);?>">genbank</option><?php endif; ?>
                <?php if ($cds    !== ''): ?><option value="<?php echo safe($cds);?>">cds</option><?php endif; ?>
                <?php if ($pep    !== ''): ?><option value="<?php echo safe($pep);?>">pep</option><?php endif; ?>
                <?php if ($gff    !== ''): ?><option value="<?php echo safe($gff);?>">gff3</option><?php endif; ?>
            </select>
            <input type="button" value="Download" class="button2" onClick="downloadFile()">
        </div>
        <?php endif; ?>
        <div class="scrollable-container">
            <table class="gridtable">
                <thead style="position: sticky;top: 0;z-index: 100;border:1">
                    <tr><th>Name</th><th>Type</th><th>Start</th><th>End</th><th>Length</th><th>Strand</th></tr>
                </thead>
                <tbody>
                <?php
                    foreach ($mitoFeats as $row) {
                        /* 被内含子切开的那几个基因：整体那一行印 gene，它的每一段
                           （GenBank 里的 CDS join 分段）印 CDS，不然同一个名字连着出现
                           三行、Length 各不相同，看起来像重复数据。见上方 PartOf 的说明。 */
                        $isPart = !empty($row['PartOf']);
                        echo "<tr align='center'>";
                        echo "<td>" . safe($row['Name'])   . "</td>";
                        echo "<td>" . ($isPart
                                ? "<span class='mito-part' title='One exon of this gene: GenBank records it as a CDS join() over the span of the same-named gene row (not necessarily the row directly above this one)'>CDS</span>"
                                : safe($row['Type'])) . "</td>";
                        echo "<td>" . safe($row['Start'])  . "</td>";
                        echo "<td>" . safe($row['End'])    . "</td>";
                        echo "<td>" . safe($row['Length'] ?? '') . "</td>";
                        echo "<td>" . safe($row['Strand']) . "</td>";
                        echo "</tr>";
                    }
                ?>
                </tbody>
            </table>
        </div>
        <?php /* 表格的口径说明。三件事都是读者看完表就会问的：
                 坐标按数值排（原来不是，见查询里的注释）、
                 CDS 行是什么、以及为什么每个 tRNA / rRNA 只出现一次。 */ ?>
        <p class="mito-note">
          Rows are ordered by <strong>start coordinate</strong>. <strong>CDS</strong> marks one
          exon of the same-named gene &mdash; not the row above it, since that sort interleaves a
          gene&rsquo;s exons with the tRNAs between them. <i>COX1</i> and <i>ND5</i> are
          <strong>trans-spliced</strong>: GenBank lists each as a full-span <code>gene</code>
          plus a <code>CDS join(&hellip;)</code> of its exons, and both appear here. A bare
          <code>gene</code> feature duplicating an annotated tRNA or rRNA is omitted.
          Coordinates, lengths and strand are NCBI&rsquo;s own.
        </p>
        <?php else: ?>
        <?php /* 基因组级统计有、逐基因注释没有的物种 */ ?>
        <div class="mito-nofeat">
            <h3>Genome-level data only</h3>
            <p>The NCBI record for <i><?php echo safe($dispName); ?></i>
               (<?php echo safe($dispAcc); ?>) provides the mitochondrial genome assembly and its
               size, GC content and gene counts, but no annotated gene coordinates. There is
               therefore no gene table and no per-gene annotation file to download for this
               species.</p>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

</div>
</div>
</div>
<script>
function downloadFile() {
    var selectElement = document.getElementById("fileSelect");
    var selectedFile = selectElement.options[selectElement.selectedIndex].value;
    window.location = "download_fun.php?download=download&fname=" + encodeURIComponent(selectedFile);
}
document.querySelector('form').addEventListener('submit', function() {
  const algValue = document.querySelector('select[name="Class"]').value;
  const speciesValue = document.querySelector('select[name="species"]').value;
  localStorage.setItem('selectedAlg', algValue);
  localStorage.setItem('selectedSpecies', speciesValue);
});
</script>
<?php
    include "Webpage_components.php";
    print $footer;
?>
</body>
</html>