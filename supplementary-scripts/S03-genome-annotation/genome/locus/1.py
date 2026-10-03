import sys
import re

def parse_attributes(attr_str):
    """解析第九列属性，兼容 GFF3 (k=v;) 和 GTF (k "v"; 或 k=v) 格式"""
    attrs = {}
    if not attr_str.strip():
        return attrs
        
    attr_str = attr_str.strip().rstrip(';')
    items = attr_str.split(';')
    for item in items:
        item = item.strip()
        if not item:
            continue
            
        if '=' in item:
            k, v = item.split('=', 1)
            attrs[k.strip()] = v.strip().strip('"\'')
        else:
            parts = re.split(r'\s+', item, maxsplit=1)
            if len(parts) == 2:
                k, v = parts
                attrs[k.strip()] = v.strip().strip('"\'')
    return attrs

def parse_gff3_or_gtf(gff_file, output_file):
    tx_dict = {}       
    tx_info = {}       
    
    print(f"正在解析输入文件: {gff_file} ...")
    
    try:
        with open(gff_file, 'r') as f:
            for line in f:
                if line.startswith('#') or not line.strip():
                    continue
                    
                parts = line.strip().split('\t')
                if len(parts) < 9:
                    continue
                    
                chrom, source, feature_type, start, end, score, strand, phase, attributes_str = parts
                start_num, end_num = int(start), int(end)
                
                attrs = parse_attributes(attributes_str)

                tx_id = attrs.get('ID') or attrs.get('transcript_id')
                gene_id = attrs.get('Parent') or attrs.get('gene_id') or attrs.get('gene')
                if gene_id:
                    gene_id = gene_id.replace('gene-', '')
                
                protein_id = attrs.get('protein_id')
                if not protein_id and 'Dbxref' in attrs:
                    match = re.search(r'Genbank:(XP_[0-9.]+)', attrs['Dbxref'])
                    if match:
                        protein_id = match.group(1)

                if feature_type in ['mRNA', 'transcript', 'lnc_RNA', 'tRNA', 'rRNA']:
                    if tx_id:
                        tx_info[tx_id] = {
                            'gene_id': gene_id or 'NA',
                            'chr': chrom,
                            'strand': strand,
                            'protein_id': protein_id or tx_id
                        }
                        tx_dict[tx_id] = {'start': start_num, 'end': end_num}

                elif feature_type == 'CDS' and tx_id:
                    if tx_id not in tx_info:
                        tx_info[tx_id] = {
                            'gene_id': gene_id or 'NA',
                            'chr': chrom,
                            'strand': strand,
                            'protein_id': protein_id or tx_id
                        }
                    
                    if tx_id not in tx_dict:
                        tx_dict[tx_id] = {'start': start_num, 'end': end_num}
                    else:
                        if start_num < tx_dict[tx_id]['start']:
                            tx_dict[tx_id]['start'] = start_num
                        if end_num > tx_dict[tx_id]['end']:
                            tx_dict[tx_id]['end'] = end_num

    except FileNotFoundError:
        print(f"❌ 错误: 找不到输入文件 '{gff_file}'")
        sys.exit(1)

    print(f"正在将结果写入到: {output_file} ...")
    with open(output_file, 'w') as out:
        out.write("Protein_ID\tGene_ID\tChr\tStart\tEnd\tStrand\n")
        
        for tx_id, info in tx_info.items():
            coords = tx_dict.get(tx_id)
            if not coords:
                continue
                
            p_id = info['protein_id']
            if p_id in ['NA', '', None]:
                continue
                
            out.write(f"{p_id}\t{info['gene_id']}\t{info['chr']}\t{coords['start']}\t{coords['end']}\t{info['strand']}\n")

    print("🎉 转换完成！")

if __name__ == '__main__':
    if len(sys.argv) != 3:
        print("❌ 错误: 参数数量不正确！")
        print("💡 用法提示: python3 1.py <输入文件> <输出结果文件>")
        sys.exit(1)

    # ⭐ 修复处：明确提取第 1 和第 2 个参数字符串，而不是整个列表
    input_gff = sys.argv[1]
    output_locus = sys.argv[2]

    parse_gff3_or_gtf(input_gff, output_locus)
