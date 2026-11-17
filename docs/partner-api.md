# MavaPOS Partner REST API v1

API baca saja untuk aplikasi akuntansi, dashboard, atau integrasi lain. Data outlet, katalog, transaksi dan ringkasan dibatasi pada **seluruh outlet milik satu merchant** sesuai key. Tidak ada endpoint pembuatan/ubah transaksi. Ini terpisah dari API mobile dan token Sanctum lama.

Spesifikasi yang dapat diimpor ke Postman/Swagger: [partner-api.openapi.json](partner-api.openapi.json).

## 1. Aktivasi server dan penerbitan akses

Setelah kode ini dipasang, operator MavaPOS menjalankan migrasi pada database target dengan prosedur backup/deployment normal:

```sh
php artisan migrate --force
php artisan config:cache
```

Tidak ada paket baru yang perlu diinstal. Untuk webhook, PHP CLI membutuhkan ext-curl dan scheduler Laravel harus berjalan. Semua perintah penerbitan key dijalankan pada backend target, bukan pada server aplikasi konsumen.

Buat key sandbox untuk email akun **owner yang sudah ada**:

```sh
php artisan partner:key owner@example.com --name="ERP Sandbox" --sandbox --days=30
```

Buat key untuk data asli setelah uji sandbox:

```sh
php artisan partner:key owner@example.com --name="ERP Produksi" --days=90
```

Token hanya ditampilkan sekali. Simpan di secret manager/environment backend penerima. Database MavaPOS hanya menyimpan hash SHA-256 token. Masa berlaku 1–365 hari; default perintah 90 hari. Format token: `mava_test_...` atau `mava_live_...`. Jangan menaruh key pada frontend publik atau query URL.

Cabut key berdasarkan ID yang ditampilkan saat penerbitan:

```sh
php artisan partner:key owner@example.com --revoke=123
```

Untuk integrasi dengan admin internal MavaPOS, tersedia endpoint berbasis **session web owner/admin dan CSRF**, terpisah dari Partner API:

| Method | URL | Body |
| --- | --- | --- |
| GET | `/settings/partner-keys` | Daftar key merchant; token tidak ditampilkan |
| POST | `/settings/partner-keys` | `{"name":"ERP","sandbox":true,"expires_in_days":30}` |
| DELETE | `/settings/partner-keys/{id}` | Mencabut key merchant; 204 |

POST mengembalikan 201 dengan `data` (metadata key) dan `token` (sekali). Token mobile/partner tidak dapat mengakses pengelolaan ini. Menu API Key lama menggunakan token Sanctum; token tersebut **tidak berlaku untuk Partner API ini**. Penerbitan key baru saat ini melalui perintah operator atau endpoint session di atas, tanpa perubahan form pengaturan lama.

Tidak ada pembatasan nama paket atau biaya khusus yang ditambahkan pada kode ini. API baca tetap dapat dipakai ketika langganan POS habis untuk rekonsiliasi, selama key berlaku dan akun owner masih aktif. Hal ini bukan penetapan harga layanan komersial.

## 2. Base URL dan autentikasi

Ganti domain berikut dengan domain backend tempat kode ini dipasang:

- Data asli: `https://your-mavapos-domain.com/api/partner/v1`
- Sandbox: `https://your-mavapos-domain.com/api/partner/sandbox/v1`

Semua endpoint memerlukan:

```http
Authorization: Bearer mava_live_...
Accept: application/json
```

Key live tidak dapat mengakses sandbox, begitu juga sebaliknya (403). Cookie login dan token mobile tidak diterima sebagai pengganti key. Key partner tidak dapat dipakai untuk checkout/mobile atau mutasi data bisnis melalui route web.

**Rate limit:** 60 request per menit per key, digabung untuk semua endpoint termasuk webhook. Respons menyertakan `X-RateLimit-Limit` dan `X-RateLimit-Remaining`; saat 429, ikuti `Retry-After`. Gunakan cache server bersama (database/Redis) bila backend berjalan di beberapa instance. Pagination: `page` mulai 1, `per_page` default 50, maksimum 100, page maksimum 100000. Jika data kosong, `data: []`, `total: 0`, `last_page: 1`.

