@extends('layouts.app')

{{-- Hallmark · pre-emit critique: P5 H4 E4 S5 R5 V4 --}}
{{-- Hallmark · genre: modern-minimal · macrostructure: Workbench · theme: existing TailAdmin tokens · enrichment: none · nav: app-sidebar · footer: none · tone: utilitarian --}}

@section('content')
    <div x-data="posManager(
        @js($items),
        @js($categories),
        @js($activeShift),
        @js($blockingShift),
        @js($lastClosedShift),
        @js($cashierSopHtml),
        @js($availableStaff),
        {
            startShift: @js(route('pos.shift.start')),
            changeShift: @js(route('pos.shift.change')),
            closeShift: @js(route('pos.shift.close')),
            checkout: @js(route('pos.checkout')),
            displayPush: @js(route('display.push')),
            displayStand: @js(route('display.stand')),
        }
    )" class="space-y-4">
        <div class="hidden">
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

        <section x-data="{ mobileShiftOpen: false }" class="grid gap-3 rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-white/[0.03] lg:grid-cols-[minmax(0,1fr)_auto]">
            <button type="button" @click="mobileShiftOpen = !mobileShiftOpen" class="flex items-center justify-between text-left lg:hidden">
                <span><span class="block text-[10px] font-medium uppercase text-gray-400">Shift Kasir</span><span class="mt-0.5 block text-sm font-semibold text-gray-800 dark:text-white/90" x-text="shift ? `${shift.cashier} · Aktif` : (blockingShift ? 'Shift lain masih aktif' : 'Belum mulai shift')"></span></span>
                <svg class="h-5 w-5 text-gray-400 transition-transform" :class="mobileShiftOpen && 'rotate-180'" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M5 7.5L10 12.5L15 7.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
            <div x-cloak x-show="mobileShiftOpen" class="border-t border-gray-100 pt-3 dark:border-gray-800 lg:hidden">
                <template x-if="shift"><div class="flex flex-wrap items-center gap-2"><span class="rounded-full bg-success-50 px-2.5 py-1 text-xs font-semibold text-success-700 dark:bg-success-500/15 dark:text-success-400">Aktif</span><span class="text-sm font-semibold text-gray-800 dark:text-white/90" x-text="shift.cashier"></span><span class="text-xs text-gray-500 dark:text-gray-400">mulai <span x-text="shift.openedAt"></span> · <span x-text="shift.salesCount"></span> cup/item</span><span class="text-xs font-semibold tabular-nums text-gray-800 dark:text-white/90" x-text="formatRupiah(shift.netSales)"></span></div></template>
                <template x-if="!shift && blockingShift"><p class="text-sm text-warning-700 dark:text-warning-300">Sesi <span class="font-semibold" x-text="blockingShift.cashier"></span> masih aktif. Akhiri sesi tersebut sebelum shift berikutnya.</p></template>
                <template x-if="!shift && !blockingShift"><p class="text-sm text-gray-500 dark:text-gray-400">Belum mulai pekerjaan. Konfirmasi mulai shift sebelum transaksi.</p></template>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="button" @click="openCustomerDisplay()" class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-200 px-3 text-xs font-semibold text-gray-700 dark:border-gray-800 dark:text-gray-300">Display</button>
                    <button type="button" @click="sopModal = true" class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-200 px-3 text-xs font-semibold text-gray-700 dark:border-gray-800 dark:text-gray-300">SOP Kasir</button>
                    <button type="button" x-show="!shift && !blockingShift" @click="startModal = true" class="inline-flex h-9 items-center justify-center rounded-lg bg-brand-500 px-3 text-xs font-semibold text-white">Buka Kasir</button>
                    <button type="button" x-show="blockingShift && !shift" @click="changeModal = true" class="inline-flex h-9 items-center justify-center rounded-lg bg-warning-500 px-3 text-xs font-semibold text-white">Ganti Shift</button>
                    <button type="button" x-show="shift" @click="changeModal = true" class="inline-flex h-9 items-center justify-center rounded-lg border border-warning-200 px-3 text-xs font-semibold text-warning-700 dark:border-warning-500/30 dark:text-warning-300">Ganti Shift</button>
                    <button type="button" x-show="shift" @click="closeModal = true; sopModal = true" class="inline-flex h-9 items-center justify-center rounded-lg border border-error-200 px-3 text-xs font-semibold text-error-600 dark:border-error-500/30">Tutup Kasir</button>
                </div>
            </div>
            <div class="hidden lg:block">
                <p class="text-[10px] font-medium uppercase text-gray-400">Shift Kasir</p>
                <template x-if="shift">
                    <div class="mt-1 flex flex-wrap items-center gap-2">
                        <span class="rounded-full bg-success-50 px-2.5 py-1 text-xs font-semibold text-success-700 dark:bg-success-500/15 dark:text-success-400">Aktif</span>
                        <span class="text-sm font-semibold text-gray-800 dark:text-white/90" x-text="shift.cashier"></span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">mulai <span x-text="shift.openedAt"></span></span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">· <span x-text="shift.salesCount"></span> cup/item</span>
                        <span class="text-xs font-semibold tabular-nums text-gray-800 dark:text-white/90" x-text="formatRupiah(shift.netSales)"></span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">Kas awal <span class="font-semibold tabular-nums text-gray-800 dark:text-white/90" x-text="formatRupiah(shift.openingCashAmount || 0)"></span></span>
                    </div>
                </template>
                <template x-if="!shift && blockingShift">
                    <p class="mt-1 text-sm text-warning-700 dark:text-warning-300">
                        Sesi <span class="font-semibold" x-text="blockingShift.cashier"></span> masih aktif sejak <span x-text="blockingShift.openedAt"></span>. Akhiri sesi tersebut sebelum shift berikutnya.
                    </p>
                </template>
                <template x-if="!shift && !blockingShift">
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Belum mulai pekerjaan. Konfirmasi mulai shift sebelum transaksi.</p>
                </template>
            </div>
            <div class="hidden items-center gap-2 lg:flex">
                <button type="button" @click="openCustomerDisplay()"
                    class="inline-flex h-10 items-center justify-center gap-1.5 rounded-lg border border-gray-200 px-3 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.04]">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2" y="4" width="16" height="10" rx="1.5"/><path d="M6 17h8" stroke-linecap="round"/></svg>
                    Display
                </button>
                <button type="button" @click="sopModal = true"
                    class="inline-flex h-10 items-center justify-center rounded-lg border border-gray-200 px-4 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.04]">
                    SOP Kasir
                </button>
                <button type="button" x-show="!shift && !blockingShift" @click="startModal = true"
                    class="inline-flex h-10 items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-semibold text-white shadow-theme-xs transition hover:bg-brand-600">
                    Buka Kasir
                </button>
                <button type="button" x-show="blockingShift && !shift" @click="changeModal = true" class="inline-flex h-10 items-center justify-center rounded-lg bg-warning-500 px-4 text-sm font-semibold text-white">Ganti Shift</button>
                <button type="button" x-show="shift" @click="changeModal = true" class="inline-flex h-10 items-center justify-center rounded-lg border border-warning-200 px-4 text-sm font-semibold text-warning-700 dark:border-warning-500/30 dark:text-warning-300">Ganti Shift</button>
                <button type="button" x-show="shift" @click="closeModal = true; sopModal = true"
                    class="inline-flex h-10 items-center justify-center rounded-lg border border-error-200 px-4 text-sm font-semibold text-error-600 transition hover:bg-error-50 dark:border-error-500/30 dark:hover:bg-error-500/10">
                    Tutup Kasir
                </button>
            </div>
        </section>

        <section class="relative grid min-w-0 gap-4 xl:grid-cols-[minmax(0,1fr)_350px]">
            <div x-show="!shift" class="absolute inset-0 z-10 rounded-xl bg-white/75 backdrop-blur-sm dark:bg-gray-950/75"></div>
            <div class="min-w-0 space-y-4">
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
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
                                    <button type="button" @click="addItem(item, $event)"
                                        class="min-h-16 rounded-lg border border-gray-200 bg-gray-50 p-2 text-left transition hover:border-brand-200 hover:bg-brand-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/20 dark:border-gray-800 dark:bg-gray-900/70 dark:hover:border-brand-500/40 dark:hover:bg-brand-500/10">
                                        <span class="block truncate text-xs font-semibold text-gray-800 dark:text-white/90" x-text="item.name"></span>
                                        <span class="mt-1 block text-xs font-semibold tabular-nums text-brand-600 dark:text-brand-400" x-text="formatRupiah(item.price)"></span>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2 sm:gap-3 lg:grid-cols-3 xl:grid-cols-4">
                            <template x-for="item in filteredItems" :key="item.id">
                                <button type="button" @click="addItem(item, $event)"
                                    :class="{ 'pos-product-card-added': recentlyAddedItemId === item.id }"
                                    class="group relative z-0 grid h-[204px] grid-cols-1 grid-rows-[112px_minmax(0,1fr)] overflow-hidden rounded-xl border border-gray-200 bg-white text-left shadow-theme-xs transition hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-theme-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/20 sm:h-[124px] sm:grid-cols-[96px_minmax(0,1fr)] sm:grid-rows-1 dark:border-gray-800 dark:bg-gray-900 dark:hover:border-brand-500/50">
                                    <div class="relative h-full bg-gray-100 dark:bg-gray-800">
                                        <template x-if="item.imageUrl"><img :src="item.imageUrl" alt="" class="h-full w-full object-cover transition duration-500 group-hover:scale-110" /></template>
                                        <template x-if="!item.imageUrl"><div class="grid h-full place-items-center bg-gradient-to-br from-brand-100 to-gray-100 text-brand-600 dark:from-brand-500/20 dark:to-gray-800 dark:text-brand-300"><svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 17l4.5-4.5 3.2 3.2L15 12.4 20 17M5 20h14a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v14a1 1 0 001 1z" stroke-linecap="round" stroke-linejoin="round"/><circle cx="9" cy="8" r="1.3"/></svg></div></template>
                                        <span class="pointer-events-none absolute inset-y-0 -right-8 z-0 hidden w-16 bg-gradient-to-r from-transparent to-white sm:block dark:to-gray-900"></span>
                                        <span class="absolute inset-x-0 bottom-0 h-12 bg-gradient-to-t from-gray-950/50 to-transparent"></span>
                                    </div>
                                    <div class="relative z-[1] flex min-w-0 flex-col p-2.5 sm:p-3">
                                        <p class="line-clamp-2 text-[15px] font-normal leading-5 text-gray-800 dark:text-white/90" x-text="item.name"></p>
                                        <p class="mt-1 text-[9px] font-medium leading-none tracking-wide text-gray-500 dark:text-gray-400" x-text="stockLabel(item)"></p>
                                        <div class="mt-auto flex items-end justify-end">
                                            <p class="w-full text-right text-sm font-bold tabular-nums text-gray-900 dark:text-white" x-text="formatRupiah(item.price)"></p>
                                            <span class="grid h-8 w-8 shrink-0 translate-y-1 place-items-center rounded-lg bg-gray-900 text-white opacity-0 transition duration-200 group-hover:translate-y-0 group-hover:bg-brand-500 group-hover:opacity-100 group-focus-visible:translate-y-0 group-focus-visible:opacity-100 dark:bg-white dark:text-gray-900 dark:group-hover:bg-brand-400"><svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M10 4.16667V15.8333M4.16663 10H15.8333" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" /></svg></span>
                                        </div>
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

            <aside class="hidden xl:block min-w-0 xl:sticky xl:top-24 xl:h-fit">
                <div data-pos-cart-target class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                    @include('pages.pos.cart', ['maxHeightClass' => 'max-h-[420px]'])
                </div>
            </aside>
        </section>

        <!-- Floating Bottom Bar for Mobile -->
        <div data-pos-cart-target x-cloak x-show="cart.length > 0"
            class="fixed bottom-4 left-4 right-4 z-40 xl:hidden rounded-2xl bg-gray-900 text-white shadow-lg p-3 dark:bg-white dark:text-gray-900 flex items-center justify-between transition-all duration-300">
            <div class="min-w-0 flex-1">
                <p class="text-xs font-semibold" x-text="`${cart.reduce((sum, item) => sum + item.quantity, 0)} Item`"></p>
                <p class="text-sm font-bold truncate tabular-nums text-brand-400 dark:text-brand-600" x-text="formatRupiah(total)"></p>
            </div>
            <button type="button" @click="showMobileCart = true"
                class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-brand-500 hover:bg-brand-600 dark:bg-brand-600 dark:hover:bg-brand-700 px-4 text-xs font-bold text-white transition">
                <span>Lihat Keranjang</span>
                <span class="grid h-5 w-5 place-items-center rounded-full bg-white/20 text-[10px]" x-text="cart.length"></span>
            </button>
        </div>

        <!-- Mobile Drawer Overlay -->
        <div x-cloak x-show="showMobileCart" class="fixed inset-0 z-50 xl:hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <!-- Backdrop -->
            <div x-show="showMobileCart" 
                 x-transition:enter="ease-in-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in-out duration-300"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @click="showMobileCart = false"
                 class="fixed inset-0 bg-gray-950/60 backdrop-blur-xs transition-opacity"></div>

            <!-- Drawer Content -->
            <div class="fixed inset-y-0 right-0 flex max-w-full pl-10">
                <div x-show="showMobileCart"
                     x-transition:enter="transform transition ease-in-out duration-300 sm:duration-500"
                     x-transition:enter-start="translate-x-full"
                     x-transition:enter-end="translate-x-0"
                     x-transition:leave="transform transition ease-in-out duration-300 sm:duration-500"
                     x-transition:leave-start="translate-x-0"
                     x-transition:leave-end="translate-x-full"
                     class="w-screen max-w-md bg-white dark:bg-gray-900 shadow-xl flex flex-col h-full border-l border-gray-200 dark:border-gray-800">
                     @include('pages.pos.cart', ['maxHeightClass' => 'flex-1 overflow-y-auto'])
                </div>
            </div>
        </div>

        <div x-cloak x-show="sopModal" x-transition.opacity.duration.200ms class="fixed inset-0 z-99999 flex items-center justify-center bg-gray-950/50 p-4">
            <div @click.outside="closeSopModal()" x-transition:enter="ease-out duration-200" x-transition:enter-start="translate-y-2 scale-95 opacity-0" x-transition:enter-end="translate-y-0 scale-100 opacity-100" x-transition:leave="ease-in duration-150" x-transition:leave-start="translate-y-0 scale-100 opacity-100" x-transition:leave-end="translate-y-2 scale-95 opacity-0" class="w-full max-w-2xl rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xl dark:border-gray-800 dark:bg-gray-900">
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

                <div class="mt-5">
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm leading-6 text-gray-600 dark:border-gray-800 dark:bg-white/[0.04] dark:text-gray-300">
                        @if ($cashierSopHtml)
                            {!! $cashierSopHtml !!}
                        @else
                            <p class="font-medium text-gray-700 dark:text-gray-200">Belum ada SOP custom untuk cabang ini.</p>
                            <p class="mt-1">Gunakan SOP bawaan berikut sebagai panduan operasional.</p>
                        @endif
                    </div>
                </div>

                <div class="mt-5 grid gap-4 md:grid-cols-2" x-show="!cashierSopHtml">
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

        <div x-cloak x-show="startModal" x-transition.opacity.duration.200ms class="fixed inset-0 z-99999 flex items-center justify-center bg-gray-950/50 p-4">
            <div @click.outside="shift ? startModal = false : null" x-transition:enter="ease-out duration-200" x-transition:enter-start="translate-y-2 scale-95 opacity-0" x-transition:enter-end="translate-y-0 scale-100 opacity-100" x-transition:leave="ease-in duration-150" x-transition:leave-start="translate-y-0 scale-100 opacity-100" x-transition:leave-end="translate-y-2 scale-95 opacity-0" class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xl dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-start gap-3">
                    <div class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-500/15">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M10 4V10L14 12" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M18 10A8 8 0 1 1 2 10A8 8 0 0 1 18 10Z" stroke="currentColor" stroke-width="1.7" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Mulai shift kasir?</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Kasir masuk wajib cocokkan cash dan kartu dari rekap sesi sebelumnya sebelum transaksi.</p>
                    </div>
                </div>

                <div x-show="lastClosedShift" class="mt-4 rounded-xl border border-warning-200 bg-warning-50 p-3 text-xs dark:border-warning-500/20 dark:bg-warning-500/10">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold text-warning-800 dark:text-warning-200">Validasi rekap sebelumnya</p>
                            <p class="mt-0.5 text-warning-700 dark:text-warning-300">
                                <span x-text="lastClosedShift?.cashier || '-'"></span> · <span x-text="lastClosedShift?.closedAt || '-'"></span>
                            </p>
                        </div>
                        <button type="button" @click="shiftRecap = lastClosedShift" class="text-xs font-semibold text-warning-800 underline dark:text-warning-200">Lihat rekap</button>
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <div class="rounded-lg bg-white/70 p-2 dark:bg-gray-950/30">
                            <p class="text-[10px] uppercase text-warning-700 dark:text-warning-300">Cash struk</p>
                            <p class="mt-1 font-semibold tabular-nums text-warning-900 dark:text-warning-100" x-text="formatRupiah(lastClosedShift?.expectedCashInDrawer || 0)"></p>
                        </div>
                        <div class="rounded-lg bg-white/70 p-2 dark:bg-gray-950/30">
                            <p class="text-[10px] uppercase text-warning-700 dark:text-warning-300">Kartu struk</p>
                            <p class="mt-1 font-semibold tabular-nums text-warning-900 dark:text-warning-100" x-text="formatRupiah(lastClosedShift?.cardTotal || 0)"></p>
                        </div>
                    </div>
                </div>

                <label x-show="!lastClosedShift" class="mt-4 block">
                    <span class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Uang kas awal untuk kembalian</span>
                    <input type="text" inputmode="numeric" autocomplete="off" :value="formatInputNumber(openingCashAmount)" @input="onMoneyInput('openingCashAmount', $event)" placeholder="0"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-right text-sm tabular-nums text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-950 dark:text-white/90" />
                    <span class="mt-1 block text-[11px] text-gray-500 dark:text-gray-400">Nominal cash yang disiapkan di laci kas untuk uang kembalian.</span>
                </label>

                <div x-show="lastClosedShift" class="mt-4 grid gap-3 sm:grid-cols-2">
                    <label class="block">
                        <span class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Cash tervalidasi</span>
                        <input type="text" inputmode="numeric" autocomplete="off" :value="formatInputNumber(validatedCashAmount)" @input="onMoneyInput('validatedCashAmount', $event)" placeholder="0"
                            class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-right text-sm tabular-nums text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-950 dark:text-white/90" />
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Kartu tervalidasi</span>
                        <input type="text" inputmode="numeric" autocomplete="off" :value="formatInputNumber(validatedCardAmount)" @input="onMoneyInput('validatedCardAmount', $event)" placeholder="0"
                            class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-right text-sm tabular-nums text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-950 dark:text-white/90" />
                    </label>
                </div>

                <label class="mt-4 block">
                    <span class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Catatan awal shift (opsional)</span>
                    <textarea x-model="openingNote" rows="3" class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-950 dark:text-white/90"></textarea>
                </label>
                <div class="mt-4 rounded-lg border border-brand-200 bg-brand-50/50 p-3 dark:border-brand-500/20 dark:bg-brand-500/10">
                    <p class="text-xs font-semibold text-brand-800 dark:text-brand-200">Checklist pembukaan</p>
                    <div class="mt-2 grid gap-2 text-xs text-gray-700 dark:text-gray-300">
                        <label class="flex items-center gap-2"><input type="checkbox" value="cash" x-model="openingChecklist" class="rounded border-gray-300 text-brand-500"> Modal awal dan laci kas sudah dihitung</label>
                        <label class="flex items-center gap-2"><input type="checkbox" value="printer" x-model="openingChecklist" class="rounded border-gray-300 text-brand-500"> Printer, laci kas, dan pembayaran siap</label>
                        <label class="flex items-center gap-2"><input type="checkbox" value="stock" x-model="openingChecklist" class="rounded border-gray-300 text-brand-500"> Produk dan stok penting sudah diperiksa</label>
                    </div>
                </div>
                <div x-show="availableStaff.length" class="mt-4 rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                    <p class="text-xs font-semibold text-gray-700 dark:text-gray-300">Staff pendamping / asisten</p>
                    <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">Pilih staff yang bertugas bersama operator kasir hari ini.</p>
                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                        <template x-for="staff in availableStaff" :key="staff.id">
                            <label class="flex items-center gap-2 rounded-lg bg-gray-50 px-2.5 py-2 text-xs dark:bg-gray-900">
                                <input type="checkbox" :value="staff.id" x-model="companionStaffIds" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500/20">
                                <span class="text-gray-700 dark:text-gray-300" x-text="staff.name"></span>
                            </label>
                        </template>
                    </div>
                </div>
                <p x-show="shiftError" class="mt-3 rounded-lg border border-error-200 bg-error-50 px-3 py-2 text-xs text-error-700 dark:border-error-500/20 dark:bg-error-500/10 dark:text-error-300" x-text="shiftError"></p>

                <div class="mt-5 grid gap-2 sm:grid-cols-2">
                    <button type="button" @click="startShift()" :disabled="shiftLoading"
                        class="inline-flex h-10 items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-semibold text-white shadow-theme-xs transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60">
                        <span x-show="!shiftLoading">Ya, mulai shift</span>
                        <span x-show="shiftLoading">Memulai...</span>
                    </button>
                    <a href="{{ route('dashboard') }}" class="inline-flex h-10 items-center justify-center rounded-lg border border-gray-200 px-4 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.04]">
                        Tidak, kembali
                    </a>
                </div>
            </div>
        </div>

        <div x-cloak x-show="changeModal" x-transition.opacity.duration.200ms class="fixed inset-0 z-99999 flex items-center justify-center bg-gray-950/50 p-4">
            <div @click.outside="changeModal = false" class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xl dark:border-gray-800 dark:bg-gray-900">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Ganti Shift</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Nama petugas baru otomatis tercatat dari akun login. Checklist tidak diperlukan untuk pergantian shift.</p>
                <div class="mt-4 rounded-lg bg-warning-50 p-3 text-xs text-warning-800 dark:bg-warning-500/10 dark:text-warning-200">
                    Shift aktif: <span class="font-semibold" x-text="(shift || blockingShift)?.cashier || '-'"></span>
                </div>
                <label class="mt-4 block"><span class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Asisten/Rekan kerja (opsional)</span>
                    <select x-model="changeCompanionStaffId" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-950 dark:text-white/90"><option value="">Sendirian</option><template x-for="staff in availableStaff" :key="staff.id"><option :value="staff.id" x-text="staff.name"></option></template></select>
                </label>
                <p x-show="shiftError" class="mt-3 rounded-lg border border-error-200 bg-error-50 px-3 py-2 text-xs text-error-700" x-text="shiftError"></p>
                <div class="mt-5 grid grid-cols-2 gap-2"><button type="button" @click="changeShift()" :disabled="shiftLoading" class="h-10 rounded-lg bg-warning-500 px-4 text-sm font-semibold text-white disabled:opacity-60"><span x-text="shiftLoading ? 'Mencatat...' : 'Simpan Pergantian'"></span></button><button type="button" @click="changeModal = false" class="h-10 rounded-lg border border-gray-200 px-4 text-sm font-semibold text-gray-700 dark:border-gray-800 dark:text-gray-300">Batal</button></div>
            </div>
        </div>

        <div x-cloak x-show="closeModal" x-transition.opacity.duration.200ms class="fixed inset-0 z-99999 flex items-center justify-center bg-gray-950/50 p-4">
            <div @click.outside="closeModal = false" x-transition:enter="ease-out duration-200" x-transition:enter-start="translate-y-2 scale-95 opacity-0" x-transition:enter-end="translate-y-0 scale-100 opacity-100" x-transition:leave="ease-in duration-150" x-transition:leave-start="translate-y-0 scale-100 opacity-100" x-transition:leave-end="translate-y-2 scale-95 opacity-0" class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xl dark:border-gray-800 dark:bg-gray-900">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Akhiri sesi kasir?</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Sistem mencetak rekap cash dan kartu untuk dicocokkan. Kasir harian tetap berjalan sampai closing malam.</p>
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
                <div class="mt-4 rounded-lg border border-error-200 bg-error-50/50 p-3 dark:border-error-500/20 dark:bg-error-500/10">
                    <p class="text-xs font-semibold text-error-800 dark:text-error-200">Checklist penutupan</p>
                    <div class="mt-2 grid gap-2 text-xs text-gray-700 dark:text-gray-300">
                        <label class="flex items-center gap-2"><input type="checkbox" value="transactions" x-model="closingChecklist" class="rounded border-gray-300 text-error-500"> Semua transaksi dan pembayaran sudah selesai</label>
                        <label class="flex items-center gap-2"><input type="checkbox" value="cash" x-model="closingChecklist" class="rounded border-gray-300 text-error-500"> Cash, QRIS, dan kartu sudah dicocokkan</label>
                        <label class="flex items-center gap-2"><input type="checkbox" value="report" x-model="closingChecklist" class="rounded border-gray-300 text-error-500"> Laporan dan bukti pembayaran sudah dirapikan</label>
                    </div>
                </div>
                <p x-show="shiftError" class="mt-3 rounded-lg border border-error-200 bg-error-50 px-3 py-2 text-xs text-error-700 dark:border-error-500/20 dark:bg-error-500/10 dark:text-error-300" x-text="shiftError"></p>

                <div class="mt-5 grid gap-2 sm:grid-cols-2">
                    <button type="button" @click="closeShift()" :disabled="shiftLoading"
                        class="inline-flex h-10 items-center justify-center rounded-lg bg-error-600 px-4 text-sm font-semibold text-white shadow-theme-xs transition hover:bg-error-700 disabled:cursor-not-allowed disabled:opacity-60">
                        <span x-show="!shiftLoading">Akhiri sesi</span>
                        <span x-show="shiftLoading">Menutup...</span>
                    </button>
                    <button type="button" @click="closeModal = false" class="inline-flex h-10 items-center justify-center rounded-lg border border-gray-200 px-4 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.04]">
                        Batal
                    </button>
                </div>
            </div>
        </div>

        <div x-cloak x-show="receiptModal" x-transition.opacity.duration.200ms class="fixed inset-0 z-99999 flex items-center justify-center bg-gray-950/50 p-4">
            <div @click.outside="closeReceiptModal()" x-transition:enter="ease-out duration-200" x-transition:enter-start="translate-y-2 scale-95 opacity-0" x-transition:enter-end="translate-y-0 scale-100 opacity-100" x-transition:leave="ease-in duration-150" x-transition:leave-start="translate-y-0 scale-100 opacity-100" x-transition:leave-end="translate-y-2 scale-95 opacity-0" class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xl dark:border-gray-800 dark:bg-gray-900">
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

        <x-shift-recap-modal />

        <!-- Variant Selection Modal -->
        <div x-cloak x-show="variantModal" x-transition.opacity.duration.200ms class="fixed inset-0 z-99999 flex items-center justify-center bg-gray-950/50 p-4">
            <div @click.outside="variantModal = false; variantProduct = null" x-transition:enter="ease-out duration-200" x-transition:enter-start="translate-y-2 scale-95 opacity-0" x-transition:enter-end="translate-y-0 scale-100 opacity-100" x-transition:leave="ease-in duration-150" x-transition:leave-start="translate-y-0 scale-100 opacity-100" x-transition:leave-end="translate-y-2 scale-95 opacity-0" class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xl dark:border-gray-800 dark:bg-gray-900">
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
