# License

> **Status: to be confirmed by the authors before the repository is made public.**
> Two separate grants are needed — one for the code, one for the data — and neither has been
> chosen yet. Nothing below is in force until the corresponding line is uncommented.

## Code (`site/`, `scripts/`, `schema/`)

The web application is the authors' own work. Third-party components bundled with it keep
their own licenses and are **not** covered by the grant below — see
[Third-party components](#third-party-components).

Recommended: **MIT**, which is what most database front ends use and what makes the code
reusable:

```
MIT License

Copyright (c) 2026 <authors>

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

Recommended: **CC BY 4.0**, which is the usual choice for a curated biological database and
allows reuse with attribution.

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
| jQuery, jQuery UI, Highcharts, Leaflet, Modernizr, Cytoscape.js, phylotree.js, D3, Bootstrap | `site/js/`, `site/css/`, `site/cytoscape/`, `site/phylotree/` | MIT / BSD / GPL-3 (Highcharts is **non-commercial** — check before redistribution) |
| NCBI BLAST CGI front end | `site/blast/` | Public domain (US Government work), except the bundled binaries |
| Primer3Plus | `site/primer3plus/` | GPL-3 |
| GSEA | `site/GSEA/` | BSD-3 (Broad Institute); the `gsea/` data directory is **not** included |

Compiled binaries (the BLAST `.REAL` executables, GSEA's `exp_file_credential`, the
Primer3Plus `node_modules` tree) are deliberately **not** included; install them from upstream
as described in `docs/DEPLOYMENT.md`.
