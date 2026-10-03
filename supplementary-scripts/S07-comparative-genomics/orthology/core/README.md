# CCO — the CnidoSite Core Ortholog resource

A Cnidaria-specific core single-copy ortholog set, plus the species × orthogroup
presence/absence matrix, built from the same OrthoFinder run the rest of CnidoSite uses.

This answers the reviewer request for "a set of genes that are consistently present as
single-copy orthologs across a defined proportion of the high-quality cnidarian genomes,
together with a species-by-gene presence/absence matrix" — without inventing another BUSCO
lineage dataset.

Live at <https://cnidosite.org/core/> (page source: `index.php`, built from
`index_template.php` by `build.py`).

## What it is

Every orthogroup in the run is scored on two axes over the 60 cnidarian genomes whose BUSCO
`cnidaria_odb12` completeness is ≥90% ("busco90" below):

* **occupancy** — the fraction of genomes with ≥1 gene in the orthogroup
* **single-copy fraction** — the fraction with exactly 1 gene

Tiers are defined on the single-copy fraction. Because single-copy implies present, the
tiers are nested:

| tier | cutoff | orthogroups |
|---|---|---|
| strict | single-copy in ≥90% of the 60 | **14** |
| core | ≥80% | **224** |
| extended | ≥70% | **1017** |

Tiers are used by `score.py`/`build_resource.py` via the literal set name `hq90`, which is
the 60-genome set — the name predates the `busco90` column rename in `species.tsv`.

Three tiers rather than one cutoff because no single number suits every use, and because the
right cutoff is a property of cnidarian genomes rather than of the pipeline. The full
per-orthogroup score table is published so users can re-threshold to anything they like.

## Pipeline

Run in this order from this directory. Inputs come from the OrthoFinder
`Results_Sep14` run (153 proteomes) and from the site's own `busco`/`busco_summary` tables.

| step | script | writes |
|---|---|---|
| 1 | `build_species.py` | `species.tsv` — maps OrthoFinder names to site `abbr1` codes + taxonomy, sets `busco90` |
| 2 | `build_copynumber.py` | `copynumber_full.tsv`, `og_genes.tsv` — the mother table |
| 3 | `score.py` | `og_scores.tsv` — per-orthogroup occupancy/single-copy against 5 species sets |
| 4 | `build_resource.py` | `core_og.tsv`, `core_members.tsv`, `matrix_{presence,copynumber,singlecopy}.tsv` |
| 5 | `build_sequences.py` | `CCO_members.faa`, `supermatrix/{strict,core,extended}.{faa,partitions.txt}` |
| 6 | `build_load.py` | `load_*.tsv`, `schema_core.sql` |
| — | `build_busco_check.py` | the BUSCO comparison (reads the site tables) |
| — | `tree_check.py` | class monophyly of the FastTree trees |

`common.py` holds the header parser shared by steps 2/5.

Then: `pack_downloads.sh` stages `download/`, and `import_core.sh` loads the database
**on the server** (it is not runnable from here).

## Decisions worth knowing

**Copy number counts distinct genes, not transcripts.** Trailing `.tN` is stripped before
counting. Without this a genome with two isoforms of a single-copy gene looks duplicated.
65,239 gene bases carry more than one isoform, so this is not a corner case.

**`busco90`, not `high_quality`.** `busco_summary` already has a `high_quality` column with a
different and much stricter meaning — 15 genomes, all ≥91%. Reusing that name for these 60
genomes would have been a trap for anyone joining the two tables, so the column is named
`busco90` and the page says "BUSCO ≥90%" everywhere rather than "high quality". Verified: there
are 0 genomes with `high_quality=1` below 90% completeness, and 14 of the 15 flagged genomes
are inside these 60. The exception is `PMULT`, which is flagged on the site but is not in the
OrthoFinder run at all (and is the one genome flagged `protein_set=0`); it is therefore absent
from this resource entirely rather than excluded by the 90% cutoff.

