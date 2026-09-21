import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

type Props = { auth: { user: { name: string; email: string; timezone: string } } };
const modules = ['Penilaian', 'Laporan', 'Pengaturan & backup'];

export default function Dashboard() {
    const { auth } = usePage<Props>().props;
    const [menuOpen, setMenuOpen] = useState(false);

    return (
        <div className="workspace">
            <Head title="Ringkasan" />
            <a className="skip-link" href="#main">Lewati navigasi</a>
            <aside className="sidebar">
                <div className="brand"><span className="brand-mark" aria-hidden="true">PT</span><span>Penilaian<br /><strong>Tahfidz</strong></span></div>
                <button className="menu-toggle" onClick={() => setMenuOpen(!menuOpen)} aria-expanded={menuOpen} aria-controls="main-nav">{menuOpen ? 'Tutup menu' : 'Buka menu'}</button>
                <nav id="main-nav" className={menuOpen ? 'is-open' : ''} aria-label="Navigasi utama">
                    <Link className="nav-current" href="/dashboard" aria-current="page" onClick={() => setMenuOpen(false)}>Ringkasan</Link>
                    <Link href="/students" onClick={() => setMenuOpen(false)}>Santri dan kegiatan</Link>
                    <Link href="/programs" onClick={() => setMenuOpen(false)}>Program hafalan</Link>
                    <Link href="/rubrics" onClick={() => setMenuOpen(false)}>Rubrik penilaian</Link>
                    <Link href="/mushaf/prototype" onClick={() => setMenuOpen(false)}>Prototipe mushaf</Link>
                    <p className="nav-caption">Dalam pengembangan</p>
                    {modules.map((label) => <span key={label} className="nav-pending">{label}</span>)}
                </nav>
                <div className="sidebar-footer">Ruang pribadi guru tahfidz</div>
            </aside>
            <div className="main-wrap">
                <header className="topbar"><span>Ruang guru</span><Link href="/logout" method="post" as="button" className="logout">Keluar</Link></header>
                <main id="main" tabIndex={-1} className="dashboard">
                    <p className="eyebrow">Ringkasan</p>
                    <h1>Assalamu’alaikum, {auth.user.name}.</h1>
                    <p className="muted">Selamat datang di ruang pendampingan hafalan Anda.</p>
                    <section className="welcome-panel" aria-labelledby="welcome-title">
                        <span className="status">Tahap persiapan</span>
                        <h2 id="welcome-title">Ruang Anda sudah siap diakses</h2>
                        <p>Akun pribadi sudah aktif. Santri, kelompok, kegiatan, dan rubrik dapat dikelola. Penyusunan program tersedia setelah referensi mushaf tervalidasi; sesi penilaian menyusul.</p>
                        <p>Belum ada data santri atau hasil penilaian yang ditampilkan.</p>
                    </section>
                    <section className="account-panel" aria-labelledby="account-title">
                        <h2 id="account-title">Akun pemilik</h2>
                        <dl><div><dt>Email</dt><dd>{auth.user.email}</dd></div><div><dt>Zona waktu</dt><dd>{auth.user.timezone}</dd></div></dl>
                    </section>
                </main>
                <footer className="page-footer">Penilaian Tahfidz · Mendampingi proses, menjaga amanah.</footer>
            </div>
        </div>
    );
}
