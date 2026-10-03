# Deploying the single-cell module

Everything the site needs, where each file goes, and how to add a dataset. The
whole module is static files plus five PHP pages — there is no new server
runtime, no daemon, and no build step.

---

## 0. What has been verified, and what has not

Stated plainly, because the difference matters when you are about to run this
against a live database.

**Verified.** `sql/singlecell_schema.sql` and `sql/load_singlecell.sql` were
loaded into a clean MariaDB 11.4.5 server -- and, separately, into one created
from the *older* schema and then migrated -- and produce exactly the expected rows
— 33 map rows, 35 QC rows, 12 atlas rows, 255 cell-type rows, 12750 marker rows
— with no warnings. Re-running both scripts leaves every count unchanged, so the
load is idempotent, and re-running the schema over an existing database is safe.
Re-running the emitted column check passes.

Five bugs were found this way and fixed. All had passed a syntax check and would
have failed on the site:

- the boolean `mito_filtering_available` was written as the Python literal
  `False`, which MariaDB in strict mode rejects for a `TINYINT` column;
- `singlecell_qc` had a foreign key onto `singlecell_atlas`, but 23 of the 35
  datasets have a QC row and no atlas row, so the load aborted on the first one;
- `asset_dir` was computed but missing from the emitted column list, so it was
  silently `NULL` in every row;
- the 04/03/05 stages caught per-dataset exceptions, printed `FAILED`, and still
  exited `0`, so a run in which every dataset failed reported success;
- `singlecell_qc.analysis_status` was `VARCHAR(64)`, which cannot hold the reason
  strings 06 emits (the longest is 82 characters).  A clean database never
  noticed, because it is created from the current schema; an installation
  created from an earlier revision did, and so did the live server on
  2026-09-20: the load aborted with `ERROR 1406 Data too long for column
  'analysis_status' at row 1`.  `singlecell_schema.sql` now carries an
  idempotent `ALTER TABLE ... MODIFY` migration for that column, because
  `CREATE TABLE IF NOT EXISTS` cannot widen a column that already exists --
  which is the trap, and the reason this one only appears on upgrade.

`pipeline/06_aggregate_qc_table.py` now checks the emitted column lists against
the schema and refuses to finish if they disagree, and the three pipeline stages
return a non-zero exit status when a dataset raises. The first of those two
checks is what turned up the `asset_dir` bug; the second means a build script
that cannot fail is no longer possible.

**A sixth defect was found by looking at the output rather than at the SQL.**
The de-novo marker panel matched its patterns anywhere inside a feature name,
and the Hydra atlas names features `g31685.t1|CFAD_DICDI` — transcript ID plus
best non-cnidarian BLAST hit. Unanchored matching named 22 of 42 clusters and
every one of those names was wrong: `CFA` (complement factor) matched the
*Macaca fascicularis* suffix in `RL13A_MACFA` in eight clusters, and `CRY`
(cryptochrome) matched the crystallins `CRYAB`, `CRYST` and the fungus
`CRYNJ`. The panel now requires a token boundary on both sides and at least two
independent hits per cluster; `pipeline/tests/test_marker_panel.py` pins all
twelve observed false positives. On the Hydra atlas that correctly yields zero
names, so the module ships 42 Leiden cluster identities with
`annotation_provenance = 'cluster_only'` rather than fabricated cell types.

**A seventh defect was found on the live site, after the assets were in place.**
`05_export_web.py` escaped every character outside `[A-Za-z0-9._-]` in a gene
name to make a reversible filename, then published that same string as the URL.
`%` is itself a URL escape introducer, so the two decoders disagreed: the atlas
stores `g10034.t1|CEL2A_PIG` as `g10034.t1%7CCEL2A_PIG.bin.gz`, the browser sent
`%7C`, Apache decoded it to `|`, and asked for a file that does not exist.
**684 of the Hydra atlas's 1017 expression files answered 404**, so a third of
that dataset's genes reported "Could not load expression"; `NVECT_gastruloid`
has one such name (`GLWG peptide`) and was affected too, which is why this is not
a Hydra-only trap. Every other dataset was unaffected only because its gene
names happen to be plain identifiers. Fixed by publishing
`quote(safe_name(gene))`, so one decode step lands on the filename that exists;
the files on disk are unchanged. `pipeline/tests/test_export_urls.py` pins the
round trip, that the escape is applied exactly once, and that names needing no
escape are not double-escaped — the last of which is what would 404 every
ordinary dataset. Confirmed on the live server afterwards: the URL the browser
sends for a formerly-404ing gene now returns 200.

**An eighth defect was found by comparing two copies of the export rather than
comparing the data.** `write_expression` wrote each gene file with `gzip.open`,
whose header carries the current time, so re-exporting *unchanged* data rewrote
all 8,686 files with different bytes. An md5 comparison between
`web/singlecell_data/` and the copy inside the bundle returned 1,387 lines of
diff whose payloads were every one of them identical — and that noise is what
had hidden the five files that really had drifted: HVULG's and gastruloid's
`genes.json` and `manifest.json` were still publishing the `%7C` URLs from the
defect above, and `_export_index.json` counts them. In other words the bundle
would have shipped the 404s a second time, and the check that was supposed to
catch it was returning a wall of false positives instead. It costs bandwidth
as well: `rsync --checksum` cannot skip a file whose bytes moved because the
clock moved. The stamp is now `mtime=0`; nothing reads it (the viewer gunzips
with `DecompressionStream`, which ignores the header), and the bytes still
depend on the basename, which gzip embeds as FNAME — so a comparison has to
use the same name on both sides, which the test spells out.
`pipeline/tests/test_export_reproducible.py` pins the zero stamp (the check
that fails if `gzip.open` returns), byte equality across two exports of the
same data, and that the payload still decodes to the cells it was built from.

**A ninth defect is the eighth one again, in a different file.**
`04_cluster_annotate.py` wrote `_annotate_summary.json` as whatever the last
run produced, so re-annotating one dataset — `--dataset NVECT_embryo`, which is
the ordinary way to fix one annotation — replaced a twelve-dataset record with
a one-dataset record. Nothing failed and nothing warned; the directory simply
held twelve per-dataset stats files and a summary naming one of them, and that
only becomes visible when the two are compared. The file is the evidence
bundle's aggregate account of what was annotated and how, so it matters for the
same reason `_export_index.json` did. It is now merged by `dataset_id`, exactly
as `05_export_web.py` merges the index: entries for the datasets in this run are
refreshed, the rest are carried over. `pipeline/tests/test_summary_merge.py`
pins the merge — and, because a mutation test showed the function-level checks
would not catch the overwrite coming back in `main()`, it also reads the call
site, which it says out loud rather than implying the coverage is behavioural.

**The same defect was in `03_qc.py` as well, and there it had already fired.**
`03_qc.py` wrote `_qc_summary.json` the same way, so a one-dataset QC re-run
shrank that summary too — and when the work tree was audited on 2026-09-20,
`data/qc/_qc_summary.json` held `NVECT_embryo` **alone**: eleven entries had
been overwritten by a single re-run, with no error to show for it. The bundle's
copy still had all twelve only because `evidence/assemble_summaries.py` had
been run inside the bundle, i.e. the repair tool was masking the defect it
exists to repair. Both stages now call one helper, `pipeline/lib/summary.py`
(so the fix cannot be applied to one file and forgotten in the other, which is
what happened here), and the work tree's summary was rebuilt from the twelve
per-dataset records. `test_summary_merge.py` checks the call site in *both*
stages. That check reads source with comments stripped: the first version
failed on the comment in `03_qc.py` that quotes the code being banned, which is
a check reading prose as code.

**The shipped evidence was stale, and in a way that broke a property the bundle
advertises.** The same audit compared every file under `bundle/evidence/` with
the work tree. Nine of the twelve `*.qc_stats.json` were from a run that
predated the `mad_outlier` fix (defect #7), so for four datasets the per-criterion
counts no longer summed to the number of cells actually removed — the invariant
§4 of the bundle README states is *not* satisfied by the evidence shipped
beside it:

    NVECT_gastrula    criteria sum 5821, actually removed 5847
    NVECT_neoplasm    criteria sum  198, actually removed  222
    NVECT_tentacle    criteria sum 1353, actually removed 1370
    OPATA_whole_adult criteria sum 7917, actually removed 7929

(each short by the `mad_outlier` delta: 26 / 24 / 17 / 12). The other five
differed only in `processed_utc` / `runtime_sec`, and the AMILL record also
carried the older library label — see below. All twelve were refreshed from
`data/qc/`, the two combined summaries regenerated from the per-dataset records,
and the invariant re-asserted: 0 violations in both trees.

These counts are not invisible, so it is worth saying what was and was not
affected. The viewer *does* display them: each exported `manifest.json` carries
`qc_summary.cells_removed_by_criterion`, and `cellatlas.js` prints it as "Cells
removed by". That is where the staleness would have shown — but the exported
manifests, live ones included, were already post-fix, and all twelve satisfy the
invariant (verified against `/var/www/html/CnidoSite`, byte for byte). So the
package's *evidence* contradicted the package's own exported assets and the live
site: the four datasets above read correctly on cnidosite.org while the
`qc_stats.json` shipped beside them did not add up. The DB is unaffected either
way — `cells_removed_by_criterion` is not among the columns
`sql/load_singlecell.sql` loads — and the `annotate_stats`, `markers`,
`composition` and `auto_annotation` files beside them were already current.

**One label difference, kept as it is (decided 2026-09-20).** AMILL's second
library is
`Amil02` in the deposited readme (`cDNA Library` column of
`GSE289546_Readme_ClickTag_demultiplexing.xlsx`, whose clicktag libraries are
`ctAmil01` / `ctAmil02`) and `Amil02_ACM` in the deposit's per-cell table, whose
cell ids read `Amil02_ACM_<16bp barcode>-1`. That table is the only per-cell
file this deposit has, so it is where the loader reads the barcodes from, and it
is what the current QC run reports:

    data/qc/AMILL_whole_adult.qc.h5ad     (2026-09-20 10:19)  ['Amil01', 'Amil02_ACM']
    data/annotated/AMILL_whole_adult.annotated.h5ad
                                          (2026-09-18 20:48)  ['Amil01', 'Amil02']

The exported `samples` list comes from the annotated object, not from
`qc_stats.json` (`n_samples` prefers the stats file; `samples` is the AnnData's
own categories), which is why the site says `Amil02`: the annotated object
predates the QC re-run. **No number moves** — both runs have 25,164 cells, two
libraries, the same 12,839 / 12,325 split, and AMILL is the only dataset where
the two disagree. So this is a stale *label* in a derived artifact, not a wrong
count, and closing it means re-running `04` and `05` for AMILL and reloading its
`singlecell_qc` row — a live write.

**The decision is to leave both spellings.** Each one is right where it stands:
the site says what the deposit's readme says, the QC record says what the
deposit's barcodes say. Unifying them costs a live write (relabel the annotated
object's `obs["sample"]`, re-export, re-upload three files — or re-run
`04`+`05`, which would redo Harmony/Leiden/UMAP for a label) and buys nothing a
reader can act on, since no count differs. Closing it the cheap way is also the
dishonest way: it would mean hand-editing the categories of a released derived
object, so that file would no longer be the output of the run that produced it.
Left as it is, and recorded here, because a reader who notices the two spellings
should find the explanation next to them. Reopen it if the deposit ever
publishes a corrected readme, or if a sample-name-keyed join against AMILL's
per-cell table is ever needed — that join is the one thing the two spellings
would break.

**The QC stage named a plan after a result (fixed 2026-09-20).** In
`data/qc/*.qc_stats.json` — and so in the `bundle/evidence/*.qc_stats.json`
refreshed from them — `integration_method` read `Harmony` /
`Harmony (per dataset)` for **all twelve** datasets, including the five that
have a single library. That value is what the QC stage was *told* to use; this
stage never integrates, so it cannot observe one. `04_cluster_annotate.py`
performs the integration and records what happened, and for those five it says
`none (single library)`. The readers that mattered already preferred the
observation — `05_export_web.py` loads the QC stats and lets the annotate stats
overwrite them, and `06_aggregate_qc_table.py` does the same — so the exported
`manifest.json` (live included), `referee-response/qc_dataset_table.tsv` and the
`singlecell_qc.integration_method` column were all correct. The fault was that
one file, read on its own, could not be told from a result.
The field is now `integration_declared` in `03_qc.py` and in the twelve records,
matching `doublet_method`'s arrangement rather than contradicting it: there the
run overwrites the declared value with what actually ran, which is why it still
ends in `_method`. `06` still uses the declaration for a dataset the annotation
stage has not reached, prefixed `declared: ` so it cannot be misread as an
observation. `pipeline/tests/test_integration_record.py` (16 checks) pins both
field names, both readers' precedence, and the five datasets where declared and
observed genuinely differ. The records were rewritten in place, key by key,
rather than by re-running QC: a re-run would have regenerated the h5ad objects
to change a string that is not in them. Verified afterwards by re-running
`06_aggregate_qc_table.py` (its `qc_dataset_table.tsv` and `load_singlecell.sql`
are byte-identical modulo the generation timestamp) and by re-exporting five
datasets, single- and multi-library, and diffing each against the published
export — every data file identical, the manifest differing only in
`exported_utc` and in the two fields named below.

**Five live manifests publish a superseded annotation caveat (found and fixed
2026-09-20).** The viewer prints `annotation_note` as
"Annotation caveat" (`web/viewer/cellatlas.js:556`). For the five Nematostella
cluster-only datasets the published `manifest.json` carries the *earlier*
wording:

    published, live:  ... (0 of 1150 ranked features are composite 'id|homology-transfer' names)
    current record:   ... (0 of 1150 ranked features match any of the 12 panel
                       patterns, 0 are composite 'id|homology-transfer' names;
                       no ranked feature matches any panel pattern, so this
                       deposit keys features by gene model ID rather than gene
                       symbol and the panel cannot name a cluster from it)

The first reads as "the features are fine, the evidence is thin", which is the
misattribution the rewrite existed to remove. The cause is order of operations,
not a bad copy: those five were re-annotated at 02:55–03:18 on 2026-09-20, which
is what produced the new note, and they were never re-exported; the other seven
were exported afterwards. So the same dataset now reads two ways on the live
site — the atlas page takes `cell_type_source` from the database (new wording,
verified against `singlecell_atlas`), the viewer takes `annotation_note` from
the manifest (old wording, checked live against the file). **Nothing else in
those five is stale**, which was measured rather than assumed: re-exporting all
five into `/tmp/export-check2` and diffing against `web/singlecell_data/` left
`composition.json`, `genes.json`, `markers.json`, `cellmeta.bin`,
`embedding.bin` and `qc.bin` byte-identical, and inside `manifest.json` only
`annotation_note` and `qc_summary.processed_utc` move — the re-annotation
reproduced the same clusters, the same UMAP and the same expression payloads.
The fix was five `manifest.json` uploads (2.8–3.4 KB each), and it also settles
`processed_utc`, which reported the pre-re-annotation time.

**What was uploaded, and how it was checked.** The five live files were backed
up first — `/home/jackie/backups-20260920/manifests-before-note-fix/`, holding
the five files under their old names, an `MD5SUMS` verified in place
(`md5sum -c` from inside the directory), and a `README` recording the cause and
the one-line restore command. The server-side md5s were then read before the
upload and matched what the web server was serving, so the docroot had not
drifted since the finding. The five fresh files were `scp`-ed over the live
ones and checked three ways: server-side `md5sum` == the fresh files, the
`https://cnidosite.org/singlecell_data/<dataset>/manifest.json` bytes == the
fresh files, and a parsed comparison of every served manifest against its fresh
counterpart (`identical=True` for all five) confirming the rewritten caveat is
what the viewer now fetches while `integration_method` and
`annotation_provenance` are unchanged. The invariant the viewer renders as
"Cells removed by" was re-asserted on the served files (8386/0/5847/222/1370 —
`sum(cells_removed_by_criterion) == n_cells_raw - n_cells_after_cell_qc` for each),
and the viewer and `cell_atlas.php` still return 200. `web/singlecell_data/` and
`bundle/A-interactive-module/web-root/singlecell_data/` were then given the same
five files, and a twelve-dataset comparison confirmed every manifest is now
byte-identical across local, bundle and live — the three trees agree again,
which is the state to keep.

