<?php
/* =====================================================================
 * 下载页的四组主题数据集 —— 转录组组装 / MAGs 注释 / 多组学 / 表型
 *
 * 与 includes/downloads.php 里那六份（busco、busco_summary、ubs、epigenome、
 * mito_genome、singlecell）是同一份白名单，只是按主题分了组。单独放一个文件
 * 是因为这一组条目多（27 份），混进去会把原来那段读不下去。
 *
 * 每份数据都从数据库现查现发（dataset_export.php），不预生成文件：这四类数据
 * 里只有转录组组装在磁盘上有产物（已直接放进 download/，见 download.php），
 * 其余都只存在于 MySQL。
 *
 * 可分组导出的那几份多一个 'filter' 描述：
 *     param  —— URL 参数名，例如 ?dataset=mag_interpro&mag=GCA_012267325.1
 *     column —— SQL 列名
 *     check  —— 取值存在性校验，%s 处填**已经转义过**的取值
 *     list   —— 供下载页列出可选项的查询（取值, 显示名）
 * 表名、列名、取值域一律写死在这里，dataset_export.php 不从请求里拼 SQL。
 * ===================================================================== */

if (!function_exists('cnido_dl_datasets_topic')) {
    function cnido_dl_datasets_topic()
    {
        return array(

            /* ================= 1. 转录组组装 ================= */

            'trans_assembly_species' => array(
                'group'  => 'trans_assembly',
                'title'  => 'Transcriptome assembly — per-species summary',
                'about'  => 'One row per species: assembly source, the RNA-seq run it was built '
                          . 'from, transcript and protein counts, N50, and how many proteins '
                          . 'carry each annotation type. This is the table behind the '
                          . 'Transcriptome Assembly module.',
                'table'  => 'trans_assembly_species',
                'from'   => 'trans_assembly_species',
                'cols'   => array('abbr1', 'abbr', 'species', 'class', 'source', 'run',
                                  'transcripts', 'bp', 'n50', 'longest', 'proteins', 'reps',
                                  'n_uniprot', 'n_pfam', 'n_panther', 'n_interpro', 'n_go',
                                  'n_kegg', 'notes'),
                'header' => null,
                'where'  => '',
                'xlsx'   => true,
            ),

            'trans_assembly_go' => array(
                'group'  => 'trans_assembly',
                'title'  => 'GO term dictionary',
                'about'  => 'The GO identifiers that appear in the per-species annotation tables, '
                          . 'with their category and name. Join on the go_id column to turn an '
                          . 'identifier into readable text.',
                'table'  => 'trans_assembly_go',
                'from'   => 'trans_assembly_go',
                'cols'   => array('go_id', 'category', 'name'),
                'header' => null,
                'where'  => '',
                'xlsx'   => false,
            ),

            'trans_assembly_ko' => array(
                'group'  => 'trans_assembly',
                'title'  => 'KEGG orthology dictionary',
                'about'  => 'The KEGG orthology (KO) identifiers used in the per-species '
                          . 'annotation tables, with their names.',
                'table'  => 'trans_assembly_ko',
                'from'   => 'trans_assembly_ko',
                'cols'   => array('ko', 'name'),
                'header' => null,
                'where'  => '',
                'xlsx'   => false,
            ),

            /* ================= 2. MAGs 及其注释 ================= */

            'mags_catalogue' => array(
                'group'  => 'mags',
                'title'  => 'MAG catalogue',
                'about'  => 'One row per metagenome-assembled genome: host species, taxonomy, '
                          . 'NCBI assembly accession and level, assembly size, contig N50, GC '
                          . 'content and CheckM completeness / contamination. The genome '
                          . 'sequences themselves are at NCBI under the accession shown.',
                'table'  => 'MAGs',
                'from'   => 'MAGs',
                'cols'   => array('class', 'host', 'Species', 'TaxonID', 'AssemblyAccession',
                                  'AssemblyLevel', 'AssembledSize', 'Nrcontigs', 'ContigN50',
                                  'GCPercent', 'CheckMcompleteness', 'CheckMcontamination',
                                  'pubmed', 'link'),
                'header' => array('class', 'host', 'species', 'taxon_id', 'assembly_accession',
                                  'assembly_level', 'assembled_size', 'n_contigs', 'contig_n50',
                                  'gc_percent', 'checkm_completeness', 'checkm_contamination',
                                  'pubmed', 'link'),
                'where'  => '',
                'xlsx'   => true,
            ),

            'mag_annot' => array(
                'group'  => 'mags',
                'title'  => 'MAG protein annotation',
                'about'  => 'One row per predicted protein in each MAG, with its locus tag and '
                          . 'the description taken from the annotation. Filter by assembly '
                          . 'accession to get a single MAG.',
                'table'  => 'mag_annot',
                'from'   => 'mag_annot',
                'cols'   => array('mag', 'protein', 'locus_tag', 'description'),
                'header' => null,
                'where'  => '',
                'xlsx'   => false,
                'filter' => array(
                    'param'  => 'mag',
                    'column' => 'mag',
                    'check'  => "SELECT 1 FROM MAGs WHERE AssemblyAccession = '%s' LIMIT 1",
                    'list'   => "SELECT m.AssemblyAccession, m.Species
                                   FROM MAGs m
                                  WHERE EXISTS (SELECT 1 FROM mag_annot a
                                                 WHERE a.mag = m.AssemblyAccession)
                                  ORDER BY m.Species",
                    'noun'   => 'MAG assembly accession',
                ),
            ),

            'mag_go_terms' => array(
                'group'  => 'mags',
                'title'  => 'MAG GO terms',
                'about'  => 'Gene Ontology assignments for MAG proteins, one row per protein '
                          . 'and term, with the GO category (molecular function, biological '
                          . 'process, cellular component).',
                'table'  => 'mag_go_terms',
                'from'   => 'mag_go_terms',
                'cols'   => array('mag', 'protein', 'GO_term', 'Category', 'Description',
                                  'Source', 'URL'),
                'header' => null,
                'where'  => '',
                'xlsx'   => false,
                'filter' => array(
                    'param'  => 'mag',
                    'column' => 'mag',
                    'check'  => "SELECT 1 FROM MAGs WHERE AssemblyAccession = '%s' LIMIT 1",
                    'list'   => "SELECT m.AssemblyAccession, m.Species
                                   FROM MAGs m
                                  WHERE EXISTS (SELECT 1 FROM mag_go_terms a
                                                 WHERE a.mag = m.AssemblyAccession)
                                  ORDER BY m.Species",
                    'noun'   => 'MAG assembly accession',
                ),
            ),

            'mag_interpro' => array(
                'group'  => 'mags',
                'title'  => 'MAG InterPro signatures',
                'about'  => 'InterPro matches for MAG proteins, one row per protein and '
                          . 'signature, with the signature type (domain, family, homologous '
                          . 'superfamily, ...) and its description.',
                'table'  => 'mag_interpro',
                'from'   => 'mag_interpro',
                'cols'   => array('mag', 'protein', 'InterPro_term', 'Type', 'Description',
                                  'Source', 'URL'),
                'header' => null,
                'where'  => '',
                'xlsx'   => false,
                'filter' => array(
                    'param'  => 'mag',
                    'column' => 'mag',
                    'check'  => "SELECT 1 FROM MAGs WHERE AssemblyAccession = '%s' LIMIT 1",
                    'list'   => "SELECT m.AssemblyAccession, m.Species
                                   FROM MAGs m
                                  WHERE EXISTS (SELECT 1 FROM mag_interpro a
                                                 WHERE a.mag = m.AssemblyAccession)
                                  ORDER BY m.Species",
                    'noun'   => 'MAG assembly accession',
                ),
            ),

            'mag_kegg_terms' => array(
                'group'  => 'mags',
                'title'  => 'MAG KEGG orthology and pathways',
                'about'  => 'KEGG assignments for MAG proteins: KO identifier, abbreviation, '
                          . 'EC number and the pathway it belongs to.',
                'table'  => 'mag_kegg_terms',
                'from'   => 'mag_kegg_terms',
                'cols'   => array('mag', 'protein', 'KO', 'Abbreviation', 'Enzymes',
                                  'Enzyme_ID', 'Pathway', 'Pathway_ID', 'Source'),
                'header' => null,
                'where'  => '',
                'xlsx'   => false,
                'filter' => array(
                    'param'  => 'mag',
                    'column' => 'mag',
                    'check'  => "SELECT 1 FROM MAGs WHERE AssemblyAccession = '%s' LIMIT 1",
                    'list'   => "SELECT m.AssemblyAccession, m.Species
                                   FROM MAGs m
                                  WHERE EXISTS (SELECT 1 FROM mag_kegg_terms a
                                                 WHERE a.mag = m.AssemblyAccession)
                                  ORDER BY m.Species",
                    'noun'   => 'MAG assembly accession',
                ),
            ),

            'mag_pfam_hits' => array(
                'group'  => 'mags',
                'title'  => 'MAG Pfam hits',
                'about'  => 'Pfam matches for MAG proteins, one row per protein and Pfam '
                          . 'entry, with the entry name and description.',
                'table'  => 'mag_pfam_hits',
                'from'   => 'mag_pfam_hits',
                'cols'   => array('mag', 'protein', 'Pfam_accession', 'Pfam_name',
                                  'Description', 'Type', 'Source', 'URL'),
                'header' => null,
                'where'  => '',
                'xlsx'   => false,
                'filter' => array(
                    'param'  => 'mag',
                    'column' => 'mag',
                    'check'  => "SELECT 1 FROM MAGs WHERE AssemblyAccession = '%s' LIMIT 1",
                    'list'   => "SELECT m.AssemblyAccession, m.Species
                                   FROM MAGs m
                                  WHERE EXISTS (SELECT 1 FROM mag_pfam_hits a
                                                 WHERE a.mag = m.AssemblyAccession)
                                  ORDER BY m.Species",
                    'noun'   => 'MAG assembly accession',
                ),
            ),

            'mag_panther_hits' => array(
                'group'  => 'mags',
                'title'  => 'MAG PANTHER hits',
                'about'  => 'PANTHER family assignments for MAG proteins, one row per protein '
                          . 'and family, with the family name and the method that produced it.',
                'table'  => 'mag_panther_hits',
                'from'   => 'mag_panther_hits',
                'cols'   => array('mag', 'protein', 'id', 'anno', 'method', 'url'),
                'header' => array('mag', 'protein', 'panther_id', 'panther_annotation',
                                  'method', 'url'),
                'where'  => '',
                'xlsx'   => false,
                'filter' => array(
                    'param'  => 'mag',
                    'column' => 'mag',
                    'check'  => "SELECT 1 FROM MAGs WHERE AssemblyAccession = '%s' LIMIT 1",
                    'list'   => "SELECT m.AssemblyAccession, m.Species
                                   FROM MAGs m
                                  WHERE EXISTS (SELECT 1 FROM mag_panther_hits a
                                                 WHERE a.mag = m.AssemblyAccession)
                                  ORDER BY m.Species",
                    'noun'   => 'MAG assembly accession',
                ),
            ),

            /* ================= 3. 多组学 ================= */

            'proteomic_datasets' => array(
                'group'  => 'omics',
                'title'  => 'Proteomics — dataset list and search parameters',
                'about'  => 'One row per reanalysed proteomics dataset (PXD accession, species, '
                          . 'tissue, instrument), with both the parameters the original authors '
                          . 'reported and the parameters CnidoSite used for the uniform '
                          . 'reanalysis, plus identification counts.',
                'table'  => 'proteomic_datasets',
                'from'   => 'proteomic_datasets',
                'cols'   => array('dataset_id', 'pxd', 'species', 'taxon_class', 'tissue',
                                  'treatment', 'instrument', 'pubmed', 'orig_engine',
                                  'orig_database', 'orig_precursor', 'orig_fragment', 'orig_fdr',
                                  'cnido_engine', 'cnido_enzyme', 'cnido_termini', 'cnido_missed',
                                  'cnido_precursor', 'cnido_fragment', 'cnido_fixed',
                                  'cnido_variable', 'cnido_fdr_psm', 'cnido_fdr_prot',
                                  'cnido_decoy', 'cnido_quant', 'proteome_file',
                                  'proteome_source', 'proteome_source_type', 'proteome_note',
                                  'proteome_is_surrogate', 'status', 'status_note',
                                  'pipeline_version', '`release`', 'date_incorporated',
                                  'file_count', 'n_psms', 'n_peptides', 'n_proteins'),
                'header' => null,
                'where'  => '',
                'xlsx'   => true,
            ),

            'proteomic_proteins' => array(
                'group'  => 'omics',
                'title'  => 'Proteomics — identified proteins',
                'about'  => 'One row per protein identified in a dataset: the transcript it maps '
                          . 'to (which links it to the Transcriptome Assembly annotation), its '
                          . 'peptide and PSM counts, sequence coverage, length, best q-value and '
                          . 'the cRAP contaminant flag.',
                'table'  => 'proteomic_proteins',
                'from'   => 'proteomic_proteins',
                'cols'   => array('dataset_id', 'gene_id', 'protein_id', 'links_gene', 'n_psms',
                                  'n_unique_peptides', 'coverage_pct', 'length', 'best_q',
                                  'description', 'is_contaminant'),
                'header' => null,
                'where'  => '',
                'xlsx'   => false,
            ),

            'proteomic_peptides' => array(
                'group'  => 'omics',
                'title'  => 'Proteomics — identified peptides',
                'about'  => 'One row per peptide-to-protein identification behind the protein '
                          . 'table, with its q-value. This is the evidence layer: every protein '
                          . 'row can be reconstructed from these.',
                'table'  => 'proteomic_peptides',
                'from'   => 'proteomic_peptides',
                'cols'   => array('dataset_id', 'gene_id', 'protein_id', 'peptide', 'q_value'),
                'header' => null,
                'where'  => '',
                'xlsx'   => false,
            ),

            'proteome_data' => array(
                'group'  => 'omics',
                'title'  => 'Proteome sample metadata',
                'about'  => 'One row per published proteomics study in the Proteomic Data '
                          . 'module: species, tissue, treatment, project and publication.',
                'table'  => 'proteome_data',
                'from'   => 'proteome_data',
                'cols'   => array('Class', 'species', 'tissue', 'Treatment', 'Project',
                                  'pubmed', 'link'),
                'header' => null,
                'where'  => '',
                'xlsx'   => true,
            ),

            'metaG' => array(
                'group'  => 'omics',
                'title'  => 'Metagenome sample list',
                'about'  => 'One row per metagenomic sequencing run behind the Metagenomics '
                          . 'module: species, project, study, run accession, layout and sample '
                          . 'context. The MAGs assembled from these runs are in the MAG '
                          . 'catalogue above.',
                'table'  => 'metaG',
                'from'   => 'metaG',
                'cols'   => array('Class', 'Species', 'Project', 'Study', 'Experiment', 'Run',
                                  'Layout', 'tissue', 'dev', 'Treatment'),
                'header' => null,
                'where'  => '',
                'xlsx'   => true,
            ),

            'singlecell_atlas' => array(
                'group'  => 'omics',
                'title'  => 'Single-cell atlas index',
                'about'  => 'One row per single-cell dataset: species, tissue, stage, platform, '
                          . 'final cell count, number of cell types and clusters, source '
                          . 'accessions, reference genome and pipeline version.',
                'table'  => 'singlecell_atlas',
                'from'   => 'singlecell_atlas',
                'cols'   => array('dataset_id', 'cnidosite_row', 'species', 'class',
                                  'tissue_organ', 'stage', 'platform_class', 'n_cells_final',
                                  'n_cell_types', 'n_clusters', 'n_samples',
                                  'annotation_provenance', 'bioproject', 'sra_study',
                                  'geo_series', 'reference_genome', 'genome_version',
                                  'pipeline_version', 'cell_type_source', 'asset_dir',
                                  'updated_utc', 'published_figure', 'cluster_source',
                                  'embedding_source'),
                'header' => null,
                'where'  => '',
                'xlsx'   => true,
            ),

            'singlecell_celltype' => array(
                'group'  => 'omics',
                'title'  => 'Single-cell cell-type composition',
                'about'  => 'One row per cell type per dataset: how many cells it contains and '
                          . 'what share of the dataset that is.',
                'table'  => 'singlecell_celltype',
                'from'   => 'singlecell_celltype',
                'cols'   => array('dataset_id', 'cell_type', 'n_cells', 'pct_cells'),
                'header' => null,
                'where'  => '',
                'xlsx'   => true,
            ),

            'singlecell_markers' => array(
                'group'  => 'omics',
                'title'  => 'Single-cell marker genes',
                'about'  => 'One row per marker gene per cell type: rank, log2 fold change, '
                          . 'adjusted p-value, score and the fraction of cells expressing it.',
                'table'  => 'singlecell_markers',
                'from'   => 'singlecell_markers',
                /* `rank` is a reserved word in MySQL 8 (window functions) */
                'cols'   => array('dataset_id', 'cell_type', 'gene', '`rank`', 'log2fc', 'padj',
                                  'score', 'pct_in'),
                'header' => null,
                'where'  => '',
                'xlsx'   => true,
            ),

            'singlecell_qc' => array(
                'group'  => 'omics',
                'title'  => 'Single-cell processing and QC metrics',
                'about'  => 'One row per dataset per platform: cells reported, raw, after '
                          . 'cell-level QC and after doublet removal, the doublet rate, '
                          . 'mitochondrial content before and after filtering, and the '
                          . 'thresholds and integration method used.',
                'table'  => 'singlecell_qc',
                'from'   => 'singlecell_qc',
                'cols'   => array('dataset_id', 'platform_class', 'platform_confidence',
                                  'n_cells_reported', 'n_cells_raw', 'n_cells_after_cell_qc',
                                  'n_doublets_removed', 'doublet_rate', 'n_cells_final',
                                  'pct_cells_retained', 'threshold_mode', 'threshold_detail',
                                  'mito_genes_n', 'mito_filtering_available',
                                  'mito_pct_median_raw', 'mito_pct_p95_raw',
                                  'mito_pct_median_final', 'mito_pct_p95_final',
                                  'doublet_method', 'integration_method', 'n_samples',
                                  'analysis_status', 'published_figure'),
                'header' => null,
                'where'  => '',
                'xlsx'   => true,
            ),

            'mirna_seq' => array(
                'group'  => 'omics',
                'title'  => 'miRNA sequences',
                'about'  => 'One row per miRNA locus: the MirGeneDB identifier and the mature, '
                          . 'star, precursor, loop and 5p/3p sequences.',
                'table'  => 'mirna_seq',
                'from'   => 'mirna_seq',
                'cols'   => array('species', 'abbr', 'MirGeneDB_ID', 'mature_ID', 'mature',
                                  'star_id', 'star', 'pre_id', 'pre', 'loop_id', 'loop1',
                                  '5p_id', '5p', '3p_ID', '3p1'),
                'header' => null,
                'where'  => '',
                'xlsx'   => true,
            ),

            /* ================= 4. 表型数据 ================= */

            'phenotype' => array(
                'group'  => 'phenotype',
                'title'  => 'Phenotype records',
                'about'  => 'One row per trait record: species, trait name and category, value '
                          . 'and unit, geography (region, latitude, longitude), methodology, '
                          . 'the context the measurement was taken in, and the source it came '
                          . 'from with that source\'s own record id.',
                'table'  => 'phenotype',
                'from'   => 'phenotype',
                'cols'   => array('id', 'Class', 'species', 'trait_name', 'trait_category',
                                  'value', 'traitunit', 'region', 'latitude', 'longitude',
                                  'methodology', 'value_type', 'context', 'source',
                                  'source_record_id', 'source_resource_id', 'n_records'),
                'header' => null,
                'where'  => '',
                'xlsx'   => false,
            ),

            'phenotype_trait_dict' => array(
                'group'  => 'phenotype',
                'title'  => 'Phenotype trait dictionary',
                'about'  => 'One row per trait, per source: its category, data type, unit, the '
                          . 'values it is allowed to take, and a description. Use it to '
                          . 'interpret trait_name in the record table.',
                'table'  => 'phenotype_trait_dict',
                'from'   => 'phenotype_trait_dict',
                'cols'   => array('source', 'trait_name', 'trait_category', 'data_type',
                                  'traitunit', 'allowed_values', 'trait_desc'),
                'header' => null,
                'where'  => '',
                'xlsx'   => true,
            ),

            'phenotype_species_map' => array(
                'group'  => 'phenotype',
                'title'  => 'Phenotype species name mapping',
                'about'  => 'How each source spells a species, and what that name resolves to: '
                          . 'the accepted name, its AphiaID (WoRMS), taxonomic status, class, '
                          . 'order and family. This is what lets records from different sources '
                          . 'be compared.',
                'table'  => 'phenotype_species_map',
                'from'   => 'phenotype_species_map',
                'cols'   => array('source', 'species', 'valid_name', 'aphia_id', 'status',
                                  'class', 'order_name', 'family'),
                'header' => null,
                'where'  => '',
                'xlsx'   => true,
            ),

            'phenotype_source_resource' => array(
                'group'  => 'phenotype',
                'title'  => 'Phenotype source references',
                'about'  => 'One row per source: its identifier, authors, year, title, the '
                          . 'container it was published in, DOI and resource type. Join on '
                          . 'source_resource_id from the record table.',
                'table'  => 'phenotype_source_resource',
                'from'   => 'phenotype_source_resource',
                'cols'   => array('source', 'resource_id', 'authors', 'year', 'title',
                                  'container', 'doi', 'resource_type'),
                'header' => null,
                'where'  => '',
                'xlsx'   => true,
            ),
        );
    }
}
