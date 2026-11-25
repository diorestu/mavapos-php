# MavaPOS Partner API v1

Gunakan API ini untuk membaca outlet, produk, transaksi, dan ringkasan penjualan dari aplikasi Anda. Setiap pasangan key hanya dapat mengakses data satu merchant. API ini tidak menyediakan pembuatan atau perubahan transaksi POS.

[Unduh OpenAPI untuk Postman/Swagger](partner-api.openapi.json) · [Panduan admin server](partner-api-operations.md)

## 1. Buat akses

1. Login sebagai owner/admin MavaPOS.
2. Buka **Pengaturan → Integrasi API → Kredensial REST API Pihak Ketiga**.
3. Isi nama integrasi, pilih Sandbox/Produksi dan masa berlaku 1–365 hari.
4. Klik **Buat Pasangan Kredensial**, lalu simpan **Bearer token** dan **Client Key**. Nilai lengkap hanya ditampilkan sekali.

Untuk token lama, klik **Buat Client Key**. **Ganti Client Key** menonaktifkan Client Key lama tanpa mengganti Bearer. Jika Bearer hilang, buat pasangan baru. **Cabut** menonaktifkan keduanya.

Token aplikasi lama/Sanctum dan Client Key Midtrans tidak berlaku untuk Partner API.

## 2. URL dan header

Gunakan alamat berikut untuk mengakses API MavaPOS.

| Mode | Base URL |
| --- | --- |
| Produksi | `https://mavapos.id/api/partner/v1` |
| Sandbox | `https://mavapos.id/api/partner/sandbox/v1` |

Semua endpoint wajib menerima **Bearer token dan Client Key dari pasangan yang sama**:

```http
Authorization: Bearer {token}
X-Client-Key: {client_key}
Accept: application/json
```

Tambahkan `Content-Type: application/json` jika mengirim body JSON. Simpan key di server aplikasi Anda, bukan di URL atau frontend publik. Key sandbox hanya berlaku di URL sandbox, begitu juga produksi.

## 3. Coba request

Isi tiga variabel berikut, lalu jalankan contoh cURL:

```sh
export MAVA_BASE_URL='https://mavapos.id/api/partner/v1'
export MAVA_API_KEY='ISI_BEARER_TOKEN'
export MAVA_CLIENT_KEY='ISI_CLIENT_KEY_PASANGAN'

# Daftar outlet
curl --fail-with-body "$MAVA_BASE_URL/outlets" \
  -H "Authorization: Bearer $MAVA_API_KEY" \
  -H "X-Client-Key: $MAVA_CLIENT_KEY" \
  -H 'Accept: application/json'

# Transaksi satu outlet selama satu hari WITA
curl --fail-with-body --get "$MAVA_BASE_URL/transactions" \
  -H "Authorization: Bearer $MAVA_API_KEY" \
  -H "X-Client-Key: $MAVA_CLIENT_KEY" \
  -H 'Accept: application/json' \
  --data-urlencode 'outlet_id=1' \
  --data-urlencode 'from=2026-11-13T00:00:00+08:00' \
  --data-urlencode 'to=2026-11-14T00:00:00+08:00' \
  --data-urlencode 'per_page=100'
```

Gunakan `--data-urlencode` agar tanda `+` pada zona waktu terkirim dengan benar.

## 4. Endpoint

Semua path di bawah ditambahkan setelah Base URL.

| Method | Path | Kegunaan |
| --- | --- | --- |
| GET | `/outlets` | Daftar ID, nama, kode dan status aktif outlet |
| GET | `/products` | Daftar produk, kategori, harga dan varian |
| GET | `/transactions` | Daftar transaksi beserta item, pembayaran dan refund |
| GET | `/transactions/{id}` | Detail satu transaksi |
| GET | `/sales-summary` | Total penjualan dan rincian penjualan kotor per produk |
| GET | `/webhook` | Lihat webhook milik key ini; `data: null` jika belum ada |
| POST | `/webhook` | Daftarkan webhook |
| DELETE | `/webhook` | Hapus webhook beserta antrean pengirimannya; respons 204 |
| POST | `/webhook/test` | Sandbox: buat contoh notifikasi bertanda tangan, tanpa mengirim HTTP |