**The 96 MB re-export was deliberately not done.** The `.bin.gz` timestamp
paragraph below stands as written: the payloads are right and only the gzip
header's mtime is stale, so re-uploading 8,686 files to change a field the
viewer ignores would buy reproducibility of the *tree* at the cost of a large
blind write. It stays on the list as one deliberate action, not as a defect.

**The published `.bin.gz` files still carry a real gzip timestamp** — the eighth
defect below is fixed in the code but was never propagated to the shipped
assets, which were exported at 02:45–04:14 on 2026-09-20, before `mtime=0`
landed. Sampling the tree shows live mtimes (`1789872336` = 02:45:36, matching
NVECT_2month's `exported_utc`), and re-exporting NVECT_2month changed all 812 of
its expression files with **every payload identical** and none differing. So the
viewer is unaffected (it ignores the header; `DecompressionStream` reads the
deflate stream) and no data is wrong, but the byte comparison that the fix was
for will report 8,686 false differences until the export is re-run and uploaded
(96 MB), which is why it is written down here instead of done.

**The `_export_index.json` published the build machine's own path (found
2026-09-20, fixed 2026-09-21).** `singlecell_data/_export_index.json` is fetched
by `web/viewer/index.html:129` and is public; every one of its twelve records
used to carry

    "dir": "/mnt/sda/jackie/cnidaria/codex/singlecell/pipeline/../web/singlecell_data/<dataset>"

which was `dest` straight out of `05_export_web.py`: `os.path.join(outdir,
dataset_id)` with `outdir` defaulting to the unresolved `here / ".." / "web" /
"singlecell_data"`. Nothing broke — the page reads only `dataset_id` from the
index and builds its own URLs — but the site handed out the internal directory
layout and the build account's name to anyone who fetched that one file.

Fixed by writing the served path instead: `published_dir()` returns
`/singlecell_data/<dataset>/`, the same shape the database's `asset_dir` already
uses, so the index and the database now agree (they previously contradicted each
other, and a reader trusting either one alone would be wrong about one of them).
`published_dir()` deliberately does not derive from `--outdir` — the index
describes the deployed site, and a tree staged into a temp directory is still
destined for that path. `test_export_urls.py` pins the value, pins that no entry
carries a filesystem path, and pins that a reindex agrees with what a real export
wrote (mutation-tested: restoring the absolute path fails three checks).

The regeneration avoided a re-export, which was the whole difficulty: `05 --all`
into `web/` would rewrite all 8,686 `.bin.gz`, i.e. it would silently perform the
96 MB change that was declined. `05 --reindex` walks the existing tree instead —
`n_cells` from `manifest.json`, `n_genes_exported` from `genes.json`, `bytes`
from `os.walk` — which is exactly what `export()` computes at the end of a run,
so the two agree field for field (that equivalence is one of the checks). It is
also the repair path for a wrong index, so it does not read the old one.

Diff of the deployed index: **12 entries, 48 changed lines, every one of them a
`dir` or a `bytes` line** — nothing else moved, and the file's formatting and
missing trailing newline are as they were. `bytes` rose for every dataset
because the walked size now includes the `umap_*.png` figures uploaded in the
same session (e.g. NVECT_gastruloid +967,134 = its two panels exactly).
Backup: `/home/jackie/backups-20260921/export-index-before/`.

**Verified, one layer short of the live server.** No PHP build in the
development environment has the `mysqli` extension, so the pages cannot be
rendered the way the site renders them. To close that gap,
`pipeline/tests/php/mysqli_shim.php` stands in for the extension and forwards
every statement to the MariaDB CLI. `pipeline/tests/test_php_render.py` then
loads the schema, the data and a fixture of the site's own `singlecell` and
`abbr` tables into a clean database and renders all eight page/query
combinations, asserting on the HTML: no PHP diagnostic, exit 0, the content the
page is supposed to show, and — for the atlas — the claim it must *not* make.
The schema, the SQL, the data and the HTML are all real; the driver is the only
mocked layer. The harness was itself mutation-tested: collapsing the three-way
annotation provenance in `cell_atlas.php` back to two ways makes it fail, so it
is not passing vacuously. (That expression is a nested ternary rather than a
`match` for the reason in section 0.2 — the three arms are each asserted.)

The four site-integrated pages at the repository root are verified the same way,
by `pipeline/tests/test_php_render_site.py`. Rendering them found four things
that linting and reading could not:

- the site's `abbr` table has an `abbr1` column (the five-letter species code)
  that our own tables never needed, but the site pages join on it;
- `singlecell` uses CamelCase column names — `Species`, `Emb`, `Stage`,
  `CellNumber`, `Project` — which is visible only from the site pages' SQL;
- `cnido_state()` returns a `<key>__from` entry recording whether each value
  came from GET, POST or the default, and the pages test it to tell a deep link
  from a default. That is the mechanism behind Referee 2 major 1;
- `<abbr1>_cellmarker` is what makes a species "in" the atlas at all, and its
  `tissue_dev` values must not contain a slash, because `cell_atlas.php` builds
  the image path from them.

Two of the checks are mutation-tested: breaking the md5 comparison that detects
identical UMAPs, or the `$namedOnly` test that distinguishes "Cluster 1" from a
real cell-type name, makes the suite fail. Both branches are the substance of
Referee 3 point 10g.

The site stubs under `pipeline/tests/php/site_stubs/` are reimplementations
built from the call sites, not the site's own code. A pass means the pages'
branches work; it does not mean the stubs match the site's helpers. Nothing
under `site_stubs/` is ever deployed.

What remains untested is the driver below the shim, the web server's error
handling, and the real tables in place of the fixtures. Please open the pages
once after deploying, as in section 2.

### 0.1 Verified on the live server (2026-09-18)

The gap above was later closed by reading the production host directly — no files
were written. All four of the set B pages (`cell_atlas.php`, `cell_marker.php`,
`gene_exp.php`, `sn_data.php`) were requested over the real stack — Apache, PHP
7.4.33, the genuine `mysqlnd` driver and the live database — and each returned
**HTTP 200 with zero PHP diagnostics**. That is the layer the shim cannot reach,
and it confirms set B runs as installed. It does **not** mean the pages have been
read by a human; see section 2.

### 0.1.1 The dataset refresh actually deployed (2026-09-20)

The 2026-09-18 deploy carried the module's *code* but only two datasets' worth of
*data*: `singlecell_data/` held `AMILL_whole_adult` and `HVULG_siebert_atlas`
alone (2,010 files), and the tables matched that set row for row. The other ten
re-analysed datasets — including the Stylophora whole-adult re-run — existed only
in the development tree, so "the module is deployed" was true and said nothing
about what the site could show. **No PHP file changed on either date**; the pages
are dataset-driven, so this refresh was tables and assets only.

What was done, in this order (assets first, because a file the database does not
yet reference is invisible, whereas the reverse shows broken pages):

1. `mysqldump` of the five `singlecell_*` tables to
   `/home/jackie/backups-20260920/pre-reload-tables.sql`, rehearsed by loading it
   into a scratch database and confirming it restores 2 / 33 / 69 / 3450 / 34.
2. `rsync` of `singlecell_data/` — 6,696 new files, 0 deletions, ~79 MB.
3. `ALTER TABLE singlecell_qc MODIFY analysis_status VARCHAR(255)` — the
   migration described in section 0, without which the load aborts.
4. `mysql cnidaria < sql/load_singlecell.sql`.

Verified afterwards on the host: tables at 12 / 33 / 255 / 12750 / 35; 8,686
files and 12 dataset directories under `singlecell_data/`, spot-checked
byte-identical against the development tree; ten page/query combinations
including `?dataset=SPIST_whole_adult`, `?species=SPIST` and
`?dataset=HVULG_siebert_atlas` all HTTP 200 with zero PHP diagnostics; the
default landing dataset is `NVECT_gastruloid`; site index row 9 — the
aggregates deposit PRJNA1327231 — resolves to `NVECT_gastruloid`, which is that
same deposit processed, the duplicate `NVECT_aggregate` entry that used to hold
the row having been dropped (which left row 9 unmapped — the deposit had no
atlas row, so the index pointed at nothing); and the URL the browser sends for a
formerly-404ing atlas gene returns 200.

**Rollback**: `mysql cnidaria < /home/jackie/backups-20260920/pre-reload-tables.sql`
restores the five tables to their 2026-09-18 contents. The uploaded assets are
additive, so they need no rollback.

### 0.1.2 The published figures folded into the re-analysis (2026-09-23)

**What changed, and why.** The module had two halves that did not meet: fourteen
re-analysed datasets in the interactive section, and the published
(`pub:<ABBR>:<tissue>`) figures listed beside them as static entries of their
own. A reader arriving from a published figure saw a picture and had nothing
telling them the same deposit had since been re-run.

The fold-in is one column — `singlecell_atlas.published_figure` — naming the
published figure a re-analysis answers. Where it is set, the figure's page
resolves to the re-analysis instead of a static page, and the published panels
are rendered on that same page. Eight of the fourteen atlas rows now carry one.
`pub:NVECT:WholeOrganism` and `pub:OARBU:SymbioticState` deliberately carry none:
neither has been re-run, so both keep the published page they always had, and
`?dataset=pub:OARBU:SymbioticState` must go on rendering a static page with no
viewer. That last point is an invariant, not an observation — it is the thing a
careless fallback breaks.

**The migration is not optional.** Production had no `published_figure` column at
all. `singlecell_schema.sql` therefore carries a guarded `ALTER TABLE` (an
`information_schema` count, then `PREPARE`/`EXECUTE`), and it must run before — or
with — the page patch. Without it the pages still return HTTP 200 and simply
never fold: the failure mode that looks like success.

**Two datasets added: `AMURI_regen` and `NVECT_nervous`.** Atlas 12 → 14 rows and
`singlecell_data/` 12 → 14 directories; both arrived 09-23 08:52, 1,707 files
between them (the rsync reported 1,711 created, 0 deleted — the four extra are
files that also moved within the datasets already there). Both are twinned
figures, which is what raised the `published_figure` count to 8. The tree is now
125 MB.

**Eight files changed.** `sc_common.php` (the fold-in itself: `sc_cell_markers()`
now resolves a published entry to its re-analysis, plus the rank fix below),
`cell_atlas.php` (resolves a `pub:` id to its twin, keeping `$pubEntry` assigned
on every branch so the static fallback still works), `cell_marker.php` (the
"this dataset was not re-analysed" claim is now twin-aware in *both* places it
appeared — the second copy was reached for `pub:` ids because `$gmap` is forced
empty there), `gene_exp.php` (a `pub:` id resolves to its twin, so a Twinned
figure can plot), `sn_data.php` (11px → 13px), and the two presentation files
`sc_pages.css` and `viewer/index.html`.
`viewer/cellatlas.js` was **not** written in that pass — it was byte-identical to
live (`c899c3af…`), and re-uploading it would only risk the drift described
above. It *was* written later the same day, with `viewer/cellatlas.css`, for the
layout change below; both are cache-busted by `filemtime` in `sc_common.php` and
`gene_exp.php`, so a new copy invalidates the old one without any extra step.

**The map and the expression panel became one row (2026-09-23, later).** The
per-cell-type expression panel used to be the third column of the row below the
map, where it was the narrowest of three and ran past the bottom of the row it
shared with the legend — 14 groups against a 560px cap, so the reader scrolled
it. Meanwhile the map ran a long way empty to its right, because an embedding is
square-ish and the row is not. They are now one two-column row: map left,
expression right, both 736px tall on a 1600px window, with the whole violin
panel — axis and explanatory note included — visible without scrolling.
Two things this needed:

- `_syncControls()` toggles `cna-main-solo` on the wrapper, because with no gene
  chosen `renderViolin()` has only a heading and one line of prose to show, and
  that is not worth a 360px column: the map takes the whole row back. The toggle
  lives there rather than in `renderViolin()` because `_syncControls()` runs on
  every colouring change, including the ones driven by the URL.
- the "colour by a gene" sentence moved to the map's own hint line, since the
  rail that carried it is hidden in exactly the mode where a reader needs to be
  told the option exists.

**Two hazards, both live-only.**

1. **The rsync must not use `--delete`.** Live carries 12 `gene_ids.json`
   sidecars and a 7.7 MB `_gene_ids.tsv` that no script in this tree can
   regenerate — they come from the server-side gene-ID work, not from any
   pipeline script here. `--delete` would remove all 13 and silently break the
   display layer that `includes/sc_gene_ids.php` reads. The deploy ran
   `rsync -rlptD --no-owner --no-group` and reported 0 deletions; all 13 survived
   and were checked afterwards.

2. **`ORDER BY c.log2FC` sorts a text column.** `AMILL_cellmarker.log2FC` is
   `text` on the live host, so the window that assigns the published table's
   displayed rank ordered the string `"9.5"` above `"10.2"`. Fixed by spelling
   the cast into the window — `ORDER BY CAST(c.log2FC AS DECIMAL(12,4)) DESC,
   c.gene`. It matters on every published table where the two orderings differ;
   for `AMILL` they disagree on cnidocyte, gastrodermis_1, gland_1_Xbp, gland_2
   and gland_3, among others.

