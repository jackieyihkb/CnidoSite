#!/usr/bin/env python3
"""
07_render_umap.py -- render the static UMAP figures the cell atlas page shows
beside the interactive viewer.

Why this exists
---------------
The atlas page (`web/php/cell_atlas.php`) has always shown *figures*: for the
deposits that were never re-analysed those are the source study's published
PNGs, and they are labelled as such.  The twelve datasets that were re-analysed
have an interactive viewer but no figure of their own, so for those the page
offers a picture of the study's clustering next to a re-analysis of it, and a
reader has no static view of what we actually produced.

This stage draws that missing picture -- two of them, because they answer
different questions:

    <dataset>/umap_cluster.png     coloured by Leiden cluster, every cluster
                                   labelled with its number at its own centroid
    <dataset>/umap_celltype.png    coloured by cell type, with a legend that
                                   carries the type names and their cell counts
                                   (only for datasets whose labels are names)

Both are drawn from the *exported* files -- `embedding.bin` (float32 x 2 UMAP
coordinates) and `cellmeta.bin` (uint16 x [cell_type, cluster, sample] codes),
resolved through `manifest.json`'s own category tables.  Nothing is read from
the h5ad, so the figures cannot show a different clustering from the one the
viewer draws, and re-running this stage cannot change the data.

Colour
------
The palette is the viewer's, read from `web/viewer/cellatlas.css` rather than
restated here: the stylesheet owns the palette, and a figure whose colours
disagree with the page beside it is worse than no figure.  The first eight slots
are the validated categorical order, assigned in fixed order and never cycled.
Past eight, slots are derived within the same hue families by mixing each base
toward the surface and toward the ink -- distinct from one another, but *not*
pairwise distinguishable in a scatter, which is why neither panel relies on
colour alone: the cluster panel carries a number on every cluster and the
cell-type panel carries a legend with counts.  Datasets here reach 55 clusters,
so the derived rounds are extended until the palette covers the categories; the
viewer caps its own rounds lower because it also has to stay interactive.

Outputs
-------
  <data-dir>/<dataset>/umap_cluster.png     deterministic bytes, ~1350x960
  <data-dir>/<dataset>/umap_celltype.png    named datasets only

Usage
-----
    python 07_render_umap.py --all
    python 07_render_umap.py --dataset SPIST_whole_adult
    python 07_render_umap.py --all --outdir /tmp/fig-check      # no site write
    python 07_render_umap.py --all --check                      # report only
"""

from __future__ import annotations

import argparse
import json
import re
import sys
from pathlib import Path

import numpy as np

PIPELINE_VERSION = "cnidosite-sc-1.0.0"

HERE = Path(__file__).resolve().parent
ROOT = HERE.parent

#: Must match cellatlas.js: these are its FALLBACK values, used when the
#: stylesheet cannot be read.  The live values come from the CSS.
FALLBACK_CAT = ["#2a78d6", "#eb6834", "#1baf7a", "#eda100",
                "#e87ba4", "#008300", "#4a3aa7", "#e34948"]
FALLBACK_SURFACE = "#fcfcfb"
FALLBACK_INK = "#0b0b0b"

#: The viewer's mix rounds, same weights and same order, so a category keeps its
#: colour between the figure and the page: base -> surface, base -> ink, then
#: deeper versions of both.  These four are the viewer's whole derivation, and
#: the first 40 slots here are exactly what it would paint.
MIX_ROUNDS = (("surface", 0.55), ("ink", 0.40),
              ("surface", 0.78), ("ink", 0.68))