**Waktu:** storage UTC; timestamp laporan dikeluarkan sebagai ISO 8601 WITA `Asia/Makassar` (`+08:00`). Input harus memiliki tanggal, jam, detik dan offset (`2026-11-13T00:00:00+08:00` atau `2026-11-12T16:00:00Z`); tanpa offset ditolak. Tidak menerima pecahan detik. Angka uang adalah integer rupiah (IDR), bukan float.

## 3. Endpoint

| Method | Path relatif | Hasil |
| --- | --- | --- |
| GET | `/outlets` | ID, nama, kode dan status aktif outlet |
| GET | `/products` | Katalog merchant, kategori, harga dan varian |
| GET | `/transactions` | Daftar transaksi dengan item, pembayaran, void dan refund |
| GET | `/transactions/{id}` | Satu transaksi, termasuk setelah void/refund |
| GET | `/sales-summary` | Ringkasan penjualan dan penjualan kotor per produk/varian |
| GET | `/webhook` | Konfigurasi webhook key saat ini, atau `data: null` |
| POST | `/webhook` | Mendaftarkan satu URL webhook untuk key |
| DELETE | `/webhook` | Menghapus webhook dan antrean terkait; 204 |
| POST | `/webhook/test` | Sandbox saja: contoh body/header bertanda tangan; tidak dikirim ke jaringan |

Outlet dan produk dipaginasi berdasarkan ID. Katalog berlaku pada tingkat merchant; daftar produk bukan daftar stok/harga khusus outlet. Harga varian adalah harga dasar produk ditambah penyesuaian varian; `price_adjustment` juga tersedia.

### Filter transaksi dan ringkasan

Wajib: `outlet_id`, `from`, `to`. Rentang maksimal 31 × 24 jam, **`from` inklusif dan `to` eksklusif**, berdasarkan waktu penjualan `sold_at`. Untuk satu hari WITA, gunakan 00:00 hari tersebut sampai 00:00 hari berikutnya. Outlet yang tidak dimiliki key menghasilkan 404. Outlet nonaktif tetap dapat dibaca untuk histori.

Opsional:

- `status`: `completed`, `voided`, `partially_refunded`, atau `refunded`; default mencakup semua.
- `updated_since`: ISO 8601 offset; memilih transaksi yang diubah **atau memiliki refund yang diubah** pada/setelah waktu tersebut. Filter `sold_at` tetap berlaku.
- `page`, `per_page`.

Urutan transaksi `sold_at` lalu `id`, naik. Tiap request membaca kondisi saat itu; pagination bukan snapshot database. Untuk sinkronisasi, ambil rentang kecil, deduplikasi berdasarkan ID, gunakan jendela overlap, dan ulangi histori yang masih dapat berubah. Refund hari ini untuk transaksi bulan lalu hanya ditemukan ketika jendela penjualan bulan lalu dibaca kembali, atau lewat GET detail ID yang sudah dikenal. Webhook versi ini hanya memberi tahu penciptaan transaksi.

Contoh request siap dipakai setelah mengisi URL/key:

```sh
export MAVA_BASE_URL='https://your-mavapos-domain.com/api/partner/v1'
export MAVA_API_KEY='ISI_KEY_DARI_OPERATOR'

curl --fail-with-body "$MAVA_BASE_URL/outlets" \
  -H "Authorization: Bearer $MAVA_API_KEY" -H 'Accept: application/json'

curl --fail-with-body --get "$MAVA_BASE_URL/transactions" \
  -H "Authorization: Bearer $MAVA_API_KEY" -H 'Accept: application/json' \
  --data-urlencode 'outlet_id=1' \
  --data-urlencode 'from=2026-11-13T00:00:00+08:00' \
  --data-urlencode 'to=2026-11-14T00:00:00+08:00' \
  --data-urlencode 'per_page=100'

curl --fail-with-body "$MAVA_BASE_URL/transactions/123" \
  -H "Authorization: Bearer $MAVA_API_KEY" -H 'Accept: application/json'
```

Gunakan URL encoding untuk karakter `+` pada offset. `--data-urlencode` menangani ini otomatis. Contoh respons (ID/nama ilustrasi):

