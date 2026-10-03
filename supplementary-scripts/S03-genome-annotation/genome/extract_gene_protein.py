import sys
import re

def extract_relationships(gff_path, output_path):
    print(f"正在讀取 GFF3 文件: {gff_path} ...")
    
    # 用於儲存結果，避免重複輸出相同的對應
    seen_pairs = set()
    
    # 正則表達式：精確匹配 gene 和 protein_id
    gene_re = re.compile(r'gene=([^;\n]+)')
    protein_re = re.compile(r'protein_id=([^;\n]+)')
    # 部分 NCBI 文件中可能使用 DbXref 儲存更詳細的數字 GeneID (可選備用)
    gene_id_re = re.compile(r'Dbxref=[^;\n]*GeneID:([^;,\n]+)')

    count = 0
    with open(gff_path, 'r') as f, open(output_path, 'w') as out:
        # 寫入表頭 (Tab 分隔)
        out.write("Gene_Name\tNCBI_GeneID\tProtein_ID\n")
        
        for line in f:
            # 跳過註釋行
            if line.startswith("#") or not line.strip():
                continue
                
            parts = line.strip().split('\t')
            if len(parts) < 9:
                continue
                
            # 我們只從 CDS 行中提取蛋白 ID
            if parts[2] == 'CDS':
                attributes = parts[8]
                
                # 提取 protein_id
                p_match = protein_re.search(attributes)
                if p_match:
                    protein_id = p_match.group(1)
                    
                    # 提取 基因官方符號 (如 LOC122956527)
                    g_match = gene_re.search(attributes)
                    gene_name = g_match.group(1) if g_match else "Unknown"
                    
                    # 提取 NCBI 數字 GeneID (如 122956527)
                    gid_match = gene_id_re.search(attributes)
                    gene_id = gid_match.group(1) if gid_match else "Unknown"
                    
                    # 組合唯一的 Key，防止單個蛋白因為多個 exon/CDS 片段而被重複寫入
                    pair = (gene_name, gene_id, protein_id)
                    if pair not in seen_pairs:
                        seen_pairs.add(pair)
                        out.write(f"{gene_name}\t{gene_id}\t{protein_id}\n")
                        count += 1

    print(f"提取完成！共找到 {count} 組獨特的 基因-蛋白 對應關係。")
    print(f"結果已儲存至: {output_path}")

if __name__ == "__main__":
    # 您可以根據實際需要修改輸入和輸出檔名
    input_gff = "Orbicella_franksi.gff3"
    output_txt = "gene_protein_mapping.txt"
    
    extract_relationships(input_gff, output_txt)
