/*!
 * CnidoSite interactive cell atlas viewer
 * ---------------------------------------------------------------------------
 * Replaces the two static UMAP PNGs previously shipped by cell_atlas.php, which
 * Referee 1 ("scRNA-seq data are presented only as static, non-interactive
 * images") and Referee 3 ("two identical UMAP visualisations with no
 * information about the difference; the clusters are not identified by cell
 * type") both objected to.
 *
 * Design notes
 * ------------
 * Rendering. Points are rasterised into an ImageData buffer and blitted with
 * putImageData, rather than issuing one canvas path per cell. A 200k-cell
 * dataset (e.g. Hydractinia PRJNA1124116 has 199,113 cells) then redraws in a
 * few milliseconds per frame, so pan/zoom stays interactive.
 *
 * Colour. A UMAP is an "all-pairs" form: any two cell types can end up
 * adjacent, so colour alone cannot carry identity once there are more than a
 * handful of types -- no palette makes 27 categories pairwise-distinguishable.
 * The viewer therefore defaults to HIGHLIGHT mode, where one cell type is
 * drawn in a single accent hue against a neutral grey cloud. That is
 * colour-blind-safe by construction and is the mode the legend, the search box
 * and the marker panel all drive. The "all cell types" mode is offered for
 * overview, and carries the real identifiers: a legend with counts, hover
 * readout, and click-to-isolate. Gene expression uses a single-hue sequential
 * ramp, never a rainbow.
 *
 * No build step, no external dependencies: the page is served by plain Apache
 * next to the rest of the site.
 *
 * Data contract: see singlecell_data/<dataset_id>/manifest.json, written by
 * pipeline/05_export_web.py.
 */
