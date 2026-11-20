# Partner API — panduan admin server

Panduan pemakaian oleh partner ada di [partner-api.md](partner-api.md). Dokumen ini berisi langkah pemasangan, pengelolaan akses internal dan pengiriman webhook.

## Pasang perubahan

Backup database, pasang kode, lalu jalankan pada backend MavaPOS:

```sh
php artisan migrate --force
php artisan config:cache
```

Tidak ada paket baru. Pengiriman webhook membutuhkan PHP CLI dengan ext-curl. Jika ada beberapa instance backend, gunakan cache bersama (database/Redis) agar rate limit berlaku bersama.

API tetap dapat membaca data saat langganan POS habis, selama key berlaku dan owner aktif. Kode ini tidak menetapkan paket atau biaya komersial khusus API.

## Terbitkan key lewat CLI

Owner harus sudah memiliki akun. Perintah ini menampilkan Bearer dan Client Key sekali; database hanya menyimpan hash SHA-256.

```sh
php artisan partner:key owner@example.com --name="ERP Sandbox" --sandbox --days=30
php artisan partner:key owner@example.com --name="ERP Produksi" --days=90
php artisan partner:key owner@example.com --revoke=123
```

`--days` menerima 1–365, default 90. Token diawali `mava_test_` atau `mava_live_`; Client Key diawali `mava_client_`.

## Endpoint pengelolaan internal

Endpoint ini memakai **session web owner/admin dan CSRF**, bukan Bearer/Client Key Partner.

| Method | URL | Kegunaan |
| --- | --- | --- |
| GET | `/settings/partner-keys` | Daftar metadata: ID, nama, prefix, mode, masa berlaku, status dan `client_key_configured` |
| POST | `/settings/partner-keys` | Buat pasangan baru; body `{"name":"ERP","sandbox":true,"expires_in_days":30}` |
| POST | `/settings/partner-keys/{id}/client-key` | Buat/ganti Client Key dengan Bearer tetap sama |
| DELETE | `/settings/partner-keys/{id}` | Cabut pasangan; respons 204 |

Pembuatan pasangan mengembalikan 201 dengan `data`, `token`, dan `client_key`. Pembuatan/rotasi Client Key mengembalikan 201 dengan `data` dan `client_key`. GET tidak pernah mengembalikan key lengkap atau hash. Token kedaluwarsa/dicabut tidak dapat dirotasi (422).

Token lama tanpa Client Key langsung ditolak setelah perubahan ini dipasang. Minta partner membuat Client Key di Pengaturan dan memperbarui header. Tidak ada pengecualian Bearer-only. Rotasi berlaku pada request berikutnya; request yang sudah diautentikasi dapat menyelesaikan prosesnya. Session web, mobile/Sanctum dan notifikasi Midtrans memakai autentikasinya sendiri.

## Izinkan hostname webhook

Isi hostname yang disetujui pada `.env` backend, huruf kecil dan dipisahkan koma:

```dotenv
PARTNER_WEBHOOK_HOSTS=hooks.your-company.com
```

Jangan isi scheme, path atau wildcard. Tidak ada hostname default. Jalankan `php artisan config:cache` setelah perubahan. Pengiriman hanya ke HTTPS port 443, sertifikat valid dan IPv4 publik. Alamat internal, loopback dan metadata ditolak; redirect dimatikan dan hasil DNS dipin pada setiap percobaan.

## Jalankan pengiriman

Aktifkan scheduler Laravel setiap menit dari direktori backend:

```cron
* * * * * cd /path/to/mava_backend && php artisan schedule:run >> /dev/null 2>&1
```

Untuk menjalankan satu batch secara manual:

```sh
php artisan partner:webhooks:send --limit=100
```

Limit menerima 1–1000. Request checkout hanya menyimpan antrean; HTTP dikirim terpisah. Antrean ikut rollback jika checkout gagal. Replay dengan ID checkout yang sama tidak menambah event.

Timeout koneksi 3 detik dan total 10 detik. Maksimal 5 percobaan; jeda 1, 5, 15, dan 60 menit mengikuti scheduler. Baris yang sedang diproses ditahan selama 5 menit agar bisa dipulihkan jika proses mati. ID/body tetap; timestamp dan tanda tangan dibuat ulang saat retry.

Periksa `partner_webhook_deliveries`: `attempts`, `available_at`, `delivered_at`, `failed_at`, `last_status`, `last_error`. Body respons penerima dan secret tidak disimpan pada error. Batch tidak berjalan tumpang-tindih; pantau antrean karena batch dengan banyak timeout bisa lebih lama dari satu menit.
