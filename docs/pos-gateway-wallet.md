# POS Midtrans dan wallet merchant

Fitur ini memisahkan hak dana penjualan tiap merchant/outlet dalam satu akun Midtrans bersama. Wallet adalah subledger internal MavaPOS; tidak membuat akun Midtrans terpisah dan tidak memindahkan saldo global Midtrans secara otomatis.

## Alur kasir

1. Mulai shift, masukkan barang, pilih **Midtrans / Payment Gateway**, lalu **Selesaikan Pembayaran**.
2. Server menghitung harga dan diskon, membuat pesanan pending, serta mereservasi stok. Browser membuka halaman pembayaran transaksi dan popup Snap.js.
3. Callback Snap.js dan notifikasi hanya memicu pemeriksaan; backend memverifikasi order, nominal IDR, environment dan transaction ID lewat API Midtrans. Callback browser tidak dapat membuat transaksi lunas atau menambah saldo sendiri.
4. Capture kartu dengan fraud accept atau settlement yang sah membuat penjualan paid. Pesanan pending tidak dihitung ke omzet, bonus, laporan/Excel atau ringkasan API partner.
5. Dana wallet baru diakui setelah settlement. Capture yang belum settlement masih menunggu.

Versi awal gateway POS mendukung checkout baru, online dan pembayaran penuh/direct. Split, transaksi gratis, offline replay, dan kartu loyalitas/stempel tidak dipakai untuk metode gateway. Metode pembayaran lama mempertahankan perilaku sebelumnya. Diskon biasa didukung; jika harga server berubah dari total yang dikonfirmasi browser, checkout ditolak agar kasir meninjau ulang.

Permintaan memakai UUID stabil yang disimpan browser sebelum pengiriman pertama; retry payload yang sama menggunakan UUID yang sama. Tidak ada pembuatan charge kedua diam-diam. Pergantian merchant/outlet/kasir atau payload yang berbeda tidak boleh mengubah reservasi lama. Request yang masih dibuat dapat sementara mengembalikan 409; setelah direkonsiliasi atau kedaluwarsa, ID lama tetap dapat membuka catatan pembayarannya.

Pesanan tanpa pembayaran yang telah dipastikan deny/cancel/expire melepaskan reservasi stok tepat satu kali, termasuk jika shift sudah ditutup. Barang yang sudah terbayar tidak otomatis direstock ketika pembayaran kemudian dibalik. Riwayat gateway dapat dibuka kembali dari **Penjualan → Lanjutkan Pembayaran / Detail Gateway**. Editing, void, pemindahan outlet dan retur manual lama diblokir untuk penjualan gateway; shift yang memiliki riwayat gateway tidak dapat dihapus.

## Arti saldo wallet

Menu **Wallet** tersedia bagi owner/admin merchant. Angka ringkasan adalah seluruh outlet merchant pada environment terpilih. Filter outlet hanya membatasi pembayaran/mutasi yang ditampilkan; tanggal membatasi mutasi. Pengajuan pencairan memakai saldo seluruh merchant, bukan saldo outlet yang sedang difilter.

| Pos saldo | Arti |
| --- | --- |
| Pembayaran/settlement tertunda | Pesanan belum terbayar atau capture belum settlement; bukan dana yang bisa dicairkan |
| Menunggu rekonsiliasi | Bruto settlement terverifikasi, dikurangi refund yang diketahui; biaya dan ketersediaan dana bersih belum dikonfirmasi |
| Saldo tersedia | Dana bersih setelah admin mengonfirmasi biaya/ketersediaan, dikurangi pengajuan dan reversal |
| Dalam pencairan | Dana yang ditahan untuk permintaan pending/processing |
| Sudah dicairkan | Transfer manual/simulasi yang sudah dicatat selesai |
| Biaya terverifikasi | Biaya aktual yang dicatat admin, bukan estimasi tarif metode pembayaran |

Saldo berasal dari ledger immutable, bukan dari angka saldo global Midtrans. Setiap posting settlement, refund, rekonsiliasi dan pencairan memiliki kunci unik; notifikasi berulang tidak menggandakan uang. Owner dan outlet asal disimpan sejak checkout dan tidak dapat diubah setelahnya. Pembayaran langganan MavaPOS serta cash/QRIS/kartu manual **tidak mengisi wallet**.

