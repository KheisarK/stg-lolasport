# Keputusan prototipe Lola Sport v2

Pembaruan 16 Oktober 2026 WIB. Dasar dan status setiap angka ada di [kebutuhan v2](requirements-v2.md). Informasi pembahasan melalui pengguna, pilihan sementara pengguna, asumsi teknis demo dan referensi desain dibedakan di sana. Implementasi tidak menjadikannya kebijakan resmi.

## Batas percobaan

Laravel/Next.js/SQLite dipertahankan. Pelanggan tanpa akun memperoleh token privat; pegawai menggunakan session. Pembayaran tetap pencatatan manual demo. Tidak ada uang nyata, bukti upload, gateway, verifikasi tertunda, reschedule, refund/koreksi, role pemilik atau penetapan tidak datang otomatis.

Tarif v2 untuk database baru: sintetis Rp130.000, vinil Rp160.000, badminton Rp100.000 per jam. Badminton belum dikonfirmasi wawancara. Semua hari 07.00–00.00 WIB, durasi percobaan 1–3 jam. DP Rp50.000 per booking; booking yang dibuat kurang dari 60 menit sebelum mulai hanya penuh. Batas 90 hari dipertahankan; usulan 30 hari belum diterapkan.

## Aturan dan harga

`backend/config/lola.php` menyimpan waktu, DP, durasi dan batas booking. `config/lola_tariffs.php` menyimpan tarif awal serta nilai lama untuk guard updater. Seeder hanya memasukkan lapangan yang belum ada. Update database terisi dilakukan terpisah melalui `lola:rates-v2`: preview, pemilihan ID dan konfirmasi.

Nilai khusus tidak ditimpa otomatis. Perbandingan angka tidak membuktikan apakah nilai yang kebetulan sama dengan tarif lama merupakan pilihan khusus; operator tetap meninjau ID dengan sengaja. Perubahan setelah preview membatalkan seluruh penerapan. Tidak ada updater tarif pada setup/migration.

Tarif/jam, total, payment_option dan initial_due tetap snapshot booking. Booking lama dapat memiliki DP persentase lama; nominalnya tidak dihitung ulang menjadi Rp50.000. Pembayaran lama tidak diubah/disalin. Kolom nullable baru hanya menambah informasi mulai bermain dan jenis catatan baru.

## Waktu dan konsistensi

Input/jadwal/batas laporan memakai Asia/Jakarta, penyimpanan UTC. close_hour=24 berarti 00.00 hari berikutnya. Bentrok memakai start < end lain dan end > start lain; slot bersebelahan boleh.

`BusinessClock` membaca waktu UTC saat ini untuk aturan booking. Pengujian browser menyuntikkan waktu terkendali hanya melalui router `tests/Support` yang mengharuskan database temporer; tidak ada endpoint mengubah waktu. Jam session/file/cache tetap nyata agar tes tidak merusak CSRF demi melewati jam jadwal.

Perubahan booking/pembayaran memakai transaksi SQLite IMMEDIATE, dengan PHP 8.4+ yang mendukung perilaku framework tersebut. Kunci penulis diperoleh sebelum memeriksa slot/sisa. Database sibuk mendapat retry terbatas dan respons 503. SQLite untuk satu host/disk persisten, bukan banyak instance dengan salinan masing-masing.

## Hold dan status

Pending memperoleh expires_at=min(waktu penyimpanan + 20 menit, waktu mulai). Membuka formulir belum menahan slot. Tenggat dikembalikan bersama instruksi pada respons booking; latensi jaringan tidak mengulang hold. Pending yang habis efektif expired tanpa scheduler. Pembayaran setelah itu ditolak.

Pembayaran valid mengonfirmasi booking. Timer pending tidak mengakhiri confirmed, termasuk setelah DP. Reservasi, tagihan dan mulai bermain dipisahkan. Lewatnya jam tidak otomatis berarti hadir, selesai atau tidak datang.

Pembatalan Tahap 1 hanya pending belum dibayar dan belum expired; catatan tetap disimpan. Pembatalan setelah pembayaran dan uang terlambat diperiksa memerlukan Tahap 2, tanpa catatan rekaan/pengambilalihan slot.

## Pembayaran standar dan retry

Server mengembalikan payment_choices dari snapshot/sisa. Belum dibayar dengan opsi DP: DP snapshot atau penuh. Opsi penuh: hanya penuh. Setelah sebagian dibayar: pelunasan sisa. Untuk total tarif khusus ≤DP, booking baru memakai penuh agar tidak melebihi tagihan.

UI tidak menyediakan nominal bebas. Pegawai meninjau referensi, pemesan, lapangan, jadwal, metode dan nominal sebelum konfirmasi. API menolak amount bebas dan menghitung nominal dari jenis pilihan; expected_amount hanya memastikan nominal yang ditinjau masih cocok dengan server.

UUID idempotensi unik global dan hash booking/jenis/expected_amount/metode melindungi retry. Kunci sama/isi sama mengembalikan hasil sebelumnya; isi berbeda ditolak. UI mempertahankan permintaan yang hasilnya belum pasti melalui sessionStorage, termasuk setelah reload, lalu mengikuti sisa terbaru setelah berhasil. Key penyimpanan v2 berbeda dari UI v1; bila ada catatan lama yang hasilnya belum jelas saat upgrade, periksa riwayat dahulu.

Ini tidak mengenali dua input baru sebagai transfer nyata yang sama. Nominal di luar pilihan standar belum didukung: tunda pencatatan demo dan cocokkan bersama pengelola. QRIS/transfer/tunai hanya label metode.

## Mulai bermain

Asumsi demo setelah pengguna menyerahkan pemilihannya: hanya pegawai berwenang yang dapat menandai mulai pada starts_at ≤ sekarang < ends_at, booking confirmed dan sisa nol. Server menegakkannya; UI bukan pengaman utama. started_at/started_by pertama dipertahankan saat retry atau dua pegawai menekan bersamaan. Ini catatan prosedur, bukan kendali akses fisik.

## Keamanan dan laporan

Browser memakai satu origin melalui proxy Next.js ke Laravel. Session HttpOnly/SameSite=Lax; Secure wajib pada HTTPS. CSRF berlaku untuk perubahan; login diregenerasi dan logout membatalkan session. Rate limit login/publik/admin dipertahankan. Semua fungsi admin memakai is_admin=true; role pemilik belum terpisah.

Token booking memakai 32 byte acak, hash SHA-256 disimpan. Authorization untuk API; fragment untuk tautan privat. Jadwal publik tidak memuat identitas/kontak, dan endpoint pelanggan tidak menampilkan ID pegawai internal. Jangan merekam header/body sensitif dalam log.

Uang integer rupiah. Rekap menjumlahkan payments per waktu pencatatan paid_at dengan batas WIB; ini tidak membuktikan waktu transfer bank. Tagihan/penanda bermain tidak menambah penerimaan. Pemisahan received_at/recorded_at dan laporan pengembalian belum dibuat.

## Keputusan berikutnya

[Rancangan Tahap 2](phase-2-design.md) memuat klaim, verifikasi terlambat, transaksi duplikat nyata, keterlambatan/no-show, reschedule, pembatalan/refund/koreksi dan laporan. Seluruh angka/kewenangan yang belum disetujui tetap usulan. Pengujian agen bukan persetujuan pengelola, review independen atau UAT.
