# TODO — Penilaian Tahfidz

Status 21 September 2026: F1 selesai; F2 mempunyai prototipe metadata sintetis; F3 menyediakan santri, kelompok, kegiatan, serta fondasi program; F4 menyediakan rubrik berversi dan pratinjau skor. Pembuatan program nyata menunggu referensi produksi F2; sesi penilaian persisten adalah F5. `[x]` berarti artefak tersedia dan pemeriksaan terkait tercatat; `[ ]` belum selesai.

Referensi: [PRD](docs/PRD.md), [ERD](docs/ERD.md), [AI Rules](docs/AI_RULES.md), [AGENTS](AGENTS.md).

## F0 — Fondasi dokumen

- [x] Konfirmasi aktor: guru pribadi; penilaian gabungan.
- [x] Buat AGENTS.md dan AI Rules.
- [x] Buat PRD dengan kontrak skor, lifecycle, acceptance criteria, dan keputusan terbuka.
- [x] Buat ERD dengan ownership, immutable version, anotasi, dan revisi.
- [x] Susun urutan implementasi dan kriteria selesai.
- [ ] Validasi satu contoh rubrik nyata bersama guru (D-03; tidak menghalangi scaffold).

## F1 — Scaffold dan lingkungan

Dependensi: F0. Cakupan: CAP-01, fondasi semua kapabilitas.

- [x] Tentukan versi stack dan database (D-02), cek dokumentasi Context7 dan runtime Lerd.
- [x] Scaffold Laravel + Inertia + React/TypeScript; pin dependency dan package manager.
- [x] Konfigurasi Lerd melalui tools, discover DNS/domain, pilih DB secara eksplisit, env setup lalu framework setup.
- [x] Siapkan database test terpisah dan guard agar test tidak menyentuh data aplikasi.
- [x] Tetapkan scripts lint, typecheck, test, build berdasarkan scaffold; dokumentasikan command aktual.
- [x] Implementasikan bootstrap pemilik dan login; registrasi publik mati; tanpa kredensial default.
- [x] Buat kerangka UI Indonesia dan navigasi responsif.

Selesai bila: aplikasi terbuka pada domain aktual, login berfungsi, database test terbukti terisolasi, build/check dasar lulus. Simpan bukti di bagian Verifikasi.

## F2 — Sumber dan prototipe mushaf

Dependensi: F1 untuk integrasi; pemilihan aset boleh dikerjakan lebih awal. Cakupan: CAP-05, D-01.

- [x] Tentukan keluarga mushaf dan riwayah bersama guru: Madinah, Hafs ‘an ‘Asim, 604 halaman. Cetakan/revisi spesifik masih perlu dicocokkan dengan aset.
- [ ] Verifikasi akses, versi, lisensi teks/font/layout, distribusi/cache/backup, dan atribusi.
- [ ] Impor dataset dengan manifest/checksum dan laporan validasi; jangan menggunakan teks buatan AI.
- [x] Audit struktur paket kandidat QPC V2 di luar repository: 604 halaman, 9.060 baris, 114 surah, 6.236 ayat, identitas kata unik, dan kesesuaian JSON–SQLite; simpan hash hasil evaluasi. Ini belum impor atau validasi mushaf produksi.
- [x] Bangun prototipe metadata sintetis dengan navigasi dan penanda ayat/kata; tidak untuk penilaian.
- [x] Uji prototipe pada halaman contoh awal/tengah/akhir, peralihan surah/juz, metadata basmalah, jangkar kata lintas baris/halaman, zoom dan RTL; rendering edisi produksi masih menunggu aset.
- [x] Bangun tampilan ayat adaptif prototipe; jangkar pilihan tetap pada peralihan tampilan, halaman, dan refresh. Anotasi persisten belum ada.
- [ ] Validasi otomatis seluruh mapping serta review visual oleh guru kompeten sebelum rilis.

Selesai bila: sumber jelas, mapping valid, rendering cocok dengan edisi, penanda kata/ayat stabil. Jika D-01 belum diputuskan, lanjutkan modul lain dengan fixture metadata sintetis berlabel test, bukan mushaf produksi palsu.

## F3 — Santri, kegiatan, dan program

