import sys
import re

def extract_heliopora_mapping(gff_path, output_path):
    print(f"正在讀取 GFF3 文件: {gff_path} ...")
    
    tx_to_gene = {}
    seen_pairs = set()
    
    # 使用更寬鬆的正則表達式，去掉結尾限制
    id_re = re.compile(r'ID=([^;\s]+)')
    parent_re = re.compile(r'Parent=([^;\s]+)')
    protein_id_re = re.compile(r'protein_id=([^;\s]+)')

    # 1. 第一輪：建立 Transcript -> Gene 關係
    with open(gff_path, 'r', encoding='utf-8') as f:
        for line in f:
            if line.startswith("#") or not line.strip():
                continue
            parts = line.strip().split('\t')
            if len(parts) < 9:
                continue
                
            # 將第 3 列強制轉為大寫，防止 mrna/mrna_gene/transcript 大小寫混亂
            feature_type = parts[2].upper()
            attributes = parts[8]
            
            if feature_type in ['MRNA', 'TRANSCRIPT', 'LNC_RNA', 'Y_RNA', 'NCRNA_GENE']:
                id_match = id_re.search(attributes)
                parent_match = parent_re.search(attributes)
                
                if id_match and parent_match:
                    tx_id = id_match.group(1)
                    gene_id = parent_match.group(1)
                    tx_to_gene[tx_id] = gene_id

    # 2. 第二輪：提取 CDS 行並串聯
    count = 0
    with open(gff_path, 'r', encoding='utf-8') as f, open(output_path, 'w', encoding='utf-8') as out:
        out.write("Gene_ID\tTranscript_ID\tProtein_ID\n")
        
        f.seek(0)
        for line in f:
            if line.startswith("#") or not line.strip():
                continue
            parts = line.strip().split('\t')
            if len(parts) < 9:
                continue
                
            feature_type = parts[2].upper()
            attributes = parts[8]
            
            if feature_type == 'CDS':
                p_match = protein_id_re.search(attributes)
                parent_match = parent_re.search(attributes)
                
                if p_match and parent_match:
                    protein_id = p_match.group(1)
                    transcript_id = parent_match.group(1)
                    gene_id = tx_to_gene.get(transcript_id, "Unknown")
                    
                    pair = (gene_id, transcript_id, protein_id)
                    if pair not in seen_pairs:
                        seen_pairs.add(pair)
                        out.write(f"{gene_id}\t{transcript_id}\t{protein_id}\n")
                        count += 1

    print(f"提取完成！共找到 {count} 組獨特的 基因-轉錄本-蛋白 對應關係。")
    print(f"結果已儲存至: {output_path}")

if __name__ == "__main__":
    input_gff = "Paramuricea_clavata.gff3"
    output_txt = "heliopora_id_mapping.txt"
    
    extract_heliopora_mapping(input_gff, output_txt)
