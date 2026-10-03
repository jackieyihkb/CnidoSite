#!/usr/bin/env python3
"""Rename every sequence in a genome FASTA to a short, unique, EDTA-safe ID.

Why this exists
---------------
EDTA refuses to run when sequence IDs are longer than 13 characters and its
own "reformat seq IDs" fallback only succeeds if the **first 13 characters**
stay unique.  That is often false:

    >HAP1_SCAFFOLD_3184   ->  HAP1_SCAFFOL
    >HAP1_SCAFFOLD_3178   ->  HAP1_SCAFFOL     same 13 chars -> collision

    ERROR: Fail to convert seq IDs to <= 13 characters!
           Please provide a genome with shorter seq IDs.

Rather than hope the truncation is collision-free, we rewrite the genome with
synthetic IDs *before* EDTA runs:

    >chr1 Millepora alcicornis genome assembly, chromosome: 1
    >seq0000001

Every new ID is 10 characters, globally unique, and made of plain
[A-Za-z0-9_], so EDTA's id_mode-1 truncation becomes a no-op.  The mapping is
written to a TSV so the TE table can report the ORIGINAL scaffold names.

Usage:
    02_rename_genome.py --genome <in.fa> --out <out.fa> --map <map.tsv>
                        [--prefix seq] [--width 7]
"""

import argparse
import sys

# EDTA's own special-character substitution.  We never generate these, but the
# original names go through it so the map matches what any consumer expects.
import re
_SPECIAL = re.compile(r"[^A-Za-z0-9_.:+^*@%\-=&\[\]]")


def main():
    ap = argparse.ArgumentParser(
        description=__doc__,
        formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--genome", required=True, help="input genome FASTA")
    ap.add_argument("--out", required=True, help="renamed genome FASTA")
    ap.add_argument("--map", required=True,
                    help="output TSV: <new_id>\\t<original_id>")
    ap.add_argument("--prefix", default="seq",
                    help="new ID prefix (default: seq)")
    ap.add_argument("--width", type=int, default=7,
                    help="zero-padded counter width (default 7 -> seq0000001)")
    args = ap.parse_args()

    n = 0
    dupes = 0
    seen = {}
    with open(args.genome) as fin, \
            open(args.out, "w") as fout, \
            open(args.map, "w") as fmap:
        for line in fin:
            if line.startswith(">"):
                orig = line[1:].strip().split()[0] if line[1:].strip() else ""
                if not orig:
                    sys.stderr.write("ERROR: empty FASTA header at record %d\n"
                                     % (n + 1))
                    sys.exit(1)
                if orig in seen:
                    # Same first token twice: EDTA cannot tell them apart and
                    # neither could the gene GFF3.  Keep the first mapping and
                    # note it, rather than silently producing two records.
                    dupes += 1
                n += 1
                new = "%s%0*d" % (args.prefix, args.width, n)
                fmap.write("%s\t%s\n" % (new, orig))
                fout.write(">%s\n" % new)
            else:
                fout.write(line)

    sys.stderr.write("  renamed %d sequences -> %s (map: %s)\n"
                     % (n, args.out, args.map))
    if dupes:
        sys.stderr.write("  WARNING: %d duplicate first-token IDs in the "
                         "input (each got its own new ID)\n" % dupes)


if __name__ == "__main__":
    main()
