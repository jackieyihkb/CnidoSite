#!/bin/sh
# CnidoSite — transposable-element annotation
#
# A de novo TE library is built for each genome and the genome is annotated with it, and
# each element is then joined to the nearest gene model to produce the site's eight-column
# TE table: species | TE_id | scaffold | TE_start | TE_end | related_gene | region | TE_type.
#
# ===========================================================================
# TWO IMPLEMENTATIONS EXIST. ONLY ONE PRODUCED THE SERVED TABLES.
# ===========================================================================
#
# * `TE_pipeline/`            — the pipeline of section 1 below. It emits the `region`
#                               values the site serves, and it is the one the supplementary
#                               text documents (§S4). Deposited as
#                               `supplementary-scripts/S04-transposable-elements/TE_pipeline/`.
#
# * `genome_TE/TE_pipeline/`  — a second, independent implementation, recorded in section 2
#                               as reference only. It writes the same eight columns but
#                               assigns `region` by a different rule
#                               (exon > intron > promoter > intergenic), so it does not
#                               reproduce the served values.
#
# The data settle which one ran: the served tables carry `5UTR`, `3UTR` and `gene_body`,
# which only the first rule emits.
#
# Environment (the served pipeline). One conda environment with pinned versions:
#   EDTA 2.2.2, driving RepeatMasker 4.2.1 / rmblast 2.14.1, genometools 1.6.5,
#   LTR_FINDER_parallel 1.3, LTR_retriever 3.0.4, TIR-Learner 3.0.7, HelitronScanner 1.0,
#   AnnoSINE_v2 2.0.9, RepeatModeler 2.0.7 and TEsorter 1.4.7; Python 3.12.11.
#
# ===========================================================================
# 1. The pipeline that produced the served tables   [RUN]
# ===========================================================================

# --- 1.1 De novo library and whole-genome annotation ----------------------
# One EDTA run per species, with the configuration values expanded.
# Source: TE_pipeline/bin/20_edta.sh:52-63, with TE_pipeline/config/pipeline.conf:24-44
( cd "$d" && EDTA.pl \
    --genome "$genome" \
    --species others \
    --sensitive 1 \
    --anno 1 \
    --evaluate 0 \
    --step all \
    --maxdiv 40 \
    --overwrite 0 \
    --repeatmodeler "$TE_PIPE/rmwrap/" \
    --force 1 \
    -t 32 ) >> "$logf" 2>&1

# `--step all` runs structure-based prediction (LTR, TIR, Helitron, SINE) plus
# RepeatModeler for LINEs, builds the library and classifies it; `--anno 1` performs the
# whole-genome RepeatMasker annotation, so no separate RepeatMasker run is made;
# `--sensitive 1` merges the RepeatModeler results into the final library;
# `--overwrite 0` resumes. The thread count is genome_bp / 20 Mb, clamped to 8–32.
#
# `--repeatmodeler` points at a wrapper that adds `-recoverDir` to RepeatModeler, so a
# restarted species resumes from its last complete round rather than round 1.
# Source: TE_pipeline/rmwrap/RepeatModeler:77
exec "$REAL" "${args[@]}" -recoverDir "$PWD/$best"

# --- 1.2 Table build ------------------------------------------------------
# The EDTA GFF3 is converted to the site's eight columns; the TE type comes from the GFF3
# feature column, the scaffold name is restored from an id map, and the element is
# assigned to the gene with the highest-precedence overlap
# (exon > 5'UTR > 3'UTR > intron > gene body), else to the nearest gene, with
# `region = promoter` when it lies within 2,000 bp upstream of the TSS on the correct
# strand. Source: TE_pipeline/bin/run_species.sh:43
python3 TE_pipeline/bin/30_te_table.py '<species>'

# --- 1.3 Merge and publish ------------------------------------------------
# Source: TE_pipeline/bin/90_merge.py, as invoked by run_all.py:360 and watchdog.sh:94
python3 TE_pipeline/bin/90_merge.py

# The merge daemon, when watchdog.sh is days away from a merge.
# Source: TE_pipeline/bin/merge_daemon.sh:20 (usage line)
nohup setsid bash TE_pipeline/bin/merge_daemon.sh >> TE_pipeline/logs/merge.log 2>&1 < /dev/null &

# One publishing pass; on cron every 5 min.
# Source: TE_pipeline/bin/60_publish_to_site.sh:254-269
bash TE_pipeline/bin/60_publish_to_site.sh            # one publishing pass
bash TE_pipeline/bin/60_publish_to_site.sh --dry-run  # report only
bash TE_pipeline/bin/60_publish_to_site.sh --list     # print published state

# The load itself is a table recreate plus LOAD DATA LOCAL INFILE, with the species column
# rewritten to the site's Latin display name; SHOW CREATE TABLE on a loaded table returns
# this DDL field for field, including the collation.
# Source: TE_pipeline/bin/60_publish_to_site.sh:255-267
#   DROP TABLE IF EXISTS $tbl;
#   CREATE TABLE $tbl (
#     species TEXT, TE_id TEXT, scaffold TEXT, TE_start TEXT, TE_end TEXT,
#     related_gene TEXT, region TEXT, TE_type TEXT
#   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
#   LOAD DATA LOCAL INFILE '$STAGE/$sp.tsv' INTO TABLE $tbl
#   CHARACTER SET utf8mb4 FIELDS TERMINATED BY '\t' ESCAPED BY ''
#   LINES TERMINATED BY '\n' IGNORE 1 LINES
#   (species, TE_id, scaffold, TE_start, TE_end, related_gene, region, TE_type)
#   SET species = '$latin_sql';
# The collation is stated explicitly and deliberately: a bare `CHARACTER SET utf8mb4`
# under MySQL 8 defaults to `utf8mb4_0900_ai_ci`, and that default is what the live
# tables carry.