#: Rounds past the viewer's, for the category counts it never has to draw at
#: once.  Repeating the four above would recycle colours -- 40 distinct slots
#: and then a repeat -- which is exactly the silent collision this file refuses
#: to make.  These rounds move the *hue* instead of the lightness: pushing the
#: first ladder further toward the ink gives sixteen near-black slots that are
#: distinct as bytes and indistinguishable as marks, which is a different way of
#: failing the same requirement.  Each entry is (hue degrees, toward, weight),
#: so an extra slot is the base hue rotated a step or two and then tinted or
#: shaded enough to keep it apart from the slot it was rotated from.
#: Four plus four rounds is 72 slots, which covers the largest category count in
#: the export (OPATA's 55 clusters); anything beyond that raises.
EXTRA_ROUNDS = ((18, "surface", 0.30), (-18, "ink", 0.30),
                (36, "surface", 0.55), (-36, "ink", 0.45))

FIG_SIZE = (9.0, 6.4)
FIG_DPI = 150
#: The plotting rectangle is identical in both panels on purpose -- same
#: position, same limits, same point size -- so the two can be read against
#: each other point by point.
AXES_RECT = (0.015, 0.055, 0.615, 0.885)
INK_2 = "#52514e"

#: The widest the atlas page ever draws one of these: `.sc-fig-grid` in
#: `web/php/sc_pages.css` caps a card track at 760px, so the browser fits the
#: 1350px PNG into 760 and everything on it shrinks by 0.56.
#:
#: Every text size below is chosen against that number rather than against the
#: canvas.  The first draft picked them against the canvas and shipped a 5.6pt
#: legend: 11.7px of glyph in the file, 6.6px once the card has drawn it, which
#: is the only size a reader ever sees.  `_on_page` states the arithmetic and
#: `test_render_umap.py` pins it to the stylesheet's own cap, so shrinking
#: either the text or the card fails a check instead of quietly going unreadable.
DISPLAY_W = 760
TITLE_PT = 15.0
LEGEND_PT = 9.4
CLUSTER_LABEL_PT = 10.0
NOTE_PT = 10.5

#: Where the legend column starts, as a fraction of the canvas width.  It is the
#: same x for the cell-type panel's legend and the cluster panel's note, so the
#: two panels read as one figure; everything a label may occupy is to its right.
LEGEND_X = 0.645
#: How far the first glyph of a legend label sits to the right of LEGEND_X, in
#: inches: the legend box's own padding, the colour marker and the gap after it
#: all come before the text.  Measured off a rendered figure rather than derived
#: from `markersize`/`handletextpad`, because the box's internal padding is
#: matplotlib's business and is not stated anywhere in this file.  At 150dpi it
#: is 55px of the 1350px canvas -- 6% of the width a label never gets to use,
#: which is exactly the slack a "should fit" estimate spends first.
#: `test_render_umap.py` re-measures it from a live legend, so a matplotlib that
#: lays its legend out differently fails a check rather than clipping a label.
LEGEND_TEXT_X_IN = 0.37
#: The legend's own layout: the marker that carries the colour, its size, and
#: the spacing matplotlib puts around it.  Named rather than inlined because
#: `LEGEND_TEXT_X_IN` is a consequence of them, so a test that wants to
#: re-measure the marker column has to build the same legend this file does.
LEGEND_MARKER_PT = 7.0
LEGEND_KWARGS = dict(frameon=False, fontsize=LEGEND_PT,
                     handletextpad=0.4, labelspacing=0.32, borderaxespad=0.0)


def _on_page(pt: float, fig_size=FIG_SIZE) -> float:
    """How big `pt`-sized text ends up, in CSS pixels, inside a 760px card."""
    return pt * DISPLAY_W / (72.0 * fig_size[0])


def legend_text_width(fig) -> float:
    """How much width a legend label has, in inches, on this figure."""
    return (1.0 - LEGEND_X) * fig.get_figwidth() - LEGEND_TEXT_X_IN