**Verified on the host afterwards.** Tables 33 / 35 / 14 / 310 / 15500; 8 atlas
rows with a published figure; `singlecell` untouched at 33; `sc_gene_refseq` at
79,022; seven page/query combinations HTTP 200 with zero PHP diagnostics
(including two `pub:` ids, a twinned and an untwinned one); the deployed
`sc_common.php` byte-identical to the tree's (`cba54a41…`); `pub:OPATA:Whole
adults` reaching the interactive page with the published panels on that same
page; and the picker no longer offering a twinned figure twice.

**A non-finding, recorded so it is not re-investigated.** The re-analysis marker
table (`cell_marker.php?dataset=AMILL_whole_adult`) shows a log2 FC column that
is not monotonic down the page. That is correct, and predates this deploy: the
page's displayed order follows the Wilcoxon **score**, not log2 FC. Across all
14 re-analysed datasets the score is monotonic in rank (0 of 15,190 adjacent rank
steps where it rises), while log2 FC rises in 7,209 of them (~47%) — it is a filter
column, as the page's own note says. The published `_cellmarker` path, which is
what the CAST fix above touches, *is* ordered by log2 FC and now comes back
correctly descending (calicoblast: 9.34, 9.12, 8.99, 8.88, 8.88).

**Rollback.** The tables come back in one command:

```bash
mysql cnidaria < /home/jackie/backup-sc-reanalysis-20260923-1635/pre-reload-tables.sql
```

— a genuine pre-deploy dump (1.21 MB, all six `singlecell_*` tables). The assets
are additive and need no rollback.

**The PHP files are *not* recoverable this way, and the next deploy should fix
the cause.** The backup stage copied the *development tree* into the backup
directory, so `files/` holds the post-change copies; the pre-change content
(`sc_common.php` `1695b41c…`) now survives nowhere on the host. Reverting the
pages by hand would mean starting from
`/home/jackie/backups-20260921/php-before-figures/` and re-applying the nav edits
the server-side session made on 09-21 and 09-22. The one-line fix is to reverse
the direction: **copy live → backup first, then write.**

### 0.1.3 The reason a figure has no re-analysis, told per dataset (2026-09-24)

The last two published figures on the site — `NVECT_WholeOrganism` and
`OARBU_SymbioticState` — have no re-analysis behind them, and every page that
said so gave the same reason:

> the deposit behind the figure is an image, not a count matrix

That is false for both. `NVECT_whole_adult` is an **scATAC** deposit
(PRJNA1249376 / SRP577940: 25 ATAC + 2 RNA runs, peaks and bigWigs) and
`OARBU_symbiotic` holds **raw reads only** (PRJNA1122932 / SRP513328: 10
metagenomic + 2 scRNA runs, no matrix). Neither is an image; one is not even
scRNA-seq. Stating one wrong reason for both also put *our* gap on the deposit,
which is the thing the QC table's own header comment forbids.

**The reason already existed.** `singlecell_qc.analysis_status` has carried it
since 2026-09-20, and `sc_qc_unprocessed_reason()` (sc_common.php) already
renders it — but only `sn_data.php` called it. The blocker was structural: a
`pub:` entry is synthesised from an image filename by `sc_published_entries()`
and so carries **no `dataset_id`**, leaving the marker and atlas pages with
nothing to join a reason to. `published_figure` only ran the other way, from an
atlas row to a figure.

**What changed.** The same registry column is now emitted to `singlecell_qc` as
well, so the two tables answer the two halves of one question: the atlas row
says which dataset was re-analysed *from* a figure, the QC row says which
deposit *is* it when nothing was. A stem appears in at most one of the two, so a
page can ask both and never get two answers.

| File | Change |
|---|---|
| `meta/dataset_registry.tsv` | `published_figure` set for `NVECT_whole_adult` (`NVECT_WholeOrganism`) and `OARBU_symbiotic` (`OARBU_SymbioticState`); OARBU's `not_processed_reason` filled in ("deposit holds raw reads only; no count matrix", was empty, which would have fallen back to the vague "count matrix not retrieved"); its `reference_genome`/`genome_version` filled from the assembly the deposit belongs to |
| `sql/singlecell_schema.sql` | `singlecell_qc.published_figure VARCHAR(128) NULL` + the guarded `information_schema`/`PREPARE` migration, same idiom as the atlas column |
| `pipeline/06_aggregate_qc_table.py` | `published_figure` added to `QC_COLS`; still outside `COLUMNS`, so it stays out of the published TSV and Markdown |
| `web/php/sc_common.php` | new `sc_qc_figure_reason($conn, $stem)`, the mirror of `sc_interactive_twin()` |
| `web/php/cell_marker.php` | the false sentence replaced; keeps "This dataset was not re-analysed" as its opening (two existing assertions pin that phrase) and then states the real reason |
| `web/php/cell_atlas.php` | the static-page paragraph now gives the reason instead of leaving it implicit |
| `web/php/sn_data.php` | the legend no longer enumerates a fixed list of reasons |

Verification: 14/14 suites pass (`test_php_render` 154s, `test_php_render_site`
15s), 0 failures. Six assertions were added so the absence is not silent — a
missing reason falls back to a generic sentence that reads perfectly well and is
simply not this dataset's; the tests assert **both** the reason present and the
old sentence absent, on both pages. `06` regenerated clean with no schema-check
warning; the emitted QC rows carry exactly the 10 figures that exist live
(verified 1:1 against `ls images/*_UMAP_1.png` on the host). All four PHP files
lint clean under the **production** interpreter, checked by copying them to
`/tmp` on the host and running `php7.4 -n -l`, not with the local PHP 8.5.

**Not yet deployed.** The live docroot still has the old copy; `sc_common.php`
`591c6d7e…`, `cell_marker.php` `bbc8de75…`, `cell_atlas.php` `b9902cdd…`,
`gene_exp.php` `226006d3…`, `sn_data.php` `294127d7…`. The DB also needs the new
column before the PHP is worth shipping: run `sql/singlecell_schema.sql` (the
migration is additive and idempotent) and reload `sql/load_singlecell.sql`.

Note the ordering: if the PHP ships first, `sc_qc_figure_reason()` finds no
column, returns `''`, and every page falls back to the generic sentence. That
degrades honestly rather than lying, but it is a real degradation — ship the
schema first.

### 0.1.4 Cell-type annotation for five datasets that had none (2026-09-28)

Five datasets (`NVECT_2month`, `NVECT_neoplasm`, `NVECT_tentacle`,
`NVECT_nervous`, `AMURI_regen`) were live as `cluster_only`: their features are
keyed by species-specific gene-model ids (`NV2.*`, `evm.TU.*`, …) while the
marker panel matches gene *symbols*, so 0 of ~1,000 ranked features matched a
pattern and every cluster exported as `Cluster N` — the NVECT_2month gene page
behind the report showed a UMAP with no cell types at all.

`work/label_transfer_published.py` transfers labels from an annotated reference
through the RefSeq `XP_` bridge the site already uses for gene display
(`sc_gene_refseq` plus `NVECT_locus`), with a floor on marker score, a margin
below which two families count as tied, and a generic-state filter (ribosomal /
histone / HMGB) that otherwise lets a housekeeping-driven cluster win. Result —
named clusters, then distinct names: 18 of 23 clusters (10 names) for 2month,
13/16 (8) neoplasm, 14/19 (10) tentacle, 15/27 (7) nervous, 24/28 (12) AMURI.
Every one is `de_novo`, `cell_type_mode=named`, and keeps its original cluster
label in `cluster_label_prev` for audit.

**Two landmines in the documented path.** `06_aggregate_qc_table.py` emits a
*full reload* — five unconditional `DELETE FROM`s followed by every dataset this
tree knows about. Against production on 2026-09-28 that was not safe:

1. `ACOER_lifecycle` (live: `published`, 8 cell types, 63,230 cells, cnidosite
   row 27) has **no local directory at all** — the reload would have DELETEd a
   dataset off the public site.
2. `OARBU_symbiotic` is live as another session's `published` / 28-type / 6,345
   cell build (exported 2026-09-27T00:35:36Z) but `de_novo` / 16-type / 6,540
   cells in this tree — the reload would have rolled that work back. It is not
   touched by this deploy and still reads `published` / 28.

So the load was scoped instead: `work/make_scoped_load.py` parses the generated
SQL (by column name, so it cannot be fooled by a gene that looks like a dataset
id) and re-emits it with every `DELETE` bounded by `dataset_id`. Everything not
named is untouched *by construction* rather than by review.

The rehearsal in a throwaway database (`cnidaria_rehearse_20260928`) caught an
ordering bug before production saw it: `singlecell_celltype` and
`singlecell_markers` both carry `FOREIGN KEY (dataset_id) REFERENCES
singlecell_atlas` with `ON DELETE CASCADE`, so the first draft — tables emitted
in schema order, children first — died on `ER_NO_REFERENCED_ROW_2`. Deletes go
child-first, inserts parent-first.

**The same trap in the viewer's table of contents.** `singlecell_data/
_export_index.json` is fetched by `web/viewer/index.html` and holds 17 entries
live against 16 locally, again because of `ACOER_lifecycle`. Copying ours over
theirs would have dropped that dataset from the viewer's list. `05_export_web.py`
already solves this — `write_index(..., replace=False)` merges by `dataset_id` —
so `work/merge_export_index.py` calls that function seeded with the *live* file
rather than reimplementing the merge, and refuses to write if any live entry
would be lost.

**Deployed and verified.** Assets first (`rsync -rlptD --no-owner --no-group`,
no `--delete`, as §0.1.1 requires — the 12 `gene_ids.json` sidecars and the
7.7 MB `_gene_ids.tsv` are live-only and no script here can regenerate them),
then the scoped SQL. `0 deletions`; 16/2/4/9/4 files created, being the
`umap_celltype.png` that a `cluster_only` export never wrote. Expressions:
every gene named in `genes.json` has its file (`missing=0` on all five; the
directory holds a further 95–284 unreferenced files from earlier export runs,
which `gene_exp.php` cannot reach because it resolves through `genes.json`).
Live now reads `published 9 / de_novo 5 / cluster_only 3`; all twelve
non-target datasets are byte-identical before and after, `ACOER_lifecycle`
included. HTTP: `cell_atlas`/`cell_marker`/`gene_exp` pages 200 with zero PHP
diagnostics, and the reported page
(`gene_exp.php?dataset=NVECT_2month&gene=XP_048581300.1`) correctly answers
"No expression data" — that accession is `LOC5512112`, known to the site but in
none of these datasets' feature sets, so the page was right and the missing
annotation was the actual defect.

That last check was first read through the *wrong* vhost, which makes an
expression page answer that way for any gene, so on 2026-09-28 it was re-run
against `cnidosite.org` (§2): the page is 75,963 bytes, lists 418 of the
dataset's other genes, says `No expression data for "XP_048581300.1"`, and a
control gene from the same dataset (`NV2.7661`) loads with no such message. The
claim holds; the method that produced it did not.

**Two datasets this route could not do, both since done another way.** The
transfer declines `NVECT_gastrula` (only 13.4% of the reference's markers bridge
into its `NVE` id space, under the script's 35% floor) and `NVECT_embryo` (2 of
17 clusters, median family margin 0.06 — a coin flip). Neither was annotated by
*this* script, and its refusals stand as written: the labels came from the
study's own published table for the gastrula (§0.1.6) and from a dataset-scoped
marker panel for the embryo (§0.1.7), the latter after the site owner's
instruction to annotate it 自己. Left alone on purpose: 不猜 — the rule was not
relaxed, the evidence changed.

`HVULG_siebert_atlas` was on this list too — Hydra is the only Hydra dataset on
the site, so there was no labelled reference to transfer from, and GSE121617 is
counts-only. The labels did exist, in the study's own public Single Cell Portal
study rather than in the deposit; see §0.1.5.

### 0.1.5 `HVULG_siebert_atlas` annotated from the study's own labels (2026-09-28)

**Deployed and verified** on the user's instruction ("像其他单细胞数据一样可视化，注释好的放上去"). It had been prepared and verified locally earlier the same day, and
deliberately left undeployed until that instruction arrived, because "全部部署"
had been scoped to the five datasets in §0.1.4.

The reason this dataset was stuck is that GSE121617 ships counts only. The
paper's assignments (Siebert et al. 2019, Science, doi:10.1126/science.aav9314)
are in Broad Single Cell Portal **SCP260**, whose study and file endpoints now
demand a bearer token but whose *cluster files* are still public:

```bash
curl -s 'https://singlecell.broadinstitute.org/single_cell/api/v1/studies/SCP260/clusters'
curl -s 'https://singlecell.broadinstitute.org/single_cell/api/v1/studies/SCP260/clusters/Whole%20Genome%20Clustering'
```

"Whole Genome Clustering" (41 labels, "mapped to the Hydra genome 2.0 reference")
is the file matching `GSE121617_Hydra_DS_genome_UMicounts.txt.gz`; the
transcriptome file is a different partition (42 labels) and is not used. Two
independent checks that the labels are the right ones, not merely present:

* SCP prefixes genome-mapped cells with `G` (`G01-D1_GCGCCCCATGAA`) and the
  deposit does not. With the prefix intact **0** of our 25,438 cells match; with
  it stripped **23,775** do (93.5%), and the unmatched include cell IDs the
  authors list by hand as excluded doublets in `SA06_ClustGenome.Rmd`.
* Per-label abundance against the authors' own counts, over all 41 shared
  labels: **Spearman ρ = 0.997**, identical top-5 ordering
  (`ecEp_SC1 > enEp_SC1 > enEp_SC2 > i_SC > i_nb1`). The join lands on the right
  cells, not just on cells.

Built by `work/build_hydra_celltypes.py` into
`data/raw/GSE121617_prepared/GSE121617_Hydra.cell_to_cts.csv.gz` (24,458 cells;
zero `mtime` so a re-run is byte-identical), wired through the registry's
`cell_type_table` column and the published-table route in `03_qc.py`. Labels are
shipped **verbatim** — `ecEp_head/hyp`, `i_gc/n_prog`, braces and parentheses and
all — with the authors' longer "for Broad portal" names kept only as a
short→long map in `GSE121617_Hydra.cell_to_cts.provenance.json`.

Result: `published` / `named`, 25,438 cells, 42 cell types (41 published +
`unassigned` for the 1,663 cells with no published label — distinct from the
authors' own `unident` label, which is one of the 41), 4,200 marker rows, both
figures written.

One stale file to ignore: `data/annotated/HVULG_siebert_atlas.auto_annotation.tsv`
still has mtime 2026-09-20 and says all 42 Leiden clusters are `unassigned`,
because 04 writes it only on the de-novo route and so never refreshed it. Nothing
in the pipeline or the PHP reads it (only 04 writes it, for audit), so it is
harmless — but it describes the `cluster_only` era, not the current annotation.

**The deploy itself** followed §0.1.4 exactly, scoped to this one dataset.
Backup: `/home/jackie/backup-sc-hydra-20260928/` (`pre-reload-tables.sql`,
`pre-export-index.json`, per-file md5s of the asset directory, `pre-counts.txt`,
and the scoped `load_hydra.sql` actually loaded). Rehearsed in
`cnidaria_rehearse_hydra` first — no FK error this time, and the decisive check
was that a sorted `mysqldump` of every non-HVULG row was **byte-identical**
before and after; the only diff line was the dump's own `Database:` header,
which is why the first comparison looked like a mismatch and the second (sorted)
one did not — a raw dump is order-sensitive and one INSERT per row, so sort
before diffing or you will chase a phantom.

Assets: `rsync -rlptD --no-owner --no-group`, **974 transferred, 84 created,
0 deleted**. The 84 are expression files for genes the new panel adds; the live-only
`gene_ids.json` sidecar (85,012 bytes, nothing in this tree regenerates it from
scratch) is untouched — verified by md5, not by assumption. Content changed in
5 top-level files (`cellmeta.bin`, `composition.json`, `genes.json`,
`manifest.json`, `markers.json`) and 882 expression files; `qc.bin`,
`embedding.bin` and `umap_cluster.png` are unchanged.

DB: live `HVULG_siebert_atlas` went from 42 `Cluster N` rows to the 41 published
labels + `unassigned`; table counts unchanged (atlas 17, qc 35, celltype 325,
markers 16650, atlas_map 33) because 42/2100 was 42/2100 either way. Every
non-HVULG dataset byte-identical after the load.

**One honest consequence, and its calibration.** `05_export_web.py` builds its
expression panel from the *top markers per cell type* (`panel` starts empty and
is filled only from `markers.json`), so re-annotating necessarily re-derives it:
HVULG's panel went 1017 → 965 genes, i.e. 84 genes gained a file and 135 lost
their reference (`genes.json` is the only index `gene_exp.php` resolves through,
so those 135 answer "No expression data" for this dataset; their `.bin.gz` files
remain on disk, unreachable — live now holds 142 unreferenced files, and earlier
deploys left 95–284 per dataset the same way). This is not specific to HVULG or
to the slash fix: the previous deploy's five datasets each lost far more —
NVECT_2month −211, AMURI_regen −281, NVECT_nervous −134, NVECT_tentacle −96,
NVECT_neoplasm −94. If a gene is missing from HVULG's expression view and used to
be there, that is why.

HTTP verified: `manifest.json` reads `published` / `named` / 42 types;
`cell_atlas.php?dataset=HVULG_siebert_atlas` and
`cell_marker.php?dataset=HVULG_siebert_atlas` both 200 with zero PHP diagnostics
and the published labels (including `ecEp_head/hyp`, `i_gc/n_prog`) rendered
verbatim; the atlas page references both `umap_celltype.png` and
`umap_cluster.png`; `_export_index.json` holds 17 entries with `ACOER_lifecycle`
intact.

**The sidecar is now stale by 372 genes.** `gene_ids.json` was built against the
old 1017-gene panel (`"n_genes": 1017, "n_mapped": 690`); of the 965 genes now
exported, 593 are in its `map` and 372 are not, so those display as their model
ID. This degrades exactly as the 327 unmapped genes already did —
`sc_gene_ids.php` documents the fallback ("侧车文件缺失时整段静默降级：页面照旧
显示原号") and 不猜 governs the rest — but it is a real gap. Regenerating it needs
the Hydra 2.0 protein reciprocal-best-hit pass its own `sources` field names,
which no script in this tree performs. Left as-is rather than half-faked.

**A pipeline bug this found, worth keeping in mind for any future published
table.** Two of the 41 labels contain `/`. `sc.tl.rank_genes_groups` stores each
group name as a *key* in `uns['rank_genes_groups']`, and **h5py refuses a `/` in
a key** — so 04 died at its final `write_h5ad`, *after* computing every marker and
writing the marker table, and 05 then died on the missing UMAP with an error that
points nowhere near the cause:

```
FAILED: Forward slashes are not allowed in keys in <class 'h5py._hl.group.Group'>
```

Fixed in `04_cluster_annotate.py` by ranking on a key-safe alias and restoring the
published spelling for the marker table (`marker_group_keys`). The escaping
follows `05_export_web.safe_name`: `%` is escaped along with `/`, so the map is
reversible and two labels can never collide on one key — substituting `_` would
map both `a/b` and `a_b` to `a_b` and silently merge two cell types into one
marker group. The label stays verbatim everywhere it is read (obs, marker table,
composition, manifest `cell_types`, the viewer), so the site shows the published
name. Datasets without a `/` are untouched: the groupby column and the obs schema
are unchanged, so re-running 04 on them is byte-identical.

One test file fails assertions in this tree, unrelated to the above and
pre-existing (re-measured 2026-09-29 on the whole suite: 14 files, 369 checks,
2 failures; `test_php_render*.py` report no checks here because they need a
database and fixtures):

* `test_integration_record.py`, 2 assertions — `data/qc/NVECT_whole_adult.
  qc_stats.json` (mtime 2026-09-26, written before this work) carries neither
  `integration_declared` nor `integration_method`, so "every QC record declares"
  fails for that one dataset. Left open on purpose: making it declare would mean
  asserting whether that dataset was batch-integrated, which the record does not
  say. See §0.1.8.

A third failure used to be listed here — `test_render_umap.py`'s legend margin,
the `NVECT_whole_adult` figure truncation in §4. **Fixed 2026-09-29** (§0.1.8);
that file now passes all 64 of its checks, and the count above is 369 against the
349 measured on 09-28.

Every assertion that reads `04_cluster_annotate.py` passes, including the 88
added to `test_marker_panel.py` for the dataset-scoped panel (132 checks in that
file now, against 44 before): an absent panel is a no-op, a shared label unions
rather than replaces, a dataset pattern's anchors cannot narrow the global half,
a model-ID pattern matches whole IDs only (`NV2.5` must not fire on `NV2.555`),
the loader refuses a wrong header / empty field / duplicate label / uncompilable
pattern / empty panel / missing file, and the deployed `NVECT_embryo.tsv` is
internally consistent — every pattern an anchored alternation of escaped IDs,
its third column listing exactly the IDs its pattern carries, no ID in two
groups, and no group too small to ever reach `MIN_MARKER_HITS`.

### 0.1.6 `NVECT_gastrula` annotated from the study's own UCSC table (2026-09-28)

**Deployed and verified.** §0.1.4 had left this dataset `cluster_only` because
`work/label_transfer_published.py` could bridge only 13.4% of the reference's
markers into its `NVE…` id space, below its 35% floor. The labels did exist —
not in the deposit, but in the study's own UCSC Cell Browser.

GSE200198 (Steger et al. 2022, Cell Reports 40(12):111370, doi:10.1016/
j.celrep.2022.111370, PMID 36130520; re-mapped in Cole et al. 2024, Front Zool
21:8) ships counts only: its GEO supplement is five files, and
`GSE200198_alldata.cells.csv.gz` is a headerless single column of barcodes. The
study's per-cell annotation is published at

```bash
curl -s 'https://sea-anemone-atlas.cells.ucsc.edu/sea-anemone-atlas/all/meta.tsv'
```

described by its own `desc.json` as "Full dataset containing 55,042 cells from
sea anemone *Nematostella vectensis* after quality control filtering", submitted
by Alison G. Cole and Julia Steger (Technau lab, Vienna).
`work/build_gastrula_celltypes.py` writes
`data/raw/GSE200198_prepared/GSE200198.cell_to_cts.csv.gz` with `mtime=0` (so a
re-run is byte-identical), wired through the registry's `cell_type_table` column
and the published-table route in `03_qc.py`.

Two checks make this a join to *these* cells rather than a same-study
approximation, and they are the reason this route was acceptable where §0.1.4's
transfer was not — nothing here is inferred; the study's own names are attached
to the study's own cells:

* the `Cell` column equals the deposited barcode list **both as a set and in
  list order** (55,042 barcodes, element for element);
* the two label columns, `IDs` and `Cluster`, are asserted **equal**, so there is
  one naming scheme here and not the two Hydra's SA06 turned out to hold (§0.1.5).

Labels are shipped verbatim — `NPC`, `cnidocyte.mature`, `ectoderm.epidermis`,
`retractor muscle`, `gland.mucous` — no renaming, no merging, no prettifying.
Result: `published` / `named`, 49,166 cells, 27 clusters → 12 cell types, 1,200
marker rows, both figures written. `qc_stats.published_annotation.match_rate` is
**1.0**: every one of our QC-passing cells carries a published label.

**Deployed and verified**, scoped to this one dataset, following §0.1.4's
procedure. Backup `/home/jackie/backup-sc-gastrula-20260928/`. Rehearsed in a
throwaway database first; production left every other dataset untouched
(`singlecell_atlas` 17 rows before and after; `ACOER_lifecycle` and
`OARBU_symbiotic`'s live build both intact). `_export_index.json` merged from the
*live* 17 entries by `work/merge_export_index.py`, so `ACOER_lifecycle` stays in
the viewer's list. Assets: `rsync -rlptD --no-owner --no-group`, **no
`--delete`**; a dry run afterwards reports **0 files to transfer**, i.e. the live
directory is exactly this tree's build, and every gene named in `genes.json` has
its file (`missing = 0`).

Live now: `NVECT_gastrula` went from `cluster_only` / 27 `Cluster N` types / 1,350
marker rows to `published` / 12 published types / 600 (12 × the top-50 window).
The asset directory holds 939 expression files against 507 referenced — the
difference is the old 917-gene panel's files, which `rsync` without `--delete`
deliberately preserves and `gene_exp.php` cannot reach (§0.1.5's calibration).

**The expression panel shrank 917 → 507 genes** (−410). Same cause as §0.1.5:
`05_export_web.py` builds its panel from the top markers per cell type, and there
are now 12 cell types instead of 27 clusters. `cellmeta.bin`, `embedding.bin`,
`composition.json`, `markers.json` and both PNGs were rewritten; `qc.bin` is
unchanged, and the `gene_ids.json` sidecar was not touched (md5 `bbdcb2c4…`
before and after).

**A gap that was already there, stated plainly.** Only **61 of the 507** exported
genes (12.0%) are in this dataset's `gene_ids.json`, so the other 446 display as
their raw `NVE…` id under the site's documented fallback
(`sc_gene_ids.php`: unmapped ids keep the original number, marked "not mapped to
RefSeq"). This is not a regression from the re-annotation — the previous
917-gene panel was covered at 11.6% — and the site-wide `_gene_ids.tsv` (79,022
mapped ids) reaches only 62 of the 507, so the sidecar is not the limiting
factor: ~88% of the `NVE…` id space has no RefSeq mapping anywhere on the site,
against 69.5% for the `NV2…` space (§0.1.7). Closing it needs NVE→LOC/`XP_`
evidence this tree does not have, so it is left visible rather than half-faked.

### 0.1.7 `NVECT_embryo` annotated de novo from a dataset-scoped marker panel (2026-09-28)

**Deployed and verified**, on the user's instruction (`NVECT_embryo那就自己注释，请
你注释好部署上去`). `work/nvect_embryo_annotation_finding.md` is the evidence
trail: GSE302686 publishes unnamed clusters, the deposited object's `meta.data`
has no type column of any kind, the paper's single scRNA figure attaches
identities to *genes* and never to cluster numbers, the lab's own
`ScDevTimeSeries.R` contains no `FindClusters` and no annotation step, and no
sibling series covers these 8/10/12 hpf timepoints. That finding concluded a
de-novo route "needs a decision from the site owner about acceptable inference" —
this instruction is that decision.

**Why 04 could not see the markers, and what was added.** `04_cluster_annotate.py`
ships one global panel of 12 symbol-keyed regexes, and this dataset's features are
`NV2.<n>` model ids: 0 of 850 ranked features matched any pattern, so no cluster
could be named even in principle. The fix is general, not a special case: the
registry grows a 19th column, `marker_panel`, naming a per-dataset TSV of
`label <TAB> pattern <TAB> genes`. `load_marker_panel()` validates it (header,
non-empty fields, duplicate labels, pattern compilability) and `compile_panel()`
merges it into the global panel by **union of patterns per shared label**, so a
dataset may key some markers by symbol and others by model id — and every dataset
without a `marker_panel`, which is all the others, compiles to exactly the panel
it had before.

**The panel itself** (`work/build_embryo_panel.py` →
`meta/marker_panels/NVECT_embryo.tsv`) is 44 genes in 6 groups: proliferating
(14), neural (7), mesoderm (6), ectoderm (6), germline (6), endoderm (5). Both
sources are the study's own — `github.com/technau/NemVecEndoderm/
nv2.func-04.04.23.tsv.gz`, the lab's NV2→symbol table (the site's own tables have
none: `NVECT_cellmarker.symbol` is `-` for all 10,794 NV2 genes it lists), and
`manuMarkers` in their analysis script, the 12 genes the paper plots. Every id is
asserted to carry exactly the symbol the lab's table gives it **and** to be a
feature of `data/qc/NVECT_embryo.qc.h5ad`; an id in no table fails the build, and
an id the QC stage dropped is recorded in `excluded_by_qc` rather than kept as a
pattern that matches nothing. Patterns are anchored and exact (`NV2.5` is a
prefix of `NV2.555`), so no cluster can be named off the wrong gene.

**What it produced, and what it refused to.** 5 of 17 clusters named: clusters
1, 2, 4, 7 → `auto:proliferating` (MCM2/3/4/5a/7, CDT1, CDC6, ORC1, TPX2, ASPM,
CDK1, cyclin + histones — 7/5/3/2 independent hits) and cluster 15 →
`auto:mesoderm` (`snailb` + `snaila`, and it is 100% 12 hpf). The other **12
clusters keep their Leiden identity as `Cluster N`**, and `annotation_note` says
so on the page: they are *unnamed, not found to be something else*. That is the
ceiling this evidence supports rather than a shortfall of effort — the study's own
7 clusters each hit 4–6 of these groups, its text says only mesodermal and
ectodermal clusters are clearly identifiable at 8 hpf, and 4 of the 5 named
clusters are a single cell-cycle *state*. Cluster 3 was deliberately left unnamed
even though its top gene is `brachyury`, because its count-based group is
ectodermal: a graded territory, and naming it would be a guess.

Result: `de_novo` / `named`, 9,711 cells, 17 clusters → 14 cell-type rows (2 named
+ 12 `Cluster N`), 700 marker rows (14 × 50), both figures,
`named_labels = [auto:mesoderm, auto:proliferating]`. `06_aggregate_qc_table.py`
gained one branch, gated narrowly on `marker_panel` so no other dataset's
`cell_type_source` changes, writing `de novo - dataset marker panel; …` truncated
at a sentence boundary to 237 chars.

**Deployed and verified.** Backup `/home/jackie/backup-sc-embryo-20260928/`. Same
scoped procedure, with one addition worth carrying forward: the rehearsal database
must be built from the **live** schema (`mysqldump --no-data`), because
`sql/singlecell_schema.sql` is stale — 22 columns against live's 24, another
session having added `cluster_source` and `embedding_source`. 06's INSERTs carry
explicit column lists and both new columns are nullable, so production survives
the drift, but a rehearsal built from the local schema file dies with
`ERROR 1136 Column count doesn't match value count`. Assets: `rsync -rlptD
--no-owner --no-group`, no `--delete`; live **607 files, 7 created, 0 deleted**,
and a dry run afterwards reports 0 files to transfer. `_export_index.json` merged
from the live 17 entries.

Live now: embryo went from `cluster_only` / 17 `Cluster N` / 850 marker rows to
`de_novo` / 14 types / 700; gastrula and the other 15 datasets unchanged;
`ACOER_lifecycle` still in the index; all 12 `gene_ids.json` sidecars and the
7.7 MB `_gene_ids.tsv` intact.

**HTTP, on the site's own host name.** All 200 with zero PHP diagnostics;
`cell_atlas.php?dataset=NVECT_embryo` shows `17 / 14` (clusters / cell types),
"Assigned de novo from a cnidarian marker panel" and the annotation caveat;
`cell_marker.php` shows both `auto:` groups and the 12 `Cluster N` rows;
`gene_exp.php?dataset=NVECT_embryo&gene=NV2.10624` (brachyury) renders
`XP_032233912.1`.

**One more stale sidecar, same shape as §0.1.5's.** The panel went **590 → 514**
genes; the sidecar was built for the 590 and maps 423, of which **357 (69.5%)**
are in the new 514 — so 157 genes display as raw `NV2…`, and the sidecar's header
still reads `n_genes: 590 / n_mapped: 423`. Its coverage happens to equal the
site-wide `_gene_ids.tsv`'s exactly (357 of 514), so unlike the gastrula the
sidecar is not the limiting factor — it is simply behind the panel. Regenerating
it needs the same reciprocal-best-hit pass no script in this tree performs.

### 0.1.8 The truncated legend label on `NVECT_whole_adult`, wrapped (2026-09-29)

The instruction: *"NVECT_whole_adult 的静态 UMAP 图例被截断——最长标签这个要显示完全，
一行显示不了就换行"* — the longest label must display in full, wrapping to a
second line when one line cannot hold it. The defect itself is §4's, found
09-28: the published label `gastrodermis_muscle_parietal_circular_prog` was drawn
past the canvas edge and the shipped PNG read `..._circular_pro` with its cell
count gone.

**Why it was not just a longer margin.** Three quantities were wrong, and only
the first was obvious:

| quantity | before | measured |
|---|---|---|
| the label's width | 42 chars × 0.55em = 3.59in (estimated) | **2.92in** |
| what had to fit | the label | the **entry** — label + ` (11,588)`, which is 51 chars |
| where the text starts | `LEGEND_X` = 5.805in | **6.17in** — the legend's marker column takes 0.366in first |

So the label alone fitted; the *entry* did not, by 0.37in of unmodelled marker
column plus 0.61in of count. And because the check in `test_render_umap.py` used
the same estimate the renderer did, it agreed with the broken figure instead of
catching it — the one number that could have flagged the truncation was a copy of
the number that caused it.

**The fix is measurement, not a better constant.** `07_render_umap.py` now asks
matplotlib for the width of each string (`measure_text`, via
`Text.get_window_extent()`, deterministic, no canvas draw needed) and wraps with
`wrap_legend_label(label, fits)` — the predicate is the caller's, so the break
rules stay testable against a plain character count. Breaks fall after
underscores so the parts stay words; a token longer than the whole margin is
hard-broken; if not even one glyph fits, the label is drawn as published rather
than shredded one character per line. `legend_entry()` is the single place that
decides one-line-or-two, and both the figure and the tests call it, so there is
no second copy of the rule to drift. `LEGEND_TEXT_X_IN = 0.37` states the marker
column and `LEGEND_KWARGS`/`LEGEND_MARKER_PT` name the legend layout that
consequence comes from, so the test can rebuild the same legend and re-measure
the column instead of trusting the number.

**Scoped by construction.** All 16 datasets were re-rendered and diffed by md5
(32 figures: 16 cluster panels plus the 16 named datasets' cell-type panels):
**exactly one file changed**, `NVECT_whole_adult/umap_celltype.png`. The other 31
figures — including that dataset's own `umap_cluster.png` and every other named
dataset's legend — are byte-identical. Re-rendering in a second process gives the
same 32 hashes again, so `measure_text` costs nothing in reproducibility. (The
measured rewrite was also checked against a first cut that used a corrected
character budget: the two agree byte-for-byte on all 32 figures, which is what
makes the split of the 42-character label — `..._parietal_` / `circular_prog
(11,588)` — known to be the intended one rather than an artefact of the new
code.)

**Deployed and verified.** Live md5 before: `72f2fc6d…` (688,260 bytes,
2026-09-26 11:46) — byte-identical to the staged tree, so no other session had
touched it. Backed up to
`/home/jackie/backup-sc-wholeadult-legend-20260929/umap_celltype.png.before`,
then the one file via `rsync -rlptD --no-owner --no-group` (no `--delete`); live
now `ec130948…` (690,886 bytes, mode 664, matching its neighbours).
`https://cnidosite.org/singlecell_data/NVECT_whole_adult/umap_celltype.png`
returns 200 and those exact bytes; the atlas page returns 200 with both figures
referenced and zero PHP diagnostics; the scoped dry run is clean (the only
residue is a pre-existing perms/mtime difference on `umap_cluster.png`, live 644
against staging 664, no content difference). The figure URL carries no cache
buster, so a browser holding the old PNG keeps it until it revalidates.

**Two things this deploy turned up, neither fixed here.**

1. **A whole-tree `rsync -n --stats` is not a convergence check on this site.**
   Run that way it reports 645 files to transfer: all of `OARBU_symbiotic`
   (634 created + 10 changed). The cause is benign — **live is newer than
   staging for that dataset** (live `2026-09-27 08:31`, 1,500 expression files;
   staging `2026-09-27 00:24`, 634), i.e. another session deployed a re-export
   after this tree's copy was made. Uploading this tree's `OARBU_symbiotic` would
   roll that back and leave 866 live-only expression files orphaned under a
   manifest that no longer lists them. Scope every dry run and every transfer to
   the dataset being deployed, as §0.1.5–§0.1.7 do.
2. **`test_integration_record.py` still fails 2 assertions, both on
   `NVECT_whole_adult`** — its QC record carries neither `integration_declared`
   nor `integration_method`, so the record declares nothing to compare against
   ("declares, does not report" wants `(True, False)`, gets `(False, False)`;
   the suite-wide count is 15 of 16). The third failure the suite used to report
   — the legend margin, in `test_render_umap.py` — is the one fixed here. Making
   whole_adult declare something would mean asserting whether it was
   batch-integrated, which is a provenance claim about a dataset and not
   derivable from the record that is missing it, so it is left as an open item
   rather than guessed.

### 0.1.9 The published-figure section claimed cell-type names it did not have (2026-09-30)

The instruction: *"https://cnidosite.org/cell_atlas.php界面UMAP — as published
(source study)部分，单细胞未注释到细胞类型"* — in the *UMAP — as published
(source study)* section, the single cells are not annotated to cell types.

**What the report turned out to mean.** The section itself was working: it draws
the source study's own figure, and the page above it already carries the study's
own per-cell names. What was wrong was the sentence printed under the figure,
which asserted of every published figure alike:

> The source study's own figures, kept here so the re-analysis above can be read
> against them. These are **their** cells and **their** labels: the projection
> cannot be recoloured, subset or queried, and **the cell-type names in them are
> the study's, not ours.**

For the figure the report was about, the bolded clause is false, and the reader
who believes it goes looking for names in the figure that are not there.

**The evidence, because the fix rests on it.**

| step | finding |
|---|---|
| the shipped file | `NVECT_WholeOrganism_UMAP_1.png`, 2100×1800 RGBA, prints **"UMAP by Cell Types"** *inside the raster* |
| its own legend | `0` … `11` |
| the groups behind those numbers | `NVECT_cellmarker`, `tissue_dev = 'WholeOrganism'`: exactly 12 `celltype` values, every one of them literally `cluster 0` … `cluster 11` |
| the study's own names for these cells | 16 named types, from the deposit's own `cellColData.tsv`, already applied per cell and already on this page (`umap_celltype.png`, `annotation_provenance = 'published'`, listed under **Cell types in this dataset**) |

So no cell-type names for those 12 groups ever existed, and under *不猜* they
cannot be invented; and the reader was never missing names, only being told to
look for them in the wrong place. The title inside the PNG is the publisher's and
is itself misleading — every sibling 2100×1800 render in `images/` with a
numbered legend is titled "UMAP by Clusters", and this one alone is not. A
mis-titled raster cannot be corrected by copy, so the copy says what the legend
actually lists and points at where the names really are.

**Why measured per file, and not derived.** The tempting rule — read the site's
own `<ABBR>_cellmarker` table, which is where the names would be — cannot decide
what a *figure* shows. `AMILL_cellmarker` names 27 types and `OARBU_cellmarker`
28, yet Oculina's first shipped figure is numbered `1`–`28`. A table-driven rule
would have produced a fresh false claim on exactly the datasets that look most
named. Nor can the dataset be the key: Acropora muricata and Oculina arbuscula
each ship **one numbered figure and one named one**, and both are drawn, so a
per-dataset answer is wrong for one card or the other whichever way it is set.

The ten published entries ship **13 distinct figures**, because 7 of the 10 pairs
are byte-identical and only the first is drawn. Each was read off the file the
site serves:

| legend lists | files |
|---|---|
| **names** (5) | `AMILL_UMAP_1`, `OPATA_UMAP_1`, `SPIST_UMAP_1`, `AMURI_RegenerationStage_UMAP_2`, `OARBU_SymbioticState_UMAP_2` |
| **numbers** (8) | `AMURI_RegenerationStage_UMAP_1`, `NVECT_2monthAnimal_UMAP_1`, `NVECT_NervousSystem_UMAP_1`, `NVECT_NervousSystem_UMAP_2`, `NVECT_neoplasm_UMAP_1`, `NVECT_tentacle_UMAP_1`, `NVECT_WholeOrganism_UMAP_1`, `OARBU_SymbioticState_UMAP_1` |

Two of the thirteen actively mislead, which is why the lookup is a table of
measured facts and not a sentence about figures in general: `NVECT_WholeOrganism`
and `AMURI_RegenerationStage_UMAP_2` both print "UMAP by Cell Types", the first
over `0`–`11` and the second over real names.

**The fix.** `sc_pub_figure_legend($pic)`, in `sc_common.php` beside
`sc_is_published_id()`, returns `'names'`, `'numbers'`, or `''` for a file nobody
has looked at; `''` means *say nothing*, the same rule `sc_qc_figure_reason()`
follows. `cell_atlas.php` asks it once per card (`$pfLegend1`, `$pfLegend2`) and
each card carries its own sentence — "The legend names cell types, in the study's
own words." or "The legend lists cluster numbers, not cell-type names — the title
printed inside the figure is the publisher's, not a description of the legend."
When a figure this page **draws** is numbered *and* the names shown for the
dataset are the study's own (`$pfNumbered && $ctNamed`), the caption says so and
points at the cell-type panel above and the **Cell types in this dataset** table
below; otherwise it says nothing, which is the correct answer for the seven
datasets where the two agree.

**Three mistakes made and corrected here, because each left a silent wrong answer.**

1. **The map was first keyed on the dataset stem.** That is wrong for both
   two-figure datasets, and viewing `AMURI_RegenerationStage_UMAP_2` and
   `OARBU_SymbioticState_UMAP_2` is what showed it: both are **named** while their
   `_UMAP_1` siblings are numbered. Re-keyed on the picture stem (`pic1`/`pic2`
   as `sc_published_entries()` builds them) with a per-card variable.
2. **An amend script dropped a chunk** — `parts[0] + note1 + note2 + parts[2]`,
   discarding `parts[1]`, which held figure 1's card tail and the whole of figure
   2. Symptom was a parse error at `cell_atlas.php:489`. The chunk was re-inserted
   and the file linted under the server's own interpreter.
3. **`$pfLegend2` and `$pfNumbered` were computed from variables that did not
   exist yet** — the block sat directly after `$pf1 = $pubFig['pic1'];`, before
   `$pf2`, `$pfHas2` and `$pfSame`. So `$pfLegend2` looked up `''` and
   `$pfNumbered` ignored figure 2 entirely. Moved after the `$pfSame` assignment,
   with the ordering written down in a comment, since it is not self-evident.

**Deployed and verified.**

| file | before | after |
|---|---|---|
| `sc_common.php` | `9d43ae39a8e9d594e2b745082d0bb538` | `65d062baa6b334c46a831fe7eb992a5c` |
| `cell_atlas.php` | `99d343e22ffa619bbf1bd5308707dd61` | `f03c2851aebe01ea78991e74b2d5b762` |

Pre-change copies are in `/home/jackie/backup-sc-pubfig-legend-20260930/`. Both
live files were patched in place after checking their live md5s against these
"before" values, and both lint clean under the host's `php7.4 -n -l`. Six pages
were then fetched over HTTP through `cnidosite.org`: bare and
`pub:NVECT:WholeOrganism` → numbered note plus the pointer, no names claimed;
`pub:AMURI:RegenerationStage` → one numbered card and one named card, no pointer
(the pointer needs `$ctNamed`, and Acropora's re-analysis has no published
provenance); `pub:OARBU:SymbioticState` → one of each plus the pointer; `AMILL`
and `OPATA` → the names note only. All HTTP 200, zero PHP diagnostics, and the
old blanket sentence is gone from every one. `cell_marker.php`,
`cell_marker.php?dataset=NVECT_whole_adult` and `sn_data.php` were smoke-tested
clean. *(The host was unreachable when this note was written, so live has not
been re-checked for concurrent edits since the deploy itself.)*

**The PHP render suite now runs here, and was run.** It had never been runnable
in this tree — `pipeline/tests/test_php_render.py` needs a MariaDB server at
`/tmp/mariadb/run/my.sock` and the docstring pointed at a "DEPLOY.md section 6"
that did not exist. §6 now carries the recipe; the suite runs.

Result: **209 pass, 16 fail.** To find out whether any of the 16 belongs to this
change, `sc_pub_figure_legend()` was made to `return ''` — which reproduces the
pre-change rendering path exactly, since before it there was no note and
`$pfNumbered` was always false — and the suite re-run against that control:
**207 pass, 18 fail**. The only difference is the two assertions added for this
change, which pass with the feature on and fail with it off. **None of the 16 is
caused by this change.**

**What the 16 are.** They are one pre-existing drift, and it is not subtle once
seen: the fixture database is a **live dump** — `sql/load_singlecell.sql`, dated
2026-09-27 00:24 — and in that dump both `OARBU_symbiotic` and
`NVECT_whole_adult` carry a `published_figure`, so `sc_interactive_twin()`
resolves `pub:OARBU:SymbioticState` and `pub:NVECT:WholeOrganism` to the viewer
and the page never takes the static branch. The suite has cases pinning the
*static* page for exactly those two ids (its comments call them "the only `pub:`
id that still renders the static page" and "scATAC, unreanalysed, no viewer
behind it"), including their per-dataset "why this was not re-analysed"
sentences. Those cases are now unreachable, and the remaining failures follow
from the same dump being newer than the expectations — dataset counts (`Showing
the first of 11`, `re-analysed 14`), the `gene_ids` sidecars (`OAR_g1`), and the
bare `cell_atlas.php` default selection (`NVECT_gastruloid`, vs live's
`NVECT_whole_adult`, already an open item).

They are **left failing on purpose.** The fix depends on which side is right —
whether the fixture should still model those two as unreanalysed, or the cases
should be rewritten — and that is a question about the data, not the assertions.
Rewriting assertions until they agree with whatever renders is how the legend
margin check in §0.1.8 came to certify a truncated figure. The same reasoning
keeps the AMURI static-page case in the suite: it is dead, and it is stated to be
dead rather than deleted.

**Coverage of the new code.** The two per-card legend notes are exercised by the
suite: the AMILL case requires the names note, and the AMURI case requires both
notes on one page (`pub:AMURI:RegenerationStage` draws one numbered figure and
one named figure). The pointer sentence is **not** covered — it needs
`$pfNumbered && $ctNamed`, and no fixture dataset pairs a numbered published
figure with a `published`-provenance twin (`AMURI_regen` is `cluster_only` in the
dump). It is verified on live only, and the test file says so rather than
asserting a sentence the fixture cannot reach.

**Mirrored to the repo.** `web/php/sc_common.php` (`79f1048d…`) and
`web/php/cell_atlas.php` (`f1cc7c81…`) carry the same change and lint clean under
PHP 8.5.9. `bundle/code/web/php/` is left at its 2026-09-23 snapshot — it is
already behind live by more than this change. `pipeline/tests/test_php_render.py`
carries the two new assertions. The other two PHP copies have not been
re-verified against live beyond the md5 check at deploy time.

### 0.1.10 The published section drew no cell-type figure (2026-09-30) — WITHDRAWN

**Do not deploy this. The change was staged in the repo and then withdrawn; live
was never touched.** Recorded because the reasoning is the useful part and
because a future session should not rediscover the idea and ship it.

> **Superseded by §0.1.11**, which deployed a corrected version of this panel on
> 2026-10-02. Read that section for what is actually live. What survives here
> unchanged: the card's *placement* (a third panel in the published grid), its
> *provenance discipline* (headed and captioned as this site's render), and the
> diagnosis that the missing figure is structural. What §0.1.11 changed: the
> gate (`$pfNamed`, not `$pfNumbered` — see the OARBU trap below), the figure
> (this site's published-style render, not the re-analysis one), and the tests'
> dataset id. The notes at the end of this section about the repo copy's md5 and
> about the deleted deploy scripts no longer describe the tree.

Why it was withdrawn, in one line: the caption it adds would have asserted
something the site had just discovered to be false.


The instruction, correcting §0.1.9: *"我说的针对已发表的数据，左边那个cluster图对应的
细胞类型注释UMAP图未提供"* — for the published data, the cell-type-annotated UMAP
corresponding to the cluster plot on the left is not provided.

**§0.1.9 read the report as a copy defect and fixed the copy. It was a missing
panel.** The paragraph now says which kind of legend each figure carries and
points at the cell-type panel above; the reader still sees a lone cluster
figure, and the empty half of the row is the gap being reported.

**The gap is structural, and it is in four datasets, not one.** A published
entry is a *pair*: `_UMAP_1` is the cluster plot, `_UMAP_2` the cell-type one.
Where the pair differs that works. Where the study deposited the same picture
twice, the page suppresses the duplicate and the cell-type figure is simply
absent from the site:

| pair | figure 1 | figure 2 | cell-type figure |
|---|---|---|---|
| AMILL, OPATA, SPIST | named | identical | the one figure *is* it |
| NVECT_2monthAnimal, NVECT_neoplasm, NVECT_tentacle, **NVECT_WholeOrganism** | numbered `0–N` | **identical** | **missing** |

That is 7 of the 10 pairs byte-identical, and in 4 of them the surviving figure
is a cluster plot. `NVECT_WholeOrganism_UMAP_1.png` and `_UMAP_2.png` are the
same 2100×1800 render, legend `0`–`11`.

**The fix does not invent a figure.** The names for those cells are not missing
— they are the study's own, applied per cell, and this site already renders them
as `singlecell_data/<dataset>/umap_celltype.png`. So the published section now
draws that file as a second card in its own grid, headed **"UMAP — cell types,
this site's render"** and captioned "this site's render, not the publisher's
figure", with the identical-picture callout extended to say the study published
no cell-type-annotated version. The section's other panels are the study's;
blurring that is the one thing that would make this page dishonest, so the
provenance is in the heading, the caption *and* the notice.

The card is drawn only when both halves of the condition hold, and each half is
load-bearing:

* **`$pfNumbered`** — the drawn published figure numbers its groups. If the study
  named them, the published figure *is* the cell-type figure and a second panel
  would say the same thing twice.
* **`$ctNamed`** — the names this page holds are the study's own. Without it the
  site's labels are its clusters, and a "cell types" panel built from cluster
  numbers is the figure it is standing next to. This is the OARBU trap from
  §0.1.9 in a new place.

The URL comes from `$figs`, the list already built for the re-analysis section,
so the existence check and the asset base are the same ones in use — the card
cannot point at a file that section does not also draw, and a dataset whose
figure was never uploaded degrades to no card rather than a broken `<img>`.

**Tested.** `pipeline/tests/test_php_render.py` gained one positive case (the
card appears exactly once on `pub:NVECT:WholeOrganism`) and two negatives, one
per half of the guard: `pub:AMILL:Whole%20adults` (figure named → no card) and
`pub:AMURI:RegenerationStage` (figure numbered, but the names are the site's →
no card). `pipeline/tests/php/make_fixture_data.py` stages `NVECT_whole_adult`
with both figures, since it is the only fixture that reaches the card.

Result **16 failures, the same 16 as §0.1.9** — the new case passes and no
pre-existing failure moved. The suite is now 210 checks.

**Withdrawn 2026-10-02, before deployment. Live was never touched.** The
published figure this card was built to sit beside turns out not to depict this
dataset's cells at all. Live `cell_atlas.php`, rewritten by the other session at
00:22 that day, now says so on the page itself — for `?dataset=NVECT_whole_adult`:

> This one is **not drawn from the cells on this page**. Its legend numbers 12
> groups, where this dataset has 35 clusters in 16 cell types — so its numbers
> are neither this dataset's clusters nor its cell types, and no cell type can be
> read off them. The picture goes with an earlier whole-organism deposit; the
> cells in this dataset came from a different one…

That answers the question §0.1.9 raised and left open — whether
`NVECT_WholeOrganism_UMAP_1.png` is this study's figure at all — and answers it
the other way. The card's caption said *"The same cells on the same coordinates
as the published figure"*, which is precisely what the sentence above it denies.
Deploying it would have put two contradictory claims in one section and made the
false one the more specific, which is the failure mode this file exists to
avoid.

The premise of the whole section was therefore wrong: there is no
cell-type-annotated counterpart of that figure for the site to provide, because
it holds no cell-type labels for the deposit the figure came from. What the site
does hold — this dataset's own 16 cell types over its own 35 clusters — is
already on the page twice, in the re-analysis panel and under **Clusters and
their cell types**.

Notes for anyone picking this up:

* `web/php/cell_atlas.php` here still carries the withdrawn card
  (`6cfd21fa9453d5345f6ffbf4efbdbb31`, against live
  `837b085244fccf0dac825b751f51d637`). **It is not to be deployed.** The deploy
  scripts that would have applied it were deleted rather than left guarded,
  because the reason not to run them is not "live moved" but "the change is
  wrong". The card's *condition* — a numbered published figure over a dataset
  whose names are the study's own — remains the right guard for any future
  version of this panel.
* The fixture and test additions (`NVECT_whole_adult` in `make_fixture_data.py`'s
  `LAYOUT`, and the three cases in `test_php_render.py`) are inert without the
  card. They can stay: the fixture is more complete for them, and they are what
  a corrected version would be tested against.
* **§0.1.9's copy is superseded too.** Live no longer says "The legend lists
  cluster numbers"; it says "The legend lists numbers", and it gained `$pfOff`
  and `$pfGroups` to distinguish a figure that numbers groups this dataset does
  not have. The repo's §0.1.9 mirror is behind live in that region — read live
  before editing this page again.
* The host is `<SITE-HOST>`, not `<SITE-HOST-ALT>`. The latter was tried for hours
  while this was being written and is a different machine that answers nothing;
  see [[cnidosite-server-access]].

**One thing this does not settle.** Whether the *site* should instead ship a
`_UMAP_2` for those four entries, so the published pair is complete. That is a
different change — it means producing a figure and filing it as one of the
study's — and the evidence is against it: these 2100×1800 renders look like the
old site's own output, and `NVECT_WholeOrganism_UMAP_1.png`'s shape does not
match the deposit's own `X_umap.cells.csv` embedding. Until that is settled, a
card headed "this site's render" is the honest way to show the figure, and it is
what this change does.

### 0.1.11 The published section's cell-type panel, redrawn in the figures' own style (2026-10-02)

**Deployed.** Live `cell_atlas.php` is now `5f3f4bb5a556a57e020d39c97a5d38d8`
(was `837b085244fccf0dac825b751f51d637`); five panels were uploaded beside it.
(The first application produced `ea04c40c83727229ccdfd43dd18004b8` and was
re-applied from pristine after the defect below was found; live is
`pristine + work/patch_live_pubfig_card.py`, one source, no follow-up edits.)

This is §0.1.10's idea, corrected twice over: the card was the wrong *figure*
and, when finally drawn, the wrong *style*. Both corrections came from the
reader, and both are recorded here because the second one is a judgement about
this site's house style that the code cannot make for itself.

**The correction.** With a screenshot of the AMURI_regen page: *"所有单细胞数据要像
附图那样可视化。as published意思是像以前的UMAP图那样，而不是像附图上半部分一样"* — every
single-cell dataset must be visualised the way the attached figure is; "as
published" means in the manner of the old UMAP figures, not the manner of the
upper half of the page. Asked which of three readings was meant, the answer was
**「按发表图风格重画细胞类型图」**: in the as-published block, draw that
dataset's cell-type UMAP in the deposited figures' visual language — `umap_1` /
`umap_2` axis labels, an in-figure "UMAP by Cell Types" title, legend to the
right — from that dataset's own coordinates and labels, captioned as this site's
render rather than the publisher's figure.

So the panel is not the re-analysis render `umap_celltype.png` (which §0.1.10
and its deleted v1 pointed at), and not the section's own style either. It is a
third thing, and it needs its own renderer.

**`pipeline/08_render_pubstyle_celltype.py`** draws it into
`<dataset>/umap_celltype_pub.png`. It reads `embedding.bin` and `cellmeta.bin`
through `manifest.json` — the same exported files `07` and the viewer read — and
imports `07` by path for the palette and the export reader, so "which cells are
which type" has one implementation rather than two that can disagree.

Three decisions in it are the whole design:

* **The deposited size.** 7×6in at 300dpi is 2100×1800 — exactly the deposited
  PNGs' size. The card's two panels then scale together in the browser instead
  of one being resampled against the other, and the type sizes are chosen
  against the 2100px canvas rather than the 760px card, because the point of
  this panel is to sit beside that file.
* **This site's palette, not the study's.** The colours come from
  `web/viewer/cellatlas.css` through `07`, so a cell type keeps its colour
  between this panel, the re-analysis panel and the interactive viewer. A figure
  laid out like theirs *and* coloured like theirs could be mistaken for one of
  theirs; the panel carries a grey footnote — `CnidoSite render — this dataset's
  own coordinates and labels, not a figure the source study published.` — in the
  bottom-left corner, which is empty in the deposited figures, and that
  footnote has to stay true when the PNG is saved on its own.
* **`--all` over-produces, on purpose.** The renderer draws for every dataset
  with named labels (16 here); the *page* decides which are shown. The page's
  gate is a published-figure question — does either deposited panel already name
  the types? — and that answer lives in a PHP table keyed on the figure stem.
  Re-deriving it in Python would be a second copy of one table, free to drift
  from the first. A file no page references is cheaper than two answers to one
  question.

**The page change** (`work/patch_live_pubfig_card.py`, 7 anchored edits, md5-
guarded, refuses to write unless live is exactly the revision the anchors came
from, and refuses if its own markers are already present):

* `$pfNamed` replaces §0.1.10's `$pfNumbered` as the gate. The question is not
  whether the figure is numbered but **whether any drawn panel names the types**.
  OARBU, AMURI and the Nematostella nervous system each have a numbered figure 1
  *and* a named figure 2 — §0.1.10's condition drew a duplicate panel on three
  of them. `$pfNumbered` is still computed; it now drives the copy, not the
  presence of the card.
* `$pfCtPub` is the panel's URL, and it is built only when `!$pfNamed`, the
  dataset has cell types, **and** the dataset id matches `^[A-Za-z0-9_.-]+$`.
  The id guard is not cosmetic: the id reaches the filesystem, and a `pub:` id
  carries a colon and is served by its twin instead.
* The card is a third panel in the published grid, headed **"UMAP — cell types,
  this site's render"**, captioned by provenance — *the study's own names* where
  `$ctNamed`, *CnidoSite's own annotation* otherwise — and the section's header
  caveat says outright that one panel is not theirs.
* Three pointers, one per branch, so the reader is told what the panel is in the
  case they are in: where the published figure is another deposit's, where the
  study's names exist but its figure does not use them, and where the names are
  this site's.

**`07_render_umap.py` gained one parameter.** `wrap_legend_label(label, fits,
seps="_")` — the wrapper used to break only on `_`, which turned
`Developing cnidocytes` into `Developing cnid|ocytes`. `08` passes `"_ "`. `07`'s
own suite still passes.

**What is on the host.** `~/backups-20261002/cell_atlas.php.before-pubstyle-panel-005348`
(pristine, 43648 B). Five panels, md5s verified identical to the local renders:

| dataset | bytes | md5 |
|---|---|---|
| `NVECT_whole_adult` | 1445640 | `1a69201743efcd98dab0ebb5e56d46be` |
| `NVECT_2month` | 343894 | `52612feba6cefc3c3f1fc58f7ae55e4a` |
| `NVECT_neoplasm` | 439384 | `6f91234e5608ad44ef67c6f63a211f8f` |
| `NVECT_nervous` | 987361 | `5b38b82552073b0bddaa37edd20e546c` |
| `NVECT_tentacle` | 718883 | `f17c530e7377e2d98634fd3a0fc9c39d` |

**Two defects caught before the change was left standing, both by looking at the
rendered pages rather than at the code.**

1. **A clipped label.** The first render let `digestive_filaments` measure 1.4in
   against the 1.49in it was allowed and run to within 4px of the border. The
   width budget was `0.995` of the canvas — the physical edge. It is now
   `RIGHT_MARGIN = 0.965`, so the labels wrap with air around them: a label that
   fits by four pixels does not fit. The five uploaded files are the second
   render. This is the same failure as §0.1.8's truncated legend, one layer
   down.
2. **A pointer to a panel that is not there.** Edit 6's new clause — *"and in
   the panel drawn beside this figure"* — was written as plain text rather than
   inside the `$pfCtPub !== null` echo. Its branch is `$pfNumbered && $ctNamed`,
   which **OARBU and `pub:OARBU:SymbioticState` both satisfy and both are
   `$pfNamed`** — so they got the sentence and no panel. Found by sweeping the
   live pages for each of the three pointer strings and asking why a
   card-less page carried one; the card sweep alone would not have shown it,
   because the phantom was in the *copy*, not in the markup. Now
   `<?php echo $pfCtPub !== null ? ' and in the panel drawn beside this figure' : ''; ?>`,
   which is what the repo copy already had. OARBU reads *"…are applied in the
   cell-type panel above, and listed under **Cell types in this dataset**
   below"*.

Live was restored from the pristine backup and the patcher re-run after (2), so
the deployed file is reproducible from the script rather than being a patched
patch.

**Verified over HTTPS** (`curl --resolve cnidosite.org:443:<SITE-HOST>`, no
`/CnidoSite/` prefix):

* `?dataset=NVECT_whole_adult` — card present, `<img>` served 200,
  `application/png`, 1445640 B, md5 equal to the local file.
* `?dataset=NVECT_2month` — card present, and the caption reads *"CnidoSite's
  own annotation of this dataset's cells"*: the de_novo branch, correctly.
* A sweep of all 17 dataset ids, 6 `pub:` ids and the bare page: **exactly five
  pages carry the panel — the five above — and none of the responses contains a
  PHP fatal, warning, notice or deprecation.** `AMURI_regen` carries none, which
  is the point of `$pfNamed`: its deposited figure 2 names the types already.
* A second sweep for the three pointer strings, which is what found defect (2)
  above: `$pfOff`'s notice on `NVECT_whole_adult` and `pub:NVECT:WholeOrganism`,
  the de_novo notice on the other four, and — after the fix — the "beside this
  figure" clause on no page, since every dataset that reaches edit 6 is
  card-less. Edit 6 currently fires nowhere with a panel; it is kept because the
  branch is the right branch and the condition, not the coincidence, is what
  should decide.

**One trap in that sweep.** `?dataset=pub:AMILL`, `pub:SPIST` and `pub:OPATA`
also come back carrying a panel — but it is `NVECT_whole_adult`'s, at
`/singlecell_data/NVECT_whole_adult/umap_celltype_pub.png`, on a page byte-equal
to the bare `cell_atlas.php`. Those three are not valid `pub:` ids; an
unrecognised dataset id silently falls back to the default dataset, as
`?dataset=pub:NOPE` confirms. Nothing in this change caused that, and the real
pages (`?dataset=AMILL_whole_adult` and the rest) carry no panel.

**Suite: 210 passed, 16 failed — the same 16 as §0.1.9 and §0.1.10.** The new
case passes: `cell_atlas.php the site's cell-type render of the published
figure`, asserted on `?dataset=NVECT_whole_adult` — the fixture stages
`umap_celltype_pub.png` there because a dataset id with a colon is rejected by
the guard, so `pub:NVECT:WholeOrganism` cannot reach the card in the test
harness.

**Still open, and not settled here.** For one of the five — `NVECT_whole_adult`
— the deposited figure is *another deposit's picture*, and the card says so in
as many words (`$pfOff`; the other four carry the de_novo note instead). A
reader who wants the cell types of the deposit that figure came from still has
nothing. That is a question about what this site holds, not about how it draws.

### 0.2 The production host runs PHP 7.4 — check your syntax against it

Found the hard way: the live host is **PHP 7.4.33**, so `match`, `?->`,
`str_contains`, `readonly` and `enum` are **parse errors** there. A page that uses
one returns HTTP 500, not a warning, and the development PHP here is 8.5, so the
render tests will happily pass code that cannot run in production.

Set A shipped exactly one such construct — `match ($meta['annotation_provenance'])`
in `web/php/cell_atlas.php:162` — which made the whole option undeployable.
It is now a nested ternary. Before proposing any PHP change, lint it with the
server's own interpreter rather than the local one:

```bash
php -n -l cell_atlas.php          # on the host; -n skips the broken system php.ini
```

Use `-n`: the system php.ini carries a stray `extension=modulename` that emits a
startup warning and swallows the first line of output. All six of set A's PHP files
and all four of set B's now lint clean under 7.4.33.

**Also not verified, and worth knowing before you trust a number.** The
`reference_genome` and `genome_version` columns are empty for every dataset: no
deposit inspected so far states the assembly its reads were mapped to, and we
would rather leave the column empty than infer an assembly from the species. If
the manuscript cites a reference genome per dataset, that mapping has to come
from the source publications.

### 0.3 The `?species=` repair, and how it was checked (2026-09-18)

The module went live reading only `?dataset=`. The site's own navigation does not
send that parameter: `includes/modlinks.php` links every module page as
`?species=<abbr1>` — `cell_atlas.php?species=NVECT` and the same for the marker,
expression and index pages — while the species portal sends the latin binomial.
Every one of those links was therefore ignored and the page opened the default
dataset, i.e. **another species' data under that species' URL**, with nothing on
the page saying the species had been dropped.

Five files were re-deployed to fix it (`sc_common.php`, `cell_atlas.php`,
`cell_marker.php`, `gene_exp.php`, `sn_data.php`). `sc_common.php` gained
`sc_requested_species()`, `sc_species_index()` and the two "we have nothing for
that species" notices; the parameter is resolved from a five-letter code, the
`abbr` code or the latin binomial, before any default applies.

How that was verified, in the order it was done:

1. `php -n -l` on all five under the host's own **PHP 7.4.33** (section 0.2).
2. Eleven new cases in `pipeline/tests/test_php_render.py`, all passing. The
   assertion is not the species name — the dataset picker lists every species, so
   the name is on the page either way — it is which `<option>` carries
   `selected="selected"`, which is the dataset the page actually opened.
3. **Mutation test**: disabling the guard (`$spMiss = false`) makes the
   *Aurelia coerulea* case fail, both the missing notice and the forbidden
   selection. So the case has teeth rather than passing vacuously.
4. Live requests over Apache/PHP 7.4/mysqlnd: `?species=HVULG` selects
   `HVULG_siebert_atlas` and does **not** select the old default;
   `?species=ACOER` (a species in the single-cell index with no atlas figure)
   renders the notice; `?species=NVECT` says "Showing the first of 5 datasets";
   `gene_exp.php?species=NVECT` says no matrix exists and points at the marker
   table. All HTTP 200, no PHP diagnostic.

Rollback copy of the five pre-repair files:
`backup-pre-species-link-20260918-230128/`, with an `MD5SUMS` that verifies from
the document root (`md5sum -c backup-pre-species-link-20260918-230128/MD5SUMS`).

---

## 1. What to copy where

> **Deployed 2026-09-18.** This install has been carried out against
> `cnidosite.org`. The ten files below are in the document root
> (`/var/www/html/CnidoSite`), byte-identical to `web/php/` and `web/viewer/`,
> and the five `singlecell_*` tables are loaded. All six pages return HTTP 200
> through the real Apache/PHP 7.4/mysqld stack with no PHP diagnostic, and each
> PHP file was linted there with `php -n -l`. A rollback copy of the four
> superseded pages is in `backup-pre-A-20260918-224421/`. The section is kept
> as instructions because a re-deploy or a second site still needs them.
>
> **Data refreshed 2026-09-20.** The files are unchanged — no PHP file has been
> touched on either date — but the tables and `singlecell_data/` now carry all
> twelve re-analysed datasets rather than the two that the 2026-09-18 deploy
> happened to include. See §0.1.1 for what was run and how it was verified.
>
> **The module is what runs; `bundle/B-in-place-patch/` does not.** Those four
> root-level pages were the whole of the live site before this deploy and are
> now historical.
>
> **Repair, 2026-09-18 (after that deploy).** The module read only `?dataset=`,
> so the site's own navigation was silently ignored: `includes/modlinks.php`
> links every module page as `?species=<abbr1>` (and the species portal sends the
> latin binomial), and each such link opened the *default* dataset — another
> species' data under that species' URL. Five files were re-deployed with that
> fixed (`sc_common.php`, `cell_atlas.php`, `cell_marker.php`, `gene_exp.php`,
> `sn_data.php`); `sc_common.php` gained `sc_requested_species()`,
> `sc_species_index()` and the two notices. Rollback copy of the five
> pre-repair files: `backup-pre-species-link-20260918-230128/`, with an
> `MD5SUMS` that verifies from the document root. `pipeline/tests/test_php_render.py`
> gained eleven cases for the parameter, including a mutation-checked negative
> (a species with no data must leave *no* dataset selected) — see section 0.3.

> **Read this first. There are two page sets in this repository and they
> collide.**
>
> | | `web/php/` — the module | the four pages at the repository root |
> |---|---|---|
> | What it is | a new module with its own bootstrap | patches to the *existing* site pages |
> | Needs the new SQL | yes — 5 new tables | **no** — uses only tables the site already has |
> | Needs `singlecell_data/` | yes, ~96 MB (12 datasets) | no |
> | Interactive UMAP viewer | **yes** | no — keeps the static PNGs |
> | Fixes deep links / `DynamicOptionList` empty selects / cross-module links | no | **yes** |
> | Verified by | `test_php_render.py` | `test_php_render_site.py` |
>
> Both define `cell_atlas.php`, `cell_marker.php`, `gene_exp.php` and
> `sn_data.php` in the same web root. **Deploy one or the other, not both** —
> the second copy overwrites the first.
>
> The module is what answers Referee 1's opening complaint, that single-cell
> data are shown only as static, non-interactive images, and it is the only one
> of the two that does. The root-level pages fix a different set of points
> (Referee 2 major 1, Referee 1 minor 1 & 3, Referee 3 points 10a and 10g)
> inside the site's existing architecture, and they can go live on their own
> without loading any new tables.
>
> If you want both, the merge is: take the module's `cell_atlas.php` and give it
> the root page's `cnido_state()` / `cnido_qs()` deep-link handling. That is
> not done here.

The rest of this section describes the **module**. Its file layout is:

Copy into the CnidoSite web root (the directory that currently holds
`sn_data.php` and `gene_exp.php`):

| From | To | Notes |
|---|---|---|
| `web/php/sc_common.php` | `sc_common.php` | shared bootstrap — required by all pages. **This file emits the whole site navigation, so it goes stale whenever the site's nav is edited. Diff the live copy before deploying and apply the difference as a patch, not as a copy** — see the `_export_index.json` note in §0 and the md5-gated patching in §0.1.2; on 2026-09-21 a straight copy would have deleted a live nav link |
| `web/php/cell_atlas.php` | `cell_atlas.php` | new page |
| `web/php/sc_manual.php` | `sc_manual.php` | new page |
| `web/php/sc_pages.css` | `sc_pages.css` | new |
| `web/php/cell_marker.php` | `cell_marker.php` | replaces the existing page |
| `web/php/gene_exp.php` | `gene_exp.php` | replaces the existing page |
| `web/php/sn_data.php` | `sn_data.php` | replaces the existing page |
| `web/viewer/` | `viewer/` | new directory: `cellatlas.js`, `cellatlas.css` |
| `web/singlecell_data/` | `singlecell_data/` | new directory: the viewer's data |

Then load the database:

```bash
mysql -u USER -p cnidaria < sql/singlecell_schema.sql   # creates 5 tables
mysql -u USER -p cnidaria < sql/load_singlecell.sql     # fills them
```

`singlecell_schema.sql` only creates tables and never drops one, so it is safe
to run on a live database, and safe to re-run. It uses `CREATE TABLE IF NOT
EXISTS`, so on a database where these five tables **already exist** from an
earlier revision it will leave them untouched rather than migrate them. This is
a first install, so that does not arise — but if you have already loaded an
earlier copy of the schema, drop the five `singlecell_*` tables first.

Note that `singlecell_qc` deliberately has **no** foreign key onto
`singlecell_atlas`. QC figures describe the source dataset, which exists whether
or not a count matrix was downloadable; 23 of the 35 datasets have a QC row and
no atlas row, and a foreign key would force exactly the omission this table
exists to avoid. `singlecell_celltype` and `singlecell_markers` do keep their
foreign keys, because they genuinely cannot exist without an atlas row.

**Check first:** `sc_common.php` opens the database with `mysqli` using the
credentials in `sc_conn()` (`jackie` / `jackie` / `cnidaria` locally). Change
those to match the server if they differ. The file is at the top of
`sc_common.php` and is the only place credentials appear.

### Replacing `sn_data.php`

The new `sn_data.php` reads the pre-existing `singlecell` table exactly as the
old one did, so your data is untouched. It adds two columns — **Quality
Control** and **Analyse** — and it matches its rows to viewer datasets by the
`singlecell_atlas_map` table loaded above.

**If you edit the row order of the `singlecell` table, update
`singlecell_atlas_map` to match.** That table is what prevents the index and the
atlas from disagreeing; it is the fix for Referee 1's Major 4.

### The alternative: the four in-place patches

`cell_atlas.php`, `cell_marker.php`, `gene_exp.php` and `sn_data.php` at the
repository root are drop-in replacements for the pages the site already has.
Copy them straight over the existing files, alongside the site's own
`includes/state.php` and `Webpage_components.php`, which they use and which
this repository does not contain. No SQL to load, no assets to upload, no
`viewer/` directory.

They require three columns/values on the live site that the module does not:

- `abbr.abbr1` — the five-letter species code, used to build every deep link;
- `singlecell` columns named `Species`, `Emb`, `Stage`, `CellNumber`, `Project`;
- one `<abbr1>_cellmarker` table per species, with `gene`, `tissue_dev` and
  `celltype`. **`tissue_dev` values must not contain a slash** — the atlas
  image path is built from the value as `images/<ABBR>_<tissue_dev>_UMAP_1.png`,
  so `Adult tissues/organs` would name a subdirectory, not a file. The one
  exception is the literal `Whole adults`, which is special-cased to
  `images/<ABBR>_UMAP_1.png`.

The atlas still shows the pre-rendered PNGs; what the patch adds is the
detection that two of them are byte-identical, so the Nematostella tentacle
page says so instead of showing the same UMAP twice (Referee 3 point 10g), and
a legend naming the clusters that *are* named.

---

## 2. Verifying the install

Open, in this order:

1. `sn_data.php` — the Quality Control and Analyse columns should be populated
   for datasets that have an atlas, and say *not re-analysed* for those that do
   not.
2. `cell_atlas.php` — the UMAP should render and respond to dragging. If it is
   blank, open the browser console; the page also prints errors into the text
   under the viewer.
3. `cell_marker.php` — click a gene; it should open `gene_exp.php` coloured by
   that gene.
4. `gene_exp.php` — type a gene from the autocomplete list.
5. `sc_manual.php` — the user manual.

If a page reports that the atlas has not been published, the SQL has not been
loaded, or `singlecell_data/` is not in the web root.

### Test through `cnidosite.org`, not the IP address

The public site is served from its **own** vhost
(`/etc/apache2/sites-enabled/cnidosite-ssl.conf`): `ServerName CnidoSite.org`,
`DocumentRoot /var/www/html/CnidoSite`. `https://<SITE-HOST>/...` — with or
without a `/CnidoSite/` prefix — lands on the **default** vhost, whose
DocumentRoot is `/var/www/html`, and the pages it returns are not the site's:

```bash
# right
curl --resolve cnidosite.org:443:<SITE-HOST> https://cnidosite.org/cell_atlas.php?dataset=NVECT_embryo
# wrong: default vhost, DocumentRoot /var/www/html
curl https://<SITE-HOST>/CnidoSite/cell_atlas.php?dataset=NVECT_embryo
```

The wrong one 301s, 404s on `/singlecell_data/…`, and makes `gene_exp.php` answer
"(0 genes)" or "No expression data" for **every** gene — because
`gene_exp.php` resolves `__DIR__ . '/../singlecell_data/'`, and under the default
vhost that path does not exist. Two consequences worth remembering: a negative
result from an expression page proves nothing until the host name is right, and
the off-by-one in that `__DIR__` expression is latent rather than absent — the
correct depth is the one `includes/sc_gene_ids.php` uses.

### Checking the shipped evidence adds up

Two checks that need no server, both of which found real problems on 2026-09-20:

```bash
# the invariant: every removed cell is attributed to a criterion.  Reads both
# trees, prints the datasets that do not add up and how far off they are.
python -c "
import glob, json, os, sys
for d in ('data/qc', 'bundle/evidence'):
    bad = []
    for p in sorted(glob.glob(os.path.join(d, '*.qc_stats.json'))):
        s = json.load(open(p))
        crit = sum(int(v) for v in (s.get('cells_removed_by_criterion') or {}).values())
        raw, after = s.get('n_cells_raw'), s.get('n_cells_after_cell_qc')
        if raw is not None and after is not None and crit != raw - after:
            bad.append(os.path.basename(p) + ': %d vs %d' % (crit, raw - after))
    print(d, len(bad), 'violation(s)')
    for b in bad: print('   ', b)"

# the same invariant where a reader can see it: the exported manifests, whose
# qc_summary.cells_removed_by_criterion the viewer prints as "Cells removed by"
python -c "
import glob, json
for p in sorted(glob.glob('web/singlecell_data/*/manifest.json')):
    q = json.load(open(p))['qc_summary']
    s = sum(q['cells_removed_by_criterion'].values())
    assert s == q['n_cells_raw'] - q['n_cells_after_cell_qc'], p
