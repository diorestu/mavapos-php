@extends('layouts.app')

@php $rupiah = fn ($value) => 'Rp'.number_format((int) $value, 0, ',', '.'); @endphp

@section('content')
<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div><h1 class="text-xl font-semibold text-gray-800 dark:text-white/90">Penggajian & Payslip</h1><p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Gaji pokok + tunjangan tetap + bonus berdasarkan penjualan pribadi.</p></div>
        <div class="flex gap-2">
            <form method="GET" action="{{ route('payrolls') }}" class="flex gap-2"><input type="month" name="month" value="{{ $month->format('Y-m') }}" class="h-10 rounded-lg border border-gray-300 px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"><button class="h-10 rounded-lg border border-gray-200 px-3 text-xs font-semibold dark:border-gray-800 dark:text-gray-300">Tampilkan</button></form>
            <form method="POST" action="{{ route('payrolls.generate') }}">@csrf<input type="hidden" name="month" value="{{ $month->format('Y-m') }}"><button class="h-10 rounded-lg bg-brand-500 px-3 text-xs font-semibold text-white">Generate Payslip</button></form>
        </div>
    </div>
    @if(session('status'))<div class="rounded-lg border border-success-200 bg-success-50 px-4 py-3 text-sm text-success-700">{{ session('status') }}</div>@endif

    <nav class="flex gap-1 rounded-xl border border-gray-200 bg-white p-1 dark:border-gray-800 dark:bg-white/[0.03]">
        <a href="{{ route('payrolls', ['month' => $month->format('Y-m'), 'tab' => 'payslip']) }}" class="rounded-lg px-3 py-2 text-xs font-semibold {{ $tab === 'payslip' ? 'bg-brand-500 text-white' : 'text-gray-600 dark:text-gray-300' }}">Payslip</a>
        <a href="{{ route('payrolls', ['month' => $month->format('Y-m'), 'tab' => 'bonus']) }}" class="rounded-lg px-3 py-2 text-xs font-semibold {{ $tab === 'bonus' ? 'bg-brand-500 text-white' : 'text-gray-600 dark:text-gray-300' }}">Bonus Harian</a>
    </nav>

    @if($tab === 'bonus')
    <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-800"><h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Bonus Harian per Orang · {{ $month->translatedFormat('F Y') }}</h2><p class="text-[11px] text-gray-500">Bonus personal berdasarkan target pada masing-masing tanggal.</p></div>
        <div class="overflow-x-auto"><table class="w-full min-w-[920px]"><thead><tr class="bg-gray-50 text-left dark:bg-gray-900/40"><th class="px-4 py-2 text-[11px] uppercase text-gray-500">Tanggal</th><th class="px-4 py-2 text-right text-[11px] uppercase text-gray-500">Total Penjualan</th><th class="px-4 py-2 text-right text-[11px] uppercase text-gray-500">Total Bonus</th>@foreach($users as $user)<th class="px-4 py-2 text-right text-[11px] uppercase text-gray-500">Bonus {{ $user->name }}</th>@endforeach</tr></thead><tbody>
            @foreach($bonusTable['rows'] as $row)<tr class="border-t border-gray-100 dark:border-gray-800"><td class="px-4 py-2 text-xs font-semibold text-gray-700 dark:text-gray-300">{{ $row['date']->format('d/m/Y') }}</td><td class="px-4 py-2 text-right text-xs tabular-nums">{{ number_format($row['sales'], 0, ',', '.') }} item</td><td class="px-4 py-2 text-right text-xs tabular-nums">{{ $rupiah($row['bonus']) }}</td>@foreach($users as $user) @php $level = $row['levels']->get($user->id, 0); $levelClass = [1 => 'bg-sky-100 text-sky-700', 2 => 'bg-green-100 text-green-700', 3 => 'bg-amber-100 text-amber-700', 4 => 'bg-purple-100 text-purple-700'][$level] ?? 'text-gray-500'; $salesValue = $row['salesValues']->get($user->id, 0); @endphp<td class="px-2 py-2 text-right text-xs tabular-nums"><span class="inline-flex rounded-full px-2 py-1 {{ $levelClass }}">{{ $level > 0 ? '('.number_format($salesValue, 0, ',', '.').') ' : '' }}{{ $rupiah($row['values']->get($user->id, 0)) }}</span></td>@endforeach</tr>@endforeach
        </tbody><tfoot><tr class="border-t-2 border-gray-200 bg-gray-50 font-bold dark:border-gray-700 dark:bg-gray-900/40"><td class="px-4 py-3 text-xs uppercase">Total bulan</td><td class="px-4 py-3 text-right text-xs tabular-nums">{{ number_format($bonusTable['salesTotal'], 0, ',', '.') }} item</td><td class="px-4 py-3 text-right text-xs tabular-nums">{{ $rupiah($bonusTable['bonusTotal']) }}</td>@foreach($users as $user)<td class="px-4 py-3 text-right text-xs tabular-nums">{{ $rupiah($bonusTable['totals']->get($user->id, 0)) }}</td>@endforeach</tr></tfoot></table></div>
    </section>
    @else

    <section class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-800"><h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Komponen Gaji Staf</h2><p class="text-[11px] text-gray-500">Simpan gaji pokok dan tunjangan tetap per orang.</p></div>
        <div class="divide-y divide-gray-100 dark:divide-gray-800">
            @forelse($users as $user)
                <form method="POST" action="{{ route('payrolls.salary', $user) }}" class="grid items-center gap-3 px-4 py-3 md:grid-cols-[1fr_130px_130px_auto]">@csrf @method('PATCH')
                    <div><p class="text-sm font-semibold text-gray-800 dark:text-white/90">{{ $user->name }}</p><p class="text-[11px] text-gray-500">{{ ucfirst($user->role) }} · {{ $user->email }}</p></div>
                    <input type="number" min="0" name="basic_salary" value="{{ $user->basic_salary }}" placeholder="Gaji pokok" class="h-9 rounded-lg border border-gray-300 px-3 text-xs dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    <input type="number" min="0" name="fixed_allowance" value="{{ $user->fixed_allowance }}" placeholder="Tunjangan" class="h-9 rounded-lg border border-gray-300 px-3 text-xs dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    <button class="h-9 rounded-lg border border-brand-200 px-3 text-xs font-semibold text-brand-600">Simpan</button>
                </form>
            @empty <p class="px-4 py-6 text-sm text-gray-500">Belum ada staf non-admin.</p>@endforelse
        </div>
    </section>

    <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-800"><h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Payslip {{ $month->translatedFormat('F Y') }}</h2></div>
        <div class="overflow-x-auto"><table class="w-full min-w-[760px]"><thead><tr class="bg-gray-50 text-left dark:bg-gray-900/40"><th class="px-4 py-2 text-[11px] uppercase text-gray-500">Staf</th><th class="px-4 py-2 text-right text-[11px] uppercase text-gray-500">Gaji Pokok</th><th class="px-4 py-2 text-right text-[11px] uppercase text-gray-500">Tunjangan</th><th class="px-4 py-2 text-right text-[11px] uppercase text-gray-500">Penjualan Pribadi</th><th class="px-4 py-2 text-right text-[11px] uppercase text-gray-500">Bonus</th><th class="px-4 py-2 text-right text-[11px] uppercase text-gray-500">Total Diterima</th></tr></thead><tbody>
            @forelse($payrolls as $payroll)<tr class="border-t border-gray-100 dark:border-gray-800"><td class="px-4 py-3 text-sm font-semibold text-gray-800 dark:text-white/90">{{ $payroll->user->name }}<span class="block text-[11px] font-normal text-gray-500">{{ ucfirst($payroll->user->role) }}</span></td><td class="px-4 py-3 text-right text-xs tabular-nums">{{ $rupiah($payroll->basic_salary) }}</td><td class="px-4 py-3 text-right text-xs tabular-nums">{{ $rupiah($payroll->fixed_allowance) }}</td><td class="px-4 py-3 text-right text-xs tabular-nums">{{ number_format($payroll->sales_count, 0, ',', '.') }} item</td><td class="px-4 py-3 text-right text-xs tabular-nums">{{ $rupiah($payroll->sales_bonus) }}</td><td class="px-4 py-3 text-right text-sm font-bold tabular-nums">{{ $rupiah($payroll->total_amount) }}</td></tr>@empty<tr><td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">Belum ada payslip. Klik Generate Payslip.</td></tr>@endforelse
        </tbody></table></div>
    </section>
    @endif
</div>
@endsection
