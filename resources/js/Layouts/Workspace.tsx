import { Link, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { Icon, type IconName } from '../Components/Icon';

const sections: { title: string; items: [string, string, IconName][] }[] = [
    { title: 'Ruang belajar', items: [
        ['/dashboard', 'Hari ini', 'dashboard'],
        ['/assessments', 'Sesi penilaian', 'check'],
        ['/quran', 'Al-Qur’an', 'book'],
        ['/students', 'Santri dan kegiatan', 'users'],
        ['/reports', 'Progres dan laporan', 'trend'],
    ] },
    { title: 'Persiapan', items: [
        ['/programs', 'Program hafalan', 'checklist'],
        ['/rubrics', 'Rubrik penilaian', 'clipboard'],
        ['/backups', 'Backup dan pemulihan', 'archive'],
    ] },
];

const COLLAPSE_KEY = 'tahfidz-sidebar-collapsed';

export default function Workspace({ children }: { children: ReactNode }) {
    const page = usePage<{ demoMode?: boolean; auth?: { user?: { name: string; email: string } } }>();
    const { url } = page;
    const { demoMode, auth } = page.props;
    const path = url.split('?')[0];
    const [openPath, setOpenPath] = useState<string | null>(null);
    const [collapsed, setCollapsed] = useState(() => {
        try { return localStorage.getItem(COLLAPSE_KEY) === '1'; } catch { return false; }
    });
    const menuOpen = openPath === url;
    const toggle = useRef<HTMLButtonElement>(null);
    const current = sections.flatMap((section) => section.items).find(([href]) => path === href || path.startsWith(`${href}/`));

    useEffect(() => {
        try { localStorage.setItem(COLLAPSE_KEY, collapsed ? '1' : '0'); } catch { /* per-viewer convenience only */ }
    }, [collapsed]);

    return <div className={`workspace${collapsed ? ' is-sidebar-collapsed' : ''}`}>
        <a className="skip-link" href="#workspace-content">Lewati navigasi utama</a>
        <aside className={`sidebar${collapsed ? ' is-collapsed' : ''}`} onKeyDown={(event) => { if (event.key === 'Escape' && menuOpen) { setOpenPath(null); toggle.current?.focus(); } }}>
            <div className="sidebar-head">
                <Link href="/dashboard" className="brand" aria-label="Penilaian Tahfidz — Ringkasan"><span className="brand-mark" aria-hidden="true">PT</span><span>Penilaian<br /><strong>Tahfidz</strong></span></Link>
                <button type="button" className="sidebar-collapse-toggle" onClick={() => setCollapsed((value) => !value)} aria-expanded={!collapsed} aria-controls="main-nav" title={collapsed ? 'Perluas sidebar' : 'Ciutkan sidebar'}>
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M14 5l-6 7 6 7" /></svg>
                    <span className="sr-only">{collapsed ? 'Perluas sidebar' : 'Ciutkan sidebar'}</span>
                </button>
            </div>
            <button ref={toggle} type="button" className="menu-toggle" onClick={() => setOpenPath(menuOpen ? null : url)} aria-expanded={menuOpen} aria-controls="main-nav">{menuOpen ? 'Tutup menu' : 'Buka menu'}</button>
            <nav id="main-nav" className={menuOpen ? 'is-open' : ''} aria-label="Navigasi utama">
                {sections.map((section) => <div className="nav-section" key={section.title}><p className="nav-caption">{section.title}</p>{section.items.map(([href, label, icon]) => <Link key={href} href={href} className={current?.[0] === href ? 'nav-current' : ''} aria-current={current?.[0] === href ? 'page' : undefined} aria-label={label} title={collapsed ? label : undefined} onClick={() => setOpenPath(null)}><span className="nav-icon"><Icon name={icon} /></span><span className="nav-label">{label}</span></Link>)}</div>)}
            </nav>
            <div className="sidebar-footer">
                {auth?.user ? <><strong>{auth.user.name}</strong><span>{auth.user.email}</span></> : <><strong>Ruang pribadi guru tahfidz</strong><span>Mendampingi setiap langkah.</span></>}
            </div>
        </aside>
        <div className="main-wrap">
            <header className="topbar"><span>Ruang guru <span aria-hidden="true">/</span> <strong>{current?.[1] ?? 'Penilaian Tahfidz'}</strong>{demoMode && <span className="demo-badge">Mode demo · data sintetis</span>}</span><Link href="/logout" method="post" as="button" className="logout">Keluar</Link></header>
            <div id="workspace-content" tabIndex={-1}>{children}</div>
            <footer className="page-footer">Penilaian Tahfidz · Mendampingi proses, menjaga amanah.</footer>
        </div>
    </div>;
}