**BUSCO numbers are recomputed on the page, not stored,** so they cannot drift from what the
rest of the site shows. Verified against the site for all 146 shared species: 0 disagreements
in `pct_complete`, `n_single`, `n_duplicated`.

**Two proteomes have no BUSCO row** (`AALAT`, `CCRUX` — the two with duplicate headers) and so
cannot be in the 60; both are scored in `all_*` columns but never in `hq90_*`. `PMULT` has a
BUSCO row on the site but is not in the OrthoFinder run at all, so it is absent here; it is
also the one genome the site flags `protein_set=0`, which does not affect this resource.

**Outgroups are the only way to root a matrix built from this resource** and they are not
single-copy in every tier, so `core_og.outgroup_single` is published and the page tells users
to filter on it.

**The page wears the site's chrome, not its own.** `index_template.php` used to open with a
`div.cc-wrap` and a dark gradient hero, which made `/core/` read as a separate microsite next to
busco.php / genefamily.php / phylotree. It now sits in the same shell those pages use —
`#tempatemo_content_wrapper > #templatemo_content > #column`, the `<legend>` banner with
`../images/header.jpg`, an opening `<p class="paleo-intro">` — and the stylesheet follows the
house vocabulary set by `includes/coverage_matrix_view.php` (`.cm-*`): flat surfaces, 1px
`#e2e8f0` borders, 10px radii, `#f1f5f9` table headers, `#2563eb` accent, and no gradients or
drop shadows anywhere. The headline numbers are `.cm-chip`-style pills rather than a stat band.
When adding to the page, keep to that vocabulary rather than inventing a new one.

**The page prints the site footer too.** `Webpage_components.php` holds the lab and institutional
affiliations, the HKUST / GML lab logos, the release stamp and the mapmyvisitors visitor map, and
every CnidoSite page prints it with `<?php include "../Webpage_components.php"; print $footer; ?>`.
This page did not, which is easy to miss: the nav block `build.py` splices stops at
`<div id="tempatemo_content_wrapper">`, so it never carried the footer, and a page that has dropped
it still renders perfectly — just without the site's footer. It is now printed after
`#tempatemo_content_wrapper` closes, exactly as /phylotree/ and /genetree/ do. `include __DIR__ .
'/../Webpage_components.php'` resolves the same way from `/core/` as `"../Webpage_components.php"`
does from `/phylotree/` (same depth); that file uses `__DIR__` internally and `require_once`s
`includes/release.php`, so it is safe from a subdirectory. `build.py` now asserts the include is
present, because the one failure mode here is silent. Verified byte-identical to the block
`/phylotree/` serves (1,835 bytes), with the release stamp expanding to `r1.3 (2026-09-23)` on all
seven tab/tier combinations; the footer's own styling (`.guide`, `#templatemo_footer_wrapper`,
`.section_w920`, `.cleaner`) is already in `/templatemo_style.css`, which the page links.

**Matrix column heads name the gene family, not the orthogroup id.** A 1,017-column matrix
labelled `OG0003068` … is unreadable, so each head shows a short annotation and carries the full
one in a tooltip. Three things this had to get right:

* *Description before name.* `best_name` is filled only for the Pfam-sourced rows and holds the
  internal Pfam identifier — `UQ_con`, `Ank_2`, `7tm_1` — while `best_desc` holds the readable
  text (`SH3 domain`, `IMPORTIN BETA`). The orthogroup table had this backwards and showed the
  worse of the two; both now go through `cc_anno_text()`.
* *`"-"` is not an annotation.* PANTHER gives 9 extended-tier families the literal description
  `-` and no other text. Printed as-is it reads as a missing value, so `cc_anno_text()` treats a
  string with fewer than two alphanumerics as absent and the column falls back to its orthogroup
  id, in grey italic. 935 of 1,017 extended-tier families carry a usable annotation; 82 do not.
