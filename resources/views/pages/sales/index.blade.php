@extends('layouts.app')

@php
    $rupiah = fn ($value) => 'Rp'.number_format((int) $value, 0, ',', '.');
    $currentUser = auth()->user();
    $methodLabel = [
        'cash' => 'Tunai',
        'qris' => 'QRIS',
        'card' => 'Kartu',
        'free' => 'Gratis',
    ];
@endphp

@section('content')
    <div x-data="salesVoidManager(@js(url('/sales/__SALE__/void')))" class="space-y-4">
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

        <form method="GET" action="{{ route('sales') }}" class="grid gap-2 rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-white/[0.03] md:grid-cols-2 xl:grid-cols-[260px_170px_150px_minmax(180px,1fr)_auto_auto]">
            <label class="block" x-data="salesDateRange('{{ $filters['date_from'] }}', '{{ $filters['date_to'] }}')" x-init="mount($refs.dateRangeInput)" x-destroy="destroy()">
                <span class="mb-1 block text-[11px] font-medium text-gray-500 dark:text-gray-400">Periode</span>
                <div class="relative">
                    <input x-ref="dateRangeInput" type="text" placeholder="Pilih rentang tanggal" autocomplete="off"
                        class="h-9 w-full rounded-lg border border-gray-300 bg-transparent pl-3 pr-9 text-xs text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                    <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M8 2.75V5.75M16 2.75V5.75M4.75 9.25H19.25M6.5 4.25H17.5C18.4665 4.25 19.25 5.0335 19.25 6V18.5C19.25 19.4665 18.4665 20.25 17.5 20.25H6.5C5.5335 20.25 4.75 19.4665 4.75 18.5V6C4.75 5.0335 5.5335 4.25 6.5 4.25Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>
                </div>
                <input type="hidden" name="date_from" :value="dateFrom" />
                <input type="hidden" name="date_to" :value="dateTo" />
            </label>
            <label class="block">
                <span class="mb-1 block text-[11px] font-medium text-gray-500 dark:text-gray-400">Kasir</span>
                <div class="relative">
                    <select name="cashier_id" class="h-9 w-full appearance-none rounded-lg border border-gray-300 bg-transparent pl-3 pr-9 text-xs text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                        <option value="">Semua kasir</option>
                        @foreach ($cashiers as $cashier)
                            <option value="{{ $cashier->id }}" @selected((string) $filters['cashier_id'] === (string) $cashier->id)>{{ $cashier->name }}</option>
                        @endforeach
                    </select>
                    <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                            <path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>
                </div>
            </label>
            <label class="block">
                <span class="mb-1 block text-[11px] font-medium text-gray-500 dark:text-gray-400">Pembayaran</span>
                <div class="relative">
                    <select name="payment_method" class="h-9 w-full appearance-none rounded-lg border border-gray-300 bg-transparent pl-3 pr-9 text-xs text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                        <option value="">Semua metode</option>
                        @foreach ($methodLabel as $value => $label)
                            <option value="{{ $value }}" @selected($filters['payment_method'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                            <path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>
                </div>
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
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Cup / Item Terjual</p>
                <p class="mt-2 text-xl font-semibold tabular-nums text-gray-900 dark:text-white">{{ $rupiah($summary['net_sales']) }}</p>
                <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">{{ number_format($summary['sales_count'], 0, ',', '.') }} cup/item dalam periode</p>
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

        <section class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-xl border border-brand-200 bg-brand-50/50 p-4 dark:border-brand-500/20 dark:bg-brand-500/10">
                <p class="text-xs font-medium text-brand-700 dark:text-brand-300">Pembeli Local</p>
                <p class="mt-2 text-xl font-semibold tabular-nums text-brand-900 dark:text-brand-100">{{ number_format($summary['local_buyers'], 0, ',', '.') }}</p>
                <p class="mt-1 text-[11px] text-brand-700 dark:text-brand-300">Transaksi pembeli lokal dalam periode</p>
            </div>
            <div class="rounded-xl border border-warning-200 bg-warning-50/50 p-4 dark:border-warning-500/20 dark:bg-warning-500/10">
                <p class="text-xs font-medium text-warning-700 dark:text-warning-300">Pembeli Foreigner</p>
                <p class="mt-2 text-xl font-semibold tabular-nums text-warning-900 dark:text-warning-100">{{ number_format($summary['foreigner_buyers'], 0, ',', '.') }}</p>
                <p class="mt-1 text-[11px] text-warning-700 dark:text-warning-300">Transaksi turis asing dalam periode</p>
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Capaian per Orang</h2>
                <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">Jumlah cup/item pribadi dan bonus berdasarkan target harian cabang.</p>
            </div>
            <div class="max-w-full overflow-x-auto">
                <table class="w-full min-w-[620px]">
                    <thead><tr class="bg-gray-50 text-left dark:bg-gray-900/40"><th class="px-4 py-2 text-[11px] uppercase text-gray-500">Staff</th><th class="px-4 py-2 text-right text-[11px] uppercase text-gray-500">Cup / Item</th><th class="px-4 py-2 text-center text-[11px] uppercase text-gray-500">Status</th><th class="px-4 py-2 text-right text-[11px] uppercase text-gray-500">Bonus</th></tr></thead>
                    <tbody>
                        @forelse ($bonus['staffBreakdown'] as $staffRow)
                            <tr class="border-t border-gray-100 dark:border-gray-800"><td class="px-4 py-3 text-sm font-semibold text-gray-800 dark:text-white/90">{{ $staffRow['name'] }}</td><td class="px-4 py-3 text-right text-sm tabular-nums text-gray-700 dark:text-gray-300">{{ number_format($staffRow['salesCount'], 0, ',', '.') }}</td><td class="px-4 py-3 text-center"><span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $bonus['targetReached'] ? 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300' }}">{{ $bonus['targetReached'] ? 'Target Tercapai' : 'Belum Ada Bonus' }}</span></td><td class="px-4 py-3 text-right text-sm font-semibold tabular-nums text-gray-800 dark:text-white/90">{{ $rupiah($staffRow['bonus']) }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500">Belum ada staff yang bertugas hari ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-xl border {{ $bonus['targetReached'] ? 'border-success-200 bg-success-50 dark:border-success-500/30 dark:bg-success-500/10' : 'border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]' }} p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase {{ $bonus['targetReached'] ? 'text-success-700 dark:text-success-300' : 'text-gray-500 dark:text-gray-400' }}">{{ $bonus['targetReached'] ? 'Target Tercapai' : 'Bonus Staff Harian' }}</p>
                    <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ number_format($bonus['salesCount'], 0, ',', '.') }} cup/item terjual hari ini · {{ number_format($bonus['staffCount'], 0, ',', '.') }} staff bertugas</p>
                    @if ($bonus['staff']->isNotEmpty())
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Berhak menerima: {{ $bonus['staff']->pluck('name')->join(', ') }}</p>
                    @endif
                </div>
                <div class="text-left sm:text-right">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Bonus per orang</p>
                    <p class="mt-1 text-xl font-semibold tabular-nums {{ $bonus['targetReached'] ? 'text-success-700 dark:text-success-300' : 'text-gray-800 dark:text-white/90' }}">{{ $rupiah($bonus['bonusPerPerson']) }}</p>
                </div>
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
                            <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase text-gray-500">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sales as $sale)
                            @php
                                $canVoidSale = ! $sale->voided_at && (
                                    $currentUser?->hasRole(['owner', 'admin'])
                                    || ($currentUser?->hasRole('kasir')
                                        && $sale->user_id === $currentUser->id
                                        && $sale->shift?->closed_at === null)
                                );
                            @endphp
                            <tr class="border-t border-gray-100 align-top dark:border-gray-800">
                                <td class="px-4 py-3">
                                    <details>
                                        <summary class="cursor-pointer list-none">
                                            <span class="text-[13px] font-semibold text-brand-600 dark:text-brand-400">{{ $sale->invoice_number }}</span>
                                            @if ($sale->voided_at)
                                                <span class="ml-1 rounded-full bg-error-50 px-2 py-0.5 text-[10px] font-semibold text-error-700 dark:bg-error-500/15 dark:text-error-300">Dibatalkan</span>
                                                <span class="mt-1 block text-[11px] text-error-600">{{ $sale->void_reason }} · {{ $sale->voidedBy?->name ?? 'User dihapus' }}</span>
                                            @endif
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
                                    @if ($canVoidSale)
                                        <button type="button" @click="openVoid(@js(['id' => $sale->id, 'invoice' => $sale->invoice_number, 'total' => $sale->total]))" class="mt-2 block w-full text-[11px] font-semibold text-error-600 hover:text-error-700">Batalkan Transaksi</button>
                                    @endif
                                    @if (! $sale->voided_at && $currentUser?->hasRole('admin'))
                                        <a href="{{ route('sales.edit', $sale) }}" class="mt-2 block text-[11px] font-semibold text-brand-600 hover:text-brand-700">Edit Transaksi</a>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">
                                    @if ($sale->payment_method === 'free')
                                        <span class="font-semibold text-warning-700 dark:text-warning-300">{{ ucfirst($sale->complimentary_category) }}</span>
                                        <span class="block text-[11px] text-gray-500 dark:text-gray-400">{{ $sale->complimentary_recipient_name }}</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="px-4 py-10 text-center text-sm text-gray-500">Belum ada transaksi penjualan dalam filter ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-100 px-4 py-3 dark:border-gray-800">
                {{ $sales->links() }}
            </div>
        </section>

        <div x-cloak x-show="voidModal" class="fixed inset-0 z-99999 flex items-center justify-center bg-gray-950/50 p-4">
            <div @click.outside="closeVoid()" class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xl dark:border-gray-800 dark:bg-gray-900">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Batalkan transaksi?</h2>
                <p class="mt-1 text-sm text-gray-500">Invoice <strong x-text="selectedSale?.invoice"></strong> akan di-void. Stok dikembalikan dan transaksi tetap tersimpan sebagai audit.</p>
                <label class="mt-4 block">
                    <span class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Alasan pembatalan</span>
                    <textarea x-model="voidReason" rows="3" maxlength="1000" placeholder="Contoh: Salah input produk" class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white"></textarea>
                </label>
                <p x-show="voidError" class="mt-3 rounded-lg border border-error-200 bg-error-50 px-3 py-2 text-xs text-error-700" x-text="voidError"></p>
                <div class="mt-5 grid grid-cols-2 gap-2">
                    <button type="button" @click="submitVoid()" :disabled="voidLoading || !voidReason.trim()" class="h-10 rounded-lg bg-error-600 px-4 text-sm font-semibold text-white disabled:opacity-60"><span x-text="voidLoading ? 'Memproses...' : 'Ya, void transaksi'"></span></button>
                    <button type="button" @click="closeVoid()" class="h-10 rounded-lg border border-gray-200 px-4 text-sm font-semibold text-gray-700 dark:border-gray-800 dark:text-gray-300">Batal</button>
                </div>
            </div>
        </div>
    </div>
@endsection
