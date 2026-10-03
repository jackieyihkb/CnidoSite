# Response to Reviewers — gene-level phylogeny and orthology in the annotation module

> *Gene-level phylogenetic information would significantly enhance the Evo-Devo utility of
> the annotation module. The gene annotation module is one of the strongest components of
> CnidoSite. A major additional feature that would greatly facilitate evolutionary and
> Evo-Devo analyses would be a gene-level phylogenetic/orthology view. For a queried gene,
> users could inspect homologues across cnidarians and selected outgroups, visualize a
> precomputed gene tree, identify orthologous and paralogous relationships and download the
> associated sequences/alignment.*

We have added this. Every item the reviewer lists — homologue inspection across cnidarians
and the outgroups, a precomputed gene tree, explicit orthologue/paralogue calls, and
downloads of the alignment and the sequences — is now reachable from a gene page, and the
calls are OrthoFinder's own rather than something we re-derived. Two things were wrong
before this revision and are also fixed: the gene page's orthogroup link pointed into a
*different* OrthoFinder run than the rest of the module (see §4), and nothing in the module
distinguished an orthologue from a paralogue.

---

## 1. The path a user now takes

| Step | Page | What it shows |
|---|---|---|
| 1 | `gene_detail.php?gene=…&species=…` | the gene's **orthogroup(s)** in the current 153-proteome analysis, each linking to the family page *and* to the tree viewer with that gene carried along |
| 2 | `genefamily_result.php?family=OG…` | all members with species and best NR / Swiss-Prot hits, the consensus annotation, and **Alignment (FASTA.gz)** / **Sequences (FASTA)** / **View gene tree** |
| 3 | `genetree/?family=OG…&gene=…&abbr=…` | the precomputed gene tree, the queried gene ringed in gold, duplication nodes marked, and an **Orthologues & paralogues** panel for that gene |

Try it: <https://cnidosite.org/gene_detail.php?gene=aacu_s0001.g1.t1&species=AACUM> →
OG0001578 → the viewer lands on that gene with 184 one-to-one orthologues in 69 species,
307 co-orthologues in 57 species, and 6 paralogues.

## 2. What backs it (the data)

| | value |
|---|---|
| OrthoFinder 2.5.5 orthogroups / member genes | 67,795 / 5,293,496 |
| proteomes | 153 = 148 cnidarian + 5 outgroups (*Bolinopsis*, *Corticium*, *Oscarella*, *Halichondria*, *Sycon*) |
| orthogroups with a resolved, rooted OrthoFinder gene tree | 45,514 |
| orthogroups with no rooted tree (all have **≤3 genes**), drawn from our own FastTree run | 22,281 |
| orthogroups too large to draw in full (>3,000 tips), drawn with one tip per species | 177 |
| duplication nodes in `Duplications.tsv` | 2,283,346 (of which **2,201,851** fall in the families above) |
| distinct duplication markers on the drawn trees | 1,676,046 |
| families carrying ≥1 duplication | 43,048 |
| families resolved with **no** duplication | 2,466 |
| MAFFT alignments downloadable | 67,795 (5.65 GB as gzipped FASTA; no family lacks one) |

## 3. How orthology is called

We use **OrthoFinder's own species-overlap duplication calling** on the rooted gene trees,
with the per-node support value from `Duplications.tsv` — not a reimplementation. For a
queried gene:

- **Orthologues (one-to-one)** — genes of other species whose most recent common ancestor
  with the query is an ordinary speciation node.
- **Co-orthologues (many-to-many)** — genes of other species whose MRCA with the query is a
  **duplication** node, i.e. the duplication happened after the two species split.
- **Paralogues** — the other genes of the *same* species in the same orthogroup, which is
  OrthoFinder's definition of in-paralogs; this list is complete even for families whose tree
  is drawn species-collapsed.

The panel reports each group as a count plus a per-species breakdown, and states the rule
and its source in the panel itself. Duplication markers are drawn on the tree (red node +
red branch) and clicking one explains what it means; the three placement cases are worded
differently, because on a collapsed tree a marker is a statement about a clade and we do not
want it read as a statement about one node.

