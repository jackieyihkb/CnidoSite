#!/bin/sh
# CnidoSite — transposable-element annotation
#
# Two independent sets of commands exist on disk and they do NOT agree. Both are recorded
# here; the discrepancy is unresolved and is listed in TO-BE-SUPPLIED.md item 4.

# ===========================================================================
# A. The EDTA pipeline — [RUN], but it completed on only 3 of 145 species
# ===========================================================================
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

# TE table construction (01_per_species.sh:166-173):
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

# ===========================================================================
# B. The commands actually used for the released TE data — [GAP]
# ===========================================================================
# The site's Software and Analytical Tools table (data_statistics.php:532) states:
#   RepeatModeler v2.0.7   -engine rmblast -LTRStruct
#   RepeatMasker  v4.2.1   -gff -e rmblast -nolow
# (the site text says "RepeatModeler v.4.2.1" for the second line, which is a typo for
#  RepeatMasker — recorded here as found.)
#
# **No script or log on this server contains that command pair.** The nearest thing is
# the TE section of the assembly notebook (01-genome-assembly.sh, lines 109-124), which
# differs in three ways: the engine is `ncbi` not `rmblast`, the species string is
# hard-coded to "Clytia hemisphaerica", and EDTA is used instead of RepeatModeler:
#
#   mamba create -n TE -c bioconda -c conda-forge repeatmodeler repeatmasker rmblast hmmer trf edta
#   BuildDatabase -name jellyfish_db ../jellyfish_scaffolded.fa
#   RepeatModeler -database jellyfish_db -engine ncbi -threads 120 -LTRStruct &> jellyfish.out
#   RepeatMasker -pa 120 -species "Clytia hemisphaerica" -lib jellyfish_db-families.fa \
#       -dir de_novo -gff -nolow -no_is -norna -e rmblast jellyfish_scaffolded.fa
#   EDTA.pl --genome jellyfish_scaffolded.fa --species others --sensitive 1 --anno 1 --step all -t 120
#   RepeatMasker -pa 120 -lib jellyfish_scaffolded.fa.mod.EDTA.TElib.fa -gff -nolow -no_is -norna \
#       -e rmblast jellyfish_scaffolded.fa.mod.MAKER.masked -dir RepeatMasker_EDTA
#   TEtrimmer --input_file Actinostola_sp.fa.mod.EDTA.TElib.fa --genome_file Actinostola_sp.fa \
#       --output_dir TEtrimmer_Actinostola_sp --num_threads 120 --classify_all
#
# The released dataset is 3.7 M TE records including 580,259 AIDSS entries. The command
# that produced it is not on this server. See TO-BE-SUPPLIED.md item 4.