Outlet nonaktif tetap bisa dibaca untuk riwayat. Daftar produk berlaku untuk seluruh merchant, bukan stok atau harga per outlet. Harga varian adalah harga produk ditambah `price_adjustment` varian.

## 5. Filter, halaman dan batas request

Filter berikut berlaku untuk `/transactions` dan `/sales-summary`:

| Parameter | Wajib | Isi |
| --- | --- | --- |
| `outlet_id` | Ya | ID outlet milik merchant |
| `from` | Ya | Waktu mulai, termasuk dalam hasil |
| `to` | Ya | Waktu akhir, tidak termasuk dalam hasil |
| `status` | Tidak | Salah satu status pada tabel di bawah; kosong berarti semua |
| `updated_since` | Tidak | Ambil transaksi yang berubah, atau memiliki refund yang berubah, sejak waktu ini |
| `page` | Tidak | Nomor halaman, default 1; rentang 1–100000 |
| `per_page` | Tidak | Jumlah data per halaman, default 50; maksimum 100 |

`page` dan `per_page` juga berlaku untuk daftar outlet dan produk. Transaksi diurutkan dari `sold_at` terlama, lalu ID; outlet dan produk dari ID terkecil.

- Rentang `from`–`to` harus lebih dari nol dan maksimal **31 hari**.
- Filter waktu berdasarkan **waktu penjualan (`sold_at`)**. Contoh satu hari: 00:00 hari tersebut sampai 00:00 hari berikutnya.
- Gunakan tanggal, jam, detik dan zona waktu: `2026-11-13T00:00:00+08:00` atau `2026-11-12T16:00:00Z`. Tanpa zona waktu atau dengan pecahan detik akan ditolak.
- Waktu pada respons memakai **WITA, Asia/Makassar (`+08:00`)**. Semua nominal adalah **rupiah bulat (IDR)**.
- Batas **60 request/menit per key**, digabung untuk semua endpoint. Lihat `X-RateLimit-Remaining`; jika 429, tunggu sesuai `Retry-After` dalam detik.

`updated_since` tidak menghapus filter `from`–`to`. Untuk menemukan refund baru atas transaksi bulan lalu, baca lagi rentang penjualan bulan lalu atau ambil detail ID transaksi tersebut. Saat sinkronisasi beberapa halaman, simpan berdasarkan ID dan baca ulang sedikit rentang waktu yang sama karena data bisa berubah.

## 6. Respons transaksi

Contoh berikut dipersingkat. Daftar field lengkap ada di OpenAPI.

```json
{
  "data": [{
    "id": 123,
    "outlet_id": 1,
    "transaction_number": "POS-20261113-001",
    "sold_at": "2026-11-13T10:00:00+08:00",
    "updated_at": "2026-11-13T10:00:00+08:00",
    "status": "completed",
    "subtotal": 50000,
    "discount": 5000,
    "total": 45000,
    "payment_method": "cash",
    "payments": [{"method": "cash", "amount": 45000}],
    "refunded_amount": 0,
    "net_total": 45000,
    "refunds": [],
    "items": [{
      "id": 456,
      "product_id": 8,
      "variant_id": 9,
      "name": "Kopi · Besar",
      "sku": "KOPI-L",
      "quantity": 2,
      "unit_price": 25000,
      "line_total": 50000,
      "discount": null,
      "variant": {"id": 9, "name": "Besar", "attributes": {"size": "L"}},
      "modifiers": null
    }]
  }],
  "meta": {
    "timezone": "Asia/Makassar",
    "currency": "IDR",
    "sandbox": false,
    "page": 1,
    "per_page": 50,
    "total": 1,
    "last_page": 1
  }
}
```

