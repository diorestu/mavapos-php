@extends('layouts.app')

@php($rupiah = fn ($value) => 'Rp'.number_format((int) $value, 0, ',', '.'))

@section('content')
    <div class="space-y-4">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <a href="{{ route('reports') }}" class="text-xs font-semibold text-brand-600">← Kembali ke Laporan</a>
                <h1 class="mt-2 text-xl font-semibold text-gray-800 dark:text-white/90">Jurnal Transaksi</h1>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Pencatatan berpasangan debit dan kredit untuk {{ $activeBranch->name }}.</p>
            </div>
            <form method="GET" action="{{ route('reports.journal') }}" class="grid gap-2 rounded-xl border border-gray-200 bg-white p-2 dark:border-gray-800 dark:bg-white/[0.03] sm:grid-cols-[150px_150px_auto]">
                <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="h-9 rounded-lg border border-gray-300 bg-transparent px-3 text-xs dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="h-9 rounded-lg border border-gray-300 bg-transparent px-3 text-xs dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                <button class="h-9 rounded-lg bg-brand-500 px-3 text-xs font-semibold text-white">Terapkan</button>
            </form>
        </div>

        <section class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]"><p class="text-xs text-gray-500">Jumlah Jurnal</p><p class="mt-1 text-xl font-semibold dark:text-white">{{ $lines->count() }}</p></div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]"><p class="text-xs text-gray-500">Total Debit</p><p class="mt-1 text-xl font-semibold dark:text-white">{{ $rupiah($totalDebit) }}</p></div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]"><p class="text-xs text-gray-500">Total Kredit</p><p class="mt-1 text-xl font-semibold dark:text-white">{{ $rupiah($totalCredit) }}</p></div>
        </section>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-800"><h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Jurnal Umum</h2></div>
            <div class="max-w-full overflow-x-auto">
                <table class="w-full min-w-[900px]">
                    <thead><tr class="bg-gray-50 text-left dark:bg-gray-900/40">
                        <th class="px-4 py-2 text-[11px] uppercase text-gray-500">Tanggal</th><th class="px-4 py-2 text-[11px] uppercase text-gray-500">Referensi</th><th class="px-4 py-2 text-[11px] uppercase text-gray-500">Keterangan</th><th class="px-4 py-2 text-[11px] uppercase text-gray-500">Akun Debit</th><th class="px-4 py-2 text-right text-[11px] uppercase text-gray-500">Debit</th><th class="px-4 py-2 text-[11px] uppercase text-gray-500">Akun Kredit</th><th class="px-4 py-2 text-right text-[11px] uppercase text-gray-500">Kredit</th>
                    </tr></thead>
                    <tbody>
                        @forelse ($lines as $line)
                            <tr class="border-t border-gray-100 dark:border-gray-800"><td class="px-4 py-3 text-xs tabular-nums text-gray-600 dark:text-gray-300">{{ $line['date']->format('d/m/Y H:i') }}</td><td class="px-4 py-3 text-xs font-semibold text-gray-800 dark:text-white/90">{{ $line['reference'] }}</td><td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">{{ $line['description'] }}</td><td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">{{ $line['debitAccount'] }}</td><td class="px-4 py-3 text-right text-xs tabular-nums text-gray-800 dark:text-white/90">{{ $rupiah($line['debit']) }}</td><td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">{{ $line['creditAccount'] }}</td><td class="px-4 py-3 text-right text-xs tabular-nums text-gray-800 dark:text-white/90">{{ $rupiah($line['credit']) }}</td></tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500">Belum ada transaksi pada periode ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
