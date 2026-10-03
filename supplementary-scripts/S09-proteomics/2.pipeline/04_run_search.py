#!/usr/bin/env python3
"""
04_run_search.py
----------------
Generate a Comet parameter file for each (dataset, spectrum file) pair from
`1.metadata/search_params.tsv` and run the database search.

Why a generated parameter file per dataset
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
The reviewers objected that the original pipeline applied one uniform recipe to
every project and never stated the search parameters.  The datasets genuinely
differ -- a neuropeptide study needs a *no-enzyme* search, a Mascot-era coral
study used 20 ppm / 0.5 Da, and so on.  Every parameter that Comet is run with
is therefore written to disk next to the results, so the exact command line and
settings are archived and reproducible.

Uniform across all datasets (deliberate):
    engine .................... Comet 2026.01
    decoy ..................... internal reversed decoys (decoy_search=1), 1:1
    database .................. CnidoSite reference proteome + cRAP
    FDR ....................... Percolator, 1% PSM then 1% protein (see 05_)

Usage:
    python3 04_run_search.py --pilot
    python3 04_run_search.py PXD041235
    python3 04_run_search.py --pilot --threads 32
"""
from __future__ import annotations

import argparse
import hashlib
import json
import os
import subprocess
import sys
from concurrent.futures import ThreadPoolExecutor, as_completed
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
PARAMS = ROOT / "1.metadata" / "search_params.tsv"
EXCLUDE_META = ROOT / "1.metadata" / "excluded_files.tsv"
PRIDE = ROOT / "3.work" / "pride"
DB = ROOT / "3.work" / "db"
SEARCH = ROOT / "3.work" / "search"
COMET = Path("/home/$USER/.local/share/mamba/envs/proteomics/bin/comet")
# Same conda env as Comet; used to turn Thermo .raw into MGF (step 04).
THERMO = COMET.parent / "ThermoRawFileParser"

# `.apl` is included: PXD045585/PXD045587 deposited Sciex peak lists, which
# apl2mgf.py converts without needing the multi-GB .raw files.
SPECTRUM_EXT = (".mgf", ".apl", ".mzml", ".mzxml", ".raw", ".ms2", ".cms2", ".bms2")

# Comet's fixed-modification parameter for each residue, one per residue type:
# `add_<RES>_<name>`.  Used to be spelled out for cysteine only, which silently
# dropped any other fixed modification -- see the note at the call site.
FIXED_MOD_NAMES = {
    "C": "cysteine",       "G": "glycine",        "A": "alanine",
    "S": "serine",         "P": "proline",        "T": "threonine",
    "V": "valine",         "K": "lysine",         "N": "asparagine",
    "Q": "glutamine",      "D": "aspartic_acid",  "E": "glutamic_acid",
    "R": "arginine",       "H": "histidine",      "M": "methionine",
    "F": "phenylalanine",  "U": "selenocysteine", "W": "tryptophan",
    "Y": "tyrosine",       "L": "leucine",        "I": "isoleucine",
    "n": "Nterm_peptide",  "c": "Cterm_peptide",
}


def load_params():
    """Read search_params.tsv (skipping '#' comments) into a dict keyed by PXD."""
    rows = {}
    with PARAMS.open() as fh:
        header = None
        for line in fh:
            if line.startswith("#") or not line.strip():
                continue
            f = line.rstrip("\n").split("\t")
            if header is None:
                header = f
                continue
            rows[f[0]] = dict(zip(header, f))
    return rows


def species_stems(species_field: str):
    """Map a ';'-separated species list to search-DB keys that exist locally.

    Uses `db_key_for`, not `proteome_stem`: for a species with no reference
    proteome the DB is keyed on the dataset's own species (e.g.
    `Orbicella_annularis`, `Actinia_fragacea`) even though the sequences came
    from a transcriptome or a congener, so the key is what must be looked up.

    Redundant surrogates are dropped here, not in `build_combined_db`, so that
    the key built by stage 04 is exactly the one stage 07 derives for the
    dataset page.
    """
    from cnido_common import db_keys_for_field, drop_redundant_surrogates
    keys = db_keys_for_field(species_field)
    return [k for k in drop_redundant_surrogates(keys)
            if (DB / f"{k}.search.fasta").exists()]


