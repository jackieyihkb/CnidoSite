#!/usr/bin/env bash
# Fill the gene trees OrthoFinder never wrote.
#
# Results_Sep14 was interrupted, so its Gene_Trees/ is empty; the continuation run
# Results_Sep14_1 wrote only the trees whose duplications the resolution step touched
# (57,401 of them, and they are the big families).  The 22,281 families left over are all
# small -- 23 MB of alignment between them -- and Sep14 already aligned every one of them,
# so this is just the tree step, no realignment.
#
# `-nosupport` matches what the existing trees look like: their internal nodes are labelled
# n1, n2, ... with no support values, so adding support here would make the two halves of
# the dataset look different for no benefit.
#
# Workers print nothing; the only shared output would be a status line, and letting many
# processes write into one stream is exactly how the member table got corrupted last time.
# Success is judged by the output file existing, failures are named afterwards.
set -uo pipefail

WORK=/mnt/sda/jackie/cnidaria/codex/orthology/work/genetree
MSA=/mnt/sda/jackie/cnidaria/0.tree/0.peps/OrthoFinder/Results_Sep14/MultipleSequenceAlignments
FT=/home/$USER/.local/share/mamba/envs/genetree/bin/FastTree
RAW="$WORK/fasttree_raw"
JOBS=${JOBS:-32}

cd "$WORK"
mkdir -p "$RAW"
[ -s missing.txt ] || { echo "missing.txt is empty or absent -- run harvest.py first"; exit 1; }

TOTAL=$(wc -l < missing.txt)
echo "families to build: $TOTAL   jobs: $JOBS"

build_one() {
    local og="$1"
    local msa="$MSA/$og.fa"
    local out="$RAW/$og.nwk"
    [ -s "$out" ] && return 0
    if [ ! -s "$msa" ]; then echo "NOMSA" > "$RAW/$og.status"; return 0; fi
    # FastTree needs >= 2 sequences; a one-sequence alignment has no tree to give.
    if [ "$(grep -c '^>' "$msa")" -lt 2 ]; then echo "TINY" > "$RAW/$og.status"; return 0; fi
    if "$FT" -nosupport -quiet "$msa" > "$out" 2> "$RAW/$og.err" && [ -s "$out" ]; then
        rm -f "$RAW/$og.err"
    else
        echo "FAIL" > "$RAW/$og.status"
    fi
}
export -f build_one
export MSA RAW FT

xargs -a missing.txt -d '\n' -P "$JOBS" -I{} bash -c 'build_one "$@"' _ {}

echo "== built: $(find "$RAW" -name '*.nwk' | wc -l) / $TOTAL"
echo "== failures:"
find "$RAW" -name '*.status' -printf '%f\n' | sort | uniq -c | head -20
for s in "$RAW"/*.status; do
    [ -e "$s" ] || continue
    echo "   $(cat "$s") ${s##*/}"
done | sed 's/\.status$//' | head -40

echo "== normalising"
# --src takes the suffix positionally (harvest.py --src DIR SUFFIX); there is no --suffix
# flag.  Passing one makes the sweep look for files ending in "--suffix" and quietly find
# none, which reads like a successful run of zero trees.
# --src never rewrites missing.txt (that is the sweep default), so the gap list built in
# the parent job is left alone.
python3 harvest.py --src "$RAW" .nwk
echo "FASTTREE DONE"
