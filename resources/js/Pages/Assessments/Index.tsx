import { Head, Link, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import { AssessmentHttpError, assessmentRequest } from '../../Assessment/http';

type Surah = { number: number; name_local: string; ayah_count: number };
type Range = { startSurah: number; startAyah: number; endSurah: number; endAyah: number };
type RecordRow = { id: string; student_name: string; current_final_id: string | null; draft_id: string | null; voided_at: string | null };
type Props = {
    edition: { id: string; name: string } | null;
    surahs: Surah[];
    students: { id: string; name: string; code: string }[];
    activities: { id: string; name: string }[];
    rubrics: { id: string; name_snapshot: string }[];
    records: RecordRow[];
};

export default function AssessmentsIndex({ edition, surahs, students, activities, rubrics, records }: Props) {
    const [studentId, setStudentId] = useState('');
    const [activityId, setActivityId] = useState(() => new URLSearchParams(window.location.search).get('activity') ?? '');
    const [rubricId, setRubricId] = useState(() => new URLSearchParams(window.location.search).get('rubric') ?? '');
    const [ranges, setRanges] = useState<Range[]>([{ startSurah: 1, startAyah: 1, endSurah: 1, endAyah: 1 }]);
    const [busy, setBusy] = useState(false);
    const [pendingCreate, setPendingCreate] = useState<object | null>(null);
    const [error, setError] = useState('');
    function range(index: number, part: keyof Range, value: number) { setRanges(ranges.map((item, i) => i === index ? { ...item, [part]: value } : item)); }
    async function create(event: FormEvent) {
        event.preventDefault();
        if (!edition || pendingCreate) return;
        await submitCreate({ student_id: studentId, activity_type_id: activityId, rubric_version_id: rubricId, edition_id: edition.id, planned_ranges: ranges, mutation_id: crypto.randomUUID() });
    }
    async function submitCreate(payload: object) {
        setPendingCreate(payload); setBusy(true); setError('');
        try {
            const created = await assessmentRequest<{ id: string }>('/assessments', 'POST', payload);
            router.visit(`/assessments/${created.id}/work`);
        } catch (failure) { setError(failure instanceof Error ? failure.message : 'Status pembuatan draf belum diketahui.'); setBusy(false); if (failure instanceof AssessmentHttpError) setPendingCreate(null); }
    }
    async function revise(recordId: string) {
        const reason = window.prompt('Alasan koreksi hasil final:');
        if (!reason?.trim()) return;
        try { const created = await assessmentRequest<{ id: string }>(`/assessment-records/${recordId}/revisions`, 'POST', { reason }); router.visit(`/assessments/${created.id}/work`); }
        catch (failure) { setError(failure instanceof Error ? failure.message : 'Revisi belum dibuat.'); }
    }
    async function voidRecord(recordId: string) {
        const reason = window.prompt('Alasan pembatalan hasil final:');
        if (!reason?.trim()) return;
        try { await assessmentRequest(`/assessment-records/${recordId}/void`, 'POST', { reason }); router.reload(); }
        catch (failure) { setError(failure instanceof Error ? failure.message : 'Hasil belum dibatalkan.'); }
    }
    return <div className="master-shell"><Head title="Sesi penilaian" /><a className="skip-link" href="#assessment-content">Lewati navigasi</a><header className="mushaf-header"><Link href="/dashboard">← Ringkasan</Link><span>Penilaian Tahfidz · Sesi</span></header>
        <main id="assessment-content" className="master-main"><p className="eyebrow">Sesi penilaian</p><h1>Sesi penilaian</h1>
            {pendingCreate && !busy && <button type="button" onClick={() => void submitCreate(pendingCreate)}>Coba ulang pembuatan draf yang sama</button>}
            {!edition && <section className="master-card" role="status"><h2>Edisi mushaf belum aktif</h2><p>Sesi baru menunggu referensi dan edisi Madinah Hafs 604 halaman yang tervalidasi pada F2. Rubrik dan data santri tetap bisa disiapkan.</p><div className="master-actions"><Link href="/rubrics">Buka rubrik</Link><Link href="/students">Buka santri</Link></div></section>}
            {edition && <section className="master-card"><h2>Buat draf setoran</h2><p className="muted">Edisi: {edition.name}. Materi awal dapat dipersempit menjadi bacaan aktual saat sesi berlangsung.</p><form className="master-form" onSubmit={(event) => void create(event)}><div className="master-fields"><label>Santri<select required value={studentId} onChange={(event) => setStudentId(event.target.value)}><option value="">Pilih santri</option>{students.map((student) => <option key={student.id} value={student.id}>{student.name} · {student.code}</option>)}</select></label><label>Kegiatan<select required value={activityId} onChange={(event) => setActivityId(event.target.value)}><option value="">Pilih kegiatan</option>{activities.map((activity) => <option key={activity.id} value={activity.id}>{activity.name}</option>)}</select></label><label>Rubrik terbit<select required value={rubricId} onChange={(event) => setRubricId(event.target.value)}><option value="">Pilih rubrik</option>{rubrics.map((rubric) => <option key={rubric.id} value={rubric.id}>{rubric.name_snapshot}</option>)}</select></label></div><h3>Materi rencana</h3>{ranges.map((item, index) => <div className="master-fields" key={index}><label>Surah awal<select value={item.startSurah} onChange={(event) => range(index, 'startSurah', Number(event.target.value))}>{surahs.map((surah) => <option key={surah.number} value={surah.number}>{surah.number}. {surah.name_local}</option>)}</select></label><label>Ayat awal<input type="number" min="1" value={item.startAyah} onChange={(event) => range(index, 'startAyah', Number(event.target.value))} /></label><label>Surah akhir<select value={item.endSurah} onChange={(event) => range(index, 'endSurah', Number(event.target.value))}>{surahs.map((surah) => <option key={surah.number} value={surah.number}>{surah.number}. {surah.name_local}</option>)}</select></label><label>Ayat akhir<input type="number" min="1" value={item.endAyah} onChange={(event) => range(index, 'endAyah', Number(event.target.value))} /></label>{ranges.length > 1 && <button type="button" onClick={() => setRanges(ranges.filter((_, i) => i !== index))}>Hapus rentang</button>}</div>)}<div className="master-actions"><button type="button" onClick={() => setRanges([...ranges, { startSurah: 1, startAyah: 1, endSurah: 1, endAyah: 1 }])}>Tambah rentang</button><button className="primary" disabled={busy}>Mulai draf</button></div></form></section>}
            <section className="master-card"><h2>Riwayat sesi</h2>{records.length === 0 && <p>Belum ada sesi.</p>}{records.map((record) => <article className="master-row" key={record.id}><div><strong>{record.student_name}</strong><p>{record.voided_at ? 'Dibatalkan' : record.draft_id ? 'Draf aktif' : record.current_final_id ? 'Final' : 'Draf dibatalkan'}</p></div><div className="master-actions">{record.draft_id && <Link href={`/assessments/${record.draft_id}/work`}>Lanjutkan</Link>}{record.current_final_id && <Link href={`/assessments/${record.current_final_id}/work`}>Lihat hasil</Link>}{record.current_final_id && !record.draft_id && !record.voided_at && <><button type="button" onClick={() => void revise(record.id)}>Koreksi</button><button type="button" onClick={() => void voidRecord(record.id)}>Batalkan hasil</button></>}</div></article>)}</section>{error && <p role="alert">{error}</p>}
        </main></div>;
}