print('12 manifests, invariant holds')"

# the summary files match the per-dataset records beside them, byte for byte
python bundle/evidence/assemble_summaries.py --check
```

### If the viewer does not render

- **Blank canvas, no error** — check that `singlecell_data/<id>/embedding.bin`
  is being served (fetch the URL directly; a 404 returns an HTML error page,
  not binary).
- **404 on `viewer/cellatlas.js`** — the `viewer/` directory is not in the web
  root.
- **`gzip` files served as downloads** — the expression files are `.bin.gz` and
  must be served with `Content-Encoding: gzip`. Most Apache installs do this
  from the `.gz` extension; if not, add to `.htaccess`:

  ```apache
  <FilesMatch "\.bin\.gz$">
    ForceType application/octet-stream
    Header set Content-Encoding gzip
  </FilesMatch>
  ```

  `mod_headers` is the only module this module needs.

---

## 3. Adding a dataset

The pipeline is dataset-driven — adding one is a config row plus three
commands, and no code change.

1. **Stage the counts** under `data/raw/<local_input>/` — a 10x directory (with
   or without Cell Ranger's canonical filenames), a MatrixMarket triplet, an
   `.h5ad`, or a Seurat `.rds`.

2. **Add a registry row** to `meta/dataset_registry.tsv`. Set
   `cnidosite_row` to the row number this dataset occupies on `sn_data.php`, or
   **leave it empty** if it is not on `sn_data.php` yet — an empty value means
   no map row is written, which is what you want for a dataset that is
   processed but not yet listed. `reference_genome` and `genome_version` are
   free text; leave them empty rather than guessing. `cell_type_table` names a
   per-cell published table when the study ships one (that route is
   `published`); `marker_panel` is the escape hatch for the case where it ships
   neither a table nor symbols — a deposit keyed by gene model IDs. Write the
   panel as `<label>\t<pattern>\t<genes>` rows, anchor each pattern to whole
   IDs (`^(NV2\.11441|NV2\.11442)$` — `NV2.5` is a prefix of `NV2.555`), keep
   every group at two genes or more (below `MIN_MARKER_HITS` it can never name
   anything), and let `pipeline/tests/test_marker_panel.py` check the file's
   internal consistency. Leave the column empty for every dataset that does not
   need it: an empty panel compiles to exactly the global panel, so no other
   dataset's annotation shifts.

3. **Run the pipeline:**

   ```bash
   source pipeline/env.sh                # required; see the note below
   $PY pipeline/03_qc.py             --dataset <ID>
   $PY pipeline/04_cluster_annotate.py --dataset <ID>
   $PY pipeline/05_export_web.py     --dataset <ID>
   $PY pipeline/07_render_umap.py    --dataset <ID>   # the atlas-page figures
   $PY pipeline/06_aggregate_qc_table.py
   ```

   The last command regenerates the QC table, the Markdown, and
   `sql/load_singlecell.sql` for **all** datasets. Reload that SQL and the new
   dataset appears on the pages.

   `07_render_umap.py` is the only optional stage: it draws the two static
   panels `cell_atlas.php` shows beside the interactive viewer, reading the
   files `05` wrote rather than the h5ad. A dataset without them simply has no
   figure block — the page checks for the files, so there is nothing to keep in
   sync by hand. Run it before `06`, or any time after `05`; it rewrites only
   its own two files.

   > **`source pipeline/env.sh` is not optional.** The pipeline needs scanpy,
   > scrublet, harmonypy and leidenalg, which this project vendors in
   > `.pylibs/`. Without `PYTHONPATH` pointing there, scrublet and harmonypy
   > fail *silently* — the run records "no doublets removed" and
   > "integration: none" and still produces a complete-looking atlas. The
   > script warns if the imports fail.

4. **Per-dataset QC.** Where the source publication states per-cell thresholds,
   put them in the registry so they are reproduced exactly. Where it does not,
   the pipeline falls back to per-library MAD outlier detection. Every figure it
   applies is written to `data/qc/<ID>.qc_stats.json` and surfaced on the page —
   nothing is entered by hand.

---

## 4. Known gaps

These are recorded here so they are not rediscovered as surprises. They are also
stated in `docs/referee_response_singlecell.md`.

> **The referee letter is now behind this section and must not be sent as it
> stands.** Its mtime is 2026-09-20, before the four annotation deploys recorded
> above. The specific claims that are no longer true: its answer to Referee 3
> says cell-type names for the six cluster-only datasets are "**not asserted, and
> not assertable from these deposits**" (all six are now named, and only one —
> the embryo — is partly so), and it reports "six of the twelve inherit … the
> names of their source study". It also predates the `de_novo` labels arriving at
> all, so its account of what the site asserts is out of date in the direction
> that *understates* the site. Updating it is a scientific-writing job on a
> deliverable that goes to referees, so it is deliberately not rewritten here —
> left for the site owner to commission with the new numbers in hand from §0.1.4
> –§0.1.7 and the bullets below.

- **16 of 35 datasets are processed end to end; 19 are not.** (The atlas holds
  17 rows: the 16 plus `ACOER_lifecycle`, which is live-only.) Measured on the
  published table's own `analysis_status`, the 19 are 15 "count matrix not
  retrieved", 2 "per-sample matrices deposited; cross-sample merge not yet
  implemented", 1 "deposit holds normalised values, not counts" (HSYMB_body1)
  and 1 "genes as an index map plus per-sample archive" (ACOER_gastrodermis).
  Two caveats on reading that column: the registry's own
  `not_processed_reason` is *blank* for the 15, and it is the table's fallback
  wording that names the cause — so the reason a reader sees is not always a
  reason a curator wrote; and because that fallback is uniform, an scATAC or
  Rdata-object deposit is no longer distinguishable in the table from one whose
  matrix simply was not retrieved, which an earlier version of this bullet
  listed separately. Where the gap is ours rather than the deposit's the row
  says so (the two per-sample cases, and the index-map case). No row is blank.
- **Every dataset in the atlas now carries cell types — 11 `published`, 6
  `de_novo`, 0 `cluster_only` (as of 2026-09-28).** It was not always so: six
  datasets used to ship clusters only, and this bullet used to read "of the 12,
  six carry cell types and six do not". Each of the six was resolved by its own
  route, and the routes are worth keeping apart because they carry different
  weights of evidence:
  * six datasets (AMILL_whole_adult, NVECT_bodywall, NVECT_gastruloid,
    NVECT_notch48h, OPATA_whole_adult, SPIST_whole_adult) inherit their names
    from the source study's own per-cell table — `published`, nothing inferred;
  * `HVULG_siebert_atlas` from the study's own Single Cell Portal clusters
    (§0.1.5), `NVECT_gastrula` from the study's own UCSC Cell Browser table
    (§0.1.6) — also `published`, and in both cases checked cell-for-cell against
    the deposit before the names were attached;
  * `NVECT_2month`, `NVECT_neoplasm`, `NVECT_tentacle`, `NVECT_nervous` and
    `AMURI_regen` named `de_novo` by label transfer, with a score floor, a
    tie margin and a generic-state filter (§0.1.4);
  * `NVECT_embryo` named `de_novo` from a marker panel written in its own ID
    space (§0.1.7) — and only 5 of its 17 clusters, the rest deliberately left
    as `Cluster N`.
  `NVECT_whole_adult` and `ACOER_lifecycle` were never in the cluster-only set,
  and the live `OARBU_symbiotic` is another session's `published` build.
  A `de_novo` label is an inference and the pages say so ("Assigned de novo from
  a cnidarian marker panel", with the caveat underneath); it is not presented
  the way an inherited name is. The honest remainder is the embryo: 12 of its 17
  clusters are still unnamed, on 不猜 grounds, and that is visible on the page.
- **HSYMB_body1 is refused, not pending.** GSE269914 deposits normalised
  expression values (`%%MatrixMarket matrix array real`, negative values), not
  counts, so it cannot be re-analysed from the deposit. The loader now detects
  this in 2.4 s; before the guard existed it expanded a 199,113 × 18,061 dense
  array — about 29 GB — and cost a real run two hours and 166 GB to discover.
- **The panel-vs-ID-space obstacle, and how it was closed.** `CNIDARIAN_MARKERS`
  matches gene *symbols*, and five datasets keyed their features by gene model ID
  instead — `NV2.x` (NVECT_2month, neoplasm, tentacle, embryo) and `NVE#####`
  (NVECT_gastrula). Measured with the panel's own matcher, **0 of the ranked
  features in each of those five matched any of the twelve patterns**, so
  `MIN_MARKER_HITS` could never be reached. (The Hydra atlas was a near miss: 1392
  of its 2100 ranked names are composite `g####.t1|BLAST-HIT` and the panel
  recognised 6 of the 2100, but those are three distinct genes spread one per
  cluster, so no cluster collected two hits.) All five are now named, and not by
  loosening the panel:
  * two got labels from outside the panel altogether — NVECT_gastrula from the
    study's own UCSC table (§0.1.6), the Hydra atlas from the study's own portal
    clusters (§0.1.5) — so no new inference was needed at all;
  * the other four (2month, neoplasm, tentacle) plus nervous and AMURI_regen were
    named through the RefSeq `XP_` bridge the site already uses for gene display
    (§0.1.4);
  * `NVECT_embryo` got a **dataset-scoped panel** written in its own ID space
    (§0.1.7) — the general fix for this obstacle, available to any future deposit
    that keys by model ID.
  GSE302686 (NVECT_embryo) remains the severest case and is the one place the
  result is visibly partial: it *also* ships no cell-type column (its 13 metadata
  columns hold only QC metrics, cell-cycle phase and the authors' timepoint-
  labelled clusters), so there was neither a label to inherit nor a symbol to
  match. 5 of its 17 clusters carry a name; the other 12 publish as `Cluster N`
  with a caveat saying they are unnamed rather than found to be something else.
  This is the honest answer to Referee 3: for 16 of 17 datasets the cell types are
  now identified, and for the embryo they are identified only where the evidence
  supports it.
  **Historical note (decided 2026-09-20, superseded 2026-09-28).** This bullet
  used to say the datasets were "left cluster-only on purpose" pending a decision
  about acceptable inference, and that naming the Hydra atlas through its
  BLAST-transferred symbols "is a homology-transfer workstream, not started". The
  site owner has since decided: the Hydra atlas was named from the study's own
  published clusters (not from homology), and the two remaining Nematostella
  datasets from the study's own tables or a panel of the study's own markers. The
  distinction the earlier note was protecting still holds and is now enforced in
  the data: a name that came from the study is `published`, and a name the site's
  own panel produced is `de_novo` with an `auto:` prefix and a caveat on the page.
