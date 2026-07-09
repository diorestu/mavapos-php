<div class="flex flex-col h-full bg-white dark:bg-white/[0.03] xl:bg-transparent">
    <!-- Header -->
    <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-gray-800">
        <div>
            <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Keranjang</h2>
            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                Transaksi baru · <span x-text="cart.length"></span> item
            </p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" @click="clearCart()" :disabled="cart.length === 0"
                class="h-7 rounded-lg px-2 text-xs font-semibold text-gray-500 transition hover:bg-gray-50 hover:text-gray-700 disabled:cursor-not-allowed disabled:opacity-40 dark:text-gray-400 dark:hover:bg-white/[0.04] dark:hover:text-gray-200">
                Bersihkan
            </button>
            <!-- Close Button for Mobile Drawer -->
            <button type="button" @click="showMobileCart = false" class="xl:hidden p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:bg-white/[0.04] rounded-lg">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>

    <!-- Items List -->
    <div class="{{ $maxHeightClass ?? 'max-h-[420px]' }} space-y-2 overflow-y-auto p-3 custom-scrollbar flex-1">
        <template x-for="item in cart" :key="item.id">
            <div class="rounded-xl border border-gray-200 p-2.5 dark:border-gray-800 bg-gray-50/50 dark:bg-transparent">
                <div class="flex items-center gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-xs font-bold leading-5 text-gray-800 dark:text-white/90" x-text="item.name"></p>
                        <p class="truncate text-[10px] leading-4 text-gray-500 dark:text-gray-400">
                            <span x-text="item.sku"></span>
                            <span class="mx-1">·</span>
                            <span x-text="formatRupiah(item.price)"></span>
                            <span class="mx-1">·</span>
                            <span class="font-semibold text-brand-600 dark:text-brand-400" x-text="`Subtotal: ${formatRupiah(item.price * item.quantity)}`"></span>
                        </p>
                    </div>
                    <div class="inline-flex shrink-0 items-center rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950">
                        <button type="button" @click="decrease(item.id)" aria-label="Kurangi jumlah"
                            class="grid h-7 w-7 place-items-center text-gray-600 transition hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-white/[0.04]">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M5 10H15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                            </svg>
                        </button>
                        <span class="min-w-7 text-center text-xs font-bold tabular-nums text-gray-800 dark:text-white/90" x-text="item.quantity"></span>
                        <button type="button" @click="increase(item.id)" :disabled="item.quantity >= item.stock" aria-label="Tambah jumlah"
                            class="grid h-7 w-7 place-items-center text-gray-600 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 dark:text-gray-400 dark:hover:bg-white/[0.04]">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M10 5V15M5 10H15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                            </svg>
                        </button>
                    </div>
                    <button type="button" @click="remove(item.id)" aria-label="Hapus item"
                        class="grid h-7 w-7 shrink-0 place-items-center rounded-lg text-gray-400 transition hover:bg-error-50 hover:text-error-600 dark:hover:bg-error-500/10">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M5 5L15 15M15 5L5 15" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                        </svg>
                    </button>
                </div>
            </div>
        </template>

        <div x-show="cart.length === 0" class="rounded-lg border border-dashed border-gray-300 p-6 text-center dark:border-gray-700">
            <p class="text-xs font-semibold text-gray-700 dark:text-gray-300">Keranjang kosong.</p>
            <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">Pilih produk dari daftar untuk memulai transaksi.</p>
        </div>
    </div>

    <!-- Totals & Payment Section -->
    <div class="border-t border-gray-100 p-4 dark:border-gray-800 bg-white dark:bg-gray-900/60 sticky bottom-0">
        <div class="space-y-2">
            <div class="flex items-center justify-between text-xs">
                <span class="text-gray-500 dark:text-gray-400">Subtotal</span>
                <span class="font-bold tabular-nums text-gray-800 dark:text-white/90" x-text="formatRupiah(subtotal)"></span>
            </div>
            <label class="flex items-center justify-between gap-3 text-xs">
                <span class="text-gray-500 dark:text-gray-400">Diskon</span>
                <input type="text" inputmode="numeric" autocomplete="off" :value="formatInputNumber(discount)" @input="onMoneyInput('discount', $event)" placeholder="0"
                    class="h-8 w-28 rounded-lg border border-gray-300 bg-transparent px-2.5 text-right text-xs tabular-nums text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
            </label>
            <div class="flex items-center justify-between border-t border-gray-100 pt-2 dark:border-gray-800">
                <span class="text-sm font-semibold text-gray-800 dark:text-white/90">Total</span>
                <span class="text-lg font-bold tabular-nums text-gray-900 dark:text-white" x-text="formatRupiah(total)"></span>
            </div>
        </div>

        <div class="mt-3.5 grid grid-cols-3 gap-2">
            <button type="button" @click="paymentMethod = 'cash'"
                class="h-9 rounded-lg text-xs font-semibold transition"
                :class="paymentMethod === 'cash' ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'border border-gray-200 text-gray-600 hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.04]'">
                Tunai
            </button>
            <button type="button" @click="paymentMethod = 'qris'; paidAmount = ''"
                class="h-9 rounded-lg text-xs font-semibold transition"
                :class="paymentMethod === 'qris' ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'border border-gray-200 text-gray-600 hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.04]'">
                QRIS
            </button>
            <button type="button" @click="paymentMethod = 'card'; paidAmount = ''"
                class="h-9 rounded-lg text-xs font-semibold transition"
                :class="paymentMethod === 'card' ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'border border-gray-200 text-gray-600 hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.04]'">
                Kartu
            </button>
        </div>

        <div x-show="paymentMethod === 'cash'" class="mt-3.5 space-y-2 animate-fadeIn">
            <label class="block">
                <span class="mb-1 block text-[10px] font-semibold text-gray-500 dark:text-gray-400">Uang diterima</span>
                <input type="text" inputmode="numeric" autocomplete="off" :value="formatInputNumber(paidAmount)" @input="onMoneyInput('paidAmount', $event)" placeholder="0"
                    class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-right text-sm font-semibold tabular-nums text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
            </label>
            <div class="flex items-center justify-between">
                <button type="button" @click="payExact()" class="h-7 rounded-lg border border-gray-200 px-2.5 text-[11px] font-semibold text-gray-600 transition hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.04]">Uang pas</button>
                <p class="text-[11px] text-gray-500 dark:text-gray-400">
                    <span x-show="remaining > 0">Kurang <span class="font-bold text-error-600" x-text="formatRupiah(remaining)"></span></span>
                    <span x-show="remaining === 0">Kembali <span class="font-bold text-success-600" x-text="formatRupiah(change)"></span></span>
                </p>
            </div>
        </div>

        <p x-show="checkoutError" class="mt-3.5 rounded-lg border border-error-200 bg-error-50 px-3 py-2 text-xs text-error-700 dark:border-error-500/20 dark:bg-error-500/10 dark:text-error-300" x-text="checkoutError"></p>

        <button type="button" @click="checkout()" :disabled="!canCheckout"
            class="mt-3.5 inline-flex h-11 w-full items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-semibold text-white shadow-theme-xs transition hover:bg-brand-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30 disabled:cursor-not-allowed disabled:opacity-50">
            <span x-show="!checkoutLoading">Selesaikan Pembayaran</span>
            <span x-show="checkoutLoading">Memproses...</span>
        </button>
    </div>
</div>
