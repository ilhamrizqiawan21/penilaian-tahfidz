# PRD — Penilaian Tahfidz

Versi 0.1 · 21 September 2026 · Status: kontrak awal untuk implementasi bertahap.

## Kapabilitas dan keputusan

Guru tahfidz pribadi mengelola santri dan target hafalan, menyimak melalui mushaf interaktif, mencatat kesalahan, serta memberi nilai gabungan yang dapat ditelusuri. Hasilnya membantu guru memilih materi berikutnya dan murajaah.

Keputusan pengguna yang telah dikonfirmasi:

- Pengguna utama adalah guru tahfidz pribadi.
- Penilaian gabungan nilai per kriteria dan pengurangan karena kesalahan.
- Juz/surah yang dipelajari serta kriteria penilaian ditentukan pengguna.
- Mushaf tersedia dalam proses penilaian.

Default rancangan yang dapat ditinjau: satu pemilik per instalasi, kelompok opsional, antarmuka Indonesia, satu edisi mushaf pada MVP, hasil nilai dinormalisasi 0–100, zona waktu profil awal Asia/Jakarta. Default ini bukan hasil wawancara lanjutan pengguna.

## Masalah dan hasil yang diharapkan

Guru membutuhkan satu tempat untuk materi, penilaian, dan catatan lokasi kesalahan. Sistem mengurangi perpindahan antara mushaf dan pencatatan, memberi penjelasan asal nilai, serta memisahkan hafalan unik dari pengulangan murajaah. Kebutuhan masih harus diuji pada penggunaan nyata; riset awal bukan bukti product-market fit.

## Aktor dan permukaan

- Guru/pemilik: seluruh pengaturan dan data pribadinya.
- Santri: objek pembelajaran, belum memiliki akun pada MVP.
- Permukaan: dashboard ringkas, santri, program, rubrik, mushaf/penilaian, laporan, pengaturan/backup.
- Laptop/tablet: mushaf dan panel penilaian berdampingan. Ponsel: mushaf dengan panel bawah tanpa kehilangan posisi bacaan.

## Cakupan dan acceptance criteria

### CAP-01 — Akses pribadi dan santri

- Login pemilik; tidak ada registrasi publik, akun default, atau data demo otomatis.
- Santri minimal memiliki nama dan kode unik per pemilik; kontak/catatan opsional. Kelompok belajar opsional dan boleh lebih dari satu.
- Pencarian dan arsip santri; arsip mempertahankan histori.
- Lulus verifikasi bila guru dapat menambah santri tanpa membuat kelas/lembaga dan akses objek milik pemilik lain ditolak.

### CAP-02 — Program dan materi dinamis

- Guru membuat nama program, target, tanggal opsional, kegiatan, serta urutan materi.
- Pemilihan melalui juz/surah/ayat diterjemahkan ke satu atau beberapa rentang ayat inklusif. Tidak mewajibkan urutan hafalan dari awal Al-Qur'an.
- Validasi batas ayat; preview cakupan; gabungan rentang dihitung sebagai union agar overlap tidak menggandakan target.
- Pembuatan dan publikasi program memakai dataset referensi yang berstatus aktif setelah validasi F2; fixture sintetis hanya untuk pengujian.
- Target program yang sudah diikuti dibekukan sebagai versi; perubahan membuat versi baru. Peserta lama tetap pada versi lama kecuali dipindahkan secara eksplisit.
- Penilaian boleh tanpa program. Jenis kegiatan tetap dipilih dari konfigurasi guru.
- Lulus verifikasi bila kombinasi surah dan rentang lintas surah tersimpan dengan benar, dan perubahan program tidak mengubah target historis.

### CAP-03 — Rubrik dan konfigurasi

- Guru menentukan kriteria, deskripsi, urutan, metode, min/max, bobot, ambang per kriteria, ambang akhir, dan predikat.
- Metode MVP: `direct` dan `deduction`. Keduanya dapat digunakan dalam satu rubrik.
- Jenis kesalahan: nama, tingkat berupa label, kriteria tujuan, nilai potongan per kejadian. Tujuan hanya kriteria deduction.
- Publikasi memvalidasi bobot tepat 100%, max > min, potongan positif, ambang 0–100, dan predikat tidak overlap. Bila dipakai, predikat harus mencakup 0–100.
- Published immutable; clone untuk versi baru. Template contoh opsional, bukan standar resmi.
- Lulus verifikasi bila rubrik baru dapat dibuat tanpa perubahan kode dan sesi lama tetap memakai versi sebelumnya.

