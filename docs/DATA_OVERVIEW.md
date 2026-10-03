# What is in the CnidoSite database

Every number below was read from the production database on 2026-10-02 with the query shown
next to it. Where two different numbers are in circulation for the same thing, both are
given and the difference is explained — the site itself displays several of these with a
definition that is narrower than the number's name suggests.

The database is MySQL 8.0.46, schema `cnidaria`, **1,541 base tables and 2 views**,
**178.0 GB** on disk.

```sh
# size and object count
SELECT COUNT(*), ROUND(SUM(data_length+index_length)/1024/1024/1024,1)
  FROM information_schema.tables WHERE table_schema='cnidaria';   -- 1543 | 178.0
SELECT table_type, COUNT(*) FROM information_schema.tables
  WHERE table_schema='cnidaria' GROUP BY table_type;              -- BASE TABLE 1541 | VIEW 2
```

Queries in this file are written for the CLI helper in this repository:

```sh
export CNIDO_DB_HOST=localhost CNIDO_DB_USER=... CNIDO_DB_PASS=... CNIDO_DB_NAME=cnidaria
php scripts/setup/dbq.php "SELECT COUNT(*) FROM classfy"      # -> 326
```

`dbq.php` prints one row per line, tab-separated, with a header. It reads the four
connection values from the environment, so no credentials are stored in the repository
(see [`DEPLOYMENT.md`](DEPLOYMENT.md) §2). On the vendor's PHP 7.4 the CLI `php.ini` is
broken and must be named explicitly — `PHP_INI_SCAN_DIR=/etc/php/7.4/apache2/conf.d
php -c /etc/php/7.4/apache2/php.ini scripts/setup/dbq.php "<SQL>"`.

---

## 1. Species catalogue

The site covers all seven classes of Cnidaria.

```sql
SELECT COUNT(*) FROM classfy;            -- 326
```

**Four different species counts are in play and they are not interchangeable.** Quoting the
wrong one is the most common way to get a wrong number out of this database:

| count | meaning | where |
|---|---|---|
| **326** | species in the catalogue (`classfy`) | site-wide |
| **148** | species with a gene annotation loaded (`core_species.cnidarian`) | `SELECT SUM(cnidarian) FROM core_species` |
| **145** | species that have a `<ABBR>_locus` table | `SELECT SUM(table_name LIKE '%\_locus') FROM information_schema.tables WHERE table_schema='cnidaria'` |
| **60** | genomes meeting the BUSCO-90% bar (`core_species.busco90`) | `SELECT SUM(busco90) FROM core_species` |

`core_species` itself holds **153** rows — 148 cnidarians plus the 5 outgroups used in the
species tree. The tip count of the published tree, 153, is this table's row count; the
"148" quoted in the paper is the cnidarian subset.

Two further cautions:

* **Tip identity.** The outgroups are the 5 non-cnidarian rows of `core_species`;
  *Trachythela* is a cnidarian and is **not** an outgroup.
* **Two pairs of species entries are the same organism under two names.** A duplicate scan
  over `classfy.NCBI` and `speciesinfo.Genome_Assemble` finds them exhaustively. Deleting
  a row is not free: removing one of the pair drops that accession's TPM and co-expression
  data.

---

## 2. Genome assembly and annotation

| | count | query |
|---|---|---|
| Assemblies described | 326 species | `SELECT COUNT(*) FROM speciesinfo` |
| Protein / transcript models | **13,111,410** | `SELECT COUNT(*) FROM trans_assembly` |
| `<ABBR>_locus` annotation tables | 145 | see above |
| RNA-seq expression tables | **31** (`<ABBR>_TPM`) | `SUM(table_name LIKE '%\_TPM')` |
| Methylation tables | **33** (`<ABBR>_BS_*`) | `SUM(table_name LIKE '%\_BS\_%')` |
| Mitochondrial genomes | **173** | `SELECT COUNT(*) FROM mito_genome` |
| Mitochondrial features | **3,555** | `SELECT COUNT(*) FROM mitochondrion` |

