import sys
import re

def extract_heliopora_mapping(gff_path, output_path):
    print(f"正在讀取 GFF3 文件: {gff_path} ...")
    
    # 用於儲存結果，避免因為多個 Exon/CDS 片段重複輸出
    seen_pairs = set()
    
    # 針對 Ensembl GFF3 格式建立的精確正則表達式
    gene_re = re.compile(r'gene_id=([^;\n]+)')
    tx_re = re.compile(r'transcript_id=([^;\n]+)')
    protein_re = re.compile(r'protein_id=([^;\n]+)')

    count = 0
    with open(gff_path, 'r') as f, open(output_path, 'w') as out:
        # 寫入表頭 (Tab 分隔)
        out.write("Gene_ID\tTranscript_ID\tProtein_ID\n")
        
        for line in f:
            # 跳過註釋行和空行
            if line.startswith("#") or not line.strip():
                continue
                
            parts = line.strip().split('\t')
            if len(parts) < 9:
                continue
                
            # 僅從 CDS 行提取最完整的對應資訊
            if parts[2] == 'CDS':
                attributes = parts[8]
                
                # 同時提取三者
                g_match = gene_re.search(attributes)
                t_match = tx_re.search(attributes)
                p_match = protein_re.search(attributes)
                
                if g_match and t_match and p_match:
                    gene_id = g_match.group(1)
                    transcript_id = t_match.group(1)
                    protein_id = p_match.group(1)
                    
                    # 組合唯一 Key 去重
                    pair = (gene_id, transcript_id, protein_id)
                    if pair not in seen_pairs:
                        seen_pairs.add(pair)
                        out.write(f"{gene_id}\t{transcript_id}\t{protein_id}\n")
                        count += 1

    print(f"提取完成！共找到 {count} 組獨特的 基因-轉錄本-蛋白 對應關係。")
    print(f"結果已儲存至: {output_path}")

if __name__ == "__main__":
    input_gff = "Heliopora_coerulea.gff3"
    output_txt = "heliopora_id_mapping.txt"
    
    extract_heliopora_mapping(input_gff, output_txt)