Dependensi: F1; referensi ayat produksi dari F2. Cakupan: CAP-01, CAP-02.

- [x] Implementasikan schema dan pemeriksaan ownership untuk master, program, versi, dan enrollment.
- [x] CRUD/arsip santri; kelompok opsional, termasuk histori keanggotaan.
- [x] Pengaturan jenis kegiatan dan flag kontribusi progres.
- [x] Program berversi, pemilih juz/surah/rentang, urutan belajar, preview union target dengan fixture sintetis test.
- [x] Enrollment dan perpindahan versi eksplisit dengan histori.
- [x] Uji akses silang, rentang invalid/overlap/lintas surah, arsip dengan histori.
- [ ] Aktifkan alur program nyata dan verifikasi browser penuh setelah dataset F2 sah, lengkap, dan aktif.

Selesai bila: program dan peserta dapat dikelola tanpa perubahan kode; histori target tetap stabil.

## F4 — Rubrik dan mesin penilaian

Dependensi: F1; tidak menunggu renderer. Cakupan: CAP-03, CAP-04.

- [x] Tulis test contoh hitung CAP-04 dan kasus batas sebelum implementasi mesin skor.
- [x] Rubrik draft/published/retired; clone versi beserta criteria/rules/bands.
- [x] Builder direct/deduction, bobot, potongan, ambang, dan predikat tanpa formula bebas.
- [x] Implementasikan normalisasi decimal, batas minimum, pembulatan, kelulusan sebelum pembulatan.
- [x] Pratinjau penilaian lengkap dengan asal setiap pengurangan; belum menyimpan sesi santri (F5).
- [x] Override beralasan dan validasi skala; catatan nonpenalti pada pratinjau.
- [x] Uji bobot invalid, input kosong, threshold per kriteria, predikat overlap, immutability, dan retry kejadian.
- [ ] Verifikasi alur builder/pratinjau penuh pada browser domain Lerd dengan akun uji, lalu audit query route terautentikasi.

Selesai bila: seluruh fixture hitung cocok, UI dan server konsisten, perubahan versi tidak mengubah histori.

## F5 — Sesi dan mushaf penilaian

Dependensi: F2, F3, F4. Cakupan: CAP-05, CAP-06.

- [ ] Buat draft dengan snapshot kegiatan, versi rubrik/edisi, planned ranges, enrollment opsional.
- [ ] Integrasikan mushaf, tombol kesalahan cepat, catatan, undo, nilai langsung.
- [ ] Pisahkan actual ranges dari target; validasi anotasi terhadap bacaan aktual.
- [ ] Autosave ACK, indikator gagal/offline, pemulihan draft, optimistic locking, idempotency.
- [ ] Finalisasi atomik dan snapshot hasil; double-click/retry tidak menggandakan data.
- [ ] Koreksi melalui draft revisi, swap current final, void beralasan, audit.
- [ ] Alur santri berikutnya tidak membawa skor/anotasi santri sebelumnya.
- [ ] Browser test: refresh, putus jaringan, dua tab, sesi parsial, finalisasi dua kali, koreksi dan batal koreksi.

Selesai bila: satu setoran nyata dapat diselesaikan; tidak ada klaim tersimpan palsu atau histori terhapus.

## F6 — Progres dan laporan

Dependensi: F5. Cakupan: CAP-07.

- [ ] Agregasi ayat unik sesuai current final/enrollment/flag progres; murajaah dihitung terpisah.
- [ ] Profil santri: riwayat, nilai berlabel rubrik, posisi terakhir, lokasi kesalahan berulang.
- [ ] Filter laporan, CSV aman, print stylesheet.
- [ ] Uji overlap, ulang setoran, sesi gagal lulus, revisi/void, ganti target versi, dan denominator ayat.
- [ ] Pastikan nilai/rubrik berbeda tidak dicampur menjadi kesimpulan kemampuan tanpa label.

Selesai bila: angka laporan dapat direkonsiliasi dengan data sesi dan ekspor sesuai filter.

## F7 — Backup, privasi, dan pemulihan

Dependensi: F5, schema stabil. Cakupan: CAP-08.

