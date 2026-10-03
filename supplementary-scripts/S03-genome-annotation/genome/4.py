import sys
import re

def extract_heliopora_mapping(gff_path, output_path):
    print(f"正在讀取 GFF3 文件: {gff_path} ...")
    
    # 建立字典：用於記錄 Transcript_ID -> Gene_ID
    tx_to_gene = {}
    seen_pairs = set()
    
    # 精確的正則表達式
    id_re = re.compile(r'ID=([^;\n]+)')
    parent_re = re.compile(r'Parent=([^;\n]+)')
    protein_id_re = re.compile(r'protein_id=([^;\n]+)')

    # ────────────────────────────────────────────────────────
    # 第一輪讀取：從 mRNA 行，透過 ID 和 Parent 建立轉錄本與基因的關係
    # ────────────────────────────────────────────────────────
    with open(gff_path, 'r') as f:
        for line in f:
            if line.startswith("#") or not line.strip():
                continue
            parts = line.strip().split('\t')
            if len(parts) < 9:
                continue
                
            # 涵蓋 Ensembl 中所有可能的轉錄本類型
            if parts in ['mRNA', 'transcript', 'lnc_RNA', 'Y_RNA']:
                attributes = parts
                id_match = id_re.search(attributes)
                parent_match = parent_re.search(attributes) # Ensembl 的 mRNA 行用 Parent 代表 Gene_ID
                
                if id_match and parent_match:
                    tx_id = id_match.group(1)
                    gene_id = parent_match.group(1)
                    tx_to_gene[tx_id] = gene_id

    # ────────────────────────────────────────────────────────
    # 第二輪讀取：從 CDS 行提取 Protein_ID，並透過 Parent 串聯
    # ────────────────────────────────────────────────────────
    count = 0
    with open(gff_path, 'r') as f, open(output_path, 'w') as out:
        out.write("Gene_ID\tTranscript_ID\tProtein_ID\n")
        
        f.seek(0) # 重新回到檔案開頭
        for line in f:
            if line.startswith("#") or not line.strip():
                continue
            parts = line.strip().split('\t')
            if len(parts) < 9:
                continue
                
            if parts == 'CDS':
                attributes = parts
                
                p_match = protein_id_re.search(attributes)
                parent_match = parent_re.search(attributes) # CDS 行的 Parent 是 Transcript_ID
                
                if p_match and parent_match:
                    protein_id = p_match.group(1)
                    transcript_id = parent_match.group(1)
                    
                    # 透過字典查找真正的 Gene_ID
                    gene_id = tx_to_gene.get(transcript_id, "Unknown")
                    
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
