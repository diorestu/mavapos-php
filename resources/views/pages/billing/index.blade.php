@extends('layouts.app')

@section('content')
    <div x-data="saasBilling()" class="space-y-4">
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
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Paket Langganan</h2>
                <div class="mt-3 grid gap-2">
                    <button type="button" @click="selectPlan('Basic Plan', 149000, 'Fitur basic untuk operasional toko harian.')"
                        class="rounded-lg border border-gray-200 p-3 text-left transition hover:border-brand-300 hover:bg-brand-50/40 dark:border-gray-800 dark:hover:border-brand-500/40 dark:hover:bg-brand-500/10">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-800 dark:text-white/90">Basic Plan</p>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Fitur basic untuk operasional toko harian.</p>
                            </div>
                            <p class="shrink-0 text-sm font-semibold text-brand-600 dark:text-brand-400">Rp149.000</p>
                        </div>
                    </button>

                    <button type="button" @click="selectPlan('Plus Plan', 249000, 'Full-feature untuk bisnis yang butuh seluruh modul.')"
                        class="rounded-lg border border-gray-200 p-3 text-left transition hover:border-brand-300 hover:bg-brand-50/40 dark:border-gray-800 dark:hover:border-brand-500/40 dark:hover:bg-brand-500/10">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-800 dark:text-white/90">Plus Plan</p>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Full-feature untuk bisnis yang butuh seluruh modul.</p>
                            </div>
                            <p class="shrink-0 text-sm font-semibold text-brand-600 dark:text-brand-400">Rp249.000</p>
                        </div>
                    </button>
                </div>

                <h2 class="mt-5 text-sm font-semibold text-gray-800 dark:text-white/90">Buat Tagihan</h2>
                <form method="POST" action="{{ route('billings.store') }}" class="mt-4 space-y-3">
                    @csrf

                    <div>
                        <label for="customer_id" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Pelanggan</label>
                        <select id="customer_id" name="customer_id"
                            class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                            <option value="">Pelanggan baru / umum</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer['id'] }}" @selected(old('customer_id') == $customer['id'])>
                                    {{ $customer['name'] }} - {{ $customer['code'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                        <div>
                            <label for="customer_name" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Nama Pelanggan</label>
                            <input id="customer_name" name="customer_name" value="{{ old('customer_name') }}" type="text"
                                class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                                placeholder="Nama pelanggan" />
                        </div>
                        <div>
                            <label for="customer_phone" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">No. HP</label>
                            <input id="customer_phone" name="customer_phone" value="{{ old('customer_phone') }}" type="text"
                                class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                                placeholder="08xxxxxxxxxx" />
                        </div>
                    </div>

                    <div>
                        <label for="title" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Judul Tagihan</label>
                        <input id="title" name="title" value="{{ old('title') }}" x-model="form.title" type="text" required
                            class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                            placeholder="Pembayaran order / layanan" />
                    </div>

                    <div>
                        <label for="amount" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Nominal</label>
                        <input id="amount" name="amount" value="{{ old('amount') }}" x-model="form.amount" type="number" min="1000" required
                            class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                            placeholder="50000" />
                    </div>

                    <div>
                        <label for="description" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Catatan</label>
                        <textarea id="description" name="description" rows="3" x-model="form.description"
                            class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                            placeholder="Opsional"></textarea>
                    </div>

                    <button type="submit"
                        class="inline-flex h-10 w-full items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-semibold text-white shadow-theme-xs transition hover:bg-brand-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30 disabled:cursor-not-allowed disabled:opacity-50"
                        @disabled(! $pakasirConfigured)>
                        Generate QRIS Pakasir
                    </button>
                </form>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex flex-col gap-2 border-b border-gray-100 px-4 py-3 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Daftar Tagihan SaaS</h2>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Tagihan Basic/Plus dapat dibayar via QRIS dan status otomatis berubah ketika webhook Pakasir diterima.</p>
                    </div>
                    <span class="rounded-lg bg-gray-50 px-2.5 py-1 text-xs font-medium text-gray-500 dark:bg-gray-900/60 dark:text-gray-400">
                        {{ $billings->count() }} tagihan
                    </span>
                </div>

                <div class="max-w-full overflow-x-auto custom-scrollbar">
                    <table class="w-full min-w-[980px]">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                                <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Invoice</th>
                                <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Pelanggan</th>
                                <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Tagihan</th>
                                <th class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Total</th>
                                <th class="px-4 py-2.5 text-center text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                                <th class="px-4 py-2.5 text-center text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">QRIS</th>
                                <th class="px-4 py-2.5 text-center text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($billings as $billing)
                                <tr class="border-b border-gray-100 transition-colors last:border-0 hover:bg-gray-25 dark:border-gray-800 dark:hover:bg-white/[0.02]">
                                    <td class="px-4 py-3">
                                        <p class="text-[13px] font-semibold text-gray-800 dark:text-white/90">{{ $billing['invoiceNumber'] }}</p>
                                        <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">{{ $billing['createdAt'] }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="max-w-[180px] truncate text-[13px] font-medium text-gray-800 dark:text-white/90">{{ $billing['customerName'] }}</p>
                                        <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">{{ $billing['customerPhone'] ?: '-' }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="max-w-[220px] truncate text-[13px] font-medium text-gray-800 dark:text-white/90">{{ $billing['title'] }}</p>
                                        <p class="mt-0.5 max-w-[220px] truncate text-[11px] text-gray-500 dark:text-gray-400">{{ $billing['description'] ?: '-' }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <p class="text-[13px] font-semibold tabular-nums text-gray-800 dark:text-white/90">{{ $billing['totalPaymentFormatted'] }}</p>
                                        <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">Pokok {{ $billing['amountFormatted'] }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        @php
                                            $statusClass = match ($billing['paymentStatus']) {
                                                'completed', 'paid' => 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-400',
                                                'expired', 'canceled' => 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400',
                                                default => 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-300',
                                            };
                                        @endphp
                                        <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $statusClass }}">{{ $billing['paymentStatusLabel'] }}</span>
                                        @if ($billing['paidAt'])
                                            <p class="mt-1 text-[10px] text-gray-500 dark:text-gray-400">{{ $billing['paidAt'] }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <a href="{{ $billing['paymentUrl'] }}" target="_blank" rel="noopener"
                                            class="inline-flex h-8 items-center justify-center rounded-lg border border-gray-200 px-3 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03]">
                                            Buka QRIS
                                        </a>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <form method="POST" action="{{ $billing['refreshUrl'] }}">
                                            @csrf
                                            <button type="submit"
                                                class="inline-flex h-8 items-center justify-center rounded-lg bg-gray-900 px-3 text-xs font-semibold text-white transition hover:bg-gray-800 dark:bg-white dark:text-gray-900">
                                                Cek Status
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                        Belum ada tagihan.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        function saasBilling() {
            return {
                form: {
                    title: @js(old('title', 'Basic Plan')),
                    amount: @js((int) old('amount', 149000)),
                    description: @js(old('description', 'Fitur basic untuk operasional toko harian.')),
                },
                selectPlan(title, amount, description) {
                    this.form.title = title;
                    this.form.amount = amount;
                    this.form.description = description;
                },
            };
        }
    </script>
@endpush
