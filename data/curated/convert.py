import os
import re
from collections import defaultdict

# 创建输出目录
os.makedirs("output", exist_ok=True)

def format_key(text):
    """将文本转换为大写，去除特殊字符，空格换成下划线"""
    text = text.upper()
    text = re.sub(r'[^A-Z0-9_\s-]', '', text) # 移除非字母数字字符
    text = re.sub(r'[\s-]+', '_', text.strip()) # 空格和减号换成下划线
    return text

# ==========================================
# 1. 处理 KEGG (LPERT_KEGG -> output/LPERT_KEGG)
# ==========================================
kegg_data = defaultdict(list)
kegg_names = {}
if os.path.exists("LPERT_KEGG"):
    with open("LPERT_KEGG", "r") as f:
        for line in f:
            parts = line.strip().split("\t")
            if len(parts) >= 7:
                gene_id, ko_id, _, desc, _, _, path_id = parts[0], parts[1], parts[2], parts[3], parts[4], parts[5], parts[6]
                key = format_key(desc)
                kegg_data[key].append(gene_id)
                kegg_names[key] = f"{path_id}, {desc}"

    with open("output/LPERT_KEGG", "w") as f:
        for key in sorted(kegg_data.keys()):
            genes = ",".join(sorted(list(set(kegg_data[key]))))
            f.write(f"{key}\t{kegg_names[key]}\t{genes}\n")

# ==========================================
# 2. 处理 TF (LPERT_TF -> output/LPERT_TF)
# ==========================================
tf_data = defaultdict(list)
tf_names = {}
if os.path.exists("LPERT_TF"):
    with open("LPERT_TF", "r") as f:
        for line in f:
            parts = line.strip().split("\t")
            if len(parts) >= 5:
                gene_id, _, family, domains, full_name = parts[0], parts[1], parts[2], parts[3], parts[4]
                key = format_key(f"TRANSCRIPTION_FACTOR_{full_name}")
                tf_data[key].append(gene_id)
                tf_names[key] = f"{domains},  Transcription factor: {family}"

    with open("output/LPERT_TF", "w") as f:
        for key in sorted(tf_data.keys()):
            genes = ",".join(sorted(list(set(tf_data[key]))))
            f.write(f"{key}\t{tf_names[key]}\t{genes}\n")

# ==========================================
# 3. 处理 UUCD (LPERT_UUCD 或 LPERT_uucd -> output/LPERT_UP)
# ==========================================
up_data = defaultdict(list)
up_names = {}
# 优先处理包含全信息的 LPERT_uucd
uucd_path = "LPERT_uucd" if os.path.exists("LPERT_uucd") else "LPERT_UUCD"
if os.path.exists(uucd_path):
    with open(uucd_path, "r") as f:
        for line in f:
            parts = line.strip().split("\t")
            # 兼容两种输入的列数
            if len(parts) == 10: # LPERT_uucd
                gene_id, fam, subfam, name, desc = parts[2], parts[3], parts[4], parts[5], parts[8]
            elif len(parts) >= 4: # LPERT_UUCD
                gene_id, fam, subfam, name = parts[0], parts[1], parts[2], parts[3]
                desc = name
            else:
                continue
            key = format_key(desc)
            up_data[key].append(gene_id)
            up_names[key] = f"Ubiquitin Family,  {fam}/{subfam}: {name}"

    with open("output/LPERT_UP", "w") as f:
        for key in sorted(up_data.keys()):
            genes = ",".join(sorted(list(set(up_data[key]))))
            f.write(f"{key}\t{up_names[key]}\t{genes}\n")

# ==========================================
# 4. 处理 GO (LPERT_go -> MF, BP, CC)
# ==========================================
go_map = {
    "Molecular Function": {"suffix": "MF", "goslim": "GOslim:molecular_function", "data": defaultdict(list), "names": {}},
    "Biological Process": {"suffix": "BP", "goslim": "GOslim:biological_process", "data": defaultdict(list), "names": {}},
    "Cellular Component": {"suffix": "CC", "goslim": "GOslim:cellular_component", "data": defaultdict(list), "names": {}}
}

if os.path.exists("LPERT_go"):
    with open("LPERT_go", "r") as f:
        for line in f:
            parts = line.strip().split("\t")
            if len(parts) >= 4:
                gene_id, go_id, category, desc = parts[0], parts[1], parts[2], parts[3]
                if category in go_map:
                    key = format_key(desc)
                    go_map[category]["data"][key].append(gene_id)
                    go_map[category]["names"][key] = f"{go_id}   {desc},    {go_map[category]['goslim']}"

    for cat, info in go_map.items():
        with open(f"output/LPERT_{info['suffix']}", "w") as f:
            for key in sorted(info["data"].keys()):
                genes = ",".join(sorted(list(set(info["data"][key]))))
                f.write(f"{key}\t{info['names'][key]}\t{genes}\n")

# ==========================================
# 5. 处理 Pfam (LPERT_pfam -> output/LPERT_DOMAIN)
# ==========================================
domain_data = defaultdict(list)
domain_names = {}
if os.path.exists("LPERT_pfam"):
    with open("LPERT_pfam", "r") as f:
        for line in f:
            parts = line.strip().split("\t")
            if len(parts) >= 5:
                gene_id, pfam_id, short_name, full_name, p_type = parts[0], parts[1], parts[2], parts[3], parts[4]
                key = format_key(f"PROTEIN_DOMAIN_{pfam_id}")
                domain_data[key].append(gene_id)
                domain_names[key] = f"{pfam_id}({p_type})   {short_name}, {full_name}"

    with open("output/LPERT_DOMAIN", "w") as f:
        for key in sorted(domain_data.keys()):
            genes = ",".join(sorted(list(set(domain_data[key]))))
            f.write(f"{key}\t{domain_names[key]}\t{genes}\n")

print("数据转换完成！结果已保存在 ./output/ 目录下。")