```json
{
  "data": [{
    "id": 123,
    "outlet_id": 1,
    "transaction_number": "POS-20261113-001",
    "sold_at": "2026-11-13T10:00:00+08:00",
    "updated_at": "2026-11-13T11:00:00+08:00",
    "status": "partially_refunded",
    "subtotal": 50000,
    "discount": 5000,
    "total": 45000,
    "payment_method": "cash",
    "payments": [{"method":"cash","amount":45000}],
    "paid_amount": 50000,
    "change_amount": 5000,
    "received_amount": 45000,
    "online_merchant": null,
    "merchant_deduction": 0,
    "refunded_amount": 22500,
    "net_total": 22500,
    "voided_at": null,
    "void_reason": null,
    "refunds": [{"id":10,"number":"REF-001","amount":22500,"method":"cash","refunded_at":"2026-11-13T11:00:00+08:00","items":[{"transaction_item_id":456,"quantity":1,"amount":22500}]}],
    "items": [{
      "id":456,"product_id":8,"variant_id":9,"name":"Kopi · Besar","sku":"KOPI-L",
      "quantity":2,"unit_price":25000,"line_total":50000,"discount":null,
      "variant":{"id":9,"name":"Besar","attributes":{"size":"L"}},"modifiers":null
    }]
  }],
  "meta":{"timezone":"Asia/Makassar","currency":"IDR","sandbox":false,"page":1,"per_page":50,"total":1,"last_page":1}
}
```

- `total` adalah nilai transaksi setelah diskon, sebelum refund. `refunded_amount` adalah akumulasi seluruh refund. `net_total = total - refunded_amount`; untuk void nilainya 0.
- `status` memprioritaskan `voided`, lalu `refunded` jika refund mencapai total positif, lalu `partially_refunded`, atau `completed` jika tidak ada refund.
- `payments` berisi rincian split yang tersimpan, atau satu pembayaran normalisasi untuk metode tunggal. `paid_amount` dan `change_amount` adalah nilai asli tersimpan. Tunai normal memakai total setelah kembalian; online merchant memakai `paid_amount` sebagai `received_amount`, dan selisih terhadap total sebagai `merchant_deduction`. `net_total` bukan nilai settlement online merchant. Legacy split tanpa rincian tetap `payments: []`.
- Metode mengikuti nilai tersimpan, antara lain `cash`, `qris`, `card`, `split`, `online_merchant`, `free`; konsumen sebaiknya menerima string metode baru.
- Item mengembalikan nama/SKU/harga saat transaksi dicatat, walaupun katalog berubah. Snapshot varian (`variant`) tersedia untuk item yang dibuat setelah migrasi ini. Pada item lama, `variant: null`; katalog saat ini tidak dipakai untuk mengarang histori.
- Diskon model saat ini berada pada tingkat transaksi. `items[].discount` bernilai `null`, bukan 0 atau pembagian perkiraan. `line_total` adalah nilai item sebelum diskon pesanan. Modifier/add-on terpisah belum dimodelkan sehingga `modifiers: null`. Produk atau varian yang telah dihapus dapat menghasilkan ID relasi null; nama/harga snapshot tetap tersedia.
- `refunds[].items` menjelaskan jumlah item yang diretur. Kuantitas item asli tidak dikurangi diam-diam. Tidak ada data pribadi pembeli atau harga modal produk dalam response.

### Ringkasan dan per produk

GET `/sales-summary` menerima filter yang sama. Respons `data`:

- `transaction_count`, `gross_sales` (subtotal), `discount`, `total_sales` (setelah diskon), `refunded_amount`, `net_sales`.
- `products`: `product_id`, `variant_id`, `name`, `sku`, `quantity`, `gross_sales`. Dikelompokkan menurut ID produk/varian dan nama/SKU snapshot. Produk yang berganti nama dapat muncul sebagai beberapa baris.
- Total mengabaikan transaksi void. Refund diatribusikan ke **jendela penjualan asal**, bukan tanggal refund. Per-produk adalah kuantitas dan nilai kotor asli; tidak dikurangi refund atau dialokasikan diskon tingkat pesanan.
- Total mencakup seluruh hasil filter; hanya `products` yang dipaginasi dengan metadata `meta.products`. Ini bukan laporan settlement/penerimaan bersih online merchant.

