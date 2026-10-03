import os
import re
import pandas as pd

# 1. 定義您的所有樣本文件
gtf_files = [
    "DRR550233.gtf","DRR550234.gtf","DRR550235.gtf","DRR550236.gtf","DRR550237.gtf","DRR550238.gtf","DRR550239.gtf","DRR550241.gtf","DRR550242.gtf","DRR550243.gtf","DRR550244.gtf","DRR550245.gtf","DRR550246.gtf","DRR550247.gtf","DRR550248.gtf","DRR550249.gtf","DRR550250.gtf","DRR550251.gtf","DRR550252.gtf","DRR550253.gtf","DRR550254.gtf","DRR550255.gtf","DRR550256.gtf","DRR550257.gtf","DRR550258.gtf","DRR550259.gtf","DRR550260.gtf","DRR550261.gtf","DRR550262.gtf","DRR550263.gtf","DRR550264.gtf","DRR550265.gtf","DRR550266.gtf","DRR550267.gtf","DRR550268.gtf","DRR550269.gtf","DRR550270.gtf","DRR550271.gtf","DRR550272.gtf","DRR550273.gtf"
]

# 選擇您想提取的指標: 'tpm' 或 'fpkm'
EXPRESSION_METRIC = 'tpm' 

all_data = {}

print("開始處理 GTF 文件...")
for file in gtf_files:
    if not os.path.exists(file):
        print(f"警告: 找不到文件 {file}，已跳過。")
        continue
        
    sample_name = file.replace(".gtf", "")
    sample_dict = {}
    
    with open(file, 'r') as f:
        for line in f:
            if line.startswith("#"):
                continue
            parts = line.strip().split('\t')
            if len(parts) < 9 or parts[2] != 'transcript':
                continue
                
            attributes = parts[8]
            
            # 使用正則表達式提取 gene_id 和對應的指標
            gene_id_match = re.search(r'gene_id "([^"]+)"', attributes)
            metric_match = re.search(r'{} "([^"]+)"'.format(EXPRESSION_METRIC), attributes, re.IGNORECASE)
            
            if gene_id_match and metric_match:
                gene_id = gene_id_match.group(1)
                val = float(metric_match.group(1))
                
                # 同一個基因可能有複數個轉錄本，此處取總和(與StringTie邏輯一致)
                sample_dict[gene_id] = sample_dict.get(gene_id, 0.0) + val
                
    all_data[sample_name] = sample_dict

# 2. 轉換為 Pandas DataFrame 並保存
print("正在合並矩陣...")
df = pd.DataFrame(all_data).fillna(0)
df.index.name = 'Gene_ID'

output_file = f"ATENU_expressionmatrix.csv"
df.to_csv(output_file)
print(f"成功！表達矩陣已儲存至: {output_file}")
