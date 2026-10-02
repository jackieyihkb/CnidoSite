import os
import gzip
import shutil

# 1. 建立縮寫與物種全名的對照字典
mapping_text = """
AALAT	Alatina_alata
MVIRU	Morbakka_virulenta
TMAIP	Tripedalia_maipoensis
ASP1	Actinernus_sp
AEQUI	Actinia_equina
AMEDI	Actinia_mediterranea
ATENE	Actinia_tenebrosa
AXANT	Anthopleura_xanthogrammica
CGIGA	Condylactis_gigantea
PSINE1	Paracondylactis_sinensis
ALIUI	Actinoscyphia_liui
AIDSS	Alvinactis_idsseensis
ASP2	Actinostola_sp
EDIAP	Exaiptasia_diaphana
DLINE	Diadumene_lineata
EELEG	Edwardsia_elegans
NVECT	Nematostella_vectensis
SCALL	Scolanthus_callimorphus
PXISH	Paraphelliactis_xishaensis
TSTEP	Telmatactis_stephensoni
MSENI	Metridium_senile
PPENN	Plumapathes_pennacea
ROSCU	Rhodactis_osculifera
RFLOR	Ricordea_florida
AACUM	Acropora_acuminata
AAUST	Acropora_austera
AAWI	Acropora_awi
ACERV	Acropora_cervicornis
ACYTH	Acropora_cytherea
ADIGI	Acropora_digitifera
AECHI	Acropora_echinata
AFLOR	Acropora_florida
AGEMM	Acropora_gemmifera
AHEMP	Acropora_hemprichii
AHYAC	Acropora_hyacinthus
AINTE	Acropora_intermedia
ALORI	Acropora_loripes
AMICR	Acropora_microphthalma
AMILL	Acropora_millepora
AMURI	Acropora_muricata
ANASU	Acropora_nasuta
APALM	Acropora_palmata
APULC	Acropora_pulchra
ASELA	Acropora_selago
ASPAT	Acropora_spathulata
ATENU	Acropora_tenuis
AYONG	Acropora_yongei
AMYRI	Astreopora_myriophthalma
ACOER	Aurelia_coerulea
MCACT	Montipora_cactus
MCAPI	Montipora_capitata
MCAPR	Montipora_capricornis
MEFFL	Montipora_efflorescens
MFOLI	Montipora_foliosa
MGRIS	Montipora_grisea
ASP3	Aurelia_sp_4
LSCAB	Leptoseris_scabra
PSPEC	Pachyseris_speciosa
SINTE	Stephanocoenia_intersepta
CJARD	Catalaphyllia_jardinei
DPERT	Desmophyllum_pertusum
FANCO	Fimbriaphyllia_ancora
LPERT	Lophelia_pertusa
CGRAC2	Cladopsammia_gracilis
DCRIB	Dendrophyllia_cribrosa
DAXIF	Duncanopsammia_axifuga
TCOCC	Tubastraea_coccinea
TRENI	Turbinaria_reniformis
GFASC	Galaxea_fascicularis
PCRUS	Podabacia_crustacea
MLORD	Micromussa_lordhowensis
DCYLI	Dendrogyra_cylindrus
MMEAN	Meandrina_meandrites
CSALA	Cyphastrea_salae
EHORR	Echinopora_horrida
OFAVE	Orbicella_faveolata
OFRAN	Orbicella_franksi
PSINE2	Platygyra_sinensis
CNATA	Colpophyllia_natans
OARBU	Oculina_arbuscula
OPATA	Oculina_patagonica
MAURE	Madracis_auretenra
MSENA	Madracis_senaria
PACUT	Pocillopora_acuta
PDAMI	Pocillopora_damicornis
PMEAN	Pocillopora_meandrina
PVERR	Pocillopora_verrucosa
SPIST	Stylophora_pistillata
PAUST	Porites_australiensis
PCOMP	Porites_compressa
PCYLI	Porites_cylindrica
PDIVA	Porites_divaricata
PEVER	Porites_evermanni
PHARR	Porites_harrisoni
PLOBA	Porites_lobata
PLUTE	Porites_lutea
PRUS	Porites_rus
APOCU	Astrangia_poculata
SRADI	Siderastrea_radians
SSIDE	Siderastrea_siderea
BWELL	Blastomussa_wellsi
PMIZI	Palythoa_mizigama
PUMBR	Palythoa_umbrosa
BCFM	Bougainvillia_cf_muscus
CCOCK	Candelabrum_cocksii
HECHI	Hydractinia_echinata
HSYMB	Hydractinia_symbiolongicarpus
HOLIG	Hydra_oligactis
HVIRI	Hydra_viridissima
HVULG	Hydra_vulgaris
MALCI	Millepora_alcicornis
MCOMP	Millepora_complanata
MDICH	Millepora_dichotoma
TDOHR	Turritopsis_dohrnii
TRUBR	Turritopsis_rubra
CHEMI	Clytia_hemisphaerica
NSEPT	Nanomia_septata
HSALM	Henneguya_salminicola
MHONG	Myxobolus_honghuensis
MSQUA	Myxobolus_squamalis
TKITA	Thelohanellus_kitauei
TSP	Trachythela_sp
ECAVO	Eunicella_cavolini
EVERR	Eunicella_verrucosa
AAMER	Antillogorgia_americana
LSARM	Leptogorgia_sarmentosa
DGIGA	Dendronephthya_gigantea
MMURI	Muricea_muricata
PCLAV	Paramuricea_clavata
XSP	Xenia_sp
CSP1	Chrysogorgia_sp
HIMPE	Hemicorallium_imperiale
PPAPI	Paragorgia_papillata
HCOER	Heliopora_coerulea
PGRIS	Pteroeides_griseum
CGRAC1	Callogorgia_gracilis
CSP2	Cassiopea_sp_PORT0000214
CXAMA	Cassiopea_xamachana
CMOSA	Catostylus_mosaicus
MPAPU	Mastigias_papua
NNOMU	Nemopilema_nomurai
RESCU	Rhopilema_esculentum
CQUIN	Chrysaora_quinquecirrha
PNOCT	Pelagia_noctiluca
SMALA	Sanderia_malayensis
AAURI1	Aurelia_aurita
AAURI2	Aurelia_aurita_complex
HOCTO	Haliclystus_octoradiatus
CCRUX	Calvadosia_cruxmelitensis
"""

