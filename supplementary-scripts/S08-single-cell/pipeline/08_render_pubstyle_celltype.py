#!/usr/bin/env python3
"""
08_render_pubstyle_celltype.py -- draw a cell-type UMAP in the *published*
figure's style, for the as-published section of the atlas page.

Why this exists
---------------
`07_render_umap.py` draws this site's own panels: the viewer's palette, no axes,
a legend that carries cell counts, a footnote.  A reader can tell one of those
from a study's figure at a glance, which is the point -- they sit under a
heading that says the re-analysis is ours.

The as-published section is the other way round.  It shows the source study's
own PNGs, and for several datasets the only panel deposited is a *numbered*
cluster plot: the study published no cell-type figure, so a reader comparing
their clustering with this dataset's cell types has nothing to compare it to.
The page answers that with a panel drawn here (see `$pfCtPub` in
`cell_atlas.php`), and that panel has to sit in the same visual language as the
figures around it -- axes with ticks, a title, a legend to the right -- or it
reads as belonging to the re-analysis above instead.

So this stage draws the *same data* as `07`, in the published layout:

    <dataset>/umap_celltype_pub.png

Not the study's palette, though, and not their colours.  The palette comes from
`web/viewer/cellatlas.css` exactly as `07` reads it, so a cell type keeps its
colour between this panel, the re-analysis panel and the interactive viewer.  A
figure laid out like the study's but coloured like ours is recognisably ours;
one that copied their palette as well would not be, and the whole point of the
footnote it carries is that it must stay recognisable.

Data: `embedding.bin` and `cellmeta.bin`, resolved through `manifest.json`, the
same exported files `07` reads and the viewer draws.  Nothing is read from the
h5ad.

This stage draws for every dataset that has named labels, which is more than the
page shows: the page's gate is a *published-figure* question (`$pfNamed` in
`cell_atlas.php` -- does either deposited panel already name the types?), and
that lives in a PHP table keyed on the figure stem.  Re-deriving it here would
be a second copy of the same table, free to drift from the first.  So this
writes the panel and the page decides; a file no page references is the cost of
not having two answers to one question.  Render, do not draw.

Usage
-----
    python 08_render_pubstyle_celltype.py --dataset NVECT_whole_adult
    python 08_render_pubstyle_celltype.py --all
    python 08_render_pubstyle_celltype.py --all --outdir /tmp/fig-check

The environment's OpenBLAS wants more threads than the process limit allows and
aborts the import; `OPENBLAS_NUM_THREADS=1` is needed here.
"""

from __future__ import annotations

import argparse
import importlib.util
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
ROOT = HERE.parent

#: 7in x 6in at 300dpi is 2100x1800 -- the size the deposited figures are, so
#: the card's two panels are drawn at one size and the browser scales them
#: together rather than one being resampled differently from the other.
FIG_SIZE = (7.0, 6.0)
FIG_DPI = 300

#: Sized against the 2100px canvas, read off `NVECT_WholeOrganism_UMAP_1.png`:
#: its bold title stands about 55px tall and its ticks about 40px, which at
#: 300dpi is 13pt and 10pt.  Unlike `07` these are chosen against the file
#: rather than against the 760px card, because the point of this panel is to
#: look like the file beside it.
TITLE_PT = 17.0
AXLABEL_PT = 13.0
TICK_PT = 11.0
LEGEND_PT = 11.0
DISCLAIM_PT = 8.0

#: The legend column, as a fraction of the canvas: the plot rectangle stops
#: short of the right edge and the names live in the gap.  Measured off the
#: deposited figures, whose legends start around 0.86 of the width.
AXES_RECT = (0.115, 0.125, 0.60, 0.78)
LEGEND_ANCHOR = (1.03, 0.5)
#: Where the legend box's left edge lands, as a fraction of the canvas -- the
#: axes' own right edge plus the anchor offset.  Stated separately because the
#: width a label may use is derived from it, and deriving it from a "should
#: fit" guess is how the first render shipped `cnidocyte_spiro...` cut off.
LEGEND_LEFT_FRAC = AXES_RECT[0] + LEGEND_ANCHOR[0] * AXES_RECT[2]
#: What the marker and the gap after it take, in inches, before a label's first
#: glyph.  Measured off a rendered legend the way `07` measures its own.
LEGEND_HANDLE_IN = 0.34
#: Where the longest label is allowed to end, as a fraction of the canvas --
#: see `avail` in `_figure`.
RIGHT_MARGIN = 0.965

INK = "#0b0b0b"
INK_2 = "#52514e"

OUT_NAME = "umap_celltype_pub.png"

DISCLAIMER = ("CnidoSite render — this dataset's own coordinates and labels, "
              "not a figure the source study published.")


def _stage07():
    """`07_render_umap.py` as a module, for the palette and the export reader.

    Imported by path because the module name starts with a digit and so is not
    an identifier.  Reused rather than restated: the palette must be the
    viewer's one palette, and there must be one implementation of "which cells
    are which type" or the two panels can disagree.
    """
    spec = importlib.util.spec_from_file_location("render_umap",
                                                  HERE / "07_render_umap.py")
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    return mod