def read_provenance(key: str) -> dict:
    from cnido_common import read_provenance as _rp
    return _rp(key)


def build_combined_db(stems):
    """Concatenate per-species search DBs for multi-species datasets.

    `stems` is expected to have been through
    `cnido_common.drop_redundant_surrogates` already, so that the key computed
    here is the same one stage 07 will look up.

    A combined DB needs its own manifest and provenance, not just the FASTA:
    stage 06 reads `<key>.db.tsv` for protein lengths and descriptions, and
    stage 07 reads `<key>.provenance.tsv` to state on the dataset page what was
    searched.  Without them a multi-species dataset would silently report zero
    coverage and an unstated search space.
    """
    if not stems:
        return None
    if len(stems) == 1:
        return DB / f"{stems[0]}.search.fasta"

    key = "_".join(stems)
    out = DB / f"{key}.search.fasta"
    if not out.exists():
        with out.open("w") as o:
            for s in stems:
                o.write((DB / f"{s}.search.fasta").read_text())

    # merge the per-species manifests (one shared header)
    man = DB / f"{key}.db.tsv"
    if not man.exists():
        with man.open("w") as o:
            o.write("protein_id\tis_contaminant\tlength\tdescription\n")
            seen_crap = False
            for s in stems:
                src = DB / f"{s}.db.tsv"
                if not src.exists():
                    continue
                with src.open() as fh:
                    next(fh, None)                      # drop per-file header
                    for line in fh:
                        # cRAP is identical in every component DB; keep one copy
                        if line.startswith("CRAP_"):
                            if seen_crap:
                                continue
                            seen_crap = True
                        o.write(line)

    prov = DB / f"{key}.provenance.tsv"
    if not prov.exists():
        parts, paths, kinds, surrogates, gmaps, n_tot = [], [], [], [], [], 0
        for s in stems:
            pv = DB / f"{s}.provenance.tsv"
            if not pv.exists():
                continue
            d = dict(l.rstrip("\n").split("\t", 1) for l in pv.open() if l.strip()
                     and not l.startswith("key\t"))
            sp = d.get("source_species") or s
            st = d.get("source_type") or "reference"
            parts.append(f"{sp} ({st})")
            paths.append(d.get("proteome_path", ""))
            kinds.append(st)
            if d.get("is_surrogate") == "1":
                surrogates.append(sp)
            if d.get("gene_map"):
                gmaps.append(d["gene_map"])
            n_tot += int(d.get("n_target_proteins") or 0)
        with prov.open("w") as fp:
            fp.write("key\tvalue\n")
            for k, v in (
                ("dataset_species", "; ".join(stems)),
                ("db_key", key),
                ("proteome_path", "; ".join(p for p in paths if p)),
                ("source_type", "combined"),
                ("source_species", "; ".join(parts)),
                ("is_surrogate", "1" if surrogates else "0"),
                ("n_target_proteins", str(n_tot)),
                ("gene_map", "; ".join(gmaps)),
                ("label", "Combined search space of " + ", ".join(parts)),
                ("note",
                 "This deposit contains more than one species. The search database is the "
                 "union of the proteomes available for those species, so an identification "
                 "may come from any of them and the dataset is not attributable to a single "
                 "species."
                 + (" It includes a transcriptome-derived surrogate for "
                    + ", ".join(surrogates)
                    + ", which has no proteome of its own; peptides matching that part of "
                      "the search space are reported against transcript sequences."
                    if surrogates else "")),
            ):
                fp.write(f"{k}\t{v}\n")
    return out


def comet_version_line() -> str:
    """Ask the Comet binary for its version banner (first line of a default
    params file).  Deriving it from the binary keeps the generated parameter
    files valid across Comet upgrades."""
    import tempfile
    with tempfile.TemporaryDirectory() as td:
        subprocess.run([str(COMET), "-p"], cwd=td, capture_output=True, text=True)
        p = Path(td) / "comet.params.new"
        if p.exists():
            for line in p.read_text().splitlines():
                if line.startswith("# comet_version"):
                    return line
    return "# comet_version unknown"


