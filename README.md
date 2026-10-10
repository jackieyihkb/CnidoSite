# CnidoSite — website source code and curated data

Snapshot of the **CnidoSite** database (https://cnidosite.org), a multi-omics resource for
Cnidaria, archived at the time of publication so that the resource survives the website.

This repository answers reviewer comment #1 ("a major problem with online scientific
databases/resources is that they disappear … it would be helpful to archive a snapshot of
the database at the time of publication … this would include all HTML, CSS, JavaScript, PHP,
Perl, Python files as well as curated data … the size of the sequence data is huge, but
perhaps what could be archived is a script that can be used to create the database locally or
the files of the database with a script for downloading the large datasets"):

* **all of the site's own source code** — PHP, JavaScript, CSS, HTML, Perl, Python, Apache config;
* **the curated data of modest size** — every table the site ships as JSON / TSV / CSV / TXT,
  plus the derived matrices and trees of the core-ortholog resource;
* **the complete database schema** — `CREATE TABLE` statements for all 1,541 tables;
* **scripts for everything that is too large for a Git repository** — a manifest of every
  large file with its size and public URL, plus fetchers.

Not in this repository: raw sequencing data, per-species annotation dumps, the single-cell
binary assets, the BLAST databases, and the full SQL dump. See
[`manifests/large-files.tsv`](manifests/large-files.tsv) for those, item by item.

---

## Contents

```
site/                       the web application (drop-in DocumentRoot)
  *.php  *.html  *.css  *.js         85 top-level pages + shared assets
  includes/                          42 shared includes (state, panels, refresh jobs)
  css/  js/  viewer/  cytoscape/     front-end assets
  blast/                             NCBI BLAST CGI front end (own scripts + config)
  core/  genetree/  phylotree/       phylogram / ortholog / tree viewers
  GSEA/  primer3plus/  submit/       GSEA front end, Primer3Plus, submission form
  manual_images/                     figures for the user manual
  .htaccess                          hardening rules (see docs/DEPLOYMENT.md)
data/
  curated/                    small curated tables the site reads directly
  download-index/             index files, mitochondrial genomes, transcriptome tables
  singlecell-metadata/        dataset index + per-dataset metadata (no expression binaries)
  genome_assembly_cache/  microsynteny/
schema/
  cnidaria-schema.sql         schema of all 1,541 tables (no data)
manifests/
  large-files.tsv             every file >1 MiB that is NOT in this repo, with its URL
scripts/
  setup/                      install / bootstrap helpers
  download-large-data/        fetch the big datasets back
  pipelines/                  the bioinformatics command lines behind the curated data
supplementary-scripts/      the analysis scripts those command lines cite, by text section
docs/
  DATA_OVERVIEW.md            what is in the database
  DEPLOYMENT.md               how to stand the site up locally
  REPRODUCIBILITY.md          how each dataset in the database was produced
```

## What the database contains

| | |
|---|---|
| Species catalogued | **326** (every class of Cnidaria, all seven classes) |
| Species with gene annotation | 148 |
| Tables | **1,541** base tables + 2 views; all 1,543 objects are in `schema/cnidaria-schema.sql` |
| Database size | ~178 GB |
| Protein / transcript models | 13,111,410 rows in `trans_assembly` |
| RNA-seq expression tables | 31 `<ABBR>_TPM` tables + per-sample metadata |
| DNA methylation tables | 33 `<ABBR>_BS_*` tables (up to 93.6 M rows) |
| Mitochondrial genomes | 173 species; 3,555 annotated features |
| MAGs | 315 rows (308 distinct assemblies) |
| Core ortholog resource | 1,017 orthogroups in three cumulative tiers (14 / 224 / 1,017) |
| Single-cell datasets | 33 catalogue entries; 17 with interactive UMAP viewers |

`docs/DATA_OVERVIEW.md` has the full breakdown, including how to reproduce each count.

## Quick start

```sh
# 1. prerequisites: Apache 2.4 + PHP 7.4 (mod_php, mysqli, gd, mbstring), MySQL 8
# 2. put the code in place
sudo rsync -a site/ /var/www/html/CnidoSite/
sudo chown -R www-data:www-data /var/www/html/CnidoSite

# 3. create the database and load the schema
mysql -u root -p -e "CREATE DATABASE cnidaria CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p cnidaria < schema/cnidaria-schema.sql

# 4. tell the site how to reach the database (no credentials are in this repo)
#    see docs/DEPLOYMENT.md — the four values are read from the environment
export CNIDO_DB_HOST=localhost CNIDO_DB_USER=cnidosite CNIDO_DB_PASS=... CNIDO_DB_NAME=cnidaria

# 5. fetch the large data back (optional; the site degrades gracefully without it)
python3 scripts/download-large-data/fetch_large_data.py --list
```

Full instructions, including the Apache vhost, the `tmp/` permissions, BLAST, Primer3Plus and
GSEA, are in [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md).

## Reproducing the analyses

Every dataset in the database is described in
[`docs/REPRODUCIBILITY.md`](docs/REPRODUCIBILITY.md) and in `scripts/pipelines/`, one file per
analysis module, giving the software name, its version and the exact command line as it was
run, each line carrying a `# from:` citation into
[`supplementary-scripts/`](supplementary-scripts/).

The typeset form of the same record, submitted as supplementary material with the manuscript,
is the Word document **CnidoSite — Supplementary Methods**. The two describe the same
analyses; `scripts/pipelines/README.md` maps each module file to its supplementary-text
section.

Where a command line could not be recovered from the working directories it is explicitly
marked, and listed in [`scripts/pipelines/TO-BE-SUPPLIED.md`](scripts/pipelines/TO-BE-SUPPLIED.md),
rather than guessed.

## License and citation

The code is released under the MIT licence and the curated data under CC BY 4.0 — see
[`LICENSE.md`](LICENSE.md). Machine-readable citation metadata are in
[`CITATION.cff`](CITATION.cff).

This snapshot is archived on Zenodo:

* this version (v1.0) — https://doi.org/10.5281/zenodo.23277322
* all versions — https://doi.org/10.5281/zenodo.23277321

The manuscript citation will be added on acceptance.

## Contact

Longjun Wu lab — see https://longjunwulab.org/
