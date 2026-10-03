#!/usr/bin/env Rscript
# rds_to_mtx.R -- convert a deposited Seurat/Matrix RDS into the mtx triplet
# this pipeline reads.
#
# Why not SeuratDisk
# ------------------
# pipeline/lib/io.py has an `rds` path that calls SaveH5Seurat()/Convert(), but
# the R installation on this machine has neither SeuratDisk nor hdf5r, so that
# path cannot run here at all.  Rather than add a dependency, this extracts the
# counts layer directly -- a dgCMatrix is already a CSC sparse matrix, and
# Matrix::writeMM writes the same MatrixMarket file the loader reads.  The
# registry then records `mtx` as the local_format, so the dataset goes through
# exactly the same loader as a submitter-deposited triplet.
#
# What it refuses
# ---------------
# The pipeline needs raw counts.  A deposit holding normalised values (this is
# what Hydractinia PRJNA1124116 turned out to be) is not convertible here and
# must not be quietly rounded into shape, so the integer check is a hard
# stopwith, not a warning.  That is the same check the loader performs, done
# here instead so the failure names the file and the value.
#
# Usage
# -----
#   Rscript rds_to_mtx.R --rds X.rds --outdir DIR --name PREFIX \
#                        [--cell-type-column COL] [--cell-type-file TSV] [--key COL] \
#                        [--sample-column COL]
#
#   --cell-type-column  a column of the object's meta.data to carry through as
#                       the cell-type label
#   --cell-type-file    an external per-cell table to join instead, matched on
#                       --key (a meta.data column, or the cell names)
#   --sample-column     a column of meta.data holding the library/sample, which
#                       is written into the barcode as "<sample>_<barcode>"
#
# Why --sample-column is a prefix and not a lookup
# ------------------------------------------------
# `lib/io.infer_sample_from_barcode` reads the library from a barcode's text.
# GSE307733 encodes its two libraries as a TRAILING numeric suffix
# (`AAACCCAAGAGATTCA-1_1` / `..._2`), which that function cannot see: its
# prefix rule needs the label before the underscore, and its suffix rule
# matches `-<digits>` at the end.  Both miss, the dataset pools into one
# "sample1", and per-library doublet detection and Harmony then run on a
# pooled library while the QC table reports that they ran.
#
# Teaching the loader to also split on a trailing `_<n>` would work here and
# would be wrong as a general rule -- it would re-partition every other
# dataset on a pattern that only this deposit means as a library.  The sample
# is not a property of the barcode text; it is a column of the deposit, and
# this is the only place that has it.  So it is written into the barcode in
# the form the loader already documents as the prefix convention.
#
# The rewrite is asserted, not assumed: every cell's prefix is compared back
# against its meta.data value and the run dies if any disagree.  A label
# containing `_` is rewritten to `-` first (the loader splits at the first
# underscore, so `agg.24h_1` and `agg.24h_2` would both read back as
# `agg.24h`), and that substitution is refused outright if it would make two
# distinct samples collide.
#
# A label source is only written when it is a real one: a column with a single
# distinct value is a sample name, not an annotation, and writing it would let
# the pipeline claim a provenance it does not have.
suppressPackageStartupMessages(library(Matrix))
# readRDS() restores an object whose class attribute says "Seurat" whether or
# not Seurat is attached -- and then every method on it, DefaultAssay() first,
# is not found.  The class check in get_counts() would pass and the call would
# fail, so Seurat is attached here.  It is only needed for Seurat objects; the
# converted outputs are plain MatrixMarket either way.
suppressPackageStartupMessages(library(Seurat))

die <- function(...) { message("ERROR: ", ...); quit(status = 1) }

