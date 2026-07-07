@php
    $rupiah = fn ($value) => 'Rp'.number_format((int) $value, 0, ',', '.');
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan {{ $store->store_name }}</title>
    <style>
        @page {
            margin: 14mm 12mm 18mm;
        }

        * {
            box-sizing: border-box;
            font-family: "DejaVu Sans", Helvetica, Arial, sans-serif;
            font-variant-ligatures: none;
            letter-spacing: 0;
        }
        html,
        body {
            margin: 0;
            color: #111827;
            font-size: 11px;
            line-height: 1.45;
        }
        body,
        table,
        th,
        td,
        h1,
        h2,
        h3,
        p,
        span {
            font-family: "DejaVu Sans", Helvetica, Arial, sans-serif;
        }
        h1, h2, h3, p { margin: 0; }
        .muted { color: #6b7280; }
        .header {
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 18px;
            padding-bottom: 14px;
        }
        .title { font-size: 22px; font-weight: 700; }
        .store { margin-top: 4px; font-size: 12px; font-weight: 700; }
        .period { margin-top: 3px; color: #6b7280; }
        .grid { width: 100%; border-collapse: separate; border-spacing: 8px; margin: 0 -8px 12px; }
        .grid.two .card { width: 50%; }
        .card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 10px;
            width: 25%;
        }
        .label { color: #6b7280; font-size: 10px; }
        .value { margin-top: 5px; font-size: 15px; font-weight: 700; }
        .note {
            color: #6b7280;
            font-size: 9px;
            margin-top: 4px;
        }
        .section {
            margin-top: 12px;
            padding-top: 0;
        }
        .page-break {
            page-break-before: always;
            padding-top: 12mm;
        }
        .section-title { margin-bottom: 7px; font-size: 13px; font-weight: 700; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th {
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 9px;
            font-weight: 700;
            padding: 7px;
            text-align: left;
            text-transform: uppercase;
        }
        table.data td {
            border-bottom: 1px solid #f3f4f6;
            padding: 7px;
            vertical-align: top;
        }
        .right { text-align: right; }
        .center { text-align: center; }
        .strong { font-weight: 700; }
        .success { color: #039855; }
        .error { color: #d92d20; }
        .footer {
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 9px;
        }
        .page-footer {
            position: fixed;
            right: 0;
            bottom: -14mm;
            left: 0;
            padding-top: 4mm;
            border-top: 1px solid #e5e7eb;
        }
        .page-number::after {
            content: counter(page);
        }
        .paper {
            padding: 12mm 8mm 8mm;
        }
        .stock-layout {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 12px;
        }
        .stock-layout td {
            vertical-align: top;
        }
    </style>
</head>
<body>
    <div class="paper">
        <div class="header">
            <h1 class="title">Laporan Operasional</h1>
            <p class="store">{{ $store->store_name }}</p>
            <p class="period">
                Cabang {{ $activeBranch->name }} · Periode {{ $period['from']->format('d M Y') }} sampai {{ $period['to']->format('d M Y') }}
                · Dicetak {{ now()->format('d M Y H:i') }}
            </p>
        </div>

    <table class="grid">
        <tr>
            <td class="card">
                <p class="label">Nilai Stok Jual</p>
                <p class="value">{{ $rupiah($summary['retail_value']) }}</p>
            </td>
            <td class="card">
                <p class="label">Nilai Modal Stok</p>
                <p class="value">{{ $rupiah($summary['inventory_value']) }}</p>
            </td>
            <td class="card">
                <p class="label">Pendapatan POS</p>
                <p class="value">{{ $rupiah($summary['pos_revenue']) }}</p>
            </td>
            <td class="card">
                <p class="label">Stok Masuk / Keluar</p>
                <p class="value">+{{ number_format($summary['stock_in'], 0, ',', '.') }} / -{{ number_format($summary['stock_out'], 0, ',', '.') }}</p>
            </td>
        </tr>
    </table>

    <table class="grid two">
        <tr>
            <td class="card">
                <p class="label">Estimasi Laba/Rugi</p>
                <p class="value {{ $summary['net_profit_estimate'] >= 0 ? 'success' : 'error' }}">{{ $rupiah($summary['net_profit_estimate']) }}</p>
                <p class="note">Pendapatan POS + billing lunas - HPP - total pengeluaran.</p>
            </td>
            <td class="card">
                <p class="label">Total Pengeluaran</p>
                <p class="value error">{{ $rupiah($summary['total_expense']) }}</p>
                <p class="note">Restok {{ $rupiah($summary['restock_expense']) }} · Operasional {{ $rupiah($summary['operational_expense']) }}.</p>
            </td>
        </tr>
        <tr>
            <td class="card">
                <p class="label">Laba Kotor</p>
                <p class="value {{ $summary['gross_profit'] >= 0 ? 'success' : 'error' }}">{{ $rupiah($summary['gross_profit']) }}</p>
                <p class="note">Pendapatan POS + billing lunas - HPP.</p>
            </td>
            <td class="card">
                <p class="label">HPP Terjual</p>
                <p class="value">{{ $rupiah($summary['cost_of_goods_sold']) }}</p>
                <p class="note">Estimasi dari stok keluar x harga beli produk.</p>
            </td>
        </tr>
    </table>

    <div class="section">
        <h2 class="section-title">Pendapatan per Kasir</h2>
        <table class="data">
            <thead>
                <tr>
                    <th>Kasir</th>
                    <th class="right">Transaksi</th>
                    <th class="right">Kotor</th>
                    <th class="right">Diskon</th>
                    <th class="right">Pendapatan</th>
                    <th class="right">Tunai</th>
                    <th class="right">QRIS</th>
                    <th class="right">Kartu</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($cashierRevenues as $cashier)
                    <tr>
                        <td>
                            <p class="strong">{{ $cashier['cashier'] }}</p>
                            <p class="muted">{{ $cashier['email'] }}</p>
                        </td>
                        <td class="right strong">{{ number_format($cashier['sales_count'], 0, ',', '.') }}</td>
                        <td class="right">{{ $rupiah($cashier['gross_sales']) }}</td>
                        <td class="right">{{ $rupiah($cashier['discount_total']) }}</td>
                        <td class="right strong">{{ $rupiah($cashier['net_sales']) }}</td>
                        <td class="right">{{ $rupiah($cashier['cash_total']) }}</td>
                        <td class="right">{{ $rupiah($cashier['qris_total']) }}</td>
                        <td class="right">{{ $rupiah($cashier['card_total']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="center muted">Belum ada transaksi POS dalam periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="section page-break">
        <table class="stock-layout">
            <tr>
                <td>
                    <h2 class="section-title">Produk Stok Terbesar</h2>
                    <table class="data">
                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th>SKU</th>
                                <th>Kategori</th>
                                <th class="right">Stok</th>
                                <th class="right">Nilai Jual</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($topProducts as $product)
                                <tr>
                                    <td class="strong">{{ $product->name }}</td>
                                    <td>{{ $product->sku }}</td>
                                    <td>{{ $product->category?->name ?? 'Umum' }}</td>
                                    <td class="right">{{ number_format($product->stock, 0, ',', '.') }}</td>
                                    <td class="right strong">{{ $rupiah($product->stock * $product->sell_price) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="center muted">Belum ada produk.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </td>
            </tr>
            <tr>
                <td>
                    <h2 class="section-title">Stok Perlu Perhatian</h2>
                    <table class="data">
                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th>SKU</th>
                                <th class="right">Stok</th>
                                <th class="right">Minimum</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($lowStockProducts as $product)
                                <tr>
                                    <td class="strong">{{ $product->name }}</td>
                                    <td>{{ $product->sku }}</td>
                                    <td class="right error strong">{{ number_format($product->stock, 0, ',', '.') }}</td>
                                    <td class="right">{{ number_format($product->min_stock, 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="center muted">Tidak ada stok menipis.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    </div>
    <div class="footer page-footer">
        Dokumen ini dibuat otomatis dari sistem Mava Backend.
        <span style="float: right;">Halaman <span class="page-number"></span></span>
    </div>
</body>
</html>
