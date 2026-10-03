import os
import re
import pandas as pd

# 1. 定義您的所有樣本文件
gtf_files = [
    "SRR36435264.gtf","SRR36435265.gtf","SRR36435266.gtf","SRR36435267.gtf","SRR36435268.gtf","SRR36435269.gtf","SRR36435270.gtf","SRR36435271.gtf","SRR36435272.gtf","SRR36435273.gtf","SRR36435274.gtf","SRR36435275.gtf","SRR36435276.gtf","SRR36435277.gtf","SRR36435278.gtf","SRR36435279.gtf","SRR36435280.gtf","SRR36435281.gtf","SRR36435282.gtf","SRR36435283.gtf","SRR36435284.gtf","SRR36435285.gtf","SRR36435286.gtf","SRR36435287.gtf","SRR36435288.gtf","SRR36435289.gtf","SRR36435290.gtf","SRR36435291.gtf","SRR36435292.gtf","SRR36435293.gtf","SRR36435294.gtf","SRR36435295.gtf","SRR36435296.gtf","SRR36435297.gtf","SRR36435298.gtf","SRR36435299.gtf","SRR36435300.gtf","SRR36435301.gtf","SRR36435302.gtf","SRR36435303.gtf","SRR36435304.gtf","SRR36435305.gtf","SRR36435306.gtf","SRR36435307.gtf","SRR36435308.gtf","SRR36435309.gtf","SRR36435310.gtf","SRR36435311.gtf","SRR36435312.gtf","SRR36435313.gtf","SRR36435314.gtf","SRR36435315.gtf","SRR36435316.gtf","SRR36435317.gtf","SRR36435318.gtf","SRR36435319.gtf","SRR36435320.gtf","SRR36435321.gtf","SRR36435322.gtf","SRR36435323.gtf"
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

output_file = f"HVULG_expressionmatrix.csv"
df.to_csv(output_file)
print(f"成功！表達矩陣已儲存至: {output_file}")
