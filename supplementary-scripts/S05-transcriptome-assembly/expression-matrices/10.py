import os
import re
import pandas as pd

# 1. 定義您的所有樣本文件
gtf_files = [
    "SRR12710849.gtf","SRR12710850.gtf","SRR12710851.gtf","SRR12710859.gtf","SRR12710860.gtf","SRR12710861.gtf","SRR12786899.gtf","SRR12786900.gtf","SRR12786901.gtf","SRR12807382.gtf","SRR12904784.gtf","SRR12904785.gtf","SRR12904786.gtf","SRR12927881.gtf","SRR12959191.gtf","SRR12959192.gtf","SRR12959193.gtf","SRR12959195.gtf","SRR12959204.gtf","SRR12959205.gtf","SRR12959206.gtf","SRR12959207.gtf","SRR12959217.gtf","SRR12959218.gtf","SRR12959219.gtf","SRR12959220.gtf","SRR12959231.gtf","SRR12959232.gtf","SRR12959233.gtf","SRR12995717.gtf","SRR12996627.gtf","SRR12996628.gtf","SRR27868158.gtf","SRR27868159.gtf","SRR27868160.gtf","SRR27868165.gtf","SRR27868174.gtf","SRR27868175.gtf","SRR27868176.gtf","SRR27868177.gtf","SRR27868178.gtf","SRR27868179.gtf","SRR27868180.gtf","SRR27868181.gtf","SRR27868182.gtf","SRR27868183.gtf","SRR27868184.gtf","SRR27868185.gtf","SRR27868186.gtf","SRR27868187.gtf","SRR27868188.gtf","SRR27868189.gtf","SRR27868190.gtf","SRR27868191.gtf","SRR27868192.gtf","SRR27868193.gtf","SRR27868194.gtf","SRR27868195.gtf","SRR27868196.gtf","SRR27868197.gtf","SRR27868198.gtf","SRR27868199.gtf","SRR27868200.gtf","SRR27868201.gtf","SRR27868202.gtf","SRR27868203.gtf","SRR27868204.gtf","SRR27868205.gtf","SRR27868206.gtf","SRR27868207.gtf","SRR27868208.gtf","SRR27868209.gtf","SRR27868210.gtf","SRR27940222.gtf","SRR27940224.gtf","SRR27940225.gtf","SRR27940226.gtf","SRR27940227.gtf","SRR27940228.gtf","SRR27940229.gtf","SRR9613488.gtf","SRR9613516.gtf"
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

output_file = f"AMURI_expressionmatrix.csv"
df.to_csv(output_file)
print(f"成功！表達矩陣已儲存至: {output_file}")
