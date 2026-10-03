library(WGCNA)
options(stringsAsFactors = FALSE)
enableWGCNAThreads()
fpkm<-read.table("bpl_expression_matrix_no0.txt")
# datExpr=as.data.frame(t(fpkm[,1:dim(fpkm)[2]]))
# dim(datExpr)

# #进化树
# sampleTree = hclust(dist(datExpr), method = "average");
# pdf(file = "bpl_hclust.pdf", width = 16, height = 9)
# par(cex = 0.6)
# par(mar = c(0,4,2,0))
# plot(sampleTree, main = "Sample clustering", sub="", xlab="", cex.lab = 1.5, cex.axis = 1.5, cex.main = 2)
# dev.off() 
# 计算样本之间的欧几里德距离
dist_matrix <- dist(fpkm, method = "euclidean")

# 执行聚类分析
sampleTree <- hclust(dist_matrix, method = "average")

# 绘制聚类图
plot(sampleTree, main = "Sample clustering", xlab = "Samples", ylab = "Distance")

# 如果需要绘制热图，可以使用以下代码
heatmap(as.matrix(fpkm), main = "TPM Expression Heatmap", Colv = NA, Rowv = as.dendrogram(sampleTree))