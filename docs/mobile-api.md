# MavaPOS Mobile API v1

Base URL: `https://your-mavapos-domain.com/api/mobile/v1`.
Konfigurasi Flutter yang dapat dibaca mesin: [flutter-setup.json](mobile-api/flutter-setup.json).

Gunakan HTTPS, `Accept: application/json`, `Content-Type: application/json`, dan `Authorization: Bearer {token}` setelah login. Endpoint API selalu mengembalikan respons JSON, termasuk 401/404/422 tanpa header Accept. Endpoint token tidak memerlukan CSRF.

| Method | Path relatif | Akses |
| --- | --- | --- |
| POST | `/login` | Email dan password; owner/admin/kasir |
| GET | `/me` | Token; tetap tersedia saat langganan habis |
| POST | `/logout` | Mencabut token saat ini; tetap tersedia saat langganan habis |
| DELETE atau POST | `/account/delete` | Token + verifikasi identitas; tetap tersedia saat langganan habis |
| GET | `/pos` | Katalog/fitur/shift/cabang, langganan aktif |
| POST | `/shifts/start` | Buka shift |
| POST | `/shifts/close` | Tutup shift dan rekap; Bearer token, tersedia saat langganan habis |
| POST | `/checkout` | Checkout; mendukung ID stabil dan konteks capture offline |
| GET | `/sales/{sale}/refund` | Rincian item/limit/sisa retur; owner/admin |
| POST | `/sales/{sale}/refund` | Pencatatan retur/refund; owner/admin |

Pilih `branch_id` untuk owner/admin. Kasir mengikuti cabang tugasnya. Route akun tidak memerlukan pemilihan cabang dan tidak diblokir langganan habis. Login dan `/me` menyertakan `account_deletion` berisi URL, method, path, dan `verification_methods`.

## Hapus akun: URL langsung `/account/delete`

Endpoint yang setara:

- `DELETE https://your-mavapos-domain.com/account/delete`
- `DELETE https://your-mavapos-domain.com/api/mobile/v1/account/delete`

POST pada URL yang sama disediakan untuk kompatibilitas klien. GET tidak menghapus akun. Cookie web saja tidak diterima; Bearer token diperlukan. Penghapusan selalu memakai pengguna pemilik token; `user_id` dari body tidak dipakai sebagai sasaran.

```json
{
  "verification": "password",
  "password": "kata-sandi-saat-ini",
  "confirmation": "HAPUS AKUN",
  "understand": true
}
```

Akun yang terhubung Google dapat memakai token Google yang baru:

```json
{
  "verification": "google",
  "google_id_token": "GOOGLE_OPENID_CONNECT_ID_TOKEN",
  "confirmation": "HAPUS AKUN",
  "understand": true
}
```

Gunakan Google OpenID Connect ID token, bukan access token atau Firebase ID token. `aud` harus sama dengan `GOOGLE_CLIENT_ID` backend (gunakan sebagai serverClientId di klien), `sub` harus cocok dengan akun yang terhubung, dan token harus diterbitkan dalam 5 menit terakhir. Server memverifikasi tanda tangan RS256, issuer, audience, expiry dan identitas menggunakan kunci publik Google yang di-cache sesuai Cache-Control. Implementasi mengikuti [panduan verifikasi ID token Google](https://developers.google.com/identity/openid-connect/openid-connect#validatinganidtoken).

Respons sukses 200:

```json
{"message":"Akun Anda berhasil dihapus.","deleted":true}
```

Sesudah sukses, hapus kredensial lokal dan kembali ke login. Seluruh token akun dicabut. Data profil dianonimkan; catatan transaksi, cabang, stok dan akun staf tetap disimpan. Shift milik pengguna harus sudah ditutup. Gunakan POST `/shifts/close` dengan `branch_id` cabang aktif yang sesuai; penutupan shift tetap tersedia ketika langganan habis. Aplikasi wajib memeriksa antrean offline pada perangkatnya sendiri dan meminta pengguna menyelesaikan antrean di semua perangkat sebelum mengirim permintaan: server tidak dapat membaca IndexedDB/penyimpanan perangkat.

## Checkout offline dan replay

Persist ID UUID dan payload sebelum pengiriman pertama. Kirim UUID melalui `Idempotency-Key` dan/atau `client_transaction_id`; nilainya harus sama jika keduanya dipakai. Semua retry memakai UUID dan payload yang sama.

Untuk capture offline, tambahkan `client_created_at` (ISO8601 saat pesanan direkam), `cashier_shift_id`, `cashier_id`, dan `expected_total` (integer rupiah). Server memeriksa identitas, cabang, waktu berada dalam shift asal, stok dan harga. Transaksi dapat tersinkron ke shift yang sudah ditutup jika waktu capture masih berada dalam rentang shift tersebut.

Replay sukses memiliki header `Idempotent-Replayed: true`. Invoice dan mutasi stok tidak dibuat lagi. Respons replay adalah snapshot jawaban sebelumnya; lakukan GET `/pos` untuk stok dan rekap terkini. HTTP 409/422 disimpan sebagai konflik untuk ditinjau operator, bukan dicoba ulang otomatis atau diganti UUID secara diam-diam.

## Retur/refund mobile

GET `/sales/{sale}/refund` menghasilkan JSON `sale`, `items`, `refunded_amount`, `refund_methods`, dan `refunds`. Setiap item memuat `pos_sale_item_id`, `quantity`, `returned_quantity`, `remaining_quantity`, `allocated_refund_amount`, `remaining_refund_amount`, dan `can_restock`. Kedua endpoint retur memerlukan Bearer token asli.

Untuk pratinjau nominal item, hitung `floor(allocated_refund_amount × (returned_quantity + quantity_yang_dipilih) / quantity) - floor(allocated_refund_amount × returned_quantity / quantity)`, kemudian jumlahkan semua item. Ambil rincian terbaru sebelum pembayaran balik agar jumlah retur sebelumnya sudah diperhitungkan.

POST pada path yang sama:

```json
{
  "branch_id": 1,
  "client_refund_id": "871bf75c-3e27-4fe7-8323-70a58c8dc548",
  "refund_method": "cash",
  "reason": "Satu barang dikembalikan",
  "money_returned": true,
  "items": [{"pos_sale_item_id": 123, "quantity": 1, "restock": false}]
}
```

Gunakan ID refund stabil untuk pengiriman ulang. `money_returned` hanya dikonfirmasi setelah pembayaran balik aktual dilakukan secara manual. Metode: cash, qris, card, bank_transfer. Server menentukan nominal berdasarkan penerimaan asli setelah diskon/potongan merchant dan jumlah yang belum diretur. Stok hanya dikembalikan bila dipilih; bahan resep tidak dipulihkan otomatis.

## Status kesalahan

401 token hilang/tidak valid; 402 langganan tidak aktif pada endpoint operasional; 403 akses role/cabang ditolak; 404 data tidak ditemukan; 409 ID sudah digunakan dengan data berbeda; 422 validasi; 429 batas percobaan; 503 verifikasi Google sementara tidak tersedia. Respons validasi berisi `message` dan `errors` per field. Semua nominal menggunakan integer rupiah.