* *No comma splitting.* Cutting at the first comma looked free — it removes `, MITOCHONDRIAL`
  and `, MEMBER 3, LIKE-RELATED` — but the same character is a chemical locant, where the tail is
  the meaning: it turned `BETA-1,2-N-ACETYLGLUCOSAMINYLTRANSFERASE II` into `BETA-1` and
  `RNA 2',3'-CYCLIC PHOSPHODIESTERASE` into `RNA 2'`. Plain truncation to 20 characters on a word
  boundary mishandles those too, but at least it looks truncated.

**There is no label that is both wide and narrow, so the matrix offers two header views.** Labels
run to 21 characters. At a 65° slant a column has to be about as wide as its label's horizontal
projection, so the extended tier comes out ~45,300px wide; set bottom-to-top in a 12px column the
same labels need ~12,400px. Slanted is the default because that is the readable one; the caption
on the page says plainly that upright is usually the better view past a few hundred genes, and
the `Gene labels` control switches between them. Widths are emitted as a `<colgroup>` so the
header and all 153 body cells of a column agree without repeating the width 154 times.

Row labels on the matrix are the full scientific name (`Alatina alata`), with the `abbr1` code
moved to the tooltip where the rest of the site uses it as the key. Note `th.tilt` must not
declare `position: relative` — the shared `table.mx th` rule makes the head `position: sticky`,
and sticky is already a positioned value, so it stays the containing block for the absolutely
positioned label. Overriding it silently unsticks the whole header row.

**The matrix is the one table that is not height-capped, and that is why its header no longer
sticks.** Every other table on the page sits in `.cc-scroll` (`max-height: 72vh`). The matrix
cannot: the strict tier alone is 153 rows, about 2,100px, so the cap showed roughly a third of
a tier and made the resource look truncated. It uses `.cc-scroll-mx` instead — full height, and
only the horizontal axis is still a scroll container, which is what keeps the species column
pinned while panning across the genes. The cost is that `table.mx th`'s `position: sticky` needs
a vertically scrolling ancestor and this element is no longer one, so the gene labels scroll away
with the page; their tooltips and the colgroup widths are unaffected. Nothing else changed — the
`:hover` species highlight and the `td.sp` left-sticky both still work.

**A wheel has one delta, so the matrix offers a choice of axis.** The matrix is taller than any
viewport *and* the wide tiers are far wider than one, so the `Wheel` control picks which axis the
wheel drives: `↔ across genes` (default) or `↕ down the page`, with `Shift` always meaning the
other one. The default needs no per-tier adjustment because it degrades on its own — a tier that
fits the width (strict is 852px against a ~1,588px content box) has nothing to pan, so every wheel
event falls straight through and the reader scrolls the page normally. The handler deliberately
leaves an event unconsumed when `scrollLeft` does not move, so the browser's own scroll chaining
takes over at either edge and the reader can never get stuck inside the table. This is verified
under the DOM shim (below) in all four combinations of axis and edge; note the shim has to clamp
`scrollLeft` to `[0, max]` like a browser does, or the chaining branch is unreachable.

## Honest caveats

**The BUSCO and OrthoFinder counts are far apart, and that is expected.** BUSCO finds 125
groups single-copy in ≥90% of the 60 genomes; CCO's strict tier has 14. The two are not
measuring the same thing: BUSCO scores a curated set of hidden Markov models against each
proteome independently, while OrthoFinder clusters 153 proteomes jointly and merges and splits
families. Only 12.5% of BUSCO hits on cnidarian genes land inside a CCO orthogroup, but 47.6%
of CCO member genes carry a BUSCO assignment — the CCO set is enriched for conserved genes,
not disjoint from them. Of the BUSCO groups reaching ≥90% single-copy, 66% have a member in a
CCO orthogroup. This gap is reported on the page rather than hidden.

