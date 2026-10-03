import os
import re
import pandas as pd

# 1. 定義您的所有樣本文件
gtf_files = [
    "SRR6202203.gtf","SRR6202204.gtf","SRR6202205.gtf","SRR6202206.gtf","SRR6202207.gtf","SRR6202208.gtf","SRR6202209.gtf","SRR6202210.gtf","SRR6202211.gtf","SRR6202212.gtf","SRR6202233.gtf","SRR6202234.gtf","SRR6202235.gtf","SRR6202236.gtf","SRR6202237.gtf","SRR6202238.gtf","SRR6202239.gtf","SRR6202240.gtf","SRR6202241.gtf","SRR6202242.gtf","SRR6202254.gtf","SRR6202255.gtf","SRR6202256.gtf","SRR6202257.gtf","SRR6202258.gtf","SRR6202259.gtf","SRR6202260.gtf","SRR6202261.gtf","SRR6202262.gtf","SRR6202263.gtf","SRR6202276.gtf","SRR6202277.gtf","SRR6202278.gtf","SRR6202279.gtf","SRR6202280.gtf","SRR6202281.gtf","SRR6202282.gtf","SRR6202283.gtf","SRR6202284.gtf","SRR6202285.gtf","SRR6202303.gtf","SRR6202304.gtf","SRR6202305.gtf","SRR6202306.gtf","SRR6202307.gtf","SRR6202308.gtf","SRR6202309.gtf","SRR6202310.gtf","SRR6202337.gtf","SRR6202338.gtf","SRR6202339.gtf","SRR6202340.gtf","SRR6202341.gtf","SRR6202342.gtf","SRR6202343.gtf","SRR6202344.gtf","SRR6202345.gtf","SRR6202346.gtf","SRR6202347.gtf","SRR6202348.gtf","SRR6202349.gtf","SRR6202350.gtf","SRR6202351.gtf","SRR6202352.gtf","SRR6202353.gtf","SRR6202354.gtf","SRR6202355.gtf","SRR6202356.gtf","SRR6202357.gtf","SRR6202358.gtf","SRR6202363.gtf","SRR6202364.gtf"
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

output_file = f"EDIAP_expressionmatrix.csv"
df.to_csv(output_file)
print(f"成功！表達矩陣已儲存至: {output_file}")
