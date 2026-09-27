# TODO — Penilaian Tahfidz

Status 21 September 2026: F1 selesai; F2 mempunyai prototipe metadata sintetis; F3–F7 mempunyai implementasi dan pengujian otomatis. Alur nyata masih menunggu referensi produksi F2 serta verifikasi browser terautentikasi end-to-end. `[x]` berarti artefak tersedia dan pemeriksaan terkait tercatat; `[ ]` belum selesai.

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

## F2 — Sumber dan tampilan Al-Qur’an

Dependensi: F1 untuk integrasi; pemilihan aset boleh dikerjakan lebih awal. Cakupan: CAP-05, D-01.

- [x] Tentukan keluarga mushaf dan riwayah bersama guru: Madinah, Hafs ‘an ‘Asim, 604 halaman. Cetakan/revisi spesifik masih perlu dicocokkan dengan aset.
- [x] Verifikasi akses, versi, lisensi, checksum, distribusi dan atribusi teks Uthmani Tanzil v1.1 dari sumber resmi. Font/layout 604 halaman tetap menjadi gerbang terpisah.
- [x] Impor 114 surah, 6.236 ayat, 30 rentang juz, dan indeks kata verbatim dengan checksum terpancang; jangan menggunakan teks buatan AI.
- [x] Audit struktur paket kandidat QPC V2 di luar repository: 604 halaman, 9.060 baris, 114 surah, 6.236 ayat, identitas kata unik, dan kesesuaian JSON–SQLite; simpan hash hasil evaluasi. Ini belum impor atau validasi mushaf produksi.
- [x] Cocokkan lokasi kata dan glyph sampel halaman 1, 2, 302, 303, 604 dengan pratinjau QUL langsung; hasil cocok, tanpa menyamakan ID CMS dengan ID ekspor.
- [x] Konfirmasi guru: pembagian ayat dan baris lima halaman sampel QUL V2 cocok dengan mushaf fisik; tahun cetak masih belum diketahui dan tashih menyeluruh belum selesai.
- [x] Ganti halaman prototipe utama dengan pembaca Al-Qur’an dari dataset aktif; URL lama dialihkan dan data sintetis tetap diberi peringatan eksplisit.
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
- [x] Aktifkan alur program nyata dengan dataset Tanzil Uthmani v1.1 dan verifikasi workspace browser pada domain Lerd.

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

- [x] Buat draft dengan snapshot kegiatan, versi rubrik/edisi, planned ranges, enrollment opsional.
- [x] Integrasikan ayat/kata edisi aktif, tombol kesalahan cepat, catatan, undo, dan nilai langsung; instalasi lokal kini memakai Tanzil Uthmani v1.1, sementara fixture sintetis hanya untuk test/demo.
- [x] Pisahkan actual ranges dari target; validasi anotasi terhadap bacaan aktual.
- [x] Simpan dengan ACK, indikator gagal/sambungan tidak pasti, pemulihan draft, optimistic locking, idempotency permintaan.
- [x] Finalisasi atomik dan snapshot hasil; double-click/retry tidak menggandakan data.
- [x] Koreksi melalui draft revisi, swap current final, void beralasan, audit.
- [x] Alur santri berikutnya membuat draf baru tanpa skor/anotasi santri sebelumnya.
- [ ] Browser test: refresh, putus jaringan, dua tab, sesi parsial, finalisasi dua kali, koreksi dan batal koreksi.

Selesai bila: satu setoran nyata dapat diselesaikan; tidak ada klaim tersimpan palsu atau histori terhapus.

## F6 — Progres dan laporan

Dependensi: F5. Cakupan: CAP-07.

- [x] Agregasi ayat unik sesuai current final/enrollment/flag progres; murajaah dihitung terpisah.
- [x] Profil santri: riwayat, nilai berlabel rubrik, posisi terakhir, lokasi kesalahan beserta jumlah kejadian dan sesi.
- [x] Filter laporan, CSV aman, print stylesheet.
- [x] Uji overlap, ulang setoran, sesi gagal lulus, revisi/void, ganti target versi, dan denominator ayat.
- [x] Pastikan nilai/rubrik berbeda tidak dicampur menjadi kesimpulan kemampuan tanpa label.