### CAP-04 — Kontrak penilaian gabungan

Untuk kriteria i, min = a, max = b:

1. Direct: raw = input guru dalam [a,b].
2. Deduction: raw = max(a, b − jumlah potongan kejadian aktif). Nilai awal b.
3. Bila guru override: raw efektif = override dalam [a,b], wajib alasan; raw hasil hitung tetap disimpan.
4. Normalisasi n = 100 × (raw efektif − a) / (b − a).
5. Nilai akhir = Σ(n × bobot/100). Semua kriteria wajib lengkap pada finalisasi; draft boleh belum lengkap.
6. Kelulusan: nilai akhir >= ambang akhir DAN seluruh ambang kriteria yang diatur terpenuhi. Evaluasi sebelum pembulatan.
7. Tampilan nilai akhir dua desimal, round-half-up. Gunakan decimal presisi tinggi dalam perhitungan; jangan membulatkan subtotal untuk menentukan lulus.
8. Predikat memakai nilai akhir sebelum pembulatan, interval [bawah, atas), kecuali interval terakhir mencakup 100.

Contoh uji wajib (bukan standar penilaian):

| Kasus | Hasil yang diharapkan |
|---|---|
| Kelancaran deduction 0–100 bobot 50%, dua kesalahan masing-masing 5; tajwid deduction 0–100 bobot 30%, satu kesalahan 20; fashahah direct 0–100 bobot 20%, input 85 | 90×0,5 + 80×0,3 + 85×0,2 = 86,00 |
| Ambang akhir 80, ambang tajwid 85 pada kasus di atas | Tidak lulus walau nilai akhir 86 |
| Direct skala 1–5, input 4 | Normalisasi 75 |
| Potongan 120 pada skala 0–100 | Raw 0, tidak negatif |
| Nilai direct belum diisi | Draft boleh, finalisasi ditolak |
| Catatan tanpa penalti | Skor tidak berubah |
| Nilai 79,999 dengan ambang 80 | Tidak lulus; UI menjelaskan pembulatan walau tampilan 80,00 |

Satu kejadian penalti memotong satu kriteria. Jika guru memang ingin dua dampak, buat dua kejadian eksplisit. Undo menonaktifkan kejadian dan mengembalikan hitungan. Tidak ada pemotongan tambahan tersembunyi di nilai akhir.

### CAP-05 — Mushaf interaktif

- Satu edisi terverifikasi: navigasi juz/surah/ayat/halaman, zoom, posisi terakhir per sesi, sorot cakupan.
- Tampilan halaman mengikuti edisi; tampilan ayat adaptif untuk ponsel.
- Ketuk ayat/kata → pilih kesalahan atau catatan → lihat perubahan skor. Tombol undo tersedia.
- Anotasi tetap berada pada ayat/kata yang sama saat resize, refresh, dan ganti tampilan.
- Kata memerlukan mapping sumber yang valid; penandaan ayat tersedia sebagai fallback yang dijelaskan. Dukungan kata tetap menjadi kriteria rilis MVP setelah aset dipilih.
- Tidak mengubah teks sumber, mencampur edisi, atau menutupi harakat. Attribution dan identitas edisi dapat dilihat.
- Lulus verifikasi bila teks/layout/font kompatibel, semua jangkar valid, dan guru dapat memberi anotasi tanpa kehilangan posisi.

### CAP-06 — Sesi, autosave, dan koreksi

- Alur: pilih santri → kegiatan → program opsional dan materi → mushaf → anotasi/nilai → review → finalisasi.
- Simpan planned ranges dan actual ranges terpisah. Actual wajib subset planned; perluas planned secara eksplisit jika bacaan bertambah.
- Autosave draft; tampilkan menyimpan/tersimpan/belum sinkron/konflik. Refresh memulihkan data yang sudah ACK; draf lokal dapat ditawarkan untuk pemulihan bila diizinkan browser.
- Finalisasi menampilkan nilai, rincian, cakupan aktual, dan kelulusan. Sambungan server wajib.
- Tombol santri berikutnya mempertahankan pilihan kegiatan/rubrik, tetapi mengosongkan skor, anotasi, dan cakupan aktual.
- Idempotent retry tidak menggandakan kejadian/finalisasi. Konflik dua tab tidak overwrite diam-diam.
- Koreksi hasil final melalui draft revisi; versi final sebelumnya tetap tampil sampai revisi final sukses. Alasan koreksi wajib. Laporan tidak menghitung dua revisi sebagai dua setoran.
- Draft dapat dibatalkan. Hasil final dapat dibatalkan melalui void beralasan dengan audit, tanpa dihapus.

