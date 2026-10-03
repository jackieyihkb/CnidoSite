import os
import re
import pandas as pd

# 1. 定義您的所有樣本文件
gtf_files = [
    "SRR8800026.gtf","SRR8800027.gtf","SRR8800028.gtf","SRR8800029.gtf","SRR8800030.gtf","SRR8800031.gtf","SRR8800032.gtf","SRR8800033.gtf","SRR8800034.gtf","SRR8800035.gtf","SRR8800036.gtf","SRR8800037.gtf","SRR8800038.gtf","SRR8800039.gtf","SRR8800040.gtf","SRR8800041.gtf","SRR8800042.gtf","SRR8800043.gtf","SRR8800044.gtf","SRR8800045.gtf","SRR8800046.gtf","SRR8800047.gtf","SRR8800048.gtf","SRR8800049.gtf","SRR8800050.gtf","SRR8800051.gtf","SRR8800052.gtf","SRR8800053.gtf","SRR8800054.gtf","SRR8800055.gtf","SRR8800056.gtf","SRR8800057.gtf","SRR8800058.gtf","SRR8800059.gtf","SRR8800060.gtf","SRR8800061.gtf","SRR8800062.gtf","SRR8800063.gtf","SRR8800064.gtf","SRR8800065.gtf","SRR8800066.gtf","SRR8800067.gtf","SRR8800068.gtf","SRR8800069.gtf","SRR8800070.gtf","SRR8800071.gtf","SRR8800072.gtf","SRR8800073.gtf","SRR8800074.gtf","SRR8800075.gtf","SRR8800076.gtf","SRR8800077.gtf","SRR8800078.gtf","SRR8800079.gtf","SRR8800080.gtf","SRR8800081.gtf","SRR8800082.gtf","SRR8800083.gtf","SRR8800084.gtf","SRR8800085.gtf","SRR8800086.gtf","SRR8800087.gtf","SRR8800088.gtf","SRR8800089.gtf","SRR8800090.gtf","SRR8800091.gtf","SRR8800092.gtf","SRR8800093.gtf","SRR8800094.gtf","SRR8800095.gtf","SRR8800096.gtf","SRR8800097.gtf","SRR8800098.gtf","SRR8800099.gtf","SRR8800100.gtf","SRR8800101.gtf","SRR8800102.gtf","SRR8800103.gtf","SRR8800104.gtf","SRR8800105.gtf","SRR8800106.gtf","SRR8800107.gtf","SRR8800108.gtf","SRR8800109.gtf"
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

output_file = f"APALM_expressionmatrix.csv"
df.to_csv(output_file)
print(f"成功！表達矩陣已儲存至: {output_file}")
