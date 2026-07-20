<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: Arial, sans-serif; }
        table { border-collapse: collapse; margin-bottom: 18px; }
        th, td { border: 1px solid #999; padding: 6px 10px; }
        th { background: #e5e7eb; font-weight: bold; }
        .title { font-size: 18px; font-weight: bold; }
        .number { mso-number-format: "#,##0"; }
    </style>
</head>
<body>
    <div class="title">Laporan MavaPOS</div>
    <p>Cabang: {{ $activeBranch->name }} | Periode: {{ $period['from']->format('d/m/Y') }} - {{ $period['to']->format('d/m/Y') }}</p>

    <table>
        <tr><th colspan="2">Ringkasan</th></tr>
        <tr><td>Pendapatan POS</td><td class="number">{{ $summary['pos_revenue'] }}</td></tr>
        <tr><td>Total Pendapatan</td><td class="number">{{ $summary['total_revenue'] }}</td></tr>
        <tr><td>HPP Terjual</td><td class="number">{{ $summary['cost_of_goods_sold'] }}</td></tr>
        <tr><td>Total Pengeluaran</td><td class="number">{{ $summary['total_expense'] }}</td></tr>
        <tr><td>Laba Kotor</td><td class="number">{{ $summary['gross_profit'] }}</td></tr>
        <tr><td>Estimasi Laba/Rugi</td><td class="number">{{ $summary['net_profit_estimate'] }}</td></tr>
    </table>

    <table>
        <tr>
            <th>Kasir</th><th>Email</th><th>Transaksi</th><th>Kotor</th><th>Diskon</th>
            <th>Pendapatan</th><th>Tunai</th><th>QRIS</th><th>Kartu</th>
        </tr>
        @foreach ($cashierRevenues as $cashier)
            <tr>
                <td>{{ $cashier['cashier'] }}</td><td>{{ $cashier['email'] }}</td>
                <td class="number">{{ $cashier['sales_count'] }}</td><td class="number">{{ $cashier['gross_sales'] }}</td>
                <td class="number">{{ $cashier['discount_total'] }}</td><td class="number">{{ $cashier['net_sales'] }}</td>
                <td class="number">{{ $cashier['cash_total'] }}</td><td class="number">{{ $cashier['qris_total'] }}</td>
                <td class="number">{{ $cashier['card_total'] }}</td>
            </tr>
        @endforeach
    </table>
</body>
</html>