(function (global) {
  "use strict";

  // --- palette ------------------------------------------------------------
  // These are FALLBACKS only, used if the stylesheet is missing.  The live
  // values are read from cellatlas.css custom properties at construction (see
  // _readPalette): the stylesheet owns the palette, so dark mode is a second
  // set of *selected* steps rather than an automatic inversion, and a site
  // owner can retheme the atlas without editing this file.
  //
  // The eight base slots are the validated categorical order from the
  // project's data-visualisation reference: assigned in fixed order, never
  // cycled. Beyond eight, extra steps are derived within the same hue families
  // by mixing each base toward the surface and toward the ink. Those derived
  // steps are NOT claimed to be pairwise distinguishable in a scatter, which
  // is exactly why highlight mode is the default and the legend, the hover
  // readout and the marker panel carry identity.
  var FALLBACK = {
    cat: ["#2a78d6", "#eb6834", "#1baf7a", "#eda100",
          "#e87ba4", "#008300", "#4a3aa7", "#e34948"],
    grey: "#c9c8c4",
    surface: "#fcfcfb",
    ink: "#0b0b0b",
    ramp: ["#cde2fb", "#b7d3f6", "#9ec5f4", "#86b6ef", "#6da7ec",
           "#5598e7", "#3987e5", "#2a78d6", "#256abf", "#1c5cab",
           "#184f95", "#104281", "#0d366b"],
    zero: "#efeeeb",   // neutral for "not expressed"
    na: "#d8d7d3"      // cells outside the current filter
  };

  //: 8 base slots + this many derived rounds of 8.  A dataset with more cell
  //: types than the total cycles, which is a real, visible limit rather than
  //: a silent one.
  var N_DERIVED_ROUNDS = 4;

  // --- small helpers ------------------------------------------------------
  function hexToU32(hex) {
    var h = hex.replace("#", "");
    var r = parseInt(h.substring(0, 2), 16);
    var g = parseInt(h.substring(2, 4), 16);
    var b = parseInt(h.substring(4, 6), 16);
    // little-endian RGBA packed into a Uint32, with alpha = 255
    return (255 << 24) | (b << 16) | (g << 8) | r;
  }

  function mixHex(a, b, t) {
    var ar = parseInt(a.substr(1, 2), 16), ag = parseInt(a.substr(3, 2), 16), ab = parseInt(a.substr(5, 2), 16);
    var br = parseInt(b.substr(1, 2), 16), bg = parseInt(b.substr(3, 2), 16), bb = parseInt(b.substr(5, 2), 16);
    var r = Math.round(ar + (br - ar) * t);
    var g = Math.round(ag + (bg - ag) * t);
    var bl = Math.round(ab + (bb - ab) * t);
    return "#" + ((1 << 24) | (r << 16) | (g << 8) | bl).toString(16).slice(1);
  }

  function cssVar(style, name, fallback) {
    var v = style.getPropertyValue(name);
    v = v ? v.trim() : "";
    return v || fallback;
  }

  function fmtNum(x) {
    if (x === null || x === undefined || isNaN(x)) return "–";
    return Number(x).toLocaleString("en-US");
  }

  function el(tag, cls, text) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text !== undefined) e.textContent = text;
    return e;
  }

  // --- gunzip (DecompressionStream, with a fetch fallback) ----------------
  // Expression files are gzipped. Browsers do not transparently decompress
  // them because the server sends them as application/octet-stream, and we
  // want to avoid a Content-Encoding rule in .htaccess that would also affect
  // the other gzip assets on the site.
  function gunzip(u8) {
    if (typeof DecompressionStream === "undefined") {
      return Promise.reject(new Error("DecompressionStream unsupported"));
    }
    var ds = new DecompressionStream("gzip");
    var stream = new Blob([u8]).stream().pipeThrough(ds);
    return new Response(stream).arrayBuffer().then(function (b) {
      return new Uint8Array(b);
    });
  }

  // =======================================================================
  function CnidoAtlas(opts) {
    this.mount = typeof opts.mount === "string"
      ? document.querySelector(opts.mount) : opts.mount;
    this.dataUrl = opts.dataUrl.replace(/\/?$/, "/");
    this.datasetId = opts.datasetId || "dataset";

    /* Two page layouts, chosen by the host page rather than guessed here.
       `violinBand` puts the per-cell-type distribution in a full-width band
       under the map (gene_exp.php); without it the distribution is the narrow
       rail beside the map (cell_atlas.php).
       `sideMap` fills the column next to the map with a second drawing of the
       SAME embedding coloured by cell type.  It is a canvas rather than the
       dataset's exported umap_celltype.png because that PNG cannot be made to
       line up with this one: it is drawn by matplotlib with the two axes
       scaled independently to fill its box (anisotropic), while _project()
       below fits them with one uniform scale.  Measured on SPIST_whole_adult
       the PNG stretches y by 17.9% against x (NVECT_whole_adult 7.5%, a
       different number for every dataset), so the same cluster lands in a
       different place in the two panels and the reader cannot carry a region
       from one to the other.  Drawing it from embedding.bin with this file's
       own projection makes the correspondence exact instead of approximate,
       and it also drops the title and footer that are baked into the PNG. */
    this.violinBand = !!opts.violinBand;
    this.sideMap = !!opts.sideMap;

    /* What each cell-type name stands for, for a dataset that publishes them
       as abbreviations rather than words -- {labels: {name: {brief, title}},
       note}.  It is data, not a section of the host page, because the place a
       reader meets `i_n_ec4` with nothing to decode it against is this file's
       own Groups list.  Absent for every other dataset, and then the list is
       exactly what it was. */
    this.typeKey = opts.typeKey && opts.typeKey.labels ? opts.typeKey : null;

    // Stable dispatcher rather than a bare callback: load() reports progress
    // before build() has created the status bar, so reassigning onStatus in
    // build() would leave the first few calls pointing at a function that is
    // not there yet.
    var self = this;
    this._userStatus = opts.onStatus || null;
    this.onStatus = function (msg) {
      if (self._userStatus) return self._userStatus(msg);
      if (self.statusEl) self.statusEl.textContent = msg || "";
    };

    this.canvas = null;
    this.ctx = null;
    this.img = null;
    this.buf = null;
    this.bufW = 0;
    this.bufH = 0;

    // the second map (sideMap): its own canvas and buffer, but it carries no
    // view of its own -- see _projectSide(), which derives its transform from
    // this one's, so the two can never drift apart.
    this.sideCanvas = null;
    this.sideCtx = null;
    this.sideImg = null;
    this.sideBuf = null;
    this.sideW = 0;
    this.sideH = 0;
    this.sideColors = null;    // Uint32Array[n], cell-type colour per cell
    this.sideFocus = -1;       // cell type the second map is showing alone, or -1
    this.sideMask = null;      // Uint8Array[n] for that type, built on demand
    /* Where the last frame put each map on screen: {s, ox, oy, w, h}.  Kept
       per map because the hit tests and the brush need the same numbers the
       paint used. */
    this.view = null;
    this.sideView = null;

    this.embedding = null;    // Float32Array[n*2]
    this.cellmeta = null;     // Uint16Array[n*3]
    this.qc = null;           // Float32Array[n*3]
    this.composition = null;
    this.markers = null;
    this.genes = [];          // [{gene,file,n_cells_expressing,pct_expressing}]
    this.geneIndex = {};      // gene -> entry

    this.n = 0;
    this.colors = null;       // Uint32Array[n] cache

    this.mode = "cell_type";  // cell_type | cluster | sample | qc_n_counts | qc_n_genes | qc_pct_mt | gene
    this.highlightIdx = 0;    // index into the active category list (highlight mode)
    this.highlightOn = true;
    this.activeGene = null;
    this.exprValues = null;   // Float32Array[n] for the active gene (0 = not expressed)
    this.exprLoaded = null;   // gene name currently loaded

    // view transform: screen = (data - center) * scale + canvasCenter
    this.scale = 1; this.tx = 0; this.ty = 0;
    this.dragging = false;

    // selection (brush over the embedding)
    this.selected = null;     // Uint8Array[n] or null
    this.selecting = false;
    this.selStart = null; this.selEnd = null;

    this.hoverIdx = -1;
  }

  // --- palette resolution -------------------------------------------------
  // The stylesheet owns the palette.  Reading it here, rather than hard-coding
  // hex in JS, means the dark-mode steps -- which are *selected* for the dark
  // surface, not derived by inverting the light ones -- reach the canvas too,
  // and that a site owner can retheme the atlas without editing this file.
  CnidoAtlas.prototype._readPalette = function () {
    var style = global.getComputedStyle(this.mount);

    var base = [];
    for (var i = 1; i <= 8; i++) {
      base.push(cssVar(style, "--cna-cat-" + i, FALLBACK.cat[i - 1]));
    }
    this._greyHex = cssVar(style, "--cna-grey", FALLBACK.grey);
    this._surfaceHex = cssVar(style, "--cna-surface", FALLBACK.surface);
    this._inkHex = cssVar(style, "--cna-ink", FALLBACK.ink);
    this.GREY = hexToU32(this._greyHex);
    this.SURFACE = hexToU32(this._surfaceHex);
    this.SEQ_ZERO = hexToU32(cssVar(style, "--cna-zero", FALLBACK.zero));
    this.SEQ_NA = hexToU32(cssVar(style, "--cna-na", FALLBACK.na));

    // Extended slots: mix each base toward the surface, then toward the ink,
    // in alternating rounds.  This is mode-aware for free -- in light mode the
    // first round is a pale tint and the second a deep shade; on the dark
    // surface the same two mixes come out dark and light respectively.
    var rounds = [
      [this._surfaceHex, 0.55], [this._inkHex, 0.40],
      [this._surfaceHex, 0.78], [this._inkHex, 0.68]
    ];
    var hex = base.slice();
    for (var r = 0; r < Math.min(N_DERIVED_ROUNDS, rounds.length); r++) {
      for (var k = 0; k < base.length; k++) {
        hex.push(mixHex(base[k], rounds[r][0], rounds[r][1]));
      }
    }
    this._catHex = hex;
    this._catU32 = new Uint32Array(hex.length);
    for (var j = 0; j < hex.length; j++) this._catU32[j] = hexToU32(hex[j]);

    // 256-entry lookup table, so the per-cell render loop is one array index
    // rather than an interpolation.
    var rampStr = cssVar(style, "--cna-ramp", "");
    var ramp = rampStr ? rampStr.split(/[\s,]+/).filter(Boolean) : [];
    if (ramp.length < 2) ramp = FALLBACK.ramp;
    var stops = ramp.map(hexToU32);
    this._rampHex = ramp;
    this._rampLUT = new Uint32Array(256);
    for (var s = 0; s < 256; s++) {
      var pos = (s / 255) * (stops.length - 1);
      var i0 = Math.min(stops.length - 2, Math.floor(pos));
      var f = pos - i0;
      var a = stops[i0], b = stops[i0 + 1];
      var ar = a & 255, ag = (a >> 8) & 255, ab = (a >> 16) & 255;
      var br = b & 255, bg = (b >> 8) & 255, bb = (b >> 16) & 255;
      this._rampLUT[s] = (255 << 24)
        | (Math.round(ab + (bb - ab) * f) << 16)
        | (Math.round(ag + (bg - ag) * f) << 8)
        | Math.round(ar + (br - ar) * f);
    }
  };

  CnidoAtlas.prototype._categoryColor = function (i) {
    return this._catHex[i % this._catHex.length];
  };

  //: t in [0,1].  t<=0 is the neutral "not expressed" colour, which is what
  //: makes a feature plot readable: absence must not look like low expression.
  CnidoAtlas.prototype._seqColor = function (t) {
    if (!(t > 0)) return this.SEQ_ZERO;
    if (t >= 1) return this._rampLUT[255];
    return this._rampLUT[(t * 255) | 0];
  };

  CnidoAtlas.prototype.load = function () {
    var self = this;
    self.onStatus("Loading manifest…");
    return fetch(self.dataUrl + "manifest.json")
      .then(function (r) {
        if (!r.ok) throw new Error("manifest.json not found (" + r.status + ")");
        return r.json();
      })
      .then(function (m) {
        self.manifest = m;
        self.n = m.n_cells;
        self.onStatus("Loading embedding…");
        return Promise.all([
          fetch(self.dataUrl + (m.embedding ? m.embedding.file : "embedding.bin"))
            .then(function (r) { return r.arrayBuffer(); }),
          fetch(self.dataUrl + "cellmeta.bin").then(function (r) { return r.arrayBuffer(); }),
          fetch(self.dataUrl + "qc.bin").then(function (r) { return r.arrayBuffer(); }),
          fetch(self.dataUrl + "composition.json").then(function (r) { return r.json(); }),
          fetch(self.dataUrl + "markers.json").then(function (r) { return r.json(); }),
          fetch(self.dataUrl + "genes.json").then(function (r) { return r.json(); })
            .catch(function () { return { genes: [] }; })
        ]);
      })
      .then(function (res) {
        self.embedding = new Float32Array(res[0]);
        self.cellmeta = new Uint16Array(res[1]);
        self.qc = new Float32Array(res[2]);
        self.composition = res[3];
        self.markers = res[4];
        self.genes = (res[5] && res[5].genes) || [];
        self.genes.forEach(function (g) { self.geneIndex[g.gene] = g; });
        if (self.embedding.length !== self.n * 2) {
          throw new Error("embedding.bin length " + self.embedding.length +
                          " does not match n_cells*2 = " + (self.n * 2));
        }
        self.colors = new Uint32Array(self.n);
        self.selected = new Uint8Array(self.n);
        self._fitView();
        self.build();
        self.applyColoring();
        self.onStatus("");
        return self;
      });
  };

  // ---- view --------------------------------------------------------------
  CnidoAtlas.prototype._fitView = function () {
    var n = this.n, e = this.embedding;
    var minx = Infinity, maxx = -Infinity, miny = Infinity, maxy = -Infinity;
    for (var i = 0; i < n; i++) {
      var x = e[i * 2], y = e[i * 2 + 1];
      if (x < minx) minx = x; if (x > maxx) maxx = x;
      if (y < miny) miny = y; if (y > maxy) maxy = y;
    }
    this.bounds = { minx: minx, maxx: maxx, miny: miny, maxy: maxy };
    this.pad = 0.04;
  };

  CnidoAtlas.prototype.resetView = function () {
    this.scale = 1; this.tx = 0; this.ty = 0;
    this.render();
  };

  // ---- DOM ---------------------------------------------------------------
  CnidoAtlas.prototype.build = function () {
    var self = this;
    var root = this.mount;
    root.innerHTML = "";
    root.className = "cna-root";
    // must happen after the class is set: the custom properties are scoped to
    // .cna-root, so getComputedStyle() cannot resolve them before this point
    this._readPalette();

    // ---- controls --------------------------------------------------------
    var controls = el("div", "cna-controls");
    root.appendChild(controls);
    this.controls = controls;

    controls.appendChild(el("div", "cna-title", this.manifest.species || this.datasetId));
    var sub = el("div", "cna-subtitle");
    sub.textContent = [this.manifest.tissue_organ, this.manifest.stage]
      .filter(Boolean).join(" · ") || "";
    controls.appendChild(sub);

    // n_samples is written into qc_summary by the QC stage; fall back to it so
    // the tile is correct for datasets exported before it moved to the top level
    var qs = this.manifest.qc_summary || {};
    var nLib = this.manifest.n_samples !== undefined
      ? this.manifest.n_samples : qs.n_samples;
    // For a `cluster_only` dataset cell_types *are* the clusters, so the two
    // colour-by entries would be the same view under two names and the two
    // headline tiles would show the same number twice.  Collapse both to one.
    var clusterOnly = this.manifest.cell_type_mode === "cluster";

    var stat = el("div", "cna-stat-row");
    stat.appendChild(this._stat(fmtNum(this.n), "cells"));
    stat.appendChild(this._stat(
      fmtNum(clusterOnly ? this.manifest.n_clusters : this.manifest.n_cell_types),
      clusterOnly ? "clusters" : "cell types"));
    if (!clusterOnly) {
      stat.appendChild(this._stat(fmtNum(this.manifest.n_clusters), "clusters"));
    }
    stat.appendChild(this._stat(fmtNum(nLib), "libraries"));
    controls.appendChild(stat);

    // colour-by selector
    controls.appendChild(el("label", "cna-label", "Colour cells by"));
    var sel = el("select", "cna-select");
    // The mitochondrial-% colouring only exists for a dataset whose pipeline
    // actually found mitochondrial genes.  Every dataset currently in the
    // module reports mito_filtering_available = false, and offering the entry
    // anyway would colour by a channel that holds no measurement -- the third
    // column of qc.bin is zeros in those exports.  The entry comes back by
    // itself for any dataset that does carry the numbers.
    var hasMito = this.hasMito();
    var groups = [
      [clusterOnly ? "Cluster" : "Cell type", "cell_type"]
    ].concat(clusterOnly ? [] : [["Cluster", "cluster"]]).concat([
      ["Library / sample", "sample"],
      ["QC: nCount (UMI)", "qc_n_counts"],
      ["QC: nGene", "qc_n_genes"]
    ]).concat(hasMito ? [["QC: mitochondrial %", "qc_pct_mt"]] : []).concat([
      ["Gene expression", "gene"]
    ]);
    groups.forEach(function (g) {
      var o = el("option", null, g[0]); o.value = g[1]; sel.appendChild(o);
    });
    sel.value = this.mode;
    sel.addEventListener("change", function () {
      self.mode = sel.value;
      if (self.mode === "gene") self._ensureGeneSelected();
      self.applyColoring();
      self.renderLegend();
    });
    controls.appendChild(sel);
    this.modeSelect = sel;

    // gene picker (only meaningful in gene mode)
    var geneWrap = el("div", "cna-gene-wrap");
    var geneInput = el("input", "cna-input");
    geneInput.type = "text";
    geneInput.placeholder = "Search a marker gene…";
    geneInput.setAttribute("list", "cna-gene-list");
    var datalist = el("datalist"); datalist.id = "cna-gene-list";
    this.genes.slice(0, 4000).forEach(function (g) {
      var o = el("option"); o.value = g.gene; datalist.appendChild(o);
    });
    geneWrap.appendChild(geneInput);
    geneWrap.appendChild(datalist);
    var geneBtn = el("button", "cna-btn", "Plot");
    geneBtn.addEventListener("click", function () {
      self.setGene(geneInput.value.trim());
    });
    geneInput.addEventListener("keydown", function (ev) {
      if (ev.key === "Enter") self.setGene(geneInput.value.trim());
    });
    geneWrap.appendChild(geneBtn);
    controls.appendChild(geneWrap);
    this.geneInput = geneInput;
    this.geneWrap = geneWrap;
    geneWrap.style.display = "none";

    // highlight toggle
    var hlWrap = el("label", "cna-check");
    var hlBox = el("input"); hlBox.type = "checkbox"; hlBox.checked = true;
    hlBox.addEventListener("change", function () {
      self.highlightOn = hlBox.checked;
      self.applyColoring(); self.renderLegend();
    });
    hlWrap.appendChild(hlBox);
    hlWrap.appendChild(el("span", null, "Highlight one group (recommended)"));
    controls.appendChild(hlWrap);
    this.hlBox = hlBox;

    // ---- map, and the expression panel beside it -------------------------
    /* The embedding is square-ish, so on a wide page the map row ran a long
       way empty to the right of the cloud, while the per-cell-type expression
       panel -- the third column of the row below -- was the narrowest of the
       three and had to be scrolled to read.  They are one two-column row now:
       map left, expression right.  Both children carry their own background
       and the wrapper shows through as the 1px gap, so the rule between them
       is the same hairline the panels below use. */
    var main = el("div", "cna-main");
    var stage = el("div", "cna-stage");
    var cvs = el("canvas", "cna-canvas");
    stage.appendChild(cvs);
    var tip = el("div", "cna-tip"); tip.style.display = "none";
    stage.appendChild(tip);
    /* The rail carries the "colour by a gene" note only when it is on screen;
       with no gene chosen the rail is hidden and the note would go with it, so
       that one affordance rides along here instead.  In the band layout the
       note is dropped once a gene is loaded -- the distribution is already on
       the page below the map, and the clause would otherwise tell the reader to
       go and do what the page has done for them.  See _syncHint. */
    this.hintEl = el("div", "cna-hint", "");
    stage.appendChild(this.hintEl);
    main.appendChild(stage);
    this.mainEl = main;

    this.canvas = cvs; this.ctx = cvs.getContext("2d", { willReadFrequently: true });
    this.tip = tip;

    // ---- legend / composition / violin -----------------------------------
    var lower = el("div", "cna-lower");
    var legendPanel = el("div", "cna-panel");
    legendPanel.appendChild(el("div", "cna-panel-title", "Groups"));
    this.legendBody = el("div", "cna-legend");
    legendPanel.appendChild(this.legendBody);
    /* The key's footnote, when there is one: outside the scrolling list, so it
       stays in view while the names scroll past it.  Written by
       renderLegend(), which is the one place that knows what the list is. */
    this.legendFoot = el("div", "cna-leg-foot");
    legendPanel.appendChild(this.legendFoot);
    lower.appendChild(legendPanel);

    var compPanel = el("div", "cna-panel");
    compPanel.appendChild(el("div", "cna-panel-title", "Composition"));
    this.compBody = el("div", "cna-comp");
    compPanel.appendChild(this.compBody);
    lower.appendChild(compPanel);

    var violPanel = el("div", "cna-panel cna-rail");
    violPanel.appendChild(el("div", "cna-panel-title", "Expression by cell type"));
    this.violBody = el("div", "cna-violin");
    violPanel.appendChild(this.violBody);
    this.violPanel = violPanel;
    this.violTitle = violPanel.firstChild;

    if (this.violinBand) {
      /* Map left, the same cells coloured by cell type right, the distribution
         below both -- the order the gene page reads in.  The band is a sibling
         of the map row rather than a grid cell, so it spans the viewer's whole
         width instead of being squeezed into a rail. */
      main.classList.add("cna-main-split");
      if (this.sideMap) {
        var sidePanel = el("div", "cna-panel cna-sidemap");
        sidePanel.appendChild(el("div", "cna-panel-title", clusterOnly
          ? "Coloured by cluster (" + fmtNum(this.manifest.n_clusters) + ")"
          : "Coloured by cell type (" + fmtNum(this.manifest.n_cell_types) + ")"));
        var sideStage = el("div", "cna-sidemap-stage");
        var sideCvs = el("canvas", "cna-canvas");
        sideStage.appendChild(sideCvs);
        sideStage.appendChild(el("div", "cna-hint",
          "Point at a cell to name it · click a type below to pick it out"));
        this.sideTip = el("div", "cna-tip");
        this.sideTip.style.display = "none";
        sideStage.appendChild(this.sideTip);
        sidePanel.appendChild(sideStage);
        this.sideStage = sideStage;
        this.sideCanvas = sideCvs;
        this.sideCtx = sideCvs.getContext("2d", { willReadFrequently: true });

        /* One colour per cell, and unlike this.colors it never changes: this
           map is always the categorical one, whatever the left map is showing.
           Built here rather than per frame because the per-cell loop should
           stay a lookup. */
        var nS = this.n, lut = this._catU32, lutN = lut.length;
        var sc = new Uint32Array(nS);
        for (var si = 0; si < nS; si++) {
          sc[si] = lut[this.cellmeta[si * 3] % lutN];
        }
        this.sideColors = sc;

        this.sideLegendBody = el("div", "cna-legend cna-sidemap-leg");
        sidePanel.appendChild(this.sideLegendBody);
        main.appendChild(sidePanel);
        this.sidePanel = sidePanel;
      } else {
        // No second map: the map takes the column back rather than leaving a
        // blank panel beside it.
        main.classList.add("cna-main-solo");
      }
      violPanel.classList.add("cna-vplot-band");
      root.appendChild(main);
      root.appendChild(violPanel);
    } else {
      main.appendChild(violPanel);
      root.appendChild(main);
    }
    root.appendChild(lower);

    // ---- QC / provenance disclosure --------------------------------------
    root.appendChild(this._buildQcPanel());

    this.statusEl = el("div", "cna-status");
    root.appendChild(this.statusEl);

    this._bindEvents();
    this._resize();
    this.renderLegend();
    this.renderComposition();
    this.renderViolin();
    this._syncControls();
  };

  CnidoAtlas.prototype._stat = function (value, label) {
    var d = el("div", "cna-stat");
    d.appendChild(el("div", "cna-stat-v", value));
    d.appendChild(el("div", "cna-stat-l", label));
    return d;
  };

  /* 地图左下角那句话，随「有没有基因」变；_syncControls 每换一次状态都会走到这里。 */
  CnidoAtlas.prototype._syncHint = function () {
    if (!this.hintEl) return;
    var base = "Drag to pan · scroll to zoom · shift-drag to select cells";
    var have = !!(this.activeGene && this.exprValues);
    this.hintEl.textContent = (this.violinBand && have)
      ? base
      : base + " · colour by a gene to see its distribution by cell type";
  };

  CnidoAtlas.prototype._syncControls = function () {
    if (!this.geneWrap) return;
    this._syncHint();
    this.geneWrap.style.display = this.mode === "gene" ? "flex" : "none";
    /* The rail is worth a column only when it has violins in it.  With no gene
       chosen renderViolin() leaves a heading and one line of prose, which is
       not worth 360px beside the map, so the map takes the whole row back.
       Toggled here rather than in renderViolin() because this runs on every
       colouring change, including the URL-state ones. */
    if (this.violinBand) {
      /* The violins are a full-width band under the map row, so with no gene
         the band is dropped outright -- a heading and one line of prose across
         the whole page is worse than nothing.  The map row keeps both columns
         either way: the cell-type figure is not the violins.
         Gated on the gene being loaded, NOT on the colouring: the band shows
         where this gene sits in each cell type, which does not change when the
         reader switches the map to cell types -- and switching the map to cell
         types is precisely what they do to read the figure beside it, so tying
         the band to mode === "gene" made it disappear at the moment it was
         most wanted. */
      if (this.violPanel) {
        this.violPanel.style.display =
          (this.activeGene && this.exprValues) ? "" : "none";
      }
      return;
    }
    if (this.mainEl) {
      this.mainEl.classList.toggle("cna-main-solo", this.mode !== "gene");
    }
  };

  CnidoAtlas.prototype._ensureGeneSelected = function () {
    if (!this.activeGene) {
      var first = (this.markers && Object.keys(this.markers)[0]);
      if (first && this.markers[first].length) {
        var gene = this.markers[first][0].gene;
        this.setGene(gene);
        /* setGene() sets activeGene only once the file has loaded, and fills
           the box in that same callback -- so reading activeGene here would
           always write null and leave a plotted gene above an empty field.
           Write the gene we actually asked for. */
        if (this.geneInput) this.geneInput.value = gene;
      }
    }
  };

  // ---- QC + provenance panel ---------------------------------------------
  // Referee 2 (Major #3) asks for dataset-level QC statistics and Referee 2
  // (Major #9) asks for provenance and versioning. Both are surfaced here
  // rather than buried in a supplement, so a reader can see what was done to
  // the cells they are looking at.
  CnidoAtlas.prototype._buildQcPanel = function () {
    var m = this.manifest, q = m.qc_summary || {};
    var wrap = el("details", "cna-qc");
    var sum = el("summary", null, "Dataset quality control and provenance");
    wrap.appendChild(sum);
    var body = el("div", "cna-qc-body");

    function row(k, v) {
      if (v === undefined || v === null || v === "") return;
      var r = el("div", "cna-qc-row");
      r.appendChild(el("span", "cna-qc-k", k));
      r.appendChild(el("span", "cna-qc-v", String(v)));
      body.appendChild(r);
    }

    // Explanatory prose (an annotation caveat, a filtering rationale) is a
    // paragraph, not a value.  The body is a grid of ~320px columns, so a long
    // string left in a normal row wraps into a tall narrow cell and drags the
    // whole row band down with it; these span the grid instead.
    function rowWide(k, v) {
      if (v === undefined || v === null || v === "") return;
      var r = el("div", "cna-qc-row cna-qc-row-wide");
      r.appendChild(el("span", "cna-qc-k", k));
      r.appendChild(el("span", "cna-qc-v", String(v)));
      body.appendChild(r);
    }

    var src = m.source || {};
    row("BioProject", src.bioproject);
    row("SRA study", src.sra_study);
    row("GEO series", src.geo_series);
    row("Platform", q.platform_class);
    row("Cells in source deposit", fmtNum(q.n_cells_raw));
    row("Cells after QC", fmtNum(q.n_cells_after_cell_qc));
    row("Cells in this atlas", fmtNum(q.n_cells_final));
    // null, not undefined, is what an export writes when the deposit does not
    // say: showing "– (0.00%)" would read as a measured zero
    row("Doublets removed", q.n_doublets_removed !== undefined
      && q.n_doublets_removed !== null
      ? fmtNum(q.n_doublets_removed)
        + (q.doublet_rate !== null && q.doublet_rate !== undefined
           ? " (" + (q.doublet_rate * 100).toFixed(2) + "%)" : "")
      : "");
    row("Doublet method", q.doublet_method);
    row("Integration", q.integration_method);
    row("Libraries", fmtNum(q.n_samples));
    // A null count means the export never looked for a mitochondrial gene set,
    // which is not the same finding as looking and finding too few -- so the
    // row is omitted rather than filled with a zero, and the reason (when the
    // export has one) is stated in its own row instead.
    if (q.mito_genes_n !== undefined && q.mito_genes_n !== null) {
      row("Mitochondrial genes found", fmtNum(q.mito_genes_n) +
        (q.mito_filtering_available ? "" : " (too few — no MT% filter applied)"));
    }
    if (q.mito_note) rowWide("Mitochondrial fraction", q.mito_note);
    // The four modes the exports actually use, named for what they are.  The
    // two that are not ours -- "imported" (a published object's own filtering)
    // and "none" (the deposit is already a cell set) -- must not be described
    // as thresholds "reproduced from the source publication": that reads as a
    // claim to have re-derived them, and nothing was re-derived.
    var th = q.thresholds || {};
    if (th.mode) {
      row("Filtering strategy",
        th.mode === "adaptive"
          ? "Per-library MAD outlier detection (adaptive)"
          : th.mode === "absolute"
            ? "Fixed thresholds chosen for this deposit"
            : th.mode === "imported"
              ? "As published — not re-derived by CnidoSite"
              : "No cell-level filtering applied");
      if (th.mode === "adaptive") {
        row("MADs", th.nmads);
        if (th.min_genes != null || th.min_counts != null) {
          row("min genes / min UMI", th.min_genes + " / " + th.min_counts);
        }
      } else if (th.mode === "absolute" || th.mode === "imported") {
        // print only what was actually set: an unset floor is absent, not null
        if (th.min_genes != null) row("min genes", th.min_genes);
        if (th.min_counts != null || th.max_counts != null) {
          row("min–max UMI", (th.min_counts != null ? th.min_counts : "0")
            + "–" + (th.max_counts != null ? th.max_counts : "∞"));
        }
        if (th.max_pct_mt != null) row("max MT%", th.max_pct_mt);
      }
      rowWide("Filtering provenance", th.source);
    }
    var removed = q.cells_removed_by_criterion;
    if (removed) {
      var parts = Object.keys(removed).filter(function (k) { return removed[k]; })
        .map(function (k) { return k + ": " + fmtNum(removed[k]); });
      if (parts.length) row("Cells removed by", parts.join(" · "));
    }
    // Three provenances, not two.  `cluster_only` is a dataset whose features
    // are identified only by transcript ID, so the marker panel could not name
    // any cluster; the atlas ships cluster identities and says so rather than
    // showing a name the data does not support.  Referee 3's complaint was that
    // clusters were not identified by cell type -- an unnamed cluster is an
    // honest answer to that, an invented name is not.
    row("Annotation source",
      m.annotation_provenance === "published"
        ? "Cell types inherited from the source publication"
        : m.annotation_provenance === "cluster_only"
          ? "Leiden clusters only — marker panel matched no cluster"
          : "De-novo clustering with a cnidarian marker panel (auto: prefix)");
    if (m.annotation_note) rowWide("Annotation caveat", m.annotation_note);
    row("Pipeline", q.pipeline_version || m.pipeline_version);
    row("Processed", q.processed_utc);

    wrap.appendChild(body);
    return wrap;
  };

  // ---- colouring ---------------------------------------------------------
  CnidoAtlas.prototype.activeCategories = function () {
    var m = this.manifest;
    if (this.mode === "cell_type") return m.cell_types;
    if (this.mode === "cluster") return m.clusters;
    if (this.mode === "sample") return m.samples;
    return null;
  };

  // Whether this dataset carries a mitochondrial fraction at all.  The export
  // writes qc.bin either way (three uint16 columns), but says in the manifest
  // whether a mito gene set was found; without one the column is meaningless
  // and must not be presented as a percentage.
  CnidoAtlas.prototype.hasMito = function () {
    var q = this.manifest && this.manifest.qc_summary;
    return !!(q && q.mito_filtering_available === true);
  };

  CnidoAtlas.prototype.cellValue = function (i) {
    // returns the category index (or QC value) for cell i under the current mode
    var m = this.manifest;
    if (this.mode === "cell_type") return this.cellmeta[i * 3];
    if (this.mode === "cluster") return this.cellmeta[i * 3 + 1];
    if (this.mode === "sample") return this.cellmeta[i * 3 + 2];
    if (this.mode === "qc_n_counts") return this.qc[i * 3];
    if (this.mode === "qc_n_genes") return this.qc[i * 3 + 1];
    if (this.mode === "qc_pct_mt") return this.qc[i * 3 + 2];
    if (this.mode === "gene") return this.exprValues ? this.exprValues[i] : 0;
    return 0;
  };

  CnidoAtlas.prototype.applyColoring = function () {
    var n = this.n, mode = this.mode, i;
    var isCat = (mode === "cell_type" || mode === "cluster" || mode === "sample");
    this._syncControls();

    if (isCat && this.highlightOn) {
      var target = this.highlightIdx;
      var acc = this._catU32[target % this._catU32.length];
      var grey = this.GREY;
      for (i = 0; i < n; i++) {
        this.colors[i] = (this.cellValue(i) === target) ? acc : grey;
      }
    } else if (isCat) {
      var lut = new Uint32Array(this.activeCategories().length);
      for (i = 0; i < lut.length; i++) lut[i] = this._catU32[i % this._catU32.length];
      for (i = 0; i < n; i++) {
        var c = this.cellValue(i);
        this.colors[i] = (c < lut.length) ? lut[c] : hexToU32("#999999");
      }
    } else if (mode === "gene") {
      var v = this.exprValues;
      if (!v) { this.colors.fill(this.SEQ_ZERO); }
      else {
        var mx = 0;
        for (i = 0; i < n; i++) if (v[i] > mx) mx = v[i];
        for (i = 0; i < n; i++) this.colors[i] = this._seqColor(mx > 0 ? v[i] / mx : 0);
      }
    } else {
      // QC: sequential ramp over the 2nd..98th percentile so a handful of
      // extreme cells do not flatten the whole distribution to one end.
      var col = mode === "qc_n_counts" ? 0 : (mode === "qc_n_genes" ? 1 : 2);
      var vals = [];
      for (i = 0; i < n; i++) {
        var x = this.qc[i * 3 + col];
        if (isFinite(x) && x >= 0) vals.push(x);
      }
      if (!vals.length) { this.colors.fill(this.SEQ_NA); }
      else {
        vals.sort(function (a, b) { return a - b; });
        var lo = vals[Math.floor(vals.length * 0.02)];
        var hi = vals[Math.floor(vals.length * 0.98)];
        if (hi <= lo) hi = lo + 1;
        for (i = 0; i < n; i++) {
          var q = this.qc[i * 3 + col];
          this.colors[i] = (isFinite(q) && q >= 0)
            ? this._seqColor((q - lo) / (hi - lo)) : this.SEQ_NA;
        }
      }
    }
    this.render();
    // keep the side panels in step with the colouring: switching to a gene
    // changes what the composition panel should be showing (it is defined for
    // the categorical colourings, not for expression)
    this.renderComposition();
  };

  // ---- gene expression ---------------------------------------------------
  CnidoAtlas.prototype.setGene = function (gene) {
    var self = this;
    if (!gene) return;
    var entry = this.geneIndex[gene];
    if (!entry) {
      // allow case-insensitive fallback
      var lower = gene.toLowerCase();
      var hit = this.genes.filter(function (g) {
        return g.gene.toLowerCase() === lower;
      })[0];
      if (!hit) {
        this.onStatus("No expression data for “" + gene + "” in this dataset.");
        setTimeout(function () { self.onStatus(""); }, 4000);
        return;
      }
      entry = hit; gene = hit.gene;
    }
    this.onStatus("Loading expression for " + gene + "…");
    this.mode = "gene";
    this.modeSelect.value = "gene";
    this._syncControls();
    if (this.geneInput) this.geneInput.value = gene;

    return fetch(this.dataUrl + entry.file)
      .then(function (r) { return r.arrayBuffer(); })
      .then(function (b) { return gunzip(new Uint8Array(b)); })
      .then(function (u8) {
        var dv = new DataView(u8.buffer, u8.byteOffset, u8.byteLength);
        var magic = String.fromCharCode(u8[0], u8[1], u8[2]);
        if (magic !== "CX1") throw new Error("bad expression file header");
        var vmax = dv.getFloat32(3, true);
        var count = dv.getUint32(7, true);
        var vals = new Float32Array(self.n);
        var pos = 11, acc = 0;
        for (var k = 0; k < count; k++) {
          var d = dv.getUint32(pos, true); pos += 4;
          acc += d;
          var q = u8[pos]; pos += 1;
          if (acc < self.n) vals[acc] = q / 255 * vmax;
        }
        self.exprValues = vals;
        self.exprLoaded = gene;
        self.activeGene = gene;
        self.applyColoring();
        self.renderLegend();
        self.renderViolin();
        self.onStatus("");
      })
      .catch(function (e) {
        self.onStatus("Could not load expression for " + gene + ": " + e.message);
      });
  };

  // ---- rendering ---------------------------------------------------------
  CnidoAtlas.prototype._resize = function () {
    var stage = this.canvas.parentNode;
    var w = Math.max(320, stage.clientWidth);
    var h = Math.max(320, stage.clientHeight || Math.round(w * 0.62));
    var dpr = window.devicePixelRatio || 1;
    // render at CSS pixels, not device pixels: a retina buffer quadruples the
    // per-frame fill cost for no legibility gain on a dense scatter
    this.canvas.width = w;
    this.canvas.height = h;
    this.canvas.style.width = w + "px";
    this.canvas.style.height = h + "px";
    this.bufW = w; this.bufH = h;
    this.img = this.ctx.createImageData(w, h);
    this.buf = new Uint32Array(this.img.data.buffer);
    this.surfaceU32 = this.SURFACE;
    /* The second map is sized from its own box the same way.  It comes out a
       different size AND a different shape from the map on the left -- the
       panel beside the map is narrower than the map's own cell -- which is why
       _projectSide() derives its transform instead of being handed one. */
    if (this.sideCanvas) {
      var sw = Math.max(120, this.sideStage.clientWidth);
      var sh = Math.max(120, this.sideStage.clientHeight);
      this.sideCanvas.width = sw; this.sideCanvas.height = sh;
      this.sideCanvas.style.width = sw + "px";
      this.sideCanvas.style.height = sh + "px";
      this.sideW = sw; this.sideH = sh;
      this.sideImg = this.sideCtx.createImageData(sw, sh);
      this.sideBuf = new Uint32Array(this.sideImg.data.buffer);
    }
    this.render();
  };

  CnidoAtlas.prototype._project = function () {
    // Map data space into buffer space, preserving aspect ratio.
    var b = this.bounds, w = this.bufW, h = this.bufH, pad = this.pad;
    var dx = (b.maxx - b.minx) || 1, dy = (b.maxy - b.miny) || 1;
    var sx = (w * (1 - 2 * pad)) / dx, sy = (h * (1 - 2 * pad)) / dy;
    var s = Math.min(sx, sy) * this.scale;
    this._s = s;
    this._cx = (b.minx + b.maxx) / 2;
    this._cy = (b.miny + b.maxy) / 2;
    this._w = w; this._h = h;
  };

  /* The second map shows the window the first one shows, fitted to its own
     box.  Deriving it rather than storing a second transform is what keeps the
     two in step through zoom and pan: there is one scale and one translation,
     and this map only reads them.
     A point at px on the left map lands at k*px + (wS - k*wM)/2 on the right,
     where k = min(wS/wM, hS/hM): a similarity, so the two clouds are the same
     shape and the same way up, and a cluster in the upper left of one is in
     the upper left of the other.  The right box is a different shape from the
     left one, so k is the smaller of the two ratios and the map is centred in
     whatever slack that leaves. */
  CnidoAtlas.prototype._projectSide = function () {
    var wM = this._w, hM = this._h, wS = this.sideW, hS = this.sideH;
    var k = Math.min(wS / wM, hS / hM);
    this._sideK = k;
    this.sideView = {
      s: this._s * k,
      ox: wS / 2 + this.tx * k,
      oy: hS / 2 + this.ty * k,
      w: wS, h: hS
    };
    return this.sideView;
  };

  /* 2x2 splat per cell, "or" so a later point never erases an earlier one. */
  CnidoAtlas.prototype._paint = function (buf, w, h, view, colors, dim) {
    var n = this.n, e = this.embedding, cx = this._cx, cy = this._cy;
    var s = view.s, ox = view.ox, oy = view.oy;
    for (var i = 0; i < n; i++) {
      var px = ((e[i * 2] - cx) * s + ox) | 0;
      var py = ((e[i * 2 + 1] - cy) * -s + oy) | 0;   // flip y: UMAP y grows up
      if (px < 1 || py < 1 || px >= w - 1 || py >= h - 1) continue;
      var col = colors[i];
      if (dim && !dim[i]) {
        // dim unselected cells toward the surface instead of hiding them, so
        // the embedding's shape stays readable
        col = 0xff000000 | ((((col >> 16 & 255) + 205 * 3) >> 2) << 16)
                        | ((((col >> 8 & 255) + 205 * 3) >> 2) << 8)
                        | (((col & 255) + 205 * 3) >> 2);
      }
      var r0 = py * w + px;
      buf[r0] = col; buf[r0 + 1] = col;
      buf[r0 + w] = col; buf[r0 + w + 1] = col;
    }
  };

  CnidoAtlas.prototype.render = function () {
    if (!this.buf) return;
    var t0 = (global.performance || Date).now();
    this._project();
    var w = this._w, h = this._h, n = this.n;
    var s = this._s, cx = this._cx, cy = this._cy;
    var ox = w / 2 + this.tx, oy = h / 2 + this.ty;
    this.view = { s: s, ox: ox, oy: oy, w: w, h: h };

    this.buf.fill(this.surfaceU32);

    var hasSel = false;
    if (this.selected) {
      for (var q = 0; q < n; q++) if (this.selected[q]) { hasSel = true; break; }
    }
    var dim = hasSel ? this.selected : null;

    this._paint(this.buf, w, h, this.view, this.colors, dim);

    this.ctx.putImageData(this.img, 0, 0);
    if (this.selecting && this.selStart && this.selEnd) {
      var c = this.ctx;
      c.save();
      c.strokeStyle = "#2a78d6"; c.lineWidth = 1.5; c.setLineDash([5, 4]);
      var x0 = Math.min(this.selStart.x, this.selEnd.x), x1 = Math.max(this.selStart.x, this.selEnd.x);
      var y0 = Math.min(this.selStart.y, this.selEnd.y), y1 = Math.max(this.selStart.y, this.selEnd.y);
      c.strokeRect(x0, y0, x1 - x0, y1 - y0);
      c.restore();
    }

    /* The cell-type map, repainted from the same view on every frame.  A
       brushed selection dims here too, which is how a region picked on the
       expression map gets read as cell types: the cells left dark on the left
       are the same cells left dark on the right. */
    if (this.sideBuf) {
      var sv = this._projectSide();
      this.sideBuf.fill(this.surfaceU32);
      /* A type picked out of the side legend wins over the brush here: it is
         the more recent, more explicit instruction, and letting the two
         intersect would leave the reader guessing which one is on. */
      this._paint(this.sideBuf, this.sideW, this.sideH, sv, this.sideColors,
                  this.sideMask || dim);
      this.sideCtx.putImageData(this.sideImg, 0, 0);
    }
    this._lastRenderMs = (global.performance || Date).now() - t0;
  };

  // ---- legend ------------------------------------------------------------
  CnidoAtlas.prototype.renderLegend = function () {
    var self = this, cats = this.activeCategories();
    var body = this.legendBody;
    body.innerHTML = "";
    /* Kept in step from here rather than from each caller: every path that
       changes what the legends say ends up in this function. */
    if (this.sideMap) this.renderSideLegend();

    /* A dataset that publishes its cell types as abbreviations handed the
       viewer a key (opts.typeKey).  Its footnote belongs to the categorical
       list of cell types: a gene ramp, a cluster list or the sample list has
       nothing to expand.  Cleared before the branches below, one of which
       returns early. */
    var key = this.typeKey ? this.typeKey.labels : null;
    var keyOn = !!(key && this.typeKey.note && cats && this.mode === "cell_type");
    if (this.legendFoot) {
      this.legendFoot.textContent = keyOn ? this.typeKey.note : "";
      this.legendFoot.style.display = keyOn ? "" : "none";
    }

    if (!cats) {
      var note = el("div", "cna-note");
      if (this.mode === "gene") {
        note.textContent = this.activeGene
          ? "Expression of " + this.activeGene + " — sequential blue ramp."
          : "Pick a gene to colour cells by expression.";
      } else {
        note.textContent = "Sequential blue ramp: low → high.";
      }
      body.appendChild(note);
      if (this.mode === "gene") body.appendChild(this._rampLegend());
      return;
    }

    var counts = this._categoryCounts();
    var total = this.n;
    /* With a key, the expansion reads in the row beside the name it explains,
       in the slack the name column leaves, rather than as a block to look up
       elsewhere.  A name the key does not cover keeps the row it had. */
    cats.forEach(function (name, i) {
      var row = el("div", "cna-leg-row" + (self.highlightOn && i === self.highlightIdx ? " is-on" : ""));
      var sw = el("span", "cna-sw");
      sw.style.background = self._categoryColor(i);
      row.appendChild(sw);
      var k = key ? key[name] : null;
      row.appendChild(el("span", "cna-leg-name", name));
      if (k && k.brief) {
        // `.has-brief` gives the slack to the expansion instead of to the name
        row.classList.add("has-brief");
        row.appendChild(el("span", "cna-leg-brief", k.brief));
      }
      var pct = total ? (counts[i] / total * 100) : 0;
      row.appendChild(el("span", "cna-leg-n", fmtNum(counts[i]) + "  (" + pct.toFixed(1) + "%)"));
      row.title = k && k.title
        ? name + " — " + k.title + " · click to highlight"
        : name + " — click to highlight";
      row.addEventListener("click", function () {
        self.highlightIdx = i;
        self.highlightOn = true;
        if (self.hlBox) self.hlBox.checked = true;
        self.applyColoring();
        self.renderLegend();
        self.renderComposition();
        self.renderViolin();
      });
      body.appendChild(row);
    });
  };

  /* The legend for the cell-type map.  Same rows, but always the cell types
     (or, for a cluster_only dataset, the clusters they stand in for), because
     that map is always the categorical one however the left map is coloured.
     Clicking a row dims every OTHER type on that map and nothing else: it is
     how a name in the list is turned into a place on the map, and it is kept
     off the expression map and off highlightIdx so the two readings cannot
     contradict each other. */
  CnidoAtlas.prototype.renderSideLegend = function () {
    if (!this.sideLegendBody) return;
    var self = this, body = this.sideLegendBody;
    var clusterOnly = this.manifest.cell_type_mode === "cluster";
    var cats = clusterOnly ? this.manifest.clusters : this.manifest.cell_types;
    var counts = this._categoryCounts(cats, clusterOnly ? 1 : 0);
    var total = this.n;
    body.innerHTML = "";
    cats.forEach(function (name, i) {
      var on = (self.sideFocus === i);
      var row = el("div", "cna-leg-row" + (on ? " is-on" : ""));
      var sw = el("span", "cna-sw");
      sw.style.background = self._categoryColor(i);
      row.appendChild(sw);
      row.appendChild(el("span", "cna-leg-name", name));
      row.appendChild(el("span", "cna-leg-n",
        fmtNum(counts[i]) + "  (" + (total ? counts[i] / total * 100 : 0).toFixed(1) + "%)"));
      row.title = on ? name + " — click to show every type again"
                     : name + " — click to pick this type out on the map";
      row.addEventListener("click", function () { self._setSideFocus(i); });
      body.appendChild(row);
    });
  };

  CnidoAtlas.prototype._setSideFocus = function (i) {
    var n = this.n;
    if (this.sideFocus === i) {
      this.sideFocus = -1;
      this.sideMask = null;
    } else {
      this.sideFocus = i;
      var m = new Uint8Array(n), c = this.cellmeta;
      for (var k = 0; k < n; k++) m[k] = (c[k * 3] === i) ? 1 : 0;
      this.sideMask = m;
    }
    this.renderSideLegend();
    this.render();
  };

  /* `col` names a column of cellmeta.bin (0 = cell type, 1 = cluster) for the
     callers that need a count of a fixed categorical split rather than of
     whatever the current colouring is. */
  CnidoAtlas.prototype._categoryCounts = function (cats, col) {
    cats = cats || this.activeCategories();
    var n = this.n, counts = new Uint32Array(cats.length), i, c;
    if (col === undefined) {
      for (i = 0; i < n; i++) {
        c = this.cellValue(i);
        if (c < counts.length) counts[c]++;
      }
    } else {
      for (i = 0; i < n; i++) {
        c = this.cellmeta[i * 3 + col];
        if (c < counts.length) counts[c]++;
      }
    }
    return counts;
  };

  CnidoAtlas.prototype._rampLegend = function () {
    var wrap = el("div", "cna-ramp");
    var bar = el("div", "cna-ramp-bar");
    bar.style.background = "linear-gradient(90deg," + this._rampHex.join(",") + ")";
    wrap.appendChild(el("span", "cna-ramp-l", "low"));
    wrap.appendChild(bar);
    wrap.appendChild(el("span", "cna-ramp-l", "high"));
    return wrap;
  };

  // ---- composition bar chart --------------------------------------------
  // One bar per group, split by library. Bars sit adjacent so this is the
  // "adjacent" colour form; the 2px surface gap keeps segments legible.
  CnidoAtlas.prototype.renderComposition = function () {
    var self = this, cats = this.activeCategories();
    var body = this.compBody;
    body.innerHTML = "";
    if (!cats) {
      body.appendChild(el("div", "cna-note", "Composition is shown for categorical colourings."));
      return;
    }
    var counts = this._categoryCounts();
    var max = 0;
    for (var i = 0; i < counts.length; i++) if (counts[i] > max) max = counts[i];
    if (!max) { body.appendChild(el("div", "cna-note", "No cells.")); return; }

    cats.forEach(function (name, i) {
      var row = el("div", "cna-bar-row");
      /* 名字一长就被 ellipsis 截掉，悬停要能看到全名（与图例行同一套措辞）。 */
      row.title = name + " — click to highlight";
      row.appendChild(el("span", "cna-bar-name", name));
      var track = el("div", "cna-bar-track");
      var fill = el("div", "cna-bar-fill");
      var w = counts[i] / max * 100;
      fill.style.width = Math.max(w, 0.8) + "%";
      var on = self.highlightOn && i === self.highlightIdx;
      fill.style.background = (on || !self.highlightOn)
        ? self._categoryColor(i) : self._greyHex;
      track.appendChild(fill);
      row.appendChild(track);
      row.appendChild(el("span", "cna-bar-n", fmtNum(counts[i])));
      row.addEventListener("click", function () {
        self.highlightIdx = i; self.highlightOn = true;
        if (self.hlBox) self.hlBox.checked = true;
        self.applyColoring(); self.renderLegend(); self.renderComposition(); self.renderViolin();
      });
      body.appendChild(row);
    });
  };

  // ---- violin plot -------------------------------------------------------
  // 每个细胞类型一行，横向小提琴：横轴是表达量（所有行共用同一把尺，行与行因此
  // 可以直接比），纵向半宽是该细胞类型表达值的核密度（Gaussian KDE，Silverman
  // 带宽），竖线是中位数。
  //
  // 2026-09-23 之前这里画的是「平均表达」横条，而函数名、面板标题、marker 表里
  // 的链接文字全都写着 violin —— 名字承诺了分布，实物只有均值。改成真的 KDE 之后
  // 那个链接才算名副其实。
  //
  // 每把小提琴按自己的峰值归一化（ggplot geom_violin 的默认行为）：宽度表示分布
  // 形状，不表示细胞数。否则细胞多的类型会把细胞少的压成一条线。细胞数由右侧
  // 百分比体现，tooltip 里有中位数和四分位距。

  var SVG_NS = "http://www.w3.org/2000/svg";

  function svgEl(tag, attrs, parent) {
    var e = document.createElementNS(SVG_NS, tag);
    if (attrs) {
      for (var k in attrs) {
        if (Object.prototype.hasOwnProperty.call(attrs, k)) e.setAttribute(k, attrs[k]);
      }
    }
    if (parent) parent.appendChild(e);
    return e;
  }

  function quantileOf(sorted, q) {
    var m = sorted.length;
    if (!m) return 0;
    var pos = (m - 1) * q, lo = Math.floor(pos), hi = Math.ceil(pos);
    if (lo === hi) return sorted[lo];
    return sorted[lo] + (sorted[hi] - sorted[lo]) * (pos - lo);
  }

  // Silverman 经验带宽。零膨胀数据里 IQR 常常是 0（过半细胞都是 0），此时退回
  // 标准差；标准差也是 0 时再退回量程的一小段 —— 带宽为 0 会让密度变成尖峰。
  function bandwidthOf(sorted, span) {
    var m = sorted.length, i;
    if (m < 2) return span > 0 ? span / 20 : 1;
    var mean = 0;
    for (i = 0; i < m; i++) mean += sorted[i];
    mean /= m;
    var sd = 0;
    for (i = 0; i < m; i++) { var d = sorted[i] - mean; sd += d * d; }
    sd = Math.sqrt(sd / (m - 1));
    var a = Math.min(sd, (quantileOf(sorted, 0.75) - quantileOf(sorted, 0.25)) / 1.34);
    if (!(a > 0)) a = sd > 0 ? sd : (span > 0 ? span / 20 : 1);
    return 0.9 * a * Math.pow(m, -0.2);
  }

  // 在 grid 上求高斯核密度（grid 与取值同为「表达量」，单位一致）。
  // 只累加 |z| < 5 的核：更远的贡献小于 3e-6，省掉它是为了压住内层循环。
  function kdeOn(grid, values, h) {
    var out = new Array(grid.length), m = values.length || 1;
    var inv = 1 / (h * Math.sqrt(2 * Math.PI)), k, j, s, z;
    for (k = 0; k < grid.length; k++) {
      s = 0;
      for (j = 0; j < m; j++) {
        z = (grid[k] - values[j]) / h;
        if (z > -5 && z < 5) s += Math.exp(-0.5 * z * z);
      }
      out[k] = s * inv / m;
    }
    return out;
  }

  // 进 KDE 的取值上限。密度估的是形状，抽样不改变结论，但能把 64 x m 的内层
  // 循环钉在可控范围内；中位数/四分位仍然用全部细胞算。
  var KDE_MAX_N = 1500;

  function subsample(arr, cap) {
    var m = arr.length;
    if (m <= cap) return arr;
    var out = new Array(cap), stride = m / cap;
    for (var i = 0; i < cap; i++) out[i] = arr[Math.floor(i * stride)];
    return out;
  }

  function fmtExpr(x) {
    if (!(x > 0)) return "0";
    if (x >= 100) return x.toFixed(0);
    if (x >= 10) return x.toFixed(1);
    return x.toFixed(2);
  }

  // 竖排小提琴的 y 刻度间隔取 1/2/5×10^n，刻度值因此总是整的（0、2、4…），
  // 而不是 1.64 这种间隔算出来的数。
  function niceStep(x) {
    if (!(x > 0)) return 1;
    var e = Math.pow(10, Math.floor(Math.log(x) / Math.LN10));
    var f = x / e;
    return (f <= 1 ? 1 : f <= 2 ? 2 : f <= 5 ? 5 : 10) * e;
  }

  /* 两种版面共用这一份「每组一把小提琴」的数据：统计量、Silverman 带宽、核密度
     曲线、以及曲线的可见区间。版面只负责摆位置，因此 rail（窄栏、每类一行）和
     band（通栏、并排竖排）画出来的是同一批形状，不会各算各的。 */
  CnidoAtlas.prototype._violinModel = function () {
    if (this._vmodel && this._vmodel.src === this.exprValues
        && this._vmodel.gene === this.activeGene) {
      return this._vmodel;
    }
    var m = {
      gene: this.activeGene, src: this.exprValues, empty: false,
      groups: [], grid: [], xmin: 0, xmax: 0, span: 1
    };
    this._vmodel = m;

    var cats = this.manifest.cell_types;
    var n = this.n, v = this.exprValues;
    var byType = cats.map(function () { return []; });
    for (var i = 0; i < n; i++) {
      var c = this.cellmeta[i * 3];
      if (c < byType.length) byType[c].push(v[i]);
    }

    var groups = [];
    for (var k = 0; k < cats.length; k++) {
      var arr = byType[k];
      if (!arr.length) continue;
      var sorted = arr.slice().sort(function (a, b) { return a - b; });
      var expressed = 0, sum = 0;
      for (var j = 0; j < arr.length; j++) { if (arr[j] > 0) expressed++; sum += arr[j]; }
      groups.push({
        name: cats[k], idx: k, n: arr.length,
        mean: sum / arr.length,
        median: quantileOf(sorted, 0.5),
        q1: quantileOf(sorted, 0.25),
        q3: quantileOf(sorted, 0.75),
        pct: expressed / arr.length * 100,
        lo: sorted[0], hi: sorted[sorted.length - 1],
        sample: subsample(arr, KDE_MAX_N)
      });
    }
    groups.sort(function (a, b) { return b.mean - a.mean; });
    groups = groups.slice(0, 14);
    if (!groups.length) { m.empty = true; return m; }

    /* 横轴范围＝所画这些组的真实取值区间，不从 0 起。
       单细胞表达是 log 归一化的，从 0 起只有在真有零的时候才有意义：零膨胀
       基因的最小值本来就是 0（零那一坨会画在左端，正是要讲的事），此时区间
       自然从 0 开始；而像 NV2.6264 这种几乎处处表达的基因，最小值是 3.5，
       从 0 起会让整条轨道有一半是空的，每个小提琴退化成一条横线。 */
    var xmin = Infinity, xmax = -Infinity, gi;
    for (gi = 0; gi < groups.length; gi++) {
      if (groups[gi].lo < xmin) xmin = groups[gi].lo;
      if (groups[gi].hi > xmax) xmax = groups[gi].hi;
    }
    if (!(xmax > 0)) { m.empty = true; return m; }
    // 所有值相同（span 为 0）时退回 0..xmax，免得除零
    if (!(xmax > xmin)) xmin = 0;
    var span = xmax - xmin;

    var GRID = 64, grid = new Array(GRID), t;
    for (t = 0; t < GRID; t++) grid[t] = xmin + span * t / (GRID - 1);

    /* 只画密度还「在」的那一段（>= 峰值 1%，约中位点 ±3 个带宽）。高斯核在
       整条轴上都有一个极小但非零的值，不裁的话路径两端会收拢成贴着中线的
       轮廓，中线基准线也得画满整轨。裁掉之后窄的显窄、宽的显宽，正是要传达
       的差异；基准线的长度也顺带等于这个形状真实的跨度。 */
    var EPS = 0.01;
    groups.forEach(function (g) {
      var h = bandwidthOf(g.sample.slice().sort(function (a, b) { return a - b; }), span);
      var dens = kdeOn(grid, g.sample, h);
      var dmax = 0, k2;
      for (k2 = 0; k2 < GRID; k2++) if (dens[k2] > dmax) dmax = dens[k2];
      if (!(dmax > 0)) dmax = 1;
      var k4, kFirst = -1, kLast = -1;
      for (k4 = 0; k4 < GRID; k4++) {
        if (dens[k4] / dmax >= EPS) { if (kFirst < 0) kFirst = k4; kLast = k4; }
      }
      if (kFirst < 0) { kFirst = 0; kLast = GRID - 1; }
      g.dens = dens; g.dmax = dmax; g.kFirst = kFirst; g.kLast = kLast;
    });

    m.groups = groups; m.grid = grid;
    m.xmin = xmin; m.xmax = xmax; m.span = span;
    return m;
  };

  // ---- 版面 A：窄栏里每类一行、横向小提琴（cell_atlas.php）-----------------
  // viewBox 固定 100 x ROW_H，横向拉伸到轨道宽度（preserveAspectRatio="none"）。
  // 横纵都是线性映射，形状仍然是正确的密度剖面；描边靠 non-scaling-stroke
  // 保持 1px，不然它会被横向拉粗。
  CnidoAtlas.prototype._renderViolinRows = function (m) {
    var self = this, body = this.violBody;
    var groups = m.groups, grid = m.grid, xmin = m.xmin, span = m.span;
    var ROW_H = 26, CY = ROW_H / 2, HALF = 9.5;

    groups.forEach(function (g) {
      var kFirst = g.kFirst, kLast = g.kLast;
      var dens = g.dens, dmax = g.dmax;
      var row = el("div", "cna-v-row");
      row.appendChild(el("span", "cna-v-name", g.name));
      var bx0 = (grid[kFirst] - xmin) / span * 100;
      var bx1 = (grid[kLast] - xmin) / span * 100;

      /* 形状只填色、不描边（ggplot / Seurat 的小提琴也是这样）。描边会在密度
         趋零的地方留一条 1px 的横向实线，读起来像数据的跨度，其实那里什么都
         没有。MIN_W 只是防窄分布细到看不见（viewBox 宽 100 映射到约 400px，
         1 个单位≈4px），**不能**当带宽用：给到 1 个单位时，长尾会被撑成一条
         等宽的扁带，看着像一段真实的高原。取 0.25 足够。 */
      var MIN_W = 0.25;
      var d = [], k3, x, w;
      for (k3 = kFirst; k3 <= kLast; k3++) {
        x = (grid[k3] - xmin) / span * 100;
        w = Math.max(MIN_W, dens[k3] / dmax * HALF);
        d.push((k3 === kFirst ? "M" : "L") + x.toFixed(2) + " " + (CY - w).toFixed(2));
      }
      for (k3 = kLast; k3 >= kFirst; k3--) {
        x = (grid[k3] - xmin) / span * 100;
        w = Math.max(MIN_W, dens[k3] / dmax * HALF);
        d.push("L" + x.toFixed(2) + " " + (CY + w).toFixed(2));
      }
      d.push("Z");

      var svg = svgEl("svg", {
        viewBox: "0 0 100 " + ROW_H,
        preserveAspectRatio: "none",
        class: "cna-v-svg",
        "aria-hidden": "true"
      });
      svg.style.height = ROW_H + "px";
      svgEl("path", { d: "M" + bx0.toFixed(2) + " " + CY +
                         "L" + bx1.toFixed(2) + " " + CY,
                      class: "cna-v-base", "vector-effect": "non-scaling-stroke" }, svg);
      svgEl("path", { d: d.join(" "), fill: self._categoryColor(g.idx),
                      class: "cna-v-body" }, svg);
      var mx = (g.median - xmin) / span * 100;
      svgEl("path", { d: "M" + mx.toFixed(2) + " " + (CY - 5) +
                         "L" + mx.toFixed(2) + " " + (CY + 5),
                      class: "cna-v-med", "vector-effect": "non-scaling-stroke" }, svg);
      row.appendChild(svg);

      row.appendChild(el("span", "cna-v-pct", g.pct.toFixed(0) + "%"));
      row.title = g.name + ": median " + g.median.toFixed(2) +
                  ", IQR " + g.q1.toFixed(2) + "–" + g.q3.toFixed(2) +
                  ", detected in " + g.pct.toFixed(1) + "% of " + g.n + " cells";
      body.appendChild(row);
    });

    // 横轴：刻度用绝对定位的 HTML，和拉伸过的行共用同一套百分比，必然对齐
    // （画成 SVG 的话文字会跟着 preserveAspectRatio="none" 一起被横向拉变形）。
    var axisRow = el("div", "cna-v-row cna-v-axisrow");
    axisRow.appendChild(el("span", "cna-v-name", ""));
    var axis = el("div", "cna-v-axis");
    [0, 0.5, 1].forEach(function (f) {
      var s = el("span", "cna-v-ticklabel", fmtExpr(xmin + span * f));
      s.style.left = (f * 100) + "%";
      if (f === 1) s.style.transform = "translateX(-100%)";
      else if (f > 0) s.style.transform = "translateX(-50%)";
      axis.appendChild(s);
    });
    axisRow.appendChild(axis);
    axisRow.appendChild(el("span", "cna-v-pct", ""));
    body.appendChild(axisRow);

    var legendNote = el("div", "cna-note");
    legendNote.textContent = "Violin: kernel density estimate of log-normalised " +
      "expression; the tick is the median, % is the fraction of cells in that type " +
      "with the gene detected. Each violin is scaled to its own peak, so its width " +
      "shows the shape of the distribution, not the number of cells.";
    body.appendChild(legendNote);
  };

  // ---- 小提琴：入口 ------------------------------------------------------
  CnidoAtlas.prototype.renderViolin = function () {
    var body = this.violBody;
    body.innerHTML = "";
    this.violPlot = null;
    /* 有基因就画，与地图怎么上色无关：小提琴讲的是「这个基因在各细胞类型
       里的分布」，切成 cell type 上色不改这个事实（_violinModel 只按
       cellmeta 分组，从不看 mode）。 */
    if (!this.activeGene || !this.exprValues) {
      this.violTitle.textContent = "Expression by cell type";
      body.appendChild(el("div", "cna-note",
        "Pick a gene to see its distribution per cell type."));
      return;
    }
    this.violTitle.textContent = "Expression of " + this.activeGene + " by cell type";

    var m = this._violinModel();
    if (m.empty) {
      body.appendChild(el("div", "cna-note",
        "This gene is not detected in the current cells."));
      return;
    }
    if (this.violinBand) {
      this.violPlot = el("div", "cna-vplot");
      body.appendChild(this.violPlot);
      body.appendChild(this._violinLegendNote());
      this._drawViolinBand(m);
    } else {
      this._renderViolinRows(m);
    }
  };

  CnidoAtlas.prototype._violinLegendNote = function () {
    var note = el("div", "cna-note");
    note.textContent = "Violin: kernel density estimate of log-normalised "
      + "expression; the tick is the median, % is the fraction of cells in that "
      + "type with the gene detected. The 14 cell types with the highest mean "
      + "expression are shown, each violin scaled to its own peak, so its width "
      + "shows the shape of the distribution, not the number of cells.";
    return note;
  };

  /* 版面 B（gene_exp.php）：通栏、并排的竖排小提琴。
     x 轴是细胞类型（按均值降序，与版面 A 同一批组），y 轴是表达量，所有列共用
     一把尺，因此列与列直接比高低。dens 与版面 A 同源，只是把「表达量在横、
     密度在竖」换成反过来摆。
     这里按真实像素建 SVG（viewBox 就是像素尺寸），所以文字不会被拉伸变形；
     宽度变了要重画，见 _bindEvents 里的 resize。 */
  var VP_PCT = 20, VP_TOP = 20, VP_PLOT_H = 300, VP_NAME = 124, VP_NAME_MAX = 240,
      VP_PAD_L = 58, VP_PAD_R = 14,
      //: 一列少于这个宽度，类型名和检出率就开始互相压；窄屏靠横向滚动保住可读性
      VP_MIN_W = 640;

  // 角色名斜排 45°，在竖直方向占 (文字宽 + 余量) × sin45 —— 于是名字越长，轴下面
  // 那条带子就要越高。写死一个高度会让 gastrodermis_muscle_parietal–circus_plug
  // 这种长名字横穿到下面的说明文字里去。
  function bandForTextWidth(w) {
    return 10 + Math.ceil((w + 6) * Math.SQRT1_2) + 4;
  }

  CnidoAtlas.prototype._drawViolinBand = function (m) {
    var host = this.violPlot;
    if (!host || !m || m.empty) return;
    var avail = Math.round(host.clientWidth);
    if (!(avail > 240)) return;    // 面板隐藏时 clientWidth 是 0，还没排上版
    /* 14 列各有名字和检出率，挤到 20 多像素一列就没法读了（375px 屏上正是这样）。
       所以给一个宽度下限，窄屏改为横向滚动，而不是把图压扁——比少画几组诚实：
       少的组不会说自己是少画的那几组。 */
    var W = Math.max(avail, VP_MIN_W);

    var self = this;
    host.innerHTML = "";

    var plotL = VP_PAD_L, plotR = W - VP_PAD_R, plotW = plotR - plotL;
    var plotT = VP_TOP, plotB = VP_TOP + VP_PLOT_H;
    var H = plotB + VP_NAME;
    var xmin = m.xmin, span = m.span;

    function yOf(v) { return plotB - (v - xmin) / span * VP_PLOT_H; }

    var slot = plotW / m.groups.length;
    var half = Math.min(slot * 0.40, 34);
    var nameEls = [];

    var svg = svgEl("svg", {
      viewBox: "0 0 " + W + " " + H, width: W, height: H,
      class: "cna-vp-svg", role: "img"
    });
    // 行内宽度压过 CSS 的 width:100%：窄屏时这样才能溢出容器去横向滚动
    svg.style.width = W + "px";
    svg.setAttribute("aria-label", "Distribution of " +
      (this.activeGene || "this gene") + " across cell types");

    // y 刻度：步长取 1/2/5×10^n，数字格式与版面 A 的横轴同一套（fmtExpr）
    var step = niceStep(span / 4), ti, tv, y, lab;
    for (ti = Math.ceil(xmin / step); ti <= Math.floor(m.xmax / step); ti++) {
      tv = ti * step;
      y = yOf(tv);
      svgEl("line", { x1: plotL, x2: plotR, y1: y, y2: y, class: "cna-vp-grid" }, svg);
      lab = svgEl("text", { x: plotL - 7, y: y + 4, "text-anchor": "end",
                            class: "cna-vp-tick" }, svg);
      lab.textContent = fmtExpr(tv);
    }
    svgEl("line", { x1: plotL, x2: plotL, y1: plotT, y2: plotB,
                    class: "cna-vp-axis" }, svg);
    var ymid = (plotT + plotB) / 2;
    var ylab = svgEl("text", { x: 12, y: ymid, "text-anchor": "middle",
                               class: "cna-vp-axlab" }, svg);
    ylab.setAttribute("transform", "rotate(-90 12 " + ymid + ")");
    ylab.textContent = "log-normalised expression";

    m.groups.forEach(function (g, i) {
      var xc = plotL + slot * (i + 0.5);
      var grp = svgEl("g", null, svg);
      var gt = svgEl("title", null, grp);
      gt.textContent = g.name + ": median " + g.median.toFixed(2)
        + ", IQR " + g.q1.toFixed(2) + "–" + g.q3.toFixed(2)
        + ", detected in " + g.pct.toFixed(1) + "% of " + g.n + " cells";

      /* 基准线＝这一组自己的取值跨度，与版面 A 的中线基准同一个意思 */
      svgEl("line", { x1: xc, x2: xc, y1: yOf(g.hi), y2: yOf(g.lo),
                      class: "cna-vp-base" }, grp);

      var d = [], k, w;
      for (k = g.kFirst; k <= g.kLast; k++) {
        w = Math.max(0.25, g.dens[k] / g.dmax * half);
        d.push((k === g.kFirst ? "M" : "L") + (xc - w).toFixed(2) + " "
               + yOf(m.grid[k]).toFixed(2));
      }
      for (k = g.kLast; k >= g.kFirst; k--) {
        w = Math.max(0.25, g.dens[k] / g.dmax * half);
        d.push("L" + (xc + w).toFixed(2) + " " + yOf(m.grid[k]).toFixed(2));
      }
      d.push("Z");
      svgEl("path", { d: d.join(" "), fill: self._categoryColor(g.idx),
                      class: "cna-vp-body" }, grp);

      var ym = yOf(g.median), mw = Math.max(6, half * 0.6);
      svgEl("line", { x1: xc - mw, x2: xc + mw, y1: ym, y2: ym,
                      class: "cna-vp-med" }, grp);

      /* 检出率画在列顶：横排的短标签，不会和斜排的类型名打架 */
      var pc = svgEl("text", { x: xc, y: VP_PCT - 6, "text-anchor": "middle",
                               class: "cna-vp-pct" }, grp);
      pc.textContent = g.pct.toFixed(0) + "%";

      var nm = svgEl("text", { x: xc, y: plotB + 10, "text-anchor": "end",
                               class: "cna-vp-name" }, grp);
      nm.setAttribute("transform", "rotate(-45 " + xc + " " + (plotB + 10) + ")");
      nm.textContent = g.name;
      nameEls.push(nm);
    });

    /* 先建成名义高度、量完真实字宽再改总高：名字原地不动，所以只需要改 viewBox。
       量的是浏览器实际排出来的宽度（getBBox），不是按字数估的。 */
    host.appendChild(svg);
    var longest = 0;
    nameEls.forEach(function (t) {
      var w = 0;
      try { w = t.getBBox().width; } catch (e) { w = 0; }
      if (w > longest) longest = w;
    });
    if (!(longest > 0)) return;                 // 量不出来就保持名义高度
    var band = Math.max(VP_NAME, bandForTextWidth(longest));
    if (band > VP_NAME_MAX) {
      // 带子封顶，超出部分截断（全名在 <title> 里，悬停仍看得到）
      band = VP_NAME_MAX;
      var maxW = (VP_NAME_MAX - 14) / Math.SQRT1_2 - 6;
      nameEls.forEach(function (t) {
        var s = t.textContent, w = 0;
        try { w = t.getBBox().width; } catch (e) { return; }
        if (w <= maxW) return;
        var keep = Math.max(3, Math.floor(s.length * maxW / w));
        for (;;) {
          t.textContent = s.slice(0, keep) + "…";
          var w2 = 0;
          try { w2 = t.getBBox().width; } catch (e) { break; }
          if (w2 <= maxW || keep <= 3) break;
          keep--;
        }
      });
    }
    svg.setAttribute("height", plotB + band);
    svg.setAttribute("viewBox", "0 0 " + W + " " + (plotB + band));
  };

  // ---- interaction -------------------------------------------------------
  /* Both maps are one picture under the same gestures: they share the view
     state, so panning or zooming either one moves the two together, which is
     the whole reason for drawing the second one at all.  Every pointer
     position is lifted into the LEFT map's buffer space before anything is
     done with it, so there is one coordinate system to reason about below and
     the zoom-about-the-cursor arithmetic does not need two versions. */
  CnidoAtlas.prototype._bindEvents = function () {
    var self = this, cvs = this.canvas;

    function localPosIn(el, ev) {
      var r = el.getBoundingClientRect();
      return { x: ev.clientX - r.left, y: ev.clientY - r.top };
    }
    function mapOf(el) { return el === self.sideCanvas ? "side" : "main"; }
    /* side-buffer point -> main-buffer point.  On the main map this is the
       identity; on the side map it inverts ox = k*px + (wS - k*wM)/2. */
    function toMain(p, map) {
      if (map !== "side" || !self.view || !self.sideView) return p;
      var k = self.sideView.s / self.view.s;
      return {
        x: (p.x - (self.sideView.w - k * self.view.w) / 2) / k,
        y: (p.y - (self.sideView.h - k * self.view.h) / 2) / k
      };
    }
    function elOf(map) { return map === "side" ? self.sideCanvas : cvs; }

    function onDown(map) {
      return function (ev) {
        var el = elOf(map);
        var p = toMain(localPosIn(el, ev), map);
        if (ev.shiftKey) {
          self.selecting = true; self.selStart = p; self.selEnd = p;
          self._selEl = el;
        } else {
          self.dragging = true; self._dragFrom = p;
          self._dragEl = el;
          el.style.cursor = "grabbing";
        }
      };
    }
    cvs.addEventListener("mousedown", onDown("main"));
    if (this.sideCanvas) {
      this.sideCanvas.addEventListener("mousedown", onDown("side"));
    }

    window.addEventListener("mousemove", function (ev) {
      if (!self.canvas) return;
      /* A drag or a brush keeps reporting against the element that started it,
         because the pointer is allowed to leave that canvas mid-gesture. */
      if (self.selecting) {
        self.selEnd = toMain(localPosIn(self._selEl, ev), mapOf(self._selEl));
        self.render();
        return;
      }
      if (self.dragging) {
        var q = toMain(localPosIn(self._dragEl, ev), mapOf(self._dragEl));
        self.tx += q.x - self._dragFrom.x;
        self.ty += q.y - self._dragFrom.y;
        self._dragFrom = q;
        self.render();
        return;
      }
      // hover: nearest cell within a small radius
      if (ev.target === cvs) {
        self._hover(localPosIn(cvs, ev), "main");
      } else if (self.sideCanvas && ev.target === self.sideCanvas) {
        self._hover(localPosIn(self.sideCanvas, ev), "side");
      }
    });

    window.addEventListener("mouseup", function () {
      if (self.selecting) {
        self.selecting = false;
        self._applySelection();
        self.render();
        self.renderComposition();
      }
      self.dragging = false;
      if (self.canvas) self.canvas.style.cursor = "grab";
      if (self.sideCanvas) self.sideCanvas.style.cursor = "grab";
    });

    function onLeave(tip) {
      return function () {
        tip.style.display = "none";
        self.hoverIdx = -1;
      };
    }
    cvs.addEventListener("mouseleave", onLeave(this.tip));
    if (this.sideCanvas) {
      this.sideCanvas.addEventListener("mouseleave", onLeave(this.sideTip));
    }

    function onWheel(map) {
      return function (ev) {
        ev.preventDefault();
        var p = toMain(localPosIn(elOf(map), ev), map);
        var k = ev.deltaY < 0 ? 1.12 : 1 / 1.12;
        // zoom about the cursor
        self.tx = p.x - (p.x - self.tx) * k;
        self.ty = p.y - (p.y - self.ty) * k;
        self.scale *= k;
        self.render();
      };
    }
    cvs.addEventListener("wheel", onWheel("main"), { passive: false });
    if (this.sideCanvas) {
      this.sideCanvas.addEventListener("wheel", onWheel("side"), { passive: false });
    }

    cvs.addEventListener("dblclick", function () { self.resetView(); });
    if (this.sideCanvas) {
      this.sideCanvas.addEventListener("dblclick", function () { self.resetView(); });
    }
    // 通栏小提琴是按像素宽度建的 SVG，窗口宽度一变就得重画；密度曲线本身在
    // _violinModel() 里缓存着，这里只重排位置，代价很小。防抖是为了不在拖动
    // 窗口边缘时每帧都重建 14 个 path。
    var vTimer = null;
    window.addEventListener("resize", function () {
      if (!self.canvas) return;
      self._resize();
      if (self.violPlot) {
        if (vTimer) clearTimeout(vTimer);
        vTimer = setTimeout(function () {
          self._drawViolinBand(self._vmodel);
        }, 120);
      }
    });

    var resetBtn = el("button", "cna-btn cna-reset", "Reset view");
    resetBtn.addEventListener("click", function () {
      self.resetView();
      self.selected = new Uint8Array(self.n);
      self.render(); self.renderComposition();
    });
    this.controls.appendChild(resetBtn);
  };

  /* `map` is "main" or "side": the tooltip is placed in the map the pointer is
     over, and the hit test runs in that map's own pixels, so the 10px radius
     means the same thing to the reader on either one.  The readout is the same
     on both -- cell type, cluster, library, QC and, when a gene is coloured,
     that cell's value -- which is what lets a region be identified on the
     left and named on the right. */
  CnidoAtlas.prototype._hover = function (p, map) {
    var side = (map === "side");
    var v = side ? this.sideView : this.view;
    var tip = side ? this.sideTip : this.tip;
    if (!v || !tip) return;
    var n = this.n, e = this.embedding;
    var s = v.s, ox = v.ox, oy = v.oy;
    var best = -1, bestD = 100;   // 10px radius
    for (var i = 0; i < n; i++) {
      var px = (e[i * 2] - this._cx) * s + ox;
      var py = (e[i * 2 + 1] - this._cy) * -s + oy;
      var dx = px - p.x, dy = py - p.y;
      var d = dx * dx + dy * dy;
      if (d < bestD) { bestD = d; best = i; }
    }
    if (best < 0) { tip.style.display = "none"; this.hoverIdx = -1; return; }
    this.hoverIdx = best;
    var m = this.manifest;
    var html = "<b>" + m.cell_types[this.cellmeta[best * 3]] + "</b>" +
      "<div>cluster " + m.clusters[this.cellmeta[best * 3 + 1]] +
      " · " + m.samples[this.cellmeta[best * 3 + 2]] + "</div>" +
      "<div>" + fmtNum(this.qc[best * 3]) + " UMI · " +
      fmtNum(this.qc[best * 3 + 1]) + " genes" +
      // print a mitochondrial fraction only when the dataset actually measured
      // one; where it did, the third qc column is a placeholder, and showing it
      // would put a number on screen that no pipeline ever computed
      (this.hasMito() && isFinite(this.qc[best * 3 + 2]) && this.qc[best * 3 + 2] >= 0
        ? " · " + this.qc[best * 3 + 2].toFixed(1) + "% MT" : "") + "</div>";
    if (this.activeGene && this.exprValues) {
      html += "<div>" + this.activeGene + ": " + this.exprValues[best].toFixed(2) + "</div>";
    }
    tip.innerHTML = html;
    tip.style.display = "block";
    tip.style.left = Math.min(p.x + 14, v.w - 190) + "px";
    tip.style.top = Math.max(p.y - 10, 4) + "px";
  };

  /* Both the brush corners are kept in the LEFT map's space (see toMain in
     _bindEvents), so this runs one projection whichever map drew the box. */
  CnidoAtlas.prototype._applySelection = function () {
    if (!this.selStart || !this.selEnd) return;
    var n = this.n, e = this.embedding;
    var v = this.view;
    var s = v.s, ox = v.ox, oy = v.oy;
    var x0 = Math.min(this.selStart.x, this.selEnd.x), x1 = Math.max(this.selStart.x, this.selEnd.x);
    var y0 = Math.min(this.selStart.y, this.selEnd.y), y1 = Math.max(this.selStart.y, this.selEnd.y);
    if (x1 - x0 < 4 && y1 - y0 < 4) { this.selected = new Uint8Array(n); return; }
    for (var i = 0; i < n; i++) {
      var px = (e[i * 2] - this._cx) * s + ox;
      var py = (e[i * 2 + 1] - this._cy) * -s + oy;
      this.selected[i] = (px >= x0 && px <= x1 && py >= y0 && py <= y1) ? 1 : 0;
    }
    this.selStart = this.selEnd = null;
  };

  // ---- public helpers ----------------------------------------------------
  // --- URL state ----------------------------------------------------------
  // A marker row in a PHP table can deep-link straight to "this cell type,
  // coloured by this gene" -- the cross-linking Referee 2 asked for.  Kept on
  // the prototype rather than in the demo page so every embedding of the
  // viewer (Cell Atlas, gene_exp.php, cell_marker.php) gets it for free.
  var MODES = ["cell_type", "cluster", "sample",
               "qc_n_counts", "qc_n_genes", "qc_pct_mt", "gene"];

  CnidoAtlas.prototype.applyUrlState = function (search) {
    var params = new URLSearchParams(
      search !== undefined ? search : global.location.search
    );
    var mode = params.get("mode");
    // A deep link must not be able to select a colouring this dataset has no
    // data for: ?mode=qc_pct_mt on a dataset without a mito gene set would draw
    // a uniform grey map that looks like a result.
    if (mode === "qc_pct_mt" && !this.hasMito()) mode = null;
    if (mode && MODES.indexOf(mode) >= 0) {
      this.mode = mode;
      if (this.modeSelect) this.modeSelect.value = mode;
    }
    var type = params.get("type");
    // highlight first, then the gene: a gene colouring overrides the
    // categorical highlight, but the violin panel still focuses on the type
    if (type) this.highlightCellType(type);
    var gene = params.get("gene");
    if (gene) {
      this.setGene(gene);
    } else {
      if (this.mode === "gene") this._ensureGeneSelected();
      this.applyColoring();
      this.renderLegend();
    }
    return this;
  };

  CnidoAtlas.prototype.urlState = function () {
    var p = new URLSearchParams();
    p.set("dataset", this.datasetId);
    var cats = this.activeCategories();
    if (cats && this.highlightOn) p.set("type", cats[this.highlightIdx]);
    if (this.activeGene) p.set("gene", this.activeGene);
    p.set("mode", this.mode);
    return p.toString();
  };

  CnidoAtlas.prototype.highlightCellType = function (name) {
    var cats = this.manifest.cell_types || [];
    var i = cats.indexOf(name);
    if (i < 0) return false;
    this.mode = "cell_type";
    this.modeSelect.value = "cell_type";
    this.highlightIdx = i;
    this.highlightOn = true;
    if (this.hlBox) this.hlBox.checked = true;
    this.applyColoring();
    this.renderLegend();
    this.renderComposition();
    this.renderViolin();
    return true;
  };

  global.CnidoAtlas = CnidoAtlas;
  global.CnidoAtlas.PALETTE = {
    // The live palette is resolved per-instance from the stylesheet (see
    // _readPalette); what is exported here is the fallback set, for callers
    // that need the hues outside a rendered viewer (e.g. a legend on a PHP
    // page that lists cell types without embedding the canvas).
    categorical: FALLBACK.cat, sequential: FALLBACK.ramp,
    grey: FALLBACK.grey, surface: FALLBACK.surface
  };
})(window);