## 4. Webhook transaksi baru

Operator menyetujui hostname penerima pada `.env` backend:

```dotenv
PARTNER_WEBHOOK_HOSTS=hooks.your-company.com
```

Jalankan `php artisan config:cache` sesudah perubahan. Daftar berisi hostname tepat, dipisahkan koma, tanpa scheme/path/wildcard, gunakan huruf kecil. Tidak ada hostname default yang diizinkan. Receiver harus HTTPS port 443, sertifikat valid, resolve IPv4 publik; IP privat/metadata/loopback ditolak, redirect tidak diikuti dan DNS dipin pada setiap percobaan. Host IPv6-only belum didukung.

Daftarkan URL dengan key live:

```sh
curl --fail-with-body -X POST "$MAVA_BASE_URL/webhook" \
  -H "Authorization: Bearer $MAVA_API_KEY" -H 'Content-Type: application/json' \
  -d '{"url":"https://hooks.your-company.com/mavapos"}'
```

Respons 201 berisi `data` dan `signing_secret` yang hanya dikembalikan saat registrasi. Simpan secret di backend receiver. Satu webhook per key; registrasi kedua menghasilkan 409. Untuk mengganti URL/secret, DELETE `/webhook`, kemudian POST ulang. Penghapusan juga membuang antrean delivery lama. GET `/webhook` tidak mengungkap secret.

Event `transaction.created` adalah notifikasi minimal; panggil GET `/transactions/{data.transaction_id}` untuk detail saat ini. Tidak ada event retroaktif bagi transaksi sebelum registrasi. Perubahan/void/refund tidak memicu event baru pada versi ini. Transaksi offline memicu event ketika diterima backend: `occurred_at` adalah waktu pencatatan server, sedangkan `data.sold_at` adalah waktu penjualan/capture.

```json
{"id":"UUID-STABIL","event":"transaction.created","occurred_at":"2026-11-13T10:00:00+08:00","sandbox":false,"data":{"transaction_id":123,"outlet_id":1,"transaction_number":"POS-20261113-001","sold_at":"2026-11-13T10:00:00+08:00"}}
```

Header:

```http
Content-Type: application/json
X-Mava-Event-Id: UUID-STABIL
X-Mava-Timestamp: 1794535200
X-Mava-Signature: sha256=HEX_HMAC_SHA256
```

Tanda tangan adalah HMAC SHA-256 dari `timestamp + "." + raw_body`, memakai signing secret. Verifikasi **raw body persis seperti diterima**, sebelum decode/re-encode JSON. Contoh PHP receiver:

```php
$raw = file_get_contents('php://input');
$timestamp = $_SERVER['HTTP_X_MAVA_TIMESTAMP'] ?? '';
$signature = $_SERVER['HTTP_X_MAVA_SIGNATURE'] ?? '';
$expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$raw, getenv('MAVA_WEBHOOK_SECRET'));
if (! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 300 || ! hash_equals($expected, $signature)) {
    http_response_code(401);
    exit;
}
$event = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
// Simpan/antrekan event dengan unique key pada $event['id']; balas 2xx setelah tersimpan.
http_response_code(204);
```

Scheduler Laravel harus aktif, misalnya cron setiap menit dari direktori backend:

```cron
* * * * * cd /path/to/mava_backend && php artisan schedule:run >> /dev/null 2>&1
```

Jalankan manual untuk diagnosis: `php artisan partner:webhooks:send --limit=100` (1–1000 per batch). Pengiriman dilakukan terpisah dari request checkout. Outbox ikut rollback jika checkout dibatalkan. Checkout yang dikirim ulang memakai idempotency key yang sama tidak membuat event lagi.

Timeout koneksi 3 detik dan total 10 detik; hanya 2xx dianggap sukses. Maksimum 5 percobaan; jeda setelah gagal 1, 5, 15, dan 60 menit, dibulatkan mengikuti scheduler. Timestamp/tanda tangan dibuat ulang pada retry, ID/body event tetap sama. Setelah proses mati, lease 5 menit memungkinkan pemulihan. Receiver wajib deduplikasi ID; pengiriman ulang tetap mungkin (at least once selama retry). Sesudah 5 kegagalan, delivery berhenti dan ditandai gagal; rekonsiliasi melalui REST diperlukan. Key yang dicabut/kedaluwarsa atau owner nonaktif menghentikan delivery. Bila key akan diganti, daftarkan webhook baru untuk key pengganti.

