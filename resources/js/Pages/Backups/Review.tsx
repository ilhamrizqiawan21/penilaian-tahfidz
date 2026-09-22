import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

type Props = {
    id: string;
    checksum: string;
    manifest: { app_version: string; created_at: string; source_refs: { datasets: { id: string; checksum: string }[]; editions: { id: string; checksum: string }[] } };
    impact: { current: Record<string, number>; incoming: Record<string, number> };
    errors?: Record<string, string>;
};

export default function BackupReview({ id, checksum, manifest, impact }: Props) {
    const { errors } = usePage<Props>().props;
    const [password, setPassword] = useState('');
    const [confirmation, setConfirmation] = useState('');
    const [busy, setBusy] = useState(false);
    const count = (rows: Record<string, number>) => Object.values(rows).reduce((total, value) => total + value, 0);

    function submit(event: FormEvent) {
        event.preventDefault();
        setBusy(true);
        router.post(`/backups/restores/${id}/confirm`, { password, confirmation }, { onFinish: () => { setBusy(false); setPassword(''); } });
    }

    return <div className="master-shell"><Head title="Tinjau restore" /><a className="skip-link" href="#restore-content">Lewati navigasi</a>
        <header className="mushaf-header"><Link href="/backups">← Backup</Link><span>Penilaian Tahfidz · Restore</span></header>
        <main id="restore-content" className="master-main"><p className="eyebrow">Konfirmasi dampak</p><h1>Tinjau restore</h1>
            <section className="master-card"><h2>Arsip terverifikasi</h2><p>Dibuat {manifest.created_at}; versi aplikasi {manifest.app_version}. Checksum arsip: <code>{checksum}</code></p>
                <p>Referensi sumber mushaf: {manifest.source_refs.datasets.length} dataset dan {manifest.source_refs.editions.length} edisi, dengan checksum yang cocok pada instalasi ini.</p>
                <p><strong>Data aktif akan diganti.</strong> Total baris aplikasi saat ini: {count(impact.current)}; dalam arsip: {count(impact.incoming)}. Akun login, aset mushaf, dan riwayat backup tetap ada.</p>
                <div className="report-table-wrap"><table className="report-table"><thead><tr><th>Data</th><th>Saat ini</th><th>Dalam arsip</th></tr></thead><tbody>{Object.keys(impact.incoming).map((table) => <tr key={table}><td>{table}</td><td>{impact.current[table]}</td><td>{impact.incoming[table]}</td></tr>)}</tbody></table></div>
            </section>
            <section className="master-card"><h2>Konfirmasi penggantian</h2><p>Backup terenkripsi keadaan sekarang dibuat lebih dahulu. Aplikasi akan masuk mode pemeliharaan selama penggantian data.</p>
                <form className="master-form" onSubmit={submit}><div className="master-fields">
                    <label>Kata sandi arsip<input type="password" autoComplete="off" required value={password} onChange={(event) => setPassword(event.target.value)} /></label>
                    <label>Ketik GANTI DATA<input required value={confirmation} onChange={(event) => setConfirmation(event.target.value)} /></label>
                </div>{errors?.password && <p role="alert">{errors.password}</p>}{errors?.confirmation && <p role="alert">{errors.confirmation}</p>}{errors?.archive && <p role="alert">{errors.archive}</p>}
                    <div className="master-actions"><button className="primary" disabled={busy || confirmation !== 'GANTI DATA'}>Ganti data dengan arsip</button><Link href="/backups">Batal</Link></div>
                </form>
            </section>
        </main>
    </div>;
}
