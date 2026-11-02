<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Bonus Harian {{ $month->format('Y-m') }}</title>
    <style>
        @page { margin: 30px 36px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #222; }
        h1 { font-size: 18px; margin: 0 0 8px; }
        h2 { font-size: 14px; margin: 0 0 8px; }
        p { margin: 4px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        th, td { border: 1px solid #ccc; padding: 5px 8px; text-align: left; overflow-wrap: break-word; }
        th { background: #f0f0f0; }
        .amount { text-align: right; white-space: nowrap; }
        .total { font-weight: bold; background: #f0f0f0; }
        .employee { page-break-before: always; }
        tr { page-break-inside: avoid; }
        thead { display: table-header-group; }
        .note { color: #555; margin-top: 12px; }
    </style>
</head>
<body>
    @php $rupiah = fn ($value) => 'Rp'.number_format((int) $value, 0, ',', '.'); @endphp
    <h1>Laporan Bonus Harian per Orang</h1>
    <p>Kios: <strong>{{ $activeBranch->name }}</strong></p>
    <p>Periode: {{ $month->copy()->startOfMonth()->format('d/m/Y') }} - {{ $month->copy()->endOfMonth()->format('d/m/Y') }}</p>
    <table>
        <thead><tr><th>Karyawan</th><th class="amount">Total Bonus Bulanan</th></tr></thead>
        <tbody>
            @forelse($users as $user)
                <tr><td>{{ $user->name }}</td><td class="amount">{{ $rupiah($bonusTable['totals']->get($user->id, 0)) }}</td></tr>
            @empty
                <tr><td colspan="2">Belum ada staf kasir.</td></tr>
            @endforelse
            <tr class="total"><td>Total bonus kios</td><td class="amount">{{ $rupiah($bonusTable['bonusTotal']) }}</td></tr>
        </tbody>
    </table>
    <p class="note">Bonus mengikuti target penjualan dan kehadiran tim pada kios ini, sesuai tampilan Bonus Harian per Orang. Nominal Rp0 berarti tidak ada bonus pada tanggal tersebut.</p>

    @foreach($users as $user)
        <div class="employee">
            <h2>{{ $user->name }}</h2>
            <p>Kios: {{ $activeBranch->name }} | Bulan: {{ $month->format('m/Y') }}</p>
            <table>
                <thead><tr><th>Tanggal</th><th class="amount">Bonus Harian</th></tr></thead>
                <tbody>
                    @foreach($bonusTable['rows'] as $row)
                        <tr><td>{{ $row['date']->format('d/m/Y') }}</td><td class="amount">{{ $rupiah($row['values']->get($user->id, 0)) }}</td></tr>
                    @endforeach
                    <tr class="total"><td>Total bulan</td><td class="amount">{{ $rupiah($bonusTable['totals']->get($user->id, 0)) }}</td></tr>
                </tbody>
            </table>
        </div>
    @endforeach
</body>
</html>
