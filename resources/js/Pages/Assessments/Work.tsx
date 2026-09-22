import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { AssessmentHttpError, assessmentRequest } from '../../Assessment/http';

type Range = { startSurah: number; startAyah: number; endSurah: number; endAyah: number };
type Rule = { id: string; name: string; deduction_points: string; criterion?: string };
type Criterion = { id: string; name: string; method: 'direct' | 'deduction'; min_score: string; max_score: string; rules: Rule[] };
type AyahWord = { id: string; ayah_id: string; position: number; text_or_glyph: string };
type Ayah = { id: string; number: number; global_order: number; surah_number: number; surah_name: string; text_uthmani: string | null; words: AyahWord[] };
type Event = { id: string; kind: 'note' | 'penalty'; ayah_id: string; edition_word_id?: string | null; rule_id?: string | null; note?: string | null; active: boolean };
type Detail = {
    id: string;
    record_id: string;
    student: { name: string; code: string };
    activity_type_id: string;
    activity_name: string;
    status: string;
    lock_version: number;
    notes: string | null;
    last_ayah_id: string | null;
    rubric: { id: string; name: string; criteria: Criterion[] };
    planned_ranges: Range[];
    actual_ranges: Range[];
    scores: { criterion_id: string; direct_input: string | null; override_raw: string | null; override_reason: string | null }[];
    events: { client_event_id: string; kind: 'note' | 'penalty'; ayah_id: string; edition_word_id: string | null; mistake_rule_id: string | null; note: string | null; retracted_at: string | null }[];
    ayahs: Ayah[];
    has_more_ayahs: boolean;
    final_score: string | null;
    passed: boolean | number | null;
};
type Preview = {
    complete: boolean;
    display_score: string | null;
    passed: boolean | null;
    grade: string | null;
    criteria: { id: string; name: string; computed_raw: string | null; normalized: string | null; deductions: { event_id: string; name: string; points: string }[] }[];
};
type SaveResponse = { lock_version: number; preview: Preview };
type FinalResponse = Preview & { lock_version: number; unrounded_score: string };

const blankRange = (): Range => ({ startSurah: 1, startAyah: 1, endSurah: 1, endAyah: 1 });

