@extends('layouts.app')

@php
    $selectedPlan = old('plan_slug', $currentSubscription['plan_slug'] ?: 'basic');
    $selectedCycle = old('billing_cycle', $currentSubscription['billing_cycle'] ?: 'monthly');
@endphp

@section('content')
    <div class="space-y-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <nav aria-label="Breadcrumb">
                    <ol class="flex items-center gap-1 text-[10px] text-gray-500 dark:text-gray-400">
                        <li><a href="{{ url('/') }}" class="transition hover:text-gray-700 dark:hover:text-gray-200">Home</a></li>
                        <li aria-hidden="true">
                            <svg class="h-3 w-3 stroke-current" viewBox="0 0 17 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M6.0765 12.667L10.2432 8.50033L6.0765 4.33366" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </li>
                        <li><a href="{{ route('settings') }}" class="transition hover:text-gray-700 dark:hover:text-gray-200">Pengaturan</a></li>
                        <li aria-hidden="true">
                            <svg class="h-3 w-3 stroke-current" viewBox="0 0 17 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M6.0765 12.667L10.2432 8.50033L6.0765 4.33366" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </li>
                        <li class="font-medium text-gray-700 dark:text-gray-300">Billing</li>
                    </ol>
                </nav>
                <h1 class="mt-1 text-xl font-semibold text-gray-800 dark:text-white/90">Billing SaaS</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Pilih paket langganan bulanan atau tahunan untuk akun bisnis ini.</p>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-lg border border-success-200 bg-success-50 px-4 py-3 text-sm text-success-700 dark:border-success-500/20 dark:bg-success-500/10 dark:text-success-300">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-lg border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/20 dark:bg-error-500/10 dark:text-error-300">
                {{ $errors->first() }}
            </div>
        @endif

        @unless ($pakasirConfigured)
            <div class="rounded-lg border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:border-warning-500/20 dark:bg-warning-500/10 dark:text-warning-300">
                Pakasir belum aktif. Isi <span class="font-semibold">PAKASIR_PROJECT</span> dan <span class="font-semibold">PAKASIR_API_KEY</span> di .env, lalu set webhook Pakasir ke <span class="font-semibold">{{ route('pakasir.webhook') }}</span>.
            </div>
        @endunless

        <section class="grid gap-4 xl:grid-cols-[380px_minmax(0,1fr)]">
            <div class="space-y-4">
                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Status Langganan</h2>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $currentSubscription['invoice_number'] ?: 'Belum ada invoice lunas.' }}</p>
                        </div>
                        <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $currentSubscription['active'] ? 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-400' : 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-300' }}">
                            {{ $currentSubscription['status_label'] }}
                        </span>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <div>
                            <p class="mb-1 text-xs text-gray-500 dark:text-gray-400">Plan</p>
                            <p class="text-sm font-semibold text-gray-800 dark:text-white/90">{{ $currentSubscription['plan_name'] }}</p>
                        </div>
                        <div>
                            <p class="mb-1 text-xs text-gray-500 dark:text-gray-400">Siklus</p>
                            <p class="text-sm font-semibold text-gray-800 dark:text-white/90">{{ $currentSubscription['billing_cycle_label'] }}</p>
                        </div>
                        <div class="col-span-2">
                            <p class="mb-1 text-xs text-gray-500 dark:text-gray-400">Periode Aktif</p>
                            <p class="text-sm font-medium text-gray-800 dark:text-white/90">
                                @if ($currentSubscription['period_starts_at'] && $currentSubscription['period_ends_at'])
                                    {{ $currentSubscription['period_starts_at'] }} - {{ $currentSubscription['period_ends_at'] }}
                                @else
                                    -
                                @endif
                            </p>
                        </div>
                    </div>
                </div>

                <form method="POST" action="{{ route('billings.store') }}" class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                    @csrf

                    <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Buat Tagihan Langganan</h2>
                    <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">Data akun diambil otomatis dari Pengaturan, jadi pengguna tidak perlu mengisi identitas lagi.</p>

                    <div class="mt-4">
                        <p class="mb-2 text-xs font-medium text-gray-700 dark:text-gray-300">Paket</p>
                        <div class="grid gap-2">
                            @foreach ($plans as $plan)
                                <label class="block cursor-pointer">
                                    <input type="radio" name="plan_slug" value="{{ $plan['slug'] }}" class="peer sr-only" @checked($selectedPlan === $plan['slug'])>
                                    <span class="block rounded-lg border border-gray-200 p-3 transition peer-checked:border-brand-500 peer-checked:bg-brand-50 dark:border-gray-800 dark:peer-checked:border-brand-500/60 dark:peer-checked:bg-brand-500/10">
                                        <span class="flex items-start justify-between gap-3">
                                            <span>
                                                <span class="flex flex-wrap items-center gap-2">
                                                    <span class="text-sm font-semibold text-gray-800 dark:text-white/90">{{ $plan['name'] }}</span>
                                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-600 dark:bg-white/[0.06] dark:text-gray-300">{{ $plan['change_label'] }}</span>
                                                </span>
                                                <span class="mt-0.5 block text-xs leading-5 text-gray-500 dark:text-gray-400">{{ $plan['description'] }}</span>
                                            </span>
                                            <span class="shrink-0 text-right">
                                                <span class="block text-sm font-semibold text-brand-600 dark:text-brand-400">{{ $plan['monthly_amount_formatted'] }}</span>
                                                <span class="text-[11px] text-gray-500 dark:text-gray-400">/bulan</span>
                                                <span class="mt-1 block text-[11px] text-gray-500 dark:text-gray-400">
                                                    <span class="line-through">{{ $plan['yearly_base_amount_formatted'] }}</span>
                                                    <span class="font-semibold text-success-600 dark:text-success-400">{{ $plan['yearly_amount_formatted'] }}</span>/tahun
                                                </span>
                                            </span>
                                        </span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="mt-4">
                        <p class="mb-2 text-xs font-medium text-gray-700 dark:text-gray-300">Siklus Tagihan</p>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach ($cycles as $cycle)
                                <label class="cursor-pointer">
                                    <input type="radio" name="billing_cycle" value="{{ $cycle['slug'] }}" class="peer sr-only" @checked($selectedCycle === $cycle['slug'])>
                                    <span class="flex h-full flex-col rounded-lg border border-gray-200 p-3 transition peer-checked:border-brand-500 peer-checked:bg-brand-50 dark:border-gray-800 dark:peer-checked:border-brand-500/60 dark:peer-checked:bg-brand-500/10">
                                        <span class="text-sm font-semibold text-gray-800 dark:text-white/90">{{ $cycle['label'] }}</span>
                                        <span class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">
                                            {{ $cycle['months'] }} bulan
                                            @if ($cycle['slug'] === 'yearly')
                                                · diskon 10%
                                            @endif
                                        </span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <button type="submit"
                        class="mt-4 inline-flex h-10 w-full items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-semibold text-white shadow-theme-xs transition hover:bg-brand-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30 disabled:cursor-not-allowed disabled:opacity-50"
                        @disabled(! $pakasirConfigured)>
                        Generate QRIS Pakasir
                    </button>
                </form>

                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                    <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Akun Ditagihkan</h2>
                    <div class="mt-4 space-y-3">
                        <div>
                            <p class="mb-1 text-xs text-gray-500 dark:text-gray-400">Bisnis</p>
                            <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $account['name'] ?: '-' }}</p>
                        </div>
                        <div>
                            <p class="mb-1 text-xs text-gray-500 dark:text-gray-400">Pemilik</p>
                            <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $account['owner'] ?: '-' }}</p>
                        </div>
                        <div>
                            <p class="mb-1 text-xs text-gray-500 dark:text-gray-400">Kontak</p>
                            <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $account['phone'] ?: '-' }}</p>
                        </div>
                        <div>
                            <p class="mb-1 text-xs text-gray-500 dark:text-gray-400">Email</p>
                            <p class="break-all text-sm font-medium text-gray-800 dark:text-white/90">{{ $account['email'] ?: '-' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex flex-col gap-2 border-b border-gray-100 px-4 py-3 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Riwayat Tagihan Langganan</h2>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Tagihan bulanan/tahunan dapat dibayar via QRIS dan status otomatis berubah dari webhook Pakasir.</p>
                    </div>
                    <span class="rounded-lg bg-gray-50 px-2.5 py-1 text-xs font-medium text-gray-500 dark:bg-gray-900/60 dark:text-gray-400">
                        {{ $billings->count() }} tagihan
                    </span>
                </div>

                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($billings as $billing)
                        @php
                            $statusClass = match ($billing['paymentStatus']) {
                                'completed', 'paid' => 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-400',
                                'expired', 'canceled' => 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400',
                                default => 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-300',
                            };
                        @endphp
                        <div class="grid gap-3 px-4 py-3 md:grid-cols-[minmax(0,1.1fr)_minmax(0,1.2fr)_auto] md:items-center">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="truncate text-[13px] font-semibold text-gray-800 dark:text-white/90">{{ $billing['invoiceNumber'] }}</p>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $statusClass }}">{{ $billing['paymentStatusLabel'] }}</span>
                                </div>
                                <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">{{ $billing['createdAt'] }}</p>
                            </div>

                            <div class="min-w-0">
                                <p class="truncate text-[13px] font-medium text-gray-800 dark:text-white/90">{{ $billing['title'] }}</p>
                                <p class="mt-0.5 truncate text-[11px] text-gray-500 dark:text-gray-400">
                                    {{ $billing['customerName'] }}
                                    @if ($billing['billingCycleLabel'])
                                        · {{ $billing['billingCycleLabel'] }}
                                    @endif
                                    @if ($billing['yearlyDiscountPercent'])
                                        · diskon {{ $billing['yearlyDiscountPercent'] }}%
                                    @endif
                                </p>
                                @if ($billing['periodStartsAt'] && $billing['periodEndsAt'])
                                    <p class="mt-0.5 truncate text-[11px] text-gray-500 dark:text-gray-400">{{ $billing['periodStartsAt'] }} - {{ $billing['periodEndsAt'] }}</p>
                                @endif
                            </div>

                            <div class="flex items-center justify-between gap-3 md:justify-end">
                                <div class="text-left md:text-right">
                                    <p class="text-[13px] font-semibold tabular-nums text-gray-800 dark:text-white/90">{{ $billing['totalPaymentFormatted'] }}</p>
                                    <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">Pokok {{ $billing['amountFormatted'] }}</p>
                                </div>
                                <div class="flex shrink-0 items-center gap-2">
                                    <a href="{{ $billing['paymentUrl'] }}" target="_blank" rel="noopener"
                                        class="inline-flex h-8 items-center justify-center rounded-lg border border-gray-200 px-3 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03]">
                                        QRIS
                                    </a>
                                    <form method="POST" action="{{ $billing['refreshUrl'] }}">
                                        @csrf
                                        <button type="submit"
                                            class="inline-flex h-8 items-center justify-center rounded-lg bg-gray-900 px-3 text-xs font-semibold text-white transition hover:bg-gray-800 dark:bg-white dark:text-gray-900">
                                            Cek
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                            Belum ada tagihan.
                        </div>
                    @endforelse
                </div>
            </div>
        </section>
    </div>
@endsection
