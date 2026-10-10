# License

CnidoSite's own work is released under two separate grants — one for the code, one for the
data. Third-party components bundled with the site keep their own licenses and are **not**
covered by either grant — see [Third-party components](#third-party-components).

## Code (`site/`, `scripts/`, `schema/`)

**MIT License.**

```
MIT License

Copyright (c) 2026 The CnidoSite authors

Permission is hereby granted, free of charge, to any person obtaining a copy of this software
and associated documentation files (the "Software"), to deal in the Software without
restriction, including without limitation the rights to use, copy, modify, merge, publish,
distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the
Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or
substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING
BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
```

## Curated data (`data/`, `manifests/`)

**Creative Commons Attribution 4.0 International (CC BY 4.0)** —
https://creativecommons.org/licenses/by/4.0/

You are free to share and adapt the curated data for any purpose, including commercially,
provided you give appropriate credit and indicate if changes were made.

Note that the curated data are **derived from third-party sources** — NCBI/RefSeq/GenBank
assemblies and annotations, WoRMS taxonomy, the Paleobiology Database, published
transcriptomes and epigenome datasets, and the single-cell studies listed on the site. Those
upstream terms continue to apply to the derived tables. The relevant accession numbers and
source publications are recorded in the database itself and on the corresponding pages of
https://cnidosite.org.

## Third-party components

Bundled under `site/` and used unchanged:

| Component | Where | License |
|---|---|---|
| jQuery, jQuery UI, Highcharts, Leaflet, Modernizr, Cytoscape.js, phylotree.js, D3, Bootstrap | `site/js/`, `site/css/`, `site/cytoscape/`, `site/phylotree/` | MIT / BSD / GPL-3 (Highcharts is **non-commercial** — see below) |
| NCBI BLAST CGI front end | `site/blast/` | Public domain (US Government work), except the bundled binaries |
| Primer3Plus | `site/primer3plus/` | GPL-3 |
| GSEA | `site/GSEA/` | BSD-3 (Broad Institute); the `gsea/` data directory is **not** included |

**Highcharts** is redistributed here in the form used by the published site
(`site/js/highcharts.js`, `site/js/highcharts-more.js`, `site/cytoscape/js/highcharts.js`) and
is **not** covered by the MIT grant above. CnidoSite is a non-commercial academic resource,
which is within Highcharts' free-use terms, but anyone reusing this repository for a
commercial purpose must obtain their own Highcharts licence or replace the library.

Compiled binaries (the BLAST `.REAL` executables, GSEA's `exp_file_credential`, the
Primer3Plus `node_modules` tree) are deliberately **not** included; install them from upstream
as described in `docs/DEPLOYMENT.md`.
