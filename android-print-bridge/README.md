# MavaPOS Android Print Bridge

Companion app untuk printer Bluetooth Classic/SPP seperti ECO80BT. Aplikasi membuka HTTP server lokal pada `127.0.0.1:8765` dan meneruskan payload CPCL ke printer melalui `BluetoothSocket` RFCOMM.

## Endpoints

- `GET /health`
- `GET /printers`
- `POST /printers/connect` — `{ "address": "AA:BB:CC:DD:EE:FF" }`
- `POST /settings/auto-print` — `{ "enabled": true }`
- `POST /print` — `{ "cpclBase64": "..." }`

## Flow

1. Pair printer dari Android Settings.
2. Buka aplikasi bridge dan izinkan Bluetooth.
3. Web POS mengambil `/printers`, memilih address, lalu memanggil `/printers/connect`.
4. Web POS mengirim CPCL Base64 ke `/print` setelah checkout.

Project ini adalah scaffold native Kotlin. Buka folder ini dengan Android Studio, lalu build/install ke perangkat Android.
