import { Head, Link } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { fixturePages } from '../Mushaf/fixture';
import { anchorKey, parseAnchor, type ReferenceAnchor, type ReferenceWord } from '../Mushaf/reference';

type ViewMode = 'page' | 'ayah';
const pages = fixturePages;

function readInitialState() {
    const query = new URLSearchParams(window.location.search);
    const requested = Number(query.get('page'));
    const page = pages.some((item) => item.number === requested) ? requested : 1;
    const view: ViewMode = query.get('view') === 'ayah' ? 'ayah' : 'page';
    const zoom = Math.min(1.6, Math.max(0.8, Number(query.get('zoom')) || 1));
    return { page, view, zoom, anchor: parseAnchor(query.get('anchor'), pages) };
}

export default function MushafPrototype() {
    const [initial] = useState(readInitialState);
    const [pageNumber, setPageNumber] = useState(initial.page);
    const [view, setView] = useState<ViewMode>(initial.view);
    const [zoom, setZoom] = useState(initial.zoom);
    const [anchor, setAnchor] = useState<ReferenceAnchor | null>(initial.anchor);
    const page = pages.find((item) => item.number === pageNumber)!;
    const pageIndex = pages.findIndex((item) => item.number === pageNumber);
    const currentWordKey = anchor?.kind === 'word' ? anchor.word : null;

    useEffect(() => {
        const url = new URL(window.location.href);
        url.searchParams.set('page', String(pageNumber));
        url.searchParams.set('view', view);
        url.searchParams.set('zoom', zoom.toFixed(1));
        const key = anchorKey(anchor);
        if (key) url.searchParams.set('anchor', key);
        else url.searchParams.delete('anchor');
        window.history.replaceState(window.history.state, '', url);
    }, [pageNumber, view, zoom, anchor]);

    const ayahs = useMemo(() => {
        const grouped = new Map<string, ReferenceWord[]>();
        for (const line of page.lines) {
            for (const word of line.words) grouped.set(word.ayah, [...(grouped.get(word.ayah) ?? []), word]);
        }
        return [...grouped.entries()];
    }, [page]);

    function selectWord(word: ReferenceWord) {
        if (word.tokenKind !== 'reading_word') return;
        setAnchor({ kind: 'word', ayah: word.ayah, word: word.key });
    }

    function wordButton(word: ReferenceWord) {
        return <button key={word.key} type="button" className={`mushaf-word ${currentWordKey === word.key ? 'selected' : ''}`}
            aria-label={`Pilih kata contoh ${word.key}`} aria-pressed={currentWordKey === word.key}
            onClick={() => selectWord(word)}>{word.label}</button>;
    }

    return (
        <div className="mushaf-shell">
            <Head title="Prototipe mushaf" />
            <a className="skip-link" href="#mushaf-content">Lewati navigasi</a>
            <header className="mushaf-header"><Link href="/dashboard">← Ringkasan</Link><span>Penilaian Tahfidz · Laboratorium mushaf</span></header>
            <main id="mushaf-content" className="mushaf-main">
                <p className="eyebrow">F2 · Prototipe teknis</p>
                <h1>Jangkar mushaf yang tetap</h1>
                <p className="mushaf-warning" role="note"><strong>Data uji sintetis.</strong> Nomor halaman, surah, juz, ayat, dan label kata di bawah hanya menguji navigasi dan jangkar. Ini bukan teks Al-Qur’an atau layout Mushaf Madinah yang tervalidasi. Belum dapat dipakai untuk penilaian.</p>
                <p className="muted">Target edisi: Madinah, Hafs ‘an ‘Asim, 604 halaman. Paket QPC V2 cetakan 1421 H sedang diperiksa.</p>

                <section className="mushaf-controls" aria-label="Navigasi contoh mushaf">
                    <label>Halaman contoh
                        <select value={pageNumber} onChange={(event) => setPageNumber(Number(event.target.value))}>
                            {pages.map((item) => <option key={item.number} value={item.number}>Halaman {item.number}</option>)}
                        </select>
                    </label>
                    <label>Juz contoh
                        <select value={page.juz} onChange={(event) => {
                            const target = pages.find((item) => item.juz === Number(event.target.value));
                            if (target) setPageNumber(target.number);
                        }}>
                            {[...new Set(pages.map((item) => item.juz))].map((juz) => <option key={juz} value={juz}>Juz {juz}</option>)}
                        </select>
                    </label>
                    <label>Surah contoh
                        <select value={page.lines.find((line) => line.surah)?.surah ?? Number(page.lines.flatMap((line) => line.words)[0]?.ayah.split(':')[0])}
                            onChange={(event) => {
                                const target = pages.find((item) => item.lines.some((line) => line.surah === Number(event.target.value)));
                                if (target) setPageNumber(target.number);
                            }}>
                            {[1, 2, 18, 114].map((surah) => <option key={surah} value={surah}>Surah {surah}</option>)}
                        </select>
                    </label>
                    <fieldset className="mushaf-mode"><legend>Tampilan</legend>
                        <button type="button" aria-pressed={view === 'page'} onClick={() => setView('page')}>Halaman</button>
                        <button type="button" aria-pressed={view === 'ayah'} onClick={() => setView('ayah')}>Ayat adaptif</button>
                    </fieldset>
                    <div className="mushaf-zoom" aria-label="Perbesaran"><button type="button" aria-label="Perkecil" disabled={zoom <= 0.8} onClick={() => setZoom((current) => Math.max(0.8, Math.round((current - 0.2) * 10) / 10))}>−</button><span>{Math.round(zoom * 100)}%</span><button type="button" aria-label="Perbesar" disabled={zoom >= 1.6} onClick={() => setZoom((current) => Math.min(1.6, Math.round((current + 0.2) * 10) / 10))}>+</button></div>
                </section>

                <div className="mushaf-layout">
                    <section className="mushaf-page" aria-labelledby="page-heading">
                        <div className="mushaf-page-top"><button type="button" onClick={() => setPageNumber(pages[pageIndex - 1].number)} disabled={pageIndex === 0}>← Sebelumnya</button><h2 id="page-heading">Halaman contoh {page.number}</h2><button type="button" onClick={() => setPageNumber(pages[pageIndex + 1].number)} disabled={pageIndex === pages.length - 1}>Berikutnya →</button></div>
                        {view === 'page' ? <div className="mushaf-lines" dir="rtl" style={{ fontSize: `${zoom}rem` }}>
                            {page.lines.map((line) => <div key={line.number} className={`mushaf-line ${line.type}`} data-line={line.number}>
                                {line.type === 'surah_name' ? <span>Kepala surah {line.surah} (metadata)</span> : line.type === 'basmallah' ? <span>Basmalah (metadata)</span> : line.words.map(wordButton)}
                            </div>)}
                        </div> : <div className="mushaf-ayahs" dir="rtl" style={{ fontSize: `${zoom}rem` }}>
                            {ayahs.map(([ayah, words]) => <div className="mushaf-ayah" key={ayah}>
                                <button type="button" className="ayah-marker" aria-label={`Pilih ayat contoh ${ayah}`} aria-pressed={anchor?.kind === 'ayah' && anchor.ayah === ayah} onClick={() => setAnchor({ kind: 'ayah', ayah: ayah as `${number}:${number}` })}>Ayat {ayah}</button>
                                <div>{words.map(wordButton)}</div>
                            </div>)}
                        </div>}
                    </section>
                    <aside className="mushaf-selection" aria-labelledby="selection-heading"><h2 id="selection-heading">Jangkar pilihan</h2><p aria-live="polite">{anchor ? anchor.kind === 'word' ? `Kata ${anchor.word} · ayat ${anchor.ayah}` : `Ayat ${anchor.ayah}` : 'Belum ada pilihan.'}</p><p>Jangkar disimpan di alamat halaman untuk uji refresh dan pergantian tampilan. Tidak ada anotasi atau nilai yang disimpan.</p><button type="button" onClick={() => setAnchor(null)} disabled={!anchor}>Hapus pilihan</button></aside>
                </div>
            </main>
        </div>
    );
}