name_map = {}
for line in mapping_text.strip().split('\n'):
    if line:
        parts = line.split()
        if len(parts) == 2:
            name_map[parts[0].strip()] = parts[1].strip()

# 👈 核心修正 1：目標註釋種類一律改用小寫，方便比對
target_anno_types = {"tf", "uucd"}

# 2. 掃描當前目錄下的檔案
files_in_dir = os.listdir('.')
species_files = {}

for file in files_in_dir:
    if os.path.isfile(file):
        parts = file.split('_')
        
        if len(parts) >= 2:
            short_name = parts[0]
            # 👈 核心修正 2：如果檔名帶有後綴（如 .txt），只取點之前的內容並轉小寫
            anno_type = parts[1].split('.')[0].lower()
            
            if short_name in name_map and anno_type in target_anno_types:
                if short_name not in species_files:
                    species_files[short_name] = []
                species_files[short_name].append(file)

# 3. 開始執行壓縮與合併
print("開始處理註釋檔案（大小寫 Bug 修復版）...\n" + "="*40)
success_count = 0

for short_name, files in species_files.items():
    full_name = name_map[short_name]
    output_filename = f"{full_name}.genefamily.gz"
    
    print(f"正在打包 [{short_name}] -> {output_filename}")
    try:
        with gzip.open(output_filename, 'wt', encoding='utf-8') as gz_out:
            for f in sorted(files):
                # 👈 核心修正 3：標籤提取同樣進行去後綴處理並轉大寫
                anno_label = f.split('_')[1].split('.')[0].upper()
                gz_out.write(f"# === {anno_label} ANNOTATION ===\n")
                
                with open(f, 'r', encoding='utf-8', errors='ignore') as f_in:
                    shutil.copyfileobj(f_in, gz_out)
                gz_out.write("\n\n")
                
        print(f"  成功合併了 {len(files)} 個註釋檔。")
        success_count += 1
    except Exception as e:
        print(f"  ❌ 錯誤: {e}")

print("="*40 + f"\n處理完成！成功生成 {success_count} 個物種的 .genefamily.gz 檔案。")