Selesai bila: angka laporan dapat direkonsiliasi dengan data sesi dan ekspor sesuai filter.

## F7 — Backup, privasi, dan pemulihan

Dependensi: F5, schema stabil. Cakupan: CAP-08.

- [x] Tetapkan format arsip terenkripsi, manifest versi, lifecycle file, dan pembatasan akses.
- [x] Implementasikan backup data aplikasi; aset sumber mushaf dikecualikan karena izin F2 belum terbukti.
- [x] Restore staging, konfirmasi dampak, pre-restore backup, maintenance, atomic swap/rollback.
- [x] Uji round-trip terisolasi; arsip rusak/password salah/schema tak cocok tidak menyentuh data aktif.
- [x] Review akses ekspor, logs, cache lokal, logout/perangkat bersama, dan penyimpanan secrets.

Selesai bila: pemulihan terbukti, termasuk referensi edisi lama, dan data aktif terlindungi saat gagal.

## F8 — Verifikasi dan pilot

Dependensi: F1–F7 selesai, D-03/D-04 diselesaikan.

- [x] Jalankan unit/integration/browser checks relevan, build/typecheck/lint yang tersedia.
- [x] Review keamanan ownership, CSRF, login, file export/restore, dan audit.
- [x] Playwright pada domain Lerd aktual: desktop/tablet/ponsel; keyboard, focus, contrast, RTL, touch.
- [x] Ukur budget PRD dengan dataset sintetis; catat perangkat/jaringan/sampel dan p95.
- [x] Aktifkan dumps, akses route utama, optimize_route; selesaikan N+1/regresi.
- [ ] Pilot bersama guru: rubrik nyata, setoran baru, murajaah, koreksi, laporan dan backup (menunggu keputusan D-01 & D-03).
- [x] Dokumentasikan hasil, keterbatasan, petunjuk penggunaan, instalasi dan pemulihan aktual.

Selesai bila: CAP-01 sampai CAP-08 memiliki bukti, tidak ada blocker integritas mushaf/data, dan guru dapat menyelesaikan alur utama.

## Backlog setelah MVP

- [ ] Banyak penguji/ujian terstruktur, jika diminta.
- [ ] Portal santri/wali, jika diminta.
- [ ] Rekaman audio dan retensi terpisah, jika diminta.
- [ ] Offline penuh dengan sinkronisasi konflik lintas perangkat.
- [ ] Bantuan AI setelah evaluasi akurasi dan validasi guru; bukan penentu nilai tanpa review.
- [ ] Dukungan edisi mushaf tambahan dengan pemetaan yang tervalidasi.

## Verifikasi aktual

- Dashboard & Al-Qur’an, 22 September 2026: dashboard diubah menjadi pusat kerja berbasis data nyata (santri, draf, hasil, program, kesiapan, sesi terbaru). Navigasi prototipe diganti pembaca `/quran`; URL lama mengarah ke pembaca. Teks Uthmani Tanzil v1.1 dan metadata resmi diunduh, checksum diverifikasi, lalu 114 surah/6.236 ayat/30 juz/77.881 indeks kata diaktifkan tanpa menghapus histori dataset demo. PHPUnit 60 tes/520 assertions, Pint, `npm run check`, tes referensi, dan dua tes Playwright workspace pada 1440/768/375 px lulus. Layout cetak 604 halaman tetap belum diklaim selesai.

