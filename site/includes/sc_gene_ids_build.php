<?php
/* =====================================================================
 * 由 singlecell_data/_gene_ids.tsv 生成每个数据集的 gene_ids.json。仅限命令行。
 *
 *   php includes/sc_gene_ids_build.php            # 生成
 *   php includes/sc_gene_ids_build.php --check    # 只校验，不写文件
 *
 * _gene_ids.tsv 是主表：species / sc_id / accession / locus / source 五列，
 * 由 2026-09-23 那次映射整理而来（见 Response_to_Reviewers 与 memory）。它是
 * **证据**，不要手改内容去凑页面；要加映射就加行，然后在下面 add_source()
 * 里写清这一行的凭据是什么。
 *
 * 为什么要有这个脚本而不是让页面自己去查：映射的来源是各物种蛋白质组的
 * reciprocal best hit（diamond 跑的）与两张论文附表读出来的对应关系，这些
 * 计算在网页上下文里做不了。脚本把结果摊平成 sidecar，页面只读不猜。
 *
 * 两道校验，任何一道不过就整体不写文件（宁可侧车缺失走降级，也不能写进
 * 一半错的映射）：
 *   1) 只有出现在该数据集 genes.json 里的号才进 sidecar —— 侧车是给这个
 *      数据集的基因列表用的，掺进别的号只会在反查时制造歧义。
 *   2) accession 必须能在对应 <ABBR>_locus.mRNA 里查到。查不到的行丢弃并
 *      计数报告 —— 「复制过去能打开 gene_detail.php」是这张表的全部意义。
 * ===================================================================== */

if (PHP_SAPI !== 'cli') {
    header('HTTP/1.1 403 Forbidden');
    echo "CLI only\n";
    exit(1);
}

$conn = @new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
if ($conn->connect_error) {
    fwrite(STDERR, "DB connect failed: " . $conn->connect_error . "\n");
    exit(1);
}
@$conn->set_charset('utf8mb4');

$root = dirname(__DIR__);
$check = in_array('--check', $argv, true);

/* ---- 1) 主表 ---- */
$master = $root . '/singlecell_data/_gene_ids.tsv';
if (!is_file($master)) {
    fwrite(STDERR, "missing $master\n");
    exit(1);
}
$bySpecies = array();
$fh = fopen($master, 'r');
$head = fgetcsv($fh, 0, "\t");
$col = array_flip($head);
foreach (array('abbr', 'sc_id', 'accession', 'locus', 'source') as $c) {
    if (!isset($col[$c])) {
        fwrite(STDERR, "master table is missing column '$c'\n");
        exit(1);
    }
}
$nrow = 0;
while (($r = fgetcsv($fh, 0, "\t")) !== false) {
    if (count($r) < 5 || $r[$col['sc_id']] === '') {
        continue;
    }
    $bySpecies[$r[$col['abbr']]][$r[$col['sc_id']]] = array(
        $r[$col['accession']],
        $r[$col['locus']],
        $r[$col['source']],
    );
    $nrow++;
}
fclose($fh);
echo "master rows: $nrow over " . count($bySpecies) . " species\n";

/* ---- 1b) 同一张主表也灌进 sc_gene_refseq ----
 *
 * cell_marker.php 的「已发表数据集」分支读的是 <ABBR>_cellmarker（NVECT/AMILL/
 * OPATA/SPIST 四张，共 283,939 行），那些页面没有 genes.json，sidecar 无处可放，
 * 所以那条路走数据库。两个存放点是**同一张主表派生**的，一起重建就不会漂移；
 * 不要只改一边。
 *
 * 重建后立刻全量回验：accession 必须能在对应 <ABBR>_locus.mRNA 查到 —— 与
 * sidecar 那条规则同源，理由也一样。差一行就整体回滚（先灌临时表再换名）。
 */
$known = array();
foreach ($bySpecies as $abbr => $rows) {
    $table = $abbr . '_locus';
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        continue;
    }
    $q = mysqli_query($conn, "SELECT DISTINCT mRNA FROM `$table`");
    if ($q) {
        while ($r = mysqli_fetch_row($q)) {
            $known[$abbr][(string)$r[0]] = true;
        }
    }
    if (!isset($known[$abbr])) {
        fwrite(STDERR, "cannot verify $abbr: no {$table}\n");
        exit(1);
    }
}

