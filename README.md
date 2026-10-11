# Lola Sport — prototipe reservasi web

Prototipe Laravel + Next.js untuk mencoba reservasi lapangan, input booking WA/onsite oleh pegawai, pencatatan DP dan pelunasan, serta rekap penerimaan. **Mode demo—tidak memproses uang nyata.** Tarif dan kebijakan adalah asumsi prototipe yang perlu ditinjau pengelola; lihat [keputusan prototipe](docs/prototype-decisions.md). [PRD v0.1](docs/PRD.md) tetap merupakan draft kebutuhan.

## Struktur dan teknologi

```text
backend/   Laravel API, autentikasi session, aturan bisnis, SQLite, pengujian PHP
frontend/  Next.js App Router, React, TypeScript, antarmuka pelanggan/pegawai
scripts/   setup, menjalankan dua layanan, pemeriksaan, pengujian browser
docs/     keputusan, status implementasi, panduan belajar dan staging
```

Versi yang digunakan: PHP 8.4.26; Composer 2.8.12; Laravel 12.69.3; SQLite 3.46.1 (PDO SQLite); Node.js 24.19.0; npm 11.9.0; Next.js 16.4.0; React 19.3.0; TypeScript 5.9.3. Versi paket dikunci oleh `backend/composer.lock` dan `frontend/package-lock.json`.

Browser → Next.js → API Laravel → SQLite. Browser menggunakan satu origin; `/api/*` diteruskan Next.js ke Laravel. Laravel menghitung harga, memeriksa konflik, dan memberi kewenangan. Pratinjau harga frontend tidak dipercaya sebagai tagihan final.

## Instalasi lokal

Prasyarat: Node.js 22 atau 24, PHP 8.4+ yang kompatibel dengan lockfile, Composer 2, serta ekstensi PHP ctype, curl, dom, fileinfo, mbstring, pdo_sqlite, sqlite3, tokenizer, xml, xmlreader, xmlwriter, zip. Setup memakai Bash pada Linux/macOS atau PowerShell pada Windows. Fungsi `proc_open` harus tersedia untuk pengujian proses terpisah. Tidak diperlukan gateway, Redis, queue worker, atau database eksternal.

Dari root repository:

```bash
bash scripts/setup.sh
```

