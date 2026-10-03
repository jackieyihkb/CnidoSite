import os
import re
import pandas as pd

# 1. 定義您的所有樣本文件
gtf_files = [
    "SRR1929581.gtf","SRR1929582.gtf","SRR1929583.gtf","SRR1929584.gtf","SRR1929585.gtf","SRR1929586.gtf","SRR1929587.gtf","SRR1929588.gtf","SRR1929589.gtf","SRR1929590.gtf","SRR1929591.gtf","SRR1929592.gtf","SRR1929593.gtf","SRR1929594.gtf","SRR1929595.gtf","SRR1929596.gtf","SRR1929597.gtf","SRR1929598.gtf","SRR1929599.gtf","SRR1929600.gtf","SRR1929601.gtf","SRR1929602.gtf","SRR1929603.gtf","SRR1929604.gtf","SRR1929605.gtf","SRR1929606.gtf","SRR1929607.gtf","SRR1929608.gtf","SRR1929609.gtf","SRR1929610.gtf","SRR1929611.gtf","SRR1929612.gtf","SRR1929613.gtf","SRR1929614.gtf","SRR1929615.gtf","SRR1929616.gtf","SRR1929617.gtf","SRR1929618.gtf","SRR1929619.gtf","SRR1929620.gtf","SRR1929621.gtf","SRR1929622.gtf","SRR1929623.gtf","SRR1929624.gtf","SRR1929625.gtf","SRR1929626.gtf","SRR1929627.gtf","SRR1929628.gtf","SRR1929629.gtf","SRR1929630.gtf","SRR1929631.gtf","SRR1929632.gtf","SRR1929633.gtf","SRR1929634.gtf"
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

output_file = f"AMILL_expressionmatrix.csv"
df.to_csv(output_file)
print(f"成功！表達矩陣已儲存至: {output_file}")
