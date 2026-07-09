@extends('layouts.app')

{{-- Hallmark · pre-emit critique: P5 H4 E4 S5 R5 V4 --}}
{{-- Hallmark · genre: modern-minimal · macrostructure: Workbench · theme: existing TailAdmin tokens · enrichment: none · nav: app-sidebar · footer: none · tone: utilitarian --}}

@section('content')
    <div x-data="posManager(
        @js($items),
        @js($categories),
        @js($activeShift),
        @js($blockingShift),
        {
            startShift: @js(route('pos.shift.start')),
            closeShift: @js(route('pos.shift.close')),
            checkout: @js(route('pos.checkout')),
        }
    )" class="space-y-4">
        <div class="hidden xl:flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div class="min-w-0">
                <nav aria-label="Breadcrumb">
                    <ol class="flex items-center gap-1 text-[10px] text-gray-500 dark:text-gray-400">
                        <li><a href="{{ url('/') }}" class="transition hover:text-gray-700 dark:hover:text-gray-200">Home</a></li>
                        <li aria-hidden="true">
                            <svg class="h-3 w-3 stroke-current" viewBox="0 0 17 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M6.0765 12.667L10.2432 8.50033L6.0765 4.33366" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </li>
                        <li class="font-medium text-gray-700 dark:text-gray-300">Kasir</li>
                    </ol>
                </nav>
                <h1 class="mt-1 text-xl font-semibold text-gray-800 dark:text-white/90">Kasir</h1>
                <p class="mt-1 max-w-2xl text-xs text-gray-500 dark:text-gray-400">Cari produk, tambah ke keranjang, pilih pembayaran, selesai. Semua aksi utama tetap terlihat.</p>
            </div>

            <div class="grid grid-cols-3 gap-2 sm:w-[420px]">
                <div class="rounded-lg border border-gray-200 bg-white px-3 py-2 dark:border-gray-800 dark:bg-white/[0.03]">
                    <p class="text-[10px] font-medium uppercase text-gray-400">Produk</p>
                    <p class="mt-0.5 text-sm font-semibold tabular-nums text-gray-800 dark:text-white/90" x-text="items.length"></p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white px-3 py-2 dark:border-gray-800 dark:bg-white/[0.03]">
                    <p class="text-[10px] font-medium uppercase text-gray-400">Item</p>
                    <p class="mt-0.5 text-sm font-semibold tabular-nums text-gray-800 dark:text-white/90" x-text="cart.length"></p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white px-3 py-2 dark:border-gray-800 dark:bg-white/[0.03]">
                    <p class="text-[10px] font-medium uppercase text-gray-400">Total</p>
                    <p class="mt-0.5 truncate text-sm font-semibold tabular-nums text-gray-800 dark:text-white/90" x-text="formatRupiah(total)"></p>
                </div>
            </div>
        </div>

        <section class="grid gap-3 rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-white/[0.03] lg:grid-cols-[minmax(0,1fr)_auto]">
            <div>
                <p class="text-[10px] font-medium uppercase text-gray-400">Shift Kasir</p>
                <template x-if="shift">
                    <div class="mt-1 flex flex-wrap items-center gap-2">
                        <span class="rounded-full bg-success-50 px-2.5 py-1 text-xs font-semibold text-success-700 dark:bg-success-500/15 dark:text-success-400">Aktif</span>
                        <span class="text-sm font-semibold text-gray-800 dark:text-white/90" x-text="shift.cashier"></span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">mulai <span x-text="shift.openedAt"></span></span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">· <span x-text="shift.salesCount"></span> transaksi</span>
                        <span class="text-xs font-semibold tabular-nums text-gray-800 dark:text-white/90" x-text="formatRupiah(shift.netSales)"></span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">Kas awal <span class="font-semibold tabular-nums text-gray-800 dark:text-white/90" x-text="formatRupiah(shift.openingCashAmount || 0)"></span></span>
                    </div>
                </template>
                <template x-if="!shift && blockingShift">
                    <p class="mt-1 text-sm text-warning-700 dark:text-warning-300">
                        Kasir <span class="font-semibold" x-text="blockingShift.cashier"></span> masih aktif sejak <span x-text="blockingShift.openedAt"></span>. Tutup kasir tersebut sebelum shift baru.
                    </p>
                </template>
                <template x-if="!shift && !blockingShift">
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Belum mulai pekerjaan. Konfirmasi mulai shift sebelum transaksi.</p>
                </template>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" @click="sopModal = true"
                    class="inline-flex h-10 items-center justify-center rounded-lg border border-gray-200 px-4 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.04]">
                    SOP Kasir
                </button>
                <button type="button" x-show="!shift && !blockingShift" @click="startModal = true"
                    class="inline-flex h-10 items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-semibold text-white shadow-theme-xs transition hover:bg-brand-600">
                    Mulai Pekerjaan
                </button>
                <button type="button" x-show="shift" @click="closeModal = true"
                    class="inline-flex h-10 items-center justify-center rounded-lg border border-error-200 px-4 text-sm font-semibold text-error-600 transition hover:bg-error-50 dark:border-error-500/30 dark:hover:bg-error-500/10">
                    Tutup Kasir
                </button>
            </div>
        </section>

        <section class="relative grid min-w-0 gap-4 xl:grid-cols-[minmax(0,1fr)_350px]">
            <div x-show="!shift" class="absolute inset-0 z-10 rounded-xl bg-white/75 backdrop-blur-sm dark:bg-gray-950/75"></div>
            <div class="min-w-0 space-y-4">
                <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="grid gap-3 border-b border-gray-100 p-3 dark:border-gray-800 lg:grid-cols-[minmax(0,1fr)_auto]">
                        <label class="relative block min-w-0">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M9.16667 15.8333C12.8486 15.8333 15.8333 12.8486 15.8333 9.16667C15.8333 5.48477 12.8486 2.5 9.16667 2.5C5.48477 2.5 2.5 5.48477 2.5 9.16667C2.5 12.8486 5.48477 15.8333 9.16667 15.8333Z" stroke="currentColor" stroke-width="1.5" />
                                    <path d="M14.1666 14.1667L17.5 17.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                </svg>
                            </span>
                            <input x-model.debounce.200ms="query" type="search" autocomplete="off" placeholder="Cari nama produk, SKU, atau barcode"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent py-2 pl-9 pr-3 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                        </label>

                        <div class="flex min-w-0 gap-2 overflow-x-auto pb-0.5 custom-scrollbar lg:max-w-[520px]">
                            <button type="button" @click="activeCategory = ''"
                                class="h-10 shrink-0 rounded-lg px-3 text-xs font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/20"
                                :class="activeCategory === '' ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-white/[0.03]'">
                                Semua
                            </button>
                            <template x-for="category in categories" :key="category.code">
                                <button type="button" @click="activeCategory = category.code"
                                    class="h-10 shrink-0 rounded-lg px-3 text-xs font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/20"
                                    :class="activeCategory === category.code ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-white/[0.03]'"
                                    x-text="category.name"></button>
                            </template>
                        </div>
                    </div>

                    <div class="p-3">
                        <div x-show="favoriteItems.length > 0" class="mb-3">
                            <div class="mb-2 flex items-center justify-between">
                                <h2 class="text-xs font-semibold text-gray-700 dark:text-gray-300">Favorit</h2>
                                <p class="text-[11px] text-gray-400">Tap untuk tambah</p>
                            </div>
                            <div class="grid grid-cols-2 gap-2 md:grid-cols-4">
                                <template x-for="item in favoriteItems" :key="`favorite-${item.id}`">
                                    <button type="button" @click="addItem(item)"
                                        class="min-h-16 rounded-lg border border-gray-200 bg-gray-50 p-2 text-left transition hover:border-brand-200 hover:bg-brand-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/20 dark:border-gray-800 dark:bg-gray-900/70 dark:hover:border-brand-500/40 dark:hover:bg-brand-500/10">
                                        <span class="block truncate text-xs font-semibold text-gray-800 dark:text-white/90" x-text="item.name"></span>
                                        <span class="mt-1 block text-xs font-semibold tabular-nums text-brand-600 dark:text-brand-400" x-text="formatRupiah(item.price)"></span>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                            <template x-for="item in filteredItems" :key="item.id">
                                <button type="button" @click="addItem(item)"
                                    class="group min-h-[116px] rounded-lg border border-gray-200 bg-white p-3 text-left transition hover:-translate-y-0.5 hover:border-brand-200 hover:shadow-theme-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/20 dark:border-gray-800 dark:bg-gray-900 dark:hover:border-brand-500/40">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="line-clamp-2 text-[13px] font-semibold leading-5 text-gray-800 dark:text-white/90" x-text="item.name"></p>
                                            <p class="mt-1 truncate text-[11px] text-gray-500 dark:text-gray-400">
                                                <span x-text="item.sku"></span>
                                                <span class="mx-1">·</span>
                                                <span x-text="item.type"></span>
                                            </p>
                                        </div>
                                        <span class="shrink-0 rounded-md bg-gray-50 px-2 py-1 text-[10px] font-medium text-gray-500 dark:bg-white/[0.05] dark:text-gray-400" x-text="item.categoryName"></span>
                                    </div>
                                    <div class="mt-4 flex items-end justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold tabular-nums text-gray-900 dark:text-white" x-text="formatRupiah(item.price)"></p>
                                            <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400" x-text="stockLabel(item)"></p>
                                        </div>
                                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-gray-900 text-white transition group-hover:bg-brand-500 dark:bg-white dark:text-gray-900 dark:group-hover:bg-brand-400">
                                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M10 4.16667V15.8333M4.16663 10H15.8333" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                            </svg>
                                        </span>
                                    </div>
                                </button>
                            </template>

                            <div x-show="filteredItems.length === 0" class="col-span-full rounded-lg border border-dashed border-gray-300 p-8 text-center dark:border-gray-700">
                                <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">Produk tidak ditemukan.</p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Periksa nama, SKU, barcode, atau filter kategori.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <aside class="min-w-0 xl:sticky xl:top-24 xl:h-fit">
                <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="flex items-center justify-between border-b border-gray-100 px-3 py-2.5 dark:border-gray-800">
                        <div>
                            <h2 class="text-[13px] font-semibold text-gray-800 dark:text-white/90">Keranjang</h2>
                            <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">
                                Transaksi baru · <span x-text="cart.length"></span> item
                            </p>
                        </div>
                        <button type="button" @click="clearCart()" :disabled="cart.length === 0"
                            class="h-7 rounded-lg px-2.5 text-[11px] font-semibold text-gray-500 transition hover:bg-gray-50 hover:text-gray-700 disabled:cursor-not-allowed disabled:opacity-40 dark:text-gray-400 dark:hover:bg-white/[0.04] dark:hover:text-gray-200">
                            Bersihkan
                        </button>
                    </div>

                    <div class="max-h-[420px] space-y-1.5 overflow-y-auto p-2 custom-scrollbar">
                        <template x-for="item in cart" :key="item.id">
                            <div class="rounded-lg border border-gray-200 px-2 py-1.5 dark:border-gray-800">
                                <div class="flex items-center gap-2">
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-xs font-semibold leading-4 text-gray-800 dark:text-white/90" x-text="item.name"></p>
                                        <p class="truncate text-[10px] leading-4 text-gray-500 dark:text-gray-400">
                                            <span x-text="item.sku"></span>
                                            <span class="mx-1">·</span>
                                            <span x-text="formatRupiah(item.price)"></span>
                                        </p>
                                    </div>
                                    <div class="inline-flex shrink-0 items-center rounded-lg border border-gray-200 dark:border-gray-800">
                                        <button type="button" @click="decrease(item.id)" aria-label="Kurangi jumlah"
                                            class="grid h-7 w-7 place-items-center text-gray-600 transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/20 dark:text-gray-300 dark:hover:bg-white/[0.04]">
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M5 10H15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                            </svg>
                                        </button>
                                        <span class="min-w-7 text-center text-xs font-semibold tabular-nums text-gray-800 dark:text-white/90" x-text="item.quantity"></span>
                                        <button type="button" @click="increase(item.id)" :disabled="item.quantity >= item.stock" aria-label="Tambah jumlah"
                                            class="grid h-7 w-7 place-items-center text-gray-600 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/20 dark:text-gray-300 dark:hover:bg-white/[0.04]">
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M10 5V15M5 10H15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                            </svg>
                                        </button>
                                    </div>
                                    <button type="button" @click="remove(item.id)" aria-label="Hapus item"
                                        class="grid h-7 w-7 shrink-0 place-items-center rounded-lg text-gray-400 transition hover:bg-error-50 hover:text-error-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-error-500/20 dark:hover:bg-error-500/10">
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M5 5L15 15M15 5L5 15" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </template>

                        <div x-show="cart.length === 0" class="rounded-lg border border-dashed border-gray-300 p-4 text-center dark:border-gray-700">
                            <p class="text-[13px] font-semibold text-gray-700 dark:text-gray-300">Keranjang kosong.</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Pilih produk dari daftar untuk memulai transaksi.</p>
                        </div>
                    </div>

                    <div class="border-t border-gray-100 p-3 dark:border-gray-800">
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-gray-500 dark:text-gray-400">Subtotal</span>
                                <span class="font-semibold tabular-nums text-gray-800 dark:text-white/90" x-text="formatRupiah(subtotal)"></span>
                            </div>
                            <label class="flex items-center justify-between gap-3 text-xs">
                                <span class="text-gray-500 dark:text-gray-400">Diskon</span>
                                <input type="text" inputmode="numeric" autocomplete="off" :value="formatInputNumber(discount)" @input="onMoneyInput('discount', $event)" placeholder="0"
                                    class="h-8 w-28 rounded-lg border border-gray-300 bg-transparent px-2.5 text-right text-xs tabular-nums text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                            </label>
                            <div class="flex items-center justify-between border-t border-gray-100 pt-2 dark:border-gray-800">
                                <span class="text-sm font-semibold text-gray-800 dark:text-white/90">Total</span>
                                <span class="text-lg font-semibold tabular-nums text-gray-900 dark:text-white" x-text="formatRupiah(total)"></span>
                            </div>
                        </div>

                        <div class="mt-3 grid grid-cols-3 gap-1.5">
                            <button type="button" @click="paymentMethod = 'cash'"
                                class="h-9 rounded-lg text-xs font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/20"
                                :class="paymentMethod === 'cash' ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'border border-gray-200 text-gray-600 hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.04]'">
                                Tunai
                            </button>
                            <button type="button" @click="paymentMethod = 'qris'; paidAmount = ''"
                                class="h-9 rounded-lg text-xs font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/20"
                                :class="paymentMethod === 'qris' ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'border border-gray-200 text-gray-600 hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.04]'">
                                QRIS
                            </button>
                            <button type="button" @click="paymentMethod = 'card'; paidAmount = ''"
                                class="h-9 rounded-lg text-xs font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/20"
                                :class="paymentMethod === 'card' ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'border border-gray-200 text-gray-600 hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.04]'">
                                Kartu
                            </button>
                        </div>

                        <div x-show="paymentMethod === 'cash'" class="mt-2.5 space-y-1.5">
                            <label class="block">
                                <span class="mb-1 block text-[11px] font-medium text-gray-600 dark:text-gray-400">Uang diterima</span>
                                <input type="text" inputmode="numeric" autocomplete="off" :value="formatInputNumber(paidAmount)" @input="onMoneyInput('paidAmount', $event)" placeholder="0"
                                    class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-2.5 text-right text-sm tabular-nums text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                            </label>
                            <div class="flex items-center justify-between">
                                <button type="button" @click="payExact()" class="h-7 rounded-lg border border-gray-200 px-2.5 text-[11px] font-semibold text-gray-600 transition hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.04]">Uang pas</button>
                                <p class="text-[11px] text-gray-500 dark:text-gray-400">
                                    <span x-show="remaining > 0">Kurang <span class="font-semibold text-error-600" x-text="formatRupiah(remaining)"></span></span>
                                    <span x-show="remaining === 0">Kembali <span class="font-semibold text-success-600" x-text="formatRupiah(change)"></span></span>
                                </p>
                            </div>
                        </div>

                        <p x-show="checkoutError" class="mt-3 rounded-lg border border-error-200 bg-error-50 px-3 py-2 text-xs text-error-700 dark:border-error-500/20 dark:bg-error-500/10 dark:text-error-300" x-text="checkoutError"></p>

                        <button type="button" @click="checkout()" :disabled="!canCheckout"
                            class="mt-3 inline-flex h-11 w-full items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-semibold text-white shadow-theme-xs transition hover:bg-brand-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30 disabled:cursor-not-allowed disabled:opacity-50">
                            <span x-show="!checkoutLoading">Selesaikan Pembayaran</span>
                            <span x-show="checkoutLoading">Memproses...</span>
                        </button>
                    </div>
                </div>
            </aside>
        </section>

        <div x-cloak x-show="sopModal" class="fixed inset-0 z-99999 flex items-center justify-center bg-gray-950/50 p-4">
            <div @click.outside="closeSopModal()" class="w-full max-w-2xl rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xl dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-start gap-3">
                    <div class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-warning-300">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M10 7.5V10.8333" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                            <path d="M10 13.75H10.0083" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                            <path d="M8.57499 3.2167C9.19999 2.1167 10.8 2.1167 11.425 3.2167L18.1 14.975C18.725 16.075 17.925 17.45 16.675 17.45H3.32499C2.07499 17.45 1.27499 16.075 1.89999 14.975L8.57499 3.2167Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Pengingat SOP Kasir</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Baca poin berikut sebelum membuka atau menutup kasir.</p>
                    </div>
                </div>

                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.04]">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Saat buka kasir</h3>
                        <ul class="mt-3 list-disc space-y-2 pl-5 text-sm leading-6 text-gray-600 dark:text-gray-300">
                            <li>Pastikan nama kasir yang login sudah sesuai.</li>
                            <li>Hitung modal awal dan cocokkan dengan catatan shift.</li>
                            <li>Pastikan printer, laci kas, dan koneksi pembayaran siap digunakan.</li>
                            <li>Periksa produk, harga, dan stok penting sebelum transaksi pertama.</li>
                            <li>Catat kendala awal shift pada kolom catatan bila ada.</li>
                        </ul>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.04]">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Saat tutup kasir</h3>
                        <ul class="mt-3 list-disc space-y-2 pl-5 text-sm leading-6 text-gray-600 dark:text-gray-300">
                            <li>Pastikan semua transaksi sudah selesai dan tidak ada pembayaran tertunda.</li>
                            <li>Cocokkan uang tunai, QRIS, dan kartu dengan ringkasan pendapatan shift.</li>
                            <li>Simpan atau cetak laporan penjualan bila diperlukan.</li>
                            <li>Rapikan bukti pembayaran, struk, dan catatan koreksi transaksi.</li>
                            <li>Isi catatan tutup kasir sebelum menekan tombol tutup kasir.</li>
                        </ul>
                    </div>
                </div>

                <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" @click="closeSopModal()"
                        class="inline-flex h-10 items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-semibold text-white shadow-theme-xs transition hover:bg-brand-600">
                        Saya mengerti
                    </button>
                </div>
            </div>
        </div>

        <div x-cloak x-show="startModal" class="fixed inset-0 z-99999 flex items-center justify-center bg-gray-950/50 p-4">
            <div @click.outside="shift ? startModal = false : null" class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xl dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-start gap-3">
                    <div class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-500/15">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M10 4V10L14 12" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M18 10A8 8 0 1 1 2 10A8 8 0 0 1 18 10Z" stroke="currentColor" stroke-width="1.7" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Mulai pekerjaan kasir?</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Shift akan dicatat sebagai absensi mulai kerja. Transaksi POS baru bisa dilakukan setelah shift aktif.</p>
                    </div>
                </div>

                <label class="mt-4 block">
                    <span class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Uang kas awal untuk kembalian</span>
                    <input type="text" inputmode="numeric" autocomplete="off" :value="formatInputNumber(openingCashAmount)" @input="onMoneyInput('openingCashAmount', $event)" placeholder="0"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-right text-sm tabular-nums text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-950 dark:text-white/90" />
                    <span class="mt-1 block text-[11px] text-gray-500 dark:text-gray-400">Nominal cash yang disiapkan di laci kas untuk uang kembalian.</span>
                </label>

                <label class="mt-4 block">
                    <span class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Catatan awal shift (opsional)</span>
                    <textarea x-model="openingNote" rows="3" class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-950 dark:text-white/90"></textarea>
                </label>
                <p x-show="shiftError" class="mt-3 rounded-lg border border-error-200 bg-error-50 px-3 py-2 text-xs text-error-700 dark:border-error-500/20 dark:bg-error-500/10 dark:text-error-300" x-text="shiftError"></p>

                <div class="mt-5 grid gap-2 sm:grid-cols-2">
                    <button type="button" @click="startShift()" :disabled="shiftLoading"
                        class="inline-flex h-10 items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-semibold text-white shadow-theme-xs transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60">
                        <span x-show="!shiftLoading">Ya, mulai kerja</span>
                        <span x-show="shiftLoading">Memulai...</span>
                    </button>
                    <a href="{{ route('dashboard') }}" class="inline-flex h-10 items-center justify-center rounded-lg border border-gray-200 px-4 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.04]">
                        Tidak, kembali
                    </a>
                </div>
            </div>
        </div>

        <div x-cloak x-show="closeModal" class="fixed inset-0 z-99999 flex items-center justify-center bg-gray-950/50 p-4">
            <div @click.outside="closeModal = false" class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xl dark:border-gray-800 dark:bg-gray-900">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Tutup kasir?</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Setelah ditutup, kasir berikutnya bisa memulai shift baru. Pastikan semua pembayaran sudah selesai.</p>
                <div class="mt-4 grid grid-cols-2 gap-2">
                    <div class="rounded-lg bg-gray-50 p-3 dark:bg-white/[0.04]">
                        <p class="text-[10px] uppercase text-gray-400">Transaksi</p>
                        <p class="mt-1 text-sm font-semibold text-gray-800 dark:text-white/90" x-text="shift?.salesCount || 0"></p>
                    </div>
                    <div class="rounded-lg bg-gray-50 p-3 dark:bg-white/[0.04]">
                        <p class="text-[10px] uppercase text-gray-400">Pendapatan</p>
                        <p class="mt-1 text-sm font-semibold tabular-nums text-gray-800 dark:text-white/90" x-text="formatRupiah(shift?.netSales || 0)"></p>
                    </div>
                    <div class="rounded-lg bg-gray-50 p-3 dark:bg-white/[0.04]">
                        <p class="text-[10px] uppercase text-gray-400">Kas Awal</p>
                        <p class="mt-1 text-sm font-semibold tabular-nums text-gray-800 dark:text-white/90" x-text="formatRupiah(shift?.openingCashAmount || 0)"></p>
                    </div>
                    <div class="rounded-lg bg-gray-50 p-3 dark:bg-white/[0.04]">
                        <p class="text-[10px] uppercase text-gray-400">Kas Tunai</p>
                        <p class="mt-1 text-sm font-semibold tabular-nums text-gray-800 dark:text-white/90" x-text="formatRupiah(shift?.cashInDrawer || 0)"></p>
                    </div>
                </div>
                <label class="mt-4 block">
                    <span class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Catatan tutup kasir (opsional)</span>
                    <textarea x-model="closingNote" rows="3" class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-950 dark:text-white/90"></textarea>
                </label>
                <p x-show="shiftError" class="mt-3 rounded-lg border border-error-200 bg-error-50 px-3 py-2 text-xs text-error-700 dark:border-error-500/20 dark:bg-error-500/10 dark:text-error-300" x-text="shiftError"></p>

                <div class="mt-5 grid gap-2 sm:grid-cols-2">
                    <button type="button" @click="closeShift()" :disabled="shiftLoading"
                        class="inline-flex h-10 items-center justify-center rounded-lg bg-error-600 px-4 text-sm font-semibold text-white shadow-theme-xs transition hover:bg-error-700 disabled:cursor-not-allowed disabled:opacity-60">
                        <span x-show="!shiftLoading">Tutup kasir</span>
                        <span x-show="shiftLoading">Menutup...</span>
                    </button>
                    <button type="button" @click="closeModal = false" class="inline-flex h-10 items-center justify-center rounded-lg border border-gray-200 px-4 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.04]">
                        Batal
                    </button>
                </div>
            </div>
        </div>

        <div x-cloak x-show="receiptModal" class="fixed inset-0 z-99999 flex items-center justify-center bg-gray-950/50 p-4">
            <div @click.outside="closeReceiptModal()" class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xl dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-start gap-3">
                    <div class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-400">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M6.25 10.4167L8.75 12.9167L13.75 7.08333" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M18 10A8 8 0 1 1 2 10A8 8 0 0 1 18 10Z" stroke="currentColor" stroke-width="1.6" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Pembayaran selesai</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Transaksi <span class="font-semibold text-gray-800 dark:text-white/90" x-text="lastReceipt?.invoice_number"></span> berhasil dibuat. Cetak nota sekarang?
                        </p>
                    </div>
                </div>

                <div class="mt-4 rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-white/[0.04]">
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div>
                            <p class="text-[10px] font-medium uppercase text-gray-400">Kasir</p>
                            <p class="mt-1 truncate font-semibold text-gray-800 dark:text-white/90" x-text="lastReceipt?.cashier || '-'"></p>
                        </div>
                        <div>
                            <p class="text-[10px] font-medium uppercase text-gray-400">Pembayaran</p>
                            <p class="mt-1 font-semibold text-gray-800 dark:text-white/90" x-text="paymentLabel(lastReceipt?.payment_method)"></p>
                        </div>
                        <div>
                            <p class="text-[10px] font-medium uppercase text-gray-400">Total</p>
                            <p class="mt-1 font-semibold tabular-nums text-gray-900 dark:text-white" x-text="formatRupiah(lastReceipt?.total || 0)"></p>
                        </div>
                        <div>
                            <p class="text-[10px] font-medium uppercase text-gray-400">Kembalian</p>
                            <p class="mt-1 font-semibold tabular-nums text-success-700 dark:text-success-400" x-text="formatRupiah(lastReceipt?.change_amount || 0)"></p>
                        </div>
                    </div>

                    <div class="mt-3 max-h-40 space-y-1.5 overflow-y-auto custom-scrollbar">
                        <template x-for="item in lastReceipt?.items || []" :key="`${lastReceipt?.invoice_number}-${item.sku}-${item.name}`">
                            <div class="flex items-start justify-between gap-3 rounded-lg bg-white px-2.5 py-2 text-xs dark:bg-gray-950/50">
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-gray-800 dark:text-white/90" x-text="item.name"></p>
                                    <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">
                                        <span x-text="item.quantity"></span>
                                        <span>x</span>
                                        <span x-text="formatRupiah(item.unit_price)"></span>
                                    </p>
                                </div>
                                <p class="shrink-0 font-semibold tabular-nums text-gray-900 dark:text-white" x-text="formatRupiah(item.line_total)"></p>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="mt-4 grid gap-2 rounded-xl border border-gray-200 bg-white p-3 text-xs dark:border-gray-800 dark:bg-gray-950/40 sm:grid-cols-2">
                    <label class="flex cursor-pointer items-center gap-2 text-gray-600 dark:text-gray-300">
                        <input type="checkbox" x-model="printPreferences.autoPrint" @change="savePrintPreferences()"
                            class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900" />
                        <span>Auto-print setelah checkout</span>
                    </label>
                    <label class="flex cursor-pointer items-center gap-2 text-gray-600 dark:text-gray-300">
                        <input type="checkbox" x-model="printPreferences.closeAfterPrint" @change="savePrintPreferences()"
                            class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900" />
                        <span>Tutup popup setelah print</span>
                    </label>
                </div>

                <div class="mt-5 grid gap-2 sm:grid-cols-2">
                    <button type="button" @click="printReceipt()"
                        class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-brand-500 px-4 text-sm font-semibold text-white shadow-theme-xs transition hover:bg-brand-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M5 7V3.75C5 3.33579 5.33579 3 5.75 3H14.25C14.6642 3 15 3.33579 15 3.75V7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M5.83337 14.1667H4.16671C3.24623 14.1667 2.50004 13.4205 2.50004 12.5V8.33333C2.50004 7.41286 3.24623 6.66667 4.16671 6.66667H15.8334C16.7538 6.66667 17.5 7.41286 17.5 8.33333V12.5C17.5 13.4205 16.7538 14.1667 15.8334 14.1667H14.1667" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M6.66663 11.6667H13.3333V17H6.66663V11.6667Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
                        </svg>
                        Print Nota
                    </button>
                    <button type="button" @click="closeReceiptModal()" class="inline-flex h-10 items-center justify-center rounded-lg border border-gray-200 px-4 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.04]">
                        Lewati
                    </button>
                </div>
            </div>
        </div>

        <!-- Variant Selection Modal -->
        <div x-cloak x-show="variantModal" class="fixed inset-0 z-99999 flex items-center justify-center bg-gray-950/50 p-4">
            <div @click.outside="variantModal = false; variantProduct = null" class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xl dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between border-b border-gray-100 pb-3 dark:border-gray-800">
                    <h3 class="text-base font-bold text-gray-900 dark:text-white" x-text="variantProduct ? variantProduct.name : ''"></h3>
                    <button type="button" @click="variantModal = false; variantProduct = null" class="text-gray-400 hover:text-gray-500">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M15 5L5 15M5 5L15 15" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                        </svg>
                    </button>
                </div>
                
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Pilih varian produk yang tersedia:</p>

                <div class="mt-3 max-h-60 space-y-2 overflow-y-auto custom-scrollbar">
                    <template x-for="v in (variantProduct ? variantProduct.variants : [])" :key="v.id">
                        <button type="button" @click="addVariant(v)"
                            :disabled="Number(v.stock || 0) <= 0"
                            :class="Number(v.stock || 0) <= 0 ? 'opacity-40 cursor-not-allowed bg-gray-50 dark:bg-gray-900/50' : 'hover:border-brand-200 hover:bg-brand-50/50 dark:hover:border-brand-500/40'"
                            class="flex w-full items-center justify-between rounded-xl border border-gray-200 bg-white p-3 text-left transition dark:border-gray-800 dark:bg-gray-950/50">
                            <div>
                                <span class="block text-xs font-semibold text-gray-800 dark:text-white/90" x-text="v.variant_name || (v.name.includes(' · ') ? v.name.split(' · ').slice(1).join(' · ') : v.name)"></span>
                                <span class="mt-0.5 block text-[10px]" :class="Number(v.stock || 0) <= 0 ? 'text-error-500 font-medium' : 'text-gray-500 dark:text-gray-400'" x-text="Number(v.stock || 0) <= 0 ? 'Habis' : `Stok: ${v.stock}`"></span>
                            </div>
                            <span class="text-xs font-bold" :class="Number(v.stock || 0) <= 0 ? 'text-gray-400 dark:text-gray-600 line-through font-normal' : 'text-brand-600 dark:text-brand-400'" x-text="formatRupiah(v.price)"></span>
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </div>
@endsection
