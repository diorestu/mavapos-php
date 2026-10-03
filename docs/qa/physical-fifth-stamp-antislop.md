# Diskon kartu fisik, pemeriksaan UI

Tanggal: 3 Oktober 2026. Antislop diterapkan selama pengerjaan sesuai pilihan pengguna.

## Lingkup dan arah

Pemeriksaan ini mencakup kontrol diskon dan loyalitas yang diubah pada kasir serta pilihan kartu fisik pada form koreksi penjualan. Bukan audit seluruh aplikasi.

Reading this as: form kasir untuk staf toko, mengikuti tampilan MavaPOS yang sudah ada, ENERGY 1 / RHYTHM 1 / MOTION 1.

- Warna dan tipografi mengikuti token aplikasi agar kasir tetap mengenali kontrol dan angka transaksi.
- Pilihan kartu fisik berada dalam dropdown loyalitas yang sudah ada agar tidak menambah panel atau langkah baru.
- Nominal diskon tetap dapat diisi karena kartu fisik memakai diskon yang dimasukkan kasir.
- Kontrol diskon dan dropdown setinggi 44 px untuk penggunaan layar sentuh.
- Petunjuk berada langsung di bawah dropdown; checkbox penambahan cup disembunyikan pada kartu fisik agar stempel tidak dihitung dua kali.
- Total pembayaran tetap menjadi fokus. Tidak menambah ikon, ilustrasi, kartu dekoratif, atau animasi.

## Bukti interaksi

Browser memakai server lokal dengan SQLite dan akun uji terpisah di /tmp/mava-physical-fifth-qa. Tidak memakai database operasional.

1. Diskon Rp7.000 lalu pilihan kartu fisik: nominal tetap Rp7.000; subtotal Rp20.000 menjadi total Rp13.000.
2. Diskon 0: tombol pembayaran dinonaktifkan.
3. Nomor pelanggan kosong: tombol pembayaran dinonaktifkan.
4. Nomor terisi, nominal positif, kewarganegaraan dan pembayaran lengkap: tombol pembayaran aktif.
5. Checkbox penambahan stempel dicentang lalu kartu fisik dipilih: checkbox dinonaktifkan dan disembunyikan.
6. Reward digital 50%: input nominal dinonaktifkan; perhitungan memakai reward otomatis.
7. Reward gratis cup: total produk uji Rp20.000 menjadi Rp0.
8. Pembayaran QRIS uji kartu fisik: modal sukses menampilkan Rp13.000. Database mencatat physical_fifth, discount 7000, total 13000, stamp_count 5, fifty_reward_available 0.
9. Penggunaan ulang pelanggan yang sama: pesan 'Stempel ke-5 pelanggan sudah digunakan.'; tidak membuat transaksi kedua.
10. Keranjang kosong: pesan untuk memilih produk dan tombol pembayaran nonaktif. Sesudah transaksi berhasil, nominal dan pilihan direset.
11. Tab dari input diskon menuju dropdown loyalitas: elemen SELECT mendapat fokus dan ring 3 px.
12. Lebar 390 px, terang dan gelap: scrollWidth sama dengan viewportWidth (390), dropdown 44 px, petunjuk membungkus tanpa keluar kontainer. Desktop juga tidak meluap (1280 px).
13. Form koreksi admin: physical_fifth terpilih dari transaksi tersimpan; pilihan tanpa voucher lalu kembali ke kartu fisik tidak menghapus nominal. Nominal diubah menjadi Rp8.000, simpan berhasil, daftar penjualan menampilkan diskon Rp8.000 dan total Rp12.000.
14. Console diperiksa setelah checkout dan koreksi: tidak ada error atau warning JavaScript yang tertangkap.
15. Loading menggunakan checkoutLoading dan label proses yang sudah ada; diperiksa pada kode. Tidak mengklaim merekam screenshot loading singkat.

Screenshot bukti ada di folder visualizations chat: physical-fifth-light.png dan physical-fifth-dark.png. Seluruh data pada screenshot adalah fixture uji.

## Delivery Gate antislop

Status berlaku pada bagian yang diubah; item yang tidak diperkenalkan diberi bukti tidak ada penambahan.

