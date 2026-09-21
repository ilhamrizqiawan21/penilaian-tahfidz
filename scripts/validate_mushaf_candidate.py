"""Audit a local QUL QPC V2 candidate bundle without importing or publishing it.

Usage: python3 scripts/validate_mushaf_candidate.py /path/to/bundle
Expected inputs: qul-data/qpc-v2-15-lines.db.zip,
qul-data/qpc-hafs-word-by-word.db.zip, data/pages/page-001.json ... page-604.json.
This verifies internal consistency, not textual tashih, glyph accuracy, or usage rights.
"""

import hashlib
import json
import re
import sqlite3
import sys
import tempfile
import zipfile
from pathlib import Path

PAGE_COUNT = 604
LINE_COUNT = 15
AYAH_COUNT = 6236
SURAH_COUNT = 114
WORD_LOCATION = re.compile(r"^(\d+):(\d+):(\d+)$")


def require(condition: bool, message: str) -> None:
    if not condition:
        raise ValueError(message)


def sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def extract_exact(archive: Path, member: str, destination: Path) -> None:
    require(archive.is_file(), f"Arsip tidak ada: {archive}")
    with zipfile.ZipFile(archive) as source:
        require(source.namelist() == [member], f"Isi arsip tidak sesuai: {archive}")
        require(source.getinfo(member).file_size <= 20 * 1024 * 1024, f"Arsip terlalu besar: {archive}")
        with source.open(member) as input_stream, destination.open("wb") as output_stream:
            for chunk in iter(lambda: input_stream.read(1024 * 1024), b""):
                output_stream.write(chunk)


def audit_words(db: Path) -> tuple[dict[int, tuple[str, str, bool]], int]:
    words: dict[int, tuple[str, str, bool]] = {}
    seen_locations: set[str] = set()
    last_surah = last_ayah = last_position = 0
    previous_marker = True
    markers = 0
    with sqlite3.connect(db) as connection:
        for word_id, location, surah, ayah, position, text in connection.execute(
            "select id, location, surah, ayah, word, text from words order by id"
        ):
            require(word_id == len(words) + 1, f"ID kata terputus: {word_id}")
            match = WORD_LOCATION.fullmatch(location or "")
            require(match is not None, f"Lokasi kata tidak valid: {location}")
            require(tuple(map(int, match.groups())) == (surah, ayah, position), f"Lokasi kata tidak cocok: {location}")
            require(location not in seen_locations, f"Lokasi kata duplikat: {location}")
            require(1 <= surah <= SURAH_COUNT and ayah >= 1 and position >= 1, f"Nomor kata tidak valid: {location}")
            require(bool(text), f"Teks kosong pada {location}")
            if surah != last_surah:
                require(previous_marker, f"Ayat sebelumnya tidak berakhir penanda: {location}")
                require(surah == last_surah + 1 and ayah == 1 and position == 1, f"Urutan surah terputus: {location}")
                last_surah, last_ayah, last_position = surah, 1, 0
            elif ayah != last_ayah:
                require(previous_marker, f"Ayat sebelumnya tidak berakhir penanda: {location}")
                require(ayah == last_ayah + 1 and position == 1, f"Urutan ayat terputus: {location}")
                last_ayah, last_position = ayah, 0
            require(position == last_position + 1, f"Urutan kata terputus: {location}")
            last_position = position
            marker = text.isdecimal()
            require(not (previous_marker and position > 1), f"Kata sesudah penanda ayat: {location}")
            if marker:
                require(int(text) == ayah, f"Nomor penutup ayat tidak cocok: {location}")
                markers += 1
            previous_marker = marker
            words[word_id] = (location, text, marker)
            seen_locations.add(location)
    require(last_surah == SURAH_COUNT, f"Surah terakhir {last_surah}, diharapkan {SURAH_COUNT}")
    require(previous_marker, "Ayat terakhir tidak memiliki penanda")
    require(markers == AYAH_COUNT, f"Penanda ayat {markers}, diharapkan {AYAH_COUNT}")
    return words, markers


def audit_layout(db: Path, words: dict[int, tuple[str, str, bool]]) -> dict[tuple[int, int], tuple[str, int | None, int | None, int | None]]:
    layout: dict[tuple[int, int], tuple[str, int | None, int | None, int | None]] = {}
    next_word_id = 1
    headers: list[int] = []
    basmallahs = 0
    pages_seen: set[int] = set()
    with sqlite3.connect(db) as connection:
        info = connection.execute("select name, number_of_pages, lines_per_page, font_name from info").fetchone()
        require(info is not None and info[1:] == (PAGE_COUNT, LINE_COUNT, "v2"), f"Metadata layout tidak cocok: {info}")
        require("1421H" in info[0], f"Cetakan layout tidak dikenali: {info[0]}")
        for page, line, kind, centered, first, last, surah in connection.execute(
            "select page_number, line_number, line_type, is_centered, first_word_id, last_word_id, surah_number from pages order by page_number, line_number"
        ):
            require(1 <= page <= PAGE_COUNT and 1 <= line <= LINE_COUNT, f"Halaman/baris tidak valid: {page}:{line}")
            require((page, line) not in layout, f"Halaman/baris duplikat: {page}:{line}")
            pages_seen.add(page)
            require(kind in {"ayah", "surah_name", "basmallah"} and centered in (0, 1), f"Jenis baris tidak valid: {page}:{line}")
            if kind == "ayah":
                require(isinstance(first, int) and isinstance(last, int) and first == next_word_id and last >= first, f"Rentang kata terputus: {page}:{line}")
                require(last <= len(words), f"Rentang kata melewati akhir: {page}:{line}")
                next_word_id = last + 1
            else:
                require(first in ("", None) and last in ("", None), f"Baris dekorasi berisi kata: {page}:{line}")
                if kind == "surah_name":
                    require(isinstance(surah, int), f"Nomor surah header invalid: {page}:{line}")
                    headers.append(surah)
                else:
                    basmallahs += 1
            layout[(page, line)] = (kind, first if kind == "ayah" else None, last if kind == "ayah" else None, surah if kind == "surah_name" else None)
    require(next_word_id == len(words) + 1, f"Kata akhir layout {next_word_id - 1}, diharapkan {len(words)}")
    require(pages_seen == set(range(1, PAGE_COUNT + 1)), "Ada halaman tanpa layout")
    require(headers == list(range(1, SURAH_COUNT + 1)), "Urutan kepala surah tidak lengkap")
    require(basmallahs == 112, f"Jumlah baris basmalah {basmallahs}, diharapkan 112")
    return layout


