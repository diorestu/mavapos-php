@extends('layouts.app')

@section('content')
    <div class="space-y-4">
        <div><h1 class="text-xl font-semibold text-gray-800 dark:text-white/90">Membership SaaS</h1><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Ringkasan seluruh akun yang menggunakan platform.</p></div>
        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([['Member', $summary['members']], ['Aktif', $summary['active']], ['Trial', $summary['trial']], ['Pendapatan', 'Rp'.number_format($summary['revenue'], 0, ',', '.')]] as [$label, $value])
                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]"><p class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</p><p class="mt-2 text-xl font-semibold text-gray-900 dark:text-white">{{ $value }}</p></div>
            @endforeach
        </section>
        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]"><table class="w-full min-w-[720px] text-left"><thead><tr class="border-b border-gray-100 text-[11px] uppercase text-gray-500 dark:border-gray-800"><th class="px-4 py-3">Member</th><th class="px-4 py-3">Paket</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Berakhir</th><th class="px-4 py-3 text-right">Pendapatan</th></tr></thead><tbody>@foreach ($members as $member)<tr class="border-b border-gray-100 text-sm dark:border-gray-800"><td class="px-4 py-3"><p class="font-semibold text-gray-800 dark:text-white/90">{{ $member['owner']->name }}</p><p class="text-xs text-gray-500">{{ $member['owner']->email }}</p></td><td class="px-4 py-3">{{ $member['plan'] }}</td><td class="px-4 py-3">{{ $member['status'] }}</td><td class="px-4 py-3">{{ $member['period_ends_at'] ?: '-' }}</td><td class="px-4 py-3 text-right">Rp{{ number_format($member['revenue'], 0, ',', '.') }}</td></tr>@endforeach</tbody></table></div>
    </div>
@endsection