**Where we do not claim resolution.** For the 22,281 orthogroups with ≤3 genes,
OrthoFinder resolved no rooted tree, so no duplication could be called. Those families say
so explicitly ("orthologues cannot be told apart from co-orthologues; everything listed is a
true homologue") instead of showing a spurious duplication or an arbitrary rooting. In
families drawn with one tip per species, the two orthologue groups are counted per species
clade and the panel says so.

## 4. A defect this also fixes: the orthogroup link was pointing at another run

`gene_detail.php` read the **legacy `genefamily` table** — the earlier 104-proteome run —
while the family pages and the tree viewer had already moved to the 153-proteome
`og_family*` tables. Orthogroup identifiers are reused between the two runs, so "gene → its
orthogroup" silently landed on a same-numbered but different family:

| | legacy table (104 proteomes) | current table (153 proteomes) |
|---|---|---|
| OG0000000 | 25,957 genes | 35,546 genes |
| AACUM genes whose orthogroup id changed | 24,574 / 25,276 = **97.2 %** | — |
| `aacu_s0089.g44.t1` | OG0000001 | OG0000002 |

The gene page now queries `og_family_member` (index `idx_abbr_gene`), so step 1 above and
steps 2–3 agree by construction. Genes that legitimately belong to more than one orthogroup
are all listed: two deposited proteomes (*Alatina alata*, *Calvadosia cruxmelitensis*) shipped
duplicate FASTA headers, so one identifier can be carried by several sequences and can enter
several orthogroups. That is reported as it is rather than hidden behind `LIMIT 1`.

## 5. Downloads

- **Alignment (FASTA.gz)** — OrthoFinder's MAFFT alignment for the orthogroup, byte for byte.
- **Sequences (FASTA)** — the same sequences with all gap characters removed, for realigning
  or searching elsewhere.
- **Newick (full tree)** — the untruncated tree; for the 177 large families the page *draws*
  a species-collapsed tree, and the download deliberately does not (the endpoint always
  serves the full tree).

## 6. Files changed

| File | Role |
|---|---|
| `work/genetree/annotate_dups.py` | maps `Duplications.tsv` onto the trees the pages draw (collapsed-aware) |
| `work/genetree/pack_trees.py` | packs Newick + the duplication columns into `og_family_tree` |
| `work/genetree/pack_aln.sh` | gzips the 67,795 MAFFT alignments |
| `work/genetree/index_template.php` → `build.py` → `index.php` | the viewer: duplication overlay, queried-gene highlight, orthology panel, `?dl=aln` / `?dl=seq` |
| `work/new/gene_detail.php` | orthogroup from the current tables + link into the viewer |
| `work/new/genefamily_result.php` | the two download buttons next to "View gene tree" |
| `work/new/schema_tree.sql`, `work/new/import_tree.sh` | new columns (`n_dup`, `n_dup_terminal`, `dup_gz`) and the atomic load |

## 7. Verification

- Every duplication marker was checked independently against the tree text the browser
  parses: all 2.2 M nodes resolve, 0 mismatches (checked exhaustively on an extract and on
  random samples of the full file).
- Live end-to-end in a headless browser: markers render, the queried gene is ringed, and the
  orthology panel's groups sum **exactly** to the family size —
  OG0001578: 184 + 307 + 7 = 498; OG0000001: 890 + 29,279 + 91 = 30,260.
- `?dl=aln` returns a valid gzip whose record count equals the family's member count
  (498 for OG0001578); `?dl=seq` returns the same records with **zero** gap characters
  (251,339 removed for that family).
- The alignment tree is `Require all denied` over HTTP (direct access returns 403) and is
  served only through the viewer's endpoint, which matches the family id against
  `/^OG[0-9]+$/` before touching the filesystem.
- Regression sweep of the module's pages (index, gene family, family detail, tree viewer at
  three sizes, phylotree, gene page, species page, downloads): all 200.
