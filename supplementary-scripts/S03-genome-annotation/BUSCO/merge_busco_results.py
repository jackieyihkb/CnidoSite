#!/usr/bin/env python
import os
import glob
import pandas as pd
import re

def split_sequence(seq_id):
    """
    拆分序列ID为物种缩写和基因名
    例如: "AACUM_aacu_s0202.g18.t2" -> ("AACUM", "aacu_s0202.g18.t2")
    逻辑: 以第一个下划线为界
    """
    match = re.match(r'^([^_]+)_(.+)$', str(seq_id))
    if match:
        return match.group(1), match.group(2)
    else:
        # 如果没有下划线，返回空和原值，防止报错
        return "", str(seq_id)

# 1. 获取所有 full_table.tsv 文件
file_pattern = "busco_results/*/run_cnidaria_odb12/full_table.tsv"
files = sorted(glob.glob(file_pattern))

if not files:
    print(f"未找到文件，请检查路径: {file_pattern}")
    exit()

all_data = []

print(f"找到 {len(files)} 个文件，开始处理...")

for file in files:
    try:
        # 读取文件，跳过以 '#' 开头的注释行
        df = pd.read_csv(file, sep='\t', comment='#', header=None)
        
        # 检查是否有足够的数据列
        if df.shape[1] < 7:
            print(f"跳过 {os.path.basename(file)}: 列数不足")
            continue

        # 提取所需的列 (按索引)
        # 0:BUSCO_ID, 1:Status, 2:Sequence, 3:Score, 4:Length, 6:Description
        temp_df = df.iloc[:, [0, 1, 2, 3, 4, 6]].copy()
        
        # 重命名列
        temp_df.columns = ['BUSCO_ID', 'Status', 'Sequence', 'Score', 'Length', 'Description']
        
        # 应用拆分函数
        split_result = temp_df['Sequence'].apply(lambda x: pd.Series(split_sequence(x)))
        split_result.columns = ['物种缩写', '基因名']
        
        # 合并回主数据框
        temp_df = pd.concat([temp_df, split_result], axis=1)
        
        # 移除不需要的列
        temp_df = temp_df.drop('Sequence', axis=1)
        
        # 确保所有数值列类型正确
        temp_df['Score'] = pd.to_numeric(temp_df['Score'], errors='coerce')
        temp_df['Length'] = pd.to_numeric(temp_df['Length'], errors='coerce')
        
        all_data.append(temp_df)
        print(f"成功处理: {os.path.basename(os.path.dirname(file))}")

    except Exception as e:
        print(f"处理文件出错 {file}: {e}")

# 2. 合并所有数据
if all_data:
    final_df = pd.concat(all_data, ignore_index=True)
    
    # 调整列的顺序
    final_df = final_df[[
        'BUSCO_ID', 'Status', 'Score', 'Length', '基因名', '物种缩写', 'Description'
    ]]
    
    # 3. 导出结果
    output_file = "merged_busco_results_with_desc.tsv"
    final_df.to_csv(output_file, sep='\t', index=False)
    
    print(f"\n{'='*60}")
    print(f"处理完成！")
    print(f"总记录数: {len(final_df)}")
    print(f"输出文件: {output_file}")
    print(f"{'='*60}")
    
    # 显示前5行预览
    print("\n预览 (前5行):")
    print(final_df.head().to_string(index=False))
else:
    print("未生成数据，请检查错误。")
