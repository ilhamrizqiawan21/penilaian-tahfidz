import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { AssessmentHttpError, assessmentRequest } from '../../Assessment/http';

type Range = { startSurah: number; startAyah: number; endSurah: number; endAyah: number };
type Criterion = { id: string; name: string; method: 'direct' | 'deduction'; min_score: string; max_score: string; rules: { id: string; name: string; deduction_points: string }[] };
type Ayah = { id: string; number: number; global_order: number; surah_number: number; surah_name: string; text_uthmani: string | null; words: { id: string; text_or_glyph: string }[] };
type Event = { id: string; kind: 'note' | 'penalty'; ayah_id: string; edition_word_id?: string | null; rule_id?: string | null; note?: string | null; active: boolean };
type Detail = { id: string; record_id: string; student: { name: string; code: string }; activity_type_id: string; activity_name: string; status: string; lock_version: number; notes: string | null; last_ayah_id: string | null; rubric: { id: string; name: string; criteria: Criterion[] }; planned_ranges: Range[]; actual_ranges: Range[]; scores: { criterion_id: string; direct_input: string | null; override_raw: string | null; override_reason: string | null }[]; events: { client_event_id: string; kind: 'note' | 'penalty'; ayah_id: string; edition_word_id: string | null; mistake_rule_id: string | null; note: string | null; retracted_at: string | null }[]; ayahs: Ayah[]; has_more_ayahs: boolean; final_score: string | null; passed: boolean | number | null };
type Preview = { complete: boolean; display_score: string | null; passed: boolean | null; grade: string | null; criteria: { id: string; name: string; computed_raw: string | null; normalized: string | null; deductions: { event_id: string; name: string; points: string }[] }[] };
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
    const [lockVersion, setLockVersion] = useState(0);
    const [state, setState] = useState<'loading' | 'unsaved' | 'saving' | 'saved' | 'uncertain' | 'conflict' | 'final'>('loading');
    const [error, setError] = useState('');
    const [preview, setPreview] = useState<Preview | null>(null);
    const [pendingSave, setPendingSave] = useState<unknown>(null);
    const [pendingFinal, setPendingFinal] = useState<unknown>(null);
    useEffect(() => {
        void assessmentRequest<Detail>(`/assessments/${assessmentId}`, 'GET').then((data) => {
            setDetail(data); setAyahs(data.ayahs); setHasMore(data.has_more_ayahs); setRanges(data.actual_ranges); setLockVersion(data.lock_version);
            setDirect(Object.fromEntries(data.scores.filter((item) => item.direct_input !== null).map((item) => [item.criterion_id, item.direct_input ?? ''])));
            setOverrides(Object.fromEntries(data.scores.filter((item) => item.override_raw !== null).map((item) => [item.criterion_id, { raw: item.override_raw ?? '', reason: item.override_reason ?? '' }])));
            setEvents(data.events.map((item) => ({ id: item.client_event_id, kind: item.kind, ayah_id: item.ayah_id, edition_word_id: item.edition_word_id, rule_id: item.mistake_rule_id, note: item.note, active: item.retracted_at === null })));
            setNotes(data.notes ?? ''); setLastAyahId(data.last_ayah_id);
            setState(data.status === 'draft' ? 'saved' : 'final');
        }).catch((failure) => { setError(failure instanceof Error ? failure.message : 'Sesi belum dapat dimuat.'); setState('conflict'); });
    }, [assessmentId]);
    const editable = detail?.status === 'draft' && state !== 'uncertain' && state !== 'saving' && state !== 'conflict' && state !== 'final';
    function dirty() { setState('unsaved'); setPreview(null); }
    function changeRange(index: number, field: keyof Range, value: number) { setRanges(ranges.map((item, i) => i === index ? { ...item, [field]: value } : item)); dirty(); }
    function addPenalty(ayah: Ayah, wordId?: string) { if (!selectedRule) { setError('Pilih aturan kesalahan dahulu.'); return; } setEvents([...events, { id: crypto.randomUUID(), kind: 'penalty', ayah_id: ayah.id, edition_word_id: wordId ?? null, rule_id: selectedRule, active: true }]); setLastAyahId(ayah.id); setError(''); dirty(); }
    function addNote(ayah: Ayah) { const note = noteDrafts[ayah.id]?.trim(); if (!note) return; setEvents([...events, { id: crypto.randomUUID(), kind: 'note', ayah_id: ayah.id, note, active: true }]); setNoteDrafts({ ...noteDrafts, [ayah.id]: '' }); setLastAyahId(ayah.id); dirty(); }
    async function loadMore() { if (ayahs.length === 0) return; try { const page = await assessmentRequest<Detail>(`/assessments/${assessmentId}?after=${ayahs.at(-1)?.global_order}`, 'GET'); setAyahs([...ayahs, ...page.ayahs]); setHasMore(page.has_more_ayahs); } catch (failure) { setError(failure instanceof Error ? failure.message : 'Ayat berikutnya belum termuat.'); } }
    async function save(retry = false) {
        const payload = retry ? pendingSave : { lock_version: lockVersion, mutation_id: crypto.randomUUID(), direct, overrides, actual_ranges: ranges, events, last_ayah_id: lastAyahId, notes };
        if (!payload) return;
        setPendingSave(payload); setState('saving'); setError('');
        try { const saved = await assessmentRequest<SaveResponse>(`/assessments/${assessmentId}/draft`, 'PUT', payload); setLockVersion(saved.lock_version); setPreview(saved.preview); setPendingSave(null); setState('saved'); }
        catch (failure) { if (failure instanceof AssessmentHttpError) { setPendingSave(null); setState(failure.status === 409 ? 'conflict' : 'unsaved'); setError(failure.message); } else { setState('uncertain'); setError('Sambungan terputus. Status simpan belum diketahui; ulangi permintaan yang sama.'); } }
    }
    async function finalize(retry = false) {
        const payload = retry ? pendingFinal : { lock_version: lockVersion, mutation_id: crypto.randomUUID() };
        if (!payload) return;
        setPendingFinal(payload); setState('saving'); setError('');
        try { const final = await assessmentRequest<FinalResponse>(`/assessments/${assessmentId}/finalize`, 'POST', payload); setLockVersion(final.lock_version); setPreview(final); setPendingFinal(null); setState('final'); setDetail(detail ? { ...detail, status: 'final', final_score: final.unrounded_score, passed: final.passed } : detail); }
        catch (failure) { if (failure instanceof AssessmentHttpError) { setPendingFinal(null); setState(failure.status === 409 ? 'conflict' : 'saved'); setError(failure.message); } else { setState('uncertain'); setError('Sambungan terputus saat finalisasi. Ulangi permintaan yang sama sebelum tindakan lain.'); } }
    }
    async function cancel() { try { await assessmentRequest(`/assessments/${assessmentId}/cancel`, 'POST', {}); router.visit('/assessments'); } catch (failure) { setError(failure instanceof Error ? failure.message : 'Draf belum dibatalkan.'); } }
    const rules = detail?.rubric.criteria.flatMap((criterion) => criterion.rules.map((rule) => ({ ...rule, criterion: criterion.name }))) ?? [];

    return <div className="master-shell"><Head title="Draf penilaian" /><a className="skip-link" href="#work-content">Lewati navigasi</a><header className="mushaf-header"><Link href="/assessments">← Daftar sesi</Link><span>Penilaian Tahfidz · Penilaian</span></header><main id="work-content" className="master-main">
        <p className="eyebrow">Sesi penilaian</p><h1>{detail ? `${detail.student.name} · ${detail.activity_name}` : 'Memuat sesi…'}</h1><p className="save-status" data-state={state} role="status">{state === 'saving' ? 'Menyimpan…' : state === 'saved' ? 'Tersimpan di server' : state === 'unsaved' ? 'Perubahan belum tersimpan' : state === 'uncertain' ? 'Belum diketahui apakah tersimpan' : state === 'conflict' ? 'Konflik atau gagal memuat' : state === 'final' ? 'Hasil final' : 'Memuat…'}</p>{error && <p role="alert">{error}</p>}
        {state === 'uncertain' && <div className="master-actions"><button type="button" onClick={() => pendingFinal ? void finalize(true) : void save(true)}>Coba ulang permintaan yang sama</button></div>}{state === 'conflict' && <button type="button" onClick={() => window.location.reload()}>Muat ulang data server</button>}
        {detail && <><fieldset disabled={!editable} className="master-card"><legend>Bacaan aktual</legend><p>Rencana: {detail.planned_ranges.map((range) => `${range.startSurah}:${range.startAyah}–${range.endSurah}:${range.endAyah}`).join(', ')}</p><button type="button" onClick={() => { setRanges(detail.planned_ranges); dirty(); }}>Gunakan rencana sebagai bacaan aktual</button>{ranges.map((range, index) => <div className="master-fields" key={index}><label>Surah awal<input type="number" min="1" max="114" value={range.startSurah} onChange={(event) => changeRange(index, 'startSurah', Number(event.target.value))} /></label><label>Ayat awal<input type="number" min="1" value={range.startAyah} onChange={(event) => changeRange(index, 'startAyah', Number(event.target.value))} /></label><label>Surah akhir<input type="number" min="1" max="114" value={range.endSurah} onChange={(event) => changeRange(index, 'endSurah', Number(event.target.value))} /></label><label>Ayat akhir<input type="number" min="1" value={range.endAyah} onChange={(event) => changeRange(index, 'endAyah', Number(event.target.value))} /></label><button type="button" onClick={() => { setRanges(ranges.filter((_, i) => i !== index)); dirty(); }}>Hapus</button></div>)}<button type="button" onClick={() => { setRanges([...ranges, blankRange()]); dirty(); }}>Tambah rentang aktual</button></fieldset>
            <fieldset disabled={!editable} className="master-card"><legend>Nilai dan anotasi</legend>{detail.rubric.criteria.map((criterion) => <div className="master-form" key={criterion.id}><h3>{criterion.name}</h3>{criterion.method === 'direct' && <label>Nilai langsung ({criterion.min_score}–{criterion.max_score})<input type="number" min={criterion.min_score} max={criterion.max_score} step="0.0001" value={direct[criterion.id] ?? ''} onChange={(event) => { setDirect({ ...direct, [criterion.id]: event.target.value }); dirty(); }} /></label>}<div className="master-fields"><label>Override opsional<input type="number" min={criterion.min_score} max={criterion.max_score} step="0.0001" value={overrides[criterion.id]?.raw ?? ''} onChange={(event) => { const next = { ...overrides }; if (event.target.value) next[criterion.id] = { raw: event.target.value, reason: next[criterion.id]?.reason ?? '' }; else delete next[criterion.id]; setOverrides(next); dirty(); }} /></label>{overrides[criterion.id] && <label>Alasan override<input value={overrides[criterion.id].reason} onChange={(event) => { setOverrides({ ...overrides, [criterion.id]: { ...overrides[criterion.id], reason: event.target.value } }); dirty(); }} /></label>}</div></div>)}<label>Aturan kesalahan cepat<select value={selectedRule} onChange={(event) => setSelectedRule(event.target.value)}><option value="">Pilih aturan</option>{rules.map((rule) => <option key={rule.id} value={rule.id}>{rule.criterion} · {rule.name} (−{rule.deduction_points})</option>)}</select></label><label>Catatan sesi<textarea value={notes} onChange={(event) => { setNotes(event.target.value); dirty(); }} /></label></fieldset>
            <section className="master-card" aria-labelledby="ayah-heading"><h2 id="ayah-heading">Ayat dalam materi</h2><p className="muted">Teks dan kata mengikuti edisi referensi aktif. Pilih aturan, lalu tandai ayat atau kata. Jika pemetaan kata belum tersedia, gunakan penanda ayat.</p>{ayahs.map((ayah) => <article className="master-row" key={ayah.id}><div><h3>{ayah.surah_name} {ayah.surah_number}:{ayah.number}</h3><p lang="ar" dir="rtl">{ayah.text_uthmani ?? 'Teks edisi belum tersedia'}</p><div className="master-actions">{ayah.words.map((word) => <button disabled={!editable || !selectedRule} type="button" key={word.id} onClick={() => addPenalty(ayah, word.id)} lang="ar" dir="rtl">{word.text_or_glyph}</button>)}</div><div className="master-actions"><button disabled={!editable || !selectedRule} type="button" onClick={() => addPenalty(ayah)}>Tandai kesalahan ayat</button><label>Catatan tanpa potongan<input disabled={!editable} value={noteDrafts[ayah.id] ?? ''} onChange={(event) => setNoteDrafts({ ...noteDrafts, [ayah.id]: event.target.value })} /></label><button disabled={!editable || !noteDrafts[ayah.id]?.trim()} type="button" onClick={() => addNote(ayah)}>Tambah catatan</button></div></div></article>)}{hasMore && <button type="button" onClick={() => void loadMore()}>Muat ayat berikutnya</button>}</section>
            <section className="master-card"><h2>Daftar anotasi</h2>{events.length === 0 && <p>Belum ada anotasi.</p>}{events.map((event) => <p key={event.id}>{event.kind === 'note' ? event.note : rules.find((rule) => rule.id === event.rule_id)?.name} · {event.active ? 'aktif' : 'di-undo'} {event.active && detail.status === 'draft' && <button type="button" disabled={!editable} onClick={() => { setEvents(events.map((item) => item.id === event.id ? { ...item, active: false } : item)); dirty(); }}>Undo</button>}</p>)}</section>
            {preview && <section className="master-card" role="status"><h2>{preview.complete ? `${preview.display_score} · ${preview.passed ? 'Lulus' : 'Belum lulus'}` : 'Nilai belum lengkap'}</h2>{preview.grade && <p>Predikat: {preview.grade}</p>}{preview.criteria.map((item) => <p key={item.id}>{item.name}: hasil {item.computed_raw ?? 'belum diisi'}, normalisasi {item.normalized ?? '—'}{item.deductions.map((deduction) => <span key={deduction.event_id}> · {deduction.name} −{deduction.points}</span>)}</p>)}</section>}
            <div className="master-actions save-bar">{detail.status === 'draft' && <><button className="primary" type="button" disabled={!editable} onClick={() => void save()}>Simpan draf</button><button type="button" disabled={state !== 'saved' || ranges.length === 0} onClick={() => void finalize()}>Finalisasi</button><button type="button" disabled={!editable} onClick={() => void cancel()}>Batalkan draf</button></>}{detail.status !== 'draft' && <Link href={`/assessments?activity=${detail.activity_type_id}&rubric=${detail.rubric.id}`}>Santri berikutnya</Link>}</div>
        </>}
    </main></div>;
}
