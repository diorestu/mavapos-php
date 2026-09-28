# PRD — MavaPOS UI Template & Product Identity

**Status:** Draft  
**Product:** MavaPOS  
**Platform:** Web backoffice/POS, responsive tablet, mobile API companion app

## 1. Ringkasan

MavaPOS adalah platform POS cloud untuk transaksi kasir, stok, resep, pelanggan, laporan, membership SaaS, dan operasi multi-cabang. Dokumen ini menjadi acuan identitas merek, template UI, dan standar pengalaman untuk owner, admin, kasir, gudang, superadmin, serta aplikasi mobile.

## 2. Tujuan

- Mempercepat checkout dengan interaksi yang sederhana.
- Membuat cabang, shift, stok, pelanggan, voucher, dan laporan mudah dipahami.
- Menjaga data antar-cabang dan antar-tenant tidak tercampur.
- Menyediakan pola UI yang dapat dipakai ulang pada halaman baru.
- Menjadikan web tablet dan API mobile konsisten dengan aturan bisnis yang sama.

## 3. Non-goals

- Mengganti Laravel Blade, Tailwind, Alpine.js, atau Vite.
- Membuat ulang seluruh backend.
- Mengubah aturan bisnis stok, voucher, subscription, atau otorisasi tanpa spesifikasi terpisah.

## 4. Persona

| Persona | Kebutuhan | Risiko |
|---|---|---|
| Owner | omzet, cabang, staf, laporan, billing | data cabang tercampur |
| Admin | produk, transaksi, user, koreksi | perubahan tanpa audit |
| Kasir | checkout, pelanggan, voucher, printer | salah stok/shift/voucher |
| Gudang | stok masuk, keluar, transfer | saldo stok tidak sinkron |
| Superadmin | member SaaS dan pendapatan | akses lintas tenant berlebihan |
| Mobile app | login, shift, checkout, printer | token/cabang/API tidak konsisten |

## 5. Identitas MavaPOS

### Positioning

MavaPOS membantu bisnis mengubah transaksi harian menjadi operasi yang rapi, terukur, dan siap berkembang.

### Karakter

Praktis, terpercaya, modern, dan dekat dengan bisnis lokal Indonesia.

### Voice and tone

- Gunakan Bahasa Indonesia yang singkat dan langsung.
- Gunakan label berbasis aksi: `Buka Kasir`, `Simpan Transaksi`, `Pindah Cabang`.
- Gunakan istilah konsisten: `cabang`, `shift`, `kasir`, `pelanggan`, `voucher`, `stok`, `langganan`.
- Error harus menjelaskan masalah dan tindakan berikutnya.

Contoh: `Stok cabang tujuan tidak cukup untuk Kopi Susu Aren.`

### Logo and avatar

- Gunakan `public/logo.png` tanpa mengubah proporsi.
- Foto profil memakai foto user jika tersedia.
- Jika belum ada foto, gunakan inisial nama, misalnya `Budi Santoso` menjadi `BS`.

## 6. Design tokens

| Token | Nilai | Penggunaan |
|---|---|---|
| `brand-50` | `#ecf3ff` | surface informasi ringan |
| `brand-100` | `#dde9ff` | surface terpilih |
| `brand-500` | `#465fff` | primary action/focus |
| `brand-600` | `#3641f5` | hover/active |
| `brand-700` | `#2a31d8` | emphasis |
| `brand-900` | `#262e89` | dark brand surface |
| `gray-dark` | `#1a2231` | dark surface |

- App UI memakai `Public Sans`.
- Landing page boleh memakai `Plus Jakarta Sans`.
- Page title 20–24 px; section title 14–16 px; body 13–14 px; metadata 11–12 px.
- Radius default 8–12 px; modal/hero 16–24 px.
- Gunakan shadow ringan pada card dan shadow besar hanya untuk modal/dropdown.

## 7. Template UI

### App shell

- Sidebar responsive: expanded, collapsed, dan mobile.
- Sticky header dengan global search, notifikasi, user dropdown, avatar, dan active branch.
- Kasir yang memiliki `branch_id` tidak boleh berpindah cabang.
- Superadmin memakai shell membership, bukan shell operasional tenant.

### Dashboard

Urutan: heading dan konteks cabang/periode → metric cards → chart/ringkasan → top products/activities → alert/action.

Setiap metric wajib memiliki label, nilai, periode, dan konteks cabang.

### POS

- Header menampilkan status shift, kasir, cabang, SOP, dan printer.
- Kategori: `Semua`, kategori aktif, dan favorites jika tersedia.
- Pada kategori `Semua`, produk tersedia tampil lebih dulu, lalu nama kategori, lalu nama produk; produk habis berada paling bawah.
- Produk habis tetap dapat terlihat tetapi tidak dapat ditambahkan ke cart.
- Checkout menampilkan pelanggan, voucher, diskon, metode pembayaran, total, dan hasil submit.
- Error checkout tidak menghapus isi cart.

