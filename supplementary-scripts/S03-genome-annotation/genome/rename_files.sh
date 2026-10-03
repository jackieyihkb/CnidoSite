#!/bin/bash
# 保存为 rename_files.sh
# 给脚本执行权限: chmod +x rename_files.sh

# 进入下载目录
cd downloaded_genomes

echo "开始批量重命名文件..."

# 遍历每个物种目录
for species_dir in */; do
    # 去掉目录名末尾的斜杠
    species_dir=${species_dir%/}
    
    # 提取物种名（已经是下划线格式，如Thelohanellus_kitauei）
    species_name="$species_dir"
    
    # 进入物种目录
    cd "$species_dir" || continue
    
    echo "处理物种: $species_name"
    
    # 查找NCBI数据集子目录
    if [ -d "ncbi_dataset/data" ]; then
        # 进入数据目录
        cd ncbi_dataset/data || continue
        
        # 找到具体的accession目录（通常只有一个）
        for acc_dir in */; do
            if [ -d "$acc_dir" ]; then
                # 进入accession目录
                cd "$acc_dir" || continue
                
                echo "  在目录: $acc_dir"
                
                # 1. 重命名cds文件
                if [ -f "cds_from_genomic.fna" ]; then
                    mv "cds_from_genomic.fna" "../../../../${species_name}.cds"
                    echo "    ✓ 重命名: cds_from_genomic.fna -> ${species_name}.cds"
                fi
                
                # 2. 重命名蛋白文件
                if [ -f "protein.faa" ]; then
                    mv "protein.faa" "../../../../${species_name}.pep"
                    echo "    ✓ 重命名: protein.faa -> ${species_name}.pep"
                fi
                
                # 3. 重命名基因组文件（查找以_genomic.fna结尾的文件）
                for genome_file in *_genomic.fna; do
                    if [ -f "$genome_file" ]; then
                        mv "$genome_file" "../../../../${species_name}.fa"
                        echo "    ✓ 重命名: $genome_file -> ${species_name}.fa"
                        break
                    fi
                done
                
                # 4. 重命名gff文件
                if [ -f "genomic.gff" ]; then
                    mv "genomic.gff" "../../../../${species_name}.gff3"
                    echo "    ✓ 重命名: genomic.gff -> ${species_name}.gff3"
                fi
                
                # 返回ncbi_dataset/data目录
                cd ..
                break
            fi
        done
        
        # 返回物种目录
        cd ../..
    fi
    
    # 返回主目录
    cd ..
    
    echo ""
done

echo "重命名完成！"

# 创建整理后的文件列表
echo "创建重命名后的文件列表..."
ls -1 *.cds *.pep *.fa *.gff3 2>/dev/null | sort > renamed_files.txt
echo "文件列表已保存到 renamed_files.txt"

# 显示统计信息
echo ""
echo "重命名结果统计:"
echo "CDS 文件 (.cds): $(ls *.cds 2>/dev/null | wc -l)"
echo "蛋白文件 (.pep): $(ls *.pep 2>/dev/null | wc -l)"
echo "基因组文件 (.fa): $(ls *.fa 2>/dev/null | wc -l)"
echo "注释文件 (.gff3): $(ls *.gff3 2>/dev/null | wc -l)"
