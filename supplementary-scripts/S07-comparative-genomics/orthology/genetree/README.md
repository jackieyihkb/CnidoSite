# /genetree/ — the gene-tree viewer

Two things live in this directory and they have drifted apart:

| file | what it is |
|---|---|
| `index.live.php` | a byte copy of the deployed `/var/www/html/CnidoSite/genetree/index.php`, pulled 2026-09-23 and edited here. **Source of truth for the page.** |
| `index_template.php` → `index.php` | what `build.py` produces. It is missing the page header (see below). |

Everything else here is the tree pipeline (`harvest.py` → `run_fasttree.sh` →
`pack_trees.py` → `annotate_dups.py` → `import_tree.sh`); the table and the server
side are described in `REFEREE_RESPONSE_genetree.md`.

## `build.py` does not know about the page header

The deployed page carries a `.gt-hero` block — the OG number, the four facts
(genes / species / tips / outgroup tips), the "← Family page" button, the collapse
notice and the consensus line. **It exists in none of the build inputs.** `grep
gt-hero` over `index_template.php`, `php_orig/phylotree_index.php` and every CSS
file here returns nothing; it was added to the deployed file on the server by a
later session that did not write back.

So `python3 build.py` still exits 0 and still writes an `index.php`, but that file
has no header — deploying it silently deletes the block and the page starts at the
tree controls. It still returns 200 and still looks plausible. Same failure shape
as the missing footer on `/core/`. **Diff against `index.live.php` before building.**

## Change log

**2026-09-23 — the header is no longer a dark card, and the control panel is roomier.**
Two separate pieces of feedback on `?family=OG0000003`.

*The header.* `.gt-hero` shipped with `linear-gradient(135deg,#1e293b,#334155)`,
white text and a `box-shadow`. It is now a white card — `#fff` on a 1px `#e2e8f0`
border, 10px radius, no gradient, no shadow — matching `.fam-hero` on
`genefamily_result.php`, which had the same complaint raised against it and is the
page readers jump to and from via the "Family page" button. The two are copies of
one design and have to change together.

Four colours in that block were chosen **for a dark ground** and had to move with
it: `a.gt-back`'s translucent-white fill and border became a white fill with a
`#cbd5e1` border and `#475569` text; `.gt-status-warn` went `#fcd34d` → `#92400e`
and `.gt-status-ok` `#86efac` → `#15803d` (bright yellow and bright green are
invisible on white); `.gt-status a` went `#bfdbfe` → `#2563eb`. `.gt-consensus`
was `#cbd5e1`/white/`#94a3b8` → `#475569`/`#1e293b`/`#64748b`. A grep for
`linear-gradient` and for bright-on-dark hexes is the check.

*The control panel.* Row spacing came from `.ctl-grid .ctl-row{margin-bottom:10px}`
with a separate `gap:0 32px` for the columns — two different numbers for the two
axes. It is now one `gap:18px 40px` and the row margin is `0`; setting both would
give 28px. Panel padding 16/22 → 24/28, header-to-controls 12 → 20, control gaps
10 → 12, control text 13.5 → 14px, legend pills `3px 11px` → `5px 13px` with a
10px gap. `.ctl-label` went `min-width:76px` → `92px` **plus `white-space:nowrap`**:
"Collapsed tips" is about 90px, so at 76px it broke onto its own line and pushed
that cell's buttons down out of line with the row beside it. `nowrap` means a
label that outgrows the column shifts its row instead of breaking.

`.gt-card` (the tree card) kept its heavier shadow and `#cbd5e1` border on
purpose — the comment above it records that the tree is the page's subject and
should win the eye against the control panel. Only the dark ground was reported.

Rollback copy on the server: `index.php.bak-20260923-hero`.
