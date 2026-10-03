import os
import re
import pandas as pd

# 1. 定義您的所有樣本文件
gtf_files = [
    "SRR11452216.gtf","SRR11452217.gtf","SRR11452218.gtf","SRR11452219.gtf","SRR11452220.gtf","SRR11452221.gtf","SRR11452222.gtf","SRR11452223.gtf","SRR11452224.gtf","SRR11452225.gtf","SRR11452226.gtf","SRR11452227.gtf","SRR11452228.gtf","SRR11452229.gtf","SRR11452230.gtf","SRR11452231.gtf","SRR11452232.gtf","SRR11452233.gtf","SRR11452234.gtf","SRR11452235.gtf","SRR11452236.gtf","SRR11452237.gtf","SRR11452238.gtf","SRR11452239.gtf","SRR11452240.gtf","SRR11452241.gtf","SRR11452242.gtf","SRR11452243.gtf","SRR11452244.gtf","SRR11452245.gtf","SRR11452246.gtf","SRR11452247.gtf","SRR11452248.gtf","SRR11452249.gtf","SRR11452250.gtf","SRR11452251.gtf","SRR11452252.gtf","SRR11452253.gtf","SRR11452254.gtf","SRR11452255.gtf","SRR11452256.gtf","SRR11452257.gtf","SRR11452258.gtf","SRR11452259.gtf","SRR11452260.gtf","SRR11452261.gtf","SRR11452262.gtf","SRR11452263.gtf"
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

output_file = f"MCAPI_expressionmatrix.csv"
df.to_csv(output_file)
print(f"成功！表達矩陣已儲存至: {output_file}")
