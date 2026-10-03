import os
import re
import pandas as pd

# 1. 定義您的所有樣本文件
gtf_files = [
    "SRR6320836.gtf","SRR6320837.gtf","SRR6320838.gtf","SRR6320839.gtf","SRR6320840.gtf","SRR6320841.gtf","SRR6320842.gtf","SRR6320843.gtf","SRR6320844.gtf","SRR6320845.gtf","SRR6320846.gtf","SRR6320847.gtf","SRR6320848.gtf","SRR6320849.gtf","SRR6320850.gtf","SRR6320851.gtf","SRR6320852.gtf","SRR6320853.gtf","SRR6320854.gtf","SRR6320855.gtf","SRR6320856.gtf","SRR6320857.gtf","SRR6320858.gtf","SRR6320859.gtf","SRR6320860.gtf","SRR6320861.gtf","SRR6320862.gtf","SRR6320863.gtf","SRR6320864.gtf","SRR6320865.gtf","SRR6320866.gtf","SRR6320867.gtf","SRR6320868.gtf","SRR6320869.gtf","SRR6320870.gtf","SRR6320871.gtf","SRR6320872.gtf","SRR6320873.gtf","SRR6320874.gtf","SRR6320875.gtf","SRR6320876.gtf","SRR6320877.gtf","SRR6320878.gtf","SRR6320879.gtf","SRR6320880.gtf","SRR6320881.gtf","SRR6320882.gtf","SRR6320883.gtf"
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

output_file = f"NVECT_expressionmatrix.csv"
df.to_csv(output_file)
print(f"成功！表達矩陣已儲存至: {output_file}")
