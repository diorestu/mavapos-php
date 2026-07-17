<!doctype html><html><head><meta charset="utf-8"><title>Laporan Laba Rugi</title><style>body{font-family:DejaVu Sans,sans-serif;font-size:12px;color:#222}h1{font-size:20px;margin-bottom:4px}.muted{color:#666}.row{display:flex;justify-content:space-between;border-bottom:1px solid #ccc;padding:9px 0}.total{font-weight:bold;border-top:2px solid #222;border-bottom:2px solid #222}.section{margin-top:20px;font-weight:bold}</style></head><body>
<h1>Laporan Laba Rugi</h1><div class="muted">{{ $store->store_name }} · {{ $activeBranch->name }} · {{ $filters['date_from'] }} s/d {{ $filters['date_to'] }}</div>
<div class="section">Pendapatan dan Beban</div>
<div class="row"><span>Pendapatan Penjualan POS</span><span>Rp{{ number_format($summary['revenue'],0,',','.') }}</span></div>
<div class="row"><span>HPP</span><span>(Rp{{ number_format($summary['cogs'],0,',','.') }})</span></div>
<div class="row total"><span>Laba Kotor</span><span>Rp{{ number_format($summary['grossProfit'],0,',','.') }}</span></div>
<div class="row"><span>Total Pengeluaran</span><span>(Rp{{ number_format($summary['expenseTotal'],0,',','.') }})</span></div>
<div class="row total"><span>Laba / (Rugi) Bersih</span><span>Rp{{ number_format($summary['netProfit'],0,',','.') }}</span></div>
</body></html>
