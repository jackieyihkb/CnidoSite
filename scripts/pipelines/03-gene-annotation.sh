#!/bin/sh
# CnidoSite — gene models and functional annotation
#
# ===========================================================================
# 0. WHERE CNIDOSITE'S GENE MODELS COME FROM
# ===========================================================================
#
# They are the **NCBI-submitted annotations**, not a prediction made here. Each
# per-species assembly is downloaded as an NCBI Datasets tree and flattened into four
# files; where NCBI publishes a gene set, that gene set is what the resource serves.
# The supplementary text states this in §S3.1–S3.2.
#
# Where an assembly has **no NCBI protein set** — most of the MAGs, 174 assemblies —
# genes are predicted with Prodigal 2.6.3 in metagenomic mode. The same commands serve
# the MAG module, and the copies kept in the deposit are the chunked MAG pipeline's.
#
# Functional annotation covers six evidence sources per protein set: GO,
# InterPro/Pfam/PANTHER, UniProt, KEGG, NR and transcription factors.
#
# Scripts referred to below are in the supplementary-scripts deposit, under
# `S03-genome-annotation/genome/` and `S11-mags-and-metagenome/pipeline/`.

# ===========================================================================
# 0.1 Acquisition and identifier normalisation   (§S3.1)
# ===========================================================================
# Each NCBI Datasets download is flattened by `genome/rename_files.sh`:
#   mv "cds_from_genomic.fna" "../../../../${species_name}.cds"
#   mv "protein.faa"          "../../../../${species_name}.pep"
#   mv "$genome_file"         "../../../../${species_name}.fa"
#   mv "genomic.gff"          "../../../../${species_name}.gff3"
#
# Sequence and feature identifiers are then rewritten from the raw INSDC accessions to
# a species-local scheme (chr1..chrN, SUPER_*_unloc_*, SCAFFOLD_*, mitochondrion),
# applied in step to the FASTA and the GFF3 so the two stay consistent:
#   sed -i 's/CM042746.1/chr1/g'               Catalaphyllia_jardinei.fa
#   sed -i 's/JAEMPF020017689.1/Scaffold_100/g' Catalaphyllia_jardinei.fa
#   sed -i 's/CM042746.1/chr1/g'               Catalaphyllia_jardinei.gff3
# (representative of genome/1.sh–10.sh)
#
# The gene → transcript → protein mapping behind the per-species protein tables comes
# from small GFF3 parsers, one per species, each with its input hard-coded:
#   python3 1.py                       # Exaiptasia_diaphana.gff3 -> exaiptasia_id_mapping.txt
#   python3 extract_gene_protein.py    # Orbicella_franksi.gff3
#   python3 1.py <input.gff3> <output_locus>       # from genome/locus/
# (2.py–5.py are the same parser, differing only in the species table each writes.)

# ===========================================================================
# 0.2 Gene prediction for assemblies with no NCBI protein set   (§S3.2)
# ===========================================================================
# Prodigal 2.6.3, metagenomic mode. Source: pipeline/02_predict.sh:45-47
"$PRODIGAL" -i "$fna" -p meta -q \
    -a "$outdir/proteins.faa" -d "$outdir/genes.fna" \
    -f gff -o "$outdir/genes.gff"
# -> IDs rewritten to <accession>_<n>; trailing stop characters stripped

# ===========================================================================
# 0.3 Functional annotation   (§S3.2)
# ===========================================================================
# InterPro / Pfam / PANTHER and GO. Source: pipeline/_annotate_one.sh:30-31
"$IPS" -i "$faa" -f TSV -dp -goterms -pa -cpu "${IPS_CPU_PER_JOB:-4}" -o "$tmp"

# InterProScan is dispatched in chunks, so an interrupted run resumes by re-running
# the same command. Source: pipeline/run_iprscan_chunked.sh:67-68
interproscan.sh -i "$f" -f TSV -o "$out" -iprlookup -goterms -dp -cpu "$CPU"

