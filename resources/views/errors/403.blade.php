@extends('layouts.fullscreen-layout')

@section('content')
    @php
        $homeUrl = auth()->check() ? route('dashboard') : route('signin');
    @endphp

    <div class="relative flex min-h-screen items-center justify-center overflow-hidden bg-gray-50 p-6 dark:bg-gray-950">
        <x-common.common-grid-shape />

        <div class="relative z-10 w-full max-w-[520px] rounded-2xl border border-gray-200 bg-white p-6 text-center shadow-theme-lg dark:border-gray-800 dark:bg-gray-900 sm:p-8">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-warning-300">
                <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 3.75L19.5 7.25V12.25C19.5 16.5 16.6 20.05 12 21.25C7.4 20.05 4.5 16.5 4.5 12.25V7.25L12 3.75Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
                    <path d="M12 8.25V12.25M12 15.75H12.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </div>

            <p class="mt-5 text-xs font-semibold uppercase tracking-[0.18em] text-warning-600 dark:text-warning-300">403 Forbidden</p>
            <h1 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">Akses dibatasi</h1>
            <p class="mx-auto mt-3 max-w-[420px] text-sm leading-6 text-gray-500 dark:text-gray-400">
                Role akun Anda belum memiliki izin untuk membuka halaman ini. Gunakan menu yang tersedia di sidebar atau hubungi owner/admin untuk mengubah akses.
            </p>

            <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-center">
                <a href="{{ $homeUrl }}"
                    class="inline-flex h-11 items-center justify-center rounded-lg bg-brand-500 px-5 text-sm font-semibold text-white shadow-theme-xs transition hover:bg-brand-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30">
                    Kembali ke Dashboard
                </a>
                @auth
                    <a href="{{ route('profile') }}"
                        class="inline-flex h-11 items-center justify-center rounded-lg border border-gray-200 bg-white px-5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-white/[0.04]">
                        Lihat Profil
                    </a>
                @endauth
            </div>
        </div>
    </div>
@endsection
