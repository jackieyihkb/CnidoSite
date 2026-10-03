##安装WGCNA包
#source("http://bioconductor.org/biocLite.R") 
#biocLite(c("AnnotationDbi", "impute", "GO.db", "preprocessCore")) 
#install.packages("WGCNA")

library(WGCNA)
options(stringsAsFactors = FALSE)
enableWGCNAThreads()
fpkm<-read.table("bpl_expression_matrix_no0.txt",head=T,sep="\t",row.names=1)
datExpr=as.data.frame(t(fpkm[,1:dim(fpkm)[2]]))
dim(datExpr)
pccMat = adjacency(datExpr, power = 1,type="sign")
name<-rownames(pccMat)
n<-length(name)
diag(pccMat)<-2
pccRankMat<-matrix(,ncol=n,nrow=n) 
for(i in 1:n){pccRankMat[i,]<-rank(-pccMat[i,],ties.method="min")}  #之前用的ties.method="first";计算MR,再来一个矩阵


##输出MR<100的筛选结果
funMR<-function(x){
pcctemp<-matrix(,nrow=1,ncol=7)
for(j in 2:x-1){
a<-pccRankMat[x,j]-1
b<-pccRankMat[j,x]-1
pccV<-pccMat[x,j]
if(pccV>0){
   MRpos<-sqrt(a*b)
   if((a<=3 || b<=3))
     {pcctemp[1,]<-c(name[x],name[j],pccV,MRpos,"top",a,b)
     write.table(pcctemp, file = "pos.mr_0_100.out", row.names = F, col.names=F,quote = F, sep="\t",append=TRUE)}
   else if(MRpos<5)
     {pcctemp[1,]<-c(name[x],name[j],pccV,MRpos,"L1",a,b)
     write.table(pcctemp, file = "pos.mr_0_100.out", row.names = F, col.names=F,quote = F, sep="\t",append=TRUE)}
   else if(MRpos>=5 && MRpos<30)
     {pcctemp[1,]<-c(name[x],name[j],pccV,MRpos,"L2",a,b)
     write.table(pcctemp, file = "pos.mr_0_100.out", row.names = F, col.names=F,quote = F, sep="\t",append=TRUE)}
   else if(MRpos>=30 && MRpos<100)
     {pcctemp[1,]<-c(name[x],name[j],pccV,MRpos,"L4",a,b)
     write.table(pcctemp, file = "pos.mr_0_100.out", row.names = F, col.names=F,quote = F, sep="\t",append=TRUE)}
  }
else if(pccV<0){
   c<-n-a
   d<-n-b
   MRneg<-sqrt(c*d)
   if((c<=3 || d<=3))
    {pcctemp[1,]<-c(name[x],name[j],pccV,MRneg,"top",c,d)
     write.table(pcctemp, file = "neg.mr_0_100.out", row.names = F, col.names=F,quote = F, sep="\t",append=TRUE)}
   else if(MRneg<5)
    {pcctemp[1,]<-c(name[x],name[j],pccV,MRneg,"L1",c,d)
     write.table(pcctemp, file = "neg.mr_0_100.out", row.names = F, col.names=F,quote = F, sep="\t",append=TRUE)}
   else if(MRneg>=5 && MRneg<30)
    {pcctemp[1,]<-c(name[x],name[j],pccV,MRneg,"L2",c,d)
     write.table(pcctemp, file = "neg.mr_0_100.out", row.names = F, col.names=F,quote = F, sep="\t",append=TRUE)}
   else if(MRneg>=30 && MRneg<100)
    {pcctemp[1,]<-c(name[x],name[j],pccV,MRneg,"L4",c,d)
     write.table(pcctemp, file = "neg.mr_0_100.out", row.names = F, col.names=F,quote = F, sep="\t",append=TRUE)}
  } 
}}
library("parallel")
mc<- getOption("mc.cores",5)  
mclapply(2:n,funMR,mc.cores=mc)



####输出pcc筛选的结果，只需要输出pcc大于6的结果画个图，pcc小于-6的不能画图，输出这个指示为了画ROC曲线。
funPcc6<-function(x){
pcctemp<-matrix(,nrow=1,ncol=7)
for(j in 2:x-1){
a<-pccRankMat[x,j]-1
b<-pccRankMat[j,x]-1
pccV<-pccMat[x,j]
if(pccV>0.6){
   MRpos<-sqrt(a*b)   
   pcctemp[1,]<-c(name[x],name[j],pccV,MRpos,"LLL",a,b)
   write.table(pcctemp, file = "pos_pcc_dayu6.out", row.names = F, col.names=F,quote = F, sep="\t",append=TRUE)
   }
}}
library("parallel")
mc<- getOption("mc.cores",5)
mclapply(2:n,funPcc6,mc.cores=mc)