Daftar memakai `data: []`. Detail `/transactions/{id}` memakai `data: {}`. Hasil kosong memiliki `total: 0` dan `last_page: 1`.

| Status | Arti |
| --- | --- |
| `pending` | Belum dibayar |
| `completed` | Lunas |
| `voided` | Dibatalkan |
| `partially_refunded` | Sebagian pembayaran dikembalikan |
| `refunded` | Seluruh pembayaran dikembalikan |

| Field | Arti |
| --- | --- |
| `subtotal`, `discount`, `total` | Nilai sebelum diskon, diskon pesanan, dan nilai setelah diskon |
| `refunded_amount` | Jumlah refund yang sudah tercatat |
| `net_total` | `total - refunded_amount`; bernilai 0 untuk pending/void |
| `paid_amount`, `change_amount` | Nominal bayar dan kembalian yang tersimpan |
| `received_amount` | Penerimaan sebelum refund; untuk online merchant memakai nominal bersih yang diterima penjual |
| `merchant_deduction` | Selisih `total` dan penerimaan bersih untuk metode `online_merchant` |
| `payments` | Rincian pembayaran gabungan, atau satu pembayaran untuk metode tunggal |
| `refunds` | Daftar refund: nomor, nominal, metode, waktu dan item jika tersedia |
| `items[].line_total` | Nilai item sebelum diskon pesanan |

Pending memiliki `received_amount: 0`, `net_total: 0`, dan `payments: []`. Data split lama tanpa rincian juga dapat memiliki `payments: []`. `net_total` bukan nilai pencairan bersih online merchant atau wallet gateway.

Metode pembayaran mengikuti data tersimpan, misalnya `cash`, `qris`, `card`, `split`, `online_merchant`, `midtrans`, dan `free`. Terima kemungkinan metode baru.

Nama, SKU dan harga item mengikuti catatan saat transaksi. Jika produk/varian dihapus, ID relasi bisa `null` tetapi nama dan harga tetap ada. Data varian lama dapat `null`. `items[].discount` selalu `null` karena diskon disimpan per pesanan; modifier terpisah belum tersedia sehingga `modifiers: null`. Refund tidak mengurangi `quantity` asli. Data pribadi pembeli dan harga modal tidak dikirim.

## 7. Ringkasan penjualan

`GET /sales-summary` mengembalikan:

| Field | Arti |
| --- | --- |
| `transaction_count` | Jumlah transaksi lunas, tidak termasuk void |
| `gross_sales`, `discount`, `total_sales` | Nilai sebelum diskon, diskon dan nilai setelah diskon |
| `refunded_amount`, `net_sales` | Total refund dan `total_sales - refunded_amount` |
| `products` | ID produk/varian, nama, SKU, jumlah terjual dan nilai kotor per produk |

Total dihitung dari seluruh hasil filter. Hanya `products` yang dibagi per halaman, dengan informasi halaman di `meta.products`.

Refund dihitung pada rentang **penjualan asal**, bukan tanggal refund. Angka per produk masih berupa jumlah dan nilai kotor asli, belum dikurangi refund atau dibagi diskon pesanan. Perubahan nama/SKU dapat membuat satu produk muncul dalam beberapa baris. Ringkasan ini bukan laporan pencairan dana Midtrans.

## 8. Webhook transaksi baru

Webhook memberi tahu server Anda saat ada transaksi baru. Untuk transaksi gateway, notifikasi dibuat setelah pembayaran terverifikasi. Perubahan, void dan refund tidak mengirim event baru; gunakan API untuk memeriksa pembaruannya.

Minta admin MavaPOS mengizinkan hostname penerima terlebih dahulu. URL harus **HTTPS port 443**, bersertifikat valid dan memakai IPv4 publik. Redirect tidak diikuti; host IPv6-only belum didukung.

