"""Shared helpers for reading OrthoFinder's sequence files.

OrthoFinder writes its output headers as ``>{species}_{original header}``.  For
this dataset the original header is ``{abbr1}_{gene}`` for every cnidarian
proteome but ``{of_name}+{ncbi header}`` for the five outgroups, which are not
spelled with an abbreviation at all.  So the species cannot be recovered by
splitting on "_" -- ``Aurelia_aurita`` is a prefix of
``Aurelia_aurita_complex`` -- and the abbreviation cannot be assumed present.

`HeaderMap` therefore resolves against the actual species list, longest name
first, and returns the gene ID with the abbreviation prefix stripped when it is
there.  Anything it cannot place is reported rather than silently dropped.
"""
import os
import re

ISO = re.compile(r"\.t\d+$")


class HeaderMap:
    def __init__(self, species_tsv):
        rows, hdr = [], None
        for i, line in enumerate(open(species_tsv)):
            f = line.rstrip("\n").split("\t")
            if i == 0:
                hdr = f
                continue
            rows.append(dict(zip(hdr, f)))
        self.rows = rows
        # longest first so Aurelia_aurita_complex wins over Aurelia_aurita
        self.names = sorted((r["of_name"] for r in rows), key=len, reverse=True)
        self.abbr = {r["of_name"]: r["abbr1"] for r in rows}
        self.unresolved = 0

    def parse(self, header):
        """'>Acropora_acuminata_AACUM_aacu_s0142.g24.t1' -> ('AACUM', 'aacu_s0142.g24.t1')

        The key returned for an outgroup is its full ``OUT_``-prefixed name,
        matching how the member table is keyed; the gene ID has the redundant
        ``OUT_`` taken off the front, which is what the member table records
        for those species (it read the header as abbr "OUT" and gene
        "Sycon_ciliatum+XP_...").  Without that, outgroup sequences never match
        their copy-number record and silently vanish from every matrix.
        """
        h = header.lstrip(">").strip()
        for name in self.names:
            if h.startswith(name + "_"):
                rest = h[len(name) + 1:]
                a = self.abbr[name]
                gene = rest.split()[0]
                if a and gene.startswith(a + "_"):
                    gene = gene[len(a) + 1:]
                if not a and gene.startswith("OUT_"):
                    gene = gene[4:]
                return (a or name, gene)
        self.unresolved += 1
        return (None, None)


def read_fasta(path):
    """Yield (header_without_>, sequence) pairs."""
    h, buf = None, []
    with open(path) as fh:
        for line in fh:
            if line.startswith(">"):
                if h is not None:
                    yield h, "".join(buf)
                h, buf = line[1:].rstrip("\n"), []
            else:
                buf.append(line.strip())
    if h is not None:
        yield h, "".join(buf)


def of_sequences_dir():
    return ("/mnt/sda/jackie/cnidaria/0.tree/0.peps/OrthoFinder/"
            "Results_Sep14/Orthogroup_Sequences")


def of_msa_dir():
    return ("/mnt/sda/jackie/cnidaria/0.tree/0.peps/OrthoFinder/"
            "Results_Sep14/MultipleSequenceAlignments")