def measure_text(fig, text: str, fontsize: float = LEGEND_PT) -> float:
    """The width `text` draws at, in inches, as this figure would draw it.

    Measuring rather than counting characters, because the count was wrong twice
    over: at a flat 0.55em per character it said `NVECT_whole_adult`'s
    42-character label needed 3.59in of a 3.19in margin when the glyphs come to
    2.92in -- and it ignored the 0.37in the legend's marker column takes, so a
    label that filled the character budget could still overrun the canvas while
    the arithmetic said it fitted.  An estimate has to err safe to be worth
    anything, and safe means wrapping labels that would have fitted; asking the
    renderer has neither failure.  It is deterministic, so the byte-identical
    re-render check still holds.
    """
    probe = fig.text(0.0, 0.0, text, fontsize=fontsize)
    try:
        return probe.get_window_extent().width / fig.dpi
    finally:
        probe.remove()


def legend_entry(fig, label: str, count: int) -> str:
    """The one or two lines the legend shows for `label`, its count included.

    The count is what usually pushes an entry over: see `wrap_legend_label`.
    It rides on the last line, so the wrap is made against the margin less the
    count, or the last line overruns by exactly what the count adds.
    """
    avail = legend_text_width(fig)
    suffix = " (%s)" % f"{count:,}"
    if measure_text(fig, label + suffix) <= avail:
        return label + suffix
    room = avail - measure_text(fig, suffix)
    return wrap_legend_label(
        label, lambda line: measure_text(fig, line) <= room) + suffix


def wrap_legend_label(label: str, fits, seps: str = "_") -> str:
    """Break a legend label across lines rather than off the canvas.

    `NVECT_whole_adult` publishes `gastrodermis_muscle_parietal_circular_prog`,
    which with its cell count on the line overruns the margin: drawn unwrapped
    the shipped PNG read `..._circular_pro` with the count cut off entirely.
    Wrapping loses nothing -- the legend gets taller, and the taller it gets the
    cheaper, because a cell-type legend is already as tall as its type count.

    Breaks fall after a separator, so the parts stay recognisable as the words
    the study published; a label with no separator in reach is hard-broken,
    because text leaving the figure is the worse outcome.  `fits` is the
    caller's test for a single line -- a width measured on the figure the label
    is going onto.  It is a predicate rather than a number so this stays pure
    text and its break rules can be tested against a plain character count.

    `seps` is the set of characters a break may follow.  The default is the
    underscore alone, because that is the separator the exported labels this
    module draws actually use.  `08_render_pubstyle_celltype.py` draws the same
    names but passes `"_ "`, because the labels it meets are already title-cased
    phrases (`Developing cnidocytes`), where breaking only on underscores cuts
    a word in half to avoid a space it was free to use.
    """
    if fits(label):
        return label
    cls = re.escape(seps)
    pat = r"[^%s]*[%s]?" % (cls, cls)
    lines, cur = [], ""
    for part in [p for p in re.findall(pat, label) if p]:
        if cur and not fits(cur + part):
            lines.append(cur)
            cur = part
        else:
            cur += part
        while not fits(cur):               # one token longer than a whole line
            if not fits(cur[:1]):          # not even a single glyph fits
                return label               # then draw it as the study published
            lo, hi = 1, len(cur)
            while lo < hi:                 # longest prefix that fits
                mid = (lo + hi + 1) // 2
                if fits(cur[:mid]):
                    lo = mid
                else:
                    hi = mid - 1
            lines.append(cur[:lo])
            cur = cur[lo:]
    if cur:
        lines.append(cur)
    return "\n".join(lines)


# --- palette ----------------------------------------------------------------

def read_palette(css_path: Path) -> dict:
    """The categorical slots, surface and ink, as the stylesheet defines them.

    Reading the CSS rather than restating the hex here is the decision the
    viewer already makes, for the same reason: a page and a figure that disagree
    about what a cell type looks like is a defect a reader cannot detect.
    """
    base = list(FALLBACK_CAT)
    surface, ink = FALLBACK_SURFACE, FALLBACK_INK
    try:
        text = css_path.read_text()
    except OSError:
        text = ""
    for i in range(len(FALLBACK_CAT)):
        m = re.search(r"--cna-cat-%d\s*:\s*(#[0-9A-Fa-f]{6})" % (i + 1), text)
        if m:
            base[i] = m.group(1)
    m = re.search(r"--cna-surface\s*:\s*(#[0-9A-Fa-f]{6})", text)
    if m:
        surface = m.group(1)
    m = re.search(r"--cna-ink\s*:\s*(#[0-9A-Fa-f]{6})", text)
    if m:
        ink = m.group(1)
    return {"base": base, "surface": surface, "ink": ink}