Three things about `trans_assembly` that are easy to get wrong:

* `trans_assembly_species` holds **220** rows while only 148 species have annotation. That
  table describes transcriptome coverage, which includes species whose transcriptome was
  published without being re-annotated here. It is not an annotation count.
* A `<ABBR>_locus` table's row count is **transcripts, not genes**. 115 of the 145 tables
  contain alternatively spliced models, and 11 have an entirely `NA` gene column. Any
  comment in the source code describing these as "protein-coding gene models" is wrong.
* The gene column is a prefix of the transcript column, in every table without exception,
  so the site displays only one of the two.

**Seven species' master annotation tables are truncated relative to what they declare** —
for example one species declares 215,391 models and ships 740 rows. The eight tables in
that package are also mutually inconsistent, in opposite directions. `download.php`
therefore prints the *declared* value for per-species tables rather than the row count.

### Annotation content

Functional annotation is spread across per-species tables: `<ABBR>_go`, `_ipr`, `_nr`,
`_pfam`, `_panther`, `_kegg`, `_uniprot`, plus the `trans_assembly_go` and
`trans_assembly_ko` aggregates.

* **Gene names exist for 105 of the 148 species** — the name lives in the `_uniprot` or
  `_nr` description field, not in a dedicated column. 43 species have no name source at
  all. A gene-name search must say so rather than return nothing.
* `trans_assembly_go` and `trans_assembly_ko` are the **only** place in the database where
  GO and KEGG *names* are stored; the per-species tables carry IDs only.

### BUSCO

`busco` is one row per (species, BUSCO_ID) and cannot be read directly. Completeness must
be folded per species:

```sql
SELECT species, COUNT(DISTINCT BUSCO_ID)
  FROM busco WHERE status = 'Complete' GROUP BY species;
```

`cnidaria_odb12` contains **3,203** BUSCOs. Two independent discrepancies exist in this
area and both are unresolved: 47 of 148 species have a `speciesinfo.BUSCO` value that
disagrees with the folded table (the table value is always the lower of the two), and the
lineage is given as `cnidaria_odb12` on the site but `metazoa_odb12` in the response
letter. **Completeness is not comparable across lineages** — switching a species from
`metazoa_odb10` to another lineage moves its score by 13-15 points.

---

## 3. Transposable elements

The released dataset is ~3.7 M TE records, 580,259 of them AIDSS. The TE table's
`related_gene` column holds **gene-level** identifiers, while `gene_detail.php` and the
annotation tables are keyed by **protein-level** IDs; the site converts with
`cnido_te_gene_proteins()`. Empty annotations for some species (SSIDE, ASP1, SMALA) are
data gaps, not broken links.

The commands behind this dataset are recovered: the served tables come from `TE_pipeline/`,
deposited under `supplementary-scripts/S04-transposable-elements/`. Note that a second
implementation on the working tree (`genome_TE/TE_pipeline/`) assigns `region` by a different
rule and did **not** produce these tables — see `scripts/pipelines/02-transposable-elements.sh`.

**The record count below does not reconcile with the supplementary text**, which gives
58 species tables holding 46,286,391 elements against the ~3.7 M recorded here. One of the
two is measuring something else; settling it needs the production database. Both figures are
left standing rather than one being chosen.

---

## 4. Transcriptome (RNA-seq)

31 `<ABBR>_TPM` tables hold the expression matrices, built from StringTie `-e -B` TPMs
(see `scripts/pipelines/05-transcriptome-rnaseq.sh`). Per-sample metadata is
`data/rnaseq_samples.json`, generated by `includes/rnaseq_meta_refresh.php`.

The coverage matrix counts two different things in two different columns, and mixing them
up is a known error:

