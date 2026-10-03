import os
from Bio import SeqIO

def get_species_id(sequence_id):
    parts = sequence_id.split('_')
    # 普通物种使用第一个下划线前的缩写；OUT_* 外群再保留一段，
    # 避免五个外群全部被误认为同一个名为 OUT 的物种。
    if sequence_id.startswith('OUT_') and len(parts) >= 2:
        return '_'.join(parts[:2])
    return parts[0]


def process_files(input_dir, output_dir, min_species_count=153):
    if not os.path.exists(output_dir):
        os.makedirs(output_dir)

    for filename in os.listdir(input_dir):
        if filename.endswith('.fa'):
            filepath = os.path.join(input_dir, filename)

            # 读取文件中的所有序列
            sequences = list(SeqIO.parse(filepath, 'fasta'))

            # 统计每个物种的序列
            species_dict = {}
            for seq in sequences:
                species = get_species_id(seq.id)
                if species not in species_dict:
                    species_dict[species] = []
                species_dict[species].append(seq)

            # 检查物种数量是否满足条件
            if len(species_dict) >= min_species_count:
                # 对于每个物种，只保留最长的序列
                longest_sequences = []
                for species, seqs in species_dict.items():
                    longest_seq = max(seqs, key=lambda seq: len(seq.seq))
                    # AMAS按FASTA标题识别taxon，因此不同OG中统一使用物种名。
                    longest_seq.id = species
                    longest_seq.name = species
                    longest_seq.description = species
                    longest_sequences.append(longest_seq)

                # 将处理后的文件保存到新的目录
                output_filepath = os.path.join(output_dir, filename)
                with open(output_filepath, 'w') as output_file:
                    SeqIO.write(longest_sequences, output_file, 'fasta')

if __name__ == "__main__":
    input_directory = '/mnt/sda/jackie/cnidaria/0.tree/0.peps/OrthoFinder/Results_Sep13/Orthogroup_Sequences/'
    output_directory = '/mnt/sda/jackie/cnidaria/0.tree/Filtered_Orthogroup_Sequences_new/'
    process_files(input_directory, output_directory)