def _rgb(hex_color: str) -> tuple[int, int, int]:
    h = hex_color.lstrip("#")
    return int(h[0:2], 16), int(h[2:4], 16), int(h[4:6], 16)


def mix_hex(a: str, b: str, w: float) -> str:
    """`a` moved `w` of the way toward `b` -- the viewer's own mixing."""
    ar, ag, ab = _rgb(a)
    br, bg, bb = _rgb(b)
    return "#%02x%02x%02x" % (round(ar + (br - ar) * w),
                              round(ag + (bg - ag) * w),
                              round(ab + (bb - ab) * w))


def rotate_hex(hex_color: str, degrees: float) -> str:
    """The same colour at a different hue, lightness and saturation kept."""
    import colorsys
    r, g, b = (c / 255.0 for c in _rgb(hex_color))
    h, s, v = colorsys.rgb_to_hsv(r, g, b)
    r, g, b = colorsys.hsv_to_rgb((h + degrees / 360.0) % 1.0, s, v)
    return "#%02x%02x%02x" % (round(r * 255), round(g * 255), round(b * 255))


def palette_for(n: int, palette: dict) -> list[str]:
    """At least `n` slots, in fixed order, never cycled.

    Eight slots are the validated categorical order; each further round adds
    eight derived steps -- the viewer's four rounds first, then the hue-rotated
    ones.  Raises rather than cycling: a silent repeat would paint two
    categories the same colour with nothing on the figure to say so.
    """
    base = palette["base"]
    slots = list(base)
    for rnd in list(MIX_ROUNDS) + list(EXTRA_ROUNDS):
        if len(slots) >= n:
            break
        if len(rnd) == 2:
            toward, weight = rnd
            degrees = 0
        else:
            degrees, toward, weight = rnd
        target = palette["surface"] if toward == "surface" else palette["ink"]
        for c in base:
            if degrees:
                c = rotate_hex(c, degrees)
            slots.append(mix_hex(c, target, weight))
    if len(slots) < n:
        # Every category must still get its own colour, so this is a hard stop
        # rather than a wrap-around.
        raise RuntimeError("palette exhausted at %d slots for %d categories"
                           % (len(slots), n))
    return slots


# --- data -------------------------------------------------------------------

def load_dataset(dataset_dir: Path) -> dict:
    """Everything needed to draw one dataset, all of it read from the export."""
    manifest = json.loads((dataset_dir / "manifest.json").read_text())
    n = int(manifest["n_cells"])
    emb = np.fromfile(dataset_dir / manifest["embedding"]["file"], dtype="<f4")
    if emb.size != n * 2:
        raise ValueError("%s: embedding has %d values, expected %d"
                         % (manifest["dataset_id"], emb.size, n * 2))
    layout = list(manifest.get("cellmeta_layout")
                  or ["cell_type_idx", "cluster_idx", "sample_idx"])
    meta = np.fromfile(dataset_dir / "cellmeta.bin", dtype="<u2")
    if meta.size != n * len(layout):
        raise ValueError("%s: cellmeta has %d values, expected %d"
                         % (manifest["dataset_id"], meta.size, n * len(layout)))
    meta = meta.reshape(n, len(layout))
    return {
        "id": manifest["dataset_id"],
        "manifest": manifest,
        "n": n,
        "xy": emb.reshape(n, 2),
        "cell_type": meta[:, layout.index("cell_type_idx")],
        "cluster": meta[:, layout.index("cluster_idx")],
        "cell_types": list(manifest.get("cell_types") or []),
        "clusters": list(manifest.get("clusters") or []),
        "mode": manifest.get("cell_type_mode") or "cluster",
    }