```sh
curl --fail-with-body -X POST "$MAVA_BASE_URL/webhook" \
  -H "Authorization: Bearer $MAVA_API_KEY" \
  -H "X-Client-Key: $MAVA_CLIENT_KEY" \
  -H 'Content-Type: application/json' \
  -d '{"url":"https://hooks.your-company.com/mavapos"}'
```

Respons 201 berisi `data` dan `signing_secret` yang ditampilkan sekali. Secret ini berbeda dari Client Key. Satu key hanya memiliki satu webhook; registrasi kedua ditolak dengan 409. Untuk mengganti URL/secret, DELETE lalu POST kembali. Penghapusan membuang antrean lama; tidak ada notifikasi untuk transaksi sebelum webhook didaftarkan.

Contoh event:

```json
{"id":"UUID-STABIL","event":"transaction.created","occurred_at":"2026-11-13T10:00:00+08:00","sandbox":false,"data":{"transaction_id":123,"outlet_id":1,"transaction_number":"POS-20261113-001","sold_at":"2026-11-13T10:00:00+08:00"}}
```

Ambil detail lewat `/transactions/{data.transaction_id}`. Untuk transaksi offline, `sold_at` adalah waktu penjualan, sedangkan `occurred_at` adalah waktu pencatatan server.

Verifikasi tanda tangan dari header `X-Mava-Timestamp` dan `X-Mava-Signature`. Rumusnya: HMAC SHA-256 atas `timestamp + "." + raw_body`, memakai signing secret. Gunakan body asli, jangan JSON yang sudah diubah kembali.

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
// Simpan event dengan ID unik, lalu balas 2xx setelah penyimpanan berhasil.
http_response_code(204);
```

Simpan `id` event sebagai ID unik agar pengiriman ulang tidak diproses dua kali. ID/body tetap sama pada retry; timestamp dan tanda tangan diperbarui. Header `X-Mava-Event-Id` juga berisi ID event.

Pengiriman dicoba maksimal **5 kali**, dengan jeda 1, 5, 15, dan 60 menit mengikuti jadwal server. Hanya 2xx dianggap berhasil. Setelah gagal lima kali, periksa ulang data lewat API. Key yang kedaluwarsa/dicabut atau owner tidak aktif menghentikan pengiriman. Jika Bearer diganti, daftarkan webhook untuk key baru.

## 9. Uji sandbox

Sandbox memakai data contoh tetap, bukan data produksi dan bukan simulasi checkout.

- Gunakan Base URL sandbox dan pasangan key sandbox.
- Outlet `900001`; produk, kategori dan varian juga `900001`.
- Tanggal contoh **13 November 2026 WITA**; transaksi `900001` completed, `900002` voided, `900003` refunded.
- Jalankan contoh transaksi di atas dengan `outlet_id=900001` dan rentang 13–14 November 2026. Respons memiliki `meta.sandbox: true`.

Untuk menguji tanda tangan, daftarkan webhook sandbox lalu POST `/webhook/test`. Respons berisi string `body`, `headers` bertanda tangan, dan `sent: false`. Tidak ada HTTP yang dikirim ke penerima. Untuk menguji pengiriman HTTP lengkap, gunakan instalasi staging dengan data uji dan key live pada staging tersebut.

## 10. Kode error

| HTTP | Arti |
| --- | --- |
| 401 | Bearer/Client Key hilang, salah, bukan pasangan, kedaluwarsa/dicabut, atau owner tidak aktif |
| 403 | Mode key dan URL berbeda, atau test webhook memakai key live |
| 404 | Data tidak ditemukan atau bukan milik merchant |
| 409 | Webhook sudah terdaftar |
| 422 | Parameter tidak valid; lihat `errors` per field |
| 429 | Terlalu banyak request; tunggu sesuai `Retry-After` |

Semua error berbentuk JSON dengan `message`. Respons sukses memakai `Cache-Control: private, no-store`. Token lama tanpa Client Key juga mendapat 401: buat Client Key di Pengaturan dan tambahkan header-nya pada setiap request.
