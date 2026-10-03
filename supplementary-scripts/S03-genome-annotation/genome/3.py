import sys
import re

def extract_heliopora_mapping(gff_path, output_path):
    print(f"正在讀取 GFF3 文件: {gff_path} ...")
    
    # 建立字典：用於記錄 Transcript_ID -> Gene_ID 的父子關係
    tx_to_gene = {}
    
    # 用於儲存最終結果，避免重複輸出相同的對應
    seen_pairs = set()
    
    # 精確的正則表達式
    id_re = re.compile(r'ID=([^;\n]+)')
    parent_re = re.compile(r'Parent=([^;\n]+)')
    gene_id_re = re.compile(r'gene_id=([^;\n]+)')
    protein_id_re = re.compile(r'protein_id=([^;\n]+)')

    # ────────────────────────────────────────────────────────
    # 第一輪讀取：從 mRNA 行建立 Transcript -> Gene 的對應關係
    # ────────────────────────────────────────────────────────
    with open(gff_path, 'r') as f:
        for line in f:
            if line.startswith("#") or not line.strip():
                continue
            parts = line.strip().split('\t')
            if len(parts) < 9:
                continue
                
            # 當遇到 mRNA 或其他轉錄本類型時
            if parts[2] in ['mRNA', 'transcript', 'lnc_RNA']:
                attributes = parts[8]
                id_match = id_re.search(attributes)
                gene_match = gene_id_re.search(attributes)
                
                if id_match and gene_match:
                    tx_id = id_match.group(1)
                    gene_id = gene_match.group(1)
                    tx_to_gene[tx_id] = gene_id

    # ────────────────────────────────────────────────────────
    # 第二輪讀取：從 CDS 行提取 Protein_ID，並透過 Parent 串聯
    # ────────────────────────────────────────────────────────
    count = 0
    with open(gff_path, 'r') as f, open(output_path, 'w') as out:
        out.write("Gene_ID\tTranscript_ID\tProtein_ID\n")
        
        f.seek(0) # 重新回到檔案開頭讀取
        for line in f:
            if line.startswith("#") or not line.strip():
                continue
            parts = line.strip().split('\t')
            if len(parts) < 9:
                continue
                
            if parts[2] == 'CDS':
                attributes = parts[8]
                
                p_match = protein_id_re.search(attributes)
                parent_match = parent_re.search(attributes)
                
                if p_match and parent_match:
                    protein_id = p_match.group(1)
                    transcript_id = parent_match.group(1)
                    
                    # 透過剛才記錄的字典，找到該轉錄本對應的 Gene_ID
                    gene_id = tx_to_gene.get(transcript_id, "Unknown")
                    
                    # 組合唯一 Key 去重 (因為一個 mRNA 有多個 CDS 外顯子片段)
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