### Table

- Filter/search di atas tabel.
- Status memakai badge semantic.
- Action berisiko ditempatkan di sisi kanan dan membutuhkan konfirmasi.
- Mobile memakai horizontal scroll atau card transformation.
- Empty state menjelaskan langkah membuat data pertama.

### Form

Setiap form wajib memiliki label, helper text bila perlu, validation dekat field, loading state, success feedback, dan permission state.

### Report

- Heading menampilkan cabang aktif dan periode.
- Pisahkan gross, discount, net, dan payment breakdown.
- Sediakan detail transaksi sebagai sumber angka.
- Export menyertakan periode dan cabang.

### Membership/superadmin

- Tampilkan member, paket, status, periode, pendapatan, dan kontrol akses.
- `Bypass`, `Extend`, dan `Revoke` selalu membutuhkan alasan.
- Tampilkan masa berlaku override secara eksplisit.

## 8. State dan feedback

Setiap halaman harus menangani loading, empty, error, success, forbidden, dan retry/offline bila relevan.

- Toast untuk hasil singkat.
- Inline alert untuk error yang perlu dibaca.
- Modal konfirmasi untuk perubahan stok, transaksi, membership, dan bypass.
- Jangan menampilkan stack trace kepada pengguna.

## 9. Responsive dan accessibility

### Desktop

Sidebar expanded, tabel penuh, dan cart POS berada di sisi kanan.

### Tablet

POS menjadi surface utama, cart dapat menjadi drawer/sheet, touch target minimum 44 px, dan kategori boleh horizontal-scroll.

### Mobile

Gunakan API/mobile app untuk flow intensif bila browser tidak cukup. Hindari horizontal overflow dan action yang menutupi konten.

### Accessibility

- Semua input memiliki label.
- Icon-only button memiliki `aria-label`.
- Focus state terlihat.
- Kontras memenuhi WCAG AA.
- Modal mendukung Escape dan scrolling keyboard.
- Status tidak disampaikan lewat warna saja.

## 10. Printer dan device identity

Web Bluetooth mendukung BLE GATT, bukan Bluetooth Classic/SPP. Untuk printer seperti ECO80BT yang memakai SPP, gunakan Android native bridge atau plugin Bluetooth Classic.

UI printer harus membedakan:

- Web Bluetooth/BLE GATT;
- Android Bluetooth Classic/SPP bridge;
- USB/LAN printer;
- IMIN InnerPrinter.

Diagnosis harus membedakan browser tidak mendukung Bluetooth, printer bukan GATT, UUID tidak ditemukan, koneksi terputus, dan payload CPCL gagal.

## 11. API mobile

Prefix API: `/api/mobile/v1` dengan Sanctum bearer token.

- `POST /login`
- `GET /me`
- `POST /logout`
- `GET /pos`
- `POST /shifts/start`
- `POST /shifts/close`
- `POST /checkout`

Owner/admin dapat memilih `branch_id`; kasir otomatis dibatasi ke cabang tugasnya. API dan web harus menggunakan aturan stok, pelanggan, voucher, pembayaran, dan shift yang sama.

## 12. Prioritas

### P0 — Operasional

App shell, active branch, POS, checkout, shift, stock states, token UI, responsive tablet, dan tenant isolation.

### P1 — Trust

Report drill-down, customer/loyalty clarity, printer diagnosis/bridge status, profile identity, dan membership dashboard.

### P2 — Scale

Mobile hardening, offline queue/sync, multi-branch analytics, role-specific dashboards, saved filters, dan personalization.

## 13. Acceptance criteria

- Halaman baru memakai token brand, typography, dan template yang sama.
- Semua modal panjang dapat di-scroll di tablet.
- Semua state utama memiliki copy Bahasa Indonesia.
- POS menampilkan cabang dan shift sebelum checkout.
- Item habis berada paling bawah dan tidak bisa masuk cart.
- Total voucher/diskon/pembayaran konsisten sebelum dan sesudah submit.
- Penjualan, bonus, shift, laporan, dan jurnal tidak mencampur cabang.
- Aksi edit, transfer, bypass, dan extend memiliki authorization check serta alasan.
- API mobile mengembalikan JSON konsisten dan memakai Sanctum.

## 14. Definition of done

Sebuah halaman/komponen selesai jika mengikuti token dan template, memiliki state relevan, responsive desktop/tablet, memiliki test untuk aturan bisnis dan authorization, tidak mencampur tenant/cabang, tidak mengekspos kredensial, serta sudah diverifikasi dengan test/build yang relevan.
