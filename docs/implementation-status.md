# Status implementasi Lola Sport v2

Pemeriksaan 16 Oktober 2026 WIB. Repository canonical **kheisarkc/stg-lolasport**, GitHub ID 1414080095, sama dengan repository sebelum nama akun berubah. Branch pembaruan `feat/lola-rules-v2` dibuat dari implementasi `0b2190d364996fdc06494578417059de5adde7eb`. `main` diverifikasi masih di `2c89a54b1a1483c15446b91a4ce2c8b08ee9fe4d` sebelum pekerjaan. Commit akhir dapat dilihat melalui `git log`; tidak ada merge ke main.

Prototipe eksplorasi capstone, belum sistem operasional, persetujuan pengelola, review independen atau UAT. Proposal akademik tidak diubah. [Kebutuhan v2](requirements-v2.md) memetakan asal informasi, aturan demo, file dan kasus uji. [Tahap 2](phase-2-design.md) hanya rancangan.

## Yang diimplementasikan pada Tahap 1

- Tarif awal v2, 07.00–00.00 pada tanggal berikutnya, durasi 1–3 jam dan batas 90 hari.
- DP Rp50.000 per booking; pembuatan booking kurang dari 60 menit wajib penuh, termasuk web/WA/onsite. Onsite untuk jadwal mendatang tidak otomatis penuh.
- Pembaruan tarif eksplisit melalui preview/ID/konfirmasi; nilai khusus dilewati dan perubahan setelah preview membatalkan seluruh penerapan. Seeder/setup tidak menimpa tarif yang ada.
- Snapshot lama tetap utuh. Migration hanya menambah informasi mulai bermain dan kind pembayaran secara nullable.
- Pilihan pencatatan awal DP/penuh dan pelunasan sisa dari server, tinjauan identitas/nominal/metode sebelum konfirmasi, serta retry yang sama setelah respons terputus/reload. Nominal bebas belum didukung.
- Tindakan mulai bermain oleh pegawai berwenang, saat confirmed, lunas dan dalam jadwal. Waktu/pelaku pertama disimpan sekali; jam lewat tidak menyimpulkan kehadiran.
- Hold maksimal 20 menit sejak penyimpanan, dibatasi waktu mulai. Confirmed tidak kedaluwarsa oleh timer pending. Pembayaran expired ditolak.
- Tema navy–lime, kontrol besar/tanggal cepat, tampilan 320px, rincian ponsel, penjelasan pembayaran dan tenggat. Referensi terverifikasi di `design-notes-v2.md`.
- Preflight PHP/ekstensi dan perbaikan konfigurasi CI Windows.

## Hasil lokal aktual

| Pemeriksaan | Hasil dan batas |
| --- | --- |
| Backend PHP 8.4 | **36 tes, 358 assertions lulus**; `php artisan test` |
| Runtime/launcher Node | **7 tes lulus**; termasuk path spasi/backtick dan ekstensi sengaja tidak dimuat pada konfigurasi PHP temporer |
| Composer/Pint/ESLint/typecheck/build | Lulus melalui `bash scripts/check.sh`; build produksi juga dijalankan E2E setelah perubahan tampilan terakhir |
| Browser build produksi lokal | **6 skenario lulus**, desktop/Pixel 7: booking–DP–penolakan mulai–pelunasan–mulai, retry respons hilang setelah reload, rekap, onsite/pembatalan, mendadak, tengah malam, 320px dan target utama ≥48px |
| Konkurensi | Dua worker PHP terpisah dan database file bersama; bentrok booking, dua pelunasan berpotensi berlebih, idempotensi, dan dua pegawai menandai mulai. Barrier memastikan operasi tumpang tindih; bukan server HTTP serial sebagai bukti konkurensi |
| Upgrade database lama | File SQLite sementara dengan schema v1 dan data lama dimigrasikan; harga/DP/pembayaran tetap utuh. Updater diuji preview, batal, tarif khusus, ulang, dan perubahan setelah preview |
| Persistensi | Backend HTTP dihentikan/dijalankan kembali: 8 booking, 4 pembayaran, 2 penanda mulai, Rp260.000 penerimaan demo tetap tersimpan |
| Setup Linux/PowerShell lokal | Setup baru Linux dan pengulangan PowerShell 7.5.4 di Linux + Bash lulus pada database sementara; tarif khusus, booking, pembayaran dan akun dipertahankan. Database default/`.env` dibandingkan dengan baseline dan tetap utuh; ini bukan bukti native Windows |
| Pemeriksaan visual | Screenshot desktop dan 320px diperiksa internal; bukan evaluasi handphone nyata atau audit WCAG penuh |

