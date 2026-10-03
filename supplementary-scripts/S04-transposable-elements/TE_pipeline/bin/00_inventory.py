#!/usr/bin/env python3
"""Build the species manifest: which genome goes with which GFF3.

Writes  config/species_manifest.tsv

  species | genome | gff3 | gff3_kind | n_gene | seqid_match | display_name

gff3_kind:  genomic  -> usable for nearest-gene / region assignment
            mito     -> mitochondrial-only annotation, ignored for gene assignment
            none
seqid_match: fraction of the GFF's seqids present in the genome.
"""
import os
import re
import subprocess
from collections import defaultdict
from concurrent.futures import ThreadPoolExecutor

ROOT = os.environ.get("TE_ROOT", "/mnt/sda/jackie/cnidaria/codex/genome_TE")
CFG = os.path.join(ROOT, "TE_pipeline", "config")

# Display-name overrides (species key -> name shown in the final table)
DISPLAY = {
    "Actinernus_sp": "Actinernus sp. WN-2022",
}

CAT = "gzip -dc {p} | cat"          # normalise plain/gzipped input


def genome_seqids(path):
    """FASTA sequence IDs. awk stream pass — much faster than a Python line loop."""
    pre = "gzip -dc " if path.endswith(".gz") else "cat "
    r = subprocess.run(f"{pre}{path!r} | awk '/^>/{{print substr($1,2)}}'",
                       shell=True, capture_output=True, text=True)
    return r.stdout.split()


def gff_info(path):
    """(seqids, n_feature_genes, max_end) for a GFF3."""
    pre = "gzip -dc " if path.endswith(".gz") else "cat "
    awk = (r'''/^#/{next} {n=split($0,a,"\t"); if(n<5) next;
       if(!(a[1] in seen)){seen[a[1]]=1; ids=ids a[1] " "}
       if(a[3]=="gene"||a[3]=="mRNA"||a[3]=="transcript") g++;
       if(a[5]+0>m) m=a[5]+0}
       END{print ids "\t" g+0 "\t" m+0}''')
    r = subprocess.run(f"{pre}{path!r} | awk '{awk}'",
                       shell=True, capture_output=True, text=True)
    line = r.stdout.strip().split("\t")
    if len(line) < 3:
        return [], 0, 0
    return line[0].split(), int(line[1]), int(line[2])


def main():
    files = sorted(os.listdir(ROOT))
    genomes = [f for f in files if f.endswith((".fa.gz", ".fasta.gz", ".fa", ".fasta"))]
    gffs = [f for f in files if f.endswith((".gff3", ".gff", ".gff3.gz", ".gff.gz"))]

    fstem = lambda f: re.sub(r"\.(fa|fasta)(\.gz)?$", "", f)
    gstem = lambda g: re.sub(r"\.(gff3|gff)(\.gz)?$", "", g)

    with ThreadPoolExecutor(24) as ex:
        gff_f = {g: ex.submit(gff_info, os.path.join(ROOT, g)) for g in gffs}
        gen_f = {f: ex.submit(genome_seqids, os.path.join(ROOT, f)) for f in genomes}
        gff_d = {g: t.result() for g, t in gff_f.items()}
        gen_d = {f: t.result() for f, t in gen_f.items()}

    by_stem = defaultdict(list)
    for g in gffs:
        by_stem[gstem(g)].append(g)

    rows, used = [], set()
    for f in genomes:
        sp = fstem(f)
        gen = set(gen_d[f])
        # Pick the GFF whose species stem equals the genome stem. Anything else is
        # deliberately NOT used: contig names collide between independently
        # assembled genomes of the same genus (e.g. every Acropora assembly has
        # sc0000001_pilon..sc000N_pilon), so name overlap does not imply identity.
        cands = [g for g in by_stem.get(sp, []) if gff_d[g][0]]
        best = max(cands, key=lambda g: gff_d[g][1], default=None)
        if best is None:
            kind, ngene, frac = "none", 0, 0.0
        else:
            used.add(best)
            gids, ngene, max_end = gff_d[best]
            kind = "mito" if (max_end < 200_000 and ngene < 100) else "genomic"
            frac = round(sum(i in gen for i in gids) / len(gids), 3)
        rows.append((sp, f, best or "", kind, ngene, frac))

    rows.sort()
    out = os.path.join(CFG, "species_manifest.tsv")
    with open(out, "w") as fh:
        fh.write("species\tgenome\tgff3\tgff3_kind\tn_gene\tseqid_match\tdisplay_name\n")
        for sp, f, g, kind, ngene, frac in rows:
            fh.write("\t".join([sp, f, g, kind, str(ngene), str(frac),
                                DISPLAY.get(sp, sp.replace("_", " "))]) + "\n")
    print(f"wrote {out}  ({len(rows)} genomes)")
    for k in ("genomic", "mito", "none"):
        print(f"  {k:8s}: {sum(1 for r in rows if r[3] == k)}")
    print(f"  unused GFF files: {len(set(gffs) - used)}")


if __name__ == "__main__":
    main()