**The matrix tab used to be mislabelled.** It was called "Presence / absence" but rendered copy
number, so a reviewer checking the presence/absence matrix the request asked for would have seen
`2` and `4` in the cells. It now has three selectable views — presence, copy number, single-copy
only — matching the three published files, with the last two derived in the browser from the same
copy-number payload so they cannot disagree with the downloads. Verified: all three views match
the corresponding published matrix across all 155,601 cells of the extended tier.

**Verifying the browser-built parts.** The matrix table is assembled in JavaScript, so `curl` only
ever sees the `<script>` block and never the DOM. `mx_ext.html` plus a DOM shim run under `node`
is how the header labels, colgroup widths, row labels and the wheel handler were checked. Worth
repeating after any change to `draw()`: extract the IIFE from the served page, stub
`document.getElementById` / `querySelector` / `querySelectorAll`, run it, and regex the captured
`innerHTML`. To exercise the upright header you have to patch `var HDR = 'tilt';` in the extracted
source — setting the `#mxhdr` value does nothing, because `HDR` is only reassigned by the change
listener. Same trick drives `WHEEL`: set the value and then *fire* the element's `change` handler.

Two traps in the extraction itself. A bracket-counting scanner has to understand **regex literals**
as well as string literals: `esc()` contains `.replace(/"/g, '&quot;')`, and a scanner that only
knows about strings reads that quote as the start of one and runs to EOF. Simpler and robust —
take everything from `(function () {` to the last `})();` before the closing `</script>`, which is
unambiguous here because the JSON payloads cannot contain that sequence. And the shim must clamp
`scrollLeft` to `[0, max]` the way a browser does, or the "nothing moved, so let the page have the
event" branch never fires and the wheel looks like it traps the reader at the edges when it does
not.

**Sparse single-copy conservation is real, not a pipeline artifact.** It was checked three
ways: isoform inflation (real but minor — 221 → 224 at the core cutoff after collapsing),
duplicate headers (affects only two non-busco90 species), and genuinely sparse conservation.
The independent BUSCO analysis settles it: **not one** of the 3,203 `cnidaria_odb12` groups is
single-copy in all 60 genomes. Coral genomes carry 20–43% duplicated BUSCOs.

**The trees validate usability, not relationships.** FastTree `-nosupport`, untrimmed, no
model selection, run on all three tiers. Cubozoa, Hydrozoa, Octocorallia and Scyphozoa come out
monophyletic on all three, and the five outgroups are monophyletic on core (130 taxa). Strict
(135 taxa) and extended (144 taxa) additionally recover Myxozoa but on those two the outgroups
do *not* stay together; core is the only tier that has both. Hexacorallia is recovered on none,
and Staurozoa has a single genome so its placement is meaningless. Do not read a phylogeny off
these — the trees are a usability check, which is why the page ranks them as such.

**The supermatrices are starting points.** They reuse OrthoFinder's MAFFT alignments, subset to
one sequence per genome per orthogroup, keep columns where ≥50% of the partition's taxa have a
residue, and drop sequences below 0.5× the orthogroup's median length. The core tier is 130
taxa × 66,357 columns, 224 partitions tiling 1–66,357 exactly, 23.8% gaps. Trim further and
pick a model before publishing a tree from them.

**The gene sets are nested; the genome sets are not.** Strict ⊂ core ⊂ extended holds for the
orthogroups. It does not hold for the genomes in the supermatrices, and the counts are
deliberately published on the page (from `download/dimensions.json`, written by
`pack_downloads.sh`) rather than left for a user to discover:

| tier | genomes | partitions | columns |
|---|---|---|---|
| strict | 135 | 14 | 3,408 |
| core | 130 | 224 | 66,357 |
| extended | 144 | 1,017 | 367,759 |

A genome is admitted to a tier when it is represented in ≥50% of *that tier's* partitions. The
strict tier has only 14 partitions, so its bar is 7 genes against core's 112 — which is why a
genome can clear strict and miss core. Forcing an absolute floor instead is not possible: 112 of
14 partitions is unsatisfiable, so the strict tier would come out empty. Pick a tier for its
gene set and read the table for the genomes that come with it.

