a <- commandArgs(trailingOnly=TRUE)
for (f in a) {
  cat("\n=====", basename(f), "\n")
  x <- tryCatch(readRDS(f), error=function(e) { cat("  read failed:", conditionMessage(e), "\n"); NULL })
  if (is.null(x)) next
  cat("  class:", paste(class(x), collapse=","), " dim:", paste(dim(x), collapse=" x "), "\n")
  md <- tryCatch(x@meta.data, error=function(e) NULL)
  if (is.null(md)) { cat("  no meta.data\n"); next }
  cat("  meta cols:", paste(colnames(md), collapse=" | "), "\n")
  for (cl in colnames(md)) {
    v <- md[[cl]]
    if (!is.atomic(v)) next
    u <- unique(v)
    if (length(u) <= 40 && !is.numeric(v)) {
      cat("    ", cl, "->", length(u), "levels:", paste(head(u, 25), collapse=" ; "), "\n")
    }
  }
}
