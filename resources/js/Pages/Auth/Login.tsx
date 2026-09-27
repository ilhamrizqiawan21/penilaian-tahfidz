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
            <section className="login-card" aria-labelledby="login-title">
                <div className="login-brand-row">
                    <span className="login-brand-mark" aria-hidden="true">ت</span>
                    <span>Penilaian Tahfidz</span>
                </div>
                <div className="login-card-header">
                    <p className="eyebrow">Ruang kerja guru</p>
                    <h2 id="login-title">Masuk untuk melanjutkan</h2>
                    <p className="login-description">Catat dan pantau hafalan santri dengan tenang.</p>
                </div>
                <form onSubmit={submit} aria-busy={form.processing}>
                    <div className="login-field">
                        <label htmlFor="email">Email</label>
                        <input id="email" name="email" type="email" autoComplete="username" required maxLength={255}
                            value={form.data.email} onChange={(e) => form.setData('email', e.target.value)}
                            aria-invalid={!!form.errors.email} aria-describedby={form.errors.email ? 'login-error' : undefined} />
                    </div>
                    <div className="login-field">
                        <label htmlFor="password">Kata sandi</label>
                        <input id="password" name="password" type="password" autoComplete="current-password" required maxLength={1024}
                            value={form.data.password} onChange={(e) => form.setData('password', e.target.value)}
                            aria-invalid={!!form.errors.password} aria-describedby={form.errors.password ? 'login-error' : undefined} />
                    </div>
                    {(form.errors.email || form.errors.password) && (
                        <p id="login-error" className="error" role="alert" tabIndex={-1} ref={errorRef}>{form.errors.email || form.errors.password}</p>
                    )}
                    <button className="primary" type="submit" disabled={form.processing}>{form.processing ? 'Sedang masuk…' : 'Masuk'}</button>
                </form>
                <p className="privacy-note"><span aria-hidden="true">⌁</span> Keluar setelah memakai perangkat bersama.</p>
            </section>
        </main>
    );
}