- **Xenia is missing and cannot be added from a deposited matrix, because there
  is none.** PRJNA548325 holds 27 runs of which 6 are single-cell, and
  PRJNA869069 holds 2 more — but GEO has no Xenia series at all (only GPL37475,
  a platform stub with no samples), so what is public is raw reads. Adding Xenia
  means running Cell Ranger on the fastq and then this pipeline. Verified
  run-level detail is in `meta/coverage_gaps_found.tsv`.
- **Hydra is still under-represented**, though less so: PRJNA497876 is now
  included, and GSE300723 / GSE193277 are identified but not yet inspected.
- **`reference_genome` / `genome_version` are empty** for every dataset: no
  deposit inspected states which assembly was used, and we will not infer one
  from the species.
- **Every doublet rate in the table is an order of magnitude below what a raw
  10x run gives, and none of them should be read as a doublet rate.** The
  highest is HVULG at 0.43% (111 cells of 25,549); the rest run 0.00–0.15%
  (AMILL 0.04%, NVECT_notch48h 0.03%, NVECT_gastruloid 0.05%, NVECT_embryo
  0.15%). A raw 10x run gives 5–10%, so *no* dataset here looks like an
  unfiltered measurement — the caveat is not specific to the datasets that are
  obviously pre-filtered.

  Per-library detection demonstrably ran (the per-library cell counts are
  recorded and the calls are non-zero), so this is not a silent no-op, and the
  likeliest explanation is that these deposits are the authors' own
  doublet-filtered objects, so Scrublet is measuring the residue. We have not
  proven that for every dataset, so the honest position is: the numbers are
  recorded as computed and shown with the method beside them, but they must not
  be quoted as evidence that any dataset is doublet-free, and they must not be
  compared against a raw-run expectation. If a reader needs a real doublet rate,
  it has to come from re-running Scrublet on unfiltered barcodes, which is not
  what most of these deposits contain.
