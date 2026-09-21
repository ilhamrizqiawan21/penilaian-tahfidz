import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

type Range = { startSurah: number; startAyah: number; endSurah: number; endAyah: number };
type Surah = { number: number; name_local: string; ayah_count: number };
type Juz = Range & { juz_number: number };
type Version = { id: string; version: number; name_snapshot: string; description: string | null; start_date: string | null; target_date: string | null; status: string; ranges: Range[] };
type Enrollment = { id: string; student_id: string; program_version_id: string; student_name: string };
type Program = { id: string; name: string; archived_at: string | null; versions: Version[]; enrollments: Enrollment[] };
type Props = { reference: { name: string; surahs: Surah[]; juz: Juz[] } | null; programs: Program[]; students: { id: string; code: string; name: string }[] };
const emptyRange = (): Range => ({ startSurah: 1, startAyah: 1, endSurah: 1, endAyah: 1 });

export default function ProgramsIndex({ reference, programs, students }: Props) {
    const form = useForm<{ name: string; description: string; start_date: string; target_date: string; ranges: Range[] }>({ name: '', description: '', start_date: '', target_date: '', ranges: [emptyRange()] });
    const [editing, setEditing] = useState<{ program: string; version: string } | null>(null);
    const [preview, setPreview] = useState<{ unique_ayahs: number; ranges: { count: number }[] } | null>(null);
    const [previewError, setPreviewError] = useState('');
    const [studentId, setStudentId] = useState('');
    const [versionId, setVersionId] = useState('');

    function updateRange(index: number, field: keyof Range, value: number) {
        form.setData('ranges', form.data.ranges.map((range, position) => position === index ? { ...range, [field]: value } : range));
        setPreview(null);
    }
    function submit(event: FormEvent) {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => { form.reset(); setEditing(null); setPreview(null); } };
        if (editing) form.put(`/programs/${editing.program}/versions/${editing.version}`, options);
        else form.post('/programs', options);
    }
    async function previewTargets() {
        setPreviewError('');
        const token = document.cookie.split('; ').find((part) => part.startsWith('XSRF-TOKEN='))?.split('=')[1];
        const response = await fetch('/programs/preview', {
            method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-XSRF-TOKEN': token ? decodeURIComponent(token) : '' },
            body: JSON.stringify({ ranges: form.data.ranges }),
        });
        if (response.ok) setPreview(await response.json());
        else { setPreview(null); setPreviewError('Rentang belum valid. Periksa nomor surah dan ayat.'); }
    }
    function startEdit(program: Program, version: Version) {
        setEditing({ program: program.id, version: version.id });
        form.setData({ name: version.name_snapshot, description: version.description ?? '', start_date: version.start_date ?? '', target_date: version.target_date ?? '', ranges: version.ranges });
        setPreview(null);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    return <div className="master-shell">
        <Head title="Program hafalan" />
        <a className="skip-link" href="#program-content">Lewati navigasi</a>
        <header className="mushaf-header"><Link href="/dashboard">← Ringkasan</Link><span>Penilaian Tahfidz · Program</span></header>
        <main id="program-content" className="master-main">
            <p className="eyebrow">F3 · Program hafalan</p><h1>Program hafalan</h1>
            {!reference && <section className="master-card" role="status"><h2>Referensi mushaf belum tersedia</h2><p>Program baru dapat disusun setelah dataset mushaf Madinah Hafs 604 halaman divalidasi dan diaktifkan. Data santri tetap dapat dikelola.</p><Link href="/students">Buka data santri</Link></section>}
            {reference && <section className="master-card" aria-labelledby="program-form-heading"><h2 id="program-form-heading">{editing ? 'Ubah draf program' : 'Susun program baru'}</h2><p className="muted">Referensi: {reference.name}. Rentang inklusif; urutan baris menjadi urutan belajar. Rentang yang tumpang tindih dihitung satu kali.</p>
                <form className="master-form" onSubmit={submit}>
                    <label>Nama program<input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} maxLength={160} required />{form.errors.name && <small role="alert">{form.errors.name}</small>}</label>
                    <label>Deskripsi opsional<textarea value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} /></label>
                    <div className="master-fields"><label>Tanggal mulai<input type="date" value={form.data.start_date} onChange={(e) => form.setData('start_date', e.target.value)} /></label><label>Target selesai<input type="date" value={form.data.target_date} onChange={(e) => form.setData('target_date', e.target.value)} /></label></div>
                    <label>Isi cepat dari juz<select defaultValue="" onChange={(e) => { const juz = reference.juz.find((item) => item.juz_number === Number(e.target.value)); if (juz) { form.setData('ranges', [...form.data.ranges, { startSurah: juz.startSurah, startAyah: juz.startAyah, endSurah: juz.endSurah, endAyah: juz.endAyah }]); setPreview(null); } e.target.value = ''; }}><option value="">Pilih juz…</option>{reference.juz.map((juz) => <option key={juz.juz_number} value={juz.juz_number}>Juz {juz.juz_number}</option>)}</select></label>
                    {form.data.ranges.map((range, index) => <fieldset className="master-card" key={index}><legend>Rentang {index + 1}</legend><div className="master-fields">
                        <label>Surah awal<select value={range.startSurah} onChange={(e) => updateRange(index, 'startSurah', Number(e.target.value))}>{reference.surahs.map((surah) => <option key={surah.number} value={surah.number}>{surah.number}. {surah.name_local}</option>)}</select></label>
                        <label>Ayat awal<input type="number" min={1} max={reference.surahs.find((s) => s.number === range.startSurah)?.ayah_count} value={range.startAyah} onChange={(e) => updateRange(index, 'startAyah', Number(e.target.value))} /></label>
                        <label>Surah akhir<select value={range.endSurah} onChange={(e) => updateRange(index, 'endSurah', Number(e.target.value))}>{reference.surahs.map((surah) => <option key={surah.number} value={surah.number}>{surah.number}. {surah.name_local}</option>)}</select></label>
                        <label>Ayat akhir<input type="number" min={1} max={reference.surahs.find((s) => s.number === range.endSurah)?.ayah_count} value={range.endAyah} onChange={(e) => updateRange(index, 'endAyah', Number(e.target.value))} /></label>
                    </div>{form.data.ranges.length > 1 && <button type="button" onClick={() => { form.setData('ranges', form.data.ranges.filter((_, i) => i !== index)); setPreview(null); }}>Hapus rentang</button>}{form.errors[`ranges.${index}`] && <small role="alert">{form.errors[`ranges.${index}`]}</small>}</fieldset>)}
                    {'dataset' in form.errors && <small role="alert">{String(form.errors.dataset)}</small>}
                    <div className="master-actions"><button type="button" onClick={() => { form.setData('ranges', [...form.data.ranges, emptyRange()]); setPreview(null); }}>Tambah rentang</button><button type="button" onClick={() => void previewTargets()}>Preview cakupan</button></div>
                    {preview && <p role="status"><strong>{preview.unique_ayahs} ayat unik</strong> · tiap rentang: {preview.ranges.map((range) => range.count).join(', ')} ayat</p>}{previewError && <small role="alert">{previewError}</small>}
                    <div className="master-actions"><button className="primary" disabled={form.processing}>{editing ? 'Simpan draf' : 'Buat draf'}</button>{editing && <button type="button" onClick={() => { setEditing(null); form.reset(); }}>Batal</button>}</div>
                </form>
            </section>}
            <section className="master-card" aria-labelledby="program-list-heading"><h2 id="program-list-heading">Daftar program</h2>{programs.length === 0 && <p className="muted">Belum ada program.</p>}
                {programs.map((program) => <article className="master-row" key={program.id}><div><h3>{program.name}{program.archived_at ? ' · Diarsipkan' : ''}</h3>{program.versions.map((version) => <p key={version.id}>Versi {version.version} · {version.status === 'published' ? 'Terbit' : version.status === 'draft' ? 'Draf' : 'Riwayat'} · {version.ranges.length} rentang {!program.archived_at && version.status === 'draft' && <><button type="button" onClick={() => startEdit(program, version)}>Ubah</button> <button type="button" onClick={() => router.post(`/programs/${program.id}/versions/${version.id}/publish`)}>Terbitkan</button></>}</p>)}
                    {!program.archived_at && program.versions.every((version) => version.status !== 'draft') && <button type="button" onClick={() => router.post(`/programs/${program.id}/versions`)}>Buat versi baru</button>}
                    {!program.archived_at && program.versions.some((version) => version.status === 'published') && <div className="master-form"><h4>Daftarkan santri</h4><div className="master-fields"><label>Santri<select value={studentId} onChange={(e) => setStudentId(e.target.value)}><option value="">Pilih santri</option>{students.map((student) => <option key={student.id} value={student.id}>{student.name} · {student.code}</option>)}</select></label><label>Versi<select value={versionId} onChange={(e) => setVersionId(e.target.value)}><option value="">Pilih versi</option>{program.versions.filter((version) => version.status === 'published').map((version) => <option key={version.id} value={version.id}>Versi {version.version}</option>)}</select></label></div><button type="button" disabled={!studentId || !versionId} onClick={() => router.post(`/programs/${program.id}/enrollments`, { student_id: studentId, version_id: versionId })}>Daftarkan</button></div>}
                    {program.enrollments.map((enrollment) => <p key={enrollment.id}>{enrollment.student_name} · versi {program.versions.find((version) => version.id === enrollment.program_version_id)?.version}{!program.archived_at && program.versions.some((version) => version.status === 'published' && version.id !== enrollment.program_version_id) && <button type="button" onClick={() => { const current = program.versions.find((version) => version.status === 'published'); if (current) router.post(`/programs/${program.id}/enrollments/${enrollment.id}/transfer`, { version_id: current.id }); }}>Pindahkan ke versi terbaru</button>}</p>)}
                    {!program.archived_at && <button type="button" onClick={() => router.post(`/programs/${program.id}/archive`)}>Arsipkan program</button>}
                </div></article>)}
            </section>
        </main>
    </div>;
}
