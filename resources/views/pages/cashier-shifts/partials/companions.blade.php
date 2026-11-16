@if (! empty($shift->companion_staff_ids))
    <div class="mt-2 space-y-2">
        <p class="text-xs text-gray-600 dark:text-gray-300">
            Pendamping: {{ $availableStaff->whereIn('id', $shift->companion_staff_ids)->pluck('name')->join(', ') ?: 'Staf tidak tersedia' }}
        </p>
        @if ($canManageShift)
            <form method="POST" action="{{ route('cashier-shifts.companions.destroy', $shift) }}" onsubmit="return confirm(@js('Ubah shift '.($shift->user?->name ?? 'Kasir').' menjadi kerja sendiri? Seluruh pendamping akan dihapus. Transaksi tetap tersimpan.'));">
                @csrf
                @method('DELETE')
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-brand-200 px-3 py-2 text-xs font-semibold text-brand-700 hover:bg-brand-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 dark:border-brand-500/30 dark:text-brand-300 dark:hover:bg-brand-500/10">
                    Hapus Pendamping
                </button>
            </form>
        @endif
    </div>
@endif
