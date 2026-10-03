import os
import re
import pandas as pd

# 1. 定義您的所有樣本文件
gtf_files = [
    "SRR10674706.gtf","SRR10674707.gtf","SRR10674708.gtf","SRR10674709.gtf","SRR10674710.gtf","SRR10674711.gtf","SRR10674712.gtf","SRR10674713.gtf","SRR10674714.gtf","SRR10674715.gtf","SRR10674716.gtf","SRR10674717.gtf","SRR10674718.gtf","SRR10674719.gtf","SRR10674720.gtf","SRR10674721.gtf","SRR10674722.gtf","SRR10674723.gtf","SRR10674724.gtf","SRR10674725.gtf","SRR10674726.gtf","SRR10674727.gtf","SRR10674728.gtf","SRR10674729.gtf","SRR10674730.gtf","SRR10674731.gtf","SRR10674732.gtf","SRR10674733.gtf","SRR10674734.gtf","SRR10674735.gtf","SRR10674736.gtf","SRR10674737.gtf","SRR10674738.gtf","SRR10674739.gtf","SRR10674740.gtf","SRR10674741.gtf","SRR10674742.gtf","SRR10674743.gtf","SRR10674744.gtf","SRR10674745.gtf","SRR10674746.gtf","SRR10674747.gtf","SRR10674748.gtf","SRR10674749.gtf","SRR10674750.gtf","SRR10674751.gtf","SRR10674752.gtf","SRR10674753.gtf","SRR10674754.gtf"
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

output_file = f"APOCU_expressionmatrix.csv"
df.to_csv(output_file)
print(f"成功！表達矩陣已儲存至: {output_file}")
