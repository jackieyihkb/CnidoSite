import os
import re
import pandas as pd

# 1. 定義您的所有樣本文件
gtf_files = [
    "SRR23047206.gtf","SRR23047207.gtf","SRR23047208.gtf","SRR23047209.gtf","SRR23047210.gtf","SRR23047211.gtf","SRR23047212.gtf","SRR23047213.gtf","SRR23047214.gtf","SRR23047215.gtf","SRR23047216.gtf","SRR23047217.gtf","SRR23047218.gtf","SRR23047219.gtf","SRR23047220.gtf","SRR23047221.gtf","SRR23047222.gtf","SRR23047223.gtf","SRR23047224.gtf","SRR23047225.gtf","SRR23047226.gtf","SRR23047227.gtf","SRR23047228.gtf","SRR23047229.gtf","SRR23047230.gtf","SRR23047231.gtf","SRR23047232.gtf","SRR23047233.gtf","SRR23047234.gtf","SRR23047235.gtf","SRR23047236.gtf","SRR23047237.gtf","SRR23047238.gtf","SRR23047239.gtf","SRR23047240.gtf","SRR23047241.gtf","SRR23047242.gtf","SRR23047243.gtf","SRR23047244.gtf"
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

output_file = f"ADIGI_expressionmatrix.csv"
df.to_csv(output_file)
print(f"成功！表達矩陣已儲存至: {output_file}")
