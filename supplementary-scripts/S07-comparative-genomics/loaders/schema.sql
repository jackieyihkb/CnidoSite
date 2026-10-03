-- Gene-family consensus annotation, built from the OrthoFinder Results_Sep14
-- run (153 cnidarian proteomes).
--
-- These are built under a _new suffix and swapped in with a single atomic
-- RENAME at the end of the import, so the live site never sees a missing table.

DROP TABLE IF EXISTS og_family_new;
DROP TABLE IF EXISTS og_family_term_new;
DROP TABLE IF EXISTS og_family_member_new;

CREATE TABLE og_family_new (
  og           VARCHAR(16)  NOT NULL,
  n_genes      INT          NOT NULL DEFAULT 0,  -- distinct (species, gene id) units
  n_seqs       INT          NOT NULL DEFAULT 0,  -- member sequences, may exceed n_genes
  n_species    INT          NOT NULL DEFAULT 0,
  best_source  VARCHAR(16)  NOT NULL DEFAULT '',
  best_term    VARCHAR(64)  NOT NULL DEFAULT '',
  best_name    VARCHAR(255) NOT NULL DEFAULT '',
  best_desc    VARCHAR(512) NOT NULL DEFAULT '',
  best_cat     VARCHAR(64)  NOT NULL DEFAULT '',
  best_support INT          NOT NULL DEFAULT 0,
  best_pct     DECIMAL(6,2) NOT NULL DEFAULT 0,
  best_tier    VARCHAR(8)   NOT NULL DEFAULT '',
  n_terms      INT          NOT NULL DEFAULT 0,
  n_terms_all  INT          NOT NULL DEFAULT 0,
  search_text  TEXT,
  PRIMARY KEY (og)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE og_family_term_new (
  og         VARCHAR(16)  NOT NULL,
  source     VARCHAR(16)  NOT NULL DEFAULT '',
  term       VARCHAR(64)  NOT NULL DEFAULT '',
  term_name  VARCHAR(255) NOT NULL DEFAULT '',
  term_desc  VARCHAR(512) NOT NULL DEFAULT '',
  category   VARCHAR(64)  NOT NULL DEFAULT '',
  support    INT          NOT NULL DEFAULT 0,
  n_genes    INT          NOT NULL DEFAULT 0,
  n_annot    INT          NOT NULL DEFAULT 0,
  pct        DECIMAL(6,2) NOT NULL DEFAULT 0,
  pct_annot  DECIMAL(6,2) NOT NULL DEFAULT 0,
  tier       VARCHAR(8)   NOT NULL DEFAULT '',
  PRIMARY KEY (og, source, term),
  KEY idx_term (term),
  KEY idx_name (term_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE og_family_member_new (
  og       VARCHAR(16)  NOT NULL,
  abbr     VARCHAR(24)  NOT NULL,
  gene     VARCHAR(191) NOT NULL,
  nr_id    VARCHAR(64)  NULL,   -- top NCBI-NR hit, filled in by enrich step
  nr_desc  VARCHAR(400) NULL,
  uni_id   VARCHAR(64)  NULL,   -- top Swiss-Prot hit
  uni_desc VARCHAR(400) NULL,
  KEY idx_og (og),
  KEY idx_abbr_gene (abbr, gene)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
