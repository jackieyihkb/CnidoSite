<?php
/* ---------------------------------------------------------------------------
 * 旧版根目录副本，已停用。
 *
 * 站点导航（以及所有页面的 Tools 菜单）指向的是 /GSEA/GSEA.php。这份根目录副本
 * 是它的早期版本，且从未接线：
 *   - 表单 action="./compute.php" 指向 /compute.php，该文件不存在，
 *     提交后用户的基因列表会直接丢失；
 *   - <script src="./func.js"> 指向 /func.js，同样不存在；
 *   - 物种下拉框仍使用 js/DynamicOptionList.js 里硬编码的旧物种名单，
 *     导航里的 Hexactiniaria 也已不是本站的分类用词（现为 Hexacorallia）。
 *
 * 为避免同一份工具出现两个会各自漂移的副本，这里统一跳转到正式页面。
 * 原文件已备份到 /home/jackie/CnidoSite-backups/GSEA.php.root-duplicate-20260916
 * --------------------------------------------------------------------------- */
$qs = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== ''
    ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: /GSEA/GSEA.php' . $qs, true, 301);
exit;
