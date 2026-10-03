#!/usr/bin/env bash
# 10_prepare.sh <species>
#
#   <sp>.fa.gz  ->  work/<sp>/<sp>.fa          (plain FASTA, as downloaded)
#                   work/<sp>/<sp>.idmap.tsv   (seq0000001 <TAB> original_id)
#                   work/<sp>/<sp>.renamed.fa  (FASTA EDTA is run on)
#
# EDTA/genometools choke on sequence IDs carrying spaces, pipes or non-ASCII,
# and RepeatMasker output is far easier to map back from a flat namespace.  So
# every sequence is renamed to seq%07d and the original ID is kept in the idmap,
# which is the only thing that lets the final table report the real
# chromosome/contig/scaffold name.
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

sp="${1:?usage: 10_prepare.sh <species>}"
d="$(sp_dir "$sp")"; mkdir -p "$d"

gz="$TE_ROOT/$(manifest_field "$sp" genome)"
[[ -s "$gz" ]] || { log "$sp" "ERROR: genome $gz not found"; exit 1; }

plain="$d/$sp.fa"
ren="$d/$sp.renamed.fa"
idmap="$d/$sp.idmap.tsv"

if [[ -s "$ren" && -s "$idmap" ]]; then
  log "$sp" "renamed genome already present"
  exit 0
fi

log "$sp" "decompressing $(basename "$gz")"
if [[ "$gz" == *.gz ]]; then gzip -dc "$gz" > "$plain"; else cp "$gz" "$plain"; fi
[[ -s "$plain" ]] || { log "$sp" "ERROR: decompression produced nothing"; exit 1; }

log "$sp" "renaming sequences to EDTA-safe IDs"
python3 - "$plain" "$ren" "$idmap" "$sp" <<'PY'
import sys
plain, ren, idmap, sp = sys.argv[1:5]
seen, n, bp, bad = {}, 0, 0, 0
with open(plain) as fi, open(ren, 'w') as fo, open(idmap, 'w') as fm:
    cur = None
    for line in fi:
        if line.startswith('>'):
            n += 1
            orig = line[1:].split()[0] if len(line) > 1 else f"unnamed_{n}"
            # keep the name unique even if the assembly repeats an ID
            k = seen.get(orig, 0)
            seen[orig] = k + 1
            if k:
                orig = f"{orig}_dup{k}"
                bad += 1
            new = f"seq{n:07d}"
            fm.write(f"{new}\t{orig}\n")
            cur = new
            fo.write(f">{new}\n")
        elif cur:
            fo.write(line if line.endswith('\n') else line + '\n')
            bp += len(line.strip())
print(f"  renamed {n} sequences ({bp} bp, {bad} duplicate IDs disambiguated)",
      file=sys.stderr)
PY
[[ -s "$ren" ]] || { log "$sp" "ERROR: renaming failed"; exit 1; }
rm -f "$plain"          # only needed to produce $ren; saves a genome-sized copy
log "$sp" "prepared $(grep -c '^>' "$ren") sequences, $(wc -c < "$ren") bytes"
