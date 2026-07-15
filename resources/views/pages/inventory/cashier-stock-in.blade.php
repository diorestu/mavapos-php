@extends('layouts.app')

@section('content')
    <div class="space-y-4" x-data="cashierStockInManager(@js($items), @js(route('cashier-stock-in.store')))" x-init="$nextTick(() => $refs.search?.focus())">
        <div>
            <h1 class="text-xl font-semibold text-gray-800 dark:text-white/90">Stok Masuk</h1>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Catat barang yang datang untuk cabang aktif tanpa mengubah harga atau stok keluar.</p>
        </div>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_360px]">
            <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Cari produk atau varian</span>
                    <input x-ref="search" x-model="query" type="search" placeholder="Nama, SKU, atau barcode" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90">
                </label>
                <div class="mt-3 max-h-[60vh] space-y-2 overflow-y-auto custom-scrollbar">
                    <template x-for="item in filteredItems" :key="item.sku">
                        <button type="button" @click="select(item)" :class="selected?.sku === item.sku ? 'border-brand-400 bg-brand-50 dark:bg-brand-500/10' : 'border-gray-200 dark:border-gray-800'" class="flex min-h-11 w-full items-center justify-between rounded-lg border px-3 py-2 text-left">
                            <span class="min-w-0"><span class="block truncate text-sm font-semibold text-gray-800 dark:text-white/90" x-text="item.name"></span><span class="text-xs text-gray-500" x-text="`${item.sku} · ${item.category}`"></span></span>
                            <span class="ml-3 shrink-0 text-xs font-semibold" x-text="`Stok ${item.stock}`"></span>
                        </button>
                    </template>
                    <p x-show="filteredItems.length === 0" class="py-8 text-center text-sm text-gray-500">Produk tidak ditemukan. Periksa nama, SKU, barcode, atau cabang.</p>
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Tambah Stok</h2>
                <p class="mt-2 text-sm text-gray-500" x-text="selected?.name || 'Pilih produk terlebih dahulu.'"></p>
                <label class="mt-4 block"><span class="mb-1 block text-xs font-medium">Jumlah</span><input x-model="quantity" type="number" min="1" step="1" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 dark:border-gray-700"></label>
                <details class="mt-3"><summary class="cursor-pointer text-xs font-semibold text-brand-600">Tambahkan catatan</summary><div class="mt-2 space-y-2"><input x-model="reference" placeholder="Referensi (opsional)" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700"><textarea x-model="note" rows="2" placeholder="Catatan (opsional)" class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700"></textarea></div></details>
                <p x-show="error" x-text="error" aria-live="polite" class="mt-3 rounded-lg bg-error-50 px-3 py-2 text-xs text-error-700"></p>
                <div x-show="result" aria-live="polite" class="mt-3 rounded-lg bg-success-50 px-3 py-2 text-sm font-semibold text-success-700"><span x-text="result?.stockBefore"></span> → <span x-text="result?.stockAfter"></span></div>
                <button type="button" @click="submit()" :disabled="loading || !selected" class="mt-4 inline-flex h-11 w-full items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-semibold text-white disabled:opacity-50"><span x-text="loading ? 'Menyimpan...' : 'Tambah Stok'"></span></button>
            </section>
        </div>
    </div>
@endsection
