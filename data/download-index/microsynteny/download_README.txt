Microsynteny analysis - Cnidaria
===============================

All files here belong to the all-against-all microsynteny analysis of 144
cnidarian species (10,296 unordered pairs), computed with DIAMOND + MCScanX and
visualised with Pansyn.  Companion pages:
  https://cnidosite.org/microsynteny.php
  https://cnidosite.org/microsynteny_detail.php?x=<codeA>&y=<codeB>


tables/
-------
microsynteny_statistics_144species.txt
    One row per species pair (10,296 rows).  Columns:
      Species                  pair id, "Sxxx_Syyy"
      All_genes_numbers        genes considered in the comparison
      Collinear_genes_numbers  genes inside a collinear block
      Microsyn_percentage      Collinear / All * 100
      Block_numbers            number of collinear blocks
      Max/Min/Average/Median_score   block scores
    This is the table behind the "144-species pair table" on the website.

pairwise_microsynteny_22species.tsv
    The 231 pairs among the 22 species that have chromosome-level assemblies.
      species_a, species_b, blocks, collinear_gene_pairs, min_protein_genes,
      gene_pair_density, source_collinearity
    gene_pair_density = collinear_gene_pairs / min_protein_genes; this is the
    value shown in the species x species heatmap.

matrix_gene_pair_density_22species.tsv
matrix_block_count_22species.tsv
    The same 231 pairs as symmetric 22 x 22 matrices (row/column order matches
    the header).  Diagonal is 0 (self-comparison is not meaningful).

pair_report_index_22species.tsv
    Per-pair status for those 231 pairs: blocks, collinear_gene_pairs,
    gene_pair_density, status (rendered / no_detected_chromosome_block /
    no_detected_block) and the figure paths.

microsynteny_statistics_144species.txt vs the above: the first covers all 144
species at gene level only; the second covers the 22 chromosome-level species.

species_code_map.tsv
    S-code -> species (and the genome/protein/GFF paths used as input).

doo22_chromosome_whitelist.tsv
    Which contigs were treated as named chromosomes for the 22 species.

extraction_audit.tsv
    Record of how the 22-species subset was extracted (counts, expected pairs).

Widely conserved two-gene windows anchored on S097 (Nematostella vectensis),
requiring support in >= 10 of the 143 non-reference species:
  run_summary.tsv         parameters and totals (114 soft-core clusters)
  soft_core_clusters.tsv  the clusters and the species supporting each
  soft_core_PAV.tsv       presence/absence of each cluster in each of the 144 species
  support_distribution.tsv  how many windows are supported by N species


pairs/
------
One pair per file set, named "<Sxxx_Syyy>".  Note the order is the one MCScanX
used and is not always sorted, so a pair may appear as S032_S121 or S121_S032.

  <pair>.collinearity
      Raw MCScanX output.  A commented header (parameters and per-pair
      statistics), then one "## Alignment" line per collinear block followed by
      that block's gene pairs:
          <index>:	<geneA>	<geneB>	<e-value>
      Files that are ~300 bytes contain the header only - that pair has no
      collinear block at all.

  <pair>_microsyn_genes.links
      The gene-pair link table used to draw the circos plot:
          <contigA> <startA> <endA> <contigB> <startB> <endB> color=<chr>

Figures (circos / dotplot, PNG + PDF) are on the pair pages, e.g.
  https://cnidosite.org/microsynteny_detail.php?x=S006&y=S112
and under https://cnidosite.org/images/microsynteny/


Citation
--------
Microsynteny blocks were identified using the Pansyn toolkit:
  Yu et al. 2024, https://pubmed.ncbi.nlm.nih.gov/38514839/
Collinearity was called with MCScanX:
  Wang et al. 2012, https://doi.org/10.1093/nar/gkr1293
