import { Head, Link, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

type Option = { id: string; name: string; code?: string; name_snapshot?: string; version?: number };
type Row = { id: string; student_id: string; student_name: string; student_code: string; assessed_at: string; activity_name_snapshot: string; program_name: string | null; program_version: number | null; rubric_name: string; rubric_version: number; final_score: string; passed: number | boolean; grade_label_snapshot: string | null };
type Filters = { student_id?: string; program_id?: string; activity_type_id?: string; rubric_version_id?: string; from?: string; to?: string };
type Props = {
    summary: { sessions: number; passed: number; murajaah: number };
    rows: { data: Row[]; current_page: number; last_page: number; prev_page_url: string | null; next_page_url: string | null };
    filters: Filters;
    students: Option[]; programs: Option[]; activities: Option[]; rubrics: Option[];
};

export default function ReportsIndex({ summary, rows, filters, students, programs, activities, rubrics }: Props) {
    const [form, setForm] = useState<Filters>(filters);
    function submit(event: FormEvent) {
        event.preventDefault();
        router.get('/reports', form as Record<string, string>, { preserveState: true });
    }
    const query = new URLSearchParams(Object.entries(filters).filter(([, value]) => Boolean(value)) as [string, string][]).toString();

    return <div className="master-shell"><Head title="Laporan penilaian" /><a className="skip-link" href="#report-content">Lewati navigasi</a>
        <header className="mushaf-header"><Link href="/dashboard">← Ringkasan</Link><span>Penilaian Tahfidz · Laporan</span></header>
        <main id="report-content" className="master-main">
            <p className="eyebrow">Progres dan laporan</p><h1>Laporan penilaian</h1>
            <p className="muted">Hanya hasil final yang masih aktif. Nilai selalu disertai nama dan versi rubrik; tidak ada rata-rata lintas rubrik.</p>
            <section className="master-card report-filters"><h2>Filter</h2><form className="master-form" onSubmit={submit}>
                <div className="master-fields">
                    <label>Santri<select value={form.student_id ?? ''} onChange={(event) => setForm({ ...form, student_id: event.target.value })}><option value="">Semua santri</option>{students.map((item) => <option value={item.id} key={item.id}>{item.name} · {item.code}</option>)}</select></label>
                    <label>Program<select value={form.program_id ?? ''} onChange={(event) => setForm({ ...form, program_id: event.target.value })}><option value="">Semua program</option>{programs.map((item) => <option value={item.id} key={item.id}>{item.name}</option>)}</select></label>
                    <label>Kegiatan<select value={form.activity_type_id ?? ''} onChange={(event) => setForm({ ...form, activity_type_id: event.target.value })}><option value="">Semua kegiatan</option>{activities.map((item) => <option value={item.id} key={item.id}>{item.name}</option>)}</select></label>
                    <label>Rubrik<select value={form.rubric_version_id ?? ''} onChange={(event) => setForm({ ...form, rubric_version_id: event.target.value })}><option value="">Semua rubrik</option>{rubrics.map((item) => <option value={item.id} key={item.id}>{item.name_snapshot} v{item.version}</option>)}</select></label>
                    <label>Dari tanggal<input type="date" value={form.from ?? ''} onChange={(event) => setForm({ ...form, from: event.target.value })} /></label>
                    <label>Sampai tanggal<input type="date" min={form.from} value={form.to ?? ''} onChange={(event) => setForm({ ...form, to: event.target.value })} /></label>
                </div><div className="master-actions"><button className="primary">Terapkan filter</button><Link href="/reports">Hapus filter</Link><a href={`/reports/export${query ? `?${query}` : ''}`}>Unduh CSV sesuai filter</a><button type="button" onClick={() => window.print()}>Cetak tampilan</button></div>
            </form></section>
            <section className="master-card"><h2>Ringkasan sesuai filter</h2><dl className="report-stats"><div><dt>Sesi final</dt><dd>{summary.sessions}</dd></div><div><dt>Lulus</dt><dd>{summary.passed}</dd></div><div><dt>Murajaah</dt><dd>{summary.murajaah}</dd></div></dl><p className="muted">Murajaah adalah kegiatan yang tidak menambah progres hafalan unik.</p></section>
            <section className="master-card"><h2>Riwayat hasil</h2>{rows.data.length === 0 && <p>Belum ada hasil sesuai filter.</p>}
                <div className="report-table-wrap"><table className="report-table"><thead><tr><th>Tanggal</th><th>Santri</th><th>Program</th><th>Kegiatan</th><th>Rubrik</th><th>Hasil</th></tr></thead><tbody>{rows.data.map((row) => <tr key={row.id}><td>{row.assessed_at?.slice(0, 10)}</td><td><Link href={`/reports/students/${row.student_id}`}>{row.student_name}</Link><br /><small>{row.student_code}</small></td><td>{row.program_name ? `${row.program_name} v${row.program_version}` : 'Tanpa program'}</td><td>{row.activity_name_snapshot}</td><td>{row.rubric_name} v{row.rubric_version}</td><td>{row.final_score} · {row.passed ? 'Lulus' : 'Belum lulus'}{row.grade_label_snapshot ? ` · ${row.grade_label_snapshot}` : ''}</td></tr>)}</tbody></table></div>
                <div className="master-actions report-pagination">{rows.prev_page_url && <Link href={rows.prev_page_url}>Sebelumnya</Link>}<span>Halaman {rows.current_page} dari {rows.last_page}</span>{rows.next_page_url && <Link href={rows.next_page_url}>Berikutnya</Link>}</div>
            </section>
        </main>
    </div>;
}
