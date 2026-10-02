/* ---------------------------------------------------------------------------
 * macrosynteny.js -- 交互式 Oxford grid
 *
 * 这个渲染器刻意复刻 macrosyntR::plot_oxford_grid() 的版面几何（见包内源码）：
 *     facet_grid(sp2.Chr ~ sp1.Chr, scales="free", space="free") + theme_void()
 * 在 free + space="free" 下，
 *     每一列的宽度 ∝ 该染色体上最大的 Index（= 该染色体上的锚点数），
 *     每一行的高度同理；
 *     一个点画在 (sp1.Index, sp2.Index)，也就是 (ord1, ord2)。
 * 所以这里用 「轴上的位置 = 前面所有染色体的宽度之和 + ord」* 单位长度 来布局，
 * 与 R 出的图几何一致；显著且被聚类的染色体对再叠一层区块底色。
 * 数据全部由 macrosynteny_result.php 内嵌成 MSR_DATA，本文件不发任何请求。
 * ------------------------------------------------------------------------- */

var MSR_VIEWER = (function () {
    var NS = 'http://www.w3.org/2000/svg';

    /* 与 R 包默认 Ref_palette 一致；超过 26 个连锁群时循环取色 */
    var PALETTE = ['#89C5DA', '#DA5724', '#74D944', '#CE50CA', '#3F4921', '#C0717C', '#CBD588',
                   '#5F7FC7', '#673770', '#D3D93E', '#38333E', '#508578', '#D7C1B1', '#689030',
                   '#AD6F3B', '#CD9BCD', '#D14285', '#6DDE88', '#652926', '#7FDCC0', '#C84248',
                   '#8569D5', '#5E738F', '#D1A33D', '#8A7C64', '#599861'];
    var GRAY = '#cbd5e1';
    var SIG = '#dc2626';
    var POS = '#2563eb';   // collinear —— rho 显著为正
    var NEG = '#ea580c';   // inverted  —— rho 显著为负
    var UNORD = '#7c3aed'; // unordered —— 显著连锁，但锚点序相关未达 p<0.001，方向无法判定

    var D = null;                  // MSR_DATA
    var el = {};                   // 各 DOM 节点
    var st = {                     // 视图状态
        topN: 20, colorBy: 'cluster', zoom: 1, showNonSig: true, showBlocks: true,
        fill: true,                 // 两轴各用各的单位长度、把画区铺满容器（见 render() 里的长注释）
        mode: 'cells',              // 'cells'（一对染色体一个格子）/ 'dots'（逐锚点），见 render()
        lastMode: null,             // 上一屏用的是哪种，用来决定要不要重画图例
        cmap: {},                   // 连锁群字母 → PALETTE 下标
        fit: 1,                    // 当前布局下的「适配比例」（整张图刚好放进容器）
        ux: 1,                     // 本次布局的 像素/锚点（缩放上限按它算）
        uxn: 1, uyn: 1,            // 两轴各自的 像素/锚点（铺满模式下两者不同；focusBlock 要用）
        renderedZoom: 0,           // 屏幕上这一屏是用哪个倍数画的（换算视口坐标用）
        manual: false,             // 用户是否自己按过缩放：按过就不再自动改他的视图
        keep: null,                // 重绘前记下的视口中心（自然单位坐标）
        anchor: null,              // 以光标为锚点缩放时，光标下的那一点 {x,y,px,py}
        pin: null,                 // 被「钉住」的点的下标（点一下点，提示框留在图上）
        off: {},                   // 被关掉的连锁群 {clusterIndex: 1}
        sel: null,                 // 表格点选的染色体对 {i1,i2}
        hl: null                   // 当前高亮的染色体对
    };
    var ax = {};                   // 布局结果
    var panel = {};                // "i1,i2" -> 该染色体对的显著性/连锁群/rho
    var dotOf = [];                // 每个点所属的 panel key 与颜色索引

    /* 四边留白（**屏幕像素**，不随缩放变化）。它装的是轴标签，而标签不论放大到
       几倍都该一样大、一样读得清；原来的 M.l/M.t 是自然单位，跟着 zoom 一起缩，
       适配视图（20% 上下）里只剩 25 像素，连轴标题都塞不进去 —— 这是「一打开
       标签全没了」的根因。l 装 Y 轴名（右对齐，最长 12 字 ≈ 67px）+ 左侧竖排的
       轴标题；t 装 X 轴名（竖排，最长 13 字 ≈ 73px）+ 上方横排的轴标题。 */
    var GUT = { l: 112, t: 104, r: 18, b: 30 };
    var RULER = 6;                              // 轴上量尺的厚度（屏幕像素）
    var FS_AXIS = 11;                           // 轴标签字号（屏幕像素）
    var UNIT_MIN = 1.0, UNIT_TARGET = 2600;     // 单位长度（像素/锚点）的目标总宽
    var DOT_MIN = 1.1, DOT_MAX = 8;             // 点的屏幕半径（像素）下限 / 上限
    var ZOOM_STEP = 1.5;                        // + / − 每按一次的倍数
    var PX_PER_ANCHOR_MAX = 12;                 // 1 个锚点占满 12 个屏幕像素就到顶了
    /* 相邻锚点在屏幕上的间距低于这个值（像素），就画「一个染色体对 = 一个格子」的
       格子视图。5px 是跟点半径配套算出来的：点半径上限是间距的 40%（见下面 rNat 那段），
       所以相邻两点的直径一共吃掉 80% 的间距，间距 5px（点径 4px、缝 1px）才刚刚数得清
       个数；更密就糊成一条线，还不如看格子里的数字。 */
    var CELL_PX_PER_ANCHOR = 5;
    var CELL_CLICK_PX = 5.4;                    // 点格子放大时，至少放大到这个间距
    /* 画布任一边的像素上限（缩放上限同时受它约束，见 zoomMax）。
       实测：1200% 时画布 38,074 × 38,270 px、3,139 个圆点，Chromium 画得动。
       定成 60,000 是因为「铺满」模式下它会变成硬约束：两轴尺度差得远时，更密的那一轴
       （比如 A. palmata 的 3,179 个锚点铺在 564 px 上 = 0.18 px/锚点）要放大到 5 px/锚点
       才切进点阵视图，zoom 得 30 倍，画布就是 1456 × 30 = 44,000 px —— 卡在 40,000 上
       永远进不了点阵视图，而提示框里承诺了「点一下就能逐个看锚点」。矢量图按可视窗口
       光栅化，边长翻一倍并不等于开销翻一倍，真正贵的是圆点个数。 */
    var MAX_CANVAS = 60000;
    /* 「铺满」时单个锚点最多占多少屏幕像素。碎片化物种的轴上（20 条 contig、每条
       五六个锚点）按可画区铺满算出来会是几十像素一个锚点，格子宽得荒唐；用跟缩放
       上限同一个数把它按住，不够铺满就留白边。「铺满」的失真因此是有界的。 */
    var FILL_MAX_PX_PER_ANCHOR = 12;
    /* 轴上被折进 "other" 段的那些小序列最多占轴长的比例。碎片化物种的这一段按锚点数
       等比占位能吃掉八九成轴长，而里面每条 contig 都不到一个像素 —— 一大段什么也
       看不见的空白，看着就像图没画完。封顶之后省下来的宽度全给画得出来的序列。 */
    var TAIL_MAX_FRAC = 0.25;

    /* ------------------------------------------------------------ 小工具 */
    function mk(tag, attrs, parent) {
        var n = document.createElementNS(NS, tag), k;
        if (attrs) { for (k in attrs) { if (attrs[k] !== null && attrs[k] !== undefined) { n.setAttribute(k, attrs[k]); } } }
        if (parent) { parent.appendChild(n); }
        return n;
    }
    function fmt(n) { return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ','); }
    function mb(bp) { return (bp / 1e6).toFixed(2) + ' Mb'; }
    function clamp(x, a, b) { return x < a ? a : (x > b ? b : x); }

    /* 可画区：容器里扣掉四边留白与 4px 边框余量之后的像素尺寸。
       宽度直接量容器；高度预算取自容器 CSS 上的 max-height（.msr-grid-wrap 是 78vh），
       由 getComputedStyle 读回来，避免和 CSS 各写一个数、日后改一边就对不上。
       fitScale 与「铺满」两条路都从这里取，保证两边算的是同一个框。 */
    function canvasBox() {
        var wrap = el.wrap, cs, vw = 0, vh = 0, mh;
        if (wrap) {
            cs = window.getComputedStyle ? window.getComputedStyle(wrap) : null;
            vw = wrap.clientWidth || 0;
            if (cs && cs.maxHeight && cs.maxHeight !== 'none') {
                mh = parseFloat(cs.maxHeight);
                if (!isNaN(mh) && mh > 0) {
                    /* 有的浏览器把 vh 解析成 px 回给我们，有的原样返回 "78vh"。 */
                    vh = (cs.maxHeight.indexOf('vh') > -1)
                         ? (window.innerHeight || 800) * mh / 100 : mh;
                }
            }
        }
        if (!vh) { vh = (window.innerHeight || 800) * 0.78; }
        if (!vw) { vw = 1000; }
        /* 各留 4px 余量，免得刚好卡在边界上、反而挤出滚动条 */
        return { w: vw - 4 - GUT.l - GUT.r, h: vh - 4 - GUT.t - GUT.b };
    }

    /* 等比的适配比例：把整张图缩到刚好放进可画区，宽高取小的那个。
       留白是固定像素，所以这一条可以直接解出来：
           GUT.l + 数据宽 × z + GUT.r ≤ 容器宽   →   z ≤ (容器宽 − 两侧留白) / 数据宽
       上限 1：小图本来就放得下，不做放大（要放大是「铺满」那条路的事）。 */
    function fitScale(plotW, plotH) {
        if (!el.wrap || !plotW || !plotH) { return 1; }
        var b = canvasBox();
        return Math.max(Math.min(b.w / plotW, b.h / plotH, 1), 0.02);
    }

    /* ------------------------------------------------------------ 缩放与视口
     *
     * 画布的坐标有两套，混起来就会错位：
     *   自然单位 = 布局算出来的坐标（1 个锚点 1 个单位，除非 ux 被夹到 1 以外）；
     *   像素 = 自然单位 × st.zoom —— svg 的 width/height 就是「自然尺寸 × zoom」，
     *   里面再套一层 transform: scale(zoom)，所以这个乘法是精确的。
     * 视口（.msr-grid-wrap）用 scrollLeft/scrollTop 表示，单位也是像素。
     *
     * 两条老问题都出在这里：
     *   1) 点的半径原来按自然单位定死（0.8px），适配视图（本页大图约 21%）一乘
     *      只剩 0.17 像素 —— 一屏上根本没有能看见的点，用户只看到一片浅色方块。
     *      现在半径按屏幕像素定，再除回自然单位，任何倍数下都是看得见的大小。
     *   2) 缩放只改 st.zoom，scrollLeft 是像素值不跟着换算，图一放大 1.5 倍，
     *      刚才在看的地方就被推出视口 —— 观感就是「按了 + 图自己跑了」。
     *      现在缩放前先记下锚点，重绘后把同一处摆回原处；滚轮缩放以光标为锚点，
     *      光标底下的那个点缩放前后停在原地。
     */

    /* 放大上限：按「1 个锚点占多少屏幕像素」算。12px 已经能一眼数清相邻的点，
       再放大只是把点拉得更开、没有新信息；小图本身 ux 就大，允许的倍数自然更小。
       再叠一条画布上限（MAX_CANVAS），否则「铺满」之后那条宽的轴会把画布拉到几十万像素。
       st.ux 的语义是「适配视图下 1 个锚点占几个屏幕像素」，st.zoom 是相对适配视图的倍数，
       所以 ux×zoom 就是屏幕像素/锚点，两条限制可以直接比。 */
    function zoomMax() {
        var pw = st.plotW || 1, ph = st.plotH || 1;
        /* 画布尺寸 = plot × zoom + 留白，留白是固定屏幕像素，得从预算里先扣掉，
           否则量出来的 svg 宽度会比 MAX_CANVAS 多出 GUT.l + GUT.r 那一截。两轴都要卡。 */
        var byCanvas = Math.min((MAX_CANVAS - GUT.l - GUT.r) / pw,
                                (MAX_CANVAS - GUT.t - GUT.b) / ph);
        return clamp(Math.min(PX_PER_ANCHOR_MAX / (st.ux || 1), byCanvas), 2, 100);
    }

    /* 自然坐标 ↔ 屏幕坐标：画布里的 <g> 是 translate(GUT.l,GUT.t) 之后再 scale(zoom)，
       所以 屏幕 = 留白 + 自然 × zoom。**自然坐标的原点是画区左上角**（不是 svg 左上角），
       留白换成固定像素之后这一步必须减掉，否则每次缩放都会偏掉一个留白。 */
    function toNat(px, py) {
        var w = el.wrap, z = st.renderedZoom || st.zoom;
        return { x: (px - GUT.l) / z, y: (py - GUT.t) / z };
    }
    function natToPx(n) { return GUT.l + n * st.zoom; }
    function natToPy(n) { return GUT.t + n * st.zoom; }

    /* 当前屏幕中心对应的自然单位坐标 */
    function viewCenterNat() {
        var w = el.wrap, z = st.renderedZoom || st.zoom;
        if (!w || !z) { return null; }
        return toNat(w.scrollLeft + w.clientWidth / 2, w.scrollTop + w.clientHeight / 2);
    }

    /* 重绘后把视口摆回去。读一次 scrollHeight 是为了逼浏览器先按新尺寸排版，
       否则下面写 scrollLeft 会被夹在旧的滚动范围里。 */
    function restoreView() {
        var w = el.wrap;
        if (!w) { return; }
        if (w.scrollHeight || w.scrollWidth) { /* 强制布局 */ }
        if (st.anchor) {
            w.scrollLeft = natToPx(st.anchor.x) - st.anchor.px;
            w.scrollTop  = natToPy(st.anchor.y) - st.anchor.py;
        } else if (st.keep) {
            w.scrollLeft = natToPx(st.keep.x) - w.clientWidth / 2;
            w.scrollTop  = natToPy(st.keep.y) - w.clientHeight / 2;
        }
        st.anchor = null; st.keep = null;
        st.renderedZoom = st.zoom;
    }

    /* 缩放到 newZoom，并让窗口内 (clientX,clientY) 处的那个点保持不动。
       clientLeft/clientTop 是容器边框宽度：getBoundingClientRect 给的是边框外沿，
       而 scrollLeft 的原点是内容区（边框内沿），不扣掉就差 1px。 */
    function zoomTo(newZoom, clientX, clientY) {
        var w = el.wrap, z = st.renderedZoom || st.zoom;
        /* 两个偏移量不是一回事，混起来缩放就会跑偏：
             px / py —— 光标在**视口**里的位置（相对容器内沿，不含滚动）。复原时要用它，
                       因为 scrollLeft 的度量基准就是视口左沿；
             ox / oy —— 光标在**画布**上的位置（含滚动，所以取画布自己的外框），
                       减掉留白、除以 zoom 才是光标底下那一点的坐标。
           原来的代码把 (scrollLeft + ox) 先用了一次、又把 ox 当视口偏移存下来，
           在「画布没超出容器、滚动恒为 0」时两者恰好相等，一放大到需要滚动就错开。 */
        var r = w.getBoundingClientRect();
        var px = clientX - r.left - (w.clientLeft || 0);
        var py = clientY - r.top - (w.clientTop || 0);
        var br = el.svg.getBoundingClientRect();
        var n = { x: (clientX - br.left - GUT.l) / z, y: (clientY - br.top - GUT.t) / z };
        st.anchor = { x: n.x, y: n.y, px: px, py: py };
        st.manual = true;
        st.zoom = clamp(newZoom, st.fit, zoomMax());
        render();
    }

    /* 没有光标位置时（按钮、键盘）就以视口中心为锚点 */
    function zoomByCenter(f) {
        var w = el.wrap, r = w.getBoundingClientRect();
        zoomTo(st.zoom * f,
               r.left + (w.clientLeft || 0) + w.clientWidth / 2,
               r.top + (w.clientTop || 0) + w.clientHeight / 2);
    }

    /* 回到「整张图刚好放下」。用 keep=null 让滚动条自然夹回左上角。 */
    function fitView() {
        st.manual = false; st.anchor = null; st.keep = null;
        render();
    }

    function isFull() { return el.wrap.className.indexOf('msr-full') >= 0; }

    function trunc(s, n) { return s.length > n ? s.slice(0, n - 1) + '…' : s; }
    /* 轴上的名字：放不下就**掐头去尾**地截（BLFKO1…00070T），不从前面截。
       这些轴上的名字多是 accession 和 scaffold 号，前十几个字符往往完全一样
       （BLFKO100070T / BLFKO100109T / …），从前截出来是一排一模一样的标签，等于
       没标；留下尾部那几个区分字符才读得出来。完整名字仍在悬停提示里。 */
    function axName(s, n) {
        if (s.length <= n) { return s; }
        var keep = n - 1, h = Math.ceil(keep / 2), t = keep - h;
        return s.slice(0, h) + '…' + s.slice(s.length - t);
    }
    function esc(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    /* ------------------------------------------------------------ 布局 */
    /* 返回 {list:[{name,n,max,off,w,k}], total, bandEnd, ...}；off 以「锚点数」为单位。
       chroms 的顺序由服务器端定好：macrosyntR 推断出来的那些序列按推断顺序排在前面，
       其余（碎片化物种的一千多条小 contig）按锚点数降序跟在后面。bandEnd 之后的那些
       就是折进 "other" 段的。
       squeeze 为真（= 铺满模式）时整段封顶在 TAIL_MAX_FRAC，封顶后段内坐标按同一比例
       压缩（每条序列自己的 k），所以段内各序列的相对位置、先后顺序都不变，只是整段挤窄了。
       等比模式不压：两轴用同一个单位长度，压了反而会把这条轴变短、把图拉成长条（A.
       cervicornis 那条轴从 3,175 压到 940，而 Y 轴还是 3,179），那就不是「等比」而是
       「等比 + 一个被折过的轴」了。用户关掉铺满要的是忠实的那一版，就给他忠实的那一版。 */
    function buildAxis(chroms, bandEnd, squeeze) {
        var list = [], off = 0, i, w, k = 1, spanSeq = 0, spanTail = 0, cap;
        var anchSeq = 0, anchTail = 0, nTail = 0;      // 锚点总数（不是 max）：状态行和标签要报真实数字
        for (i = 0; i < chroms.length; i++) {
            w = Math.max(1, chroms[i].max);
            if (i < bandEnd) { spanSeq += w; anchSeq += chroms[i].n; }
            else { spanTail += w; anchTail += chroms[i].n; nTail++; }
        }
        if (squeeze && spanTail > 0 && spanSeq > 0) {
            cap = TAIL_MAX_FRAC / (1 - TAIL_MAX_FRAC) * spanSeq;
            if (spanTail > cap) { k = cap / spanTail; }
        }
        for (i = 0; i < chroms.length; i++) {
            w = Math.max(1, chroms[i].max);
            var inTail = (i >= bandEnd);
            if (inTail) { w = w * k; }
            list.push({ name: chroms[i].name, n: chroms[i].n, max: chroms[i].max, off: off, w: w,
                        k: (inTail ? k : 1) });
            off += w;
        }
        return { list: list, total: Math.max(off, 1), bandEnd: bandEnd,
                 tailK: k, spanSeq: spanSeq, spanTail: spanTail,
                 anchSeq: anchSeq, anchTail: anchTail, nTail: nTail,
                 index: (function () {
                     var m = {}, j; for (j = 0; j < list.length; j++) { m[list[j].name] = j; } return m;
                 })() };
    }

    function colorOf(di) {
        var d = dotOf[di], p = panel[d.key];
        switch (st.colorBy) {
            case 'sig':  return (p && p.sig) ? SIG : GRAY;
            /* 按服务端判定好的 orientation 上色，不在这里看 rho 的符号 —— 符号本身
               不足以区分共线/倒位（见 03_build_web_db.py 的 rho_critical()）。 */
            case 'ori':  if (!p || !p.sig) { return GRAY; }
                         if (p.ori === 'collinear') { return POS; }
                         if (p.ori === 'inverted') { return NEG; }
                         return UNORD;
            case 'none': return '#475569';
            default:     return d.cl >= 0 ? PALETTE[d.cl % PALETTE.length] : GRAY;
        }
    }

    /* 连锁群字母 → PALETTE 下标。格子视图是按「染色体对」上色的，颜色得从这一对
       自己的 clust 取（点用的是每个点自带的 d[7]，两者的值一致，但对才是那个粒度）。 */
    function pairCluster(p) {
        if (!p || !p.clust) { return -1; }
        var v = st.cmap ? st.cmap[p.clust] : undefined;
        return (v === undefined || v === null) ? -1 : v;
    }
    function pairColor(p) {
        var cix = pairCluster(p);
        switch (st.colorBy) {
            case 'sig':  return (p && p.sig) ? SIG : GRAY;
            /* 按服务端判定好的 orientation 上色，不在这里看 rho 的符号 —— 符号本身
               不足以区分共线/倒位（见 03_build_web_db.py 的 rho_critical()）。 */
            case 'ori':  if (!p || !p.sig) { return GRAY; }
                         if (p.ori === 'collinear') { return POS; }
                         if (p.ori === 'inverted') { return NEG; }
                         return UNORD;
            case 'none': return '#475569';
            default:     return cix >= 0 ? PALETTE[cix % PALETTE.length] : GRAY;
        }
    }

    /* 该点是否可见：属于连锁群的点始终画（被关掉的连锁群除外）；
       不属于任何连锁群的灰点由 "显示未显著的点" 控制。 */
    function visible(di) {
        var d = dotOf[di];
        if (d.cl >= 0) { return !st.off[d.cl]; }
        if (st.colorBy === 'cluster') { return st.showNonSig; }
        return st.showNonSig || (panel[d.key] && panel[d.key].sig);
    }

    /* ------------------------------------------------------------ 渲染 */
    function render() {
        var svg = el.svg, ux, uy, W, H, g, i, j, k;

        // 轴上超过 topN 的序列合并成最后一个 "other" 段（标签只画前 topN 个）
        ax = {
            x: buildAxis(D.chrom1, st.topN > 0 ? Math.min(st.topN, D.chrom1.length) : D.chrom1.length,
                         st.fill),
            y: buildAxis(D.chrom2, st.topN > 0 ? Math.min(st.topN, D.chrom2.length) : D.chrom2.length,
                         st.fill)
        };

        var spanX = ax.x.total, spanY = ax.y.total;

        /* 单位长度（ux/uy = 适配视图下 1 个锚点占几个屏幕像素）——
           注意这里定完之后 st.fit 恒为 1、st.zoom 就是「相对适配视图的倍数」：
               Fit 按钮 → zoom = 1，显示 100%；px/锚点 = ux × zoom。
           两种模式：
             等比（关掉「铺满」）：两轴同一个单位长度、整张图按较小的那一轴缩到放得下，
                 于是方图在长方容器里只占中间一块，两侧留白（旧行为）；
             铺满（默认）：两轴各用各的单位长度，让画区在 zoom = 1 时正好等于可画区。
                 这不是新发明 —— macrosyntR 的 facet_grid 铺满 device 就是这个效果，
                 两轴的单位长度本来就不必相等（面板宽度 ∝ 各染色体锚点数，由设备宽高比定）。
                 代价是网格被横向拉长，收益是 3300×3300 的方图不再缩成 640×640 的小方块，
                 容器横向那 60% 的宽度不再浪费，格子里也放得下更多数字。
                 唯一要按住的是「单个锚点别拉得太宽」：一端锚点少（几条 contig、每条五六个锚点）
                 的轴上，铺满算出来会是几十像素一个锚点，格子宽得荒唐 —— 用 FILL_MAX_PX_PER_ANCHOR
                 截一下，截完不够铺满就留白边（不会溢出，min 保证了 plotW ≤ 可画区宽）。
                 等比模式（关掉「铺满」）下两轴同尺度，方图在长方容器里只占中间一块，两侧留白。 */
        var uNat = clamp(UNIT_TARGET / Math.max(spanX, spanY), UNIT_MIN, 8);
        ux = uy = uNat;
        if (st.fill) {
            var cb = canvasBox();
            ux = Math.min(cb.w / Math.max(spanX, 1), FILL_MAX_PX_PER_ANCHOR);
            uy = Math.min(cb.h / Math.max(spanY, 1), FILL_MAX_PX_PER_ANCHOR);
        } else {
            st.fit = fitScale(spanX * uNat, spanY * uNat);
            ux = uy = uNat * st.fit;        // 把适配比例折进单位长度，下面 zoom=1 就是「刚好放下」
        }
        st.ux = Math.min(ux, uy);

        var plotW = spanX * ux, plotH = spanY * uy;     // 画区尺寸（zoom = 1 时的像素）
        st.plotW = plotW; st.plotH = plotH;
        st.uxn = ux; st.uyn = uy;

        st.fit = 1;      // 适配比例已经折进 ux/uy 了，zoom = 1 就是「刚好放下」
        /* 打开时先缩到适配比例；改「每轴序列数」会让图变大变小，用户没手动缩放过就重新适配，
           手动缩放过就保留他的值，只保证不小于适配比例（比它还小只是图更小、留白更多，没有意义）。 */
        if (!st.manual) { st.zoom = st.fit; }
        st.zoom = clamp(st.zoom, st.fit, Math.max(zoomMax(), st.fit));

        /* 两种视图，按屏幕上的锚点间距自动切换（不是两个模式按钮，用户不用选）：
             cells —— 一个染色体对画一个格子，格子里写锚点数。默认的适配视图就走这条，
                      一眼看得出哪几对染色体之间有共线性、各有多少锚点；
             dots  —— 逐锚点的点阵。放大到相邻锚点能分开了才切过来，
                      想看「这个点到底是哪个基因」时必须的精度。
           判据见 CELL_PX_PER_ANCHOR；两边的配色、图例、显隐开关全部共用同一套规则。 */
        st.mode = (Math.min(ux, uy) * st.zoom < CELL_PX_PER_ANCHOR) ? 'cells' : 'dots';

        W = GUT.l + plotW * st.zoom + GUT.r;            // 画布尺寸（屏幕像素）
        H = GUT.t + plotH * st.zoom + GUT.b;

        /* 重绘会把所有点重建，钉住的提示框指向的节点就没用了，先撤掉，
           否则提示框会浮在一个已经不存在的点旁边。 */
        if (st.pin !== null) {
            st.pin = null; el.pinDot = null;
            el.tip.className = 'msr-tooltip'; hideTip();
        }
        /* 调用方既没指定锚点（滚轮/双击）也没指定要看的中心（点表格行）时，按「屏幕中心
           那个点不动」处理，免得改一下颜色、动一下下拉框视图就跳回左上角。 */
        if (!st.anchor && !st.keep) { st.keep = st.renderedZoom ? viewCenterNat() : null; }

        while (svg.firstChild) { svg.removeChild(svg.firstChild); }
        svg.setAttribute('width', Math.round(W));
        svg.setAttribute('height', Math.round(H));
        svg.setAttribute('viewBox', '0 0 ' + Math.round(W) + ' ' + Math.round(H));

        /* 三层：
             gScrBack —— 屏幕坐标，画框、轴量尺、分隔线（都在留白里或数据之下）
             g         —— translate(留白) + scale(zoom)，数据层
             gScr      —— 屏幕坐标，轴标签与轴名。字号、线宽都不随缩放变化，
                          所以任何倍数下都读得清；这也是原来「标签放大后变糊、
                          缩小时整组消失」的解药。 */
        var gScrBack = mk('g', { id: 'msr-screen-back' }, svg);
        g = mk('g', { id: 'msr-root',
                      transform: 'translate(' + GUT.l + ',' + GUT.t + ') scale(' + st.zoom + ')' }, svg);
        var gBlock = mk('g', { id: 'msr-blocks' }, g);
        var gDot = mk('g', { id: 'msr-dots' }, g);
        var gCell = mk('g', { id: 'msr-cells' }, g);
        var gMark = mk('g', { id: 'msr-mark' }, g);
        var gScr = mk('g', { id: 'msr-screen' }, svg);

        /* 画区坐标（以画区左上角为原点、zoom 之前）。序列内部的位置要乘它自己的 k：
           折进 "other" 段的那些序列整段被压窄了，段内的 ord 也得跟着压，
           否则它们会画到轴外面去。 */
        function X(c, o) { return (ax.x.list[c].off + o * ax.x.list[c].k) * ux; }
        function Y(c, o) { return (ax.y.list[c].off + o * ax.y.list[c].k) * uy; }
        function SX(nat) { return GUT.l + nat * st.zoom; }         // 自然坐标 → 屏幕坐标
        function SY(nat) { return GUT.t + nat * st.zoom; }

        /* --- 画框、轴量尺、分隔线 ---
           原来在整张画布上交替铺浅灰竖带 + 横带：两层叠加的地方是一块深一格的棋盘，
           灰底铺满全图、还跟数据抢眼睛，「花、看不出结构」多半是它造成的。现在画区
           留白，只在两条轴上各放一条 6 像素的量尺（哪一段属于哪条染色体）和够宽处的
           一条浅分隔线。带宽不足 2 个屏幕像素的量尺段照旧不画：碎片化物种的轴上有一
           千多条只带 1 个锚点的 contig（Paramuricea 这类），每条到屏幕上只有 0.26px，
           画出来只是一条灰边。 */
        var wpx, BAND_MIN_PX = 2, GRID_MIN_PX = 26;
        mk('rect', { x: GUT.l, y: GUT.t, width: plotW * st.zoom, height: plotH * st.zoom,
                     fill: '#fff', stroke: '#e2e8f0', 'stroke-width': 1 }, gScrBack);
        /* 轴尾部的「other」段：碎片化物种（Paramuricea 有 1767 条 contig）里一千多条
           小 contig 每条不到 2 个屏幕像素，量尺和格子都画不出来，整条尾巴看上去就是
           一块空白，容易被当成「图没画完」。铺一层极浅底色 + 一条虚线分界，把「这一
           大段是一千多条小 contig」表达出来：段宽已被 buildAxis 压到轴长的 25% 以内，
           段内的内容由 cells 视图里的聚合格子补上，条数/锚点数由轴上的 other 标签给出。 */
        var tailFill = '#f7fafc';
        if (ax.x.bandEnd < ax.x.list.length) {
            var x0 = SX(ax.x.list[ax.x.bandEnd].off * ux);
            mk('rect', { x: x0, y: GUT.t, width: Math.max(0, SX(ax.x.total * ux) - x0),
                         height: plotH * st.zoom, fill: tailFill }, gScrBack);
            mk('line', { x1: x0, y1: GUT.t - RULER - 2, x2: x0, y2: GUT.t + plotH * st.zoom,
                         stroke: '#cbd5e1', 'stroke-width': 1, 'stroke-dasharray': '3 3' }, gScrBack);
        }
        if (ax.y.bandEnd < ax.y.list.length) {
            var y0 = SY(ax.y.list[ax.y.bandEnd].off * uy);
            mk('rect', { x: GUT.l, y: y0, width: plotW * st.zoom,
                         height: Math.max(0, SY(ax.y.total * uy) - y0), fill: tailFill }, gScrBack);
            mk('line', { x1: GUT.l - RULER - 2, y1: y0, x2: GUT.l + plotW * st.zoom, y2: y0,
                         stroke: '#cbd5e1', 'stroke-width': 1, 'stroke-dasharray': '3 3' }, gScrBack);
        }
        for (i = 0; i < ax.x.list.length; i++) {
            wpx = ax.x.list[i].w * ux * st.zoom;
            if (wpx >= BAND_MIN_PX) {
                mk('rect', { x: SX(ax.x.list[i].off * ux), y: GUT.t - RULER - 2, width: wpx,
                             height: RULER, fill: i % 2 ? '#e2e8f0' : '#cbd5e1' }, gScrBack);
            }
            if (i > 0 && wpx >= GRID_MIN_PX) {
                mk('line', { x1: SX(ax.x.list[i].off * ux), y1: GUT.t,
                             x2: SX(ax.x.list[i].off * ux), y2: GUT.t + plotH * st.zoom,
                             stroke: '#eef2f7', 'stroke-width': 1 }, gScrBack);
            }
        }
        for (i = 0; i < ax.y.list.length; i++) {
            wpx = ax.y.list[i].w * uy * st.zoom;
            if (wpx >= BAND_MIN_PX) {
                mk('rect', { x: GUT.l - RULER - 2, y: SY(ax.y.list[i].off * uy),
                             width: RULER, height: wpx,
                             fill: i % 2 ? '#e2e8f0' : '#cbd5e1' }, gScrBack);
            }
            if (i > 0 && wpx >= GRID_MIN_PX) {
                mk('line', { x1: GUT.l, y1: SY(ax.y.list[i].off * uy),
                             x2: GUT.l + plotW * st.zoom, y2: SY(ax.y.list[i].off * uy),
                             stroke: '#eef2f7', 'stroke-width': 1 }, gScrBack);
            }
        }

        /* --- 连锁群区块：显著（或带 clust 字母）的染色体对，整块染色 --- */
        if (st.showBlocks) {
            for (i = 0; i < D.linkage.length; i++) {
                var r = D.linkage[i];
                var i1 = ax.x.index[r.c1], i2 = ax.y.index[r.c2];
                if (i1 === undefined || i2 === undefined) { continue; }
                if (!r.sig && (r.clust === '0' || r.clust === undefined)) { continue; }
                var cix = -1, m;
                for (m = 0; m < D.clusters.length; m++) { if (D.clusters[m].id === r.clust) { cix = m; break; } }
                if (cix >= 0 && st.colorBy === 'cluster' && st.off[cix]) { continue; }
                var fill = GRAY, op = .22;
                if (r.sig) { fill = SIG; op = .07; }
                if (cix >= 0 && st.colorBy === 'cluster') { fill = PALETTE[cix % PALETTE.length]; op = .16; }
                mk('rect', { x: X(i1, 0), y: Y(i2, 0),
                             width: ax.x.list[i1].w * ux, height: ax.y.list[i2].w * uy,
                             fill: fill, 'fill-opacity': op, stroke: fill, 'stroke-opacity': .45,
                             'stroke-width': .6 }, gBlock);
            }
        }

        /* --- 点 ---
           半径按「屏幕像素」定，再除回自然单位（画布整体乘了 st.zoom）。
           下限 1.8px：本页大图的适配比例只有 21% 左右，原来按自然单位定死的 0.8px
           一乘只剩 0.17px，屏幕上根本没有能看见的点 —— 这是「图看不清」的第一位原因。
           上限同时看两件事：绝对半径（8px，别糊成色块）、以及**相邻锚点间距的 40%** ——
           点排成一条线（共线区块就是如此）时，半径只要顶到间距的一半，一排点就并成
           一根线，放大多少倍也数不出个数；压到四成，放到 4.5 倍以上就能一个一个分开。 */
        var rNat = clamp(Math.min(ux, uy) * 0.42, 0.8, 3.6);
        var spacing = Math.min(ux, uy) * st.zoom;      // 屏幕上相邻锚点的间距
        st.r = clamp(Math.min(rNat * st.zoom, 0.40 * spacing), DOT_MIN, DOT_MAX) / st.zoom;
        var r = st.r;
        var shown = 0;

        if (st.mode === 'cells') {
            /* 格子视图：一个染色体对 = 一个格子。先把每个对的可见锚点数数出来，
               再按格子画。格子的颜色跟点一样由「按什么上色」决定，深浅表示锚点数
               （对数刻度，1 个锚点和 400 个锚点差得开、又不至于把大块压成一片黑）；
               格子够大就把数字写在里面 —— 这是这一屏最关键的信息，直接读数字比
               盯着色块猜强得多。数据里的空白格（那对染色体没有锚点）就是白底。 */
            var cnt = {}, order = [], key, cw, ch, op;
            for (i = 0; i < D.dots.length; i++) {
                if (!visible(i)) { continue; }
                shown++;
                key = dotOf[i].key;
                if (cnt[key] === undefined) { cnt[key] = 0; order.push(key); }
                cnt[key]++;
            }
            var maxN = 1, lg, MIN_CELL_PX = 1.5;
            for (k = 0; k < order.length; k++) { if (cnt[order[k]] > maxN) { maxN = cnt[order[k]]; } }
            lg = Math.log(1 + maxN);
            for (k = 0; k < order.length; k++) {
                key = order[k];
                i = key.indexOf(',');
                var cxi = +key.slice(0, i), cyi = +key.slice(i + 1);
                cw = ax.x.list[cxi].w * ux * st.zoom;
                ch = ax.y.list[cyi].w * uy * st.zoom;
                /* 不到 1.5 像素的格子画出来只是一点淡影，不算信息 */
                if (cw < MIN_CELL_PX || ch < MIN_CELL_PX) { continue; }
                var pp = panel[key] || {};
                var fill = pairColor(pp);
                op = 0.20 + 0.72 * (Math.log(1 + cnt[key]) / lg);
                var rx = X(cxi, 0), ry = Y(cyi, 0);
                var rw = ax.x.list[cxi].w * ux, rh = ax.y.list[cyi].w * uy;
                mk('rect', { x: rx, y: ry, width: rw, height: rh,
                             fill: fill, 'fill-opacity': op.toFixed(3),
                             stroke: fill, 'stroke-opacity': .45,
                             'stroke-width': .7 / st.zoom,
                             'data-cell': key, 'data-n': cnt[key],
                             'data-stroke': fill, 'data-sw': .7 / st.zoom }, gCell);
                /* 数字写不写得下，按它自己的宽度算（10px 字大约 5.6px 一个字符，
                   两侧各留 3px），不是拿一个固定列宽去卡：3171 个锚点宽的图缩到
                   23% 时，最小的一条染色体刚好 25.9 像素，卡 26 就会把它整条跳过。 */
                var lab = fmt(cnt[key]);
                if (cw >= Math.max(18, 5.6 * lab.length + 6) && ch >= 12) {
                    /* 数字压在白描边上：底色从浅到深都有，描边保证两种底色下都读得清 */
                    var tn = mk('text', { x: rx + rw / 2, y: ry + rh / 2 + 3.5 / st.zoom,
                                          'font-size': 10 / st.zoom, 'text-anchor': 'middle',
                                          fill: '#1e293b', stroke: '#fff',
                                          'stroke-width': 3 / st.zoom, 'paint-order': 'stroke',
                                          'pointer-events': 'none' }, gCell);
                    tn.textContent = lab;
                }
            }
            /* --- "other" 段里的聚合格子 ---
               折进 other 段的小序列每条都窄到画不出自己的格子（Paramuricea 那一千七百
               多条 contig，每条在屏幕上 0.26 像素），整段就只剩底色，看着像「图没画完」。
               改成一个行序列一格、横跨整段：格子里是「这一整段小序列跟它共有多少锚点」。
               空白变成信息，而且没有给任何一条小序列伪造一个位置 —— 画成普通格子就等于
               声称某条 contig 在那儿。聚合值用 data-pool 标出来，悬停说清它是聚合的。
               两边都碎片化时，段×段的那个角落两个调用都不画：谁也没在那儿量过。 */
            function poolBand(bandAx, rowAx, bandU, rowU, isX) {
                if (bandAx.bandEnd >= bandAx.list.length) { return; }
                var sums = [], j2, q, d2, gi, ri, tot = 0, mx = 1;
                for (j2 = 0; j2 < rowAx.bandEnd; j2++) { sums.push(0); }
                for (q = 0; q < D.dots.length; q++) {
                    if (!visible(q)) { continue; }
                    d2 = D.dots[q];
                    gi = isX ? d2[1] : d2[4];      // 落在被折叠那条轴上的序列
                    ri = isX ? d2[4] : d2[1];      // 行序列
                    if (gi < bandAx.bandEnd || ri >= rowAx.bandEnd) { continue; }
                    sums[ri]++; tot++;
                }
                if (!tot) { return; }
                for (j2 = 0; j2 < sums.length; j2++) { if (sums[j2] > mx) { mx = sums[j2]; } }
                var lg2 = Math.log(1 + mx);
                var b0 = bandAx.list[bandAx.bandEnd].off * bandU, bw = bandAx.total * bandU - b0;
                var rw2, rh2, rx2, ry2, lab2;
                if (bw * st.zoom < 2) { return; }
                for (j2 = 0; j2 < rowAx.bandEnd; j2++) {
                    if (!sums[j2]) { continue; }
                    rh2 = rowAx.list[j2].w * rowU;
                    if (rh2 * st.zoom < MIN_CELL_PX) { continue; }
                    if (isX) { rx2 = b0; ry2 = Y(j2, 0); rw2 = bw; }
                    else { rx2 = X(j2, 0); ry2 = b0; rw2 = rh2; rh2 = bw; }
                    mk('rect', { x: rx2, y: ry2, width: rw2, height: rh2,
                                 fill: GRAY, 'fill-opacity': (0.14 + 0.55 * (Math.log(1 + sums[j2]) / lg2)).toFixed(3),
                                 stroke: GRAY, 'stroke-opacity': .35, 'stroke-width': .7 / st.zoom,
                                 'data-pool': (isX ? 'x' : 'y') + ',' + j2 + ',' + sums[j2],
                                 'data-stroke': GRAY, 'data-sw': .7 / st.zoom }, gCell);
                    lab2 = fmt(sums[j2]);
                    if (rw2 * st.zoom >= Math.max(18, 5.6 * lab2.length + 6) && rh2 * st.zoom >= 12) {
                        mk('text', { x: rx2 + rw2 / 2, y: ry2 + rh2 / 2 + 3.5 / st.zoom,
                                     'font-size': 10 / st.zoom, 'text-anchor': 'middle',
                                     fill: '#1e293b', stroke: '#fff', 'stroke-width': 3 / st.zoom,
                                     'paint-order': 'stroke', 'pointer-events': 'none' },
                           gCell).textContent = lab2;
                    }
                }
            }
            poolBand(ax.x, ax.y, ux, uy, true);
            poolBand(ax.y, ax.x, uy, ux, false);
        } else {
            for (i = 0; i < D.dots.length; i++) {
                if (!visible(i)) { continue; }
                var d = D.dots[i];
                mk('circle', { cx: X(d[1], d[2]), cy: Y(d[4], d[5]), r: r,
                               fill: colorOf(i), 'fill-opacity': .85,
                               'data-i': i, 'stroke-width': 0 }, gDot);
                shown++;
            }
        }

        /* --- 轴标签：屏幕坐标层，固定 11 像素，**永远不整组隐藏** ---
           名字放在收缩层之外，字号就不跟着 zoom 走了：适配视图和放大以后一样大、
           一样清楚。相邻两个名字之间至少要 FS_AXIS+2.5 像素才不会叠字，放不下的
           跳过（跳过的只是名字；量尺和分隔线照旧），放大以后间距够了会自己回来。
           轴名（物种名）任何时候都画 —— 它是这一屏唯一说清「哪一边是谁」的东西。 */
        var roomX = GUT.t - 34, roomY = GUT.l - 36, gapPx = FS_AXIS + 2.5;
        var lastPos = -1e9, nm, tt, cn;
        for (i = 0; i < ax.x.bandEnd; i++) {
            cn = SX((ax.x.list[i].off + ax.x.list[i].w / 2) * ux);
            if (i && cn - lastPos < gapPx) { continue; }
            lastPos = cn;
            nm = axName(ax.x.list[i].name, Math.max(6, Math.floor(roomX / 5.8)));
            tt = mk('text', { x: cn, y: GUT.t - 12, 'font-size': FS_AXIS, fill: '#475569',
                              'text-anchor': 'start',
                              transform: 'rotate(-90 ' + cn + ' ' + (GUT.t - 12) + ')' }, gScr);
            tt.textContent = nm;
            mk('title', null, tt).textContent = ax.x.list[i].name + ' — '
                + fmt(ax.x.list[i].n) + ' anchors';
        }
        lastPos = -1e9;
        for (i = 0; i < ax.y.bandEnd; i++) {
            cn = SY((ax.y.list[i].off + ax.y.list[i].w / 2) * uy);
            if (i && cn - lastPos < gapPx) { continue; }
            lastPos = cn;
            nm = axName(ax.y.list[i].name, Math.max(6, Math.floor(roomY / 5.8)));
            tt = mk('text', { x: GUT.l - 12, y: cn + 3.5, 'font-size': FS_AXIS, fill: '#475569',
                              'text-anchor': 'end' }, gScr);
            tt.textContent = nm;
            mk('title', null, tt).textContent = ax.y.list[i].name + ' — '
                + fmt(ax.y.list[i].n) + ' anchors';
        }
        /* 被折叠进 "other" 段的序列：标一段文字，数字用 buildAxis 数出来的**真实**条数与
           锚点数（不能用 ax.total - off：那已经被 k 压过，报出来会小一大截）。措辞写
           sequences 不写 chromosomes —— Paramuricea 那 1,767 条不是染色体。
           条数、锚点数、"not to scale" 三件事里，轴上只放得下前两件，第三件交给悬停。 */
        var tailNote = function (b, sp) {
            return fmt(b.nTail) + ' smallest sequences of ' + sp + ' (' + fmt(b.anchTail)
                 + ' anchors) are merged into this band' + (b.tailK < 1
                 ? ' — each keeps its order and its size relative to the others, but the whole band is '
                   + 'squeezed to at most ' + Math.round(TAIL_MAX_FRAC * 100) + '% of the axis, so it is '
                   + '<b>not to scale</b>'
                 : ' — drawn to scale')
                 + '. They are all plotted; hover a block in the band to see how many anchors it shares '
                 + 'with that sequence.';
        };
        if (ax.x.bandEnd < ax.x.list.length) {
            var o0x = ax.x.list[ax.x.bandEnd].off;
            var tmx = mk('text', { x: SX((o0x + ax.x.total) / 2 * ux),
                                   y: GUT.t + plotH * st.zoom + 14,
                                   'font-size': 10, fill: '#64748b', 'text-anchor': 'middle' }, gScr);
            tmx.textContent = 'other — ' + fmt(ax.x.nTail) + ' sequences · '
                + fmt(ax.x.anchTail) + ' anchors';
            mk('title', null, tmx).textContent = tailNote(ax.x, D.sp1.name);
        }
        if (ax.y.bandEnd < ax.y.list.length) {
            var o0y = ax.y.list[ax.y.bandEnd].off;
            var tmy = mk('text', { x: GUT.l - 12, y: SY((o0y + ax.y.total) / 2 * uy) + 3,
                                   'font-size': 10, fill: '#64748b', 'text-anchor': 'end' }, gScr);
            tmy.textContent = 'other (' + fmt(ax.y.nTail) + ')';
            mk('title', null, tmy).textContent = tailNote(ax.y, D.sp2.name);
        }

        /* 轴名（物种名）。这两条任何时候都画：一屏图上如果连「哪一边是谁」都要靠
           上下文猜，这张图就没法读了。名称放在留白的最外沿，跟染色体名各占一条。 */
        var ax1 = mk('text', { x: SX(plotW / 2), y: 15, 'font-size': 13, 'font-weight': 600,
                               fill: '#1e293b', 'text-anchor': 'middle' }, gScr);
        ax1.textContent = D.sp1.name + '  (X axis)';
        var ax2 = mk('text', { x: 12, y: SY(plotH / 2), 'font-size': 13, 'font-weight': 600,
                               fill: '#1e293b', 'text-anchor': 'middle',
                               transform: 'rotate(-90 12 ' + SY(plotH / 2) + ')' }, gScr);
        ax2.textContent = D.sp2.name + '  (Y axis)';

        /* --- 选中块的高亮框（线宽/虚线也按屏幕像素，别跟着缩放变粗变细） --- */
        if (st.hl) {
            var h1 = ax.x.index[st.hl.c1], h2 = ax.y.index[st.hl.c2];
            if (h1 !== undefined && h2 !== undefined) {
                mk('rect', { x: X(h1, 0), y: Y(h2, 0),
                             width: ax.x.list[h1].w * ux, height: ax.y.list[h2].w * uy,
                             fill: 'none', stroke: '#10b981', 'stroke-width': 2 / st.zoom,
                             'stroke-dasharray': (5 / st.zoom) + ',' + (3 / st.zoom) }, gMark);
            }
        }

        /* 缩放显示成「相对适配视图的百分比」：st.zoom 现在是倍数而不是绝对比例，
           100% 就是「整张图刚好放下」，跟左上角那个缩放读数、Fit 按钮是同一套。
           工具条与图右上角两个读数在这里一起更新，不会出现一个 100% 一个 250%。 */
        var zTxt = Math.round(st.zoom * 100) + '%';
        el.zoom.textContent = zTxt;
        if (el.zcVal) { el.zcVal.textContent = zTxt; }
        /* 到头了就把对应那一头的按钮灰掉（缩放下限 = 适配比例，上限见 zoomMax()）。
           容差取 1e-9，否则 st.zoom 恰好等于 st.fit 时浮点比较可能判成「还能缩」。 */
        if (el.zcOut) { el.zcOut.disabled = st.zoom <= st.fit + 1e-9; }
        if (el.zcIn) { el.zcIn.disabled = st.zoom >= zoomMax() - 1e-9; }
        /* 状态行是这一屏唯一说清「画了什么、省了什么」的地方，几个数字都要给准：
             · 序列数不写 chromosomes —— Paramuricea 那 1,767 条不是染色体；
             · "other" 段要报真实条数与锚点数（用 buildAxis 数出来的 anchTail，不是被压过的轴向长度）；
             · 铺满模式下两轴尺度不同，得把两个像素/锚点都报出来，否则「scale 0.46」会被当成全图尺度。 */
        var tailTxt = '';
        if (ax.x.nTail) {
            tailTxt += '; the ' + fmt(ax.x.nTail) + ' smallest sequences of ' + esc(D.sp1.name)
                + ' (' + fmt(ax.x.anchTail) + ' anchors) are merged into the "other" band on X';
        }
        if (ax.y.nTail) {
            tailTxt += '; the ' + fmt(ax.y.nTail) + ' smallest sequences of ' + esc(D.sp2.name)
                + ' (' + fmt(ax.y.anchTail) + ' anchors) are merged into the "other" band on Y';
        }
        if (tailTxt && (ax.x.tailK < 1 || ax.y.tailK < 1)) {
            tailTxt += ' — every one of them is still plotted, the band is just squeezed'
                     + ' (not to scale) so that the sequences you can actually read get the room';
        }
        el.status.innerHTML = 'showing ' + fmt(shown) + ' / ' + fmt(D.dots.length) + ' anchors; '
            + fmt(D.chrom1.length) + ' sequences on X, ' + fmt(D.chrom2.length) + ' on Y'
            + (st.fill
               ? '; <b>fill canvas</b> — each axis is scaled to the container on its own, so the two axes'
                 + ' are not at the same scale (' + (ux * st.zoom).toFixed(2) + ' vs '
                 + (uy * st.zoom).toFixed(2) + ' px per anchor at this zoom)'
               : '; <b>equal scale</b> on both axes (' + (ux * st.zoom).toFixed(2) + ' px per anchor)')
            + tailTxt
            /* 两种视图必须在这一行里说清楚，否则用户会以为「图上没有点」是数据没了。
               说清「一个格子 = 一对染色体、格子里的数字是锚点数、放大就变成点阵」。
               px/anchor 一律用两轴里更密的那一轴（st.ux）—— 切到点阵看的是点之间分得开不开，
               而点阵能不能分开由更密的那一轴决定。 */
            + (st.mode === 'cells'
               ? '; <b>cell view</b> — one cell per sequence pair, the number in it is its anchor '
                 + 'count (' + (st.ux * st.zoom).toFixed(2) + ' px per anchor on the denser axis at this '
                 + 'zoom is too dense for single anchors). Hover a cell for its details, click it to zoom '
                 + 'in; zooming past ' + (CELL_PX_PER_ANCHOR / st.ux).toFixed(1)
                 + '× switches to individual anchors.'
               : '; <b>anchor view</b> — every dot is one anchor pair; ' + (st.ux * st.zoom).toFixed(2)
                 + ' px per anchor on the denser axis at this zoom.'
                 + (st.zoom <= st.fit * 1.0001
                    ? ' Whole grid shown — drag to pan, or zoom in further.' : ''));

        /* 视图（格子/点阵）是跟着缩放自动切的，图例得跟着换说明。只在真的换了
           之后再重建图例，别每画一屏就重排一次 DOM。 */
        if (st.lastMode !== st.mode) { st.lastMode = st.mode; legend(); }

        restoreView();
    }

    /* ------------------------------------------------------------ 悬浮提示 */
    function tip(html, ev) {
        var t = el.tip;
        t.innerHTML = html;
        t.style.display = 'block';
        var w = t.offsetWidth, h = t.offsetHeight;
        var x = ev.clientX + 14, y = ev.clientY + 14;
        if (x + w > window.innerWidth - 8) { x = ev.clientX - w - 14; }
        if (y + h > window.innerHeight - 8) { y = Math.max(8, ev.clientY - h - 14); }
        t.style.left = x + 'px';
        t.style.top = y + 'px';
    }
    function hideTip() { el.tip.style.display = 'none'; }

    function dotTip(i) {
        var d = D.dots[i], p = panel[d.key] || {};
        var bid = D.buscos[d[0]], g1 = D.gene1[d[0]], g2 = D.gene2[d[0]], an = D.symbol[d[0]] || '';
        var cix = d[7], chip = '';
        if (cix >= 0 && D.clusters[cix]) {
            chip = '<span class="cl" style="background:' + PALETTE[cix % PALETTE.length] + '"></span>'
                 + 'linkage group <b>' + esc(D.clusters[cix].id) + '</b>';
        } else {
            chip = '<span class="cl" style="background:' + GRAY + '"></span>no linkage group';
        }
        var sig = (p.sig ? '<b style="color:#fca5a5">significant</b>' : 'not significant')
                + (p.clust ? ' · clust ' + esc(p.clust) : '')
                + (p.rho === null || p.rho === undefined ? '' : ' · &rho; ' + (p.rho >= 0 ? '+' : '') + p.rho.toFixed(2))
                + (p.ori ? ' · ' + esc(p.ori) : '');
        function side(spKey, geneKey) {
            var nm = D[spKey].name, chr = d[geneKey], g = (geneKey === 'gene1' ? g1 : g2);
            var h = '<tr><td style="padding-right:8px;color:#94a3b8;">' + esc(nm) + '</td><td>'
                  + '<b>' + esc(chr) + '</b> : ' + mb(d[geneKey === 'gene1' ? 3 : 6])
                  + ' <span style="color:#94a3b8;">(ord ' + d[geneKey === 'gene1' ? 2 : 5] + ')</span></td></tr>';
            h += '<tr><td style="color:#94a3b8;">gene</td><td>' + (g
                  ? '<a href="gene_detail.php?gene=' + encodeURIComponent(g) + '&amp;species='
                    + encodeURIComponent(nm.replace(/ /g, '+')) + '">' + esc(g) + '</a>'
                  : '<span style="color:#94a3b8;">—</span>') + '</td></tr>';
            return h;
        }
        return '<div style="margin-bottom:4px;">' + chip + '</div>'
             + '<div style="margin-bottom:4px;"><b>' + esc(bid) + '</b>'
             + (an ? ' <span style="color:#94a3b8;">' + esc(trunc(an, 60)) + '</span>' : '') + '</div>'
             + '<table style="border-collapse:collapse;">'
             + side('sp1', 'gene1') + side('sp2', 'gene2')
             + '<tr><td style="color:#94a3b8;">chromosome pair</td><td style="font-size:12px;">'
             + esc(p.c1 || '') + ' × ' + esc(p.c2 || '') + ' — ' + sig + '</td></tr></table>';
    }

    /* 格子视图的提示框：说清「哪两条染色体、多少锚点、显著不显著、属哪个连锁群」。
       要精确到某个基因得先放大到点阵（提示框里最后一行就是这么写的）。 */
    function cellTip(key, n) {
        var p = panel[key] || {}, cix = pairCluster(p);
        var chip = (cix >= 0 && D.clusters[cix])
            ? '<span class="cl" style="background:' + PALETTE[cix % PALETTE.length] + '"></span>'
              + 'linkage group <b>' + esc(D.clusters[cix].id) + '</b>'
            : '<span class="cl" style="background:' + GRAY + '"></span>no linkage group';
        var sig = (p.sig ? '<b style="color:#fca5a5">significant</b>' : 'not significant')
                + (p.rho === null || p.rho === undefined ? ''
                   : ' · &rho; ' + (p.rho >= 0 ? '+' : '') + p.rho.toFixed(2))
                + (p.ori ? ' · ' + esc(p.ori) : '');
        return '<div style="margin-bottom:4px;">' + chip + '</div>'
             + '<div style="margin-bottom:4px;"><b>' + esc(p.c1 || '') + '</b> &times; <b>'
             + esc(p.c2 || '') + '</b></div>'
             + '<div style="margin-bottom:6px;">' + fmt(n || p.n || 0) + ' anchors &middot; ' + sig + '</div>'
             + '<div style="color:#94a3b8;">click the cell to zoom in — the zoomed view shows every '
             + 'anchor separately, with its BUSCO and gene.</div>';
    }

    /* "other" 段里的**聚合格子**的提示框。这里的数字跟普通格子不是一个来源：普通格子是
       「这一条序列 × 这一条序列」的锚点数；聚合格子是「折进 other 的那一整段小序列 ×
       这一条序列」的总和。措辞必须说清这一点，否则会被当成某一条 contig 的值读。
       spec = "x|y,行下标,锚点数"。 */
    function poolTip(spec) {
        var p = spec.split(','), onX = p[0] === 'x', ri = +p[1], n = +p[2];
        var band = onX ? ax.x : ax.y, row = (onX ? ax.y : ax.x).list[ri];
        if (!row || band.nTail === undefined) { return ''; }
        return '<div style="margin-bottom:4px;"><b>' + fmt(n) + ' anchors</b> shared with <b>'
             + esc(row.name) + '</b></div>'
             + '<div style="margin-bottom:4px;color:#94a3b8;">aggregated over the '
             + fmt(band.nTail) + ' smallest sequences of <b>' + esc(onX ? D.sp1.name : D.sp2.name)
             + '</b> (' + fmt(band.anchTail) + ' anchors in total), which are too small to be drawn '
             + 'one by one</div>'
             + '<div style="color:#94a3b8;">' + (band.tailK < 1
                ? 'the "other" band is <b>not to scale</b>: each sequence in it keeps its order and its '
                  + 'size relative to the others, but the whole band is squeezed to at most '
                  + Math.round(TAIL_MAX_FRAC * 100) + '% of the axis.'
                : 'the "other" band is drawn to scale, like every other column.')
             + ' Set "Labels per axis" higher to name more sequences and shrink the band.</div>';
    }

    /* ------------------------------------------------------------ 图例 */
    function legend() {
        var box = el.legend;
        box.innerHTML = '';
        function add(color, label, title, on, click) {
            var it = document.createElement('span');
            it.className = 'item' + (on === false ? ' off' : (on === true ? ' on' : ''));
            it.title = title || '';
            it.innerHTML = '<span class="sw" style="background:' + color + '"></span>' + label;
            if (click) { it.onclick = click; it.style.cursor = 'pointer'; }
            box.appendChild(it);
            return it;
        }
        if (st.colorBy === 'cluster') {
            for (var i = 0; i < D.clusters.length; i++) {
                (function (i) {
                    var c = D.clusters[i];
                    add(PALETTE[i % PALETTE.length],
                        'group <b>' + esc(c.id) + '</b> <span style="color:#94a3b8;">'
                        + fmt(c.dots || 0) + ' anchors / ' + c.n
                        + (c.n === 1 ? ' chr pair' : ' chr pairs') + '</span>',
                        'click to hide / show this linkage group', !st.off[i],
                        function () { st.off[i] = st.off[i] ? 0 : 1; render(); legend(); });
                })(i);
            }
            add(GRAY, 'no linkage group', 'anchors not assigned to any linkage group', null, null);
        } else if (st.colorBy === 'sig') {
            add(SIG, 'significant chromosome pair (q &lt; 0.001)', '', null, null);
            add(GRAY, 'not significant', '', null, null);
        } else if (st.colorBy === 'ori') {
            add(POS, 'collinear', 'significantly linked, and rho is positive and reaches the critical value for this pair\'s anchor count (two-sided p < 0.001)', null, null);
            add(NEG, 'inverted', 'significantly linked, and rho is negative and reaches the critical value for this pair\'s anchor count', null, null);
            add(UNORD, 'unordered', 'significantly linked, but |rho| does not reach the critical value for this anchor count — the anchor order is too scrambled, or the block too anchor-poor, to assign a direction', null, null);
            add(GRAY, 'not significant', '', null, null);
        } else {
            add('#475569', 'all anchors', '', null, null);
        }
        if (st.colorBy === 'cluster') {
            var b = document.createElement('span');
            b.className = 'item';
            b.style.borderStyle = 'dashed';
            b.textContent = 'block shading = significant chromosome pair';
            box.appendChild(b);
        }
        /* 格子视图下整个画面就是格子，图例必须说明「格子里的数字是什么」，
           否则那一排数字没有来历。 */
        if (st.mode === 'cells') {
            var cb = document.createElement('span');
            cb.className = 'item';
            cb.textContent = 'cell = one chromosome pair · number = its anchors · '
                           + 'darker = more anchors (zoom in for single anchors)';
            box.appendChild(cb);
        }
    }

    /* ------------------------------------------------------------ 事件 */
    function bind() {
        var drag = null;               // 拖动平移的起始状态（拖动中鼠标可能在 svg 外，所以要挂在 document 上）

        /* 悬停/钉住时把目标标出来。点：画大一圈（按屏幕像素算 +1.5px 半径，
           否则放大到十几倍后高亮那一下会变成一个盖住半条染色体的大色块）；
           格子：把描边换成深色，线宽也按屏幕像素算，任何倍数下都是 1.6px 的框。 */
        function hoverR() { return st.r + 1.5 / st.zoom; }
        function markNode(t) {
            if (t.tagName.toLowerCase() === 'circle') {
                t.setAttribute('r', hoverR());
                t.setAttribute('stroke-width', .8);
            } else {
                t.setAttribute('stroke-width', 1.6 / st.zoom);
            }
            t.setAttribute('stroke', '#0f172a');
            t.setAttribute('stroke-opacity', 1);
        }
        function resetNode(t) {
            if (t.tagName.toLowerCase() === 'circle') {
                t.setAttribute('r', st.r);
                t.removeAttribute('stroke');
                t.removeAttribute('stroke-opacity');
                t.setAttribute('stroke-width', 0);
            } else {
                /* 格子的描边原本是「和填充同色」，存了原值，照原样放回去 */
                t.setAttribute('stroke', t.getAttribute('data-stroke') || 'none');
                t.setAttribute('stroke-width', t.getAttribute('data-sw') || 0);
                t.setAttribute('stroke-opacity', .45);
            }
        }
        /* 光标底下是什么？圆点（data-i）优先，其次是格子（data-cell）。 */
        function hitOf(t) {
            if (!t || !t.tagName || !t.getAttribute) { return null; }
            var tag = t.tagName.toLowerCase(), v;
            if (tag === 'circle') {
                v = t.getAttribute('data-i');
                if (v !== null) { return { kind: 'dot', node: t, html: dotTip(+v) }; }
            } else if (tag === 'rect' || tag === 'text') {
                v = t.getAttribute('data-pool');
                if (v) { return { kind: 'cell', node: t, html: poolTip(v), c1: '', c2: '' }; }
                v = t.getAttribute('data-cell');
                if (v) { return { kind: 'cell', node: t, html: cellTip(v, +t.getAttribute('data-n')),
                                  c1: panel[v] ? panel[v].c1 : '', c2: panel[v] ? panel[v].c2 : '' }; }
            }
            return null;
        }

        el.svg.addEventListener('mousemove', function (ev) {
            if (drag && drag.moved) { return; }    // 正在拖动：别弹提示框
            if (st.pin !== null) { return; }       // 已经钉住某个点：悬停不去顶掉它
            var h = hitOf(ev.target);
            if (h) {
                markNode(h.node);
                tip(h.html, ev);
                if (el.lastDot && el.lastDot !== h.node) { resetNode(el.lastDot); }
                el.lastDot = h.node;
            } else {
                if (el.lastDot) { resetNode(el.lastDot); el.lastDot = null; }
                hideTip();
            }
        });
        el.svg.addEventListener('mouseleave', function () {
            if (st.pin !== null) { return; }
            if (el.lastDot) { resetNode(el.lastDot); el.lastDot = null; }
            hideTip();
        });

        /* 单击 = 把提示框「钉」在图上。悬停的提示框是跟随光标的、而且
           pointer-events:none，里面那两个基因链接根本点不到；钉住之后提示框才接鼠标
           事件，链接才真的能点开基因页 —— 这是「看图上某一点到底是什么」的出口。
           单击格子不做同样的事（格子没有基因链接可点），直接放大到那对染色体 ——
           「这块是什么」的下一步永远是「放大看看」。 */
        el.svg.addEventListener('click', function (ev) {
            if (drag && drag.moved) { return; }    // 刚才是拖动，不是单击
            var h = hitOf(ev.target);
            if (h && h.kind === 'cell') { unpin(); focusBlock(h.c1, h.c2, true); return; }
            if (h) { pinNode(h.node, h.html); } else { unpin(); }
        });

        function pinNode(t, html) {
            if (el.lastDot && el.lastDot !== t) { resetNode(el.lastDot); }
            el.lastDot = null;
            st.pin = 1; el.pinDot = t;
            el.tip.className = 'msr-tooltip pinned';
            el.tip.innerHTML = '<span class="x" title="close (Esc)">✕</span>' + html;
            placeTip(t);
            var x = el.tip.querySelector('.x');
            if (x) { x.onclick = unpin; }
            if (t) { markNode(t); }
        }
        function unpin() {
            if (st.pin === null) { return; }
            st.pin = null;
            if (el.pinDot) { resetNode(el.pinDot); el.pinDot = null; }
            el.tip.className = 'msr-tooltip';
            hideTip();
        }
        /* 钉住的提示框摆在那个点旁边；取不到点就用视口左上角，宁可位置差一点也不要不出。 */
        function placeTip(node) {
            var t = el.tip, b = null, x = 12, y = 12;
            if (node && node.getBoundingClientRect) { b = node.getBoundingClientRect(); }
            if (b && (b.left || b.top || b.width || b.height)) { x = b.right + 10; y = b.top - 6; }
            t.style.display = 'block';
            var w = t.offsetWidth, h = t.offsetHeight;
            if (x + w > window.innerWidth - 8) { x = (b ? b.left - w - 10 : window.innerWidth - w - 8); }
            if (x < 8) { x = 8; }
            if (y + h > window.innerHeight - 8) { y = Math.max(8, window.innerHeight - h - 8); }
            if (y < 8) { y = 8; }
            t.style.left = x + 'px'; t.style.top = y + 'px';
        }

        /* 拖动平移：放大以后靠滚动条找位置太慢，直接按住图拖（与地图一致）。
           走满 3px 才算拖动，免得把「单击锁定某个点」吃掉。 */
        el.svg.addEventListener('mousedown', function (ev) {
            if (ev.button !== undefined && ev.button !== 0) { return; }
            drag = { x: ev.clientX, y: ev.clientY,
                     l: el.wrap.scrollLeft, t: el.wrap.scrollTop, moved: false };
        });
        function onDragMove(ev) {
            if (!drag) { return; }
            var dx = ev.clientX - drag.x, dy = ev.clientY - drag.y;
            if (!drag.moved) {
                if (Math.abs(dx) + Math.abs(dy) < 3) { return; }
                drag.moved = true;
                el.wrap.className += ' dragging';
                if (el.lastDot) { resetNode(el.lastDot); el.lastDot = null; }
                hideTip();
            }
            el.wrap.scrollLeft = drag.l - dx;
            el.wrap.scrollTop  = drag.t - dy;
            if (ev.preventDefault) { ev.preventDefault(); }
        }
        function onDragUp() {
            if (!drag) { return; }
            drag = null;
            el.wrap.className = el.wrap.className.replace(/\s*dragging/, '');
        }
        document.addEventListener('mousemove', onDragMove, false);
        document.addEventListener('mouseup', onDragUp, false);

        /* 滚轮：普通滚轮照旧滚容器 / 滚页面（不抢，否则鼠标停在图上就没法往下翻页），
           Ctrl/⌘ + 滚轮才缩放 —— 与地图、与浏览器自身的缩放键一致。触控板双指捏合
           也会带 ctrlKey 过来，所以捏合同样可用。 */
        el.wrap.addEventListener('wheel', function (ev) {
            if (!(ev.ctrlKey || ev.metaKey)) { return; }
            if (ev.preventDefault) { ev.preventDefault(); }
            zoomTo(st.zoom * (ev.deltaY < 0 ? 1.25 : 1 / 1.25), ev.clientX, ev.clientY);
        }, false);

        /* 双击放大（Shift + 双击缩小），同样以光标处为锚点 */
        el.svg.addEventListener('dblclick', function (ev) {
            if (ev.preventDefault) { ev.preventDefault(); }
            zoomTo(st.zoom * (ev.shiftKey ? 0.5 : 2), ev.clientX, ev.clientY);
        });

        el.colorBy.onchange = function () { st.colorBy = this.value; render(); legend(); };
        el.topN.onchange = function () { st.topN = parseInt(this.value, 10) || 0; render(); };
        /* 手动缩放后 st.manual = true，render() 就不再自动把视图拉回适配比例；
           下限是适配比例（再往外缩只是图更小、留白更多），上限见 zoomMax()。
           缩放锚点交给 zoomByCenter()：按一次 + 后屏幕中心还是原来那一处。 */
        el.zi.onclick = function () { zoomByCenter(ZOOM_STEP); };
        el.zo.onclick = function () { zoomByCenter(1 / ZOOM_STEP); };
        if (el.zf) { el.zf.onclick = function () { fitView(); }; }
        /* 图右上角那一组：跟工具条上的 −/+/Fit 走同一批函数，所以两边永远同步 */
        if (el.zcIn) { el.zcIn.onclick = function () { zoomByCenter(ZOOM_STEP); }; }
        if (el.zcOut) { el.zcOut.onclick = function () { zoomByCenter(1 / ZOOM_STEP); }; }
        if (el.zcReset) { el.zcReset.onclick = function () { fitView(); }; }
        /* 1:1 = 1 个锚点占 1 个屏幕像素，也就是 zoom = 1 / ux。st.zoom 现在是「相对适配视图
           的倍数」而不是绝对比例，所以这里不能直接写 1 / st.zoom —— 那是「回到适配视图」，
           跟旁边的 Fit 按钮重复了。 */
        if (el.z100) { el.z100.onclick = function () { zoomByCenter(1 / (st.ux * st.zoom)); }; }
        if (el.fs) { el.fs.onclick = function () { toggleFull(); }; }
        el.chkNon.onchange = function () { st.showNonSig = this.checked; render(); };
        el.chkBlk.onchange = function () { st.showBlocks = this.checked; render(); };
        /* 铺满画区：改了以后两轴的单位长度会变，zoom 的含义也跟着变，所以重置回适配视图，
           否则用户会停在一个「按新尺度算很奇怪」的倍率上。 */
        if (el.chkFill) {
            el.chkFill.onchange = function () {
                st.fill = this.checked;
                st.manual = false; st.anchor = null; st.keep = null;
                render();
            };
        }

        /* 全屏：这张图本身有 3300 像素见方，而默认容器只有 78vh 高，横向的宽度全浪费了。
           全屏后容器按窗口重新量，非手动缩放状态下会自动适配一次（于是整张图更大）。
           进来以后工具条被盖住了，所以在容器里浮一个小控制条，按钮接的是同一批函数。 */
        function toggleFull() {
            var on = !isFull();
            el.wrap.className = on ? el.wrap.className.replace(/\s*msr-full/, '') + ' msr-full'
                                   : el.wrap.className.replace(/\s*msr-full/, '');
            document.body.style.overflow = on ? 'hidden' : '';
            if (el.fs) { el.fs.innerHTML = on ? '✕ Exit full screen' : '⛶ Full screen'; }
            if (on) {
                if (!el.panel) { el.panel = mkPanel(); }
                /* 挂到 body 上而不是容器里：容器是 overflow:auto 的滚动区，
                   挂进去控制条会跟着画布一起滚出屏幕。 */
                document.body.appendChild(el.panel);
                el.panel.style.display = 'flex';
            } else if (el.panel) {
                el.panel.style.display = 'none';
            }
            render();
        }
        /* 全屏时浮在左上角的小控制条：− / + / Fit / ✕，键盘 Esc 也能退出 */
        function mkPanel() {
            var p = document.createElement('div');
            p.className = 'msr-full-panel';
            function btn(label, title, fn) {
                var b = document.createElement('button');
                b.type = 'button'; b.className = 'btn-reset'; b.innerHTML = label; b.title = title;
                b.style.flex = 'none';
                b.onclick = fn;
                p.appendChild(b);
                return b;
            }
            btn('−', 'zoom out', function () { zoomByCenter(1 / ZOOM_STEP); });
            btn('+', 'zoom in', function () { zoomByCenter(ZOOM_STEP); });
            btn('Fit', 'fit the whole grid in the view', fitView);
            btn('✕', 'exit full screen (Esc)', function () { toggleFull(); });
            return p;
        }
        /* Esc：先退全屏；没全屏就取消钉住的提示框。只认 Esc，不抢 +/- 之类的按键，
           免得用户在页面上按一下就把视图动了。 */
        document.addEventListener('keydown', function (ev) {
            if (ev.keyCode !== 27) { return; }
            if (isFull()) { toggleFull(); } else { unpin(); }
        }, false);

        /* 窗口尺寸变了要重算适配比例，否则「打开就能看全」只在最初那次成立。
           一次渲染要建几千个节点，所以抖动一下再跑；用户手动缩放过就不动他的视图。 */
        var rzT = null;
        function onResize() {
            if (rzT) { clearTimeout(rzT); }
            rzT = setTimeout(function () { rzT = null; if (!st.manual) { render(); } }, 200);
        }
        if (window.addEventListener) { window.addEventListener('resize', onResize, false); }
        else if (window.attachEvent) { window.attachEvent('onresize', onResize); }

        /* 表格行 / 格子视图里的格子 → 把对应的染色体对放大到看得清，并高亮它。
           原来只是按像素滚一下：适配视图（21%）下一个 300 锚点的区块只有 63 像素宽，
           滚过去也还是看不清，等于白点。现在按「让这个区块占满视口的八成就够」求倍数，
           大染色体算出来比适配比例还小，就夹回适配比例 —— 也就是保持原样不动。
           两个入口共用这一个函数（顺带把另一半的选中状态同步上）。 */
        var tb = document.getElementById('msr-linkage-table');
        function focusBlock(c1, c2, detail) {
            if (!c1) { return; }
            st.hl = { c1: c1, c2: c2 };
            if (tb) {
                var rows = tb.querySelectorAll('tr'), z, rc1, rc2;
                for (z = 0; z < rows.length; z++) {
                    rc1 = rows[z].getAttribute('data-c1');
                    rc2 = rows[z].getAttribute('data-c2');
                    rows[z].className = (rc1 === c1 && rc2 === c2) ? 'sel' : '';
                }
            }
            var i1 = ax.x.index[c1], i2 = ax.y.index[c2];
            if (i1 === undefined || i2 === undefined) { render(); return; }
            /* 区块的边长：w1/w2 是它占的锚点数，乘上各自的单位长度（st.uxn/st.uyn）才是画区像素。
               求「让这块占满八成视口」要的倍数，分母就得是画区像素 —— 只除以锚点数等于假定
               ux = uy = 1，那正是「铺满」模式里不再成立的东西。可用的视口要去掉留白。 */
            var a1 = ax.x.list[i1].w, a2 = ax.y.list[i2].w;
            var w1 = a1 * st.uxn, w2 = a2 * st.uyn;
            var wz = clamp(0.8 * Math.min((el.wrap.clientWidth - GUT.l - GUT.r) / Math.max(w1, 1),
                                          (el.wrap.clientHeight - GUT.t - GUT.b) / Math.max(w2, 1)),
                           st.fit, zoomMax());
            /* 从格子点进来的，就一路放大到看得见单个锚点为止：提示框里承诺的是
               「zoom in — the zoomed view shows every anchor separately」，不能点完
               还停在格子视图上（300 个锚点的区块「占满八成视口」也才 3.8px/锚点）。
               从表格行进来的仍旧只把整块框住，那是「看这块在哪」的用法。 */
            if (detail) { wz = clamp(Math.max(wz, CELL_CLICK_PX / st.ux), st.fit, zoomMax()); }
            st.manual = true;
            st.zoom = wz;
            st.anchor = null;
            /* st.keep / st.anchor 用的是**画区**坐标（已经乘过 ux/uy），不是锚点数 ——
               natToPx 是 GUT.l + n × zoom。少了这个 ×ux/×uy，在「铺满」模式下（ux/uy 不再
               恒等于 1）点格子放大就会偏到别处去。这里不能借用 X()/Y()：那两个是 render()
               内部的函数，focusBlock 看不到（定义域不同，调用会 ReferenceError）。 */
            st.keep = { x: (ax.x.list[i1].off + a1 / 2) * st.uxn,
                        y: (ax.y.list[i2].off + a2 / 2) * st.uyn };
            render();
        }
        if (tb) {
            tb.addEventListener('click', function (ev) {
                var tr = ev.target;
                while (tr && tr.tagName && tr.tagName.toLowerCase() !== 'tr') { tr = tr.parentNode; }
                if (!tr || !tr.getAttribute) { return; }
                focusBlock(tr.getAttribute('data-c1'), tr.getAttribute('data-c2'));
            });
        }
    }

    /* ------------------------------------------------------------ 入口 */
    function init(data, svgId, legendId, tipId, wrapId) {
        D = data;
        el.svg = document.getElementById(svgId);
        el.legend = document.getElementById(legendId);
        el.tip = document.getElementById(tipId);
        el.wrap = document.getElementById(wrapId);
        el.status = document.getElementById('msr-status');
        el.colorBy = document.getElementById('msr-colorby');
        el.topN = document.getElementById('msr-topn');
        el.zi = document.getElementById('msr-zoom-in');
        el.zo = document.getElementById('msr-zoom-out');
        el.zf = document.getElementById('msr-zoom-fit');
        el.z100 = document.getElementById('msr-zoom-100');
        el.fs = document.getElementById('msr-full');
        el.panel = null;               // 全屏时才建的小控制条
        el.chkNon = document.getElementById('msr-shownonsig');
        el.chkBlk = document.getElementById('msr-showblocks');
        el.chkFill = document.getElementById('msr-fill');
        el.zoom = document.getElementById('msr-zoom-val');
        /* 图右上角常驻的缩放控制条（结果页有；别的页面没有这几个 id，允许缺） */
        el.zcIn = document.getElementById('msr-zc-in');
        el.zcOut = document.getElementById('msr-zc-out');
        el.zcReset = document.getElementById('msr-zc-reset');
        el.zcVal = document.getElementById('msr-zc-val');
        if (!D || !el.svg || !D.dots || !D.dots.length) {
            if (el.svg) { el.svg.parentNode.innerHTML = '<p style="padding:20px;color:#94a3b8;">No anchors to plot.</p>'; }
            return;
        }

        /* 染色体对 → 显著性 / 连锁群 / rho，供上色与提示使用 */
        var ix1 = {}, ix2 = {}, i;
        /* 连锁群字母 → PALETTE 下标：格子视图按「对」上色时要按字母查颜色 */
        st.cmap = {};
        for (i = 0; i < D.clusters.length; i++) { st.cmap[D.clusters[i].id] = i; }
        for (i = 0; i < D.chrom1.length; i++) { ix1[D.chrom1[i].name] = i; }
        for (i = 0; i < D.chrom2.length; i++) { ix2[D.chrom2[i].name] = i; }
        for (i = 0; i < D.linkage.length; i++) {
            var r = D.linkage[i], a = ix1[r.c1], b = ix2[r.c2];
            if (a === undefined || b === undefined) { continue; }
            panel[a + ',' + b] = { key: a + ',' + b, c1: r.c1, c2: r.c2, sig: !!r.sig,
                                   clust: r.clust, rho: r.rho, ori: r.ori, n: r.n };
        }
        var plain = { sig: false, clust: '0', rho: null, ori: null };
        for (i = 0; i < D.dots.length; i++) {
            var d = D.dots[i], key = d[1] + ',' + d[4];
            if (!panel[key]) { panel[key] = { key: key, c1: D.chrom1[d[1]].name, c2: D.chrom2[d[4]].name,
                                              sig: false, clust: '0', rho: null, ori: null, n: 0 }; }
            dotOf[i] = { key: key, cl: d[7] };
        }

        bind();
        render();
        legend();
    }

    return { init: init, render: render };
})();