* `n_*` columns count **genes**;
* one row of `trans_assembly` is one **protein**.

Sample metadata is missing where the database says it is missing. `'-'` in `dev_stage` is
a sentinel meaning absent — 13,398 of 15,642 `sample` rows — and must not be guessed at.
1,971 rows of that table were damaged by a `℃` (U+2103) character being absorbed into the
wrong field.

---

## 5. Co-expression networks

Per-species `<ABBR>_coexpress_positive` and `<ABBR>_coexpress_negative` tables.

* Edge strength is Pearson correlation; the site applies a top-K cap of
  `CNIDO_NET_TOP_K=100`.
* `rankA` and `rankB` are **mutual-rank positions, not strengths** — they must not be used
  to order results. The positive table is ordered by `pcc DESC`, the negative table by
  `pcc ASC`.
* Two *Aurelia* tables are legitimately empty; the coverage matrix previously counted table
  existence rather than content and reported them as present. It now counts rows (using
  `LIMIT 1`, not `COUNT(*)`, for speed).

---

## 6. Proteomics

`proteomic_datasets` (one row per PRIDE/other deposit) plus per-dataset peptide and
protein tables. Each dataset carries both the re-analysis values (`cnido_*`: engine, enzyme,
tolerances, FDR, decoy strategy) and the original study's own reported values (`orig_*`).

* The re-analysis is **Comet 2026.01 with Percolator (Crux 4.2) FDR control at q ≤ 0.01**,
  with a Comet-internal reversed 1:1 decoy. The commands survive in
  `supplementary-scripts/S09-proteomics/2.pipeline/`, and the parameter *values* are in the
  table. See `scripts/pipelines/11-proteome.md`.
* **Six datasets identified no peptides.** Four are the Glu-C residual fractions of a
  MED-FASP experiment. For these the site prints the original study's own published result
  from `data/proteomic_published.json` instead of an empty panel.
* The join to the transcriptome is `trans_assembly.protein = protein_id`. Joining on the
  gene ID doubles the row count. Where the species has no proteome loaded, matching goes
  through the congener substitution named in the `proteome_file` basename.

---

## 7. Epigenome

| assay | what is stored |
|---|---|
| ATAC-seq | peak tables, coverage BigWigs, per-sample inventory (`build_epigenome_samples.php`) |
| ChIP-seq | peak tables, broad-mark calls for four histone marks |
| DNase-seq / DHS | peak tables |
| WGBS | 33 `<ABBR>_BS_*` tables (methylation calls), up to **93.6 M rows** in the largest |

Peak tables index `protein_id` (a lookup takes ~1 ms); the `Sample` column is **not**
indexed and costs ~1.4 s. `hisType` / `hisMark` can only be derived from the site's own
choice list. `--nomodel --shift` is the subject of an unresolved discrepancy — see
`scripts/pipelines/06-epigenome.md` §3.

**A known data defect to be aware of when re-using the epigenome tables:** every ChIP
sample was originally peak-called with an effective genome size of 2.61e8 regardless of
species, and paired-end data with `-f BAM`. Both were corrected in a re-run on
2026-09-22 and the corrected tables are what the site serves.

---

## 8. Single-cell

| | count |
|---|---|
| Catalogue entries (`singlecell`) | **33** |
| Entries with an interactive UMAP viewer | 17 |
| Datasets with a published-figure section | varies — see below |

`singlecell_data/<dataset>/` holds `embedding.bin`, `cellmeta.bin`, `qc.bin`,
`expression/*.bin.gz` and JSON metadata. The dtypes are a strict contract: **cellmeta and
qc are uint16**. Writing them as another integer type still passes a byte-length check, so
the error only surfaces on decoding — validate by decoding, not by size.

Counting cautions:

* `singlecell_atlas_map.cnidosite_row` is a **row ordinal into `singlecell`**, which has no
  primary key. Inserting one row silently re-points every dataset below it at the wrong
  species.