# UniProt and NR, by DIAMOND. Source: pipeline/04_uniprot.sh:83-87
"$DIAMOND" blastp --db "$db" --query "$all_faa" --out "$hits" \
    --outfmt 6 qseqid sseqid pident length evalue bitscore stitle \
    --max-target-seqs 1 --evalue 1e-5 \
    --threads "${DIAMOND_THREADS:-128}" --sensitive --quiet

# KEGG, by KofamScan — one chunk per job. Source: pipeline/05_kegg.sh:79
ls "$chunk_dir"/chunk_*.faa | xargs -r -P "$CHUNKS" -n 1 "$MAG_ROOT/pipeline/_kofam_chunk.sh"

# The KofamScan mapper is the matching step. Source: pipeline/run_kofam.sh:49-56
exec_annotation -f mapper -o "${OUT}" -p "$K/profiles" -k "$K/ko_list" \
    --cpu "$CPU" --tmp-dir "$TMP" "$IN"

# Three silent failure modes in the InterProScan step, each of which yields an empty
# column rather than an error — see the supplementary text §S11:
#   · omitting any of -iprlookup, -goterms or -dp loads zero InterPro entries;
#   · the GO column (field 14) carries a suffix that must be stripped;
#   · interproscan.properties does not predict which analyses run — pin InterPro
#     release 109 explicitly, and include obsolete terms and alternative identifiers
#     in go_term.tsv.

# ===========================================================================
# ---- EXTERNAL MATERIAL BEGINS — NOT CNIDOSITE PROVENANCE ------------------
# ===========================================================================
#
# Everything below is the structural annotation chain of a **separate genome project**
# for one jellyfish genome. It is not the provenance of any CnidoSite gene set: no
# BRAKER, AUGUSTUS, MAKER or EVM invocation exists for the per-species annotations the
# resource serves, which are the NCBI-submitted ones described in section 0 above.
#
# It is retained because it is the only complete annotation record on the working tree,
# and because it is easy to mistake for this resource's own. Its own notebook
# (/mnt/sda/jackie/PASA/jellyfish_final/assembly.sh:5) describes the strategy as
#
#   "1) de novo used augustus, genscan and glimmerhmm, 2) homolog used gemoma,
#    3) transcriptome used PASA, finally merged with EVM"
#
# and the site's tools table (data_statistics.php:530-546) describes the same strategy —
# which is exactly why the confusion is worth foreclosing. Note also that the
# `--species=nematostella_vectensis` flag in the BRAKER command below selects an AUGUSTUS
# training profile; it does not name the assembly being annotated.
#
# Nothing below should be cited as a CnidoSite method. The supplementary text records the
# same exclusion in §S1 and §S3.2.
#
# ===========================================================================
# ---- the external chain, section by section, as recovered -----------------
# ===========================================================================

# ===========================================================================
# 1. BRAKER3 — [RUN]  (the production command, verbatim)
# ===========================================================================
# Version: braker.pl 3.0.8        (/mnt/sda/jackie/braker3/nohup.out:5, braker/braker.log:5)
#          ProtHint 2.6.0        (test2.log:16)
#          GeneMark-ETP etp-v1.02-preprint-15-g8b3dbc2
#          DIAMOND 0.9.24.125 (inside ProtHint) and 2.0.15.153 (aa2nonred)
#          AUGUSTUS version: never printed. [GAP]
# Container built from the upstream image (assembly.sh:212):
singularity build braker3.sif docker://teambraker/braker3:latest