def write_comet_params(path: Path, db: Path, p: dict, threads: int, out_dir: Path):
    """Emit a fully explicit Comet parameter file for one dataset."""
    try:
        prec = float(p["cnido_prec_ppm"])
    except (KeyError, ValueError):
        prec = 10.0

    # Precursor tolerance units.  Orbitrap projects are searched in ppm, but the
    # low-resolution ion-trap datasets (Q TRAP, e.g. PXD027774) must be searched
    # in Da -- 1.5 ppm on a unit-resolution precursor would match nothing.
    # Comet: 0 = amu/Da, 1 = mmu, 2 = ppm.
    units = (p.get("cnido_prec_units") or "ppm").strip().lower()
    if units in ("da", "amu", "daltons"):
        prec_units, units_label = 0, "Da"
    elif units in ("mmu",):
        prec_units, units_label = 1, "mmu"
    else:
        prec_units, units_label = 2, "ppm"
    try:
        frag = float(p["cnido_frag_da"])
    except (KeyError, ValueError):
        frag = 0.02
    enzyme = int(p.get("cnido_enzyme", 1) or 1)
    termini = int(p.get("cnido_termini", 2) or 2)
    missed = int(p.get("cnido_missed", 2) or 2)
    fixed = (p.get("cnido_fixed") or "").strip()
    var = (p.get("cnido_variable") or "").strip()

    lines = [
        comet_version_line(),
        f"# CnidoSite proteomics re-processing -- dataset {p['pxd']}",
        f"# species = {p.get('species','')}   tissue = {p.get('tissue','')}",
        f"# original study: {p.get('orig_engine','')} / {p.get('orig_database','')}",
        f"# original tolerances: {p.get('orig_prec','')} precursor, {p.get('orig_frag','')} fragment",
        f"# original FDR: {p.get('orig_fdr','')}   PMID {p.get('orig_pmid','')}",
        f"# NOTE: {p.get('notes','')}",
        "",
        f"database_name = {db}",
        "decoy_search = 1                 # internal reversed decoys, concatenated (1:1)",
        f"num_threads = {threads}",
        "",
        f"peptide_mass_tolerance_upper = {prec}",
        f"peptide_mass_tolerance_lower = -{prec}",
        f"peptide_mass_units = {prec_units}           # {units_label}",
        "precursor_tolerance_type = 0     # 0=MH+",
        "isotope_error = 0",
        "",
        f"search_enzyme_number = {enzyme}",
        "search_enzyme2_number = 0",
        f"sample_enzyme_number = {enzyme}",
        f"num_enzyme_termini = {termini}",
        f"allowed_missed_cleavage = {missed}",
        "",
        f"fragment_bin_tol = {frag}",
        "fragment_bin_offset = 0.0",
        "theoretical_fragment_ions = 0",
        "use_A_ions = 0", "use_B_ions = 1", "use_C_ions = 0",
        "use_X_ions = 0", "use_Y_ions = 1", "use_Z_ions = 0", "use_Z1_ions = 0",
        "use_NL_ions = 0",
        "",
        "max_variable_mods_in_peptide = 3",
        "require_variable_mod = 0",
    ]

    # fixed modification, e.g. "C+57.021464"
    #
    # Comet names each fixed modification after both the residue and the group,
    # `add_<RES>_<group>`, and only supports one fixed modification per residue.
    # This used to write the correct line for cysteine and a *comment* for
    # everything else, claiming the modification was "applied below" when no
    # such line was ever emitted -- so a dataset fixed on, say, lysine would
    # have been searched without it, silently.  Every dataset currently in
    # search_params.tsv uses C+57.021464 alone, so no result was affected; a
    # missing name is now a loud failure rather than a dropped parameter.
    if fixed:
        for tok in fixed.split(","):
            tok = tok.strip()
            if not tok or "+" not in tok:
                continue
            res, mass = tok.split("+", 1)
            res, mass = res.strip(), mass.strip()
            name = FIXED_MOD_NAMES.get(res)
            if name is None:
                raise SystemExit(
                    f"04_run_search: fixed modification {tok!r} names residue "
                    f"{res!r}, which has no Comet add_<RES>_<group> parameter. "
                    f"Add it to FIXED_MOD_NAMES, or express it as a variable "
                    f"modification -- it cannot be silently omitted.")
            lines.append(f"add_{res}_{name} = {mass}")
    # variable modification, e.g. "M+15.9949"
    vi = 0
    for tok in var.split(","):
        tok = tok.strip()
        if not tok or "+" not in tok:
            continue
        res, mass = tok.split("+", 1)
        vi += 1
        if vi > 15:
            break
        n = str(vi).zfill(2)
        lines.append(f"variable_mod{n} = {mass.strip()} {res.strip()} 0 3 -1 0 0 0.0")

    lines += [
        "",
        "digest_mass_range = 600.0 5000.0",
        "peptide_length_range = 5 50",
        "min_precursor_charge = 1",
        "max_precursor_charge = 6",
        "max_fragment_charge = 3",
        "clip_nterm_methionine = 0",
        "spectrum_batch_size = 15000",
        "minimum_peaks = 10",
        "equal_I_and_L = 1",
        "",
        "output_sqtfile = 0",
        "output_pepxmlfile = 1",
        "output_percolatorfile = 1        # PIN file for Percolator (step 05)",
        "output_txtfile = 0",
        "output_mzidentmlfile = 0",
        "num_output_lines = 5",
        "max_duplicate_proteins = 10",
        "",
        "mass_offsets =",
        "",
        "# COMET_ENZYME_INFO _must_ be at the end of this parameters file",
        "[COMET_ENZYME_INFO]",
        "0.  Cut_everywhere         0      -           -",
        "1.  Trypsin                1      KR          P",
        "2.  Trypsin/P              1      KR          -",
        "3.  Lys_C                  1      K           P",
        "4.  Lys_N                  1      -           X",
        "5.  Arg_C                  1      R           P",
        "6.  Asp_N                  1      -           D",
        "7.  CNBr                   1      M           P",
        "8.  Glu_C                  1      DE          P",
        "9.  PepsinA                1      FL          P",
        "10. Chymotrypsin           1      FWYL        P",
        "11. No_cut                 1      @           @",
    ]
    path.write_text("\n".join(lines) + "\n")


