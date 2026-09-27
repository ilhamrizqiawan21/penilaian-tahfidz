import { Head, Link, usePage } from '@inertiajs/react';

type Session = {
    id: string; student_name: string; student_code: string; activity: string | null;
    status: 'draft' | 'final' | 'void' | 'cancelled'; assessment_id: string | null;
    score: string | null; passed: boolean | number | null;
};
type Props = {
    auth: { user: { name: string; email: string; timezone: string } };
    summary: { students: number; active_drafts: number; final_assessments: number; published_programs: number };
    setup: { students_ready: boolean; activities_ready: boolean; rubrics_ready: boolean; reference_ready: boolean };
    recent_sessions: Session[];
};

const statusLabel = (status: Session['status']) => ({ draft: 'Draf', final: 'Final', void: 'Dibatalkan', cancelled: 'Tidak dilanjutkan' })[status];

export default function Dashboard() {
    const { auth, summary, setup, recent_sessions: recentSessions } = usePage<Props>().props;
    const ready = Object.values(setup).filter(Boolean).length;
    const primaryHref = summary.active_drafts > 0 ? '/assessments' : setup.students_ready && setup.activities_ready && setup.rubrics_ready && setup.reference_ready ? '/assessments' : !setup.students_ready || !setup.activities_ready ? '/students' : !setup.rubrics_ready ? '/rubrics' : '/quran';
    const primaryLabel = summary.active_drafts > 0 ? `Lanjutkan ${summary.active_drafts} draf` : ready === 4 ? 'Mulai penilaian' : 'Lanjutkan persiapan';

    return <main id="main" tabIndex={-1} className="dashboard dashboard-home">
        <Head title="Hari ini" />
        <header className="dashboard-heading">
            <div><p className="eyebrow">Ruang pendampingan hafalan</p><h1>Assalamu’alaikum, {auth.user.name}.</h1><p className="muted">Berikut keadaan ruang belajar Anda hari ini.</p></div>
            <Link href={primaryHref} className="primary action-link">{primaryLabel}<span aria-hidden="true">→</span></Link>
        </header>
        <section className="dashboard-stats" aria-label="Ringkasan kegiatan">
            <article><span>Santri aktif</span><strong>{summary.students}</strong><Link href="/students">Lihat santri</Link></article>
            <article><span>Draf berjalan</span><strong>{summary.active_drafts}</strong><Link href="/assessments">Buka sesi</Link></article>
            <article><span>Hasil tersimpan</span><strong>{summary.final_assessments}</strong><Link href="/reports">Lihat laporan</Link></article>
            <article><span>Program terbit</span><strong>{summary.published_programs}</strong><Link href="/programs">Kelola program</Link></article>
        </section>
        <div className="dashboard-grid">
            <section className="dashboard-panel dashboard-recent" aria-labelledby="recent-title">
                <div className="panel-heading"><div><p className="eyebrow">Aktivitas terbaru</p><h2 id="recent-title">Sesi santri</h2></div><Link href="/assessments">Semua sesi</Link></div>
                {recentSessions.length === 0 ? <div className="dashboard-empty"><span aria-hidden="true">۝</span><p>Belum ada sesi penilaian.</p><Link href="/assessments">Buat sesi pertama</Link></div> : <div className="session-list">
                    {recentSessions.map((session) => <article key={session.id}>
                        <span className={`session-status is-${session.status}`}>{statusLabel(session.status)}</span>
                        <div><strong>{session.student_name}</strong><p>{session.student_code}{session.activity ? ` · ${session.activity}` : ''}</p></div>
                        {session.status === 'final' && session.score !== null && <span className="session-score">{session.score}</span>}
                        {session.assessment_id && <Link href={`/assessments/${session.assessment_id}/work`} aria-label={`Buka sesi ${session.student_name}`}>→</Link>}
                    </article>)}
                </div>}
            </section>
            <aside className="dashboard-panel setup-panel" aria-labelledby="setup-title">
                <div className="panel-heading"><div><p className="eyebrow">Kesiapan ruang kerja</p><h2 id="setup-title">{ready} dari 4 siap</h2></div><span className="setup-count">{Math.round((ready / 4) * 100)}%</span></div>
                <div className="setup-progress" aria-hidden="true"><span style={{ width: `${(ready / 4) * 100}%` }} /></div>
                <ul>
                    <li className={setup.students_ready && setup.activities_ready ? 'is-ready' : ''}><span>{setup.students_ready && setup.activities_ready ? '✓' : '1'}</span><div><strong>Santri & kegiatan</strong><small>{setup.students_ready && setup.activities_ready ? 'Sudah siap' : 'Lengkapi data dasar'}</small></div><Link href="/students">→</Link></li>
                    <li className={setup.rubrics_ready ? 'is-ready' : ''}><span>{setup.rubrics_ready ? '✓' : '2'}</span><div><strong>Rubrik terbit</strong><small>{setup.rubrics_ready ? 'Sudah siap' : 'Terbitkan satu rubrik'}</small></div><Link href="/rubrics">→</Link></li>
                    <li className={setup.reference_ready ? 'is-ready' : ''}><span>{setup.reference_ready ? '✓' : '3'}</span><div><strong>Referensi Al-Qur’an</strong><small>{setup.reference_ready ? 'Sumber aktif tersedia' : 'Belum ada sumber aktif'}</small></div><Link href="/quran">→</Link></li>
                </ul>
            </aside>
        </div>
        <section className="dashboard-shortcuts" aria-labelledby="shortcut-title"><h2 id="shortcut-title">Akses cepat</h2><div>
            <Link href="/quran"><span aria-hidden="true">◇</span><strong>Buka Al-Qur’an</strong><small>Periksa surah dan ayat</small></Link>
            <Link href="/programs"><span aria-hidden="true">≡</span><strong>Program hafalan</strong><small>Atur target santri</small></Link>
            <Link href="/reports"><span aria-hidden="true">↗</span><strong>Progres santri</strong><small>Lihat hasil dan riwayat</small></Link>
        </div></section>
    </main>;
}