Windows PowerShell, tanpa WSL:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\setup.ps1
```

Lihat [panduan Windows](docs/windows.md) untuk memilih PHP 8.4 jika terminal masih menggunakan PHP 8.2 dari XAMPP, termasuk konfigurasi ekstensi dan penanganan error awal.

Script memakai lockfile, membuat `.env` hanya jika belum ada, membuat APP_KEY hanya jika kosong, menjalankan migration yang belum diterapkan, dan mengisi lapangan contoh tanpa menimpa tarif yang sudah ada. Script tidak mereset database dan tidak membuat akun dengan password bawaan. Pengguna cloud di workspace ini dapat memakai runtime yang sudah disiapkan pada `/workspace/lola-tools/bin`; script mendeteksinya otomatis.

Konfigurasi lokal backend mengikuti `backend/.env.example`. `APP_KEY` adalah kunci enkripsi aplikasi; buat lokal melalui Artisan dan jangan masukkan ke Git. `DB_DATABASE` opsional untuk path SQLite absolut; default `backend/database/database.sqlite`. Backend menyimpan waktu UTC, lalu input/tampilan/laporan memakai Asia/Jakarta. Frontend dapat memakai `frontend/.env.local` dari `.env.example`; `BACKEND_URL` adalah alamat internal Laravel, bukan variabel publik browser. Jangan mengubah APP_KEY yang telah dipakai lingkungan aktif tanpa rencana migrasi session/enkripsi.

## Akun pegawai dan menjalankan aplikasi

Dari `backend/`, buat akun lokal dengan kata sandi pilihan sendiri. Input kata sandi tersembunyi dan tidak disimpan dalam perintah:

```bash
php artisan lola:admin pegawai@example.test --name="Pegawai Demo"
```

Di cloud, jika `php` belum ada di PATH, jalankan `export PATH=/workspace/lola-tools/bin:$PATH` terlebih dahulu. Perintah menolak email yang sudah ada dan tidak mengganti password pengguna lama.

Dari root:

```bash
node scripts/dev.mjs
```

Frontend berjalan pada port 3000 dan backend pada port 8000, keduanya terikat ke loopback. Buka alamat lokal port 3000 melalui browser mesin pengembangan; `/admin` meminta login. `Ctrl+C` menghentikan kedua proses. `FRONTEND_PORT` dan `BACKEND_PORT` dapat mengubah port. Di cloud ini loopback divalidasi melalui request internal; belum ada tautan staging publik.

## Mencoba booking → DP → pelunasan

1. Pilih lapangan, tanggal besok, jam mulai dan durasi. Gunakan nama/kontak fiktif, pilih DP, lalu buat booking demo.
2. Simpan tautan halaman status. Bagian setelah `#` adalah token rahasia; jangan dibagikan di issue atau screenshot alamat browser.
3. Buka `/admin`, login dengan akun yang dibuat, pilih tanggal booking dan klik booking pemesan.
4. Catat pembayaran awal minimal yang ditampilkan. Untuk Futsal Sintetis satu jam: total contoh Rp150.000, DP minimum Rp45.000, sisa Rp105.000.
5. Catat sisa Rp105.000 sebagai pembayaran terpisah. Status pembayaran menjadi lunas; booking tetap terkonfirmasi.
6. Muat ulang status pelanggan. Periksa dua catatan pembayaran dan sisa nol. Rekap harian memakai tanggal pencatatan pembayaran WIB, bukan tanggal bermain.
7. Coba tab booking WA/onsite; slot menggunakan sumber jadwal yang sama. Pembatalan hanya tersedia untuk pending belum dibayar. Kedaluwarsa otomatis berlaku saat pembacaan/pemeriksaan slot, tanpa scheduler.

Jika koneksi terputus saat pembayaran, ulangi dengan nominal/metode yang sama untuk memakai kunci idempotensi yang sama. Periksa status sebelum mengubah isi atau membuat pembayaran baru.

## Pemeriksaan dan pengujian

```bash
bash scripts/check.sh
# Browser otomatis: instal sekali dari frontend/
cd frontend
npx playwright install --with-deps chromium
cd ..
node scripts/e2e.mjs
# Pilihan: menguji build produksi lokal
LOLA_E2E_MODE=production node scripts/e2e.mjs
```

Pengujian backend memakai database sementara/in-memory. Uji konkurensi menggunakan dua proses PHP terpisah, barrier mulai, dan file SQLite bersama. Pengujian browser membuat database serta akun acak di direktori temporer, menguji ukuran desktop/handphone, menghentikan dan menyalakan backend, memeriksa persistensi, kemudian membersihkan fixture. Jangan menjalankan E2E pada database operasional. Browser alternatif dapat diatur dengan `PLAYWRIGHT_CHROMIUM_EXECUTABLE`.

CI disiapkan di `.github/workflows/ci.yml`. Hasil lokal, status CI remote, staging, dan evaluasi pengelola dibedakan dalam [status implementasi](docs/implementation-status.md).

## Dokumentasi lanjutan

- [Keputusan prototipe](docs/prototype-decisions.md): aturan contoh, keamanan, arsitektur dan batas cakupan.
- [Status implementasi](docs/implementation-status.md): bukti pemeriksaan dan pekerjaan tersisa.
- [Panduan belajar](docs/learning-guide.md): hubungan komponen, tahapan dan latihan.
- [Panduan staging](docs/staging.md): hosting Laravel/Next.js, penyimpanan persisten, deployment, backup dan pemulihan.
- [Panduan Windows/VS Code](docs/windows.md): setup PowerShell, pemilihan PHP, dan menjalankan aplikasi tanpa WSL.

Belum ada transaksi nyata, reschedule, refund, koreksi pembayaran, peran pemilik terpisah, ekspor laporan, maupun persetujuan penggunaan bisnis. Evaluasi pengguna dan keputusan rilis memerlukan kelompok/pengelola yang bertanggung jawab.