def _figure(ds: dict, codes, labels, kind: str, dpi: int, palette: dict):
    """One panel: `codes` coloured, `labels` naming them."""
    import matplotlib
    matplotlib.use("Agg")
    import matplotlib.pyplot as plt
    from matplotlib.lines import Line2D

    n_cat = max(int(codes.max()) + 1 if codes.size else 0, len(labels))
    slots = palette_for(n_cat, palette)

    fig = plt.figure(figsize=FIG_SIZE, dpi=dpi)
    fig.patch.set_facecolor(palette["surface"])
    ax = fig.add_axes(AXES_RECT)
    ax.set_facecolor(palette["surface"])
    ax.set_xticks([])
    ax.set_yticks([])
    ax.margins(0.04)   # the viewer's own padding, so the cloud sits the same way
    for s in ax.spines.values():
        s.set_visible(False)

    xy = ds["xy"]
    # stable category-ordered draw, so overlapping points keep the same
    # z-order on every render rather than depending on point order
    order = np.argsort(codes, kind="stable")
    # One mark size per dataset, shrinking with cell count only so the densest
    # UMAPs do not turn into a solid block; identical in both panels.
    mark = 1.4 if ds["n"] > 30000 else (2.0 if ds["n"] > 12000 else 3.2)
    ax.scatter(xy[order, 0], xy[order, 1],
               c=[slots[int(c)] for c in codes[order]],
               s=mark, linewidths=0, marker=".")

    ax.set_title("%s — %s (%d)" % (
        ds["id"],
        "cell types" if kind == "celltype" else "Leiden clusters",
        len(labels)), fontsize=TITLE_PT, loc="left")

    if kind == "cluster":
        # Cluster identity cannot ride on colour at 55 categories, so every
        # cluster states its own number at its own centroid.
        import matplotlib.patheffects as pe
        for code, label in enumerate(labels):
            sel = codes == code
            if not sel.any():
                continue
            ax.text(float(np.median(xy[sel, 0])), float(np.median(xy[sel, 1])),
                    str(label), fontsize=CLUSTER_LABEL_PT, ha="center",
                    va="center", color=palette["ink"],
                    path_effects=[pe.withStroke(linewidth=3.0,
                                                foreground=palette["surface"])])
        # The margin the cell-type panel fills with a legend is empty here.
        # Rather than widen this panel and make the two impossible to read
        # against each other, it says what the numbers are -- which is also
        # what a reader of the PNG alone needs.  Wrapped short on purpose: the
        # margin is 3.06in of a 9in canvas, so a longer line would not fit
        # without shrinking the text past the size the card will draw it at.
        fig.text(LEGEND_X, 0.955,
                 "%d Leiden clusters\n"
                 "Each number sits at its\n"
                 "cluster's centroid. Colour\n"
                 "separates neighbours; the\n"
                 "number is the identity, so\n"
                 "the two panels can be read\n"
                 "against each other." % len(labels),
                 fontsize=NOTE_PT, color=INK_2, va="top", ha="left",
                 linespacing=1.5)
    else:
        handles = []
        for i, label in enumerate(labels):
            handles.append(Line2D([], [], marker="o", linestyle="none",
                                  markersize=LEGEND_MARKER_PT,
                                  markerfacecolor=slots[i],
                                  markeredgecolor="none",
                                  label=legend_entry(fig, label,
                                                     int((codes == i).sum()))))
        fig.legend(handles=handles, loc="upper left",
                   bbox_to_anchor=(LEGEND_X, 0.955), **LEGEND_KWARGS)

    fig.text(0.015, 0.018,
             "%s cells · %s · re-analysed for CnidoSite"
             % (f"{ds['n']:,}",
                "labels inherited from the source study's per-cell table"
                if kind == "celltype"
                else "Leiden clusters, not cell types"),
             fontsize=NOTE_PT, color=INK_2)
    return fig


