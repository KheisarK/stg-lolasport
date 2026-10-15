# Tahap 2 — rancangan, belum diimplementasikan

16 Oktober 2026 WIB. Seluruh bagian ini adalah usulan untuk dibahas kelompok/pengelola, bukan SOP atau persetujuan bisnis. Tidak ada perubahan tabel, endpoint, timer atau kewenangan Tahap 1 yang menerapkan rancangan ini. Angka kandidat dicatat di bagian keputusan terbuka.

## 1. Klaim pembayaran dan verifikasi terlambat

Usulan alur:

1. Pelanggan melaporkan pembayaran sebelum tenggat, memilih booking dan metode, lalu mengirim bukti melalui layar khusus yang belum tersedia.
2. Server mencatat `submitted_at` dan identitas klaim. Waktu pada gambar/yang diketik pelanggan tidak menggantikan waktu penerimaan server.
3. Klaim masuk antrean `pending_review`. **Menunggu verifikasi bukan pembayaran sah**, tidak menambah penerimaan atau mengurangi sisa tagihan.
4. Pegawai memeriksa rekening/kanal pembayaran yang menjadi acuan, nominal dan referensi; hasilnya dapat accepted/rejected/needs_resolution. Bukti gambar saja tidak dianggap kepastian uang diterima.
5. Persetujuan dijalankan dalam transaksi: periksa identitas penerimaan, hak akses, keadaan klaim, booking, dan konflik slot terkini. Klaim yang sama tidak menghasilkan pembayaran ganda.
6. Jika slot masih tersedia, alokasi penerimaan dan konfirmasi booking mengikuti aturan yang nanti disepakati. Jika slot sudah diambil booking lain, jangan mengonfirmasi booking lama atau membatalkan booking lain secara otomatis. Buat kasus penyelesaian: alternatif jadwal, pengembalian, atau alokasi penerimaan tertunda setelah persetujuan.

Keputusan yang harus dibuat sebelum implementasi:

| Pilihan slot ketika klaim masuk tepat waktu | Implikasi |
| --- | --- |
| Klaim tidak memperpanjang hold | Pelanggan yang benar-benar membayar dapat kehilangan slot saat pemeriksaan terlambat; perlu prosedur penyelesaian dana |
| Hold diperpanjang selama pemeriksaan | Mengurangi risiko kehilangan slot, tetapi klaim palsu dapat menahan kapasitas; perlu batas/otorisasi dan mitigasi penyalahgunaan |
| Booking masuk antrean penyelesaian dengan perlindungan terbatas | Memerlukan batas waktu, eskalasi, dan status publik yang sangat jelas |

Target verifikasi **10 menit belum disetujui**. Harus diputuskan juga jam kerja pemeriksa, antrean melewati jam mulai, peringatan keterlambatan, dan siapa yang bertanggung jawab. Jangan membuat unggahan bukti menjadi jalan otomatis mengunci slot tanpa batas.

## 2. Usulan data dan layar

| Data/layar yang diusulkan | Isi dan batas |
| --- | --- |
| `payment_claims` | booking_id, claimed_amount/method, submitted_at server, bukti privat, status, reviewed_by/at, alasan keputusan dan versi aturan; belum berupa penerimaan |
| `cash_receipts` atau entitas penerimaan sepadan | Identitas transaksi sumber, akun/kanal tujuan, amount, `received_at` dari sumber yang diverifikasi, `recorded_at` server, pelaku, dan dasar/bukti verifikasi |
| Alokasi penerimaan ke booking | Satu penerimaan dialokasikan tanpa menggandakan uang; jumlah alokasi tidak melebihi penerimaan; dana yang belum memperoleh slot masuk penyelesaian yang dapat ditelusuri |
| `booking_events` / revisi jadwal | Sebelum/sesudah, alasan, waktu, pelaku dan keterkaitan persetujuan; peristiwa penting tidak dihapus |
| Pembatalan/refund/koreksi | Entitas permintaan dan persetujuan terpisah dari uang keluar aktual; tautan ke catatan asal |
| Status pelanggan | “Bukti diterima, menunggu pemeriksaan”, “Perlu informasi”, “Terkonfirmasi”, atau “Perlu penyelesaian jadwal/dana”; jangan menampilkan lunas hanya karena upload |
| Antrean pegawai | Umur antrean, tenggat, referensi booking, ketersediaan kini, ringkasan bukti dan siapa yang sedang menangani |
| Pengelola/pemberi persetujuan | Daftar kasus berisiko, alasan, jumlah, riwayat dan tindakan persetujuan sesuai kewenangan yang ditetapkan |

Penyimpanan bukti harus privat, dibatasi ukuran/tipe, diperiksa isinya, diberi nama acak, dan memiliki aturan akses/retensi. Hash gambar dapat memberi peringatan kemiripan, tetapi tidak membuktikan satu transaksi nyata yang sama: gambar dapat diedit, dan bukti yang sah bisa diunggah ulang.

