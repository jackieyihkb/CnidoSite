import os
import re
import pandas as pd

# 1. 定義您的所有樣本文件
gtf_files = [
    "ERR12861049.gtf","SRR12454619.gtf","SRR14295600.gtf","SRR14295601.gtf","SRR14295603.gtf","SRR14295604.gtf","SRR22214400.gtf","SRR22214402.gtf","SRR22214403.gtf","SRR22214404.gtf","SRR22214405.gtf","SRR22214406.gtf","SRR22214407.gtf","SRR22214408.gtf","SRR22214409.gtf","SRR22214410.gtf","SRR22214411.gtf","SRR22214412.gtf","SRR22214413.gtf","SRR22214414.gtf","SRR22214415.gtf","SRR22214416.gtf","SRR22214417.gtf","SRR22214418.gtf","SRR22214419.gtf","SRR22214420.gtf","SRR22214421.gtf","SRR22214422.gtf","SRR22214423.gtf","SRR22214424.gtf","SRR22214425.gtf","SRR22214426.gtf","SRR22214427.gtf","SRR22214428.gtf","SRR22214429.gtf","SRR22214430.gtf","SRR22214431.gtf","SRR22214432.gtf","SRR22214433.gtf","SRR22214434.gtf","SRR22214435.gtf","SRR22214436.gtf","SRR22214437.gtf","SRR22214438.gtf","SRR22214439.gtf","SRR22214440.gtf","SRR22214441.gtf","SRR22214442.gtf","SRR22214443.gtf","SRR22214444.gtf","SRR22214445.gtf","SRR22214446.gtf","SRR22214447.gtf","SRR22214448.gtf","SRR22214449.gtf","SRR22214450.gtf","SRR22214451.gtf","SRR22214452.gtf","SRR22214453.gtf","SRR22214454.gtf","SRR22214455.gtf","SRR22214456.gtf","SRR22214457.gtf","SRR22214458.gtf","SRR22214459.gtf","SRR22214460.gtf","SRR22214461.gtf","SRR22214462.gtf","SRR22214463.gtf","SRR22214464.gtf","SRR22214465.gtf","SRR22214466.gtf","SRR22214467.gtf","SRR22214468.gtf","SRR22214469.gtf","SRR22214470.gtf","SRR22214471.gtf","SRR22214481.gtf","SRR22214492.gtf","SRR22214506.gtf","SRR22214524.gtf","SRR22214535.gtf","SRR22214546.gtf","SRR22214547.gtf","SRR22214548.gtf"
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

output_file = f"SSIDE_expressionmatrix.csv"
df.to_csv(output_file)
print(f"成功！表達矩陣已儲存至: {output_file}")