Mutasi memakai waktu pencatatan sistem dalam WITA; payload status menyimpan waktu provider untuk audit. Semua nominal disimpan dalam rupiah bulat. Biaya/pecahan atau pembulatan dalam laporan provider harus direkonsiliasi ke nominal dana bersih rupiah yang benar-benar tersedia sesuai laporan/bukti; aplikasi tidak menebak tarif maupun membulatkan estimasi secara otomatis.

## Rekonsiliasi admin platform

Hanya akun **superadmin** dapat membuka **Administrasi Wallet** (`/superadmin/wallets`). Admin merchant tidak dapat mengelola wallet merchant lain atau mengonfirmasi ketersediaan dana platform.

1. Cocokkan pembayaran settlement, refund, biaya dan ketersediaan dana dengan laporan Midtrans. Settlement tidak otomatis berarti dana telah melewati cut-off penarikan provider.
2. Isi **Biaya Midtrans aktual**, referensi laporan, dan konfirmasi **Dana bersih ... tersedia untuk dicairkan**.
3. Aplikasi mengalihkan sisa bruto setelah refund dan biaya dari menunggu rekonsiliasi ke saldo tersedia. Nominal nol tetap membutuhkan referensi/konfirmasi; biaya yang belum diketahui tidak dianggap nol.
4. Rekonsiliasi yang sudah dicatat tidak dapat ditimpa; koreksi kondisi eksternal ditangani sebagai mutasi/status terverifikasi, bukan mengedit ledger.

Wallet mencatat dana, bukan menambahkan omzet kedua. Jurnal penjualan gateway menggunakan akun **Dana Payment Gateway**, bukan kas tunai. Buku pengeluaran/rekonsiliasi bank operasional tetap memerlukan prosedur akuntansi merchant; rekonsiliasi wallet tidak otomatis membuat expense operasional tambahan yang bisa menggandakan pencatatan biaya.

## Pengajuan dan transfer manual

- Owner mengajukan nominal positif maksimal saldo tersedia, nama bank, nomor rekening, nama pemilik dan UUID pengajuan. Nomor rekening disimpan terenkripsi dan hanya empat digit akhir muncul pada riwayat merchant. Superadmin dapat melihat rekening tujuan untuk melakukan transfer.
- Saldo langsung ditahan ketika pengajuan diterima. Pengiriman ulang UUID yang sama tidak menahan dana dua kali; perubahan payload dengan UUID lama ditolak.
- Superadmin memilih **Mulai Proses**, memeriksa kecukupan dana/kewajiban wallet, lalu melakukan transfer **di luar aplikasi** dari dana yang telah tersedia pada bank/platform.
- Sesudah transfer aktual, isi referensi dan konfirmasi untuk **Tandai Transfer Selesai**. Menekan tombol ini hanya mencatat transfer; aplikasi tidak memanggil API bank, Iris, payout atau penarikan Midtrans.
- Penolakan membutuhkan alasan dan pernyataan dana belum ditransfer. Saldo yang ditahan dikembalikan ke tersedia tepat satu kali.
- Nominal yang dicatat selesai adalah nominal yang dikirim ke merchant. Tidak ada biaya transfer bank tambahan yang otomatis dibebankan ke wallet; operator perlu memperhitungkan biaya bank di sisi platform sesuai kebijakan komersialnya.

Refund/reversal bisa terjadi setelah pengajuan. Saldo tersedia dapat menjadi negatif untuk menunjukkan kewajiban yang nyata; pengajuan/proses baru diblokir ketika dana tidak cukup atau memerlukan review. Jangan melakukan transfer baru dari wallet defisit. Jika bank transfer sudah terlanjur terjadi, tetap catat transfer aktual, bukan menghapusnya dari sejarah. Akun owner dengan dana/kewajiban wallet produksi atau pembayaran produksi yang belum selesai tidak dapat dihapus sebelum urusannya diselesaikan.

## Refund, chargeback dan reversal

Refund gateway dilakukan melalui proses Midtrans yang berwenang, kemudian diverifikasi kembali oleh backend. Cumulative refund hanya mendebit pertambahan nominal baru; pesan lama/duplikat tidak dapat mengkreditkan ulang dana atau mendebit dua kali. Reversal `settlement → deny` mengurangi hak dana; `capture → cancel` membalik penjualan tanpa mengarang pemasukan wallet yang belum settlement.

