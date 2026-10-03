import os
import re
import pandas as pd

# 1. 定義您的所有樣本文件
gtf_files = [
    "SRR12710845.gtf","SRR12710852.gtf","SRR12710853.gtf","SRR12710854.gtf","SRR12710865.gtf","SRR12710866.gtf","SRR12786895.gtf","SRR12786903.gtf","SRR12786904.gtf","SRR12807380.gtf","SRR12849112.gtf","SRR12904780.gtf","SRR12904791.gtf","SRR12904792.gtf","SRR12927879.gtf","SRR12959181.gtf","SRR12959185.gtf","SRR12959186.gtf","SRR12959187.gtf","SRR12959198.gtf","SRR12959199.gtf","SRR12959200.gtf","SRR12959211.gtf","SRR12959212.gtf","SRR12959213.gtf","SRR12959224.gtf","SRR12959225.gtf","SRR12959226.gtf","SRR12959237.gtf","SRR12959238.gtf","SRR12963483.gtf","SRR27940177.gtf","SRR27940179.gtf","SRR27940180.gtf","SRR9129315.gtf","SRR9613518.gtf"
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

output_file = f"MFOLI_expressionmatrix.csv"
df.to_csv(output_file)
print(f"成功！表達矩陣已儲存至: {output_file}")
