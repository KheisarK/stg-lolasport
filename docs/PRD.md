# PRD — Sistem Informasi Reservasi Lola Sport

| Metadata | Nilai |
| --- | --- |
| Versi | 0.1 |
| Tanggal penyusunan | 11 Oktober 2026 |
| Status | Draft kebutuhan untuk ditinjau kelompok dan pengelola |
| Arah solusi | Alternatif 1: reservasi melalui web, sementara menjadi pilihan yang diunggulkan kelompok |
| Metode pengembangan | Waterfall, sesuai draft proposal kelompok |
| Status implementasi | Belum diperiksa dalam penyusunan dokumen ini |

Dokumen ini menjadi bahan untuk menyepakati kebutuhan dan mencocokkannya dengan project yang sudah dikerjakan. Arah solusi, aturan yang diusulkan, dan kriteria penerimaan di bawah belum berarti telah disetujui pengelola. Fitur yang ditemukan dalam kode juga tidak otomatis menjadi kebutuhan yang disepakati.

## 1. Dasar dan tingkat kepastian

Sumber konteks:

- **S1 — Wawancara awal:** transkrip dengan Pasha Pramadewa, suami pemilik Lola Sport, yang diberikan pengguna. Tanggal wawancara belum dicantumkan.
- **S2 — Diskusi kelompok:** gambaran pelanggan memilih lapangan dan jadwal melalui web, mengisi data diri, memilih DP atau pembayaran penuh, kemudian melunasi sisa DP di tempat. Pemilihan alternatif 1 masih sementara.
- **S3 — Draft proposal:** III.3.1 Sistematika Pengembangan yang diberikan pengguna, meliputi analisis, perancangan, implementasi, pengujian, serta penyiapan dan evaluasi pengguna.

Penanda yang digunakan:

| Penanda | Makna |
| --- | --- |
| Fakta wawancara | Dilaporkan narasumber dalam S1; belum merupakan hasil observasi tambahan |
| Rencana kelompok | Arah yang disampaikan dalam S2 atau S3 |
| Usulan kebutuhan | Rekomendasi rancangan untuk ditinjau dan disepakati |
| Belum diputuskan | Memerlukan jawaban kelompok atau pengelola |
| Implementasi terverifikasi | Hanya boleh digunakan setelah pemeriksaan repo atau lingkungan disertai bukti |

## 2. Kondisi awal dan masalah

Berdasarkan S1, Lola Sport mempunyai dua lapangan futsal, yaitu sintetis dan vinil, serta dua lapangan badminton. Usaha juga menyediakan gym. Dua pegawai menangani kegiatan operasional, reservasi, dan pembukuan. Perangkat yang tersedia untuk operasional adalah handphone; narasumber menyatakan belum pernah menggunakan aplikasi untuk membantu kegiatan tersebut.

Reservasi diterima melalui WhatsApp atau kedatangan langsung dan dicatat dalam buku. Pembayaran menggunakan uang muka sebagai tanda jadi, dengan metode QRIS, transfer, atau tunai. Catatan buku juga memuat pembayaran penuh atau DP. Pemantauan dan perhitungan bulanan masih dilakukan secara manual.

Masalah yang dilaporkan:

1. Reservasi kadang tidak tercatat atau terlupa.
2. Jadwal reservasi dapat berbenturan.
3. Catatan pembayaran tidak lengkap, sehingga pegawai perlu mengonfirmasi kembali kepada pelanggan.
4. Kesalahan perhitungan dan transaksi yang terlewat menyulitkan pembukuan bulanan.

Jam buka disebut mulai pukul 07.00; jam tutup belum diketahui. Pernyataan DP hangus disampaikan untuk pelanggan yang tidak jadi datang. Aturan rinci pembatalan, reschedule, dan pengecualiannya belum lengkap.

## 3. Tujuan pengembangan

Usulan tujuan sistem:

