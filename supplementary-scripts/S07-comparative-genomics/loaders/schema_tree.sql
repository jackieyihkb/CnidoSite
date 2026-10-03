-- Gene trees for the gene-family pages.
--
-- One row per orthogroup.  The Newick is gzipped and base64-encoded in a MEDIUMTEXT
-- rather than stored as plain text: the raw trees run to ~0.9 GB and the server's root
-- filesystem only has 65 GB free, while gzip takes them down to roughly a sixth of that.
-- PHP inflates with gzdecode(base64_decode(...)).
--
-- n_tips / n_species / n_outgroup are precomputed because the page needs them before it
-- has parsed anything -- to decide whether to draw the tree whole or collapse it to one
-- tip per species, and to caption the header card.
--
-- Built as _new and swapped in with a single RENAME, same as og_family*: the live page
-- must never see the table missing.
DROP TABLE IF EXISTS og_family_tree_new;

CREATE TABLE og_family_tree_new (
  og         VARCHAR(16) NOT NULL,
  n_tips     INT NOT NULL DEFAULT 0,
  n_species  INT NOT NULL DEFAULT 0,
  n_outgroup INT NOT NULL DEFAULT 0,
  tree_gz    MEDIUMTEXT,
  -- species-collapsed version, only for families too big to draw whole; NULL otherwise
  tree_col_gz MEDIUMTEXT,
  -- OrthoFinder's duplication calls, mapped onto the tree the page draws (see
  -- work/genetree/annotate_dups.py).  dup_gz is gzipped JSON [[pathkey, support, type], ...]
  -- where type is T/N/C: T = a radiation inside one species, N = the node's species are
  -- exactly the species the duplication spans, C = the marker sits on the nearest clade
  -- that still contains all of them.  0 / NULL for the 24,747 families with no duplication.
  n_dup      INT NOT NULL DEFAULT 0,
  n_dup_terminal INT NOT NULL DEFAULT 0,
  dup_gz     MEDIUMTEXT,
  PRIMARY KEY (og),
  KEY idx_ntips (n_tips)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