# The run that produced the released annotation. Source: braker3/nohup.out:455, and
# identically braker3/braker/braker.log:4. Twelve sorted RNA-seq BAMs, one per sample:
/opt/BRAKER/scripts/braker.pl --species=nematostella_vectensis \
  --genome=jellyfish_scaffolded.fa.mod.MAKER.masked.masked \
  --bam=bam_files/cyst1_sorted.bam,bam_files/cyst2_sorted.bam,bam_files/cyst3_sorted.bam,bam_files/four-leafstage1_sorted.bam,bam_files/four-leafstage2_sorted.bam,bam_files/four-leafstage3_sorted.bam,bam_files/Jellyfishstage1_sorted.bam,bam_files/Jellyfishstage2_sorted.bam,bam_files/Jellyfishstage3_sorted.bam,bam_files/shortstolon1_sorted.bam,bam_files/shortstolon2_sorted.bam,bam_files/shortstolon3_sorted.bam \
  --GENEMARK_PATH=/gmes --threads 20 --useexisting

# An earlier attempt, same directory, driven from raw reads instead of BAMs (nohup.out:4):
#   braker.pl --species=nematostella_vectensis --genome=... \
#     --rnaseq_sets_ids=<22 SRA runs> --rnaseq_sets_dirs=fastq/ --threads 20
# The equivalent form in the notebook adds UTRs (assembly.sh:213):
#   braker.pl --species=nematostella_vectensis --genome=... --bam=<the same 12> \
#     --GENEMARK_PATH=${ETP}/gmes --threads 20 --addUTR=on --useexisting
#
# RE-RUNNABLE: no. The command is complete, but neither fastq/ nor bam_files/ nor the
# original /mnt/jackie path exists on this server any more.
#
# NOTE the `--species=nematostella_vectensis` argument: it selects the AUGUSTUS training
# profile only. It does not mean the assembly is Nematostella.

# ===========================================================================
# 2. PASA — [RUN]
# ===========================================================================
# Version: PASA 2.5.3   (container pasapipeline.v2.5.3.simg; PASApipeline/Docker/VERSION.txt)
# Source: /mnt/sda/jackie/PASA/annevo/run.sh:1-3

singularity exec -B $PWD -B /mnt/jackie/PASA/jellyfish_final pasapipeline.v2.5.3.simg \
  /usr/local/src/PASApipeline/Launch_PASA_pipeline.pl -c /mnt/jackie/PASA/annevo/alignAssembly.config -C -R \
  -g /mnt/jackie/PASA/jellyfish_final/jellyfish_scaffolded.fa.mod.MAKER.masked.masked \
  -t /mnt/jackie/PASA/annevo/transcripts.fasta.clean -T -u /mnt/sda/jackie/PASA/annevo/transcripts.fasta \
  -f /mnt/jackie/PASA/annevo/FL_accs.txt --ALIGNERS blat,gmap,minimap2 --CPU 120

# Then load the merged gene set and re-run in annotation-comparison mode
# (same run.sh, lines 2-3; also assembly.sh:235-236):
singularity exec -B $PWD pasapipeline.v2.5.3.simg \
  /usr/local/src/PASApipeline/scripts/Load_Current_Gene_Annotations.dbi \
  -c alignAssembly.config -g jellyfish_scaffolded.final.fa -P jellyfish.EVM.new.gff3
singularity exec -B $PWD pasapipeline.v2.5.3.simg \
  /usr/local/src/PASApipeline/Launch_PASA_pipeline.pl \
  -c annotCompare.config -A -g jellyfish_scaffolded.final.fa -t transcripts.fasta.clean --CPU 120

# Key alignAssembly.config settings (alignAssembly.config:5,13,14,17):
#   DATABASE=/tmp/annevo.sqlite
#   MIN_PERCENT_ALIGNED=80
#   MIN_AVG_PER_ID=80
#   subcluster_builder.dbi -m 50

# The transcript evidence going in is built in assembly.sh:170-184 and 194-199:
trimmomatic PE -threads 120 -phred33 rawdata/cyst1/cyst1_R1.fq.gz rawdata/cyst1/cyst1_R2.fq.gz \
    cyst1_trimmed_R1.fq.gz cyst1_unpaired_R1.fq.gz cyst1_trimmed_R2.fq.gz cyst1_unpaired_R2.fq.gz \
    ILLUMINACLIP:/home/jiajie/miniconda3/pkgs/trimmomatic-0.39-hdfd78af_2/share/trimmomatic-0.39-2/adapters/TruSeq3-PE.fa:2:30:10 \
    LEADING:3 TRAILING:3 SLIDINGWINDOW:4:15 MINLEN:36