def _figure(ds: dict, ru, palette: dict):
    """One published-style cell-type panel."""
    import matplotlib
    matplotlib.use("Agg")
    import matplotlib.pyplot as plt
    from matplotlib.lines import Line2D

    codes = ds["cell_type"]
    labels = ds["cell_types"]
    n_cat = max(int(codes.max()) + 1 if codes.size else 0, len(labels))
    slots = ru.palette_for(n_cat, palette)

    fig = plt.figure(figsize=FIG_SIZE, dpi=FIG_DPI)
    fig.patch.set_facecolor("#ffffff")
    ax = fig.add_axes(AXES_RECT)
    ax.set_facecolor("#ffffff")
    for side in ("top", "right"):
        ax.spines[side].set_visible(False)
    for side in ("left", "bottom"):
        ax.spines[side].set_linewidth(1.2)
        ax.spines[side].set_color(INK)
    ax.tick_params(labelsize=TICK_PT, width=1.2, colors=INK)

    xy = ds["xy"]
    order = __import__("numpy").argsort(codes, kind="stable")
    # Bigger marks than `07`'s: this canvas is 300dpi, where a 1.4pt^2 mark is
    # a third of the width it is at 150dpi, so the same number would draw a
    # visibly thinner cloud than the panel above it.
    mark = 3.0 if ds["n"] > 30000 else (4.5 if ds["n"] > 12000 else 7.0)
    ax.scatter(xy[order, 0], xy[order, 1],
               c=[slots[int(c)] for c in codes[order]],
               s=mark, linewidths=0, marker=".")

    ax.set_title("UMAP by Cell Types", fontsize=TITLE_PT, fontweight="bold",
                 color=INK, pad=18)
    ax.set_xlabel("umap_1", fontsize=AXLABEL_PT, color=INK)
    ax.set_ylabel("umap_2", fontsize=AXLABEL_PT, color=INK)

    # `RIGHT_MARGIN` is where the longest label is allowed to end, as a
    # fraction of the canvas.  0.995 is the physical edge and was the first
    # value; it let `digestive_filaments` -- which measures 1.4in against the
    # 1.49in it was given -- run to within 4px of the border, so the panel
    # looked cropped and any renderer whose font is a hair wider would clip it.
    # The margin is air, not a claim about the measurement: a label that only
    # fits by four pixels does not fit.
    avail = ((RIGHT_MARGIN - LEGEND_LEFT_FRAC) * fig.get_figwidth()
             - LEGEND_HANDLE_IN)

    def fits(line):
        return ru.measure_text(fig, line, fontsize=LEGEND_PT) <= avail

    handles = []
    for i, label in enumerate(labels):
        handles.append(Line2D([], [], marker="o", linestyle="none",
                              markersize=8.0,
                              markerfacecolor=slots[i], markeredgecolor="none",
                              label=ru.wrap_legend_label(label, fits, "_ ")))
    ax.legend(handles=handles, loc="center left", bbox_to_anchor=LEGEND_ANCHOR,
              frameon=False, fontsize=LEGEND_PT, handletextpad=0.4,
              labelspacing=0.55, borderaxespad=0.0)

    # The one thing that must survive the file being taken out of the page.
    # Small and grey in the bottom-left corner: the deposited figures leave
    # that corner empty (their xlabel is centred, their legend is right), so
    # the panel still reads as the study's while being unable to pass for
    # theirs if someone saves the PNG on its own.  Top-left was the first
    # choice and is where the centred title reaches -- the two overprinted.
    fig.text(0.012, 0.010, DISCLAIMER, fontsize=DISCLAIM_PT, color=INK_2,
             va="bottom", ha="left", linespacing=1.35)
    return fig


def eligible(manifest: dict) -> bool:
    """Whether this dataset can have a published-style cell-type panel."""
    return ((manifest.get("cell_type_mode") or "cluster") == "named"
            and bool(manifest.get("cell_types")))


def render_one(dataset_dir: Path, outdir: Path, ru, palette: dict) -> int | None:
    import json
    manifest = json.loads((dataset_dir / "manifest.json").read_text())
    if not eligible(manifest):
        return None
    ds = ru.load_dataset(dataset_dir)
    out = outdir / ds["id"]
    out.mkdir(parents=True, exist_ok=True)
    path = out / OUT_NAME
    fig = _figure(ds, ru, palette)
    ru._save(fig, path)
    return path.stat().st_size


def main() -> int:
    ap = argparse.ArgumentParser(
        description="Render the published-style cell-type UMAP panel.")
    ap.add_argument("--dataset", action="append", default=[])
    ap.add_argument("--all", action="store_true")
    ap.add_argument("--data-dir", default=str(ROOT / "web" / "singlecell_data"))
    ap.add_argument("--outdir", default=None)
    ap.add_argument("--css", default=str(ROOT / "web" / "viewer" / "cellatlas.css"))
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
    ru = _stage07()
    palette = ru.read_palette(Path(args.css))
    for did in ids:
        size = render_one(data_dir / did, outdir, ru, palette)
        if size is None:
            print("  %-20s (not a named-label dataset; skipped)" % did)
        else:
            print("  %-20s %-26s %8d bytes" % (did, OUT_NAME, size))
    return 0


if __name__ == "__main__":
    sys.exit(main())
