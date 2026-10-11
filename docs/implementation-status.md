# Status implementasi prototipe Lola Sport

Pemeriksaan lokal pada 11 Oktober 2026, branch `feat/lola-booking-prototype`. Repository sebelumnya hanya memiliki commit awal `2c89a54b1a1483c15446b91a4ce2c8b08ee9fe4d`; implementasi pada branch ini belum menjadi persetujuan kebutuhan bisnis. Nomor commit akhir dapat dilihat melalui `git log -1`.

## Cakupan yang tersedia

| Cakupan | Status dan bukti kode |
| --- | --- |
| Empat lapangan dan tarif contoh | Seeder di `backend/database/seeders/DatabaseSeeder.php`; seeding ulang tidak mereset tarif |
| Booking pelanggan tanpa akun, harga server, referensi, token rahasia | `BookingService::create`, `LolaController::customer`, `frontend/src/components/BookingForm.tsx`, `frontend/src/app/booking/page.tsx` |
| Konflik, batas waktu, durasi, slot bersebelahan | Validasi dan transaksi `BookingService`; konfigurasi SQLite IMMEDIATE di `backend/config/database.php`; PHP 8.4 diwajibkan |
| Login/logout dan otorisasi pegawai | `LolaController`, `AdminOnly`, route admin, halaman server `/admin`; pembuatan akun `lola:admin` |
| Jadwal admin dan input WA/onsite | `LolaController::schedule/adminCreate`, `AdminDashboard`, layanan booking yang sama |
| DP/penuh, beberapa pembayaran, sisa dan pelunasan | `BookingService::pay/detail`, tabel `payments`, antarmuka pembayaran manual demo |
| Idempotensi dan pembayaran bersamaan | UUID unik + hash isi; transaksi IMMEDIATE; UI mempertahankan kunci saat respons tidak pasti |
| Kedaluwarsa dan pembatalan unpaid | Status efektif serta `active/cancel`; paid cancellation ditolak; riwayat tidak dihapus |
| Rekap harian/bulanan | `LolaController::report`, batas tanggal Jakarta → UTC, hanya menjumlahkan pembayaran |
| Tampilan handphone dan pesan status | CSS responsif, loading/empty/error/success, banner demo |
| CI, setup, dev dan panduan staging | `scripts/`, `.github/workflows/ci.yml`, `deploy/`, `docs/staging.md` |

## Pemetaan PRD

FR-01–FR-09 dan FR-12–FR-14 tersedia dalam cakupan prototipe. FR-05 mengikuti tenggat contoh, bukan keputusan final. FR-07 berupa pencatatan manual oleh pegawai, bukan verifikasi rekening/gateway. FR-10 hanya pembatalan pending belum dibayar; kasus refund/no-show belum dibuat. FR-15 sebagian: waktu/pelaku booking pegawai, pembayaran dan pembatalan dicatat; tidak ada audit log lengkap atau koreksi transaksi. FR-11 reschedule belum dibuat. FR-16 tarif melalui data/config, bukan UI pengaturan/penutupan.

Pemilik belum memiliki role tersendiri; semua akun pegawai berwenang menggunakan fungsi admin demo. Mekanisme penerimaan uang nyata tidak tersedia. QRIS/transfer/tunai di sini hanya label metode pada catatan contoh.

## Perintah dan bukti pengujian

- `bash scripts/setup.sh`: instalasi dari lockfile, migration non-reset, seeder lapangan. Pemeriksaan pengulangan dijalankan pada lingkungan lokal, tanpa mengubah APP_KEY yang sudah ada.
- `bash scripts/check.sh`: **lulus**, meliputi validasi Composer, Pint, **19 pengujian backend / 158 assertions**, ESLint, typecheck, dan build Next.js. Setelah perbaikan UI terakhir, lint/typecheck dan build produksi diulang dan lulus melalui rangkaian E2E.
- `LOLA_E2E_MODE=production node scripts/e2e.mjs`: **2 skenario lulus**, Chromium 154.0.8037.92 melalui UI desktop dan emulasi Pixel 7, dengan database/akun temporer. Mencakup booking, login, DP, respons pembayaran terputus dan retry, pelunasan, token salah, jadwal publik tanpa nama, CSRF, rekap harian/bulanan, input WA, pembatalan dan logout. Browser sistem dipilih melalui `PLAYWRIGHT_CHROMIUM_EXECUTABLE`; unduhan browser Playwright standar ditolak proxy cloud ini.
- Backend dihentikan/dijalankan kembali pada database yang sama; fixture memastikan empat booking, empat pembayaran, dua pembatalan dan Rp300.000 penerimaan demo tetap tersimpan.
- `ConcurrencyTest`: dua worker/proses terpisah dengan barrier mulai dan SQLite bersama. Permintaan tumpang tindih terbukti waktunya; uji booking, overpayment dan idempotensi bersamaan. Uji tambahan memastikan kunci diperoleh sebelum read dan database sibuk menghasilkan 503 yang terkendali.