hisat2-build -p 120 ../scaffolds.fa hisat2_db
hisat2 -p 120 -x hisat2_db -1 cyst1_trimmed_R1.fq.gz -2 cyst1_trimmed_R2.fq.gz \
    | samtools sort -@ 40 -m 4G -o cyst1_sorted.bam
samtools merge --threads 120 merged.bam *bam
stringtie -p 120 -o stringtie_merged.gtf merged.bam

~/miniforge3/envs/PASA/opt/transdecoder/util/gtf_genome_to_cdna_fasta.pl \
    stringtie_merged.gtf ../jellyfish_scaffolded.fa.mod.MAKER.masked.masked > transcripts.fa
~/miniforge3/envs/PASA/opt/transdecoder/util/gtf_to_alignment_gff3.pl stringtie_merged.gtf > transcripts.gff3
TransDecoder.LongOrfs -t transcripts.fa -O transdecoder_dir
diamond blastp -d ./uniprot/uniprot.dmnd -q transdecoder_dir/longest_orfs.pep \
    -o blastp.outfmt6 --evalue 1e-5 --outfmt 6 --threads 120 --max-target-seqs 10000000
hmmscan --cpu 120 --domtblout pfam.domtblout pfam/Pfam-A.hmm transdecoder_dir/longest_orfs.pep
TransDecoder.Predict -t transcripts.fa --retain_pfam_hits pfam.domtblout \
    --retain_blastp_hits blastp.outfmt6 -O transdecoder_dir
~/miniforge3/envs/PASA/opt/transdecoder/util/cdna_alignment_orf_to_genome_orf.pl \
    transcripts.fa.transdecoder.gff3 transcripts.gff3 transcripts.fa > transcript_alignments.gff3

# De novo transcript assembly feeding the same evidence set (assembly.sh:187-199):
Trinity --seqType fq --max_memory 400G --samples_file sample_list.txt --SS_lib_type RF \
    --CPU 120 --output trinity_out_dir_denovo --full_cleanup > trinity.denovo.log 2> trinity.denovo.err
Trinity --max_memory 200G --CPU 120 --SS_lib_type RF --genome_guided_bam merged.bam \
    --genome_guided_max_intron 20000 > trinity.GG.log

# ===========================================================================
# 3. De novo predictors — [RUN]
# ===========================================================================
# AUGUSTUS (assembly.sh:129-130). The training profile is the same Nematostella one:
augustus --species=nematostella_vectensis ../jellyfish_scaffolded.fa.mod.MAKER.masked.masked --gff3=on > augustus/jellyfish_augustus.gff
~/miniforge3/envs/eukaryotes_anno/opt/evidencemodeler-2.1.0/EvmUtils/misc/augustus_GFF3_to_EVM_GFF3.pl jellyfish_augustus.gff > jellyfish.august.evm.gff

# GlimmerHMM (assembly.sh:136-139):
python ./train_prepare_glimmerhmm.py ../05-PASA-1-clean/trainingSetComplete.gff3 genome.softmask.fa exon.hint
./GlimmerHMM/train/trainGlimmerHMM genome.softmask.fa exon.hint -d train.params
./GlimmerHMM/bin/glimmhmm.pl glimmerhmm genome.hardmask.fa train.params -g -f > glimmerhmm.gff
~/miniforge3/envs/eukaryotes_anno/opt/evidencemodeler-2.1.0/EvmUtils/misc/glimmerHMM_to_GFF3.pl glimmerhmm.gff > glimmerhmm.evm.format.gff3

