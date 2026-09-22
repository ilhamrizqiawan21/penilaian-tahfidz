import { Link, usePage } from '@inertiajs/react';
import { useRef, useState, type ReactNode } from 'react';

const sections = [
    { title: 'Ruang belajar', items: [['/dashboard', 'Ringkasan', '▦'], ['/assessments', 'Sesi penilaian', '✓'], ['/students', 'Santri dan kegiatan', '♧'], ['/reports', 'Progres dan laporan', '↗']] },
    { title: 'Persiapan', items: [['/programs', 'Program hafalan', '≡'], ['/rubrics', 'Rubrik penilaian', '☷']] },
    { title: 'Lainnya', items: [['/backups', 'Backup dan pemulihan', '↥'], ['/mushaf/prototype', 'Prototipe mushaf', '◇']] },
];

export default function Workspace({ children }: { children: ReactNode }) {
    const page = usePage<{ demoMode?: boolean }>();
    const { url } = page;
    const { demoMode } = page.props;
    const path = url.split('?')[0];
    const [openPath, setOpenPath] = useState<string | null>(null);
    const menuOpen = openPath === url;
    const toggle = useRef<HTMLButtonElement>(null);
    const current = sections.flatMap((section) => section.items).find(([href]) => path === href || path.startsWith(`${href}/`));

    return <div className="workspace">
        <a className="skip-link" href="#workspace-content">Lewati navigasi utama</a>
        <aside className="sidebar" onKeyDown={(event) => { if (event.key === 'Escape' && menuOpen) { setOpenPath(null); toggle.current?.focus(); } }}>
            <Link href="/dashboard" className="brand" aria-label="Penilaian Tahfidz — Ringkasan"><span className="brand-mark" aria-hidden="true">PT</span><span>Penilaian<br /><strong>Tahfidz</strong></span></Link>
            <button ref={toggle} type="button" className="menu-toggle" onClick={() => setOpenPath(menuOpen ? null : url)} aria-expanded={menuOpen} aria-controls="main-nav">{menuOpen ? 'Tutup menu' : 'Buka menu'}</button>
            <nav id="main-nav" className={menuOpen ? 'is-open' : ''} aria-label="Navigasi utama">
                {sections.map((section) => <div className="nav-section" key={section.title}><p className="nav-caption">{section.title}</p>{section.items.map(([href, label, icon]) => <Link key={href} href={href} className={current?.[0] === href ? 'nav-current' : ''} aria-current={current?.[0] === href ? 'page' : undefined} onClick={() => setOpenPath(null)}><span className="nav-icon" aria-hidden="true">{icon}</span>{label}</Link>)}</div>)}
            </nav>
            <div className="sidebar-footer">Ruang pribadi guru tahfidz<br /><span>Mendampingi setiap langkah.</span></div>
        </aside>
        <div className="main-wrap">
            <header className="topbar"><span>Ruang guru <span aria-hidden="true">/</span> <strong>{current?.[1] ?? 'Penilaian Tahfidz'}</strong>{demoMode && <span className="demo-badge">Mode demo · data sintetis</span>}</span><Link href="/logout" method="post" as="button" className="logout">Keluar</Link></header>
            <div id="workspace-content" tabIndex={-1}>{children}</div>
            <footer className="page-footer">Penilaian Tahfidz · Mendampingi proses, menjaga amanah.</footer>
        </div>
    </div>;
}