##输出PCC top300的筛选结果
funMR<-function(x){
pcctemp<-matrix(,nrow=1,ncol=7)
for(j in 2:x-1){
a<-pccRankMat[x,j]-1
b<-pccRankMat[j,x]-1
pccV<-pccMat[x,j]
if(pccV>0){
   if(a<=300 || b<=300)
     {   
	 MRpos<-sqrt(a*b);
	 pcctemp[1,]<-c(name[x],name[j],pccV,MRpos,"top300",a,b)
     write.table(pcctemp, file = "pos.pcc_top300.out", row.names = F, col.names=F,quote = F, sep="\t",append=TRUE)
	 }
  }
else if(pccV<0){
   c<-n-a
   d<-n-b
   if(c<=300 || d<=300)
    {  
	 MRneg<-sqrt(c*d)
	 pcctemp[1,]<-c(name[x],name[j],pccV,MRneg,"top300",c,d)
     write.table(pcctemp, file = "neg.pcc_top300.out", row.names = F, col.names=F,quote = F, sep="\t",append=TRUE)
	}
  } 
}}
library("parallel")
mc<- getOption("mc.cores",5)
mclapply(2:n,funMR,mc.cores=mc)



##########
###画PCC的分布图
b1<- sum(pccMat>=-1 & pccMat<(-0.95))
b95<- sum(pccMat>=-0.95 & pccMat<(-0.9))
b90<- sum(pccMat>=-0.9 & pccMat<(-0.85))
b85<- sum(pccMat>=-0.85 & pccMat<(-0.8))
b80<- sum(pccMat>=-0.8 & pccMat<(-0.75))
b75<- sum(pccMat>=-0.75 & pccMat<(-0.7))
b70<- sum(pccMat>=-0.7 & pccMat<(-0.65))
b65<- sum(pccMat>=-0.65 & pccMat<(-0.6))
b60<- sum(pccMat>=-0.6 & pccMat<(-0.55))
b55<- sum(pccMat>=-0.55 & pccMat<(-0.5))
b50<- sum(pccMat>=-0.5 & pccMat<(-0.45))
b45<- sum(pccMat>=-0.45 & pccMat<(-0.4))
b40<- sum(pccMat>=-0.4 & pccMat<(-0.35))
b35<- sum(pccMat>=-0.35 & pccMat<(-0.3))
b30<- sum(pccMat>=-0.3 & pccMat<(-0.25))
b25<- sum(pccMat>=-0.25 & pccMat<(-0.2))
b20<- sum(pccMat>=-0.2 & pccMat<(-0.15))
b15<- sum(pccMat>=-0.15 & pccMat<(-0.1))
b10<- sum(pccMat>=-0.1 & pccMat<(-0.05))
b05<- sum(pccMat>=-0.05 & pccMat<0)
a0<- sum(pccMat>=0 & pccMat<0.05)
a05<- sum(pccMat>=0.05 & pccMat<0.1)
a10<- sum(pccMat>=0.1 & pccMat<0.15)
a15<- sum(pccMat>=0.15 & pccMat<0.2)
a20<- sum(pccMat>=0.2 & pccMat<0.25)
a25<- sum(pccMat>=0.25 & pccMat<0.3)
a30<- sum(pccMat>=0.3 & pccMat<0.35)
a35<- sum(pccMat>=0.35 & pccMat<0.4)
a40<- sum(pccMat>=0.4 & pccMat<0.45)
a45<- sum(pccMat>=0.45 & pccMat<0.5)
a50<- sum(pccMat>=0.5 & pccMat<0.55)
a55<- sum(pccMat>=0.55 & pccMat<0.6)
a60<- sum(pccMat>=0.6 & pccMat<0.65)
a65<- sum(pccMat>=0.65 & pccMat<0.7)
a70<- sum(pccMat>=0.7 & pccMat<0.75)
a75<- sum(pccMat>=0.75 & pccMat<0.8)
a80<- sum(pccMat>=0.8 & pccMat<0.85)
a85<- sum(pccMat>=0.85 & pccMat<0.9)
a90<- sum(pccMat>=0.9 & pccMat<0.95)
a95<- sum(pccMat>=0.95 & pccMat<1)

cat(b1,b95,b90,b85,b80,b75,b70,b65,b60,b55,b50,b45,b40,b35,b30,b25,b20,b15,b10,b05,a0,a05,a10,a15,a20,a25,a30,a35,a40,a45,a50,a55,a60,a65,a70,a75,a80,a85,a90,a95,sep="\t","\n")
