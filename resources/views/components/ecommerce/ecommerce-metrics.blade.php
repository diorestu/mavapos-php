<section>
    <div class="mb-3">
        <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Ringkasan Toko</h2>
        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Pantauan cepat operasional toko hari ini.</p>
    </div>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($stats as $stat)
            <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-[11px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</p>
                        <p class="mt-1 text-xl font-semibold tabular-nums text-gray-800 dark:text-white/90">{{ $stat['value'] }}</p>
                        <p class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400">{{ $stat['note'] }}</p>
                    </div>
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $stat['tone'] }}">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M4.16663 5.83334H15.8333M4.16663 10H15.8333M4.16663 14.1667H10.8333"
                                stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                        </svg>
                    </span>
                </div>
            </div>
        @endforeach
    </div>
</section>