- Memusatkan reservasi pelanggan dan reservasi yang dimasukkan pegawai dalam satu sumber jadwal.
- Memudahkan pemeriksaan ketersediaan berdasarkan tanggal, lapangan, waktu mulai, dan durasi.
- Memeriksa konflik saat booking disimpan atau diubah, termasuk permintaan yang terjadi bersamaan.
- Menghubungkan booking dengan catatan pembayaran DP, pembayaran penuh, dan pelunasan.
- Menyediakan laporan periodik yang dapat ditelusuri ke catatan pembentuknya.
- Mendukung penggunaan melalui handphone dan evaluasi oleh pengguna yang relevan.

Tujuan tersebut merupakan sasaran rancangan, bukan klaim bahwa sistem telah mengurangi kesalahan atau meningkatkan omzet. Dampak operasional perlu dievaluasi setelah penggunaan; pengujian fungsi saja belum membuktikan dampak tersebut.

## 4. Pengguna dan akses yang diusulkan

| Peran | Kebutuhan utama | Batas akses yang diusulkan |
| --- | --- | --- |
| Pelanggan | Melihat jadwal, membuat booking, menerima informasi tagihan dan status | Mengakses booking miliknya melalui mekanisme identifikasi yang disepakati |
| Pegawai operasional | Melihat jadwal, memasukkan booking WA/onsite, memeriksa pembayaran, mencatat pelunasan | Fungsi operasional sesuai kewenangan yang disetujui pengelola |
| Pemilik/pengelola yang berwenang | Memantau reservasi, pembayaran, dan laporan | Akses laporan dan pengaturan sesuai pembagian kewenangan |

Pembuatan akun pelanggan atau penggunaan booking tanpa akun belum diputuskan. Hak mengubah tarif, membatalkan booking, mengoreksi pembayaran, dan melihat laporan perlu ditetapkan; tidak diasumsikan semua pegawai memiliki akses yang sama. Pasha tidak otomatis ditetapkan sebagai pemilik atau pemegang seluruh kewenangan sistem.

## 5. Ruang lingkup yang diusulkan

### 5.1 Cakupan awal

1. Informasi dua lapangan futsal dan dua lapangan badminton.
2. Ketersediaan jadwal berdasarkan tanggal dan lapangan.
3. Reservasi pelanggan melalui web.
4. Input reservasi oleh pegawai untuk pelanggan WA atau datang langsung.
5. Pemeriksaan konflik dan pengelolaan status reservasi.
6. Pilihan DP atau pembayaran penuh; verifikasi dan pencatatan sesuai mekanisme yang dipilih.
7. Pencatatan pelunasan di tempat dan penelusuran sisa tagihan.
8. Pengelolaan pembatalan dan perubahan jadwal menurut aturan yang disepakati.
9. Rekap reservasi dan pembayaran harian/bulanan, dengan dasar perhitungan yang jelas.
10. Hak akses, panduan penggunaan, dan bukti pengujian.

### 5.2 Di luar cakupan awal yang diusulkan

Manajemen gym, turnamen, marketplace banyak venue, chatbot WhatsApp/LLM, program loyalitas, dan aplikasi mobile native tidak dimasukkan dalam draft cakupan awal. Usulan fitur tambahan harus ditinjau bersama kelompok dan pengelola sebelum menjadi pekerjaan proyek. Format ekspor laporan belum diputuskan.

## 6. Alur operasional yang diusulkan

### 6.1 Booking melalui web