def _md5(path: Path, chunk: int = 1 << 20) -> str:
    h = hashlib.md5()
    with path.open("rb") as fh:
        for block in iter(lambda: fh.read(chunk), b""):
            h.update(block)
    return h.hexdigest()


def drop_duplicate_spectra(specs, pxd: str, out: Path, deposited=None):
    """Drop files holding byte-identical spectra to one already kept.

    One run can reach the working directory under two names: the deposit's own,
    and a shorter name from a manual fix.  PXD009253 had both -- the
    AipInf_153-4 run sat there as the deposited
    `..._170816_160819211115.mzid_....MGF` *and* as a renamed
    `AipInf_153-4.mgf`, identical md5 -- so the same spectra were searched
    twice and every PSM from them counted twice in the release.  Nothing
    downstream can see that: the duplicate looks like more evidence.

    Compare size first and md5 only within a size group, so the read cost falls
    on the few files that could plausibly be the same.  A pin already on disk
    for a dropped file is removed, because 05 merges every pin it finds and
    would otherwise reintroduce the duplicate.
    """
    if deposited:
        # Decide which name survives a duplicate before merging them: prefer
        # the one the deposit uses, so the run's provenance reads as the
        # deposited name and not as whatever a local fix happened to call it.
        specs = sorted(specs, key=lambda f: (f.name not in deposited, f.name))
    by_size = {}
    for s in specs:
        by_size.setdefault(s.stat().st_size, []).append(s)
    kept, seen = [], {}
    for s in specs:
        group = by_size[s.stat().st_size]
        if len(group) == 1:
            kept.append(s)
            continue
        digest = _md5(s)
        if digest in seen:
            original = seen[digest]
            print(f"[{pxd}] DUPLICATE {s.name} is byte-identical to "
                  f"{original.name} -- searched once", file=sys.stderr)
            stale = out / f"{s.stem}.pin"
            if stale.exists():
                stale.unlink()
                print(f"[{pxd}]   removed stale {stale.name}, which would "
                      f"otherwise be merged in again", file=sys.stderr)
        else:
            seen[digest] = s
            kept.append(s)
    return kept