def audit_pages(root: Path, words: dict[int, tuple[str, str, bool]], layout: dict) -> str:
    page_dir = root / "data" / "pages"
    files = sorted(page_dir.glob("page-*.json"))
    require([path.name for path in files] == [f"page-{page:03}.json" for page in range(1, PAGE_COUNT + 1)], "Berkas halaman tidak lengkap atau berlebih")
    digest = hashlib.sha256()
    blank_lines = 0
    for page, path in enumerate(files, 1):
        content = path.read_bytes()
        digest.update(content)
        data = json.loads(content)
        require(data.get("page") == page, f"Nomor halaman JSON tidak cocok: {page}")
        lines = data.get("lines")
        require(isinstance(lines, list) and len(lines) == LINE_COUNT, f"Jumlah baris JSON tidak cocok: {page}")
        for line_number, item in enumerate(lines, 1):
            require(item.get("line") == line_number, f"Nomor baris JSON tidak cocok: {page}:{line_number}")
            source = layout.get((page, line_number))
            expected_type = "text" if source and source[0] == "ayah" else source[0] if source else "blank"
            if expected_type == "blank":
                blank_lines += 1
            require(item.get("type") == expected_type, f"Jenis baris JSON tidak cocok: {page}:{line_number}")
            tokens = item.get("words")
            require(isinstance(tokens, list), f"Token JSON tidak valid: {page}:{line_number}")
            if source and source[0] == "ayah":
                expected_ids = list(range(source[1], source[2] + 1))
                require([token.get("word_id") for token in tokens] == expected_ids, f"ID kata JSON tidak cocok: {page}:{line_number}")
                for token in tokens:
                    location, _, marker = words[token["word_id"]]
                    require(token.get("location") == location, f"Lokasi JSON tidak cocok: {page}:{line_number}")
                    require(token.get("kind") == ("marker" if marker else "word"), f"Jenis token JSON tidak cocok: {page}:{line_number}")
                    glyph = token.get("qpcV2")
                    require(isinstance(glyph, str) and glyph and all(0xFC00 <= ord(char) <= 0xFCFF or char == " " for char in glyph), f"Glyph QPC tidak valid: {page}:{line_number}")
            else:
                require(not tokens, f"Baris dekorasi JSON berisi token: {page}:{line_number}")
                if source and source[0] == "surah_name":
                    require(item.get("decor", {}).get("surah") == source[3], f"Kepala surah JSON tidak cocok: {page}:{line_number}")
    require(blank_lines == 14, f"Baris kosong {blank_lines}, diharapkan 14")
    return digest.hexdigest()


def main(root: Path) -> dict:
    layout_zip = root / "qul-data" / "qpc-v2-15-lines.db.zip"
    words_zip = root / "qul-data" / "qpc-hafs-word-by-word.db.zip"
    with tempfile.TemporaryDirectory() as temp:
        layout_db = Path(temp) / "layout.db"
        words_db = Path(temp) / "words.db"
        extract_exact(layout_zip, "qpc-v2-15-lines.db", layout_db)
        extract_exact(words_zip, "qpc-hafs-word-by-word.db", words_db)
        words, ayahs = audit_words(words_db)
        layout = audit_layout(layout_db, words)
        pages_digest = audit_pages(root, words, layout)
        return {
            "status": "candidate_structure_valid",
            "pages": PAGE_COUNT,
            "lines": PAGE_COUNT * LINE_COUNT,
            "surahs": SURAH_COUNT,
            "ayahs": ayahs,
            "tokens_including_markers": len(words),
            "reading_words": len(words) - ayahs,
            "layout_archive_sha256": sha256(layout_zip),
            "words_archive_sha256": sha256(words_zip),
            "page_json_aggregate_sha256": pages_digest,
            "warning": "Struktur internal saja; teks/glyph, kecocokan cetakan, dan hak pakai belum ditashih.",
        }


if __name__ == "__main__":
    if len(sys.argv) != 2:
        raise SystemExit("Usage: python3 scripts/validate_mushaf_candidate.py /path/to/bundle")
    try:
        print(json.dumps(main(Path(sys.argv[1])), ensure_ascii=False, indent=2))
    except (ValueError, OSError, sqlite3.Error, zipfile.BadZipFile, json.JSONDecodeError) as error:
        raise SystemExit(f"Validasi gagal: {error}") from error
