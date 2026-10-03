import os
import re
import pandas as pd

# 1. 定義您的所有樣本文件
gtf_files = [
    "SRR22214401.gtf","SRR22214472.gtf","SRR22214473.gtf","SRR22214474.gtf","SRR22214475.gtf","SRR22214476.gtf","SRR22214477.gtf","SRR22214478.gtf","SRR22214479.gtf","SRR22214480.gtf","SRR22214482.gtf","SRR22214483.gtf","SRR22214484.gtf","SRR22214485.gtf","SRR22214486.gtf","SRR22214487.gtf","SRR22214488.gtf","SRR22214489.gtf","SRR22214490.gtf","SRR22214491.gtf","SRR22214493.gtf","SRR22214494.gtf","SRR22214495.gtf","SRR22214496.gtf","SRR22214497.gtf","SRR22214498.gtf","SRR22214499.gtf","SRR22214500.gtf","SRR22214501.gtf","SRR22214502.gtf","SRR22214503.gtf","SRR22214504.gtf","SRR22214505.gtf","SRR22214507.gtf","SRR22214508.gtf","SRR22214509.gtf","SRR22214510.gtf","SRR22214511.gtf","SRR22214512.gtf","SRR22214513.gtf","SRR22214514.gtf","SRR22214515.gtf","SRR22214516.gtf","SRR22214517.gtf","SRR22214518.gtf","SRR22214519.gtf","SRR22214520.gtf","SRR22214521.gtf","SRR22214522.gtf","SRR22214523.gtf","SRR22214525.gtf","SRR22214526.gtf","SRR22214527.gtf","SRR22214528.gtf","SRR22214529.gtf","SRR22214530.gtf","SRR22214531.gtf","SRR22214532.gtf","SRR22214533.gtf","SRR22214534.gtf","SRR22214536.gtf","SRR22214537.gtf","SRR22214538.gtf","SRR22214539.gtf","SRR22214540.gtf","SRR22214541.gtf","SRR22214542.gtf","SRR22214543.gtf","SRR22214544.gtf","SRR22214545.gtf"
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

output_file = f"OFAVE_expressionmatrix.csv"
df.to_csv(output_file)
print(f"成功！表達矩陣已儲存至: {output_file}")