### CAP-07 — Progres dan laporan

- Progres dihitung dari union actual ranges pada current final yang lulus dan kegiatan yang dihitung sebagai pencapaian, dibatasi target versi program terkait.
- Simpan flag kontribusi progres dari jenis kegiatan pada sesi, sehingga perubahan master tidak mengubah histori.
- Persentase berdasarkan ayat unik/ayat target; label menjelaskan ini bukan persentase panjang bacaan. Nilai berdasarkan halaman hanya ditambahkan setelah definisinya jelas.
- Murajaah berulang meningkatkan frekuensi latihan, bukan hafalan unik. Void/revisi memperbarui agregat.
- Dashboard santri: cakupan tercapai, bacaan terakhir, frekuensi latihan, nilai, dan kesalahan berulang.
- Nilai beda versi rubrik diberi label; tidak membuat rata-rata lintas rubrik sebagai ukuran kemampuan tanpa penjelasan.
- Riwayat kesalahan menampilkan jumlah kejadian dan jumlah sesi yang menilai ayat tersebut; jangan menyebut frekuensi mentah sebagai akurasi.
- Laporan dapat difilter tanggal/santri/program/kegiatan/rubrik, diekspor CSV dan dicetak melalui tampilan print.
- Lulus verifikasi bila overlap, pengulangan, sesi parsial, pembatalan, dan revisi menghasilkan agregat yang benar.

### CAP-08 — Backup dan pemulihan

- Pemilik dapat membuat dan mengunduh backup terenkripsi data aplikasi dengan manifest schema/app version, referensi versi mushaf, dan checksum.
- Paket sumber mushaf hanya disertakan jika lisensi mengizinkan; jika tidak, manifest memandu pemulihan sumber yang sama sebelum sesi dapat dilanjutkan.
- Restore memvalidasi arsip/version/password/checksum pada staging, menunjukkan dampak, meminta konfirmasi penggantian, lalu membuat pre-restore backup.
- Restore atomik atau prosedur rollback teruji; mode maintenance selama pergantian data. Dilarang merge diam-diam.
- Lulus verifikasi dengan round-trip pada lingkungan terisolasi; password salah/arsip rusak tidak mengubah data saat ini.

## Lifecycle

- Master santri/kegiatan/kelompok: active → archived; dapat diaktifkan kembali.
- Rubrik/program version: draft → published → retired. Published/retired tetap immutable dan bisa direferensikan histori.
- Assessment: draft → final atau cancelled; final → superseded (hanya setelah revisi final) atau void.
- Satu assessment record mengelompokkan revisi; current_final menunjuk satu hasil aktif atau kosong. Draft revisi tidak masuk laporan.
- Edisi mushaf: imported → validated → active → retired. Sesi lama tetap membaca edisi pensiun, tidak dihapus.

## Kontrak interaksi aplikasi

Nama berikut adalah operasi logis. CreateDraft, SaveDraft, Finalize, Revise, dan Void mempunyai endpoint F5; ekspor laporan mempunyai endpoint F6; backup dan restore mempunyai endpoint F7.

| Operasi | Input utama | Output dan kegagalan |
|---|---|---|
| CreateDraft | santri, kegiatan, rubric_version, edition, planned ranges, enrollment opsional, mutation_id | ID dan revision; retry dengan mutation_id sama mengembalikan draf sama; tolak referensi asing/tidak valid |
| SaveDraft | ID, expected_revision, mutation_id, nilai/ranges/annotation delta | ACK revision; 409 konflik; 422 invalid; perubahan lokal dipertahankan |
| PreviewScore | rubric_version dan draft | rincian skor/incomplete; tidak memfinalisasi |
| Finalize | ID, expected_revision, mutation_id | snapshot hasil, current final; atomik; retry aman |
| Revise / Void | current final dan alasan | draft revisi atau pembatalan beraudit; tolak target stale |
| Export / Backup / Restore | scope sah, opsi operasi, konfirmasi restore | file/status; tidak bocor data/rahasia saat gagal |

Implementasi F7 menggunakan arsip `.pthbackup` terenkripsi XChaCha20-Poly1305 dengan kunci turunan Argon2id dari kata sandi pengguna. Manifest memuat fingerprint migrasi, versi format/aplikasi, checksum data, hitungan tabel, dan ID/checksum dataset serta edisi mushaf. Arsip memuat data aplikasi; akun login, APP_KEY, aset mushaf, dan riwayat backup tidak ikut. Restore hanya menerima skema dan referensi mushaf yang identik pada instalasi tujuan. Staging berlaku 30 menit; data diganti dalam transaksi setelah backup pra-restore berhasil.

