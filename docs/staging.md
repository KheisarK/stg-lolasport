# Persiapan staging Lola Sport

**Status: deployment publik belum dilakukan.** Belum tersedia host/domain, akses SSH, disk persisten, atau tujuan deployment yang terverifikasi. Jangan menyamakan build lokal dengan aplikasi online atau persetujuan bisnis. Tidak ada layanan berbayar yang dibuat.

## Topologi yang disiapkan

Satu VM/host Linux dengan Node.js 22/24, PHP 8.4+ dan ekstensi pada README, Composer, Nginx, PHP-FPM, serta disk persisten. Satu instance Laravel dan satu instance Next.js. SQLite harus berada pada disk lokal persisten host yang sama; tidak menggunakan volume jaringan yang semantik pengunciannya belum dibuktikan. Hosting frontend statis/serverless saja tidak memenuhi kebutuhan PHP dan SQLite.

Nginx HTTPS → Next.js port internal 3000. Next.js meneruskan `/api` ke Nginx Laravel port loopback 8080 → PHP-FPM. Laravel tidak dibuka langsung ke internet. Pengunjung memakai satu origin, sehingga cookie session dan CSRF tidak memerlukan CORS lintas origin. Untuk topologi ini tambahkan hanya proxy tepercaya yang dikendalikan operator jika membutuhkan IP asli untuk rate limit; jangan mempercayai semua header proxy secara umum. Tanpa itu pembatasan IP publik berlaku bersama pada IP proxy, cocok untuk demo kecil tetapi perlu ditinjau sebelum banyak pengguna.

## Konfigurasi dan file

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` origin HTTPS staging.
- `APP_KEY`: kunci stabil yang dibuat aman saat provisioning; simpan di konfigurasi server, bukan repository/log.
- `DB_CONNECTION=sqlite`, `DB_DATABASE`: path absolut disk persisten, contoh `/srv/lola-data/database.sqlite`.
- `SESSION_DRIVER=file`, `SESSION_FILES`: direktori session persisten; `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true`, `SESSION_SAME_SITE=lax`, `SESSION_COOKIE=lola_session`; domain cookie kosong agar mengikuti host.
- `CACHE_STORE=file`, `CACHE_PATH`: direktori cache yang dapat ditulis aplikasi.
- `LOG_LEVEL=warning`, `LOG_CHANNEL=single`; rotasi log dan hindari logging Authorization/cookie/body data pribadi.
- `BACKEND_URL`: alamat internal Laravel, contoh `http://127.0.0.1:8080`. Tetapkan saat build Next.js dan saat start; URL rewrite ikut build.
- `backend/storage` dan `backend/bootstrap/cache` dapat ditulis user layanan; database serta direktori tempatnya harus dapat ditulis agar journal WAL berfungsi.
- `.env`, database, file `-wal`/`-shm`, session, log, dan kredensial berada di luar Git. Jangan membuka root repo sebagai web root; Laravel memakai `backend/public`.

## Langkah deployment

Gunakan release directory baru per commit yang ditinjau. Simpan release sebelumnya untuk rollback. Jangan menjalankan setup lokal sebagai script deployment; operator menyediakan `.env`/APP_KEY dan lokasi persistensi terlebih dahulu.

```bash
# Dari release/backend, setelah konfigurasi aman tersedia
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
php artisan migrate --force
# Hanya pengisian lapangan demo, jika belum ada; tidak membuat akun
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
# Dari release/frontend, BACKEND_URL internal sudah ditetapkan
npm ci
npm run build
# Dikelola systemd/process manager, bukan terminal sesi agen
npm run start -- --port 3000
```

Konfigurasi Nginx dan systemd contoh ada di `deploy/`; ganti path release, socket PHP, user layanan dan domain sesuai host. Uji dengan `nginx -t` serta jalankan proses melalui pengelola layanan. Untuk Laravel gunakan PHP-FPM, bukan `artisan serve` pada staging publik. Buat akun pegawai melalui `php artisan lola:admin` di console host dan input password tersembunyi. Tidak ada scheduler/queue worker yang diperlukan; expiry dihitung saat operasi aplikasi.

## Pemeriksaan setelah deployment

1. Request `/api/health` harus 200 dan menyatakan mode demo; buka halaman pelanggan tanpa error asset/API.
2. Coba booking, login, DP, pelunasan, status pelanggan dan rekap menggunakan data fiktif.
3. Coba API admin tanpa login, token booking salah, POST tanpa CSRF, dan nominal berlebih; harus ditolak.
4. Periksa cookie Secure/HttpOnly/SameSite, HTTPS, pemisahan data dari bisnis, dan log tanpa rahasia.
5. Restart layanan dan redeploy commit yang sama; data harus tetap ada di path persisten. Catat commit, waktu, langkah dan hasil. Bukti lokal tidak menggantikan pemeriksaan ini.

## Backup dan pemulihan

SQLite WAL memiliki file pendamping; menyalin database saja saat aplikasi aktif tidak menjamin backup konsisten. Gunakan perintah backup SQLite pada host, atau hentikan penulis dan lakukan checkpoint sebelum salinan. Contoh setelah mengatur path yang benar:

```bash
sqlite3 /srv/lola-data/database.sqlite '.backup /srv/lola-backups/lola-backup.sqlite'
sqlite3 /srv/lola-backups/lola-backup.sqlite 'PRAGMA integrity_check;'
```

Backup berisi data pribadi, sehingga batasi akses, simpan terenkripsi bila dipindahkan, dan tetapkan retensi/penanggung jawab bersama pengelola. Uji pemulihan ke lokasi terpisah dahulu: jalankan integrity_check, cocokkan jumlah booking/pembayaran, hubungkan instance uji ke salinan, lalu coba membaca data. Persetujuan dan jadwal pemulihan operasional belum ditetapkan. Backup staging belum dicoba pada host publik.

Rollback aplikasi dilakukan dengan mengarahkan release ke versi sebelumnya dan me-restart layanan. Migration tidak otomatis dibalik; schema baru mungkin tidak kompatibel dengan kode lama. Jangan memakai `migrate:fresh`, reset atau rollback migration rutin pada data aktif. Siapkan backup dan penilaian kompatibilitas sebelum perubahan schema.

## Sebelum penggunaan bisnis

Perlu validasi kebijakan pengelola, UAT melalui handphone nyata, review keamanan independen, mekanisme koreksi/refund/reschedule yang disepakati, prosedur akun/pemulihan, backup teruji, dan evaluasi beban sesuai jumlah pengguna. Pembayaran saat ini sepenuhnya pencatatan demo; tidak ada gateway atau transaksi uang nyata.