# SNAP (assembly.sh:142-150):
gff3_to_zff.pl ../05-PASA-1/genome.softmask.fa ../05-PASA-1-clean/trainingSetComplete.gff3 > contig.ann
ln -s ../05-PASA-1/genome.softmask.fa contig.dna
fathom contig.ann contig.dna -categorize 1000
fathom uni.ann uni.dna -export 1000 -plus
forge export.ann export.dna
hmm-assembler.pl snap.pasa . > snap.hmm
snap snap.hmm ../05-PASA-1/genome.softmask.fa -lcmask > snap.out.zff
zff2gff3.pl snap.out.zff > snap.out.gff3
~/miniforge3/envs/eukaryotes_anno/opt/evidencemodeler-2.1.0/EvmUtils/misc/SNAP_CDS_to_GFF3.pl snap.out.gff3 > snap.evm.format.gff3
# (the source line 143 reads `ln-s`, a typo for `ln -s`; copied as found)

# ===========================================================================
# 4. Homology predictor — GeMoMa — [RUN]
# ===========================================================================
mamba create -n eukaryotes_anno python=3.8
mamba activate eukaryotes_anno
mamba install -c conda-forge ncbi-datasets-cli -y
datasets download genome accession --inputfile accession.txt --filename ncbi_genomes.zip --include genome
# Five reference species (assembly.sh:158); note the references are named in the notebook's
# header comment as H. symbiolongicarpus, T. rubra, C. hemisphaerica, H. vulgaris,
# A. coerulea and R. esculentum, while the command itself uses these five:
GeMoMa GeMoMaPipeline threads=120 outdir=GeMoMa_res GeMoMa.Score=ReAlign AnnotationFinalizer.r=NO o=true \
    t=jellyfish_scaffolded.final.fa \
    s=own i=Aurelia_coerulea a=Aurelia_coerulea.gtf g=Aurelia_coerulea.fa \
    s=own i=Clytia_hemisphaerica a=Clytia_hemisphaerica.gtf g=Clytia_hemisphaerica.fa \
    s=own i=Hydractinia_symbiolongicarpus a=Hydractinia_symbiolongicarpus.gtf g=Hydractinia_symbiolongicarpus.fa \
    s=own i=Hydra_vulgaris a=Hydra_vulgaris.gtf g=Hydra_vulgaris.fa \
    s=own i=Turritopsis_dohrnii a=Turritopsis_dohrnii.gtf g=Turritopsis_dohrnii.fa -Xmx64G
~/miniforge3/envs/eukaryotes_anno/opt/evidencemodeler-2.1.0/EvmUtils/misc/GeMoMa_gff_to_gff3.pl GeMoMa_res/final_annotation.gff > ../jellyfish.gemoma.evm.gff

# ===========================================================================
# 5. EVidenceModeler — the merge — [RUN]
# ===========================================================================
# Version: EVM 2.1.0  (tools/EVidenceModeler/EVidenceModeler:13, Docker/VERSION.txt)
EVidenceModeler --sample_id jellyfish \
    --genome jellyfish_scaffolded.fa.mod.MAKER.masked.masked --weights weight.txt \
    --gene_predictions jellyfish.braker.evm.fixed_1.gff3 \
    --protein_alignments jellyfish.gemoma.evm.gff \
    --transcript_alignments jellyfish.pasa.evm.gff3 \
    --segmentSize 100000 --overlapSize 10000 --CPU 120

# GFF3 clean-up before EVM (assembly.sh:215-217):
agat_convert_sp_gxf2gxf.pl --gff jellyfish.braker.evm.gff3 -o jellyfish.braker.evm.fixed.gff3
agat_sq_manage_IDs.pl --gff jellyfish.braker.evm.fixed.gff3 -o jellyfish.braker.evm.fixed_1.gff3
~/tools/EVidenceModeler/EvmUtils/gff3_gene_prediction_file_validator.pl/... your.gff3

