import { Head, Link, router, useForm } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';
import { Icon } from '../../Components/Icon';

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

    const totalWeight = useMemo(() => {
        return form.data.criteria.reduce((sum, item) => sum + (Number(item.weight) || 0), 0);
    }, [form.data.criteria]);

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

                <div className="section-switcher" role="tablist">
                    <button type="button" role="tab" aria-pressed={section === 'list'} onClick={() => setSection('list')}>
                        <Icon name="clipboard" /> Daftar Rubrik ({rubrics.length})
                    </button>
                    <button type="button" role="tab" aria-pressed={section === 'builder'} onClick={() => { if (!editing) form.reset(); setSection('builder'); }}>
                        <Icon name="plus" /> {editing ? 'Ubah Draf Rubrik' : 'Buat Rubrik Baru'}
                    </button>
                    {selected && (
                        <button type="button" role="tab" aria-pressed={section === 'simulator'} onClick={() => setSection('simulator')}>
                            <Icon name="target" /> Simulator Nilai ({selected.version.name} v{selected.version.version})
                        </button>
                    )}
                </div>

                {section === 'list' && (
                    <section className="master-card" aria-labelledby="rubric-list-heading">
                        <div className="rubric-row">
                            <h2 id="rubric-list-heading" className="rubric-row-title">Daftar Rubrik Tersedia</h2>
                            <button type="button" className="primary" onClick={() => { setEditing(null); form.reset(); setSection('builder'); }}>
                                <Icon name="plus" /> Buat Rubrik Baru
                            </button>
                        </div>

                        {rubrics.length === 0 ? (
                            <p className="muted rubric-empty-block">
                                Belum ada rubrik penilaian. Klik tombol di atas untuk membuat rubrik pertama Anda.
                            </p>
                        ) : (
                            <div className="rubric-list">
                                {rubrics.map((rubric) => {
                                    const latestVersion = rubric.versions[0];
                                    return (
                                        <article key={rubric.id} className={`rubric-card${rubric.archived_at ? ' is-archived' : ''}`}>
                                            <div className="rubric-row rubric-row--top">
                                                <div>
                                                    <div className="rubric-inline">
                                                        <h3 className="rubric-name">{rubric.name}</h3>
                                                        {rubric.archived_at && (
                                                            <span className="demo-badge rubric-badge-archived">Diarsipkan</span>
                                                        )}
                                                    </div>
                                                    <p className="rubric-meta">
                                                        {rubric.versions.length} Versi terdaftar · Ambang lulus: {latestVersion?.pass_threshold ?? '80'}%
                                                    </p>
                                                </div>

                                                <div className="master-actions">
                                                    {!rubric.archived_at && rubric.versions.every((v) => v.status !== 'draft') && (
                                                        <button type="button" onClick={() => router.post(`/rubrics/${rubric.id}/versions`)} title="Kloning versi terbit menjadi draf versi baru">
                                                            <Icon name="plus" /> Kloning Versi Baru
                                                        </button>
                                                    )}
                                                    {!rubric.archived_at && (
                                                        <button type="button" className="action-danger" onClick={() => { if (confirm(`Yakin ingin mengarsipkan rubrik "${rubric.name}"?`)) { router.post(`/rubrics/${rubric.id}/archive`); } }}>
                                                            Arsipkan
                                                        </button>
                                                    )}
                                                </div>
                                            </div>

                                            <div className="rubric-version-list">
                                                {rubric.versions.map((version) => (
                                                    <div key={version.id} className="rubric-version-row">
                                                        <div>
                                                            <div className="rubric-inline">
                                                                <strong className="rubric-version-label">Versi {version.version}</strong>
                                                                <span className={`rubric-version-badge ${version.status}`}>
                                                                    {version.status === 'published' ? 'Terbit (Aktif)' : version.status === 'draft' ? 'Draf' : 'Riwayat'}
                                                                </span>
                                                            </div>
                                                            <div className="rubric-version-meta">
                                                                {version.criteria.length} Kriteria ({version.criteria.map((c) => `${c.name} [${c.weight}%]`).join(', ')})
                                                                {version.bands.length > 0 && ` · ${version.bands.length} Predikat`}
                                                            </div>
                                                        </div>

                                                        <div className="master-actions">
                                                            {!rubric.archived_at && version.status === 'draft' && (
                                                                <>
                                                                    <button type="button" onClick={() => edit(rubric, version)}>
                                                                        <Icon name="edit" /> Ubah Draf
                                                                    </button>
                                                                    <button type="button" className="primary rubric-btn-compact" onClick={() => { if (confirm(`Terbitkan versi ${version.version}? Setelah terbit, versi ini tidak dapat diubah lagi.`)) { router.post(`/rubrics/${rubric.id}/versions/${version.id}/publish`); } }}>
                                                                        Terbitkan
                                                                    </button>
                                                                </>
                                                            )}
                                                            <button type="button" onClick={() => select(rubric, version)}>
                                                                <Icon name="target" /> Uji Nilai (Simulator)
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

                {section === 'builder' && (
                    <section className="master-card" aria-labelledby="builder-heading">
                        <div className="rubric-row">
                            <h2 id="builder-heading" className="rubric-row-title">
                                {editing ? 'Ubah Draf Rubrik' : 'Buat Rubrik Penilaian Baru'}
                            </h2>
                            <button type="button" onClick={() => setSection('list')}>
                                ← Kembali ke Daftar
                            </button>
                        </div>

                        <div className="rubric-preset-box">
                            <div className="rubric-preset-label">Template Cepat:</div>
                            <div className="preset-buttons-bar">
                                <button type="button" className="preset-chip-btn" onClick={applyTahfidzStandardPreset}>
                                    Standar Tahfidz 3 Kriteria (Kelancaran 40%, Tajwid 30%, Makhraj 30%)
                                </button>
                                <button type="button" className="preset-chip-btn" onClick={applyDirectScorePreset}>
                                    Standar Nilai Langsung (Kelancaran 50%, Tajwid 50%)
                                </button>
                            </div>
                        </div>

                        <form className="master-form" onSubmit={submit}>
                            <div className="master-fields">
                                <label>
                                    Nama Rubrik
                                    <input required maxLength={160} placeholder="Contoh: Rubrik Ujian Juz 'Amma" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                                    {form.errors.name && <small role="alert">{form.errors.name}</small>}
                                </label>
                                <label>
                                    Ambang Kelulusan Akhir (%)
                                    <input type="number" min="0" max="100" step="0.0001" value={form.data.pass_threshold} onChange={(e) => form.setData('pass_threshold', e.target.value)} />
                                    {form.errors.pass_threshold && <small role="alert">{form.errors.pass_threshold}</small>}
                                </label>
                            </div>

                            <div className={`weight-indicator ${Math.abs(totalWeight - 100) < 0.001 ? 'valid' : 'invalid'}`}>
                                <span>Total Bobot Kriteria: <strong>{totalWeight}%</strong></span>
                                <span>{Math.abs(totalWeight - 100) < 0.001 ? 'Bobot pas 100%' : `Total bobot harus tepat 100% (saat ini ${totalWeight}%)`}</span>
                            </div>

                            <div className="rubric-criteria-head">
                                <h3>Daftar Kriteria Penilaian</h3>
                                <button type="button" onClick={() => form.setData('criteria', [...form.data.criteria, { ...newCriterion(), weight: '0' }])}>
                                    <Icon name="plus" /> Tambah Kriteria
                                </button>
                            </div>
                            {form.errors.criteria && <small role="alert">{form.errors.criteria}</small>}

                            {form.data.criteria.map((item, index) => (
                                <fieldset className="master-card rubric-criterion-fieldset" key={index}>
                                    <legend className="rubric-criterion-legend">Kriteria {index + 1}</legend>
                                    <div className="master-fields">
                                        <label>
                                            Nama Kriteria
                                            <input required placeholder="Contoh: Tajwid" value={item.name} onChange={(e) => criterion(index, { name: e.target.value })} />
                                        </label>
                                        <label>
                                            Metode Penilaian
                                            <select value={item.method} onChange={(e) => criterion(index, { method: e.target.value as Criterion['method'], rules: e.target.value === 'direct' ? [] : (item.rules.length > 0 ? item.rules : [newRule()]) })}>
                                                <option value="deduction">Pengurangan (Deduction)</option>
                                                <option value="direct">Nilai Langsung (Direct Input)</option>
                                            </select>
                                        </label>
                                        <label>
                                            Bobot (%)
                                            <input type="number" min="0" max="100" step="0.0001" value={item.weight} onChange={(e) => criterion(index, { weight: e.target.value })} />
                                        </label>
                                    </div>

                                    <div className="master-fields">
                                        <label>
                                            Skor Minimum
                                            <input type="number" min="0" step="0.0001" value={item.min_score} onChange={(e) => criterion(index, { min_score: e.target.value })} />
                                        </label>
                                        <label>
                                            Skor Maksimum (Awal)
                                            <input type="number" min="0" step="0.0001" value={item.max_score} onChange={(e) => criterion(index, { max_score: e.target.value })} />
                                        </label>
                                        <label>
                                            Ambang Kelulusan Kriteria (%) (Opsional)
                                            <input type="number" min="0" max="100" step="0.0001" placeholder="Kosongkan jika tidak ada" value={item.min_pass_normalized ?? ''} onChange={(e) => criterion(index, { min_pass_normalized: e.target.value || null })} />
                                        </label>
                                    </div>

                                    <label>
                                        Deskripsi Panduan Kriteria (Opsional)
                                        <textarea className="rubric-textarea-compact" value={item.description ?? ''} placeholder="Penjelasan kriteria penilaian ini untuk penguji…" onChange={(e) => criterion(index, { description: e.target.value })} />
                                    </label>

                                    {item.method === 'deduction' && (
                                        <div className="rubric-deduction-box">
                                            <div className="rubric-deduction-head">
                                                <h4>Aturan Kesalahan & Pemotongan Nilai</h4>
                                                <button type="button" className="rubric-btn-compact" onClick={() => criterion(index, { rules: [...item.rules, newRule()] })}>
                                                    <Icon name="plus" /> Tambah Aturan Kesalahan
                                                </button>
                                            </div>

                                            {item.rules.length === 0 ? (
                                                <p className="muted rubric-empty-hint">
                                                    Belum ada aturan kesalahan. Klik tombol di atas untuk menambahkan jenis kesalahan (misal: Ghunnah kurang -1 poin).
                                                </p>
                                            ) : (
                                                item.rules.map((entry, ruleIndex) => (
                                                    <div className="master-fields rubric-rule-fields" key={ruleIndex}>
                                                        <label className="rubric-rule-field-label">
                                                            Nama Kesalahan
                                                            <input required placeholder="Contoh: Lupa / Tawaqquf" value={entry.name} onChange={(e) => rule(index, ruleIndex, { name: e.target.value })} />
                                                        </label>
                                                        <label className="rubric-rule-field-label">
                                                            Tingkat (Opsional)
                                                            <input placeholder="Ringan/Sedang/Berat" value={entry.severity_label ?? ''} onChange={(e) => rule(index, ruleIndex, { severity_label: e.target.value || null })} />
                                                        </label>
                                                        <label className="rubric-rule-field-label">
                                                            Poin Potongan
                                                            <input type="number" min="0.0001" step="0.0001" value={entry.deduction_points} onChange={(e) => rule(index, ruleIndex, { deduction_points: e.target.value })} />
                                                        </label>
                                                        <button type="button" className="rubric-delete-link" onClick={() => criterion(index, { rules: item.rules.filter((_, i) => i !== ruleIndex) })}>
                                                            <Icon name="trash" /> Hapus
                                                        </button>
                                                    </div>
                                                ))
                                            )}
                                        </div>
                                    )}

                                    <div className="rubric-criterion-footer">
                                        {form.data.criteria.length > 1 && (
                                            <button type="button" className="rubric-delete-link" onClick={() => form.setData('criteria', form.data.criteria.filter((_, i) => i !== index))}>
                                                <Icon name="trash" /> Hapus Kriteria {index + 1}
                                            </button>
                                        )}
                                    </div>
                                    {Object.entries(form.errors).filter(([key]) => key.startsWith(`criteria.${index}.`)).map(([key, message]) => (
                                        <small key={key} role="alert">{message}</small>
                                    ))}
                                </fieldset>
                            ))}

                            <div className="rubric-bands-section">
                                <div className="rubric-bands-head">
                                    <div>
                                        <h3>Rentang Predikat Kelulusan (Opsional)</h3>
                                        <p className="muted rubric-bands-hint">Rentang nilai harus berurutan tanpa celah dari 0 sampai 100.</p>
                                    </div>
                                    <button type="button" className="preset-chip-btn" onClick={applyIslamicBandsPreset}>
                                        Gunakan Predikat Standar (Mumtaz/Jayyid/Maqbul/Rasib)
                                    </button>
                                </div>
                                {form.errors.bands && <small role="alert">{form.errors.bands}</small>}

                                {form.data.bands.length > 0 && (
                                    <div className="rubric-bands-list">
                                        {form.data.bands.map((item, index) => (
                                            <div className="master-fields rubric-band-fields" key={index}>
                                                <label className="rubric-band-field-label">
                                                    Label Predikat
                                                    <input required placeholder="Contoh: Mumtaz (A)" value={item.label} onChange={(e) => band(index, { label: e.target.value })} />
                                                </label>
                                                <label className="rubric-band-field-label">
                                                    Batas Bawah
                                                    <input type="number" min="0" max="100" step="0.0001" value={item.lower_bound} onChange={(e) => band(index, { lower_bound: e.target.value })} />
                                                </label>
                                                <label className="rubric-band-field-label">
                                                    Batas Atas
                                                    <input type="number" min="0" max="100" step="0.0001" value={item.upper_bound} onChange={(e) => band(index, { upper_bound: e.target.value })} />
                                                </label>
                                                <button type="button" className="rubric-delete-link" onClick={() => form.setData('bands', form.data.bands.filter((_, i) => i !== index))}>
                                                    <Icon name="trash" /> Hapus
                                                </button>
                                            </div>
                                        ))}
                                    </div>
                                )}

                                <div className="rubric-bands-add">
                                    <button type="button" onClick={() => form.setData('bands', [...form.data.bands, { label: '', lower_bound: form.data.bands.at(-1)?.upper_bound ?? '0', upper_bound: '100' }])}>
                                        <Icon name="plus" /> Tambah Baris Predikat
                                    </button>
                                </div>
                            </div>

                            <div className="master-actions rubric-form-actions">
                                <button className="primary" disabled={form.processing}>
                                    {editing ? 'Simpan Perubahan Draf' : 'Buat Draf Rubrik'}
                                </button>
                                <button type="button" onClick={() => { setEditing(null); form.reset(); setSection('list'); }}>
                                    Batal
                                </button>
                            </div>
                        </form>
                    </section>
                )}

                {section === 'simulator' && selected && (
                    <section className="master-card" aria-labelledby="preview-heading">
                        <div className="rubric-simulator-head">
                            <div>
                                <h2 id="preview-heading" className="rubric-row-title">
                                    <Icon name="target" /> Simulator Nilai · {selected.version.name} (v{selected.version.version})
                                </h2>
                                <p className="muted rubric-simulator-subtitle">Uji coba simulasi kalkulasi nilai berdasarkan kriteria dan potongan kesalahan rubrik ini.</p>
                            </div>
                            <button type="button" onClick={() => setSection('list')}>
                                Tutup Simulator
                            </button>
                        </div>

                        <div className="assessment-workspace">
                            <div className="rubric-simulator-grid">
                                {selected.version.criteria.map((criterion) => (
                                    <div key={criterion.id} className="criterion-summary-card">
                                        <div className="rubric-criterion-head">
                                            <strong className="rubric-criterion-title">{criterion.name} (Bobot {criterion.weight}%)</strong>
                                            <span className="rubric-criterion-method">Metode: {criterion.method === 'direct' ? 'Nilai Langsung' : 'Pengurangan'} (Rentang {criterion.min_score}–{criterion.max_score})</span>
                                        </div>

                                        {criterion.method === 'direct' ? (
                                            <label className="rubric-direct-field">
                                                Masukkan Nilai ({criterion.min_score}–{criterion.max_score})
                                                <input type="number" min={criterion.min_score} max={criterion.max_score} step="0.0001" value={direct[criterion.id ?? ''] ?? ''} onChange={(e) => setDirect({ ...direct, [criterion.id ?? '']: e.target.value })} />
                                            </label>
                                        ) : (
                                            <div>
                                                <p className="rubric-penalty-hint">Klik tombol di bawah untuk menyimulasikan kesalahan:</p>
                                                <div className="rubric-rule-chips">
                                                    {criterion.rules.map((rule) => (
                                                        <button key={rule.id} type="button" className="rule-chip" onClick={() => setEvents([...events, { id: crypto.randomUUID(), kind: 'penalty', criterion_id: criterion.id, rule_id: rule.id, active: true }])}>
                                                            + {rule.name} (−{rule.deduction_points})
                                                        </button>
                                                    ))}
                                                </div>
                                            </div>
                                        )}

                                        <div className="rubric-override-box">
                                            <div className="rubric-override-grid">
                                                <label className="rubric-override-label">
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
                                                    />
                                                </label>
                                                {overrides[criterion.id ?? ''] && (
                                                    <label className="rubric-override-label">
                                                        Alasan Override
                                                        <input
                                                            placeholder="Alasan perubahan nilai…"
                                                            value={overrides[criterion.id ?? ''].reason}
                                                            onChange={(e) => {
                                                                const id = criterion.id ?? '';
                                                                setOverrides({ ...overrides, [id]: { ...overrides[id], reason: e.target.value } });
                                                            }}
                                                        />
                                                    </label>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                ))}

                                <div className="master-actions">
                                    <button type="button" onClick={() => setEvents([...events, { id: crypto.randomUUID(), kind: 'note', note: 'Catatan simulasi', active: true }])}>
                                        Tambah Catatan Tanpa Potongan
                                    </button>
                                </div>

                                {events.length > 0 && (
                                    <div className="rubric-events-box">
                                        <div className="rubric-events-head">
                                            <strong>Daftar Kejadian Simulasi ({events.filter((e) => e.active).length} Aktif)</strong>
                                            <button type="button" className="rubric-text-btn" onClick={() => setEvents([])}>Reset Semua</button>
                                        </div>
                                        <div className="rubric-events-list">
                                            {events.map((event) => {
                                                const ruleObj = selected.version.criteria.flatMap((c) => c.rules).find((r) => r.id === event.rule_id);
                                                return (
                                                    <div key={event.id} className={`rubric-event-row${!event.active ? ' is-inactive' : event.kind === 'note' ? ' is-active-note' : ' is-active-penalty'}`}>
                                                        <span>
                                                            {event.kind === 'note' ? event.note : `${ruleObj?.name ?? 'Kesalahan'} (−${ruleObj?.deduction_points ?? '0'})`}
                                                            {!event.active && ' (Dibatalkan)'}
                                                        </span>
                                                        <button type="button" className="rubric-text-btn" onClick={() => setEvents(events.map((item) => (item.id === event.id ? { ...item, active: !item.active } : item)))}>
                                                            {event.active ? 'Undo' : 'Redo'}
                                                        </button>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </div>
                                )}

                                <div className="master-actions">
                                    <button type="button" className="primary" onClick={() => void calculate(false)}>Hitung Simulasi</button>
                                    <button type="button" onClick={() => void calculate(true)}>Periksa Sebagai Final</button>
                                </div>
                                {previewError && <p role="alert">{previewError}</p>}
                            </div>

                            <div className="master-card rubric-result-box">
                                <h3>Hasil Kalkulasi</h3>
                                {preview ? (
                                    <div>
                                        <div className={`rubric-result-score ${preview.passed ? 'is-pass' : 'is-fail'}`}>{preview.display_score ?? '—'}</div>
                                        <div className="rubric-result-status">
                                            Status: <strong>{preview.passed ? 'LULUS' : 'BELUM LULUS'}</strong>
                                            {preview.grade && <span className="demo-badge">{preview.grade}</span>}
                                        </div>
                                        {preview.unrounded_score && (
                                            <div className="rubric-result-precision">Nilai presisi: {preview.unrounded_score} (Ambang: {selected.version.pass_threshold}%)</div>
                                        )}

                                        <div className="rubric-result-criteria">
                                            {preview.criteria.map((c) => (
                                                <div key={c.id} className="rubric-result-criterion">
                                                    <div className="rubric-result-criterion-head">
                                                        <span>{c.name}</span>
                                                        <span>{c.computed_raw ?? '—'}</span>
                                                    </div>
                                                    <div className="rubric-result-criterion-sub">
                                                        <span>Normalisasi: {c.normalized ?? '—'}%</span>
                                                        <span>Kontribusi: {c.weighted ?? '—'} poin</span>
                                                    </div>
                                                    {c.deductions.map((d) => (
                                                        <div key={d.event_id} className="rubric-result-deduction">• {d.name} (−{d.points})</div>
                                                    ))}
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                ) : (
                                    <p className="rubric-result-empty">
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
