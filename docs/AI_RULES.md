# AI Rules

Status: aturan implementasi v0.1, 21 September 2026. Baca bersama [PRD](PRD.md), [ERD](ERD.md), dan [panduan agen](../AGENTS.md).

## 1. Batas produk

- Satu guru pemilik per instalasi MVP; banyak santri. Kelompok belajar opsional.
- Kriteria, bobot, kesalahan, predikat, jenis kegiatan, target dan urutan materi adalah konfigurasi database. Tidak ada daftar domain yang wajib di-hardcode.
- State sistem dan metode perhitungan boleh berupa enum terbatas. Pengguna tidak mengeksekusi kode atau ekspresi bebas.
- Teks Al-Qur'an dan struktur kanonis bukan CRUD pengguna. Pengguna memilih rentang dari referensi, bukan mendefinisikan ulang juz/surah.
- Contoh rubrik hanya template opsional yang disalin dan disimpan; jangan menyebutnya standar resmi.

## 2. Arsitektur yang direncanakan

- Modular monolith Laravel 13 + Inertia 3 + React 19/TypeScript dengan MySQL 8.4 sudah terpasang pada F1. F3 telah menambah santri, kelompok, kegiatan, program berversi, dan enrollment. F4 menambah rubrik berversi dan pratinjau skor memakai BCMath; sesi penilaian persisten masih F5. Referensi mushaf produksi F2 belum diaktifkan sehingga pembuatan program nyata masih terkunci.
- PHP 8.5 dan Node 24/npm 11 dipilih dari runtime Lerd. Versi dependency aktual tercatat pada lockfile; jangan menganggap F2–F8 sudah berjalan.
- Pisahkan modul QuranReference, Students, Programs, Rubrics, Assessments, Reports, Backup.
- Logika skor dan progres berada di service/domain yang bisa diuji, bukan controller atau komponen UI.
- Server merupakan otoritas perhitungan dan finalisasi. Pratinjau frontend harus memakai kontrak yang sama; nilai kiriman browser tidak dipercaya.
- Relasi penting memakai foreign key dan constraint. JSON terbatas untuk snapshot/audit, bukan pengganti semua relasi.

## 3. Skor dan histori

- Ikuti CAP-04 persis: metode langsung/pengurangan per kriteria; normalisasi; bobot total 100%; pembulatan akhir; batas minimum; kelulusan menggunakan hasil belum dibulatkan.
- Gunakan decimal/fixed-point untuk bobot, skor, dan potongan. Jangan memakai floating-point biner sebagai sumber nilai final.
- Nilai kosong bukan nol. Larang finalisasi jika kriteria wajib belum diisi atau cakupan aktual kosong.
- Satu anotasi penalti MVP menunjuk satu aturan kesalahan dan satu kriteria pengurangan; catatan biasa tidak memotong nilai.
- Published rubric immutable. Perubahan membuat versi draft baru. Sesi mengikat versi dan edisi sejak dibuat.
- Finalisasi transaksi atomik: skor, hasil kelulusan, cakupan, anotasi, dan audit harus konsisten.
- Hasil final tidak diedit langsung. Koreksi membuat draft revisi, versi lama tetap current sampai revisi final berhasil; laporan menghitung current final saja.
- Override skor hanya dengan alasan, jejak skor sebelum/sesudah, dan batas skala valid. Tidak ada bypass syarat kelulusan tersembunyi.
- Penghapusan master yang direferensikan diganti arsip. Jangan cascade-delete histori penilaian atau referensi mushaf.

## 4. Mushaf

- Impor sumber dengan identitas, versi, atribusi, lisensi, checksum, dan validasi cakupan. Tidak menghasilkan teks ayat dengan AI/OCR sebagai sumber produksi.
- Pasangkan script, font, layout dari edisi yang kompatibel. Jangan mencampur indeks kata antarpenyedia tanpa pemetaan tervalidasi.
- Simpan anotasi di tabel terpisah, berjangkar ayat dan word ID sumber; bukan offset piksel atau posisi karakter yang berubah saat resize.
- Teks asli tidak dimodifikasi untuk pencarian. Jika perlu, gunakan indeks pencarian terpisah.
- Perbedaan basmalah, tanda ayat, akhir surah, dan pergantian halaman harus diuji terhadap edisi terpilih.
- Sediakan teks/label untuk penanda; jangan mengandalkan warna saja atau menutupi harakat dengan highlight.
- Sumber API gagal tidak boleh menyebabkan hilangnya sesi. Impor/cache lokal hanya jika ketentuan sumber mengizinkan.

## 5. Autosave, konflik, dan keamanan

- Semua mutasi memeriksa pemilik, state, validasi input, expected revision dan idempotency key yang relevan.
- Retry key yang sama dengan payload sama mengembalikan hasil sebelumnya; payload berbeda dengan key sama ditolak.
- Dua tab tidak boleh saling menimpa diam-diam. Konflik revision meminta reload/rekonsiliasi dengan draf pengguna dipertahankan.
- Status tersimpan hanya setelah ACK server. Saat offline tampilkan belum tersinkron; finalisasi membutuhkan server.
- Draf pemulihan lokal harus dibatasi akun, memiliki expiry, dibersihkan saat logout, dan tidak menyimpan token. Risiko perangkat bersama perlu dijelaskan pada UI pemulihan.
- Autentikasi wajib; registrasi publik mati. Bootstrap pemilik tidak memakai kredensial default yang dikomit.
- Setiap santri/program/sesi/ekspor milik guru terautentikasi; uji akses silang walaupun MVP hanya satu pemilik.
- Terapkan proteksi CSRF, session aman, rate limit login, escaping catatan dan pembatasan ukuran file/input.
- Minimalisasi data santri. Log/audit tidak berisi password, token, teks catatan sensitif, atau isi backup.
- Ekspor hanya data terotorisasi; tangani spreadsheet formula injection pada CSV.
- Backup terenkripsi dan akses terbatas; restore memerlukan konfirmasi, pre-restore backup, validasi versi/checksum, dan jalur rollback. Jangan merekam key dalam arsip yang sama.

## 6. Verifikasi

- Unit: skor, bobot, potongan, pembulatan, overlap rentang, agregasi progres.
- Integrasi: ownership, immutable version, finalisasi atomik, retry idempotent, konflik revision, revisi current final, restore.
- Browser: tambah santri → pilih materi → anotasi mushaf → nilai langsung → simpan final → lihat progres; juga refresh/gangguan jaringan dan ponsel/tablet.
- Mushaf: validasi otomatis seluruh mapping dan review visual sampel representatif; tinjauan guru kompeten sebelum rilis.
- Jalankan test database hanya pada database terisolasi. Verifikasi latensi dan query dengan data sintetis yang ukurannya dicatat.
- Jangan menandai TODO selesai jika hanya membuat kode tanpa acceptance check terkait.
