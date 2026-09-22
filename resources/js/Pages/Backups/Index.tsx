import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

type Backup = { id: string; started_at: string; checksum: string; manifest: string | { table_counts: Record<string, number> }; pre_restore_backup_id: string | null };
type Props = { backups: Backup[]; flash?: { success?: string }; errors?: Record<string, string> };

export default function BackupIndex({ backups }: Props) {
    const { flash, errors } = usePage<Props>().props;
    const [password, setPassword] = useState('');
    const [confirmation, setConfirmation] = useState('');
    const [restorePassword, setRestorePassword] = useState('');
    const [archive, setArchive] = useState<File | null>(null);
    const [busy, setBusy] = useState(false);

    function create(event: FormEvent) {
        event.preventDefault();
        setBusy(true);
        router.post('/backups', { password, password_confirmation: confirmation }, {
            onFinish: () => { setBusy(false); setPassword(''); setConfirmation(''); },
        });
    }

    function preview(event: FormEvent) {
        event.preventDefault();
        if (!archive) return;
        setBusy(true);
        router.post('/backups/restore/preview', { archive, password: restorePassword }, {
            forceFormData: true,
            onFinish: () => { setBusy(false); setRestorePassword(''); },
        });
    }

    return <div className="master-shell"><Head title="Backup dan pemulihan" /><a className="skip-link" href="#backup-content">Lewati navigasi</a>
        <header className="mushaf-header"><Link href="/dashboard">← Ringkasan</Link><span>Penilaian Tahfidz · Backup</span></header>
        <main id="backup-content" className="master-main">
            <p className="eyebrow">Privasi dan pemulihan</p><h1>Backup dan pemulihan</h1>
            <p className="muted">Arsip berisi data aplikasi yang dienkripsi dengan kata sandi pilihan Anda. Simpan kata sandi dan berkas unduhan di tempat terpisah. Kata sandi tidak dapat dipulihkan.</p>
            <p className="muted">Akun login, kunci aplikasi, dan aset teks/font/layout mushaf tidak masuk arsip. Restore membutuhkan instalasi dengan referensi mushaf dan skema yang sama. Pada perangkat bersama, hapus berkas unduhan lokal setelah dipindah ke tempat aman dan keluar dari akun saat selesai.</p>
            {flash?.success && <p role="status" className="status">{flash.success}</p>}
            <section className="master-card"><h2>Buat backup</h2><form className="master-form" onSubmit={create}>
                <div className="master-fields"><label>Kata sandi arsip (minimal 12 karakter)<input type="password" autoComplete="new-password" minLength={12} required value={password} onChange={(event) => setPassword(event.target.value)} /></label>
                    <label>Ulangi kata sandi<input type="password" autoComplete="new-password" required value={confirmation} onChange={(event) => setConfirmation(event.target.value)} /></label></div>
                {errors?.password && <p role="alert">{errors.password}</p>}
                <div className="master-actions"><button className="primary" disabled={busy}>Buat backup terenkripsi</button></div>
            </form></section>
            <section className="master-card"><h2>Backup tersedia</h2>{backups.length === 0 && <p>Belum ada backup.</p>}
                <ul>{backups.map((item) => <li key={item.id}><a href={`/backups/${item.id}/download`}>Unduh {item.started_at?.slice(0, 16).replace('T', ' ')}</a> · checksum SHA-256 <code>{item.checksum}</code></li>)}</ul>
            </section>
            <section className="master-card"><h2>Pratinjau restore</h2><p>Unggah arsip untuk pemeriksaan. Data aktif belum diganti pada tahap ini.</p>
                <form className="master-form" onSubmit={preview}><div className="master-fields">
                    <label>Arsip .pthbackup<input type="file" accept=".pthbackup" required onChange={(event) => setArchive(event.target.files?.[0] ?? null)} /></label>
                    <label>Kata sandi arsip<input type="password" autoComplete="off" required value={restorePassword} onChange={(event) => setRestorePassword(event.target.value)} /></label>
                </div>{errors?.archive && <p role="alert">{errors.archive}</p>}{errors?.password && <p role="alert">{errors.password}</p>}
                    <div className="master-actions"><button className="primary" disabled={busy || !archive}>Periksa arsip</button></div>
                </form>
            </section>
        </main>
    </div>;
}