* Gene IDs in these datasets are the datasets' own namespaces. Where a dataset gene maps to
  no site gene, the site keeps the original ID and says so. One dataset's numbering
  (NV2.8285) looks wrong and is not.
* `published_figure` is not always the figure that produced the coordinates: one dataset
  (NVECT_whole_adult) points at the wrong figure. Two others (OARBU, AMURI) have
  numbered panel figures whose labels cannot be decoded — the numbered and named embeddings
  are different coordinate systems (affine registration residual is at the random baseline),
  so the decoded cross-reference must not be published.

---

## 9. Comparative genomics

| resource | count |
|---|---|
| Core orthogroups (`core_og`) | **1,017** |
| — strict ∩ core ∩ extended | 14 |
| — core ∩ extended | 210 |
| — extended only | 793 |
| — accession sets in `core_species` | 153 (148 cnidarian + 5 outgroup) |
| Gene families in `/genetree/` | 67,795 families served |

```sql
SELECT tiers, COUNT(*) FROM core_og GROUP BY tiers;
-- strict,core,extended  14
-- core,extended        210
-- extended             793
```

`core_og.best_tier` is a **different** partition (53 / 891 / 73) — it describes the tier of
the best available functional annotation for the orthogroup, not the orthogroup's own tier.
Use `tiers` for the resource's three levels.

The tier trees are FastTree with `-nosupport`. The species tree used a 70-orthogroup,
24,975-site supermatrix (MAFFT → BMGE → AMAS → IQ-TREE), which is **not on this server**.

**Synteny data is not in this database.** Macro- and microsynteny live in a separate
schema, `jackie_db`. When summarising within-class correlation, use the **median** |rho|
(0.835 within class vs 0.238 between); `AVG()` returns 0.689 / 0.301 and is wrong. MySQL 8
has no `PERCENTILE_CONT`.

---

## 10. Metagenome-assembled genomes

| | count |
|---|---|
| Rows in `MAGs` | **315** |
| Distinct assemblies | **308** |
| Annotated by NCBI | 141 |
| Rows in `mag_annot` | 1,289,024 |
| Rows in `mag_interpro` | 2,597,813 |
| Rows in `mag_go_terms` | 1,897,159 |
| Rows in `mag_kegg_terms` | 462,036 |
| Rows in `mag_pfam_hits` | 1,178,349 |
| Rows in `mag_panther_hits` | 663,850 |

Seven `GCF_` rows are RefSeq twins of `GCA_` rows carrying identical assembly statistics —
the same assembly under two accessions, and the `GCF_` ones also carry RefSeq's own
annotation. The table keeps all 315 rows; the site de-duplicates in the display layer only,
so the true count of distinct assemblies is 308.

**A MAG's host species is resolved through the coverage-matrix function
`cnido_spcov_resolve`, not through the `abbr` column.** NCBI annotated only 141 of the 315;
the rest carry annotations produced here with InterProScan and KofamScan.

The annotation dictionaries are not self-validating: an incomplete dictionary produces
**blank columns, not an error**. Two traps are recorded because they cause silent data loss:
`entry.list` must be pinned to InterPro release 109 (never `current_release`), and
`go_term.tsv` must include obsolete terms and `alt_id` mappings.

---

## 11. Taxonomy, phenotype and literature

* **Phenotype** — 180,652 rows. The `value` column holds real measurements; the
  OCTD `standard_id` that used to be there is in a separate column. `phenotype_species_map`
  holds the WoRMS name mapping and `phenotype_species.php` prints the current accepted name
  from it. Do **not** render the line as "the accepted name is X" — WoRMS's own
  `valid_name` points at a sponge for some of these taxa.
* **Paleobiology** — PBDB records, with a local taxon card at `pbdb_taxon.php`. PBDB rejects
  requests without a `Referer` header, which is why the site no longer links out to it
  directly.
