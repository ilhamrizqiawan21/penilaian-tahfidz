# Pemeriksaan UI dan alur input

Tanggal: 22 September 2026. Situs: `https://penilaian-tahfidz.test`.

## Temuan dan perbaikan

| Temuan | Perbaikan |
| --- | --- |
| Navigasi modul hanya lengkap di Ringkasan. | Layout bersama dengan menu aktif, pengelompokan kerja/persiapan, menu ponsel, dan akses keluar di seluruh halaman. |
| Ringkasan didominasi informasi persiapan dan klaim kosong statis. | Pintasan sesi, santri, rubrik, dan laporan; tidak menyajikan angka atau status data yang tidak diperoleh dari server. |
| Santri, kelompok, dan kegiatan tampil sekaligus. | Pemilih bagian menampilkan satu pekerjaan; input yang belum disimpan tetap dipertahankan saat berganti bagian. |
| Pengisian santri berulang kurang mendapat umpan balik. | Pesan sukses setelah respons server, indikator menyimpan, serta fokus kembali ke kode setelah berhasil. Tombol ubah mengarahkan fokus ke form. |
| Select, textarea, dan tombol sekunder tidak konsisten. | Ukuran kontrol, fokus, jarak, border, dan target sentuh diseragamkan. Fieldset rubrik diratakan untuk mengurangi panel bersarang. |
| Status draf kurang menonjol. | Badge status dengan teks yang mengikuti state asli; bar aksi simpan melekat pada desktop dan kembali ke alur dokumen pada ponsel. |
| Label tahap implementasi muncul dalam UI. | Kode F3–F7 di eyebrow diganti label pekerjaan pengguna. |
| Belum ada aturan motion yang seragam. | Transisi kontrol 150–160 ms dan masuk halaman 220 ms; animasi/transisi dimatikan pada reduced motion. |

## Bukti aktual

- `npm run check`: lint, TypeScript, dan build lulus.
- `git diff --check`: lulus.
- `npm run test:browser -- tests/Browser/workspace.spec.ts`: **2 tes lulus**, 21,7 detik. Kredensial akun uji diberikan melalui `QA_EMAIL` dan `QA_PASSWORD`, tidak disimpan di repository.
- Delapan halaman terautentikasi diperiksa pada 1440, 768, dan 375 px: Ringkasan, Santri, Rubrik, Program, Sesi, Laporan, Backup, dan Prototipe mushaf. Login juga diperiksa pada ketiga ukuran: total 27 kombinasi, tanpa overflow horizontal atau error JavaScript pada pemeriksaan awal.
- Interaksi: login/logout, perpindahan halaman melalui Inertia, menu ponsel menutup setelah navigasi, Escape mengembalikan fokus ke tombol menu, pemilih bagian form mempertahankan input belum tersimpan, dan reduced motion.
- Screenshot desktop/ponsel Ringkasan, Santri, dan Rubrik ditinjau secara visual. Tidak ada baseline sebelumnya untuk menyatakan kelulusan regresi visual.
- Lerd dumps diaktifkan selama pemeriksaan akhir. `optimize_route` menghasilkan `routes: []`, median 98 ms dari 48 sampel pada saat pemeriksaan; bukan benchmark dengan dataset besar. Capture dikembalikan ke kondisi mati.
- Serena digunakan untuk aktivasi, overview, lookup simbol, dan referensi. Context7 digunakan untuk dokumentasi layout Inertia 3. Playwright MCP gagal karena profil sedang dipakai; verifikasi dijalankan dengan Playwright lokal dan Chrome terisolasi.

## Batas verifikasi

- Browser QA ini tidak membuat, menghapus, atau mengganti data aplikasi. Umpan balik setelah simpan ditinjau pada kode; submit CRUD penuh tidak dijalankan pada akun yang sudah ada. Tes foundation disesuaikan dengan pemilih bagian baru, tetapi tidak dijalankan karena fixture-nya mensyaratkan instalasi tanpa pemilik.
- Program dan sesi nyata masih menunggu referensi mushaf aktif. Workspace penilaian, finalisasi, profil dengan histori terisi, dan restore tidak dinyatakan terverifikasi end-to-end oleh pemeriksaan ini.
- Tidak ada audit axe/screen-reader atau pengukuran Core Web Vitals lengkap. Pemeriksaan ini bukan klaim kepatuhan WCAG menyeluruh.
- Kontrak skor, histori, database, autentikasi, dan integritas mushaf tidak diubah. Pekerjaan backend yang sudah ada di workspace dipertahankan.

Untuk mengulang pemeriksaan UI, sediakan variabel lingkungan akun uji lalu jalankan command Playwright di atas. Jangan memasukkan kredensial ke file test atau menjalankan suite foundation terhadap instalasi yang telah memiliki data pemilik.
