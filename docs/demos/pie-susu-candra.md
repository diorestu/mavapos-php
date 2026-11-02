# Data demo komersial Pie Susu Candra

Permintaan: isi dashboard dengan histori bervariasi dan foto produk untuk presentasi komersial. Target yang dipilih pengguna adalah usaha Pie Susu Candra (owner 8, cabang 3 pada database lokal ini).

## Menjalankan

```sh
php artisan demo:seed-commercial 8 --branch=3
```

Perintah hanya menerima lingkungan `local` atau `testing`. Cabang harus aktif dan dimiliki owner yang dipilih. Katalog harus memiliki SKU PSC40 atau PSC75. Tidak dipanggil oleh DatabaseSeeder dan tidak berjalan saat aplikasi dibuka.

- Transaksi simulasi dibuat dari Januari sampai bulan berjalan, tanpa tanggal masa depan. Kuantitas, nilai pesanan, diskon, pelanggan, asal pembeli, metode pembayaran dan merchant bervariasi.
- Nomor transaksi, catatan shift, pengeluaran, pelanggan dan SKU produk tambahan memakai awalan `DEMO-PSC-{owner}-`. Produk tambahan juga mencantumkan `(Demo)` pada nama.
- Transaksi lama dan stok akhir produk asli dipertahankan. Histori mutasi demo memiliki saldo pembukaan simulasi dan berakhir pada stok saat ini.
- Kasir memakai owner yang dipilih; tidak ada akun atau kredensial baru dan tidak ada perubahan akses langganan.
- Menjalankan ulang pada periode yang sama tidak menggandakan transaksi. Data operasional tidak dihapus.
- Foto PSC40 dan PSC75 diganti dengan ilustrasi katalog baru. File foto lama tidak dihapus. Tiga produk tambahan adalah contoh katalog demo, bukan klaim katalog resmi usaha.

## Foto

Dibuat dengan built-in imagegen, kemudian dikodekan ke WebP untuk ukuran file web yang ringan. Sumber PNG tetap berada di folder generated_images Codex. Aset proyek disimpan di `public/images/demo/pie-susu-candra/` lalu disalin ke disk public pada `products/demo-commercial/` oleh perintah demo.

Prompt bersama: foto katalog persegi yang fotorealistis untuk pie susu Bali, kulit pastry keemasan dan isian susu kuning pucat, kotak bakery ivory tanpa merek, latar krem hangat, cahaya lembut dari samping, detail makanan yang menggugah selera, tanpa teks/logo/watermark/orang. Variasi subjek: kotak 40 pcs; kotak keluarga besar 75 pcs; kotak kecil 10 pcs; kotak sedang 20 pcs dengan potongan pie; gift box dengan pita krem. Jumlah isi dan kemasan merupakan ilustrasi untuk demo.

## Cadangan lokal

Sebelum migrasi atau penambahan demo, SQLite dicadangkan ke `storage/app/private/demo-backups/pie-susu-candra-20261029-103700.sqlite`. Manifest JSON di sebelahnya menyimpan ID transaksi lama dan referensi foto/stok asli. Cadangan tidak dilacak Git dan berisi data lokal privat; jangan dibagikan bersama materi presentasi.
