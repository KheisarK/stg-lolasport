# Keputusan prototipe Lola Sport

Semua kebijakan pada bagian ini adalah **asumsi prototipe—perlu ditinjau pengelola**, bukan persetujuan atas PRD. PRD v0.1 disalin utuh dari lampiran ke `docs/PRD.md`; tidak diubah menjadi spesifikasi final.

## Penerimaan cakupan

- Pelanggan tanpa akun memesan lapangan/waktu; backend menghitung harga dan menolak bentrok.
- Pegawai login, melihat jadwal, memasukkan booking WA/onsite, mencatat DP dan pelunasan pada booking yang sama.
- Status pelanggan hanya dapat dibaca dengan token acak rahasia; jadwal publik tidak mengungkap pemesan.
- Pembayaran manual demo positif, tidak berlebih, tahan pengiriman ulang dan permintaan bersamaan.
- Booking tanpa pembayaran kedaluwarsa atau dapat dibatalkan; riwayat dipertahankan.
- Rekap menghitung pembayaran valid berdasarkan tanggal Jakarta, bukan nilai booking.
- Jalur UI handphone, pengujian API, pengujian konkurensi, build, CI, dan petunjuk staging tersedia.

## Aturan sementara

Konfigurasi terpusat di `backend/config/lola.php`, tarif awal di seeder lapangan.

- Empat lapangan: Futsal Sintetis Rp150.000/jam, Futsal Vinil Rp120.000/jam, Badminton 1 dan 2 Rp50.000/jam. Ini data contoh, bukan tarif Lola.
- Buka 07.00–23.00 Asia/Jakarta setiap hari. Mulai pada jam bulat, durasi 1–3 jam; booking paling jauh 90 hari.
- Minimum pembayaran pertama untuk opsi DP: 30% total, dibulatkan ke atas ke rupiah utuh. Opsi penuh memerlukan pembayaran pertama sebesar total.
- Booking baru `pending`, menahan slot selama 20 menit. Pembayaran pertama valid sebelum tenggat mengubahnya menjadi `confirmed`; tidak ada gateway/uang nyata.
- Status pembayaran dihitung terpisah: `unpaid`, `partial`, `paid` dari jumlah pembayaran. Pilihan DP/penuh sendiri bukan pembayaran.
- Pending yang tenggatnya lewat dianggap `expired` langsung pada pembacaan/pemeriksaan konflik, tanpa scheduler. Baris database boleh tetap pending; status efektif tetap expired. Tidak boleh menerima pembayaran lagi.
- `confirmed` tetap memblokir jadwal. Penyelesaian/no-show belum diotomatisasi.
- Hanya pegawai dapat membatalkan pending yang belum kedaluwarsa dan belum dibayar. Booking dengan pembayaran ditolak pembatalannya karena refund/koreksi belum dibuat. Tidak ada penghapusan riwayat.
- Reschedule, refund, koreksi transaksi, pengaturan tarif di UI, penutupan lapangan, peran pemilik terpisah, dan dampak operasional di luar cakupan implementasi ini.
- Pelanggan menggunakan tautan dengan token di fragment URL; siapa pun yang memperoleh tautan dapat membaca booking tersebut. Jangan membagikan tautan/screenshot token.

## Arsitektur dan batas

Browser → Next.js (tampilan dan proxy `/api`) → Laravel (session, otorisasi, aturan, perhitungan) → SQLite (penyimpanan).

Satu origin browser menghindari kebutuhan CORS lintas origin. Session Laravel memakai cookie HttpOnly, SameSite=Lax; CSRF token diperoleh dari `/api/csrf` dan dikirim pada request perubahan. Cookie Secure wajib di HTTPS staging. Login meregenerasi session, logout membatalkannya. Percobaan login dan request publik dibatasi. Hanya `is_admin=true` yang dapat mengakses endpoint admin; role pemilik belum dibedakan.

Token booking dibuat dari 32 byte acak (256 bit), hanya hash SHA-256 disimpan. Token dikirim lewat Authorization saat membaca status, bukan path/query API. Jadwal publik hanya memuat rentang sibuk. Log tidak boleh merekam body, cookie, Authorization, atau fragment tautan.

Uang adalah integer rupiah. Tarif/jam, tagihan, dan minimum pembayaran disalin ke booking. Waktu booking dan pembayaran disimpan sebagai UTC; input jadwal dan batas rekap menggunakan Asia/Jakarta. Rekap harian/bulanan memakai interval awal inklusif, akhir eksklusif yang dikonversi ke UTC.

Semua perubahan booking/pembayaran menggunakan transaksi SQLite IMMEDIATE: penulis memperoleh kunci sebelum membaca konflik/sisa tagihan. Timeout sibuk dan retry transaksi terbatas; jika tetap sibuk, API mengembalikan 503 dan pengguna dapat mencoba ulang. SQLite mendukung satu penulis pada satu waktu, untuk satu host dengan disk persisten; bukan banyak instance dengan salinan database sendiri.

Pembayaran memiliki kunci idempotensi UUID unik global dan hash isi (booking, nominal, metode). Pengiriman ulang identik mengembalikan transaksi yang sama. Kunci sama dengan isi berbeda ditolak 409. Kunci ini dipertahankan UI ketika respons jaringan tidak pasti; perubahan isi memerlukan kunci baru.

## Keputusan lanjutan pengelola

Konfirmasi fasilitas, tarif, jam/durasi, DP, tenggat, konfirmasi manual, kewenangan, SOP input WA, pembatalan/no-show/refund, reschedule, dasar laporan, penyimpanan data pribadi, dan penerimaan pengguna. Pemeriksaan agen bukan review independen atau persetujuan stakeholder.

## Catatan kompatibilitas SQLite

PHP 8.4+ diwajibkan karena implementasi SQLiteConnection Laravel hanya memakai mode transaksi IMMEDIATE pada versi tersebut. PHP 8.2 yang dipakai pada percobaan awal tidak memenuhi strategi ini. Composer, CI dan panduan sudah menetapkan PHP 8.4. Ada uji khusus yang memastikan transaksi memperoleh kunci penulis sebelum membaca data.
