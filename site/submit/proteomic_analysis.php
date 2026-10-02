<?php
/* ---------------------------------------------------------------------------
 * 本文件是 proteomic_analysis.php 的一份陈旧副本，被误放在了 submit/ 这个
 * 「表单提交数据」目录里（同目录下的 *.txt 都是访客提交的内容）。
 *
 * 它没有任何页面链接过去，内容比 ../proteomic_analysis.php 旧，却带着同样的
 * 两个问题（Referee 3 点 10）：
 *   · 物种下拉框提交的是 abbr1 短码 SPIST，而数据表前缀是 Stylophora_pistillata，
 *     于是拼出来的 SPIST_colony_proteomics 在库里不存在，选中该物种只有一张空表；
 *   · 级联下拉框依赖已废弃的 js/DynamicOptionList.js。
 *
 * 与其维护第二份、让它继续和正本各自漂移，不如把访问者送回正本。
 * 旧书签与搜索引擎里已收录的这个地址仍然可用（301 永久跳转）。
 * --------------------------------------------------------------------------- */

/* 只转发本页认识的参数；不从 QUERY_STRING 原样回填 Location，避免把任意
   输入拼进响应头。 */
$cnidoKeep = array();
foreach (array('species', 'hisType', 'hisMark', 'per_page', 'page') as $cnidoK) {
    if (isset($_GET[$cnidoK]) && $_GET[$cnidoK] !== '') { $cnidoKeep[$cnidoK] = $_GET[$cnidoK]; }
}
$cnidoTo = '/proteomic_analysis.php'
         . ($cnidoKeep ? '?' . http_build_query($cnidoKeep) : '');

header('Location: ' . $cnidoTo, true, 301);
header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><title>Proteomic Analysis - CnidoSite</title></head><body>'
   . '<p>This page has moved to <a href="' . htmlspecialchars($cnidoTo, ENT_QUOTES, 'UTF-8') . '">'
   . htmlspecialchars($cnidoTo) . '</a>.</p></body></html>';
