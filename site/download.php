<?php
/* =====================================================================
 * Download —— 全站数据下载总入口
 *
 * 本页上半部分的表格覆盖 download/ 目录里以拉丁名命名的 *.gz（基因组、CDS、
 * 转录本、蛋白、GFF3、功能注释、基因家族），共 1027 条链接，数据在 $__dlStatic
 * 数组里（下面第一个 PHP 块）。目录里另有 846 个文件从来没有出现在任何页面上：
 *   · 168 个物种的线粒体基因组及其派生序列（.fna / .gb / .gff3 / _cds.fna /
 *     _pep.faa，共 812 个）—— 同目录下用短码命名，与上面那套拉丁名命名不同，
 *     当初加的时候没有并进这张表；
 *   · 4 个物种的 miRNA 序列与基因组注释（32 个）；
 *   · miRNA.csv 与 Pan-geneset_familydata.txt。
 * 站内还有 6 份数据只在数据库里，没有对应文件可链（busco、busco_summary、ubs、
 * epigenome、mito_genome、singlecell），它们的原始文件在 data/ 下，而 data/ 是
 * 整目录拒绝 HTTP 的。
 *
 * 上半部分那张表原来是 148 行 HTML 字面量，按 Class / Order / Species 分组、用
 * rowspan 撑起类群。现在它是 $__dlStatic 数组（本文件第一个 PHP 块），由循环渲染
 * —— 只有这样它才能分页、排序、搜索。rowspan 不再写在数据里，而是渲染每一页时
 * 按该页的切片现算。数组里每行七个文件名原样保留。下面追加三段生成出来的内容
 * 补上缺的部分，于是本页覆盖 download/ 里全部 1873 个文件，而不是 1027 个。
 *
 * 「哪些文件上面已经链过」不另外维护一张清单，而是从 $__dlStatic 里数 —— 页面
 * 链了什么就是什么。以后往数组里加一行，下面生成的段落会自动少一个重复项，两边
 * 不会各说各话。（这件事原先是拿正则扫本文件的源码做的（抓 fname= 字面量）；
 * 表变成数组之后源码里没有字面量了，改成直接读数组。语义不变而更结实：数组存的
 * 是解码后的真文件名，不再受 rawurlencode 写法的影响。）下面那些段落自己产生的
 * 链接一律走 cnido_dl_url()，不写进这份数组，所以不会自我污染。
 *
 * 本文件开头原本有一段调用 bedtools 的代码（读 $_POST['type'] 与 $_POST['idList']
 * 后拆行），是从别的页面复制过来的残留：全站没有任何表单提交到本页，本页也不
 * 存在 idList 字段，那段代码取到的永远是空值，且会在每次访问时产生 PHP notice。
 * 已删除。
 * ===================================================================== */
require_once __DIR__ . '/includes/state.php';       // cnido_latin_of / cnido_classes_in
require_once __DIR__ . '/includes/downloads.php';   // cnido_dl_* —— 与 api.php?resource=downloads 共用

$__dlDir  = __DIR__ . '/download';
$__dlAll  = cnido_dl_files($__dlDir);
$__dlConn = new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost', getenv('CNIDO_DB_USER') ?: 'cnidosite', getenv('CNIDO_DB_PASS') ?: '', getenv('CNIDO_DB_NAME') ?: 'cnidaria');
$__dlDbOk = !$__dlConn->connect_error;
if ($__dlDbOk) { mysqli_set_charset($__dlConn, 'utf8'); }

/* 文件名 -> 物种的解析规则（cnido_dl_species）依赖 abbr 表。数据库取不到时
   不能猜，只能把这一段降级：静态表照常显示，生成的段落说明取不到清单。 */
$__dlTok  = array();
$__dlKeys = array();
if ($__dlDbOk) { list($__dlTok, $__dlKeys) = cnido_dl_tokens($__dlConn); }

