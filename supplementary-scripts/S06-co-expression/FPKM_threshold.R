# 读取数据
fpkm <- read.table("Csq_expression_matrix.txt", header=TRUE,sep = "\t")  # 如果有表头，添加 header=TRUE
data <- as.data.frame(fpkm)

# 确保所有列都是数值类型
data <- as.data.frame(lapply(data, as.numeric))

# 动态设置列数
c <- numeric(ncol(data))  # 根据实际列数动态设置

# 计算每列的 5% 分位数
for (i in 1:ncol(data)) {
  a <- data[, i]
  b <- sort(a[a != 0])  # 排除 0 的值
  if (length(b) > 0) {  # 确保非零元素存在
    c[i] <- b[round(0.05 * length(b))]
  } else {
    c[i] <- 0  # 如果列中全为 0，则设置为 0
  }
}

# 计算 cutoff 值
mean_c <- mean(c, na.rm=TRUE)  # 确保忽略 NA
cutoff <- mean_c + 3 * sd(c, na.rm=TRUE)

# 小于 cutoff 的值赋为 0
data[data < cutoff] <- 0

# 删除全为 0 的行
new <- data[rowSums(data) != 0, ]

# 剩下的 FPKM 小于 cutoff 的值赋为 cutoff
new[new < cutoff] <- cutoff

# 写入结果
write.table(new, "Csq_expression_matrix_no0_cutoff.txt", sep="\t", row.names=FALSE, col.names=TRUE)