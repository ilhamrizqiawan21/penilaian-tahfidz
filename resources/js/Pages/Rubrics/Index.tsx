import { Head, Link, router, useForm } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';

type Rule = { id?: string; name: string; severity_label: string | null; deduction_points: string };
type Criterion = { id?: string; name: string; description: string; method: 'direct' | 'deduction'; min_score: string; max_score: string; weight: string; min_pass_normalized: string | null; rules: Rule[] };
type Band = { label: string; lower_bound: string; upper_bound: string };
type Version = { id: string; version: number; name: string; status: string; pass_threshold: string; criteria: Criterion[]; bands: Band[] };
type Rubric = { id: string; name: string; archived_at: string | null; versions: Version[] };
type Props = { rubrics: Rubric[] };
type Payload = { name: string; pass_threshold: string; criteria: Criterion[]; bands: Band[] };
type Event = { id: string; kind: 'penalty' | 'note'; criterion_id?: string; rule_id?: string; note?: string; active: boolean };
type Preview = { complete: boolean; display_score: string | null; unrounded_score: string | null; passed: boolean | null; grade: string | null; criteria: { id: string; name: string; computed_raw: string | null; effective_raw: string | null; normalized: string | null; weighted: string | null; deductions: { event_id: string; name: string; points: string }[] }[] };

const newCriterion = (): Criterion => ({ name: '', description: '', method: 'direct', min_score: '0', max_score: '100', weight: '100', min_pass_normalized: null, rules: [] });
const newRule = (): Rule => ({ name: '', severity_label: null, deduction_points: '5' });
const token = () => { const value = document.cookie.split('; ').find((part) => part.startsWith('XSRF-TOKEN='))?.split('=')[1]; return value ? decodeURIComponent(value) : ''; };