Data lama tidak boleh memperoleh `received_at` rekaan dari `paid_at` Tahap 1. `paid_at` lama pada dasarnya adalah waktu pencatatan manual. Pertahankan asal data, beri penanda “waktu penerimaan belum diketahui”, dan tentukan perlakuan laporan untuk tanggal yang belum dapat dibuktikan.

## 3. Dua pegawai dan transfer yang sama

Usulan: normalisasi identitas transaksi dari sumber yang benar-benar diperiksa, misalnya kombinasi kanal/akun tujuan dan referensi transaksi sumber yang stabil. Tetapkan constraint unik hanya setelah makna referensi sumber dipastikan. Untuk transfer tanpa identitas andal, tampilkan peringatan kandidat duplikat dan wajibkan pemeriksaan, bukan menganggap nominal+waktu yang mirip sebagai kepastian.

Gunakan transaksi/constraint ketika klaim diambil dan disetujui. Dua pegawai tidak boleh sama-sama mengesahkan klaim yang telah diproses. Kunci idempotensi tetap dibutuhkan untuk retry, tetapi bukan pengganti identitas transfer nyata. Tunjukkan siapa yang memeriksa dan keputusan terdahulu; jangan mengandalkan tombol yang disembunyikan di UI.

## 4. Kedatangan, keterlambatan dan tidak datang

Mulai bermain di Tahap 1 hanya catatan tindakan pegawai. Usulan Tahap 2 menambah peristiwa kedatangan/tidak datang dengan waktu, pelaku dan alasan. Lewatnya jam mulai atau tidak adanya klik tidak membuktikan pelanggan absen. Keputusan melepaskan slot, pengurangan durasi, serta perlakuan dana harus disepakati.

Toleransi datang **15 menit belum disetujui**. Perlu membahas keterlambatan pegawai memasukkan data, gangguan perangkat/koneksi, pelanggan sudah hadir tetapi belum dicatat, dan siapa yang dapat membatalkan penetapan tidak datang.

## 5. Reschedule setelah DP atau lunas

Usulan: pertahankan identitas booking/kewajiban dan pembayaran asal; perubahan jadwal menjadi revisi yang memiliki riwayat. Saat menerapkan perubahan, periksa slot tujuan dan harga di bawah transaksi yang sama dengan perpindahan jadwal. Gagal memperoleh slot tujuan harus mempertahankan jadwal lama dan seluruh pembayaran.

Reschedule tidak menyalin pembayaran menjadi penerimaan baru. Jika harga berubah, perlu aturan penyesuaian tagihan, tambahan pembayaran atau pengembalian; semuanya belum diputuskan. Periksa permintaan simultan, perpindahan melewati tengah malam, booking yang sudah mulai, klaim pembayaran yang belum selesai, dan penentuan tanggal pada laporan.

Usulan **maksimal satu kali, paling lambat H-24, dengan lapangan/durasi/harga sama belum disetujui**. Tentukan juga makna H-24 (24 jam sebelum mulai atau batas kalender), pihak pemohon/pemberi persetujuan, dan pengecualian.

## 6. Pembatalan, dana ditahan dan pengembalian

Pisahkan kejadian pembatalan slot, penetapan kewajiban/tagihan, uang yang masih ditahan, persetujuan pengembalian, dan uang yang benar-benar dikembalikan. Booking batal tidak menghapus penerimaan semula. Persetujuan refund bukan bukti uang telah keluar.

Usulan status pengembalian: requested → approved/rejected → processing → paid_out/failed. Catat nominal, penerima/tujuan yang diverifikasi, referensi keluar, waktu keluar nyata, waktu catat dan petugas. Retry tidak membuat dua pengembalian. Jumlah pengembalian tidak boleh melampaui hak refund/dana yang masih tersedia setelah perhitungan disepakati.

**Potongan pembatalan Rp50.000 dan pengembalian sisanya belum disetujui.** Jangan otomatis menjadikan semua DP booking batal sebagai pendapatan atau dana hangus. Bedakan pembatalan pelanggan, pembatalan oleh pengelola, lapangan tidak tersedia, dan tidak datang.

## 7. Koreksi dan kewenangan

Usulan: catatan pembayaran asli tidak diedit/dihapus diam-diam. Koreksi merujuk catatan asal melalui pembalikan/penyesuaian dengan alasan, pelaku, waktu, bukti dan persetujuan. Setelah disahkan, laporan dapat menelusuri pengaruh koreksi tanpa menghitung catatan asal dan koreksinya dua kali.

