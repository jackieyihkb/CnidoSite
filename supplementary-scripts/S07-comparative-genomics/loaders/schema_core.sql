-- CnidoSite core ortholog resource (CCO).
--
-- Built by work/core/{build_species,build_copynumber,score,build_resource,
-- build_load}.py from the OrthoFinder Results_Sep14 run over 153 proteomes.
-- Loaded under a _new suffix and swapped in atomically by import_core.sh.

DROP TABLE IF EXISTS core_species_new;
DROP TABLE IF EXISTS core_og_new;
DROP TABLE IF EXISTS core_member_new;

CREATE TABLE core_species_new (
  abbr1          VARCHAR(64)  NOT NULL,
  of_name        VARCHAR(190) NOT NULL DEFAULT '',
  latin          VARCHAR(190) NOT NULL DEFAULT '',
  phylum         VARCHAR(64)  NOT NULL DEFAULT '',
  class          VARCHAR(64)  NOT NULL DEFAULT '',
  order1         VARCHAR(64)  NOT NULL DEFAULT '',
  family         VARCHAR(64)  NOT NULL DEFAULT '',
  genus          VARCHAR(64)  NOT NULL DEFAULT '',
  ncbi           VARCHAR(32)  NOT NULL DEFAULT '',
  cnidarian      TINYINT      NOT NULL DEFAULT 0,
  -- BUSCO cnidaria_odb12 complete >= 90% on this proteome.  Deliberately NOT
  -- named high_quality: busco_summary already has a high_quality column with a
  -- different and much stricter meaning (15 genomes, all >=91%), and two columns
  -- of the same name holding different sets is a trap for anyone joining them.
  -- The five outgroup proteomes and the two with no BUSCO run are 0 here.
  busco90        TINYINT      NOT NULL DEFAULT 0,
  n_buscos       INT          NOT NULL DEFAULT 0,
  n_single       INT          NOT NULL DEFAULT 0,
  n_duplicated   INT          NOT NULL DEFAULT 0,
  n_fragmented   INT          NOT NULL DEFAULT 0,
  n_missing      INT          NOT NULL DEFAULT 0,
  pct_complete   DECIMAL(5,2) NOT NULL DEFAULT 0,
  pct_single     DECIMAL(5,2) NOT NULL DEFAULT 0,
  pct_duplicated DECIMAL(5,2) NOT NULL DEFAULT 0,
  n_core_og      INT          NOT NULL DEFAULT 0,   -- CCO orthogroups present
  n_core_single  INT          NOT NULL DEFAULT 0,   -- ... of which single-copy
  PRIMARY KEY (abbr1),
  KEY idx_busco90 (busco90),
  KEY idx_class (class)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE core_og_new (
  og              VARCHAR(16)  NOT NULL,
  tiers           VARCHAR(32)  NOT NULL DEFAULT '',  -- strict,core,extended
  -- occupancy among the 60 high-quality cnidarian genomes
  hq90_n          INT          NOT NULL DEFAULT 0,
  hq90_present    INT          NOT NULL DEFAULT 0,
  hq90_single     INT          NOT NULL DEFAULT 0,
  hq90_occ        DECIMAL(6,4) NOT NULL DEFAULT 0,
  hq90_sc         DECIMAL(6,4) NOT NULL DEFAULT 0,
  -- ... among all 148 cnidarian proteomes
  all_n           INT          NOT NULL DEFAULT 0,
  all_present     INT          NOT NULL DEFAULT 0,
  all_single      INT          NOT NULL DEFAULT 0,
  all_occ         DECIMAL(6,4) NOT NULL DEFAULT 0,
  all_sc          DECIMAL(6,4) NOT NULL DEFAULT 0,
  -- the five non-cnidarian outgroups, the only way to root a matrix
  outgroup_present TINYINT     NOT NULL DEFAULT 0,
  outgroup_single  TINYINT     NOT NULL DEFAULT 0,
  n_members       INT          NOT NULL DEFAULT 0,
  best_source     VARCHAR(16)  NOT NULL DEFAULT '',
  best_term       VARCHAR(64)  NOT NULL DEFAULT '',
  best_name       VARCHAR(255) NOT NULL DEFAULT '',
  best_desc       VARCHAR(512) NOT NULL DEFAULT '',
  best_cat        VARCHAR(64)  NOT NULL DEFAULT '',
  best_support    INT          NOT NULL DEFAULT 0,
  best_tier       VARCHAR(8)   NOT NULL DEFAULT '',
  search_text     TEXT,
  PRIMARY KEY (og),
  KEY idx_tiers (tiers),
  KEY idx_hq90sc (hq90_sc),
  FULLTEXT KEY ft_search (search_text)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE core_member_new (
  og        VARCHAR(16)  NOT NULL,
  -- 64, not 24: the outgroup codes are full species names (OUT_Corticium_
  -- candelabrum is 25 chars) and a narrower column truncates them silently,
  -- which then breaks the join against core_species.
  abbr      VARCHAR(64)  NOT NULL,
  gene      VARCHAR(191) NOT NULL,
  n_copies  INT          NOT NULL DEFAULT 0,
  is_single TINYINT      NOT NULL DEFAULT 0,
  KEY idx_og (og),
  KEY idx_abbr (abbr),
  KEY idx_abbr_gene (abbr, gene)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
