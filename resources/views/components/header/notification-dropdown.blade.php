{{-- Notification Dropdown Component --}}
@php
    $attentionCount = $attentionCount ?? 0;
    $activities = $activities ?? [];
@endphp

<div class="relative" x-data="{
    dropdownOpen: false,
    notifying: @js($attentionCount > 0),
    toggleDropdown() {
        this.dropdownOpen = !this.dropdownOpen;
        this.notifying = false;
    },
    closeDropdown() {
        this.dropdownOpen = false;
    }
}" @click.away="closeDropdown()">
    <button
        class="relative flex h-[38px] w-[38px] items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
        @click="toggleDropdown()"
        type="button"
        aria-label="Aktivitas sistem"
    >
        @if ($attentionCount > 0)
            <span
                x-show="notifying"
                class="absolute -right-0.5 -top-0.5 z-1 inline-flex min-h-3.5 min-w-3.5 items-center justify-center rounded-full bg-error-500 px-1 text-[9px] font-semibold leading-3.5 text-white"
            >
                {{ $attentionCount > 9 ? '9+' : $attentionCount }}
                <span class="absolute inline-flex h-full w-full rounded-full bg-error-400 opacity-40 -z-1 animate-ping"></span>
            </span>
        @endif

        <svg class="h-[17px] w-[17px] fill-current" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path fill-rule="evenodd" clip-rule="evenodd" d="M10.75 2.29248C10.75 1.87827 10.4143 1.54248 10 1.54248C9.58583 1.54248 9.25004 1.87827 9.25004 2.29248V2.83613C6.08266 3.20733 3.62504 5.9004 3.62504 9.16748V14.4591H3.33337C2.91916 14.4591 2.58337 14.7949 2.58337 15.2091C2.58337 15.6234 2.91916 15.9591 3.33337 15.9591H4.37504H15.625H16.6667C17.0809 15.9591 17.4167 15.6234 17.4167 15.2091C17.4167 14.7949 17.0809 14.4591 16.6667 14.4591H16.375V9.16748C16.375 5.9004 13.9174 3.20733 10.75 2.83613V2.29248ZM14.875 14.4591V9.16748C14.875 6.47509 12.6924 4.29248 10 4.29248C7.30765 4.29248 5.12504 6.47509 5.12504 9.16748V14.4591H14.875ZM8.00004 17.7085C8.00004 18.1228 8.33583 18.4585 8.75004 18.4585H11.25C11.6643 18.4585 12 18.1228 12 17.7085C12 17.2943 11.6643 16.9585 11.25 16.9585H8.75004C8.33583 16.9585 8.00004 17.2943 8.00004 17.7085Z" fill="" />
        </svg>
    </button>

    <div
        x-cloak
        x-show="dropdownOpen"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="transform opacity-0 scale-95"
        x-transition:enter-end="transform opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="transform opacity-100 scale-100"
        x-transition:leave-end="transform opacity-0 scale-95"
        class="absolute -right-[220px] mt-[17px] flex h-[480px] w-[350px] flex-col rounded-2xl border border-gray-200 bg-white p-3 shadow-theme-lg dark:border-gray-800 dark:bg-gray-dark sm:w-[380px] lg:right-0"
    >
        <div class="mb-3 flex items-start justify-between gap-3 border-b border-gray-100 pb-3 dark:border-gray-800">
            <div>
                <h5 class="text-base font-semibold text-gray-800 dark:text-white/90">Aktivitas Sistem</h5>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                    @if ($attentionCount > 0)
                        {{ number_format($attentionCount, 0, ',', '.') }} update perlu perhatian
                    @else
                        Semua aktivitas terbaru sudah normal
                    @endif
                </p>
            </div>

            <button @click="closeDropdown()" class="grid h-8 w-8 place-items-center rounded-lg text-gray-500 transition hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-white/[0.04]" type="button" aria-label="Tutup aktivitas">
                <svg class="h-5 w-5 fill-current" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M6.21967 7.28131C5.92678 6.98841 5.92678 6.51354 6.21967 6.22065C6.51256 5.92775 6.98744 5.92775 7.28033 6.22065L11.999 10.9393L16.7176 6.22078C17.0105 5.92789 17.4854 5.92788 17.7782 6.22078C18.0711 6.51367 18.0711 6.98855 17.7782 7.28144L13.0597 12L17.7782 16.7186C18.0711 17.0115 18.0711 17.4863 17.7782 17.7792C17.4854 18.0721 17.0105 18.0721 16.7176 17.7792L11.999 13.0607L7.28033 17.7794C6.98744 18.0722 6.51256 18.0722 6.21967 17.7794C5.92678 17.4865 5.92678 17.0116 6.21967 16.7187L10.9384 12L6.21967 7.28131Z" fill="" />
                </svg>
            </button>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto custom-scrollbar">
            @forelse ($activities as $activity)
                <a
                    href="{{ $activity['url'] }}"
                    class="group flex gap-3 rounded-xl border-b border-gray-100 px-3 py-3 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/[0.04]"
                    @click="closeDropdown()"
                >
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl text-[11px] font-bold {{ $activity['tone'] }}">
                        {{ $activity['icon'] }}
                    </span>

                    <span class="min-w-0 flex-1">
                        <span class="mb-1 flex items-start justify-between gap-2">
                            <span class="truncate text-sm font-semibold text-gray-800 dark:text-white/90">
                                {{ $activity['title'] }}
                            </span>
                            @if (! $activity['read'])
                                <span class="mt-0.5 h-2 w-2 shrink-0 rounded-full bg-error-500"></span>
                            @endif
                        </span>
                        <span class="line-clamp-2 text-xs leading-5 text-gray-500 dark:text-gray-400">
                            {{ $activity['description'] }}
                        </span>
                        <span class="mt-2 flex items-center gap-2 text-[11px] text-gray-400 dark:text-gray-500">
                            <span>{{ $activity['type'] }}</span>
                            <span class="h-1 w-1 rounded-full bg-gray-300 dark:bg-gray-700"></span>
                            <span>{{ $activity['time'] }}</span>
                        </span>
                    </span>
                </a>
            @empty
                <div class="flex h-full flex-col items-center justify-center px-6 text-center">
                    <div class="grid h-12 w-12 place-items-center rounded-xl bg-gray-50 text-gray-400 dark:bg-white/[0.04]">
                        <svg class="h-6 w-6 stroke-current" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M15 17H9M18 10C18 6.68629 15.3137 4 12 4C8.68629 4 6 6.68629 6 10V14.25L4.75 16H19.25L18 14.25V10Z" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </div>
                    <p class="mt-3 text-sm font-semibold text-gray-800 dark:text-white/90">Belum ada aktivitas.</p>
                    <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">Transaksi, shift kasir, perubahan stok, dan tagihan akan muncul di sini.</p>
                </div>
            @endforelse
        </div>

        <a
            href="{{ route('notifications') }}"
            class="mt-3 flex justify-center rounded-lg border border-gray-300 bg-white p-3 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200"
            @click="closeDropdown()"
        >
            Lihat Semua Notifikasi
        </a>
    </div>
</div>