parse_args <- function(argv) {
  o <- list(cell_type_column = NULL, cell_type_file = NULL, key = NULL,
            sample_column = NULL)
  i <- 1
  while (i <= length(argv)) {
    a <- argv[i]
    take <- function() { if (i + 1 > length(argv)) die("missing value for ", a); argv[i + 1] }
    if (a == "--rds") o$rds <- take()
    else if (a == "--outdir") o$outdir <- take()
    else if (a == "--name") o$name <- take()
    else if (a == "--cell-type-column") o$cell_type_column <- take()
    else if (a == "--cell-type-file") o$cell_type_file <- take()
    else if (a == "--key") o$key <- take()
    else if (a == "--sample-column") o$sample_column <- take()
    else die("unknown argument ", a)
    i <- i + 2
  }
  for (k in c("rds", "outdir", "name")) {
    if (is.null(o[[k]])) die("--", gsub("_", "-", k), " is required")
  }
  o
}

# The counts layer, wherever this object keeps it.  Layer= is Seurat 5, slot=
# is Seurat 4; a bare dgCMatrix is neither.
get_counts <- function(obj) {
  if (inherits(obj, "Seurat")) {
    assay <- DefaultAssay(obj)
    m <- tryCatch(GetAssayData(obj, assay = assay, layer = "counts"),
                  error = function(e) NULL)
    if (is.null(m)) {
      m <- tryCatch(GetAssayData(obj, assay = assay, slot = "counts"),
                    error = function(e) NULL)
    }
    if (is.null(m)) die("object has no counts layer in assay ", assay)
    attr(m, "assay") <- assay
    return(m)
  }
  if (inherits(obj, "dgCMatrix") || inherits(obj, "dgTMatrix") ||
      inherits(obj, "matrix")) {
    return(as(obj, "CsparseMatrix"))
  }
  die("unsupported object class: ", paste(class(obj), collapse = ", "))
}

# Mirror of `lib/io.infer_sample_from_barcode` case 1, so this script can ask
# what the loader WILL read rather than what we hope it reads.
#
# The pattern's class is [A-Za-z0-9._-] -- underscore INCLUDED -- and it is
# greedy, so the split is at the LAST underscore that still leaves eight
# characters behind it, not at the first.  Getting that wrong is not academic:
# prefixing a barcode that already carries its sample turns `wt.24h_AAAC...`
# into `wt.24h_wt.24h_AAAC...`, which the loader then reads back as the
# doubled label `wt.24h_wt.24h`.  That is what this function exists to catch.
loader_prefix <- function(bc) {
  m <- regmatches(bc, regexec("^([A-Za-z0-9][A-Za-z0-9._-]{0,31})_(.{8,})$", bc))
  out <- vapply(m, function(x) if (length(x) >= 2) x[2] else NA_character_,
                character(1))
  # the loader only trusts the prefix when it covers almost every barcode and
  # distinguishes at least two values
  if (mean(!is.na(out)) <= 0.9 || length(unique(out[!is.na(out)])) < 2) {
    return(NULL)
  }
  out
}

# Guard against the R and Python regex engines disagreeing about greedy
# matching.  If POSIX ERE ever split these differently from PCRE the guard
# below would silently do the wrong thing, so the split points are pinned.
local({
  fix <- c("wt.24h_AAACCCAAGATTACCC-1" = "wt.24h",
           "agg.24h_1_AAACCCAAGATTACCC-1" = "agg.24h_1",
           "agg.48h_2_AAACCCAAG-1" = "agg.48h_2",
           "01-D1_CCCATCTTCACT" = "01-D1")
  got <- loader_prefix(names(fix))
  if (is.null(got) || !identical(unname(got), unname(fix))) {
    die("loader_prefix disagrees with lib/io.infer_sample_from_barcode on the ",
        "pinned examples; the split rule has changed and --sample-column ",
        "cannot be trusted")
  }
})