def _save(fig, path: Path) -> int:
    import matplotlib.pyplot as plt
    # No build machine's clock in the bytes: the Software field is a fixed
    # string, for the same reason `05` writes its gzip headers with mtime=0 --
    # re-rendering an unchanged dataset must give an identical file rather than
    # a diff that hides real changes.
    fig.savefig(path, dpi=fig.dpi, format="png",
                metadata={"Software": "cnidosite-sc %s" % PIPELINE_VERSION})
    plt.close(fig)
    return path.stat().st_size


def render_dataset(dataset_dir: Path, outdir: Path, dpi: int,
                   palette: dict) -> list[tuple[str, int]]:
    ds = load_dataset(dataset_dir)
    out = outdir / ds["id"]
    out.mkdir(parents=True, exist_ok=True)
    written = [("umap_cluster.png",
                _save(_figure(ds, ds["cluster"], ds["clusters"], "cluster",
                              dpi, palette),
                      out / "umap_cluster.png"))]
    if ds["mode"] == "named" and ds["cell_types"]:
        written.append(("umap_celltype.png",
                        _save(_figure(ds, ds["cell_type"], ds["cell_types"],
                                      "celltype", dpi, palette),
                              out / "umap_celltype.png")))
    return written


def expected_figures(dataset_dir: Path) -> list[str]:
    """Which figures a dataset should have -- the same rule, without drawing."""
    manifest = json.loads((dataset_dir / "manifest.json").read_text())
    names = ["umap_cluster.png"]
    if (manifest.get("cell_type_mode") or "cluster") == "named" \
            and manifest.get("cell_types"):
        names.append("umap_celltype.png")
    return names


def main() -> int:
    ap = argparse.ArgumentParser(
        description="Render the static UMAP figures for the cell atlas page.")
    ap.add_argument("--dataset", action="append", default=[],
                    help="dataset id; repeatable")
    ap.add_argument("--all", action="store_true",
                    help="every dataset under the data directory")
    ap.add_argument("--data-dir", default=str(ROOT / "web" / "singlecell_data"))
    ap.add_argument("--outdir", default=None,
                    help="write here instead of beside the data")
    ap.add_argument("--css", default=str(ROOT / "web" / "viewer" / "cellatlas.css"))
    ap.add_argument("--dpi", type=int, default=FIG_DPI)
    ap.add_argument("--check", action="store_true",
                    help="report what is missing or stray; write nothing")
    args = ap.parse_args()

    data_dir = Path(args.data_dir)
    outdir = Path(args.outdir) if args.outdir else data_dir
    if not data_dir.is_dir():
        print("no data directory: %s" % data_dir, file=sys.stderr)
        return 1

    ids = args.dataset
    if args.all or not ids:
        ids = sorted(p.name for p in data_dir.iterdir()
                     if (p / "manifest.json").is_file())
    if not ids:
        print("no datasets to render", file=sys.stderr)
        return 1

    if args.check:
        wrong = []
        for did in ids:
            want = expected_figures(data_dir / did)
            for name in ("umap_cluster.png", "umap_celltype.png"):
                p = outdir / did / name
                if name in want and not p.is_file():
                    wrong.append("missing   %s/%s" % (did, name))
                elif name not in want and p.is_file():
                    wrong.append("unwanted  %s/%s" % (did, name))
        for w in wrong:
            print(w)
        if wrong:
            return 1
        print("all %d datasets carry exactly the figures their manifest calls "
              "for" % len(ids))
        return 0

    palette = read_palette(Path(args.css))
    for did in ids:
        for name, size in render_dataset(data_dir / did, outdir, args.dpi,
                                         palette):
            print("  %-20s %-20s %8d bytes" % (did, name, size))
    return 0


if __name__ == "__main__":
    sys.exit(main())