| Aturan | Status | Bukti |
|---|---|---|
| R-01 | PASS | Token warna aplikasi dipertahankan, tanpa gradient baru. |
| R-02 | PASS | Teks opsi yang diubah menggunakan titik dua; tanpa em dash. |
| R-03 | PASS | Browser 390 px tidak meluap; input dan dropdown 44 px, checkbox dalam label min-height 44 px. |
| R-04 | PASS | Tidak menambah ikon atau emoji. |
| R-05 | PASS | Kontrol berada pada form transaksi yang sudah ada, tanpa bagian template baru. |
| R-06 | PASS | Tipografi aplikasi dipertahankan untuk keterbacaan label dan angka. |
| R-07 | PASS | Tidak menambah pola latar. |
| R-08 | PASS | Tidak menambah panah dekoratif. |
| R-09 | PASS | Tidak menambah badge. |
| R-10 | PASS | Tidak menambah glass atau blur. |
| R-11 | PASS | Input rounded-lg mengikuti kontrol aplikasi. |
| R-12 | PASS | Shadow input diskon dihapus; tidak menambah elevasi. |
| R-13 | PASS | Ring hanya pada fokus kontrol, tanpa glow dekoratif. |
| R-14 | PASS | Tidak menambah feature card. |
| R-15 | PASS | Opsi menjelaskan penggunaan stempel ke-5; CTA pembayaran tetap tindakan konkret. |
| R-16 | PASS | Copy menyebut nomor, diskon, stempel dan transaksi; tanpa buzzword. |
| R-17 | PASS | Angka 5 dan 10 adalah aturan reward; total dan diskon berasal dari input dan transaksi. |
| R-18 | PASS | Tidak menambah testimonial atau identitas fiktif pada produk. |
| R-19 | PASS | Tidak menambah animasi; dial MOTION 1 untuk kontrol ini. |
| R-20 | PASS | Alur kartu fisik khusus kasir MavaPOS; menyatu dengan total dan pembayaran. |
| R-21 | PASS | Tema aplikasi dipertahankan; diperiksa terang dan gelap. |
| R-22 | PASS | Tidak menambah ilustrasi. |
| R-23 | PASS | Tidak membuat aset visual atau navigasi baru. |
| R-24 | PASS | Tidak menambah link navigasi. |
| R-25 | PASS | Token label/petunjuk: #475467 pada putih 7.69:1; #d0d5dd pada #101828 12.04:1; input #1d2939 pada putih 14.70:1. |
| R-26 | PASS | Dropdown, nominal, checkbox dan checkout diuji pada interaksi 1-10; koreksi pada 13. |
| R-27 | PASS | Empty dan error diamati, loading dipertahankan dan diperiksa pada kode (10, 9, 15). |
| R-28 | PASS | Tidak menambah FAQ. |
| R-29 | PASS | Hanya token netral dan aksen fokus aplikasi; tidak menambah palet. |
| R-30 | PASS | Mengikuti aplikasi ini, tidak meniru produk lain. |
| R-31 | PASS | Alasan layout, warna, ukuran, copy, dan kontrol ditulis di bagian arah. |
| R-32 | PASS | Label native terpisah dari checkbox, tidak memakai ID duplikat pada dua keranjang; fokus Tab diamati (11). |
| R-33 | PASS | Perubahan ditulis langsung pada Blade dan JavaScript melalui patch, tanpa script penggantian sumber/CSS. |
| R-34 | PASS | Screenshot terang dan gelap pada 390 px; tidak ada overflow (12). |
| R-35 | PASS | npm run build berhasil; interaksi kontrol yang berubah tercatat pada 1-14. |
| R-36 | PASS | Tidak menambah klaim keamanan, kepatuhan, performa atau pelanggan. |
| R-37 | PASS | Arah mengikuti form kasir yang sudah ada; Design Read dan dial ditulis sebelum implementasi. |
| R-38 | PASS | Tidak menambah isi fiktif pada produk; browser memakai fixture uji yang dijelaskan. |
| Liveliness | PASS | Total sebagai fokus, jarak mengelompokkan kontrol, aksen pada aksi/fokus, motif angka transaksi dipertahankan. |
| C-1 sampai C-5 | PASS | Pilihan punya tujuan; kontrol berfungsi; layout sesuai transaksi; tema/mobile diuji; angka diuji lewat checkout. |

## Verifikasi dan batas

- Tes terkait: 17 lolos, 110 assertions (LoyaltyCardCheckoutTest, AdminSaleAndShiftEditTest, PosCustomerCheckoutTest).
- Build frontend: berhasil.
- git diff --check: berhasil.
- Full suite: 168 lolos, 7 gagal, 1025 assertions. Kegagalan yang sama sudah muncul sebelum perubahan UI; tidak diperbaiki dalam lingkup ini:
  - ExampleTest: payload checkout membawa pengaturan struk dan printer toko (paper_width integer 80 dibanding string '80').
  - ExampleTest: owner dapat mengelola user dan role staf (branch_id wajib untuk kasir).
  - PosCashierShiftTest: kasir wajib mulai shift sebelum checkout dan report menampilkan pendapatan per kasir ($owner tidak didefinisikan).
  - PosCashierShiftTest: kasir berikutnya wajib memvalidasi cash dan kartu dari rekap sesi sebelumnya (mengharapkan 422, menerima 200).
  - PosCashierShiftTest: halaman stok mengikuti stok cabang aktif (404 saat mengganti cabang).
  - StockTransferTest: transfer stok ditolak jika stok cabang asal tidak cukup (validasi cabang aktif mendahului quantity).
  - StockTransferTest: owner dapat memilih dan transfer stok bahan baku antar cabang (bahan uji tidak ditemukan pada halaman).

Gate UI ini tidak menyatakan seluruh suite aplikasi bersih atau membuktikan perilaku produksi.
