# CnidoSite — proteomics

The site re-analyses public proteomics datasets with one consistent pipeline, rather than
re-displaying each depositor's own results. Two sets of numbers are therefore shown side by
side on `proteomic_dataset.php`: the re-analysis (`cnido_*` columns) and the original
study's own reported values (`orig_*` columns).

---

## 1. The pipeline — `[RUN]`

The re-analysis is a six-step pipeline, and the steps are published both on the site itself
(`proteomic_dataset.php:480-485`) and as the scripts in `2.pipeline/`:

```sh
python3 2.pipeline/02_fetch_pride.py <PXD>          # retrieve peak lists from PRIDE
python3 2.pipeline/03_build_search_db.py "<species>" # proteome + cRAP contaminants
python3 2.pipeline/04_run_search.py <PXD>            # writes comet.params, runs Comet
python3 2.pipeline/05_fdr_percolator.py <PXD>        # Percolator, q <= 0.01
python3 2.pipeline/06_map_to_genes.py <PXD>          # peptides -> CnidoSite genes
python3 2.pipeline/07_build_tables.py --release <rel> # load tables + SQL
```

**The scripts survive, and they are the authoritative record of the search.** The
`2.pipeline/` directory is deposited under `supplementary-scripts/S09-proteomics/2.pipeline/`,
and the commands below are taken from it and from the loader. The same sequence is printed
on the resource's own dataset page.

```sh
# Conversion, only for projects that ship no peak list
ThermoRawFileParser -i <file.raw> -o <tmpdir> -f 0 -m 0
python3 apl2mgf.py SHARD.apl                      # 2.pipeline/apl2mgf.py:41-44

# Search — one Comet run per spectrum file. The parameter file is written per dataset by
# write_comet_params(), which is the authoritative record of the search.
comet -P3.work/search/<PXD>/comet.params <peaklist>.mgf      # 04_run_search.py:686-703

# FDR — all PIN files for a dataset merged, then scored with Crux 4.2 Percolator at q <= 0.01
crux percolator --decoy-prefix DECOY_ --output-dir 4.results/<PXD>/percolator \
     4.results/<PXD>/<PXD>.merged.pin                        # 05_fdr_percolator.py:39-40, :65-79
```

**What is *not* recoverable** is narrower than "the engine is missing": the per-dataset
`comet.params` contents are archived only for the representative example (PXD009253), and
the exact Comet invocation for datasets other than that one is not recorded. See
`TO-BE-SUPPLIED.md`.

The merge is not cosmetic. The header of the first PIN file is kept and every `SpecId` is
prefixed with its source file stem, so scan numbers cannot collide across the files being
merged. The output directory is cleared before the run because Crux refuses to overwrite it.
The inner Percolator command Crux issues confirms the effective settings
(`--trainFDR 0.01 --testFDR 0.01 --protein-decoy-pattern DECOY_ --post-processing-tdc`).

**Uniform search settings across datasets**, from the parameter files: Comet 2026.01,
`decoy_search = 1` (internal reversed decoys, concatenated 1:1),
`precursor_tolerance_type = 0` (MH+), B/Y ions only, `max_variable_mods_in_peptide = 3`,
`digest_mass_range = 600.0 5000.0`, `peptide_length_range = 5 50`, charges 1–6,
`spectrum_batch_size = 15000`, `minimum_peaks = 10`, `equal_I_and_L = 1`,
`output_percolatorfile = 1`. Fixed modification C+57.021464; variable modification
M+15.9949 with up to three per peptide. Precursor tolerance and units, fragment tolerance
and enzyme are per dataset and are read from a metadata table.

**cRAP.** The species reference proteome (BUSCO-validated, or a transcriptome-derived
`.rep.pep`, or a congener surrogate named in the metadata) is concatenated with the
116-protein cRAP set. cRAP entries carry a `CRAP_` prefix and `is_contaminant = 1`.
Decoys are not added here — Comet generates them internally, 1:1 reversed.

**Conversion.** Deposited peak lists are used as they are; only projects that ship no peak
list are converted. `.mgf` inputs are rewritten with LF line endings and unique titles,
because CRLF line endings make Comet dump core. `.mzML`/`.mzXML` are read natively by Comet
(symlinked, not converted); Sciex `.wiff` requires msconvert, and no loaded dataset is of
that type.

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