def prepare_input(src: Path, out: Path, limit: int = 0):
    """Materialise a Comet-readable peak list for one deposited file.

    Returns the path Comet should be pointed at, or None when the format cannot
    be converted with the tools available here.

    Comet writes its output next to the *input* file, so everything is staged
    inside the per-dataset output directory.  MGF is rewritten rather than
    symlinked for two reasons: some PRIDE MGFs use CRLF line endings, which make
    Comet dump core, and `limit` needs a truncated copy.
    """
    ext = src.suffix.lower()
    mgf = out / (src.stem + ".mgf") if ext != ".mgf" else out / src.name

    if mgf.exists():
        return mgf

    if ext == ".mgf":
        _normalise_mgf(src, mgf, limit)
        return mgf

    if ext == ".apl":
        # Sciex peak list -- see apl2mgf.py
        sys.path.insert(0, str(Path(__file__).resolve().parent))
        from apl2mgf import convert_file
        st = convert_file(src, mgf, max_spectra=limit)
        print(f"    apl -> mgf: {st['spectra_out']}/{st['spectra_in']} spectra",
              file=sys.stderr)
        if st["spectra_out"] == 0:
            mgf.unlink(missing_ok=True)
            print(f"    !! {src.name} produced no spectra", file=sys.stderr)
            return None
        return mgf

    if ext == ".raw":
        # ThermoRawFileParser ships with the same conda env as Comet.
        if not THERMO.exists():
            print(f"    !! {src.name}: ThermoRawFileParser not found at {THERMO}",
                  file=sys.stderr)
            return None
        tmp = out / (src.stem + ".raw.mgf_tmp")
        tmp.mkdir(exist_ok=True)
        r = subprocess.run([str(THERMO), "-i", str(src.resolve()),
                            "-o", str(tmp), "-f", "0", "-m", "0"],
                           capture_output=True, text=True)
        produced = list(tmp.glob("*.mgf"))
        if r.returncode != 0 or not produced:
            print(f"    !! {src.name}: ThermoRawFileParser failed "
                  f"(exit {r.returncode})", file=sys.stderr)
            return None
        produced[0].replace(mgf)
        for junk in tmp.iterdir():
            junk.unlink()
        tmp.rmdir()
        return mgf

    if ext in (".mzml", ".mzxml"):
        # Comet reads these natively; no copy needed.
        link = out / src.name
        if not link.exists():
            link.symlink_to(src.resolve())
        return link

    if ext in (".wiff", ".d"):
        print(f"    !! {src.name}: Sciex {ext} needs msconvert (ProteoWizard), "
              f"which is not installed -- this dataset cannot be searched here",
              file=sys.stderr)
        return None

    print(f"    !! {src.name}: unsupported spectrum format {ext}", file=sys.stderr)
    return None


def _normalise_mgf(src: Path, dst: Path, limit: int = 0):
    """Rewrite an MGF with LF endings, unique TITLEs, and optionally only the
    first `limit` spectra.  Streams, so a multi-GB MGF never lands in memory.

    Why TITLEs are made unique
    ~~~~~~~~~~~~~~~~~~~~~~~~~~
    Some deposited MGFs carry no scan number.  PXD027774's titles look like
    "Sample: Hydra  Elution from: 0.24 to 0.24   period: 0 ..." and repeat: 26,099
    spectra share only 9,380 distinct titles.  Comet copies the TITLE into the
    pepXML `spectrum` attribute, so the archived result had 2,693 spectrum_query
    elements but just 381 distinguishable names -- a PSM could not be traced back
    to the spectrum it came from, which is exactly the provenance complaint under
    review.  Appending the 1-based spectrum ordinal fixes it.  The deposited title
    is preserved verbatim as a prefix; nothing is discarded.

    (Comet's PIN file is unaffected either way -- its SpecId already encodes
    scan and charge, which is why the FDR step was never at risk.)
    """
    kept = 0
    with src.open("r", errors="replace") as fh, dst.open("w") as out:
        for line in fh:
            line = line.rstrip("\r\n")
            if line.startswith("BEGIN IONS") and limit and kept >= limit:
                break
            if line.startswith("TITLE="):
                line = f"{line} #{kept + 1}"
            out.write(line + "\n")
            if line.startswith("END IONS"):
                kept += 1