Pengujian awal memakai PHP 8.2; pembacaan implementasi framework menunjukkan konfigurasi IMMEDIATE hanya diterapkan PHP 8.4+. Runtime/manifest/CI diperbarui ke PHP 8.4 dan pemeriksaan diulang. Hasil sebelum perbaikan ini tidak digunakan untuk mengklaim kunci IMMEDIATE.

Pemeriksaan UI menemukan klik lapangan yang sudah terpilih dapat membuat form terus memeriksa jadwal. Handler sekarang hanya mengubah indikator jika pilihan berubah; skenario browser mencakup klik pilihan lapangan/tanggal yang sama setelah jadwal dimuat. Ekspektasi skenario ponsel juga memperhitungkan booking desktop yang sengaja tetap berada di database bersama.

Tangkapan layar desktop/ponsel diperiksa secara visual di lingkungan lokal; assertion browser memastikan tidak ada overflow horizontal pada halaman status dan admin. Ini bukan evaluasi perangkat nyata atau penilaian pengelola. Pengulangan setup memverifikasi APP_KEY, tarif dan jumlah booking/pembayaran yang sudah ada tetap dipertahankan.

## Dukungan Windows dan PowerShell

Perbaikan setelah percobaan pengguna Windows: `scripts/setup.ps1` tersedia tanpa WSL/Bash; `scripts/process.mjs` menjalankan npm melalui Node dan `npm-cli.js`, sehingga tidak memanggil batch `npm.cmd` secara langsung yang menyebabkan `spawn EINVAL`. Launcher menghentikan pohon proses Windows melalui taskkill. Setup memilih executable pertama pada PATH, menolak PHP di bawah 8.4 sebelum instalasi, dan mempertahankan konfigurasi/data yang sudah ada. Panduan PHP 8.4 terpisah dari XAMPP ada di `docs/windows.md`.

Lima pengujian launcher lulus di cloud Linux, termasuk subprocess nyata dengan path spasi/backtick dan argumen literal. Setup dijalankan melalui PowerShell 7.5.4 pada Linux: sintaks valid, setup berhasil, pengulangan mempertahankan `.env`, tarif, booking, pembayaran dan akun. Skenario PHP 8.2 ditolak sebelum Composer dipanggil. Launcher development dinyalakan melalui helper, health/empat lapangan diperiksa, lalu SIGINT menghentikan kedua layanan dan melepas port. Rangkaian backend 19/158, lint/typecheck/build, dua skenario browser produksi dan persistensi diulang dan lulus.

CI Windows dengan PHP 8.4/Node 22, setup PowerShell dan E2E telah disiapkan. **Hasil Windows asli, Windows PowerShell 5.1, taskkill, dan CI remote belum diverifikasi**; hasil simulasi Windows pada Linux tidak menggantikan percobaan laptop pengguna.

## Batas bukti dan pekerjaan lanjutan

Belum ada deployment/URL staging terverifikasi, hasil CI remote, review independen, UAT pengelola, pengujian handphone nyata/Safari, evaluasi beban bisnis, pemulihan backup staging atau pengukuran dampak operasional. Contoh Nginx/systemd perlu disesuaikan dan diuji pada host yang disediakan.

Tidak ada reschedule, refund/koreksi pembayaran, fitur penyelesaian/no-show, notifikasi WhatsApp/email, pengaturan tarif di UI, penutupan jadwal, ekspor, atau pemisahan role pemilik. Booking yang sudah dibayar tetap confirmed sampai ada aturan lanjutan. Token pelanggan memberi akses kepada pemegang tautan; perlindungan perangkat dan pembagian tautan tetap perlu dijelaskan pada pengguna.

## Hambatan akses

Baca Git remote tersedia. Akses GitHub API melalui `gh api` ditolak jaringan/proxy (Forbidden); ini membatasi pembacaan Actions dan pembuatan draft PR. Status push diperiksa pada langkah serah terima; jangan menganggap push sama dengan deploy. Hosting, domain, disk persisten dan akses deployment belum ditemukan. Tidak dibuat langganan berbayar.