| Kemampuan usulan | Pegawai | Pemberi persetujuan/pengelola |
| --- | --- | --- |
| Memasukkan klaim dan pemeriksaan awal | Sesuai tugas yang disetujui | Memantau antrean |
| Menyetujui penerimaan standar | Perlu keputusan kewenangan | Menangani eskalasi |
| Reschedule/pembatalan setelah dibayar | Mengajukan/mencatat alasan | Kewenangan akhir perlu ditentukan |
| Refund dan koreksi | Mengajukan, tidak menghapus asal | **Usulan** persetujuan oleh pemilik; belum diputuskan |

Identitas pemilik, delegasi saat tidak tersedia, larangan menyetujui permintaan sendiri, dan keadaan darurat masih harus dibahas. Tabel ini tidak mengubah `is_admin` tunggal yang ada pada Tahap 1.

## 8. Waktu dan laporan

Usulan laporan terpisah:

- Penerimaan bruto dari uang yang benar-benar masuk, dengan dasar `received_at` terverifikasi.
- Pencatatan/aktivitas pegawai dengan dasar `recorded_at`.
- Pengembalian aktual berdasarkan waktu uang keluar; daftar approved-belum-paid_out terpisah.
- Penerimaan bersih = penerimaan bruto − pengembalian aktual; bukan laba atau pendapatan akuntansi tanpa kebijakan tambahan.
- Sisa tagihan per booking berdasarkan kewajiban, alokasi pembayaran dan penyesuaian yang sah.
- Booking batal: penerimaan asal, dana ditahan, keputusan potongan, hak refund, pengembalian aktual dan saldo yang belum selesai ditampilkan terpisah.

Simpan UTC dan tampilkan/batasi periode dalam Asia/Jakarta. Penerimaan dan pencatatan pada hari/bulan berbeda harus tetap dapat ditelusuri. Angka klaim belum diperiksa tidak masuk penerimaan; nominal tagihan tidak ditambahkan sebagai uang masuk. Perlakuan tanggal tidak diketahui pada data lama perlu disepakati dan diberi penjelasan, bukan diisi otomatis.

## 9. Kasus uji yang direncanakan

| ID | Skenario dan hasil yang perlu disepakati |
| --- | --- |
| P2-T01 | Klaim tepat sebelum/tepat pada/setelah tenggat; server timestamp menjadi acuan; bukti belum dianggap pembayaran sah |
| P2-T02 | Klaim tepat waktu, verifikasi terlambat, slot masih kosong versus sudah terisi; tidak ada dua booking aktif |
| P2-T03 | Klaim palsu/ulang, bukti diedit, gangguan unggah, penahanan massal, dan petugas melewati target pemeriksaan |
| P2-T04 | Dua pegawai mengesahkan klaim/transfer yang sama bersamaan; satu penerimaan/alokasi sah; referensi tidak andal masuk pemeriksaan |
| P2-T05 | Hadir terlambat, hadir belum dicatat, penetapan/koreksi tidak datang; tidak otomatis dari jam |
| P2-T06 | Reschedule DP/lunas, slot tujuan konflik, dua permintaan bersamaan; jadwal lama dan pembayaran tidak hilang/duplikat |
| P2-T07 | Pembatalan setelah DP/penuh; dana ditahan, keputusan potongan, refund disetujui tetapi belum dibayar, pembayaran refund gagal dan retry |
| P2-T08 | Koreksi salah booking/nominal/metode/tanggal, permintaan tanpa wewenang, persetujuan sendiri, riwayat dan laporan sebelum/sesudah |
| P2-T09 | received_at/recorded_at/refunded_at berbeda hari/bulan; data lama tanpa waktu penerimaan; jumlah bruto/neto/sisa dapat direkonsiliasi |
| P2-T10 | Akses bukti pelanggan lain, unggahan berbahaya, retensi, pencatatan audit dan pemulihan backup |

Ini **rencana uji**, bukan hasil pelaksanaan. Belum ada evaluasi pengguna atau ukuran efektivitas yang disimpulkan.

## 10. Keputusan terbuka dan urutan lanjut

Kelompok/pengelola perlu menyepakati kebijakan klaim/hold, kewenangan verifikasi, sumber identitas transaksi, jam dan target pemeriksaan, kedatangan/no-show, reschedule, pembatalan/refund/koreksi, dasar laporan, privasi bukti dan retensi. Batas booking **30 hari** masih usulan; Tahap 1 tetap 90 hari.

Setelah keputusan dicatat beserta pihak peninjau/tanggal/alasan: tinjau alur dan rancangan data → tetapkan kriteria penerimaan → implementasi pada branch berikutnya → pengujian teknis → evaluasi pengguna sesuai kesediaan → keputusan penerapan oleh pihak yang bertanggung jawab. Tidak ada perubahan proposal akademik atau klaim bahwa seluruh tahapan Waterfall telah selesai.
