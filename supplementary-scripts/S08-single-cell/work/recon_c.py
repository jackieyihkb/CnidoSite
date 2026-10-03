import numpy as np, pandas as pd, os
W = "/mnt/sda/jackie/cnidaria/codex/singlecell/work"

genes = open(f"{W}/genematrix_SEACell.genes.txt").read().split("\n")[:-1]
cols  = open(f"{W}/genematrix_SEACell.cols.txt").read().split("\n")[:-1]
M = np.fromfile(f"{W}/genematrix_SEACell.bin", dtype="<f4").reshape(len(genes), len(cols), order="F")
print("SEACell matrix:", M.shape, "dtype", M.dtype)
print("  zeros %%: %.1f   min %.3g  max %.3g  mean(nonzero) %.3g" % (
    100*(M==0).mean(), M.min(), M.max(), M[M>0].mean()))
print("  genes sample:", genes[:3], " cols sample:", cols[:3])

cc = pd.read_csv(f"{W}/repo/cellColData.tsv", index_col=0, low_memory=False)
print("\ncellColData:", cc.shape); print("  cols:", list(cc.columns))
print("  cell_type nunique:", cc['cell_type'].nunique())
print("  Sample nunique:", cc['Sample'].nunique(), sorted(cc['Sample'].astype(str).unique())[:12])

ann = pd.read_csv(f"{W}/repo/SEACell_annotation.tsv", sep="\t")
print("\nSEACell_annotation:", ann.shape, list(ann.columns))
print("  stage:", ann['stage'].value_counts().to_dict())
print("  n SEACell:", ann['SEACell'].nunique())
print("  n SEACell adult:", ann.loc[ann.stage=='adult','SEACell'].nunique())
print("  cell_type nunique:", ann['cell_type'].nunique())

um = pd.read_csv(f"{W}/repo/X_umap.cells.csv", index_col=0)
print("\nX_umap.cells:", um.shape, list(um.columns))
us = pd.read_csv(f"{W}/repo/X_umap.seacells.csv", index_col=0)
print("X_umap.seacells:", us.shape, list(us.columns))

adult = set(ann.loc[ann.stage=='adult','cell'].astype(str))
print("\nadult cells in ann:", len(adult))
print("  in cellColData:", len(adult & set(cc.index.astype(str))))
print("  in umap:", len(adult & set(um.index.astype(str))))
sac = ann[ann.stage=='adult'].drop_duplicates('SEACell').set_index('SEACell')
print("  adult SEACells present in matrix cols:", len(set(sac.index.astype(str)) & set(cols)))
print("\n  top cell types (per-cell, adult):")
sub = cc.loc[list(adult & set(cc.index.astype(str)))]
print(sub['cell_type'].value_counts().to_string())
