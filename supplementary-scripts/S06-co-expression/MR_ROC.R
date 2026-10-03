library("gplots")
library("ROCR")
pdf("ROC_mr2.pdf")
go.data2=read.delim("data_ROC_in20_MR")
go.data3=read.delim("data_ROC_in30_MR")
go.data4=read.delim("data_ROC_in40_MR")
go.data5=read.delim("data_ROC_in50_MR")
go.data10=read.delim("data_ROC_in100_MR")
go.data2 <- go.data2[order(go.data2[, 1], decreasing = TRUE), ]
go.data3 <- go.data3[order(go.data3[, 1], decreasing = TRUE), ]
go.data4 <- go.data4[order(go.data4[, 1], decreasing = TRUE), ]
go.data5 <- go.data5[order(go.data5[, 1], decreasing = TRUE), ]
go.data10 <- go.data10[order(go.data10[, 1], decreasing = TRUE), ]

pred.go=prediction(go.data3[,1],go.data3[,2]) 
pref.go=performance(pred.go,"tpr","fpr")
auc.go6=performance(pred.go,"auc")@y.values
auc.go6
library("pROC")
citation("pROC")
library("pROC")

plot.roc(go.data3[,2],go.data3[,1], print.thres=FALSE, col="red")
roc(go.data3[,2],go.data3[,1])
lines.roc(go.data2[,2],go.data2[,1], col="brown")
lines.roc(go.data4[,2],go.data4[,1], col="black")
lines.roc(go.data5[,2],go.data5[,1], col="yellow")
lines.roc(go.data10[,2],go.data10[,1], col="blue")

legend("topleft",legend=c("MR 20","MR 30","MR 40","MR 50","MR 100"),col=c("brown","red","black","yellow","blue"),pch=15,bty="o")

roc(go.data2[,2],go.data2[,1])
roc(go.data3[,2],go.data3[,1])
roc(go.data4[,2],go.data4[,1])
roc(go.data5[,2],go.data5[,1])
roc(go.data10[,2],go.data10[,1])

legend("bottomright",legend=c("AUC:0.7806","AUC:0.7909","AUC:0.7885","AUC:0.7811","AUC:0.7914","AUC:0.794"),col=c("brown","red","pink","black","yellow","blue"),pch=15,bty="o")

dev.off()