$bad = 0;
foreach ($bySpecies as $abbr => $rows) {
    foreach ($rows as $sc => $v) {
        if ($v[0] !== '' && !isset($known[$abbr][$v[0]])) {
            $bad++;
        }
    }
}
if ($bad > 0) {
    fwrite(STDERR, "ABORT: $bad row(s) map to an accession not in <ABBR>_locus.mRNA\n");
    exit(1);
}

if (!$check) {
    mysqli_query($conn, "DROP TABLE IF EXISTS sc_gene_refseq_new");
    $ddl = "CREATE TABLE sc_gene_refseq_new ("
         . " abbr VARCHAR(16) NOT NULL,"
         . " sc_id VARCHAR(128) NOT NULL,"
         . " accession VARCHAR(64) NOT NULL,"
         . " locus VARCHAR(64) NOT NULL DEFAULT '',"
         . " source VARCHAR(255) NOT NULL DEFAULT '',"
         . " PRIMARY KEY (abbr, sc_id),"
         . " KEY ix_acc (accession(48))"
         . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if (!mysqli_query($conn, $ddl)) {
        fwrite(STDERR, "CREATE failed: " . mysqli_error($conn) . "\n");
        exit(1);
    }
    /* 一条 INSERT 里塞 53k 行会撞 max_allowed_packet，分批。 */
    $st = mysqli_prepare($conn, "INSERT INTO sc_gene_refseq_new (abbr, sc_id, accession, locus, source) VALUES (?,?,?,?,?)");
    mysqli_begin_transaction($conn);
    $n = 0;
    foreach ($bySpecies as $abbr => $rows) {
        foreach ($rows as $sc => $v) {
            if ($v[0] === '') {
                continue;
            }
            mysqli_stmt_bind_param($st, 'sssss', $abbr, $sc, $v[0], $v[1], $v[2]);
            if (!mysqli_stmt_execute($st)) {
                fwrite(STDERR, "INSERT failed at $abbr/$sc: " . mysqli_stmt_error($st) . "\n");
                exit(1);
            }
            $n++;
            if ($n % 5000 === 0) {
                mysqli_commit($conn);
                mysqli_begin_transaction($conn);
            }
        }
    }
    mysqli_commit($conn);
    mysqli_stmt_close($st);
    /* 首次运行时 sc_gene_refseq 还不存在，而 RENAME TABLE 要求两边都在，
       所以要分两种情形换名 —— 建站顺序不同不该让脚本第一次跑就失败。 */
    mysqli_query($conn, "DROP TABLE IF EXISTS sc_gene_refseq_old");
    $has = mysqli_query($conn, "SELECT 1 FROM sc_gene_refseq LIMIT 1");
    if ($has !== false) {
        mysqli_query($conn, "RENAME TABLE sc_gene_refseq TO sc_gene_refseq_old, sc_gene_refseq_new TO sc_gene_refseq");
        mysqli_query($conn, "DROP TABLE sc_gene_refseq_old");
    } else {
        mysqli_query($conn, "RENAME TABLE sc_gene_refseq_new TO sc_gene_refseq");
    }
    echo "sc_gene_refseq: $n rows\n";
}

/* ---- 2) 物种缩写：物种全名 -> <ABBR>_locus 的缩写，从 abbr 表读，不写死 ---- */
$spec2abbr = array();
$q = mysqli_query($conn, "SELECT species, abbr1 FROM abbr");
if ($q) {
    while ($r = mysqli_fetch_row($q)) {
        $spec2abbr[(string)$r[0]] = (string)$r[1];
    }
}

/* ---- 3) 每个数据集的基因列表 ---- */
$dirs = glob($root . '/singlecell_data/*/genes.json');
sort($dirs);
if (!$dirs) {
    fwrite(STDERR, "no datasets found\n");
    exit(1);
}

