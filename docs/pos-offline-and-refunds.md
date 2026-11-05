# Transaksi offline, pencegahan duplikat, dan retur sebagian

## Pemakaian kasir

1. Login dan buka halaman Kasir saat terhubung internet. Mulai shift dan tunggu halaman offline siap.
2. Jika internet terputus, lanjutkan transaksi langsung dengan Tunai, QRIS manual, Kartu manual, atau Split Payment. Katalog dan shift menggunakan data yang sudah dimuat. Transaksi Online Merchant, gratis, dan kartu loyalitas memerlukan koneksi.
3. Checkout disimpan dahulu di IndexedDB browser. Selama server belum mengonfirmasi, bukti sementara menampilkan ID lokal dan status belum tersinkron. Ini belum merupakan nomor invoice resmi.
4. Ketika koneksi kembali, antrean dengan kegagalan jaringan dicoba berurutan. Transaksi yang ditolak server atau sesi login kedaluwarsa memerlukan tindakan operator dan tombol Coba Lagi. Login kembali dengan akun yang sesuai jika perlu.
5. Harga, stok, waktu, kasir dan cabang diperiksa kembali oleh server. Konflik tidak menghilangkan data antrean. Antrean yang sudah ditolak secara pasti dapat dibatalkan setelah barang/uang diperiksa. Konflik ID409 tetap disimpan untuk rekonsiliasi.

Antrean milik browser/perangkat dan identitas kasir/cabang asal. Jangan menghapus data browser atau mengganti perangkat sebelum antrean selesai. Halaman offline hanya tersedia setelah persiapan online pada tab yang dikenali; tab atau identitas lain tidak mendapat cache akun sebelumnya. Pergantian akun membersihkan cache halaman, bukan menghapus antrean transaksi.

Shift baru harus dibuka saat online. Transaksi yang direkam sebelum shift ditutup dapat disinkronkan ke shift asal; waktu di luar shift akan ditolak. Hak kasir dan verifikasi pergantian kasir tetap berlaku.

## Pencegahan transaksi ganda

Browser memberikan UUID tetap sebelum checkout pertama. Pengiriman ulang memakai ID dan payload yang sama. Server menyimpan transaksi dan jawaban sukses dalam satu transaksi database; pengiriman ulang mengembalikan invoice yang sama tanpa mengurangi stok atau mengubah loyalitas lagi. Payload, akun, kasir atau cabang yang berbeda dengan ID yang sama menghasilkan409.

Integrasi API dapat mengirim header `Idempotency-Key` atau body `client_transaction_id`. Keduanya harus sama jika dikirim bersama. Klien lama tanpa ID tetap kompatibel, tetapi tidak mendapatkan jaminan replay. Permintaan ber-ID diserialkan per tenant untuk menjaga atomisitas.

## Retur / refund sebagian

Owner/admin membuka Penjualan → Retur / Riwayat Refund pada transaksi:

- Pilih jumlah per barang, maksimal jumlah yang belum diretur.
- Centang pengembalian ke stok hanya jika produk/varian layak dijual kembali. Bahan resep tidak otomatis dipulihkan.
- Nominal mengikuti alokasi harga setelah diskon; transaksi Online Merchant dibatasi penerimaan bersih asli. Pembulatan dihitung kumulatif sehingga total beberapa refund tidak melebihi pembayaran asli.
- Pilih metode pengembalian aktual, isi alasan, lalu konfirmasi uang sudah dikembalikan. Pengembalian uang dilakukan manual; sistem mencatatnya.

Transaksi asli, dokumen refund, operator, waktu, dan pergerakan stok tetap tersimpan. Transaksi yang sudah direfund tidak dapat di-void, diedit, dipindahkan cabang, atau dihapus bersama shift terkait. Dokumen refund memakai ID sendiri untuk mencegah pencatatan ulang.

Refund ditampilkan terpisah di laporan/PDF, jurnal, dan sheet Excel Retur. Nilai bersih setelah refund dan pemulihan biaya stok yang secara eksplisit dikembalikan diperhitungkan. Refund tunai yang terhubung shift pemroses mengurangi uang yang seharusnya di laci; refund tanpa shift pemroses tercatat pada laporan cabang. Bonus penjualan tidak otomatis dibalik oleh retur.

## Deployment

Jalankan migrasi baru pada database tujuan melalui proses deployment biasa:

```sh
php artisan migrate --force
npm run build
php artisan optimize:clear
```

Migrasi baru: `2026_11_04_000001_create_checkout_requests_table.php` dan `2026_11_04_000002_create_sale_refunds_tables.php`. Service worker dan aset hasil build harus terpasang bersama kode server. Cache browser memperbarui worker ketika halaman online dibuka kembali.

## Verifikasi implementasi

- PHP:267 tes lulus,3092 assertions.
- Node:25 tes lulus.
- Build Vite, kompilasi Blade dan pemeriksaan diff berhasil.
- Browser plugin tidak tersedia; alur diperiksa dengan Playwright pada database SQLite sementara terpisah.
- Browser: checkout online, simpan offline, reload saat offline, sinkron kembali, retur1 dari3 barang + restock, dan respons hilang setelah commit lalu replay berhasil.
- Replay tidak menambah transaksi dan tidak mengembalikan total shift/katalog ke snapshot lama.
- Pengujian paralel banyak proses di MySQL dan uji perangkat pembayaran/printer fisik belum dilakukan. Refund menggunakan konfirmasi manual, bukan pengiriman dana melalui gateway.
