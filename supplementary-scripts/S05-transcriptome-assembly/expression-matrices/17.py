import os
import re
import pandas as pd

# 1. 定義您的所有樣本文件
gtf_files = [
    "ERR2816230.gtf","ERR2816231.gtf","ERR2816232.gtf","ERR2816233.gtf","ERR2816234.gtf","ERR2816235.gtf","ERR2816236.gtf","ERR2816237.gtf","ERR2816238.gtf","ERR2816239.gtf","ERR2816240.gtf","ERR2816241.gtf","ERR2816242.gtf","ERR2816243.gtf","ERR2816244.gtf","ERR2816245.gtf","ERR2816246.gtf","ERR2816247.gtf","ERR2816248.gtf","ERR2816249.gtf","ERR2816250.gtf","ERR2816251.gtf","ERR2862244.gtf","ERR2862245.gtf","ERR3299471.gtf","ERR3299472.gtf","ERR3299473.gtf","ERR3299474.gtf","ERR3299475.gtf","ERR3299476.gtf","ERR3299477.gtf","ERR3299478.gtf","ERR3299479.gtf","ERR3299480.gtf","ERR3299481.gtf","ERR3299482.gtf","ERR3299483.gtf","ERR3299484.gtf","ERR3299485.gtf","ERR3299486.gtf"
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

output_file = f"CHEMI_expressionmatrix.csv"
df.to_csv(output_file)
print(f"成功！表達矩陣已儲存至: {output_file}")
