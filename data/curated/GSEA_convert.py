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

def get_all_species():
    """自动扫描当前目录，通过已知后缀提取所有物种前缀"""
    species_set = set()
    suffixes = ['_KEGG', '_TF', '_UUCD', '_go', '_pfam']
    
    for file in os.listdir('.'):
        if os.path.isfile(file):
            for suffix in suffixes:
                if file.endswith(suffix):
                    # 截取后缀前面的部分作为物种名
                    species = file[:-len(suffix)]
                    if species:
                        species_set.add(species)
    return sorted(list(species_set))

# 获取当前目录下所有的物种列表
species_list = get_all_species()
print(f"检测到以下物种数据: {species_list}")

# 循环处理每一个物种
for sp in species_list:
    print(f"正在处理物种: {sp} ...")
    
    # ==========================================
    # 1. 处理 KEGG
    # ==========================================
    kegg_file = f"{sp}_KEGG"
    if os.path.exists(kegg_file):
        kegg_data = defaultdict(list)
        kegg_names = {}
        with open(kegg_file, "r") as f:
            for line in f:
                parts = line.strip().split("\t")
                if len(parts) >= 7:
                    gene_id, ko_id, _, desc, _, _, path_id = parts[0], parts[1], parts[2], parts[3], parts[4], parts[5], parts[6]
                    key = format_key(desc)
                    kegg_data[key].append(gene_id)
                    kegg_names[key] = f"{path_id}, {desc}"

        with open(f"output/{sp}_KEGG", "w") as f:
            for key in sorted(kegg_data.keys()):
                genes = ",".join(sorted(list(set(kegg_data[key]))))
                f.write(f"{key}\t{kegg_names[key]}\t{genes}\n")

    # ==========================================
    # 2. 处理 TF
    # ==========================================
    tf_file = f"{sp}_TF"
    if os.path.exists(tf_file):
        tf_data = defaultdict(list)
        tf_names = {}
        with open(tf_file, "r") as f:
            for line in f:
                parts = line.strip().split("\t")
                if len(parts) >= 5:
                    gene_id, _, family, domains, full_name = parts[0], parts[1], parts[2], parts[3], parts[4]
                    key = format_key(f"TRANSCRIPTION_FACTOR_{full_name}")
                    tf_data[key].append(gene_id)
                    tf_names[key] = f"{domains},  Transcription factor: {family}"

        with open(f"output/{sp}_TF", "w") as f:
            for key in sorted(tf_data.keys()):
                genes = ",".join(sorted(list(set(tf_data[key]))))
                f.write(f"{key}\t{tf_names[key]}\t{genes}\n")

    # ==========================================
    # 3. 处理 UUCD (兼容大小写后缀)
    # ==========================================
    uucd_file = f"{sp}_UUCD"
    if os.path.exists(uucd_file):
        up_data = defaultdict(list)
        up_names = {}
        with open(uucd_file, "r") as f:
            for line in f:
                parts = line.strip().split("\t")
                if len(parts) == 10: # 小写 uucd 格式
                    gene_id, fam, subfam, name, desc = parts[2], parts[3], parts[4], parts[5], parts[8]
                elif len(parts) >= 4: # 大写 UUCD 格式
                    gene_id, fam, subfam, name = parts[0], parts[1], parts[2], parts[3]
                    desc = name
                else:
                    continue
                key = format_key(desc)
                up_data[key].append(gene_id)
                up_names[key] = f"Ubiquitin Family,  {fam}/{subfam}: {name}"

        with open(f"output/{sp}_UP", "w") as f:
            for key in sorted(up_data.keys()):
                genes = ",".join(sorted(list(set(up_data[key]))))
                f.write(f"{key}\t{up_names[key]}\t{genes}\n")

    # ==========================================
    # 4. 处理 GO
    # ==========================================
    go_file = f"{sp}_go"
    if os.path.exists(go_file):
        go_map = {
            "Molecular Function": {"suffix": "MF", "goslim": "GOslim:molecular_function", "data": defaultdict(list), "names": {}},
            "Biological Process": {"suffix": "BP", "goslim": "GOslim:biological_process", "data": defaultdict(list), "names": {}},
            "Cellular Component": {"suffix": "CC", "goslim": "GOslim:cellular_component", "data": defaultdict(list), "names": {}}
        }
        with open(go_file, "r") as f:
            for line in f:
                parts = line.strip().split("\t")
                if len(parts) >= 4:
                    gene_id, go_id, category, desc = parts[0], parts[1], parts[2], parts[3]
                    if category in go_map:
                        key = format_key(desc)
                        go_map[category]["data"][key].append(gene_id)
                        go_map[category]["names"][key] = f"{go_id}   {desc},    {go_map[category]['goslim']}"

        for cat, info in go_map.items():
            # 只有当该分支有数据时才创建文件
            if info["data"]:
                with open(f"output/{sp}_{info['suffix']}", "w") as f:
                    for key in sorted(info["data"].keys()):
                        genes = ",".join(sorted(list(set(info["data"][key]))))
                        f.write(f"{key}\t{info['names'][key]}\t{genes}\n")

    # ==========================================
    # 5. 处理 Pfam
    # ==========================================
    pfam_file = f"{sp}_pfam"
    if os.path.exists(pfam_file):
        domain_data = defaultdict(list)
        domain_names = {}
        with open(pfam_file, "r") as f:
            for line in f:
                parts = line.strip().split("\t")
                if len(parts) >= 5:
                    gene_id, pfam_id, short_name, full_name, p_type = parts[0], parts[1], parts[2], parts[3], parts[4]
                    key = format_key(f"PROTEIN_DOMAIN_{pfam_id}")
                    domain_data[key].append(gene_id)
                    domain_names[key] = f"{pfam_id}({p_type})   {short_name}, {full_name}"

        with open(f"output/{sp}_DOMAIN", "w") as f:
            for key in sorted(domain_data.keys()):
                genes = ",".join(sorted(list(set(domain_data[key]))))
                f.write(f"{key}\t{domain_names[key]}\t{genes}\n")

print("\n所有物种数据转换完成！结果已保存在 ./output/ 目录下。")
