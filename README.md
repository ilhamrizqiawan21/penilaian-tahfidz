# Penilaian Tahfidz

Aplikasi untuk guru tahfidz pribadi: program hafalan dinamis, penilaian gabungan, dan mushaf interaktif.

Status: F1 selesai; F2 masih menunggu izin dan sumber mushaf produksi. F3–F7 mempunyai implementasi dan pengujian otomatis dengan data sintetis, 21 September 2026. Alur penilaian nyata tetap menunggu F2 dan verifikasi browser terautentikasi.

## Menjalankan aplikasi

Lingkungan lokal memakai Lerd, PHP 8.5, Node 24/npm 11, dan MySQL 8.4. Aplikasi tersedia pada `https://penilaian-tahfidz.test` di mesin yang telah mengaktifkan Lerd dan DNS `.test`. Dependensi dikunci dalam `composer.lock` dan `package-lock.json`; gunakan `lerd composer install` dan `npm ci` untuk memasangnya.

Setelah clone baru, gunakan `lerd link`, pilih MySQL saat `lerd setup`, dan jalankan tahap `lerd env` serta migrasi yang disediakan setup. Jangan jalankan `composer run setup`: prosedur Lerd memilih database dan menyiapkan environment terlebih dahulu. Situs saat ini memakai database aplikasi `penilaian_tahfidz` dan database test `penilaian_tahfidz_testing` yang terpisah. File `.env` dan `.env.lerd_override` bersifat lokal dan diabaikan Git.

Pemilik pertama dibuat sekali melalui `lerd artisan tahfidz:owner` pada terminal interaktif. Perintah meminta nama, email, dan kata sandi tersembunyi. Tidak ada registrasi publik, seeder akun demo, atau kredensial bawaan. Setelah akun dibuat, masuk melalui halaman login. Perintah akan menolak pembuatan akun kedua.

Untuk mencoba fitur pada instalasi lokal yang sudah memiliki akun `demo@penilaian-tahfidz.test`, jalankan `lerd artisan db:seed --class=Database\\Seeders\\DemoSeeder --no-interaction`. Seeder ini hanya boleh berjalan pada `local/testing`, memakai konten mushaf sintetis yang diberi label, menolak database yang sudah berisi data, dan aman dijalankan ulang. Data demo mencakup santri, kelompok, kegiatan, program, rubrik, draf, hasil final, koreksi, pembatalan, dan laporan. Seeder tidak membuat akun atau menampilkan data demo pada instalasi produksi.

Setelah login, menu **Prototipe mushaf** membuka `/mushaf/prototype`. Lima halaman contoh di sana memakai nomor dan label kata sintetis untuk menguji navigasi serta jangkar. Tidak ada teks Al-Qur’an, layout edisi, anotasi, atau nilai yang tersimpan; halaman ini tidak dapat dipakai untuk penilaian. Target edisi dan hambatan sumber tercatat pada [status sumber mushaf](docs/MUSHAF_SOURCE.md).

Menu **Santri dan kegiatan** membuka `/students` untuk mengelola santri, kelompok opsional, serta jenis kegiatan. Menu **Program hafalan** membuka `/programs`. Builder rentang juz/surah/ayat, versi program, preview union, dan enrollment sudah diuji dengan data sintetis di database test. Pada database aplikasi, halaman menjelaskan bahwa penyusunan program terkunci sampai dataset ayat F2 terverifikasi dan aktif.

Menu **Rubrik penilaian** membuka `/rubrics` untuk membuat draf kriteria nilai langsung/pengurangan, bobot, potongan, ambang, dan predikat. Publikasi memvalidasi kontrak CAP-03; versi terbit tidak dapat diubah. Pratinjau nilai memakai mesin CAP-04 yang sama dengan sesi penilaian. Perhitungan memerlukan ekstensi PHP `bcmath` yang dinyatakan dalam `composer.json`.

Menu **Backup dan pemulihan** membuka `/backups`. Buat arsip dengan kata sandi minimal 12 karakter, lalu unduh dan simpan bersama kata sandi di tempat terpisah. Arsip tidak memuat akun login, kunci aplikasi, atau aset mushaf; instalasi tujuan harus sudah memiliki skema dan referensi mushaf (ID serta checksum) yang sama. Untuk restore, unggah arsip, masukkan kata sandi, tinjau jumlah baris yang akan diganti, lalu ketik `GANTI DATA` dan ulangi kata sandi. Aplikasi membuat backup keadaan saat ini sebelum mengganti data dalam transaksi. Jangan gunakan arsip ini sebagai satu-satunya salinan aset mushaf.

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
