import os
import re
import pandas as pd

# 1. 定義您的所有樣本文件
gtf_files = [
    "SRR12710846.gtf","SRR12710847.gtf","SRR12710848.gtf","SRR12710855.gtf","SRR12710857.gtf","SRR12710858.gtf","SRR12786896.gtf","SRR12786897.gtf","SRR12786898.gtf","SRR12807381.gtf","SRR12849113.gtf","SRR12904781.gtf","SRR12904782.gtf","SRR12904783.gtf","SRR12927880.gtf","SRR12959182.gtf","SRR12959183.gtf","SRR12959184.gtf","SRR12959188.gtf","SRR12959189.gtf","SRR12959190.gtf","SRR12959201.gtf","SRR12959202.gtf","SRR12959203.gtf","SRR12959214.gtf","SRR12959215.gtf","SRR12959216.gtf","SRR12959227.gtf","SRR12959229.gtf","SRR12959230.gtf","SRR12963484.gtf","SRR27940191.gtf","SRR27940192.gtf","SRR27940193.gtf","SRR9129316.gtf","SRR9613519.gtf"
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

output_file = f"MCAPR_expressionmatrix.csv"
df.to_csv(output_file)
print(f"成功！表達矩陣已儲存至: {output_file}")
