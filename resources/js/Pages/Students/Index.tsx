import { Head, Link, router, useForm } from '@inertiajs/react';
import { useRef, useState, type FormEvent } from 'react';

type Student = { id: string; code: string; name: string; contact: string | null; notes: string | null; archived_at: string | null };
type Group = { id: string; name: string; archived_at: string | null; student_ids: string[] };
type Activity = { id: string; name: string; counts_toward_progress: number | boolean; archived_at: string | null };
type Props = {
    students: { data: Student[]; current_page: number; last_page: number; prev_page_url: string | null; next_page_url: string | null };
    filters: { q: string; status: string };
    groups: Group[];
    groupCandidates: Pick<Student, 'id' | 'code' | 'name'>[];
    activities: Activity[];
};

export default function StudentsIndex({ students, filters, groups, groupCandidates, activities }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [section, setSection] = useState('students');
    const [notice, setNotice] = useState('');
    const codeInput = useRef<HTMLInputElement>(null);
    const [editingStudent, setEditingStudent] = useState<string | null>(null);
    const [editingGroup, setEditingGroup] = useState<string | null>(null);
    const [editingActivity, setEditingActivity] = useState<string | null>(null);
    const student = useForm({ code: '', name: '', contact: '', notes: '' });
    const group = useForm<{ name: string; student_ids: string[] }>({ name: '', student_ids: [] });
    const activity = useForm({ name: '', counts_toward_progress: true });

    function submitStudent(event: FormEvent) {
        event.preventDefault();
        setNotice('');
        const options = { preserveScroll: true, onSuccess: () => { student.reset(); setEditingStudent(null); setNotice(editingStudent ? 'Perubahan santri tersimpan.' : 'Santri ditambahkan. Anda bisa langsung mengisi santri berikutnya.'); codeInput.current?.focus(); } };
        if (editingStudent) student.put(`/students/${editingStudent}`, options);
        else student.post('/students', options);
    }

    function submitGroup(event: FormEvent) {
        event.preventDefault();
        setNotice('');
        const options = { preserveScroll: true, onSuccess: () => { group.reset(); setEditingGroup(null); setNotice('Kelompok tersimpan.'); } };
        if (editingGroup) group.put(`/groups/${editingGroup}`, options);
        else group.post('/groups', options);
    }

    function submitActivity(event: FormEvent) {
        event.preventDefault();
        setNotice('');
        const options = { preserveScroll: true, onSuccess: () => { activity.reset(); setEditingActivity(null); setNotice('Kegiatan tersimpan.'); } };
        if (editingActivity) activity.put(`/activity-types/${editingActivity}`, options);
        else activity.post('/activity-types', options);
    }

    return <div className="master-shell">
        <Head title="Santri dan pengaturan" />
        <a className="skip-link" href="#master-content">Lewati navigasi</a>
        <header className="mushaf-header"><Link href="/dashboard">← Ringkasan</Link><span>Penilaian Tahfidz · Data santri</span></header>
        <main id="master-content" className="master-main">
            <p className="eyebrow">Data pembelajaran</p>
            <h1>Santri dan pengaturan</h1>
            <p className="muted">Tambahkan santri terlebih dahulu, lalu siapkan kegiatan belajarnya. Kelompok bersifat opsional.</p>
            <div className="section-switcher" role="group" aria-label="Bagian data pembelajaran">{[['students', 'Santri'], ['activities', 'Jenis kegiatan'], ['groups', 'Kelompok opsional']].map(([key, label]) => <button key={key} type="button" aria-pressed={section === key} aria-controls={`${key}-section`} onClick={() => { setSection(key); setNotice(''); }}>{label}</button>)}</div>
            <p className="form-notice" role="status">{notice}</p>

            <section id="students-section" hidden={section !== 'students'} className="master-card" aria-labelledby="students-heading">
                <h2 id="students-heading">Santri</h2>
                <form className="master-search" onSubmit={(event) => { event.preventDefault(); router.get('/students', { q: search, status: filters.status }, { preserveState: true }); }}>
                    <label htmlFor="student-search">Cari nama atau kode</label>
                    <input id="student-search" value={search} onChange={(event) => setSearch(event.target.value)} />
                    <button className="primary" type="submit">Cari</button>
                    <Link href={`/students?status=${filters.status === 'active' ? 'archived' : 'active'}`}>{filters.status === 'active' ? 'Lihat arsip' : 'Lihat aktif'}</Link>
                </form>
                <form className="master-form" onSubmit={submitStudent} aria-busy={student.processing}>
                    <h3>{editingStudent ? 'Ubah santri' : 'Tambah santri'}</h3>
                    <div className="master-fields">
                        <label>Kode<input ref={codeInput} placeholder="Contoh: S-001" autoComplete="off" value={student.data.code} maxLength={40} required aria-invalid={!!student.errors.code} onChange={(event) => student.setData('code', event.target.value)} />{student.errors.code && <small role="alert">{student.errors.code}</small>}</label>
                        <label>Nama<input value={student.data.name} maxLength={160} required onChange={(event) => student.setData('name', event.target.value)} />{student.errors.name && <small role="alert">{student.errors.name}</small>}</label>
                        <label>Kontak opsional<input value={student.data.contact} maxLength={160} onChange={(event) => student.setData('contact', event.target.value)} />{student.errors.contact && <small role="alert">{student.errors.contact}</small>}</label>
                    </div>
                    <label>Catatan opsional<textarea value={student.data.notes} maxLength={4000} onChange={(event) => student.setData('notes', event.target.value)} />{student.errors.notes && <small role="alert">{student.errors.notes}</small>}</label>
                    <div className="master-actions"><button className="primary" disabled={student.processing}>{student.processing ? 'Menyimpan…' : editingStudent ? 'Simpan perubahan' : 'Tambah santri'}</button>{editingStudent && <button type="button" onClick={() => { student.reset(); setEditingStudent(null); }}>Batal</button>}<span className="field-hint">Kode dan nama wajib diisi.</span></div>
                </form>
                <div className="master-list" role="list" aria-label="Daftar santri">
                    {students.data.length === 0 && <p className="muted">Belum ada santri pada tampilan ini.</p>}
                    {students.data.map((item) => <article className="master-row" role="listitem" key={item.id}><div><strong>{item.name}</strong><br /><span className="muted">{item.code}{item.contact ? ` · ${item.contact}` : ''}</span></div><div className="master-actions">
                        <button type="button" onClick={() => { setEditingStudent(item.id); setNotice(''); student.clearErrors(); student.setData({ code: item.code, name: item.name, contact: item.contact ?? '', notes: item.notes ?? '' }); codeInput.current?.focus(); }}>Ubah</button>
                        {item.archived_at ? <button type="button" onClick={() => router.post(`/students/${item.id}/restore`, {}, { preserveScroll: true })}>Pulihkan</button> : <button type="button" onClick={() => router.post(`/students/${item.id}/archive`, {}, { preserveScroll: true })}>Arsipkan</button>}
                    </div></article>)}
                </div>
                <div className="master-actions">{students.prev_page_url && <Link href={students.prev_page_url}>Sebelumnya</Link>}<span>Halaman {students.current_page} dari {students.last_page}</span>{students.next_page_url && <Link href={students.next_page_url}>Berikutnya</Link>}</div>
            </section>

            <div>
                <section id="groups-section" hidden={section !== 'groups'} className="master-card" aria-labelledby="groups-heading"><h2 id="groups-heading">Kelompok opsional</h2>
                    <form className="master-form" onSubmit={submitGroup}><h3>{editingGroup ? 'Ubah kelompok' : 'Tambah kelompok'}</h3>
                        <label>Nama kelompok<input value={group.data.name} required maxLength={160} onChange={(event) => group.setData('name', event.target.value)} />{group.errors.name && <small role="alert">{group.errors.name}</small>}</label>
                        <fieldset className="master-checks"><legend>Anggota</legend>{groupCandidates.length === 0 && <p className="muted">Tambah santri dahulu jika ingin memilih anggota.</p>}{groupCandidates.map((item) => <label key={item.id}><input type="checkbox" checked={group.data.student_ids.includes(item.id)} onChange={(event) => group.setData('student_ids', event.target.checked ? [...group.data.student_ids, item.id] : group.data.student_ids.filter((id) => id !== item.id))} />{item.name} · {item.code}</label>)}</fieldset>
                        {group.errors.student_ids && <small role="alert">{group.errors.student_ids}</small>}
                        <div className="master-actions"><button className="primary" disabled={group.processing}>{editingGroup ? 'Simpan kelompok' : 'Tambah kelompok'}</button>{editingGroup && <button type="button" onClick={() => { group.reset(); setEditingGroup(null); }}>Batal</button>}</div>
                    </form>
                    <div className="master-list">{groups.map((item) => <article className="master-row" key={item.id}><div><strong>{item.name}</strong><br /><span className="muted">{item.archived_at ? 'Diarsipkan' : `${item.student_ids.length} anggota`}</span></div>{!item.archived_at && <div className="master-actions"><button type="button" onClick={() => { setEditingGroup(item.id); group.setData({ name: item.name, student_ids: item.student_ids }); }}>Ubah</button><button type="button" onClick={() => router.post(`/groups/${item.id}/archive`, {}, { preserveScroll: true })}>Arsipkan</button></div>}</article>)}</div>
                </section>
                <section id="activities-section" hidden={section !== 'activities'} className="master-card" aria-labelledby="activities-heading"><h2 id="activities-heading">Jenis kegiatan</h2>
                    <form className="master-form" onSubmit={submitActivity}><h3>{editingActivity ? 'Ubah kegiatan' : 'Tambah kegiatan'}</h3>
                        <label>Nama kegiatan<input value={activity.data.name} required maxLength={160} onChange={(event) => activity.setData('name', event.target.value)} />{activity.errors.name && <small role="alert">{activity.errors.name}</small>}</label>
                        <label className="master-inline"><input type="checkbox" checked={activity.data.counts_toward_progress} onChange={(event) => activity.setData('counts_toward_progress', event.target.checked)} />Hitung dalam progres hafalan</label>
                        <div className="master-actions"><button className="primary" disabled={activity.processing}>{editingActivity ? 'Simpan kegiatan' : 'Tambah kegiatan'}</button>{editingActivity && <button type="button" onClick={() => { activity.reset(); setEditingActivity(null); }}>Batal</button>}</div>
                    </form>
                    <div className="master-list">{activities.map((item) => <article className="master-row" key={item.id}><div><strong>{item.name}</strong><br /><span className="muted">{item.archived_at ? 'Diarsipkan · ' : ''}{item.counts_toward_progress ? 'Menghitung progres' : 'Tidak menghitung progres'}</span></div>{!item.archived_at && <div className="master-actions"><button type="button" onClick={() => { setEditingActivity(item.id); activity.setData({ name: item.name, counts_toward_progress: Boolean(item.counts_toward_progress) }); }}>Ubah</button><button type="button" onClick={() => router.post(`/activity-types/${item.id}/archive`, {}, { preserveScroll: true })}>Arsipkan</button></div>}</article>)}</div>
                </section>
            </div>
        </main>
    </div>;
}
