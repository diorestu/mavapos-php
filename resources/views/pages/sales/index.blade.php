@extends('layouts.app')

@php
    $rupiah = fn ($value) => 'Rp'.number_format((int) $value, 0, ',', '.');
    $methodLabel = [
        'cash' => 'Tunai',
        'qris' => 'QRIS',
        'card' => 'Kartu',
    ];
@endphp

@section('content')
    <div class="space-y-4">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <nav aria-label="Breadcrumb">
                    <ol class="flex items-center gap-1 text-[10px] text-gray-500 dark:text-gray-400">
                        <li><a href="{{ url('/') }}" class="transition hover:text-gray-700 dark:hover:text-gray-200">Home</a></li>
                        <li aria-hidden="true">
                            <svg class="h-3 w-3 stroke-current" viewBox="0 0 17 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M6.0765 12.667L10.2432 8.50033L6.0765 4.33366" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </li>
                        <li class="font-medium text-gray-700 dark:text-gray-300">Penjualan</li>
                    </ol>
                </nav>
                <h1 class="mt-1 text-xl font-semibold text-gray-800 dark:text-white/90">Penjualan</h1>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Daftar transaksi POS, detail item, metode pembayaran, dan kasir yang melayani.</p>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('pos') }}" class="inline-flex h-10 items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-semibold text-white shadow-theme-xs transition hover:bg-brand-600">
                    Buka Kasir
                </a>
                <a href="{{ route('cashier-shifts') }}" class="inline-flex h-10 items-center justify-center rounded-lg border border-gray-200 px-4 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.04]">
                    Shift Kasir
                </a>
            </div>
        </div>

        <form method="GET" action="{{ route('sales') }}" class="grid gap-2 rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-white/[0.03] md:grid-cols-2 xl:grid-cols-[140px_140px_170px_150px_minmax(180px,1fr)_auto_auto]">
            <label class="block">
                <span class="mb-1 block text-[11px] font-medium text-gray-500 dark:text-gray-400">Dari</span>
                <input type="date" name="date_from" value="{{ $filters['date_from'] }}"
                    class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-xs text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
            </label>
            <label class="block">
                <span class="mb-1 block text-[11px] font-medium text-gray-500 dark:text-gray-400">Sampai</span>
                <input type="date" name="date_to" value="{{ $filters['date_to'] }}"
                    class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-xs text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
            </label>
            <label class="block">
                <span class="mb-1 block text-[11px] font-medium text-gray-500 dark:text-gray-400">Kasir</span>
                <select name="cashier_id" class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-xs text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    <option value="">Semua kasir</option>
                    @foreach ($cashiers as $cashier)
                        <option value="{{ $cashier->id }}" @selected((string) $filters['cashier_id'] === (string) $cashier->id)>{{ $cashier->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="mb-1 block text-[11px] font-medium text-gray-500 dark:text-gray-400">Pembayaran</span>
                <select name="payment_method" class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-xs text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    <option value="">Semua metode</option>
                    @foreach ($methodLabel as $value => $label)
                        <option value="{{ $value }}" @selected($filters['payment_method'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="mb-1 block text-[11px] font-medium text-gray-500 dark:text-gray-400">Cari invoice / produk</span>
                <input type="search" name="search" value="{{ $filters['search'] }}" placeholder="POS-..., SKU, nama produk"
                    class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-xs text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
            </label>
            <button type="submit" class="self-end inline-flex h-9 items-center justify-center rounded-lg border border-gray-200 px-3 text-xs font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.04]">
                Terapkan
            </button>
            <a href="{{ route('sales') }}" class="self-end inline-flex h-9 items-center justify-center rounded-lg px-3 text-xs font-semibold text-gray-500 transition hover:bg-gray-50 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/[0.04]">
                Reset
            </a>
        </form>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Penjualan</p>
                <p class="mt-2 text-xl font-semibold tabular-nums text-gray-900 dark:text-white">{{ $rupiah($summary['net_sales']) }}</p>
                <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">{{ number_format($summary['sales_count'], 0, ',', '.') }} transaksi dalam periode</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Penjualan Kotor</p>
                <p class="mt-2 text-xl font-semibold tabular-nums text-gray-900 dark:text-white">{{ $rupiah($summary['gross_sales']) }}</p>
                <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">Sebelum diskon transaksi</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Diskon</p>
                <p class="mt-2 text-xl font-semibold tabular-nums text-error-600 dark:text-error-400">{{ $rupiah($summary['discount_total']) }}</p>
                <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">Potongan dari semua invoice</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Metode Pembayaran</p>
                <p class="mt-2 text-sm font-semibold tabular-nums text-gray-900 dark:text-white">Tunai {{ $rupiah($summary['cash_total']) }}</p>
                <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">QRIS {{ $rupiah($summary['qris_total']) }} · Kartu {{ $rupiah($summary['card_total']) }}</p>
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex flex-col gap-1 border-b border-gray-100 px-4 py-3 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Daftar Invoice</h2>
                    <p class="text-[11px] text-gray-500 dark:text-gray-400">Klik baris detail untuk melihat item yang dibeli.</p>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Menampilkan {{ number_format($sales->firstItem() ?? 0, 0, ',', '.') }}-{{ number_format($sales->lastItem() ?? 0, 0, ',', '.') }}
                    dari {{ number_format($sales->total(), 0, ',', '.') }}
                </p>
            </div>
            <div class="max-w-full overflow-x-auto custom-scrollbar">
                <table class="w-full min-w-[980px]">
                    <thead>
                        <tr class="bg-gray-50 text-left dark:bg-gray-900/40">
                            <th class="px-4 py-2 text-[11px] font-semibold uppercase text-gray-500">Invoice</th>
                            <th class="px-4 py-2 text-[11px] font-semibold uppercase text-gray-500">Waktu</th>
                            <th class="px-4 py-2 text-[11px] font-semibold uppercase text-gray-500">Kasir</th>
                            <th class="px-4 py-2 text-center text-[11px] font-semibold uppercase text-gray-500">Item</th>
                            <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase text-gray-500">Subtotal</th>
                            <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase text-gray-500">Diskon</th>
                            <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase text-gray-500">Total</th>
                            <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase text-gray-500">Bayar</th>
                            <th class="px-4 py-2 text-center text-[11px] font-semibold uppercase text-gray-500">Metode</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sales as $sale)
                            <tr class="border-t border-gray-100 align-top dark:border-gray-800">
                                <td class="px-4 py-3">
                                    <details>
                                        <summary class="cursor-pointer list-none">
                                            <span class="text-[13px] font-semibold text-brand-600 dark:text-brand-400">{{ $sale->invoice_number }}</span>
                                            <span class="mt-1 block text-[11px] text-gray-500 dark:text-gray-400">Shift #{{ $sale->cashier_shift_id }}</span>
                                        </summary>
                                        <div class="mt-3 w-[520px] max-w-[calc(100vw-4rem)] rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-gray-900/60">
                                            <p class="mb-2 text-xs font-semibold text-gray-700 dark:text-gray-300">Detail Item</p>
                                            <div class="space-y-2">
                                                @foreach ($sale->items as $item)
                                                    <div class="flex items-start justify-between gap-3 border-t border-gray-200 pt-2 first:border-t-0 first:pt-0 dark:border-gray-800">
                                                        <div class="min-w-0">
                                                            <p class="truncate text-xs font-semibold text-gray-800 dark:text-white/90">{{ $item->name }}</p>
                                                            <p class="text-[11px] text-gray-500 dark:text-gray-400">{{ $item->sku }} · {{ $item->quantity }} x {{ $rupiah($item->unit_price) }}</p>
                                                        </div>
                                                        <p class="shrink-0 text-xs font-semibold tabular-nums text-gray-800 dark:text-white/90">{{ $rupiah($item->line_total) }}</p>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </details>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">{{ $sale->sold_at?->format('d M Y H:i') }}</td>
                                <td class="px-4 py-3">
                                    <p class="text-xs font-semibold text-gray-800 dark:text-white/90">{{ $sale->user?->name ?? 'Kasir' }}</p>
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400">{{ $sale->user?->email }}</p>
                                </td>
                                <td class="px-4 py-3 text-center text-xs font-semibold tabular-nums text-gray-800 dark:text-white/90">{{ number_format($sale->items->sum('quantity'), 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-xs tabular-nums text-gray-600 dark:text-gray-300">{{ $rupiah($sale->subtotal) }}</td>
                                <td class="px-4 py-3 text-right text-xs tabular-nums text-error-600 dark:text-error-400">{{ $rupiah($sale->discount) }}</td>
                                <td class="px-4 py-3 text-right text-xs font-semibold tabular-nums text-gray-900 dark:text-white">{{ $rupiah($sale->total) }}</td>
                                <td class="px-4 py-3 text-right text-xs tabular-nums text-gray-600 dark:text-gray-300">
                                    {{ $rupiah($sale->paid_amount) }}
                                    @if ($sale->change_amount > 0)
                                        <span class="block text-[11px] text-success-600 dark:text-success-400">Kembali {{ $rupiah($sale->change_amount) }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="rounded-full bg-gray-100 px-2 py-1 text-[11px] font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                        {{ $methodLabel[$sale->payment_method] ?? strtoupper($sale->payment_method) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-4 py-10 text-center text-sm text-gray-500">Belum ada transaksi penjualan dalam filter ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-100 px-4 py-3 dark:border-gray-800">
                {{ $sales->links() }}
            </div>
        </section>
    </div>
@endsection