**Fixed: the taxon bar used to rise as tiers grew.** `min_genes` was
`int(len(partitions) * 0.5)` against the tier's own size, so the requirement was 112 genes in
core but 508 in extended — and 10 genomes silently vanished from the *most inclusive* tier
(extended had 120 taxa, core 130). It is now
`int(min(len(partitions), REF_TIER_SIZE) * 0.5)` with `REF_TIER_SIZE = 224`, the core tier's
size, giving a 112-gene bar everywhere and `min()` keeping it satisfiable for strict. Strict and
core came out byte-identical, so only extended changed — 120 → 144 taxa.

**`-nosupport` trees are unrooted with a trifurcating root.** That is correct FastTree output,
not a parsing artifact.

## Deployment

The site's document root is `/var/www/html/CnidoSite/` on `<SITE-HOST>`.

```bash
# database (from a staging dir on the server)
rsync load_*.tsv schema_core.sql import_core.sh <SITE-ACCOUNT>@<SITE-HOST>:~/cco_stage/
ssh <SITE-ACCOUNT>@<SITE-HOST> 'cd ~/cco_stage && bash import_core.sh'

# page + downloads
python3 build.py && bash pack_downloads.sh
rsync index.php <SITE-ACCOUNT>@<SITE-HOST>:/var/www/html/CnidoSite/core/
rsync download/ <SITE-ACCOUNT>@<SITE-HOST>:/var/www/html/CnidoSite/core/download/
```

`import_core.sh` builds under a `_new` suffix and swaps with one atomic `RENAME`, refusing the
swap unless the row counts clear a floor. Two bugs it now guards against, both hit on the first
attempt: MySQL refuses a multi-table `RENAME` outright if *any* source table is missing (so a
first deployment never swapped), and the old `q()` helper ended in `|| true`, which hid the
failure of the index `ALTER`s — those are gone, since the schema declares every index inline.

**Truncation guard.** `build_load.py:check_widths()` refuses to emit a load file whose fields
exceed their declared column. MySQL silently truncates over-long values on `LOAD DATA`: a
`VARCHAR(24)` cut `OUT_Corticium_candelabrum` to 24 characters, which broke the
`core_member`→`core_species` join with no error anywhere. Both `abbr` and `abbr1` are now
`VARCHAR(64)`, matching `busco_summary.abbr1`.

Note `local_infile` is OFF by default and does not survive a server restart;
`import_core.sh` sets it rather than relying on it having been set by hand.

## Rebuttal text

> We thank the reviewer for this suggestion, which we agree substantially increases the
> comparative value of CnidoSite. We have added a CnidoSite-specific core ortholog resource
> (CCO) built from the OrthoFinder run already underlying the gene-family pages, rather than
> developing an additional BUSCO lineage dataset.
>
> Orthogroups were scored by the proportion of genomes in which they are present and in which
> they are single-copy, over the 60 cnidarian genomes with BUSCO `cnidaria_odb12` completeness
> ≥90%. We provide three nested tiers — strict (single-copy in ≥90% of these genomes; 14
> orthogroups), core (≥80%; 224) and extended (≥70%; 1017) — together with the complete
> per-orthogroup score table so that users can apply any cutoff. Copy number is counted over
> distinct gene models rather than transcripts.
>
> The resource is published with a species × orthogroup presence/absence matrix (and companion
> copy-number and single-copy matrices) covering all 153 proteomes, downloadable concatenated
> supermatrices for each tier with partition files, and a per-species summary. All are
> browsable and downloadable at https://cnidosite.org/core/.
>
> We note that this analysis also quantifies a limitation of the existing BUSCO resource for
> this taxon: across the same 60 genomes, **none** of the 3,203 `cnidaria_odb12` groups is
> single-copy in all of them, and only 125 reach ≥90%. The CCO tiers are therefore intended to
> complement, not replace, the BUSCO analysis.
