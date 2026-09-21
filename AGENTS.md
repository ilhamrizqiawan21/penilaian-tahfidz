# Panduan Agen — Penilaian Tahfidz

## Konteks dan cakupan

- Aktor utama: guru tahfidz pribadi, banyak santri, kelompok opsional.
- Penilaian gabungan berarti metode dipilih per kriteria: nilai langsung atau pengurangan. Anotasi tanpa potongan diperbolehkan.
- F1 sudah memiliki scaffold aplikasi, login pemilik, dan dashboard awal. Jangan menganggap modul F2–F8 sebagai fitur yang sudah tersedia.
- Tidak membuat SaaS multi-lembaga, billing, portal wali, AI penilai, atau sistem ujian banyak penguji tanpa permintaan lanjutan.
- Instruksi eksplisit pengguna mengungguli pedoman proyek. Bila mengubah kontrak domain, selaraskan dokumentasi dan pengujian.

## Sumber kebenaran

1. [PRD](docs/PRD.md): perilaku produk dan acceptance criteria ber-ID `CAP-xx`.
2. [ERD](docs/ERD.md): data dan integritas untuk memenuhi PRD.
3. [AI Rules](docs/AI_RULES.md): aturan implementasi.
4. [TODO](TODO.md): pekerjaan, dependensi, dan bukti selesai.

Jika terjadi konflik, jangan menebak diam-diam. Jelaskan konflik, gunakan arahan terbaru pengguna, lalu perbarui semua dokumen terkait. Jangan menduplikasi aturan hitung ke tempat lain tanpa merujuk kontrak CAP-04.

## Alur kerja

1. Aktifkan direktori proyek yang benar dengan Serena; baca initial instructions dan periksa onboarding. Pada fase dokumentasi tidak ada simbol aplikasi untuk diindeks.
2. Sebelum mengubah kode aplikasi, gunakan overview, symbol lookup, dan reference lookup Serena untuk memahami jalur yang terdampak. Baca file penuh hanya bila perlu.
3. Pilih skill ECC sesuai pekerjaan: product-capability untuk kontrak, tdd-workflow untuk logika domain, security-review untuk autentikasi/otorisasi, laravel-verification untuk verifikasi Laravel. Baca skill sebelum menggunakannya; jangan memasang salinan ECC baru.
4. Untuk API/library/framework/CLI, resolve library lalu query dokumentasi Context7 per konsep. Bila tidak tersedia, gunakan dokumentasi resmi dan laporkan batasannya.
5. Implementasikan irisan fitur beserta verifikasi sesuai risiko. Jangan menambah unit test yang hanya menyalin markup untuk perubahan kosmetik.
6. Tinjau keamanan, konsistensi PRD–ERD, dan regresi. Perbarui TODO hanya berdasarkan bukti aktual.
7. Laporkan apa yang selesai, yang diuji, hasil, dan keterbatasan. Bedakan tool terkonfigurasi dengan tool yang benar-benar dipakai.

## Lerd dan lingkungan

- Gunakan path absolut proyek bila sesi dimulai dari `/home/ilhamzp/Projects`.
- Discover `site action=list` sebelum operasi situs. Proyek ini terdaftar sebagai `penilaian-tahfidz` pada Lerd lokal.
- Baca `diag action=status` untuk DNS/TLD; jangan mengasumsikan domain atau TLS. Jangan menawarkan TLS jika managed DNS nonaktif.
- Gunakan Lerd untuk PHP, Composer, Artisan, database/service, dan logs; semua grouped tool wajib memiliki `action`.
- Discover `worker action=list` sebelum start; `exec action=vendor_bins` sebelum `vendor_run`; `logs action=sources` sebelum fetch.
- Tentukan engine database secara eksplisit. Untuk bootstrap clone Laravel yang dipindah dari SQLite: `db set`, `env setup`, kemudian wajib `framework setup`.
- Jangan menyunting file environment secara manual untuk wiring layanan. Override lokal milik `.lerd.local.yaml` tetap lokal.
- Deteksi package manager dari pin/lockfile. Jangan mengasumsikan npm, versi PHP/Node, atau nama worker.
- Jangan memanggil test yang memigrasi database sebelum memastikan database test terpisah dari data aplikasi, termasuk host/nama database dan config cache.
- Setelah perubahan request/database: aktifkan capture dumps, akses route relevan, lalu `optimize_route`; tindak lanjuti N+1. Laporkan jika route belum dapat diakses atau tool tidak tersedia.
- Verifikasi browser dengan Playwright pada domain Lerd aktual. Browser QA menggunakan akun/data test, bukan menghapus data guru.

## Batas perubahan

- Pertahankan perubahan pengguna. Jangan overwrite file, reset git, menghapus data, atau menjalankan migrate:fresh pada database aplikasi.
- Gunakan patch untuk perubahan file. Jangan menyimpan rahasia, data santri, dump produksi, atau kredensial dalam repository/log.
- Jangan mengklaim sumber mushaf sah, akses API berhasil, atau lisensi sesuai tanpa verifikasi aset yang benar-benar digunakan.
- Jangan menjalankan subagen kecuali diminta pengguna atau skill yang sedang berlaku.
- Jangan publish, deploy, mengirim pesan eksternal, atau melakukan pembelian sebagai efek samping implementasi.

## Definition of done

- Acceptance criteria fitur terkait terpenuhi dan dapat ditunjukkan.
- Aturan skor, histori, kepemilikan, dan integritas mushaf tidak dilanggar.
- Pemeriksaan yang relevan benar-benar dijalankan; kegagalan/batasan dicatat.
- PRD, ERD, TODO, dan kontrak API tetap selaras.
- Jangan membuat daftar command test fiktif. Tetapkan command konkret setelah scaffold dan scripts tersedia.