/* ---------------------------------------------------------------------------
 * MSR_HEAT -- 全部物种对概览热图（macrosynteny_overview.php）
 *
 * 读 03_build_web_db.py 生成的 web/data/overview_pairs.json：
 *   species: [{id, name, class, anchors, contigs}]
 *   pairs:   [[pair_id, sp1, sp2, frac_in_sig, n_sig_pairs, n_clusters, n_anchors]]
 * 只用 <rect> 画（不依赖 canvas，保持站点 XHTML 1.0 的写法）；
 * 139 个物种 = 约 1.9 万个格子，浏览器没有压力。
 * ------------------------------------------------------------------------- */
var MSR_HEAT = (function () {
    var NS = 'http://www.w3.org/2000/svg';
    /* 色带一律用站内配色（原来是紫罗兰 frac + 绿色 nsig，与站点其它页面不一致）。
       默认指标 frac 改用绿色渐变，所以 css/macrosynteny.css 里 .msr-heat-legend .ramp
       那条 linear-gradient 要与这一行对上。 */
    var RAMPS = {
        frac:  { lo: '#f8fafc', mid: '#a7f3d0', hi: '#047857', pow: 0.7, fmt: function (v) { return (100 * v).toFixed(0) + '%'; } },
        nsig:  { lo: '#f8fafc', mid: '#bfdbfe', hi: '#1d4ed8', pow: 0.55, fmt: function (v) { return v.toFixed(0); } },
        nclu:  { lo: '#f8fafc', mid: '#fde68a', hi: '#b45309', pow: 0.55, fmt: function (v) { return v.toFixed(0); } },
        nanch: { lo: '#f8fafc', mid: '#c7d2fe', hi: '#4338ca', pow: 0.5,  fmt: function (v) { return v.toFixed(0); } }
    };
    var CLASS_COLORS = ['#0ea5e9', '#f97316', '#22c55e', '#a855f7', '#eab308', '#14b8a6',
                        '#ef4444', '#6366f1', '#84cc16', '#ec4899', '#0891b2', '#78350f'];
    var D = null, el = {}, st = { metric: 'frac', cell: 10, cls: '' };
    var idx = {}, order = [], blocks = [], mat = {}, maxv = 1;
    /* 02_run_macrosyntR.R 的 --min-anchors：共同锚点少于此数的物种对不参与分析，
       也就不会有数据行，热图上按“无结果”处理。页面会注入这个常量，取不到就退回 30。
       每次用时现读，免得依赖 <script> 与 init() 的先后顺序。 */
    function minAnchPair() {
        return (typeof MSR_MIN_ANCH_PAIR === 'number') ? MSR_MIN_ANCH_PAIR : 30;
    }

    function mk(tag, attrs, parent) {
        var n = document.createElementNS(NS, tag), k;
        if (attrs) { for (k in attrs) { if (attrs[k] !== null && attrs[k] !== undefined) { n.setAttribute(k, attrs[k]); } } }
        if (parent) { parent.appendChild(n); }
        return n;
    }
    function hex2rgb(h) {
        return [parseInt(h.substr(1, 2), 16), parseInt(h.substr(3, 2), 16), parseInt(h.substr(5, 2), 16)];
    }
    function mix(a, b, t) {
        var A = hex2rgb(a), B = hex2rgb(b), o = '#';
        for (var i = 0; i < 3; i++) {
            o += ('0' + Math.round(A[i] + (B[i] - A[i]) * t).toString(16)).slice(-2);
        }
        return o;
    }
    function color(v) {
        if (v === null || v === undefined || isNaN(v)) { return '#f8fafc'; }
        var R = RAMPS[st.metric];
        var t = Math.pow(Math.max(0, Math.min(1, v / maxv)), R.pow);
        return t < 0.5 ? mix(R.lo, R.mid, t * 2) : mix(R.mid, R.hi, (t - 0.5) * 2);
    }
    function fmt(n) { return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ','); }
    function esc(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    /* 选中的物种子集：按 class 分组、组内按名字排；返回 {list, blocks} */
    function subset() {
        var by = {}, i, s;
        for (i = 0; i < D.species.length; i++) {
            s = D.species[i];
            if (st.cls && s.class !== st.cls) { continue; }
            (by[s.class] = by[s.class] || []).push(s);
        }
        var cls = Object.keys(by).sort(function (a, b) {
            return by[b].length - by[a].length || (a < b ? -1 : 1);
        });
        var list = [], bl = [];
        for (i = 0; i < cls.length; i++) {
            by[cls[i]].sort(function (a, b) { return a.name < b.name ? -1 : (a.name > b.name ? 1 : 0); });
            bl.push({ cls: cls[i], start: list.length, n: by[cls[i]].length,
                      color: CLASS_COLORS[i % CLASS_COLORS.length] });
            for (s = 0; s < by[cls[i]].length; s++) { list.push(by[cls[i]][s]); }
        }
        return { list: list, blocks: bl };
    }

    function tipHtml(a, b) {
        var v = mat[a.id + '\x1f' + b.id];
        if (!v) {
            return '<b>' + esc(a.name) + '</b> × <b>' + esc(b.name) + '</b><br />'
                 + '<span style="color:#94a3b8;">no result (not computed yet, or fewer than '
                 + minAnchPair() + ' shared anchors)</span>';
        }
        return '<b>' + esc(a.name) + '</b> <span style="color:#94a3b8;">(' + esc(a.class) + ', '
             + fmt(a.anchors) + ' anchors)</span><br />'
             + '<b>' + esc(b.name) + '</b> <span style="color:#94a3b8;">(' + esc(b.class) + ', '
             + fmt(b.anchors) + ' anchors)</span><br />'
             + '<span style="color:#94a3b8;">shared anchors</span> <b>' + fmt(v[6]) + '</b><br />'
             + '<span style="color:#94a3b8;">significant chr pairs</span> <b>' + fmt(v[4]) + '</b><br />'
             + '<span style="color:#94a3b8;">anchors in blocks</span> <b>'
             + (v[3] === null || v[3] === undefined ? '—' : (100 * v[3]).toFixed(1) + '%') + '</b><br />'
             + '<span style="color:#94a3b8;">linkage groups</span> <b>' + fmt(v[5]) + '</b><br />'
             + '<span style="color:#a5b4fc;">click to open the Oxford grid →</span>';
    }

    function render() {
        var sub = subset(), list = sub.list, bl = sub.blocks, i, j;
        idx = {};
        for (i = 0; i < list.length; i++) { idx[list[i].id] = i; }

        // 当前指标下的最大值（用于归一化）
        maxv = 1;
        for (i = 0; i < list.length; i++) {
            for (j = 0; j < list.length; j++) {
                var v = mat[list[i].id + '\x1f' + list[j].id];
                if (v && v[st.metric === 'frac' ? 3 : (st.metric === 'nsig' ? 4 : (st.metric === 'nclu' ? 5 : 6))]
                    > maxv) {
                    maxv = v[st.metric === 'frac' ? 3 : (st.metric === 'nsig' ? 4 : (st.metric === 'nclu' ? 5 : 6))];
                }
            }
        }
        // frac 固定按 0-1 归一化，其余按最大值
        if (st.metric === 'frac') { maxv = 1; }

        var c = st.cell, lab = 96;
        var W = lab + list.length * c + 16, H = lab + list.length * c + 16;
        var svg = el.svg;
        while (svg.firstChild) { svg.removeChild(svg.firstChild); }
        svg.setAttribute('width', W);
        svg.setAttribute('height', H);
        svg.setAttribute('viewBox', '0 0 ' + W + ' ' + H);
        var frag = document.createDocumentFragment();

        // 类群边条 + 名称
        for (i = 0; i < bl.length; i++) {
            var b = bl[i];
            var p0 = lab + b.start * c, p1 = p0 + b.n * c;
            mk('rect', { x: p0, y: lab - 12, width: b.n * c, height: 10, fill: b.color }, frag);
            mk('rect', { x: lab - 12, y: p0, width: 10, height: b.n * c, fill: b.color }, frag);
            if (b.n * c > 26) {
                var t1 = mk('text', { x: p0 + b.n * c / 2, y: lab - 16, 'font-size': 11, fill: '#64748b',
                                      'text-anchor': 'start',
                                      transform: 'rotate(-90 ' + (p0 + b.n * c / 2) + ' ' + (lab - 16) + ')' }, frag);
                t1.textContent = b.cls + ' (' + b.n + ')';
                var t2 = mk('text', { x: lab - 16, y: p0 + b.n * c / 2 + 4, 'font-size': 11, fill: '#64748b',
                                      'text-anchor': 'end' }, frag);
                t2.textContent = b.cls;
            }
        }
        // 对角线 + 格子（按行输出，找不到结果的对留浅灰）
        for (i = 0; i < list.length; i++) {
            var y = lab + i * c;
            for (j = 0; j < list.length; j++) {
                if (i === j) { continue; }
                var x = lab + j * c;
                var v = mat[list[i].id + '\x1f' + list[j].id];
                var val = v ? v[st.metric === 'frac' ? 3 : (st.metric === 'nsig' ? 4 : (st.metric === 'nclu' ? 5 : 6))] : null;
                var cell = mk('rect', { x: x, y: y, width: c, height: c,
                                        fill: color(val), 'shape-rendering': 'crispEdges' }, frag);
                cell.setAttribute('data-i', i);
                cell.setAttribute('data-j', j);
                cell.setAttribute('stroke', (c >= 10 ? '#ffffff' : 'none'));
                cell.setAttribute('stroke-width', c >= 10 ? 0.5 : 0);
            }
            if (i % 10 === 0) {
                mk('line', { x1: lab, y1: y, x2: lab + list.length * c, y2: y, stroke: '#e2e8f0', 'stroke-width': .5 }, frag);
                mk('line', { x1: y, y1: lab, x2: y, y2: lab + list.length * c, stroke: '#e2e8f0', 'stroke-width': .5 }, frag);
            }
        }
        svg.appendChild(frag);

        var R = RAMPS[st.metric];
        el.legend_min.textContent = R.fmt(0);
        el.legend_max.textContent = R.fmt(maxv);
        el.status.textContent = '| ' + list.length + ' × ' + list.length + ' species'
            + (st.cls ? ' (' + st.cls + ' only)' : '') + ', max ' + R.fmt(maxv);
        // 图例色带按当前指标实时生成（与格子用的是同一套插值）
        if (el.ramp) {
            var stops = [];
            for (var z = 0; z <= 10; z++) { stops.push(color(maxv * z / 10) + ' ' + (z * 10) + '%'); }
            el.ramp.style.background = 'linear-gradient(90deg,' + stops.join(',') + ')';
        }
        st.list = list; st.blocks = bl;
    }

    function bind() {
        el.svg.addEventListener('mousemove', function (ev) {
            var t = ev.target;
            if (t && t.tagName && t.tagName.toLowerCase() === 'rect' && t.getAttribute('data-i') !== null) {
                var i = +t.getAttribute('data-i'), j = +t.getAttribute('data-j');
                if (!st.list || !st.list[i] || !st.list[j]) { return; }
                el.tip.style.display = 'block';
                el.tip.innerHTML = tipHtml(st.list[i], st.list[j]);
                var w = el.tip.offsetWidth, h = el.tip.offsetHeight;
                var x = ev.clientX + 14, y = ev.clientY + 14;
                if (x + w > window.innerWidth - 8) { x = ev.clientX - w - 14; }
                if (y + h > window.innerHeight - 8) { y = Math.max(8, ev.clientY - h - 14); }
                el.tip.style.left = x + 'px';
                el.tip.style.top = y + 'px';
            } else {
                el.tip.style.display = 'none';
            }
        });
        el.svg.addEventListener('mouseleave', function () { el.tip.style.display = 'none'; });
        el.svg.addEventListener('click', function (ev) {
            var t = ev.target;
            if (!t || !t.tagName || t.tagName.toLowerCase() !== 'rect' || t.getAttribute('data-i') === null) { return; }
            var i = +t.getAttribute('data-i'), j = +t.getAttribute('data-j');
            if (i === j || !st.list || !st.list[i] || !st.list[j]) { return; }
            window.location.href = 'macrosynteny_result.php?species1=' + encodeURIComponent(st.list[i].name)
                                 + '&species2=' + encodeURIComponent(st.list[j].name);
        });
        el.metric.onchange = function () { st.metric = this.value; render(); };
        el.size.onchange = function () { st.cell = parseInt(this.value, 10) || 10; render(); };
        el.cls.onchange = function () { st.cls = this.value; render(); };
    }

    function init(data, svgId, tipId, wrapId) {
        D = data; el.svg = document.getElementById(svgId); el.tip = document.getElementById(tipId);
        el.wrap = document.getElementById(wrapId);
        el.metric = document.getElementById('msr-hm-metric');
        el.size = document.getElementById('msr-hm-size');
        el.cls = document.getElementById('msr-hm-class');
        el.status = document.getElementById('msr-hm-status');
        el.legend_min = document.getElementById('msr-hm-legend-label');
        el.legend_max = document.getElementById('msr-hm-legend-max');
        el.ramp = document.getElementById('msr-hm-ramp');
        if (!D || !el.svg || !D.species || !D.species.length) { return; }

        // 物种对 → 矩阵
        var i, p, nsing = {};
        for (i = 0; i < D.pairs.length; i++) {
            p = D.pairs[i];
            mat[p[1] + '\x1f' + p[2]] = p;
            mat[p[2] + '\x1f' + p[1]] = p;
        }
        // class 过滤下拉
        for (i = 0; i < D.species.length; i++) { nsing[D.species[i].class] = (nsing[D.species[i].class] || 0) + 1; }
        var cs = Object.keys(nsing).sort();
        for (i = 0; i < cs.length; i++) {
            var o = document.createElement('option');
            o.value = cs[i];
            o.textContent = cs[i] + ' (' + nsing[cs[i]] + ')';
            el.cls.appendChild(o);
        }
        bind();
        render();
    }

    return { init: init, render: render };
})();
