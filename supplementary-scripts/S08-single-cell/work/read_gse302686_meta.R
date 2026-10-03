suppressPackageStartupMessages(library(Seurat))
obj <- readRDS("data/raw/GSE302686/GSE302686_nv2DevInt.rds")
cat("class:", paste(class(obj), collapse=","), "\n")
cat("dim:", dim(obj)[1], "genes x", dim(obj)[2], "cells\n")
cat("assays:", paste(names(obj@assays), collapse=", "), "\n")
cat("reductions:", paste(names(obj@reductions), collapse=", "), "\n")
cat("Idents levels:", length(levels(Idents(obj))), "\n")
print(head(levels(Idents(obj)), 30))
md <- obj@meta.data
cat("meta cols:", paste(colnames(md), collapse=" | "), "\n")
cat("--- head ---\n"); print(head(md, 3))
for (cn in colnames(md)) {
  v <- md[[cn]]
  if (is.character(v) || is.factor(v)) {
    u <- unique(as.character(v))
    cat(sprintf("COL %s: %d unique\n", cn, length(u)))
    if (length(u) <= 40) print(sort(table(as.character(v)), decreasing=TRUE))
  }
}
out <- data.frame(cell=rownames(md), md, check.names=FALSE)
write.table(out, "work/nvect_atlas/GSE302686.meta.tsv", sep="\t", quote=FALSE, row.names=FALSE)
cat("WROTE work/nvect_atlas/GSE302686.meta.tsv\n")