def load_excludes() -> dict:
    """Files that must not be searched, keyed pxd -> {filename: reason}.

    A deposit can mix acquisition modes inside one project and the filename is
    the only place that says so.  PXD014076 ships 44 phospho DDA runs and 20 DIA
    (SWATH) runs together; Comet is a DDA database-search engine, and a DIA MS2
    is a composite of every co-isolated precursor in the window, so searching
    one does not fail -- it returns confident-looking nonsense.  Keeping the
    list in 1.metadata/ rather than deleting files from 3.work/ means the
    exclusion is reproducible and shows up in the dataset's own provenance.
    """
    out: dict = {}
    if not EXCLUDE_META.exists():
        return out
    for ln in EXCLUDE_META.read_text().splitlines():
        if not ln.strip() or ln.startswith("#"):
            continue
        f = ln.split("\t")
        if len(f) < 3:
            continue
        out.setdefault(f[0].strip(), {})[f[1].strip()] = f[2].strip()
    return out


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("pxds", nargs="*")
    ap.add_argument("--pilot", action="store_true")
    ap.add_argument("--threads", type=int, default=32,
                    help="threads handed to a single Comet process")
    ap.add_argument("-j", "--jobs", type=int, default=1,
                    help="Comet processes to run at once; 1 keeps the old "
                         "file-at-a-time behaviour. Total CPU use is roughly "
                         "--jobs x --threads, so size them to the machine")
    ap.add_argument("--limit-spectra", type=int, default=0,
                    help="only search the first N spectra per file (smoke test)")
    a = ap.parse_args()

    allp = load_params()
    excludes = load_excludes()
    want = list(a.pxds)
    if a.pilot:
        want += list(allp)
    want = [w for w in want if w in allp]
    if not want:
        sys.exit("no known datasets requested; see 1.metadata/search_params.tsv")

    # Anything that would make a result describe only part of its experiment.
    # A run that cannot be reproduced in full is not a smaller result, it is a
    # different and unsupported claim -- which is the whole objection this
    # reprocessing exists to answer.  Collect and refuse to emit.
    failures: list = []
    empty_ok: list = []

    for pxd in want:
        p = allp[pxd]
        d = PRIDE / pxd
        specs = sorted(f for f in d.glob("*") if f.suffix.lower() in SPECTRUM_EXT) if d.exists() else []
        ex = excludes.get(pxd, {})
        if ex:
            keep = []
            for f in specs:
                if f.name in ex:
                    print(f"[{pxd}] EXCLUDED {f.name}: {ex[f.name]}", file=sys.stderr)
                else:
                    keep.append(f)
            specs = keep

        # A project can be fetched only in part.  An interrupted download keeps
        # whatever arrived and records nothing about what did not, so the search
        # runs on a fraction of the experiment while every later step calls the
        # result complete.  PXD014076 reached the release that way -- searched
        # from 19 of its 44 deposited DDA runs, with the shortfall invisible.
        # Compare what is on disk against the PRIDE inventory and say so.
        inventory = d / "pride_files.json"
        if inventory.exists():
            try:
                recs = json.loads(inventory.read_text())
            except ValueError:
                recs = []
            if specs:
                sfx = {f.suffix.lower() for f in specs}
            else:
                # Nothing staged at all: judge against the format we would use,
                # which is the same choice pick() makes -- peak lists if the
                # deposit offers them, otherwise raw.  Otherwise an entirely
                # absent dataset reports "no spectra" and hides the real cause.
                names = [r.get("fileName", "").lower() for r in recs]
                sfx = ({".mgf", ".apl"}
                       if any(n.endswith((".mgf", ".apl")) for n in names)
                       else {".raw", ".mzml", ".mzxml", ".wiff"})
            deposited = {r.get("fileName", "") for r in recs
                         if Path(r.get("fileName", "")).suffix.lower() in sfx}
            on_disk = {f.name for f in specs}
            missing = sorted(deposited - on_disk - set(ex))
            renamed = sorted(on_disk - deposited)
            # Pair a local file matching no deposited name with a missing
            # deposit entry by byte count.  The same run stored under another
            # name has the same size; a file named for an accession that holds
            # a different digest does not.  PXD017813.raw and
            # PXD027774_PEAK.mgf are both the first case, and reporting them as
            # "not searched" would be false.  Doing the size check here also
            # means the warning that remains is worth reading.
            dep_size = {r.get("fileName", ""): int(r.get("fileSizeBytes") or 0)
                        for r in recs}
            local_size = {}
            for f in specs:
                # A .gz is advertised by PRIDE at its uncompressed size, so
                # length is not comparable for one -- see the fetch notes.
                if f.suffix.lower() != ".gz":
                    local_size.setdefault(f.stat().st_size, f.name)
            matched, absent = {}, []
            for m in missing:
                f = local_size.get(dep_size.get(m, -1))
                if f:
                    matched[m] = f
                else:
                    absent.append(m)
            for m, f in sorted(matched.items()):
                print(f"[{pxd}] {f} matches deposited {m} byte for byte -- "
                      f"same run, different local name", file=sys.stderr)
            for m in renamed:
                if m not in matched.values():
                    print(f"[{pxd}] note: local {m} matches no deposited name, "
                          f"and no missing deposit entry by size -- confirm what "
                          f"is inside it before trusting the result",
                          file=sys.stderr)
            if absent:
                print(f"[{pxd}] ERROR: {len(absent)} of {len(deposited)} "
                      f"deposited {sorted(sfx)} file(s) are not on disk:",
                      file=sys.stderr)
                for m in absent:
                    print(f"    missing   {m}", file=sys.stderr)
                failures.append(
                    (pxd, "incomplete",
                     f"{len(absent)} of {len(deposited)} deposited file(s) "
                     f"absent: {', '.join(absent[:4])}"
                     + (f" (+{len(absent) - 4} more)" if len(absent) > 4 else "")))
                print(f"[{pxd}] NOT SEARCHED: searching part of a deposit "
                      f"yields a result about part of an experiment, and nothing "
                      f"downstream can tell the difference. Run "
                      f"02_fetch_pride.py {pxd} to fetch the rest, or record the "
                      f"files in 1.metadata/excluded_files.tsv with a reason "
                      f"if they must stay out.", file=sys.stderr)
                continue
        if not specs:
            print(f"[{pxd}] ERROR: no spectra on disk and none searchable",
                  file=sys.stderr)
            failures.append((pxd, "no-spectra", "nothing on disk to search"))
            continue

        stems = species_stems(p["species"])
        db = build_combined_db(stems)
        if db is None:
            print(f"[{pxd}] no reference proteome for {p['species']} -- "
                  f"cannot search; will be reported as 'no reference proteome'",
                  file=sys.stderr)
            continue

        out = SEARCH / pxd
        out.mkdir(parents=True, exist_ok=True)
        pf = out / "comet.params"
        write_comet_params(pf, db, p, a.threads, out)

        specs = drop_duplicate_spectra(specs, pxd, out, deposited)

        todo = []
        for s in specs:
            if (out / f"{s.stem}.pin").exists():
                print(f"[{pxd}] {s.stem}: already searched", file=sys.stderr)
            else:
                todo.append(s)

        def search_one(s):
            """Convert and search one file.  Returns (tag, returncode, note).

            Comet writes its output next to the *input* file, so work on a
            normalised copy inside the output directory.  Some PRIDE MGFs use
            CRLF line endings, which makes Comet dump core -- normalise to LF.
            Each file's inputs and outputs are named for its own stem, so
            several of these can run at once without touching each other.
            """
            tag = s.stem
            work_in = prepare_input(s, out, a.limit_spectra)
            if work_in is None:
                return (tag, None, "cannot convert to a searchable peak list", "")
            r = subprocess.run([str(COMET), "-P" + str(pf), work_in.name],
                               cwd=out, capture_output=True, text=True)
            text = r.stdout + "\n" + r.stderr
            (out / f"{tag}.comet.log").write_text(text)
            return (tag, r.returncode, "", text)

        def report(tag, rc, note, text):
            """Judge one file's outcome; record anything that voids the result.

            A non-zero Comet exit is NOT automatically a failure.  Comet exits 1
            when every spectrum is filtered out, which is what happens to the
            high-mass .apl bins in PXD045585/587 and is a real result: an
            extended-range re-search of those bins found 0 PSMs against 1,800 in
            a positive control (see the mass-range notes).  Failing on it would
            block two datasets over spectra that hold nothing.  Anything else
            that exits non-zero, or a file that will not convert, does fail.
            """
            if rc is None:
                print(f"[{pxd}] {tag}: FAILED -- {note} (see message above)",
                      file=sys.stderr)
                failures.append((pxd, "convert", tag))
            elif rc == 0:
                print(f"[{pxd}] {tag}: ok -> {tag}.pin", file=sys.stderr)
            elif "no spectra searched" in text.lower():
                empty_ok.append((pxd, tag))
                print(f"[{pxd}] {tag}: no spectra in range -- 0 PSMs (expected)",
                      file=sys.stderr)
            else:
                print(f"[{pxd}] {tag}: FAILED -- comet exit {rc}; see "
                      f"{out / f'{tag}.comet.log'}", file=sys.stderr)
                failures.append((pxd, "comet", f"{tag} (exit {rc})"))

        if todo:
            print(f"[{pxd}] searching {len(todo)} file(s) with "
                  f"{a.jobs} x {a.threads} threads", file=sys.stderr)
        if a.jobs <= 1:
            for s in todo:
                report(*search_one(s))
        else:
            with ThreadPoolExecutor(a.jobs) as ex:
                futs = {ex.submit(search_one, s): s for s in todo}
                for fu in as_completed(futs):
                    s = futs[fu]
                    try:
                        report(*fu.result())
                    except Exception as e:                        # noqa: BLE001
                        print(f"[{pxd}] {s.stem}: FAILED "
                              f"{type(e).__name__}: {e}", file=sys.stderr)
                        failures.append(
                            (pxd, "crash", f"{s.stem}: {type(e).__name__}"))

        # "No spectra in range" is legitimate for an individual file, but not
        # for every file in a dataset: that means the parameters, not the
        # spectra, are wrong.
        n_empty = sum(1 for f_pxd, _ in empty_ok if f_pxd == pxd)
        if todo and n_empty == len(todo):
            failures.append((pxd, "all-empty",
                             f"all {len(todo)} file(s) held no spectrum inside "
                             f"digest_mass_range -- check the search parameters"))
            print(f"[{pxd}] ERROR: every file was filtered out; that points at "
                  f"the parameters, not the data", file=sys.stderr)

    if failures:
        print("\n" + "=" * 72, file=sys.stderr)
        print(f"# NOT COMPLETE: {len(failures)} problem(s) -- exit 1",
              file=sys.stderr)
        print("=" * 72, file=sys.stderr)
        for f_pxd, kind, detail in failures:
            print(f"  {f_pxd:<12} {kind:<12} {detail}", file=sys.stderr)
        print("\nNo result here describes a whole experiment, so none should be "
              "released. Fix the cause and re-run, or record a file that "
              "genuinely cannot be searched in 1.metadata/excluded_files.tsv "
              "with its reason -- that keeps the decision visible.", file=sys.stderr)
        sys.exit(1)

    print("\n# search step complete", file=sys.stderr)


if __name__ == "__main__":
    main()
