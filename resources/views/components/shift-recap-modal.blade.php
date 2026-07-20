<div x-cloak x-show="shiftRecap" x-transition.opacity.duration.200ms class="fixed inset-0 z-99999 flex items-center justify-center bg-gray-950/50 p-4">
    <div @click.outside="dismissShiftRecap()" x-transition:enter="ease-out duration-200" x-transition:enter-start="translate-y-2 scale-95 opacity-0" x-transition:enter-end="translate-y-0 scale-100 opacity-100" x-transition:leave="ease-in duration-150" x-transition:leave-start="translate-y-0 scale-100 opacity-100" x-transition:leave-end="translate-y-2 scale-95 opacity-0" class="w-full max-w-lg rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xl dark:border-gray-800 dark:bg-gray-900">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Rekap tutup kasir</h2>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400"><span x-text="shiftRecap?.cashier"></span> · <span x-text="shiftRecap?.closedAt"></span></p>
            </div>
            <span class="rounded-full bg-success-50 px-2 py-1 text-[11px] font-semibold text-success-700 dark:bg-success-500/15 dark:text-success-300">Shift selesai</span>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3">
            <template x-for="item in [
                ['Cup / item', shiftRecap?.salesCount || 0, false],
                ['Penjualan', shiftRecap?.netSales || 0, true],
                ['Tunai', shiftRecap?.cashTotal || 0, true],
                ['QRIS', shiftRecap?.qrisTotal || 0, true],
                ['Kartu', shiftRecap?.cardTotal || 0, true],
                ['Total uang laci', shiftRecap?.expectedCashInDrawer || 0, true],
            ]" :key="item[0]">
                <div class="rounded-xl bg-gray-50 p-3 dark:bg-white/[0.04]">
                    <p class="text-[10px] font-medium uppercase text-gray-400" x-text="item[0]"></p>
                    <p class="mt-1 text-sm font-semibold tabular-nums text-gray-800 dark:text-white/90" x-text="item[2] ? shiftRecapRupiah(item[1]) : item[1]"></p>
                </div>
            </template>
        </div>

        <div class="mt-3 rounded-xl border border-gray-200 p-3 text-xs dark:border-gray-800">
            <div class="flex justify-between gap-3"><span class="text-gray-500">Kas awal</span><strong class="tabular-nums" x-text="shiftRecapRupiah(shiftRecap?.openingCashAmount || 0)"></strong></div>
            <div class="mt-1 flex justify-between gap-3"><span class="text-gray-500">Tunai penjualan</span><strong class="tabular-nums" x-text="shiftRecapRupiah(shiftRecap?.cashTotal || 0)"></strong></div>
            <div class="mt-2 flex justify-between gap-3 border-t border-dashed border-gray-200 pt-2 dark:border-gray-700"><span class="font-semibold">Uang seharusnya di laci</span><strong class="tabular-nums" x-text="shiftRecapRupiah(shiftRecap?.expectedCashInDrawer || 0)"></strong></div>
            <p x-show="shiftRecap?.closingNote" class="mt-2 text-gray-500">Catatan: <span x-text="shiftRecap?.closingNote"></span></p>
        </div>

        <div x-show="shiftRecap?.dailyBonus?.staff?.length" class="mt-3 rounded-xl border border-success-200 bg-success-50/60 p-3 dark:border-success-500/20 dark:bg-success-500/10">
            <p class="font-semibold text-success-800 dark:text-success-200">Staff berhak bonus hari ini</p>
            <p class="mt-1 text-success-700 dark:text-success-300" x-text="shiftRecap?.dailyBonus?.staff?.map((staff) => staff.name).join(', ')"></p>
            <p class="mt-1 text-[11px] text-success-700 dark:text-success-300" x-text="`${shiftRecap?.dailyBonus?.staff?.length || 0} orang · Bonus per orang ${shiftRecapRupiah(shiftRecap?.dailyBonus?.bonusPerPerson || 0)}`"></p>
        </div>

        <p x-show="shiftRecapError" class="mt-3 rounded-lg border border-error-200 bg-error-50 px-3 py-2 text-xs text-error-700" x-text="shiftRecapError"></p>
        <div class="mt-5 grid gap-2 sm:grid-cols-2">
            <button type="button" @click="printShiftRecap()" :disabled="shiftRecapPrinting" class="inline-flex h-10 items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-semibold text-white disabled:opacity-60">
                <span x-text="shiftRecapPrinting ? 'Mencetak...' : 'Print Rekap'"></span>
            </button>
            <button type="button" @click="dismissShiftRecap()" class="inline-flex h-10 items-center justify-center rounded-lg border border-gray-200 px-4 text-sm font-semibold text-gray-700 dark:border-gray-800 dark:text-gray-300">Selesai</button>
        </div>
    </div>
</div>
