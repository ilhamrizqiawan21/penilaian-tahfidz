import { Head, useForm } from '@inertiajs/react';
import { useEffect, useRef, type FormEvent } from 'react';

export default function Login() {
    const form = useForm({ email: '', password: '' });
    const errorRef = useRef<HTMLParagraphElement>(null);
    useEffect(() => {
        if (form.errors.email || form.errors.password) errorRef.current?.focus();
    }, [form.errors.email, form.errors.password]);

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/login', { onFinish: () => form.reset('password') });
    }

    return (
        <main className="login-shell">
            <Head title="Masuk" />
            <section className="intro" aria-labelledby="brand-title">
                <div className="brand-mark" aria-hidden="true">PT</div>
                <p className="eyebrow">Ruang pribadi guru</p>
                <h1 id="brand-title">Penilaian<br />Tahfidz</h1>
                <p>Tempat mendampingi hafalan, mencatat perkembangan, dan menjaga setiap proses belajar.</p>
                <div className="intro-note">Satu langkah kecil, hafalan yang terus terjaga.</div>
            </section>
            <section className="login-card" aria-labelledby="login-title">
                <p className="eyebrow">Selamat datang kembali</p>
                <h2 id="login-title">Masuk ke ruang Anda</h2>
                <p className="muted">Gunakan akun pemilik untuk melanjutkan.</p>
                <form onSubmit={submit} aria-busy={form.processing}>
                    <label htmlFor="email">Email</label>
                    <input id="email" name="email" type="email" autoComplete="username" required maxLength={255}
                        value={form.data.email} onChange={(e) => form.setData('email', e.target.value)}
                        aria-invalid={!!form.errors.email} aria-describedby={form.errors.email ? 'login-error' : undefined} />
                    <label htmlFor="password">Kata sandi</label>
                    <input id="password" name="password" type="password" autoComplete="current-password" required maxLength={1024}
                        value={form.data.password} onChange={(e) => form.setData('password', e.target.value)}
                        aria-invalid={!!form.errors.password} aria-describedby={form.errors.password ? 'login-error' : undefined} />
                    {(form.errors.email || form.errors.password) && (
                        <p id="login-error" className="error" role="alert" tabIndex={-1} ref={errorRef}>{form.errors.email || form.errors.password}</p>
                    )}
                    <button className="primary" type="submit" disabled={form.processing}>{form.processing ? 'Sedang masuk…' : 'Masuk'}</button>
                </form>
                <p className="privacy-note">Akses khusus pemilik. Keluar dari akun setelah memakai perangkat bersama.</p>
            </section>
        </main>
    );
}
