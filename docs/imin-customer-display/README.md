# IMIN D1 Customer Display — Native Bridge Spec

Sub-display 2.4" (240×320) pada IMIN D1/D1 Pro/Falcon 1 dikontrol via Java API
`com.imin.lcd.ILcdManager`. Library ini **hanya tersedia dari aplikasi Android
native**, bukan dari browser. Karena POS ini adalah web app, kita butuh satu
APK kecil yang berjalan di background untuk menerjemahkan state dari server
ke LCD.

## Arsitektur

```
┌──────────────────┐    POST /display/state    ┌──────────────────┐
│ POS Web (kasir)  │ ─────────────────────────▶│  Laravel server  │
└──────────────────┘                           └──────────────────┘
                                                          │ GET /display/state
                                                          ▼
┌──────────────────┐    HTTP poll tiap 1 dtk   ┌──────────────────┐
│ CustomerDisplay  │ ◀─────────────────────────│  (state in cache)│
│ APK (foreground) │                           └──────────────────┘
└──────────────────┘
         │
         │ ILcdManager.sendLCDMultiString / sendLCDCommand
         ▼
   [ LCD 240×320 di belakang D1 ]
```

## Alur kerja

1. Kasir ubah cart di web → `posManager` di `app.js` push state ke
   `POST /display/state`. State disimpan di cache Laravel per-branch selama 60
   detik.
2. APK `CustomerDisplay` jalan terus di D1 (foreground service), polling
   `GET /display/state` setiap 1 detik.
3. APK render teks ke LCD via `ILcdManager` (10 char/baris).
4. Transaksi selesai → mode berubah ke `checkout` → APK tampilkan total +
   kembali dengan font besar.

## Limitasi ILcdManager (D1)

| Method | Kapasitas |
|---|---|
| `sendLCDString` | 1 baris, maks 10 char Inggris / 5 Mandarin |
| `sendLCDMultiString(texts[], aligns[])` | multi-baris (untuk D1 Pro: sampai 4 baris; Falcon 1: sampai 8 baris) |
| `sendLCDDoubleString(top, bottom)` | 2 baris fixed |
| `sendLCDBitmap` | gambar maks 240×320 px |
| `setTextSize` | default `55`; range aman `40-70` |

## Layout LCD yang ditampilkan

Mode `cart`:

```
┌──────────────────────┐
│ MAVA MART      CART  │
├──────────────────────┤
│ 2x KOPI SUSU   24K   │
│ 1x ROTI         8K   │
│ 1x AIR MNRL     5K   │
│                      │
│                      │
│                      │
│                      │
├──────────────────────┤
│ TOTAL         Rp37K  │
└──────────────────────┘
```

Mode `checkout`:

```
┌──────────────────────┐
│  PEMBAYARAN SELESAI  │
│                      │
│    Rp 37.000         │
│                      │
│    KEMBALI           │
│    Rp  3.000         │
│                      │
│                      │
│                      │
└──────────────────────┘
```

## Library AAR

- `ILcdManager` shipped via SDK IMIN. Hubungi partner portal
  <https://oss-sg.imin.sg/> untuk unduh AAR-nya.
- AAR biasanya diberi nama `iminsdk.aar` atau `IminLibrary.aar`. Taruh di
  `app/libs/` lalu tambahkan di `app/build.gradle`:
  `implementation files('libs/iminsdk.aar')`

## File-file di folder ini

- `CustomerDisplayService.kt` — Foreground service + polling + render ke LCD
- `LcdRenderer.kt` — Helper untuk format rupiah pendek & layout multi-baris
- `build.gradle` (snippet) — Setup dependency
- `AndroidManifest.xml` (snippet) — Permission & service declaration
