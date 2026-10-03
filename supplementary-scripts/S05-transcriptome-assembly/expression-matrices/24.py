import os
import re
import pandas as pd

# 1. 定義您的所有樣本文件
gtf_files = [
    "SRR24482133.gtf","SRR24482134.gtf","SRR24482135.gtf","SRR24482136.gtf","SRR24482137.gtf","SRR24482138.gtf","SRR24482139.gtf","SRR24482140.gtf","SRR24482141.gtf","SRR24482142.gtf","SRR24482143.gtf","SRR24482144.gtf","SRR24482145.gtf","SRR24482146.gtf","SRR24482147.gtf","SRR24482148.gtf","SRR24482149.gtf","SRR24482150.gtf","SRR24482151.gtf","SRR24482152.gtf","SRR24482153.gtf","SRR24482154.gtf","SRR24482155.gtf","SRR24482156.gtf","SRR24482157.gtf","SRR24482158.gtf","SRR24482159.gtf","SRR24482160.gtf","SRR24482161.gtf","SRR24482162.gtf","SRR24482163.gtf","SRR24482165.gtf","SRR24482166.gtf","SRR24482167.gtf","SRR24482168.gtf","SRR24482169.gtf","SRR24482170.gtf","SRR24482171.gtf","SRR24482172.gtf","SRR24482173.gtf","SRR24482174.gtf","SRR24482175.gtf","SRR24482176.gtf","SRR24482177.gtf"
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

output_file = f"HSYMB_expressionmatrix.csv"
df.to_csv(output_file)
print(f"成功！表達矩陣已儲存至: {output_file}")