# Two different weight files are on disk. Both are recorded; see TO-BE-SUPPLIED.md item 8.
# (a) assembly.sh:224-226 — the one named in the EVM command above:
#       PROTEIN             GeMoMa              5
#       ABINITIO_PREDICTION BRAKER3             8
#       TRANSCRIPT          PASA_threemethods  10
# (b) /mnt/sda/jackie/PASA/annevo/weight.txt — used by the ANNEVO-based run:
#       PROTEIN             GeMoMa              6
#       ABINITIO_PREDICTION ANNEVO            10
#       TRANSCRIPT          transdecoder       10

# ===========================================================================
# 6. ANNEVO — [GAP]
# ===========================================================================
# Environment: ANNEVO, python 3.10.18 / pytorch 2.1.0+cu121 (ANNEVO.yml:103-105);
# models ANNEVO_model/ANNEVO_Invertebrate.pt and four others.
# The run is real — ANNEVO/nohup.out is a genuine log, ending in
# "The gene annotation took 27204.6 seconds", with output jellyfish_annevo.gff3 —
# but **no command line was recorded**: the driver script does not echo argv, and
# ~/.bash_history contains only `cd ANNEVO/`. The README template
# (ANNEVO/README.md:63/74/77) is
#     python annotation.py --genome ... --model_path ... --output ... --threads 48
# which is documentation, not this project's record. See TO-BE-SUPPLIED.md item 6.

# ===========================================================================
# 7. Helixer — [GAP]
# ===========================================================================
# /mnt/sda/jackie/Helixer/ is a clean checkout at git v0.3.6-1-g7d5941e with no log,
# no nohup output and no annotation output. The only trace is `ls Helixer/` in
# ~/.bash_history:1546. **Whether Helixer was ever run for this project is unknown.**
# See TO-BE-SUPPLIED.md item 7.

# ===========================================================================
# 8. Functional annotation of the final protein set  (assembly.sh:250-254)
# ===========================================================================
diamond blastp -d databases/diamond_nr.dmnd -q jellyfish.EVM.final.pep.fa -o diamond_nr_results.tsv \
    --evalue 1e-5 --outfmt 6 qseqid sseqid stitle pident length mismatch gapopen qstart qend \
    sstart send evalue bitscore staxids --threads 120 --max-target-seqs 10000000
diamond blastp -d my_DB/uniprot/uniprot.dmnd -q jellyfish.EVM.final.pep.fa -o diamond_uniprot_results.tsv \
    --evalue 1e-5 --outfmt 6 qseqid sseqid stitle pident length mismatch gapopen qstart qend \
    sstart send evalue bitscore --threads 120 --max-target-seqs 10000000
./interproscan.sh -p 240 -i jellyfish/jellyfish.EVM.final.pep.fa -f tsv -goterms -pa -o jellyfish/jellyfish_interproscan
./emapper.py -i ../interproscan-5.67-99.0/jellyfish/jellyfish.EVM.final.pep.fa \
    --output jellyfish_maNOG --usemem --cpu 120
# InterProScan version on the site: v.5.67-97.0 with `-f tsv -goterms -pa`
# (data_statistics.php:530). The line above is from an InterProScan 5.67-99.0 directory,
# i.e. the installed version moved between runs. HMMER cutoff on the site: 0.0001.

# ===========================================================================
# 9. Post-PASA naming and export  (assembly.sh:238-246)
# ===========================================================================
python ./Genome-annotation-pipeline/scripts/gff_rename.py mydb.sqlite.gene_structures_post_PASA_updates.2797852.gff3 Cae > jellyfish_pasa_renamed.gff3
perl gff3sort/gff3sort.pl jellyfish_pasa_renamed.gff3 > jellyfish_pasa_renamed_sorted.gff3
python rename_gff.py jellyfish_pasa_renamed_sorted.gff3 -c scaffold
gffread jellyfish_pasa.final.gff3 -g jellyfish_scaffolded.final.fa -x jellyfish_pasa.final.cds -y jellyfish_pasa.final.pep
python Collect_no_alt.py jellyfish_pasa.final.pep jellyfish_pasa.final.cds jellyfish_pasa.final.gff3