$rc = 0;
foreach ($dirs as $gj) {
    $dataset = basename(dirname($gj));
    $raw = json_decode(file_get_contents($gj), true);
    if (!is_array($raw) || !isset($raw['genes']) || !is_array($raw['genes'])) {
        echo "  SKIP $dataset (unreadable genes.json)\n";
        continue;
    }

    /* 物种：优先问 singlecell_atlas（页面用的就是那一列），退化到 manifest.json */
    $species = '';
    $abbr = '';
    $st = @mysqli_prepare($conn, "SELECT species FROM singlecell_atlas WHERE dataset_id = ? LIMIT 1");
    if ($st) {
        mysqli_stmt_bind_param($st, 's', $dataset);
        mysqli_stmt_execute($st);
        $res = mysqli_stmt_get_result($st);
        if ($res && ($row = mysqli_fetch_row($res))) {
            $species = (string)$row[0];
        }
        mysqli_stmt_close($st);
    }
    if ($species === '' && is_file(dirname($gj) . '/manifest.json')) {
        $mf = json_decode(file_get_contents(dirname($gj) . '/manifest.json'), true);
        if (is_array($mf) && isset($mf['species'])) {
            $species = (string)$mf['species'];
        }
    }
    $abbr = isset($spec2abbr[$species]) ? $spec2abbr[$species] : '';
    if ($abbr === '') {
        echo "  SKIP $dataset (no abbr for species '" . $species . "')\n";
        continue;
    }

    /* 4) 该物种里，哪些 accession 真的能在 <ABBR>_locus.mRNA 查到 */
    $known = array();
    $table = $abbr . '_locus';
    if (preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        $q = mysqli_query($conn, "SELECT DISTINCT mRNA FROM `$table`");
        if ($q) {
            while ($r = mysqli_fetch_row($q)) {
                if ((string)$r[0] !== 'Protein_ID') {   // 源文件表头被当成数据存了一行
                    $known[(string)$r[0]] = true;
                }
            }
        }
    }

    $map = array();
    $order = array();
    $nUnres = 0;
    $srcs = array();
    foreach ($raw['genes'] as $g) {
        if (!isset($g['gene'])) {
            continue;
        }
        $sc = (string)$g['gene'];
        if (isset($map[$sc]) || !isset($bySpecies[$abbr][$sc])) {
            /* 有些数据集的基因列不是裸号：Hydra 那份把同源转移的结果用 `|` 接在
               号后面（g4335.t1|CATL_DROME）。`|` 之前才是来源注释的基因号，映射
               按它查；但 sidecar 的键必须是 genes.json 里的**原字符串**，否则
               cell_marker.php 拿整串来查会落空。所以键存整串、值取裸号的映射。 */
            $bare = strstr($sc, '|', true);
            if ($bare === false || !isset($bySpecies[$abbr][$bare])) {
                continue;                   // 未映射：不进 sidecar，页面走原号
            }
            $v = $bySpecies[$abbr][$bare];
        } else {
            $v = $bySpecies[$abbr][$sc];
        }
        if (!isset($known[$v[0]])) {
            $nUnres++;                      // accession 在站点上查不到 —— 丢弃
            continue;
        }
        $map[$sc] = array($v[0], $v[1], $v[2]);
        $order[] = $sc;
        $srcs[$v[2]] = true;
    }

    /* 反查歧义：同一個 accession 被两个原号映射到时，页面输入 accession 只能
       归约到一个 —— 按原号排序取第一个，并把冲突数报出来（有冲突不一定是错，
       但必须看得见）。 */
    $rev = array();
    $dupe = 0;
    foreach ($map as $sc => $v) {
        if (isset($rev[$v[0]]) && $rev[$v[0]] !== $sc) {
            $dupe++;
        }
        if (!isset($rev[$v[0]]) || strcmp($sc, $rev[$v[0]]) < 0) {
            $rev[$v[0]] = $sc;
        }
    }

    $nGene = count($raw['genes']);
    $nMap  = count($map);
    printf("  %-22s %-8s %4d/%-4d mapped (%4.1f%%)%s%s\n",
        $dataset, $abbr, $nMap, $nGene, $nGene ? 100.0 * $nMap / $nGene : 0.0,
        $nUnres ? "  dropped:$nUnres(unresolvable)" : '',
        $dupe ? "  ambiguous:$dupe" : '');

    if ($check) {
        continue;
    }

    ksort($map);
    $out = array(
        'generated'   => gmdate('Y-m-d'),
        'dataset_id'  => $dataset,
        'species'     => $species,
        'abbr'        => $abbr,
        'target'      => 'the accession used by ' . $abbr . '_locus.mRNA, i.e. the '
                       . 'identifier every other page on this site shows for this species',
        'n_genes'     => $nGene,
        'n_mapped'    => $nMap,
        'sources'     => array_keys($srcs),
        'map'         => $map,
    );
    $dst = dirname($gj) . '/gene_ids.json';
    $json = json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (file_put_contents($dst, $json . "\n") === false) {
        fwrite(STDERR, "  FAILED to write $dst\n");
        $rc = 1;
    }
}
exit($rc);
