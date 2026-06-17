@extends('layouts.app')

@section('content')
    {{-- Hallmark · component: product table/filter · genre: utilitarian · theme: existing TailAdmin tokens · density: compact --}}
    <div x-data="productManager(@js($products), @js($categories))" class="space-y-4">
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
                            Produk
                        </li>
                    </ol>
                </nav>

                <h1 class="mt-1 text-xl font-semibold text-gray-800 dark:text-white/90">
                    Produk
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
                    <input id="product_search" type="text" placeholder="Cari produk atau SKU"
                        x-model.debounce.300ms="filters.search" @input="currentPage = 1"
                        class="h-9 w-full rounded-lg border border-gray-300 bg-transparent py-2 pl-9 pr-3 text-xs text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800" />
                </div>

                <div class="flex flex-wrap items-center justify-end gap-2">
                    <div class="inline-flex w-fit items-center gap-1.5 rounded-lg bg-gray-50 px-2.5 py-1 text-xs text-gray-500 dark:bg-gray-900/60 dark:text-gray-400">
                        Total: <span class="font-semibold text-gray-800 dark:text-white/90" x-text="filteredProducts.length"></span> produk
                    </div>

                    <div x-data="{ isOptionSelected: false }" class="relative w-40">
                        <select id="product_category" x-model="filters.category"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-9 w-full appearance-none rounded-lg border border-gray-300 bg-transparent bg-none px-3 py-2 pr-9 text-xs text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                            :class="isOptionSelected && 'text-gray-800 dark:text-white/90'"
                            @change="isOptionSelected = true; currentPage = 1">
                            <option value="" class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">Semua Kategori</option>
                            <template x-for="category in categoryOptions" :key="category.code">
                                <option :value="category.code" class="text-gray-700 dark:bg-gray-900 dark:text-gray-400" x-text="category.name"></option>
                            </template>
                        </select>
                        <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="1.6"
                                    stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                    </div>

                    <div x-data="{ isOptionSelected: false }" class="relative w-36">
                        <select id="product_status" x-model="filters.status"
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
                <table class="w-full min-w-[860px]">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                            <th class="px-4 py-2.5 text-left">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Produk</p>
                            </th>
                            <th class="px-4 py-2.5 text-center">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">SKU</p>
                            </th>
                            <th class="px-4 py-2.5 text-center">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Kategori</p>
                            </th>
                            <th class="px-4 py-2.5 text-center">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Stok</p>
                            </th>
                            <th class="px-4 py-2.5 text-center">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Harga Jual</p>
                            </th>
                            <th class="px-4 py-2.5 text-center">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</p>
                            </th>
                            <th class="px-4 py-2.5 text-center">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Aksi</p>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="product in paginatedProducts" :key="product.sku">
                            <tr class="border-b border-gray-100 transition-colors last:border-0 hover:bg-gray-25 dark:border-gray-800 dark:hover:bg-white/[0.02]">
                                <td class="px-4 py-2">
                                    <div class="flex items-center gap-2">
                                        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-brand-50 text-brand-500 dark:bg-brand-500/15 dark:text-brand-400">
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M4.16663 6.66669L9.99996 10L15.8333 6.66669M9.99996 16.6667V10"
                                                    stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                                    stroke-linejoin="round" />
                                                <path
                                                    d="M3.33337 5.83335L10 2.5L16.6667 5.83335V14.1667L10 17.5L3.33337 14.1667V5.83335Z"
                                                    stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
                                            </svg>
                                        </div>
                                        <p class="max-w-[220px] truncate text-[13px] font-semibold text-gray-800 dark:text-white/90" x-text="product.name"></p>
                                    </div>
                                </td>
                                <td class="px-4 py-2 text-center text-[12px] font-medium text-gray-500 dark:text-gray-400" x-text="product.sku"></td>
                                <td class="px-4 py-2 text-center text-[12px] text-gray-500 dark:text-gray-400" x-text="product.category"></td>
                                <td class="px-4 py-2 text-right">
                                    <p class="text-[12px] font-semibold tabular-nums text-gray-800 dark:text-white/90" x-text="product.stock"></p>
                                </td>
                                <td class="px-4 py-2 text-right text-[12px] font-semibold tabular-nums text-gray-800 dark:text-white/90" x-text="product.price"></td>
                                <td class="px-4 py-2 text-center">
                                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="statusClass(product.status)" x-text="product.status">
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <button type="button" aria-label="Edit produk" @click="openEditModal(product)"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-md text-gray-500 transition hover:bg-brand-50 hover:text-brand-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/20 dark:text-gray-400 dark:hover:bg-brand-500/10 dark:hover:text-brand-400">
                                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M11.05 4.21669L4.99996 10.2667C4.76663 10.5 4.58329 10.7917 4.48329 11.1084L3.74996 13.3334L5.97496 12.6C6.29163 12.5 6.58329 12.3167 6.81663 12.0834L12.8666 6.03336"
                                                    stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                                    stroke-linejoin="round" />
                                                <path d="M11.95 3.31668C12.4583 2.80834 13.2833 2.80834 13.7916 3.31668L13.7666 3.29168C14.275 3.80001 14.275 4.62501 13.7666 5.13334L12.85 6.05001L11.0333 4.23334L11.95 3.31668Z"
                                                    stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                                    stroke-linejoin="round" />
                                                <path d="M3.33329 16.6667H16.6666" stroke="currentColor" stroke-width="1.5"
                                                    stroke-linecap="round" />
                                            </svg>
                                        </button>
                                        <button type="button" aria-label="Lihat detail produk" @click="openDetailModal(product)"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-md text-gray-500 transition hover:bg-brand-50 hover:text-brand-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/20 dark:text-gray-400 dark:hover:bg-brand-500/10 dark:hover:text-brand-400">
                                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M10 4.16669C5.83329 4.16669 2.91663 10 2.91663 10C2.91663 10 5.83329 15.8334 10 15.8334C14.1666 15.8334 17.0833 10 17.0833 10C17.0833 10 14.1666 4.16669 10 4.16669Z"
                                                    stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                                    stroke-linejoin="round" />
                                                <path d="M10 12.5C11.3807 12.5 12.5 11.3807 12.5 10C12.5 8.61931 11.3807 7.5 10 7.5C8.61925 7.5 7.49996 8.61931 7.49996 10C7.49996 11.3807 8.61925 12.5 10 12.5Z"
                                                    stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                                    stroke-linejoin="round" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="filteredProducts.length === 0" class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                            <td colspan="7" class="px-4 py-8 text-center text-xs text-gray-500 dark:text-gray-400">
                                Tidak ada produk yang cocok dengan filter.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col gap-3 border-t border-gray-100 px-3 py-3 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between sm:px-4">
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Menampilkan
                    <span class="font-medium text-gray-800 dark:text-white/90" x-text="fromItem"></span>
                    -
                    <span class="font-medium text-gray-800 dark:text-white/90" x-text="toItem"></span>
                    dari
                    <span class="font-medium text-gray-800 dark:text-white/90" x-text="filteredProducts.length"></span>
                    produk
                </p>

                <div class="flex items-center justify-end gap-1.5">
                    <button type="button" @click="firstPage()" :disabled="currentPage === 1" aria-label="Halaman pertama"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-white text-gray-700 shadow-theme-xs transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M5 4.16666V15.8333M15 4.16666L9.16667 10L15 15.8333" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                    <button type="button" @click="previousPage()" :disabled="currentPage === 1" aria-label="Halaman sebelumnya"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-white text-gray-700 shadow-theme-xs transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12.5 4.16666L6.66667 10L12.5 15.8333" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>

                    <template x-for="page in totalPages" :key="page">
                        <button type="button" @click="goToPage(page)"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-xs font-semibold transition"
                            :class="currentPage === page ? 'bg-brand-500 text-white shadow-theme-xs' : 'bg-white text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]'"
                            x-text="page"></button>
                    </template>

                    <button type="button" @click="nextPage()" :disabled="currentPage === totalPages" aria-label="Halaman berikutnya"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-white text-gray-700 shadow-theme-xs transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M7.5 4.16666L13.3333 10L7.5 15.8333" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                    <button type="button" @click="lastPage()" :disabled="currentPage === totalPages" aria-label="Halaman terakhir"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-white text-gray-700 shadow-theme-xs transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M15 4.16666V15.8333M5 4.16666L10.8333 10L5 15.8333" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                </div>
            </div>
        </section>

        <div x-show="createProductModal" x-cloak @keydown.escape.window="closeCreateModal()"
            class="fixed inset-0 z-99999 flex items-center justify-center overflow-y-auto p-4">
            <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm" @click="closeCreateModal()"></div>

            <div class="relative w-full max-w-3xl rounded-2xl bg-white p-5 shadow-theme-xl dark:bg-gray-900 sm:p-6">
                <div class="mb-6 flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-800 dark:text-white/90">Buat Produk Baru</h2>
                        <p class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">
                            Isi data produk untuk ditampilkan di POS dan stok inventori.
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

                <form @submit.prevent="addProduct()" class="space-y-5">
                    <div x-show="formError" x-cloak
                        class="rounded-lg border border-error-200 bg-error-50 px-4 py-3 text-theme-sm text-error-600 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400"
                        x-text="formError">
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="new_product_name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Nama produk<span class="text-error-500">*</span>
                            </label>
                            <input id="new_product_name" type="text" placeholder="Contoh: Kopi Susu Aren 250ml"
                                x-model="draft.name"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800" />
                        </div>
                        <div>
                            <label for="new_product_sku" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                SKU<span class="text-error-500">*</span>
                            </label>
                            <input id="new_product_sku" type="text" placeholder="SKU-000"
                                x-model="draft.sku"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800" />
                        </div>
                        <div>
                            <label for="new_product_category" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Kategori
                            </label>
                            <div x-data="{ isOptionSelected: false }" class="relative">
                                <select id="new_product_category" x-model="draft.category"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent bg-none px-4 py-2.5 pr-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                                    :class="isOptionSelected && 'text-gray-800 dark:text-white/90'"
                                    @change="isOptionSelected = true">
                                    <option value="" class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">Pilih Kategori</option>
                                    <template x-for="category in activeCategoryOptions" :key="category.code">
                                        <option :value="category.code" class="text-gray-700 dark:bg-gray-900 dark:text-gray-400" x-text="category.name"></option>
                                    </template>
                                </select>
                                <span class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400">
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="1.6"
                                            stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </span>
                            </div>
                        </div>
                        <div>
                            <label for="new_product_barcode" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Barcode
                            </label>
                            <input id="new_product_barcode" type="text" placeholder="Opsional"
                                x-model="draft.barcode"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800" />
                        </div>
                        <div>
                            <label for="new_product_buy_price" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Harga modal
                            </label>
                            <input id="new_product_buy_price" type="number" min="0" placeholder="0"
                                x-model="draft.buyPrice"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800" />
                        </div>
                        <div>
                            <label for="new_product_sell_price" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Harga jual<span class="text-error-500">*</span>
                            </label>
                            <input id="new_product_sell_price" type="number" min="0" placeholder="0"
                                x-model="draft.sellPrice"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800" />
                        </div>
                        <div>
                            <label for="new_product_stock" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Stok awal
                            </label>
                            <input id="new_product_stock" type="number" min="0" placeholder="0"
                                x-model="draft.stock"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800" />
                        </div>
                        <div>
                            <label for="new_product_min_stock" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Batas stok minimum
                            </label>
                            <input id="new_product_min_stock" type="number" min="0" placeholder="10"
                                x-model="draft.minStock"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800" />
                        </div>
                    </div>

                    <div>
                        <label for="new_product_description" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Deskripsi
                        </label>
                        <textarea id="new_product_description" rows="3" placeholder="Catatan produk untuk kasir atau inventori"
                            x-model="draft.description"
                            class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"></textarea>
                    </div>

                    <div class="flex flex-col-reverse gap-3 border-t border-gray-100 pt-5 dark:border-gray-800 sm:flex-row sm:justify-end">
                        <button type="button" @click="closeCreateModal()"
                            class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-5 py-3 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]">
                            Batal
                        </button>
                        <button type="submit"
                            class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-5 py-3 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">
                            Simpan Produk
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div x-show="editProductModal" x-cloak @keydown.escape.window="closeEditModal()"
            class="fixed inset-0 z-99999 flex items-center justify-center overflow-y-auto p-4">
            <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm" @click="closeEditModal()"></div>

            <div class="relative w-full max-w-3xl rounded-2xl bg-white p-5 shadow-theme-xl dark:bg-gray-900 sm:p-6">
                <div class="mb-6 flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-800 dark:text-white/90">Edit Produk</h2>
                        <p class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">
                            Perbarui data produk melalui controller update.
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

                <form @submit.prevent="updateProduct()" class="space-y-5">
                    <div x-show="editFormError" x-cloak
                        class="rounded-lg border border-error-200 bg-error-50 px-4 py-3 text-theme-sm text-error-600 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400"
                        x-text="editFormError">
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="edit_product_name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Nama produk<span class="text-error-500">*</span>
                            </label>
                            <input id="edit_product_name" type="text" x-model="editDraft.name"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800" />
                        </div>
                        <div>
                            <label for="edit_product_sku" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                SKU<span class="text-error-500">*</span>
                            </label>
                            <input id="edit_product_sku" type="text" x-model="editDraft.sku"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800" />
                        </div>
                        <div>
                            <label for="edit_product_category" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Kategori
                            </label>
                            <div x-data="{ isOptionSelected: false }" class="relative">
                                <select id="edit_product_category" x-model="editDraft.category"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent bg-none px-4 py-2.5 pr-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                                    :class="(isOptionSelected || editDraft.category) && 'text-gray-800 dark:text-white/90'"
                                    @change="isOptionSelected = true">
                                    <option value="" class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">Pilih Kategori</option>
                                    <template x-for="category in categoryOptions" :key="category.code">
                                        <option :value="category.code" class="text-gray-700 dark:bg-gray-900 dark:text-gray-400" x-text="category.name"></option>
                                    </template>
                                </select>
                                <span class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400">
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="1.6"
                                            stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </span>
                            </div>
                        </div>
                        <div>
                            <label for="edit_product_barcode" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Barcode
                            </label>
                            <input id="edit_product_barcode" type="text" x-model="editDraft.barcode"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800" />
                        </div>
                        <div>
                            <label for="edit_product_buy_price" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Harga modal
                            </label>
                            <input id="edit_product_buy_price" type="number" min="0" x-model="editDraft.buyPrice"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800" />
                        </div>
                        <div>
                            <label for="edit_product_sell_price" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Harga jual<span class="text-error-500">*</span>
                            </label>
                            <input id="edit_product_sell_price" type="number" min="0" x-model="editDraft.sellPrice"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800" />
                        </div>
                        <div>
                            <label for="edit_product_stock" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Stok
                            </label>
                            <input id="edit_product_stock" type="number" min="0" x-model="editDraft.stock"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800" />
                        </div>
                        <div>
                            <label for="edit_product_min_stock" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Batas stok minimum
                            </label>
                            <input id="edit_product_min_stock" type="number" min="0" x-model="editDraft.minStock"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800" />
                        </div>
                    </div>

                    <div>
                        <label for="edit_product_description" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Deskripsi
                        </label>
                        <textarea id="edit_product_description" rows="3" x-model="editDraft.description"
                            class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"></textarea>
                    </div>

                    <div class="flex flex-col-reverse gap-3 border-t border-gray-100 pt-5 dark:border-gray-800 sm:flex-row sm:justify-end">
                        <button type="button" @click="closeEditModal()"
                            class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-5 py-3 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]">
                            Batal
                        </button>
                        <button type="submit"
                            class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-5 py-3 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">
                            Update Produk
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div x-show="detailProductModal" x-cloak @keydown.escape.window="closeDetailModal()"
            class="fixed inset-0 z-99999 flex items-center justify-center overflow-y-auto p-4">
            <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm" @click="closeDetailModal()"></div>

            <div class="relative w-full max-w-2xl rounded-2xl bg-white p-5 shadow-theme-xl dark:bg-gray-900 sm:p-6">
                <div class="mb-6 flex items-start justify-between gap-4">
                    <div class="flex min-w-0 items-start gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-500/15 dark:text-brand-400">
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M4.16663 6.66669L9.99996 10L15.8333 6.66669M9.99996 16.6667V10"
                                    stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                    stroke-linejoin="round" />
                                <path d="M3.33337 5.83335L10 2.5L16.6667 5.83335V14.1667L10 17.5L3.33337 14.1667V5.83335Z"
                                    stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h2 class="truncate text-xl font-semibold text-gray-800 dark:text-white/90" x-text="detailProduct?.name || '-'"></h2>
                            <p class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">
                                Detail master produk untuk POS dan inventori.
                            </p>
                        </div>
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
                    <span class="rounded-full px-2.5 py-1 text-theme-xs font-medium" :class="statusClass(detailProduct?.status)" x-text="detailProduct?.status || '-'"></span>
                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-theme-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400" x-text="detailProduct?.category || '-'"></span>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                        <p class="text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">SKU</p>
                        <p class="mt-2 text-theme-sm font-semibold text-gray-800 dark:text-white/90" x-text="detailProduct?.sku || '-'"></p>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                        <p class="text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">Barcode</p>
                        <p class="mt-2 text-theme-sm font-semibold text-gray-800 dark:text-white/90" x-text="detailProduct?.barcode || 'Belum diisi'"></p>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                        <p class="text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">Harga Jual</p>
                        <p class="mt-2 text-theme-sm font-semibold text-gray-800 dark:text-white/90" x-text="detailProduct?.price || '-'"></p>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                        <p class="text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">Harga Modal</p>
                        <p class="mt-2 text-theme-sm font-semibold text-gray-800 dark:text-white/90" x-text="detailProduct?.buyPrice || 'Belum diisi'"></p>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                        <p class="text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">Stok Saat Ini</p>
                        <p class="mt-2 text-theme-sm font-semibold text-gray-800 dark:text-white/90" x-text="detailProduct?.stock ?? '-'"></p>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                        <p class="text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">Batas Stok Minimum</p>
                        <p class="mt-2 text-theme-sm font-semibold text-gray-800 dark:text-white/90" x-text="detailProduct?.minStock ?? 'Belum diisi'"></p>
                    </div>
                </div>

                <div class="mt-4 rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                    <p class="text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">Deskripsi</p>
                    <p class="mt-2 text-theme-sm leading-6 text-gray-700 dark:text-gray-300" x-text="detailProduct?.description || 'Belum ada deskripsi produk.'"></p>
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