- **The Hydra atlas has no computable MT% filter.** Its annotation contains no
  recognisable mitochondrial genes (`mito_genes_n = 0`), so
  `mito_filtering_available = 0` and the QC panel says so. Any manuscript
  sentence that reports an MT% threshold for this dataset would be wrong.
- **~~One live figure truncates its longest legend label: `NVECT_whole_adult`.~~**
  **Repaired 2026-09-29 — see §0.1.8.** Found 2026-09-28 while re-running the
  suite after the gastrula/embryo deploys; it is *not* caused by them and
  predates them (the manifest is from 09-26). The cell-type legend is anchored at
  `x = 0.645` of a 9in canvas and text is drawn at `LEGEND_PT = 9.4` with no
  wrapping, so the published label `gastrodermis_muscle_parietal_circular_prog`
  plus its count ran past the canvas. Rendered and inspected, the live figure
  read `..._circular_pro` with the cell count gone entirely and the ink stopping
  1px from the edge. It was the only dataset affected — the next-longest label
  anywhere is `neurosecretory_progenitors` — and the two datasets deployed on
  09-28 were well inside the margin. **The numbers in this bullet were
  rewritten on 09-29**, because the "3.59in of a 3.19in margin" it originally
  quoted came from the same flawed estimate that let the overflow ship: measured
  glyphs, the label alone is 2.92in, and what actually overran was the *entry* —
  the label with ` (11,588)` on the line, plus the 0.37in the legend's marker
  column takes before the first glyph, which the estimate did not model at all.
  The page was never affected: it draws the PNG as an image, so the truncation
  was only ever in the picture, not in the data or the interactive viewer.

