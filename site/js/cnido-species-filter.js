/* ---------------------------------------------------------------------------
 * 类群（Class）→ 物种下拉框的前端过滤。
 *
 * 背景：这些页面的物种下拉框历史上由 js/DynamicOptionList.js 在浏览器里生成，
 * 而该库的 printOptions() 在现代浏览器里是空操作，initDynamicOptionLists() 又会
 * 在 onLoad 时清空下拉框，于是「物种下拉框是空的 / 选了没反应」（Referee 2 major 1）。
 * 现在选项一律由 PHP 渲染（includes/state.php 的 cnido_species_select），
 * 每个 option 带 data-class，本文件只负责按所选类群显示/隐藏。
 *
 * 配对方式：class 下拉框带 data-cnido-class="N"，物种下拉框带 data-cnido-species="N"。
 * 一个表单里可能有多组（search.php 有 4 组），靠 N 区分，互不干扰。
 * 行为与 MAGs.php 的 magsFilterHosts() 保持一致：不属于所选类群的项 disable + hide，
 * 若当前选中项被隐藏则自动切到第一个可见项。
 * --------------------------------------------------------------------------- */

function cnidoFilterPair(classSel) {
    if (!classSel) { return; }
    var pair = classSel.getAttribute('data-cnido-class');
    if (!pair) { return; }
    var want = classSel.value;
    var orgs = document.querySelectorAll('select[data-cnido-species="' + pair + '"]');
    for (var i = 0; i < orgs.length; i++) {
        var org = orgs[i], first = null, j;
        for (j = 0; j < org.options.length; j++) {
            var o = org.options[j];
            var ok = (want === '' || o.getAttribute('data-class') === want);
            o.disabled = !ok;
            o.hidden = !ok;
            if (ok && first === null) { first = o; }
        }
        var cur = org.options[org.selectedIndex];
        if (first !== null && (!cur || cur.disabled)) { first.selected = true; }
    }
}

/* 页面加载时按当前类群对齐一次：服务端可能因为深链给了一个不属于该类群的物种，
   或者类群本身有多个来源（GET / POST / 会话），这里统一到可见的那个。 */
function cnidoFilterAll() {
    var cs = document.querySelectorAll('select[data-cnido-class]');
    for (var i = 0; i < cs.length; i++) { cnidoFilterPair(cs[i]); }
}
