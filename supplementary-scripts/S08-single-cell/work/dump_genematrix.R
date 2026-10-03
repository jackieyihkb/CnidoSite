# Convert the deposited SEACell / cell-type gene-activity matrices out of R so the
# rest of the build can happen in Python.  Read-only: we never re-derive activity.
#
# Raw float32, column-major.  Matrix::writeMM() on a ~93%-dense Matrix is
# unusably slow here (10 MB in 19 min), so write the dense block instead.
W <- "/mnt/sda/jackie/cnidaria/codex/singlecell/work"
IN <- list(SEACell  = "gse294388/GSE294388_Matrix-Gene-Scores-SEACell-FC.rds",
           celltype = "gse294388/GSE294388_Matrix-Gene-Scores-cell-type-FC.rds")

for (nm in names(IN)) {
  x <- readRDS(file.path(W, IN[[nm]]))
  cat(nm, ":", nrow(x), "x", ncol(x), "\n"); flush(stdout())
  m <- as.matrix(x)
  con <- file(file.path(W, sprintf("genematrix_%s.bin", nm)), "wb")
  writeBin(as.vector(m), con, size = 4)          # column-major
  close(con)
  writeLines(rownames(m), file.path(W, sprintf("genematrix_%s.genes.txt", nm)))
  writeLines(colnames(m), file.path(W, sprintf("genematrix_%s.cols.txt", nm)))
  cat(nm, "done\n"); flush(stdout())
}
cat("DONE\n")
