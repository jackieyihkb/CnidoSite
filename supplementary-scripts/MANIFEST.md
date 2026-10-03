# supplementary-scripts — manifest

Companion to the Appendix of the Supplementary Methods. The scripts are laid out by
document section, so a reader who follows a section finds its scripts in the
directory of the same number. Alongside the file list, this manifest carries the two
tables the Supplementary Methods refers to it for: the software versions recorded for
each module, and the summary counts of the data served.

Generated: 2026-10-02 17:58:02   
Scripts: 271

## Sections

- **S03-genome-annotation/** — Genome sequence data, gene models, functional annotation, BUSCO (29 files)
- **S04-transposable-elements/** — Transposable-element annotation (EDTA pipeline + second implementation) (40 files)
- **S05-transcriptome-assembly/** — Transcriptome assembly, quantification and expression matrices (40 files)
- **S06-co-expression/** — Co-expression network construction and threshold selection (9 files)
- **S07-comparative-genomics/** — Orthology, species tree, core ortholog resource, loaders (38 files)
- **S08-single-cell/** — Single-cell processing, annotation, export and rendering (39 files)
- **S09-proteomics/** — Proteomics search pipeline and web loaders (13 files)
- **S10-epigenomics/** — ATAC-seq, WGBS, ChIP-seq and DNase-seq (19 files)
- **S11-mags-and-metagenome/** — MAG annotation pipeline and database loaders (29 files)
- **S12-phenotype-and-traits/** — Curated trait data and mitochondrial genome import (8 files)
- **S13-database-and-website/** — Website refresh jobs (7 files)

## Placeholders

No credential, no host address and no author login is published here.

- Database passwords appear as `<REDACTED>`.
- Host addresses appear as `<SITE-HOST>`, `<SITE-HOST-ALT>`, `<ANALYSIS-HOST>` or
  `<THIRD-HOST>`; the transfer account as `<SITE-ACCOUNT>`.
- The author's home directory appears as `/home/$USER`, which expands correctly on
  whichever machine the script is run on.

Substitute your own values before running. Nothing else in these files has been altered.

## Checks

All 117 shell scripts pass `bash -n` except two, which are shipped verbatim from the
authors and contain an unquoted parenthesis inside a file name — a defect of the
original, not of the redaction: `S05-transcriptome-assembly/run.sh`,
`S10-epigenomics/WGBS/PE.sh`. All 108 Python files pass `ast.parse`.

## Software versions

Versions are those the runs recorded — printed to a log, echoed by `--version`, or
pinned in a module's environment. A tool whose version was never printed is listed as
*not recorded* rather than inferred from what is installed now.

| Software | Version | Module |
|---|---|---|
| fastp | 0.23.4 | Transcriptome |
| HISAT2 | 2.2.2 | Transcriptome |
| samtools | 1.21 (htslib 1.23) | Transcriptome |
| StringTie | 3.0.3 | Transcriptome |
| deepTools bamCoverage | 3.5.6 | Transcriptome, ATAC |
| R / Perl (co-expression) | not recorded (Perl 5.34.0) | Co-expression |
| WGCNA, gplots, ROCR, pROC | not recorded | Co-expression |
| BUSCO | 5.8.3 (protein mode); 6.0.0 (genome mode) | Completeness |
| Prodigal | 2.6.3 | Annotation, MAGs |
| InterProScan | 5.78-109.0 (MAG and annotation pipeline); 5.67-97.0 (site tool table) | Annotation, MAGs |
| DIAMOND | v2 (`uniprot_sprot.dmnd`, release 226) | Annotation, MAGs |
| HMMER3 hmmscan / hmmpress (Kofam) | not recorded | Annotation, MAGs |
| EDTA | 2.2.2 | Transposable elements |
| RepeatModeler / RepeatMasker | 2.0.7 / 4.2.1 (inside EDTA) | Transposable elements |
| Python (TE pipeline) | 3.12.11 | Transposable elements |
| OrthoFinder | 3.1.0 | Comparative |
| MAFFT | not recorded | Comparative |
| BMGE | not recorded (1.12 defaults) | Comparative |
| AMAS | not recorded | Comparative |
| IQ-TREE | 3.1.2 | Comparative |
| FastTree | not recorded | Comparative |
| STAR (STARsolo mode) | 2.7.11b | Single-cell |
| scanpy / anndata | 1.12.4 / 0.13.3.post0 | Single-cell |
| scrublet / harmonypy | 0.2.3 / 2.0.2 | Single-cell |
| leidenalg / igraph | 0.12.0 / 1.0.0 | Single-cell |
| pandas / matplotlib | 3.0.6 / 3.11.2 | Single-cell |
| fasterq-dump, Seurat, Matrix | not recorded | Single-cell |
| Comet | 2026.01 rev. 1 (e4f767c) | Proteomics |
| Crux (Percolator) | 4.2 (inner Percolator 3.06.nightly-2) | Proteomics |
| ThermoRawFileParser | 2.0.0.dev | Proteomics |
| Bismark | 0.25.1 | WGBS |
| Bowtie2 | 2.5.5 | WGBS, ATAC, ChIP, DNase |
| Trimmomatic | 0.40 | WGBS |
| FastQC | 0.12.1 | ATAC |
| MACS2 | 2.2.9.1 | ATAC, ChIP, DNase |
| samtools | 1.23.1 | Epigenomics |
| UCSC bedGraphToBigWig | not recorded | WGBS |
| CheckM2 | 1.1.0 | MAGs |
| MySQL server | 8.0.46 | Database |
| PHP / Apache | 7.4 / 2.4 | Web resource |

## Data volumes

Counts are read from the production database on 2026-10-02 and are the figures the
resource's own statistics page reports.

> **Two of these do not reconcile with the archive, and both are left standing rather than
> one being chosen.** The repository's own `docs/DATA_OVERVIEW.md` records **~3.7 M TE
> records** (580,259 of them AIDSS) against the 46,286,391 elements below, and
> **13,111,410 rows in `trans_assembly`** against the 5,156,916 predicted transcripts below.
> Each pair may be measuring different things — rows including isoforms versus transcripts,
> or raw elements versus filtered table rows — but that has not been established. Settling it
> needs the production database.

| Dataset | Count |
|---|---|
| Species catalogued | 326 |
| Species with an assembly accession | 321 |
| Species with a gene-model table | 145 |
| Species with functional annotation | 148 |
| Predicted transcripts | 5,156,916 |
| BUSCO hit records | 515,427 |
| GO / InterPro / Pfam assignments | 34,443,367 / 34,050,436 / 7,135,337 |
| PANTHER / KEGG / NR / UniProt assignments | 3,616,693 / 1,296,227 / 10,852,826 / 5,599,392 |
| Gene-family assignments (distinct families) | 3,621,064 (113,207) |
| Core orthogroups | 1,017 (14 / 224 / 1,017 cumulative) |
| Transposable elements | 58 species tables, 46,286,391 elements |
| Transcriptomic datasets | 15,642 |
| Co-expression networks (gene pairs) | 29 (19,279,624) |
| Single-cell datasets (with interactive viewer) | 33 (17) |
| Cell types / cell markers | 105 / 291,897 |
| Proteomic datasets (re-analysed) | 46 (27) |
| Quantified proteins | 83,307 |
| Epigenomic datasets | 723 (266 WGBS, 210 ATAC, 193 ChIP, 53 miRNA, 1 DNase) |
| Epigenomic peaks / CpG sites | 2,151,027 / 786,963,020 |
| Metagenomic datasets | 1,650 |
| MAG records (distinct assemblies) | 315 (308) |
| Phenotype records | 180,652 |
| Fossil records | 410 |
| Species with a mitochondrial genome | 173 |
| Database objects | 1,542 tables, 2 views (≈178 GB) |

## Files

```
S03-genome-annotation/BUSCO/merge_busco_results.py                              2943  8d12b9083e2bf5aa
S03-genome-annotation/BUSCO/run_busco.sh                                         794  168a80f11a31e0c8
S03-genome-annotation/busco_assembly/logs/collect_and_publish.sh                1534  a6f09a288de7d327
S03-genome-annotation/busco_assembly/logs/collector_loop.sh                     2136  0be1c3d5514d7d57
S03-genome-annotation/busco_assembly/logs/pilot.sh                              1206  fa1011ad9b9a98ed
S03-genome-annotation/busco_assembly/logs/progress.sh                            858  a2cac77a2f4d0245
S03-genome-annotation/busco_assembly/logs/relaunch_full.sh                      1377  dc0886010d58dff1
S03-genome-annotation/busco_assembly/logs/run_cnidaria_missing.sh               2552  3e6669ffc61cde91
S03-genome-annotation/busco_assembly/logs/transfer.sh                           1624  bb20c32b282dafe4
S03-genome-annotation/busco_assembly/logs/watchdog.sh                           4277  93bbd0b556329533
S03-genome-annotation/busco_assembly/run_le.sh                                 16807  c9b02b7b9f033034
S03-genome-annotation/genome/1.py                                               2288  6cd6417bbbbb0d46
S03-genome-annotation/genome/1.sh                                               8729  220c35707cb7f87e
S03-genome-annotation/genome/10.sh                                           2812381  809b7319190f2b3d
S03-genome-annotation/genome/2.py                                               2139  9756ecc474081cec
S03-genome-annotation/genome/2.sh                                               8194  9cfe391b81bacf4e
S03-genome-annotation/genome/3.py                                               3835  926aac52395e3455
S03-genome-annotation/genome/3.sh                                              13250  0536adedba6c745d
S03-genome-annotation/genome/4.py                                               3721  1fbb6910dfc65423
S03-genome-annotation/genome/4.sh                                              12641  9b74dc0fd0381389
S03-genome-annotation/genome/5.py                                               2891  957bcc56605279e1
S03-genome-annotation/genome/5.sh                                                980  e4ddf038fa125ed7
S03-genome-annotation/genome/7.sh                                              83348  cd7af164b887ec5f
S03-genome-annotation/genome/8.sh                                              84389  a746247e7a88ae2b
S03-genome-annotation/genome/9.sh                                            2736848  1d566d45118e1006
S03-genome-annotation/genome/extract_gene_protein.py                            2504  9c005385effedcc5
S03-genome-annotation/genome/locus/1.py                                         4369  ea526f37c667a040
S03-genome-annotation/genome/rename_files.sh                                    3038  d56e5f2c1b014b40
S03-genome-annotation/genome/rename_script.sh                                   3460  aab331f27c2498b1
S04-transposable-elements/TE_pipeline/bin/00_inventory.py                       4218  567aec193a1f82d0
S04-transposable-elements/TE_pipeline/bin/00_scan_fasta.sh                       338  33506ff2eef8ce8e
S04-transposable-elements/TE_pipeline/bin/01_scan_gff.sh                         498  aa52b43f5e02d0fe
S04-transposable-elements/TE_pipeline/bin/10_prepare.sh                         2448  51b014c0256beaec
S04-transposable-elements/TE_pipeline/bin/20_edta.sh                            3051  c818c6d6b44773b6
S04-transposable-elements/TE_pipeline/bin/25_repair_ltr.sh                     10468  619f3fb719f98de9
S04-transposable-elements/TE_pipeline/bin/26_rerun_ltr_retriever.sh             4750  a95445c5263d080e
S04-transposable-elements/TE_pipeline/bin/27_fix_ltr_identifier.sh             10721  51073bffaafa30ce
S04-transposable-elements/TE_pipeline/bin/28_fix_ltr_intact.sh                 16013  cd3065d87d5c7f81
S04-transposable-elements/TE_pipeline/bin/29_fix_ltr_rawlib.sh                  5001  ea5211d3e98ea527
S04-transposable-elements/TE_pipeline/bin/30_te_table.py                       17434  5732ab453d67038a
S04-transposable-elements/TE_pipeline/bin/33_drop_foreign_entries.py           13546  22310ec36d1b1429
S04-transposable-elements/TE_pipeline/bin/34_redo_anno.sh                       6145  49332637da4ae635
S04-transposable-elements/TE_pipeline/bin/40_migrate_to_nvme.sh                16567  5daf04f4a3220b91
S04-transposable-elements/TE_pipeline/bin/60_publish_to_site.sh                12912  813fba70cc215bd1
S04-transposable-elements/TE_pipeline/bin/61_publish_watch.sh                   2255  11dce33fb0c3ca9f
S04-transposable-elements/TE_pipeline/bin/90_merge.py                           9923  e9eb02c1a0e1e1f0
S04-transposable-elements/TE_pipeline/bin/common.sh                             5750  9974edf0dc8ead0f
S04-transposable-elements/TE_pipeline/bin/ltr_piece.sh                          3218  7451180c47c9b329
S04-transposable-elements/TE_pipeline/bin/merge_daemon.sh                       2545  292c74bff94b739c
S04-transposable-elements/TE_pipeline/bin/migrate_to_nvme.sh                   12135  07223fe039de353f
S04-transposable-elements/TE_pipeline/bin/progress.sh                           3677  66b554fff6580162
S04-transposable-elements/TE_pipeline/bin/query_te.py                           4121  77875bb284815656
S04-transposable-elements/TE_pipeline/bin/run_all.py                           14779  929a53251765e669
S04-transposable-elements/TE_pipeline/bin/run_species.sh                        2158  f33d723d99c0ea13
S04-transposable-elements/TE_pipeline/bin/throttle.py                          12627  427f8f54a002b2b2
S04-transposable-elements/TE_pipeline/bin/verify_nvme_resume.sh                 3266  8b400e629fdfb558
S04-transposable-elements/TE_pipeline/bin/watchdog.sh                           6422  acf004f8a5e444df
S04-transposable-elements/TE_pipeline/config/pipeline.conf                      5450  622d9de52f278b9b
S04-transposable-elements/TE_pipeline/envs/EDTA.full.yml                        9673  0e3ad30599097871
S04-transposable-elements/TE_pipeline/envs/EDTA.yml                             1151  1bd0f798a362c4aa
S04-transposable-elements/TE_pipeline/rmwrap/RepeatModeler                      2914  9663d1bc4db1a3ff
S04-transposable-elements/second-implementation/00_setup_env.sh                 1688  437532ccbfebd617
S04-transposable-elements/second-implementation/01_per_species.sh               7739  b40731f7c55a6228
S04-transposable-elements/second-implementation/02_rename_genome.py             3335  59617e1621dbf18e
S04-transposable-elements/second-implementation/03_te_annotation_table.py      26033  7b9d1658e4718e91
S04-transposable-elements/second-implementation/04_combine_tables.sh            2504  837c786ead761f16
S04-transposable-elements/second-implementation/README.md                      15448  5987b1516e587ddf
S04-transposable-elements/second-implementation/run_all.sh                      4887  99f5de6fe9d97b04
S04-transposable-elements/second-implementation/test_table_logic.sh             9276  11f389f9c1b0fbf3
S05-transcriptome-assembly/expression-matrices/1.py                             2015  c44261d962648930
S05-transcriptome-assembly/expression-matrices/10.py                            3150  f3de70704b68266a
S05-transcriptome-assembly/expression-matrices/11.py                            3104  0205a47f48459e33
S05-transcriptome-assembly/expression-matrices/12.py                            2108  6e63252ebe30874d
S05-transcriptome-assembly/expression-matrices/13.py                            2316  47bfb641af314bb8
S05-transcriptome-assembly/expression-matrices/14.py                            2558  5df28492ac6fac99
S05-transcriptome-assembly/expression-matrices/15.py                            2051  1e765fc28310e3d0
S05-transcriptome-assembly/expression-matrices/16.py                            2216  1dba844905a51012
S05-transcriptome-assembly/expression-matrices/17.py                            2356  3df71f8ac2563aa1
S05-transcriptome-assembly/expression-matrices/18.py                            2054  e0d54a5ddfa93821
S05-transcriptome-assembly/expression-matrices/19.py                            2900  08db516143727e88
S05-transcriptome-assembly/expression-matrices/2.py                             2170  d3c4f5b944fa5a72
S05-transcriptome-assembly/expression-matrices/20.py                            1964  3416b11fe0537459
S05-transcriptome-assembly/expression-matrices/21.py                            2072  5011ec228b589105
S05-transcriptome-assembly/expression-matrices/22.py                            2322  db4cdf0018a70d57
S05-transcriptome-assembly/expression-matrices/23.py                            2756  c51cf6233ef940bb
S05-transcriptome-assembly/expression-matrices/24.py                            2468  ff046fc00225e2f3
S05-transcriptome-assembly/expression-matrices/25.py                            2540  f062f212a4075611
S05-transcriptome-assembly/expression-matrices/26.py                            2322  6fd651471e5954bd
S05-transcriptome-assembly/expression-matrices/27.py                            2322  f0c617338b912d2a
S05-transcriptome-assembly/expression-matrices/28.py                            2492  7dd8e85b4555c0e9
S05-transcriptome-assembly/expression-matrices/29.py                            2936  8e9ac755fbd36206
S05-transcriptome-assembly/expression-matrices/3.py                             2190  b991adc18ccc0c27
S05-transcriptome-assembly/expression-matrices/30.py                            2378  93740dc43d1558f0
S05-transcriptome-assembly/expression-matrices/31.py                            2054  736fab14043d8bd2
S05-transcriptome-assembly/expression-matrices/4.py                             3206  843791a10fef51f3
S05-transcriptome-assembly/expression-matrices/5.py                             2000  54c478e44b1b3fd1
S05-transcriptome-assembly/expression-matrices/6.py                             2378  a8ed1f44b3ea52a6
S05-transcriptome-assembly/expression-matrices/7.py                             2509  c807543a22f6f98e
S05-transcriptome-assembly/expression-matrices/8.py                             2543  d6c4227cb5ac377c
S05-transcriptome-assembly/expression-matrices/80.py                            2298  c879aad95492fffd
S05-transcriptome-assembly/expression-matrices/81.py                            2111  0124565a81159be7
S05-transcriptome-assembly/expression-matrices/9.py                             2594  a2b80742f1795da4
S05-transcriptome-assembly/hisat2_build.sh                                      7167  5f545322ac562427
S05-transcriptome-assembly/run.pl                                               2252  b70dae250b71c120
S05-transcriptome-assembly/run.sh                                            1724101  ac5c9f7579f6ab0f
S05-transcriptome-assembly/run1.sh                                           1643690  9d7e2e8ddbf8fe12
S05-transcriptome-assembly/run_analysis.sh                                      4568  6bab85d58364f727
S05-transcriptome-assembly/runrun.sh                                           19631  5578b81a97aac4d5
S05-transcriptome-assembly/runrunrun.sh                                         1821  301558827ed8691b
S06-co-expression/FPKM_threshold.R                                              1057  98d7ceb404a5e977
S06-co-expression/MR_ROC.R                                                      1582  0205e590dc2e795a
S06-co-expression/PCC_MR_by_WGCNA.R                                             5840  f488645b974d61ba
S06-co-expression/PCC_ROC.R                                                     1064  50eb7584129143f8
S06-co-expression/ROC_in_MR_1.pl                                                 854  6d97226b36799ce2
S06-co-expression/ROC_in_PCC_1.pl                                                848  102a73d2bde0bc90
S06-co-expression/WGCNA_cluster.R                                                923  ac7ad8436d380496
S06-co-expression/extract_TPM.pl                                                1896  0ee21521e2db6677
S06-co-expression/pcc.pl                                                        1213  9b5eb5d6e90c8f46
S07-comparative-genomics/loaders/enrich.sh                                      1922  8ed3e4157f088b34
S07-comparative-genomics/loaders/import.sh                                      3447  6ba583d73dc4bea3
S07-comparative-genomics/loaders/import_core.sh                                 4652  09147e57d9e54528
S07-comparative-genomics/loaders/import_tree.sh                                 2888  a540cd1840f617f6
S07-comparative-genomics/loaders/schema.sql                                     2462  81381ef73ce90dc0
S07-comparative-genomics/loaders/schema_core.sql                                4188  4b8b4ac8deb4e594
S07-comparative-genomics/loaders/schema_tree.sql                                1740  0d16b286ef261eff
S07-comparative-genomics/orthology/core/README.md                              20146  fc7fd4015b73eeb8
S07-comparative-genomics/orthology/core/build.py                                4262  71fddcdf754d17bd
S07-comparative-genomics/orthology/core/build_busco_check.py                    4580  e56bd90d289937ba
S07-comparative-genomics/orthology/core/build_copynumber.py                     3948  850e11c6e10f2c4d
S07-comparative-genomics/orthology/core/build_core.py                           5644  c7ccfebe36b1f1a9
S07-comparative-genomics/orthology/core/build_load.py                          10586  499efffe3ed2466f
S07-comparative-genomics/orthology/core/build_resource.py                       6802  8d08e0905bee0dde
S07-comparative-genomics/orthology/core/build_sequences.py                      9317  386161f5671b090f
S07-comparative-genomics/orthology/core/build_species.py                        8177  86dec4d30a5d011b
S07-comparative-genomics/orthology/core/common.py                               3166  ecb3c261b26b8411
S07-comparative-genomics/orthology/core/import_core.sh                          4652  09147e57d9e54528
S07-comparative-genomics/orthology/core/pack_downloads.sh                       3110  1d135c6f846c828b
S07-comparative-genomics/orthology/core/score.py                                4286  60577a2cad22ad64
S07-comparative-genomics/orthology/core/tree_check.py                           3585  46e04fb1ceaa354d
S07-comparative-genomics/orthology/genetree/README.md                           3652  00aba9986fed7aa3
S07-comparative-genomics/orthology/genetree/REFEREE_RESPONSE_genetree.md        8120  7107638123bbceb4
S07-comparative-genomics/orthology/genetree/annotate_dups.py                   16390  ec06ce0ceac90098
S07-comparative-genomics/orthology/genetree/build.py                            3237  64cc3f34182799c0
S07-comparative-genomics/orthology/genetree/harvest.py                          9624  687e9d9a12ac6937
S07-comparative-genomics/orthology/genetree/pack_aln.sh                         2450  2dbba61098e07dc4
S07-comparative-genomics/orthology/genetree/pack_trees.py                      10499  1d302e5f508c160f
S07-comparative-genomics/orthology/genetree/run_fasttree.sh                     2825  25524cf030c0722e
S07-comparative-genomics/tree/0.peps/orthofinder.sh                             4005  633ab39e2974043b
S07-comparative-genomics/tree/0.peps/orthofinder_for_oldversion.sh              4867  7d95034ae06f101c
S07-comparative-genomics/tree/0.peps/orthofinder_old.sh                         5415  341713045cb723d0
S07-comparative-genomics/tree/1_OGfilter_new.py                                 2257  26da6f7e9161cd56
S07-comparative-genomics/tree/2_mafft_new.sh                                     781  785f3f377c3344cc
S07-comparative-genomics/tree/3_bmge_new.sh                                      647  bbed0523e8020dd6
S07-comparative-genomics/tree/5_phylo_new/run_drop3_guide_pmsf.sh               1141  9c56a6157d22fb97
S07-comparative-genomics/tree/5_phylo_new/run_drop5_guide_pmsf.sh                911  a44d10afa59711d1
S07-comparative-genomics/tree/5_phylo_new/run_drop5_partitioned.sh               668  94cc860dc07c6841
S08-single-cell/DEPLOY.md                                                     138507  71b6bf61015e5b20
S08-single-cell/pipeline/00_harvest_sra_metadata.py                            16553  650db5d84be7d551
S08-single-cell/pipeline/03_qc.py                                              21536  eee140a378973ec0
S08-single-cell/pipeline/04_cluster_annotate.py                                31633  cee2f19f1a5cf8b4
S08-single-cell/pipeline/05_export_web.py                                      22768  4f93557c4052c2c2
S08-single-cell/pipeline/06_aggregate_qc_table.py                              32260  996380f41287c11c
S08-single-cell/pipeline/07_render_umap.py                                     24130  52d56d054b6c1b2d
S08-single-cell/pipeline/08_render_pubstyle_celltype.py                        10622  62f34f33a806b03a
S08-single-cell/pipeline/env.sh                                                 1544  ec5892f01eeb01ad
S08-single-cell/pipeline/tools/merge_samples.py                                11885  cc23f676587b48a2
S08-single-cell/pipeline/tools/preview_pages.sh                                 4340  1c46b48fbb127ecf
S08-single-cell/pipeline/tools/preview_serve.py                                 4415  062cc05410978a84
S08-single-cell/pipeline/tools/rds_to_mtx.R                                    15475  068f42b87636cc22
S08-single-cell/work/build_embryo_panel.py                                     10320  3ee05b794cce54ef
S08-single-cell/work/build_gastrula_celltypes.py                                5930  d5fdd6da45663923
S08-single-cell/work/build_hydra_celltypes.py                                   6494  a86108c47d313ea7
S08-single-cell/work/build_nvect_whole_adult.py                                11790  be959bcf9ed11a37
S08-single-cell/work/dump_genematrix.R                                          1032  e4298ffbc1d9ba02
S08-single-cell/work/fix_c_markers.py                                           1997  3a7eab841b5f7a97
S08-single-cell/work/gse302686_markers.R                                        1044  232720c4a3b11ef7
S08-single-cell/work/label_transfer_published.py                               20269  73254e6710ee2f7b
S08-single-cell/work/make_c_qcstats.py                                          3617  5e214d1f9bc9590c
S08-single-cell/work/make_scoped_load.py                                        6211  1ccedd41417a3d67
S08-single-cell/work/merge_export_index.py                                      3358  f959ef8eff291cf6
S08-single-cell/work/merge_oarbu_solo.py                                        6699  f0094202eb56a88e
S08-single-cell/work/nvect_embryo_annotation_finding.md                         7139  716d2479047b1036
S08-single-cell/work/oarbu_label_transfer.py                                   11689  b0c49351f0c87520
S08-single-cell/work/panel_probe.py                                             5052  c0a5dfd7d5f311eb
S08-single-cell/work/patch_live_pubfig_card.py                                  9157  d6bd2a619dcd0fd4
S08-single-cell/work/probe_seurat.R                                              726  e208e5a32f0d9ad7
S08-single-cell/work/read_gse302686_meta.R                                      1034  d39e897bb27d2777
S08-single-cell/work/recon_c.py                                                 2039  bf6086114ea546f7
S08-single-cell/work/run_annot.sh                                                820  d5b1533c055e74ed
S08-single-cell/work/run_export5.sh                                              566  64fd37f79515a65f
S08-single-cell/work/run_hydra.sh                                                935  7748c6977dc2c15f
S08-single-cell/work/run_oarbu_finalize.sh                                      3826  28a880d12fa3becd
S08-single-cell/work/run_oarbu_full.sh                                          3778  aadbd8b8200846a4
S08-single-cell/work/run_oarbu_post.sh                                          3324  039cb1b54ea05ca1
S08-single-cell/work/run_transfer5.sh                                            869  d6d253da14e9c639
S09-proteomics/2.pipeline/01_parse_dataset_table.py                             3820  613156be8ba9afec
S09-proteomics/2.pipeline/02_fetch_pride.py                                    21992  83cdd54f025690f3
S09-proteomics/2.pipeline/03_build_search_db.py                                 5865  8a9014f58c6d4b72
S09-proteomics/2.pipeline/04_run_search.py                                     34435  8f9557726e049462
S09-proteomics/2.pipeline/05_fdr_percolator.py                                  6726  a32f3fa68823e21f
S09-proteomics/2.pipeline/06_map_to_genes.py                                   10787  aa7faa3d7b27f359
S09-proteomics/2.pipeline/07_build_tables.py                                   23902  1275bc8f603481e6
S09-proteomics/2.pipeline/08_coverage_audit.py                                  4925  e5b1d43b2f29e989
S09-proteomics/2.pipeline/09_enzyme_qc.py                                       4666  c14b330e75d3467c
S09-proteomics/2.pipeline/apl2mgf.py                                            9275  340708574a71b0dc
S09-proteomics/2.pipeline/cnido_common.py                                       8277  c63962078b3254f0
S09-proteomics/5.web/sql/load_proteomics.sh                                     5337  a7a697228a343dbb
S09-proteomics/README.md                                                        5029  b842a1412942ee2e
S10-epigenomics/ATAC/Exaiptasia_diaphana/1.sh                                   3534  03f29d788ef53257
S10-epigenomics/ATAC/Exaiptasia_diaphana/generate_bw.sh                         3208  35f5beec374bdbcc
S10-epigenomics/ATAC/Exaiptasia_diaphana/run_atac_pipeline.sh                   3514  fb620a1a5ad9b229
S10-epigenomics/ATAC/Hydra_vulgaris/generate_bw.sh                              2973  94edbe082a8b2fd2
S10-epigenomics/ATAC/Hydra_vulgaris/macs2.sh                                    5682  5ea1af3e526032fb
S10-epigenomics/ATAC/Hydra_vulgaris/run_atac_pipeline.sh                        3563  aa37db3d6c221a35
S10-epigenomics/ATAC/Nematostella_vectensis/generate_bw.sh                      3098  5c0b98c7ffd085da
S10-epigenomics/ATAC/Nematostella_vectensis/macs2.sh                            3372  1dc29e0cc462f22a
S10-epigenomics/ATAC/Nematostella_vectensis/run_atac_pipeline.sh                3567  b3b4a5d30ae90a42
S10-epigenomics/ChIP/chip_gfix.sh                                              11153  35815dd04e4ff24c
S10-epigenomics/ChIP/run_Exaiptasia_diaphana.sh                                12555  ebfb601d018048f5
S10-epigenomics/ChIP/run_Hydra_vulgaris.sh                                     28897  ac0dd0cf2b7a2c99
S10-epigenomics/ChIP/run_Nematostella_vectensis.sh                             38445  01b54d83354e0736
S10-epigenomics/DHS/run.sh                                                       539  0c545e2377086282
S10-epigenomics/WGBS/BSseq_cnidaria_analysis.sh                                 3349  af64a012e691255e
S10-epigenomics/WGBS/PE.sh                                                    339279  b09a6b93b0b3eb86
S10-epigenomics/WGBS/SE.sh                                                     15209  080d97b100cf9235
S10-epigenomics/WGBS/bismark.sh                                                  675  1bf8e8ac922384d1
S10-epigenomics/WGBS/build_bismark.sh                                            843  56c758dd8549d06e
S11-mags-and-metagenome/pipeline/00_refs.sh                                     2396  d4881cdff639f925
S11-mags-and-metagenome/pipeline/01_fetch.sh                                    3697  38ea55336ef2bc6e
S11-mags-and-metagenome/pipeline/02_predict.sh                                  4246  e70f58fdf29cf621
S11-mags-and-metagenome/pipeline/03_interproscan.sh                             4052  5eb72eae489d80d2
S11-mags-and-metagenome/pipeline/04_uniprot.sh                                  3899  0c9f25dbe5976625
S11-mags-and-metagenome/pipeline/05_kegg.sh                                     6842  d00314b01349fb0b
S11-mags-and-metagenome/pipeline/06_aggregate.py                               15095  52a14490561d186e
S11-mags-and-metagenome/pipeline/07_verify.py                                   5938  cfcca08648d3f32f
S11-mags-and-metagenome/pipeline/08_stage_dbload.py                             9407  8a1a23ef6e4a68b2
S11-mags-and-metagenome/pipeline/09_load_kenti_dbload.sh                        3443  6354fe2f9719635b
S11-mags-and-metagenome/pipeline/11_load_dbload.sh                              3708  6fcdcea9d9bd39b5
S11-mags-and-metagenome/pipeline/12_verify_load.py                              5998  56b553656a292668
S11-mags-and-metagenome/pipeline/14_load_cytherea.sh                            4196  2f6cf37695a035c7
S11-mags-and-metagenome/pipeline/_annotate_one.sh                               1838  f5fd9eaee7c1e77a
S11-mags-and-metagenome/pipeline/_kofam_chunk.sh                                1327  ebb32b0d00a5b801
S11-mags-and-metagenome/pipeline/bench_iprscan.sh                               2699  2b03412978d9dc4d
S11-mags-and-metagenome/pipeline/build_annot_ref.py                             6465  9a8857fe9ee2f4fb
S11-mags-and-metagenome/pipeline/env.sh                                         2493  5e2fd6b34e663bf8
S11-mags-and-metagenome/pipeline/fill_mags_checkm.sql                          19441  c16ea9dbb195f766
S11-mags-and-metagenome/pipeline/load_iprscan.php                              14922  06f79cba2dcec13f
S11-mags-and-metagenome/pipeline/load_kofam.php                                 7760  9163a13cb5533820
S11-mags-and-metagenome/pipeline/load_mag_annot.php                             4488  09b88c6d4e5c0349
S11-mags-and-metagenome/pipeline/load_pilot.sh                                  2807  77961a296c1e422c
S11-mags-and-metagenome/pipeline/run_iprscan.sh                                 2395  96d42eb4bd779d57
S11-mags-and-metagenome/pipeline/run_iprscan_chunked.sh                         3997  ce05ef3244f6ee89
S11-mags-and-metagenome/pipeline/run_iprscan_parallel.sh                        4963  9e3c9923e675a9b2
S11-mags-and-metagenome/pipeline/run_kofam.sh                                   2608  d046ce011d40a654
S11-mags-and-metagenome/pipeline/run_remaining.sh                               3304  9bd2cc5ef502cc7c
S11-mags-and-metagenome/pipeline/split_by_mag.py                                5021  bff6089b62da29ec
S12-phenotype-and-traits/build_phenotype_refs.php                              16088  742730bbc0731c96
S12-phenotype-and-traits/build_phenotype_v2.php                                23954  8f3ae5489f980302
S12-phenotype-and-traits/fetch_worms_traits.php                                22680  eed38cd37f06d312
S12-phenotype-and-traits/mito/fetch.py                                          5058  fec61345067d2e6f
S12-phenotype-and-traits/mito/fetch_annotated.py                                3408  66d0eee60e8b29e4
S12-phenotype-and-traits/mito/import.php                                        4536  4bd9c9fc94722252
S12-phenotype-and-traits/pheno_v2_verify.php                                    4830  3d702cb261bd7046
S12-phenotype-and-traits/pheno_worms_verify.php                                 5629  23be970d3124ad84
S13-database-and-website/ds_vocab_refresh.php                                   7705  f2455d23c8bdd7f4
S13-database-and-website/gene_literature_refresh.php                           28411  551d3c3ad55422ac
S13-database-and-website/gene_name_backfill.php                                11470  0e3b7bb10f36bad6
S13-database-and-website/genome_assembly_refresh.php                           20982  0806a3747ff321fc
S13-database-and-website/rnaseq_meta_refresh.php                               10643  b6f0c4a188753d78
S13-database-and-website/sc_gene_ids_build.php                                 11629  2b200de4d24029f6
S13-database-and-website/stats_refresh.php                                      2305  73f3e6506c37b61d
```