## Kualitas, keamanan, dan ukuran keberhasilan

Target berikut adalah acceptance budget yang harus diukur, bukan hasil benchmark:

- Pada perangkat/jaringan uji yang dicatat, setelah aplikasi hangat: respons visual anotasi <100 ms; autosave ACK p95 <=1 detik pada jaringan lokal; buka sesi p95 <=2 detik.
- Dataset uji awal: 200 santri, 10.000 sesi, 100.000 anotasi; jangan memuat seluruh histori/mushaf sekaligus.
- Dari daftar santri hingga sesi siap dalam <=3 tindakan utama jika default sudah diatur.
- Target pilot: guru menyelesaikan setoran end-to-end tanpa bantuan; ukur waktu pencatatan dibanding cara sebelumnya, jangan mengklaim penghematan sebelum pilot.
- Keyboard/focus terlihat, label aksesibel, kontras memadai, tombol sentuh nyaman, RTL hanya pada area Arab, teks tidak terpotong pada layar 360 px.
- Privasi: data minimal, sesi autentikasi aman, ownership di server, ekspor/backup terbatas, audit mutasi penting.

## Non-goals MVP

Multi-lembaga, billing, portal santri/wali, ranking publik, banyak penguji, rekaman/audio storage, penilaian AI, generator teks ayat, editor formula bebas, dan sinkronisasi offline penuh lintas perangkat.

## Keputusan terbuka dan handoff

| ID | Keputusan | Dampak / kapan perlu |
|---|---|---|
| D-01 | Pengguna memilih Madinah, Hafs ‘an ‘Asim, 604 halaman. Cetakan/revisi fisik, sumber teks/font/layout, dan izin distribusi masih terbuka; lihat [status sumber F2](MUSHAF_SOURCE.md). | Memblokir impor/render produksi; prototipe metadata sintetis boleh berjalan |
| D-02 | Diputuskan pada F1: Laravel 13, Inertia 3, React 19/TypeScript, PHP 8.5, Node 24/npm 11, MySQL 8.4 | MySQL `penilaian_tahfidz` untuk aplikasi dan `penilaian_tahfidz_testing` untuk test; versi paket tepat ada di lockfile |
| D-03 | Rubrik nyata pertama dan daftar kesalahan guru | Template ilustrasi cukup untuk development, perlu validasi sebelum pilot |
| D-04 | Perangkat utama dan rencana hosting | Ukur UI/performa pada perangkat yang disepakati sebelum rilis |

Siap untuk fondasi dan prototipe dengan asumsi terdokumentasi. Implementasi mushaf produksi menunggu D-01. Lanjutkan melalui [TODO](../TODO.md); gunakan tdd-workflow untuk mesin skor dan review schema sebelum migration.

## Sumber riset awal

Ditelaah pada percakapan 21 September 2026; verifikasi ulang akses dan lisensi saat integrasi.

- [LPMQ: API Qur'an Kemenag](https://lajnah.kemenag.go.id/info-lpmq/berita-dan-artikel/berita/api-qur%E2%80%99an-kemenag-jadi-jembatan-digital-akses-mushaf-standar-indonesia.html): sumber resmi MSI; portal API belum berhasil diakses saat riset.
- [QUL: mushaf layout](https://qul.tarteel.ai/docs/mushaf-layout): kebutuhan script/font/layout kompatibel.
- [Tanzil: text license](https://tanzil.net/docs/Text_License): atribusi dan larangan perubahan teks.
- [Tanzil: metadata](https://tanzil.net/docs/Quran_Metadata): struktur surah/juz/halaman sesuai sumber.
- [Juknis MHQ Kota Batu 2022](https://batukota.kemenag.go.id/phocadownload/userupload/humas/Juknis%20MTQ%20TINGKAT%20KOTA%20BATU%20TAHUN%202022%20R.pdf): contoh aspek penilaian, bukan standar universal.
- [Tarteel: riwayat kesalahan](https://tarteel.ai/blog/so-youve-made-a-mistake-now-what/): pembanding alur tindak lanjut hafalan, bukan validasi kebutuhan pengguna ini.
- [Laravel starter kits](https://github.com/laravel/docs/blob/13.x/starter-kits.md): referensi usulan stack; belum dependency proyek.
