@extends('layouts.app')

@php
    $rupiah = fn ($value) => 'Rp'.number_format((int) $value, 0, ',', '.');
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
                        <li class="font-medium text-gray-700 dark:text-gray-300">Laporan</li>
                    </ol>
                </nav>
                <h1 class="mt-1 text-xl font-semibold text-gray-800 dark:text-white/90">Laporan</h1>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Ringkasan stok, nilai inventori, pendapatan POS, billing, dan pergerakan barang.
                    <span class="font-semibold text-gray-700 dark:text-gray-300">Cabang aktif: {{ $activeBranch->name }}</span>
                </p>
            </div>

            <form method="GET" action="{{ route('reports') }}" class="grid gap-2 rounded-xl border border-gray-200 bg-white p-2 dark:border-gray-800 dark:bg-white/[0.03] sm:grid-cols-[150px_150px_auto_auto]">
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
                <button type="submit" class="self-end inline-flex h-9 items-center justify-center rounded-lg border border-gray-200 px-3 text-xs font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.04]">
                    Terapkan
                </button>
                <a href="{{ route('reports.download', request()->query()) }}" class="self-end inline-flex h-9 items-center justify-center rounded-lg bg-brand-500 px-3 text-xs font-semibold text-white shadow-theme-xs transition hover:bg-brand-600">
                    Unduh PDF
                </a>
                <a href="{{ route('reports.excel', request()->query()) }}" class="self-end inline-flex h-9 items-center justify-center rounded-lg border border-success-200 px-3 text-xs font-semibold text-success-700 transition hover:bg-success-50 dark:border-success-500/30 dark:text-success-400 dark:hover:bg-success-500/10">
                    Export Excel
                </a>
                <a href="{{ route('reports.journal', request()->query()) }}" class="self-end inline-flex h-9 items-center justify-center rounded-lg border border-brand-200 px-3 text-xs font-semibold text-brand-600 transition hover:bg-brand-50 dark:border-brand-500/30 dark:hover:bg-brand-500/10">
                    Jurnal Akunting
                </a>
                <a href="{{ route('reports.financial.download', request()->query()) }}" class="self-end inline-flex h-9 items-center justify-center rounded-lg border border-gray-200 px-3 text-xs font-semibold text-gray-700 dark:border-gray-800 dark:text-gray-300">Download Keuangan</a>
                <a href="{{ route('reports.profit-loss.download', request()->query()) }}" class="self-end inline-flex h-9 items-center justify-center rounded-lg border border-gray-200 px-3 text-xs font-semibold text-gray-700 dark:border-gray-800 dark:text-gray-300">Download Laba Rugi</a>
            </form>
        </div>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Nilai Stok Jual</p>
                <p class="mt-2 text-xl font-semibold tabular-nums text-gray-900 dark:text-white">{{ $rupiah($summary['retail_value']) }}</p>
                <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">{{ number_format($summary['products'], 0, ',', '.') }} produk aktif di laporan</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Nilai Modal Stok</p>
                <p class="mt-2 text-xl font-semibold tabular-nums text-gray-900 dark:text-white">{{ $rupiah($summary['inventory_value']) }}</p>
                <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">Berdasarkan harga beli produk</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Pendapatan POS</p>
                <p class="mt-2 text-xl font-semibold tabular-nums text-gray-900 dark:text-white">{{ $rupiah($summary['pos_revenue']) }}</p>
                <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">Total penjualan kasir dalam periode</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Pergerakan Stok</p>
                <p class="mt-2 text-xl font-semibold tabular-nums text-gray-900 dark:text-white">+{{ number_format($summary['stock_in'], 0, ',', '.') }} / -{{ number_format($summary['stock_out'], 0, ',', '.') }}</p>
                <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">{{ number_format($summary['low_stock'], 0, ',', '.') }} produk perlu perhatian</p>
            </div>
        </section>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Estimasi Laba/Rugi</p>
                <p class="mt-2 text-xl font-semibold tabular-nums {{ $summary['net_profit_estimate'] >= 0 ? 'text-success-600 dark:text-success-400' : 'text-error-600 dark:text-error-400' }}">{{ $rupiah($summary['net_profit_estimate']) }}</p>
                <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">Pendapatan POS + billing lunas - HPP - pengeluaran</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Pengeluaran</p>
                <p class="mt-2 text-xl font-semibold tabular-nums text-error-600 dark:text-error-400">{{ $rupiah($summary['total_expense']) }}</p>
                <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">Restok {{ $rupiah($summary['restock_expense']) }} · Operasional {{ $rupiah($summary['operational_expense']) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Laba Kotor</p>
                <p class="mt-2 text-xl font-semibold tabular-nums {{ $summary['gross_profit'] >= 0 ? 'text-success-600 dark:text-success-400' : 'text-error-600 dark:text-error-400' }}">{{ $rupiah($summary['gross_profit']) }}</p>
                <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">Pendapatan POS + billing lunas - HPP</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">HPP Terjual</p>
                <p class="mt-2 text-xl font-semibold tabular-nums text-gray-900 dark:text-white">{{ $rupiah($summary['cost_of_goods_sold']) }}</p>
                <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">Stok keluar x harga beli</p>
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex flex-col gap-1 border-b border-gray-100 px-4 py-3 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Pendapatan per Kasir</h2>
                    <p class="text-[11px] text-gray-500 dark:text-gray-400">Berdasarkan transaksi POS dalam periode filter laporan.</p>
                </div>
                <div class="text-xs font-semibold tabular-nums text-gray-800 dark:text-white/90">
                    Total {{ $rupiah($summary['pos_revenue']) }}
                </div>
            </div>
            <div class="max-w-full overflow-x-auto custom-scrollbar">
                <table class="w-full min-w-[760px]">
                    <thead>
                        <tr class="bg-gray-50 text-left dark:bg-gray-900/40">
                            <th class="px-4 py-2 text-[11px] font-semibold uppercase text-gray-500">Kasir</th>
                            <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase text-gray-500">Transaksi</th>
                            <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase text-gray-500">Kotor</th>
                            <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase text-gray-500">Diskon</th>
                            <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase text-gray-500">Pendapatan</th>
                            <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase text-gray-500">Tunai</th>
                            <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase text-gray-500">QRIS</th>
                            <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase text-gray-500">Kartu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($cashierRevenues as $cashier)
                            <tr class="border-t border-gray-100 dark:border-gray-800">
                                <td class="px-4 py-3">
                                    <p class="text-[13px] font-semibold text-gray-800 dark:text-white/90">{{ $cashier['cashier'] }}</p>
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400">{{ $cashier['email'] }}</p>
                                </td>
                                <td class="px-4 py-3 text-right text-xs font-semibold tabular-nums text-gray-800 dark:text-white/90">{{ number_format($cashier['sales_count'], 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-xs tabular-nums text-gray-600 dark:text-gray-300">{{ $rupiah($cashier['gross_sales']) }}</td>
                                <td class="px-4 py-3 text-right text-xs tabular-nums text-gray-600 dark:text-gray-300">{{ $rupiah($cashier['discount_total']) }}</td>
                                <td class="px-4 py-3 text-right text-xs font-semibold tabular-nums text-gray-800 dark:text-white/90">{{ $rupiah($cashier['net_sales']) }}</td>
                                <td class="px-4 py-3 text-right text-xs tabular-nums text-gray-600 dark:text-gray-300">{{ $rupiah($cashier['cash_total']) }}</td>
                                <td class="px-4 py-3 text-right text-xs tabular-nums text-gray-600 dark:text-gray-300">{{ $rupiah($cashier['qris_total']) }}</td>
                                <td class="px-4 py-3 text-right text-xs tabular-nums text-gray-600 dark:text-gray-300">{{ $rupiah($cashier['card_total']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-8 text-center text-sm text-gray-500">Belum ada transaksi POS dalam periode ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-2">
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                    <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Produk Stok Terbesar</h2>
                </div>
                <div class="max-w-full overflow-x-auto custom-scrollbar">
                    <table class="w-full min-w-[560px]">
                        <thead>
                            <tr class="bg-gray-50 text-left dark:bg-gray-900/40">
                                <th class="px-4 py-2 text-[11px] font-semibold uppercase text-gray-500">Produk</th>
                                <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase text-gray-500">Stok</th>
                                <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase text-gray-500">Nilai Jual</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($topProducts as $product)
                                <tr class="border-t border-gray-100 dark:border-gray-800">
                                    <td class="px-4 py-2">
                                        <p class="text-[13px] font-semibold text-gray-800 dark:text-white/90">{{ $product->name }}</p>
                                        <p class="text-[11px] text-gray-500 dark:text-gray-400">{{ $product->sku }} · {{ $product->category?->name ?? 'Umum' }}</p>
                                    </td>
                                    <td class="px-4 py-2 text-right text-xs font-semibold tabular-nums text-gray-800 dark:text-white/90">{{ number_format($product->stock, 0, ',', '.') }}</td>
                                    <td class="px-4 py-2 text-right text-xs font-semibold tabular-nums text-gray-800 dark:text-white/90">{{ $rupiah($product->stock * $product->sell_price) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-4 py-8 text-center text-sm text-gray-500">Belum ada produk.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                    <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Stok Perlu Perhatian</h2>
                </div>
                <div class="max-w-full overflow-x-auto custom-scrollbar">
                    <table class="w-full min-w-[520px]">
                        <thead>
                            <tr class="bg-gray-50 text-left dark:bg-gray-900/40">
                                <th class="px-4 py-2 text-[11px] font-semibold uppercase text-gray-500">Produk</th>
                                <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase text-gray-500">Stok</th>
                                <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase text-gray-500">Minimum</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($lowStockProducts as $product)
                                <tr class="border-t border-gray-100 dark:border-gray-800">
                                    <td class="px-4 py-2">
                                        <p class="text-[13px] font-semibold text-gray-800 dark:text-white/90">{{ $product->name }}</p>
                                        <p class="text-[11px] text-gray-500 dark:text-gray-400">{{ $product->sku }}</p>
                                    </td>
                                    <td class="px-4 py-2 text-right text-xs font-semibold tabular-nums text-error-600">{{ number_format($product->stock, 0, ',', '.') }}</td>
                                    <td class="px-4 py-2 text-right text-xs font-semibold tabular-nums text-gray-800 dark:text-white/90">{{ number_format($product->min_stock, 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-4 py-8 text-center text-sm text-gray-500">Tidak ada stok menipis.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

    </div>
@endsection