- F8, 22 September 2026: Pemeriksaan menyeluruh suite verifikasi F1–F8. PHPUnit 56 tes/479 assertions lulus; `npm run check` (ESLint, TypeScript strict, Vite build) lulus; `npm run test:reference` 2 tes lulus; Pint style passed. Diagnostik Lerd `site_doctor` melaporkan 0 failures dan 0 warnings (seluruh wiring env, dependensi, migrations, PHP 8.5, vhost, dan audit berstatus ok). Keamanan: proteksi CSRF 419 terbukti, registrasi 404, cookie session bertanda HttpOnly/Secure/SameSite=Lax, isolasi database testing terverifikasi. Rute publik dan terautentikasi dievaluasi tanpa temuan N+1 (`optimize_route: []`). Dokumentasi keterbatasan dan panduan pilot dicatat pada [UI Review](docs/UI_REVIEW.md), [AI Rules](docs/AI_RULES.md), dan [MUSHAF_SOURCE.md](docs/MUSHAF_SOURCE.md). Pilot operasional langsung bersama guru serta aktivasi aset produksi mushaf (D-01/D-03) tetap menjadi langkah integrasi riil berikutnya.

- Demo data, 22 September 2026: `DemoSeeder` idempoten untuk akun lokal yang sudah dibuat. Database lokal berisi 12 santri, 3 kelompok, 3 kegiatan, 2 program, 3 rubrik, 12 enrollment, 8 hasil final, 1 hasil superseded, 1 draf, dan 7 hasil laporan. Konten mushaf ditandai sintetis dan bukan sumber produksi. Tes `DemoSeederTest` lulus 3 tes/33 assertion; suite PHP lulus 56 tes/479 assertion; `npm run check` lulus. Dua tes browser read-only pada data demo lulus setelah memperbaiki pembacaan URL layout bersama.

- UI, 22 September 2026: layout bersama, pintasan Ringkasan, pemisahan form santri/kegiatan/kelompok, umpan balik simpan, kontrol responsif, status draf, dan reduced motion diperbarui. `npm run check`, `git diff --check`, dan dua tes `workspace.spec.ts` lulus pada akun uji yang sudah ada tanpa mutasi data aplikasi. Pemeriksaan awal 27 kombinasi halaman/viewport tidak menemukan overflow atau error JavaScript; capture akhir Lerd `optimize_route` tidak menampilkan route bermasalah. Detail dan batas verifikasi ada di [UI Review](docs/UI_REVIEW.md). Ini tidak menandai alur setoran nyata F5 atau pilot F8 selesai.

