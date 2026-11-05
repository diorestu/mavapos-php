# Hapus akun sendiri

Lokasi: Profil (`/profile`) → Hapus Akun → Hapus Akun Saya.

## Konfirmasi

- Pengguna harus login, mengetik `HAPUS AKUN`, dan menyetujui dampak penghapusan.
- Verifikasi menggunakan kata sandi akun saat ini atau OAuth akun Google yang terhubung. Verifikasi Google berlaku 5 menit dan terikat pada ID pengguna serta ID Google yang sama.
- Verifikasi Google memakai callback login yang sudah ada (`/auth/google/callback`); tidak memerlukan URL callback baru di konfigurasi Google.
- Shift milik pengguna harus ditutup terlebih dahulu. Antrean transaksi belum selesai milik pengguna di browser ini memblokir pengiriman formulir. Pengguna juga diminta menyelesaikan antrean di perangkat lain.
- Penghapusan memerlukan koneksi internet. Data dari perangkat yang tidak terhubung tidak bisa diperiksa oleh server.

## Dampak

Akun pengguna saat ini dianonimkan dan diberi `deleted_at`. Nama login, email, ID Google, verifikasi email, password, remember token, dan referensi foto dibersihkan/diganti; foto profil dihapus dari storage. Token API dicabut, sesi database milik pengguna dibersihkan, dan sesi saat ini diakhiri. Autentikasi mengecualikan pengguna yang sudah dihapus untuk driver sesi lain.

Riwayat transaksi, dokumen keuangan, cabang, stok, dan akun staf tetap tersimpan. Dokumen historis dapat memuat data yang tercatat pada saat transaksi. Ini bukan penghapusan usaha atau seluruh data tenant. Tidak ada perubahan pada akun staf lain dan tidak ada force-delete record pengguna yang bisa memicu cascade ke catatan usaha.

Pengguna tanpa langganan aktif tetap dapat mengakses profil dan menghapus akunnya. Menambahkan user_id lain ke request tidak mengubah akun sasaran: endpoint selalu memakai pengguna yang terautentikasi.

## Implementasi

- DELETE `/profile` (`profile.destroy`), autentikasi web, CSRF dan throttle 5 percobaan/menit.
- GET `/profile/delete/google` memulai verifikasi, callback hanya menyimpan bukti dalam sesi; akun belum dihapus sampai konfirmasi DELETE.
- Error menggunakan bag `accountDeletion`, sehingga tidak mengganggu validasi foto profil.
- Pemeriksaan antrean memakai IndexedDB yang sama dengan transaksi offline; cache halaman privat dibersihkan sebelum formulir dikirim.
- Tidak ada migrasi baru untuk fitur ini. Deploy kode dan aset frontend bersamaan, jalankan `npm run build` dan refresh cache Laravel sesuai proses deployment proyek.

## Verifikasi

15 tes fitur penghapusan akun lulus: password/konfirmasi/persetujuan, histori tetap ada, token/foto/sesi dicabut, shift aktif, akun staf, guest, expiry/identitas Google, callback ditolak, dan regresi login Google biasa.

Seluruh suite PHP 282 tes  / 3200 assertions lulus menggunakan `php -d memory_limit=512M vendor/bin/pest`. Run dengan limit bawaan 128 MB berhenti karena kehabisan memori saat ZIP ekspor Excel; tidak mengubah limit aplikasi/konfigurasi server. Seluruh 29 tes Node lulus, termasuk 4 tes pengamanan formulir penghapusan.

Browser Playwright diuji pada SQLite sementara: desktop/ponsel 390 px tanpa horizontal overflow, antrean tersimpan mencegah penghapusan, kemudian akun uji dihapus dan diarahkan ke signin dengan pesan berhasil. OAuth Google diperiksa melalui tes integrasi dengan provider mock; tidak ada akun Google sungguhan yang dihapus atau diubah.

## API mobile

Gunakan DELETE atau POST `/account/delete`, atau path yang sama relatif ke base `/api/mobile/v1`. Endpoint Bearer ini mendukung password atau ID token Google baru dan dapat digunakan tanpa langganan aktif. Lihat [kontrak API mobile](mobile-api.md).