1. Pelanggan memilih lapangan, tanggal, waktu mulai, dan durasi yang diizinkan.
2. Sistem menampilkan ketersediaan dan rincian harga berdasarkan tarif yang berlaku.
3. Pelanggan mengisi data pemesan minimum dan memilih DP atau pembayaran penuh.
4. Sistem memeriksa kembali ketersediaan sebelum mengamankan slot atau memulai pembayaran. Halaman jadwal yang sebelumnya terbuka tidak dianggap bukti bahwa slot masih tersedia.
5. Pelanggan menjalankan pembayaran menggunakan mekanisme yang dipilih kelompok: verifikasi manual atau gateway. Pemilihan mekanisme ini belum final.
6. Sistem/pegawai memverifikasi pembayaran dan mengubah status menurut aturan konfirmasi booking yang disepakati.
7. Pelanggan menerima referensi booking beserta lapangan, jadwal, total tagihan, pembayaran yang diterima, sisa tagihan, dan statusnya.

Titik mulai penahanan slot, lama penahanan, masa berlaku pembayaran, dan kondisi pelepasan slot harus ditetapkan sebelum alur ini dianggap final. Jika pembayaran diterima setelah slot kedaluwarsa, sistem harus menyediakan status penanganan yang jelas; tidak boleh otomatis mengonfirmasi slot yang sudah diambil booking lain.

### 6.2 Booking melalui pegawai

1. Pegawai menerima permintaan melalui WA atau langsung di tempat.
2. Pegawai memeriksa jadwal dan memasukkan booking ke sistem yang sama.
3. Sistem menjalankan pemeriksaan konflik yang sama dengan booking melalui web.
4. Pegawai mencatat atau memverifikasi pembayaran dan memberi konfirmasi sesuai aturan.

SOP perlu menentukan kapan pegawai harus memasukkan booking. Jika pencatatan ditunda sementara pegawai sudah menjanjikan slot, pemeriksaan sistem tidak dapat mengamankan janji yang belum tercatat.

### 6.3 Pelunasan di tempat

Pegawai mencari booking menggunakan referensi atau data pemesan yang diizinkan, memeriksa sisa tagihan, menerima pembayaran, lalu menyimpan catatan pelunasan. Pembayaran harus dikaitkan dengan booking yang sama. Status lunas ditetapkan ketika jumlah pembayaran valid memenuhi tagihan menurut aturan yang disepakati.

### 6.4 Pembatalan dan perubahan jadwal

Pegawai atau pihak yang berwenang memproses permintaan berdasarkan kebijakan Lola. Untuk perubahan jadwal, tujuan jadwal harus diperiksa kembali saat perubahan diterapkan. Jika perubahan gagal, booking lama tidak boleh hilang. Riwayat pembayaran harus tetap dapat ditelusuri, termasuk jika tarif jadwal baru berbeda.

Untuk pembatalan, catatan booking dan pembayaran dipertahankan sebagai riwayat. Perlakuan terhadap DP, pengembalian dana jika ada, pelepasan slot, dan pencatatan laporan mengikuti kebijakan yang disepakati. Pernyataan dalam wawancara tentang DP hangus belum dianggap aturan lengkap untuk semua jenis pembatalan.

## 7. Aturan bisnis dan keputusan terbuka

| ID | Aturan/pertanyaan | Status dan dasar |
| --- | --- | --- |
| BR-01 | Dua lapangan futsal dan dua badminton; ID/nama operasional masing-masing perlu dicatat | Fakta S1; penamaan sistem belum diputuskan |
| BR-02 | Jam buka 07.00; jam tutup, hari libur, dan jadwal penutupan perlu dikonfirmasi | Fakta sebagian dari S1 |
| BR-03 | Tarif tiap lapangan, variasi hari/jam, durasi minimum, dan kelipatan durasi | Belum diputuskan |
| BR-04 | Besaran DP: persentase atau nominal, minimum, dan aturan pembulatan | Belum diputuskan |
| BR-05 | Kondisi booking terkonfirmasi: pembayaran terverifikasi dan/atau persetujuan pegawai | Belum diputuskan |
| BR-06 | Awal penahanan slot, batas pembayaran, dan kapan slot dilepas | Belum diputuskan |
| BR-07 | QRIS, transfer, dan tunai tersedia; kanal pembayaran online perlu dipilih | Fakta S1; integrasi belum diputuskan |
| BR-08 | Akun pelanggan atau booking tanpa akun; cara mengakses booking dengan aman | Belum diputuskan |
| BR-09 | Sisa DP dilunasi di tempat; waktu tepat pelunasan dan kewenangan pencatatan | Rencana S2; rincian belum diputuskan |
| BR-10 | DP hangus jika tidak jadi datang; aturan cancel, no-show, refund, dan pengecualian | Fakta sebagian dari S1; rincian belum diputuskan |
| BR-11 | Batas reschedule, frekuensi, perubahan tarif, dan pemberi persetujuan | Belum diputuskan |
| BR-12 | Dasar laporan: tanggal bermain, tanggal penerimaan pembayaran, atau ringkasan terpisah | Belum diputuskan |
| BR-13 | Pembagian hak akses, koreksi transaksi, dan pencatatan pelaku perubahan | Belum diputuskan |
| BR-14 | Jadwal ditutup untuk pemeliharaan/kegiatan lain dan siapa yang dapat mengaturnya | Usulan; perlu konfirmasi kebutuhan |

Informasi terbuka dicatat sebagai keputusan tertunda, bukan diisi angka atau kebijakan rekaan. Keputusan yang disetujui perlu memuat tanggal, pihak peninjau, alasan, dan kebutuhan yang terdampak.

## 8. Kebutuhan fungsional yang diusulkan

Prioritas **inti** berarti dibutuhkan untuk mencapai tujuan cakupan awal; **konfirmasi** berarti kebutuhannya perlu dipastikan sebelum menjadi komitmen pengembangan.

| ID | Kebutuhan | Prioritas | Kriteria penerimaan yang diusulkan |
| --- | --- | --- | --- |
| FR-01 | Menampilkan lapangan dan ketersediaan per tanggal | Inti | Jadwal sesuai data aktif; nama/kontak pelanggan dan rincian pembayaran tidak ditampilkan ke publik |
| FR-02 | Menyusun booking dan menghitung tagihan | Inti | Lapangan, tanggal, rentang waktu, dan data wajib divalidasi; harga dihitung dari aturan yang disepakati, bukan dipercaya dari input pelanggan |
| FR-03 | Memeriksa jadwal bertumpang tindih | Inti | Booking yang konflik pada lapangan yang sama ditolak; permintaan bersamaan tidak menghasilkan dua booking aktif pada rentang yang sama |
| FR-04 | Memasukkan booking WA/onsite melalui pegawai | Inti | Masuk ke sumber jadwal yang sama dan tunduk pada aturan konflik yang sama |
| FR-05 | Mengelola penahanan slot dan masa berlaku | Konfirmasi | Slot dilepas sesuai aturan; pembayaran yang datang terlambat masuk penanganan yang ditetapkan |
| FR-06 | Menawarkan DP atau pembayaran penuh | Inti | Nominal awal, total tagihan, dan sisa pembayaran terlihat; memilih opsi tidak dianggap sebagai bukti pembayaran |
| FR-07 | Memverifikasi serta mencatat pembayaran | Inti | Pembayaran valid memiliki nominal, waktu, metode, referensi, dan kaitan booking; upaya gagal tidak dihitung sebagai uang diterima |
| FR-08 | Mencatat pelunasan di tempat | Inti | Sisa tagihan berubah dengan benar; pengulangan permintaan yang sama tidak membuat catatan pembayaran ganda |
| FR-09 | Menampilkan referensi dan status booking | Inti | Pengguna dapat memeriksa booking yang berhak diakses beserta status pembayaran dan sisa tagihannya |
| FR-10 | Mengelola pembatalan | Inti | Status, jadwal, dan perlakuan pembayaran konsisten dengan kebijakan; riwayat tidak dihapus begitu saja |
| FR-11 | Mengelola reschedule | Konfirmasi | Jadwal tujuan diperiksa saat diterapkan; kegagalan tidak menghilangkan booking lama atau menggandakan pembayaran |
| FR-12 | Menampilkan jadwal operasional pegawai | Inti | Dapat diperiksa berdasarkan tanggal dan lapangan, termasuk pemesan, jam, durasi, dan status yang diizinkan |
| FR-13 | Menyusun laporan periodik | Inti | Hasil sesuai nilai acuan terpisah, menunjukkan dasar tanggal/perhitungan, dan dapat ditelusuri ke booking/pembayaran |
| FR-14 | Membatasi akses menurut peran | Inti | Pelanggan tidak dapat mengakses booking pelanggan lain; fungsi dan laporan pegawai/pemilik mengikuti kewenangan yang disepakati |
| FR-15 | Menelusuri perubahan penting | Inti | Perubahan status, pembayaran, dan koreksi memiliki waktu serta pelaku/sumber perubahan yang dapat diperiksa |
| FR-16 | Mengelola tarif dan penutupan jadwal | Konfirmasi | Setelah disepakati, pengaturan hanya dapat dilakukan oleh pihak berwenang dan tidak merusak riwayat harga booking |

## 9. Status dan pencatatan pembayaran

Status reservasi dan status pembayaran harus dibedakan secara konseptual. Usulan status reservasi: **menunggu proses/pembayaran**, **terkonfirmasi**, **selesai digunakan**, **dibatalkan**, dan **kedaluwarsa**. Usulan status pembayaran tagihan: **belum dibayar**, **dibayar sebagian**, dan **lunas**. Nama teknis, perpindahan status, serta status yang memblokir jadwal ditentukan pada perancangan setelah BR-05 dan BR-06 disepakati.

Contoh kemungkinan: booking terkonfirmasi dengan pembayaran sebagian setelah DP valid, atau booking terkonfirmasi dengan tagihan lunas setelah pembayaran penuh valid. Memilih opsi penuh tidak langsung mengubah status menjadi lunas. Upaya pembayaran dapat memiliki status gagal atau tertunda tanpa menjadi penerimaan yang sah.

**Data contoh, bukan tarif atau ketentuan Lola:** tagihan Rp300.000, DP Rp100.000, dan pelunasan Rp200.000. Setelah keduanya valid, jumlah pembayaran untuk booking tersebut Rp300.000. Nilai tagihan tidak ditambahkan lagi ke pembayaran menjadi Rp600.000. Jika DP dan pelunasan diterima pada tanggal berbeda, laporan harus memperlakukan tanggal tersebut sesuai dasar yang disepakati.

Jika menggunakan gateway, pemberitahuan transaksi diperiksa di server dan diproses tanpa menggandakan catatan ketika pemberitahuan yang sama datang lagi. Jika menggunakan verifikasi manual, bukti yang diunggah belum dianggap pembayaran valid sebelum diperiksa pegawai. Provider, biaya, dan prosedur koreksi/refund belum dipilih.

## 10. Data minimum dan rancangan konseptual

Ini kebutuhan informasi, belum merupakan skema tabel final:

| Kelompok data | Informasi yang diperlukan |
| --- | --- |
| Lapangan | Identitas, jenis, nama, dan pengaturan ketersediaan yang disepakati |
| Booking | Referensi, pemesan/kontak, lapangan, tanggal, jam mulai/akhir, total tagihan saat booking, kanal, status, dan waktu pencatatan |
| Pembayaran | Referensi transaksi, booking terkait, nominal, metode, waktu, hasil verifikasi/status, dan sumber atau pelaku pencatatan |
| Pengguna/akses | Identitas pengguna sistem dan kewenangannya; mekanisme identifikasi pelanggan masih terbuka |
| Riwayat | Perubahan status, jadwal, nominal/koreksi, waktu, pelaku, dan alasan yang relevan |