- [ ] Tetapkan format arsip terenkripsi, manifest versi, lifecycle file, dan pembatasan akses.
- [ ] Implementasikan backup; sumber mushaf disertakan hanya bila diizinkan.
- [ ] Restore staging, konfirmasi dampak, pre-restore backup, maintenance, atomic swap/rollback.
- [ ] Uji round-trip terisolasi; arsip rusak/password salah/schema tak cocok tidak menyentuh data aktif.
- [ ] Review akses ekspor, logs, cache lokal, logout/perangkat bersama, dan penyimpanan secrets.

Selesai bila: pemulihan terbukti, termasuk referensi edisi lama, dan data aktif terlindungi saat gagal.

## F8 — Verifikasi dan pilot

Dependensi: F1–F7 selesai, D-03/D-04 diselesaikan.

- [ ] Jalankan unit/integration/browser checks relevan, build/typecheck/lint yang tersedia.
- [ ] Review keamanan ownership, CSRF, login, file export/restore, dan audit.
- [ ] Playwright pada domain Lerd aktual: desktop/tablet/ponsel; keyboard, focus, contrast, RTL, touch.
- [ ] Ukur budget PRD dengan dataset sintetis; catat perangkat/jaringan/sampel dan p95.
- [ ] Aktifkan dumps, akses route utama, optimize_route; selesaikan N+1/regresi.
- [ ] Pilot bersama guru: rubrik nyata, setoran baru, murajaah, koreksi, laporan dan backup.
- [ ] Dokumentasikan hasil, keterbatasan, petunjuk penggunaan, instalasi dan pemulihan aktual.

Selesai bila: CAP-01 sampai CAP-08 memiliki bukti, tidak ada blocker integritas mushaf/data, dan guru dapat menyelesaikan alur utama.

## Backlog setelah MVP

- [ ] Banyak penguji/ujian terstruktur, jika diminta.
- [ ] Portal santri/wali, jika diminta.
- [ ] Rekaman audio dan retensi terpisah, jika diminta.
- [ ] Offline penuh dengan sinkronisasi konflik lintas perangkat.
- [ ] Bantuan AI setelah evaluasi akurasi dan validasi guru; bukan penentu nilai tanpa review.
- [ ] Dukungan edisi mushaf tambahan dengan pemetaan yang tervalidasi.

## Verifikasi aktual

