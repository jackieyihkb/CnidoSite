/* ---------------------------------------------------------------------------
 * 表头排序：给数据表的每一列加一个小的上下箭头，点一下升序、再点一下降序、第三
 * 下还原成服务器给的原始顺序。字形沿用站内已有的约定（microsynteny.php、
 * trans_assembly.php 两处都是 \2195 / \2191 / \2193，选中时 #1d4ed8）。
 *
 * 为什么是一份自跑的文件，而不是逐页改 PHP：
 *   站内绝大多数表格都是服务端一次渲染好的（几百到几千行），排序在浏览器里重排
 *   DOM 就能做到；而站点没有统一的 <head> 生产点（页头是各页自己写的、样式表路径
 *   也不统一），所以把它挂在页脚，一次覆盖 Webpage_components.php 的 77 个页面。
 *   挂页脚还有一个原因：页脚里 mapmyvisitors 那行同步外链会挡住 DOMContentLoaded，
 *   脚本放在它前面就能立刻跑（见 phylotree/index.php 里那两段实测注释）。
 *
 * 跳过哪些表（宁可少给，不给错）：
 *   · 键值表：第一列是 <th> 行标签的（data_statistics 的统计块、mitdata 的
 *     mito-summary、core 的 table.cc 等），它们不是「一列一个字段」的表；
 *   · 表头带 rowspan/colspan 的（download.php 的两行表头、trans_assembly 的
 *     分组表头）——表头与列不是一一对应，点了不知道该排哪一列；
 *   · 已经自带排序的（th.sortable / th.ts-sort / th[data-k]），别叠加第二套；
 *   · 表头里有 <input>/<select> 的（tpm_ratio 的样品勾选表）；
 *   · 分页表：服务端一页只给 25 行，在浏览器里排只是把这 25 行换个次序，会让人
 *     以为整表排好了。这类表由服务端出表头链接（includes/sort_head.php，<a> 上带
 *     data-sort-link，URL 带 sort/dir），本文件见到就整表跳过，交给服务器；服务端
 *     还没接管的先用 isPaged() 那条启发式兜底。单张表想强制打开，写 data-sort="on"。
 *   · 少于 3 个数据行的表（排了也没有意义）。
 *   单张表想手动排除，写 data-no-sort 即可。
 *
 * 跟着数据行走的「尾巴行」：
 *   · 数据行**后面紧跟**的非数据行（trans_assembly_species.php 的展开行 tr.detail、
 *     各页「暂无数据」的整行 colspan 说明行、表尾的合计行等）排序时跟它上面那一行
 *     一起走。它们和上面那行是一对：不跟着走的话，点第 4 行展开、翻出来的内容会长在
 *     表格别的地方（2026-09-30 修的就是这个）。
 *   · 但**分组标签**跟不了：那种「只有每组第一行印字、其余行留空」的表（如
 *     gene_epigenome_panel 的峰表），标签本身没有「属于哪一行」的语义，跟谁走都错。
 *     这类表请自己写 data-no-sort —— 挂标签的那一行并不是「上一行的展开区」，
 *     本文件分辨不出来。
 *
 * 排序键怎么取：
 *   · 单元格有 data-v（core/index.php 已有的约定）就用它，否则用可见文字；
 *   · 取文字时把 <br> 和块级元素当空格 —— 否则 "38920<br>20797 sequences" 会拼成
 *     "3892020797"，整数列就排错了；
 *   · 数值列认 1,234 / 45.2% / 1.20e-30 / "12 / 60" / "1234 (88.5%)" 这类写法
 *     （整串是数就用它，否则取开头那个数）；
 *   · 空值（-、—、n/a、not recorded…）不论升降一律排在最后；
 *   · 其余按自然序比较，"OG9" 排在 "OG10" 前面。
 * ------------------------------------------------------------------------- */
