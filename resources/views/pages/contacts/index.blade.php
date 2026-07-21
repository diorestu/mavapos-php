@extends('layouts.app')

@section('content')
    <div x-data="contactManager(@js($items), @js($routePath), @js($entityLabel), @js($entityName), @js($entityPlural), @js($filters))" class="space-y-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <nav aria-label="Breadcrumb">
                    <ol class="flex items-center gap-1 text-[10px] text-gray-500 dark:text-gray-400">
                        <li>
                            <a href="{{ url('/') }}" class="transition hover:text-gray-700 dark:hover:text-gray-200">
                                Home
                            </a>
                        </li>
                        <li aria-hidden="true">
                            <svg class="h-3 w-3 stroke-current" viewBox="0 0 17 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M6.0765 12.667L10.2432 8.50033L6.0765 4.33366" stroke-width="1.2"
                                    stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </li>
                        <li class="font-medium text-gray-700 dark:text-gray-300">
                            {{ $entityLabel }}
                        </li>
                    </ol>
                </nav>

                <h1 class="mt-1 text-xl font-semibold text-gray-800 dark:text-white/90">
                    {{ $entityLabel }}
                </h1>
            </div>

            <button type="button" @click="openCreateModal()"
                class="inline-flex h-9 w-full items-center justify-center gap-2 rounded-lg bg-brand-500 px-3 text-xs font-semibold text-white shadow-theme-xs transition hover:bg-brand-600 sm:w-auto">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M10 4.16669V15.8334M4.16663 10H15.8333" stroke="currentColor" stroke-width="1.7"
                        stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                Buat Data Baru
            </button>
        </div>

        @if ($entityName === 'customer')
            <section class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Statistik Loyalitas</p>
                    <p class="mt-2 text-xl font-semibold tabular-nums text-gray-800 dark:text-white"><span>{{ number_format($loyaltyStats['stamps'], 0, ',', '.') }}</span> stempel</p>
                    <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">Total stempel aktif pelanggan</p>
                </div>
                <div class="rounded-xl border border-warning-200 bg-warning-50/60 p-4 dark:border-warning-500/20 dark:bg-warning-500/10">
                    <p class="text-xs font-medium text-warning-700 dark:text-warning-300">Reward Diskon 50%</p>
                    <p class="mt-2 text-xl font-semibold tabular-nums text-warning-800 dark:text-warning-200">{{ number_format($loyaltyStats['fiftyRewardCustomers'], 0, ',', '.') }} pelanggan</p>
                    <p class="mt-1 text-[11px] text-warning-700/80 dark:text-warning-300/80">Siap dipakai di pembelian berikutnya</p>
                </div>
                <div class="rounded-xl border border-success-200 bg-success-50/60 p-4 dark:border-success-500/20 dark:bg-success-500/10">
                    <p class="text-xs font-medium text-success-700 dark:text-success-300">Reward Gratis 1 Cup</p>
                    <p class="mt-2 text-xl font-semibold tabular-nums text-success-800 dark:text-success-200">{{ number_format($loyaltyStats['freeCupRewardCustomers'], 0, ',', '.') }} pelanggan</p>
                    <p class="mt-1 text-[11px] text-success-700/80 dark:text-success-300/80">Siap dipakai di pembelian berikutnya</p>
                </div>
            </section>
        @endif

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <form method="GET" action="{{ url($routePath) }}" class="flex flex-col gap-3 border-b border-gray-100 px-3 py-3 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between sm:px-4">
                <div class="relative w-full sm:max-w-[30%]">
                    <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M9.16667 15.8334C12.8486 15.8334 15.8333 12.8486 15.8333 9.16669C15.8333 5.48479 12.8486 2.50002 9.16667 2.50002C5.48477 2.50002 2.5 5.48479 2.5 9.16669C2.5 12.8486 5.48477 15.8334 9.16667 15.8334Z"
                                stroke="currentColor" stroke-width="1.5" />
                            <path d="M14.1666 14.1667L17.5 17.5" stroke="currentColor" stroke-width="1.5"
                                stroke-linecap="round" />
                        </svg>
                    </span>
                    <input name="search" type="search" :placeholder="`Cari ${entityPlural} atau kode`"
                        value="{{ $filters['search'] }}" x-model.debounce.300ms="filters.search" @input="currentPage = 1"
                        class="h-9 w-full rounded-lg border border-gray-300 bg-transparent py-2 pl-9 pr-3 text-xs text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800" />
                </div>

                <div class="flex flex-wrap items-center justify-end gap-2">
                    <div class="inline-flex w-fit items-center gap-1.5 rounded-lg bg-gray-50 px-2.5 py-1 text-xs text-gray-500 dark:bg-gray-900/60 dark:text-gray-400">
                        Total: <span class="font-semibold text-gray-800 dark:text-white/90" x-text="filteredItems.length"></span> {{ $entityPlural }}
                    </div>

                    <div x-data="{ isOptionSelected: false }" class="relative w-36">
                        <select name="status" x-model="filters.status"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-9 w-full appearance-none rounded-lg border border-gray-300 bg-transparent bg-none px-3 py-2 pr-9 text-xs text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                            :class="isOptionSelected && 'text-gray-800 dark:text-white/90'"
                            @change="isOptionSelected = true; currentPage = 1; $el.form.submit()">
                            <option value="" class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">Semua Status</option>
                            <option value="aktif" class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">Aktif</option>
                            <option value="nonaktif" class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">Nonaktif</option>
                        </select>
                        <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="1.6"
                                    stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                    </div>
                </div>
            </form>

            <div class="max-w-full overflow-x-auto custom-scrollbar">
                <table class="w-full min-w-[860px]">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                            <th class="px-4 py-2.5 text-left">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Nama</p>
                            </th>
                            <th class="px-4 py-2.5 text-center">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Kode</p>
                            </th>
                            <th class="px-4 py-2.5 text-left">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Telepon</p>
                            </th>
                            <th class="px-4 py-2.5 text-left">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Email</p>
                            </th>
                            @if ($entityName === 'customer')
                                <th class="px-4 py-2.5 text-left">
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Loyalitas</p>
                                </th>
                            @endif
                            <th class="px-4 py-2.5 text-center">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</p>
                            </th>
                            <th class="px-4 py-2.5 text-center">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Aksi</p>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="item in paginatedItems" :key="item.code">
                            <tr class="border-b border-gray-100 transition-colors last:border-0 hover:bg-gray-25 dark:border-gray-800 dark:hover:bg-white/[0.02]">
                                <td class="px-4 py-2">
                                    <div class="flex items-center gap-2">
                                        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-brand-50 text-brand-500 dark:bg-brand-500/15 dark:text-brand-400">
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M10 10.8333C11.8409 10.8333 13.3333 9.34095 13.3333 7.5C13.3333 5.65905 11.8409 4.16667 10 4.16667C8.15905 4.16667 6.66667 5.65905 6.66667 7.5C6.66667 9.34095 8.15905 10.8333 10 10.8333Z"
                                                    stroke="currentColor" stroke-width="1.5" />
                                                <path d="M4.16667 16.6667C4.16667 14.3655 6.77834 12.5 10 12.5C13.2217 12.5 15.8333 14.3655 15.8333 16.6667"
                                                    stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                            </svg>
                                        </div>
                                        <p class="max-w-[260px] truncate text-[13px] font-semibold text-gray-800 dark:text-white/90" x-text="item.name"></p>
                                    </div>
                                </td>
                                <td class="px-4 py-2 text-center text-[12px] font-medium text-gray-500 dark:text-gray-400" x-text="item.code"></td>
                                <td class="px-4 py-2 text-[12px] text-gray-500 dark:text-gray-400" x-text="item.phone || '-'"></td>
                                <td class="px-4 py-2 text-[12px] text-gray-500 dark:text-gray-400" x-text="item.email || '-'"></td>
                                @if ($entityName === 'customer')
                                    <td class="px-4 py-2">
                                        <p class="text-xs font-semibold text-gray-800 dark:text-white"><span x-text="item.loyaltyStamps || 0"></span> stempel</p>
                                        <div class="mt-1 flex flex-wrap gap-1">
                                            <span x-show="item.loyaltyFiftyAvailable" class="rounded-full bg-warning-50 px-1.5 py-0.5 text-[10px] font-semibold text-warning-700 dark:bg-warning-500/15 dark:text-warning-300">Diskon 50%</span>
                                            <span x-show="item.loyaltyFreeCupAvailable" class="rounded-full bg-success-50 px-1.5 py-0.5 text-[10px] font-semibold text-success-700 dark:bg-success-500/15 dark:text-success-300">Gratis 1 cup</span>
                                            <span x-show="!item.loyaltyFiftyAvailable && !item.loyaltyFreeCupAvailable" class="text-[10px] text-gray-400">Belum ada reward</span>
                                        </div>
                                    </td>
                                @endif
                                <td class="px-4 py-2 text-center">
                                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="statusClass(item.status)" x-text="item.status"></span>
                                </td>
                                <td class="px-4 py-2 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <button type="button" :aria-label="`Edit ${entityLabel}`" @click="openEditModal(item)"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-md text-gray-500 transition hover:bg-brand-50 hover:text-brand-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/20 dark:text-gray-400 dark:hover:bg-brand-500/10 dark:hover:text-brand-400">
                                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M11.05 4.21669L4.99996 10.2667C4.76663 10.5 4.58329 10.7917 4.48329 11.1084L3.74996 13.3334L5.97496 12.6C6.29163 12.5 6.58329 12.3167 6.81663 12.0834L12.8666 6.03336M11.05 4.21669L12.2833 2.98336C12.9333 2.33336 13.9833 2.33336 14.6333 2.98336L14.1 2.45002C14.75 3.10002 14.75 4.15002 14.1 4.80002L12.8666 6.03336M11.05 4.21669L12.8666 6.03336"
                                                    stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                        </button>
                                        <button type="button" :aria-label="`Lihat detail ${entityLabel}`" @click="openDetailModal(item)"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-md text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-500/20 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200">
                                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M10 4.16669C5.83337 4.16669 2.91671 10 2.91671 10C2.91671 10 5.83337 15.8334 10 15.8334C14.1667 15.8334 17.0834 10 17.0834 10C17.0834 10 14.1667 4.16669 10 4.16669Z"
                                                    stroke="currentColor" stroke-width="1.5" />
                                                <path d="M10 12.5C11.3808 12.5 12.5 11.3807 12.5 10C12.5 8.61931 11.3808 7.50002 10 7.50002C8.61933 7.50002 7.50004 8.61931 7.50004 10C7.50004 11.3807 8.61933 12.5 10 12.5Z"
                                                    stroke="currentColor" stroke-width="1.5" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="filteredItems.length === 0" class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                            <td colspan="{{ $entityName === 'customer' ? 7 : 6 }}" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                Tidak ada data {{ $entityPlural }} yang cocok dengan filter.
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
                    {{ $entityPlural }}
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

        <div x-show="createModal" x-cloak @keydown.escape.window="closeCreateModal()"
            class="fixed inset-0 z-99999 flex items-center justify-center overflow-y-auto p-4">
            <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm" @click="closeCreateModal()"></div>

            <div class="relative w-full max-w-2xl rounded-2xl bg-white p-5 shadow-theme-xl dark:bg-gray-900 sm:p-6">
                <div class="mb-6 flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-800 dark:text-white/90" x-text="`Buat ${entityLabel}`"></h2>
                        <p class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">
                            Isi data master untuk kebutuhan transaksi POS.
                        </p>
                    </div>
                    <button type="button" @click="closeCreateModal()"
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-500 transition hover:bg-gray-200 hover:text-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M5 5L15 15M15 5L5 15" stroke="currentColor" stroke-width="1.7"
                                stroke-linecap="round" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="addItem()" class="space-y-5">
                    <div x-show="formError" x-cloak
                        class="rounded-lg border border-error-200 bg-error-50 px-4 py-3 text-theme-sm text-error-600 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400"
                        x-text="formError">
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Nama<span class="text-error-500">*</span>
                            </label>
                            <input type="text" x-model="draft.name"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Kode<span class="text-error-500">*</span>
                            </label>
                            <input type="text" x-model="draft.code"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Telepon
                            </label>
                            <input type="text" x-model="draft.phone"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Email
                            </label>
                            <input type="email" x-model="draft.email"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Status
                            </label>
                            <select x-model="draft.status"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                <option value="aktif">Aktif</option>
                                <option value="nonaktif">Nonaktif</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Alamat
                        </label>
                        <textarea rows="3" x-model="draft.address"
                            class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"></textarea>
                    </div>

                    <div class="flex flex-col-reverse gap-3 border-t border-gray-100 pt-5 dark:border-gray-800 sm:flex-row sm:justify-end">
                        <button type="button" @click="closeCreateModal()"
                            class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-5 py-3 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]">
                            Batal
                        </button>
                        <button type="submit"
                            class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-5 py-3 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">
                            Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div x-show="editModal" x-cloak @keydown.escape.window="closeEditModal()"
            class="fixed inset-0 z-99999 flex items-center justify-center overflow-y-auto p-4">
            <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm" @click="closeEditModal()"></div>

            <div class="relative w-full max-w-2xl rounded-2xl bg-white p-5 shadow-theme-xl dark:bg-gray-900 sm:p-6">
                <div class="mb-6 flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-800 dark:text-white/90" x-text="`Edit ${entityLabel}`"></h2>
                        <p class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">
                            Perbarui data master melalui controller update.
                        </p>
                    </div>
                    <button type="button" @click="closeEditModal()"
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-500 transition hover:bg-gray-200 hover:text-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M5 5L15 15M15 5L5 15" stroke="currentColor" stroke-width="1.7"
                                stroke-linecap="round" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="updateItem()" class="space-y-5">
                    <div x-show="editFormError" x-cloak
                        class="rounded-lg border border-error-200 bg-error-50 px-4 py-3 text-theme-sm text-error-600 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400"
                        x-text="editFormError">
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <template x-for="field in ['name', 'code', 'phone', 'email']" :key="field">
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400" x-text="fieldLabel(field)"></label>
                                <input :type="field === 'email' ? 'email' : 'text'" x-model="editDraft[field]"
                                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                            </div>
                        </template>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Status
                            </label>
                            <select x-model="editDraft.status"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                <option value="aktif">Aktif</option>
                                <option value="nonaktif">Nonaktif</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Alamat
                        </label>
                        <textarea rows="3" x-model="editDraft.address"
                            class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"></textarea>
                    </div>

                    <div class="flex flex-col-reverse gap-3 border-t border-gray-100 pt-5 dark:border-gray-800 sm:flex-row sm:justify-end">
                        <button type="button" @click="closeEditModal()"
                            class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-5 py-3 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]">
                            Batal
                        </button>
                        <button type="submit"
                            class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-5 py-3 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">
                            Update
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div x-show="detailModal" x-cloak @keydown.escape.window="closeDetailModal()"
            class="fixed inset-0 z-99999 flex items-center justify-center overflow-y-auto p-4">
            <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm" @click="closeDetailModal()"></div>

            <div class="relative w-full max-w-xl rounded-2xl bg-white p-5 shadow-theme-xl dark:bg-gray-900 sm:p-6">
                <div class="mb-6 flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h2 class="truncate text-xl font-semibold text-gray-800 dark:text-white/90" x-text="detailItem?.name || '-'"></h2>
                        <p class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">
                            Detail data master.
                        </p>
                    </div>
                    <button type="button" @click="closeDetailModal()"
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-500 transition hover:bg-gray-200 hover:text-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M5 5L15 15M15 5L5 15" stroke="currentColor" stroke-width="1.7"
                                stroke-linecap="round" />
                        </svg>
                    </button>
                </div>

                <div class="mb-5 flex flex-wrap items-center gap-3">
                    <span class="rounded-full px-2.5 py-1 text-theme-xs font-medium" :class="statusClass(detailItem?.status)" x-text="detailItem?.status || '-'"></span>
                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-theme-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400" x-text="detailItem?.code || '-'"></span>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                        <p class="text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">Telepon</p>
                        <p class="mt-2 text-theme-sm font-semibold text-gray-800 dark:text-white/90" x-text="detailItem?.phone || '-'"></p>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                        <p class="text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">Email</p>
                        <p class="mt-2 truncate text-theme-sm font-semibold text-gray-800 dark:text-white/90" x-text="detailItem?.email || '-'"></p>
                    </div>
                    @if ($entityName === 'customer')
                        <div class="rounded-xl border border-brand-200 bg-brand-50/40 p-4 dark:border-brand-500/20 dark:bg-brand-500/10">
                            <p class="text-theme-xs font-medium uppercase text-brand-600 dark:text-brand-300">Kartu Loyalitas</p>
                            <p class="mt-2 text-theme-sm font-semibold text-gray-800 dark:text-white/90"><span x-text="detailItem?.loyaltyStamps || 0"></span> stempel</p>
                            <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400" x-text="detailItem?.loyaltyFreeCupAvailable ? 'Reward gratis 1 cup tersedia' : (detailItem?.loyaltyFiftyAvailable ? 'Reward diskon 50% tersedia' : 'Belum ada reward tersedia')"></p>
                        </div>
                    @endif
                </div>

                <div class="mt-4 rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                    <p class="text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">Alamat</p>
                    <p class="mt-2 text-theme-sm leading-6 text-gray-700 dark:text-gray-300" x-text="detailItem?.address || 'Belum ada alamat.'"></p>
                </div>

                <div class="mt-6 flex justify-end border-t border-gray-100 pt-5 dark:border-gray-800">
                    <button type="button" @click="closeDetailModal()"
                        class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-5 py-3 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection
