# Kebutuhan prototipe v2 — Tahap 1

Tanggal pencatatan: **16 Oktober 2026, Asia/Jakarta**. Dasar pekerjaan adalah permintaan pembaruan pengguna pada percakapan pengembangan. Dokumen ini tidak merupakan persetujuan pengelola, berita acara wawancara baru, UAT, atau perubahan proposal akademik.

## Asal dan tingkat kepastian

| Sumber | Isi | Status |
| --- | --- | --- |
| U2 — informasi pembahasan Lola yang disampaikan pengguna | Sintetis Rp130.000/jam, vinyl Rp160.000/jam; Senin–Minggu 07.00–00.00; kelipatan satu jam; DP Rp50.000; lunas sebelum bermain | Informasi narasumber melalui pengguna; transkrip/konfirmasi tambahan belum diperiksa. Perlu ditinjau sebelum operasional |
| P2 — pilihan sementara pengguna untuk percobaan | Badminton Rp100.000/jam; DP Rp50.000 per booking; kurang dari 60 menit sebelum mulai wajib penuh | Pilihan prototipe; badminton belum dikonfirmasi wawancara |
| D2 — asumsi demo yang dipertahankan | Awal jadwal jam bulat, durasi 1/2/3 jam, hold maksimal 20 menit, batas 90 hari, pembayaran manual demo, pembatalan hanya pending belum dibayar | Bukan ketentuan resmi Lola |
| D3 — penetapan teknis demo setelah pengguna menyerahkan pilihannya | Mulai bermain dicatat pegawai hanya pada rentang mulai inklusif–selesai eksklusif; tarif khusus tidak ditimpa otomatis; nominal selain pilihan standar ditunda | Perlu ditinjau pengelola; tidak menjadi aturan akses fisik |
| R2 — referensi desain | Sumber visual prototipe olahraga dan panduan W3C dalam `design-notes-v2.md` | Referensi tampilan; tidak membuktikan kebutuhan, efektivitas Lola, atau aturan pembayaran |

Nama data yang sudah ada `Futsal Vinil` dipertahankan agar ID dan relasinya stabil; istilah pengguna “vinyl” mengacu pada lapangan tersebut. Tidak ada penamaan ulang booking historis.

## Penerimaan dan keterlacakan

| ID | Kebutuhan dan sumber | Implementasi utama | Kasus uji |
| --- | --- | --- | --- |
| R2-01 | Tarif baru untuk booking baru, U2/P2 | `backend/config/lola_tariffs.php`, seeder; snapshot `BookingService::create` | `RulesV2Test::test_new_tariffs_and_flat_dp_for_one_two_and_three_hours` |
| R2-02 | 07.00–00.00 setiap hari, penutupan pada tanggal berikutnya; U2 | `config/lola.php` close_hour=24; perhitungan start/end server; UI memakai tanggal akhir sebenarnya | `RulesV2Test::test_midnight_is_next_date_and_does_not_allow_spilling_past_close` |
| R2-03 | Durasi 1–3 jam, jam bulat dan konflik lapangan yang sama; U2/D2 | Validasi server, interval awal inklusif/akhir eksklusif, SQLite IMMEDIATE | `BookingFlowTest` konflik/adjacency; `ConcurrencyTest` dua proses |
| R2-04 | DP Rp50.000 per booking, bukan dikalikan durasi; U2/P2 | Snapshot `initial_due`; pilihan penuh tetap tersedia | `RulesV2Test` tarif/DP, penuh bukan bukti pembayaran |
| R2-05 | Kurang dari 60 menit wajib penuh, tepat 60 menit boleh DP; P2 | Diperiksa ulang setelah memperoleh transaksi; preview memakai waktu server | `RulesV2Test::test_exact_sixty_minutes_allows_dp_but_one_second_less_requires_full` |
| R2-06 | Pembaruan tarif eksplisit dan aman diulang; D3 | `lola:rates-v2`: preview, ID pilihan, konfirmasi, guard tarif/nama/jenis dan pembatalan atomik bila berubah | `TariffUpdateTest`; `LegacyUpgradeTest` pada file SQLite sementara |
| R2-07 | Awal DP/penuh, lalu pelunasan sisa, nominal dihitung server; D3 | `payment_choices`, payload kind/expected_amount/method/key; raw amount ditolak; review identitas sebelum konfirmasi | `RulesV2Test` manipulasi/standar; `BookingFlowTest` idempotensi; browser retry setelah respons terputus dan reload |
| R2-08 | Lunas sebelum bermain dan tindakan pegawai; U2/D3 | `BookingService::start`, middleware admin, `started_at/started_by`; reservasi tetap confirmed | `RulesV2Test` sah/lunas/waktu; `BookingFlowTest` 401/403; `ConcurrencyTest` dua pegawai mulai bersamaan |
| R2-09 | Hold dari penyimpanan, maksimal 20 menit dan tidak melewati waktu mulai; D2 | expires_at=min(created+20 menit, starts_at), status efektif; confirmed tidak memakai timer pending | `RulesV2Test::test_hold_begins_on_save_and_is_capped_at_start_and_confirmed_never_expires` |
| R2-10 | Manual demo; pembayaran dilaporkan tetapi belum diperiksa tidak otomatis sah; D2 | Tidak ada upload/claim/verifikasi tertunda pada Tahap 1; booking expired menolak pembayaran | `BookingFlowTest` expiry; rancangan `phase-2-design.md` |
| R2-11 | Batas booking tetap 90 hari; D2 | `config/lola.php`, validasi server dan batas tanggal UI | `RulesV2Test::test_horizon_remains_ninety_days_including_dates_beyond_thirty`; usulan 30 hari hanya di rancangan |
| R2-12 | Alur pelanggan dan pegawai melalui handphone; D3/R2 | Kontrol besar, tanggal cepat, review pembayaran, rincian mobile dan penanda mulai | `frontend/tests/booking.spec.ts`; desktop/Pixel 7 dan 320px; bukan UAT/perangkat nyata |
| R2-13 | Setup Windows/Linux sesuai lockfile, konfigurasi/data dipertahankan | Preflight `scripts/check-runtime.php`, setup .sh/.ps1; CI mengaktifkan fileinfo | `scripts/tests/runtime.test.mjs`, CI dan bukti di `implementation-status.md` |

Awal jadwal jam bulat adalah asumsi demo lama; informasi kelipatan sewa satu jam dari pengguna menetapkan durasi, bukan bukti terpisah bahwa semua kedatangan lapangan selalu tepat jam bulat.

Aturan mendadak diterapkan **pada saat booking dibuat**, bukan diberlakukan mundur terhadap booking yang sudah memiliki snapshot DP. Semua kanal mengikuti aturan waktu yang sama; booking onsite untuk jadwal mendatang tetap boleh DP.

## Hubungan data dan batas Tahap 1

Lapangan memiliki banyak booking. Booking menyimpan tarif/jam, total, opsi pembayaran dan nilai awal pada saat dibuat. Pembayaran terhubung ke booking yang sama; jumlah pembayaran valid menentukan sisa dan status pembayaran. Pembaruan lapangan tidak menghitung ulang snapshot tersebut.

Migration v2 hanya menambah `bookings.started_at`, `bookings.started_by`, dan `payments.kind` yang nullable. Booking lama tetap memiliki waktu mulai bermain kosong; jenis pembayaran lama dibiarkan null dan ditampilkan sebagai “Catatan awal”. Tidak ada penafsiran ulang bahwa pelanggan lama sudah hadir.

| Aspek | Status/perilaku |
| --- | --- |
| Reservasi | pending → confirmed setelah pembayaran awal sah; pending → expired berdasarkan tenggat atau cancelled oleh pegawai sebelum dibayar |
| Pembayaran | unpaid/partial/paid dihitung dari jumlah catatan pembayaran; pilihan DP/penuh belum merupakan penerimaan |
| Mulai bermain | Belum dicatat atau sudah dicatat melalui started_at; tidak otomatis dari lewatnya jam; satu waktu dan satu pelaku pertama dipertahankan |
| Pembayaran standar | Belum dibayar dan opsi DP: DP snapshot atau seluruh tagihan. Opsi penuh: seluruh tagihan. Setelah pembayaran sebagian: hanya pelunasan sisa |
| Nominal lain | Belum didukung. Tunda pencatatan di demo dan cocokkan bersama pengelola; jangan menyesuaikan nominal menjadi angka rekaan |

`expected_amount` hanya pemeriksaan kesesuaian nominal yang ditinjau pegawai. Server memilih nominal dari snapshot dan sisa terkini; nominal dari browser tidak menjadi sumber uang yang dicatat. Kunci yang sama dengan isi berbeda ditolak. Kunci sama/isi sama mengembalikan hasil sebelumnya, termasuk setelah halaman dimuat ulang.

Idempotensi tidak membuktikan bahwa dua input baru berasal dari dua transfer nyata yang berbeda. Identifikasi transfer nyata, bukti pembayaran, alokasi penerimaan, verifikasi terlambat, refund dan koreksi belum tersedia.

Untuk tarif khusus dengan total tidak melebihi Rp50.000, opsi DP ditolak dan digunakan opsi penuh agar pencatatan tidak melebihi tagihan. Ini pengaman konsistensi demo, bukan tarif minimum Lola.

## Penerapan pada database yang sudah terisi

Setup/migration tidak menjalankan pembaruan tarif. Setelah backup konsisten dan setup selesai, jalankan dari `backend/`:

```text
php artisan lola:rates-v2
php artisan lola:rates-v2 --apply --court=1 --court=2 --court=3 --court=4
```

ID pada perintah kedua hanya contoh; gunakan ID yang sudah diperiksa pada preview database sendiri. Nilai lama dan usulan baru ditampilkan sebelum konfirmasi. Hanya nama/jenis dan tarif lama bawaan yang cocok dapat diubah. Nilai khusus dilewati, sekalipun ID-nya dipilih. Jika nilainya berubah setelah preview, seluruh pembaruan dibatalkan. Pengulangan setelah berhasil tidak mengubah apa pun lagi. Tarif khusus memerlukan pembahasan terpisah.

Tidak ada `migrate:fresh`, penghapusan booking, atau perubahan pembayaran lama. Petunjuk backup lokal dan pergantian branch Windows ada di `windows.md`.

## Menunggu keputusan

Semua nilai usulan Tahap 2 tetap terbuka: target verifikasi 10 menit, toleransi 15 menit, satu reschedule H-24 dengan lapangan/durasi/harga sama, batas 30 hari, potongan pembatalan Rp50.000, serta persetujuan koreksi oleh pemilik. Tidak satu pun menjadi konstanta atau prosedur otomatis Tahap 1.
