import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState, type CSSProperties, type FormEvent } from 'react';

type Surah = { number: number; name_local: string; ayah_count: number };
type Word = { id: string; position: number; text_or_glyph: string };
type Ayah = { id: string; number: number; global_order: number; text_uthmani: string | null; words: Word[] };
type Reference = {
    name: string;
    source_name: string;
    source_url: string | null;
    version: string;
    riwayah: string;
    license_metadata: string | null;
    is_demo: boolean;
};

type Props = {
    reference: Reference | null;
    surahs: Surah[];
    selected_surah: Surah | null;
    ayahs: Ayah[];
};

const ZOOM_KEY = 'quran-zoom';

export default function QuranReader({ reference, surahs, selected_surah: selectedSurah, ayahs }: Props) {
    const [zoom, setZoom] = useState(() => {
        try { const saved = Number(localStorage.getItem(ZOOM_KEY)); return saved >= .8 && saved <= 1.5 ? saved : 1; } catch { return 1; }
    });
    const [jumpAyah, setJumpAyah] = useState('');

    useEffect(() => {
        try { localStorage.setItem(ZOOM_KEY, String(zoom)); } catch { /* per-viewer convenience only */ }
    }, [zoom]);

    const surahIndex = surahs.findIndex((surah) => surah.number === selectedSurah?.number);
    const prevSurah = surahIndex > 0 ? surahs[surahIndex - 1] : null;
    const nextSurah = surahIndex >= 0 && surahIndex < surahs.length - 1 ? surahs[surahIndex + 1] : null;

    function jumpToAyah(event: FormEvent) {
        event.preventDefault();
        const target = Number(jumpAyah);
        if (!target) return;
        document.getElementById(`ayah-${target}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    return <main id="main" tabIndex={-1} className="quran-reader">
        <Head title="Al-Qur’an" />
        <div className="quran-reader-heading">
            <div>
                <p className="eyebrow">Referensi bacaan</p>
                <h1>Al-Qur’an</h1>
                <p className="muted">Baca dan periksa ayat sebelum memulai sesi penilaian.</p>
            </div>
            <Link href="/assessments" className="primary action-link">Mulai penilaian <span aria-hidden="true">→</span></Link>
        </div>

        {!reference && <section className="quran-empty" role="status">
            <span className="quran-empty-mark" aria-hidden="true">۝</span>
            <h2>Referensi Al-Qur’an belum aktif</h2>
            <p>Instal sumber Uthmani yang terverifikasi terlebih dahulu. Data sintetis tidak akan ditampilkan sebagai ayat.</p>
        </section>}

        {reference && <>
            {reference.is_demo && <p className="mushaf-warning" role="note"><strong>Mode demo.</strong> Isi yang tampil adalah data sintetis untuk menguji alur aplikasi, bukan teks Al-Qur’an. Pasang sumber produksi sebelum digunakan untuk penilaian nyata.</p>}
            <section className="quran-toolbar" aria-label="Navigasi Al-Qur’an">
                <label>Surah
                    <select value={selectedSurah?.number ?? 1} onChange={(event) => router.get('/quran', { surah: Number(event.target.value) }, { preserveScroll: false })}>
                        {surahs.map((surah) => <option key={surah.number} value={surah.number}>{`${surah.number}. ⁧${surah.name_local}⁩ · ${surah.ayah_count} ayat`}</option>)}
                    </select>
                </label>
                <form className="quran-jump" onSubmit={jumpToAyah}>
                    <label htmlFor="jump-ayah">Lompat ke ayat</label>
                    <input id="jump-ayah" type="number" min="1" max={selectedSurah?.ayah_count} value={jumpAyah} onChange={(event) => setJumpAyah(event.target.value)} placeholder="No." />
                    <button type="submit">Ke ayat</button>
                </form>
                <div className="quran-zoom" aria-label="Ukuran teks">
                    <span>Ukuran teks</span>
                    <button type="button" aria-label="Perkecil teks" disabled={zoom <= .8} onClick={() => setZoom((value) => Math.max(.8, value - .1))}>−</button>
                    <output>{Math.round(zoom * 100)}%</output>
                    <button type="button" aria-label="Perbesar teks" disabled={zoom >= 1.5} onClick={() => setZoom((value) => Math.min(1.5, value + .1))}>+</button>
                </div>
            </section>

            <section className="quran-page" aria-labelledby="surah-heading">
                <header className="quran-surah-heading">
                    <div className="quran-surah-nav">
                        <button type="button" className="quran-surah-nav-btn" disabled={!prevSurah} aria-label="Surah sebelumnya" onClick={() => prevSurah && router.get('/quran', { surah: prevSurah.number })}>‹</button>
                        <p>Surah {selectedSurah?.number}</p>
                    </div>
                    <h2 id="surah-heading">{selectedSurah?.name_local}</h2>
                    <div className="quran-surah-nav quran-surah-nav-end">
                        <span>{selectedSurah?.ayah_count} ayat</span>
                        <button type="button" className="quran-surah-nav-btn" disabled={!nextSurah} aria-label="Surah berikutnya" onClick={() => nextSurah && router.get('/quran', { surah: nextSurah.number })}>›</button>
                    </div>
                </header>
                {selectedSurah?.number !== 1 && selectedSurah?.number !== 9 && <p className="quran-basmalah" lang="ar" dir="rtl">بِسْمِ ٱللَّهِ ٱلرَّحْمَـٰنِ ٱلرَّحِيمِ</p>}
                <div className="quran-ayah-list" lang="ar" dir="rtl" style={{ '--quran-zoom': zoom } as CSSProperties}>
                    {ayahs.map((ayah) => <article className="quran-ayah" id={`ayah-${ayah.number}`} key={ayah.id}>
                        <p>{ayah.words.length > 0 ? ayah.words.map((word) => <span className="quran-word" key={word.id}>{word.text_or_glyph}</span>) : ayah.text_uthmani}</p>
                        <span className="quran-ayah-number" aria-label={`Ayat ${ayah.number}`}>{ayah.number}</span>
                    </article>)}
                </div>
            </section>

            <footer className="quran-attribution">
                <div><strong>{reference.name}</strong><span>{reference.riwayah} · versi {reference.version}</span></div>
                {reference.source_url ? <a href={reference.source_url} target="_blank" rel="noreferrer">Sumber: {reference.source_name} ↗</a> : <span>Sumber: {reference.source_name}</span>}
            </footer>
        </>}
    </main>;
}