/* 拉丁名 -> Class，用于把线粒体那张表按类群分组。 */
$__dlClass = array();
if ($__dlDbOk) {
    $__q = mysqli_query($__dlConn, "SELECT a.species, s.`Class` FROM abbr a
                                      LEFT JOIN speciesinfo s ON s.abbr = a.abbr1");
    while ($__q && ($__r = mysqli_fetch_row($__q))) {
        if (!isset($__dlClass[$__r[0]])) { $__dlClass[$__r[0]] = ($__r[1] === null ? '' : $__r[1]); }
    }
}

/* miRNA 的 4 个物种用小写三字母码命名文件（nve-mature.fas、adi.gff），这套码
   不在 abbr 表里，只在 mirna_metadata 里。表里第一行是源文件的表头，要跳过。 */
$__dlMirna = array();
if ($__dlDbOk) {
    $__q = mysqli_query($__dlConn, "SELECT DISTINCT species, abbr FROM mirna_metadata");
    while ($__q && ($__r = mysqli_fetch_row($__q))) {
        if ($__r[0] !== '' && $__r[1] !== '' && strcasecmp($__r[0], 'species') !== 0) {
            $__dlMirna[strtolower($__r[1])] = $__r[0];
        }
    }
}

$__dlStatic = array(
  /* 上半部分那张静态表，解析成数据后由下面的循环渲染。每行一项：
       array(Class, Order, 物种, array(7 个文件名 | null))
     Class / Order 为 null ＝ 与上一行同组（原先是 rowspan），渲染时按当前
     页的切片重算 rowspan。文件列顺序同表头：Genome / CDS / Transcript /
     Protein / GFF3 / Basic annotation / Gene Family。
     这份数组同时是 $__dlLinked 的唯一来源 —— 下面「Additional files」那段
     靠它知道哪些文件上面已经链过，所以在这里加一行，那一段会自动少一个
     重复项。 */
  array('Cubozoa', 'Carybdeida', 'Alatina alata', array('Alatina_alata.fa.gz', null, null, 'Alatina_alata.pep.gz', null, 'Alatina_alata.anno.gz', 'Alatina_alata.genefamily.gz')),
  array('Cubozoa', 'Carybdeida', 'Morbakka virulenta', array('Morbakka_virulenta.fa.gz', 'Morbakka_virulenta.cds.gz', 'Morbakka_virulenta.transcript.gz', 'Morbakka_virulenta.pep.gz', 'Morbakka_virulenta.gff3.gz', 'Morbakka_virulenta.anno.gz', 'Morbakka_virulenta.genefamily.gz')),
  array('Cubozoa', 'Carybdeida', 'Tripedalia maipoensis', array('Tripedalia_maipoensis.fa.gz', 'Tripedalia_maipoensis.cds.gz', 'Tripedalia_maipoensis.transcript.gz', 'Tripedalia_maipoensis.pep.gz', 'Tripedalia_maipoensis.gff3.gz', 'Tripedalia_maipoensis.anno.gz', 'Tripedalia_maipoensis.genefamily.gz')),
  array('Hexacorallia', 'Actiniaria', 'Actinernus sp. WN-2022', array('Actinernus_sp.fa.gz', 'Actinernus_sp.cds.gz', 'Actinernus_sp.transcript.gz', 'Actinernus_sp.pep.gz', 'Actinernus_sp.gff3.gz', 'Actinernus_sp.anno.gz', 'Actinernus_sp.genefamily.gz')),
  array('Hexacorallia', 'Actiniaria', 'Actinia equina', array('Actinia_equina.fa.gz', 'Actinia_equina.cds.gz', 'Actinia_equina.transcript.gz', 'Actinia_equina.pep.gz', 'Actinia_equina.gff3.gz', 'Actinia_equina.anno.gz', 'Actinia_equina.genefamily.gz')),
  array('Hexacorallia', 'Actiniaria', 'Actinia mediterranea', array('Actinia_mediterranea.fa.gz', 'Actinia_mediterranea.cds.gz', 'Actinia_mediterranea.transcript.gz', 'Actinia_mediterranea.pep.gz', 'Actinia_mediterranea.gff3.gz', 'Actinia_mediterranea.anno.gz', 'Actinia_mediterranea.genefamily.gz')),
  array('Hexacorallia', 'Actiniaria', 'Actinia tenebrosa', array('Actinia_tenebrosa.fa.gz', 'Actinia_tenebrosa.cds.gz', 'Actinia_tenebrosa.transcript.gz', 'Actinia_tenebrosa.pep.gz', 'Actinia_tenebrosa.gff3.gz', 'Actinia_tenebrosa.anno.gz', 'Actinia_tenebrosa.genefamily.gz')),
  array('Hexacorallia', 'Actiniaria', 'Anthopleura xanthogrammica', array('Anthopleura_xanthogrammica.fa.gz', 'Anthopleura_xanthogrammica.cds.gz', 'Anthopleura_xanthogrammica.transcript.gz', 'Anthopleura_xanthogrammica.pep.gz', 'Anthopleura_xanthogrammica.gff3.gz', 'Anthopleura_xanthogrammica.anno.gz', 'Anthopleura_xanthogrammica.genefamily.gz')),
  array('Hexacorallia', 'Actiniaria', 'Condylactis gigantea', array('Condylactis_gigantea.fa.gz', 'Condylactis_gigantea.cds.gz', 'Condylactis_gigantea.transcript.gz', 'Condylactis_gigantea.pep.gz', 'Condylactis_gigantea.gff3.gz', 'Condylactis_gigantea.anno.gz', 'Condylactis_gigantea.genefamily.gz')),
  array('Hexacorallia', 'Actiniaria', 'Paracondylactis sinensis', array('Paracondylactis_sinensis.fa.gz', 'Paracondylactis_sinensis.cds.gz', 'Paracondylactis_sinensis.transcript.gz', 'Paracondylactis_sinensis.pep.gz', 'Paracondylactis_sinensis.gff3.gz', 'Paracondylactis_sinensis.anno.gz', 'Paracondylactis_sinensis.genefamily.gz')),
  array('Hexacorallia', 'Actiniaria', 'Actinoscyphia liui', array('Actinoscyphia_liui.fa.gz', 'Actinoscyphia_liui.cds.gz', 'Actinoscyphia_liui.transcript.gz', 'Actinoscyphia_liui.pep.gz', 'Actinoscyphia_liui.gff3.gz', 'Actinoscyphia_liui.anno.gz', 'Actinoscyphia_liui.genefamily.gz')),
  array('Hexacorallia', 'Actiniaria', 'Alvinactis idsseensis sp. nov.', array('Alvinactis_idsseensis.fa.gz', 'Alvinactis_idsseensis.cds.gz', 'Alvinactis_idsseensis.transcript.gz', 'Alvinactis_idsseensis.pep.gz', 'Alvinactis_idsseensis.gff3.gz', 'Alvinactis_idsseensis.anno.gz', 'Alvinactis_idsseensis.genefamily.gz')),
  array('Hexacorallia', 'Actiniaria', 'Actinostola sp. cb2023', array('Actinostola_sp.fa.gz', 'Actinostola_sp.cds.gz', 'Actinostola_sp.transcript.gz', 'Actinostola_sp.pep.gz', 'Actinostola_sp.gff3.gz', 'Actinostola_sp.anno.gz', 'Actinostola_sp.genefamily.gz')),
  array('Hexacorallia', 'Actiniaria', 'Exaiptasia diaphana', array('Exaiptasia_diaphana.fa.gz', 'Exaiptasia_diaphana.cds.gz', 'Exaiptasia_diaphana.transcript.gz', 'Exaiptasia_diaphana.pep.gz', 'Exaiptasia_diaphana.gff3.gz', 'Exaiptasia_diaphana.anno.gz', 'Exaiptasia_diaphana.genefamily.gz')),
  array('Hexacorallia', 'Actiniaria', 'Diadumene lineata', array('Diadumene_lineata.fa.gz', 'Diadumene_lineata.cds.gz', 'Diadumene_lineata.transcript.gz', 'Diadumene_lineata.pep.gz', 'Diadumene_lineata.gff3.gz', 'Diadumene_lineata.anno.gz', 'Diadumene_lineata.genefamily.gz')),
  array('Hexacorallia', 'Actiniaria', 'Edwardsia elegans', array('Edwardsia_elegans.fa.gz', 'Edwardsia_elegans.cds.gz', 'Edwardsia_elegans.transcript.gz', 'Edwardsia_elegans.pep.gz', 'Edwardsia_elegans.gff3.gz', 'Edwardsia_elegans.anno.gz', 'Edwardsia_elegans.genefamily.gz')),
  array('Hexacorallia', 'Actiniaria', 'Nematostella vectensis', array('Nematostella_vectensis.fa.gz', 'Nematostella_vectensis.cds.gz', 'Nematostella_vectensis.transcript.gz', 'Nematostella_vectensis.pep.gz', 'Nematostella_vectensis.gff3.gz', 'Nematostella_vectensis.anno.gz', 'Nematostella_vectensis.genefamily.gz')),
  array('Hexacorallia', 'Actiniaria', 'Scolanthus callimorphus', array('Scolanthus_callimorphus.fa.gz', 'Scolanthus_callimorphus.cds.gz', 'Scolanthus_callimorphus.transcript.gz', 'Scolanthus_callimorphus.pep.gz', 'Scolanthus_callimorphus.gff3.gz', 'Scolanthus_callimorphus.anno.gz', 'Scolanthus_callimorphus.genefamily.gz')),
  array('Hexacorallia', 'Actiniaria', 'Paraphelliactis xishaensis sp. nov.', array('Paraphelliactis_xishaensis.fa.gz', 'Paraphelliactis_xishaensis.cds.gz', 'Paraphelliactis_xishaensis.transcript.gz', 'Paraphelliactis_xishaensis.pep.gz', 'Paraphelliactis_xishaensis.gff3.gz', 'Paraphelliactis_xishaensis.anno.gz', 'Paraphelliactis_xishaensis.genefamily.gz')),
  array('Hexacorallia', 'Actiniaria', 'Telmatactis stephensoni', array('Telmatactis_stephensoni.fa.gz', 'Telmatactis_stephensoni.cds.gz', 'Telmatactis_stephensoni.transcript.gz', 'Telmatactis_stephensoni.pep.gz', 'Telmatactis_stephensoni.gff3.gz', 'Telmatactis_stephensoni.anno.gz', 'Telmatactis_stephensoni.genefamily.gz')),
  array('Hexacorallia', 'Actiniaria', 'Metridium senile', array('Metridium_senile.fa.gz', 'Metridium_senile.cds.gz', 'Metridium_senile.transcript.gz', 'Metridium_senile.pep.gz', 'Metridium_senile.gff3.gz', 'Metridium_senile.anno.gz', 'Metridium_senile.genefamily.gz')),
  array('Hexacorallia', 'Antipatharia', 'Plumapathes pennacea', array('Plumapathes_pennacea.fa.gz', 'Plumapathes_pennacea.cds.gz', 'Plumapathes_pennacea.transcript.gz', 'Plumapathes_pennacea.pep.gz', 'Plumapathes_pennacea.gff3.gz', 'Plumapathes_pennacea.anno.gz', 'Plumapathes_pennacea.genefamily.gz')),
  array('Hexacorallia', 'Corallimorpharia', 'Rhodactis osculifera', array('Rhodactis_osculifera.fa.gz', 'Rhodactis_osculifera.cds.gz', 'Rhodactis_osculifera.transcript.gz', 'Rhodactis_osculifera.pep.gz', 'Rhodactis_osculifera.gff3.gz', 'Rhodactis_osculifera.anno.gz', 'Rhodactis_osculifera.genefamily.gz')),
  array('Hexacorallia', 'Corallimorpharia', 'Ricordea florida', array('Ricordea_florida.fa.gz', 'Ricordea_florida.cds.gz', 'Ricordea_florida.transcript.gz', 'Ricordea_florida.pep.gz', 'Ricordea_florida.gff3.gz', 'Ricordea_florida.anno.gz', 'Ricordea_florida.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Acropora acuminata', array('Acropora_acuminata.fa.gz', 'Acropora_acuminata.cds.gz', 'Acropora_acuminata.transcript.gz', 'Acropora_acuminata.pep.gz', 'Acropora_acuminata.gff3.gz', 'Acropora_acuminata.anno.gz', 'Acropora_acuminata.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Acropora austera', array('Acropora_austera.fa.gz', 'Acropora_austera.cds.gz', 'Acropora_austera.transcript.gz', 'Acropora_austera.pep.gz', 'Acropora_austera.gff3.gz', 'Acropora_austera.anno.gz', 'Acropora_austera.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Acropora awi', array('Acropora_awi.fa.gz', 'Acropora_awi.cds.gz', 'Acropora_awi.transcript.gz', 'Acropora_awi.pep.gz', 'Acropora_awi.gff3.gz', 'Acropora_awi.anno.gz', 'Acropora_awi.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Acropora cervicornis', array('Acropora_cervicornis.fa.gz', 'Acropora_cervicornis.cds.gz', 'Acropora_cervicornis.transcript.gz', 'Acropora_cervicornis.pep.gz', 'Acropora_cervicornis.gff3.gz', 'Acropora_cervicornis.anno.gz', 'Acropora_cervicornis.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Acropora cytherea', array('Acropora_cytherea.fa.gz', 'Acropora_cytherea.cds.gz', 'Acropora_cytherea.transcript.gz', 'Acropora_cytherea.pep.gz', 'Acropora_cytherea.gff3.gz', 'Acropora_cytherea.anno.gz', 'Acropora_cytherea.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Acropora digitifera', array('Acropora_digitifera.fa.gz', 'Acropora_digitifera.cds.gz', 'Acropora_digitifera.transcript.gz', 'Acropora_digitifera.pep.gz', 'Acropora_digitifera.gff3.gz', 'Acropora_digitifera.anno.gz', 'Acropora_digitifera.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Acropora echinata', array('Acropora_echinata.fa.gz', 'Acropora_echinata.cds.gz', 'Acropora_echinata.transcript.gz', 'Acropora_echinata.pep.gz', 'Acropora_echinata.gff3.gz', 'Acropora_echinata.anno.gz', 'Acropora_echinata.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Acropora florida', array('Acropora_florida.fa.gz', 'Acropora_florida.cds.gz', 'Acropora_florida.transcript.gz', 'Acropora_florida.pep.gz', 'Acropora_florida.gff3.gz', 'Acropora_florida.anno.gz', 'Acropora_florida.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Acropora gemmifera', array('Acropora_gemmifera.fa.gz', 'Acropora_gemmifera.cds.gz', 'Acropora_gemmifera.transcript.gz', 'Acropora_gemmifera.pep.gz', 'Acropora_gemmifera.gff3.gz', 'Acropora_gemmifera.anno.gz', 'Acropora_gemmifera.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Acropora hemprichii', array('Acropora_hemprichii.fa.gz', 'Acropora_hemprichii.cds.gz', 'Acropora_hemprichii.transcript.gz', 'Acropora_hemprichii.pep.gz', 'Acropora_hemprichii.gff3.gz', 'Acropora_hemprichii.anno.gz', 'Acropora_hemprichii.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Acropora hyacinthus', array('Acropora_hyacinthus.fa.gz', 'Acropora_hyacinthus.cds.gz', 'Acropora_hyacinthus.transcript.gz', 'Acropora_hyacinthus.pep.gz', 'Acropora_hyacinthus.gff3.gz', 'Acropora_hyacinthus.anno.gz', 'Acropora_hyacinthus.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Acropora intermedia', array('Acropora_intermedia.fa.gz', 'Acropora_intermedia.cds.gz', 'Acropora_intermedia.transcript.gz', 'Acropora_intermedia.pep.gz', 'Acropora_intermedia.gff3.gz', 'Acropora_intermedia.anno.gz', 'Acropora_intermedia.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Acropora loripes', array('Acropora_loripes.fa.gz', 'Acropora_loripes.cds.gz', 'Acropora_loripes.transcript.gz', 'Acropora_loripes.pep.gz', 'Acropora_loripes.gff3.gz', 'Acropora_loripes.anno.gz', 'Acropora_loripes.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Acropora microphthalma', array('Acropora_microphthalma.fa.gz', 'Acropora_microphthalma.cds.gz', 'Acropora_microphthalma.transcript.gz', 'Acropora_microphthalma.pep.gz', 'Acropora_microphthalma.gff3.gz', 'Acropora_microphthalma.anno.gz', 'Acropora_microphthalma.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Acropora millepora', array('Acropora_millepora.fa.gz', 'Acropora_millepora.cds.gz', 'Acropora_millepora.transcript.gz', 'Acropora_millepora.pep.gz', 'Acropora_millepora.gff3.gz', 'Acropora_millepora.anno.gz', 'Acropora_millepora.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Acropora muricata', array('Acropora_muricata.fa.gz', 'Acropora_muricata.cds.gz', 'Acropora_muricata.transcript.gz', 'Acropora_muricata.pep.gz', 'Acropora_muricata.gff3.gz', 'Acropora_muricata.anno.gz', 'Acropora_muricata.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Acropora nasuta', array('Acropora_nasuta.fa.gz', 'Acropora_nasuta.cds.gz', 'Acropora_nasuta.transcript.gz', 'Acropora_nasuta.pep.gz', 'Acropora_nasuta.gff3.gz', 'Acropora_nasuta.anno.gz', 'Acropora_nasuta.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Acropora palmata', array('Acropora_palmata.fa.gz', 'Acropora_palmata.cds.gz', 'Acropora_palmata.transcript.gz', 'Acropora_palmata.pep.gz', 'Acropora_palmata.gff3.gz', 'Acropora_palmata.anno.gz', 'Acropora_palmata.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Acropora pulchra', array('Acropora_pulchra.fa.gz', 'Acropora_pulchra.cds.gz', 'Acropora_pulchra.transcript.gz', 'Acropora_pulchra.pep.gz', 'Acropora_pulchra.gff3.gz', 'Acropora_pulchra.anno.gz', 'Acropora_pulchra.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Acropora selago', array('Acropora_selago.fa.gz', 'Acropora_selago.cds.gz', 'Acropora_selago.transcript.gz', 'Acropora_selago.pep.gz', 'Acropora_selago.gff3.gz', 'Acropora_selago.anno.gz', 'Acropora_selago.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Acropora spathulata', array('Acropora_spathulata.fa.gz', 'Acropora_spathulata.cds.gz', 'Acropora_spathulata.transcript.gz', 'Acropora_spathulata.pep.gz', 'Acropora_spathulata.gff3.gz', 'Acropora_spathulata.anno.gz', 'Acropora_spathulata.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Acropora tenuis', array('Acropora_tenuis.fa.gz', 'Acropora_tenuis.cds.gz', 'Acropora_tenuis.transcript.gz', 'Acropora_tenuis.pep.gz', 'Acropora_tenuis.gff3.gz', 'Acropora_tenuis.anno.gz', 'Acropora_tenuis.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Acropora yongei', array('Acropora_yongei.fa.gz', 'Acropora_yongei.cds.gz', 'Acropora_yongei.transcript.gz', 'Acropora_yongei.pep.gz', 'Acropora_yongei.gff3.gz', 'Acropora_yongei.anno.gz', 'Acropora_yongei.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Astreopora myriophthalma', array('Astreopora_myriophthalma.fa.gz', 'Astreopora_myriophthalma.cds.gz', 'Astreopora_myriophthalma.transcript.gz', 'Astreopora_myriophthalma.pep.gz', 'Astreopora_myriophthalma.gff3.gz', 'Astreopora_myriophthalma.anno.gz', 'Astreopora_myriophthalma.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Aurelia coerulea', array('Aurelia_coerulea.fa.gz', 'Aurelia_coerulea.cds.gz', 'Aurelia_coerulea.transcript.gz', 'Aurelia_coerulea.pep.gz', 'Aurelia_coerulea.gff3.gz', 'Aurelia_coerulea.anno.gz', 'Aurelia_coerulea.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Montipora cactus', array('Montipora_cactus.fa.gz', 'Montipora_cactus.cds.gz', 'Montipora_cactus.transcript.gz', 'Montipora_cactus.pep.gz', 'Montipora_cactus.gff3.gz', 'Montipora_cactus.anno.gz', 'Montipora_cactus.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Montipora capitata', array('Montipora_capitata.fa.gz', 'Montipora_capitata.cds.gz', 'Montipora_capitata.transcript.gz', 'Montipora_capitata.pep.gz', 'Montipora_capitata.gff3.gz', 'Montipora_capitata.anno.gz', 'Montipora_capitata.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Montipora capricornis', array('Montipora_capricornis.fa.gz', 'Montipora_capricornis.cds.gz', 'Montipora_capricornis.transcript.gz', 'Montipora_capricornis.pep.gz', 'Montipora_capricornis.gff3.gz', 'Montipora_capricornis.anno.gz', 'Montipora_capricornis.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Montipora efflorescens', array('Montipora_efflorescens.fa.gz', 'Montipora_efflorescens.cds.gz', 'Montipora_efflorescens.transcript.gz', 'Montipora_efflorescens.pep.gz', 'Montipora_efflorescens.gff3.gz', 'Montipora_efflorescens.anno.gz', 'Montipora_efflorescens.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Montipora foliosa', array('Montipora_foliosa.fa.gz', 'Montipora_foliosa.cds.gz', 'Montipora_foliosa.transcript.gz', 'Montipora_foliosa.pep.gz', 'Montipora_foliosa.gff3.gz', 'Montipora_foliosa.anno.gz', 'Montipora_foliosa.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Montipora grisea', array('Montipora_grisea.fa.gz', 'Montipora_grisea.cds.gz', 'Montipora_grisea.transcript.gz', 'Montipora_grisea.pep.gz', 'Montipora_grisea.gff3.gz', 'Montipora_grisea.anno.gz', 'Montipora_grisea.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Aurelia sp. 4 Dawson et al 2005', array('Aurelia_sp_4.fa.gz', 'Aurelia_sp_4.cds.gz', 'Aurelia_sp_4.transcript.gz', 'Aurelia_sp_4.pep.gz', 'Aurelia_sp_4.gff3.gz', 'Aurelia_sp_4.anno.gz', 'Aurelia_sp_4.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Leptoseris scabra', array('Leptoseris_scabra.fa.gz', 'Leptoseris_scabra.cds.gz', 'Leptoseris_scabra.transcript.gz', 'Leptoseris_scabra.pep.gz', 'Leptoseris_scabra.gff3.gz', 'Leptoseris_scabra.anno.gz', 'Leptoseris_scabra.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Pachyseris speciosa', array('Pachyseris_speciosa.fa.gz', 'Pachyseris_speciosa.cds.gz', 'Pachyseris_speciosa.transcript.gz', 'Pachyseris_speciosa.pep.gz', 'Pachyseris_speciosa.gff3.gz', 'Pachyseris_speciosa.anno.gz', 'Pachyseris_speciosa.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Stephanocoenia intersepta', array('Stephanocoenia_intersepta.fa.gz', 'Stephanocoenia_intersepta.cds.gz', 'Stephanocoenia_intersepta.transcript.gz', 'Stephanocoenia_intersepta.pep.gz', 'Stephanocoenia_intersepta.gff3.gz', 'Stephanocoenia_intersepta.anno.gz', 'Stephanocoenia_intersepta.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Catalaphyllia jardinei', array('Catalaphyllia_jardinei.fa.gz', 'Catalaphyllia_jardinei.cds.gz', 'Catalaphyllia_jardinei.transcript.gz', 'Catalaphyllia_jardinei.pep.gz', 'Catalaphyllia_jardinei.gff3.gz', 'Catalaphyllia_jardinei.anno.gz', 'Catalaphyllia_jardinei.genefamily.gz')),
  /* 这一行的文件前缀是 Desmophyllum_pertusum，原来却跟着下一行一起标成
     "Lophelia pertusa" —— 于是 Species 列出现两行同名而文件不同，下载到的
     文件与标签对不上（abbr 表：DPERT = Desmophyllum pertusum）。按文件前缀标名。
     两个名字是同物异名（NCBI TaxID 174260），导语里已说明。 */
  array('Hexacorallia', 'Scleractinia', 'Desmophyllum pertusum', array('Desmophyllum_pertusum.fa.gz', 'Desmophyllum_pertusum.cds.gz', 'Desmophyllum_pertusum.transcript.gz', 'Desmophyllum_pertusum.pep.gz', 'Desmophyllum_pertusum.gff3.gz', 'Desmophyllum_pertusum.anno.gz', 'Desmophyllum_pertusum.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Fimbriaphyllia ancora', array('Fimbriaphyllia_ancora.fa.gz', 'Fimbriaphyllia_ancora.cds.gz', 'Fimbriaphyllia_ancora.transcript.gz', 'Fimbriaphyllia_ancora.pep.gz', 'Fimbriaphyllia_ancora.gff3.gz', 'Fimbriaphyllia_ancora.anno.gz', 'Fimbriaphyllia_ancora.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Lophelia pertusa', array('Lophelia_pertusa.fa.gz', 'Lophelia_pertusa.cds.gz', 'Lophelia_pertusa.transcript.gz', 'Lophelia_pertusa.pep.gz', 'Lophelia_pertusa.gff3.gz', 'Lophelia_pertusa.anno.gz', 'Lophelia_pertusa.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Cladopsammia gracilis', array('Cladopsammia_gracilis.fa.gz', 'Cladopsammia_gracilis.cds.gz', 'Cladopsammia_gracilis.transcript.gz', 'Cladopsammia_gracilis.pep.gz', 'Cladopsammia_gracilis.gff3.gz', 'Cladopsammia_gracilis.anno.gz', 'Cladopsammia_gracilis.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Dendrophyllia cribrosa', array('Dendrophyllia_cribrosa.fa.gz', 'Dendrophyllia_cribrosa.cds.gz', 'Dendrophyllia_cribrosa.transcript.gz', 'Dendrophyllia_cribrosa.pep.gz', 'Dendrophyllia_cribrosa.gff3.gz', 'Dendrophyllia_cribrosa.anno.gz', 'Dendrophyllia_cribrosa.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Duncanopsammia axifuga', array('Duncanopsammia_axifuga.fa.gz', 'Duncanopsammia_axifuga.cds.gz', 'Duncanopsammia_axifuga.transcript.gz', 'Duncanopsammia_axifuga.pep.gz', 'Duncanopsammia_axifuga.gff3.gz', 'Duncanopsammia_axifuga.anno.gz', 'Duncanopsammia_axifuga.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Tubastraea coccinea', array('Tubastraea_coccinea.fa.gz', 'Tubastraea_coccinea.cds.gz', 'Tubastraea_coccinea.transcript.gz', 'Tubastraea_coccinea.pep.gz', 'Tubastraea_coccinea.gff3.gz', 'Tubastraea_coccinea.anno.gz', 'Tubastraea_coccinea.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Turbinaria reniformis', array('Turbinaria_reniformis.fa.gz', 'Turbinaria_reniformis.cds.gz', 'Turbinaria_reniformis.transcript.gz', 'Turbinaria_reniformis.pep.gz', 'Turbinaria_reniformis.gff3.gz', 'Turbinaria_reniformis.anno.gz', 'Turbinaria_reniformis.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Galaxea fascicularis', array('Galaxea_fascicularis.fa.gz', 'Galaxea_fascicularis.cds.gz', 'Galaxea_fascicularis.transcript.gz', 'Galaxea_fascicularis.pep.gz', 'Galaxea_fascicularis.gff3.gz', 'Galaxea_fascicularis.anno.gz', 'Galaxea_fascicularis.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Podabacia crustacea', array('Podabacia_crustacea.fa.gz', 'Podabacia_crustacea.cds.gz', 'Podabacia_crustacea.transcript.gz', 'Podabacia_crustacea.pep.gz', 'Podabacia_crustacea.gff3.gz', 'Podabacia_crustacea.anno.gz', 'Podabacia_crustacea.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Micromussa lordhowensis', array('Micromussa_lordhowensis.fa.gz', 'Micromussa_lordhowensis.cds.gz', 'Micromussa_lordhowensis.transcript.gz', 'Micromussa_lordhowensis.pep.gz', 'Micromussa_lordhowensis.gff3.gz', 'Micromussa_lordhowensis.anno.gz', 'Micromussa_lordhowensis.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Dendrogyra cylindrus', array('Dendrogyra_cylindrus.fa.gz', 'Dendrogyra_cylindrus.cds.gz', 'Dendrogyra_cylindrus.transcript.gz', 'Dendrogyra_cylindrus.pep.gz', 'Dendrogyra_cylindrus.gff3.gz', 'Dendrogyra_cylindrus.anno.gz', 'Dendrogyra_cylindrus.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Meandrina meandrites', array('Meandrina_meandrites.fa.gz', 'Meandrina_meandrites.cds.gz', 'Meandrina_meandrites.transcript.gz', 'Meandrina_meandrites.pep.gz', 'Meandrina_meandrites.gff3.gz', 'Meandrina_meandrites.anno.gz', 'Meandrina_meandrites.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Cyphastrea salae', array('Cyphastrea_salae.fa.gz', 'Cyphastrea_salae.cds.gz', 'Cyphastrea_salae.transcript.gz', 'Cyphastrea_salae.pep.gz', 'Cyphastrea_salae.gff3.gz', 'Cyphastrea_salae.anno.gz', 'Cyphastrea_salae.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Echinopora horrida', array('Echinopora_horrida.fa.gz', 'Echinopora_horrida.cds.gz', 'Echinopora_horrida.transcript.gz', 'Echinopora_horrida.pep.gz', 'Echinopora_horrida.gff3.gz', 'Echinopora_horrida.anno.gz', 'Echinopora_horrida.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Orbicella faveolata', array('Orbicella_faveolata.fa.gz', 'Orbicella_faveolata.cds.gz', 'Orbicella_faveolata.transcript.gz', 'Orbicella_faveolata.pep.gz', 'Orbicella_faveolata.gff3.gz', 'Orbicella_faveolata.anno.gz', 'Orbicella_faveolata.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Orbicella franksi', array('Orbicella_franksi.fa.gz', 'Orbicella_franksi.cds.gz', 'Orbicella_franksi.transcript.gz', 'Orbicella_franksi.pep.gz', 'Orbicella_franksi.gff3.gz', 'Orbicella_franksi.anno.gz', 'Orbicella_franksi.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Platygyra sinensis', array('Platygyra_sinensis.fa.gz', 'Platygyra_sinensis.cds.gz', 'Platygyra_sinensis.transcript.gz', 'Platygyra_sinensis.pep.gz', 'Platygyra_sinensis.gff3.gz', 'Platygyra_sinensis.anno.gz', 'Platygyra_sinensis.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Colpophyllia natans', array('Colpophyllia_natans.fa.gz', 'Colpophyllia_natans.cds.gz', 'Colpophyllia_natans.transcript.gz', 'Colpophyllia_natans.pep.gz', 'Colpophyllia_natans.gff3.gz', 'Colpophyllia_natans.anno.gz', 'Colpophyllia_natans.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Oculina arbuscula', array('Oculina_arbuscula.fa.gz', 'Oculina_arbuscula.cds.gz', 'Oculina_arbuscula.transcript.gz', 'Oculina_arbuscula.pep.gz', 'Oculina_arbuscula.gff3.gz', 'Oculina_arbuscula.anno.gz', 'Oculina_arbuscula.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Oculina patagonica', array('Oculina_patagonica.fa.gz', 'Oculina_patagonica.cds.gz', 'Oculina_patagonica.transcript.gz', 'Oculina_patagonica.pep.gz', 'Oculina_patagonica.gff3.gz', 'Oculina_patagonica.anno.gz', 'Oculina_patagonica.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Madracis auretenra', array('Madracis_auretenra.fa.gz', 'Madracis_auretenra.cds.gz', 'Madracis_auretenra.transcript.gz', 'Madracis_auretenra.pep.gz', 'Madracis_auretenra.gff3.gz', 'Madracis_auretenra.anno.gz', 'Madracis_auretenra.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Madracis senaria', array('Madracis_senaria.fa.gz', 'Madracis_senaria.cds.gz', 'Madracis_senaria.transcript.gz', 'Madracis_senaria.pep.gz', 'Madracis_senaria.gff3.gz', 'Madracis_senaria.anno.gz', 'Madracis_senaria.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Pocillopora acuta', array('Pocillopora_acuta.fa.gz', 'Pocillopora_acuta.cds.gz', 'Pocillopora_acuta.transcript.gz', 'Pocillopora_acuta.pep.gz', 'Pocillopora_acuta.gff3.gz', 'Pocillopora_acuta.anno.gz', 'Pocillopora_acuta.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Pocillopora damicornis', array('Pocillopora_damicornis.fa.gz', 'Pocillopora_damicornis.cds.gz', 'Pocillopora_damicornis.transcript.gz', 'Pocillopora_damicornis.pep.gz', 'Pocillopora_damicornis.gff3.gz', 'Pocillopora_damicornis.anno.gz', 'Pocillopora_damicornis.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Pocillopora meandrina', array('Pocillopora_meandrina.fa.gz', 'Pocillopora_meandrina.cds.gz', 'Pocillopora_meandrina.transcript.gz', 'Pocillopora_meandrina.pep.gz', 'Pocillopora_meandrina.gff3.gz', 'Pocillopora_meandrina.anno.gz', 'Pocillopora_meandrina.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Pocillopora verrucosa', array('Pocillopora_verrucosa.fa.gz', 'Pocillopora_verrucosa.cds.gz', 'Pocillopora_verrucosa.transcript.gz', 'Pocillopora_verrucosa.pep.gz', 'Pocillopora_verrucosa.gff3.gz', 'Pocillopora_verrucosa.anno.gz', 'Pocillopora_verrucosa.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Stylophora pistillata', array('Stylophora_pistillata.fa.gz', 'Stylophora_pistillata.cds.gz', 'Stylophora_pistillata.transcript.gz', 'Stylophora_pistillata.pep.gz', 'Stylophora_pistillata.gff3.gz', 'Stylophora_pistillata.anno.gz', 'Stylophora_pistillata.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Porites australiensis', array('Porites_australiensis.fa.gz', 'Porites_australiensis.cds.gz', 'Porites_australiensis.transcript.gz', 'Porites_australiensis.pep.gz', 'Porites_australiensis.gff3.gz', 'Porites_australiensis.anno.gz', 'Porites_australiensis.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Porites compressa', array('Porites_compressa.fa.gz', 'Porites_compressa.cds.gz', 'Porites_compressa.transcript.gz', 'Porites_compressa.pep.gz', 'Porites_compressa.gff3.gz', 'Porites_compressa.anno.gz', 'Porites_compressa.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Porites cylindrica', array('Porites_cylindrica.fa.gz', 'Porites_cylindrica.cds.gz', 'Porites_cylindrica.transcript.gz', 'Porites_cylindrica.pep.gz', 'Porites_cylindrica.gff3.gz', 'Porites_cylindrica.anno.gz', 'Porites_cylindrica.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Porites divaricata', array('Porites_divaricata.fa.gz', 'Porites_divaricata.cds.gz', 'Porites_divaricata.transcript.gz', 'Porites_divaricata.pep.gz', 'Porites_divaricata.gff3.gz', 'Porites_divaricata.anno.gz', 'Porites_divaricata.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Porites evermanni', array('Porites_evermanni.fa.gz', 'Porites_evermanni.cds.gz', 'Porites_evermanni.transcript.gz', 'Porites_evermanni.pep.gz', 'Porites_evermanni.gff3.gz', 'Porites_evermanni.anno.gz', 'Porites_evermanni.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Porites harrisoni', array('Porites_harrisoni.fa.gz', 'Porites_harrisoni.cds.gz', 'Porites_harrisoni.transcript.gz', 'Porites_harrisoni.pep.gz', 'Porites_harrisoni.gff3.gz', 'Porites_harrisoni.anno.gz', 'Porites_harrisoni.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Porites lobata', array('Porites_lobata.fa.gz', 'Porites_lobata.cds.gz', 'Porites_lobata.transcript.gz', 'Porites_lobata.pep.gz', 'Porites_lobata.gff3.gz', 'Porites_lobata.anno.gz', 'Porites_lobata.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Porites lutea', array('Porites_lutea.fa.gz', 'Porites_lutea.cds.gz', 'Porites_lutea.transcript.gz', 'Porites_lutea.pep.gz', 'Porites_lutea.gff3.gz', 'Porites_lutea.anno.gz', 'Porites_lutea.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Porites rus', array('Porites_rus.fa.gz', 'Porites_rus.cds.gz', 'Porites_rus.transcript.gz', 'Porites_rus.pep.gz', 'Porites_rus.gff3.gz', 'Porites_rus.anno.gz', 'Porites_rus.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Astrangia poculata', array('Astrangia_poculata.fa.gz', 'Astrangia_poculata.cds.gz', 'Astrangia_poculata.transcript.gz', 'Astrangia_poculata.pep.gz', 'Astrangia_poculata.gff3.gz', 'Astrangia_poculata.anno.gz', 'Astrangia_poculata.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Siderastrea radians', array('Siderastrea_radians.fa.gz', 'Siderastrea_radians.cds.gz', 'Siderastrea_radians.transcript.gz', 'Siderastrea_radians.pep.gz', 'Siderastrea_radians.gff3.gz', 'Siderastrea_radians.anno.gz', 'Siderastrea_radians.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Siderastrea siderea', array('Siderastrea_siderea.fa.gz', 'Siderastrea_siderea.cds.gz', 'Siderastrea_siderea.transcript.gz', 'Siderastrea_siderea.pep.gz', 'Siderastrea_siderea.gff3.gz', 'Siderastrea_siderea.anno.gz', 'Siderastrea_siderea.genefamily.gz')),
  array('Hexacorallia', 'Scleractinia', 'Blastomussa wellsi', array('Blastomussa_wellsi.fa.gz', 'Blastomussa_wellsi.cds.gz', 'Blastomussa_wellsi.transcript.gz', 'Blastomussa_wellsi.pep.gz', 'Blastomussa_wellsi.gff3.gz', 'Blastomussa_wellsi.anno.gz', 'Blastomussa_wellsi.genefamily.gz')),
  array('Hexacorallia', 'Zoantharia', 'Palythoa mizigama', array('Palythoa_mizigama.fa.gz', 'Palythoa_mizigama.cds.gz', 'Palythoa_mizigama.transcript.gz', 'Palythoa_mizigama.pep.gz', 'Palythoa_mizigama.gff3.gz', 'Palythoa_mizigama.anno.gz', 'Palythoa_mizigama.genefamily.gz')),
  array('Hexacorallia', 'Zoantharia', 'Palythoa umbrosa', array('Palythoa_umbrosa.fa.gz', 'Palythoa_umbrosa.cds.gz', 'Palythoa_umbrosa.transcript.gz', 'Palythoa_umbrosa.pep.gz', 'Palythoa_umbrosa.gff3.gz', 'Palythoa_umbrosa.anno.gz', 'Palythoa_umbrosa.genefamily.gz')),
  array('Hydrozoa', 'Anthoathecata', 'Bougainvillia cf. muscus', array('Bougainvillia_cf_muscus.fa.gz', 'Bougainvillia_cf_muscus.cds.gz', 'Bougainvillia_cf_muscus.transcript.gz', 'Bougainvillia_cf_muscus.pep.gz', 'Bougainvillia_cf_muscus.gff3.gz', 'Bougainvillia_cf_muscus.anno.gz', 'Bougainvillia_cf_muscus.genefamily.gz')),
  array('Hydrozoa', 'Anthoathecata', 'Candelabrum cocksii', array('Candelabrum_cocksii.fa.gz', 'Candelabrum_cocksii.cds.gz', 'Candelabrum_cocksii.transcript.gz', 'Candelabrum_cocksii.pep.gz', 'Candelabrum_cocksii.gff3.gz', 'Candelabrum_cocksii.anno.gz', 'Candelabrum_cocksii.genefamily.gz')),
  array('Hydrozoa', 'Anthoathecata', 'Hydractinia echinata', array('Hydractinia_echinata.fa.gz', 'Hydractinia_echinata.cds.gz', 'Hydractinia_echinata.transcript.gz', 'Hydractinia_echinata.pep.gz', 'Hydractinia_echinata.gff3.gz', 'Hydractinia_echinata.anno.gz', 'Hydractinia_echinata.genefamily.gz')),
  array('Hydrozoa', 'Anthoathecata', 'Hydractinia symbiolongicarpus', array('Hydractinia_symbiolongicarpus.fa.gz', 'Hydractinia_symbiolongicarpus.cds.gz', 'Hydractinia_symbiolongicarpus.transcript.gz', 'Hydractinia_symbiolongicarpus.pep.gz', 'Hydractinia_symbiolongicarpus.gff3.gz', 'Hydractinia_symbiolongicarpus.anno.gz', 'Hydractinia_symbiolongicarpus.genefamily.gz')),
  array('Hydrozoa', 'Anthoathecata', 'Hydra oligactis', array('Hydra_oligactis.fa.gz', 'Hydra_oligactis.cds.gz', 'Hydra_oligactis.transcript.gz', 'Hydra_oligactis.pep.gz', 'Hydra_oligactis.gff3.gz', 'Hydra_oligactis.anno.gz', 'Hydra_oligactis.genefamily.gz')),
  array('Hydrozoa', 'Anthoathecata', 'Hydra viridissima', array('Hydra_viridissima.fa.gz', 'Hydra_viridissima.cds.gz', 'Hydra_viridissima.transcript.gz', 'Hydra_viridissima.pep.gz', 'Hydra_viridissima.gff3.gz', 'Hydra_viridissima.anno.gz', 'Hydra_viridissima.genefamily.gz')),
  array('Hydrozoa', 'Anthoathecata', 'Hydra vulgaris', array('Hydra_vulgaris.fa.gz', 'Hydra_vulgaris.cds.gz', 'Hydra_vulgaris.transcript.gz', 'Hydra_vulgaris.pep.gz', 'Hydra_vulgaris.gff3.gz', 'Hydra_vulgaris.anno.gz', 'Hydra_vulgaris.genefamily.gz')),
  array('Hydrozoa', 'Anthoathecata', 'Millepora alcicornis', array('Millepora_alcicornis.fa.gz', 'Millepora_alcicornis.cds.gz', 'Millepora_alcicornis.transcript.gz', 'Millepora_alcicornis.pep.gz', 'Millepora_alcicornis.gff3.gz', 'Millepora_alcicornis.anno.gz', 'Millepora_alcicornis.genefamily.gz')),
  array('Hydrozoa', 'Anthoathecata', 'Millepora complanata', array('Millepora_complanata.fa.gz', 'Millepora_complanata.cds.gz', 'Millepora_complanata.transcript.gz', 'Millepora_complanata.pep.gz', 'Millepora_complanata.gff3.gz', 'Millepora_complanata.anno.gz', 'Millepora_complanata.genefamily.gz')),
  array('Hydrozoa', 'Anthoathecata', 'Millepora dichotoma', array('Millepora_dichotoma.fa.gz', 'Millepora_dichotoma.cds.gz', 'Millepora_dichotoma.transcript.gz', 'Millepora_dichotoma.pep.gz', 'Millepora_dichotoma.gff3.gz', 'Millepora_dichotoma.anno.gz', 'Millepora_dichotoma.genefamily.gz')),
  array('Hydrozoa', 'Anthoathecata', 'Turritopsis dohrnii', array('Turritopsis_dohrnii.fa.gz', 'Turritopsis_dohrnii.cds.gz', 'Turritopsis_dohrnii.transcript.gz', 'Turritopsis_dohrnii.pep.gz', 'Turritopsis_dohrnii.gff3.gz', 'Turritopsis_dohrnii.anno.gz', 'Turritopsis_dohrnii.genefamily.gz')),
  array('Hydrozoa', 'Anthoathecata', 'Turritopsis rubra', array('Turritopsis_rubra.fa.gz', 'Turritopsis_rubra.cds.gz', 'Turritopsis_rubra.transcript.gz', 'Turritopsis_rubra.pep.gz', 'Turritopsis_rubra.gff3.gz', 'Turritopsis_rubra.anno.gz', 'Turritopsis_rubra.genefamily.gz')),
  array('Hydrozoa', 'Leptothecata', 'Clytia hemisphaerica', array('Clytia_hemisphaerica.fa.gz', 'Clytia_hemisphaerica.cds.gz', 'Clytia_hemisphaerica.transcript.gz', 'Clytia_hemisphaerica.pep.gz', 'Clytia_hemisphaerica.gff3.gz', 'Clytia_hemisphaerica.anno.gz', 'Clytia_hemisphaerica.genefamily.gz')),
  array('Hydrozoa', 'Siphonophorae', 'Nanomia septata', array('Nanomia_septata.fa.gz', 'Nanomia_septata.cds.gz', 'Nanomia_septata.transcript.gz', 'Nanomia_septata.pep.gz', 'Nanomia_septata.gff3.gz', 'Nanomia_septata.anno.gz', 'Nanomia_septata.genefamily.gz')),
  array('Myxozoa', 'Bivalvulida', 'Henneguya salminicola', array('Henneguya_salminicola.fa.gz', 'Henneguya_salminicola.cds.gz', 'Henneguya_salminicola.transcript.gz', 'Henneguya_salminicola.pep.gz', 'Henneguya_salminicola.gff3.gz', 'Henneguya_salminicola.anno.gz', 'Henneguya_salminicola.genefamily.gz')),
  array('Myxozoa', 'Bivalvulida', 'Myxobolus honghuensis', array('Myxobolus_honghuensis.fa.gz', 'Myxobolus_honghuensis.cds.gz', 'Myxobolus_honghuensis.transcript.gz', 'Myxobolus_honghuensis.pep.gz', 'Myxobolus_honghuensis.gff3.gz', 'Myxobolus_honghuensis.anno.gz', 'Myxobolus_honghuensis.genefamily.gz')),
  array('Myxozoa', 'Bivalvulida', 'Myxobolus squamalis', array('Myxobolus_squamalis.fa.gz', 'Myxobolus_squamalis.cds.gz', 'Myxobolus_squamalis.transcript.gz', 'Myxobolus_squamalis.pep.gz', 'Myxobolus_squamalis.gff3.gz', 'Myxobolus_squamalis.anno.gz', 'Myxobolus_squamalis.genefamily.gz')),
  array('Myxozoa', 'Bivalvulida', 'Thelohanellus kitauei', array('Thelohanellus_kitauei.fa.gz', 'Thelohanellus_kitauei.cds.gz', 'Thelohanellus_kitauei.transcript.gz', 'Thelohanellus_kitauei.pep.gz', 'Thelohanellus_kitauei.gff3.gz', 'Thelohanellus_kitauei.anno.gz', 'Thelohanellus_kitauei.genefamily.gz')),
  array('Octocorallia', 'Malacalcyonacea', 'Trachythela sp. YZ-2020', array('Trachythela_sp.fa.gz', 'Trachythela_sp.cds.gz', 'Trachythela_sp.transcript.gz', 'Trachythela_sp.pep.gz', 'Trachythela_sp.gff3.gz', 'Trachythela_sp.anno.gz', 'Trachythela_sp.genefamily.gz')),
  array('Octocorallia', 'Malacalcyonacea', 'Eunicella cavolini', array('Eunicella_cavolini.fa.gz', 'Eunicella_cavolini.cds.gz', 'Eunicella_cavolini.transcript.gz', 'Eunicella_cavolini.pep.gz', 'Eunicella_cavolini.gff3.gz', 'Eunicella_cavolini.anno.gz', 'Eunicella_cavolini.genefamily.gz')),
  array('Octocorallia', 'Malacalcyonacea', 'Eunicella verrucosa', array('Eunicella_verrucosa.fa.gz', 'Eunicella_verrucosa.cds.gz', 'Eunicella_verrucosa.transcript.gz', 'Eunicella_verrucosa.pep.gz', 'Eunicella_verrucosa.gff3.gz', 'Eunicella_verrucosa.anno.gz', 'Eunicella_verrucosa.genefamily.gz')),
  array('Octocorallia', 'Malacalcyonacea', 'Leptogorgia sarmentosa', array('Leptogorgia_sarmentosa.fa.gz', 'Leptogorgia_sarmentosa.cds.gz', 'Leptogorgia_sarmentosa.transcript.gz', 'Leptogorgia_sarmentosa.pep.gz', 'Leptogorgia_sarmentosa.gff3.gz', 'Leptogorgia_sarmentosa.anno.gz', 'Leptogorgia_sarmentosa.genefamily.gz')),
  array('Octocorallia', 'Malacalcyonacea', 'Dendronephthya gigantea', array('Dendronephthya_gigantea.fa.gz', 'Dendronephthya_gigantea.cds.gz', 'Dendronephthya_gigantea.transcript.gz', 'Dendronephthya_gigantea.pep.gz', 'Dendronephthya_gigantea.gff3.gz', 'Dendronephthya_gigantea.anno.gz', 'Dendronephthya_gigantea.genefamily.gz')),
  array('Octocorallia', 'Malacalcyonacea', 'Muricea muricata', array('Muricea_muricata.fa.gz', 'Muricea_muricata.cds.gz', 'Muricea_muricata.transcript.gz', 'Muricea_muricata.pep.gz', 'Muricea_muricata.gff3.gz', 'Muricea_muricata.anno.gz', 'Muricea_muricata.genefamily.gz')),
  array('Octocorallia', 'Malacalcyonacea', 'Paramuricea clavata', array('Paramuricea_clavata.fa.gz', 'Paramuricea_clavata.cds.gz', 'Paramuricea_clavata.transcript.gz', 'Paramuricea_clavata.pep.gz', 'Paramuricea_clavata.gff3.gz', 'Paramuricea_clavata.anno.gz', 'Paramuricea_clavata.genefamily.gz')),
  array('Octocorallia', 'Malacalcyonacea', 'Xenia sp. Carnegie-2017', array('Xenia_sp.fa.gz', 'Xenia_sp.cds.gz', 'Xenia_sp.transcript.gz', 'Xenia_sp.pep.gz', 'Xenia_sp.gff3.gz', 'Xenia_sp.anno.gz', 'Xenia_sp.genefamily.gz')),
  array('Octocorallia', 'Scleralcyonacea', 'Chrysogorgia sp. JL179-B06', array('Chrysogorgia_sp.fa.gz', 'Chrysogorgia_sp.cds.gz', 'Chrysogorgia_sp.transcript.gz', 'Chrysogorgia_sp.pep.gz', 'Chrysogorgia_sp.gff3.gz', 'Chrysogorgia_sp.anno.gz', 'Chrysogorgia_sp.genefamily.gz')),
  array('Octocorallia', 'Scleralcyonacea', 'Hemicorallium imperiale', array('Hemicorallium_imperiale.fa.gz', 'Hemicorallium_imperiale.cds.gz', 'Hemicorallium_imperiale.transcript.gz', 'Hemicorallium_imperiale.pep.gz', 'Hemicorallium_imperiale.gff3.gz', 'Hemicorallium_imperiale.anno.gz', 'Hemicorallium_imperiale.genefamily.gz')),
  array('Octocorallia', 'Scleralcyonacea', 'Paragorgia papillata', array('Paragorgia_papillata.fa.gz', 'Paragorgia_papillata.cds.gz', 'Paragorgia_papillata.transcript.gz', 'Paragorgia_papillata.pep.gz', 'Paragorgia_papillata.gff3.gz', 'Paragorgia_papillata.anno.gz', 'Paragorgia_papillata.genefamily.gz')),
  array('Octocorallia', 'Scleralcyonacea', 'Heliopora coerulea', array('Heliopora_coerulea.fa.gz', 'Heliopora_coerulea.cds.gz', 'Heliopora_coerulea.transcript.gz', 'Heliopora_coerulea.pep.gz', 'Heliopora_coerulea.gff3.gz', 'Heliopora_coerulea.anno.gz', 'Heliopora_coerulea.genefamily.gz')),
  array('Octocorallia', 'Scleralcyonacea', 'Pteroeides griseum', array('Pteroeides_griseum.fa.gz', 'Pteroeides_griseum.cds.gz', 'Pteroeides_griseum.transcript.gz', 'Pteroeides_griseum.pep.gz', 'Pteroeides_griseum.gff3.gz', 'Pteroeides_griseum.anno.gz', 'Pteroeides_griseum.genefamily.gz')),
  array('Octocorallia', 'Scleralcyonacea', 'Callogorgia gracilis', array('Callogorgia_gracilis.fa.gz', 'Callogorgia_gracilis.cds.gz', 'Callogorgia_gracilis.transcript.gz', 'Callogorgia_gracilis.pep.gz', 'Callogorgia_gracilis.gff3.gz', 'Callogorgia_gracilis.anno.gz', 'Callogorgia_gracilis.genefamily.gz')),
  array('Scyphozoa', 'Rhizostomeae', 'Cassiopea sp. PORT0000214', array('Cassiopea_sp_PORT0000214.fa.gz', 'Cassiopea_sp_PORT0000214.cds.gz', 'Cassiopea_sp_PORT0000214.transcript.gz', 'Cassiopea_sp_PORT0000214.pep.gz', 'Cassiopea_sp_PORT0000214.gff3.gz', 'Cassiopea_sp_PORT0000214.anno.gz', 'Cassiopea_sp_PORT0000214.genefamily.gz')),
  array('Scyphozoa', 'Rhizostomeae', 'Cassiopea xamachana', array('Cassiopea_xamachana.fa.gz', null, null, 'Cassiopea_xamachana.pep.gz', null, 'Cassiopea_xamachana.anno.gz', 'Cassiopea_xamachana.genefamily.gz')),
  array('Scyphozoa', 'Rhizostomeae', 'Catostylus mosaicus', array('Catostylus_mosaicus.fa.gz', 'Catostylus_mosaicus.cds.gz', 'Catostylus_mosaicus.transcript.gz', 'Catostylus_mosaicus.pep.gz', 'Catostylus_mosaicus.gff3.gz', 'Catostylus_mosaicus.anno.gz', 'Catostylus_mosaicus.genefamily.gz')),
  array('Scyphozoa', 'Rhizostomeae', 'Mastigias papua', array('Mastigias_papua.fa.gz', 'Mastigias_papua.cds.gz', 'Mastigias_papua.transcript.gz', 'Mastigias_papua.pep.gz', 'Mastigias_papua.gff3.gz', 'Mastigias_papua.anno.gz', 'Mastigias_papua.genefamily.gz')),
  array('Scyphozoa', 'Rhizostomeae', 'Nemopilema nomurai', array('Nemopilema_nomurai.fa.gz', 'Nemopilema_nomurai.cds.gz', 'Nemopilema_nomurai.transcript.gz', 'Nemopilema_nomurai.pep.gz', 'Nemopilema_nomurai.gff3.gz', 'Nemopilema_nomurai.anno.gz', 'Nemopilema_nomurai.genefamily.gz')),
  array('Scyphozoa', 'Rhizostomeae', 'Rhopilema esculentum', array('Rhopilema_esculentum.fa.gz', 'Rhopilema_esculentum.cds.gz', 'Rhopilema_esculentum.transcript.gz', 'Rhopilema_esculentum.pep.gz', 'Rhopilema_esculentum.gff3.gz', 'Rhopilema_esculentum.anno.gz', 'Rhopilema_esculentum.genefamily.gz')),
  array('Scyphozoa', 'Semaeostomeae', 'Chrysaora quinquecirrha', array('Chrysaora_quinquecirrha.fa.gz', 'Chrysaora_quinquecirrha.cds.gz', 'Chrysaora_quinquecirrha.transcript.gz', 'Chrysaora_quinquecirrha.pep.gz', 'Chrysaora_quinquecirrha.gff3.gz', 'Chrysaora_quinquecirrha.anno.gz', 'Chrysaora_quinquecirrha.genefamily.gz')),
  array('Scyphozoa', 'Semaeostomeae', 'Pelagia noctiluca', array('Pelagia_noctiluca.fa.gz', 'Pelagia_noctiluca.cds.gz', 'Pelagia_noctiluca.transcript.gz', 'Pelagia_noctiluca.pep.gz', 'Pelagia_noctiluca.gff3.gz', 'Pelagia_noctiluca.anno.gz', 'Pelagia_noctiluca.genefamily.gz')),
  array('Scyphozoa', 'Semaeostomeae', 'Sanderia malayensis', array('Sanderia_malayensis.fa.gz', 'Sanderia_malayensis.cds.gz', 'Sanderia_malayensis.transcript.gz', 'Sanderia_malayensis.pep.gz', 'Sanderia_malayensis.gff3.gz', 'Sanderia_malayensis.anno.gz', 'Sanderia_malayensis.genefamily.gz')),
  array('Scyphozoa', 'Semaeostomeae', 'Aurelia aurita', array('Aurelia_aurita.fa.gz', 'Aurelia_aurita.cds.gz', 'Aurelia_aurita.transcript.gz', 'Aurelia_aurita.pep.gz', 'Aurelia_aurita.gff3.gz', 'Aurelia_aurita.anno.gz', 'Aurelia_aurita.genefamily.gz')),
  array('Scyphozoa', 'Semaeostomeae', 'Aurelia aurita complex sp. Pacific', array('Aurelia_aurita_complex.fa.gz', 'Aurelia_aurita_complex.cds.gz', 'Aurelia_aurita_complex.transcript.gz', 'Aurelia_aurita_complex.pep.gz', 'Aurelia_aurita_complex.gff3.gz', 'Aurelia_aurita_complex.anno.gz', 'Aurelia_aurita_complex.genefamily.gz')),
  array('Staurozoa', 'Stauromedusae', 'Haliclystus octoradiatus', array('Haliclystus_octoradiatus.fa.gz', 'Haliclystus_octoradiatus.cds.gz', 'Haliclystus_octoradiatus.transcript.gz', 'Haliclystus_octoradiatus.pep.gz', 'Haliclystus_octoradiatus.gff3.gz', 'Haliclystus_octoradiatus.anno.gz', 'Haliclystus_octoradiatus.genefamily.gz')),
  array('Staurozoa', 'Stauromedusae', 'Calvadosia cruxmelitensis', array('Calvadosia_cruxmelitensis.fa.gz', null, null, 'Calvadosia_cruxmelitensis.pep.gz', null, 'Calvadosia_cruxmelitensis.anno.gz', 'Calvadosia_cruxmelitensis.genefamily.gz')),
);

/* 上半部分已经链出去的文件 = $__dlStatic 里出现过的每一个文件名。不另记一份
   清单：数组里加一行，下面「Additional files」那段自动少一个重复项。 */
$__dlLinked = array();
foreach ($__dlStatic as $__r) {
    foreach ($__r[3] as $__f) { if ($__f !== null) { $__dlLinked[$__f] = true; } }
}
unset($__r, $__f);

/* 线粒体那 5 种后缀。'_cds.fna' 必须排在 '.fna' 前面，否则会被后者先匹配走。 */
$__dlMitoSuf = array('_cds.fna' => 'cds', '.fna' => 'fna', '.gb' => 'gb',
                     '.gff3' => 'gff3', '_pep.faa' => 'pep');

$__dlMito   = array();   // 拉丁名 => array(后缀键 => 文件名)
$__dlExtra  = array();   // 拉丁名 => array(文件名)    非线粒体的零散文件
$__dlGlobal = array();   // array(文件名)              不属于任何物种的文件
$__dlMissing = 0;

foreach ($__dlAll as $__f) {
    if (isset($__dlLinked[$__f])) { continue; }
    $__dlMissing++;

    $__sp = cnido_dl_species($__f, $__dlTok, $__dlKeys);
    if ($__sp === '') {
        /* miRNA 的码：<码>-mature.fas / <码>.gff 之类的文件名。 */
        $__dot  = strpos($__f, '.');
        $__stem = ($__dot === false) ? '' : substr($__f, 0, $__dot);
        $__code = strtolower(preg_replace('/-(mature|pre|star|5p|3p|loop|tissueItems)$/', '', $__stem));
        if (isset($__dlMirna[$__code])) { $__sp = $__dlMirna[$__code]; }
    }
    if ($__sp === '') { $__dlGlobal[] = $__f; continue; }

    $__mitoKey = null;
    foreach ($__dlMitoSuf as $__suf => $__k) {
        $__n = strlen($__suf);
        if (strlen($__f) > $__n && substr($__f, -$__n) === $__suf) { $__mitoKey = $__k; break; }
    }
    if ($__mitoKey !== null) { $__dlMito[$__sp][$__mitoKey] = $__f; }
    else                     { $__dlExtra[$__sp][] = $__f; }
}

/* 线粒体表按类群排序：类群顺序用全站统一的那套，物种在类群内按字母排。 */
$__dlClasses = array();
foreach ($__dlMito as $__sp => $__fs) {
    $__c = isset($__dlClass[$__sp]) ? $__dlClass[$__sp] : '';
    if ($__c === '') { $__c = '(unclassified)'; }
    if (!in_array($__c, $__dlClasses, true)) { $__dlClasses[] = $__c; }
}
$__dlClasses = cnido_classes_in($__dlClasses);
$__dlMitoRows = array();       // array(类群, array(物种, 文件…))
foreach ($__dlClasses as $__c) {
    $__sps = array();
    foreach ($__dlMito as $__sp => $__fs) {
        $__cc = isset($__dlClass[$__sp]) ? $__dlClass[$__sp] : '';
        if ($__cc === '') { $__cc = '(unclassified)'; }
        if ($__cc === $__c) { $__sps[] = $__sp; }
    }
    sort($__sps, SORT_NATURAL | SORT_FLAG_CASE);
    $__dlMitoRows[] = array($__c, $__sps);
}
ksort($__dlExtra, SORT_NATURAL | SORT_FLAG_CASE);
sort($__dlGlobal, SORT_NATURAL | SORT_FLAG_CASE);

/* 只在数据库里、没有文件可链的那几份数据。清单与导出脚本共用 cnido_dl_datasets()。
   元组第三格是每个数据集的行数，以前印在表的「Rows」列里；那一列已按要求去掉，
   所以不再现查 —— 那是六个 COUNT(*)，其中 busco 一趟要数 51 万行，没人看就不值。
   格子留着占位，免得 $__ox / $__about 错位（cnido_dl_dataset_count_sql() 也还在，
   要恢复那一列时用得上）。 */
$__dlDs = array();          // 只有「不属于任何主题」的那几份，给上面那一节用
$__dlDsAll = array();       // 全部数据集，下面按主题分区时用
if ($__dlDbOk) {
    foreach (cnido_dl_datasets() as $__t => $__d) {
        $__dlDsAll[$__t] = $__d;
        if ((isset($__d['group']) ? $__d['group'] : 'db') === 'db') {
            $__dlDs[] = array($__t, $__d['title'], null, $__d['xlsx'], $__d['about']);
        }
    }
}

/* 字节数转成人读的形式。下载页上「这个文件多大」是决定点不点的重要信息。 */
if (!function_exists('cnido_dl_size')) {
    function cnido_dl_size($bytes)
    {
        if ($bytes === null) { return ''; }
        $u = array('B', 'KB', 'MB', 'GB', 'TB');
        $i = 0;
        $b = (float)$bytes;
        while ($b >= 1024 && $i < count($u) - 1) { $b /= 1024; $i++; }
        return ($i === 0) ? ((string)(int)$b . ' B') : (number_format($b, 1) . ' ' . $u[$i]);
    }
}
$__dlFileSize = array();   // 文件名 => 字节数（只对要显示的算，读目录属性，不打开文件）
if (!function_exists('cnido_dl_stat_size')) {
    function cnido_dl_stat_size($dir, $fname, &$cache)
    {
        if (!isset($cache[$fname])) {
            $s = @filesize($dir . '/' . $fname);
            $cache[$fname] = ($s === false) ? null : $s;
        }
        return $cache[$fname];
    }
}

/* =====================================================================
 * 下面到本 PHP 块结束，都是「取数 + 搜索/排序/分页」。全部前置到渲染之前：
 * 搜索框下面那行「哪几节有命中」要在印出任何一节之前就知道每一节有多少行。
 * ===================================================================== */

/* miRNA 的八个文件原来一个文件一行、物种用 rowspan 撑起八行。改成和线粒体表
   同一个形状：一个物种一行，文件类型分列（3p、5p、loop、mature、pre、star、组织
   元数据、GFF）。每个文件的大小挪进按钮的 title —— 一格一个按钮，放不下单独一列
   尺寸，这也正是线粒体表的做法。
   认不出后缀的文件不丢：落到下面第二张表，按老的「文件名 + 内容」形状列出。 */
$__dlMirnaCols = array(
    '3p'     => '-3p.fas',
    '5p'     => '-5p.fas',
    'loop'   => '-loop.fas',
    'mature' => '-mature.fas',
    'pre'    => '-pre.fas',
    'star'   => '-star.fas',
    'tissue' => '-tissueItems.txt',
    'gff'    => '.gff',
);
$__dlGrid  = array();   // 物种 => array(列键 => 文件名)
$__dlOther = array();   // 物种 => array(文件名)  后缀认不出的
foreach ($__dlExtra as $__sp => $__fs) {
    foreach ($__fs as $__f) {
        $__hit = null;
        foreach ($__dlMirnaCols as $__k => $__suf) {
            $__n = strlen($__suf);
            if (strlen($__f) >= $__n && substr($__f, -$__n) === $__suf) { $__hit = $__k; break; }
        }
        if ($__hit === null) { $__dlOther[$__sp][] = $__f; }
        else                 { $__dlGrid[$__sp][$__hit] = $__f; }
    }
}

/* 分物种注释包的索引：download.php 每次访问都 stat 225 个文件不划算，索引里
   已经带了物种名、类群与体积。取不到就整段降级，不显示半截清单。 */
$__dlTsDir = $__dlDir . '/transcriptome_assembly';
$__dlTs    = array();
$__dlTsIdx = $__dlTsDir . '/_index.tsv';
if (is_readable($__dlTsIdx) && ($__fh = @fopen($__dlTsIdx, 'r')) !== false) {
    $__head = fgetcsv($__fh, 0, "\t");
    while (($__row = fgetcsv($__fh, 0, "\t")) !== false) {
        if (!is_array($__head) || count($__row) !== count($__head)) { continue; }
        $__dlTs[] = array_combine($__head, $__row);
    }
    fclose($__fh);
}
$__dlTsBase = 'download/transcriptome_assembly';

/* 有注释的 MAG：六个注释表各自可能存在或不存在，用 EXISTS 探一次，
   只给真的有数据的注释类型出按钮（index 上取，129 行 0.17 秒）。 */
$__dlMagRows = array();
if ($__dlDbOk) {
    $__q = mysqli_query($__dlConn,
        "SELECT m.AssemblyAccession, m.Species, m.host, m.class,
                EXISTS(SELECT 1 FROM mag_annot a        WHERE a.mag = m.AssemblyAccession) AS h_annot,
                EXISTS(SELECT 1 FROM mag_go_terms a     WHERE a.mag = m.AssemblyAccession) AS h_go,
                EXISTS(SELECT 1 FROM mag_interpro a     WHERE a.mag = m.AssemblyAccession) AS h_ipr,
                EXISTS(SELECT 1 FROM mag_kegg_terms a   WHERE a.mag = m.AssemblyAccession) AS h_kegg,
                EXISTS(SELECT 1 FROM mag_pfam_hits a    WHERE a.mag = m.AssemblyAccession) AS h_pfam,
                EXISTS(SELECT 1 FROM mag_panther_hits a WHERE a.mag = m.AssemblyAccession) AS h_pthr
           FROM MAGs m
          WHERE EXISTS(SELECT 1 FROM mag_annot a WHERE a.mag = m.AssemblyAccession)
          ORDER BY m.class, m.host, m.Species");
    while ($__q && ($__r = mysqli_fetch_assoc($__q))) { $__dlMagRows[] = $__r; }
}
/* 按 MAG 导出的六种注释，顺序即按钮顺序 */
$__dlMagKinds = array(
    'mag_annot'        => array('Proteins', 'h_annot'),
    'mag_go_terms'     => array('GO',       'h_go'),
    'mag_interpro'     => array('InterPro', 'h_ipr'),
    'mag_kegg_terms'   => array('KEGG',     'h_kegg'),
    'mag_pfam_hits'    => array('Pfam',     'h_pfam'),
    'mag_panther_hits' => array('PANTHER',  'h_pthr'),
);

$__dlHC = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };

/* =====================================================================
 * 搜索 / 排序 / 分页
 *
 * 本页十节内容共用一个搜索词，其中四张长表（分物种文件表 148 行、线粒体
 * 168 行、分物种注释包 225 行、分 MAG 注释 129 行）各自分页、各自排序。
 *
 * 查询串的约定（与全站一致，见 includes/sort_head.php）：
 *   q            搜索词，全页共用
 *   per_page     每页行数，全页共用（25 / 50 / 100）
 *   pf pm pt pg  四张长表的页码
 *   f_ m_ t_ g_  四张长表的 sort/dir 前缀
 * 前缀是必需的：同一个页面上多张服务端排序的表共用 sort/dir 的话，点一张表另一张
 * 会跟着变（sort_head.php 的开头写明了这个坑）。
 *
 * 查询串一律用裸 & 拼（与 go_result.php / browse.php 相同）：交给
 * cnido_sort_link() 时它自己会 htmlspecialchars 一次，先转义就会变成 &amp;amp;。
 * ===================================================================== */
require_once __DIR__ . '/includes/sort_head.php';

$__dlPerPageOpts = array(25, 50, 100);

/* 折叠成可比较的形式：小写 + 下划线当空格。物种表的 species 列是
   Acropora_abrotanoides 这种下划线写法，而页面上印的是 "Acropora abrotanoides"，
   两边折叠成同一串，用户敲哪种都能命中。 */
function cnido_dl_fold($s)
{
    $s = str_replace('_', ' ', (string)$s);
    return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
}

/* 读一个正整数 GET 参数；不像数或越界就退回默认。 */
function cnido_dl_int($key, $def, $max)
{
    $v = isset($_GET[$key]) ? (string)$_GET[$key] : '';
    if ($v === '' || !preg_match('/^[0-9]+$/', $v)) { return $def; }
    $v = (int)$v;
    return ($v < 1 || $v > $max) ? $def : $v;
}

$__dlQ  = cnido_search_term(isset($_GET['q']) ? $_GET['q'] : '', 100);
$__dlPP = cnido_dl_int('per_page', 25, 200);
if (!in_array($__dlPP, $__dlPerPageOpts, true)) { $__dlPP = 25; }

/* 每一节登记一次。paged 的长表才有 page/pfx（页码参数、排序参数前缀）；keys 是
   排序白名单（值写 null —— 数组排序不需要 ORDER BY 表达式，白名单在这里只用来
   校验 $_GET）；tie 是并列键，**必须能唯一定位一行**，否则 usort 的兜底比较会去
   strval 行里的数组字段；match 是搜索命中判定要看的列。 */
$__dlSec = array(
    'files' => array('paged' => true, 'page' => 'pf', 'pfx' => 'f_',
        'anchor' => 'species-files', 'nav' => 'Files per species',
        'label' => 'species with files',
        'keys' => array('default' => null, 'class' => null, 'order' => null, 'species' => null),
        'default' => 'default', 'tie' => array('class', 'order', 'species'),
        'match' => array('class', 'order', 'species', 'blob')),
    'mito' => array('paged' => true, 'page' => 'pm', 'pfx' => 'm_',
        'anchor' => 'mito-genomes', 'nav' => 'Mitochondrial genomes',
        'label' => 'mitochondrial genomes',
        'keys' => array('default' => null, 'class' => null, 'species' => null),
        'default' => 'default', 'tie' => array('class', 'species'),
        'match' => array('class', 'species', 'blob')),

    /* 不分页的小表：参与搜索，但页面上本来就没几行。 */
    'otherfiles' => array('paged' => false, 'anchor' => 'otherfiles',
        'nav' => 'Other files', 'label' => 'other files',
        'match' => array('species', 'file', 'kind', 'blob')),
    'dbonly' => array('paged' => false, 'anchor' => 'datasets',
        'nav' => 'Datasets in the database', 'label' => 'database-only datasets',
        'match' => array('title', 'key', 'about')),
    'tsx' => array('paged' => false, 'anchor' => 'trans-assembly',
        'nav' => 'Transcriptome assembly', 'label' => 'transcriptome-assembly datasets',
        'match' => array('title', 'key', 'about')),
    'ts' => array('paged' => true, 'page' => 'pt', 'pfx' => 't_',
        'anchor' => 'per-species-tables', 'nav' => 'Per-species annotation tables',
        'label' => 'annotation bundles',
        'keys' => array('default' => null, 'class' => null, 'species' => null,
                        'proteins' => null, 'annotated' => null, 'size' => null),
        'default' => 'default', 'tie' => array('species'),
        'match' => array('class', 'species', 'code', 'file')),
    'magx' => array('paged' => false, 'anchor' => 'mags-annotation',
        'nav' => 'MAGs and their annotation', 'label' => 'MAG datasets',
        'match' => array('title', 'key', 'about')),
    'mag' => array('paged' => true, 'page' => 'pg', 'pfx' => 'g_',
        'anchor' => 'mag-annotation-single', 'nav' => 'Annotation for a single MAG',
        'label' => 'MAGs',
        'keys' => array('default' => null, 'acc' => null, 'species' => null,
                        'host' => null, 'class' => null),
        'default' => 'default', 'tie' => array('acc'),
        'match' => array('acc', 'species', 'host', 'class')),
    'omix' => array('paged' => false, 'anchor' => 'multi-omics',
        'nav' => 'Multi-omics results', 'label' => 'multi-omics datasets',
        'match' => array('title', 'key', 'about')),
    'pheno' => array('paged' => false, 'anchor' => 'phenotype-data',
        'nav' => 'Phenotype data', 'label' => 'phenotype datasets',
        'match' => array('title', 'key', 'about')),
);

/* ---- 各节的行。行是关联数组，键名与上面 keys/tie/match 用的是同一套名字。
        数值列额外带一个补零的排序键（p_*）—— cnido_sort_rows() 用 strcasecmp
        比较，"9" 会排在 "10" 后面，只有等宽的数字串才排得对。 ---- */
$__dlRows = array();

/* 分物种文件表：$__dlStatic 一行 -> 一行。文件名数组不放进行里（行里放数组会让
   usort 的兜底比较去 strval 一个数组），另存一份按 $__i 取。 */
$__dlRowFiles = array();
$__dlRows['files'] = array();
foreach ($__dlStatic as $__i => $__r) {
    $__dlRowFiles[$__i] = $__r[3];
    /* 七个文件名接成一个串参与搜索（'blob'）—— 行里不能放数组，cnido_sort_rows()
       的兜底比较会拿 implode 去 strval 它。搜 "gff3" 要能落在这一节，靠的就是它。 */
    $__blob = array();
    foreach ($__r[3] as $__f) { if ($__f !== null) { $__blob[] = $__f; } }
    $__dlRows['files'][] = array('n' => $__i, 'class' => $__r[0],
                                 'order' => $__r[1], 'species' => $__r[2],
                                 'blob' => implode(' ', $__blob));
}
unset($__i, $__r, $__f, $__blob);

/* 线粒体：$__dlMitoRows 是按类群分好组的（物种用 rowspan 撑起类群），
   这里摊平成一行一个物种；类群名留在行里，渲染当前页时再按连续段重算 rowspan。 */
$__dlRows['mito'] = array();
foreach ($__dlMitoRows as $__grp) {
    foreach ($__grp[1] as $__sp) {
        $__fs = $__dlMito[$__sp];
        $__blob = array();
        foreach ($__fs as $__f) { if ($__f !== null) { $__blob[] = $__f; } }
        $__dlRows['mito'][] = array('class' => $__grp[0], 'species' => $__sp,
            'fna'  => isset($__fs['fna'])  ? $__fs['fna']  : null,
            'gb'   => isset($__fs['gb'])   ? $__fs['gb']   : null,
            'gff3' => isset($__fs['gff3']) ? $__fs['gff3'] : null,
            'cds'  => isset($__fs['cds'])  ? $__fs['cds']  : null,
            'pep'  => isset($__fs['pep'])  ? $__fs['pep']  : null,
            'blob' => implode(' ', $__blob));
    }
}
unset($__grp, $__sp, $__fs, $__f, $__blob);

/* 分物种注释包。species 列是拉丁名，code 列是下划线写法，两个都参与搜索；
   数值列带补零排序键。补零宽度：蛋白数 6 位够（最大 215,391），字节数 12 位
   够（到 TB 级）。缺值给空串 —— cnido_sort_rows 的约定是空值恒垫底。 */
$__dlRows['ts'] = array();
foreach ($__dlTs as $__b) {
    $__bp = (isset($__b['proteins'])  && $__b['proteins']  !== '') ? (int)$__b['proteins']  : null;
    $__ba = (isset($__b['annotated']) && $__b['annotated'] !== '') ? (int)$__b['annotated'] : null;
    $__by = (int)$__b['bytes'];
    $__dlRows['ts'][] = array(
        'class'      => isset($__b['class']) ? $__b['class'] : '',
        'species'    => (isset($__b['latin']) && $__b['latin'] !== '') ? $__b['latin'] : $__b['species'],
        'code'       => $__b['species'],
        'file'       => isset($__b['file']) ? $__b['file'] : '',
        'proteins'   => $__bp,
        'annotated'  => $__ba,
        'size'       => $__by,
        'p_proteins' => ($__bp === null) ? '' : sprintf('%09d', $__bp),
        'p_annotated'=> ($__ba === null) ? '' : sprintf('%09d', $__ba),
        'p_size'     => sprintf('%012d', $__by),
    );
}
unset($__b, $__bp, $__ba, $__by);

/* 单个 MAG 的注释按钮。行里只放标量：'n' 是它在 $__dlMagRows 里的下标，渲染当前页
   时按它取回那一行（$__dlMagKinds 的四个按钮要按 MAG 逐表判有没有注释）。不把整行
   $__m 塞进来 —— 行里带数组会让 cnido_sort_rows() 的兜底比较去 strval 一个数组。 */
$__dlRows['mag'] = array();
foreach ($__dlMagRows as $__i => $__m) {
    $__dlRows['mag'][] = array('n' => $__i,
                               'acc' => $__m['AssemblyAccession'], 'species' => $__m['Species'],
                               'host' => $__m['host'], 'class' => $__m['class']);
}
unset($__i, $__m);

/* 「其余文件」这一节是两小张表，但只算一节：miRNA 网格（一行一个物种、一行八个文件）
   和平表（一个文件一行）。两张表的行都进同一个数组，靠 'where' 区分；渲染时再分开。
   网格行把这一物种的全部文件名接成 'blob' 一个字符串参与搜索 —— 行里不能放数组，
   cnido_sort_rows() 的兜底比较会拿 implode 去 strval 它。 */
$__dlRows['otherfiles'] = array();
foreach ($__dlGrid as $__sp => $__set) {
    /* 文件名 + 它的种类说明一起进 blob：网格的列名是「mature sequences」这种说法，
       文件名里只有 -mature.fas —— 搜 "mirna"/"precursor" 要落在这里就得带上种类。 */
    $__blob = array();
    foreach ($__set as $__f) {
        if ($__f !== null) { $__blob[] = $__f . ' ' . cnido_dl_kind($__f); }
    }
    $__dlRows['otherfiles'][] = array('where' => 'grid', 'grid' => $__sp, 'species' => $__sp,
        'file' => '', 'kind' => '', 'blob' => implode(' ', $__blob));
}
foreach ($__dlOther as $__sp => $__fset) {
    foreach (array_values($__fset) as $__f) {
        $__dlRows['otherfiles'][] = array('where' => 'flat', 'grid' => '', 'species' => $__sp,
            'file' => $__f, 'kind' => cnido_dl_kind($__f), 'blob' => '');
    }
}
foreach ($__dlGlobal as $__f) {
    $__dlRows['otherfiles'][] = array('where' => 'flat', 'grid' => '', 'species' => '',
        'file' => $__f, 'kind' => cnido_dl_kind($__f), 'blob' => '');
}
unset($__sp, $__set, $__fset, $__f, $__blob);

/* 主题数据集：$__dlDsAll 按组摊开，一组一节。 */
foreach (array('dbonly' => 'db', 'tsx' => 'trans_assembly', 'magx' => 'mags',
               'omix' => 'omics', 'pheno' => 'phenotype') as $__sec => $__grpName) {
    $__dlRows[$__sec] = array();
    foreach ($__dlDsAll as $__t => $__d) {
        if ((isset($__d['group']) ? $__d['group'] : 'db') !== $__grpName) { continue; }
        $__dlRows[$__sec][] = array('key' => $__t, 'title' => $__d['title'],
                                    'about' => $__d['about'], 'xlsx' => $__d['xlsx']);
    }
}
unset($__sec, $__grpName, $__t, $__d);

/* 转录组组装那一节最前面还有两行是写死的（整表汇总、全物种打包），它们也在页面上、
   也要能搜到，所以同样进 $__dlRows：static 非 0 表示写死行，渲染时按 file_a/file_b
   印按钮。文件不在就不加这一行 —— 加了的话搜索计数里会多出页面上根本没有的行。
   写死行的 title/slug/about 是页面自己的常量，渲染时原样输出（about 里有
   <span class="dl-mono">）。 */
if (is_file($__dlTsDir . '/CnidoSite_325species_transcriptome_annotation.tsv')) {
    $__dlRows['tsx'][] = array('static' => 1, 'key' => '',
        'title' => 'Assembly and annotation summary, 325 of the 326 catalogue species',
        'slug'  => 'CnidoSite_325species_transcriptome_annotation',
        /* 这张表实际是 325 行数据（表头外），比物种表少一个：Pachycerianthus
           multiplicatus 本站只有基因组、没有转录组组装，所以没有可汇总的一行。
           原来写「the table covers the whole list」——「整个名单」是 326 个物种，
           读者一数就能发现少一个，这里把那一个是谁写明。 */
        'about' => 'One row per species in the site\'s species list: taxonomy ID, RNA-seq run and'
                 . ' its SRA link, transcript and protein counts, N50 and longest transcript, the'
                 . ' number of proteins carrying each annotation type, and the source of the'
                 . ' assembly. The 105 species with no assembly yet are present as rows, so the'
                 . ' table lists 325 of the 326 catalogue species; the one absent is'
                 . ' <i>Pachycerianthus multiplicatus</i>, for which the site holds a genome but'
                 . ' no transcriptome assembly. A companion <span class="dl-mono">.csv</span> is'
                 . ' byte-for-byte the same content for spreadsheet users.',
        'fmt_a' => 'TSV', 'file_a' => 'CnidoSite_325species_transcriptome_annotation.tsv',
        'fmt_b' => 'CSV', 'file_b' => 'CnidoSite_325species_transcriptome_annotation.csv');
}
if (is_file($__dlTsDir . '/CnidoSite_transcriptome_annotation_tables.tar.gz')) {
    $__dlRows['tsx'][] = array('static' => 1, 'key' => '',
        'title' => 'Per-species annotation tables, all species at once',
        'slug'  => 'CnidoSite_transcriptome_annotation_tables',
        'about' => 'The same 225 bundles in a single archive, for mirroring the collection or'
                 . ' scripting over every species. Unpacking it gives one directory-less set of'
                 . ' files, so use the per-species archives below if you only need a few species.',
        'fmt_a' => 'TAR.GZ', 'file_a' => 'CnidoSite_transcriptome_annotation_tables.tar.gz',
        'fmt_b' => '', 'file_b' => '');
}

/* ---- 排序/页码状态 ---- */
$__dlSt = array();
foreach ($__dlSec as $__k => $__s) {
    if (empty($__s['paged'])) { continue; }
    list($__so, $__di) = cnido_sort_state($__s['keys'], $__s['default'], array(), $__s['pfx']);
    $__dlSt[$__k] = array('sort' => $__so, 'dir' => $__di,
                          'page' => cnido_dl_int($__s['page'], 1, 100000));
}
unset($__k, $__s, $__so, $__di);

/* ---- 工具 ---- */

/* 当前页里 $f 列（或 $f1.$f2 两列合起来）的连续段长度，用来算 rowspan。
   只看本页：跨页的那一段会在下一页重新起一行，组名不会丢。 */
function cnido_dl_run($rows, $i, $f1, $f2 = null)
{
    $key = function ($r) use ($f1, $f2) {
        return ($f2 === null) ? (string)$r[$f1] : ($r[$f1] . "\x1f" . $r[$f2]);
    };
    $v = $key($rows[$i]);
    $n = 1;
    for ($j = $i + 1; $j < count($rows); $j++) {
        if ($key($rows[$j]) !== $v) { break; }
        $n++;
    }
    return $n;
}

/* 这一节的页所有行是否该印出来。命中 0 时不印表 —— 但**不能**拿它当「有没有
   这一节」的开关（没有搜索词时即使一行都没有也照印，否则空表看着像页面坏了）。 */
function cnido_dl_show($key)
{
    global $__dlPg;
    return $__dlPg[$key]['n_hit'] > 0;
}

/* 把一节的行按「搜索 -> 排序 -> 切片」走一遍。$rows 按值传入，函数内部就地改，
   不动调用方的数组。$idx 是「排序键 => 行里的字段名」。 */
function cnido_dl_prep($key, $rows, $idx)
{
    global $__dlSec, $__dlPP, $__dlQ, $__dlSt;
    $s    = $__dlSec[$key];
    $nAll = count($rows);

    if ($__dlQ !== '') {
        $needle = cnido_dl_fold($__dlQ);
        $keep = array();
        foreach ($rows as $r) {
            foreach ($s['match'] as $f) {
                if (isset($r[$f]) && $r[$f] !== '' && strpos(cnido_dl_fold($r[$f]), $needle) !== false) {
                    $keep[] = $r; break;
                }
            }
        }
        $rows = $keep;
    }
    $nHit = count($rows);

    if (!empty($s['paged'])) {
        $st = $__dlSt[$key];
        cnido_sort_rows($rows, $idx, $st['sort'], $st['dir'], $s['tie']);
        $pages = ($nHit === 0) ? 0 : (int)ceil($nHit / $__dlPP);
        $page  = $st['page'];
        if ($pages > 0 && $page > $pages) { $page = $pages; }
        $slice = array_slice($rows, ($page - 1) * $__dlPP, $__dlPP);
        return array('rows' => $slice, 'n_all' => $nAll, 'n_hit' => $nHit,
                     'page' => $page, 'pages' => $pages,
                     'sort' => $st['sort'], 'dir' => $st['dir']);
    }
    return array('rows' => $rows, 'n_all' => $nAll, 'n_hit' => $nHit,
                 'page' => 1, 'pages' => 1, 'sort' => '', 'dir' => '');
}

/* 当前参数拼成查询串。$dropPage / $dropSort 是**本表**的页码参数与排序参数前缀：
   翻页链接自己接页码，排序表头自己接 sort/dir（cnido_sort_link 会拼），先放进去
   就重复了。$dropPerPage 给「每页行数」下拉框用 —— 它的 URL 自己接 per_page，
   不摘掉就会拼出两个 per_page=。其余各表的状态都带上，免得翻这张表时把另一张的
   页码/排序弄丢。 */
function cnido_dl_qs($dropPage = '', $dropSort = '', $dropPerPage = false)
{
    global $__dlSec, $__dlSt;
    $p = array();
    if (!$dropPerPage) { $p['per_page'] = $GLOBALS['__dlPP']; }
    if ($GLOBALS['__dlQ'] !== '') { $p['q'] = $GLOBALS['__dlQ']; }
    foreach ($__dlSec as $k => $s) {
        if (empty($s['paged'])) { continue; }
        if ($s['page'] !== $dropPage) { $p[$s['page']] = $__dlSt[$k]['page']; }
        if ($s['pfx'] !== $dropSort
            && cnido_sort_qs($__dlSt[$k]['sort'], $__dlSt[$k]['dir'], $s['keys'], $s['default'], $s['pfx']) !== '') {
            $p[$s['pfx'] . 'sort'] = $__dlSt[$k]['sort'];
            $p[$s['pfx'] . 'dir']  = $__dlSt[$k]['dir'];
        }
    }
    return http_build_query($p, '', '&');
}

/* 这一节的排序表头。$attr 是原样接在 <th 后面的属性（宽度、rowspan）。
   查询串要把本表的页码与 sort/dir 都摘掉 —— cnido_sort_link 自己会拼 sort/dir。 */
function cnido_dl_head($key, $col, $label, $attr = '')
{
    global $__dlSec, $__dlPg;
    $s = $__dlSec[$key];
    $p = $__dlPg[$key];
    /* 末尾那个 #锚点：点表头是整页重载，不带锚点的话浏览器停在页面最顶上 —— 而这张表
       可能在下面几千像素处。翻页按钮同理（见 cnido_dl_pager），回来时都还在这一节。 */
    return '<th' . ($attr === '' ? '' : ' ' . $attr) . '>'
         . cnido_sort_link($col, $label, $p['sort'], $p['dir'],
                           cnido_dl_qs($s['page'], $s['pfx']), $s['pfx'],
                           '#' . $s['anchor']) . '</th>';
}

/* 搜索命中 0 的那一节：标题留着（锚点不能凭空消失、页面结构不能跟着搜索词变），
   表格换成一行说明。没有搜索词、或这一节有命中时，返回空串。
   用法： <?= cnido_dl_nomatch('mito') ?> 紧挨在 <?php if (cnido_dl_show('mito')): ?> 前面。 */
function cnido_dl_nomatch($key)
{
    global $__dlPg, $__dlQ;
    if ($__dlQ === '' || $__dlPg[$key]['n_hit'] > 0) { return ''; }
    return '<div class="dl-nomatch">No row in this section matches <b>'
         . htmlspecialchars($__dlQ, ENT_QUOTES, 'UTF-8') . '</b>.</div>';
}

/* 搜索跳转的目标元素 id（页脚前那段脚本用）。
   搜索框在页面第一节（species-files）的说明文字上面，所以命中的就是第一节时停在
   搜索框上 —— 那一节能同时看到搜索框、「哪几节有命中」和第一张表。命中在别的节
   时停在那一节的标题上（用户要的就是「直接显示该部分」）。
   一条都没命中时返回空串：那种情况下页面顶部就写着「Nothing on this page matches」，
   把用户滚到某个「这一节没有匹配」的地方只会更让人糊涂。 */
function cnido_dl_jump()
{
    global $__dlSec, $__dlPg, $__dlQ;
    if ($__dlQ === '') { return ''; }
    foreach ($__dlSec as $k => $s) {
        if ($__dlPg[$k]['n_hit'] > 0) { return ($k === 'files') ? 'dlFind' : $s['anchor']; }
    }
    return '';
}

/* 主题数据集那五节的表体（Dataset / Content / Download 三列）。行取自 $__dlPg，
   即筛选后的结果 —— 搜索能逐节过滤。五张表的表体一模一样，写五遍迟早会有一处
   改漏，所以合成一个函数。 */
function cnido_dl_ds_table($key)
{
    global $__dlPg;
    $h = '';
    foreach ($__dlPg[$key]['rows'] as $__r) {
        /* 写死行（static，key 为空）没有数据库导出，文件按钮由调用方那圈循环自己印
           （见「Transcriptome assembly results」一节）。这里跳过，否则会多印一个
           dataset_export.php?dataset=&format=tsv —— 空 dataset 一律 404，
           而且那一行在页面上有两个重复的下载入口。 */
        if (empty($__r['key'])) { continue; }
        $h .= "  <tr>\n"
            . '    <td align="center"><b>' . htmlspecialchars($__r['title'], ENT_QUOTES, 'UTF-8') . '</b><br />' . "\n"
            . '        <span class="dl-sz dl-mono">' . htmlspecialchars($__r['key'], ENT_QUOTES, 'UTF-8') . '</span></td>' . "\n"
            . '    <td align="left">' . htmlspecialchars($__r['about'], ENT_QUOTES, 'UTF-8') . '</td>' . "\n"
            . '    <td align="center">' . "\n"
            . '      <a class="dl-btn" target="_blank" rel="noopener" href="dataset_export.php?dataset=' . urlencode($__r['key']) . '&amp;format=tsv">TSV</a>' . "\n";
        if (!empty($__r['xlsx'])) {
            $h .= '      <a class="dl-btn" target="_blank" rel="noopener" href="dataset_export.php?dataset=' . urlencode($__r['key']) . '&amp;format=xlsx">XLSX</a>' . "\n";
        }
        $h .= "    </td>\n  </tr>\n";
    }
    return $h;
}

/* 一张表的分页条。没有命中、或只有一页且不需要改每页行数时，只印一行计数。
   命中 0 时返回空串 —— 印一个没有行的页码条只会让人以为表坏了。 */
function cnido_dl_pager($key)
{
    global $__dlSec, $__dlPP, $__dlPerPageOpts, $__dlPg;
    $p = $__dlPg[$key];
    if (empty($__dlSec[$key]['paged']) || $p['n_hit'] <= 0) { return ''; }
    $s    = $__dlSec[$key];
    $self = htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8');
    $open = $self . '?' . cnido_dl_qs($s['page'], '');
    $pg   = htmlspecialchars($s['page'], ENT_QUOTES, 'UTF-8');
    $gid  = 'gotoPage_' . preg_replace('/[^A-Za-z0-9_]/', '', $key);
    /* 翻页/改每页行数/跳到第 N 页，都是整页 GET 重载。链接末尾带上这一节的锚点，
       重载后浏览器才不会停在页面最顶上（页脚前那段脚本负责把位置摆正）。 */
    $fr   = '#' . $s['anchor'];

    $from = ($p['page'] - 1) * $__dlPP + 1;
    $to   = $from + count($p['rows']) - 1;
    if ($p['n_hit'] === $p['n_all']) {
        $count = 'Showing ' . number_format($from) . '&ndash;' . number_format($to)
               . ' of ' . number_format($p['n_all']) . ' ' . $s['label'];
    } else {
        $count = 'Showing ' . number_format($from) . '&ndash;' . number_format($to)
               . ' of ' . number_format($p['n_hit']) . ' matching'
               . ' &middot; ' . number_format($p['n_all']) . ' in this table';
    }

    $h  = '<div class="pagination-container">';
    $h .= '<div class="pagination-info"><div class="total-records">' . $count . '</div>';
    $h .= '<div class="per-page-selector"><label for="pp_' . $gid . '">Show:</label>'
        . '<select id="pp_' . $gid . '" onchange="window.location.href=\''
        . $self . '?' . cnido_dl_qs($s['page'], '', true)
        . '&amp;per_page=\'+this.value+\'&amp;' . $pg . '=1' . $fr . '\'">';
    foreach ($__dlPerPageOpts as $o) {
        $h .= '<option value="' . $o . '"' . ($o == $__dlPP ? ' selected="selected"' : '') . '>' . $o . '</option>';
    }
    $h .= '</select><span>rows per page</span></div></div>';

    if ($p['pages'] > 1) {
        $mk = function ($n, $txt) use ($open, $pg, $fr) {
            return '<a class="page-btn" href="' . $open . '&amp;' . $pg . '=' . $n . $fr . '">' . $txt . '</a>';
        };
        $h .= '<div class="pagination-nav">';
        $h .= ($p['page'] > 1)
            ? $mk(1, '&laquo; First') . $mk($p['page'] - 1, '&lsaquo; Prev')
            : '<span class="page-btn disabled">&laquo; First</span><span class="page-btn disabled">&lsaquo; Prev</span>';

        $stPg = max(1, $p['page'] - 3);
        $enPg = min($p['pages'], $p['page'] + 3);
        if ($stPg > 1) {
            $h .= $mk(1, '1');
            if ($stPg > 2) { $h .= '<span class="page-btn disabled">&hellip;</span>'; }
        }
        for ($i = $stPg; $i <= $enPg; $i++) {
            $h .= ($i == $p['page'])
                ? '<span class="page-btn active">' . $i . '</span>'
                : $mk($i, $i);
        }
        if ($enPg < $p['pages']) {
            if ($enPg < $p['pages'] - 1) { $h .= '<span class="page-btn disabled">&hellip;</span>'; }
            $h .= $mk($p['pages'], $p['pages']);
        }
        $h .= ($p['page'] < $p['pages'])
            ? $mk($p['page'] + 1, 'Next &rsaquo;') . $mk($p['pages'], 'Last &raquo;')
            : '<span class="page-btn disabled">Next &rsaquo;</span><span class="page-btn disabled">Last &raquo;</span>';
        $h .= '</div>';

        $h .= '<div class="go-to-page"><label for="' . $gid . '">Go to page:</label>'
            . '<input type="number" id="' . $gid . '" min="1" max="' . $p['pages'] . '" value="' . $p['page'] . '" />'
            . '<button type="button" onclick="window.location.href=\'' . $open . '&amp;' . $pg . '='
            . '\'+document.getElementById(\'' . $gid . '\').value+\'' . $fr . '\'">Go</button>'
            . '<span>of ' . $p['pages'] . ' pages</span></div>';
    }
    $h .= '</div>';
    return $h;
}

/* 搜索框下面那行「哪几节有命中」。只在搜索时出现，按节的顺序列出，每条是一个
   跳到该节的链接。没有命中的节不列 —— 但页面里那些节仍然在（印一行「这一节没有
   匹配」），否则锚点会凭空消失、页面结构也跟着变。 */
function cnido_dl_hitnav()
{
    global $__dlSec, $__dlPg, $__dlQ;
    if ($__dlQ === '') { return ''; }
    $hit = array(); $miss = 0;
    foreach ($__dlSec as $k => $s) {
        if ($__dlPg[$k]['n_hit'] > 0) { $hit[] = array($s['anchor'], $s['label'], $__dlPg[$k]['n_hit']); }
        else { $miss++; }
    }
    if (!$hit) {
        return '<p class="dl-hitnav dl-hitnav-none">Nothing on this page matches <b>'
             . htmlspecialchars($__dlQ, ENT_QUOTES, 'UTF-8') . '</b>. Try a shorter term, or a '
             . 'species name, an accession such as <span class="dl-mono">GCA_</span>, or a word from a '
             . 'file or dataset name. <a href="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8')
             . '">Clear the search</a> to see everything.</p>';
    }
    $h = '<p class="dl-hitnav">' . count($hit) . ' of ' . count($__dlSec) . ' sections match <b>'
       . htmlspecialchars($__dlQ, ENT_QUOTES, 'UTF-8') . '</b>: ';
    $bits = array();
    foreach ($hit as $x) {
        $bits[] = '<a href="#' . htmlspecialchars($x[0], ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars($x[1], ENT_QUOTES, 'UTF-8')
                . ' <span class="dl-hitn">' . number_format($x[2]) . '</span></a>';
    }
    $h .= implode(' &middot; ', $bits);
    if ($miss > 0) { $h .= ' <span class="dl-hitmiss">(' . $miss . ' section' . ($miss == 1 ? '' : 's') . ' with no match)</span>'; }
    return $h . '</p>';
}

/* ---- 全部走一遍。分页的长表要同时给出 $idx（排序键 -> 字段名）。 ---- */
$__dlPg = array();
$__dlPg['files'] = cnido_dl_prep('files', $__dlRows['files'],
    array('class' => 'class', 'order' => 'order', 'species' => 'species'));
$__dlPg['mito'] = cnido_dl_prep('mito', $__dlRows['mito'],
    array('class' => 'class', 'species' => 'species'));
$__dlPg['ts'] = cnido_dl_prep('ts', $__dlRows['ts'],
    array('class' => 'class', 'species' => 'species',
          'proteins' => 'p_proteins', 'annotated' => 'p_annotated', 'size' => 'p_size'));
$__dlPg['mag'] = cnido_dl_prep('mag', $__dlRows['mag'],
    array('acc' => 'acc', 'species' => 'species', 'host' => 'host', 'class' => 'class'));
foreach (array('otherfiles', 'dbonly', 'tsx', 'magx', 'omix', 'pheno') as $__k) {
    $__dlPg[$__k] = cnido_dl_prep($__k, $__dlRows[$__k], array());
}
unset($__k);
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/js/rwd-tables.js" defer></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">  
<meta name="renderer" content="webkit">
<title>Download - CnidoSite</title>
<meta name="description" content="Browse and download the files held for each cnidarian species &mdash; genome assemblies, annotations, transcriptomes, mitochondrial genomes and miRNA sets" />
<link href="/templatemo_style.css?v=<?php echo (int)@filemtime(__DIR__ . '/templatemo_style.css'); ?>" rel="stylesheet" type="text/css" />
<script LANGUAGE="JavaScript" src="js/jquery.min.js" type="text/javascript"></script>
<style type="text/css">
<?php /* 全页的表都是同一套控件：gridtable 的壳 + 一个 a.dl-btn。放在 head 里 ——
   XHTML 1.0 Transitional 不允许 body 里出现 <style>。左边那道竖条属于同一族
   颜色，跟着 .dl-btn 一起走，整页才是一个色系。 */ ?>
.dl-h2 {
    font-size: 20px;
    font-weight: bold;
    color: #1e293b;
    margin: 36px 0 8px 0;
    padding-left: 10px;
    border-left: 5px solid #1d4ed8;
}
.dl-note {
    color: #475569;
    font-size: 16px;
    line-height: 1.75;
    margin: 0 0 14px 0;
}
<?php /* 全页 2,857 个下载按钮。之前是实心 #90B0D9（灰扑扑的藕荷蓝）配深海军蓝字：
   这个色既不属于导航的品牌蓝（#19456a / #336699）也不属于表格的岩蓝灰
   （#f1f5f9 / #334155），两千多个实心块铺下来整页就是一片发闷的蓝。现在改成
   链接蓝 #1d4ed8 的淡色 chip：底 #dbeafe、描边 #bfdbfe、字 #1d4ed8，和表里
   链接本身同色系，安静但一眼仍是个按钮。悬停铺满 #1d4ed8 反白 —— 一行七个
   按钮，靠这一下确认自己点的是哪一格。
   对比度：#1d4ed8 on #dbeafe = 5.49:1，白 on #1d4ed8 = 6.70:1，都过 AA。
   底色的 !important 是必需的：table.gridtable a（0,1,2）比 a.dl-btn（0,1,1）
   特异性高，不写就会被链接蓝顶掉。 */ ?>
a.dl-btn {
    display: inline-block;
    background: #dbeafe;
    color: #1d4ed8 !important;
    border: 1px solid #bfdbfe;
    text-decoration: none !important;
    font-size: 15px;
    line-height: 1.5;
    padding: 2px 10px;
    border-radius: 4px;
    white-space: nowrap;
}
a.dl-btn:hover { background: #1d4ed8; border-color: #1d4ed8; color: #ffffff !important; }
<?php /* 单写一条，不并进上面那条 —— 认不得 :focus-visible 的浏览器会把整个选择器
   列表整条丢掉，连 hover 一起丢。 */ ?>
a.dl-btn:focus-visible { background: #1d4ed8; border-color: #1d4ed8; color: #ffffff !important; }
<?php /* 两张下载表都挂着 gridtable：表头、行分隔线、斑马纹、悬停、字号全部由共用样式
   提供（与 core 的 table.cc 一致）。原来这里重复声明了 13px 字号和 hover 底色
   （写的就是共用的 #f1f5f9），字号已在统一时删掉。
   148 行 × 10 列全靠 hover 让整行亮起来才不串行 —— 这条现在是共用样式给的，
   但刻意留在这里当注释：读到一个按钮时不会数错物种，靠的就是它。 */ ?>
.dl-tbl { margin-bottom: 6px; }
.dl-tbl td { vertical-align: middle; }
<?php /* 第二张表（不属于任何单一物种的那几份）与上面那张表之间留一口气 */ ?>
.dl-gap { margin: 18px 0 10px 0; }
<?php /* 「Datasets available only from the database」那张表：单元格里是两行标题、一句说明和
   两枚并排的按钮。早年 gridtable 带 white-space:pre-wrap，会把 PHP 源码里的换行和
   缩进当成真空行 —— 下载格实测三行高（textContent 就是 "\n      TSV\n    "），
   把全表行高顶到 89–114px。那条 pre-wrap 已在共用样式里删掉，这里留着当保险丝：
   这张表不需要保留空白，常规换行下按钮并排、行高由内容决定。 */ ?>
table.dl-ds td, table.dl-ds th { white-space: normal; }
.dl-sz { color: #64748b; font-size: 12px; }
.dl-mono { font-family: Consolas, "Courier New", monospace; font-size: 15px; }
.dl-none { color:#64748b; }
.dl-empty {
    background: #f1f5f9;
    border-left: 4px solid #94a3b8;
    padding: 12px 16px;
    color: #475569;
    font-size: 16px;
    line-height: 1.7;
    margin: 0 0 16px 0;
}
<?php /* 搜索框 + 分节跳转。搜索框常驻 —— 一条都没搜到时它也必须还在，否则用户除了
   浏览器后退没有别的路可走（MAGs.php / busco_result.php 记过同一条）。 */ ?>
.dl-find {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-left: 5px solid #1d4ed8;
    border-radius: 6px;
    padding: 14px 18px;
    margin: 0 0 18px 0;
}
.dl-find form { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin: 0; }
.dl-find label { font-size: 15px; font-weight: bold; color: #1e293b; }
.dl-find input[type="text"] {
    flex: 1 1 320px; min-width: 180px;
    padding: 8px 12px; font-size: 15px;
    border: 1px solid #cbd5e1; border-radius: 6px;
    background: #ffffff; color: #1e293b;
}
.dl-find button {
    padding: 8px 18px; font-size: 15px; font-weight: 500;
    background: #1d4ed8; border: 1px solid #1d4ed8; border-radius: 6px;
    color: #ffffff !important; cursor: pointer;
}
.dl-find button:hover { background: #1e40af; border-color: #1e40af; }
a.dl-find-clear { font-size: 14px; color: #475569 !important; }
.dl-find-hint { color: #64748b; font-size: 16px; line-height: 1.6; margin: 8px 0 0 0; }
<?php /* 分节跳转条：整页很长，先给一条能直接跳的索引。 */ ?>
.dl-jump { margin: 10px 0 0 0; line-height: 2.2; }
.dl-jump a {
    display: inline-block; margin: 0 6px 0 0; padding: 1px 9px;
    font-size: 13px; background: #eef2f7; border: 1px solid #dbe3ec;
    border-radius: 10px; color: #334155 !important; text-decoration: none !important;
}
.dl-jump a:hover { background: #dbeafe; border-color: #bfdbfe; color: #1d4ed8 !important; }
<?php /* 搜索命中的节清单 */ ?>
.dl-hitnav { font-size: 14px; color: #475569; line-height: 1.9; margin: 10px 0 0 0; }
.dl-hitnav a { color: #1d4ed8 !important; }
.dl-hitn { color: #64748b; font-size: 12px; }
.dl-hitmiss { color: #94a3b8; font-size: 13px; }
.dl-hitnav-none {
    background: #f1f5f9; border-left: 4px solid #94a3b8; border-radius: 4px;
    padding: 10px 14px; color: #475569;
}
<?php /* 搜索把每张表都缩成「只剩命中的行」，这本身就是最强的强调，所以不再逐格标黄：
   单元格里混着 <i>/<b>/<a>，逐格加 <mark> 要拆开来重新转义，得不偿失。 */ ?>
<?php /* 命中 0 的节：标题留着（锚点不能凭空消失），表格换成一行说明。 */ ?>
.dl-nomatch {
    background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 4px;
    padding: 10px 14px; color: #64748b; font-size: 14px; margin: 0 0 16px 0;
}
<?php /* 跳转目标不要贴在视口最上沿（与 includes/gene_panels_common.php 的 .cn-anchor 同做法） */ ?>
h2.dl-h2, h3.dl-h2 { scroll-margin-top: 14px; }
<?php /* 搜索框自己也是一个跳转目标（命中的是第一节时就停在这里），同样别贴着视口上沿 */ ?>
.dl-find { scroll-margin-top: 14px; }
<?php /* 可排序表头要跟同一行里不可排序的表头同色。sort_head.php 里那条
   a.cnido-sort-link{color:inherit} 是 (0,1,1)，而外链样式表里的
   table.gridtable a 是 (0,1,2)，压不过 —— 结果「Class/Order/Species」是链接蓝、
   旁边的「Genome/.fa.gz」是表头灰，同一行两种颜色。这里写成 (0,2,3) 压过去。
   页面自己的 <style> 在 head 里，比 function 里织出来的那份 <style> 先出现，
   所以只能靠特异性赢，不能靠顺序。 */ ?>
table.gridtable th a.cnido-sort-link,
table.gridtable th a.cnido-sort-link:visited { color: inherit; }
table.gridtable th a.cnido-sort-link:hover { color: #1d4ed8; }
<?php /* 分页控件。结构与类名照 go_result.php 搬过来（templatemo_style.css 里没有，
   每个用到分页的页面各自带一份，这是本站既有的做法）。两处改动：
   · 配色规则写成 a.page-btn 而不是 .page-btn —— 外链样式表里的 a:link 是
     (0,1,1)，压得过 .page-btn (0,1,0)，光写类名的话普通页按钮会变成链接蓝，
     只有 .active 因为 (0,2,0) 才幸免（data_statistics.php:126 记过同一个坑）；
   · 去掉白卡片与阴影：那一套在 go_result.php 上是一张表独占一页才好看，本页
     四张表各带一条，铺下来太重。 */ ?>
.pagination-container {
    border-top: 1px solid #e2e8f0;
    margin: 10px 0 4px 0;
    padding-top: 10px;
}
.pagination-info {
    display: flex; justify-content: space-between; align-items: center;
    flex-wrap: wrap; gap: 10px; margin-bottom: 8px;
}
.total-records {
    background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af;
    padding: 5px 12px; border-radius: 6px; font-size: 13px; font-weight: 600;
}
.per-page-selector { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #475569; }
.per-page-selector select {
    padding: 5px 10px; border-radius: 6px; border: 1px solid #cbd5e1;
    background: #ffffff; font-size: 14px; cursor: pointer;
}
.pagination-nav {
    display: flex; justify-content: center; align-items: center;
    flex-wrap: wrap; gap: 6px; margin: 8px 0;
}
.page-btn {
    display: inline-block; padding: 6px 12px; min-width: 34px; text-align: center;
    border: 1px solid #e2e8f0; border-radius: 6px; background: #ffffff;
    font-size: 14px; font-weight: 500; text-decoration: none;
}
a.page-btn, a.page-btn:visited { color: #475569; }
a.page-btn:hover { background: #eff6ff; border-color: #1d4ed8; color: #1d4ed8; }
.page-btn.active { background: #1d4ed8; border-color: #1d4ed8; color: #ffffff; font-weight: 600; }
.page-btn.disabled { opacity: 0.45; cursor: not-allowed; }
.go-to-page {
    display: flex; justify-content: center; align-items: center;
    flex-wrap: wrap; gap: 8px; font-size: 13px; color: #475569;
}
.go-to-page input {
    width: 66px; padding: 5px 8px; text-align: center; font-size: 14px;
    border: 1px solid #cbd5e1; border-radius: 6px;
}
.go-to-page button {
    padding: 5px 14px; background: #1d4ed8; color: #ffffff !important;
    border: 1px solid #1d4ed8; border-radius: 6px; font-size: 14px; cursor: pointer;
}
.go-to-page button:hover { background: #1e40af; border-color: #1e40af; }
</style>
</head>
<body>
<div id="templatemo_header_wrapper">

	<div id="templatemo_header">
    
    	<div id="site_logo"></div>
    
    </div> <!-- end of header -->

</div> <!-- end of header wrapper -->

<?php /*导航栏*/ ?>
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
			<li><a href="#">Phenotype</a>
				<ul>
					<li><a href="/phenotype.php?class=all">All</a></li>
					<li><a href="/phenotype.php?class=Cubozoa">Cubozoa</a></li>
					<li><a href="/phenotype.php?class=Hexacorallia">Hexacorallia</a></li>
					<li><a href="/phenotype.php?class=Octocorallia">Octocorallia</a></li>
					<li><a href="/phenotype.php?class=Hydrozoa">Hydrozoa</a></li>
					<li><a href="/phenotype.php?class=Scyphozoa">Scyphozoa</a></li>
				</ul>
			</li>
			<li><a href="#">Tools</a>
				<ul>
					<li><a href="/GSEA/GSEA.php">Gene Sets Analysis</a></li>
					<li><a href="/blast/blast.php">BLAST</a></li>
					<li><a href="/primer3plus/primer3.html">Primer Design</a></li>
					<li><a href="/jbrowse.php">JBrowse</a></li>
				</ul>
			</li> 
			<li><a href="/download.php" class="current">Download</a></li>
			<li><a href="#">Help</a>
				<ul>
					<li><a href="/data_statistics.php">Statistics</a></li>
					<li><a href="/tutorial.php">User Manual</a></li>
					<li><a href="/submit_comments.php">Data Submit</a></li>
					<li><a href="/contact.php" class="last">Contact Us</a></li>
				</ul>
			</li>
		</ul>
	</div> <!-- end of menu -->
</div> <!-- end of menu wrapper -->

<div id="tempatemo_content_wrapper">
<div id="templatemo_content">
<div id="column">

		
<legend><img src="./images/header.jpg" height="35px"  style="margin-bottom:-10px">&nbsp;<b>Download</b></legend>
<p class="paleo-intro">Genome and gene sequences in FASTA, gene annotations in GFF3, and functional annotation (InterPro, protein domains, GO terms, KEGG pathways, gene families) are all downloadable from this page. Everything served from the site's download directory is reachable here: the per-species table below, the <a href="#additionalfiles">mitochondrial genomes, microRNA sets and other files</a> named after short species codes, and the <a href="#datasets">datasets that exist only in the database</a> &mdash; BUSCO results, Ubs family assignments, epigenome and single-cell sample metadata &mdash; which are exported on demand.</p>
<?php /* 搜索框 + 分节跳转 + 命中清单。全页共用一个搜索词。刻意放在所有表格与计数
         之外：一条都没搜到时它也必须还在，否则用户除了浏览器后退没有别的路可走
         （与 MAGs.php / busco_result.php / sn_data.php 同一条约定）。 */ ?>
<div class="dl-find" id="dlFind">
  <form method="get" action="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>">
    <label for="dlQ">Search this page</label>
    <input type="text" id="dlQ" name="q" value="<?= htmlspecialchars($__dlQ, ENT_QUOTES, 'UTF-8') ?>"
           autocomplete="off" placeholder="species, class, file name, MAG accession, dataset" />
    <button type="submit">Search</button>
<?php if ($__dlQ !== ''): ?>
    <a class="dl-find-clear" href="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>">Clear</a>
<?php endif; ?>
  </form>
  <p class="dl-find-hint">
    One term, applied to every table on this page at once: species and class names, file names, MAG
    accessions and dataset names. A section with no match keeps its heading and says so, so the
    anchors below still land where they always did.
  </p>
  <p class="dl-jump"><b>Jump to:</b>
<?php /* 只列页面上真有数据的节：某个数据源这一天没读到（例如线粒体目录空）时，
         它的锚点在页面里根本不存在，列在这里点下去就是原地不动。 */
      foreach ($__dlSec as $__sk => $__ss):
        if ($__dlPg[$__sk]['n_all'] <= 0) { continue; } ?>
    <a href="#<?= htmlspecialchars($__ss['anchor'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($__ss['nav'], ENT_QUOTES, 'UTF-8') ?></a>
<?php endforeach; unset($__sk, $__ss); ?>
  </p>
<?= cnido_dl_hitnav() ?>
</div>
<h2 class="dl-h2" id="species-files">Per-species files &mdash;
  <?= number_format($__dlPg['files']['n_all']) ?> species</h2>
<p class="dl-note">
  One row per species whose genome files are available here, grouped by class and order.
  <?php /* 原标题写「one row per species with a genome assembly」，读起来像是收录了全部有
           基因组的物种，而 speciesinfo 里带组装号的物种有 321 个、这张表只有 148 行；
           这 148 行确实是「文件放在本站」的那一批（每行都有 .fa.gz），改按实际口径说。 */ ?>
  <i>Desmophyllum pertusum</i> and <i>Lophelia pertusa</i> are the same organism catalogued
  under two names &mdash; they share NCBI Taxonomy ID 174260 &mdash; and each name has its own
  file set, so both are listed separately. Each button downloads one
  file: the column heading names what it holds and the file extension you will receive. A blank cell
  means that file has not been deposited for that species, and every table on this page uses the same
  button and the same layout. Hover a button for the file name and its compressed size. The column
  headings sort the whole table, not just the rows on screen.
</p>
<?= cnido_dl_nomatch('files') ?>
<?php if (cnido_dl_show('files')): ?>
<table class="gridtable dl-tbl">
  <tr align="center" style="font-weight: bold;">
    <?= cnido_dl_head('files', 'class', 'Class', 'rowspan="2" width="9%"') ?>
    <?= cnido_dl_head('files', 'order', 'Order', 'rowspan="2" width="10%"') ?>
    <?= cnido_dl_head('files', 'species', 'Species', 'rowspan="2" width="14%"') ?>
    <th colspan="5">Sequences And GFF3</th><th colspan="2" width="17%">Annotation</th>
  </tr>
  <tr align="center" style="font-weight: bold;">
    <td>Genome<br /><span class="dl-mono">.fa.gz</span></td><td>CDS<br /><span class="dl-mono">.cds.gz</span></td><td>Transcript<br /><span class="dl-mono">.transcript.gz</span></td><td>Protein<br /><span class="dl-mono">.pep.gz</span></td><td>GFF3<br /><span class="dl-mono">.gff3.gz</span></td><td>Basic annotation<br /><span class="dl-mono">.anno.gz</span></td><td>Gene Family<br /><span class="dl-mono">.genefamily.gz</span></td>
  </tr>
<?php
/* rowspan 按当前页的连续段现算：跨页的那一段会在下一页重新起一行，类群名不会丢。
   段长只看本页 —— $__fx 就是本页那 $__dlPP 行。 */
$__fx = $__dlPg['files']['rows'];
foreach ($__fx as $__i => $__row):
    $__prev  = ($__i > 0) ? $__fx[$__i - 1] : null;
    $__newC  = ($__prev === null || $__prev['class'] !== $__row['class']);
    $__newO  = ($__newC || $__prev['order'] !== $__row['order']);
    $__spanC = $__newC ? cnido_dl_run($__fx, $__i, 'class') : 0;
    $__spanO = $__newO ? cnido_dl_run($__fx, $__i, 'class', 'order') : 0;
?>
  <tr align="center">
<?php if ($__spanC): ?>
    <td rowspan="<?= $__spanC ?>"><?= $__dlHC($__row['class']) ?></td>
<?php endif; ?>
<?php if ($__spanO): ?>
    <td rowspan="<?= $__spanO ?>"><?= $__dlHC($__row['order']) ?></td>
<?php endif; ?>
    <td align="left"><b><i><?= $__dlHC($__row['species']) ?></i></b></td>
<?php foreach ($__dlRowFiles[$__row['n']] as $__f): ?>
<?php   if ($__f === null): ?>
    <td class="dl-none">&nbsp;</td>
<?php   else: $__b = cnido_dl_stat_size($__dlDir, $__f, $__dlFileSize); ?>
    <td><a class="dl-btn" target="_blank" rel="noopener" title="<?= $__dlHC($__f) ?><?= $__b !== null ? ' (' . cnido_dl_size($__b) . ')' : '' ?>"
           href="<?= $__dlHC(cnido_dl_url($__f)) ?>">Download</a></td>
<?php   endif; ?>
<?php endforeach; ?>
  </tr>
<?php endforeach; ?>
</table>
<?= cnido_dl_pager('files') ?>
<?php endif; /* cnido_dl_show('files') */ ?>

<?php
/* =====================================================================
 * 以下内容由脚本生成，补上上面那张静态表没有覆盖的文件。
 * 三段：
 *   ① 线粒体基因组（短码命名，静态表里一个都没有）
 *   ② 其余零散文件（miRNA 序列与注释、miRNA.csv、泛基因组家族数据）
 *   ③ 只在数据库里、没有文件可链的数据集
 * ===================================================================== */
$__dlMitoFiles = 0;
foreach ($__dlMito as $__fs) { $__dlMitoFiles += count($__fs); }
$__dlExtraFiles = 0;
foreach ($__dlExtra as $__fs) { $__dlExtraFiles += count($__fs); }
$__dlTotal = count($__dlAll);
$__dlCovered = $__dlTotal - $__dlMissing;
?>

<?php if ($__dlMissing > 0): ?>
<h2 class="dl-h2" id="additionalfiles">Additional files in this collection</h2>
<p class="dl-note">
  The table above covers <b><?= number_format($__dlCovered) ?></b> of the
  <b><?= number_format($__dlTotal) ?></b> files served from this site's download directory: the
  genome assemblies, CDS, transcripts, proteins, GFF3 and functional annotations named after the
  species, in compressed form. The remaining <b><?= number_format($__dlMissing) ?></b> files —
  named after short species codes rather than full Latin names, so they were never part of that
  table — are listed below. Everything on this page is delivered by the same handler, so a link
  here behaves exactly like a link above.
</p>

<?php if (!empty($__dlMito)): ?>
<h3 class="dl-h2" id="mito-genomes">Mitochondrial genomes &mdash; <?= count($__dlMito) ?> species,
  <?= number_format($__dlMitoFiles) ?> files</h3>
<p class="dl-note">
  One set per species: the assembled mitochondrial genome and the annotation derived from it.
  GenBank and GFF3 come from the deposited record; the CDS and protein sets are the sequences
  extracted from it. The table lists the species whose files are held on the server, so it is not the
  same set of species as the <a href="/mitdata.php">Mitogenomic Data</a> module, which covers every
  species that has a mitochondrial record; a record can exist without files, and a file can exist
  on its own. The two headings sort the whole table, and each button's tooltip gives the file name
  and its size.
</p>
<?= cnido_dl_nomatch('mito') ?>
<?php if (cnido_dl_show('mito')): ?>
<table class="gridtable dl-tbl">
  <tr align="center" style="font-weight: bold;">
    <?= cnido_dl_head('mito', 'class', 'Class', 'width="9%"') ?>
    <?= cnido_dl_head('mito', 'species', 'Species', 'width="21%"') ?>
    <th>Genome<br /><span class="dl-mono">.fna</span></th>
    <th>GenBank flat file<br /><span class="dl-mono">.gb</span></th>
    <th>GFF3 annotation<br /><span class="dl-mono">.gff3</span></th>
    <th>CDS sequences<br /><span class="dl-mono">_cds.fna</span></th>
    <th>Protein sequences<br /><span class="dl-mono">_pep.faa</span></th>
  </tr>
<?php
/* rowspan 按当前页的连续段现算（与第一节同一个做法）：跨页的那一段在下一页重新
   起一行，类群名不会丢。按物种排序时同类的物种不再相邻，段会自然变短。 */
$__mx = $__dlPg['mito']['rows'];
foreach ($__mx as $__i => $__row):
    $__newC = ($__i === 0 || $__mx[$__i - 1]['class'] !== $__row['class']);
?>
  <tr align="center">
<?php if ($__newC): ?>
    <td rowspan="<?= cnido_dl_run($__mx, $__i, 'class') ?>"><?= $__dlHC($__row['class']) ?></td>
<?php endif; ?>
    <td align="left"><b><i><?= $__dlHC($__row['species']) ?></i></b></td>
<?php /* 顺序与表头一致：.fna / .gb / .gff3 / _cds.fna / _pep.faa */ ?>
<?php foreach (array('fna', 'gb', 'gff3', 'cds', 'pep') as $__k):
        $__f = $__row[$__k]; ?>
<?php   if ($__f === null): ?>
    <td class="dl-none">&nbsp;</td>
<?php   else: $__b = cnido_dl_stat_size($__dlDir, $__f, $__dlFileSize); ?>
    <td><a class="dl-btn" target="_blank" rel="noopener" title="<?= $__dlHC($__f) ?><?= $__b !== null ? ' (' . cnido_dl_size($__b) . ')' : '' ?>"
           href="<?= $__dlHC(cnido_dl_url($__f)) ?>">Download</a></td>
<?php   endif; ?>
<?php endforeach; ?>
  </tr>
<?php endforeach; ?>
</table>
<?= cnido_dl_pager('mito') ?>
<?php endif; /* cnido_dl_show('mito') */ ?>

<?php endif; /* $__dlMito */ ?>

<?php if (!empty($__dlExtra) || !empty($__dlGlobal)):
/* $__dlMirnaCols / $__dlGrid / $__dlOther 已经在上面第一个 PHP 块里算好了（搜索要
   在这些行上做，所以取数必须前置）。 */
?>
<h3 class="dl-h2" id="otherfiles">Other files in the download directory &mdash;
  <?= count($__dlGrid) + count($__dlOther) ?> species,
  <?= number_format($__dlExtraFiles + count($__dlGlobal)) ?> files</h3>
<p class="dl-note">
  Files that are not tied to a genome assembly: microRNA sequences and their genome coordinates
  for the <?= count($__dlGrid) ?> species covered by the <a href="/miRNA_analysis.php">miRNA-seq Analysis</a>
  module, the combined miRBase table, and the gene-family data behind the
  <a href="/pan-geneset.php">Pan-geneset</a> module. The microRNA sets are laid out one species
  per row, with one column per file type; each button's tooltip gives the file name and its size.
  Both tables below answer to the search box: a species is kept when its name or any of its file
  names matches.
</p>
<?= cnido_dl_nomatch('otherfiles') ?>
<?php if (cnido_dl_show('otherfiles')):
/* 这一节是两小张表（miRNA 网格 + 平表），命中行合起来算一节：先按 'where' 把筛选后
   的行分成两拨，各自渲染。网格表按物种名回查 $__dlGrid（行里只放标量，文件名不进
   行 —— 进去会让排序的兜底比较去 strval 一个数组）。 */
$__ofGrid = array(); $__ofFlat = array();
foreach ($__dlPg['otherfiles']['rows'] as $__r) {
    if ($__r['where'] === 'grid') { $__ofGrid[] = $__r['grid']; }
    else { $__ofFlat[] = $__r; }
}
unset($__r);
?>
<?php if ($__ofGrid): ?>
<table class="gridtable dl-tbl">
  <tr align="center" style="font-weight: bold;">
    <th width="16%">Species</th>
<?php foreach ($__dlMirnaCols as $__k => $__suf):
        /* 列标题直接取自 cnido_dl_kind()，去掉 "miRNA " 前缀，免得同一批文件的措辞
           在两处各写一份、日后对不上。 */
        $__lab = preg_replace('/^miRNA /', '', cnido_dl_kind('x' . $__suf)); ?>
    <th width="10.5%"><?= htmlspecialchars($__lab, ENT_QUOTES, 'UTF-8') ?><br />
      <span class="dl-mono"><?= htmlspecialchars($__suf, ENT_QUOTES, 'UTF-8') ?></span></th>
<?php endforeach; ?>
  </tr>
<?php foreach ($__ofGrid as $__sp): $__set = $__dlGrid[$__sp]; ?>
  <tr align="center">
    <td align="left"><b><i><?= $__dlHC($__sp) ?></i></b></td>
<?php   foreach ($__dlMirnaCols as $__k => $__suf):
            $__f = isset($__set[$__k]) ? $__set[$__k] : null;
            if ($__f === null): ?>
    <td class="dl-none">&nbsp;</td>
<?php       else: $__b = cnido_dl_stat_size($__dlDir, $__f, $__dlFileSize); ?>
    <td><a class="dl-btn" target="_blank" rel="noopener" title="<?= $__dlHC($__f) ?><?= $__b !== null ? ' (' . cnido_dl_size($__b) . ')' : '' ?>"
           href="<?= $__dlHC(cnido_dl_url($__f)) ?>">Download</a></td>
<?php       endif; ?>
<?php   endforeach; ?>
  </tr>
<?php endforeach; ?>
</table>
<?php endif; /* $__ofGrid */ ?>

<?php if ($__ofFlat): ?>
<p class="dl-note dl-gap">
  Not tied to a single species, or named in a way the columns above do not cover, so they keep the
  plain file list:
</p>
<table class="gridtable dl-tbl">
  <tr align="center" style="font-weight: bold;">
    <th width="24%">Species</th><th width="26%">File</th><th width="26%">Content</th>
    <th width="10%">Size</th><th width="14%">Download</th>
  </tr>
<?php /* rowspan 按筛选后的连续段现算，与上面两张长表同一个做法。 */ ?>
<?php foreach ($__ofFlat as $__i => $__row):
        $__f = $__row['file'];
        $__b = cnido_dl_stat_size($__dlDir, $__f, $__dlFileSize);
        $__newS = ($__i === 0 || $__ofFlat[$__i - 1]['species'] !== $__row['species']); ?>
  <tr align="center">
<?php   if ($__newS): ?>
    <td rowspan="<?= cnido_dl_run($__ofFlat, $__i, 'species') ?>" align="left"><?php
        if ($__row['species'] === '') { echo '<i>all species</i>'; }
        else { echo '<b><i>' . $__dlHC($__row['species']) . '</i></b>'; } ?></td>
<?php   endif; ?>
    <td align="left" class="dl-mono"><?= $__dlHC($__f) ?></td>
    <td align="left"><?= $__dlHC($__row['kind']) ?></td>
    <td><span class="dl-sz"><?= $__b === null ? '' : cnido_dl_size($__b) ?></span></td>
    <td><a class="dl-btn" target="_blank" rel="noopener" href="<?= $__dlHC(cnido_dl_url($__f)) ?>">Download</a></td>
  </tr>
<?php endforeach; ?>
</table>
<?php endif; /* $__ofFlat */ ?>
<?php endif; /* cnido_dl_show('otherfiles') */ ?>
<?php endif; /* $__dlExtra || $__dlGlobal */ ?>

<?php elseif ($__dlMissing === 0 && $__dlTotal > 0): ?>
<p class="dl-note">Every file in this site's download directory is linked from the table above.</p>
<?php endif; /* $__dlMissing */ ?>

<?php if (!$__dlDbOk): ?>
<div class="dl-empty">
  The download directory could not be listed, or the database is temporarily unavailable, so the
  additional file listings and dataset exports cannot be shown on this page. The links in the
  table above are static and still work.
</div>
<?php elseif (!empty($__dlDs)): ?>
<h2 class="dl-h2" id="datasets">Datasets available only from the database</h2>
<p class="dl-note">
  These <?= count($__dlDs) ?> datasets are shown as tables elsewhere on the site but have no file
  in the download directory — their source files live in a working directory that is not served
  over HTTP. They are exported straight from the database here, so what you get is always the
  current content rather than a copy that may have gone stale. TSV files carry the database column
  names as their header row and open directly in R (<span class="dl-mono">read.delim()</span>) or
  pandas (<span class="dl-mono">read_csv(sep="\t")</span>). Where a spreadsheet is offered, the
  workbook has a second sheet describing the export. The transcriptome-assembly, MAG, multi-omics
  and phenotype tables are in the four sections further down this page.
</p>
<?= cnido_dl_nomatch('dbonly') ?>
<?php if (cnido_dl_show('dbonly')): ?>
<table class="gridtable dl-tbl dl-ds">
  <tr align="center" style="font-weight: bold;">
    <th width="22%">Dataset</th><th width="58%">Content</th>
    <th width="20%">Download</th>
  </tr>
<?php /* 表体由 cnido_dl_ds_table() 印（五张主题数据集表共用一段；行取自 $__dlPg，
         即筛选后的结果）。三列合计 100：Content 吃掉大头（那是整句话），Download
         只放 TSV / XLSX 两枚按钮，够放下就行。 */
      echo cnido_dl_ds_table('dbonly'); ?>
</table>
<?php endif; /* cnido_dl_show('dbonly') */ ?>
<p class="dl-note">
<?php
  /* 数字现算，不写死。原先这里是「The 102 MB … (8,601 files, 13 datasets)」，
     而 2026-09-23 实测是 91.5 MB / 10,292 个 .bin.gz / 14 个数据集 —— 数据集
     增加后这句就悄悄变成假话了。_export_index.json 本来就是这个用途，
     读不动时退回一句不含数字的话，绝不显示旧数字。 */
  $__scIdx = __DIR__ . '/singlecell_data/_export_index.json';
  $__scN = 0; $__scFiles = 0; $__scBytes = 0;
  if (@is_readable($__scIdx)) {
      $__j = json_decode(file_get_contents($__scIdx), true);
      if (is_array($__j)) {
          foreach ($__j as $__s) {
              $__scN++;
              $__scFiles += (int)(isset($__s['n_genes_exported']) ? $__s['n_genes_exported'] : 0);
              $__scBytes += (int)(isset($__s['bytes']) ? $__s['bytes'] : 0);
          }
      }
  }
?>
<?php if ($__scN > 0): ?>
  The <?= number_format($__scBytes / 1048576, 1) ?> MB of per-cell single-cell expression matrices
  (<?= number_format($__scFiles) ?> files, <?= $__scN ?> datasets) are served as
<?php else: ?>
  The per-cell single-cell expression matrices are served as
<?php endif; ?>
  plain files under <a href="singlecell_data/">singlecell_data/</a>; the machine-readable index of
  what is there, with cell and gene counts per dataset, is
  <a href="singlecell_data/_export_index.json" target="_blank" rel="noopener">_export_index.json</a>. See the
  <a href="/sn_data.php">Single-cell Data</a> module for what each dataset is.
</p>
<?php
/* =====================================================================
 * 四组主题数据集 —— 转录组组装 / MAGs 及其注释 / 多组学 / 表型
 *
 * 与上面几节的分工：上面那张大表与「Additional files」列的是 download/ 目录里的
 * 静态文件；这一部分是按主题归拢的结果数据。其中
 *   · 转录组组装的注释表：已经打成 tar.gz 落在 download/transcriptome_assembly/ 下，
 *     这里直接链（子目录不能走 download_fun.php，它只认 basename —— 与
 *     microsynteny.php 链 download/microsynteny/ 的做法一致）；
 *   · MAGs、多组学、表型：本地没有产物文件，只有数据库，走 dataset_export.php
 *     现查现发。分组导出（例如只要某一个 MAG 的 InterPro 注释）用 filter 参数，
 *     描述写在 includes/downloads_topics.php。
 *
 * 分物种的注释包与分 MAG 的按钮都从目录/数据库现读，不写死清单：新增一个物种或
 * 一个 MAG，这里自动多一行。
 *
 * 这些数据（转录组索引、MAG 清单）与 $__dlHC 的定义都在上面第一个 PHP 块里：
 * 搜索框下面那行「哪几节有命中」要在渲染任何一节之前就知道每一节的行数，所以
 * 取数必须全部前置。
 * ===================================================================== */
?>

<h2 class="dl-h2" id="trans-assembly">Transcriptome assembly results</h2>
<p class="dl-note">
  De novo transcriptome assemblies for every species that had a usable RNA-seq run, with the
  functional annotation of the predicted proteins. Each species is one
  <span class="dl-mono">.tar.gz</span> holding eight tables: a master table with all annotation
  types side by side (<span class="dl-mono">&lt;species&gt;_annotation.tsv</span>, one row per
  transcript, columns for UniProt, NR, Pfam, PANTHER, InterPro, GO and KEGG KO) and one
  long-format table per source (one row per transcript and hit, which is what the individual
  annotation pages of the Transcriptome Assembly module are built from). The tables use the
  species' Latin name, so <span class="dl-mono">Acropora_abrotanoides.tar.gz</span> is the bundle
  for <i>Acropora abrotanoides</i>. The assembly and peptide FASTA files are not part of these
  bundles.
</p>
<?= cnido_dl_nomatch('tsx') ?>
<?php if (cnido_dl_show('tsx')): ?>
<table class="gridtable dl-tbl dl-ds">
  <tr align="center" style="font-weight: bold;">
    <th width="22%">Dataset</th><th width="58%">Content</th><th width="20%">Download</th>
  </tr>
<?php foreach ($__dlPg['tsx']['rows'] as $__row):
        if (empty($__row['static'])) { continue; } /* 上面两行写死的：整表汇总、全物种打包 */ ?>
  <tr>
    <td align="center"><b><?= $__row['title'] ?></b><br />
        <span class="dl-sz dl-mono"><?= $__row['slug'] ?></span></td>
    <td align="left"><?= $__row['about'] ?></td>
    <td align="center">
<?php     foreach (array(array('fmt_a', 'file_a'), array('fmt_b', 'file_b')) as $__p):
            if ($__row[$__p[1]] === '') { continue; } ?>
      <a class="dl-btn" target="_blank" rel="noopener" href="<?= $__dlTsBase ?>/<?= rawurlencode($__row[$__p[1]]) ?>"><?= $__row[$__p[0]] ?></a>
<?php     endforeach; unset($__p); ?>
    </td>
  </tr>
<?php endforeach; ?>
<?= cnido_dl_ds_table('tsx') ?>
</table>
<?php endif; /* cnido_dl_show('tsx') */ ?>

<?php if (!empty($__dlTs)):
/* 破折号只能写成字面量：$__dlHC() 是 htmlspecialchars()，传 '&ndash;' 进去会
   把 & 转义，页面上印出七个字符的 "&ndash;"（那 6 行缺 class 的原来就是这个毛病）。 */
$__dlDash = "\xE2\x80\x93";
?>
<h3 class="dl-h2" id="per-species-tables">Per-species annotation tables &mdash; <?= count($__dlTs) ?> species</h3>
<p class="dl-note">
  Each archive holds that species' eight annotation tables. Sizes are the compressed size of the
  archive, not the unpacked size. <b>Proteins</b> is the species' predicted protein count and
  <b>with InterPro</b> is how many of those carry at least one InterPro entry. Both are the figures
  the <a href="#datasets">assembly and annotation summary</a> above publishes, so species that
  summary does not cover show &ndash; here too. The five headings sort the whole table, not just
  the rows on screen.
</p>
<?= cnido_dl_nomatch('ts') ?>
<?php if (cnido_dl_show('ts')): ?>
<table class="gridtable dl-tbl">
  <tr align="center" style="font-weight: bold;">
    <?= cnido_dl_head('ts', 'class', 'Class', 'width="14%"') ?>
    <?= cnido_dl_head('ts', 'species', 'Species', 'width="30%"') ?>
    <?= cnido_dl_head('ts', 'proteins', 'Proteins', 'class="num" width="12%"') ?>
    <?= cnido_dl_head('ts', 'annotated', 'with InterPro', 'class="num" width="12%"') ?>
    <?= cnido_dl_head('ts', 'size', 'Size', 'width="12%"') ?>
    <th width="20%">Download</th>
  </tr>
<?php foreach ($__dlPg['ts']['rows'] as $__row):
        $__bp = $__row['proteins'];
        $__ba = $__row['annotated']; ?>
  <tr>
    <td align="center"><?= $__dlHC($__row['class'] !== '' ? $__row['class'] : $__dlDash) ?></td>
    <td align="left"><i><?= $__dlHC($__row['species']) ?></i></td>
    <td class="<?= $__bp === null ? 'num dl-none' : 'num' ?>"><?= $__bp === null ? $__dlDash : number_format($__bp) ?></td>
    <td class="<?= $__ba === null ? 'num dl-none' : 'num' ?>"><?= $__ba === null ? $__dlDash : number_format($__ba) ?></td>
    <td align="center"><span class="dl-sz"><?= cnido_dl_size((int)$__row['size']) ?></span></td>
    <td align="center">
      <a class="dl-btn" target="_blank" rel="noopener" href="<?= $__dlTsBase ?>/<?= rawurlencode($__row['file']) ?>">TAR.GZ</a>
    </td>
  </tr>
<?php endforeach; ?>
</table>
<?= cnido_dl_pager('ts') ?>
<?php endif; /* cnido_dl_show('ts') */ ?>
<?php else: ?>
<div class="dl-empty">
  The per-species annotation bundles could not be listed. The links above and the tables in the
  rest of this page are unaffected.
</div>
<?php endif; ?>

<h2 class="dl-h2" id="mags-annotation">MAGs and their annotation</h2>
<p class="dl-note">
  Metagenome-assembled genomes recovered from the metagenomic runs in the Metagenomics module, and
  the functional annotation computed for their predicted proteins. The genome sequences themselves
  are not redistributed here &mdash; each MAG is a public NCBI assembly, and the catalogue gives its
  accession and assembly level so you can fetch it from there. What CnidoSite adds is the uniform
  annotation, which is what these tables carry. Every annotation table can be downloaded whole or
  restricted to a single MAG with the buttons in the per-MAG list.
</p>
<?= cnido_dl_nomatch('magx') ?>
<?php if (cnido_dl_show('magx')): ?>
<table class="gridtable dl-tbl dl-ds">
  <tr align="center" style="font-weight: bold;">
    <th width="22%">Dataset</th><th width="58%">Content</th><th width="20%">Download</th>
  </tr>
<?= cnido_dl_ds_table('magx') ?>
</table>
<?php endif; /* cnido_dl_show('magx') */ ?>

<?php if (!empty($__dlMagRows)): ?>
<h3 class="dl-h2" id="mag-annotation-single">Annotation for a single MAG &mdash; <?= count($__dlMagRows) ?> MAGs</h3>
<p class="dl-note">
  One row per MAG that has annotation. The buttons download only that MAG's rows from the table of
  the same name above; a greyed entry means that table holds nothing for that MAG. The four
  headings sort the whole table, not just the rows on screen.
</p>
<?= cnido_dl_nomatch('mag') ?>
<?php if (cnido_dl_show('mag')): ?>
<table class="gridtable dl-tbl">
  <tr align="center" style="font-weight: bold;">
    <?= cnido_dl_head('mag', 'acc', 'Assembly', 'width="13%"') ?>
    <?= cnido_dl_head('mag', 'species', 'MAG', 'width="27%"') ?>
    <?= cnido_dl_head('mag', 'host', 'Host', 'width="20%"') ?>
    <?= cnido_dl_head('mag', 'class', 'Class', 'width="10%"') ?>
    <th width="30%">Download this MAG only</th>
  </tr>
<?php foreach ($__dlPg['mag']['rows'] as $__row): $__m = $__dlMagRows[$__row['n']]; ?>
  <tr>
    <td align="center"><span class="dl-mono"><?= $__dlHC($__row['acc']) ?></span></td>
    <td align="left"><i><?= $__dlHC($__row['species']) ?></i></td>
    <td align="left"><i><?= $__dlHC($__row['host']) ?></i></td>
    <td align="center"><?= $__dlHC($__row['class']) ?></td>
    <td align="center">
<?php   $__any = false;
        foreach ($__dlMagKinds as $__k => $__kd):
            if (empty($__m[$__kd[1]])) { continue; }
            $__any = true; ?>
      <a class="dl-btn" target="_blank" rel="noopener" href="dataset_export.php?dataset=<?= urlencode($__k) ?>&amp;mag=<?= urlencode($__row['acc']) ?>"><?= $__dlHC($__kd[0]) ?></a>
<?php   endforeach;
        if (!$__any) { echo '<span class="dl-none">&ndash;</span>'; } ?>
    </td>
  </tr>
<?php endforeach; ?>
</table>
<?= cnido_dl_pager('mag') ?>
<?php endif; /* cnido_dl_show('mag') */ ?>
<?php endif; /* $__dlMagRows */ ?>

<h2 class="dl-h2" id="multi-omics">Multi-omics results</h2>
<p class="dl-note">
  The tables behind the site's other omics modules: the uniform reanalysis of public proteomics
  datasets, epigenomic and metagenomic run lists, the single-cell atlases with their cell-type
  composition and marker genes, and the miRNA sequences. These have no file in the download
  directory, so they are exported from the database on demand.
</p>
<?= cnido_dl_nomatch('omix') ?>
<?php if (cnido_dl_show('omix')): ?>
<table class="gridtable dl-tbl dl-ds">
  <tr align="center" style="font-weight: bold;">
    <th width="22%">Dataset</th><th width="58%">Content</th><th width="20%">Download</th>
  </tr>
<?= cnido_dl_ds_table('omix') ?>
</table>
<?php endif; /* cnido_dl_show('omix') */ ?>

<h2 class="dl-h2" id="phenotype-data">Phenotype data</h2>
<p class="dl-note">
  Trait records compiled for Cnidaria together with the dictionaries that make them usable on their
  own: the trait dictionary says what each trait means and which values it may take, the species
  map resolves each source's spelling of a name to the accepted name and its AphiaID, and the
  source list gives the full reference behind every record.
</p>
<?= cnido_dl_nomatch('pheno') ?>
<?php if (cnido_dl_show('pheno')): ?>
<table class="gridtable dl-tbl dl-ds">
  <tr align="center" style="font-weight: bold;">
    <th width="22%">Dataset</th><th width="58%">Content</th><th width="20%">Download</th>
  </tr>
<?= cnido_dl_ds_table('pheno') ?>
</table>
<?php endif; /* cnido_dl_show('pheno') */ ?>

<?php endif; /* !$__dlDbOk */ ?>
<div class="clr"></div>
<div class="clr"></div>
<div class="clr"></div>
</div>
</div>
</div>
<?php /* 两处整页 GET 重载都会把窗口甩回页面最顶上，所以页脚前统一摆一次位置：
   ① 搜索（目标由 PHP 算好：命中的是第一节就停在搜索框上，否则停在那一节的标题上）；
   ② 翻页 / 改每页行数 / 点表头排序 —— 这些链接与按钮自己带着「#这一节的锚点」，
      目标就是地址里的片段。两者都没有时脚本什么都不做。 */
$__dlJump = cnido_dl_jump(); ?>
<script type="text/javascript">
<?php /* 为什么不把这件事交给浏览器：地址里带 #片段时的原生滚动实测靠不住（同片段不再滚、
   滚动时机早于解析完成、后面内容落位又把位置顶偏），data_statistics.php 上已经踩过
   一遍，这份是同一套做法 —— 解析到这里先滚一次，DOMContentLoaded / load 再各一次，
   之后按 300/800/1600/3000/5000ms 校正；用户一动（滚轮/触摸/按键/拖滚动条）就不再
   打扰他。后退/前进时完全不动：那时用户要的是「回到我刚才读到的地方」。
   脚本写在页脚 include **之前**，不与页脚的第三方脚本抢解析顺序。 */ ?>
(function () {
    var id = <?= json_encode($__dlJump) ?>;
    var h = (window.location.hash || '').substring(1);
    if (h) { try { h = decodeURIComponent(h); } catch (err) { h = ''; } }
    var el = h ? document.getElementById(h) : null;
    if (!el) { el = document.getElementById(id); }
    if (!el || !el.scrollIntoView) { return; }

    var navType = '';
    try {
        var ent = window.performance && performance.getEntriesByType
                ? performance.getEntriesByType('navigation') : null;
        if (ent && ent.length) { navType = ent[0].type; }
        else if (window.performance && performance.navigation) {
            navType = (performance.navigation.type === 2) ? 'back_forward' : '';
        }
    } catch (err) { navType = ''; }
    if (navType === 'back_forward') { return; }

    var taken = false;
    var mine  = function () { taken = true; };
    if (window.addEventListener) {
        window.addEventListener('wheel', mine, { passive: true });
        window.addEventListener('touchstart', mine, { passive: true });
        window.addEventListener('mousedown', mine);
        window.addEventListener('keydown', mine);
    }
    <?php /* 拖滚动条不发 mousedown，只能拿「滚动位置明显离开了我们放下的地方」当判据
       （自己滚、浏览器片段滚都会发 scroll 事件，按事件本身判会全都误判成用户）。 */ ?>
    var want = null;
    if (window.addEventListener) {
        window.addEventListener('scroll', function () {
            if (want === null || taken) { return; }
            if (Math.abs(window.scrollY - want) > 40) { taken = true; }
            else { want = window.scrollY; }
        }, { passive: true });
    }
    var place = function () {
        if (taken) { return; }
        el.scrollIntoView();
        want = window.scrollY;
    };

    place();
    if (document.readyState === 'loading' && document.addEventListener) {
        document.addEventListener('DOMContentLoaded', place);
    }
    if (window.addEventListener) { window.addEventListener('load', place); }
    var delays = [300, 800, 1600, 3000, 5000], k;
    for (k = 0; k < delays.length; k++) { setTimeout(place, delays[k]); }
})();
</script>
<?php
	include "Webpage_components.php";
	print $footer;
?>
</body>
</html>