Operator dapat memeriksa `partner_webhook_deliveries`: `attempts`, `available_at`, `delivered_at`, `failed_at`, `last_status`, `last_error`. Respons receiver dan secret tidak ditulis ke kolom error. Scheduler memiliki pencegahan overlap, tetapi latency bisa bertambah saat backlog; pantau antrean dan durasi batch. Dengan limit default dan worst-case timeout, satu batch dapat berjalan lebih dari satu menit.

## 5. Sandbox

Sandbox adalah **fixture kontrak**, bukan salinan database produksi dan bukan mesin simulasi checkout. Key tetap diterbitkan untuk owner yang ada, tetapi endpoint data selalu menggunakan contoh tetap dan tidak membaca penjualan asli.

- Outlet contoh `900001`, produk/varian/kategori `900001`.
- Tiga transaksi pada **13 November 2026 WITA**, ID `900001` completed, `900002` voided, `900003` refunded.
- Gunakan `from=2026-11-13T00:00:00+08:00`, `to=2026-11-14T00:00:00+08:00`, `outlet_id=900001`.
- Semua response laporan memiliki `meta.sandbox: true`.

```sh
export MAVA_BASE_URL='https://your-mavapos-domain.com/api/partner/sandbox/v1'
export MAVA_API_KEY='ISI_KEY_SANDBOX_DARI_OPERATOR'

curl --fail-with-body --get "$MAVA_BASE_URL/transactions" \
  -H "Authorization: Bearer $MAVA_API_KEY" \
  --data-urlencode 'outlet_id=900001' \
  --data-urlencode 'from=2026-11-13T00:00:00+08:00' \
  --data-urlencode 'to=2026-11-14T00:00:00+08:00'
```

Untuk uji verifikasi webhook, daftarkan `/webhook` menggunakan key sandbox (hostname tetap perlu allowlist), kemudian POST `/webhook/test`. Respons mengembalikan `body` sebagai **string raw JSON**, `headers` bertanda tangan, dan `sent: false`. Gunakan keduanya sebagai input lokal pengujian receiver. Backend tidak menghubungi URL sandbox dan transaksi produksi tidak dimasukkan ke antrean sandbox. Untuk uji HTTP end-to-end, gunakan instalasi staging terpisah beserta data uji dan key live pada instalasi staging tersebut.

## 6. Respons kesalahan

| HTTP | Makna |
| --- | --- |
| 401 | Bearer key hilang/tidak valid/kedaluwarsa/dicabut atau akun owner tidak aktif |
| 403 | Key untuk environment berbeda; test webhook dipanggil dengan key live |
| 404 | Outlet/transaksi tidak ditemukan atau bukan milik merchant |
| 409 | Webhook untuk key sudah terdaftar |
| 422 | Parameter/pagination/rentang waktu/URL webhook tidak valid |
| 429 | Rate limit tercapai; tunggu sesuai `Retry-After` |

Kesalahan berbentuk JSON dengan `message`; 422 juga memiliki `errors` per field. Endpoint bisnis tidak menerima mutasi melalui key partner. Respons sukses diberi `Cache-Control: private, no-store`. Jangan gunakan URL contoh domain atau key placeholder sebagai kredensial aktual.

## Pending POS gateway transactions

Transaksi POS Midtrans yang belum dibayar memiliki `status: pending`, `received_amount: 0`, `net_total: 0`, dan `payments: []`. `total` tetap menunjukkan nilai pesanan. Ringkasan penjualan hanya mencakup transaksi paid non-void. Notifikasi `transaction.created` untuk transaksi gateway diterbitkan setelah pembayaran terverifikasi, bukan saat reservasi stok. Refund/reversal yang terverifikasi terlihat sebagai penyesuaian keuangan tanpa menebak jumlah barang yang kembali.