---

## 5. File inventory

```
web/php/
  sc_common.php      bootstrap, DB access, navigation, dataset picker
  cell_atlas.php     the atlas page (viewer + cell types + full QC panel)
  cell_marker.php    marker table with feature/violin cross-links
  gene_exp.php       gene expression page
  sn_data.php        the index (replaces the existing page)
  sc_manual.php      user manual
  sc_pages.css       page styles

web/viewer/
  cellatlas.js       the viewer (canvas, no dependencies)
  cellatlas.css      palette as CSS custom properties, light and dark
  index.html         standalone demo page, not part of the site

web/singlecell_data/<dataset_id>/
  manifest.json      metadata, category tables, QC summary, provenance
  embedding.bin      float32[n x 2] UMAP
  cellmeta.bin       uint16[n x 3] cell type / cluster / library
  qc.bin             float32[n x 3] counts / genes / MT%
  composition.json   cell counts per type per library
  markers.json       ranked markers per cell type
  genes.json         index of genes with an expression file
  expression/*.bin.gz  sparse per-cell expression, one file per gene
  umap_cluster.png   the static figure for the atlas page, from 07_render_umap.py:
                     coloured by Leiden cluster, every cluster numbered at its
                     own centroid.  Present for every re-analysed dataset
  umap_celltype.png  the same cells coloured by cell type, with the type names
                     and counts in a legend.  Only for `cell_type_mode: named`
                     datasets -- a cluster-only dataset has no names to show

sql/
  singlecell_schema.sql   5 tables; creates only, never drops
  load_singlecell.sql     generated by pipeline/06_aggregate_qc_table.py

pipeline/
  00_harvest_sra_metadata.py   accession + library metadata from NCBI
  03_qc.py                     load, filter, doublet removal
  04_cluster_annotate.py       normalize, HVG, PCA, Harmony, Leiden, UMAP, markers
  05_export_web.py             write the viewer assets (--reindex rebuilds
                               the index without re-exporting)
  06_aggregate_qc_table.py     the QC table, the Markdown, and the load SQL
  07_render_umap.py            the two static UMAP panels the atlas page shows
  env.sh                       required environment (see section 3)
  lib/                         io.py (matrix loaders), mito.py and summary.py
                               (the merge-by-dataset_id batch summaries), shared
                               by the numbered scripts
  tools/
    rds_to_mtx.R               Seurat RDS -> mtx, for deposits that ship RDS
    merge_samples.py           join per-sample matrices for deposits that ship
                               one matrix per sample
  tests/                       14 files, 369 checks on a machine with no
                               database; the two php/ render files need a live
                               connection and their fixtures, and are where the
                               older 476-check figure came from. 2 assertions
                               fail in this tree, both pre-existing and both in
                               test_integration_record.py (section 0.1.5)
    test_io_dense.py           dense genes x cells tables, and the missing
                               header field every one of them has
    test_io_ids.py             count-matrix id parsing
    test_io_mtx_guard.py       pre-flight guard against `array real` MatrixMarket
    test_marker_panel.py       the twelve observed marker-panel false positives,
                               and the per-dataset panel: union semantics,
                               anchor stripping, whole-ID matching, the loader's
                               refusals, and the deployed NVECT_embryo panel
                               checked against its own documentation column
    test_export_urls.py        expression-file URL round trip (the 404 defect)
                               and the published index's `dir`
    test_export_reproducible.py  the export is byte-reproducible (the header stamp)
    test_summary_merge.py      the QC and annotation summaries are merged by
                               dataset_id, not replaced by the last run
    test_qc_thresholds.py      the threshold-mode invariants
    test_sample_inference.py   library-label recovery from barcodes
    test_integration_record.py the QC stage declares integration, the annotate
                               stage observes it, and the readers show what ran
    test_php_render.py         renders the module's pages against a real database
    test_php_render_site.py    renders the four in-place patches
    test_render_umap.py        the static figures: the palette is the
                               stylesheet's, no category is given another's
                               colour, the text is sized for the card, the
                               legend's measured margin holds for every one of
                               the 287 published labels, the wrap's break rules,
                               and the bytes are reproducible
    php/mysqli_shim.php        mysqli stand-in backed by the mariadb CLI
    php/render_one.php         renders one module page per process
    php/render_site_page.php   stages and renders one in-place patch
    php/make_fixture_images.py writes the published-figure fixtures
    php/make_fixture_data.py   stages the per-dataset figure fixtures the atlas
                               page looks for
    php/site_stubs/            test-only stand-ins for the site's own includes
    php/fixture_site_tables.sql  fixture for the site's own singlecell/abbr/
                               <abbr1>_cellmarker tables

the four in-place patches (see section 1)
  cell_atlas.php     detection of identical UMAPs, cell-type legend
  cell_marker.php    marker table, feature/violin cross-links
  gene_exp.php       gene expression, no broken images
  sn_data.php        the index

meta/
  dataset_registry.tsv      one row per dataset; the pipeline's only config.
                            Its 19th column, `marker_panel`, names a per-dataset
                            marker panel for a deposit whose features are gene
                            model IDs; unset for every other dataset
  marker_panels/            the per-dataset panels themselves, and the
                            provenance JSON each build writes beside it
    NVECT_embryo.tsv        NVECT_embryo's panel: 6 groups, 44 NV2 gene IDs,
                            anchored so `NV2.5` cannot fire on `NV2.555`
  qc_dataset_table.tsv      the dataset-level QC table (the referee deliverable)
  qc_dataset_table.md       the same table rendered for the response letter
  qc_thresholds.tsv         the per-dataset threshold values behind it
  sra_metadata.json         accession + library metadata (10 MB, harvested by 00_)
  sra_metadata.tsv          the same metadata as a table
  coverage_gaps_found.tsv   verified accessions for the Xenia / Hydra gaps

docs/
  referee_response_singlecell.md   the response letter text
  asset_format.md                  the downloadable-file format specification
```

