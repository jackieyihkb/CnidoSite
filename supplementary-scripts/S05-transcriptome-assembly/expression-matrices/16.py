import os
import re
import pandas as pd

# 1. 定義您的所有樣本文件
gtf_files = [
    "SRR25982246.gtf","SRR25982247.gtf","SRR25982248.gtf","SRR25982249.gtf","SRR25982250.gtf","SRR25982251.gtf","SRR25982252.gtf","SRR25982253.gtf","SRR25982254.gtf","SRR25982255.gtf","SRR25982256.gtf","SRR25982257.gtf","SRR25982258.gtf","SRR25982259.gtf","SRR25982260.gtf","SRR25982261.gtf","SRR25982262.gtf","SRR25982263.gtf","SRR25982264.gtf","SRR25982265.gtf","SRR25982266.gtf","SRR25982267.gtf","SRR25982268.gtf","SRR25982269.gtf","SRR25982270.gtf","SRR25982271.gtf","SRR25982272.gtf","SRR25982273.gtf","SRR25982274.gtf","SRR25982275.gtf"
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

output_file = f"ACOER_expressionmatrix.csv"
df.to_csv(output_file)
print(f"成功！表達矩陣已儲存至: {output_file}")