* **Literature** — the gene↔publication relation comes from NCBI `gene2pubmed`; the site's
  own gene names are backfilled onto it by `gene_name_backfill.php`, because the NCBI
  `symbol` column is not the name the site displays.
* **Species names exist in four copies** (`classfy`, `abbr`, `tf`, `ubs`) plus 7 files with
  hard-coded names. The collation is case-insensitive, so `=` cannot distinguish
  "sp. Nov." from "sp. nov."; comparisons that need to must use `BINARY`. The `abbr` slug
  is a deep-link key and must not be changed.

---

## 12. Table naming rules that the site depends on

The site classifies tables **by name suffix**. Creating a table whose name accidentally ends
in a known suffix makes the coverage matrix attribute it to the wrong species, silently.

* `<ABBR>_<suffix>` is a per-species table. Shared tables must not end in a reserved suffix.
* `_TPM`, `_BS_*`, `_locus`, `_go`, `_ipr`, `_nr`, `_pfam`, `_panther`, `_kegg`,
  `_uniprot`, `_coexpress_positive`, `_coexpress_negative`, `_cellmarker` are all reserved
  in this sense.
* After any ingest that changes the table inventory, **delete `site/tmp/coverage_cache.json`**
  or the coverage matrix keeps serving the old species-to-table mapping.

Gene-ID columns are `TEXT` and need **prefix** indexes: `(geneA(64))`. `data_length` in
`information_schema` is a stale estimate and cannot be used to judge whether an index exists.

---

## 13. What is in this repository, and what is not

**In the repository:** all site code; every curated table the site ships as JSON / TSV / CSV
/ TXT; the core-ortholog matrices and trees; the single-cell metadata (not the expression
binaries); the full schema.

**Not in the repository, itemised in [`manifests/large-files.tsv`](../manifests/large-files.tsv):**
4,538 files of 1 MiB or more, 261.1 GB in total. The manifest records each file's size and
its actual HTTP reachability, measured rather than assumed:

| reachability | files | size | what |
|---|---|---|---|
| fetchable over HTTP (**200**) | 2,154 | 38.0 GB | `download/` (1,219), `images/` (933), `singlecell_data/` (2) |
| **403 — not on HTTP** | 2,384 | 223.1 GB | 2,260 under `data/`, plus 124 unpublished staging images |

Reachability is **measured, not assumed**, and the measurement is auditable. A directory-root
probe is not a valid test: `/download/` and `/images/` answer 403 at their root because
`Options -Indexes` refuses a listing, which says nothing about the files inside. The manifest
is therefore built by probing **one real file in each directory** and letting the verdict
inherit down the tree, with the sampled filename recorded in
[`manifests/http-probe.tsv`](../manifests/http-probe.tsv) so any verdict can be re-checked.

The two 403 groups have different causes and only one of them is a policy:

* **`data/` — 2,260 files.** `data/.htaccess` carries `Require all denied`: these files are
  staging material for the database build and are read only through the filesystem. The
  223 GB is **not needed to rebuild the database**. It is the per-species annotation dump the
  database was built *from* — intermediates, not inputs. Rebuilding needs the schema plus the
  curated tables in this repository; the intermediates are regenerable with
  `scripts/pipelines/`, or obtainable from the authors.
* **`images/img_OARBU_SymbionticState/` — 124 files.** This directory is mode `drwx------`
  (owner-only) while every sibling is `drwxr-xr-x`, so Apache — which runs as `www-data` —
  cannot traverse it. It is **not a broken-link problem**: nothing references it, neither any
  page in `site/` nor any column in the database. It is 12 GB of unpublished single-cell
  plots left in a web-served tree, and refusing it over HTTP is the correct outcome.

To fetch what is fetchable:

```sh
python3 scripts/download-large-data/fetch_large_data.py --list
python3 scripts/download-large-data/fetch_large_data.py --fetch --only download/
```