## 6. The test MariaDB server

`pipeline/tests/test_php_render.py` renders the five PHP pages against a real
database. No PHP build in this development environment has `mysqli`, so
`pipeline/tests/php/mysqli_shim.php` stands in for the extension and forwards
every statement to the `mariadb` CLI. Nothing else is mocked: the schema, the
SQL, the fixture data and the rendered HTML are real.

The server is not part of this repository and is not installed system-wide. Any
matching MariaDB will do; 11.4.5 is what has been used:

```sh
cd /tmp
curl -sSLO https://archive.mariadb.org/mariadb-11.4.5/bintar-linux-systemd-x86_64/mariadb-11.4.5-linux-systemd-x86_64.tar.gz   # 349 MB
mkdir -p /tmp/mariadb && tar xzf mariadb-11.4.5-linux-systemd-x86_64.tar.gz -C /tmp/mariadb
# extract WITHOUT --strip-components: the test's default path expects the
# versioned directory to survive.
mkdir -p /tmp/mariadb/mariadb-11.4.5-linux-systemd-x86_64/run
cd /tmp/mariadb/mariadb-11.4.5-linux-systemd-x86_64
./scripts/mariadb-install-db --no-defaults --basedir=$PWD --datadir=$PWD/data \
  --auth-root-authentication-method=normal
./bin/mariadbd --no-defaults --basedir=$PWD --datadir=$PWD/data \
  --socket=$PWD/run/my.sock --skip-networking &
./bin/mariadb-admin --no-defaults --socket=$PWD/run/my.sock -u root ping
```

Then, from the repository root:

```sh
source pipeline/env.sh && $PY pipeline/tests/test_php_render.py
```

The test drops and recreates the `cnidaria` database on each run, so it never
touches production data — but note the name and credentials it uses are
production's own (`cnidaria`, `jackie`/`jackie`), because `sc_common.php`
hardcodes them and the point is to exercise the real path. **Run it against the
throwaway server above, never against the production host.**

An extraction that used `--strip-components=1` leaves the binaries one level up
from the default; either extract without it, or point the test at where they
actually are:

```sh
SC_TEST_MARIADB=/tmp/mariadb/bin/mariadb $PY pipeline/tests/test_php_render.py
```

Environment overrides: `SC_TEST_PHP` (default
`/home/$USER/miniconda3/envs/phplint/bin/php`, development PHP only — the
production interpreter is checked separately, see §0.2), `SC_TEST_SOCKET`,
`SC_TEST_MARIADB`.

**Expect failures on a fresh run**, and read them before believing them. The
fixture database is a live dump refreshed by hand (`sql/load_singlecell.sql`,
currently 2026-09-27 00:24), so a dump newer than the suite's expectations turns
cases that were passing into cases that are merely unreachable, and the two look
identical in the output. §0.1.9 records 16 such failures and how they were
attributed. Before treating a failure as a regression, re-run with the change
disabled and see whether the failure set moves.