export default function RubricsIndex({ rubrics }: Props) {
    const form = useForm<Payload>({ name: '', pass_threshold: '80', criteria: [newCriterion()], bands: [] });
    const [section, setSection] = useState<'list' | 'builder' | 'simulator'>('list');
    const [editing, setEditing] = useState<{ rubric: string; version: string } | null>(null);
    const [selected, setSelected] = useState<{ rubric: string; version: Version } | null>(null);
    const [direct, setDirect] = useState<Record<string, string>>({});
    const [overrides, setOverrides] = useState<Record<string, { raw: string; reason: string }>>({});
    const [events, setEvents] = useState<Event[]>([]);
    const [preview, setPreview] = useState<Preview | null>(null);
    const [previewError, setPreviewError] = useState('');

    function criterion(index: number, change: Partial<Criterion>) {
        form.setData('criteria', form.data.criteria.map((item, i) => i === index ? { ...item, ...change } : item));
    }

    function rule(index: number, ruleIndex: number, change: Partial<Rule>) {
        criterion(index, { rules: form.data.criteria[index].rules.map((item, i) => i === ruleIndex ? { ...item, ...change } : item) });
    }

    function band(index: number, change: Partial<Band>) {
        form.setData('bands', form.data.bands.map((item, i) => i === index ? { ...item, ...change } : item));
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setEditing(null);
                setSection('list');
            },
        };
        if (editing) form.put(`/rubrics/${editing.rubric}/versions/${editing.version}`, options);
        else form.post('/rubrics', options);
    }

    function edit(rubric: Rubric, version: Version) {
        setEditing({ rubric: rubric.id, version: version.id });
        form.setData({
            name: version.name,
            pass_threshold: version.pass_threshold,
            criteria: version.criteria.map((item) => ({
                ...item,
                description: item.description ?? '',
                min_pass_normalized: item.min_pass_normalized ?? null,
                rules: item.rules.map((entry) => ({ ...entry, severity_label: entry.severity_label ?? null })),
            })),
            bands: version.bands,
        });
        setSection('builder');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function select(rubric: Rubric, version: Version) {
        setSelected({ rubric: rubric.id, version });
        setDirect({});
        setOverrides({});
        setEvents([]);
        setPreview(null);
        setPreviewError('');
        setSection('simulator');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    async function calculate(final: boolean) {
        if (!selected) return;
        setPreviewError('');
        try {
            const response = await fetch(`/rubrics/${selected.rubric}/versions/${selected.version.id}/preview`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-XSRF-TOKEN': token() },
                body: JSON.stringify({ direct, overrides, events, final }),
            });
            if (response.ok) {
                setPreview(await response.json());
                return;
            }
            const data = await response.json().catch(() => null) as { errors?: Record<string, string[]> } | null;
            setPreview(null);
            setPreviewError(Object.values(data?.errors ?? {}).flat().join(' ') || 'Pratinjau belum dapat dihitung.');
        } catch {
            setPreview(null);
            setPreviewError('Gagal menghubungkan server untuk kalkulasi nilai.');
        }
    }

    // Weight sum calculation
    const totalWeight = useMemo(() => {
        return form.data.criteria.reduce((sum, item) => sum + (Number(item.weight) || 0), 0);
    }, [form.data.criteria]);

    // Presets for quick rubric creation
    function applyTahfidzStandardPreset() {
        form.setData({
            ...form.data,
            name: form.data.name || 'Rubrik Tahfidz 3 Kriteria',
            pass_threshold: '75',
            criteria: [
                {
                    name: 'Tahfidz & Kelancaran',
                    description: 'Kelancaran hafalan dan ingatan lafazh',
                    method: 'deduction',
                    min_score: '0',
                    max_score: '100',
                    weight: '40',
                    min_pass_normalized: '60',
                    rules: [
                        { name: 'Tawaqquf / Lupa (Ditegur)', severity_label: 'Sedang', deduction_points: '2' },
                        { name: 'Salah Huruf / Baris (Terkoreksi)', severity_label: 'Ringan', deduction_points: '1' },
                        { name: 'Terhenti Total / Ganti Ayat', severity_label: 'Berat', deduction_points: '5' },
                    ],
                },
                {
                    name: 'Tajwid',
                    description: 'Kaidah hukum bacaan nun, mim, mad, dan ghunnah',
                    method: 'deduction',
                    min_score: '0',
                    max_score: '100',
                    weight: '30',
                    min_pass_normalized: '60',
                    rules: [
                        { name: 'Kurang Ghunnah / Dengung', severity_label: 'Ringan', deduction_points: '1' },
                        { name: 'Panjang Mad Tidak Sesuai', severity_label: 'Ringan', deduction_points: '1' },
                        { name: 'Idgham / Ikhfa Tertukar', severity_label: 'Sedang', deduction_points: '2' },
                    ],
                },
                {
                    name: 'Makharijul Huruf & Fashahah',
                    description: 'Ketepatan pengucapan huruf, waqaf, dan ibtida',
                    method: 'deduction',
                    min_score: '0',
                    max_score: '100',
                    weight: '30',
                    min_pass_normalized: '60',
                    rules: [
                        { name: 'Makhraj Huruf Kurang Tepat', severity_label: 'Ringan', deduction_points: '1' },
                        { name: 'Waqaf / Ibtida Salah Tempat', severity_label: 'Ringan', deduction_points: '1' },
                    ],
                },
            ],
            bands: [
                { label: 'Rasib (D)', lower_bound: '0', upper_bound: '59.99' },
                { label: 'Maqbul (C)', lower_bound: '60', upper_bound: '69.99' },
                { label: 'Jayyid (B)', lower_bound: '70', upper_bound: '79.99' },
                { label: 'Jayyid Jiddan (B+)', lower_bound: '80', upper_bound: '89.99' },
                { label: 'Mumtaz (A)', lower_bound: '90', upper_bound: '100' },
            ],
        });
    }

    function applyDirectScorePreset() {
        form.setData({
            ...form.data,
            name: form.data.name || 'Rubrik Nilai Langsung Sederhana',
            pass_threshold: '70',
            criteria: [
                {
                    name: 'Kelancaran Hafalan',
                    description: 'Nilai kelancaran setoran santri (0-100)',
                    method: 'direct',
                    min_score: '0',
                    max_score: '100',
                    weight: '50',
                    min_pass_normalized: null,
                    rules: [],
                },
                {
                    name: 'Kualitas Tajwid & Makhraj',
                    description: 'Nilai ketepatan tajwid dan makhraj (0-100)',
                    method: 'direct',
                    min_score: '0',
                    max_score: '100',
                    weight: '50',
                    min_pass_normalized: null,
                    rules: [],
                },
            ],
        });
    }

    function applyIslamicBandsPreset() {
        form.setData('bands', [
            { label: 'Rasib (Kurang)', lower_bound: '0', upper_bound: '59.99' },
            { label: 'Maqbul (Cukup)', lower_bound: '60', upper_bound: '69.99' },
            { label: 'Jayyid (Baik)', lower_bound: '70', upper_bound: '79.99' },
            { label: 'Jayyid Jiddan (Sangat Baik)', lower_bound: '80', upper_bound: '89.99' },
            { label: 'Mumtaz (Istimewa)', lower_bound: '90', upper_bound: '100' },
        ]);
    }

    return (
        <div className="master-shell">
            <Head title="Rubrik dan Nilai" />
            <a className="skip-link" href="#rubric-content">Lewati navigasi</a>
            <header className="mushaf-header">
                <Link href="/dashboard">← Ringkasan</Link>
                <span>Penilaian Tahfidz · Pengaturan Rubrik & Nilai</span>
            </header>

            <main id="rubric-content" className="master-main">
                <p className="eyebrow">Rubrik dan Skema Penilaian</p>
                <h1>Rubrik Penilaian Tahfidz</h1>
                <p className="muted">
                    Rancang kriteria penilaian, metode pengurangan otomatis, bobot, dan predikat kelulusan. Versi terbit dijamin kekal (immutable) agar histori nilai santri tidak berubah.
                </p>

                {/* Section Navigation Switcher */}
                <div className="section-switcher" role="tablist">
                    <button
                        type="button"
                        role="tab"
                        aria-pressed={section === 'list'}
                        onClick={() => setSection('list')}
                    >
                        📋 Daftar Rubrik ({rubrics.length})
                    </button>
                    <button
                        type="button"
                        role="tab"
                        aria-pressed={section === 'builder'}
                        onClick={() => {
                            if (!editing) form.reset();
                            setSection('builder');
                        }}
                    >
                        ➕ {editing ? 'Ubah Draf Rubrik' : 'Buat Rubrik Baru'}
                    </button>
                    {selected && (
                        <button
                            type="button"
                            role="tab"
                            aria-pressed={section === 'simulator'}
                            onClick={() => setSection('simulator')}
                        >
                            🎯 Simulator Nilai ({selected.version.name} v{selected.version.version})
                        </button>
                    )}
                </div>

                {/* TAB 1: LIST RUBRICS */}
                {section === 'list' && (
                    <section className="master-card" aria-labelledby="rubric-list-heading" style={{ marginTop: '20px' }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '16px', flexWrap: 'wrap', gap: '10px' }}>
                            <h2 id="rubric-list-heading" style={{ margin: 0 }}>Daftar Rubrik Tersedia</h2>
                            <button
                                type="button"
                                className="primary"
                                onClick={() => {
                                    setEditing(null);
                                    form.reset();
                                    setSection('builder');
                                }}
                            >
                                + Buat Rubrik Baru
                            </button>
                        </div>

                        {rubrics.length === 0 ? (
                            <p className="muted" style={{ padding: '30px', textAlign: 'center', background: '#f8faf6', borderRadius: '8px' }}>
                                Belum ada rubrik penilaian. Klik tombol di atas untuk membuat rubrik pertama Anda.
                            </p>
                        ) : (
                            <div style={{ display: 'grid', gap: '18px' }}>
                                {rubrics.map((rubric) => {
                                    const latestVersion = rubric.versions[0];
                                    return (
                                        <article
                                            key={rubric.id}
                                            style={{
                                                padding: '20px',
                                                border: '1px solid var(--line)',
                                                borderRadius: '12px',
                                                background: rubric.archived_at ? '#fafafa' : '#fff',
                                            }}
                                        >
                                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: '12px' }}>
                                                <div>
                                                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                                        <h3 style={{ fontSize: '1.2rem', margin: 0, color: 'var(--ink)' }}>{rubric.name}</h3>
                                                        {rubric.archived_at && (
                                                            <span className="demo-badge" style={{ background: '#eee', borderColor: '#ccc', color: '#666' }}>
                                                                Diarsipkan
                                                            </span>
                                                        )}
                                                    </div>
                                                    <p style={{ margin: '4px 0 0', fontSize: '0.85rem', color: 'var(--muted)' }}>
                                                        {rubric.versions.length} Versi terdaftar · Ambang lulus: {latestVersion?.pass_threshold ?? '80'}%
                                                    </p>
                                                </div>

                                                <div className="master-actions">
                                                    {!rubric.archived_at && rubric.versions.every((v) => v.status !== 'draft') && (
                                                        <button
                                                            type="button"
                                                            onClick={() => router.post(`/rubrics/${rubric.id}/versions`)}
                                                            title="Kloning versi terbit menjadi draf versi baru"
                                                        >
                                                            + Kloning Versi Baru
                                                        </button>
                                                    )}
                                                    {!rubric.archived_at && (
                                                        <button
                                                            type="button"
                                                            style={{ color: '#942c26' }}
                                                            onClick={() => {
                                                                if (confirm(`Yakin ingin mengarsipkan rubrik "${rubric.name}"?`)) {
                                                                    router.post(`/rubrics/${rubric.id}/archive`);
                                                                }
                                                            }}
                                                        >
                                                            Arsipkan
                                                        </button>
                                                    )}
                                                </div>
                                            </div>

                                            {/* Version Cards */}
                                            <div style={{ display: 'grid', gap: '10px', marginTop: '16px' }}>
                                                {rubric.versions.map((version) => (
                                                    <div
                                                        key={version.id}
                                                        style={{
                                                            display: 'flex',
                                                            justifyContent: 'space-between',
                                                            alignItems: 'center',
                                                            padding: '12px 16px',
                                                            background: '#f8faf6',
                                                            borderRadius: '8px',
                                                            flexWrap: 'wrap',
                                                            gap: '10px',
                                                        }}
                                                    >
                                                        <div>
                                                            <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                                                <strong style={{ fontSize: '0.9rem' }}>Versi {version.version}</strong>
                                                                <span className={`rubric-version-badge ${version.status}`}>
                                                                    {version.status === 'published' ? 'Terbit (Aktif)' : version.status === 'draft' ? 'Draf' : 'Riwayat'}
                                                                </span>
                                                            </div>
                                                            <div style={{ fontSize: '0.8rem', color: 'var(--muted)', marginTop: '3px' }}>
                                                                {version.criteria.length} Kriteria ({version.criteria.map((c) => `${c.name} [${c.weight}%]`).join(', ')})
                                                                {version.bands.length > 0 && ` · ${version.bands.length} Predikat`}
                                                            </div>
                                                        </div>

                                                        <div className="master-actions">
                                                            {!rubric.archived_at && version.status === 'draft' && (
                                                                <>
                                                                    <button type="button" onClick={() => edit(rubric, version)}>
                                                                        ✏️ Ubah Draf
                                                                    </button>
                                                                    <button
                                                                        type="button"
                                                                        className="primary"
                                                                        style={{ padding: '6px 14px', minHeight: '36px', fontSize: '0.8rem' }}
                                                                        onClick={() => {
                                                                            if (confirm(`Terbitkan versi ${version.version}? Setelah terbit, versi ini tidak dapat diubah lagi.`)) {
                                                                                router.post(`/rubrics/${rubric.id}/versions/${version.id}/publish`);
                                                                            }
                                                                        }}
                                                                    >
                                                                        Terbitkan
                                                                    </button>
                                                                </>
                                                            )}
                                                            <button type="button" onClick={() => select(rubric, version)}>
                                                                🎯 Uji Nilai (Simulator)
                                                            </button>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </article>
                                    );
                                })}
                            </div>
                        )}
                    </section>
                )}

                {/* TAB 2: BUILDER */}
                {section === 'builder' && (
                    <section className="master-card" aria-labelledby="builder-heading" style={{ marginTop: '20px' }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '12px' }}>
                            <h2 id="builder-heading" style={{ margin: 0 }}>
                                {editing ? 'Ubah Draf Rubrik' : 'Buat Rubrik Penilaian Baru'}
                            </h2>
                            <button type="button" onClick={() => setSection('list')}>
                                ← Kembali ke Daftar
                            </button>
                        </div>

                        {/* Presets Header */}
                        <div style={{ padding: '12px 16px', background: '#f5f7f2', borderRadius: '10px', marginBottom: '20px' }}>
                            <div style={{ fontSize: '0.82rem', fontWeight: 600, color: 'var(--green)', marginBottom: '6px' }}>
                                💡 Template Cepat:
                            </div>
                            <div className="preset-buttons-bar" style={{ margin: 0 }}>
                                <button type="button" className="preset-chip-btn" onClick={applyTahfidzStandardPreset}>
                                    ✨ Standar Tahfidz 3 Kriteria (Kelancaran 40%, Tajwid 30%, Makhraj 30%)
                                </button>
                                <button type="button" className="preset-chip-btn" onClick={applyDirectScorePreset}>
                                    📝 Standar Nilai Langsung (Kelancaran 50%, Tajwid 50%)
                                </button>
                            </div>
                        </div>

                        <form className="master-form" onSubmit={submit}>
                            <div className="master-fields">
                                <label>
                                    Nama Rubrik
                                    <input
                                        required
                                        maxLength={160}
                                        placeholder="Contoh: Rubrik Ujian Juz 'Amma"
                                        value={form.data.name}
                                        onChange={(e) => form.setData('name', e.target.value)}
                                    />
                                    {form.errors.name && <small role="alert">{form.errors.name}</small>}
                                </label>
                                <label>
                                    Ambang Kelulusan Akhir (%)
                                    <input
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.0001"
                                        value={form.data.pass_threshold}
                                        onChange={(e) => form.setData('pass_threshold', e.target.value)}
                                    />
                                    {form.errors.pass_threshold && <small role="alert">{form.errors.pass_threshold}</small>}
                                </label>
                            </div>

                            {/* Weight check indicator */}
                            <div className={`weight-indicator ${Math.abs(totalWeight - 100) < 0.001 ? 'valid' : 'invalid'}`}>
                                <span>
                                    Total Bobot Kriteria: <strong>{totalWeight}%</strong>
                                </span>
                                <span>
                                    {Math.abs(totalWeight - 100) < 0.001 ? '✓ Bobot pas 100%' : `⚠️ Total bobot harus tepat 100% (saat ini ${totalWeight}%)`}
                                </span>
                            </div>

                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: '16px' }}>
                                <h3 style={{ margin: 0 }}>Daftar Kriteria Penilaian</h3>
                                <button
                                    type="button"
                                    onClick={() => form.setData('criteria', [...form.data.criteria, { ...newCriterion(), weight: '0' }])}
                                >
                                    + Tambah Kriteria
                                </button>
                            </div>
                            {form.errors.criteria && <small role="alert">{form.errors.criteria}</small>}

                            {form.data.criteria.map((item, index) => (
                                <fieldset className="master-card" key={index} style={{ border: '1px solid #dce4d9', background: '#fdfdfb' }}>
                                    <legend style={{ fontWeight: 700, color: 'var(--green)' }}>Kriteria {index + 1}</legend>
                                    <div className="master-fields">
                                        <label>
                                            Nama Kriteria
                                            <input
                                                required
                                                placeholder="Contoh: Tajwid"
                                                value={item.name}
                                                onChange={(e) => criterion(index, { name: e.target.value })}
                                            />
                                        </label>
                                        <label>
                                            Metode Penilaian
                                            <select
                                                value={item.method}
                                                onChange={(e) => criterion(index, {
                                                    method: e.target.value as Criterion['method'],
                                                    rules: e.target.value === 'direct' ? [] : (item.rules.length > 0 ? item.rules : [newRule()]),
                                                })}
                                            >
                                                <option value="deduction">Pengurangan (Deduction)</option>
                                                <option value="direct">Nilai Langsung (Direct Input)</option>
                                            </select>
                                        </label>
                                        <label>
                                            Bobot (%)
                                            <input
                                                type="number"
                                                min="0"
                                                max="100"
                                                step="0.0001"
                                                value={item.weight}
                                                onChange={(e) => criterion(index, { weight: e.target.value })}
                                            />
                                        </label>
                                    </div>

                                    <div className="master-fields">
                                        <label>
                                            Skor Minimum
                                            <input
                                                type="number"
                                                min="0"
                                                step="0.0001"
                                                value={item.min_score}
                                                onChange={(e) => criterion(index, { min_score: e.target.value })}
                                            />
                                        </label>
                                        <label>
                                            Skor Maksimum (Awal)
                                            <input
                                                type="number"
                                                min="0"
                                                step="0.0001"
                                                value={item.max_score}
                                                onChange={(e) => criterion(index, { max_score: e.target.value })}
                                            />
                                        </label>
                                        <label>
                                            Ambang Kelulusan Kriteria (%) (Opsional)
                                            <input
                                                type="number"
                                                min="0"
                                                max="100"
                                                step="0.0001"
                                                placeholder="Kosongkan jika tidak ada"
                                                value={item.min_pass_normalized ?? ''}
                                                onChange={(e) => criterion(index, { min_pass_normalized: e.target.value || null })}
                                            />
                                        </label>
                                    </div>

                                    <label>
                                        Deskripsi Panduan Kriteria (Opsional)
                                        <textarea
                                            value={item.description ?? ''}
                                            placeholder="Penjelasan kriteria penilaian ini untuk penguji…"
                                            onChange={(e) => criterion(index, { description: e.target.value })}
                                            style={{ minHeight: '60px' }}
                                        />
                                    </label>

                                    {item.method === 'deduction' && (
                                        <div style={{ marginTop: '16px', padding: '16px', background: '#f6f9f5', borderRadius: '10px', border: '1px solid #dbe6d9' }}>
                                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '10px' }}>
                                                <h4 style={{ margin: 0, fontSize: '0.95rem' }}>Aturan Kesalahan & Pemotongan Nilai</h4>
                                                <button
                                                    type="button"
                                                    style={{ fontSize: '0.78rem', minHeight: '32px', padding: '4px 10px' }}
                                                    onClick={() => criterion(index, { rules: [...item.rules, newRule()] })}
                                                >
                                                    + Tambah Aturan Kesalahan
                                                </button>
                                            </div>

                                            {item.rules.length === 0 ? (
                                                <p className="muted" style={{ fontSize: '0.82rem' }}>
                                                    Belum ada aturan kesalahan. Klik tombol di atas untuk menambahkan jenis kesalahan (misal: Ghunnah kurang -1 poin).
                                                </p>
                                            ) : (
                                                item.rules.map((entry, ruleIndex) => (
                                                    <div className="master-fields" key={ruleIndex} style={{ gridTemplateColumns: 'minmax(0, 1.5fr) minmax(0, 1fr) 120px 80px', alignItems: 'end', marginBottom: '8px' }}>
                                                        <label style={{ fontSize: '0.8rem' }}>
                                                            Nama Kesalahan
                                                            <input
                                                                required
                                                                placeholder="Contoh: Lupa / Tawaqquf"
                                                                value={entry.name}
                                                                onChange={(e) => rule(index, ruleIndex, { name: e.target.value })}
                                                                style={{ minHeight: '36px', margin: 0 }}
                                                            />
                                                        </label>
                                                        <label style={{ fontSize: '0.8rem' }}>
                                                            Tingkat (Opsional)
                                                            <input
                                                                placeholder="Ringan/Sedang/Berat"
                                                                value={entry.severity_label ?? ''}
                                                                onChange={(e) => rule(index, ruleIndex, { severity_label: e.target.value || null })}
                                                                style={{ minHeight: '36px', margin: 0 }}
                                                            />
                                                        </label>
                                                        <label style={{ fontSize: '0.8rem' }}>
                                                            Poin Potongan
                                                            <input
                                                                type="number"
                                                                min="0.0001"
                                                                step="0.0001"
                                                                value={entry.deduction_points}
                                                                onChange={(e) => rule(index, ruleIndex, { deduction_points: e.target.value })}
                                                                style={{ minHeight: '36px', margin: 0 }}
                                                            />
                                                        </label>
                                                        <button
                                                            type="button"
                                                            style={{ color: '#942c26', minHeight: '36px', padding: '4px' }}
                                                            onClick={() => criterion(index, { rules: item.rules.filter((_, i) => i !== ruleIndex) })}
                                                        >
                                                            Hapus
                                                        </button>
                                                    </div>
                                                ))
                                            )}
                                        </div>
                                    )}

                                    <div style={{ marginTop: '12px', textAlign: 'right' }}>
                                        {form.data.criteria.length > 1 && (
                                            <button
                                                type="button"
                                                style={{ color: '#942c26', fontSize: '0.82rem' }}
                                                onClick={() => form.setData('criteria', form.data.criteria.filter((_, i) => i !== index))}
                                            >
                                                🗑️ Hapus Kriteria {index + 1}
                                            </button>
                                        )}
                                    </div>
                                    {Object.entries(form.errors).filter(([key]) => key.startsWith(`criteria.${index}.`)).map(([key, message]) => (
                                        <small key={key} role="alert">{message}</small>
                                    ))}
                                </fieldset>
                            ))}

                            {/* Predicate Bands Section */}
                            <div style={{ marginTop: '24px', borderTop: '1px solid var(--line)', paddingTop: '20px' }}>
                                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '8px' }}>
                                    <div>
                                        <h3 style={{ margin: 0 }}>Rentang Predikat Kelulusan (Opsional)</h3>
                                        <p className="muted" style={{ fontSize: '0.82rem', margin: '2px 0 0' }}>
                                            Rentang nilai harus berurutan tanpa celah dari 0 sampai 100.
                                        </p>
                                    </div>
                                    <button type="button" className="preset-chip-btn" onClick={applyIslamicBandsPreset}>
                                        ✨ Gunakan Predikat Standar (Mumtaz/Jayyid/Maqbul/Rasib)
                                    </button>
                                </div>
                                {form.errors.bands && <small role="alert">{form.errors.bands}</small>}

                                {form.data.bands.length > 0 && (
                                    <div style={{ marginTop: '12px' }}>
                                        {form.data.bands.map((item, index) => (
                                            <div className="master-fields" key={index} style={{ gridTemplateColumns: 'minmax(0, 1.5fr) 120px 120px 80px', alignItems: 'end', marginBottom: '8px' }}>
                                                <label style={{ fontSize: '0.8rem' }}>
                                                    Label Predikat
                                                    <input
                                                        required
                                                        placeholder="Contoh: Mumtaz (A)"
                                                        value={item.label}
                                                        onChange={(e) => band(index, { label: e.target.value })}
                                                        style={{ minHeight: '36px', margin: 0 }}
                                                    />
                                                </label>
                                                <label style={{ fontSize: '0.8rem' }}>
                                                    Batas Bawah
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        max="100"
                                                        step="0.0001"
                                                        value={item.lower_bound}
                                                        onChange={(e) => band(index, { lower_bound: e.target.value })}
                                                        style={{ minHeight: '36px', margin: 0 }}
                                                    />
                                                </label>
                                                <label style={{ fontSize: '0.8rem' }}>
                                                    Batas Atas
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        max="100"
                                                        step="0.0001"
                                                        value={item.upper_bound}
                                                        onChange={(e) => band(index, { upper_bound: e.target.value })}
                                                        style={{ minHeight: '36px', margin: 0 }}
                                                    />
                                                </label>
                                                <button
                                                    type="button"
                                                    style={{ color: '#942c26', minHeight: '36px', padding: '4px' }}
                                                    onClick={() => form.setData('bands', form.data.bands.filter((_, i) => i !== index))}
                                                >
                                                    Hapus
                                                </button>
                                            </div>
                                        ))}
                                    </div>
                                )}

                                <div style={{ marginTop: '10px' }}>
                                    <button
                                        type="button"
                                        style={{ fontSize: '0.82rem' }}
                                        onClick={() => form.setData('bands', [...form.data.bands, { label: '', lower_bound: form.data.bands.at(-1)?.upper_bound ?? '0', upper_bound: '100' }])}
                                    >
                                        + Tambah Baris Predikat
                                    </button>
                                </div>
                            </div>

                            <div className="master-actions" style={{ marginTop: '24px' }}>
                                <button className="primary" disabled={form.processing}>
                                    {editing ? 'Simpan Perubahan Draf' : 'Buat Draf Rubrik'}
                                </button>
                                <button
                                    type="button"
                                    onClick={() => {
                                        setEditing(null);
                                        form.reset();
                                        setSection('list');
                                    }}
                                >
                                    Batal
                                </button>
                            </div>
                        </form>
                    </section>
                )}

                {/* TAB 3: SIMULATOR & PREVIEW */}
                {section === 'simulator' && selected && (
                    <section className="master-card" aria-labelledby="preview-heading" style={{ marginTop: '20px' }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '16px', flexWrap: 'wrap', gap: '10px' }}>
                            <div>
                                <h2 id="preview-heading" style={{ margin: 0 }}>
                                    🎯 Simulator Nilai · {selected.version.name} (v{selected.version.version})
                                </h2>
                                <p className="muted" style={{ fontSize: '0.82rem', margin: '2px 0 0' }}>
                                    Uji coba simulasi kalkulasi nilai berdasarkan kriteria dan potongan kesalahan rubrik ini.
                                </p>
                            </div>
                            <button type="button" onClick={() => setSection('list')}>
                                Tutup Simulator ✕
                            </button>
                        </div>

                        <div className="assessment-workspace">
                            {/* Simulator Input Area */}
                            <div style={{ display: 'grid', gap: '16px' }}>
                                {selected.version.criteria.map((criterion) => (
                                    <div key={criterion.id} className="criterion-summary-card">
                                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '8px' }}>
                                            <strong style={{ fontSize: '0.95rem', color: 'var(--green)' }}>
                                                {criterion.name} (Bobot {criterion.weight}%)
                                            </strong>
                                            <span style={{ fontSize: '0.78rem', color: 'var(--muted)' }}>
                                                Metode: {criterion.method === 'direct' ? 'Nilai Langsung' : 'Pengurangan'} (Rentang {criterion.min_score}–{criterion.max_score})
                                            </span>
                                        </div>

                                        {criterion.method === 'direct' ? (
                                            <label style={{ fontSize: '0.82rem' }}>
                                                Masukkan Nilai ({criterion.min_score}–{criterion.max_score})
                                                <input
                                                    type="number"
                                                    min={criterion.min_score}
                                                    max={criterion.max_score}
                                                    step="0.0001"
                                                    value={direct[criterion.id ?? ''] ?? ''}
                                                    onChange={(e) => setDirect({ ...direct, [criterion.id ?? '']: e.target.value })}
                                                    style={{ minHeight: '38px', marginTop: '4px' }}
                                                />
                                            </label>
                                        ) : (
                                            <div>
                                                <p style={{ fontSize: '0.78rem', color: 'var(--muted)', margin: '0 0 6px' }}>
                                                    Klik tombol di bawah untuk menyimulasikan kesalahan:
                                                </p>
                                                <div style={{ display: 'flex', flexWrap: 'wrap', gap: '8px' }}>
                                                    {criterion.rules.map((rule) => (
                                                        <button
                                                            key={rule.id}
                                                            type="button"
                                                            className="rule-chip"
                                                            onClick={() => setEvents([...events, { id: crypto.randomUUID(), kind: 'penalty', criterion_id: criterion.id, rule_id: rule.id, active: true }])}
                                                        >
                                                            + {rule.name} (−{rule.deduction_points})
                                                        </button>
                                                    ))}
                                                </div>
                                            </div>
                                        )}

                                        {/* Optional Override */}
                                        <div style={{ marginTop: '12px', borderTop: '1px dashed #dce4d9', paddingTop: '8px' }}>
                                            <div style={{ display: 'grid', gridTemplateColumns: '140px minmax(0, 1fr)', gap: '8px', alignItems: 'end' }}>
                                                <label style={{ fontSize: '0.75rem', color: 'var(--muted)' }}>
                                                    Override (Opsional)
                                                    <input
                                                        type="number"
                                                        min={criterion.min_score}
                                                        max={criterion.max_score}
                                                        step="0.0001"
                                                        placeholder="Skor baru"
                                                        value={overrides[criterion.id ?? '']?.raw ?? ''}
                                                        onChange={(e) => {
                                                            const id = criterion.id ?? '';
                                                            const next = { ...overrides };
                                                            if (e.target.value) next[id] = { raw: e.target.value, reason: next[id]?.reason ?? '' };
                                                            else delete next[id];
                                                            setOverrides(next);
                                                        }}
                                                        style={{ minHeight: '34px', margin: 0 }}
                                                    />
                                                </label>
                                                {overrides[criterion.id ?? ''] && (
                                                    <label style={{ fontSize: '0.75rem', color: 'var(--muted)' }}>
                                                        Alasan Override
                                                        <input
                                                            placeholder="Alasan perubahan nilai…"
                                                            value={overrides[criterion.id ?? ''].reason}
                                                            onChange={(e) => {
                                                                const id = criterion.id ?? '';
                                                                setOverrides({ ...overrides, [id]: { ...overrides[id], reason: e.target.value } });
                                                            }}
                                                            style={{ minHeight: '34px', margin: 0 }}
                                                        />
                                                    </label>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                ))}

                                <div className="master-actions">
                                    <button
                                        type="button"
                                        style={{ fontSize: '0.8rem' }}
                                        onClick={() => setEvents([...events, { id: crypto.randomUUID(), kind: 'note', note: 'Catatan simulasi', active: true }])}
                                    >
                                        + Tambah Catatan Tanpa Potongan
                                    </button>
                                </div>

                                {/* Active Simulation Events */}
                                {events.length > 0 && (
                                    <div style={{ padding: '14px', background: '#f9faf7', borderRadius: '8px', border: '1px solid #dce4d9' }}>
                                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '8px' }}>
                                            <strong style={{ fontSize: '0.85rem' }}>Daftar Kejadian Simulasi ({events.filter((e) => e.active).length} Aktif)</strong>
                                            <button
                                                type="button"
                                                style={{ border: 'none', background: 'transparent', color: '#942c26', fontSize: '0.75rem', cursor: 'pointer' }}
                                                onClick={() => setEvents([])}
                                            >
                                                Reset Semua
                                            </button>
                                        </div>
                                        <div style={{ display: 'grid', gap: '6px' }}>
                                            {events.map((event) => {
                                                const ruleObj = selected.version.criteria.flatMap((c) => c.rules).find((r) => r.id === event.rule_id);
                                                return (
                                                    <div
                                                        key={event.id}
                                                        style={{
                                                            display: 'flex',
                                                            justifyContent: 'space-between',
                                                            alignItems: 'center',
                                                            padding: '6px 10px',
                                                            borderRadius: '6px',
                                                            background: event.active ? (event.kind === 'note' ? '#fff9e6' : '#fff4f2') : '#eee',
                                                            fontSize: '0.78rem',
                                                            color: event.active ? 'inherit' : '#888',
                                                        }}
                                                    >
                                                        <span>
                                                            {event.kind === 'note' ? `📝 ${event.note}` : `⚠️ ${ruleObj?.name ?? 'Kesalahan'} (−${ruleObj?.deduction_points ?? '0'})`}
                                                            {!event.active && ' (Dibatalkan)'}
                                                        </span>
                                                        <button
                                                            type="button"
                                                            style={{ border: 'none', background: 'transparent', color: '#942c26', fontSize: '0.72rem', cursor: 'pointer' }}
                                                            onClick={() => setEvents(events.map((item) => (item.id === event.id ? { ...item, active: !item.active } : item)))}
                                                        >
                                                            {event.active ? 'Undo' : 'Redo'}
                                                        </button>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </div>
                                )}

                                <div className="master-actions">
                                    <button type="button" className="primary" onClick={() => void calculate(false)}>
                                        Hitung Simulasi
                                    </button>
                                    <button type="button" onClick={() => void calculate(true)}>
                                        Periksa Sebagai Final
                                    </button>
                                </div>
                                {previewError && <p role="alert">{previewError}</p>}
                            </div>

                            {/* Simulator Result Sidebar */}
                            <div className="master-card" style={{ marginTop: 0, padding: '20px' }}>
                                <h3 style={{ fontSize: '1.1rem', margin: '0 0 12px' }}>Hasil Kalkulasi</h3>
                                {preview ? (
                                    <div>
                                        <div style={{ fontSize: '2.2rem', fontWeight: 700, color: preview.passed ? 'var(--green)' : '#942c26', marginBottom: '4px' }}>
                                            {preview.display_score ?? '—'}
                                        </div>
                                        <div style={{ fontSize: '0.88rem', marginBottom: '14px' }}>
                                            Status: <strong>{preview.passed ? 'LULUS' : 'BELUM LULUS'}</strong>
                                            {preview.grade && (
                                                <span className="demo-badge" style={{ marginLeft: '8px' }}>
                                                    {preview.grade}
                                                </span>
                                            )}
                                        </div>
                                        {preview.unrounded_score && (
                                            <div style={{ fontSize: '0.75rem', color: 'var(--muted)', marginBottom: '16px' }}>
                                                Nilai presisi: {preview.unrounded_score} (Ambang: {selected.version.pass_threshold}%)
                                            </div>
                                        )}

                                        <div style={{ borderTop: '1px solid #e0e5db', paddingTop: '12px', display: 'grid', gap: '10px' }}>
                                            {preview.criteria.map((c) => (
                                                <div key={c.id} style={{ fontSize: '0.82rem', padding: '8px', background: '#f8faf6', borderRadius: '6px' }}>
                                                    <div style={{ display: 'flex', justifyContent: 'space-between', fontWeight: 600 }}>
                                                        <span>{c.name}</span>
                                                        <span>{c.computed_raw ?? '—'}</span>
                                                    </div>
                                                    <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.75rem', color: 'var(--muted)', marginTop: '2px' }}>
                                                        <span>Normalisasi: {c.normalized ?? '—'}%</span>
                                                        <span>Kontribusi: {c.weighted ?? '—'} poin</span>
                                                    </div>
                                                    {c.deductions.map((d) => (
                                                        <div key={d.event_id} style={{ color: '#942c26', fontSize: '0.72rem', marginTop: '2px' }}>
                                                            • {d.name} (−{d.points})
                                                        </div>
                                                    ))}
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                ) : (
                                    <p style={{ fontSize: '0.85rem', color: 'var(--muted)', margin: 0 }}>
                                        Masukkan nilai atau pilih potongan kesalahan lalu klik <strong>Hitung Simulasi</strong> untuk melihat hasil kalkulasi mesin skor.
                                    </p>
                                )}
                            </div>
                        </div>
                    </section>
                )}
            </main>
        </div>
    );
}
