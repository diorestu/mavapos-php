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
                        <li class="font-medium text-gray-700 dark:text-gray-300">Shift Kasir</li>
                    </ol>
                </nav>
                <h1 class="mt-1 text-xl font-semibold text-gray-800 dark:text-white/90">Shift Kasir</h1>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Pantau absensi mulai kerja, tutup kasir, dan ringkasan pendapatan per sesi kasir.</p>
            </div>

            <a href="{{ route('pos') }}" class="inline-flex h-10 items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-semibold text-white shadow-theme-xs transition hover:bg-brand-600">
                Buka Kasir
            </a>
        </div>

        @if ($activeShift)
            <section class="rounded-xl border border-success-200 bg-success-50 p-4 dark:border-success-500/20 dark:bg-success-500/10">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase text-success-700 dark:text-success-300">Shift aktif</p>
                        <h2 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $activeShift->user?->name ?? 'Kasir' }}</h2>
                        <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">Mulai {{ $activeShift->opened_at?->format('d M Y H:i') }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <div class="rounded-lg bg-white/70 px-3 py-2 dark:bg-white/[0.04]">
                            <p class="text-[10px] uppercase text-gray-500">Transaksi</p>
                            <p class="mt-1 text-sm font-semibold">{{ number_format($activeShift->sales_count, 0, ',', '.') }}</p>
                        </div>
                        <div class="rounded-lg bg-white/70 px-3 py-2 dark:bg-white/[0.04]">
                            <p class="text-[10px] uppercase text-gray-500">Pendapatan</p>
                            <p class="mt-1 text-sm font-semibold tabular-nums">{{ $rupiah($activeShift->net_sales) }}</p>
                        </div>
                        <div class="rounded-lg bg-white/70 px-3 py-2 dark:bg-white/[0.04]">
                            <p class="text-[10px] uppercase text-gray-500">Tunai</p>
                            <p class="mt-1 text-sm font-semibold tabular-nums">{{ $rupiah($activeShift->cash_total) }}</p>
                        </div>
                        <div class="rounded-lg bg-white/70 px-3 py-2 dark:bg-white/[0.04]">
                            <p class="text-[10px] uppercase text-gray-500">Non-tunai</p>
                            <p class="mt-1 text-sm font-semibold tabular-nums">{{ $rupiah($activeShift->qris_total + $activeShift->card_total) }}</p>
                        </div>
                    </div>
                </div>
            </section>
        @endif

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Riwayat Shift</h2>
            </div>
            <div class="max-w-full overflow-x-auto custom-scrollbar">
                <table class="w-full min-w-[860px]">
                    <thead>
                        <tr class="bg-gray-50 text-left dark:bg-gray-900/40">
                            <th class="px-4 py-2 text-[11px] font-semibold uppercase text-gray-500">Kasir</th>
                            <th class="px-4 py-2 text-[11px] font-semibold uppercase text-gray-500">Mulai</th>
                            <th class="px-4 py-2 text-[11px] font-semibold uppercase text-gray-500">Tutup</th>
                            <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase text-gray-500">Transaksi</th>
                            <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase text-gray-500">Pendapatan</th>
                            <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase text-gray-500">Tunai</th>
                            <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase text-gray-500">QRIS</th>
                            <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase text-gray-500">Kartu</th>
                            <th class="px-4 py-2 text-center text-[11px] font-semibold uppercase text-gray-500">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($shifts as $shift)
                            <tr class="border-t border-gray-100 dark:border-gray-800">
                                <td class="px-4 py-3">
                                    <p class="text-[13px] font-semibold text-gray-800 dark:text-white/90">{{ $shift->user?->name ?? 'Kasir' }}</p>
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400">{{ $shift->user?->email }}</p>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">{{ $shift->opened_at?->format('d M Y H:i') }}</td>
                                <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">{{ $shift->closed_at?->format('d M Y H:i') ?? '-' }}</td>
                                <td class="px-4 py-3 text-right text-xs font-semibold tabular-nums text-gray-800 dark:text-white/90">{{ number_format($shift->sales_count, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-xs font-semibold tabular-nums text-gray-800 dark:text-white/90">{{ $rupiah($shift->net_sales) }}</td>
                                <td class="px-4 py-3 text-right text-xs tabular-nums text-gray-600 dark:text-gray-300">{{ $rupiah($shift->cash_total) }}</td>
                                <td class="px-4 py-3 text-right text-xs tabular-nums text-gray-600 dark:text-gray-300">{{ $rupiah($shift->qris_total) }}</td>
                                <td class="px-4 py-3 text-right text-xs tabular-nums text-gray-600 dark:text-gray-300">{{ $rupiah($shift->card_total) }}</td>
                                <td class="px-4 py-3 text-center">
                                    @if ($shift->closed_at)
                                        <span class="rounded-full bg-gray-100 px-2 py-1 text-[11px] font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">Selesai</span>
                                    @else
                                        <span class="rounded-full bg-success-50 px-2 py-1 text-[11px] font-semibold text-success-700 dark:bg-success-500/15 dark:text-success-400">Aktif</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-4 py-8 text-center text-sm text-gray-500">Belum ada shift kasir.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-100 px-4 py-3 dark:border-gray-800">
                {{ $shifts->links() }}
            </div>
        </section>
    </div>
@endsection
