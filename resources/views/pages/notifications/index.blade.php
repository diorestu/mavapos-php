@extends('layouts.app')

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
                        <li class="font-medium text-gray-700 dark:text-gray-300">Notifikasi</li>
                    </ol>
                </nav>

                <h1 class="mt-1 text-xl font-semibold text-gray-800 dark:text-white/90">Notifikasi</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ number_format($activities->count(), 0, ',', '.') }} aktivitas sistem, {{ number_format($unreadCount, 0, ',', '.') }} belum dibaca.
                </p>
            </div>

            <form method="POST" action="{{ route('notifications.mark-all-read') }}">
                @csrf
                <button type="submit"
                    class="inline-flex h-10 items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-50"
                    @disabled($unreadCount === 0)>
                    Mark All as Read
                </button>
            </form>
        </div>

        @if (session('status'))
            <div class="rounded-lg border border-success-200 bg-success-50 px-4 py-3 text-sm font-medium text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400">
                {{ session('status') }}
            </div>
        @endif

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Semua Notifikasi</h2>
            </div>

            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($activities as $activity)
                    <a href="{{ $activity['url'] }}"
                        class="flex gap-4 px-5 py-4 transition hover:bg-gray-50 dark:hover:bg-white/[0.04] {{ $activity['read'] ? '' : 'bg-brand-50/50 dark:bg-brand-500/5' }}">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl text-[11px] font-bold {{ $activity['tone'] }}">
                            {{ $activity['icon'] }}
                        </span>

                        <span class="min-w-0 flex-1">
                            <span class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                                <span class="min-w-0">
                                    <span class="flex items-center gap-2">
                                        @if (! $activity['read'])
                                            <span class="h-2 w-2 shrink-0 rounded-full bg-error-500"></span>
                                        @endif
                                        <span class="truncate text-sm font-semibold text-gray-800 dark:text-white/90">
                                            {{ $activity['title'] }}
                                        </span>
                                    </span>
                                    <span class="mt-1 block text-xs leading-5 text-gray-500 dark:text-gray-400">
                                        {{ $activity['description'] }}
                                    </span>
                                </span>

                                <span class="shrink-0 text-xs text-gray-400 dark:text-gray-500">{{ $activity['time'] }}</span>
                            </span>

                            <span class="mt-3 flex flex-wrap items-center gap-2 text-[11px] text-gray-400 dark:text-gray-500">
                                <span class="rounded-full bg-gray-100 px-2 py-1 font-medium text-gray-600 dark:bg-white/[0.06] dark:text-gray-300">{{ $activity['type'] }}</span>
                                <span>{{ $activity['read'] ? 'Sudah dibaca' : 'Belum dibaca' }}</span>
                            </span>
                        </span>
                    </a>
                @empty
                    <div class="flex min-h-[320px] flex-col items-center justify-center px-6 py-12 text-center">
                        <div class="grid h-12 w-12 place-items-center rounded-xl bg-gray-50 text-gray-400 dark:bg-white/[0.04]">
                            <svg class="h-6 w-6 stroke-current" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M15 17H9M18 10C18 6.68629 15.3137 4 12 4C8.68629 4 6 6.68629 6 10V14.25L4.75 16H19.25L18 14.25V10Z" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </div>
                        <p class="mt-3 text-sm font-semibold text-gray-800 dark:text-white/90">Belum ada notifikasi.</p>
                        <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">Transaksi, shift kasir, perubahan stok, dan billing akan muncul di sini.</p>
                    </div>
                @endforelse
            </div>
        </section>
    </div>
@endsection
