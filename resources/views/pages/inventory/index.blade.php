@extends('layouts.app')

@section('content')
    <div x-data="inventoryManager(@js($items), @js($movements))" class="space-y-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <nav aria-label="Breadcrumb">
                    <ol class="flex items-center gap-1 text-[10px] text-gray-500 dark:text-gray-400">
                        <li>
                            <a href="{{ url('/') }}" class="transition hover:text-gray-700 dark:hover:text-gray-200">Home</a>
                        </li>
                        <li aria-hidden="true">
                            <svg class="h-3 w-3 stroke-current" viewBox="0 0 17 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M6.0765 12.667L10.2432 8.50033L6.0765 4.33366" stroke-width="1.2"
                                    stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </li>
                        <li class="font-medium text-gray-700 dark:text-gray-300">Stok</li>
                    </ol>
                </nav>

                <h1 class="mt-1 text-xl font-semibold text-gray-800 dark:text-white/90">Stok</h1>
            </div>
        </div>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex flex-col gap-3 border-b border-gray-100 px-3 py-3 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between sm:px-4">
                <div class="relative w-full sm:max-w-[30%]">
                    <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M9.16667 15.8334C12.8486 15.8334 15.8333 12.8486 15.8333 9.16669C15.8333 5.48479 12.8486 2.50002 9.16667 2.50002C5.48477 2.50002 2.5 5.48479 2.5 9.16669C2.5 12.8486 5.48477 15.8334 9.16667 15.8334Z"
                                stroke="currentColor" stroke-width="1.5" />
                            <path d="M14.1666 14.1667L17.5 17.5" stroke="currentColor" stroke-width="1.5"
                                stroke-linecap="round" />
                        </svg>
                    </span>
                    <input type="text" placeholder="Cari produk atau SKU"
                        x-model.debounce.300ms="filters.search" @input="currentPage = 1"
                        class="h-9 w-full rounded-lg border border-gray-300 bg-transparent py-2 pl-9 pr-3 text-xs text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800" />
                </div>

                <div class="flex flex-wrap items-center justify-end gap-2">
                    <div class="inline-flex w-fit items-center gap-1.5 rounded-lg bg-gray-50 px-2.5 py-1 text-xs text-gray-500 dark:bg-gray-900/60 dark:text-gray-400">
                        Total: <span class="font-semibold text-gray-800 dark:text-white/90" x-text="filteredItems.length"></span> produk
                    </div>

                    <div x-data="{ isOptionSelected: false }" class="relative w-36">
                        <select x-model="filters.status"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-9 w-full appearance-none rounded-lg border border-gray-300 bg-transparent bg-none px-3 py-2 pr-9 text-xs text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                            :class="isOptionSelected && 'text-gray-800 dark:text-white/90'"
                            @change="isOptionSelected = true; currentPage = 1">
                            <option value="" class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">Semua Status</option>
                            <option value="aktif" class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">Aktif</option>
                            <option value="stok-menipis" class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">Stok Menipis</option>
                            <option value="habis" class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">Habis</option>
                        </select>
                        <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="1.6"
                                    stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                    </div>
                </div>
            </div>

            <div class="max-w-full overflow-x-auto custom-scrollbar">
                <table class="w-full min-w-[820px]">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                            <th class="px-4 py-2.5 text-left"><p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Produk</p></th>
                            <th class="px-4 py-2.5 text-center"><p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">SKU</p></th>
                            <th class="px-4 py-2.5 text-center"><p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Kategori</p></th>
                            <th class="px-4 py-2.5 text-right"><p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Stok</p></th>
                            <th class="px-4 py-2.5 text-right"><p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Minimum</p></th>
                            <th class="px-4 py-2.5 text-center"><p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</p></th>
                            <th class="px-4 py-2.5 text-center"><p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Aksi</p></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="item in paginatedItems" :key="item.sku">
                            <tr class="border-b border-gray-100 transition-colors last:border-0 hover:bg-gray-25 dark:border-gray-800 dark:hover:bg-white/[0.02]">
                                <td class="px-4 py-2">
                                    <div class="flex items-center gap-2">
                                        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-brand-50 text-brand-500 dark:bg-brand-500/15 dark:text-brand-400">
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M4.5 8.5L10 5.5L15.5 8.5V14.5L10 17.5L4.5 14.5V8.5Z" stroke="currentColor" stroke-width="1.5" />
                                                <path d="M4.75 8.75L10 11.75L15.25 8.75M10 17V11.75" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                            </svg>
                                        </div>
                                        <p class="max-w-[240px] truncate text-[13px] font-semibold text-gray-800 dark:text-white/90" x-text="item.name"></p>
                                    </div>
                                </td>
                                <td class="px-4 py-2 text-center text-[12px] font-medium text-gray-500 dark:text-gray-400" x-text="item.sku"></td>
                                <td class="px-4 py-2 text-center text-[12px] text-gray-500 dark:text-gray-400" x-text="item.category"></td>
                                <td class="px-4 py-2 text-right text-[12px] font-semibold tabular-nums text-gray-800 dark:text-white/90" x-text="item.stock"></td>
                                <td class="px-4 py-2 text-right text-[12px] font-semibold tabular-nums text-gray-800 dark:text-white/90" x-text="item.minStock"></td>
                                <td class="px-4 py-2 text-center">
                                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="statusClass(item.status)" x-text="item.status"></span>
                                </td>
                                <td class="px-4 py-2 text-center">
                                    <button type="button" aria-label="Stok masuk" @click="openMovementModal('in', item)"
                                        class="inline-flex h-7 w-7 items-center justify-center rounded-md text-gray-500 transition hover:bg-success-50 hover:text-success-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-success-500/20 dark:text-gray-400 dark:hover:bg-success-500/10 dark:hover:text-success-400">
                                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M10 4.16666V15.8333M4.16663 10H15.8333" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                                        </svg>
                                    </button>
                                    <button type="button" aria-label="Stok keluar" @click="openMovementModal('out', item)"
                                        class="inline-flex h-7 w-7 items-center justify-center rounded-md text-gray-500 transition hover:bg-error-50 hover:text-error-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-error-500/20 dark:text-gray-400 dark:hover:bg-error-500/10 dark:hover:text-error-400">
                                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M4.16663 10H15.8333" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                                        </svg>
                                    </button>
                                    <button type="button" aria-label="Update stok" @click="openEditModal(item)"
                                        class="inline-flex h-7 w-7 items-center justify-center rounded-md text-gray-500 transition hover:bg-brand-50 hover:text-brand-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/20 dark:text-gray-400 dark:hover:bg-brand-500/10 dark:hover:text-brand-400">
                                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M4.16663 10.8333L7.49996 14.1667L15.8333 5.83334" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="filteredItems.length === 0" class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                            <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                Tidak ada stok produk yang cocok dengan filter.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col gap-3 border-t border-gray-100 px-4 py-3 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Menampilkan <span class="font-medium text-gray-800 dark:text-white/90" x-text="fromItem"></span>
                    - <span class="font-medium text-gray-800 dark:text-white/90" x-text="toItem"></span>
                    dari <span class="font-medium text-gray-800 dark:text-white/90" x-text="filteredItems.length"></span>
                    produk
                </p>

                <div class="flex items-center justify-end gap-1.5">
                    <button type="button" @click="previousPage()" :disabled="currentPage === 1" aria-label="Halaman sebelumnya"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-white text-gray-700 shadow-theme-xs transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12.5 4.16666L6.66667 10L12.5 15.8333" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                    <span class="min-w-10 text-center text-xs font-medium text-gray-500 dark:text-gray-400">
                        <span x-text="currentPage"></span>/<span x-text="totalPages"></span>
                    </span>
                    <button type="button" @click="nextPage()" :disabled="currentPage === totalPages" aria-label="Halaman berikutnya"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-white text-gray-700 shadow-theme-xs transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M7.5 4.16666L13.3333 10L7.5 15.8333" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                <div>
                    <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Riwayat Stok</h2>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Mutasi stok masuk dan keluar terbaru.</p>
                </div>
                <span class="rounded-lg bg-gray-50 px-2.5 py-1 text-xs font-medium text-gray-500 dark:bg-gray-900/60 dark:text-gray-400">
                    <span x-text="movements.length"></span> transaksi
                </span>
            </div>

            <div class="max-w-full overflow-x-auto custom-scrollbar">
                <table class="w-full min-w-[900px]">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                            <th class="px-4 py-2.5 text-left"><p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Waktu</p></th>
                            <th class="px-4 py-2.5 text-center"><p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Tipe</p></th>
                            <th class="px-4 py-2.5 text-left"><p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Produk</p></th>
                            <th class="px-4 py-2.5 text-center"><p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">SKU</p></th>
                            <th class="px-4 py-2.5 text-right"><p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Jumlah</p></th>
                            <th class="px-4 py-2.5 text-right"><p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Stok Awal</p></th>
                            <th class="px-4 py-2.5 text-right"><p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Stok Akhir</p></th>
                            <th class="px-4 py-2.5 text-center"><p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Referensi</p></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="movement in movements" :key="movement.id">
                            <tr class="border-b border-gray-100 transition-colors last:border-0 hover:bg-gray-25 dark:border-gray-800 dark:hover:bg-white/[0.02]">
                                <td class="px-4 py-2 text-[12px] text-gray-500 dark:text-gray-400" x-text="movement.occurredAt"></td>
                                <td class="px-4 py-2 text-center">
                                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                        :class="movement.typeCode === 'in' ? 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-400' : 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400'"
                                        x-text="movement.type"></span>
                                </td>
                                <td class="px-4 py-2">
                                    <p class="max-w-[260px] truncate text-[13px] font-semibold text-gray-800 dark:text-white/90" x-text="movement.product"></p>
                                    <p class="mt-0.5 max-w-[260px] truncate text-[11px] text-gray-500 dark:text-gray-400" x-text="movement.note"></p>
                                </td>
                                <td class="px-4 py-2 text-center text-[12px] font-medium text-gray-500 dark:text-gray-400" x-text="movement.sku"></td>
                                <td class="px-4 py-2 text-right text-[12px] font-semibold tabular-nums text-gray-800 dark:text-white/90" x-text="movement.quantity"></td>
                                <td class="px-4 py-2 text-right text-[12px] tabular-nums text-gray-500 dark:text-gray-400" x-text="movement.stockBefore"></td>
                                <td class="px-4 py-2 text-right text-[12px] font-semibold tabular-nums text-gray-800 dark:text-white/90" x-text="movement.stockAfter"></td>
                                <td class="px-4 py-2 text-center text-[12px] text-gray-500 dark:text-gray-400" x-text="movement.reference"></td>
                            </tr>
                        </template>
                        <tr x-show="movements.length === 0" class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                            <td colspan="8" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                Belum ada transaksi stok.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div x-show="movementModal" x-cloak @keydown.escape.window="closeMovementModal()"
            class="fixed inset-0 z-99999 flex items-center justify-center overflow-y-auto p-4">
            <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm" @click="closeMovementModal()"></div>

            <div class="relative w-full max-w-xl rounded-2xl bg-white p-5 shadow-theme-xl dark:bg-gray-900 sm:p-6">
                <div class="mb-6 flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h2 class="truncate text-xl font-semibold text-gray-800 dark:text-white/90" x-text="`${movementTypeLabel} ${movementDraft.name || ''}`"></h2>
                        <p class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">
                            Catat jumlah, referensi, dan catatan transaksi stok.
                        </p>
                    </div>
                    <button type="button" @click="closeMovementModal()"
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-500 transition hover:bg-gray-200 hover:text-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M5 5L15 15M15 5L5 15" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="saveMovement()" class="space-y-5">
                    <div x-show="movementFormError" x-cloak
                        class="rounded-lg border border-error-200 bg-error-50 px-4 py-3 text-theme-sm text-error-600 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400"
                        x-text="movementFormError">
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Jumlah<span class="text-error-500">*</span></label>
                            <input type="number" min="1" x-model="movementDraft.quantity"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Referensi</label>
                            <input type="text" x-model="movementDraft.reference" placeholder="PO/SO/nota"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Catatan</label>
                        <textarea rows="3" x-model="movementDraft.note" placeholder="Contoh: restok pemasok atau penjualan kasir"
                            class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"></textarea>
                    </div>

                    <div class="flex flex-col-reverse gap-3 border-t border-gray-100 pt-5 dark:border-gray-800 sm:flex-row sm:justify-end">
                        <button type="button" @click="closeMovementModal()"
                            class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-5 py-3 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]">
                            Batal
                        </button>
                        <button type="submit"
                            class="inline-flex items-center justify-center rounded-lg px-5 py-3 text-sm font-medium text-white shadow-theme-xs transition"
                            :class="movementType === 'in' ? 'bg-success-500 hover:bg-success-600' : 'bg-error-500 hover:bg-error-600'"
                            x-text="movementTypeLabel">
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div x-show="editModal" x-cloak @keydown.escape.window="closeEditModal()"
            class="fixed inset-0 z-99999 flex items-center justify-center overflow-y-auto p-4">
            <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm" @click="closeEditModal()"></div>

            <div class="relative w-full max-w-xl rounded-2xl bg-white p-5 shadow-theme-xl dark:bg-gray-900 sm:p-6">
                <div class="mb-6 flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h2 class="truncate text-xl font-semibold text-gray-800 dark:text-white/90" x-text="`Update Stok ${editDraft.name || ''}`"></h2>
                        <p class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">
                            Perbarui jumlah stok dan batas minimum.
                        </p>
                    </div>
                    <button type="button" @click="closeEditModal()"
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-500 transition hover:bg-gray-200 hover:text-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M5 5L15 15M15 5L5 15" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="updateStock()" class="space-y-5">
                    <div x-show="editFormError" x-cloak
                        class="rounded-lg border border-error-200 bg-error-50 px-4 py-3 text-theme-sm text-error-600 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400"
                        x-text="editFormError">
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Stok<span class="text-error-500">*</span></label>
                            <input type="number" min="0" x-model="editDraft.stock"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Batas Minimum</label>
                            <input type="number" min="0" x-model="editDraft.minStock"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                        </div>
                    </div>

                    <div class="flex flex-col-reverse gap-3 border-t border-gray-100 pt-5 dark:border-gray-800 sm:flex-row sm:justify-end">
                        <button type="button" @click="closeEditModal()"
                            class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-5 py-3 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]">
                            Batal
                        </button>
                        <button type="submit"
                            class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-5 py-3 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">
                            Simpan Stok
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
