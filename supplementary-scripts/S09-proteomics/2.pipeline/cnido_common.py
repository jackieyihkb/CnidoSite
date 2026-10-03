#!/usr/bin/env python3
"""
cnido_common.py
---------------
Shared helpers for the CnidoSite proteomics pipeline: species-name resolution
(synonyms -> reference proteome stem) and small path constants.

Keeping the synonym table in one place matters because the same species is
spelled differently across CnidoSite modules, PRIDE records and proteome files.
"""
from __future__ import annotations

import csv
import re
from dataclasses import dataclass
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
PROTEOME_DIR = Path("/mnt/sda/jackie/cnidaria/2.anno")
TRANSCRIPTOME_PROTEOME_DIR = Path("/mnt/sdb/jackie/cnidaria_omics/transcriptome_proteins")
DB = ROOT / "3.work" / "db"
PROTEOME_SOURCES = ROOT / "1.metadata" / "proteome_sources.tsv"

# Shown in `proteomic_datasets.proteome_source` for the default case.
REFERENCE_LABEL = "CnidoSite reference proteome (2.anno), BUSCO-validated"

# Species names as they appear in proteomic datasets -> CnidoSite proteome stem.
# `Exaiptasia pallida`, `Aiptasia sp.` and `Aiptasia pulchella` are all junior
# synonyms / misapplications of Exaiptasia diaphana.
PROTEOME_ALIAS = {
    "Exaiptasia pallida": "Exaiptasia_diaphana",
    "Aiptasia sp.": "Exaiptasia_diaphana",
    "Aiptasia pulchella": "Exaiptasia_diaphana",
    "Exaiptasia diaphana": "Exaiptasia_diaphana",
    "Orbicella annularis": "Orbicella_annularis",
    "Hydra vulgaris": "Hydra_vulgaris",
}


def proteome_stem(species: str) -> str | None:
    """Resolve any spelling of a species name to an existing reference proteome stem.

    Only covers the BUSCO-validated reference proteomes in `2.anno`.  For the
    transcriptome-derived and congener-surrogate proteomes that fill the gaps,
    use `resolve_proteome()`, which layers those on top.
    """
    cand = PROTEOME_ALIAS.get(species, species.replace(" ", "_"))
    if (PROTEOME_DIR / f"{cand}.pep").exists():
        return cand
    t = re.sub(r"[^a-z]", "", cand.lower())
    for q in sorted(PROTEOME_DIR.glob("*.pep")):
        if re.sub(r"[^a-z]", "", q.stem.lower()) == t:
            return q.stem
    return None


# --------------------------------------------------------------- provenance
@dataclass(frozen=True)
class ProteomeRef:
    """A search database and, crucially, where it came from.

    Reviewers questioned dataset provenance, so a proteome is never just a path:
    the search space's origin (`source_type`) travels with it all the way into
    `proteomic_datasets.proteome_source` and onto the dataset page.

    `source_type` is one of:
      reference    -- CnidoSite reference proteome (2.anno), BUSCO-validated
      transcriptome-- TransDecoder proteins from a Trinity assembly of the
                      *same* species (no genome annotation exists for it)
      congener     -- surrogate proteome from a *different* species in the same
                      genus; identifications are conditional on conservation
    """
    species: str
    db_key: str          # names the built DB; never collides across species
    path: Path
    source_type: str
    source_species: str
    n_proteins: int
    label: str
    note: str
    gene_map: Path | None = None

    @property
    def is_reference(self) -> bool:
        return self.source_type == "reference"

    @property
    def is_surrogate(self) -> bool:
        """True when the search space is not the dataset's own species."""
        return self.source_species != self.species


def load_proteome_registry() -> dict[str, dict]:
    """Read `1.metadata/proteome_sources.tsv`, keyed by dataset species name."""
    out: dict[str, dict] = {}
    if not PROTEOME_SOURCES.exists():
        return out
    with PROTEOME_SOURCES.open() as fh:
        for row in csv.DictReader(fh, delimiter="\t"):
            sp = (row.get("species") or "").strip()
            if not sp or sp.startswith("#"):
                continue
            out[sp] = {k: (v or "").strip() for k, v in row.items()}
    return out


