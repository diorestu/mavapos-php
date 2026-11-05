@extends('layouts.app')

@section('content')
    @php($rupiah = fn ($value) => 'Rp '.number_format($value, 0, ',', '.'))
    <div class="mx-auto max-w-3xl space-y-5 text-gray-800 dark:text-gray-200"
         x-data="{ lines: @js($lines), quantities: @js(array_replace($lines->mapWithKeys(fn ($line) => [$line['id'] => 0])->all(), collect(old('items', []))->mapWithKeys(fn ($line) => [$line['pos_sale_item_id'] ?? '' => $line['quantity'] ?? 0])->all())),
            get amount() { return this.lines.reduce((sum, line) => { const qty = Math.max(0, Math.min(line.sold - line.returned, Number(this.quantities[line.id] || 0))); return sum + Math.floor(line.cap * (line.returned + qty) / line.sold) - Math.floor(line.cap * line.returned / line.sold); }, 0); } }">
        <div>
            <a href="{{ route('sales') }}" class="text-sm font-semibold text-brand-600">Kembali ke penjualan</a>
            <h1 class="mt-2 text-xl font-semibold">Retur / Refund {{ $sale->invoice_number }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Total transaksi {{ $rupiah($sale->total) }} · Refund tercatat {{ $rupiah($sale->refunds->sum('amount')) }}</p>
        </div>
        @if (session('success'))
            <p role="status" class="rounded-lg bg-success-50 p-3 text-sm text-success-700 dark:bg-success-500/10 dark:text-success-300">{{ session('success') }}</p>
        @endif
        @if ($errors->any())
            <div role="alert" class="rounded-lg bg-error-50 p-3 text-sm text-error-700 dark:bg-error-500/10 dark:text-error-300">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif
        @if (! $sale->voided_at && $lines->contains(fn ($line) => $line['sold'] > $line['returned']))
            <form method="POST" action="{{ route('sales.refund.store', $sale) }}" class="space-y-4 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                @csrf
                <input type="hidden" name="client_refund_id" value="{{ old('client_refund_id', $clientRefundId) }}">
                <p class="text-sm text-gray-500 dark:text-gray-400">Pilih jumlah yang dikembalikan. Nilai refund mengikuti harga bersih setelah diskon dan potongan merchant. Transaksi asli tetap tersimpan.</p>
                @foreach ($sale->items as $index => $item)
                    @php($remaining = $item->quantity - $item->refundItems->sum('quantity'))
                    @if ($remaining > 0)
                        <div class="grid gap-3 border-b border-gray-100 pb-4 sm:grid-cols-[1fr_100px] dark:border-gray-800">
                            <div>
                                <p class="text-sm font-semibold">{{ $item->name }}</p>
                                <p class="mt-1 text-xs text-gray-500">{{ $item->sku }} · Terjual {{ $item->quantity }} · Sudah retur {{ $item->refundItems->sum('quantity') }} · Tersisa {{ $remaining }}</p>
                                <input type="hidden" name="items[{{ $index }}][pos_sale_item_id]" value="{{ $item->id }}">
                                <input type="hidden" name="items[{{ $index }}][restock]" value="0">
                                @if ($item->product?->stock_mode === 'inventory' && (! $item->product_variant_id || $item->productVariant))
                                    <label class="mt-3 flex items-center gap-2 text-sm"><input type="checkbox" name="items[{{ $index }}][restock]" value="1" @checked(old("items.$index.restock")) class="rounded border-gray-300">Kembalikan ke stok layak jual</label>
                                @else
                                    <p class="mt-2 text-xs text-gray-500">Stok bahan resep tidak dikembalikan.</p>
                                @endif
                            </div>
                            <label class="block text-xs font-medium">Jumlah retur
                                <input type="number" min="0" max="{{ $remaining }}" step="1" required name="items[{{ $index }}][quantity]" value="{{ old("items.$index.quantity", 0) }}" x-model.number="quantities[{{ $item->id }}]" class="mt-1 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700">
                            </label>
                        </div>
                    @endif
                @endforeach
                <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800" aria-live="polite">
                    <p class="text-sm">Jumlah uang dikembalikan</p>
                    <p class="mt-1 text-xl font-semibold tabular-nums" x-text="'Rp ' + amount.toLocaleString('id-ID')">Rp 0</p>
                </div>
                <label class="block text-sm font-medium">Metode pengembalian aktual
                    <select name="refund_method" required class="mt-1 h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-900">
                        @foreach ($methods as $value => $label)<option value="{{ $value }}" @selected(old('refund_method', $sale->payment_method === 'online_merchant' ? 'bank_transfer' : 'cash') === $value)>{{ $label }}</option>@endforeach
                    </select>
                </label>
                <label class="block text-sm font-medium">Alasan retur / refund
                    <textarea name="reason" required maxlength="500" rows="3" class="mt-1 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700">{{ old('reason') }}</textarea>
                </label>
                <p class="text-xs text-gray-500 dark:text-gray-400">Pengembalian uang dilakukan manual. Pencatatan ini tidak mengirim uang atau memanggil payment gateway.</p>
                <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="money_returned" value="1" required class="mt-0.5 rounded border-gray-300">Saya telah mengembalikan uang sesuai jumlah dan metode di atas (atau retur tanpa uang jika Rp 0).</label>
                <button type="submit" class="h-11 rounded-lg bg-brand-500 px-4 text-sm font-semibold text-white hover:bg-brand-600">Catat refund</button>
            </form>
        @else
            <p class="rounded-lg border border-gray-200 p-4 text-sm dark:border-gray-800">{{ $sale->voided_at ? 'Transaksi dibatalkan dan tidak dapat direfund.' : 'Seluruh item sudah diretur.' }}</p>
        @endif
        <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <h2 class="text-base font-semibold">Riwayat refund</h2>
            @forelse ($sale->refunds->sortByDesc('id') as $refund)
                <article class="mt-4 border-t border-gray-100 pt-3 dark:border-gray-800">
                    <div class="flex flex-wrap justify-between gap-2"><p class="break-all text-sm font-semibold">{{ $refund->refund_number }}</p><p class="text-sm font-semibold">{{ $rupiah($refund->amount) }}</p></div>
                    <p class="mt-1 text-xs text-gray-500">@localtime($refund->refunded_at) · {{ $refund->user?->name }} · {{ $methods[$refund->refund_method] ?? $refund->refund_method }}</p>
                    <p class="mt-2 text-sm">{{ $refund->reason }}</p>
                    @foreach ($refund->items as $line)<p class="mt-1 text-xs text-gray-500">{{ $line->quantity }} × {{ $line->saleItem?->name }} · {{ $rupiah($line->amount) }} · {{ $line->restock ? 'Masuk stok' : 'Tanpa pengembalian stok' }}</p>@endforeach
                </article>
            @empty
                <p class="mt-3 text-sm text-gray-500">Belum ada refund untuk transaksi ini.</p>
            @endforelse
        </section>
    </div>
@endsection