export default function AssessmentWork({ assessmentId }: { assessmentId: string }) {
    const [detail, setDetail] = useState<Detail | null>(null);
    const [ayahs, setAyahs] = useState<Ayah[]>([]);
    const [hasMore, setHasMore] = useState(false);
    const [direct, setDirect] = useState<Record<string, string>>({});
    const [overrides, setOverrides] = useState<Record<string, { raw: string; reason: string }>>({});
    const [ranges, setRanges] = useState<Range[]>([]);
    const [events, setEvents] = useState<Event[]>([]);
    const [notes, setNotes] = useState('');
    const [lastAyahId, setLastAyahId] = useState<string | null>(null);
    const [selectedRule, setSelectedRule] = useState('');
    const [noteDrafts, setNoteDrafts] = useState<Record<string, string>>({});
    const [activeNoteAyah, setActiveNoteAyah] = useState<string | null>(null);
    const [lockVersion, setLockVersion] = useState(0);
    const [state, setState] = useState<'loading' | 'unsaved' | 'saving' | 'saved' | 'uncertain' | 'conflict' | 'final'>('loading');
    const [error, setError] = useState('');
    const [preview, setPreview] = useState<Preview | null>(null);
    const [pendingSave, setPendingSave] = useState<unknown>(null);
    const [pendingFinal, setPendingFinal] = useState<unknown>(null);

    // Mushaf display settings
    const [viewMode, setViewMode] = useState<'sheet' | 'detail'>('sheet');
    const [zoom, setZoom] = useState(1.2);

    useEffect(() => {
        void assessmentRequest<Detail>(`/assessments/${assessmentId}`, 'GET').then((data) => {
            setDetail(data);
            setAyahs(data.ayahs);
            setHasMore(data.has_more_ayahs);
            setRanges(data.actual_ranges);
            setLockVersion(data.lock_version);
            setDirect(Object.fromEntries(data.scores.filter((item) => item.direct_input !== null).map((item) => [item.criterion_id, item.direct_input ?? ''])));
            setOverrides(Object.fromEntries(data.scores.filter((item) => item.override_raw !== null).map((item) => [item.criterion_id, { raw: item.override_raw ?? '', reason: item.override_reason ?? '' }])));
            setEvents(data.events.map((item) => ({ id: item.client_event_id, kind: item.kind, ayah_id: item.ayah_id, edition_word_id: item.edition_word_id, rule_id: item.mistake_rule_id, note: item.note, active: item.retracted_at === null })));
            setNotes(data.notes ?? '');
            setLastAyahId(data.last_ayah_id);
            setState(data.status === 'draft' ? 'saved' : 'final');
        }).catch((failure) => {
            setError(failure instanceof Error ? failure.message : 'Sesi belum dapat dimuat.');
            setState('conflict');
        });
    }, [assessmentId]);

    const editable = detail?.status === 'draft' && state !== 'uncertain' && state !== 'saving' && state !== 'conflict' && state !== 'final';

    function dirty() {
        setState('unsaved');
        setPreview(null);
    }

    function changeRange(index: number, field: keyof Range, value: number) {
        setRanges(ranges.map((item, i) => (i === index ? { ...item, [field]: value } : item)));
        dirty();
    }

    const rules: Rule[] = useMemo(() => {
        return detail?.rubric.criteria.flatMap((criterion) => criterion.rules.map((rule) => ({ ...rule, criterion: criterion.name }))) ?? [];
    }, [detail]);

    // Group ayahs by surah
    const surahGroups = useMemo(() => {
        const groups: { surahNumber: number; surahName: string; ayahs: Ayah[] }[] = [];
        for (const ayah of ayahs) {
            const lastGroup = groups[groups.length - 1];
            if (lastGroup && lastGroup.surahNumber === ayah.surah_number) {
                lastGroup.ayahs.push(ayah);
            } else {
                groups.push({
                    surahNumber: ayah.surah_number,
                    surahName: ayah.surah_name,
                    ayahs: [ayah],
                });
            }
        }
        return groups;
    }, [ayahs]);

    function addPenalty(ayah: Ayah, wordId?: string) {
        if (!selectedRule) {
            setError('Pilih aturan kesalahan pada bilah alat di atas terlebih dahulu.');
            return;
        }
        setEvents([...events, {
            id: crypto.randomUUID(),
            kind: 'penalty',
            ayah_id: ayah.id,
            edition_word_id: wordId ?? null,
            rule_id: selectedRule,
            active: true,
        }]);
        setLastAyahId(ayah.id);
        setError('');
        dirty();
    }

    function toggleWordPenalty(ayah: Ayah, wordId: string) {
        if (!editable) return;
        const existingActive = events.find((e) => e.active && e.edition_word_id === wordId && e.kind === 'penalty');
        if (existingActive) {
            // Undo this penalty
            setEvents(events.map((e) => (e.id === existingActive.id ? { ...e, active: false } : e)));
            dirty();
            return;
        }
        addPenalty(ayah, wordId);
    }

    function addNote(ayah: Ayah) {
        const noteText = noteDrafts[ayah.id]?.trim();
        if (!noteText) return;
        setEvents([...events, {
            id: crypto.randomUUID(),
            kind: 'note',
            ayah_id: ayah.id,
            note: noteText,
            active: true,
        }]);
        setNoteDrafts({ ...noteDrafts, [ayah.id]: '' });
        setActiveNoteAyah(null);
        setLastAyahId(ayah.id);
        dirty();
    }

    async function loadMore() {
        if (ayahs.length === 0) return;
        try {
            const page = await assessmentRequest<Detail>(`/assessments/${assessmentId}?after=${ayahs.at(-1)?.global_order}`, 'GET');
            setAyahs([...ayahs, ...page.ayahs]);
            setHasMore(page.has_more_ayahs);
        } catch (failure) {
            setError(failure instanceof Error ? failure.message : 'Ayat berikutnya belum termuat.');
        }
    }

    async function save(retry = false) {
        const payload = retry ? pendingSave : { lock_version: lockVersion, mutation_id: crypto.randomUUID(), direct, overrides, actual_ranges: ranges, events, last_ayah_id: lastAyahId, notes };
        if (!payload) return;
        setPendingSave(payload);
        setState('saving');
        setError('');
        try {
            const saved = await assessmentRequest<SaveResponse>(`/assessments/${assessmentId}/draft`, 'PUT', payload);
            setLockVersion(saved.lock_version);
            setPreview(saved.preview);
            setPendingSave(null);
            setState('saved');
        } catch (failure) {
            if (failure instanceof AssessmentHttpError) {
                setPendingSave(null);
                setState(failure.status === 409 ? 'conflict' : 'unsaved');
                setError(failure.message);
            } else {
                setState('uncertain');
                setError('Sambungan terputus. Status simpan belum diketahui; ulangi permintaan yang sama.');
            }
        }
    }

    async function finalize(retry = false) {
        const payload = retry ? pendingFinal : { lock_version: lockVersion, mutation_id: crypto.randomUUID() };
        if (!payload) return;
        setPendingFinal(payload);
        setState('saving');
        setError('');
        try {
            const final = await assessmentRequest<FinalResponse>(`/assessments/${assessmentId}/finalize`, 'POST', payload);
            setLockVersion(final.lock_version);
            setPreview(final);
            setPendingFinal(null);
            setState('final');
            setDetail(detail ? { ...detail, status: 'final', final_score: final.unrounded_score, passed: final.passed } : detail);
        } catch (failure) {
            if (failure instanceof AssessmentHttpError) {
                setPendingFinal(null);
                setState(failure.status === 409 ? 'conflict' : 'saved');
                setError(failure.message);
            } else {
                setState('uncertain');
                setError('Sambungan terputus saat finalisasi. Ulangi permintaan yang sama sebelum tindakan lain.');
            }
        }
    }

    async function cancel() {
        try {
            await assessmentRequest(`/assessments/${assessmentId}/cancel`, 'POST', {});
            router.visit('/assessments');
        } catch (failure) {
            setError(failure instanceof Error ? failure.message : 'Draf belum dibatalkan.');
        }
    }

    const activePenaltiesCount = events.filter((e) => e.active && e.kind === 'penalty').length;

    return (
        <div className="master-shell">
            <Head title="Sesi Penilaian Mushaf" />
            <a className="skip-link" href="#work-content">Lewati navigasi</a>
            <header className="mushaf-header">
                <Link href="/assessments">← Daftar sesi</Link>
                <span>Penilaian Tahfidz · Lembar Penilaian Mushaf</span>
            </header>

            <main id="work-content" className="master-main">
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: '12px' }}>
                    <div>
                        <p className="eyebrow">Sesi Penilaian Santri</p>
                        <h1>{detail ? `${detail.student.name} (${detail.student.code}) · ${detail.activity_name}` : 'Memuat sesi…'}</h1>
                    </div>
                    <div style={{ display: 'flex', gap: '10px', alignItems: 'center' }}>
                        <p className="save-status" data-state={state} role="status">
                            {state === 'saving' ? 'Menyimpan…' : state === 'saved' ? 'Tersimpan di server' : state === 'unsaved' ? 'Perubahan belum tersimpan' : state === 'uncertain' ? 'Belum diketahui apakah tersimpan' : state === 'conflict' ? 'Konflik atau gagal memuat' : state === 'final' ? 'Hasil Final' : 'Memuat…'}
                        </p>
                        {preview && preview.display_score && (
                            <span className="demo-badge" style={{ fontSize: '0.85rem', padding: '6px 14px' }}>
                                Skor: <strong>{preview.display_score}</strong> {preview.grade && `(${preview.grade})`} · {preview.passed ? 'Lulus' : 'Belum lulus'}
                            </span>
                        )}
                    </div>
                </div>

                {error && <p role="alert">{error}</p>}
                {state === 'uncertain' && (
                    <div className="master-actions" style={{ margin: '14px 0' }}>
                        <button type="button" onClick={() => pendingFinal ? void finalize(true) : void save(true)}>
                            Coba ulang permintaan yang sama
                        </button>
                    </div>
                )}
                {state === 'conflict' && (
                    <div className="master-actions" style={{ margin: '14px 0' }}>
                        <button type="button" onClick={() => window.location.reload()}>
                            Muat ulang data server
                        </button>
                    </div>
                )}

                {detail && (
                    <>
                        {/* Interactive Toolbar for Mushaf Grading */}
                        <div className="mushaf-toolbar">
                            <div className="rule-selector-bar">
                                <span style={{ fontSize: '0.82rem', fontWeight: 600, color: 'var(--muted)', marginRight: '4px' }}>
                                    Aturan Aktif:
                                </span>
                                {rules.map((rule) => {
                                    const isSelected = selectedRule === rule.id;
                                    return (
                                        <button
                                            key={rule.id}
                                            type="button"
                                            className="rule-chip"
                                            aria-pressed={isSelected}
                                            disabled={!editable}
                                            onClick={() => setSelectedRule(isSelected ? '' : rule.id)}
                                        >
                                            {rule.name} (−{rule.deduction_points})
                                        </button>
                                    );
                                })}
                                {selectedRule && (
                                    <button
                                        type="button"
                                        style={{ fontSize: '0.75rem', padding: '4px 8px', minHeight: 'auto', background: 'transparent', border: 'none', color: '#942c26', textDecoration: 'underline' }}
                                        onClick={() => setSelectedRule('')}
                                    >
                                        Batal pilih
                                    </button>
                                )}
                            </div>

                            <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                                <div className="mushaf-mode" aria-label="Mode tampilan mushaf">
                                    <button
                                        type="button"
                                        aria-pressed={viewMode === 'sheet'}
                                        onClick={() => setViewMode('sheet')}
                                        style={{ padding: '6px 12px', minHeight: '36px', fontSize: '0.8rem' }}
                                    >
                                        Lembar Mushaf
                                    </button>
                                    <button
                                        type="button"
                                        aria-pressed={viewMode === 'detail'}
                                        onClick={() => setViewMode('detail')}
                                        style={{ padding: '6px 12px', minHeight: '36px', fontSize: '0.8rem' }}
                                    >
                                        Detail Ayat
                                    </button>
                                </div>

                                <div className="mushaf-zoom" aria-label="Ukuran font mushaf">
                                    <button
                                        type="button"
                                        aria-label="Perkecil teks"
                                        disabled={zoom <= 0.9}
                                        onClick={() => setZoom((z) => Math.max(0.8, Math.round((z - 0.1) * 10) / 10))}
                                        style={{ minHeight: '36px', padding: '4px 10px' }}
                                    >
                                        −
                                    </button>
                                    <span style={{ fontSize: '0.8rem' }}>{Math.round(zoom * 100)}%</span>
                                    <button
                                        type="button"
                                        aria-label="Perbesar teks"
                                        disabled={zoom >= 1.6}
                                        onClick={() => setZoom((z) => Math.min(1.6, Math.round((z + 0.1) * 10) / 10))}
                                        style={{ minHeight: '36px', padding: '4px 10px' }}
                                    >
                                        +
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div className="assessment-workspace">
                            {/* Primary Mushaf Workspace */}
                            <div className="mushaf-sheet">
                                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', borderBottom: '1px solid #dce4d9', paddingBottom: '12px', marginBottom: '16px' }}>
                                    <div>
                                        <h2 style={{ fontSize: '1.2rem', margin: 0, color: 'var(--ink)' }}>Lembar Bacaan Mushaf</h2>
                                        <p style={{ margin: 0, fontSize: '0.8rem', color: 'var(--muted)' }}>
                                            {selectedRule ? (
                                                <span style={{ color: '#235545', fontWeight: 600 }}>
                                                    Mode Penandaan Aktif: Klik kata atau ayat yang salah untuk menerapkan potongan.
                                                </span>
                                            ) : (
                                                'Pilih aturan kesalahan di atas, lalu klik kata atau nomor ayat untuk menandai kesalahan.'
                                            )}
                                        </p>
                                    </div>
                                    <span className="demo-badge" style={{ fontSize: '0.75rem' }}>
                                        {activePenaltiesCount} kesalahan tercatat
                                    </span>
                                </div>

                                {viewMode === 'sheet' ? (
                                    /* Lembar Mushaf Continuous Flow */
                                    <div className="mushaf-continuous-flow" style={{ fontSize: `${zoom * 1.3}rem` }}>
                                        {surahGroups.map((group) => (
                                            <div key={group.surahNumber} style={{ margin: '20px 0' }}>
                                                <div className="mushaf-surah-banner">
                                                    <h3>سُورَةُ {group.surahName}</h3>
                                                    <div className="mushaf-surah-meta">
                                                        Surah ke-{group.surahNumber} · {group.ayahs.length} Ayat
                                                    </div>
                                                </div>

                                                {group.surahNumber !== 9 && (
                                                    <div className="mushaf-basmalah-text">
                                                        بِسْمِ ٱللَّهِ ٱلرَّحْمَـٰنِ ٱلرَّحِيمِ
                                                    </div>
                                                )}

                                                <div style={{ margin: '14px 0' }}>
                                                    {group.ayahs.map((ayah) => {
                                                        const ayahPenalty = events.find((e) => e.active && e.ayah_id === ayah.id && !e.edition_word_id && e.kind === 'penalty');
                                                        const ayahNotes = events.filter((e) => e.active && e.ayah_id === ayah.id && e.kind === 'note');

                                                        return (
                                                            <span key={ayah.id} style={{ display: 'inline', margin: '0 2px' }}>
                                                                {ayah.words && ayah.words.length > 0 ? (
                                                                    ayah.words.map((word) => {
                                                                        const penalty = events.find((e) => e.active && e.edition_word_id === word.id && e.kind === 'penalty');
                                                                        const ruleObj = penalty ? rules.find((r) => r.id === penalty.rule_id) : null;

                                                                        return (
                                                                            <button
                                                                                key={word.id}
                                                                                type="button"
                                                                                className={`mushaf-word-token ${penalty ? 'penalized' : ''}`}
                                                                                disabled={!editable}
                                                                                title={penalty ? `Kesalahan: ${ruleObj?.name ?? 'Penalti'} (−${ruleObj?.deduction_points ?? '0'}). Klik untuk membatalkan.` : 'Klik untuk menandai kesalahan kata'}
                                                                                onClick={() => toggleWordPenalty(ayah, word.id)}
                                                                            >
                                                                                <span>{word.text_or_glyph}</span>
                                                                                {penalty && ruleObj && (
                                                                                    <span className="mushaf-word-badge">
                                                                                        {ruleObj.name} −{ruleObj.deduction_points}
                                                                                    </span>
                                                                                )}
                                                                            </button>
                                                                        );
                                                                    })
                                                                ) : (
                                                                    <span style={{ margin: '0 6px' }}>{ayah.text_uthmani ?? `[Ayat ${ayah.number}]`}</span>
                                                                )}

                                                                {/* End of Ayah marker */}
                                                                <button
                                                                    type="button"
                                                                    className={`mushaf-ayah-token-end ${ayahPenalty ? 'penalized' : ''} ${ayahNotes.length > 0 ? 'has-note' : ''}`}
                                                                    disabled={!editable}
                                                                    title={`Ayat ${ayah.number}. ${ayahPenalty ? 'Terdapat kesalahan ayat. Klik untuk batal.' : 'Klik untuk menandai kesalahan ayat atau catatan.'}`}
                                                                    onClick={() => {
                                                                        if (ayahPenalty) {
                                                                            setEvents(events.map((e) => (e.id === ayahPenalty.id ? { ...e, active: false } : e)));
                                                                            dirty();
                                                                        } else if (selectedRule) {
                                                                            addPenalty(ayah);
                                                                        } else {
                                                                            setActiveNoteAyah(activeNoteAyah === ayah.id ? null : ayah.id);
                                                                        }
                                                                    }}
                                                                >
                                                                    {ayah.number}
                                                                </button>
                                                            </span>
                                                        );
                                                    })}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    /* Detail Per-Ayat Cards */
                                    <div style={{ display: 'grid', gap: '16px' }}>
                                        {ayahs.map((ayah) => {
                                            const ayahPenalty = events.find((e) => e.active && e.ayah_id === ayah.id && !e.edition_word_id && e.kind === 'penalty');
                                            const ayahNotes = events.filter((e) => e.active && e.ayah_id === ayah.id && e.kind === 'note');

                                            return (
                                                <article
                                                    key={ayah.id}
                                                    style={{
                                                        padding: '16px',
                                                        borderRadius: '10px',
                                                        border: ayahPenalty ? '1.5px solid #e57368' : '1px solid #d8e0d1',
                                                        background: ayahPenalty ? '#fff8f7' : '#fff',
                                                    }}
                                                >
                                                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '10px' }}>
                                                        <strong style={{ fontSize: '0.9rem', color: 'var(--green)' }}>
                                                            {ayah.surah_name} ({ayah.surah_number}:{ayah.number})
                                                        </strong>
                                                        <div className="master-actions">
                                                            <button
                                                                type="button"
                                                                disabled={!editable}
                                                                style={{ fontSize: '0.75rem', padding: '4px 10px', minHeight: '32px' }}
                                                                onClick={() => {
                                                                    if (ayahPenalty) {
                                                                        setEvents(events.map((e) => (e.id === ayahPenalty.id ? { ...e, active: false } : e)));
                                                                        dirty();
                                                                    } else {
                                                                        addPenalty(ayah);
                                                                    }
                                                                }}
                                                            >
                                                                {ayahPenalty ? 'Batalkan Kesalahan Ayat' : 'Tandai Kesalahan Ayat'}
                                                            </button>
                                                        </div>
                                                    </div>

                                                    <div style={{ direction: 'rtl', fontSize: `${zoom * 1.25}rem`, lineHeight: 2.2, marginBottom: '12px' }}>
                                                        {ayah.words && ayah.words.length > 0 ? (
                                                            <div style={{ display: 'flex', flexWrap: 'wrap', gap: '6px', justifyContent: 'flex-start' }}>
                                                                {ayah.words.map((word) => {
                                                                    const penalty = events.find((e) => e.active && e.edition_word_id === word.id && e.kind === 'penalty');
                                                                    const ruleObj = penalty ? rules.find((r) => r.id === penalty.rule_id) : null;

                                                                    return (
                                                                        <button
                                                                            key={word.id}
                                                                            type="button"
                                                                            className={`mushaf-word-token ${penalty ? 'penalized' : ''}`}
                                                                            disabled={!editable}
                                                                            onClick={() => toggleWordPenalty(ayah, word.id)}
                                                                        >
                                                                            <span>{word.text_or_glyph}</span>
                                                                            {penalty && ruleObj && (
                                                                                <span className="mushaf-word-badge">
                                                                                    {ruleObj.name} −{ruleObj.deduction_points}
                                                                                </span>
                                                                            )}
                                                                        </button>
                                                                    );
                                                                })}
                                                            </div>
                                                        ) : (
                                                            <p lang="ar">{ayah.text_uthmani ?? 'Teks belum tersedia'}</p>
                                                        )}
                                                    </div>

                                                    {/* Inline Notes for this Ayah */}
                                                    {ayahNotes.length > 0 && (
                                                        <div style={{ marginTop: '8px', padding: '8px 12px', background: '#fff9e6', borderRadius: '6px', fontSize: '0.82rem', color: '#664d03' }}>
                                                            {ayahNotes.map((n) => (
                                                                <div key={n.id} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                                                    <span>📝 {n.note}</span>
                                                                    {editable && (
                                                                        <button
                                                                            type="button"
                                                                            style={{ border: 'none', background: 'transparent', color: '#942c26', cursor: 'pointer', fontSize: '0.75rem' }}
                                                                            onClick={() => {
                                                                                setEvents(events.map((e) => (e.id === n.id ? { ...e, active: false } : e)));
                                                                                dirty();
                                                                            }}
                                                                        >
                                                                            Hapus
                                                                        </button>
                                                                    )}
                                                                </div>
                                                            ))}
                                                        </div>
                                                    )}

                                                    <div style={{ marginTop: '10px', display: 'flex', gap: '8px', alignItems: 'center' }}>
                                                        <input
                                                            type="text"
                                                            disabled={!editable}
                                                            placeholder="Catatan tanpa potongan untuk ayat ini…"
                                                            value={noteDrafts[ayah.id] ?? ''}
                                                            onChange={(e) => setNoteDrafts({ ...noteDrafts, [ayah.id]: e.target.value })}
                                                            style={{ margin: 0, minHeight: '36px', fontSize: '0.82rem', flex: 1 }}
                                                        />
                                                        <button
                                                            type="button"
                                                            disabled={!editable || !noteDrafts[ayah.id]?.trim()}
                                                            onClick={() => addNote(ayah)}
                                                            style={{ minHeight: '36px', padding: '6px 12px', fontSize: '0.8rem' }}
                                                        >
                                                            Tambah Catatan
                                                        </button>
                                                    </div>
                                                </article>
                                            );
                                        })}
                                    </div>
                                )}

                                {hasMore && (
                                    <div style={{ textAlign: 'center', marginTop: '24px' }}>
                                        <button type="button" onClick={() => void loadMore()} style={{ minHeight: '42px', padding: '8px 24px' }}>
                                            Muat Ayat Selanjutnya ↓
                                        </button>
                                    </div>
                                )}
                            </div>

                            {/* Sidebar: Scoring, Ranges & Annotations */}
                            <div style={{ display: 'grid', gap: '18px' }}>
                                {/* Real-time Score Preview */}
                                <div className="master-card" style={{ marginTop: 0, padding: '20px' }}>
                                    <h3 style={{ fontSize: '1.05rem', margin: '0 0 10px', color: 'var(--ink)' }}>Hasil & Skor Sesi</h3>
                                    {preview ? (
                                        <div>
                                            <div style={{ fontSize: '1.8rem', fontWeight: 700, color: preview.passed ? 'var(--green)' : '#942c26', marginBottom: '4px' }}>
                                                {preview.display_score ?? '—'}
                                            </div>
                                            <div style={{ fontSize: '0.85rem', color: 'var(--muted)', marginBottom: '14px' }}>
                                                Status: <strong>{preview.passed ? 'Lulus' : 'Belum Lulus'}</strong> {preview.grade && `(Predikat ${preview.grade})`}
                                            </div>
                                            <div style={{ borderTop: '1px solid #e0e5db', paddingTop: '10px' }}>
                                                {preview.criteria.map((item) => (
                                                    <div key={item.id} style={{ fontSize: '0.82rem', marginBottom: '6px' }}>
                                                        <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                                                            <span>{item.name}:</span>
                                                            <strong>{item.computed_raw ?? '—'}</strong>
                                                        </div>
                                                        {item.deductions.map((d) => (
                                                            <div key={d.event_id} style={{ paddingLeft: '8px', color: '#942c26', fontSize: '0.75rem' }}>
                                                                • {d.name} (−{d.points})
                                                            </div>
                                                        ))}
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    ) : (
                                        <p style={{ fontSize: '0.85rem', color: 'var(--muted)', margin: 0 }}>
                                            Simpan draf untuk melihat kalkulasi nilai terbaru.
                                        </p>
                                    )}
                                </div>

                                {/* Active Annotations List */}
                                <div className="master-card" style={{ marginTop: 0, padding: '20px' }}>
                                    <h3 style={{ fontSize: '1.05rem', margin: '0 0 10px' }}>
                                        Daftar Anotasi ({events.filter((e) => e.active).length})
                                    </h3>
                                    {events.filter((e) => e.active).length === 0 ? (
                                        <p style={{ fontSize: '0.85rem', color: 'var(--muted)', margin: 0 }}>Belum ada kesalahan atau catatan.</p>
                                    ) : (
                                        <div style={{ display: 'grid', gap: '8px', maxHeight: '240px', overflowY: 'auto' }}>
                                            {events.map((event) => {
                                                if (!event.active) return null;
                                                const rule = rules.find((r) => r.id === event.rule_id);
                                                return (
                                                    <div
                                                        key={event.id}
                                                        style={{
                                                            display: 'flex',
                                                            justifyContent: 'space-between',
                                                            alignItems: 'center',
                                                            padding: '6px 10px',
                                                            background: event.kind === 'penalty' ? '#fff4f2' : '#fff9e6',
                                                            borderRadius: '6px',
                                                            fontSize: '0.8rem',
                                                        }}
                                                    >
                                                        <span>
                                                            {event.kind === 'note' ? `📝 ${event.note}` : `⚠️ ${rule?.name ?? 'Kesalahan'} (−${rule?.deduction_points ?? '0'})`}
                                                        </span>
                                                        {editable && (
                                                            <button
                                                                type="button"
                                                                style={{ border: 'none', background: 'transparent', color: '#942c26', cursor: 'pointer', fontSize: '0.75rem', padding: '2px 6px' }}
                                                                onClick={() => {
                                                                    setEvents(events.map((item) => (item.id === event.id ? { ...item, active: false } : item)));
                                                                    dirty();
                                                                }}
                                                            >
                                                                Undo
                                                            </button>
                                                        )}
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    )}
                                </div>

                                {/* Direct Scores & Override Section */}
                                <fieldset disabled={!editable} className="master-card" style={{ marginTop: 0, padding: '20px' }}>
                                    <legend style={{ fontSize: '0.95rem', fontWeight: 600 }}>Nilai Langsung & Override</legend>
                                    {detail.rubric.criteria.map((criterion) => (
                                        <div key={criterion.id} style={{ marginBottom: '14px' }}>
                                            {criterion.method === 'direct' && (
                                                <label style={{ fontSize: '0.82rem' }}>
                                                    {criterion.name} ({criterion.min_score}–{criterion.max_score})
                                                    <input
                                                        type="number"
                                                        min={criterion.min_score}
                                                        max={criterion.max_score}
                                                        step="0.0001"
                                                        value={direct[criterion.id] ?? ''}
                                                        onChange={(e) => {
                                                            setDirect({ ...direct, [criterion.id]: e.target.value });
                                                            dirty();
                                                        }}
                                                        style={{ minHeight: '38px', margin: '4px 0 8px' }}
                                                    />
                                                </label>
                                            )}
                                            <div style={{ display: 'grid', gap: '6px' }}>
                                                <label style={{ fontSize: '0.78rem', color: 'var(--muted)' }}>
                                                    Override {criterion.name} (opsional)
                                                    <input
                                                        type="number"
                                                        min={criterion.min_score}
                                                        max={criterion.max_score}
                                                        step="0.0001"
                                                        value={overrides[criterion.id]?.raw ?? ''}
                                                        onChange={(e) => {
                                                            const next = { ...overrides };
                                                            if (e.target.value) {
                                                                next[criterion.id] = { raw: e.target.value, reason: next[criterion.id]?.reason ?? '' };
                                                            } else {
                                                                delete next[criterion.id];
                                                            }
                                                            setOverrides(next);
                                                            dirty();
                                                        }}
                                                        style={{ minHeight: '34px', margin: '2px 0' }}
                                                    />
                                                </label>
                                                {overrides[criterion.id] && (
                                                    <label style={{ fontSize: '0.78rem', color: 'var(--muted)' }}>
                                                        Alasan Override
                                                        <input
                                                            value={overrides[criterion.id].reason}
                                                            onChange={(e) => {
                                                                setOverrides({
                                                                    ...overrides,
                                                                    [criterion.id]: { ...overrides[criterion.id], reason: e.target.value },
                                                                });
                                                                dirty();
                                                            }}
                                                            style={{ minHeight: '34px', margin: '2px 0' }}
                                                        />
                                                    </label>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                    <label style={{ fontSize: '0.82rem' }}>
                                        Catatan Sesi Umum
                                        <textarea
                                            value={notes}
                                            onChange={(e) => {
                                                setNotes(e.target.value);
                                                dirty();
                                            }}
                                            placeholder="Catatan umum jalannya setoran…"
                                            style={{ minHeight: '60px', marginTop: '4px' }}
                                        />
                                    </label>
                                </fieldset>

                                {/* Actual Ranges Section */}
                                <fieldset disabled={!editable} className="master-card" style={{ marginTop: 0, padding: '20px' }}>
                                    <legend style={{ fontSize: '0.95rem', fontWeight: 600 }}>Cakupan Bacaan Aktual</legend>
                                    <p style={{ fontSize: '0.78rem', color: 'var(--muted)', margin: '0 0 8px' }}>
                                        Rencana: {detail.planned_ranges.map((r) => `${r.startSurah}:${r.startAyah}–${r.endSurah}:${r.endAyah}`).join(', ')}
                                    </p>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setRanges(detail.planned_ranges);
                                            dirty();
                                        }}
                                        style={{ fontSize: '0.78rem', padding: '5px 10px', minHeight: '34px', marginBottom: '10px' }}
                                    >
                                        Gunakan rencana
                                    </button>
                                    {ranges.map((range, index) => (
                                        <div key={index} style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: '6px', marginBottom: '8px', padding: '8px', background: '#f8faf6', borderRadius: '6px' }}>
                                            <label style={{ fontSize: '0.75rem' }}>
                                                Surah Awal
                                                <input type="number" min="1" max="114" value={range.startSurah} onChange={(e) => changeRange(index, 'startSurah', Number(e.target.value))} style={{ minHeight: '32px', margin: 0 }} />
                                            </label>
                                            <label style={{ fontSize: '0.75rem' }}>
                                                Ayat Awal
                                                <input type="number" min="1" value={range.startAyah} onChange={(e) => changeRange(index, 'startAyah', Number(e.target.value))} style={{ minHeight: '32px', margin: 0 }} />
                                            </label>
                                            <label style={{ fontSize: '0.75rem' }}>
                                                Surah Akhir
                                                <input type="number" min="1" max="114" value={range.endSurah} onChange={(e) => changeRange(index, 'endSurah', Number(e.target.value))} style={{ minHeight: '32px', margin: 0 }} />
                                            </label>
                                            <label style={{ fontSize: '0.75rem' }}>
                                                Ayat Akhir
                                                <input type="number" min="1" value={range.endAyah} onChange={(e) => changeRange(index, 'endAyah', Number(e.target.value))} style={{ minHeight: '32px', margin: 0 }} />
                                            </label>
                                            <button type="button" onClick={() => { setRanges(ranges.filter((_, i) => i !== index)); dirty(); }} style={{ gridColumn: 'span 2', fontSize: '0.72rem', color: '#942c26', minHeight: '28px', padding: '2px' }}>
                                                Hapus Rentang
                                            </button>
                                        </div>
                                    ))}
                                    <button type="button" onClick={() => { setRanges([...ranges, blankRange()]); dirty(); }} style={{ fontSize: '0.78rem', minHeight: '34px', padding: '4px 10px' }}>
                                        + Tambah Rentang
                                    </button>
                                </fieldset>
                            </div>
                        </div>

                        {/* Sticky Action / Save Bar */}
                        <div className="master-actions save-bar">
                            {detail.status === 'draft' ? (
                                <>
                                    <button className="primary" type="button" disabled={!editable} onClick={() => void save()}>
                                        Simpan Draf
                                    </button>
                                    <button type="button" disabled={state !== 'saved' || ranges.length === 0} onClick={() => void finalize()}>
                                        Finalisasi (Selesai Penilaian)
                                    </button>
                                    <button type="button" disabled={!editable} onClick={() => void cancel()}>
                                        Batalkan Draf
                                    </button>
                                </>
                            ) : (
                                <Link className="primary" href={`/assessments?activity=${detail.activity_type_id}&rubric=${detail.rubric.id}`}>
                                    Santri Berikutnya →
                                </Link>
                            )}
                        </div>
                    </>
                )}
            </main>
        </div>
    );
}