main <- function() {
  o <- parse_args(commandArgs(trailingOnly = TRUE))
  obj <- readRDS(o$rds)
  m <- get_counts(obj)
  m <- as(m, "CsparseMatrix")

  v <- m@x
  if (length(v) == 0) die("matrix has no non-zero entries")
  if (any(v < 0)) {
    die(sprintf("%s holds negative values (min %.6g): this is normalised data, not counts",
                basename(o$rds), min(v)))
  }
  if (!isTRUE(all(v == round(v)))) {
    die(sprintf("%s holds fractional values (e.g. %.6g): this is normalised data, not counts",
                basename(o$rds), v[which(v != round(v))[1]]))
  }
  # Deliberately NOT coercing the storage mode to integer.  Matrix::writeMM()
  # already emits the `%%MatrixMarket matrix coordinate integer general` banner
  # for a double-storage matrix whose values are all whole, which is what the
  # loader's integer check reads; forcing integer storage instead makes writeMM
  # fail with `invalid type "integer" in 'M2CHS'`.

  dir.create(o$outdir, showWarnings = FALSE, recursive = TRUE)

  # ---- barcodes, carrying the sample when the deposit names one -----------
  orig_bc <- colnames(m)   # untouched, so a rejected rewrite can be redone
  bc <- orig_bc
  if (!is.null(o$sample_column)) {
    if (!inherits(obj, "Seurat")) die("--sample-column needs a Seurat object")
    if (!o$sample_column %in% colnames(obj@meta.data)) {
      die("no meta.data column ", o$sample_column)
    }
    smp <- as.character(obj@meta.data[[o$sample_column]])
    if (length(smp) != length(bc)) die("sample column length does not match cells")
    if (nlevels(factor(smp)) < 2) {
      die("--sample-column ", o$sample_column, " has one value (", unique(smp)[1],
          "); there is no library structure to carry")
    }
    # Ask the loader what it already reads, and leave the barcodes alone when
    # the answer is already right.  GSE302686 names its cells `8h_AAAC...`, so
    # the samples come back correctly with no help; rewriting those would
    # produce `8h_8h_AAAC...` for nothing.
    already <- loader_prefix(orig_bc)
    if (!is.null(already) && identical(already, smp)) {
      cat(sprintf("barcodes already encode %d sample label(s) matching %s; left as deposited\n",
                  length(unique(smp)), o$sample_column))
      print(table(smp))
    } else {
      # Otherwise the barcode has to be BUILT so the loader reads back exactly
      # `smp`, not merely prefixed and hoped for.
      #
      # GSE307733's two objects show why.  dt's cells are `AAAC...-1_1` -- the
      # library is a bare trailing index and nothing in the loader can see it,
      # so dt pools into one "sample1".  g's cells are `agg.24h_AAAC...-1_1`,
      # where the trailing index is the replicate and an earlier segment names
      # the timepoint; the loader reads that as `agg.24h` for BOTH replicates,
      # because its greedy prefix rule lands at the last underscore that still
      # leaves eight characters, and `1` is not eight.  Seven libraries become
      # five, and the QC table then reports doublet detection per library and
      # Harmony over seven when five were used.
      #
      # Prefixing the original text does not fix that: prepending `smp` to
      # `agg.24h_AAAC...-1_1` gives the greedy rule a longer prefix to grab and
      # it reads back the doubled label `agg.24h_2_agg.24h`.  The tail has to
      # be reduced to the barcode alone and stripped of the underscores that
      # are the loader's only separator.
      if (!is.null(already)) {
        cat(sprintf("barcodes encode %d sample label(s), not the %d in %s; rewriting\n",
                    length(unique(already)), length(unique(smp)), o$sample_column))
      }
      first <- sub("_.*$", "", orig_bc)
      # where the deposit already leads with its own sample text, drop it; what
      # remains is the barcode
      tail <- ifelse(startsWith(smp, first), sub("^[^_]*_", "", orig_bc), orig_bc)
      tail <- gsub("_", "-", tail)
      short <- which(nchar(tail) < 8)
      if (length(short)) {
        die(sprintf("barcode for cell %d is only %d characters; the loader ",
                    short[1], nchar(tail[short[1]])), "needs at least 8 after the separator")
      }
      # The label itself keeps its underscores: with an underscore-free tail the
      # greedy rule's longest-prefix preference lands on the whole label, so
      # `agg.24h_1` reads back as `agg.24h_1` instead of truncating to
      # `agg.24h`.  That is what keeps the two 24 h replicates apart.
      bc <- paste0(smp, "_", tail)

      # Dropping separators can in principle collide two distinct barcodes, and
      # a duplicate cell ID is silent data loss.  Refuse rather than ship it.
      if (anyDuplicated(bc)) {
        die(sprintf("%d cell ID(s) collide after rewriting, e.g. %s",
                    sum(duplicated(bc)), bc[duplicated(bc)][1]))
      }
      back <- loader_prefix(bc)
      if (is.null(back) || !identical(back, smp) ||
          length(unique(back)) != length(unique(smp))) {
        bad <- which(is.na(back) | back != smp)[1]
        die(sprintf("barcode rewrite does not round-trip at cell %d: %s -> %s",
                    bad, smp[bad], if (is.na(back[bad])) "NA" else back[bad]))
      }
      cat(sprintf("barcodes rewritten to <sample>_<barcode>; %d sample label(s) from %s\n",
                  length(unique(smp)), o$sample_column))
      print(table(smp))
    }
  }

  mtx <- file.path(o$outdir, paste0(o$name, ".counts.mtx"))
  writeMM(m, mtx)
  # The loader reads a plain or gzipped .mtx; gzip keeps the deposit-sized
  # files manageable and costs nothing on read.
  R.utils::gzip(mtx, destname = paste0(mtx, ".gz"), overwrite = TRUE, remove = TRUE)
  writeLines(rownames(m), file.path(o$outdir, paste0(o$name, ".genes.txt")))
  writeLines(bc, file.path(o$outdir, paste0(o$name, ".barcodes.tsv")))

  cat(sprintf("matrix: %d genes x %d cells; nnz %d; counts integer and non-negative\n",
              nrow(m), ncol(m), length(m@x)))

  # ---- labels, only when they are really labels --------------------------
  lab <- NULL
  if (!is.null(o$cell_type_column)) {
    if (!inherits(obj, "Seurat")) die("--cell-type-column needs a Seurat object")
    if (!o$cell_type_column %in% colnames(obj@meta.data)) {
      die("no meta.data column ", o$cell_type_column)
    }
    lab <- as.character(obj@meta.data[[o$cell_type_column]])
  } else if (!is.null(o$cell_type_file)) {
    md <- read.table(o$cell_type_file, header = TRUE, sep = "\t",
                     quote = "\"", comment.char = "", check.names = FALSE)
    key <- if (!is.null(o$key)) o$key else colnames(md)[1]
    if (!key %in% colnames(md)) die("no column ", key, " in ", o$cell_type_file)
    tcol <- setdiff(colnames(md), key)[1]
    lab <- as.character(md[[tcol]][match(colnames(m), md[[key]])])
    cat(sprintf("joined %s: %d of %d cells matched\n", basename(o$cell_type_file),
                sum(!is.na(lab)), length(lab)))
  }

  if (!is.null(lab)) {
    n <- length(unique(lab[!is.na(lab)]))
    if (n < 2) {
      # A single distinct value is the sample, the tissue or a constant -- the
      # GSE308593 objects carry exactly this (Idents is "tentacle_twist" for
      # all 9,350 cells).  Writing it as a cell-type table would make the
      # pipeline label the dataset `published` on the strength of a string that
      # says nothing, so refuse and let the run come out cluster_only.
      cat(sprintf("label source has %d distinct value(s) -- not an annotation; no cell-type table written\n", n))
    } else {
      un <- sort(table(lab, useNA = "ifany"), decreasing = TRUE)
      print(un)
      # `bc`, not colnames(m): once --sample-column has rewritten the
      # barcodes, a table keyed on the original names joins to nothing and the
      # dataset silently loses its annotation.
      ct <- data.frame(cellID = bc, cell_type = lab)
      write.table(ct, gzfile(file.path(o$outdir, paste0(o$name, ".cell_to_cts.csv.gz"))),
                  sep = "\t", quote = FALSE, row.names = FALSE)
      cat(sprintf("wrote cell-type table: %d types over %d cells\n", n, nrow(ct)))
    }
  }
  cat("done\n")
}

main()
