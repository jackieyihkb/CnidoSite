#!/usr/bin/env bash
# Render the module's pages to static HTML and serve them, so the result can be
# looked at in a browser instead of inferred from the CSS.
#
# Not part of the test suite: the suite asserts on the HTML, this is for judging
# layout and type size, which no assertion covers.  The HTML is produced by the
# same render_one.php the suite uses, against the same fixture images, and the
# site's own stylesheet is fetched from the live host read-only, so what the
# browser shows is what the page will look like once deployed.
#
# Usage:  pipeline/tools/preview_pages.sh [port]
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
OUT="${SC_PREVIEW_DIR:-/tmp/scpreview}"
PORT="${1:-8765}"
KEY="${SC_SSH_KEY:-/home/$USER/.local/share/codeg/uploads/new-1789733092332-k2r45m/id_rsa(2)}"
HOST="${SC_SSH_HOST:-<SITE-ACCOUNT>@<SITE-HOST>}"
PHP="${SC_TEST_PHP:-/home/$USER/miniconda3/envs/phplint/bin/php}"
PY="${PY:-/mnt/sda/Yan/Anaconda3/envs/SAMap/bin/python}"

rm -rf "$OUT"
mkdir -p "$OUT/js" "$OUT/images"

# 1. the module's own assets, as they will be deployed
cp "$ROOT/web/php/sc_pages.css" "$OUT/"
cp -r "$ROOT/web/viewer" "$OUT/viewer"
ln -sfn "$ROOT/web/singlecell_data" "$OUT/singlecell_data"

# the companion that prints computed font sizes and box widths, because a
# screenshot shows that the type changed but not by how much
cp "$(dirname "$0")/preview_measure.html" "$OUT/_measure.html"

# 2. the site's chrome, read-only from the host (the pages load it themselves)
ssh -i "$KEY" -o BatchMode=yes "$HOST" 'cat /var/www/html/CnidoSite/templatemo_style.css' \
  > "$OUT/templatemo_style.css"
ssh -i "$KEY" -o BatchMode=yes "$HOST" 'cat /var/www/html/CnidoSite/js/func.js' \
  > "$OUT/js/func.js" || true

# 3. the published-figure fixtures, both as files (for is_file) and under the
#    URL the pages use (images/)
"$PY" - "$OUT/images" <<'PY'
import sys
sys.path.insert(0, "pipeline/tests/php")
from make_fixture_images import write
write(sys.argv[1])
PY

# 4. render
#    The query string goes in *without* a leading "?": render_one.php feeds it
#    to parse_str(), which would otherwise key the first parameter as
#    "?dataset" and leave $_GET empty -- every page silently rendering the
#    default dataset.  Strip it so the caller can write either form.
render() {  # name query outname
  local qs="${2#\?}"
  SC_TEST_SOCKET="${SC_TEST_SOCKET:-/tmp/mariadb/run/my.sock}" \
  SC_TEST_USER=jackie SC_TEST_PASS="<REDACTED>" \
  SC_IMAGES_DIR="$OUT/images" \
  SC_DATA_DIR="$ROOT/web/singlecell_data" \
  "$PHP" "$ROOT/pipeline/tests/php/render_one.php" "$1" "$qs" > "$OUT/$3.html"
  echo "  $3.html  $(wc -c < "$OUT/$3.html") bytes"
}

# The three atlas branches are reached by dataset id, and the fixture images
# decide the rest:  OPATA_whole_adult carries published_figure=OPATA and
# OPATA_UMAP_1.png exists, so it renders our re-analysis *and* the source
# study's panels on one page;  pub:NVECT:WholeOrganism has no
# singlecell_atlas row claiming that figure, so it is the static page;
# pub:OPATA:Whole adults is a published id whose dataset was re-analysed, so it
# must land on the re-analysis rather than the PNG.
cd "$ROOT"
echo "rendering:"
render cell_atlas.php "?dataset=OPATA_whole_adult"        atlas-reanalysis
render cell_atlas.php "?dataset=pub:NVECT:WholeOrganism"  atlas-static
render cell_atlas.php "?dataset=pub:OPATA:Whole%20adults" atlas-published
render cell_marker.php "?dataset=AMILL_whole_adult"       marker
render gene_exp.php "?species=Acropora%20millepora"       gene-exp
render sn_data.php ""                                     sn-data

if [ -n "${SC_PREVIEW_NOSERVE:-}" ]; then
  echo
  echo "rendered into $OUT"
  echo "serve it with: $PY $(dirname "$0")/preview_serve.py $OUT $PORT"
  exit 0
fi

echo
echo "serving $OUT at http://127.0.0.1:$PORT/  (ctrl-c to stop)"
echo "screenshot with:"
echo "  firefox --headless --no-remote --profile ~/ffprof/p \\"
echo "    --window-size=1700,2600 --screenshot=~/shots/x.png \\"
echo "    http://127.0.0.1:$PORT/atlas-reanalysis.html"
# Not `http.server`: the atlas canvas is painted asynchronously, so a plain
# static server would let `load` fire -- and the screenshot be taken -- before
# the viewer has drawn anything.  preview_serve.py holds `load` open.
exec "$PY" "$(dirname "$0")/preview_serve.py" "$OUT" "$PORT"