- F0: dokumen awal dibuat; contoh rubrik nyata D-03 masih menunggu validasi guru.
- F1, 21 September 2026: Runtime Lerd PHP 8.5, Node 24/npm 11, MySQL 8.4; DNS `.test` tersedia. [Laravel 13](https://github.com/laravel/docs/blob/13.x/starter-kits.md), [autentikasi Laravel](https://github.com/laravel/docs/blob/13.x/authentication.md), [pengujian database](https://github.com/laravel/docs/blob/13.x/database-testing.md), dan [setup Inertia](https://github.com/inertiajs/docs/blob/main/v3/installation/client-side-setup.mdx) diperiksa melalui Context7. Lockfile berisi Laravel 13.32.0, Inertia Laravel 3.3.4, React 19.3.0, Inertia React 3.7.1, Vite 8.3.0.
- Lerd: `site list` menemukan proyek belum terdaftar; kemudian `site link`, `db set mysql`, `site tls_enable`, `env setup`, `framework setup` berhasil. Situs menjawab di `https://penilaian-tahfidz.test`; migrasi pengguna, cache, jobs berjalan. Database aplikasi `penilaian_tahfidz` dan test `penilaian_tahfidz_testing` dibuat terpisah. Akun percobaan pemilik dibuat kemudian atas permintaan pengguna; kredensial tidak disimpan di repository.
- Test: `vendor_run phpunit` lulus 20 test/102 assertions; guard memverifikasi koneksi `testing`, database aktif, penolakan database aplikasi dan configuration cache. `vendor_run pint --test`, `composer validate --strict`, `composer audit`, `npm audit`, `npm run check` lulus. `npm run test:browser` lulus 1 alur Playwright pada domain Lerd: salah/benar password, desktop 1440 px, tablet 768 px, ponsel 360 px, menu keyboard, refresh, logout/back, cookie aman, registrasi 404 dan request lintas situs 419. Tidak ada overflow horizontal pada tiga viewport.
- Lerd `site_doctor`: 0 kegagalan, 1 peringatan `POST /login` p95 488 ms (7 sampel), di atas median route umum 33 ms. `dumps_toggle` dan `optimize_route` awal menemukan query cache berulang pada rate limiter; setelah limiter memakai file, capture terbaru tidak lagi menampilkan temuan query berulang. Waktu login tetap perlu dipantau saat dataset dan perangkat pilot tersedia. Source test dan perintah pengguna ada di [README](README.md).
- F2, 21 September 2026: pilihan Madinah Hafs 604 halaman dicatat pada [status sumber](docs/MUSHAF_SOURCE.md). Prototipe lima halaman metadata sintetis berada di `/mushaf/prototype`; tidak ada teks/font/layout Al-Qur’an yang diimpor. Lisensi, akses unduh, checksum, kecocokan cetakan dan tinjauan guru masih tertunda. `npm run test:reference` lulus 2 tes, `lerd test` 20 tes/112 assertions, `npm run check` lulus. Playwright prototipe lulus pada domain Lerd, termasuk ponsel 360 px tanpa overflow. Bukti ini hanya untuk mekanika prototipe, bukan validasi mushaf produksi.
- Setelah route prototipe diakses, Lerd `optimize_route` tidak melaporkan temuan N+1/slow query baru untuk route tersebut; laporan yang muncul masih `POST /login` dari F1. Capture dumps dimatikan lagi setelah pemeriksaan.
- Audit lanjutan F2: validator `scripts/validate_mushaf_candidate.py` lulus atas mirror QUL QPC V2 604 halaman (commit dan hash tercatat pada [status sumber](docs/MUSHAF_SOURCE.md)); perubahan ID kata sengaja ditolak. Ini hanya validasi konsistensi struktur kandidat, bukan impor aset produksi/tashih/lisensi. Tahun cetak mushaf fisik belum diketahui. Jalur Quran Foundation memiliki ketentuan akun, atribusi, dan batas cache yang perlu dipenuhi; server unduhan resmi KFGQPC tidak dapat diakses dari lingkungan ini (timeout).
- Pemeriksaan silang F2: `scripts/compare_mushaf_live_samples.py` lulus pada lima halaman sampel melawan pratinjau QUL langsung. Unduhan QUL meminta login; FAQ mewajibkan peninjauan lisensi per sumber. Arsip setup dalam mirror ternyata bukan ZIP sah. Bukti kecocokan cetakan fisik, tashih oleh guru, checksum unduhan resmi, dan izin distribusi/cache/backup masih belum ada; status produksi tetap tertahan. Detail di [status sumber](docs/MUSHAF_SOURCE.md).
- F3, 21 September 2026: migrasi master dan tabel program/referensi minimal berhasil pada MySQL lokal. Halaman `/students` mendukung santri, kelompok, kegiatan, dan arsip. Halaman `/programs` menampilkan status menunggu referensi F2 pada database aplikasi; builder juz/surah/rentang dan enrollment diuji dengan fixture sintetis hanya pada database test. Versi terbit immutable; clone versi, union target, perpindahan peserta eksplisit, ownership, serta arsip dengan histori diuji. `lerd test` lulus 30 tes/245 assertions; `npm run check` dan Pint lulus. Playwright sebelumnya lulus 3 alur F3 master data; builder program belum bisa diverifikasi end-to-end pada domain Lerd karena dataset produksi belum aktif. Request tanpa login ke `/programs` mengarah ke `/login` (302). Daftar program mengambil versi/rentang/enrollment secara berkelompok agar query tidak bertambah per program. Evaluasi `optimize_route` terautentikasi menunggu data program nyata F2.
- F4, 21 September 2026: migration rubrik, versi, kriteria, aturan kesalahan, dan predikat berjalan pada MySQL aplikasi. `composer.json` mewajibkan `ext-bcmath`. Mesin skor CAP-04 dan route builder/pratinjau lulus 9 tes F4/85 assertions; keseluruhan 39 tes/330 assertions. Coverage pada tes F4: `RubricController` 100%, `RubricVersions` 94,9%, `ScoreCalculator` 97,7%; total proyek pada subset tes itu 46,4% karena modul lain tidak dijalankan. `npm run check` lulus. Xdebug coverage diaktifkan hanya saat pengukuran lalu dimatikan. Route `/rubrics` di domain Lerd mengalihkan tamu ke login (302); alur browser terautentikasi dan `optimize_route` belum dijalankan karena sesi akun percobaan tidak tersedia pada otomasi ini. Pratinjau belum menyimpan sesi/hasil santri (F5).
- F5, 21 September 2026: migrasi tabel sesi, edisi/kata, receipt, dan audit diterapkan secara aditif pada MySQL aplikasi. Enam tes F5 menggunakan dataset sintetis dan database testing terpisah; memeriksa kepemilikan, route kerja, penyimpanan dan retry, cakupan aktual, finalisasi, revisi, void, serta undo. Suite lengkap lulus 45 tes/400 assertions; `npm run check` dan Pint lulus. Route `/assessments` pada domain Lerd mengalihkan tamu ke login. Lerd site doctor: 0 kegagalan, 0 peringatan; composer audit berstatus unknown. `optimize_route` belum memiliki traffic sesi terautentikasi. Uji browser terautentikasi, jaringan putus, dua tab, dan setoran nyata tetap menunggu akun pengujian serta referensi produksi F2.
- F6, 21 September 2026: route `/reports`, profil `/reports/students/{student}`, dan CSV `/reports/export` memakai current final milik pemilik. Progres dihitung sebagai union ayat aktual yang lulus per enrollment dan target versi, dengan murajaah terpisah; laporan memberi label rubrik dan tidak merata-ratakan versi berbeda. Tiga tes F6 memakai fixture sintetis di database testing terpisah untuk overlap, denominator target overlap, gagal lulus, murajaah, revisi/void, perpindahan versi, ownership, filter tanggal/program, jumlah kejadian/sesi kesalahan, dan CSV formula injection. Suite lengkap lulus 48 tes/425 assertions; `npm run check`, Pint, dan `git diff --check` lulus. Playwright memastikan `/reports` mengalihkan tamu ke login; Lerd site doctor 0 kegagalan/0 peringatan, `optimize_route` belum mendapat traffic terautentikasi. Profil/laporan browser dengan sesi guru dan data mushaf produksi tetap perlu divalidasi setelah F2 aktif.
- F7, 21 September 2026: arsip `.pthbackup` memakai Argon2id + XChaCha20-Poly1305 dan checksum SHA-256; `storage/app/private` tidak tersambung ke storage publik. Manifest mencatat versi format/aplikasi, fingerprint migrasi, jumlah tabel, ID/checksum dataset dan seluruh edisi termasuk yang pensiun. Tidak ada akun/password, APP_KEY, atau aset mushaf dalam arsip. Restore melalui staging 30 menit, pratinjau jumlah baris, frasa konfirmasi, backup pra-restore, mode pemeliharaan, dan transaksi database; permintaan ganda ditolak. Lima tes F7 pada database `penilaian_tahfidz_testing` lulus: round-trip, enkripsi tak membocorkan nama, owner scope, password/arsip rusak, skema/sumber berbeda, dan rollback saat insert gagal. Suite penuh 53 tes/446 assertions; `npm run check`, Pint, dan `git diff --check` lulus. Migrasi F7 aditif diterapkan ke database aplikasi. Playwright memastikan `/backups` mengalihkan tamu ke login; Lerd site doctor 0 kegagalan/0 peringatan, composer audit unknown, dan `optimize_route` belum memiliki traffic F7 terautentikasi. Berkas unduhan di perangkat bersama harus dihapus pengguna setelah dipindah ke penyimpanan aman; alur browser login lengkap dan pemulihan lintas instalasi masih perlu pilot dengan referensi F2 yang sah.
