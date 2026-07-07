@extends('layouts.app')

@section('content')
    <div x-data="productCategoryManager(@js($categories), @js($filters))" class="space-y-4">
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
                            Kategori Produk
                        </li>
                    </ol>
                </nav>

                <h1 class="mt-1 text-xl font-semibold text-gray-800 dark:text-white/90">
                    Kategori Produk
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
            <form method="GET" action="{{ route('product-categories') }}" class="flex flex-col gap-3 border-b border-gray-100 px-3 py-3 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between sm:px-4">
                <div class="relative w-full sm:max-w-[30%]">
                    <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M9.16667 15.8334C12.8486 15.8334 15.8333 12.8486 15.8333 9.16669C15.8333 5.48479 12.8486 2.50002 9.16667 2.50002C5.48477 2.50002 2.5 5.48479 2.5 9.16669C2.5 12.8486 5.48477 15.8334 9.16667 15.8334Z"
                                stroke="currentColor" stroke-width="1.5" />
                            <path d="M14.1666 14.1667L17.5 17.5" stroke="currentColor" stroke-width="1.5"
                                stroke-linecap="round" />
                        </svg>
                    </span>
                    <input id="category_search" name="search" type="search" placeholder="Cari kategori atau kode"
                        value="{{ $filters['search'] }}" x-model.debounce.300ms="filters.search" @input="currentPage = 1"
                        class="h-9 w-full rounded-lg border border-gray-300 bg-transparent py-2 pl-9 pr-3 text-xs text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800" />
                </div>

                <div class="flex flex-wrap items-center justify-end gap-2">
                    <div class="inline-flex w-fit items-center gap-1.5 rounded-lg bg-gray-50 px-2.5 py-1 text-xs text-gray-500 dark:bg-gray-900/60 dark:text-gray-400">
                        Total: <span class="font-semibold text-gray-800 dark:text-white/90" x-text="filteredCategories.length"></span> kategori
                    </div>

                    <div x-data="{ isOptionSelected: false }" class="relative w-36">
                        <select id="category_status" name="status" x-model="filters.status"
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
                <table class="w-full min-w-[760px]">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                            <th class="px-4 py-2.5 text-left">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Kategori</p>
                            </th>
                            <th class="px-4 py-2.5 text-center">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Kode</p>
                            </th>
                            <th class="px-4 py-2.5 text-center">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Produk</p>
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
                        <template x-for="category in paginatedCategories" :key="category.code">
                            <tr class="border-b border-gray-100 transition-colors last:border-0 hover:bg-gray-25 dark:border-gray-800 dark:hover:bg-white/[0.02]">
                                <td class="px-4 py-2">
                                    <div class="flex items-center gap-2">
                                        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-brand-50 text-brand-500 dark:bg-brand-500/15 dark:text-brand-400">
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M4.16663 5.83334H15.8333M4.16663 10H15.8333M4.16663 14.1667H10.8333"
                                                    stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                            </svg>
                                        </div>
                                        <p class="max-w-[260px] truncate text-[13px] font-semibold text-gray-800 dark:text-white/90" x-text="category.name"></p>
                                    </div>
                                </td>
                                <td class="px-4 py-2 text-center text-[12px] font-medium text-gray-500 dark:text-gray-400" x-text="category.code"></td>
                                <td class="px-4 py-2 text-right">
                                    <p class="text-[12px] font-semibold tabular-nums text-gray-800 dark:text-white/90" x-text="category.productCount"></p>
                                </td>
                                <td class="px-4 py-2 text-center">
                                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="statusClass(category.status)" x-text="category.status"></span>
                                </td>
                                <td class="px-4 py-2 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <button type="button" aria-label="Edit kategori" @click="openEditModal(category)"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-md text-gray-500 transition hover:bg-brand-50 hover:text-brand-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/20 dark:text-gray-400 dark:hover:bg-brand-500/10 dark:hover:text-brand-400">
                                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M11.05 4.21669L4.99996 10.2667C4.76663 10.5 4.58329 10.7917 4.48329 11.1084L3.74996 13.3334L5.97496 12.6C6.29163 12.5 6.58329 12.3167 6.81663 12.0834L12.8666 6.03336M11.05 4.21669L12.2833 2.98336C12.9333 2.33336 13.9833 2.33336 14.6333 2.98336L14.1 2.45002C14.75 3.10002 14.75 4.15002 14.1 4.80002L12.8666 6.03336M11.05 4.21669L12.8666 6.03336"
                                                    stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                        </button>
                                        <button type="button" aria-label="Lihat detail kategori" @click="openDetailModal(category)"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-md text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-500/20 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200">
                                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M10 4.16669C5.83337 4.16669 2.91671 10 2.91671 10C2.91671 10 5.83337 15.8334 10 15.8334C14.1667 15.8334 17.0834 10 17.0834 10C17.0834 10 14.1667 4.16669 10 4.16669Z"
                                                    stroke="currentColor" stroke-width="1.5" />
                                                <path d="M10 12.5C11.3808 12.5 12.5 11.3807 12.5 10C12.5 8.61931 11.3808 7.50002 10 7.50002C8.61933 7.50002 7.50004 8.61931 7.50004 10C7.50004 11.3807 8.61933 12.5 10 12.5Z"
                                                    stroke="currentColor" stroke-width="1.5" />
                                            </svg>
                                        </button>
                                        <button type="button" aria-label="Hapus kategori" @click="deleteCategory(category)"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-md text-gray-500 transition hover:bg-error-50 hover:text-error-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-error-500/20 dark:text-gray-400 dark:hover:bg-error-500/10 dark:hover:text-error-500">
                                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M7.5 4.16669H12.5M3.33337 5.83335H16.6667M15 5.83335L14.4167 14.5834C14.35 15.5667 13.5334 16.3334 12.55 16.3334H7.45004C6.46671 16.3334 5.65004 15.5667 5.58337 14.5834L5.00004 5.83335"
                                                    stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                                <path d="M8.33337 9.16669V13.3334M11.6667 9.16669V13.3334"
                                                    stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="filteredCategories.length === 0" class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                Tidak ada kategori produk yang cocok dengan filter.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col gap-3 border-t border-gray-100 px-4 py-3 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Menampilkan <span class="font-medium text-gray-800 dark:text-white/90" x-text="fromItem"></span>
                    - <span class="font-medium text-gray-800 dark:text-white/90" x-text="toItem"></span>
                    dari <span class="font-medium text-gray-800 dark:text-white/90" x-text="filteredCategories.length"></span>
                    kategori
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
                    <span class="min-w-10 text-center text-xs font-medium text-gray-500 dark:text-gray-400">
                        <span x-text="currentPage"></span>/<span x-text="totalPages"></span>
                    </span>
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

        <div x-show="createCategoryModal" x-cloak @keydown.escape.window="closeCreateModal()"
            class="fixed inset-0 z-99999 flex items-center justify-center overflow-y-auto p-4">
            <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm" @click="closeCreateModal()"></div>

            <div class="relative w-full max-w-2xl rounded-2xl bg-white p-5 shadow-theme-xl dark:bg-gray-900 sm:p-6">
                <div class="mb-6 flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-800 dark:text-white/90">Buat Kategori Produk</h2>
                        <p class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">
                            Isi data kategori untuk pengelompokan produk.
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

                <form @submit.prevent="addCategory()" class="space-y-5">
                    <div x-show="formError" x-cloak
                        class="rounded-lg border border-error-200 bg-error-50 px-4 py-3 text-theme-sm text-error-600 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400"
                        x-text="formError">
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="new_category_name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Nama kategori<span class="text-error-500">*</span>
                            </label>
                            <input id="new_category_name" type="text" x-model="draft.name"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800" />
                        </div>
                        <div>
                            <label for="new_category_code" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Kode<span class="text-error-500">*</span>
                            </label>
                            <input id="new_category_code" type="text" x-model="draft.code"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800" />
                        </div>
                        <div>
                            <label for="new_category_status" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Status
                            </label>
                            <select id="new_category_status" x-model="draft.status"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                <option value="aktif">Aktif</option>
                                <option value="nonaktif">Nonaktif</option>
                            </select>
                        </div>
                        <div>
                            <label for="new_category_product_count" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Jumlah produk
                            </label>
                            <input id="new_category_product_count" type="number" min="0" x-model="draft.productCount"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800" />
                        </div>
                    </div>

                    <div>
                        <label for="new_category_description" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Deskripsi
                        </label>
                        <textarea id="new_category_description" rows="3" x-model="draft.description"
                            class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"></textarea>
                    </div>

                    <div class="flex flex-col-reverse gap-3 border-t border-gray-100 pt-5 dark:border-gray-800 sm:flex-row sm:justify-end">
                        <button type="button" @click="closeCreateModal()"
                            class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-5 py-3 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]">
                            Batal
                        </button>
                        <button type="submit"
                            class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-5 py-3 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">
                            Simpan Kategori
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div x-show="editCategoryModal" x-cloak @keydown.escape.window="closeEditModal()"
            class="fixed inset-0 z-99999 flex items-center justify-center overflow-y-auto p-4">
            <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm" @click="closeEditModal()"></div>

            <div class="relative w-full max-w-2xl rounded-2xl bg-white p-5 shadow-theme-xl dark:bg-gray-900 sm:p-6">
                <div class="mb-6 flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-800 dark:text-white/90">Edit Kategori Produk</h2>
                        <p class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">
                            Perbarui data kategori melalui controller update.
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

                <form @submit.prevent="updateCategory()" class="space-y-5">
                    <div x-show="editFormError" x-cloak
                        class="rounded-lg border border-error-200 bg-error-50 px-4 py-3 text-theme-sm text-error-600 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400"
                        x-text="editFormError">
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="edit_category_name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Nama kategori<span class="text-error-500">*</span>
                            </label>
                            <input id="edit_category_name" type="text" x-model="editDraft.name"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800" />
                        </div>
                        <div>
                            <label for="edit_category_code" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Kode<span class="text-error-500">*</span>
                            </label>
                            <input id="edit_category_code" type="text" x-model="editDraft.code"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800" />
                        </div>
                        <div>
                            <label for="edit_category_status" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Status
                            </label>
                            <select id="edit_category_status" x-model="editDraft.status"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                <option value="aktif">Aktif</option>
                                <option value="nonaktif">Nonaktif</option>
                            </select>
                        </div>
                        <div>
                            <label for="edit_category_product_count" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Jumlah produk
                            </label>
                            <input id="edit_category_product_count" type="number" min="0" x-model="editDraft.productCount"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800" />
                        </div>
                    </div>

                    <div>
                        <label for="edit_category_description" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Deskripsi
                        </label>
                        <textarea id="edit_category_description" rows="3" x-model="editDraft.description"
                            class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"></textarea>
                    </div>

                    <div class="flex flex-col-reverse gap-3 border-t border-gray-100 pt-5 dark:border-gray-800 sm:flex-row sm:justify-end">
                        <button type="button" @click="closeEditModal()"
                            class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-5 py-3 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]">
                            Batal
                        </button>
                        <button type="submit"
                            class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-5 py-3 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">
                            Update Kategori
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div x-show="detailCategoryModal" x-cloak @keydown.escape.window="closeDetailModal()"
            class="fixed inset-0 z-99999 flex items-center justify-center overflow-y-auto p-4">
            <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm" @click="closeDetailModal()"></div>

            <div class="relative w-full max-w-xl rounded-2xl bg-white p-5 shadow-theme-xl dark:bg-gray-900 sm:p-6">
                <div class="mb-6 flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h2 class="truncate text-xl font-semibold text-gray-800 dark:text-white/90" x-text="detailCategory?.name || '-'"></h2>
                        <p class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">
                            Detail master kategori produk.
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
                    <span class="rounded-full px-2.5 py-1 text-theme-xs font-medium" :class="statusClass(detailCategory?.status)" x-text="detailCategory?.status || '-'"></span>
                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-theme-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400" x-text="detailCategory?.code || '-'"></span>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                        <p class="text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">Kode</p>
                        <p class="mt-2 text-theme-sm font-semibold text-gray-800 dark:text-white/90" x-text="detailCategory?.code || '-'"></p>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                        <p class="text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">Jumlah Produk</p>
                        <p class="mt-2 text-theme-sm font-semibold text-gray-800 dark:text-white/90" x-text="detailCategory?.productCount ?? 0"></p>
                    </div>
                </div>

                <div class="mt-4 rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                    <p class="text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">Deskripsi</p>
                    <p class="mt-2 text-theme-sm leading-6 text-gray-700 dark:text-gray-300" x-text="detailCategory?.description || 'Belum ada deskripsi kategori.'"></p>
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
