# Menjalankan Lola Sport melalui VS Code di Windows

Gunakan terminal PowerShell di root repository. Setup Windows tersedia melalui `scripts/setup.ps1`; tidak membutuhkan WSL atau Bash. Error WSL `/bin/bash ... No such file or directory` berarti perintah `bash` yang dipanggil mengarah ke instalasi WSL tanpa shell yang tersedia.

Node.js 22.16.0 dan Composer 2.8.6 kompatibel. PHP 8.2 dari XAMPP perlu diganti dengan PHP 8.4+ pada terminal proyek: strategi transaksi SQLite IMMEDIATE yang digunakan Laravel membutuhkan PHP 8.4. Mengabaikan persyaratan Composer tidak menyelesaikan kebutuhan ini.

## Memasang PHP terpisah dari XAMPP

1. Unduh ZIP PHP 8.4 terbaru, **x64 Non Thread Safe**, dari [unduhan resmi PHP untuk Windows](https://windows.php.net/download/). Ekstrak ke `C:\Tools\php84` sehingga `php.exe` langsung berada di folder tersebut. Jika muncul pesan DLL runtime hilang saat menjalankan PHP, pasang [Microsoft Visual C++ Redistributable x64](https://aka.ms/vs/17/release/vc_redist.x64.exe).
2. Salin `php.ini-development` menjadi `php.ini` di folder PHP baru. Atur `extension_dir="C:\Tools\php84\ext"`, lalu aktifkan baris `extension=curl`, `extension=fileinfo`, `extension=mbstring`, `extension=openssl`, `extension=pdo_sqlite`, `extension=sqlite3`, dan `extension=zip` dengan menghapus tanda `;` di awal barisnya. Ekstensi DOM/XML/ctype/tokenizer biasanya sudah tersedia pada build PHP Windows; Composer memeriksa persyaratan paket saat instalasi.
3. Pilih PHP baru pada terminal PowerShell yang akan dipakai menjalankan proyek:

```powershell
$env:Path = "C:\Tools\php84;" + $env:Path
php -v
where.exe php
composer --version
php --ini
php -m
```

`php -v` dan PHP yang dilaporkan Composer harus memakai 8.4+. `where.exe php` boleh menampilkan beberapa instalasi; PHP baru harus berada pertama. Perubahan PATH di atas hanya berlaku pada terminal saat ini; ulangi di terminal baru atau atur urutan PATH pengguna melalui pengaturan Windows. XAMPP tetap menggunakan instalasinya sendiri.

Jika Composer masih memakai PHP 8.2 meskipun `php -v` sudah 8.4+, periksa lokasi `where.exe composer` dan konfigurasi launcher Composer. Installer Composer resmi juga dapat dikonfigurasi ulang untuk PHP baru. Jangan mengganti konfigurasi proyek agar mengabaikan persyaratan PHP.

## Mengambil perbaikan dan setup

Jika clone sudah berada pada branch `feat/lola-booking-prototype`, jalankan dari root:

```powershell
git pull --ff-only
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\setup.ps1
```

`ExecutionPolicy Bypass` hanya berlaku pada proses PowerShell tersebut, tidak mengubah kebijakan Windows secara permanen. Script memeriksa tool/versi, memasang dependensi dari lockfile, membuat `.env` hanya jika belum ada, mempertahankan APP_KEY yang telah terisi, menjalankan migration tanpa reset, dan mengisi lapangan yang belum tersedia. Script tidak membuat akun/password bawaan.

Kemudian:

```powershell
Set-Location backend
php artisan lola:admin pegawai@example.test --name="Pegawai Demo"
Set-Location ..
node scripts/dev.mjs
```

Tentukan password melalui prompt tersembunyi, lalu buka http://localhost:3000 dan http://localhost:3000/admin. Pilih tanggal besok dan gunakan data fiktif sesuai panduan README. Jika email sudah terdaftar, perintah admin mempertahankan akun lama.

Launcher menggunakan Node untuk menjalankan `npm-cli.js`, bukan memanggil `npm.cmd` sebagai proses langsung. Ini memperbaiki error `spawn EINVAL`. Path folder dengan spasi/backtick diteruskan sebagai working directory; tidak disisipkan ke perintah shell. `Ctrl+C` menghentikan layanan yang dibuat launcher; di Windows, taskkill digunakan untuk menghentikan pohon proses PHP/Next.js.

## Bila masih gagal

- PHP tidak ditemukan atau versi masih 8.2: pastikan pemilihan PATH dilakukan pada terminal yang sama.
- Composer melaporkan ekstensi hilang: periksa `php --ini`, aktifkan ekstensi pada `php.ini` yang benar, lalu ulangi setup.
- Node/npm tidak ditemukan: instal Node.js 22/24 dengan npm dan buka ulang terminal VS Code.
- `Setup belum selesai`: jalankan setup dan selesaikan error yang muncul sebelum memulai aplikasi.
- Port terpakai: tutup launcher lama dengan Ctrl+C, atau gunakan `$env:FRONTEND_PORT='3001'` dan `$env:BACKEND_PORT='8001'` sebelum menjalankan launcher; buka port frontend yang dipilih.

Pemeriksaan launcher dapat dijalankan dengan `node --test scripts/tests/process.test.mjs`. Hasil cloud Linux dan simulasi pemilihan executable Windows tidak membuktikan seluruh aplikasi telah diuji pada laptop Windows pengguna. Catat hasil percobaan Windows secara terpisah.
