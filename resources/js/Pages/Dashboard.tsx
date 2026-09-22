import { Head, Link, usePage } from '@inertiajs/react';

type Props = { auth: { user: { name: string; email: string; timezone: string } } };

export default function Dashboard() {
    const { auth } = usePage<Props>().props;
    return <main id="main" tabIndex={-1} className="dashboard">
        <Head title="Ringkasan" />
        <p className="eyebrow">Ruang pendampingan hafalan</p>
        <h1>Assalamu’alaikum, {auth.user.name}.</h1>
        <p className="muted">Mulai dari santri, dampingi setorannya, lalu lihat perkembangannya.</p>
        <section className="quick-start" aria-labelledby="start-title">
            <div><p className="eyebrow">Kegiatan hari ini</p><h2 id="start-title">Siap mendampingi hafalan?</h2><p>Buka sesi untuk melanjutkan draf atau menyiapkan penilaian berikutnya.</p><Link href="/assessments" className="primary action-link">Buka sesi penilaian <span aria-hidden="true">↗</span></Link></div>
            <div className="start-detail"><span aria-hidden="true">01 — 02 — 03</span><p>Pilih santri.<br />Catat bacaan.<br /><strong>Tinjau hasilnya.</strong></p></div>
        </section>
        <section className="workflow-section" aria-labelledby="workflow-title"><h2 id="workflow-title">Langkah berikutnya</h2><div className="workflow-grid">
            <Link href="/students" className="workflow-link"><span className="step-number">01</span><h3>Siapkan santri</h3><p>Tambah santri dan atur jenis kegiatan. Kelompok boleh menyusul.</p><span className="link-label">Kelola santri <span aria-hidden="true">→</span></span></Link>
            <Link href="/rubrics" className="workflow-link"><span className="step-number">02</span><h3>Tentukan penilaian</h3><p>Susun kriteria, bobot, dan aturan kesalahan pada rubrik.</p><span className="link-label">Buka rubrik <span aria-hidden="true">→</span></span></Link>
            <Link href="/reports" className="workflow-link"><span className="step-number">03</span><h3>Lihat perkembangan</h3><p>Tinjau riwayat setoran dan progres setiap santri.</p><span className="link-label">Lihat laporan <span aria-hidden="true">→</span></span></Link>
        </div></section>
        <section className="welcome-panel" aria-labelledby="welcome-title"><h2 id="welcome-title">Persiapan referensi mushaf</h2><p>Program dan sesi baru memerlukan referensi mushaf yang tervalidasi dan aktif. Status ketersediaannya dapat dilihat di halaman program atau sesi. Data santri dan rubrik bisa disiapkan lebih dahulu.</p></section>
        <section className="account-panel" aria-labelledby="account-title"><h2 id="account-title">Akun pemilik</h2><dl><div><dt>Email</dt><dd>{auth.user.email}</dd></div><div><dt>Zona waktu</dt><dd>{auth.user.timezone}</dd></div></dl></section>
    </main>;
}
