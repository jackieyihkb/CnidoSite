import os
import re
import pandas as pd

# 1. 定義您的所有樣本文件
gtf_files = [
    "SRR12578063.gtf","SRR12578064.gtf","SRR12578065.gtf","SRR12578066.gtf","SRR12578067.gtf","SRR12578068.gtf","SRR12587798.gtf","SRR12587799.gtf","SRR12587800.gtf","SRR12587801.gtf","SRR12587802.gtf","SRR12587803.gtf","SRR12587804.gtf","SRR12587805.gtf","SRR12587806.gtf","SRR12587807.gtf","SRR12587808.gtf","SRR5949848.gtf","SRR5949849.gtf","SRR5949850.gtf","ERR6178387.gtf","ERR6178388.gtf","ERR6178389.gtf","ERR6178770.gtf","ERR6178771.gtf","ERR6178772.gtf","ERR6178773.gtf","ERR6178774.gtf","ERR6178775.gtf","ERR6178776.gtf","ERR6178777.gtf","ERR6178778.gtf","ERR6178779.gtf","ERR6178780.gtf","ERR6178781.gtf","ERR6178782.gtf","ERR6178783.gtf"
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

output_file = f"HCOER_expressionmatrix.csv"
df.to_csv(output_file)
print(f"成功！表達矩陣已儲存至: {output_file}")