Satu booking perlu dapat ditautkan ke lebih dari satu pembayaran agar DP dan pelunasan dapat ditelusuri. Data pelanggan yang dikumpulkan dibatasi pada kebutuhan operasional; email, alamat, dan dokumen identitas tidak otomatis menjadi kolom wajib. Harga historis booking perlu tetap dapat dipahami setelah tarif berubah.

## 11. Kebutuhan nonfungsional yang diusulkan

- **Penggunaan melalui handphone:** alur pelanggan dan pegawai diuji pada perangkat/browser representatif; informasi jadwal, sisa tagihan, dan tombol utama dapat digunakan tanpa kehilangan informasi penting.
- **Konsistensi data:** pemeriksaan konflik tetap berlaku di server dan saat penyimpanan bersamaan; pengulangan permintaan tidak menghasilkan pencatatan ganda.
- **Hak akses dan privasi:** pembatasan diterapkan di server; data pribadi tidak muncul pada jadwal publik; kredensial tidak disimpan di dokumentasi yang dibagikan.
- **Kejelasan kegagalan:** kesalahan validasi, jaringan, atau pembayaran menghasilkan informasi status yang dapat dipahami dan langkah lanjut yang jelas.
- **Pemulihan:** kebutuhan backup, pemulihan data, serta penanggung jawab pemeliharaan ditetapkan sebelum penggunaan operasional. Jika disiapkan, pemulihannya perlu dicoba.
- **Kinerja:** waktu respons dan kondisi pengujian dicatat. Target, jumlah pengguna bersamaan, serta batas penerimaan disepakati sebelum pengujian; tidak ada angka performa yang diasumsikan dalam draft ini.

## 12. Pengujian dan evaluasi

Semua baris berikut adalah **rencana pengujian**, belum hasil pelaksanaan:

| ID | Skenario | Hasil yang diharapkan | Kebutuhan terkait |
| --- | --- | --- | --- |
| T-01 | Booking valid serta input tanggal/durasi di luar aturan | Yang valid tersimpan; input tidak valid ditolak tanpa catatan parsial | FR-01, FR-02, FR-09 |
| T-02 | Booking bertumpang tindih dan booking pada lapangan berbeda | Konflik lapangan yang sama ditolak; lapangan berbeda mengikuti ketersediaannya sendiri | FR-03 |
| T-03 | Dua pelanggan atau pelanggan dan pegawai mengirim booking bersamaan | Tidak terbentuk dua booking aktif yang konflik | FR-03, FR-04 |
| T-04 | Penahanan slot habis dan pembayaran datang setelah masa berlaku | Pelepasan dan penanganan pembayaran mengikuti keputusan BR-06 | FR-05, FR-07 |
| T-05 | Pilihan penuh belum dibayar; pembayaran gagal/tertunda | Tidak ditandai lunas atau dihitung sebagai penerimaan valid | FR-06, FR-07, FR-09 |
| T-06 | DP dan pelunasan contoh; pengiriman ulang catatan/notifikasi | Sisa tagihan benar; pembayaran tidak digandakan | FR-07, FR-08 |
| T-07 | Pembatalan atau reschedule setelah pembayaran | Riwayat, jadwal, dan pembayaran konsisten; jika perubahan gagal, booking lama tetap ada | FR-10, FR-11, FR-15 |
| T-08 | DP dan pelunasan pada tanggal berbeda; booking dibatalkan | Laporan cocok dengan acuan terpisah dan dasar perhitungan yang disepakati | FR-13 |
| T-09 | Pelanggan mencoba booking orang lain; pegawai mencoba fungsi di luar akses | Akses ditolak di server; data publik tidak membocorkan identitas | FR-01, FR-14 |
| T-10 | Pelanggan dan pegawai menjalankan tugas melalui handphone | Keberhasilan tugas, kesalahan, hambatan, dan waktu pelaksanaan dicatat | FR-01, FR-04, FR-08, FR-12 |
| T-11 | Koreksi transaksi/perubahan tarif atau penutupan jadwal, jika disetujui | Perubahan berwenang dapat ditelusuri; riwayat harga dan pembayaran terjaga | FR-15, FR-16 |

