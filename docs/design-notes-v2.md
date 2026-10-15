# Referensi dan keputusan tampilan v2

Ditelaah 16 Oktober 2026 WIB. Referensi membantu pilihan rancangan, bukan bukti efektivitas untuk pengguna Lola. Efektivitas perlu dinilai bersama pengguna dengan tugas dan instrumen yang disepakati.

## Sumber yang diperiksa

1. [Gelanggang Petang / reservasi-futsal](https://github.com/aryasis87/reservasi-futsal/tree/8a397b6c0f42517474aa122317a00b97c882ec96): README dan gambar `public/og.jpg` pada commit tersebut diperiksa. Ini prototipe futsal fiktif yang menyimpan data lokal di browser. Referensi visual: suasana papan skor gelap dengan aksen terang, harga dekat pilihan lapangan, dan tanggal cepat. Tidak mengambil kode/aset, tarif, kebijakan, atau klaim kesiapan operasionalnya.
2. W3C, [Understanding Target Size (Minimum)](https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum.html), diperiksa melalui [sumber resmi GitHub](https://github.com/w3c/wcag/blob/23bad5904949904275a08f1c6e395b78a02aa6f3/understanding/22/target-size-minimum.html). Panduan membahas minimum 24×24 CSS px beserta pengecualian jarak. Demo ini memilih kontrol utama minimal 48px sebagai keputusan desain sendiri; 48px tidak diklaim sebagai minimum wajib sumber tersebut.
3. W3C, [Understanding Reflow](https://www.w3.org/WAI/WCAG22/Understanding/reflow.html), melalui [sumber resmi GitHub](https://github.com/w3c/wcag/blob/23bad5904949904275a08f1c6e395b78a02aa6f3/understanding/21/reflow.html). Panduan menjelaskan reflow konten horizontal pada lebar setara 320 CSS px. Halaman penerbit langsung ditolak proxy cloud, sehingga isi diverifikasi dari repository resmi, bukan hanya judul/URL.

## Penerapan

- Tema olahraga navy–lime, ilustrasi garis lapangan dari CSS, angka harga tegas; formulir tetap terang agar mudah dibaca. Tidak menambah font atau gambar jarak jauh yang harus diunduh pengguna.
- Hero lebih ringkas pada handphone, empat lapangan berupa kartu dua kolom, serta tombol Hari ini/Besok. Dropdown tanggal/jam/durasi tetap memakai kontrol browser.
- Jam akhir tengah malam diberi penanda hari berikutnya; tanggal akhir lengkap ditampilkan pada rincian.
- DP tetap per booking ditulis dekat pilihan pembayaran. Opsi mendadak yang tidak tersedia diberi penjelasan; nominal akhir mengikuti server.
- Pegawai memilih jenis pembayaran, melihat nominal, lalu meninjau referensi, pemesan, lapangan, jadwal dan metode sebelum konfirmasi. Kolom nominal bebas dihapus dari Tahap 1.
- Menu pegawai membungkus teks pada layar kecil. Setelah memilih booking di ponsel, rincian ditampilkan dan difokuskan; preferensi pengurangan animasi dihormati.
- Penanda mulai bermain terpisah dari status tagihan dan reservasi. Penjelasan tidak menyatakan sistem mengendalikan pintu/akses lapangan.

File utama: `frontend/src/app/globals.css`, `page.tsx`, `layout.tsx`, `components/BookingForm.tsx`, `PaymentForm.tsx`, `BookingDetails.tsx`, `AdminDashboard.tsx`, dan `HoldNotice.tsx`.

## Pemeriksaan dan batasnya

Pengujian browser memeriksa alur desktop/emulasi Pixel 7, overflow horizontal pada halaman/form/admin, lebar 320px, dan ukuran kontrol utama. Tangkapan layar dipakai untuk pemeriksaan visual internal. Ini bukan audit WCAG menyeluruh, evaluasi handphone nyata/Safari, review independen, atau UAT. Tidak ada paper, DOI, angka peningkatan kecepatan, maupun klaim penurunan kesalahan pengguna yang dijadikan hasil penelitian.