Waktu bisnis pada browser tests disuntikkan lewat router privat dalam `tests/Support`, pada database temporer terjaga. Jam session/cache nyata dan CSRF tetap aktif; POST tanpa CSRF ditolak. Tidak ada endpoint pengubah waktu pada aplikasi biasa.

## CI remote

CI awal yang diverifikasi: [run 38141742834](https://github.com/kheisarkc/stg-lolasport/actions/runs/38141742834), commit 0b2190d. Linux berhasil, Windows gagal pada setup. Anotasi resmi mencatat league/flysystem-local dan league/mime-type-detection membutuhkan **ext-fileinfo** yang belum aktif pada `C:\tools\php\php.ini`. Arsip log penuh ditolak proxy, tetapi anotasi menyediakan sebab spesifik tersebut.

Perbaikan v2 mengaktifkan fileinfo beserta ekstensi lockfile secara eksplisit pada kedua runner, serta preflight sebelum Composer. Tidak ada composer update atau pengabaian platform. Bukti remote v2 yang diperiksa: [run 38789406332](https://github.com/kheisarkc/stg-lolasport/actions/runs/38789406332), source commit `705db288b784cf3313b7f4bd7c0bdf0ab5e409ff`, **checks Linux dan windows berhasil**, termasuk setup PowerShell native, backend, runtime/launcher dan browser. Ini bukti runner Windows, bukan laptop pengguna.

Pemeriksaan akhir menambah pengaman jika tenggat terlewati antara pembacaan booking dan pencatatan pembayaran. Tes tambahan gagal sebelum perbaikan dan lulus sesudahnya, sehingga jumlah lokal menjadi 36/358. Untuk commit sesudah 705db28, cocokkan SHA dan hasil pada [Actions branch v2](https://github.com/kheisarkc/stg-lolasport/actions?query=branch%3Afeat%2Flola-rules-v2); hasil commit akhir dilaporkan pada serah terima/PR. Hasil satu source commit tidak otomatis membuktikan revisi berikutnya.

## Data dan penggunaan yang belum dilakukan

Pembaruan tarif pada database pengguna bukan bagian otomatis implementasi. Pengguna menjalankan langkah backup, setup/migration, preview dan apply terpisah di `windows.md`/README. Tidak ada perintah reset database atau perubahan snapshot historis dalam deployment/setup rutin.

Belum ada hosting/domain/disk persisten atau URL staging terverifikasi; konfigurasi staging tetap contoh. Belum ada pengujian laptop pengguna untuk v2, Safari/perangkat nyata, evaluasi beban operasional, pemulihan backup staging, UAT atau pengukuran efektivitas.

Tahap 1 tidak memiliki bukti upload, antrean verifikasi, pengenalan transfer nyata duplikat, received_at terpisah, reschedule, no-show, refund/koreksi, role pemilik, ekspor atau penutupan lapangan. Kasus uang masuk tetapi verifikasi melewati hold belum diselesaikan. Nilai 10 menit/15 menit/H-24/satu reschedule/30 hari/potongan Rp50.000 serta persetujuan koreksi pemilik tetap usulan Tahap 2.

## Riwayat verifikasi sebelumnya

Implementasi v1 pada 11 Oktober 2026 mencatat 19 tes backend/158 assertions dan dua skenario browser. Perbaikan Windows 0b2190d belum berhasil pada CI karena fileinfo di atas. Angka v1 bukan hasil uji v2; gunakan tabel hasil terbaru dan run CI yang dinyatakan secara eksplisit.
