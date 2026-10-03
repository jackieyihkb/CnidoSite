#!/usr/bin/env python3
"""Serve the preview tree, holding each page's `load` event open long enough
for the viewer to paint.

Why this exists
---------------
`firefox --headless --screenshot` captures the page when the `load` event
fires, and there is no option to make it wait.  That is fine for the pages'
own markup, which is server-rendered, but the cell atlas canvas is painted
asynchronously: cellatlas.js `fetch`es the embedding, rasterises it into an
ImageData and `putImageData`s it.  `fetch` does not block `load`, so the
screenshot of the atlas page shows the DOM with an empty `#atlas` div -- the
one part of the page that most needs looking at.

The fix uses the one thing that *does* block `load`: a subresource.  Each
HTML response gets a hidden `<img>` appended, pointing at `/__slow.png`,
which this server answers after a few seconds.  By the time `load` fires the
viewer has had that long to draw, and the screenshot has something in it.
The delay is a property of this preview server only -- the deployed site is
unaffected, and the rendered .html files on disk stay clean for inspection.

Usage:  preview_serve.py <root> <port> [--delay SECONDS]
"""

import io
import struct
import sys
import time
import zlib
from functools import partial
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer


def _pixel_png() -> bytes:
    """A 1x1 transparent PNG, built rather than pasted, so it is valid."""

    def chunk(tag: bytes, data: bytes) -> bytes:
        return (
            struct.pack(">I", len(data))
            + tag
            + data
            + struct.pack(">I", zlib.crc32(tag + data) & 0xFFFFFFFF)
        )

    ihdr = struct.pack(">IIBBBBB", 1, 1, 8, 6, 0, 0, 0)
    idat = zlib.compress(b"\x00\x00\x00\x00\x00")
    return (
        b"\x89PNG\r\n\x1a\n"
        + chunk(b"IHDR", ihdr)
        + chunk(b"IDAT", idat)
        + chunk(b"IEND", b"")
    )


PIXEL = _pixel_png()

#: Appended before </body>.  `display:none` keeps it out of the layout; the
#: browser still waits for it, which is the whole point.
PROBE = (
    '<img src="/__slow.png" alt="" width="1" height="1" '
    'style="position:absolute;width:1px;height:1px;opacity:0">'
)


class Handler(SimpleHTTPRequestHandler):
    delay = 3.0

    def do_GET(self):  # noqa: N802  (the name is fixed by the base class)
        if self.path.startswith("/__slow.png"):
            time.sleep(self.delay)
            self.send_response(200)
            self.send_header("Content-Type", "image/png")
            self.send_header("Content-Length", str(len(PIXEL)))
            self.send_header("Cache-Control", "no-store")
            self.end_headers()
            self.wfile.write(PIXEL)
            return
        super().do_GET()

    def send_head(self):
        """Serve HTML with the probe appended."""
        path = self.translate_path(self.path)
        if path.endswith(".html"):
            try:
                with open(path, "rb") as fh:
                    body = fh.read()
            except OSError:
                return super().send_head()
            if b"</body>" in body:
                body = body.replace(b"</body>", PROBE.encode() + b"</body>", 1)
            else:
                body += PROBE.encode()
            self.send_response(200)
            self.send_header("Content-Type", "text/html; charset=utf-8")
            self.send_header("Content-Length", str(len(body)))
            self.send_header("Cache-Control", "no-store")
            self.end_headers()
            return io.BytesIO(body)
        return super().send_head()

    def log_message(self, fmt, *args):
        # The probe fires on every page; drop it so the log stays readable.
        if "/__slow.png" not in (args[0] if args else ""):
            super().log_message(fmt, *args)


def main() -> int:
    args = [a for a in sys.argv[1:] if not a.startswith("--")]
    delay = 3.0
    for a in sys.argv[1:]:
        if a.startswith("--delay"):
            delay = float(a.split("=", 1)[1] if "=" in a else 3.0)
    if len(args) < 2:
        print(__doc__)
        return 2
    root, port = args[0], int(args[1])
    Handler.delay = delay
    srv = ThreadingHTTPServer(
        ("127.0.0.1", port), partial(Handler, directory=root)
    )
    print(f"serving {root} at http://127.0.0.1:{port}/ (probe delay {delay}s)")
    srv.serve_forever()
    return 0


if __name__ == "__main__":
    sys.exit(main())