Penyesuaian keuangan juga tercatat pada riwayat retur penjualan agar omzet bersih tidak tetap menganggap dana yang sudah dikembalikan sebagai penerimaan. Karena notifikasi gateway tidak menentukan barang yang kembali, penyesuaian ini tidak menebak kuantitas atau mengembalikan stok. Retur barang/restock perlu dicatat sesuai barang yang benar-benar diterima. Reversal dengan nominal parsial tidak tersedia, pembayaran terlambat setelah reservasi dilepas, atau kondisi tidak konsisten ditandai untuk review dan menahan pengajuan pencairan sampai aman.

## Sandbox dan produksi

Wallet sandbox dan produksi terpisah, termasuk ledger dan pengajuan. UI sandbox menyatakan pencairan sebagai simulasi dan meminta rekening uji. Tidak ada transfer uang nyata dari aplikasi.

Sandbox checkout tetap menggunakan inventory dan data penjualan pada deployment tempat pengujian dijalankan. Gunakan staging atau merchant demo untuk uji transaksi; jangan mencampurkan data simulasi dengan operasional produksi. Selesaikan/rekonsiliasi seluruh reservasi sandbox sebelum memindahkan suatu deployment ke kredensial produksi. Reconciler hanya memakai environment yang sedang dikonfigurasi dan tidak akan menggunakan Server Key produksi untuk menebak status order sandbox lama.

## Operasional server

Gunakan konfigurasi Midtrans yang sama dengan [panduan langganan](midtrans-subscription.md). Notification URL bersama:

```text
https://DOMAIN-BACKEND/midtrans/notification
```

Server mencocokkan order dengan payment record POS atau invoice langganan yang tersimpan. Dana dua tujuan ini tidak dicampur ke wallet. Localhost tidak dapat menerima webhook; halaman pembayaran melakukan pengecekan status dan scheduler menjadi cadangan ketika halaman ditutup.

```sh
php artisan migrate --force
php artisan config:cache
php artisan gateway:reconcile --limit=20
```

Scheduler Laravel perlu dijalankan setiap menit, misalnya cron `php artisan schedule:run`. Pengembangan lokal dapat menjalankan `php artisan schedule:work`. Reconciler memeriksa pending/capture paling cepat satu menit sekali, settlement/refund parsial paling cepat satu jam sekali; batch dibatasi dan tidak overlap. UI dan webhook tetap menjadi jalur pemeriksaan utama. Sesi Snap POS memakai batas awal 15 menit. Status charge yang masih pending di provider tidak dibatalkan hanya karena jam lokal lewat; reservasi dibatalkan setelah expiry terverifikasi atau sesi tanpa charge benar-benar melewati batasnya.

Backup database lengkap diperlukan untuk ledger. Export/import SQL tenant lama berisi data operasional, bukan backup ledger wallet; import data penjualan tidak dapat menciptakan saldo wallet. Jangan menghapus akun/ledger keuangan atau merubah kepemilikan payment record untuk memperbaiki saldo.

## Verifikasi implementasi

Pengujian mencakup isolasi merchant/environment, UUID checkout/pengajuan, stok pending dan pemulihan sekali, settlement/capture/reversal, refund kumulatif, biaya aktual dan saldo tertahan, role admin, masking/enkripsi rekening, saldo negatif, request worker yang terputus, dan filter/API laporan. Uji browser pada database terpisah membuktikan alur checkout → settlement → rekonsiliasi Rp40.000−Rp1.000 → pengajuan/simulasi Rp20.000 → sisa Rp19.000, tanpa bocornya dana merchant lain. Provider browser disimulasikan; permintaan pembuatan Snap sandbox juga diuji langsung, tanpa mengirim pembayaran atau transfer bank nyata.

Referensi primer: [status transaksi Midtrans](https://docs.midtrans.com/docs/transaction-status-cycle), [verifikasi notifikasi](https://docs.midtrans.com/docs/https-notification-webhooks), [saldo dan ketersediaan penarikan](https://docs.midtrans.com/docs/balance-page-manage-withdraw-your-funds).
