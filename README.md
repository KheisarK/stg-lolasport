# Lola Sport — prototipe reservasi web v2 (Tahap 1)

Prototipe Laravel + Next.js untuk mencoba reservasi lapangan, input booking WA/onsite oleh pegawai, pencatatan DP dan pelunasan, serta rekap penerimaan. **Mode demo—tidak memproses uang nyata.** Informasi kebutuhan melalui pengguna dan pilihan demo dibedakan serta perlu ditinjau pengelola; lihat [keputusan prototipe](docs/prototype-decisions.md). [PRD](docs/PRD.md) dan [kebutuhan v2 beserta asalnya](docs/requirements-v2.md) tetap draft. [Tahap 2](docs/phase-2-design.md) hanya rancangan, belum diimplementasikan.

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

Prasyarat: Node.js 22 atau 24, PHP 8.4+ yang kompatibel dengan lockfile, Composer 2, serta ekstensi PHP ctype, curl, dom, fileinfo, mbstring, openssl, pdo_sqlite, sqlite3, tokenizer, xml, xmlreader, xmlwriter, zip. Setup memakai Bash pada Linux/macOS atau PowerShell pada Windows. Fungsi `proc_open` harus tersedia untuk pengujian proses terpisah. Tidak diperlukan gateway, Redis, queue worker, atau database eksternal.

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

**Database yang sudah terisi:** migration hanya menambah kolom; seeder tidak mengganti tarif yang sudah ada. Untuk tarif v2, cadangkan data saat layanan berhenti, lalu tinjau `php artisan lola:rates-v2` dari `backend/`. Penerapan merupakan tindakan terpisah menggunakan `--apply --court=ID` dan konfirmasi. [Petunjuk Windows](docs/windows.md) menjelaskan backup/pergantian branch; [kebutuhan v2](docs/requirements-v2.md) menjelaskan guard tarif khusus. Jangan mereset database.

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

## Mencoba booking → DP → mulai ditolak → lunas → mulai

1. Pilih Futsal Sintetis satu jam dengan jadwal minimal 60 menit ke depan. Pada database dengan tarif v2: total Rp130.000, DP Rp50.000 dan sisa Rp80.000. Gunakan data fiktif. DP tetap Rp50.000 untuk dua/tiga jam; badminton Rp100.000/jam masih pilihan prototipe.
2. Buat booking, lalu simpan tautan status. Bagian setelah `#` adalah token rahasia. Memilih DP/penuh belum berarti sudah membayar; hold dimulai dari booking tersimpan dan maksimal 20 menit atau sampai jam mulai.
3. Login `/admin`, pilih tanggal dan booking. Pada Jenis pencatatan pilih DP; pilih metode, tinjau identitas/nominal, lalu konfirmasi pencatatan demo sebelum tenggat.
4. Klik **Tandai mulai bermain** saat masih ada sisa. Server menolak karena belum lunas.
5. Pilih **Pelunasan sisa tagihan** Rp80.000, tinjau lalu konfirmasi. Jumlah dua catatan menjadi Rp130.000 dan sisa nol; seluruh tagihan tidak ditambahkan lagi.
6. Setelah masuk rentang jadwal booking, klik **Tandai mulai bermain**. Waktu dan pegawai dicatat sekali. Klik sebelum jam mulai/setelah jam selesai ditolak; jam yang lewat tidak otomatis membuktikan kehadiran.
7. Periksa status pelanggan dan rekap. Rekap Tahap 1 memakai waktu pencatatan pembayaran WIB. Ini bukan waktu transfer bank yang diverifikasi.
8. Coba WA/onsite untuk jadwal mendatang: DP tetap tersedia. Booking kurang dari 60 menit sebelum mulai hanya penuh. Sesi 23.00 satu jam selesai 00.00 pada tanggal berikutnya.

Alur manual DP sampai mulai bermain perlu menunggu jadwal tersebut. Untuk verifikasi cepat tanpa menunggu satu jam, jalankan E2E: jam bisnis dikendalikan hanya pada server pengujian/database sementara, tanpa mengubah jam laptop atau menyediakan pengubah waktu pada aplikasi biasa.

Jika respons pembayaran terputus, UI menyimpan permintaan dan kunci yang sama, termasuk setelah reload; buka booking lalu coba ulang pencatatan tersebut. Dua input baru atas transfer nyata yang sama belum dikenali otomatis. Nominal berbeda dari pilihan standar belum didukung: tunda pencatatan demo dan cocokkan bersama pengelola, jangan mengarang nominal.

Belum ada upload bukti/antrean verifikasi. Pembayaran masuk tetapi baru diperiksa setelah hold habis belum diselesaikan Tahap 1; booking expired menolak pembayaran, tanpa merebut slot yang sudah dipesan pihak lain. Lihat rancangan Tahap 2.

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

- [Kebutuhan v2](docs/requirements-v2.md): sumber informasi, batas demo dan pemetaan kebutuhan–kode–uji.
- [Rancangan Tahap 2](docs/phase-2-design.md): alur, data, layar, kewenangan, risiko dan keputusan terbuka.
- [Catatan desain](docs/design-notes-v2.md): referensi terverifikasi dan pemeriksaan layar handphone.
- [Keputusan prototipe](docs/prototype-decisions.md): aturan contoh, keamanan, arsitektur dan batas cakupan.
- [Status implementasi](docs/implementation-status.md): bukti pemeriksaan dan pekerjaan tersisa.
- [Panduan belajar](docs/learning-guide.md): hubungan komponen, tahapan dan latihan.
- [Panduan staging](docs/staging.md): hosting Laravel/Next.js, penyimpanan persisten, deployment, backup dan pemulihan.
- [Panduan Windows/VS Code](docs/windows.md): setup PowerShell, pemilihan PHP, dan menjalankan aplikasi tanpa WSL.

Belum ada transaksi nyata, reschedule, refund, koreksi pembayaran, peran pemilik terpisah, ekspor laporan, maupun persetujuan penggunaan bisnis. Evaluasi pengguna dan keputusan rilis memerlukan kelompok/pengelola yang bertanggung jawab.
