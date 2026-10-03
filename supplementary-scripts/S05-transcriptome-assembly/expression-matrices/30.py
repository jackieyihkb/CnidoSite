import os
import re
import pandas as pd

# 1. 定義您的所有樣本文件
gtf_files = [
    "SRR25247782.gtf","SRR25247791.gtf","SRR25247802.gtf","SRR25247813.gtf","SRR25247824.gtf","SRR25247835.gtf","SRR25247836.gtf","SRR25247837.gtf","SRR25247839.gtf","SRR25247840.gtf","SRR25247841.gtf","SRR25247842.gtf","SRR25247843.gtf","SRR25247844.gtf","SRR25247845.gtf","SRR25247846.gtf","SRR25247847.gtf","SRR25247848.gtf","SRR25247849.gtf","SRR25247850.gtf","SRR25247851.gtf","SRR25247852.gtf","SRR25247853.gtf","SRR25247854.gtf","SRR25247855.gtf","SRR25247856.gtf","SRR25247857.gtf","SRR25247858.gtf","SRR25247859.gtf","SRR25247860.gtf","SRR25247861.gtf","SRR25247862.gtf","SRR25247863.gtf","SRR25247864.gtf","SRR25247865.gtf","SRR25247866.gtf","SRR25247867.gtf","SRR25247868.gtf","SRR25247869.gtf"
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

output_file = f"OFRAN_expressionmatrix.csv"
df.to_csv(output_file)
print(f"成功！表達矩陣已儲存至: {output_file}")
