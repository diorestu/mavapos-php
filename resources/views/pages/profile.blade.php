@extends('layouts.app')

@php
    $businessTypes = [
        'cafe' => 'Cafe',
        'restoran' => 'Restoran',
        'retail' => 'Retail',
        'salon' => 'Salon',
        'laundry' => 'Laundry',
        'lainnya' => 'Lainnya',
    ];

    $businessType = $businessTypes[$setting->business_type] ?? ucfirst((string) $setting->business_type);
    $joinedAt = $user->created_at ? $user->created_at->timezone(config('app.timezone'))->format('d M Y') : null;
    $verifiedAt = $user->email_verified_at ? $user->email_verified_at->timezone(config('app.timezone'))->format('d M Y, H:i') : null;
    $fallback = fn ($value, $label = '-') => filled($value) ? $value : $label;
@endphp

@section('content')
    <div class="space-y-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <nav aria-label="Breadcrumb">
                    <ol class="flex items-center gap-1 text-[10px] text-gray-500 dark:text-gray-400">
                        <li>
                            <a href="{{ url('/') }}" class="transition hover:text-gray-700 dark:hover:text-gray-200">Home</a>
                        </li>
                        <li aria-hidden="true">
                            <svg class="h-3 w-3 stroke-current" viewBox="0 0 17 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M6.0765 12.667L10.2432 8.50033L6.0765 4.33366" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </li>
                        <li class="font-medium text-gray-700 dark:text-gray-300">Profil</li>
                    </ol>
                </nav>

                <h1 class="mt-1 text-xl font-semibold text-gray-800 dark:text-white/90">Profil</h1>
            </div>

            <a href="{{ route('settings') }}"
                class="inline-flex h-10 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.03]">
                Edit Pengaturan
            </a>
        </div>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-100 bg-gray-50 px-5 py-5 dark:border-gray-800 dark:bg-gray-900/40">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                        <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                            <img src="{{ $logoUrl }}" alt="{{ $setting->store_name }}" class="h-full w-full object-cover">
                        </div>

                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Akun aktif</p>
                            <h2 class="mt-1 text-2xl font-semibold text-gray-800 dark:text-white/90">{{ $user->name }}</h2>
                            <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
                                <span>{{ $user->email }}</span>
                                <span class="hidden h-3.5 w-px bg-gray-300 sm:block dark:bg-gray-700"></span>
                                <span>{{ $setting->store_name }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 sm:min-w-[320px]">
                        <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-white/[0.03]">
                            <p class="text-[11px] text-gray-500 dark:text-gray-400">Status Email</p>
                            <p class="mt-1 text-sm font-semibold text-gray-800 dark:text-white/90">
                                {{ $user->email_verified_at ? 'Terverifikasi' : 'Belum Verifikasi' }}
                            </p>
                        </div>
                        <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-white/[0.03]">
                            <p class="text-[11px] text-gray-500 dark:text-gray-400">Bergabung</p>
                            <p class="mt-1 text-sm font-semibold text-gray-800 dark:text-white/90">{{ $joinedAt ?: '-' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 p-5 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div class="space-y-4">
                    <section class="rounded-xl border border-gray-200 dark:border-gray-800">
                        <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Informasi Akun</h3>
                        </div>
                        <div class="grid grid-cols-1 gap-4 p-4 md:grid-cols-2">
                            <div>
                                <p class="mb-1 text-xs text-gray-500 dark:text-gray-400">Nama User</p>
                                <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $user->name }}</p>
                            </div>
                            <div>
                                <p class="mb-1 text-xs text-gray-500 dark:text-gray-400">Email Login</p>
                                <p class="break-all text-sm font-medium text-gray-800 dark:text-white/90">{{ $user->email }}</p>
                            </div>
                            <div>
                                <p class="mb-1 text-xs text-gray-500 dark:text-gray-400">Verifikasi Email</p>
                                <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $verifiedAt ?: 'Belum diverifikasi' }}</p>
                            </div>
                            <div>
                                <p class="mb-1 text-xs text-gray-500 dark:text-gray-400">ID Akun</p>
                                <p class="text-sm font-medium text-gray-800 dark:text-white/90">#{{ $user->id }}</p>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-xl border border-gray-200 dark:border-gray-800">
                        <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Informasi Bisnis</h3>
                        </div>
                        <div class="grid grid-cols-1 gap-4 p-4 md:grid-cols-2">
                            <div>
                                <p class="mb-1 text-xs text-gray-500 dark:text-gray-400">Nama Bisnis</p>
                                <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $fallback($setting->store_name) }}</p>
                            </div>
                            <div>
                                <p class="mb-1 text-xs text-gray-500 dark:text-gray-400">Nama Legal</p>
                                <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $fallback($setting->legal_name) }}</p>
                            </div>
                            <div>
                                <p class="mb-1 text-xs text-gray-500 dark:text-gray-400">Pemilik</p>
                                <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $fallback($setting->owner_name) }}</p>
                            </div>
                            <div>
                                <p class="mb-1 text-xs text-gray-500 dark:text-gray-400">Tipe Bisnis</p>
                                <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $fallback($businessType) }}</p>
                            </div>
                            <div>
                                <p class="mb-1 text-xs text-gray-500 dark:text-gray-400">Mata Uang</p>
                                <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $fallback($setting->currency, 'IDR') }}</p>
                            </div>
                            <div>
                                <p class="mb-1 text-xs text-gray-500 dark:text-gray-400">NPWP / NIB</p>
                                <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $fallback($setting->tax_number) }}</p>
                            </div>
                        </div>
                    </section>
                </div>

                <aside class="space-y-4">
                    <section class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Kontak Outlet</h3>
                        <div class="mt-4 space-y-3">
                            <div>
                                <p class="mb-1 text-xs text-gray-500 dark:text-gray-400">Telepon</p>
                                <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $fallback($setting->phone) }}</p>
                            </div>
                            <div>
                                <p class="mb-1 text-xs text-gray-500 dark:text-gray-400">WhatsApp</p>
                                <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $fallback($setting->whatsapp) }}</p>
                            </div>
                            <div>
                                <p class="mb-1 text-xs text-gray-500 dark:text-gray-400">Email Bisnis</p>
                                <p class="break-all text-sm font-medium text-gray-800 dark:text-white/90">{{ $fallback($setting->email) }}</p>
                            </div>
                            <div>
                                <p class="mb-1 text-xs text-gray-500 dark:text-gray-400">Website</p>
                                <p class="break-all text-sm font-medium text-gray-800 dark:text-white/90">{{ $fallback($setting->website) }}</p>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Alamat & Operasional</h3>
                        <div class="mt-4 space-y-3">
                            <div>
                                <p class="mb-1 text-xs text-gray-500 dark:text-gray-400">Alamat Outlet</p>
                                <p class="text-sm font-medium leading-6 text-gray-800 dark:text-white/90">{{ $fallback($setting->address) }}</p>
                            </div>
                            <div>
                                <p class="mb-1 text-xs text-gray-500 dark:text-gray-400">Jam Operasional</p>
                                <p class="text-sm font-medium leading-6 text-gray-800 dark:text-white/90">{{ $fallback($setting->operational_hours) }}</p>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Media Sosial</h3>
                        <div class="mt-4 grid grid-cols-1 gap-3">
                            <p class="truncate text-sm font-medium text-gray-800 dark:text-white/90">Instagram: {{ $fallback($setting->instagram) }}</p>
                            <p class="truncate text-sm font-medium text-gray-800 dark:text-white/90">Facebook: {{ $fallback($setting->facebook) }}</p>
                            <p class="truncate text-sm font-medium text-gray-800 dark:text-white/90">TikTok: {{ $fallback($setting->tiktok) }}</p>
                        </div>
                    </section>
                </aside>
            </div>
        </section>
    </div>
@endsection