(function () {
  "use strict";

  var MIN_ROWS = 3;
  var MISSING = {
    "": 1, "-": 1, "--": 1, "---": 1, "\u2013": 1, "\u2014": 1, "n/a": 1, "na": 1,
    "none": 1, "null": 1, "not recorded": 1, "not available": 1, "no data": 1,
    "no hit": 1, "no hits": 1, "?": 1
  };
  var NUM = /^[-+]?(?:\d+\.?\d*|\.\d+)(?:[eE][-+]?\d+)?$/;
  var LEAD = /^[<>~\u2248\u2264\u2265]*\s*([-+]?(?:\d+\.?\d*|\.\d+)(?:[eE][-+]?\d+)?)/;

  /* 单元格的可见文字。<br> 与块级元素补一个空格，别让两段文字粘成一个数。 */
  function textOf(el) {
    var out = "";
    (function walk(node) {
      for (var i = 0; i < node.childNodes.length; i++) {
        var c = node.childNodes[i];
        if (c.nodeType === 3) {
          out += c.nodeValue;
        } else if (c.nodeType === 1) {
          var tag = c.tagName;
          if (tag === "BR") {
            out += " ";
          } else {
            walk(c);
            if (tag === "DIV" || tag === "P" || tag === "LI") out += " ";
          }
        }
      }
    })(el);
    return out.replace(/\s+/g, " ").trim();
  }

  /* 一个单元格的排序键：n = 数值（认不出为 null），t = 文字，miss = 是否缺值 */
  function keyOf(td) {
    var raw = td.getAttribute("data-v");
    if (raw === null || raw === "") raw = td.getAttribute("data-sort");
    var probe = (raw !== null ? raw : textOf(td)).replace(/\s+/g, " ").trim();
    if (MISSING[probe.toLowerCase()] === 1) return { n: null, t: probe, miss: true };
    var flat = probe.replace(/[\s,]/g, "");
    if (NUM.test(flat)) return { n: parseFloat(flat), t: probe, miss: false };
    var m = probe.replace(/,/g, "").match(LEAD);
    if (m) return { n: parseFloat(m[1]), t: probe, miss: false };
    return { n: null, t: probe, miss: false };
  }

  /* 整列八成以上是数字就按数字排（"1,234" 与 "45.2%" 都算），否则按文字 */
  function isNumericColumn(rows, col) {
    var num = 0, seen = 0;
    for (var i = 0; i < rows.length; i++) {
      var k = keyOf(rows[i].cells[col]);
      if (k.miss) continue;
      seen++;
      if (k.n !== null) num++;
    }
    return seen > 0 && num >= seen * 0.8;
  }

  /* 表头行：优先 thead 的最后一行，其次头两行里整行都是 <th> 的那一行 */
  function headerRow(table) {
    if (table.tHead && table.tHead.rows.length) {
      return table.tHead.rows[table.tHead.rows.length - 1];
    }
    for (var i = 0; i < Math.min(table.rows.length, 2); i++) {
      var r = table.rows[i], ths = 0;
      for (var j = 0; j < r.cells.length; j++) {
        if (r.cells[j].tagName === "TH") ths++;
      }
      if (ths && ths === r.cells.length) return r;
    }
    return null;
  }

  /* 参与排序的行：列数与表头一致、不含整行 colspan 的说明行、首格不是 <th> */
  function dataRows(table, head) {
    var out = [];
    for (var i = 0; i < table.rows.length; i++) {
      var r = table.rows[i];
      if (r === head) continue;
      if (r.parentNode && r.parentNode.tagName === "TFOOT") continue;
      if (r.cells.length !== head.cells.length) continue;
      if (r.cells.length && r.cells[0].tagName === "TH") continue;
      out.push(r);
    }
    return out;
  }

  /* 分页表：往上看，最近的、装着分页条的那个容器里如果只有这一张表，那它多半就是
     被分页的那张（browse.php、go_result.php 这些页面一张表配一个分页条）。
     容器里有好几张表时判不出来，宁可当成不分页 —— 这类页面由服务端出
     th[data-sort-link] 明确接管，本文件的这条启发式只在服务端还没接管时兜底。 */
  function isPaged(table) {
    for (var p = table.parentNode; p && p !== document.body; p = p.parentNode) {
      if (!p.querySelector) continue;
      if (p.querySelector(".pagination-container, .cm-page")) {
        return p.getElementsByTagName("table").length === 1;
      }
    }
    return false;
  }

  /* 值不值得给箭头：null = 可以，否则是跳过的原因（供调试与巡检脚本读） */
  function whyNot(table) {
    if (table.getAttribute("data-no-sort") !== null) return "opt-out";
    /* 服务端已经接管了排序（includes/sort_head.php 的 cnido_sort_link()）：
       这类表必须由服务器重发整段结果，客户端重排一页的 25 行是错的。 */
    if (table.querySelector("a[data-sort-link]")) return "server-sorted";
    if (table.querySelector("th.sortable, th.ts-sort, th[data-k]")) {
      return "already-sortable";
    }
    if (table.getAttribute("data-sort") !== "on" && isPaged(table)) return "paged";
    /* 表头里已经有链接的表不碰：要么是服务端排序（本文件按约定让位），要么是覆盖
       矩阵那种「点表头切换列显示」的控件，两种都不该再叠一套客户端排序。 */
    if (table.querySelector("thead th a, tr th a")) return "linked-header";
    var head = headerRow(table);
    if (!head) return "no-header";
    if (head.cells.length < 2) return "one-column";
    for (var j = 0; j < head.cells.length; j++) {
      var th = head.cells[j];
      if (th.rowSpan > 1 || th.colSpan > 1) return "spanned-header";
      if (th.querySelector("input, select, textarea")) return "form-in-header";
    }
    var rows = dataRows(table, head);
    if (rows.length < MIN_ROWS) return "too-few-rows";
    var labelled = 0;
    for (var i = 0; i < rows.length; i++) {
      if (rows[i].cells.length && rows[i].cells[0].tagName === "TH") labelled++;
    }
    if (labelled >= rows.length * 0.6) return "key-value";
    var parent = rows[0].parentNode;
    for (var k = 1; k < rows.length; k++) {
      if (rows[k].parentNode !== parent) return "split-body";
    }
    return null;
  }

  function compare(a, b, numeric, dir) {
    if (a.miss || b.miss) {
      if (a.miss && b.miss) return a.i - b.i;
      return a.miss ? 1 : -1;                 // 缺值不分升降都排最后
    }
    var r;
    if (a.n !== null && b.n !== null) {
      r = a.n - b.n;                          // 都是数就按数排，哪怕这列判定成文字列
    } else if (numeric) {
      r = a.t.toLowerCase().localeCompare(b.t.toLowerCase(), "en");
    } else {
      r = a.t.toLowerCase().localeCompare(b.t.toLowerCase(), "en",
                                          { numeric: true, sensitivity: "base" });
    }
    if (r !== 0) return r * dir;
    return a.i - b.i;
  }

  /* 数据行后面紧跟的、不属于数据集的行：trans_assembly_species.php 的展开行 tr.detail、
     各页「暂无数据」的整行 colspan 说明行等。它们和上面那一行是一对（展开区里装的就是
     上面那行的内容），排序时必须跟着一起搬 —— 否则点第 4 行展开，翻出来的东西长在
     表格别的地方。_tsData 由 apply() 在排之前打在数据行上。 */
  function tailRows(row) {
    var out = [], n = row.nextSibling;
    while (n) {
      if (n.nodeType === 1 && n.tagName === "TR") {
        if (n._tsData) break;            // 下一个数据行 —— 尾巴到此为止
        out.push(n);                     // 展开行 / 说明行 —— 跟着上面那行走
        n = n.nextSibling;
      } else if (n.nodeType === 1) {
        break;                           // 不是 <tr> 的元素，不越过它去接后面的行
      } else {
        n = n.nextSibling;               // 标签之间的空白与注释节点，跳过
      }
    }
    return out;
  }

  function apply(table, st) {
    var head = headerRow(table);
    var rows = dataRows(table, head);
    /* 挂载点必须在往 fragment 里搬行**之前**取出：搬完 rows[0] 已经属于 fragment 了，
       那时再读 rows[0].parentNode 拿到的是 fragment 自己，appendChild 会抛
       HierarchyRequestError（"The new child element contains the parent"）。 */
    var parent = rows.length ? rows[0].parentNode : null;
    if (!parent) return;
    var decorated = [], i;
    for (i = 0; i < rows.length; i++) {
      rows[i]._tsData = 1;
      decorated.push({
        row: rows[i],
        i: i,
        k: keyOf(rows[i].cells[st.k])
      });
    }
    if (st.dir === 0) {
      decorated.sort(function (a, b) {
        return (+a.row.getAttribute("data-ts-i")) - (+b.row.getAttribute("data-ts-i"));
      });
    } else {
      decorated.sort(function (a, b) { return compare(a.k, b.k, st.numeric, st.dir); });
    }
    /* 搬行：每个数据行把它**后面紧跟的那些非数据行**一起带走。
       必须在动 row 之前取尾巴 —— row 一旦离开父节点，它的 nextSibling 就没了。 */
    var frag = document.createDocumentFragment();
    for (i = 0; i < decorated.length; i++) {
      var row = decorated[i].row;
      var tail = tailRows(row);
      frag.appendChild(row);
      for (var t = 0; t < tail.length; t++) frag.appendChild(tail[t]);
    }
    parent.appendChild(frag);
    for (i = 0; i < rows.length; i++) delete rows[i]._tsData;

    for (i = 0; i < st.ths.length; i++) {
      var th = st.ths[i];
      th.className = th.className.replace(/\s*\b(asc|desc)\b/g, "");
      th.removeAttribute("aria-sort");
      if (i === st.k && st.dir !== 0) {
        th.className += st.dir > 0 ? " asc" : " desc";
        th.setAttribute("aria-sort", st.dir > 0 ? "ascending" : "descending");
      }
    }
  }

  function enhance(table) {
    if (whyNot(table) !== null) return false;
    var head = headerRow(table);
    var rows = dataRows(table, head);
    var st = { k: -1, dir: 0, numeric: false, ths: [] };
    for (var i = 0; i < rows.length; i++) rows[i].setAttribute("data-ts-i", i);

    for (var j = 0; j < head.cells.length; j++) {
      (function (col) {
        var th = head.cells[col];
        th.className += " sortable tsx-th";
        th.setAttribute("tabindex", "0");
        th.setAttribute("role", "button");
        th.setAttribute("title", "Click to sort by this column");
        function hit() {
          if (st.k === col) {
            st.dir = st.dir === 1 ? -1 : (st.dir === -1 ? 0 : 1);
          } else {
            st.k = col;
            st.dir = 1;
          }
          st.numeric = isNumericColumn(dataRows(table, head), col);
          apply(table, st);
        }
        th.addEventListener("click", hit);
        th.addEventListener("keydown", function (e) {
          if (e.key === "Enter" || e.key === " " || e.keyCode === 13 || e.keyCode === 32) {
            e.preventDefault();
            hit();
          }
        });
      })(j);
      st.ths.push(head.cells[j]);
    }
    table.className += " tsx-table";
    return true;
  }

  var CSS = [
    "table.tsx-table th.sortable{cursor:pointer;-webkit-user-select:none;user-select:none;}",
    "table.tsx-table th.sortable:focus{outline:2px solid #1d4ed8;outline-offset:-2px;}",
    "table.tsx-table th.sortable::after{content:\"\\2195\";color:#9aa7b4;margin-left:4px;",
    "font-size:11px;line-height:1;}",
    "table.tsx-table th.sortable.asc::after{content:\"\\2191\";color:#1d4ed8;}",
    "table.tsx-table th.sortable.desc::after{content:\"\\2193\";color:#1d4ed8;}",
    "table.tsx-table th.sortable:hover::after{color:#1d4ed8;}"
  ].join("");

  function run() {
    var tables = document.getElementsByTagName("table");
    for (var i = 0; i < tables.length; i++) enhance(tables[i]);
  }

  function boot() {
    var style = document.createElement("style");
    style.type = "text/css";
    style.appendChild(document.createTextNode(CSS));
    (document.head || document.getElementsByTagName("head")[0]).appendChild(style);
    run();
  }

  /* 动态插进来的表可以自己再叫一次；巡检脚本用 whyNot 读每张表被跳过的原因 */
  window.cnidoSortTables = run;
  window.cnidoSortWhyNot = whyNot;
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
  window.addEventListener("load", run);   // 页脚外链之后才建出来的表，再扫一遍
})();
