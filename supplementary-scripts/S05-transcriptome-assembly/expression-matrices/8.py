import os
import re
import pandas as pd

# 1. 定義您的所有樣本文件
gtf_files = [
    "SRR4029951.gtf","SRR4029952.gtf","SRR4029953.gtf","SRR4029954.gtf","SRR4029955.gtf","SRR4029956.gtf","SRR4029957.gtf","SRR4029958.gtf","SRR4029959.gtf","SRR4029960.gtf","SRR4029961.gtf","SRR4029962.gtf","SRR4029963.gtf","SRR4029964.gtf","SRR4029965.gtf","SRR4029966.gtf","SRR4029967.gtf","SRR4029968.gtf","SRR4029969.gtf","SRR4029970.gtf","SRR4029971.gtf","SRR4029972.gtf","SRR4029973.gtf","SRR4029974.gtf","SRR4029975.gtf","SRR4029976.gtf","SRR4029977.gtf","SRR4029978.gtf","SRR4029979.gtf","SRR4029980.gtf","SRR4029981.gtf","SRR4029982.gtf","SRR4029983.gtf","SRR4029984.gtf","SRR4029985.gtf","SRR4029986.gtf","SRR4029987.gtf","SRR4029988.gtf","SRR4029989.gtf","SRR4029990.gtf","SRR4029991.gtf","SRR4029992.gtf","SRR4029993.gtf","SRR4029994.gtf","SRR4029995.gtf","SRR4029996.gtf","SRR4029997.gtf","SRR4029998.gtf","SRR4029999.gtf","SRR4030000.gtf","SRR4030001.gtf"
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

output_file = f"AHYAC_expressionmatrix.csv"
df.to_csv(output_file)
print(f"成功！表達矩陣已儲存至: {output_file}")
