/* ---------------------------------------------------------------------------
 * rwd-tables.js -- 窄屏下让宽数据表可以横向滚动。
 *
 * 为什么要用 JS 加壳，而不是一条 CSS：见 templatemo_style.css 末尾那段注释。
 * 一句话版：`display:block;overflow-x:auto` 能让表格自己滚，但表内那个匿名
 * table 盒只会取 min-content 宽度，每一列都被压到"最长单词"那么窄（Species
 * 列 368 -> 113），文本疯狂换行、行高翻倍，表格看起来像坏了；加上
 * `width:max-content` 列宽是回来了，可表格盒子本身胀到几千像素，
 * overflow 永远不触发，直接把页面撑破。要同时拿到"容器宽度 + 自然列宽"，
 * 只有真正的包裹元素能做到 —— 这就是本脚本存在的唯一理由。
 *
 * 套上之后，样式由 templatemo_style.css 里 @media (max-width:1200px) 的
 * `.gt-scroll` / `.gt-scroll > table.gridtable` 两条规则接管。桌面端那两条
 * 规则不生效，所以本脚本在宽屏上等于没做事（只是多了一层 div）。
 *
 * 载入方式：`<script src="/js/rwd-tables.js" defer></script>`
 *   - 用绝对路径，子目录页面（blast/、cytoscape/、tmp/、GSEA/）才不会 404。
 *     站内 js/func.js 用的是相对路径，那些目录下一直是取不到的。
 *   - defer 让它在解析完成后、DOMContentLoaded 之前执行，是外部脚本能拿到
 *     完整 DOM 的最早时机。
 *
 * 与 js/table-sort.js 的相容性已确认：它靠 `table.parentNode` 一路向上遍历到
 * body（第 129 行）来找分页容器，靠 `document.getElementsByTagName("table")`
 * 列表（第 278 行），多一层包裹都不影响。
 * ------------------------------------------------------------------------- */
(function () {
    "use strict";

    /* 全站三族数据表。加新的一族时，templatemo_style.css 里
       `@media (max-width:1200px)` 那段 `.gt-scroll > table.…` 的选择器要一起加。 */
    var SEL = "table.gridtable, table.cc, table.sc-table";

    /* 祖先里已经有能横滑的容器了（core 的 .cc-scroll-mx、单细胞的
       .sc-table-wrap 都是），再套一层就是两层滚动容器，横滑手感会很怪，
       而且内层永远滚不动。 */
    function alreadyScrollable(el) {
        for (var p = el.parentNode; p && p !== document.body; p = p.parentNode) {
            if (!p.nodeType || p.nodeType !== 1) continue;
            var ox = getComputedStyle(p).overflowX;
            if (ox === "auto" || ox === "scroll") return true;
        }
        return false;
    }

    function wrapTables() {
        var tables = document.querySelectorAll(SEL);
        for (var i = 0; i < tables.length; i++) {
            var table = tables[i];
            var parent = table.parentNode;
            if (!parent) continue;

            /* 幂等。页面里已经有人套过 .gt-scroll、或本脚本被重复执行时，
               不要再套一层 —— 两层滚动容器会让横滑手感变得很怪。 */
            if (parent.classList && parent.classList.contains("gt-scroll")) continue;
            if (alreadyScrollable(table)) continue;

            var box = document.createElement("div");
            box.className = "gt-scroll";
            parent.insertBefore(box, table);
            box.appendChild(table);
        }
    }

    /* defer 的脚本执行时 readyState 已经是 interactive，正常走 else 分支；
       loading 那条是给"有人把 script 挪到 <head> 且没加 defer"留的后路。 */
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", wrapTables);
    } else {
        wrapTables();
    }
})();
