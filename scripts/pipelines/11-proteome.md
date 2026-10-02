# CnidoSite — proteomics

The site re-analyses public proteomics datasets with one consistent pipeline, rather than
re-displaying each depositor's own results. Two sets of numbers are therefore shown side by
side on `proteomic_dataset.php`: the re-analysis (`cnido_*` columns) and the original
study's own reported values (`orig_*` columns).

---

## 1. The pipeline — `[PROSE]`: script names only, no commands

The only surviving description of the re-analysis is on the site itself
(`proteomic_dataset.php:480-485`), which publishes the pipeline as a sequence of scripts:

```sh
python3 2.pipeline/02_fetch_pride.py <PXD>          # retrieve peak lists from PRIDE
python3 2.pipeline/03_build_search_db.py "<species>" # proteome + cRAP contaminants
python3 2.pipeline/04_run_search.py <PXD>            # writes comet.params, runs Comet
python3 2.pipeline/05_fdr_percolator.py <PXD>        # Percolator, q <= 0.01
python3 2.pipeline/06_map_to_genes.py <PXD>          # peptides -> CnidoSite genes
python3 2.pipeline/07_build_tables.py --release <rel> # load tables + SQL
```

**The `2.pipeline/` directory is not on this server.** It could not be found anywhere:

* whole-filesystem search for `04_run_search.py`, `cnido_common.py`, `comet.params`
  → no results;
* `grep -rilE 'comet|crux|percolator|msconvert|thermo'` over the entire
  `/mnt/sda/jackie/cnidaria_omics/proteome/` tree → **zero matches**. The only text logs in
  that tree are PRIDE `wget` transcripts;
* no Comet, Crux, Percolator or MSConvert executable exists on this host.

So the exact Comet invocation, and the contents of the `comet.params` that
`04_run_search.py` writes, **cannot be recovered here** — see `TO-BE-SUPPLIED.md`.

---

## 2. What the parameters were — recoverable from the database

The search parameters are not lost: they are stored per dataset in the `proteomic_datasets`
table, in the `cnido_*` columns, with these defaults:

| column | default |
|---|---|
| `cnido_engine` | `Comet 2026.01` |
| `cnido_fdr_psm` | `0.01` |
| `cnido_fdr_prot` | `0.01` |
| `cnido_decoy` | `Comet internal reversed (1:1)` |

plus `cnido_enzyme`, `cnido_termini`, `cnido_missed`, `cnido_precursor`, `cnido_fragment`,
`cnido_fixed`, `cnido_variable` and `cnido_quant` — and a parallel `orig_*` block recording
the original study's engine, database, tolerances and FDR.

A worked example, quoted in the response letter for PXD045585: Comet 2026.01, trypsin, at
most 2 missed cleavages, 10 ppm precursor tolerance, 0.5 Da fragment tolerance, fixed
carbamidomethylation C+57.021464, variable oxidation M+15.9949, Comet internal reversed
1:1 decoy, 1% PSM and 1% protein FDR, quantified by spectral counting. Pipeline versions
`cnidosite-proteomics 1.0` and `1.1`.

Percolator is referenced on the site as the **Crux 4.2** build (`proteomic_dataset.php:371`).

Reading the parameters out of the database is the reliable route (the helper is archived as
`scripts/setup/dbq.php`; the path below is its production location):

```sh
PHP_INI_SCAN_DIR=/etc/php/7.4/apache2/conf.d php -c /etc/php/7.4/apache2/php.ini \
  /var/www/cnidosite-tools/src/dbq.php "SELECT dataset_id, cnido_engine, cnido_enzyme, cnido_precursor, cnido_fragment, cnido_fdr_psm, cnido_decoy FROM proteomic_datasets"
```

---

## 3. Data retrieval — `[RUN]`

This part is on disk. Sources: `proteome/PXD041235/wget.sh`, `proteome/PXD051329/wget.sh`,
and the download logs `proteome/nohup.out`, `proteome/PXD041235/nohup.out`,
`proteome/PXD051329/nohup.out`.

```sh
wget https://ftp.pride.ebi.ac.uk/pride/data/archive/2024/04/PXD041235/ADU.mgf
wget https://ftp.pride.ebi.ac.uk/pride/data/archive/2024/04/PXD041235/peptides_1_1_0.mzid.gz
```

---

## 4. The depositors' own results, kept for the `orig_*` columns

These are the deposits' own files — they carry no CnidoSite command line, and they are
retained only because the site displays the original studies' numbers alongside its own:

| source engine | where |
|---|---|
| Mascot | `proteome/PXD029717/BM_20170612_Fr_HPLC_Tel_02_PeptideSummary.txt` (40 MB; `N Unused Total %Cov` columns plus an explicit `RRRRRTR…` reversed decoy) and the matching Sciex `.wiff` |
| MaxQuant | `proteome/PXD036981/{parameters.txt, Ch121-1-21mqpar.xml, proteinGroups.txt, peptides.txt}`, `proteome/PXD045587/` (`proteinGroups.txt`, `mzTab.mzTab.gz`) |
| PEAKS | `proteome/PXD045585/` (1,450 `.apl` files), `proteome/PXD045587/` (579) |

---

## 5. Two data-quality findings that belong in the methods

Both were discovered while cross-checking the proteomics module against the transcriptome
tables, and both should be stated (or fixed) rather than left implicit:

* **Six datasets identified no peptides at all.** Four of them are the Glu-C residual
  fractions of a MED-FASP experiment, not failed runs. For these, the site prints the
  original study's own published result instead of an empty re-analysis — the mechanism is
  `data/proteomic_published.json` read by `includes/proteomic_published.php`. The score
  shading in that panel is relative to the highest score in the same table, not an
  absolute threshold.
* **The join between proteomics and transcriptomics is on the protein ID**, not the gene
  ID: `trans_assembly.protein = protein_id`. Joining on gene doubles the rows. Where a
  dataset's species has no proteome in the database, it is matched through the congener
  substitution recorded in the `proteome_file` basename (ATENE / *Zoanthus sociatus*,
  which recovers 99.7% of peptides). An earlier note in the project claimed the congener
  match returned zero — that conclusion was wrong and has been retracted.
