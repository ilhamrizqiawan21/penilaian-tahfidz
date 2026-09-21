# Penilaian Tahfidz

Aplikasi untuk guru tahfidz pribadi: program hafalan dinamis, penilaian gabungan, dan mushaf interaktif.

Status: F1 selesai; F2 dan F3 berjalan, fondasi F4 tersedia, 21 September 2026. Login pemilik, dashboard, prototipe mushaf metadata sintetis, pengelolaan santri/kelompok/kegiatan, program berversi, rubrik berversi, dan pratinjau skor tersedia. Pembuatan program nyata menunggu referensi mushaf produksi F2; sesi penilaian persisten, laporan, dan backup belum tersedia.

## Menjalankan aplikasi

Lingkungan lokal memakai Lerd, PHP 8.5, Node 24/npm 11, dan MySQL 8.4. Aplikasi tersedia pada `https://penilaian-tahfidz.test` di mesin yang telah mengaktifkan Lerd dan DNS `.test`. Dependensi dikunci dalam `composer.lock` dan `package-lock.json`; gunakan `lerd composer install` dan `npm ci` untuk memasangnya.

Setelah clone baru, gunakan `lerd link`, pilih MySQL saat `lerd setup`, dan jalankan tahap `lerd env` serta migrasi yang disediakan setup. Jangan jalankan `composer run setup`: prosedur Lerd memilih database dan menyiapkan environment terlebih dahulu. Situs saat ini memakai database aplikasi `penilaian_tahfidz` dan database test `penilaian_tahfidz_testing` yang terpisah. File `.env` dan `.env.lerd_override` bersifat lokal dan diabaikan Git.

Pemilik pertama dibuat sekali melalui `lerd artisan tahfidz:owner` pada terminal interaktif. Perintah meminta nama, email, dan kata sandi tersembunyi. Tidak ada registrasi publik, seeder akun demo, atau kredensial bawaan. Setelah akun dibuat, masuk melalui halaman login. Perintah akan menolak pembuatan akun kedua.

Setelah login, menu **Prototipe mushaf** membuka `/mushaf/prototype`. Lima halaman contoh di sana memakai nomor dan label kata sintetis untuk menguji navigasi serta jangkar. Tidak ada teks Al-Qur’an, layout edisi, anotasi, atau nilai yang tersimpan; halaman ini tidak dapat dipakai untuk penilaian. Target edisi dan hambatan sumber tercatat pada [status sumber mushaf](docs/MUSHAF_SOURCE.md).

Menu **Santri dan kegiatan** membuka `/students` untuk mengelola santri, kelompok opsional, serta jenis kegiatan. Menu **Program hafalan** membuka `/programs`. Builder rentang juz/surah/ayat, versi program, preview union, dan enrollment sudah diuji dengan data sintetis di database test. Pada database aplikasi, halaman menjelaskan bahwa penyusunan program terkunci sampai dataset ayat F2 terverifikasi dan aktif.

Menu **Rubrik penilaian** membuka `/rubrics` untuk membuat draf kriteria nilai langsung/pengurangan, bobot, potongan, ambang, dan predikat. Publikasi memvalidasi kontrak CAP-03; versi terbit tidak dapat diubah. Pratinjau nilai memakai mesin CAP-04 yang sama untuk draf dan pemeriksaan hasil final, tetapi belum menyimpan penilaian santri. Perhitungan memerlukan ekstensi PHP `bcmath` yang dinyatakan dalam `composer.json`.

## Pemeriksaan

- `lerd test`: test PHP termasuk autentikasi dan isolasi database. Guard menolak konfigurasi selain `lerd-mysql:3306/penilaian_tahfidz_testing` dan configuration cache aktif.
- `lerd composer run lint`: gaya kode PHP.
- `npm run check`: lint, typecheck TypeScript, dan build Vite.
- `npm run test:reference`: validasi mapping dan jangkar metadata sintetis.
- `python3 scripts/validate_mushaf_candidate.py /path/to/bundle`: audit lokal kandidat QUL QPC V2 terhadap dua arsip SQLite dan 604 JSON halaman. Paket eksternal tidak disertakan dalam repository; hasil audit bukan izin penggunaan atau tashih.
- `npm run test:browser`: Playwright pada domain Lerd aktual. Skrip memerlukan Google Chrome lokal dan menolak berjalan bila akun pemilik sudah ada; akun uji sementara dihapus setelah selesai. Pemeriksaan ini tidak mereset database aplikasi.
- `lerd composer validate --strict`, `lerd composer audit`, `npm audit`: validasi lockfile dan dependensi.

Perintah di atas adalah perintah proyek yang benar-benar tersedia. Jangan memakai test yang memigrasi database sebelum guard dan nama database test diverifikasi. Lihat [TODO](TODO.md) untuk status dan bukti hasil eksekusi terbaru.

## Dokumen

- [AGENTS.md](AGENTS.md): panduan kerja agen dalam repositori.
- [AI Rules](docs/AI_RULES.md): aturan teknis dan invariant domain.
- [PRD](docs/PRD.md): cakupan produk, perilaku, kriteria penerimaan, keputusan terbuka.
- [ERD](docs/ERD.md): model data logis dan batas integritas.
- [TODO](TODO.md): urutan implementasi dan bukti penyelesaian.
- [Sumber mushaf](docs/MUSHAF_SOURCE.md): pilihan edisi, kandidat aset, dan syarat impor produksi.

Cetakan/revisi sumber mushaf dan izin aset masih memerlukan verifikasi sebelum impor dan renderer produksi.