# --- 1.4 Recorded repairs -------------------------------------------------
# Four procedures altered delivered tables and are part of their provenance, all deposited
# with the pipeline:
#   · `33_drop_foreign_entries.py <species> --apply` removed rice sequences that EDTA's
#     fallback had left in the Myxozoa libraries (Myxobolus 20,106 -> 20,098 rows;
#     Thelohanellus 2,293 -> 2,286; Henneguya 37,231 -> 37,183), keeping the originals
#     as `*.with_foreign`;
#   · `34_redo_anno.sh <species> [threads]` replayed the ANNO stage alone where a deleted
#     TMPDIR had left an empty annotation, reusing the existing RepeatMasker output via
#     `--rmout` (Nemopilema 0.00 % -> 15.74 %);
#   · five LTR repair scripts re-invoke
#     `LTR_retriever -genome "$g" -inharvest "$g.rawLTR.scn" -u 1.3e-8 -threads "$threads" -noanno`,
#     `1.3e-8` being EDTA's own divergence cut-off;
#   · for three species the external EDTA products were transferred and only the local
#     table build re-run; the md5 checksums are in the pipeline README.

# ===========================================================================
# ---- EXTERNAL MATERIAL BEGINS — THE SECOND IMPLEMENTATION, REFERENCE ONLY --
# ===========================================================================
#
# `genome_TE/TE_pipeline/` is an independent implementation. It did not produce the
# tables the site serves (see the header). It is kept because it is the only other TE
# record on the working tree, and because the two are easy to confuse: both write the
# same eight columns and both are EDTA-based.
#
# Its environment differs from the served pipeline's — RepeatModeler 2.0.8 and
# RepeatMasker 4.2.3 here, against 2.0.7 and 4.2.1 there.
#
# Source: /mnt/sda/jackie/cnidaria_omics/genome_TE/TE_pipeline/
#   EDTA v2.2.2            (Morbakka_virulenta.pipeline.log:8)
#   RepeatModeler 2.0.8    (repeatmodeler.log:1-6, called internally by EDTA)
#   RepeatMasker 4.2.3     (same)
#   rmblast 2.14.1+        (same)
#   TEtrimmer 1.7.2, python 3.10   (00_setup_env.sh:17-31)
#
# Environment setup (00_setup_env.sh:17-36). The script is supposed to write a frozen
# environment export `te_anno.yml`; **that file does not exist**, so the sub-package
# versions above cannot be reproduced exactly.
#   name: te_anno

# The per-species call, as executed (01_per_species.sh:107-117):
timeout --signal=TERM --kill-after=300 "$(( TIMEOUT_H * 3600 ))s" \
    conda run --no-capture-output -n "${ENV_NAME}" EDTA.pl \
        --genome "${FASTA}" --species others --sensitive "${SENSITIVE}" \
        --anno 1 --step all --overwrite 0 --force 1 -t "${THREADS}"

# EDTA's own parameter echo, from a real run (Morbakka_virulenta.pipeline.log:13) —
# this is the authoritative form of the above for that species:
#   Parameters: --genome .../Morbakka_virulenta.renamed.fa --species others --sensitive 0
#               --anno 1 --step all --overwrite 0 --force 1 -t 5

# Batch driver (run_all.sh:110-114), JOBS=3 THREADS=5 TIMEOUT_H as set there:
parallel --will-cite -j "${JOBS}" --joblog .../parallel.joblog --resume-failed --tag \
    "bash ${PIPELINE}/01_per_species.sh {} ${THREADS} --timeout-hours ${TIMEOUT_H} --cleanup"

# TE table construction (01_per_species.sh:166-173) — note the different `region` rule:
python "${PIPELINE}/03_te_annotation_table.py" \
    --species ... --te-gff ... --gene-gff ... --genome ... --id-map ... \
    --promoter 2000 --out ...

# Standalone RepeatMasker, present in the script but never enabled (WITH_RM=0 default)
# (01_per_species.sh:153-157):
RepeatMasker -pa "${THREADS}" -lib "${TELib}" -gff -nolow -no_is -norna -e rmblast "${FASTA}.mod" -dir "${RM_DIR}"

# STATUS: input genome_TE/<Species>.fa.gz + <Species>.gff3.gz; output
# results/TE_tables/<Species>.TE_info.tsv. Only Morbakka_virulenta, Pachyseris sp. and
# Platygyra sp. were started and **all three were killed during the LINE stage**;
# results/TE_tables/ is empty and parallel.joblog contains only its header.
#
# This is why the second implementation cannot be the source of the served tables: it
# completed for nothing. It is recorded here only so that a reader who finds it on the
# working tree can see at once that it is not the pipeline to reproduce.
