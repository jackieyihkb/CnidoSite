suppressPackageStartupMessages(library(Seurat))
obj <- readRDS("data/raw/GSE302686/GSE302686_nv2DevInt.rds")
Idents(obj) <- obj$SCT_snn_res.0.2
cat("Idents:", paste(levels(Idents(obj)), collapse=","), "\n")
cat("cells/ident:\n"); print(table(Idents(obj)))
cat("cells/ident x sample:\n"); print(table(Idents(obj), obj$orig.ident))
obj <- PrepSCTFindMarkers(obj)
m <- FindAllMarkers(obj, assay="SCT", only.pos=TRUE,
                    min.pct=0.25, logfc.threshold=0.25, verbose=TRUE)
cat("FindAllMarkers cols:", paste(colnames(m), collapse=","), "\n")
fc <- names(m)[grepl("log2FC", names(m))][1]
cat("ordering by:", fc, "\n")
m <- m[order(m$cluster, -m[[fc]]), ]
write.table(m, "work/nvect_atlas/GSE302686.author_cluster_markers.tsv",
            sep="\t", quote=FALSE, row.names=FALSE)
top <- do.call(rbind, lapply(split(m, m$cluster), function(d) head(d, 25)))
write.table(top, "work/nvect_atlas/GSE302686.author_cluster_top25.tsv",
            sep="\t", quote=FALSE, row.names=FALSE)
cat("WROTE rows:", nrow(m), " and top25 per cluster\n")