Kriteria penerimaan ditinjau bersama pengelola. Kegagalan dicatat, diperbaiki, dan diuji ulang pada bagian yang terdampak. Evaluasi pengguna melibatkan pegawai, pengelola yang berwenang, dan pelanggan yang relevan sesuai kesediaan mereka; jumlah peserta serta instrumen belum ditetapkan. Rancangan juga dapat ditinjau sebelum implementasi, tidak hanya setelah sistem selesai.

Draft ini mendukung penyusunan III.3.3, tetapi tidak menggantikan penetapan mekanisme, instrumen, dan profil peserta dalam proposal. Hasil uji lokal, staging, evaluasi pengguna, dan dampak operasional harus dilaporkan terpisah.

## 13. Keterkaitan dengan tahapan pengembangan

| Tahap dalam III.3.1 | Keluaran kerja yang direncanakan |
| --- | --- |
| Analisis kebutuhan | PRD/dokumen kebutuhan yang ditinjau, aturan bisnis, keputusan terbuka, dan kriteria penerimaan |
| Perancangan sistem | Alur pelanggan/pegawai, hubungan data, aturan status, rancangan antarmuka, dan spesifikasi fungsi |
| Implementasi sistem | Kode, integrasi fungsi, dokumentasi cara menjalankan, serta pemeriksaan komponen |
| Pengujian sistem | Data uji, hasil aktual versus harapan, ketidaksesuaian, dan bukti perbaikan |
| Penyiapan dan evaluasi pengguna | Lingkungan uji, tugas pengguna, umpan balik, perbaikan, panduan, dan rekomendasi penerapan |

Perubahan yang memengaruhi kebutuhan, data, atau cakupan ditinjau dan dicatat sebelum diterapkan. Dokumen proposal dan pekerjaan teknis perlu tetap konsisten tanpa mengklaim seluruh tahap telah selesai.

## 14. Pemeriksaan yang diminta dari Codex

1. Cari dokumentasi kebutuhan dan README yang sudah ada sebelum membuat dokumen pengganti.
2. Periksa tiap kebutuhan terhadap kode dan lingkungan yang dapat diakses; beri path file dan bukti pengujian jika tersedia.
3. Pisahkan fitur yang sudah dibuat, sudah diuji, direncanakan, dan berbeda dari draft ini.
4. Laporkan repo, branch, teknologi, serta kondisi `stg-lolasport`; nama staging tidak membuktikan bahwa deployment berhasil.
5. Laporkan keputusan yang perlu dibahas kelompok/pengelola. Jangan menetapkan nominal DP, provider, atau kebijakan operasional tanpa dasar.

## 15. Referensi praktik

Referensi ini membantu mengkaji pola rancangan; tidak menjadi bukti kebutuhan atau hasil implementasi Lola:

- [AYO Venue Management — fitur](https://ayo.co.id/venue/features): reservasi pelanggan, input manual, dan opsi DP/penuh.
- [CourtReserve — pembayaran melalui admin](https://help.courtreserve.com/en/articles/8548565-manage-payments-on-the-admin-panel): pembayaran sebagian dan sisa tagihan.
- [Midtrans — notifikasi pembayaran](https://docs.midtrans.com/docs/https-notification-webhooks): verifikasi dan penanganan notifikasi jika gateway ini dipilih.
- [Cal.diy — panduan kontribusi](https://github.com/calcom/cal.diy/blob/main/CONTRIBUTING.md): konteks perubahan, issue, dan bukti pengujian.

## 16. Riwayat dokumen

| Versi | Perubahan | Status |
| --- | --- | --- |
| 0.1 | Draft awal berdasarkan S1–S3 dan referensi praktik; implementasi belum diperiksa | Menunggu peninjauan kelompok/pengelola dan pencocokan dengan repo |
