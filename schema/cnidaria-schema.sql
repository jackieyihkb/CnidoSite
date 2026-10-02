
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AACUM_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AACUM_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AACUM_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AACUM_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AACUM_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AACUM_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AACUM_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AACUM_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AACUM_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AACUM_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AALAT_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AALAT_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`GO_term`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AALAT_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`InterPro_term`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AALAT_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AALAT_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AALAT_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AALAT_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AAURI1_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AAURI1_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AAURI1_TPM` (
  `Gene` text,
  `SRR7992468_polyp_endoderm_from_body_column_polyp` text,
  `SRR7992469_polyp_head_region_polyp` text,
  `SRR7992480_bell_edge_without_ropalia_juvenile` text,
  `SRR7992481_polyp_ectoderm_from_body_column_polyp` text,
  `SRR7992482_complete_juvenile_juvenile` text,
  `SRR7992483_13_ropalia_juvenile` text,
  `SRR7992484_complete_polyp_polyp` text,
  `SRR7992485_complete_strobila_strobila` text,
  `SRR7992486_complete_polyp_polyp` text,
  `SRR7992487_complete_polyp_polyp` text,
  `SRR8090255_polyp_20h_induction_with_5M2MI_20C_polyp` text,
  `SRR8090256_polyp_24h_induction_with_5M2MI_20C_polyp` text,
  `SRR8090257_ropalia` text,
  `SRR8090258_bell_edge` text,
  `SRR8090259_strobila_heads_strobila` text,
  `SRR8090260_strobila_segments_strobila` text,
  `SRR8090261_polyp_not_induced_polyp` text,
  `SRR8090262_polyp_not_induced_polyp` text,
  `SRR8090263_polyp_24h_induction_with_5M2MI_polyp` text,
  `SRR8090264_polyp_12h_induction_with_5M2MI_20C_polyp` text,
  `SRR8090265_strobila_non_segmented_part_strobila` text,
  `SRR8090266_3_juvenile_7mm_in_diameter` text,
  `avg` text,
  `std` text,
  KEY `ix_gene` (`Gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AAURI1_coexpress_negative` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AAURI1_coexpress_positive` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AAURI1_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AAURI1_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AAURI1_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AAURI1_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AAURI1_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AAURI1_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AAURI2_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AAURI2_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AAURI2_TPM` (
  `Gene` text,
  `SRR8040387_complete_polyp_induced_20h` text,
  `SRR8040388_strobila_head` text,
  `SRR8040389_complete_polyp_not_induced` text,
  `SRR8040390_complete_polyp_induced_12h` text,
  `SRR8040395_strobila_segments` text,
  `SRR8040396_strobila_non_segmented_part` text,
  `SRR8040397_polyp_endoderm_from_body_column` text,
  `SRR8040398_polyp_head_region` text,
  `SRR8040399_polyp_ectoderm_from_body_column` text,
  `SRR8040400_jellyfish_mesoglea_cells` text,
  `SRR8040401_jellyfish_bell_middle_part` text,
  `SRR8040402_jellyfish_oral_arm` text,
  `SRR8040403_jellyfish_bell_edge` text,
  `SRR8040404_jellyfish_gastric_filaments` text,
  `SRR8040405_jellyfish_mesoglea_cells` text,
  `SRR8040406_jellyfish_striated_muscle_layer` text,
  `SRR8040407_jellyfish_canal_system_endoderm` text,
  `SRR8040408_complete_juvenile_jellyfish_2_5cm_in_diameter` text,
  `SRR8040409_jellyfish_ectoderm_from_the_upper_side_of_the_bell` text,
  `SRR8040410_strobila_foot` text,
  `SRR8040411_complete_juvenile_jellyfish_1cm_in_diameter` text,
  `SRR8089698_jellyfish_ectoderm_muscle_layer` text,
  `SRR8089699_jellyfish_ectoderm_upper_bell_surface` text,
  `SRR8089700_male_gonad` text,
  `SRR8089701_planula_complete` text,
  `SRR8089702_tentacles_distal_part` text,
  `SRR8089703_bell_edge_ectoderm_and_canal` text,
  `SRR8089704_mesoglea_cells` text,
  `SRR8089705_endoderm_canal_system` text,
  `avg` text,
  `std` text,
  KEY `ix_gene` (`Gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AAURI2_coexpress_negative` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AAURI2_coexpress_positive` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AAURI2_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AAURI2_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AAURI2_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AAURI2_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AAURI2_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AAURI2_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AAURI_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AAURI_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AAUST_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AAUST_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AAUST_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AAUST_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AAUST_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AAUST_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AAUST_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AAUST_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AAUST_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AAUST_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AAWI_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AAWI_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AAWI_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AAWI_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AAWI_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AAWI_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AAWI_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AAWI_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AAWI_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AAWI_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ACERV_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ACERV_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ACERV_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ACERV_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ACERV_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ACERV_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ACERV_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ACERV_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ACERV_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ACOER_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ACOER_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ACOER_TPM` (
  `Gene` text,
  `SRR25982246_whole_organism_T_A1ES` text,
  `SRR25982247_whole_organism_T_C3ES` text,
  `SRR25982248_whole_organism_T_C2ES` text,
  `SRR25982249_whole_organism_T_C1ES` text,
  `SRR25982250_whole_organism_T_A3P` text,
  `SRR25982251_whole_organism_T_A2P` text,
  `SRR25982252_whole_organism_T_A1P` text,
  `SRR25982253_whole_organism_T_AAS3` text,
  `SRR25982254_whole_organism_T_AAS2` text,
  `SRR25982255_whole_organism_T_AAS1` text,
  `SRR25982256_whole_organism_T_AES3` text,
  `SRR25982257_whole_organism_T_AES2` text,
  `SRR25982258_whole_organism_T_AES1` text,
  `SRR25982259_whole_organism_T_AE3` text,
  `SRR25982260_whole_organism_T_AE2` text,
  `SRR25982261_whole_organism_T_AE1` text,
  `SRR25982262_whole_organism_T_C3E` text,
  `SRR25982263_whole_organism_T_C3P` text,
  `SRR25982264_whole_organism_T_C2E` text,
  `SRR25982265_whole_organism_T_C1E` text,
  `SRR25982266_whole_organism_T_A3AS` text,
  `SRR25982267_whole_organism_T_A2AS` text,
  `SRR25982268_whole_organism_T_A1AS` text,
  `SRR25982269_whole_organism_T_C3AS` text,
  `SRR25982270_whole_organism_T_C2AS` text,
  `SRR25982271_whole_organism_T_C1AS` text,
  `SRR25982272_whole_organism_T_A3ES` text,
  `SRR25982273_whole_organism_T_A2ES` text,
  `SRR25982274_whole_organism_T_C2P` text,
  `SRR25982275_whole_organism_T_C1P` text,
  `avg` text,
  `std` text,
  KEY `ix_gene` (`Gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ACOER_coexpress_negative` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ACOER_coexpress_positive` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ACOER_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`GO_term`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ACOER_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`InterPro_term`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ACOER_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ACOER_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ACOER_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ACOER_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ACOER_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ACOER_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ACYTH_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ACYTH_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ACYTH_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ACYTH_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ACYTH_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ACYTH_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ACYTH_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ACYTH_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ACYTH_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ACYTH_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ADIGI_DHS` (
  `Sample` text,
  `Chr` text,
  `start` text,
  `end` text,
  `peak_name` text,
  `Summit` text,
  `Pileup` text,
  `peak_location` text,
  `geneChr` text,
  `geneStart` text,
  `geneEnd` text,
  `geneLength` text,
  `distanceToTSS` text,
  `protein_id` text,
  `description` text,
  KEY `idx_protein_id` (`protein_id`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ADIGI_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ADIGI_TPM` (
  `Gene` text,
  `SRR23047206_Coral_branch_adult` text,
  `SRR23047207_Coral_branch_adult` text,
  `SRR23047208_Coral_branch_adult` text,
  `SRR23047209_Coral_branch_adult` text,
  `SRR23047210_Coral_branch_adult` text,
  `SRR23047211_Coral_branch_adult` text,
  `SRR23047212_Coral_branch_adult` text,
  `SRR23047213_Coral_branch_adult` text,
  `SRR23047214_Coral_branch_adult` text,
  `SRR23047215_Coral_branch_adult` text,
  `SRR23047216_Coral_branch_adult` text,
  `SRR23047217_Coral_branch_adult` text,
  `SRR23047218_Coral_branch_adult` text,
  `SRR23047219_Coral_branch_adult` text,
  `SRR23047220_Coral_branch_adult` text,
  `SRR23047221_Coral_branch_adult` text,
  `SRR23047222_Coral_branch_adult` text,
  `SRR23047223_Coral_branch_adult` text,
  `SRR23047224_Coral_branch_adult` text,
  `SRR23047225_Coral_branch_adult` text,
  `SRR23047226_Coral_branch_adult` text,
  `SRR23047227_Coral_branch_adult` text,
  `SRR23047228_Coral_branch_adult` text,
  `SRR23047229_Coral_branch_adult` text,
  `SRR23047230_Coral_branch_adult` text,
  `SRR23047231_Coral_branch_adult` text,
  `SRR23047232_Coral_branch_adult` text,
  `SRR23047233_Coral_branch_adult` text,
  `SRR23047234_Coral_branch_adult` text,
  `SRR23047235_Coral_branch_adult` text,
  `SRR23047236_Coral_branch_adult` text,
  `SRR23047237_Coral_branch_adult` text,
  `SRR23047238_Coral_branch_adult` text,
  `SRR23047239_Coral_branch_adult` text,
  `SRR23047240_Coral_branch_adult` text,
  `SRR23047241_Coral_branch_adult` text,
  `SRR23047242_Coral_branch_adult` text,
  `SRR23047243_Coral_branch_adult` text,
  `SRR23047244_Coral_branch_adult` text,
  `avg` text,
  `std` text,
  KEY `ix_gene` (`Gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ADIGI_coexpress_negative` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ADIGI_coexpress_positive` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ADIGI_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ADIGI_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ADIGI_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ADIGI_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ADIGI_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ADIGI_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ADIGI_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ADIGI_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AECHI_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AECHI_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AECHI_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AECHI_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AECHI_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AECHI_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AECHI_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AECHI_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AECHI_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AECHI_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AEQUI_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AEQUI_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AEQUI_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AEQUI_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AEQUI_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AEQUI_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AEQUI_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AEQUI_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AEQUI_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AEQUI_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AFLOR_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AFLOR_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AFLOR_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AFLOR_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AFLOR_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AFLOR_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AFLOR_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AFLOR_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AFLOR_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AFLOR_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AGEMM_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AGEMM_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AGEMM_TPM` (
  `Gene` text,
  `SRR2169558_branch_tip` text,
  `SRR3169421_branch_tip` text,
  `SRR3169422_branch_tip` text,
  `SRR3169423_branch_tip` text,
  `SRR3169425_branch_tip` text,
  `SRR3169426_branch_tip` text,
  `SRR3169518_branch_tip` text,
  `SRR3169527_branch_tip` text,
  `SRR3169528_branch_tip` text,
  `SRR3169529_branch_tip` text,
  `SRR3169530_branch_tip` text,
  `SRR3169531_branch_tip` text,
  `SRR3169532_branch_tip` text,
  `SRR3169533_branch_tip` text,
  `SRR3169534_branch_tip` text,
  `SRR3169535_branch_tip` text,
  `SRR3169536_branch_tip` text,
  `SRR3169537_branch_tip` text,
  `SRR3169538_branch_tip` text,
  `SRR3169539_branch_tip` text,
  `SRR3169540_branch_tip` text,
  `SRR3169541_branch_tip` text,
  `SRR3182410_branch_tip` text,
  `SRR3182448_branch_tip` text,
  `SRR3182557_branch_tip` text,
  `SRR3182684_branch_tip` text,
  `SRR3182685_branch_tip` text,
  `SRR3182686_branch_tip` text,
  `SRR3182775_branch_tip` text,
  `SRR3182776_branch_tip` text,
  `SRR3182777_branch_tip` text,
  `SRR3182778_branch_tip` text,
  `SRR3182779_branch_tip` text,
  `SRR3182780_branch_tip` text,
  `SRR3182781_branch_tip` text,
  `SRR3182784_branch_tip` text,
  `SRR3182785_branch_tip` text,
  `SRR3182786_branch_tip` text,
  `SRR3182787_branch_tip` text,
  `SRR3182788_branch_tip` text,
  `SRR3182789_branch_tip` text,
  `SRR3182790_branch_tip` text,
  `SRR3182791_branch_tip` text,
  `SRR3182792_branch_tip` text,
  `SRR3182793_branch_tip` text,
  `SRR3182794_branch_tip` text,
  `SRR3223317_branch_tip` text,
  `SRR3223319_branch_tip` text,
  `avg` text,
  `std` text,
  KEY `ix_gene` (`Gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AGEMM_coexpress_negative` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AGEMM_coexpress_positive` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AGEMM_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AGEMM_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AGEMM_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AGEMM_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AGEMM_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AGEMM_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AGEMM_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AGEMM_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AHEMP_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AHEMP_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AHEMP_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AHEMP_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AHEMP_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AHEMP_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AHEMP_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AHEMP_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AHEMP_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AHEMP_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AHYAC_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AHYAC_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AHYAC_TPM` (
  `Gene` text,
  `SRR4029951_animal_tissue_AH06_community` text,
  `SRR4029952_animal_tissue_AH06_community` text,
  `SRR4029953_animal_tissue_AH06_community` text,
  `SRR4029954_animal_tissue_AH06_community` text,
  `SRR4029955_animal_tissue_AH06_community` text,
  `SRR4029956_animal_tissue_AH06_community` text,
  `SRR4029957_animal_tissue_AH06_community` text,
  `SRR4029958_animal_tissue_AH06_community` text,
  `SRR4029959_animal_tissue_AH06_community` text,
  `SRR4029960_animal_tissue_AH75_community` text,
  `SRR4029961_animal_tissue_AH75_community` text,
  `SRR4029962_animal_tissue_AH75_community` text,
  `SRR4029963_animal_tissue_AH06_community` text,
  `SRR4029964_animal_tissue_AH75_community` text,
  `SRR4029965_animal_tissue_AH75_community` text,
  `SRR4029966_animal_tissue_AH75_community` text,
  `SRR4029967_animal_tissue_AH75_community` text,
  `SRR4029968_animal_tissue_AH75_community` text,
  `SRR4029969_animal_tissue_AH75_community` text,
  `SRR4029970_animal_tissue_AH75_community` text,
  `SRR4029971_animal_tissue_AH75_community` text,
  `SRR4029972_animal_tissue_AH75_community` text,
  `SRR4029973_animal_tissue_AH75_community` text,
  `SRR4029974_animal_tissue_AH06_community` text,
  `SRR4029975_animal_tissue_AH75_community` text,
  `SRR4029976_animal_tissue_AH75_community` text,
  `SRR4029977_animal_tissue_AH75_community` text,
  `SRR4029978_animal_tissue_AH75_community` text,
  `SRR4029979_animal_tissue_AH88_community` text,
  `SRR4029980_animal_tissue_AH88_community` text,
  `SRR4029981_animal_tissue_AH88_community` text,
  `SRR4029982_animal_tissue_AH88_community` text,
  `SRR4029983_animal_tissue_AH88_community` text,
  `SRR4029984_animal_tissue_AH88_community` text,
  `SRR4029985_animal_tissue_AH06_community` text,
  `SRR4029986_animal_tissue_AH88_community` text,
  `SRR4029987_animal_tissue_AH88_community` text,
  `SRR4029988_animal_tissue_AH88_community` text,
  `SRR4029989_animal_tissue_AH88_community` text,
  `SRR4029990_animal_tissue_AH88_community` text,
  `SRR4029991_animal_tissue_AH88_community` text,
  `SRR4029992_animal_tissue_AH88_community` text,
  `SRR4029993_animal_tissue_AH88_community` text,
  `SRR4029994_animal_tissue_AH88_community` text,
  `SRR4029995_animal_tissue_AH88_community` text,
  `SRR4029996_animal_tissue_AH06_community` text,
  `SRR4029997_animal_tissue_AH88_community` text,
  `SRR4029998_animal_tissue_AH06_community` text,
  `SRR4029999_animal_tissue_AH06_community` text,
  `SRR4030000_animal_tissue_AH06_community` text,
  `SRR4030001_animal_tissue_AH06_community` text,
  `avg` text,
  `std` text,
  KEY `ix_gene` (`Gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AHYAC_coexpress_negative` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AHYAC_coexpress_positive` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AHYAC_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AHYAC_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AHYAC_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AHYAC_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AHYAC_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AHYAC_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AHYAC_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AHYAC_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AIDSS_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AIDSS_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AIDSS_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AIDSS_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AIDSS_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AIDSS_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AIDSS_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AIDSS_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AIDSS_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AIDSS_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AINTE_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AINTE_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AINTE_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AINTE_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AINTE_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AINTE_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AINTE_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AINTE_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AINTE_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AINTE_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ALIUI_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ALIUI_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ALIUI_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ALIUI_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ALIUI_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ALIUI_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ALIUI_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ALIUI_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ALIUI_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ALIUI_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ALORI_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ALORI_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ALORI_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ALORI_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ALORI_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ALORI_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ALORI_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ALORI_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ALORI_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ALORI_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMEDI_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMEDI_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMEDI_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMEDI_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMEDI_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMEDI_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMEDI_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMEDI_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMEDI_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMEDI_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMICR_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMICR_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMICR_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMICR_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMICR_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMICR_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMICR_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMICR_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMICR_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMICR_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMILL_BS_planula` (
  `Type` text,
  `seqnames` text,
  `start` text,
  `end` text,
  `meth_level` text,
  `annotation` text,
  `chr` text,
  `geneStart` text,
  `geneEnd` text,
  `geneLength` text,
  `distanceToTSS` text,
  `protein_id` text,
  `product` text,
  KEY `idx_protein_id` (`protein_id`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMILL_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMILL_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMILL_TPM` (
  `Gene` text,
  `SRR1929581_branch_adult` text,
  `SRR1929582_branch_adult` text,
  `SRR1929583_branch_adult` text,
  `SRR1929584_branch_adult` text,
  `SRR1929585_branch_adult` text,
  `SRR1929586_branch_adult` text,
  `SRR1929587_branch_adult` text,
  `SRR1929588_branch_adult` text,
  `SRR1929589_branch_adult` text,
  `SRR1929590_branch_adult` text,
  `SRR1929591_branch_adult` text,
  `SRR1929592_branch_adult` text,
  `SRR1929593_branch_adult` text,
  `SRR1929594_branch_adult` text,
  `SRR1929595_branch_adult` text,
  `SRR1929596_branch_adult` text,
  `SRR1929597_branch_adult` text,
  `SRR1929598_branch_adult` text,
  `SRR1929599_branch_adult` text,
  `SRR1929600_branch_adult` text,
  `SRR1929601_branch_adult` text,
  `SRR1929602_branch_adult` text,
  `SRR1929603_branch_adult` text,
  `SRR1929604_branch_adult` text,
  `SRR1929605_whole_larvae_adult` text,
  `SRR1929606_whole_larvae_adult` text,
  `SRR1929607_whole_larvae_adult` text,
  `SRR1929608_whole_larvae_adult` text,
  `SRR1929609_whole_larvae_adult` text,
  `SRR1929610_whole_larvae_adult` text,
  `SRR1929611_whole_larvae_adult` text,
  `SRR1929612_whole_larvae_adult` text,
  `SRR1929613_whole_larvae_adult` text,
  `SRR1929614_whole_larvae_adult` text,
  `SRR1929615_whole_larvae_adult` text,
  `SRR1929616_whole_larvae_adult` text,
  `SRR1929617_whole_larvae_adult` text,
  `SRR1929618_whole_larvae_adult` text,
  `SRR1929619_whole_larvae_adult` text,
  `SRR1929620_whole_larvae_adult` text,
  `SRR1929621_whole_larvae_adult` text,
  `SRR1929622_whole_larvae_adult` text,
  `SRR1929623_whole_larvae_adult` text,
  `SRR1929624_whole_larvae_adult` text,
  `SRR1929625_whole_larvae_adult` text,
  `SRR1929626_whole_larvae_adult` text,
  `SRR1929627_whole_larvae_adult` text,
  `SRR1929628_whole_larvae_adult` text,
  `SRR1929629_whole_larvae_adult` text,
  `SRR1929630_whole_larvae_adult` text,
  `SRR1929631_whole_larvae_adult` text,
  `SRR1929632_whole_larvae_adult` text,
  `SRR1929633_whole_larvae_adult` text,
  `SRR1929634_whole_larvae_adult` text,
  `avg` text,
  `std` text,
  KEY `ix_gene` (`Gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMILL_cellmarker` (
  `tissue_dev` text,
  `pct1` text,
  `pct2` text,
  `log2FC` text,
  `FDR` text,
  `celltype` text,
  `gene` text,
  `symbol` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMILL_coexpress_negative` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMILL_coexpress_positive` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMILL_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMILL_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMILL_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMILL_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMILL_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMILL_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMILL_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMILL_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMURI_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMURI_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMURI_TPM` (
  `Gene` text,
  `SRR12710849_Polyps_OA_2_day3` text,
  `SRR12710850_Polyps_OA_2_day3` text,
  `SRR12710851_Polyps_OA_2_day3` text,
  `SRR12710859_Polyps_OA_2_day9` text,
  `SRR12710860_Polyps_OA_2_day9` text,
  `SRR12710861_Polyps_OA_2_day9` text,
  `SRR12786899_Polyps_OA_2_day0` text,
  `SRR12786900_Polyps_OA_2_day0` text,
  `SRR12786901_Polyps_OA_2_day0` text,
  `SRR12807382_Polyps_OA_2_day0` text,
  `SRR12904784_Polyps` text,
  `SRR12904785_Polyps` text,
  `SRR12904786_Polyps` text,
  `SRR12927881_Polyps_E2_day0` text,
  `SRR12959191_polyps_E2_day21` text,
  `SRR12959192_Polyps_E2_day21` text,
  `SRR12959193_Polyps_E2_day21` text,
  `SRR12959195_Polyps_E2_day0` text,
  `SRR12959204_Polyps_E2_day15` text,
  `SRR12959205_Polyps_E2_day15` text,
  `SRR12959206_Polyps_E2_day0` text,
  `SRR12959207_Polyps_E2_day15` text,
  `SRR12959217_Polyps_E2_day0` text,
  `SRR12959218_Polyps_E2_day9` text,
  `SRR12959219_Polyps_E2_day9` text,
  `SRR12959220_Polyps_E2_day9` text,
  `SRR12959231_Polyps_E2_day3` text,
  `SRR12959232_Polyps_E2_day3` text,
  `SRR12959233_Polyps_E2_day3` text,
  `SRR12995717_Severed_branch_regeneration_day0` text,
  `SRR12996627_Severed_branch_regeneration_day0` text,
  `SRR12996628_Severed_branch_regeneration_High_gene_expression` text,
  `SRR27868158_Polyps_regeneration_day6` text,
  `SRR27868159_Polyps_regeneration_day6` text,
  `SRR27868160_Polyps_regeneration_day6` text,
  `SRR27868165_Polyps_regeneration_day3` text,
  `SRR27868174_Polyps_regeneration_day39` text,
  `SRR27868175_Polyps_regeneration_day39` text,
  `SRR27868176_Polyps_regeneration_day3` text,
  `SRR27868177_Polyps_regeneration_day39` text,
  `SRR27868178_Polyps_regeneration_day36` text,
  `SRR27868179_Polyps_regeneration_day36` text,
  `SRR27868180_Polyps_regeneration_day36` text,
  `SRR27868181_Polyps_regeneration_day33` text,
  `SRR27868182_Polyps_regeneration_day33` text,
  `SRR27868183_Polyps_regeneration_day33` text,
  `SRR27868184_Polyps_regeneration_day30` text,
  `SRR27868185_Polyps_regeneration_day30` text,
  `SRR27868186_Polyps_regeneration_day30` text,
  `SRR27868187_Polyps_regeneration_day3` text,
  `SRR27868188_Polyps_regeneration_day27` text,
  `SRR27868189_Polyps_regeneration_day27` text,
  `SRR27868190_Polyps_regeneration_day27` text,
  `SRR27868191_Polyps_regeneration_day24` text,
  `SRR27868192_Polyps_regeneration_day24` text,
  `SRR27868193_Polyps_regeneration_day24` text,
  `SRR27868194_Polyps_regeneration_day21` text,
  `SRR27868195_Polyps_regeneration_day21` text,
  `SRR27868196_Polyps_regeneration_day21` text,
  `SRR27868197_Polyps_regeneration_day18` text,
  `SRR27868198_Polyps_regeneration_day0` text,
  `SRR27868199_Polyps_regeneration_day18` text,
  `SRR27868200_Polyps_regeneration_day18` text,
  `SRR27868201_Polyps_regeneration_day15` text,
  `SRR27868202_Polyps_regeneration_day15` text,
  `SRR27868203_Polyps_regeneration_day15` text,
  `SRR27868204_Polyps_regeneration_day12` text,
  `SRR27868205_Polyps_regeneration_day12` text,
  `SRR27868206_Polyps_regeneration_day12` text,
  `SRR27868207_Polyps_regeneration_day9` text,
  `SRR27868208_Polyps_regeneration_day9` text,
  `SRR27868209_Polyps_regeneration_day0` text,
  `SRR27868210_Polyps_regeneration_day0` text,
  `SRR27940222_Polyps` text,
  `SRR27940224_Polyps` text,
  `SRR27940225_Polyps` text,
  `SRR27940226_Polyps` text,
  `SRR27940227_Polyps` text,
  `SRR27940228_Polyps` text,
  `SRR27940229_Polyps` text,
  `SRR9613488_Polyps` text,
  `SRR9613516_Polyps` text,
  `avg` text,
  `std` text,
  KEY `ix_gene` (`Gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMURI_cellmarker` (
  `tissue_dev` text,
  `pct1` text,
  `pct2` text,
  `log2FC` text,
  `FDR` text,
  `celltype` text,
  `gene` text,
  `symbol` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMURI_coexpress_negative` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMURI_coexpress_positive` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMURI_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMURI_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMURI_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMURI_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMURI_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMURI_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMURI_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMURI_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMYRI_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMYRI_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMYRI_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMYRI_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMYRI_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMYRI_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMYRI_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMYRI_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMYRI_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AMYRI_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ANASU_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ANASU_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ANASU_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ANASU_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ANASU_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ANASU_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ANASU_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ANASU_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ANASU_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ANASU_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `APALM_BS_Polyp_Underside_Control_2` (
  `Type` text,
  `seqnames` text,
  `start` text,
  `end` text,
  `meth_level` text,
  `annotation` text,
  `chr` text,
  `geneStart` text,
  `geneEnd` text,
  `geneLength` text,
  `distanceToTSS` text,
  `protein_id` text,
  `product` text,
  KEY `idx_protein_id` (`protein_id`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `APALM_BS_Polyp_Underside_Control_3` (
  `Type` text,
  `seqnames` text,
  `start` text,
  `end` text,
  `meth_level` text,
  `annotation` text,
  `chr` text,
  `geneStart` text,
  `geneEnd` text,
  `geneLength` text,
  `distanceToTSS` text,
  `protein_id` text,
  `product` text,
  KEY `idx_protein_id` (`protein_id`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `APALM_BS_Polyp_Underside_Treatment_1` (
  `Type` text,
  `seqnames` text,
  `start` text,
  `end` text,
  `meth_level` text,
  `annotation` text,
  `chr` text,
  `geneStart` text,
  `geneEnd` text,
  `geneLength` text,
  `distanceToTSS` text,
  `protein_id` text,
  `product` text,
  KEY `idx_protein_id` (`protein_id`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `APALM_BS_Polyp_Upperside_Control_1` (
  `Type` text,
  `seqnames` text,
  `start` text,
  `end` text,
  `meth_level` text,
  `annotation` text,
  `chr` text,
  `geneStart` text,
  `geneEnd` text,
  `geneLength` text,
  `distanceToTSS` text,
  `protein_id` text,
  `product` text,
  KEY `idx_protein_id` (`protein_id`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `APALM_BS_Polyp_Upperside_Treatment_3` (
  `Type` text,
  `seqnames` text,
  `start` text,
  `end` text,
  `meth_level` text,
  `annotation` text,
  `chr` text,
  `geneStart` text,
  `geneEnd` text,
  `geneLength` text,
  `distanceToTSS` text,
  `protein_id` text,
  `product` text,
  KEY `idx_protein_id` (`protein_id`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `APALM_BS_Polyp_Upperside_Treatment_4` (
  `Type` text,
  `seqnames` text,
  `start` text,
  `end` text,
  `meth_level` text,
  `annotation` text,
  `chr` text,
  `geneStart` text,
  `geneEnd` text,
  `geneLength` text,
  `distanceToTSS` text,
  `protein_id` text,
  `product` text,
  KEY `idx_protein_id` (`protein_id`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `APALM_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `APALM_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `APALM_TPM` (
  `Gene` text,
  `SRR8800026_all_coral_tissue_exposed` text,
  `SRR8800027_all_coral_tissue_exposed` text,
  `SRR8800028_all_coral_tissue_exposed` text,
  `SRR8800029_all_coral_tissue_exposed` text,
  `SRR8800030_all_coral_tissue_baseline` text,
  `SRR8800031_all_coral_tissue_baseline` text,
  `SRR8800032_all_coral_tissue_baseline` text,
  `SRR8800033_all_coral_tissue_exposed` text,
  `SRR8800034_all_coral_tissue_exposed` text,
  `SRR8800035_all_coral_tissue_baseline` text,
  `SRR8800036_all_coral_tissue_exposed` text,
  `SRR8800037_all_coral_tissue_baseline` text,
  `SRR8800038_all_coral_tissue_exposed` text,
  `SRR8800039_all_coral_tissue_exposed` text,
  `SRR8800040_all_coral_tissue_exposed` text,
  `SRR8800041_all_coral_tissue_baseline` text,
  `SRR8800042_all_coral_tissue_exposed` text,
  `SRR8800043_all_coral_tissue_baseline` text,
  `SRR8800044_all_coral_tissue_exposed` text,
  `SRR8800045_all_coral_tissue_exposed` text,
  `SRR8800046_all_coral_tissue_baseline` text,
  `SRR8800047_all_coral_tissue_exposed` text,
  `SRR8800048_all_coral_tissue_baseline` text,
  `SRR8800049_all_coral_tissue_baseline` text,
  `SRR8800050_all_coral_tissue_baseline` text,
  `SRR8800051_all_coral_tissue_exposed` text,
  `SRR8800052_all_coral_tissue_baseline` text,
  `SRR8800053_all_coral_tissue_exposed` text,
  `SRR8800054_all_coral_tissue_baseline` text,
  `SRR8800055_all_coral_tissue_baseline` text,
  `SRR8800056_all_coral_tissue_exposed` text,
  `SRR8800057_all_coral_tissue_baseline` text,
  `SRR8800058_all_coral_tissue_exposed` text,
  `SRR8800059_all_coral_tissue_baseline` text,
  `SRR8800060_all_coral_tissue_exposed` text,
  `SRR8800061_all_coral_tissue_exposed` text,
  `SRR8800062_all_coral_tissue_exposed` text,
  `SRR8800063_all_coral_tissue_exposed` text,
  `SRR8800064_all_coral_tissue_baseline` text,
  `SRR8800065_all_coral_tissue_exposed` text,
  `SRR8800066_all_coral_tissue_exposed` text,
  `SRR8800067_all_coral_tissue_baseline` text,
  `SRR8800068_all_coral_tissue_exposed` text,
  `SRR8800069_all_coral_tissue_baseline` text,
  `SRR8800070_all_coral_tissue_baseline` text,
  `SRR8800071_all_coral_tissue_exposed` text,
  `SRR8800072_all_coral_tissue_baseline` text,
  `SRR8800073_all_coral_tissue_exposed` text,
  `SRR8800074_all_coral_tissue_baseline` text,
  `SRR8800075_all_coral_tissue_exposed` text,
  `SRR8800076_all_coral_tissue_baseline` text,
  `SRR8800077_all_coral_tissue_exposed` text,
  `SRR8800078_all_coral_tissue_baseline` text,
  `SRR8800079_all_coral_tissue_exposed` text,
  `SRR8800080_all_coral_tissue_exposed` text,
  `SRR8800081_all_coral_tissue_baseline` text,
  `SRR8800082_all_coral_tissue_baseline` text,
  `SRR8800083_all_coral_tissue_exposed` text,
  `SRR8800084_all_coral_tissue_baseline` text,
  `SRR8800085_all_coral_tissue_baseline` text,
  `SRR8800086_all_coral_tissue_exposed` text,
  `SRR8800087_all_coral_tissue_exposed` text,
  `SRR8800088_all_coral_tissue_exposed` text,
  `SRR8800089_all_coral_tissue_exposed` text,
  `SRR8800090_all_coral_tissue_baseline` text,
  `SRR8800091_all_coral_tissue_exposed` text,
  `SRR8800092_all_coral_tissue_exposed` text,
  `SRR8800093_all_coral_tissue_exposed` text,
  `SRR8800094_all_coral_tissue_exposed` text,
  `SRR8800095_all_coral_tissue_exposed` text,
  `SRR8800096_all_coral_tissue_baseline` text,
  `SRR8800097_all_coral_tissue_exposed` text,
  `SRR8800098_all_coral_tissue_baseline` text,
  `SRR8800099_all_coral_tissue_exposed` text,
  `SRR8800100_all_coral_tissue_exposed` text,
  `SRR8800101_all_coral_tissue_baseline` text,
  `SRR8800102_all_coral_tissue_baseline` text,
  `SRR8800103_all_coral_tissue_baseline` text,
  `SRR8800104_all_coral_tissue_baseline` text,
  `SRR8800105_all_coral_tissue_exposed` text,
  `SRR8800106_all_coral_tissue_baseline` text,
  `SRR8800107_all_coral_tissue_exposed` text,
  `SRR8800108_all_coral_tissue_baseline` text,
  `SRR8800109_all_coral_tissue_baseline` text,
  `avg` text,
  `std` text,
  KEY `ix_gene` (`Gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `APALM_coexpress_negative` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `APALM_coexpress_positive` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `APALM_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `APALM_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `APALM_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `APALM_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `APALM_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `APALM_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `APALM_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `APALM_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `APOCU_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `APOCU_TPM` (
  `Gene` text,
  `SRR10674706_whole_organism_heat_control` text,
  `SRR10674707_whole_organism_heat_control` text,
  `SRR10674708_whole_organism_heat_control` text,
  `SRR10674709_whole_organism_heat_challenge` text,
  `SRR10674710_whole_organism_heat_challenge` text,
  `SRR10674711_whole_organism_cold_challenge` text,
  `SRR10674712_whole_organism_cold_challenge` text,
  `SRR10674713_whole_organism_cold_challenge` text,
  `SRR10674714_whole_organism_cold_control` text,
  `SRR10674715_whole_organism_cold_control` text,
  `SRR10674716_whole_organism_heat_challenge` text,
  `SRR10674717_whole_organism_heat_control` text,
  `SRR10674718_whole_organism_heat_control` text,
  `SRR10674719_whole_organism_heat_challenge` text,
  `SRR10674720_whole_organism_heat_control` text,
  `SRR10674721_whole_organism_heat_control` text,
  `SRR10674722_whole_organism_cold_challenge` text,
  `SRR10674723_whole_organism_cold_challenge` text,
  `SRR10674724_whole_organism_cold_control` text,
  `SRR10674725_whole_organism_heat_control` text,
  `SRR10674726_whole_organism_heat_challenge` text,
  `SRR10674727_whole_organism_heat_challenge` text,
  `SRR10674728_whole_organism_heat_challenge` text,
  `SRR10674729_whole_organism_heat_challenge` text,
  `SRR10674730_whole_organism_cold_challenge` text,
  `SRR10674731_whole_organism_heat_control` text,
  `SRR10674732_whole_organism_cold_challenge` text,
  `SRR10674733_whole_organism_cold_control` text,
  `SRR10674734_whole_organism_cold_control` text,
  `SRR10674735_whole_organism_cold_control` text,
  `SRR10674736_whole_organism_cold_control` text,
  `SRR10674737_whole_organism_cold_control` text,
  `SRR10674738_whole_organism_cold_challenge` text,
  `SRR10674739_whole_organism_cold_control` text,
  `SRR10674740_whole_organism_cold_challenge` text,
  `SRR10674741_whole_organism_heat_control` text,
  `SRR10674742_whole_organism_heat_challenge` text,
  `SRR10674743_whole_organism_cold_control` text,
  `SRR10674744_whole_organism_cold_challenge` text,
  `SRR10674745_whole_organism_cold_challenge` text,
  `SRR10674746_whole_organism_cold_control` text,
  `SRR10674747_whole_organism_cold_control` text,
  `SRR10674748_whole_organism_cold_control` text,
  `SRR10674749_whole_organism_cold_challenge` text,
  `SRR10674750_whole_organism_cold_control` text,
  `SRR10674751_whole_organism_heat_challenge` text,
  `SRR10674752_whole_organism_heat_control` text,
  `SRR10674753_whole_organism_heat_control` text,
  `SRR10674754_whole_organism_heat_challenge` text,
  `avg` text,
  `std` text,
  KEY `ix_gene` (`Gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `APOCU_coexpress_negative` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `APOCU_coexpress_positive` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `APOCU_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `APOCU_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `APOCU_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `APOCU_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `APOCU_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `APOCU_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `APOCU_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `APOCU_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `APULC_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `APULC_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `APULC_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `APULC_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `APULC_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `APULC_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `APULC_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `APULC_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `APULC_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `APULC_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASELA_BS_Coral_Fragment_Parent` (
  `Type` text,
  `seqnames` text,
  `start` text,
  `end` text,
  `meth_level` text,
  `annotation` text,
  `chr` text,
  `geneStart` text,
  `geneEnd` text,
  `geneLength` text,
  `distanceToTSS` text,
  `protein_id` text,
  `product` text,
  KEY `idx_protein_id` (`protein_id`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASELA_BS_Larval_Pool_Offspring` (
  `Type` text,
  `seqnames` text,
  `start` text,
  `end` text,
  `meth_level` text,
  `annotation` text,
  `chr` text,
  `geneStart` text,
  `geneEnd` text,
  `geneLength` text,
  `distanceToTSS` text,
  `protein_id` text,
  `product` text,
  KEY `idx_protein_id` (`protein_id`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASELA_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASELA_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASELA_TPM` (
  `Gene` text,
  `SRR14308004_coral_larvae` text,
  `SRR14308005_coral_larvae` text,
  `SRR14308006_coral_larvae` text,
  `SRR14308007_coral_larvae` text,
  `SRR14308008_coral_larvae` text,
  `SRR14308009_coral_larvae` text,
  `SRR14308010_coral_larvae` text,
  `SRR14308011_coral_larvae` text,
  `SRR14308012_coral_larvae` text,
  `SRR14308013_coral_larvae` text,
  `SRR14308014_coral_larvae` text,
  `SRR14308015_coral_larvae` text,
  `SRR14308016_coral_larvae` text,
  `SRR14308017_coral_larvae` text,
  `SRR14308018_coral_larvae` text,
  `SRR14308019_coral_larvae` text,
  `SRR14308020_coral_larvae` text,
  `SRR14308021_coral_larvae` text,
  `SRR14308022_coral_larvae` text,
  `SRR14308023_coral_larvae` text,
  `SRR14308024_coral_larvae` text,
  `SRR14308025_coral_larvae` text,
  `SRR14308026_coral_larvae` text,
  `SRR14308027_coral_larvae` text,
  `avg` text,
  `std` text,
  KEY `ix_gene` (`Gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASELA_coexpress_negative` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASELA_coexpress_positive` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASELA_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASELA_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASELA_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASELA_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASELA_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASELA_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASELA_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASELA_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASP1_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASP1_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASP1_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASP1_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASP1_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASP1_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASP1_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASP1_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASP2_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASP2_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASP2_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`GO_term`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASP2_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`InterPro_term`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASP2_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASP2_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASP2_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASP2_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASP3_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASP3_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASP3_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASP3_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASP3_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASP3_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASP3_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASPAT_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASPAT_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASPAT_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASPAT_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASPAT_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASPAT_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASPAT_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASPAT_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASPAT_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASPAT_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASP_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASP_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ATENE_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ATENE_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ATENE_TPM` (
  `Gene` text,
  `SRR2437124_whole_organism` text,
  `SRR3193284_whole` text,
  `SRR3193648_whole_organism` text,
  `SRR3206038_whole_organism` text,
  `SRR3207346_whole_organisim` text,
  `SRR3210696_whole_organism` text,
  `SRR3216075_whole` text,
  `SRR4677488_Mesentery` text,
  `SRR4677492_Mesentery` text,
  `SRR4677495_Tentacle` text,
  `SRR4677502_Tentacle` text,
  `SRR4677507_Acrorhagi` text,
  `SRR4677512_Acrorhagi` text,
  `SRR4677515_Acrorhagi` text,
  `SRR4677518_Mesentery` text,
  `SRR4677522_Tentacle` text,
  `SRR4696535_Whole_Organism` text,
  `SRR6282389_Tentacles` text,
  `avg` text,
  `std` text,
  KEY `ix_gene` (`Gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ATENE_coexpress_negative` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ATENE_coexpress_positive` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ATENE_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ATENE_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ATENE_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ATENE_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ATENE_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ATENE_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ATENE_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ATENE_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ATENU_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ATENU_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ATENU_TPM` (
  `Gene` text,
  `DRR550233_whole_tissue_of_branch_fragment_adult_BC_2` text,
  `DRR550234_whole_tissue_of_branch_fragment_adult_BC_3` text,
  `DRR550235_whole_tissue_of_branch_fragment_adult_BC_4` text,
  `DRR550236_whole_tissue_of_branch_fragment_adult_BC_5` text,
  `DRR550237_whole_tissue_of_branch_fragment_adult_BC_6` text,
  `DRR550238_whole_tissue_of_branch_fragment_adult_SC_1` text,
  `DRR550239_whole_tissue_of_branch_fragment_adult_SC_2` text,
  `DRR550241_whole_tissue_of_branch_fragment_adult_SC_4` text,
  `DRR550242_whole_tissue_of_branch_fragment_adult_SC_5` text,
  `DRR550243_whole_tissue_of_branch_fragment_adult_SC_6` text,
  `DRR550244_whole_tissue_of_branch_fragment_adult_BP_3_0_38_1` text,
  `DRR550245_whole_tissue_of_branch_fragment_adult_BP_3_0_38_2` text,
  `DRR550246_whole_tissue_of_branch_fragment_adult_BP_3_0_38_3` text,
  `DRR550247_whole_tissue_of_branch_fragment_adult_BP_3_0_38_4` text,
  `DRR550248_whole_tissue_of_branch_fragment_adult_BP_3_0_38_5` text,
  `DRR550249_whole_tissue_of_branch_fragment_adult_BP_3_0_38_6` text,
  `DRR550250_whole_tissue_of_branch_fragment_adult_BP_3_0_77_1` text,
  `DRR550251_whole_tissue_of_branch_fragment_adult_BP_3_0_77_2` text,
  `DRR550252_whole_tissue_of_branch_fragment_adult_BP_3_0_77_3` text,
  `DRR550253_whole_tissue_of_branch_fragment_adult_BP_3_0_77_4` text,
  `DRR550254_whole_tissue_of_branch_fragment_adult_BP_3_0_77_5` text,
  `DRR550255_whole_tissue_of_branch_fragment_adult_BP_3_0_77_6` text,
  `DRR550256_whole_tissue_of_branch_fragment_adult_BP_3_1_5_1` text,
  `DRR550257_whole_tissue_of_branch_fragment_adult_BP_3_1_5_2` text,
  `DRR550258_whole_tissue_of_branch_fragment_adult_BP_3_1_5_3` text,
  `DRR550259_whole_tissue_of_branch_fragment_adult_BP_3_1_5_4` text,
  `DRR550260_whole_tissue_of_branch_fragment_adult_BP_3_1_5_5` text,
  `DRR550261_whole_tissue_of_branch_fragment_adult_BP_3_1_5_6` text,
  `DRR550262_whole_tissue_of_branch_fragment_adult_BP_3_2_7_1` text,
  `DRR550263_whole_tissue_of_branch_fragment_adult_BP_3_2_7_2` text,
  `DRR550264_whole_tissue_of_branch_fragment_adult_BP_3_2_7_3` text,
  `DRR550265_whole_tissue_of_branch_fragment_adult_BP_3_2_7_4` text,
  `DRR550266_whole_tissue_of_branch_fragment_adult_BP_3_2_7_5` text,
  `DRR550267_whole_tissue_of_branch_fragment_adult_BP_3_2_7_6` text,
  `DRR550268_whole_tissue_of_branch_fragment_adult_Heat_1` text,
  `DRR550269_whole_tissue_of_branch_fragment_adult_Heat_2` text,
  `DRR550270_whole_tissue_of_branch_fragment_adult_Heat_3` text,
  `DRR550271_whole_tissue_of_branch_fragment_adult_Heat_4` text,
  `DRR550272_whole_tissue_of_branch_fragment_adult_Heat_5` text,
  `DRR550273_whole_tissue_of_branch_fragment_adult_Heat_6` text,
  `avg` text,
  `std` text,
  KEY `ix_gene` (`Gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ATENU_coexpress_negative` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ATENU_coexpress_positive` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ATENU_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ATENU_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ATENU_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ATENU_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ATENU_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ATENU_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ATENU_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ATENU_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AXANT_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AXANT_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AXANT_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AXANT_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AXANT_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AXANT_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AXANT_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AXANT_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AXANT_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AXANT_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AYONG_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AYONG_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AYONG_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AYONG_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AYONG_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AYONG_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AYONG_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AYONG_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AYONG_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AYONG_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Aiptasia_sp_anemones_proteomics` (
  `Protein` text,
  `group1` text,
  `Protein_identification_probability` text,
  `unique_peptides` text,
  `unique_spectra` text,
  `total_spectra` text,
  `total_spectra_percent` text,
  `coverage` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Antipathes_griggi_Coral_skeleton_proteomics` (
  `Description` text,
  `Log_Prob` text,
  `Best_Log_Prob` text,
  `Best_score` text,
  `Total_Intensity` text,
  `spectra` text,
  `unique_peptides` text,
  `mod_peptides` text,
  `Coverage` text,
  `AA_in_protein` text,
  `Protein_DB_number` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `BCFM_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `BCFM_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `BCFM_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `BCFM_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `BCFM_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `BCFM_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `BCFM_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `BCF_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `BCF_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `BWELL_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `BWELL_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `BWELL_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `BWELL_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `BWELL_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `BWELL_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `BWELL_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `BWELL_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `BWELL_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `BWELL_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Buddenbrockia_plumatellae_myxoworms_proteomics` (
  `Protein` text,
  `lgP` text,
  `Coverage` text,
  `Coverage_Sample1` text,
  `Area_Sample1` text,
  `Peptides` text,
  `Unique1` text,
  `Spec_Sample11` text,
  `PTM` text,
  `Avg_Mass` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CCOCK_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CCOCK_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CCOCK_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CCOCK_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CCOCK_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CCOCK_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CCOCK_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CCOCK_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CCOCK_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CCOCK_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CCRUX_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CCRUX_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`GO_term`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CCRUX_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`InterPro_term`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CCRUX_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CCRUX_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CCRUX_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CCRUX_tentacle_proteomics` (
  `Protein` text,
  `lgP` text,
  `Coverage` text,
  `Coverage_Sample1` text,
  `Area_Sample1` text,
  `Peptides` text,
  `Unique1` text,
  `Spec_Sample11` text,
  `PTM` text,
  `Avg_Mass` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CCRUX_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CGIGA_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CGIGA_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CGIGA_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CGIGA_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CGIGA_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CGIGA_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CGIGA_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CGIGA_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CGIGA_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CGIGA_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CGRAC1_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CGRAC1_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CGRAC1_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CGRAC1_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CGRAC1_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CGRAC1_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CGRAC1_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CGRAC1_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CGRAC2_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CGRAC2_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CGRAC2_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CGRAC2_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CGRAC2_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CGRAC2_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CGRAC2_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CGRAC_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CGRAC_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CHEMI_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CHEMI_TPM` (
  `Gene` text,
  `ERR2816230_Early_gastrula` text,
  `ERR2816231_Early_gastrula` text,
  `ERR2816232_Planula_24hpf` text,
  `ERR2816233_Planula_24hpf` text,
  `ERR2816234_Planula_48hpf` text,
  `ERR2816235_Planula_48hpf` text,
  `ERR2816236_Planula_72hpf` text,
  `ERR2816237_Planula_72hpf` text,
  `ERR2816238_Primary_polyp` text,
  `ERR2816239_Primary_polyp` text,
  `ERR2816240_Gastrozooid_female` text,
  `ERR2816241_Gastrozooid_female` text,
  `ERR2816242_Gonozooid_female` text,
  `ERR2816243_Gonozooid_female` text,
  `ERR2816244_Stolon_female` text,
  `ERR2816245_Stolon_female` text,
  `ERR2816246_Baby_medusa_female` text,
  `ERR2816247_Baby_medusa_female` text,
  `ERR2816248_Mature_medusa_female` text,
  `ERR2816249_Mature_medusa_female` text,
  `ERR2816250_Mature_medusa_male` text,
  `ERR2816251_Mature_medusa_male` text,
  `ERR2862244_Mixed_MF` text,
  `ERR2862245_Mature_medusa_MF` text,
  `ERR3299471_medusa_Experiment_Condition_B1_female_strain_Z4B` text,
  `ERR3299472_medusa_Experiment_Condition_B1_female_strain_Z4B` text,
  `ERR3299473_medusa_Experiment_Condition_B2_female_strain_Z4B` text,
  `ERR3299474_medusa_Experiment_Condition_B2_female_strain_Z4B` text,
  `ERR3299475_medusa_Experiment_Condition_A1_female_strain_Z4B` text,
  `ERR3299476_medusa_Experiment_Condition_A1_female_strain_Z4B` text,
  `ERR3299477_medusa_Experiment_Condition_A1_female_strain_Z4B` text,
  `ERR3299478_medusa_Experiment_Condition_A1_female_strain_Z4B` text,
  `ERR3299479_medusa_Experiment_Condition_A2_female_strain_Z4B` text,
  `ERR3299480_medusa_Experiment_Condition_A2_female_strain_Z4B` text,
  `ERR3299481_medusa_Experiment_Condition_A2_female_strain_Z4B` text,
  `ERR3299482_medusa_Experiment_Condition_A2_female_strain_Z4B` text,
  `ERR3299483_medusa_Experiment_Condition_A3_female_strain_Z4B` text,
  `ERR3299484_medusa_Experiment_Condition_A3_female_strain_Z4B` text,
  `ERR3299485_medusa_Experiment_Condition_A3_female_strain_Z4B` text,
  `ERR3299486_medusa_Experiment_Condition_A3_female_strain_Z4B` text,
  `avg` text,
  `std` text,
  KEY `ix_gene` (`Gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CHEMI_coexpress_negative` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CHEMI_coexpress_positive` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CHEMI_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CHEMI_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CHEMI_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CHEMI_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CHEMI_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CHEMI_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CHEMI_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CHEMI_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CJARD_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CJARD_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CJARD_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CJARD_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CJARD_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CJARD_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CJARD_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CJARD_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CJARD_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CMOSA_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CMOSA_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CMOSA_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CMOSA_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CMOSA_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CMOSA_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CMOSA_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CMOSA_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CMOSA_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CMOSA_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CNATA_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CNATA_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CNATA_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CNATA_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CNATA_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CNATA_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CNATA_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CNATA_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CNATA_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CQUIN_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CQUIN_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CQUIN_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`GO_term`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CQUIN_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`InterPro_term`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CQUIN_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CQUIN_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CQUIN_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CQUIN_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CQUIN_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CQUIN_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CSALA_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CSALA_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CSALA_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CSALA_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CSALA_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CSALA_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CSALA_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CSALA_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CSALA_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CSALA_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CSP1_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CSP1_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CSP1_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CSP1_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CSP1_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CSP1_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CSP1_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CSP2_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CSP2_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CSP2_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CSP2_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CSP2_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CSP2_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CSP2_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CSP2_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CSP_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CSP_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CXAMA_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CXAMA_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CXAMA_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CXAMA_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CXAMA_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CXAMA_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CXAMA_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DAXIF_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DAXIF_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DAXIF_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DAXIF_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DAXIF_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DAXIF_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DAXIF_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DAXIF_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DAXIF_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DCRIB_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DCRIB_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DCRIB_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DCRIB_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DCRIB_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DCRIB_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DCRIB_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DCRIB_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DCRIB_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DCYLI_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DCYLI_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DCYLI_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DCYLI_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DCYLI_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DCYLI_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DCYLI_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DCYLI_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DCYLI_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DCYLI_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DGIGA_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DGIGA_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DGIGA_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DGIGA_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DGIGA_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DGIGA_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DGIGA_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DGIGA_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DGIGA_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DGIGA_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DLINE_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DLINE_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DLINE_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DLINE_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DLINE_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DLINE_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DLINE_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DLINE_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DLINE_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DLINE_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DPERT_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DPERT_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DPERT_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DPERT_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DPERT_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DPERT_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DPERT_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DPERT_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DPERT_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ECAVO_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ECAVO_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ECAVO_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ECAVO_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ECAVO_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ECAVO_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ECAVO_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ECAVO_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ECAVO_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EDIAP_ATAC` (
  `Sample` text,
  `Chr` text,
  `start` text,
  `end` text,
  `peak_name` text,
  `Summit` text,
  `Pileup` text,
  `peak_location` text,
  `geneChr` text,
  `geneStart` text,
  `geneEnd` text,
  `geneLength` text,
  `distanceToTSS` text,
  `protein_id` text,
  `description` text,
  KEY `idx_protein_id` (`protein_id`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EDIAP_BS_whole_animal_aposymbiotic_1` (
  `Type` text,
  `seqnames` text,
  `start` text,
  `end` text,
  `meth_level` text,
  `annotation` text,
  `chr` text,
  `geneStart` text,
  `geneEnd` text,
  `geneLength` text,
  `distanceToTSS` text,
  `protein_id` text,
  `product` text,
  KEY `idx_protein_id` (`protein_id`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EDIAP_BS_whole_animal_aposymbiotic_2` (
  `Type` text,
  `seqnames` text,
  `start` text,
  `end` text,
  `meth_level` text,
  `annotation` text,
  `chr` text,
  `geneStart` text,
  `geneEnd` text,
  `geneLength` text,
  `distanceToTSS` text,
  `protein_id` text,
  `product` text,
  KEY `idx_protein_id` (`protein_id`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EDIAP_BS_whole_animal_aposymbiotic_3` (
  `Type` text,
  `seqnames` text,
  `start` text,
  `end` text,
  `meth_level` text,
  `annotation` text,
  `chr` text,
  `geneStart` text,
  `geneEnd` text,
  `geneLength` text,
  `distanceToTSS` text,
  `protein_id` text,
  `product` text,
  KEY `idx_protein_id` (`protein_id`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EDIAP_BS_whole_animal_aposymbiotic_4` (
  `Type` text,
  `seqnames` text,
  `start` text,
  `end` text,
  `meth_level` text,
  `annotation` text,
  `chr` text,
  `geneStart` text,
  `geneEnd` text,
  `geneLength` text,
  `distanceToTSS` text,
  `protein_id` text,
  `product` text,
  KEY `idx_protein_id` (`protein_id`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EDIAP_BS_whole_animal_aposymbiotic_5` (
  `Type` text,
  `seqnames` text,
  `start` text,
  `end` text,
  `meth_level` text,
  `annotation` text,
  `chr` text,
  `geneStart` text,
  `geneEnd` text,
  `geneLength` text,
  `distanceToTSS` text,
  `protein_id` text,
  `product` text,
  KEY `idx_protein_id` (`protein_id`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EDIAP_BS_whole_animal_aposymbiotic_6` (
  `Type` text,
  `seqnames` text,
  `start` text,
  `end` text,
  `meth_level` text,
  `annotation` text,
  `chr` text,
  `geneStart` text,
  `geneEnd` text,
  `geneLength` text,
  `distanceToTSS` text,
  `protein_id` text,
  `product` text,
  KEY `idx_protein_id` (`protein_id`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EDIAP_BS_whole_animal_aposymbiotic_7` (
  `Type` text,
  `seqnames` text,
  `start` text,
  `end` text,
  `meth_level` text,
  `annotation` text,
  `chr` text,
  `geneStart` text,
  `geneEnd` text,
  `geneLength` text,
  `distanceToTSS` text,
  `protein_id` text,
  `product` text,
  KEY `idx_protein_id` (`protein_id`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EDIAP_BS_whole_animal_aposymbiotic_8` (
  `Type` text,
  `seqnames` text,
  `start` text,
  `end` text,
  `meth_level` text,
  `annotation` text,
  `chr` text,
  `geneStart` text,
  `geneEnd` text,
  `geneLength` text,
  `distanceToTSS` text,
  `protein_id` text,
  `product` text,
  KEY `idx_protein_id` (`protein_id`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EDIAP_BS_whole_animal_aposymbiotic_9` (
  `Type` text,
  `seqnames` text,
  `start` text,
  `end` text,
  `meth_level` text,
  `annotation` text,
  `chr` text,
  `geneStart` text,
  `geneEnd` text,
  `geneLength` text,
  `distanceToTSS` text,
  `protein_id` text,
  `product` text,
  KEY `idx_protein_id` (`protein_id`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EDIAP_BS_whole_animal_symbiotic_1` (
  `Type` text,
  `seqnames` text,
  `start` text,
  `end` text,
  `meth_level` text,
  `annotation` text,
  `chr` text,
  `geneStart` text,
  `geneEnd` text,
  `geneLength` text,
  `distanceToTSS` text,
  `protein_id` text,
  `product` text,
  KEY `idx_protein_id` (`protein_id`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EDIAP_BS_whole_animal_symbiotic_10` (
  `Type` text,
  `seqnames` text,
  `start` text,
  `end` text,
  `meth_level` text,
  `annotation` text,
  `chr` text,
  `geneStart` text,
  `geneEnd` text,
  `geneLength` text,
  `distanceToTSS` text,
  `protein_id` text,
  `product` text,
  KEY `idx_protein_id` (`protein_id`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EDIAP_BS_whole_animal_symbiotic_11` (
  `Type` text,
  `seqnames` text,
  `start` text,
  `end` text,
  `meth_level` text,
  `annotation` text,
  `chr` text,
  `geneStart` text,
  `geneEnd` text,
  `geneLength` text,
  `distanceToTSS` text,
  `protein_id` text,
  `product` text,
  KEY `idx_protein_id` (`protein_id`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EDIAP_BS_whole_animal_symbiotic_12` (
  `Type` text,
  `seqnames` text,
  `start` text,
  `end` text,
  `meth_level` text,
  `annotation` text,
  `chr` text,
  `geneStart` text,
  `geneEnd` text,
  `geneLength` text,
  `distanceToTSS` text,
  `protein_id` text,
  `product` text,
  KEY `idx_protein_id` (`protein_id`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EDIAP_BS_whole_animal_symbiotic_13` (
  `Type` text,
  `seqnames` text,
  `start` text,
  `end` text,
  `meth_level` text,
  `annotation` text,
  `chr` text,
  `geneStart` text,
  `geneEnd` text,
  `geneLength` text,
  `distanceToTSS` text,
  `protein_id` text,
  `product` text,
  KEY `idx_protein_id` (`protein_id`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EDIAP_BS_whole_animal_symbiotic_14` (
  `Type` text,
  `seqnames` text,
  `start` text,
  `end` text,
  `meth_level` text,
  `annotation` text,
  `chr` text,
  `geneStart` text,
  `geneEnd` text,
  `geneLength` text,
  `distanceToTSS` text,
  `protein_id` text,
  `product` text,
  KEY `idx_protein_id` (`protein_id`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EDIAP_BS_whole_animal_symbiotic_2` (
  `Type` text,
  `seqnames` text,
  `start` text,
  `end` text,
  `meth_level` text,
  `annotation` text,
  `chr` text,
  `geneStart` text,
  `geneEnd` text,
  `geneLength` text,
  `distanceToTSS` text,
  `protein_id` text,
  `product` text,
  KEY `idx_protein_id` (`protein_id`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EDIAP_BS_whole_animal_symbiotic_3` (
  `Type` text,
  `seqnames` text,
  `start` text,
  `end` text,
  `meth_level` text,
  `annotation` text,
  `chr` text,
  `geneStart` text,
  `geneEnd` text,
  `geneLength` text,
  `distanceToTSS` text,
  `protein_id` text,
  `product` text,
  KEY `idx_protein_id` (`protein_id`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EDIAP_BS_whole_animal_symbiotic_4` (
  `Type` text,
  `seqnames` text,
  `start` text,
  `end` text,
  `meth_level` text,
  `annotation` text,
  `chr` text,
  `geneStart` text,
  `geneEnd` text,
  `geneLength` text,
  `distanceToTSS` text,
  `protein_id` text,
  `product` text,
  KEY `idx_protein_id` (`protein_id`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EDIAP_BS_whole_animal_symbiotic_5` (
  `Type` text,
  `seqnames` text,
  `start` text,
  `end` text,
  `meth_level` text,
  `annotation` text,
  `chr` text,
  `geneStart` text,
  `geneEnd` text,
  `geneLength` text,
  `distanceToTSS` text,
  `protein_id` text,
  `product` text,
  KEY `idx_protein_id` (`protein_id`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EDIAP_BS_whole_animal_symbiotic_6` (
  `Type` text,
  `seqnames` text,
  `start` text,
  `end` text,
  `meth_level` text,
  `annotation` text,
  `chr` text,
  `geneStart` text,
  `geneEnd` text,
  `geneLength` text,
  `distanceToTSS` text,
  `protein_id` text,
  `product` text,
  KEY `idx_protein_id` (`protein_id`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EDIAP_BS_whole_animal_symbiotic_7` (
  `Type` text,
  `seqnames` text,
  `start` text,
  `end` text,
  `meth_level` text,
  `annotation` text,
  `chr` text,
  `geneStart` text,
  `geneEnd` text,
  `geneLength` text,
  `distanceToTSS` text,
  `protein_id` text,
  `product` text,
  KEY `idx_protein_id` (`protein_id`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EDIAP_BS_whole_animal_symbiotic_8` (
  `Type` text,
  `seqnames` text,
  `start` text,
  `end` text,
  `meth_level` text,
  `annotation` text,
  `chr` text,
  `geneStart` text,
  `geneEnd` text,
  `geneLength` text,
  `distanceToTSS` text,
  `protein_id` text,
  `product` text,
  KEY `idx_protein_id` (`protein_id`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EDIAP_BS_whole_animal_symbiotic_9` (
  `Type` text,
  `seqnames` text,
  `start` text,
  `end` text,
  `meth_level` text,
  `annotation` text,
  `chr` text,
  `geneStart` text,
  `geneEnd` text,
  `geneLength` text,
  `distanceToTSS` text,
  `protein_id` text,
  `product` text,
  KEY `idx_protein_id` (`protein_id`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EDIAP_ChIP` (
  `Sample` text,
  `Chr` text,
  `start` text,
  `end` text,
  `peak_name` text,
  `Summit` text,
  `Pileup` text,
  `peak_location` text,
  `geneChr` text,
  `geneStart` text,
  `geneEnd` text,
  `geneLength` text,
  `distanceToTSS` text,
  `protein_id` text,
  `description` text,
  KEY `idx_protein_id` (`protein_id`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EDIAP_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EDIAP_Symbiont_Breviolum_minutum_proteomics` (
  `protein` text,
  `Q_value` text,
  `coverage` text,
  `Intensity` text,
  `Peptides` text,
  `Unique_peptides` text,
  `Mol_weight` text,
  `Score` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EDIAP_Symbiont_Durusdinium_trenchii_proteomics` (
  `protein` text,
  `Q_value` text,
  `coverage` text,
  `Intensity` text,
  `Peptides` text,
  `Unique_peptides` text,
  `Mol_weight` text,
  `Score` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EDIAP_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EDIAP_TPM` (
  `Gene` text,
  `SRR6202203_whole_animal_aposymbiotic_A3_Run2_L5` text,
  `SRR6202204_whole_animal_aposymbiotic_A3_Run2_L6` text,
  `SRR6202205_whole_animal_aposymbiotic_A1_Run2_L5` text,
  `SRR6202206_whole_animal_aposymbiotic_A1_Run2_L6` text,
  `SRR6202207_whole_animal_aposymbiotic_A1_Run2_L7` text,
  `SRR6202208_whole_animal_aposymbiotic_A1_Run2_L8` text,
  `SRR6202209_whole_animal_aposymbiotic_A2_Run2_L5` text,
  `SRR6202210_whole_animal_aposymbiotic_A2_Run2_L6` text,
  `SRR6202211_whole_animal_aposymbiotic_A2_Run2_L7` text,
  `SRR6202212_whole_animal_aposymbiotic_A2_Run2_L8` text,
  `SRR6202233_whole_animal_aposymbiotic_A5_Run2_L6` text,
  `SRR6202234_whole_animal_aposymbiotic_A5_Run2_L5` text,
  `SRR6202235_whole_animal_aposymbiotic_A4_Run2_L8` text,
  `SRR6202236_whole_animal_aposymbiotic_A4_Run2_L7` text,
  `SRR6202237_whole_animal_aposymbiotic_A4_Run2_L6` text,
  `SRR6202238_whole_animal_aposymbiotic_A4_Run2_L5` text,
  `SRR6202239_whole_animal_aposymbiotic_A3_Run2_L8` text,
  `SRR6202240_whole_animal_aposymbiotic_A3_Run2_L7` text,
  `SRR6202241_whole_animal_aposymbiotic_A5_Run2_L8` text,
  `SRR6202242_whole_animal_aposymbiotic_A5_Run2_L7` text,
  `SRR6202254_whole_animal_aposymbiotic_A4_Run1_L5` text,
  `SRR6202255_whole_animal_aposymbiotic_A4_Run1_L6` text,
  `SRR6202256_whole_animal_aposymbiotic_A3_Run1_L5` text,
  `SRR6202257_whole_animal_aposymbiotic_A3_Run1_L6` text,
  `SRR6202258_whole_animal_aposymbiotic_A6_Run1_L5` text,
  `SRR6202259_whole_animal_aposymbiotic_A6_Run1_L6` text,
  `SRR6202260_whole_animal_aposymbiotic_A5_Run1_L5` text,
  `SRR6202261_whole_animal_aposymbiotic_A5_Run1_L6` text,
  `SRR6202262_whole_animal_symbiotic_S1_Run1_L5` text,
  `SRR6202263_whole_animal_symbiotic_S1_Run1_L6` text,
  `SRR6202276_whole_animal_symbiotic_S2_Run1_L6` text,
  `SRR6202277_whole_animal_symbiotic_S2_Run1_L5` text,
  `SRR6202278_whole_animal_symbiotic_S3_Run1_L6` text,
  `SRR6202279_whole_animal_symbiotic_S3_Run1_L5` text,
  `SRR6202280_whole_animal_symbiotic_S4_Run1_L6` text,
  `SRR6202281_whole_animal_symbiotic_S4_Run1_L5` text,
  `SRR6202282_whole_animal_symbiotic_S5_Run1_L6` text,
  `SRR6202283_whole_animal_symbiotic_S5_Run1_L5` text,
  `SRR6202284_whole_animal_symbiotic_S6_Run1_L6` text,
  `SRR6202285_whole_animal_symbiotic_S6_Run1_L5` text,
  `SRR6202303_whole_animal_symbiotic_S6_Run2_L5` text,
  `SRR6202304_whole_animal_symbiotic_S6_Run2_L6` text,
  `SRR6202305_whole_animal_symbiotic_S6_Run2_L7` text,
  `SRR6202306_whole_animal_symbiotic_S6_Run2_L8` text,
  `SRR6202307_whole_animal_symbiotic_S5_Run2_L5` text,
  `SRR6202308_whole_animal_symbiotic_S5_Run2_L6` text,
  `SRR6202309_whole_animal_symbiotic_S5_Run2_L7` text,
  `SRR6202310_whole_animal_symbiotic_S5_Run2_L8` text,
  `SRR6202337_whole_animal_symbiotic_S2_Run2_L5` text,
  `SRR6202338_whole_animal_symbiotic_S2_Run2_L6` text,
  `SRR6202339_whole_animal_symbiotic_S1_Run2_L7` text,
  `SRR6202340_whole_animal_symbiotic_S1_Run2_L8` text,
  `SRR6202341_whole_animal_symbiotic_S1_Run2_L5` text,
  `SRR6202342_whole_animal_symbiotic_S1_Run2_L6` text,
  `SRR6202343_whole_animal_aposymbiotic_A6_Run2_L7` text,
  `SRR6202344_whole_animal_aposymbiotic_A6_Run2_L8` text,
  `SRR6202345_whole_animal_aposymbiotic_A6_Run2_L5` text,
  `SRR6202346_whole_animal_aposymbiotic_A6_Run2_L6` text,
  `SRR6202347_whole_animal_symbiotic_S4_Run2_L8` text,
  `SRR6202348_whole_animal_symbiotic_S4_Run2_L7` text,
  `SRR6202349_whole_animal_symbiotic_S3_Run2_L8` text,
  `SRR6202350_whole_animal_symbiotic_S3_Run2_L7` text,
  `SRR6202351_whole_animal_symbiotic_S4_Run2_L6` text,
  `SRR6202352_whole_animal_symbiotic_S4_Run2_L5` text,
  `SRR6202353_whole_animal_symbiotic_S2_Run2_L8` text,
  `SRR6202354_whole_animal_symbiotic_S2_Run2_L7` text,
  `SRR6202355_whole_animal_symbiotic_S3_Run2_L6` text,
  `SRR6202356_whole_animal_symbiotic_S3_Run2_L5` text,
  `SRR6202357_whole_animal_aposymbiotic_A2_Run1_L6` text,
  `SRR6202358_whole_animal_aposymbiotic_A2_Run1_L5` text,
  `SRR6202363_whole_animal_aposymbiotic_A1_Run1_L6` text,
  `SRR6202364_whole_animal_aposymbiotic_A1_Run1_L5` text,
  `avg` text,
  `std` text,
  KEY `ix_gene` (`Gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EDIAP_Whole_anemone_proteomics` (
  `gene` text,
  `Q_value` text,
  `Peptides` text,
  `Unique_peptides` text,
  `coverage` text,
  `Mol_weight` text,
  `Score` text,
  `Intensity` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EDIAP_coexpress_negative` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EDIAP_coexpress_positive` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EDIAP_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EDIAP_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EDIAP_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EDIAP_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EDIAP_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EDIAP_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EDIAP_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EDIAP_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EELEG_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EELEG_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`GO_term`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EELEG_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`InterPro_term`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EELEG_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EELEG_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EELEG_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EELEG_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EELEG_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EELEG_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EHORR_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EHORR_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EHORR_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EHORR_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EHORR_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EHORR_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EHORR_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EHORR_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EHORR_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EHORR_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EVERR_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EVERR_TPM` (
  `Gene` text,
  `ERR11252240_whole_tissue_polyp` text,
  `ERR11252242_whole_tissue_polyp` text,
  `ERR11252243_whole_tissue_polyp` text,
  `ERR11252244_whole_tissue_polyp` text,
  `ERR11252245_whole_tissue_polyp` text,
  `ERR11252246_whole_tissue_polyp` text,
  `ERR11252248_whole_tissue_polyp` text,
  `ERR11252249_whole_tissue_polyp` text,
  `ERR11252250_whole_tissue_polyp` text,
  `ERR11252251_whole_tissue_polyp` text,
  `ERR11252252_whole_tissue_polyp` text,
  `ERR11252253_whole_tissue_polyp` text,
  `ERR11252254_whole_tissue_polyp` text,
  `ERR11252255_whole_tissue_polyp` text,
  `ERR11252256_whole_tissue_polyp` text,
  `ERR11252257_whole_tissue_polyp` text,
  `ERR11252258_whole_tissue_polyp` text,
  `ERR11252259_whole_tissue_polyp` text,
  `ERR11252260_whole_tissue_polyp` text,
  `ERR11252261_whole_tissue_polyp` text,
  `ERR11252262_whole_tissue_polyp` text,
  `avg` text,
  `std` text,
  KEY `ix_gene` (`Gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EVERR_coexpress_negative` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EVERR_coexpress_positive` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EVERR_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EVERR_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EVERR_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EVERR_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EVERR_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EVERR_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EVERR_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EVERR_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `FANCO_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `FANCO_TPM` (
  `Gene` text,
  `DRR235372_ovaries_oocytes_with_cytoplasmic_polarization` text,
  `DRR235374_ovaries_oocytes_with_cytoplasmic_polarization` text,
  `DRR235375_ovaries_oocytes_126_200_um_in_diameter_female` text,
  `DRR235376_ovaries_oocytes_126_200_um_in_diameter_female` text,
  `DRR235377_ovaries_oocytes_126_200_um_in_diameter_female` text,
  `DRR235378_ovaries_oocytes_201_275_um_in_diameter_female` text,
  `DRR235379_ovaries_oocytes_201_275_um_in_diameter_female` text,
  `DRR235380_ovaries_oocytes_201_275_um_in_diameter_female` text,
  `DRR235381_276_um_in_diameter_and_GVBD_female` text,
  `DRR235382_276_um_in_diameter_and_GVBD_female` text,
  `DRR235383_276_um_in_diameter_and_GVBD_female` text,
  `DRR235384_testes_spermatogonia_male` text,
  `DRR235385_testes_spermatogonia_male` text,
  `DRR235386_testes_spermatogonia_male` text,
  `DRR235387_testes_spermatogonia_and_primary_spermatocytes_male` text,
  `DRR235389_testes_spermatogonia_and_primary_spermatocytes_male` text,
  `DRR397929_Tentacles` text,
  `DRR397944_Mouth_and_pharynx` text,
  `avg` text,
  `std` text,
  KEY `ix_gene` (`Gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `FANCO_coexpress_negative` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `FANCO_coexpress_positive` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `FANCO_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `FANCO_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `FANCO_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `FANCO_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `FANCO_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `FANCO_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `FANCO_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `FANCO_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `GFASC_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `GFASC_TPM` (
  `Gene` text,
  `SRR27118466_holosome_30oc_treatment` text,
  `SRR27118468_holosome_30oc_treatment` text,
  `SRR27118469_holosome_Prometryn_herbicidess_treatment` text,
  `SRR27118472_holosome_Prometryn_herbicidess_and_30oc_treatment` text,
  `SRR27118475_holosome_Prometryn_herbicidess_and_30oc_treatment` text,
  `SRR27118476_holosome_30oc_treatment` text,
  `SRR27118477_holosome_30oc_treatment` text,
  `SRR27118478_holosome_30oc_treatment` text,
  `SRR27118479_holosome_30oc_treatment` text,
  `SRR27118480_holosome_Control` text,
  `SRR27118481_holosome_Prometryn_herbicidess_treatment` text,
  `SRR27118482_holosome_Prometryn_herbicidess_treatment` text,
  `SRR27118483_holosome_Prometryn_herbicidess_treatment` text,
  `SRR27118484_holosome_Prometryn_herbicidess_treatment` text,
  `SRR27118485_holosome_Control` text,
  `SRR27118486_holosome_Control` text,
  `SRR27118487_holosome_Control` text,
  `SRR27118488_holosome_Control` text,
  `SRR27118489_holosome_Prometryn_herbicidess_and_30oc_treatment` text,
  `SRR27118490_holosome_Prometryn_herbicidess_and_30oc_treatment` text,
  `SRR27118491_holosome_Control` text,
  `SRR27118492_holosome_Control` text,
  `avg` text,
  `std` text,
  KEY `ix_gene` (`Gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `GFASC_coexpress_negative` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `GFASC_coexpress_positive` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `GFASC_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `GFASC_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `GFASC_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `GFASC_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `GFASC_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `GFASC_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `GFASC_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `GFASC_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HCOER_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HCOER_TPM` (
  `Gene` text,
  `SRR12578063_polyp_and_skeleton_28C_24hr` text,
  `SRR12578064_polyp_and_skeleton_31C_24hr` text,
  `SRR12578065_polyp_and_skeleton_31C_3week` text,
  `SRR12578066_polyp_and_skeleton_31C_3week` text,
  `SRR12578067_polyp_and_skeleton_28C_3week` text,
  `SRR12578068_polyp_and_skeleton_31C_3week` text,
  `SRR12587798_polyp_and_skeleton_26C_3week` text,
  `SRR12587799_polyp_and_skeleton_31C_3week` text,
  `SRR12587800_polyp_and_skeleton_31C_3week` text,
  `SRR12587801_polyp_and_skeleton_26C_3week` text,
  `SRR12587802_polyp_and_skeleton_28C_3week` text,
  `SRR12587803_polyp_and_skeleton_28C_3week` text,
  `SRR12587804_polyp_and_skeleton_31C_3week` text,
  `SRR12587805_polyp_and_skeleton_26C_3week` text,
  `SRR12587806_polyp_and_skeleton_26C_3week` text,
  `SRR12587807_polyp_and_skeleton_28C_3week` text,
  `SRR12587808_polyp_and_skeleton_28C_3week` text,
  `SRR5949848_polyp_and_skeleton_31C_24hr` text,
  `SRR5949849_polyp_and_skeleton_28C_3week` text,
  `SRR5949850_polyp_and_skeleton_28C_24hr` text,
  `ERR6178387_Whole_coral_Molecular_and_mineral_responses` text,
  `ERR6178388_Whole_coral_Molecular_and_mineral_responses` text,
  `ERR6178389_Whole_coral_Molecular_and_mineral_responses` text,
  `ERR6178770_Whole_coral_Molecular_and_mineral_responses` text,
  `ERR6178771_Whole_coral_Molecular_and_mineral_responses` text,
  `ERR6178772_Whole_coral_Molecular_and_mineral_responses` text,
  `ERR6178773_Whole_coral_Molecular_and_mineral_responses` text,
  `ERR6178774_Whole_coral_Molecular_and_mineral_responses` text,
  `ERR6178775_Whole_coral_Molecular_and_mineral_responses` text,
  `ERR6178776_Whole_coral_Molecular_and_mineral_responses` text,
  `ERR6178777_Whole_coral_Molecular_and_mineral_responses` text,
  `ERR6178778_Whole_coral_Molecular_and_mineral_responses` text,
  `ERR6178779_Whole_coral_Molecular_and_mineral_responses` text,
  `ERR6178780_Whole_coral_Molecular_and_mineral_responses` text,
  `ERR6178781_Whole_coral_Molecular_and_mineral_responses` text,
  `ERR6178782_Whole_coral_Molecular_and_mineral_responses` text,
  `ERR6178783_Whole_coral_Molecular_and_mineral_responses` text,
  `avg` text,
  `std` text,
  KEY `ix_gene` (`Gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HCOER_coexpress_negative` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HCOER_coexpress_positive` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HCOER_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HCOER_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HCOER_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HCOER_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HCOER_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HCOER_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HCOER_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HCOER_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HECHI_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HECHI_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HECHI_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HECHI_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HECHI_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HECHI_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HECHI_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HECHI_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HECHI_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HIMPE_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HIMPE_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HIMPE_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HIMPE_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HIMPE_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HIMPE_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HIMPE_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HIMPE_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HIMPE_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HOCTO_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HOCTO_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HOCTO_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`InterPro_term`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HOCTO_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HOCTO_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HOCTO_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HOCTO_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HOCTO_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HOCTO_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HOLIG_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HOLIG_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HOLIG_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HOLIG_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HOLIG_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HOLIG_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HOLIG_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HOLIG_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HOLIG_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HOLIG_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HSALM_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HSALM_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HSALM_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`GO_term`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HSALM_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`InterPro_term`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HSALM_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HSALM_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HSALM_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HSALM_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HSALM_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HSALM_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HSYMB_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HSYMB_TPM` (
  `Gene` text,
  `SRR24482133_Whole_embryo_6_hpf` text,
  `SRR24482134_Whole_embryo_5_hpf` text,
  `SRR24482135_Whole_embryo_4_hpf` text,
  `SRR24482136_Whole_embryo_4_hpf` text,
  `SRR24482137_Whole_embryo_3_hpf` text,
  `SRR24482138_Whole_embryo_3_hpf` text,
  `SRR24482139_Whole_embryo_2_hpf` text,
  `SRR24482140_Whole_embryo_2_hpf` text,
  `SRR24482141_Whole_embryo_1_hpf` text,
  `SRR24482142_Whole_embryo_30_mpf` text,
  `SRR24482143_Whole_embryo_Unfertilized_egg` text,
  `SRR24482144_Whole_embryo_Unfertilized_egg` text,
  `SRR24482145_Whole_embryo_Unfertilized_egg` text,
  `SRR24482146_Whole_embryo_7_hpf_Triptolide_20_uM` text,
  `SRR24482147_Whole_embryo_7_hpf_Triptolide_20_uM` text,
  `SRR24482148_Whole_embryo_7_hpf_DMSO_0_5percent` text,
  `SRR24482149_Whole_embryo_7_hpf_Triptolide_20_uM` text,
  `SRR24482150_Whole_embryo_7_hpf_Triptolide_20_uM` text,
  `SRR24482151_Whole_embryo_7_hpf_DMSO_0_5percent` text,
  `SRR24482152_Whole_embryo_7_hpf_DMSO_0_5percent` text,
  `SRR24482153_Whole_embryo_7_hpf` text,
  `SRR24482154_Whole_embryo_7_hpf_DMSO_0_5percent` text,
  `SRR24482155_Whole_embryo_72_hpf` text,
  `SRR24482156_Whole_embryo_72_hpf` text,
  `SRR24482157_Whole_embryo_48_hpf` text,
  `SRR24482158_Whole_embryo_48_hpf` text,
  `SRR24482159_Whole_embryo_24_hpf` text,
  `SRR24482160_Whole_embryo_24_hpf` text,
  `SRR24482161_Whole_embryo_7_hpf` text,
  `SRR24482162_Whole_embryo_7_hpf` text,
  `SRR24482163_Whole_embryo_6_hpf` text,
  `SRR24482165_Whole_embryo_4_hpf` text,
  `SRR24482166_Whole_embryo_4_hpf` text,
  `SRR24482167_Whole_embryo_3_hpf` text,
  `SRR24482168_Whole_embryo_3_hpf` text,
  `SRR24482169_Whole_embryo_2_hpf` text,
  `SRR24482170_Whole_embryo_2_hpf` text,
  `SRR24482171_Whole_embryo_1_hpf` text,
  `SRR24482172_Whole_embryo_30_mpf` text,
  `SRR24482173_Whole_embryo_Unfertilized_egg` text,
  `SRR24482174_Whole_embryo_Unfertilized_egg` text,
  `SRR24482175_Whole_embryo_Unfertilized_egg` text,
  `SRR24482176_Whole_embryo_1_hpf` text,
  `SRR24482177_Whole_embryo_1_hpf` text,
  `avg` text,
  `std` text,
  KEY `ix_gene` (`Gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HSYMB_coexpress_negative` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HSYMB_coexpress_positive` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HSYMB_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HSYMB_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HSYMB_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HSYMB_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HSYMB_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HSYMB_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HSYMB_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HSYMB_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HVIRI_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HVIRI_TPM` (
  `Gene` text,
  `DRR048593_aposymbioic_hydra_rep1_M9_strain` text,
  `DRR048594_aposymbioic_hydra_rep2_M9_strain` text,
  `DRR048595_symbioic_hydra_rep1_M9_strain` text,
  `DRR048596_symbioic_hydra_rep2_M9_strain` text,
  `ERR13389755` text,
  `SRR10058802_Whole` text,
  `SRR10058803_Whole` text,
  `SRR10058804_Whole` text,
  `SRR10058805_Whole` text,
  `SRR10058806_Whole` text,
  `SRR10058807_Whole` text,
  `SRR21134050_whole_body` text,
  `SRR21134051_whole_body` text,
  `SRR21134052_whole_body` text,
  `SRR21134053_whole_body` text,
  `SRR21134054_whole_body` text,
  `SRR21134055_whole_body` text,
  `SRR21134056_whole_body` text,
  `SRR21134057_whole_body` text,
  `SRR21134058_whole_body` text,
  `SRR21134059_whole_body` text,
  `SRR21134060_whole_body` text,
  `SRR21134061_whole_body` text,
  `SRR21134062_whole_body` text,
  `SRR21134063_whole_body` text,
  `SRR21134064_whole_body` text,
  `SRR21134065_whole_body` text,
  `SRR21134066_whole_body` text,
  `SRR21134067_whole_body` text,
  `avg` text,
  `std` text,
  KEY `ix_gene` (`Gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HVIRI_coexpress_negative` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HVIRI_coexpress_positive` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HVIRI_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HVIRI_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HVIRI_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HVIRI_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HVIRI_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HVIRI_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HVIRI_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HVIRI_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HVULG_ChIP` (
  `Sample` text,
  `Chr` text,
  `start` text,
  `end` text,
  `peak_name` text,
  `Summit` text,
  `Pileup` text,
  `peak_location` text,
  `geneChr` text,
  `geneStart` text,
  `geneEnd` text,
  `geneLength` text,
  `distanceToTSS` text,
  `protein_id` text,
  `description` text,
  KEY `idx_protein_id` (`protein_id`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HVULG_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HVULG_TPM` (
  `Gene` text,
  `SRR36435264_Regenerating_foot_0hpa_u0126_treatment` text,
  `SRR36435265_Regenerating_foot_1_5hpa_dmso_treatment` text,
  `SRR36435266_Regenerating_foot_1_5hpa_dmso_treatment` text,
  `SRR36435267_Regenerating_foot_1_5hpa_dmso_treatment` text,
  `SRR36435268_Regenerating_head_12hpa_u0126_treatment` text,
  `SRR36435269_Regenerating_head_12hpa_u0126_treatment` text,
  `SRR36435270_Regenerating_head_12hpa_u0126_treatment` text,
  `SRR36435271_Regenerating_head_12hpa_dmso_treatment` text,
  `SRR36435272_Regenerating_head_12hpa_dmso_treatment` text,
  `SRR36435273_Regenerating_head_12hpa_dmso_treatment` text,
  `SRR36435274_Regenerating_head_8hpa_25uM_U0126_treatment` text,
  `SRR36435275_Regenerating_head_8hpa_25uM_U0126_treatment` text,
  `SRR36435276_Regenerating_head_8hpa_25uM_U0126_treatment` text,
  `SRR36435277_Regenerating_head_8hpa_dmso_treatment` text,
  `SRR36435278_Regenerating_foot_3hpa_dmso_treatment` text,
  `SRR36435279_Regenerating_head_8hpa_dmso_treatment` text,
  `SRR36435280_Regenerating_head_8hpa_dmso_treatment` text,
  `SRR36435281_Regenerating_head_0hpa_25uM_U0126_treatment` text,
  `SRR36435282_Regenerating_head_0hpa_25uM_U0126_treatment` text,
  `SRR36435283_Regenerating_head_0hpa_25uM_U0126_treatment` text,
  `SRR36435284_Regenerating_head_0hpa_dmso_treatment` text,
  `SRR36435285_Regenerating_head_0hpa_dmso_treatment` text,
  `SRR36435286_Regenerating_head_0hpa_dmso_treatment` text,
  `SRR36435287_Whole_animal_12h_25uM_U0126_treatment` text,
  `SRR36435288_Whole_animal_12h_25uM_U0126_treatment` text,
  `SRR36435289_Regenerating_foot_3hpa_dmso_treatment` text,
  `SRR36435290_Whole_animal_12h_25uM_U0126_treatment` text,
  `SRR36435291_Whole_animal_12h_dmso_treatment` text,
  `SRR36435292_Whole_animal_12h_dmso_treatment` text,
  `SRR36435293_Whole_animal_12h_dmso_treatment` text,
  `SRR36435294_Regenerating_head_1_5hpa_25uM_U0126_treatment` text,
  `SRR36435295_Regenerating_head_1_5hpa_25uM_U0126_treatment` text,
  `SRR36435296_Regenerating_head_1_5hpa_25uM_U0126_treatment` text,
  `SRR36435297_Regenerating_head_3hpa_25uM_U0126_treatment` text,
  `SRR36435298_Regenerating_head_3hpa_25uM_U0126_treatment` text,
  `SRR36435299_Regenerating_head_3hpa_25uM_U0126_treatment` text,
  `SRR36435300_Regenerating_foot_3hpa_dmso_treatment` text,
  `SRR36435301_Regenerating_head_0hpa_25uM_U0126_treatment` text,
  `SRR36435302_Regenerating_head_0hpa_25uM_U0126_treatment` text,
  `SRR36435303_Regenerating_head_0hpa_25uM_U0126_treatment` text,
  `SRR36435304_Regenerating_head_1_5hpa_dmso_treatment` text,
  `SRR36435305_Regenerating_head_1_5hpa_dmso_treatment` text,
  `SRR36435306_Regenerating_head_1_5hpa_dmso_treatment` text,
  `SRR36435307_Regenerating_head_3hpa_dmso_treatment` text,
  `SRR36435308_Regenerating_head_3hpa_dmso_treatment` text,
  `SRR36435309_Regenerating_head_3hpa_dmso_treatment` text,
  `SRR36435310_Regenerating_head_0hpa_dmso_treatment` text,
  `SRR36435311_Regenerating_foot_0hpa_dmso_treatment` text,
  `SRR36435312_Regenerating_head_0hpa_dmso_treatment` text,
  `SRR36435313_Regenerating_head_0hpa_dmso_treatment` text,
  `SRR36435314_Regenerating_foot_1_5hpa_25uM_U0126_treatment` text,
  `SRR36435315_Regenerating_foot_1_5hpa_25uM_U0126_treatment` text,
  `SRR36435316_Regenerating_foot_1_5hpa_25uM_U0126_treatment` text,
  `SRR36435317_Regenerating_foot_3hpa_25uM_U0126_treatment` text,
  `SRR36435318_Regenerating_foot_3hpa_25uM_U0126_treatment` text,
  `SRR36435319_Regenerating_foot_3hpa_25uM_U0126_treatment` text,
  `SRR36435320_Regenerating_foot_0hpa_25uM_U0126_treatment` text,
  `SRR36435321_Regenerating_foot_0hpa_25uM_U0126_treatment` text,
  `SRR36435322_Regenerating_foot_0hpa_dmso_treatment` text,
  `SRR36435323_Regenerating_foot_0hpa_dmso_treatment` text,
  `ave` text,
  `std` text,
  KEY `ix_gene` (`Gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HVULG_coexpress_negative` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HVULG_coexpress_positive` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HVULG_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HVULG_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HVULG_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HVULG_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HVULG_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HVULG_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HVULG_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HVULG_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Hydra_ATAC_mid_body` (
  `Sample` text,
  `Chr` text,
  `start` text,
  `end` text,
  `peak_name` text,
  `Summit` text,
  `Pileup` text,
  `peak_location` text,
  `geneChr` text,
  `geneStart` text,
  `geneEnd` text,
  `geneLength` text,
  `distanceToTSS` text,
  `protein_id` text,
  `description` text,
  KEY `idx_protein_id` (`protein_id`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Hydra_ATAC_multitissue` (
  `Sample` text,
  `Chr` text,
  `start` text,
  `end` text,
  `peak_name` text,
  `Summit` text,
  `Pileup` text,
  `peak_location` text,
  `geneChr` text,
  `geneStart` text,
  `geneEnd` text,
  `geneLength` text,
  `distanceToTSS` text,
  `protein_id` text,
  `description` text,
  KEY `idx_protein_id` (`protein_id`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Hydra_ATAC_regenerating_head` (
  `Sample` text,
  `Chr` text,
  `start` text,
  `end` text,
  `peak_name` text,
  `Summit` text,
  `Pileup` text,
  `peak_location` text,
  `geneChr` text,
  `geneStart` text,
  `geneEnd` text,
  `geneLength` text,
  `distanceToTSS` text,
  `protein_id` text,
  `description` text,
  KEY `idx_protein_id` (`protein_id`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `LPERT_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `LPERT_TPM` (
  `Gene` text,
  `SRR11359494_polyp_Colony1_sampled_2weeks_at_pH7_9` text,
  `SRR11359495_polyp_Colony1_sampled_2weeks_at_pH7_9` text,
  `SRR11359496_polyp_Colony1_sampled_4_5weeks_at_pH7_9` text,
  `SRR11359497_polyp_Colony1_sampled_4_5weeks_at_pH7_9` text,
  `SRR11359498_polyp_Colony1_sampled_8_5weeks_at_pH7_9` text,
  `SRR11359499_polyp_Colony1_sampled_8_5weeks_at_pH7_9` text,
  `SRR11359500_polyp_Colony1_sampled_2weeks_at_pH7_6` text,
  `SRR11359501_polyp_Colony1_sampled_2weeks_at_pH7_6` text,
  `SRR11359502_polyp_Colony1_sampled_4_5weeks_at_pH7_6` text,
  `SRR11359503_polyp_Colony1_sampled_4_5weeks_at_pH7_6` text,
  `SRR11359504_polyp_Colony1_sampled_8_5weeks_at_pH7_6` text,
  `SRR11359505_polyp_Colony1_sampled_8_5weeks_at_pH7_6` text,
  `SRR11359506_polyp_Colony3_sampled_2weeks_at_pH7_9` text,
  `SRR11359507_polyp_Colony3_sampled_2weeks_at_pH7_9` text,
  `SRR11359508_polyp_Colony3_sampled_4_5weeks_at_pH7_9` text,
  `SRR11359509_polyp_Colony3_sampled_4_5weeks_at_pH7_9` text,
  `SRR11359510_polyp_Colony3_sampled_8_5weeks_at_pH7_9` text,
  `SRR11359511_polyp_Colony3_sampled_8_5weeks_at_pH7_9` text,
  `SRR11359512_polyp_Colony3_sampled_2weeks_at_pH7_6` text,
  `SRR11359513_polyp_Colony3_sampled_2weeks_at_pH7_6` text,
  `SRR11359514_polyp_Colony3_sampled_4_5weeks_at_pH7_6` text,
  `SRR11359515_polyp_Colony3_sampled_4_5weeks_at_pH7_6` text,
  `SRR11359516_polyp_Colony3_sampled_8_5weeks_at_pH7_6` text,
  `SRR11359517_polyp_Colony3_sampled_8_5weeks_at_pH7_6` text,
  `SRR11359518_polyp_Colony4_sampled_2weeks_at_pH7_9` text,
  `SRR11359519_polyp_Colony4_sampled_2weeks_at_pH7_9` text,
  `SRR11359520_polyp_Colony4_sampled_4_5weeks_at_pH7_9` text,
  `SRR11359521_polyp_Colony4_sampled_4_5weeks_at_pH7_9` text,
  `SRR11359522_polyp_Colony4_sampled_8_5weeks_at_pH7_9` text,
  `SRR11359523_polyp_Colony4_sampled_8_5weeks_at_pH7_9` text,
  `SRR11359524_polyp_Colony4_sampled_2weeks_at_pH7_6` text,
  `SRR11359525_polyp_Colony4_sampled_2weeks_at_pH7_6` text,
  `SRR11359526_polyp_Colony4_sampled_4_5weeks_at_pH7_6` text,
  `SRR11359527_polyp_Colony4_sampled_4_5weeks_at_pH7_6` text,
  `SRR11359528_polyp_Colony4_sampled_8_5weeks_at_pH7_6` text,
  `SRR11359529_polyp_Colony4_sampled_8_5weeks_at_pH7_6` text,
  `SRR16705942_coral_polyp_control_treatment` text,
  `SRR16705943_coral_polyp_control_treatment` text,
  `SRR16705944_coral_polyp_oil_and_dispersant_treatment` text,
  `SRR16705945_coral_polyp_oil_and_dispersant_treatment` text,
  `SRR16705946_coral_polyp_control_treatment` text,
  `SRR16705947_coral_polyp_oil_and_dispersant_treatment` text,
  `SRR16705948_coral_polyp_oil_and_dispersant_treatment` text,
  `SRR16705949_coral_polyp_oil_treatment` text,
  `SRR16705950_coral_polyp_oil_treatment` text,
  `SRR16705951_coral_polyp_oil_treatment` text,
  `SRR16705952_coral_polyp_oil_treatment` text,
  `SRR16705953_coral_polyp_dispersant_treatment` text,
  `SRR16705954_coral_polyp_dispersant_treatment` text,
  `SRR16705955_coral_polyp_dispersant_treatment` text,
  `SRR16705956_coral_polyp_dispersant_treatment` text,
  `SRR16705957_coral_polyp_control_treatment` text,
  `SRR16705958_coral_polyp_dispersant_treatment` text,
  `SRR16705959_coral_polyp_dispersant_treatment` text,
  `SRR16705960_coral_polyp_control_treatment` text,
  `SRR16705961_coral_polyp_oil_treatment` text,
  `SRR16705962_coral_polyp_oil_treatment` text,
  `SRR16705963_coral_polyp_oil_treatment` text,
  `SRR16705964_coral_polyp_oil_treatment` text,
  `SRR16705965_coral_polyp_control_treatment` text,
  `SRR16705966_coral_polyp_dispersant_treatment` text,
  `SRR16705967_coral_polyp_dispersant_treatment` text,
  `SRR16705968_coral_polyp_dispersant_treatment` text,
  `SRR16705969_coral_polyp_dispersant_treatment` text,
  `SRR16705970_coral_polyp_control_treatment` text,
  `SRR16705971_coral_polyp_control_treatment` text,
  `SRR16705972_coral_polyp_control_treatment` text,
  `SRR16705973_coral_polyp_control_treatment` text,
  `SRR16705974_coral_polyp_control_treatment` text,
  `SRR16705975_coral_polyp_control_treatment` text,
  `SRR16705976_coral_polyp_oil_and_dispersant_treatment` text,
  `SRR16705977_coral_polyp_oil_and_dispersant_treatment` text,
  `SRR16705978_coral_polyp_oil_and_dispersant_treatment` text,
  `SRR16705979_coral_polyp_oil_and_dispersant_treatment` text,
  `SRR16705980_coral_polyp_oil_treatment` text,
  `SRR16705981_coral_polyp_oil_treatment` text,
  `SRR16705982_coral_polyp_oil_and_dispersant_treatment` text,
  `SRR16705983_coral_polyp_oil_treatment` text,
  `SRR16705984_coral_polyp_oil_treatment` text,
  `SRR16705985_coral_polyp_dispersant_treatment` text,
  `SRR16705986_coral_polyp_dispersant_treatment` text,
  `SRR16705987_coral_polyp_dispersant_treatment` text,
  `SRR16705988_coral_polyp_dispersant_treatment` text,
  `SRR16705989_coral_polyp_control_treatment` text,
  `SRR16705990_coral_polyp_control_treatment` text,
  `SRR16705991_coral_polyp_control_treatment` text,
  `SRR16705992_coral_polyp_control_treatment` text,
  `SRR16705993_coral_polyp_oil_and_dispersant_treatment` text,
  `SRR16705994_coral_polyp_oil_and_dispersant_treatment` text,
  `SRR16705995_coral_polyp_oil_and_dispersant_treatment` text,
  `SRR16705996_coral_polyp_oil_and_dispersant_treatment` text,
  `SRR16705997_coral_polyp_oil_and_dispersant_treatment` text,
  `SRR16705998_coral_polyp_oil_treatment` text,
  `SRR16705999_coral_polyp_oil_treatment` text,
  `SRR16706000_coral_polyp_oil_treatment` text,
  `SRR16706001_coral_polyp_oil_treatment` text,
  `SRR16706002_coral_polyp_dispersant_treatment` text,
  `SRR16706003_coral_polyp_dispersant_treatment` text,
  `SRR16706004_coral_polyp_oil_and_dispersant_treatment` text,
  `SRR16706005_coral_polyp_oil_and_dispersant_treatment` text,
  `SRR23025708_Polyp` text,
  `SRR23025709_Polyp` text,
  `SRR23025710_Polyp` text,
  `SRR23025711_Polyp` text,
  `SRR23025712_Polyp` text,
  `SRR23025713_Polyp` text,
  `SRR7746666_polyp` text,
  `SRR7746667_polyp` text,
  `SRR7746668_polyp` text,
  `SRR7746669_polyp` text,
  `ave` text,
  `atd` text,
  KEY `ix_gene` (`Gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `LPERT_coexpress_negative` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `LPERT_coexpress_positive` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `LPERT_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `LPERT_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `LPERT_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `LPERT_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `LPERT_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `LPERT_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `LPERT_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `LPERT_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `LSARM_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `LSARM_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `LSARM_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `LSARM_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `LSARM_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `LSARM_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `LSARM_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `LSARM_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `LSARM_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `LSCAB_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `LSCAB_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `LSCAB_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `LSCAB_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `LSCAB_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `LSCAB_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `LSCAB_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `LSCAB_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `LSCAB_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MAGs` (
  `class` text,
  `host` text,
  `Species` text,
  `TaxonID` text,
  `AssemblyAccession` text,
  `AssemblyLevel` text,
  `AssembledSize` text,
  `Nrcontigs` text,
  `ContigN50` text,
  `GCPercent` text,
  `CheckMcompleteness` text,
  `CheckMcontamination` text,
  `pubmed` text,
  `link` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MALCI_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MALCI_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MALCI_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MALCI_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`InterPro_term`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MALCI_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MALCI_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MALCI_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MALCI_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MALCI_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MALCI_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MAURE_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MAURE_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MAURE_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MAURE_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MAURE_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MAURE_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MAURE_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MAURE_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MAURE_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MCACT_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MCACT_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MCACT_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MCACT_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MCACT_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MCACT_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MCACT_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MCACT_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MCACT_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MCAPI_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MCAPI_TPM` (
  `Gene` text,
  `SRR11452216` text,
  `SRR11452217` text,
  `SRR11452218` text,
  `SRR11452219` text,
  `SRR11452220` text,
  `SRR11452221` text,
  `SRR11452222` text,
  `SRR11452223` text,
  `SRR11452224` text,
  `SRR11452225` text,
  `SRR11452226` text,
  `SRR11452227` text,
  `SRR11452228` text,
  `SRR11452229` text,
  `SRR11452230` text,
  `SRR11452231` text,
  `SRR11452232` text,
  `SRR11452233` text,
  `SRR11452234` text,
  `SRR11452235` text,
  `SRR11452236` text,
  `SRR11452237` text,
  `SRR11452238` text,
  `SRR11452239` text,
  `SRR11452240` text,
  `SRR11452241` text,
  `SRR11452242` text,
  `SRR11452243` text,
  `SRR11452244` text,
  `SRR11452245` text,
  `SRR11452246` text,
  `SRR11452247` text,
  `SRR11452248` text,
  `SRR11452249` text,
  `SRR11452250` text,
  `SRR11452251` text,
  `SRR11452252` text,
  `SRR11452253` text,
  `SRR11452254` text,
  `SRR11452255` text,
  `SRR11452256` text,
  `SRR11452257` text,
  `SRR11452258` text,
  `SRR11452259` text,
  `SRR11452260` text,
  `SRR11452261` text,
  `SRR11452262` text,
  `SRR11452263` text,
  `avg` text,
  `std` text,
  KEY `ix_gene` (`Gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MCAPI_coexpress_negative` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MCAPI_coexpress_positive` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MCAPI_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MCAPI_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MCAPI_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MCAPI_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MCAPI_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MCAPI_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MCAPI_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MCAPI_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MCAPR_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MCAPR_TPM` (
  `Gene` text,
  `SRR12710846_Polyps_OA3_day3` text,
  `SRR12710847_Polyps_OA3_day3` text,
  `SRR12710848_Polyps_OA3_day3` text,
  `SRR12710855_Polyps_OA3_day9` text,
  `SRR12710857_Polyps_OA3_day9` text,
  `SRR12710858_Polyps_OA3_day9` text,
  `SRR12786896_Polyps_OA3_day0` text,
  `SRR12786897_Polyps_OA3_day0` text,
  `SRR12786898_Polyps_OA3_day0` text,
  `SRR12807381_Polyps_OA3_day0` text,
  `SRR12849113_Polyps_OA3_day0` text,
  `SRR12904781_Polyps` text,
  `SRR12904782_Polyps` text,
  `SRR12904783_Polyps` text,
  `SRR12927880_Polyps_E3_day0` text,
  `SRR12959182_Polyps_E3_day0` text,
  `SRR12959183_Polyps_E3_day0` text,
  `SRR12959184_Polyps_E3_day0` text,
  `SRR12959188_Polyps_E3_day21` text,
  `SRR12959189_Polyps_E3_day21` text,
  `SRR12959190_Polyps_E3_day21` text,
  `SRR12959201_Polyps_E3_day15` text,
  `SRR12959202_Polyps_E3_day15` text,
  `SRR12959203_Polyps_E3_day15` text,
  `SRR12959214_Polyps_E3_day9` text,
  `SRR12959215_Polyps_E3_day9` text,
  `SRR12959216_Polyps_E3_day9` text,
  `SRR12959227_Polyps_E3_day3` text,
  `SRR12959229_Polyps_E3_day3` text,
  `SRR12959230_Polyps_E3_day3` text,
  `SRR12963484_Polyps_E3_day0` text,
  `SRR27940191_Polyps` text,
  `SRR27940192_Polyps` text,
  `SRR27940193_Polyps` text,
  `SRR9129316_Polyps` text,
  `SRR9613519_Polyps` text,
  `avg` text,
  `std` text,
  KEY `ix_gene` (`Gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MCAPR_coexpress_negative` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MCAPR_coexpress_positive` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MCAPR_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MCAPR_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MCAPR_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MCAPR_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MCAPR_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MCAPR_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MCAPR_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MCAPR_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MCOMP_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MCOMP_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MCOMP_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MCOMP_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MCOMP_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MCOMP_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MCOMP_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MCOMP_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MCOMP_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MCOMP_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MDICH_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MDICH_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MDICH_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MDICH_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MDICH_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MDICH_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MDICH_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MDICH_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MDICH_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MDICH_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MEFFL_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MEFFL_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MEFFL_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MEFFL_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MEFFL_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MEFFL_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MEFFL_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MEFFL_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MEFFL_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MFOLI_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MFOLI_TPM` (
  `Gene` text,
  `SRR12710845_Polyps_OA4_day3` text,
  `SRR12710852_Polyps_OA4_day9` text,
  `SRR12710853_Polyps_OA4_day9` text,
  `SRR12710854_Polyps_OA4_day9` text,
  `SRR12710865_Polyps_OA4_day3` text,
  `SRR12710866_Polyps_OA4_day3` text,
  `SRR12786895_Polyps_OA4_day0` text,
  `SRR12786903_Polyps_OA4_day0` text,
  `SRR12786904_Polyps_OA4_day0` text,
  `SRR12807380_Polyps_OA4_day0` text,
  `SRR12849112_Polyps_OA4_day0` text,
  `SRR12904780_Polyps` text,
  `SRR12904791_Polyps` text,
  `SRR12904792_Polyps` text,
  `SRR12927879_Polyps_E4_day0` text,
  `SRR12959181_Polyps_E4_day0` text,
  `SRR12959185_Polyps_E4_day21` text,
  `SRR12959186_Polyps_E4_day21` text,
  `SRR12959187_Polyps_E4_day21` text,
  `SRR12959198_Polyps_E4_day15` text,
  `SRR12959199_Polyps_E4_day15` text,
  `SRR12959200_Polyps_E4_day15` text,
  `SRR12959211_Polyps_E4_day9` text,
  `SRR12959212_Polyps_E4_day9` text,
  `SRR12959213_Polyps_E4_day9` text,
  `SRR12959224_Polyps_E4_day3` text,
  `SRR12959225_Polyps_E4_day3` text,
  `SRR12959226_Polyps_E4_day3` text,
  `SRR12959237_Polyps_E4_day0` text,
  `SRR12959238_Polyps_E4_day0` text,
  `SRR12963483_Polyps_E4_day0` text,
  `SRR27940177_Polyps` text,
  `SRR27940179_Polyps` text,
  `SRR27940180_Polyps` text,
  `SRR9129315_Polyps` text,
  `SRR9613518_Polyps` text,
  `avg` text,
  `std` text,
  KEY `ix_gene` (`Gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MFOLI_coexpress_negative` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MFOLI_coexpress_positive` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MFOLI_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MFOLI_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MFOLI_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MFOLI_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MFOLI_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MFOLI_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MFOLI_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MFOLI_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MGRIS_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MGRIS_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MGRIS_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MGRIS_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MGRIS_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MGRIS_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MGRIS_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MGRIS_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MGRIS_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MGRIS_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MHONG_Cysts_proteomics` (
  `Protein` text,
  `Proteins` text,
  `Peptides` text,
  `Uniquepeptides` text,
  `coverage` text,
  `Molweight` text,
  `Q_value` text,
  `Intensity` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MHONG_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MHONG_Nematocysts_proteomics` (
  `Protein` text,
  `Proteins` text,
  `Peptides` text,
  `Uniquepeptides` text,
  `coverage` text,
  `Molweight` text,
  `Q_value` text,
  `Intensity` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MHONG_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`GO_term`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MHONG_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`InterPro_term`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MHONG_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MHONG_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MHONG_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MHONG_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MHONG_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MHONG_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MLORD_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MLORD_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MLORD_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MLORD_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MLORD_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MLORD_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MLORD_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MLORD_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MLORD_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MMEAN_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MMEAN_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MMEAN_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MMEAN_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MMEAN_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MMEAN_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MMEAN_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MMEAN_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MMEAN_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MMURI_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MMURI_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MMURI_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MMURI_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MMURI_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MMURI_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MMURI_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MMURI_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MMURI_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MPAPU_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MPAPU_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MPAPU_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MPAPU_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MPAPU_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MPAPU_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MPAPU_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MPAPU_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MPAPU_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MSENA_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MSENA_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MSENA_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MSENA_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MSENA_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MSENA_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MSENA_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MSENA_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MSENA_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MSENI_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MSENI_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MSENI_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MSENI_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MSENI_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MSENI_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MSENI_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MSENI_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MSENI_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MSQUA_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MSQUA_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MSQUA_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`GO_term`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MSQUA_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`InterPro_term`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MSQUA_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MSQUA_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MSQUA_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MSQUA_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MSQUA_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MSQUA_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MVIRU_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MVIRU_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MVIRU_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MVIRU_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MVIRU_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MVIRU_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MVIRU_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MVIRU_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MVIRU_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Myxobilatus_gasterostei_stickleback_kidney_proteomics` (
  `Protein` text,
  `lgP` text,
  `Coverage` text,
  `Coverage_Sample1` text,
  `Area_Sample1` text,
  `Peptides` text,
  `Unique1` text,
  `Spec_Sample1` text,
  `PTM` text,
  `Avg_Mass` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Myxobolus_wulii_Cysts_proteomics` (
  `Protein` text,
  `Proteins` text,
  `Peptides` text,
  `Uniquepeptides` text,
  `coverage` text,
  `Molweight` text,
  `Q_value` text,
  `Intensity` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Myxobolus_wulii_Nematocysts_proteomics` (
  `Protein` text,
  `Proteins` text,
  `Peptides` text,
  `Uniquepeptides` text,
  `coverage` text,
  `Molweight` text,
  `Q_value` text,
  `Intensity` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `NNOMU_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `NNOMU_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `NNOMU_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `NNOMU_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`InterPro_term`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `NNOMU_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `NNOMU_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `NNOMU_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `NNOMU_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `NNOMU_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `NNOMU_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `NSEPT_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `NSEPT_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`GO_term`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `NSEPT_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`InterPro_term`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `NSEPT_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `NSEPT_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `NSEPT_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `NSEPT_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `NSEPT_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `NSEPT_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `NVECT_ATAC` (
  `Sample` text,
  `Chr` text,
  `start` text,
  `end` text,
  `peak_name` text,
  `Summit` text,
  `Pileup` text,
  `peak_location` text,
  `geneChr` text,
  `geneStart` text,
  `geneEnd` text,
  `geneLength` text,
  `distanceToTSS` text,
  `protein_id` text,
  `description` text,
  KEY `idx_protein_id` (`protein_id`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `NVECT_BS_Whole_adults` (
  `Type` text,
  `seqnames` text,
  `start` text,
  `end` text,
  `meth_level` text,
  `annotation` text,
  `chr` text,
  `geneStart` text,
  `geneEnd` text,
  `geneLength` text,
  `distanceToTSS` text,
  `protein_id` text,
  `product` text,
  KEY `idx_protein_id` (`protein_id`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `NVECT_ChIP` (
  `Sample` text,
  `Chr` text,
  `start` text,
  `end` text,
  `peak_name` text,
  `Summit` text,
  `Pileup` text,
  `peak_location` text,
  `geneChr` text,
  `geneStart` text,
  `geneEnd` text,
  `geneLength` text,
  `distanceToTSS` text,
  `protein_id` text,
  `description` text,
  KEY `idx_protein_id` (`protein_id`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `NVECT_Embryos_proteomics` (
  `Accession` text,
  `q_value` text,
  `Coverage` text,
  `Peptides` text,
  `PSMs` text,
  `UniquePeptides` text,
  `MW` text,
  `calcpI` text,
  `Protein` text,
  `Modifications` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `NVECT_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `NVECT_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `NVECT_TPM` (
  `Gene` text,
  `SRR6320836_whole_6w_aboral_regenerate_6w_96hpa` text,
  `SRR6320837_whole_6w_aboral_regenerate_6w_96hpa` text,
  `SRR6320838_whole_6w_aboral_regenerate_6w_144hpa` text,
  `SRR6320839_whole_6w_aboral_regenerate_6w_0hpa` text,
  `SRR6320840_whole_6w_aboral_regenerate_6w_0hpa` text,
  `SRR6320841_whole_6w_aboral_regenerate_6w_144hpa` text,
  `SRR6320842_whole_6w_aboral_regenerate_6w_2hpa` text,
  `SRR6320843_whole_6w_aboral_regenerate_6w_uncut` text,
  `SRR6320844_whole_6w_aboral_regenerate_6w_uncut` text,
  `SRR6320845_whole_6w_aboral_regenerate_6w_uncut` text,
  `SRR6320846_whole_6w_aboral_regenerate_6w_0hpa` text,
  `SRR6320847_whole_6w_aboral_regenerate_6w_144hpa` text,
  `SRR6320848_whole_6w_aboral_regenerate_6w_2hpa` text,
  `SRR6320849_whole_6w_aboral_regenerate_6w_4hpa` text,
  `SRR6320850_whole_6w_aboral_regenerate_6w_120hpa` text,
  `SRR6320851_whole_6w_aboral_regenerate_6w_36hpa` text,
  `SRR6320852_whole_6w_aboral_regenerate_6w_36hpa` text,
  `SRR6320853_whole_6w_aboral_regenerate_6w_24hpa` text,
  `SRR6320854_whole_6w_aboral_regenerate_6w_24hpa` text,
  `SRR6320855_whole_6w_aboral_regenerate_6w_36hpa` text,
  `SRR6320856_whole_6w_aboral_regenerate_6w_24hpa` text,
  `SRR6320857_whole_6w_aboral_regenerate_6w_20hpa` text,
  `SRR6320858_whole_6w_aboral_regenerate_6w_16hpa` text,
  `SRR6320859_whole_6w_aboral_regenerate_6w_20hpa` text,
  `SRR6320860_whole_6w_aboral_regenerate_6w_20hpa` text,
  `SRR6320861_whole_6w_aboral_regenerate_6w_2hpa` text,
  `SRR6320862_whole_6w_aboral_regenerate_6w_72hpa` text,
  `SRR6320863_whole_6w_aboral_regenerate_6w_96hpa` text,
  `SRR6320864_whole_6w_aboral_regenerate_6w_72hpa` text,
  `SRR6320865_whole_6w_aboral_regenerate_6w_72hpa` text,
  `SRR6320866_whole_6w_aboral_regenerate_6w_60hpa` text,
  `SRR6320867_whole_6w_aboral_regenerate_6w_60hpa` text,
  `SRR6320868_whole_6w_aboral_regenerate_6w_48hpa` text,
  `SRR6320869_whole_6w_aboral_regenerate_6w_60hpa` text,
  `SRR6320870_whole_6w_aboral_regenerate_6w_48hpa` text,
  `SRR6320871_whole_6w_aboral_regenerate_6w_48hpa` text,
  `SRR6320872_whole_6w_aboral_regenerate_6w_4hpa` text,
  `SRR6320873_whole_6w_aboral_regenerate_6w_4hpa` text,
  `SRR6320874_whole_6w_aboral_regenerate_6w_8hpa` text,
  `SRR6320875_whole_6w_aboral_regenerate_6w_8hpa` text,
  `SRR6320876_whole_6w_aboral_regenerate_6w_8hpa` text,
  `SRR6320877_whole_6w_aboral_regenerate_6w_12hpa` text,
  `SRR6320878_whole_6w_aboral_regenerate_6w_12hpa` text,
  `SRR6320879_whole_6w_aboral_regenerate_6w_12hpa` text,
  `SRR6320880_whole_6w_aboral_regenerate_6w_16hpa` text,
  `SRR6320881_whole_6w_aboral_regenerate_6w_16hpa` text,
  `SRR6320882_whole_6w_aboral_regenerate_6w_120hpa` text,
  `SRR6320883_whole_6w_aboral_regenerate_6w_120hpa` text,
  `avg` text,
  `atd` text,
  KEY `ix_gene` (`Gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `NVECT_cellmarker` (
  `tissue_dev` text,
  `pct1` text,
  `pct2` text,
  `log2FC` text,
  `FDR` text,
  `celltype` text,
  `gene` text,
  `symbol` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `NVECT_coexpress_negative` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `NVECT_coexpress_positive` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `NVECT_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `NVECT_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `NVECT_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `NVECT_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `NVECT_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `NVECT_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `NVECT_polyps_proteomics` (
  `Proteins` text,
  `Modifications` text,
  `Mass` text,
  `Mass_Fractional_Part` text,
  `Unique_Groups` text,
  `Unique_Proteins` text,
  `Acetyl_Protein` text,
  `Oxidation_M` text,
  `Missed_cleavages` text,
  `Q_value` text,
  `Score` text,
  `Intensity` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `NVECT_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `NVECT_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `OARBU_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `OARBU_cellmarker` (
  `tissue_dev` text,
  `pct1` text,
  `pct2` text,
  `log2FC` text,
  `FDR` text,
  `celltype` text,
  `gene` text,
  `symbol` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `OARBU_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `OARBU_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `OARBU_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `OARBU_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `OARBU_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `OARBU_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `OARBU_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `OARBU_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `OFAVE_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `OFAVE_TPM` (
  `Gene` text,
  `SRR22214401_holobiont_control_pH_Control_temp` text,
  `SRR22214472_holobiont_low_pH_high_temp` text,
  `SRR22214473_holobiont_low_pH_high_temp` text,
  `SRR22214474_holobiont_low_pH_high_temp` text,
  `SRR22214475_holobiont_low_pH_high_temp` text,
  `SRR22214476_holobiont_low_pH_high_temp` text,
  `SRR22214477_holobiont_low_pH_high_temp` text,
  `SRR22214478_holobiont_low_pH_high_temp` text,
  `SRR22214479_holobiont_low_pH_high_temp` text,
  `SRR22214480_holobiont_low_pH_high_temp` text,
  `SRR22214482_holobiont_low_pH_high_temp` text,
  `SRR22214483_holobiont_low_pH_high_temp` text,
  `SRR22214484_holobiont_low_pH_high_temp` text,
  `SRR22214485_holobiont_low_pH_high_temp` text,
  `SRR22214486_holobiont_low_pH_high_temp` text,
  `SRR22214487_holobiont_low_pH_high_temp` text,
  `SRR22214488_holobiont_low_pH_high_temp` text,
  `SRR22214489_holobiont_low_pH_high_temp` text,
  `SRR22214490_holobiont_low_pH_high_temp` text,
  `SRR22214491_holobiont_low_pH_high_temp` text,
  `SRR22214493_holobiont_low_pH_Control_temp` text,
  `SRR22214494_holobiont_low_pH_Control_temp` text,
  `SRR22214495_holobiont_low_pH_Control_temp` text,
  `SRR22214496_holobiont_control_pH_high_temp` text,
  `SRR22214497_holobiont_control_pH_high_temp` text,
  `SRR22214498_holobiont_control_pH_high_temp` text,
  `SRR22214499_holobiont_control_pH_high_temp` text,
  `SRR22214500_holobiont_control_pH_high_temp` text,
  `SRR22214501_holobiont_control_pH_high_temp` text,
  `SRR22214502_holobiont_control_pH_high_temp` text,
  `SRR22214503_holobiont_control_pH_Control_temp` text,
  `SRR22214504_holobiont_control_pH_Control_temp` text,
  `SRR22214505_holobiont_control_pH_Control_temp` text,
  `SRR22214507_holobiont_control_pH_Control_temp` text,
  `SRR22214508_holobiont_control_pH_Control_temp` text,
  `SRR22214509_holobiont_control_pH_Control_temp` text,
  `SRR22214510_holobiont_control_pH_Control_temp` text,
  `SRR22214511_holobiont_control_pH_Control_temp` text,
  `SRR22214512_holobiont_control_pH_Control_temp` text,
  `SRR22214513_holobiont_control_pH_Control_temp` text,
  `SRR22214514_holobiont_control_pH_Control_temp` text,
  `SRR22214515_holobiont_control_pH_Control_temp` text,
  `SRR22214516_holobiont_control_pH_Control_temp` text,
  `SRR22214517_holobiont_low_pH_Control_temp` text,
  `SRR22214518_holobiont_low_pH_Control_temp` text,
  `SRR22214519_holobiont_low_pH_Control_temp` text,
  `SRR22214520_holobiont_low_pH_Control_temp` text,
  `SRR22214521_holobiont_low_pH_Control_temp` text,
  `SRR22214522_holobiont_low_pH_Control_temp` text,
  `SRR22214523_holobiont_low_pH_Control_temp` text,
  `SRR22214525_holobiont_low_pH_Control_temp` text,
  `SRR22214526_holobiont_low_pH_Control_temp` text,
  `SRR22214527_holobiont_low_pH_Control_temp` text,
  `SRR22214528_holobiont_low_pH_Control_temp` text,
  `SRR22214529_holobiont_low_pH_Control_temp` text,
  `SRR22214530_holobiont_low_pH_Control_temp` text,
  `SRR22214531_holobiont_control_pH_high_temp` text,
  `SRR22214532_holobiont_control_pH_high_temp` text,
  `SRR22214533_holobiont_control_pH_high_temp` text,
  `SRR22214534_holobiont_control_pH_high_temp` text,
  `SRR22214536_holobiont_control_pH_high_temp` text,
  `SRR22214537_holobiont_control_pH_high_temp` text,
  `SRR22214538_holobiont_control_pH_high_temp` text,
  `SRR22214539_holobiont_control_pH_high_temp` text,
  `SRR22214540_holobiont_control_pH_high_temp` text,
  `SRR22214541_holobiont_control_pH_high_temp` text,
  `SRR22214542_holobiont_control_pH_high_temp` text,
  `SRR22214543_holobiont_control_pH_high_temp` text,
  `SRR22214544_holobiont_control_pH_high_temp` text,
  `SRR22214545_holobiont_control_pH_high_temp` text,
  `avg` text,
  `std` text,
  KEY `ix_gene` (`Gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `OFAVE_coexpress_negative` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `OFAVE_coexpress_positive` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `OFAVE_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `OFAVE_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `OFAVE_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `OFAVE_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `OFAVE_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `OFAVE_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `OFAVE_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `OFAVE_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `OFRAN_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `OFRAN_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `OFRAN_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `OFRAN_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `OFRAN_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `OFRAN_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `OFRAN_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `OFRAN_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `OFRAN_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `OPATA_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `OPATA_cellmarker` (
  `tissue_dev` text,
  `pct1` text,
  `pct2` text,
  `log2FC` text,
  `FDR` text,
  `celltype` text,
  `gene` text,
  `symbol` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `OPATA_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `OPATA_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `OPATA_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `OPATA_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `OPATA_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `OPATA_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `OPATA_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `OPATA_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PACUT_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PACUT_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PACUT_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PACUT_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PACUT_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PACUT_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PACUT_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PACUT_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PACUT_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PAUST_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PAUST_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PAUST_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PAUST_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PAUST_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PAUST_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PAUST_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PCLAV_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PCLAV_TPM` (
  `Gene` text,
  `SRR19977425_apical_branchlet_Temperature_treatment_at_T25` text,
  `SRR19977426_apical_branchlet_Temperature_treatment_at_T0` text,
  `SRR19977427_apical_branchlet_Temperature_treatment_at_T0` text,
  `SRR19977428_apical_branchlet_Temperature_treatment_at_T0` text,
  `SRR19977432_apical_branchlet_Temperature_treatment_at_T25` text,
  `SRR19977433_apical_branchlet_Control_at_T25` text,
  `SRR19977434_apical_branchlet_Temperature_treatment_at_T25` text,
  `SRR19977435_apical_branchlet_Temperature_treatment_at_T25` text,
  `SRR19977436_apical_branchlet_Temperature_treatment_at_T0` text,
  `SRR19977437_apical_branchlet_Temperature_treatment_at_T0` text,
  `SRR19977438_apical_branchlet_Temperature_treatment_at_T0` text,
  `SRR19977439_apical_branchlet_Control_at_T25` text,
  `SRR19977440_apical_branchlet_Control_at_T25` text,
  `SRR19977441_apical_branchlet_Control_at_T25` text,
  `SRR19977442_apical_branchlet_Control_at_T0` text,
  `SRR19977443_apical_branchlet_Control_at_T0` text,
  `SRR19977444_apical_branchlet_Control_at_T25` text,
  `SRR19977445_apical_branchlet_Control_at_T0` text,
  `SRR19977446_apical_branchlet_Control_at_T0` text,
  `SRR19977455_apical_branchlet_Control_at_T25` text,
  `SRR19977463_apical_branchlet_Temperature_treatment_at_T25` text,
  `avg` text,
  `std` text,
  KEY `ix_gene` (`Gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PCLAV_coexpress_negative` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PCLAV_coexpress_positive` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PCLAV_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PCLAV_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PCLAV_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PCLAV_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PCLAV_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PCLAV_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PCLAV_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PCLAV_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PCOMP_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PCOMP_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PCOMP_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PCOMP_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PCOMP_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PCOMP_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PCOMP_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PCRUS_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PCRUS_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PCRUS_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PCRUS_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PCRUS_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PCRUS_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PCRUS_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PCYLI_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PCYLI_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PCYLI_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PCYLI_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PCYLI_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PCYLI_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PCYLI_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PDAMI_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PDAMI_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PDAMI_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PDAMI_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PDAMI_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PDAMI_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PDAMI_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PDAMI_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PDIVA_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PDIVA_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PDIVA_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PDIVA_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PDIVA_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PDIVA_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PDIVA_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PEVER_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PEVER_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PEVER_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PEVER_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PEVER_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PEVER_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PEVER_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PGRIS_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PGRIS_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PGRIS_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PGRIS_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PGRIS_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PGRIS_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PGRIS_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PHARR_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PHARR_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PHARR_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PHARR_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PHARR_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PHARR_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PHARR_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PLOBA_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PLOBA_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PLOBA_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PLOBA_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PLOBA_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PLOBA_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PLOBA_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PLUTE_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PLUTE_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PLUTE_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PLUTE_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PLUTE_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PLUTE_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PLUTE_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PMEAN_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PMEAN_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PMEAN_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PMEAN_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PMEAN_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PMEAN_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PMEAN_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PMIZI_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PMIZI_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PMIZI_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PMIZI_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PMIZI_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PMIZI_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PMIZI_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PMIZI_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PMIZI_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PNOCT_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PNOCT_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PNOCT_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PNOCT_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PNOCT_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PNOCT_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PNOCT_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PNOCT_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PNOCT_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PPAPI_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PPAPI_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PPAPI_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PPAPI_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PPAPI_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PPAPI_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PPAPI_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PPAPI_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PPAPI_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PPENN_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PPENN_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PPENN_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PPENN_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PPENN_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PPENN_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PPENN_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PPENN_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PPENN_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PRUS_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PRUS_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PRUS_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PRUS_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PRUS_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PRUS_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PRUS_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PSINE1_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PSINE1_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PSINE1_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PSINE1_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PSINE1_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PSINE1_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PSINE1_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PSINE2_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PSINE2_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PSINE2_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PSINE2_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PSINE2_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PSINE2_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PSINE2_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PSINE_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PSINE_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PSPEC_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PSPEC_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PSPEC_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PSPEC_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PSPEC_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PSPEC_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PSPEC_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PSPEC_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PSPEC_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PUMBR_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PUMBR_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PUMBR_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PUMBR_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PUMBR_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PUMBR_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PUMBR_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PUMBR_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PUMBR_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PVERR_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PVERR_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PVERR_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PVERR_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PVERR_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PVERR_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PVERR_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PXISH_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PXISH_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PXISH_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PXISH_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PXISH_nr` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PXISH_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PXISH_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PXISH_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PXISH_uniprot` (
  `gene` text,
  `ID` text,
  `description` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Polypodium_hydriforme_stolon_proteomics` (
  `Protein` text,
  `lgP` text,
  `Coverage` text,
  `Coverage_Sample1` text,
  `Area_Sample1` text,
  `Peptides` text,
  `Unique1` text,
  `Spec_Sample11` text,
  `PTM` text,
  `Avg_Mass` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `RESCU_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `RESCU_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `RESCU_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `RESCU_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `RESCU_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `RESCU_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `RESCU_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `RFLOR_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `RFLOR_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `RFLOR_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `RFLOR_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `RFLOR_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `RFLOR_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `RFLOR_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ROSCU_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ROSCU_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ROSCU_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ROSCU_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ROSCU_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ROSCU_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ROSCU_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SCALL_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SCALL_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SCALL_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SCALL_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SCALL_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SCALL_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SCALL_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SINTE_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SINTE_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SINTE_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SINTE_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SINTE_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SINTE_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SINTE_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SMALA_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SMALA_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SMALA_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SMALA_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SMALA_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SMALA_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SMALA_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SMALA_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SPIST_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SPIST_cellmarker` (
  `tissue_dev` text,
  `pct1` text,
  `pct2` text,
  `log2FC` text,
  `FDR` text,
  `celltype` text,
  `gene` text,
  `symbol` text,
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SPIST_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SPIST_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SPIST_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SPIST_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SPIST_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SPIST_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SRADI_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SRADI_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SRADI_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SRADI_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SRADI_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SRADI_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SRADI_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SSIDE_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SSIDE_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SSIDE_TPM` (
  `Gene` text,
  `ERR12861049` text,
  `SRR12454619_live_coral_tissue_skeleton` text,
  `SRR14295600_Whole_organism` text,
  `SRR14295601_Whole_organism` text,
  `SRR14295603_Whole_organism` text,
  `SRR14295604_Whole_organism` text,
  `SRR22214400_holobiont_control_pH_Control_temp` text,
  `SRR22214402_holobiont_low_pH_high_temp` text,
  `SRR22214403_holobiont_low_pH_high_temp` text,
  `SRR22214404_holobiont_low_pH_high_temp` text,
  `SRR22214405_holobiont_low_pH_high_temp` text,
  `SRR22214406_holobiont_low_pH_high_temp` text,
  `SRR22214407_holobiont_low_pH_high_temp` text,
  `SRR22214408_holobiont_low_pH_high_temp` text,
  `SRR22214409_holobiont_low_pH_high_temp` text,
  `SRR22214410_holobiont_low_pH_high_temp` text,
  `SRR22214411_holobiont_control_pH_Control_temp` text,
  `SRR22214412_holobiont_low_pH_high_temp` text,
  `SRR22214413_holobiont_low_pH_high_temp` text,
  `SRR22214414_holobiont_low_pH_high_temp` text,
  `SRR22214415_holobiont_low_pH_high_temp` text,
  `SRR22214416_holobiont_low_pH_high_temp` text,
  `SRR22214417_holobiont_low_pH_high_temp` text,
  `SRR22214418_holobiont_low_pH_high_temp` text,
  `SRR22214419_holobiont_low_pH_high_temp` text,
  `SRR22214420_holobiont_low_pH_high_temp` text,
  `SRR22214421_holobiont_low_pH_high_temp` text,
  `SRR22214422_holobiont_control_pH_Control_temp` text,
  `SRR22214423_holobiont_low_pH_high_temp` text,
  `SRR22214424_holobiont_low_pH_Control_temp` text,
  `SRR22214425_holobiont_low_pH_Control_temp` text,
  `SRR22214426_holobiont_low_pH_Control_temp` text,
  `SRR22214427_holobiont_low_pH_Control_temp` text,
  `SRR22214428_holobiont_low_pH_Control_temp` text,
  `SRR22214429_holobiont_low_pH_Control_temp` text,
  `SRR22214430_holobiont_low_pH_Control_temp` text,
  `SRR22214431_holobiont_low_pH_Control_temp` text,
  `SRR22214432_holobiont_low_pH_Control_temp` text,
  `SRR22214433_holobiont_control_pH_Control_temp` text,
  `SRR22214434_holobiont_low_pH_Control_temp` text,
  `SRR22214435_holobiont_low_pH_Control_temp` text,
  `SRR22214436_holobiont_low_pH_Control_temp` text,
  `SRR22214437_holobiont_low_pH_Control_temp` text,
  `SRR22214438_holobiont_low_pH_Control_temp` text,
  `SRR22214439_holobiont_low_pH_Control_temp` text,
  `SRR22214440_holobiont_low_pH_Control_temp` text,
  `SRR22214441_holobiont_low_pH_Control_temp` text,
  `SRR22214442_holobiont_low_pH_Control_temp` text,
  `SRR22214443_holobiont_low_pH_Control_temp` text,
  `SRR22214444_holobiont_control_pH_Control_temp` text,
  `SRR22214445_holobiont_low_pH_Control_temp` text,
  `SRR22214446_holobiont_control_pH_high_temp` text,
  `SRR22214447_holobiont_control_pH_high_temp` text,
  `SRR22214448_holobiont_control_pH_high_temp` text,
  `SRR22214449_holobiont_control_pH_high_temp` text,
  `SRR22214450_holobiont_control_pH_high_temp` text,
  `SRR22214451_holobiont_control_pH_high_temp` text,
  `SRR22214452_holobiont_control_pH_high_temp` text,
  `SRR22214453_holobiont_control_pH_high_temp` text,
  `SRR22214454_holobiont_control_pH_high_temp` text,
  `SRR22214455_holobiont_control_pH_Control_temp` text,
  `SRR22214456_holobiont_control_pH_high_temp` text,
  `SRR22214457_holobiont_control_pH_high_temp` text,
  `SRR22214458_holobiont_control_pH_high_temp` text,
  `SRR22214459_holobiont_control_pH_high_temp` text,
  `SRR22214460_holobiont_control_pH_high_temp` text,
  `SRR22214461_holobiont_control_pH_high_temp` text,
  `SRR22214462_holobiont_control_pH_high_temp` text,
  `SRR22214463_holobiont_control_pH_high_temp` text,
  `SRR22214464_holobiont_control_pH_high_temp` text,
  `SRR22214465_holobiont_control_pH_high_temp` text,
  `SRR22214466_holobiont_control_pH_Control_temp` text,
  `SRR22214467_holobiont_control_pH_high_temp` text,
  `SRR22214468_holobiont_control_pH_Control_temp` text,
  `SRR22214469_holobiont_control_pH_Control_temp` text,
  `SRR22214470_holobiont_control_pH_Control_temp` text,
  `SRR22214471_holobiont_control_pH_Control_temp` text,
  `SRR22214481_holobiont_control_pH_Control_temp` text,
  `SRR22214492_holobiont_control_pH_Control_temp` text,
  `SRR22214506_holobiont_control_pH_Control_temp` text,
  `SRR22214524_holobiont_control_pH_Control_temp` text,
  `SRR22214535_holobiont_control_pH_Control_temp` text,
  `SRR22214546_holobiont_control_pH_Control_temp` text,
  `SRR22214547_holobiont_control_pH_Control_temp` text,
  `SRR22214548_holobiont_control_pH_Control_temp` text,
  `avg` text,
  `atd` text,
  KEY `ix_gene` (`Gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SSIDE_coexpress_negative` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SSIDE_coexpress_positive` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SSIDE_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SSIDE_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SSIDE_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SSIDE_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SSIDE_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SSIDE_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Stichopathes_sp_Coral_skeleton_proteomics` (
  `Description` text,
  `Log_Prob` text,
  `Best_Log_Prob` text,
  `Best_score` text,
  `Total_Intensity` text,
  `spectra` text,
  `unique_peptides` text,
  `mod_peptides` text,
  `Coverage` text,
  `AA_in_protein` text,
  `Protein_DB_number` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Stylophora_pistillata_colony_proteomics` (
  `Description` text,
  `Log_Prob` text,
  `Best_Log_Prob` text,
  `Best_score` text,
  `Total_Intensity` text,
  `spectra` text,
  `unique_peptides` text,
  `mod_peptides` text,
  `Coverage` text,
  `AA_in_protein` text,
  `Protein_DB_number` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TCOCC_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TCOCC_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TCOCC_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TCOCC_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TCOCC_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TCOCC_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TCOCC_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TDOHR_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TDOHR_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TDOHR_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TDOHR_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TDOHR_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TDOHR_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TDOHR_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TE_publish_log` (
  `table_name` varchar(64) NOT NULL,
  `species` varchar(255) NOT NULL,
  `rows_loaded` bigint unsigned NOT NULL,
  `src_size` bigint unsigned NOT NULL,
  `src_mtime` bigint unsigned NOT NULL,
  `dataset` varchar(32) NOT NULL,
  `published_at` datetime NOT NULL,
  PRIMARY KEY (`table_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TKITA_Cysts_proteomics` (
  `Protein` text,
  `Proteins` text,
  `Peptides` text,
  `Uniquepeptides` text,
  `coverage` text,
  `Molweight` text,
  `Q_value` text,
  `Intensity` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TKITA_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TKITA_Nematocysts_proteomics` (
  `Protein` text,
  `Proteins` text,
  `Peptides` text,
  `Uniquepeptides` text,
  `coverage` text,
  `Molweight` text,
  `Q_value` text,
  `Intensity` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TKITA_TE` (
  `species` text,
  `TE_id` text,
  `scaffold` text,
  `TE_start` text,
  `TE_end` text,
  `related_gene` text,
  `region` text,
  `TE_type` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TKITA_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`GO_term`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TKITA_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`InterPro_term`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TKITA_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TKITA_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TKITA_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TKITA_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TMAIP_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TMAIP_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`GO_term`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TMAIP_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`InterPro_term`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TMAIP_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TMAIP_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TMAIP_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TMAIP_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TRENI_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TRENI_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TRENI_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TRENI_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TRENI_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TRENI_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TRENI_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TRUBR_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TRUBR_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TRUBR_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TRUBR_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TRUBR_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TRUBR_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TRUBR_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TSP_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TSP_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`GO_term`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TSP_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`InterPro_term`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TSP_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TSP_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TSP_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TSP_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TSTEP_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TSTEP_TPM` (
  `Gene` text,
  `SRR14511800_Mesentery` text,
  `SRR14511801_Mesentery` text,
  `SRR14511802_Club_tips` text,
  `SRR14511803_Club_tips` text,
  `SRR14511804_Mesentery` text,
  `SRR14511805_Actinopharynx` text,
  `SRR14511806_Actinopharynx` text,
  `SRR14511807_Actinopharynx` text,
  `SRR14511808_Tentacles` text,
  `SRR14511809_Tentacles` text,
  `SRR14511810_Tentacles` text,
  `SRR14511811_Club_tips` text,
  `SRR14511812_Pedal_disc` text,
  `SRR14511813_Pedal_disc` text,
  `SRR14511814_Pedal_disc` text,
  `SRR14511815_Body_column` text,
  `SRR14511816_Body_column` text,
  `SRR14511817_Body_column` text,
  `avg` text,
  `std` text,
  KEY `ix_gene` (`Gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TSTEP_coexpress_negative` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TSTEP_coexpress_positive` (
  `geneA` text,
  `geneB` text,
  `pcc` text,
  `mr` text,
  `level` text,
  `rankB` text,
  `rankA` text,
  KEY `ix_geneA` (`geneA`(64)),
  KEY `ix_geneB` (`geneB`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TSTEP_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TSTEP_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TSTEP_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TSTEP_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TSTEP_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TSTEP_seq` (
  `gene` text,
  `cds_seq` longtext,
  `transcript` text,
  `transcript_seq` longtext,
  `protein` text,
  `protein_seq` longtext,
  KEY `ix_gene` (`gene`(48)),
  KEY `ix_protein` (`protein`(48)),
  KEY `ix_transcript` (`transcript`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `XSP_KEGG` (
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`KO`(48)),
  KEY `ix_pathway` (`Pathway_ID`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `XSP_go` (
  `gene` text,
  `GO_term` text,
  `Category` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`GO_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `XSP_ipr` (
  `gene` text,
  `InterPro_term` text,
  `Type` text,
  `Description` text,
  `Source` text,
  `URL` text,
  KEY `ix_term` (`InterPro_term`(48)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `XSP_locus` (
  `mRNA` text,
  `gene` text,
  `assembly` text,
  `start` text,
  `end` text,
  `strand` text,
  KEY `ix_mrna` (`mRNA`(64)),
  KEY `ix_gene` (`gene`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `XSP_panther` (
  `gene` text,
  `id` text,
  `anno` text,
  `method` text,
  `url` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`id`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `XSP_pfam` (
  `gene` text,
  `Pfam_accession` text,
  `Pfam_name` text,
  `Description` text,
  `Type` text,
  `Source` text,
  `URL` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_term` (`Pfam_accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `_amedi_keep` (
  `prot` varchar(40) DEFAULT NULL,
  `gene` varchar(40) DEFAULT NULL,
  `tx` varchar(40) DEFAULT NULL,
  KEY `prot` (`prot`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `_dedup_map` (
  `sp` varchar(8) DEFAULT NULL,
  `kind` varchar(6) DEFAULT NULL,
  `old` varchar(80) DEFAULT NULL,
  `new` varchar(80) DEFAULT NULL,
  KEY `k1` (`sp`,`kind`,`old`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `_l2bmap` (
  `sp` varchar(8) DEFAULT NULL,
  `lg` varchar(80) DEFAULT NULL,
  `br` varchar(80) DEFAULT NULL,
  KEY `k1` (`sp`,`lg`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `_m` (
  `lg` varchar(80) DEFAULT NULL,
  `br` varchar(80) DEFAULT NULL,
  KEY `lg` (`lg`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `abbr` (
  `species` text,
  `abbr` text,
  `abbr1` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `busco` (
  `BUSCO_ID` text,
  `Status` text,
  `Score` text,
  `Length` text,
  `gene` text,
  `abbr` text,
  `Description` text,
  KEY `ix_gene` (`gene`(64)),
  KEY `ix_abbr` (`abbr`(64)),
  KEY `ix_abbr_busco_status` (`abbr`(64),`BUSCO_ID`(64),`Status`(16))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `busco_assembly` (
  `abbr1` varchar(64) NOT NULL,
  `species` varchar(190) NOT NULL DEFAULT '',
  `accession` varchar(64) NOT NULL DEFAULT '',
  `lineage` varchar(64) NOT NULL,
  `lineage_size` int NOT NULL,
  `n_buscos` int NOT NULL,
  `n_single` int NOT NULL,
  `n_duplicated` int NOT NULL,
  `n_fragmented` int NOT NULL,
  `n_missing` int NOT NULL,
  `pct_complete` decimal(5,2) NOT NULL,
  `pct_single` decimal(5,2) NOT NULL,
  `pct_duplicated` decimal(5,2) NOT NULL,
  `pct_fragmented` decimal(5,2) NOT NULL,
  `pct_missing` decimal(5,2) NOT NULL,
  `high_quality` tinyint NOT NULL DEFAULT '0',
  `input_file` varchar(255) NOT NULL DEFAULT '',
  `run_date` date DEFAULT NULL,
  PRIMARY KEY (`abbr1`,`lineage`),
  KEY `species` (`species`),
  KEY `lineage` (`lineage`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='BUSCO genome mode on the assembly (the accession in genome_assembly), one row per species x lineage; written by cnidosite-work/busco/assembly/collect_genome.py';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `busco_summary` (
  `abbr1` varchar(64) NOT NULL,
  `species` varchar(190) NOT NULL DEFAULT '',
  `lineage` varchar(64) NOT NULL DEFAULT '',
  `lineage_size` int NOT NULL,
  `n_buscos` int NOT NULL,
  `n_single` int NOT NULL,
  `n_duplicated` int NOT NULL,
  `n_fragmented` int NOT NULL,
  `n_missing` int NOT NULL,
  `pct_complete` decimal(5,2) NOT NULL,
  `pct_single` decimal(5,2) NOT NULL,
  `pct_duplicated` decimal(5,2) NOT NULL,
  `pct_fragmented` decimal(5,2) NOT NULL,
  `pct_missing` decimal(5,2) NOT NULL,
  `high_quality` tinyint NOT NULL DEFAULT '0',
  `ambiguous` tinyint NOT NULL DEFAULT '0',
  `protein_set` tinyint NOT NULL DEFAULT '1' COMMENT '1 = scored on a deposited protein sequence set; 0 = genes predicted during the assessment (BUSCO genome mode), not comparable with the rest',
  PRIMARY KEY (`abbr1`),
  KEY `species` (`species`),
  KEY `high_quality` (`high_quality`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `classfy` (
  `Phylum` text,
  `Class` text,
  `order1` text,
  `Family` text,
  `Genus` text,
  `Species` text,
  `NCBI` text,
  `Worms` text,
  `GBIF` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `core_member` (
  `og` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abbr` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gene` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `n_copies` int NOT NULL DEFAULT '0',
  `is_single` tinyint NOT NULL DEFAULT '0',
  KEY `idx_og` (`og`),
  KEY `idx_abbr` (`abbr`),
  KEY `idx_abbr_gene` (`abbr`,`gene`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `core_member_prev` (
  `og` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abbr` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gene` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `n_copies` int NOT NULL DEFAULT '0',
  `is_single` tinyint NOT NULL DEFAULT '0',
  KEY `idx_og` (`og`),
  KEY `idx_abbr` (`abbr`),
  KEY `idx_abbr_gene` (`abbr`,`gene`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `core_og` (
  `og` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tiers` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `hq90_n` int NOT NULL DEFAULT '0',
  `hq90_present` int NOT NULL DEFAULT '0',
  `hq90_single` int NOT NULL DEFAULT '0',
  `hq90_occ` decimal(6,4) NOT NULL DEFAULT '0.0000',
  `hq90_sc` decimal(6,4) NOT NULL DEFAULT '0.0000',
  `all_n` int NOT NULL DEFAULT '0',
  `all_present` int NOT NULL DEFAULT '0',
  `all_single` int NOT NULL DEFAULT '0',
  `all_occ` decimal(6,4) NOT NULL DEFAULT '0.0000',
  `all_sc` decimal(6,4) NOT NULL DEFAULT '0.0000',
  `outgroup_present` tinyint NOT NULL DEFAULT '0',
  `outgroup_single` tinyint NOT NULL DEFAULT '0',
  `n_members` int NOT NULL DEFAULT '0',
  `best_source` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `best_term` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `best_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `best_desc` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `best_cat` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `best_support` int NOT NULL DEFAULT '0',
  `best_tier` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `search_text` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`og`),
  KEY `idx_tiers` (`tiers`),
  KEY `idx_hq90sc` (`hq90_sc`),
  FULLTEXT KEY `ft_search` (`search_text`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `core_og_prev` (
  `og` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tiers` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `hq90_n` int NOT NULL DEFAULT '0',
  `hq90_present` int NOT NULL DEFAULT '0',
  `hq90_single` int NOT NULL DEFAULT '0',
  `hq90_occ` decimal(6,4) NOT NULL DEFAULT '0.0000',
  `hq90_sc` decimal(6,4) NOT NULL DEFAULT '0.0000',
  `all_n` int NOT NULL DEFAULT '0',
  `all_present` int NOT NULL DEFAULT '0',
  `all_single` int NOT NULL DEFAULT '0',
  `all_occ` decimal(6,4) NOT NULL DEFAULT '0.0000',
  `all_sc` decimal(6,4) NOT NULL DEFAULT '0.0000',
  `outgroup_present` tinyint NOT NULL DEFAULT '0',
  `outgroup_single` tinyint NOT NULL DEFAULT '0',
  `n_members` int NOT NULL DEFAULT '0',
  `best_source` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `best_term` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `best_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `best_desc` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `best_cat` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `best_support` int NOT NULL DEFAULT '0',
  `best_tier` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `search_text` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`og`),
  KEY `idx_tiers` (`tiers`),
  KEY `idx_hq90sc` (`hq90_sc`),
  FULLTEXT KEY `ft_search` (`search_text`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `core_species` (
  `abbr1` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `of_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `latin` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `phylum` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `class` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `order1` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `family` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `genus` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ncbi` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `cnidarian` tinyint NOT NULL DEFAULT '0',
  `busco90` tinyint NOT NULL DEFAULT '0',
  `n_buscos` int NOT NULL DEFAULT '0',
  `n_single` int NOT NULL DEFAULT '0',
  `n_duplicated` int NOT NULL DEFAULT '0',
  `n_fragmented` int NOT NULL DEFAULT '0',
  `n_missing` int NOT NULL DEFAULT '0',
  `pct_complete` decimal(5,2) NOT NULL DEFAULT '0.00',
  `pct_single` decimal(5,2) NOT NULL DEFAULT '0.00',
  `pct_duplicated` decimal(5,2) NOT NULL DEFAULT '0.00',
  `n_core_og` int NOT NULL DEFAULT '0',
  `n_core_single` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`abbr1`),
  KEY `idx_busco90` (`busco90`),
  KEY `idx_class` (`class`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `core_species_prev` (
  `abbr1` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL,
  `of_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `latin` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `phylum` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `class` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `order1` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `family` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `genus` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ncbi` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `cnidarian` tinyint NOT NULL DEFAULT '0',
  `high_quality` tinyint NOT NULL DEFAULT '0',
  `n_buscos` int NOT NULL DEFAULT '0',
  `n_single` int NOT NULL DEFAULT '0',
  `n_duplicated` int NOT NULL DEFAULT '0',
  `n_fragmented` int NOT NULL DEFAULT '0',
  `n_missing` int NOT NULL DEFAULT '0',
  `pct_complete` decimal(5,2) NOT NULL DEFAULT '0.00',
  `pct_single` decimal(5,2) NOT NULL DEFAULT '0.00',
  `pct_duplicated` decimal(5,2) NOT NULL DEFAULT '0.00',
  `n_core_og` int NOT NULL DEFAULT '0',
  `n_core_single` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`abbr1`),
  KEY `idx_hq` (`high_quality`),
  KEY `idx_class` (`class`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `description` (
  `species` text,
  `abbr` text,
  `png` text,
  `description` longtext
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ds_vocab` (
  `db` varchar(16) NOT NULL,
  `col` varchar(32) NOT NULL,
  `value` varchar(512) NOT NULL,
  `abbrs` text NOT NULL,
  PRIMARY KEY (`db`,`col`,`value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `epigenome` (
  `Class` text,
  `Species` text,
  `Type` text,
  `Project` text,
  `Study` text,
  `Experiment` text,
  `Run` text,
  `tissue` text,
  `dev` text,
  `Treatment` text,
  `Description` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gene_literature` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `abbr1` varchar(64) NOT NULL,
  `species` varchar(160) DEFAULT NULL,
  `taxid` varchar(16) DEFAULT NULL,
  `gene_id` varchar(20) NOT NULL,
  `symbol` varchar(120) DEFAULT NULL,
  `locus_tag` varchar(120) DEFAULT NULL,
  `site_gene` varchar(120) DEFAULT NULL,
  `site_name` varchar(160) DEFAULT NULL,
  `site_mrna` varchar(64) DEFAULT NULL,
  `map_source` varchar(20) DEFAULT NULL,
  `pmid` varchar(12) NOT NULL,
  `is_annotation_method` tinyint NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `ix_site_gene` (`site_gene`(48)),
  KEY `ix_symbol` (`symbol`(48)),
  KEY `ix_locus_tag` (`locus_tag`(48)),
  KEY `ix_gid` (`gene_id`),
  KEY `ix_pmid` (`pmid`),
  KEY `ix_abbr1` (`abbr1`(24)),
  KEY `ix_ann` (`is_annotation_method`),
  KEY `ix_ann_id` (`is_annotation_method`,`id`),
  KEY `ix_abbr_ann` (`abbr1`(24),`is_annotation_method`,`id`),
  KEY `ix_site_name` (`site_name`(48)),
  KEY `ix_site_mrna` (`site_mrna`(32))
) ENGINE=InnoDB AUTO_INCREMENT=607918 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gene_literature_prev` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `abbr1` varchar(64) NOT NULL,
  `species` varchar(160) DEFAULT NULL,
  `taxid` varchar(16) DEFAULT NULL,
  `gene_id` varchar(20) NOT NULL,
  `symbol` varchar(120) DEFAULT NULL,
  `locus_tag` varchar(120) DEFAULT NULL,
  `site_gene` varchar(120) DEFAULT NULL,
  `map_source` varchar(20) DEFAULT NULL,
  `pmid` varchar(12) NOT NULL,
  `is_annotation_method` tinyint NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `ix_site_gene` (`site_gene`(48)),
  KEY `ix_symbol` (`symbol`(48)),
  KEY `ix_locus_tag` (`locus_tag`(48)),
  KEY `ix_gid` (`gene_id`),
  KEY `ix_pmid` (`pmid`),
  KEY `ix_abbr1` (`abbr1`(24)),
  KEY `ix_ann` (`is_annotation_method`),
  KEY `ix_ann_id` (`is_annotation_method`,`id`),
  KEY `ix_abbr_ann` (`abbr1`(24),`is_annotation_method`,`id`)
) ENGINE=InnoDB AUTO_INCREMENT=607918 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `genefamily` (
  `family` text,
  `abbr` text,
  `gene` text,
  `Nr_proteins` text,
  `anno` text,
  KEY `ix_gene_abbr` (`gene`(100),`abbr`(16)),
  KEY `ix_family` (`family`(16))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `genefamily_num` (
  `genefamily` text,
  `number` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `genome_assembly` (
  `abbr1` varchar(64) NOT NULL,
  `abbr` varchar(96) DEFAULT NULL,
  `species` varchar(160) DEFAULT NULL,
  `taxid` varchar(16) DEFAULT NULL,
  `accession` varchar(32) NOT NULL,
  `current_accession` varchar(32) DEFAULT NULL,
  `source_database` varchar(16) DEFAULT NULL,
  `organism_name` varchar(200) DEFAULT NULL,
  `assembly_name` varchar(255) DEFAULT NULL,
  `assembly_level` varchar(24) DEFAULT NULL,
  `assembly_status` varchar(24) DEFAULT NULL,
  `assembly_type` varchar(40) DEFAULT NULL,
  `diploid_role` varchar(40) DEFAULT NULL,
  `refseq_category` varchar(40) DEFAULT NULL,
  `assembly_method` varchar(255) DEFAULT NULL,
  `sequencing_tech` varchar(255) DEFAULT NULL,
  `total_sequence_length` bigint unsigned DEFAULT NULL,
  `total_ungapped_length` bigint unsigned DEFAULT NULL,
  `number_of_contigs` int unsigned DEFAULT NULL,
  `number_of_scaffolds` int unsigned DEFAULT NULL,
  `number_of_component_sequences` int unsigned DEFAULT NULL,
  `total_number_of_chromosomes` int unsigned DEFAULT NULL,
  `contig_n50` bigint unsigned DEFAULT NULL,
  `scaffold_n50` bigint unsigned DEFAULT NULL,
  `gc_percent` decimal(5,2) DEFAULT NULL,
  `genome_coverage` decimal(8,2) DEFAULT NULL,
  `bioproject_accession` varchar(24) DEFAULT NULL,
  `release_date` date DEFAULT NULL,
  `submitter` varchar(255) DEFAULT NULL,
  `n_organelles` int unsigned DEFAULT NULL,
  `is_site_used` tinyint NOT NULL DEFAULT '0',
  `site_source` varchar(16) DEFAULT NULL,
  `fetched_at` datetime DEFAULT NULL,
  PRIMARY KEY (`abbr1`,`accession`),
  KEY `ix_acc` (`accession`),
  KEY `ix_site` (`is_site_used`),
  KEY `ix_tax` (`taxid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `genome_assembly_prev` (
  `abbr1` varchar(24) NOT NULL,
  `abbr` varchar(96) DEFAULT NULL,
  `species` varchar(160) DEFAULT NULL,
  `taxid` varchar(16) DEFAULT NULL,
  `accession` varchar(32) NOT NULL,
  `current_accession` varchar(32) DEFAULT NULL,
  `source_database` varchar(16) DEFAULT NULL,
  `organism_name` varchar(160) DEFAULT NULL,
  `assembly_name` varchar(200) DEFAULT NULL,
  `assembly_level` varchar(24) DEFAULT NULL,
  `assembly_status` varchar(24) DEFAULT NULL,
  `assembly_type` varchar(40) DEFAULT NULL,
  `diploid_role` varchar(40) DEFAULT NULL,
  `refseq_category` varchar(40) DEFAULT NULL,
  `assembly_method` varchar(200) DEFAULT NULL,
  `sequencing_tech` varchar(200) DEFAULT NULL,
  `total_sequence_length` bigint unsigned DEFAULT NULL,
  `total_ungapped_length` bigint unsigned DEFAULT NULL,
  `number_of_contigs` int unsigned DEFAULT NULL,
  `number_of_scaffolds` int unsigned DEFAULT NULL,
  `number_of_component_sequences` int unsigned DEFAULT NULL,
  `total_number_of_chromosomes` int unsigned DEFAULT NULL,
  `contig_n50` bigint unsigned DEFAULT NULL,
  `scaffold_n50` bigint unsigned DEFAULT NULL,
  `gc_percent` decimal(5,2) DEFAULT NULL,
  `genome_coverage` decimal(8,2) DEFAULT NULL,
  `bioproject_accession` varchar(24) DEFAULT NULL,
  `release_date` date DEFAULT NULL,
  `submitter` varchar(200) DEFAULT NULL,
  `n_organelles` int unsigned DEFAULT NULL,
  `is_site_used` tinyint NOT NULL DEFAULT '0',
  `site_source` varchar(16) DEFAULT NULL,
  `fetched_at` datetime DEFAULT NULL,
  PRIMARY KEY (`abbr1`,`accession`),
  KEY `ix_acc` (`accession`),
  KEY `ix_site` (`is_site_used`),
  KEY `ix_tax` (`taxid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kegg` (
  `species` text,
  `abbr` text,
  `gene` text,
  `KO` text,
  `Abbreviation` text,
  `Enzymes` text,
  `Enzyme_ID` text,
  `Pathway` text,
  `Pathway_ID` text,
  `Source` text,
  `URL` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `literature` (
  `pmid` varchar(12) NOT NULL,
  `title` varchar(512) DEFAULT NULL,
  `journal` varchar(255) DEFAULT NULL,
  `pub_year` smallint unsigned DEFAULT NULL,
  `first_author` varchar(160) DEFAULT NULL,
  `n_genes` int unsigned NOT NULL DEFAULT '0',
  `n_species` int unsigned NOT NULL DEFAULT '0',
  `is_annotation_method` tinyint NOT NULL DEFAULT '0',
  `fetched_at` datetime DEFAULT NULL,
  PRIMARY KEY (`pmid`),
  KEY `ix_year` (`pub_year`),
  KEY `ix_ann` (`is_annotation_method`),
  KEY `ix_n` (`n_genes`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mag_annot` (
  `mag` varchar(32) NOT NULL,
  `protein` varchar(64) NOT NULL,
  `locus_tag` varchar(64) NOT NULL DEFAULT '',
  `description` text,
  PRIMARY KEY (`mag`,`protein`),
  KEY `ix_mag` (`mag`),
  KEY `ix_locus` (`locus_tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mag_go_terms` (
  `mag` varchar(32) NOT NULL,
  `protein` varchar(64) NOT NULL,
  `GO_term` varchar(32) NOT NULL DEFAULT '',
  `Category` varchar(64) DEFAULT '',
  `Description` text,
  `Source` varchar(64) DEFAULT '',
  `URL` varchar(255) DEFAULT '',
  UNIQUE KEY `uq_mag_prot_term` (`mag`,`protein`,`GO_term`),
  KEY `ix_mag_protein` (`mag`,`protein`),
  KEY `ix_protein` (`protein`),
  KEY `ix_term` (`GO_term`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mag_interpro` (
  `mag` varchar(32) NOT NULL,
  `protein` varchar(64) NOT NULL,
  `InterPro_term` varchar(32) NOT NULL DEFAULT '',
  `Type` varchar(64) DEFAULT '',
  `Description` text,
  `Source` varchar(64) DEFAULT '',
  `URL` varchar(255) DEFAULT '',
  UNIQUE KEY `uq_mag_prot_term` (`mag`,`protein`,`InterPro_term`),
  KEY `ix_mag_protein` (`mag`,`protein`),
  KEY `ix_protein` (`protein`),
  KEY `ix_term` (`InterPro_term`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mag_kegg_terms` (
  `mag` varchar(32) NOT NULL,
  `protein` varchar(64) NOT NULL,
  `KO` varchar(32) NOT NULL DEFAULT '',
  `Abbreviation` varchar(128) DEFAULT '',
  `Enzymes` text,
  `Enzyme_ID` varchar(128) DEFAULT '',
  `Pathway` varchar(255) DEFAULT '',
  `Pathway_ID` varchar(64) DEFAULT '',
  `Source` varchar(64) DEFAULT '',
  UNIQUE KEY `uq_mag_prot_ko` (`mag`,`protein`,`KO`),
  KEY `ix_mag_protein` (`mag`,`protein`),
  KEY `ix_protein` (`protein`),
  KEY `ix_term` (`KO`),
  KEY `ix_pathway` (`Pathway_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mag_panther_hits` (
  `mag` varchar(32) NOT NULL,
  `protein` varchar(64) NOT NULL,
  `id` varchar(32) NOT NULL DEFAULT '',
  `anno` varchar(255) DEFAULT '',
  `method` varchar(64) DEFAULT '',
  `url` varchar(255) DEFAULT '',
  UNIQUE KEY `uq_mag_prot_pthr` (`mag`,`protein`,`id`),
  KEY `ix_mag_protein` (`mag`,`protein`),
  KEY `ix_protein` (`protein`),
  KEY `ix_pthr` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mag_pfam_hits` (
  `mag` varchar(32) NOT NULL,
  `protein` varchar(64) NOT NULL,
  `Pfam_accession` varchar(32) NOT NULL DEFAULT '',
  `Pfam_name` varchar(128) DEFAULT '',
  `Description` text,
  `Type` varchar(64) DEFAULT '',
  `Source` varchar(64) DEFAULT '',
  `URL` varchar(255) DEFAULT '',
  UNIQUE KEY `uq_mag_prot_pfam` (`mag`,`protein`,`Pfam_accession`),
  KEY `ix_mag_protein` (`mag`,`protein`),
  KEY `ix_protein` (`protein`),
  KEY `ix_pfam` (`Pfam_accession`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `metaG` (
  `Class` text,
  `Species` text,
  `Project` text,
  `Study` text,
  `Experiment` text,
  `Run` text,
  `Layout` text,
  `tissue` text,
  `dev` text,
  `Treatment` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mirna_metadata` (
  `species` text,
  `abbr` text,
  `MirGeneDB_ID` text,
  `MiRBase_ID` text,
  `Family` text,
  `Seed` text,
  `5p_accession` text,
  `3p_accession` text,
  `Chromosome` text,
  `Start` text,
  `End` text,
  `Strand` text,
  `adult_female` text,
  `adult_male` text,
  `blastula` text,
  `juvenile` text,
  `late_planula` text,
  `primary_polyp` text,
  `exp1` text,
  `exp2` text,
  `exp3` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mirna_seq` (
  `species` text,
  `abbr` text,
  `MirGeneDB_ID` text,
  `mature_ID` text,
  `mature` text,
  `star_id` text,
  `star` text,
  `pre_id` text,
  `pre` text,
  `loop_id` text,
  `loop1` text,
  `5p_id` text,
  `5p` text,
  `3p_ID` text,
  `3p1` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mito_genome` (
  `abbr1` varchar(64) NOT NULL,
  `species` varchar(190) NOT NULL,
  `accession` varchar(64) NOT NULL,
  `size_bp` int DEFAULT NULL,
  `gc_percent` decimal(5,2) DEFAULT NULL,
  `n_genes` int DEFAULT NULL,
  `n_trna` int DEFAULT NULL,
  `n_rrna` int DEFAULT NULL,
  `source` varchar(32) DEFAULT NULL,
  `retrieved` date DEFAULT NULL,
  PRIMARY KEY (`abbr1`),
  KEY `species` (`species`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mitochondrion` (
  `species` text,
  `abbr` text,
  `accession` text,
  `Name` text,
  `Type` text,
  `Start` text,
  `End` text,
  `Length` text,
  `Strand` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `og_family` (
  `og` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `n_genes` int NOT NULL DEFAULT '0',
  `n_seqs` int NOT NULL DEFAULT '0',
  `n_species` int NOT NULL DEFAULT '0',
  `best_source` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `best_term` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `best_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `best_desc` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `best_cat` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `best_support` int NOT NULL DEFAULT '0',
  `best_pct` decimal(6,2) NOT NULL DEFAULT '0.00',
  `best_tier` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `n_terms` int NOT NULL DEFAULT '0',
  `n_terms_all` int NOT NULL DEFAULT '0',
  `search_text` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`og`),
  KEY `idx_tier` (`best_tier`),
  KEY `idx_nspecies` (`n_species`),
  KEY `idx_ngenes` (`n_genes`),
  FULLTEXT KEY `ft_search` (`search_text`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `og_family_member` (
  `og` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abbr` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gene` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nr_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nr_desc` varchar(400) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uni_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uni_desc` varchar(400) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  KEY `idx_og` (`og`),
  KEY `idx_abbr_gene` (`abbr`,`gene`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `og_family_member_prev` (
  `og` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abbr` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gene` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  KEY `idx_og` (`og`),
  KEY `idx_abbr_gene` (`abbr`,`gene`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `og_family_prev` (
  `og` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `n_genes` int NOT NULL DEFAULT '0',
  `n_species` int NOT NULL DEFAULT '0',
  `best_source` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `best_term` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `best_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `best_desc` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `best_cat` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `best_support` int NOT NULL DEFAULT '0',
  `best_pct` decimal(6,2) NOT NULL DEFAULT '0.00',
  `best_tier` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `n_terms` int NOT NULL DEFAULT '0',
  `n_terms_all` int NOT NULL DEFAULT '0',
  `search_text` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`og`),
  KEY `idx_tier` (`best_tier`),
  KEY `idx_nspecies` (`n_species`),
  KEY `idx_ngenes` (`n_genes`),
  FULLTEXT KEY `ft_search` (`search_text`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `og_family_term` (
  `og` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `term` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `term_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `term_desc` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `category` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `support` int NOT NULL DEFAULT '0',
  `n_genes` int NOT NULL DEFAULT '0',
  `n_annot` int NOT NULL DEFAULT '0',
  `pct` decimal(6,2) NOT NULL DEFAULT '0.00',
  `pct_annot` decimal(6,2) NOT NULL DEFAULT '0.00',
  `tier` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`og`,`source`,`term`),
  KEY `idx_term` (`term`),
  KEY `idx_name` (`term_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `og_family_term_low` (
  `og` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `term` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `term_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `term_desc` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `category` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `support` int NOT NULL,
  `n_genes` int NOT NULL,
  `n_annot` int NOT NULL,
  `n_species` int NOT NULL,
  `pct` decimal(6,2) NOT NULL,
  `pct_annot` decimal(6,2) NOT NULL,
  PRIMARY KEY (`og`,`source`,`term`),
  KEY `idx_og_pct` (`og`,`pct`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `og_family_term_prev` (
  `og` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `term` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `term_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `term_desc` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `category` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `support` int NOT NULL DEFAULT '0',
  `n_genes` int NOT NULL DEFAULT '0',
  `n_annot` int NOT NULL DEFAULT '0',
  `pct` decimal(6,2) NOT NULL DEFAULT '0.00',
  `pct_annot` decimal(6,2) NOT NULL DEFAULT '0.00',
  `tier` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`og`,`source`,`term`),
  KEY `idx_term` (`term`),
  KEY `idx_name` (`term_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `og_family_tree` (
  `og` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `n_tips` int NOT NULL DEFAULT '0',
  `n_species` int NOT NULL DEFAULT '0',
  `n_outgroup` int NOT NULL DEFAULT '0',
  `tree_gz` mediumtext COLLATE utf8mb4_unicode_ci,
  `tree_col_gz` mediumtext COLLATE utf8mb4_unicode_ci,
  `n_dup` int NOT NULL DEFAULT '0',
  `n_dup_terminal` int NOT NULL DEFAULT '0',
  `dup_gz` mediumtext COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`og`),
  KEY `idx_ntips` (`n_tips`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `og_family_tree_prev` (
  `og` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `n_tips` int NOT NULL DEFAULT '0',
  `n_species` int NOT NULL DEFAULT '0',
  `n_outgroup` int NOT NULL DEFAULT '0',
  `tree_gz` mediumtext COLLATE utf8mb4_unicode_ci,
  `tree_col_gz` mediumtext COLLATE utf8mb4_unicode_ci,
  `n_dup` int NOT NULL DEFAULT '0',
  `n_dup_terminal` int NOT NULL DEFAULT '0',
  `dup_gz` mediumtext COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`og`),
  KEY `idx_ntips` (`n_tips`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `paleobiology` (
  `Phylum` text,
  `url1` text,
  `Class` text,
  `url2` text,
  `Order1` text,
  `url3` text,
  `Family` text,
  `url4` text,
  `Genus` text,
  `url5` text,
  `species` text,
  `url6` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `phenotype` (
  `id` int NOT NULL AUTO_INCREMENT,
  `Class` text,
  `species` text,
  `trait_name` text,
  `trait_category` text,
  `value` text,
  `traitunit` text,
  `region` text,
  `latitude` text,
  `longitude` text,
  `methodology` text,
  `value_type` text,
  `context` varchar(60) NOT NULL DEFAULT '',
  `source` varchar(40) NOT NULL DEFAULT '',
  `source_record_id` varchar(64) NOT NULL DEFAULT '',
  `source_resource_id` varchar(32) NOT NULL DEFAULT '',
  `n_records` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `ix_species` (`species`(64)),
  KEY `ix_class` (`Class`(32)),
  KEY `ix_unit` (`traitunit`(32)),
  KEY `ix_cat` (`trait_category`(32)),
  KEY `ix_source` (`source`)
) ENGINE=InnoDB AUTO_INCREMENT=195690 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `phenotype_old_20260927` (
  `id` int NOT NULL AUTO_INCREMENT,
  `Class` text,
  `species` text,
  `trait_name` text,
  `trait_category` text,
  `value` text,
  `traitunit` text,
  `region` text,
  `latitude` text,
  `longitude` text,
  `methodology` text,
  `value_type` text,
  `context` varchar(60) NOT NULL DEFAULT '',
  `source` varchar(40) NOT NULL DEFAULT '',
  `source_record_id` varchar(64) NOT NULL DEFAULT '',
  `source_resource_id` varchar(32) NOT NULL DEFAULT '',
  `n_records` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=151047 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `phenotype_source_resource` (
  `source` varchar(40) NOT NULL DEFAULT '',
  `resource_id` varchar(20) NOT NULL DEFAULT '',
  `authors` text,
  `year` varchar(10) NOT NULL DEFAULT '',
  `title` text,
  `container` text,
  `doi` varchar(160) NOT NULL DEFAULT '',
  `resource_type` varchar(40) NOT NULL DEFAULT '',
  UNIQUE KEY `uq` (`source`,`resource_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `phenotype_species_map` (
  `source` varchar(40) NOT NULL DEFAULT '',
  `species` varchar(160) NOT NULL DEFAULT '',
  `valid_name` varchar(160) NOT NULL DEFAULT '',
  `aphia_id` varchar(16) NOT NULL DEFAULT '',
  `status` varchar(24) NOT NULL DEFAULT '',
  `class` varchar(40) NOT NULL DEFAULT '',
  `order_name` varchar(60) NOT NULL DEFAULT '',
  `family` varchar(60) NOT NULL DEFAULT '',
  UNIQUE KEY `uq` (`source`,`species`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `phenotype_trait_dict` (
  `source` varchar(40) NOT NULL DEFAULT '',
  `trait_name` varchar(120) NOT NULL DEFAULT '',
  `trait_category` varchar(60) NOT NULL DEFAULT '',
  `data_type` varchar(24) NOT NULL DEFAULT '',
  `traitunit` varchar(40) NOT NULL DEFAULT '',
  `allowed_values` text,
  `trait_desc` text,
  UNIQUE KEY `uq` (`source`,`trait_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `proteome_data` (
  `Class` text,
  `species` text,
  `tissue` text,
  `Treatment` text,
  `Project` text,
  `pubmed` text,
  `link` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `proteomic_datasets` (
  `dataset_id` varchar(32) NOT NULL COMMENT 'stable CnidoSite dataset id, e.g. PXD009253_AipInf_153-4',
  `pxd` varchar(16) NOT NULL COMMENT 'PRIDE project accession',
  `species` varchar(128) NOT NULL,
  `taxon_class` varchar(64) DEFAULT NULL,
  `tissue` varchar(255) DEFAULT NULL,
  `treatment` varchar(255) DEFAULT NULL,
  `instrument` varchar(96) DEFAULT NULL,
  `pubmed` varchar(16) DEFAULT NULL,
  `orig_engine` varchar(128) DEFAULT NULL,
  `orig_database` varchar(255) DEFAULT NULL,
  `orig_precursor` varchar(32) DEFAULT NULL,
  `orig_fragment` varchar(32) DEFAULT NULL,
  `orig_fdr` varchar(96) DEFAULT NULL,
  `cnido_engine` varchar(64) DEFAULT 'Comet 2026.01',
  `cnido_enzyme` varchar(32) DEFAULT NULL,
  `cnido_termini` varchar(16) DEFAULT NULL,
  `cnido_missed` varchar(8) DEFAULT NULL,
  `cnido_precursor` varchar(32) DEFAULT NULL,
  `cnido_fragment` varchar(32) DEFAULT NULL,
  `cnido_fixed` varchar(96) DEFAULT NULL,
  `cnido_variable` varchar(96) DEFAULT NULL,
  `cnido_fdr_psm` varchar(16) DEFAULT '0.01',
  `cnido_fdr_prot` varchar(16) DEFAULT '0.01',
  `cnido_decoy` varchar(64) DEFAULT 'Comet internal reversed (1:1)',
  `cnido_quant` varchar(96) DEFAULT 'spectral counting (PSMs)',
  `proteome_file` varchar(128) DEFAULT NULL COMMENT 'reference proteome used',
  `proteome_source` varchar(255) DEFAULT NULL COMMENT 'human-readable provenance of the search space; see 1.metadata/proteome_sources.tsv',
  `proteome_source_type` varchar(16) DEFAULT NULL,
  `proteome_note` varchar(600) DEFAULT NULL,
  `proteome_is_surrogate` tinyint DEFAULT '0' COMMENT '1 = searched against a different species in the same genus',
  `status` varchar(32) DEFAULT 'reprocessed' COMMENT 'reprocessed | psms_below_peptide_fdr | no_identifications_at_fdr | no_reference_proteome | bespoke_database_required | pending',
  `status_note` varchar(1000) DEFAULT NULL,
  `pipeline_version` varchar(32) DEFAULT NULL,
  `release` varchar(32) DEFAULT NULL,
  `date_incorporated` date DEFAULT NULL,
  `file_count` int DEFAULT NULL,
  `n_psms` int DEFAULT NULL,
  `n_peptides` int DEFAULT NULL,
  `n_proteins` int DEFAULT NULL,
  PRIMARY KEY (`dataset_id`),
  KEY `idx_pxd` (`pxd`),
  KEY `idx_species` (`species`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `proteomic_peptides` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `dataset_id` varchar(32) NOT NULL,
  `gene_id` varchar(64) DEFAULT NULL,
  `protein_id` varchar(96) DEFAULT NULL,
  `peptide` varchar(160) NOT NULL,
  `q_value` double DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_gene` (`gene_id`),
  KEY `idx_dataset` (`dataset_id`),
  KEY `idx_peptide` (`peptide`),
  KEY `idx_protein_id` (`protein_id`(32))
) ENGINE=InnoDB AUTO_INCREMENT=301126 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `proteomic_proteins` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `dataset_id` varchar(32) NOT NULL,
  `gene_id` varchar(64) DEFAULT NULL COMMENT 'resolves on gene_detail.php only when links_gene = 1',
  `protein_id` varchar(96) NOT NULL COMMENT 'protein id as in the search database',
  `links_gene` tinyint DEFAULT '1',
  `n_psms` int DEFAULT '0',
  `n_unique_peptides` int DEFAULT '0',
  `coverage_pct` float DEFAULT '0',
  `length` int DEFAULT '0',
  `best_q` double DEFAULT NULL,
  `description` text,
  `is_contaminant` tinyint DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_gene` (`gene_id`),
  KEY `idx_dataset` (`dataset_id`),
  KEY `idx_protein` (`protein_id`),
  KEY `idx_contam` (`is_contaminant`),
  KEY `idx_protein_id` (`protein_id`(32))
) ENGINE=InnoDB AUTO_INCREMENT=65918 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sample` (
  `Class` text,
  `Latin_name` text,
  `Project_ID` text,
  `Study_Accession` text,
  `Experiment_Accession` text,
  `Layout` text,
  `tissue` text,
  `dev_stage` text,
  `Treatment` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sc_gene_refseq` (
  `abbr` varchar(16) NOT NULL,
  `sc_id` varchar(128) NOT NULL,
  `accession` varchar(64) NOT NULL,
  `locus` varchar(64) NOT NULL DEFAULT '',
  `source` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`abbr`,`sc_id`),
  KEY `ix_acc` (`accession`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `singlecell` (
  `Phylum` text,
  `Species` text,
  `Project` text,
  `Study` text,
  `Emb` text,
  `Stage` text,
  `CellNumber` text,
  `Ref` text,
  `Link` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `singlecell_atlas` (
  `dataset_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cnidosite_row` int DEFAULT NULL,
  `species` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `class` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tissue_organ` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stage` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `platform_class` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `n_cells_final` int DEFAULT NULL,
  `n_cell_types` int DEFAULT NULL,
  `n_clusters` int DEFAULT NULL,
  `n_samples` int DEFAULT NULL,
  `annotation_provenance` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bioproject` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sra_study` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `geo_series` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_genome` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `genome_version` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'free-text version label, printed after reference_genome',
  `pipeline_version` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cell_type_source` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `asset_dir` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_utc` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `published_figure` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cluster_source` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'what the cluster colouring is, when it is not our own Leiden run',
  `embedding_source` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'where the UMAP coordinates came from, when they are not ours',
  PRIMARY KEY (`dataset_id`),
  KEY `idx_species` (`species`),
  KEY `idx_row` (`cnidosite_row`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `singlecell_atlas_map` (
  `cnidosite_row` int NOT NULL,
  `dataset_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`cnidosite_row`),
  KEY `idx_map_dataset` (`dataset_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `singlecell_celltype` (
  `dataset_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cell_type` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `n_cells` int NOT NULL,
  `pct_cells` float DEFAULT NULL,
  PRIMARY KEY (`dataset_id`,`cell_type`),
  CONSTRAINT `fk_ct_dataset` FOREIGN KEY (`dataset_id`) REFERENCES `singlecell_atlas` (`dataset_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `singlecell_markers` (
  `dataset_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cell_type` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gene` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rank` int DEFAULT NULL,
  `log2fc` float DEFAULT NULL,
  `padj` double DEFAULT NULL,
  `score` float DEFAULT NULL,
  `pct_in` float DEFAULT NULL,
  PRIMARY KEY (`dataset_id`,`cell_type`,`gene`),
  KEY `idx_gene` (`gene`),
  CONSTRAINT `fk_mk_dataset` FOREIGN KEY (`dataset_id`) REFERENCES `singlecell_atlas` (`dataset_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `singlecell_qc` (
  `dataset_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `platform_class` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `platform_confidence` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `n_cells_reported` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `n_cells_raw` int DEFAULT NULL,
  `n_cells_after_cell_qc` int DEFAULT NULL,
  `n_doublets_removed` int DEFAULT NULL,
  `doublet_rate` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `n_cells_final` int DEFAULT NULL,
  `pct_cells_retained` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `threshold_mode` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `threshold_detail` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mito_genes_n` int DEFAULT NULL,
  `mito_filtering_available` tinyint(1) DEFAULT NULL,
  `mito_pct_median_raw` float DEFAULT NULL,
  `mito_pct_p95_raw` float DEFAULT NULL,
  `mito_pct_median_final` float DEFAULT NULL,
  `mito_pct_p95_final` float DEFAULT NULL,
  `doublet_method` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `integration_method` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `n_samples` int DEFAULT NULL,
  `analysis_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `published_figure` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`dataset_id`),
  KEY `idx_qc_dataset` (`dataset_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `speciesinfo` (
  `Class` text,
  `Latin_name` text,
  `abbr` text,
  `NCBI_taxonomy_ID` text,
  `Assembl_level` text,
  `Genome_Assemble` text,
  `Assembly_Name` text,
  `Size` text,
  `year` text,
  `WGS_accession` text,
  `Submitter` text,
  `BioProject` text,
  `Publication_Title` text,
  `Publication_Journal` text,
  `Pubmed_ID` text,
  `paper_URL` text,
  `Number_of_chromosomes` text,
  `scaffolds_number` text,
  `Contig_number` text,
  `Protein_number` text,
  `proteincoding_gene_number` text,
  `contig_N50` text,
  `scaffold_N50` text,
  `GC_percent` text,
  `BUSCO` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tf` (
  `species` text,
  `abbr` text,
  `gene` text,
  `Pfam_selfbuild` text,
  `Family` text,
  `DNA_Binding_Domain` text,
  `Full_name` text,
  `Description` text,
  KEY `ix_gene_species` (`gene`(64),`species`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `trans_assembly` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `abbr1` varchar(16) NOT NULL,
  `protein` varchar(80) NOT NULL,
  `gene` varchar(80) NOT NULL DEFAULT '',
  `plen` int unsigned NOT NULL DEFAULT '0',
  `uniprot` text,
  `uniprot_desc` text,
  `pfam` text,
  `pfam_desc` text,
  `panther` text,
  `panther_desc` text,
  `interpro` text,
  `interpro_desc` text,
  `go` text,
  `kegg` text,
  PRIMARY KEY (`id`),
  KEY `ix_prot` (`abbr1`,`protein`),
  KEY `ix_gene` (`abbr1`,`gene`),
  KEY `ix_abbr_id` (`abbr1`,`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18446015 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `trans_assembly_go` (
  `go_id` varchar(16) NOT NULL,
  `category` varchar(48) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`go_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `trans_assembly_ko` (
  `ko` varchar(16) NOT NULL,
  `name` varchar(320) DEFAULT NULL,
  PRIMARY KEY (`ko`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `trans_assembly_species` (
  `abbr1` varchar(16) NOT NULL,
  `abbr` varchar(64) DEFAULT NULL,
  `species` varchar(128) DEFAULT NULL,
  `class` varchar(32) DEFAULT NULL,
  `source` varchar(48) DEFAULT NULL,
  `run` varchar(32) DEFAULT NULL,
  `transcripts` int unsigned DEFAULT '0',
  `bp` bigint unsigned DEFAULT '0',
  `n50` int unsigned DEFAULT '0',
  `longest` int unsigned DEFAULT '0',
  `proteins` int unsigned DEFAULT '0',
  `reps` int unsigned DEFAULT '0',
  `n_uniprot` int unsigned DEFAULT '0',
  `n_pfam` int unsigned DEFAULT '0',
  `n_panther` int unsigned DEFAULT '0',
  `n_interpro` int unsigned DEFAULT '0',
  `n_go` int unsigned DEFAULT '0',
  `n_kegg` int unsigned DEFAULT '0',
  `notes` text,
  PRIMARY KEY (`abbr1`),
  KEY `ix_class` (`class`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ubs` (
  `species` text,
  `abbr` text,
  `gene` text,
  `family` text,
  `subfamily` text,
  `familyid` text,
  `evalue` text,
  `score` text,
  `descr` text,
  `source` text,
  KEY `ix_gene_species` (`gene`(80),`species`(48))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_proteomic_gene_summary` AS SELECT 
 1 AS `gene_id`,
 1 AS `n_datasets`,
 1 AS `n_unique_peptides`,
 1 AS `n_psms`,
 1 AS `max_coverage_pct`,
 1 AS `best_q`,
 1 AS `species`,
 1 AS `datasets`*/;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_proteomic_species_summary` AS SELECT 
 1 AS `species`,
 1 AS `n_datasets`,
 1 AS `n_proteins_reported`,
 1 AS `n_psms`*/;
SET character_set_client = @saved_cs_client;
/*!50001 DROP VIEW IF EXISTS `v_proteomic_gene_summary`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=CURRENT_USER SQL SECURITY DEFINER */
/*!50001 VIEW `v_proteomic_gene_summary` AS select `p`.`gene_id` AS `gene_id`,count(distinct `p`.`dataset_id`) AS `n_datasets`,sum(`p`.`n_unique_peptides`) AS `n_unique_peptides`,sum(`p`.`n_psms`) AS `n_psms`,max(`p`.`coverage_pct`) AS `max_coverage_pct`,min(`p`.`best_q`) AS `best_q`,group_concat(distinct `d`.`species` order by `d`.`species` ASC separator '; ') AS `species`,group_concat(distinct `p`.`dataset_id` order by `p`.`dataset_id` ASC separator '; ') AS `datasets` from (`proteomic_proteins` `p` join `proteomic_datasets` `d` on((`d`.`dataset_id` = `p`.`dataset_id`))) where ((`p`.`is_contaminant` = 0) and (`p`.`links_gene` = 1)) group by `p`.`gene_id` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `v_proteomic_species_summary`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=CURRENT_USER SQL SECURITY DEFINER */
/*!50001 VIEW `v_proteomic_species_summary` AS select `d`.`species` AS `species`,count(distinct `d`.`dataset_id`) AS `n_datasets`,sum(`d`.`n_proteins`) AS `n_proteins_reported`,sum(`d`.`n_psms`) AS `n_psms` from `proteomic_datasets` `d` group by `d`.`species` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

