"""Compare local QPC V2 candidate glyphs with public QUL page previews.

Usage: python3 scripts/compare_mushaf_live_samples.py /path/to/bundle [pages...]
This checks sampled location/glyph sequences, not textual tashih or usage rights.
The preview's data-word-id is a CMS identifier, not the export's stable word ID.
"""

import json
import sys
import urllib.request
from html.parser import HTMLParser
from pathlib import Path


class PreviewWords(HTMLParser):
    def __init__(self):
        super().__init__()
        self.words = []
        self.current = None
        self.in_anchor = False

    def handle_starttag(self, tag, attrs):
        values = dict(attrs)
        if tag == "span" and "char" in values.get("class", "").split() and "data-word-id" in values:
            self.current = [values.get("data-location"), ""]
            self.in_anchor = False
        elif tag == "a" and self.current is not None:
            self.in_anchor = True

    def handle_data(self, data):
        if self.in_anchor and self.current is not None:
            self.current[1] += data

    def handle_endtag(self, tag):
        if tag == "a":
            self.in_anchor = False
        elif tag == "span" and self.current is not None:
            glyph = self.current[1].strip()
            if glyph:
                self.words.append((self.current[0], glyph))
            self.current = None


def compare(root: Path, page: int):
    if not 1 <= page <= 604:
        raise ValueError(f"Halaman di luar rentang: {page}")
    path = root / "data" / "pages" / f"page-{page:03}.json"
    data = json.loads(path.read_text(encoding="utf-8"))
    if data.get("page") != page:
        raise ValueError(f"Nomor halaman kandidat salah: {page}")
    expected = [(word["location"], word["qpcV2"]) for line in data["lines"] for word in line["words"]]
    request = urllib.request.Request(
        f"https://qul.tarteel.ai/resources/mushaf-layout/10?page={page}",
        headers={"User-Agent": "Mozilla/5.0 (F2 source review)"},
    )
    with urllib.request.urlopen(request, timeout=30) as response:
        parser = PreviewWords()
        parser.feed(response.read().decode("utf-8"))
    if parser.words != expected:
        first = next(
            ((index, live, local) for index, (live, local) in enumerate(zip(parser.words, expected), 1) if live != local),
            None,
        )
        raise ValueError(f"Halaman {page} berbeda: live={len(parser.words)}, lokal={len(expected)}, pertama={first}")
    return {"page": page, "words": len(expected), "matched": True}


if __name__ == "__main__":
    if len(sys.argv) < 2:
        raise SystemExit("Usage: python3 scripts/compare_mushaf_live_samples.py /path/to/bundle [pages...]")
    try:
        pages = [int(value) for value in sys.argv[2:]] or [1, 2, 302, 303, 604]
        print(json.dumps([compare(Path(sys.argv[1]), page) for page in pages], indent=2))
    except (ValueError, OSError, KeyError, UnicodeError) as error:
        raise SystemExit(f"Perbandingan gagal: {error}") from error