- F0: dokumen awal dibuat; contoh rubrik nyata D-03 masih menunggu validasi guru.
- F1, 21 September 2026: Runtime Lerd PHP 8.5, Node 24/npm 11, MySQL 8.4; DNS `.test` tersedia. [Laravel 13](https://github.com/laravel/docs/blob/13.x/starter-kits.md), [autentikasi Laravel](https://github.com/laravel/docs/blob/13.x/authentication.md), [pengujian database](https://github.com/laravel/docs/blob/13.x/database-testing.md), dan [setup Inertia](https://github.com/inertiajs/docs/blob/main/v3/installation/client-side-setup.mdx) diperiksa melalui Context7. Lockfile berisi Laravel 13.32.0, Inertia Laravel 3.3.4, React 19.3.0, Inertia React 3.7.1, Vite 8.3.0.
- Lerd: `site list` menemukan proyek belum terdaftar; kemudian `site link`, `db set mysql`, `site tls_enable`, `env setup`, `framework setup` berhasil. Situs menjawab di `https://penilaian-tahfidz.test`; migrasi pengguna, cache, jobs berjalan. Database aplikasi `penilaian_tahfidz` dan test `penilaian_tahfidz_testing` dibuat terpisah. Akun percobaan pemilik dibuat kemudian atas permintaan pengguna; kredensial tidak disimpan di repository.
- Test: `vendor_run phpunit` lulus 20 test/102 assertions; guard memverifikasi koneksi `testing`, database aktif, penolakan database aplikasi dan configuration cache. `vendor_run pint --test`, `composer validate --strict`, `composer audit`, `npm audit`, `npm run check` lulus. `npm run test:browser` lulus 1 alur Playwright pada domain Lerd: salah/benar password, desktop 1440 px, tablet 768 px, ponsel 360 px, menu keyboard, refresh, logout/back, cookie aman, registrasi 404 dan request lintas situs 419. Tidak ada overflow horizontal pada tiga viewport.
- Lerd `site_doctor`: 0 kegagalan, 1 peringatan `POST /login` p95 488 ms (7 sampel), di atas median route umum 33 ms. `dumps_toggle` dan `optimize_route` awal menemukan query cache berulang pada rate limiter; setelah limiter memakai file, capture terbaru tidak lagi menampilkan temuan query berulang. Waktu login tetap perlu dipantau saat dataset dan perangkat pilot tersedia. Source test dan perintah pengguna ada di [README](README.md).
- F2, 21 September 2026: pilihan Madinah Hafs 604 halaman dicatat pada [status sumber](docs/MUSHAF_SOURCE.md). Prototipe lima halaman metadata sintetis berada di `/mushaf/prototype`; tidak ada teks/font/layout Al-Qur’an yang diimpor. Lisensi, akses unduh, checksum, kecocokan cetakan dan tinjauan guru masih tertunda. `npm run test:reference` lulus 2 tes, `lerd test` 20 tes/112 assertions, `npm run check` lulus. Playwright prototipe lulus pada domain Lerd, termasuk ponsel 360 px tanpa overflow. Bukti ini hanya untuk mekanika prototipe, bukan validasi mushaf produksi.
- Setelah route prototipe diakses, Lerd `optimize_route` tidak melaporkan temuan N+1/slow query baru untuk route tersebut; laporan yang muncul masih `POST /login` dari F1. Capture dumps dimatikan lagi setelah pemeriksaan.
- Audit lanjutan F2: validator `scripts/validate_mushaf_candidate.py` lulus atas mirror QUL QPC V2 604 halaman (commit dan hash tercatat pada [status sumber](docs/MUSHAF_SOURCE.md)); perubahan ID kata sengaja ditolak. Ini hanya validasi konsistensi struktur kandidat, bukan impor aset produksi/tashih/lisensi. Tahun cetak mushaf fisik belum diketahui. Jalur Quran Foundation memiliki ketentuan akun, atribusi, dan batas cache yang perlu dipenuhi; server unduhan resmi KFGQPC tidak dapat diakses dari lingkungan ini (timeout).
- F3, 21 September 2026: migrasi master dan tabel program/referensi minimal berhasil pada MySQL lokal. Halaman `/students` mendukung santri, kelompok, kegiatan, dan arsip. Halaman `/programs` menampilkan status menunggu referensi F2 pada database aplikasi; builder juz/surah/rentang dan enrollment diuji dengan fixture sintetis hanya pada database test. Versi terbit immutable; clone versi, union target, perpindahan peserta eksplisit, ownership, serta arsip dengan histori diuji. `lerd test` lulus 30 tes/245 assertions; `npm run check` dan Pint lulus. Playwright sebelumnya lulus 3 alur F3 master data; builder program belum bisa diverifikasi end-to-end pada domain Lerd karena dataset produksi belum aktif. Request tanpa login ke `/programs` mengarah ke `/login` (302). Daftar program mengambil versi/rentang/enrollment secara berkelompok agar query tidak bertambah per program. Evaluasi `optimize_route` terautentikasi menunggu data program nyata F2.
- F4, 21 September 2026: migration rubrik, versi, kriteria, aturan kesalahan, dan predikat berjalan pada MySQL aplikasi. `composer.json` mewajibkan `ext-bcmath`. Mesin skor CAP-04 dan route builder/pratinjau lulus 9 tes F4/85 assertions; keseluruhan 39 tes/330 assertions. Coverage pada tes F4: `RubricController` 100%, `RubricVersions` 94,9%, `ScoreCalculator` 97,7%; total proyek pada subset tes itu 46,4% karena modul lain tidak dijalankan. `npm run check` lulus. Xdebug coverage diaktifkan hanya saat pengukuran lalu dimatikan. Route `/rubrics` di domain Lerd mengalihkan tamu ke login (302); alur browser terautentikasi dan `optimize_route` belum dijalankan karena sesi akun percobaan tidak tersedia pada otomasi ini. Pratinjau belum menyimpan sesi/hasil santri (F5).