def resolve_proteome(species: str) -> ProteomeRef | None:
    """Best available search proteome for a dataset species, with provenance.

    Order: an explicit registry entry (transcriptome or congener surrogate)
    wins, because those exist precisely for species `2.anno` cannot serve; then
    the BUSCO-validated reference proteome; then nothing.
    """
    reg = load_proteome_registry().get(species)
    if reg:
        path = Path(reg["proteome_path"])
        if path.exists():
            gm = reg.get("gene_map") or ""
            return ProteomeRef(
                species=species,
                db_key=reg["db_key"],
                path=path,
                source_type=reg["source_type"],
                source_species=reg.get("source_species") or species,
                n_proteins=int(reg.get("n_proteins") or 0),
                label=reg.get("label") or "",
                note=reg.get("note") or "",
                gene_map=Path(gm) if gm and Path(gm).exists() else None,
            )

    stem = proteome_stem(species)
    if stem is None:
        return None
    return ProteomeRef(
        species=species,
        db_key=stem,
        path=PROTEOME_DIR / f"{stem}.pep",
        source_type="reference",
        source_species=species,
        n_proteins=0,
        label=REFERENCE_LABEL,
        note="",
        gene_map=None,
    )


def db_key_for(species: str) -> str | None:
    """Name of the built search DB for a species (see `ProteomeRef.db_key`)."""
    stem = proteome_stem(species)
    if stem is not None:
        return stem
    ref = resolve_proteome(species)
    return ref.db_key if ref else None


def read_provenance(key: str) -> dict:
    """The `<key>.provenance.tsv` sidecar written by stage 03, as a dict."""
    p = DB / f"{key}.provenance.tsv"
    if not p.exists():
        return {}
    out = {}
    with p.open() as fh:
        for line in fh:
            k, _, v = line.rstrip("\n").partition("\t")
            if k and k != "key":
                out[k] = v
    return out


# ------------------------------------------------- multi-species datasets
def db_keys_for_field(species_field: str) -> list[str]:
    """Search-DB keys for a ';'-separated species field, in order."""
    keys = []
    for s in (species_field or "").split(";"):
        s = s.strip()
        if not s:
            continue
        k = db_key_for(s)
        if k:
            keys.append(k)
    return keys


def drop_redundant_surrogates(keys: list[str]) -> list[str]:
    """Drop surrogate components already covered by a reference component.

    A congener surrogate stands in for a species that has no proteome.  If that
    same species is *also* present under its own name, the surrogate is a
    near-duplicate of its sequences: keeping both would inflate the search space
    with redundant targets and tilt the target-decoy competition.  Concretely, a
    myxozoan study holding both M. honghuensis (reference) and M. wulii (searched
    via the M. honghuensis transcriptome surrogate) must use the reference half
    only.
    """
    prov = {k: read_provenance(k) for k in keys}
    own = {p.get("dataset_species") for p in prov.values() if p}
    keep = []
    for k in keys:
        p = prov.get(k) or {}
        if (p.get("source_type") == "congener"
                and p.get("source_species") in own
                and p.get("source_species") != p.get("dataset_species")):
            continue
        keep.append(k)
    return keep


def db_key_for_field(species_field: str) -> str | None:
    """Key of the (possibly combined) search DB for a dataset's species field.

    Single source of truth for how a multi-species dataset names its database:
    stage 04 builds it, stage 07 looks up its provenance, and the two must agree
    or the dataset page would describe a search space that was never used.
    """
    keys = drop_redundant_surrogates(db_keys_for_field(species_field))
    if not keys:
        return None
    return keys[0] if len(keys) == 1 else "_".join(keys)


def search_db_for(species: str) -> Path | None:
    """Path to the built Comet search DB for a species, if it exists."""
    key = db_key_for_field(species)
    if key is None:
        return None
    p = DB / f"{key}.search.fasta"
    return p if p.exists() else None
