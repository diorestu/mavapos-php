@extends('layouts.app')

@section('content')
    <div x-data="productRecipeManager(@js($recipePayload), @js($rawMaterialPayload))" class="space-y-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <nav aria-label="Breadcrumb">
                    <ol class="flex items-center gap-1 text-[10px] text-gray-500 dark:text-gray-400">
                        <li><a href="{{ url('/') }}" class="transition hover:text-gray-700 dark:hover:text-gray-200">Home</a></li>
                        <li aria-hidden="true">
                            <svg class="h-3 w-3 stroke-current" viewBox="0 0 17 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M6.0765 12.667L10.2432 8.50033L6.0765 4.33366" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </li>
                        <li><a href="{{ route('products') }}" class="transition hover:text-gray-700 dark:hover:text-gray-200">Produk</a></li>
                        <li aria-hidden="true">
                            <svg class="h-3 w-3 stroke-current" viewBox="0 0 17 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M6.0765 12.667L10.2432 8.50033L6.0765 4.33366" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </li>
                        <li class="font-medium text-gray-700 dark:text-gray-300">Resep</li>
                    </ol>
                </nav>

                <h1 class="mt-1 text-xl font-semibold text-gray-800 dark:text-white/90">Resep Produk</h1>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Atur standar bahan untuk menghasilkan setiap produk.</p>
            </div>

            <button type="button" @click="openModal()"
                class="inline-flex h-10 items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-semibold text-white shadow-theme-xs transition hover:bg-brand-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30">
                Atur Resep
            </button>
        </div>

        <section class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Produk</p>
                <p class="mt-2 text-xl font-semibold tabular-nums text-gray-900 dark:text-white">{{ number_format($products->count(), 0, ',', '.') }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Produk Dengan Resep</p>
                <p class="mt-2 text-xl font-semibold tabular-nums text-gray-900 dark:text-white">{{ number_format($products->filter(fn ($product) => $product->recipeItems->isNotEmpty())->count(), 0, ',', '.') }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Item Bahan</p>
                <p class="mt-2 text-xl font-semibold tabular-nums text-gray-900 dark:text-white">{{ number_format($products->sum(fn ($product) => $product->recipeItems->count()), 0, ',', '.') }}</p>
            </div>
        </section>

        @if ($rawMaterials->isEmpty())
            <div class="rounded-lg border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:border-warning-500/20 dark:bg-warning-500/10 dark:text-warning-300">
                Belum ada bahan baku. Tambahkan data di menu <a href="{{ route('raw-materials') }}" class="font-semibold underline">Inventory</a> sebelum membuat resep.
            </div>
        @endif

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                <div>
                    <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Daftar Resep</h2>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Klik atur untuk membuat atau mengganti standar bahan produk.</p>
                </div>
            </div>

            <div class="max-w-full overflow-x-auto custom-scrollbar">
                <table class="w-full min-w-[860px]">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                            <th class="px-4 py-2.5 text-left"><p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Produk</p></th>
                            <th class="px-4 py-2.5 text-center"><p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">SKU</p></th>
                            <th class="px-4 py-2.5 text-left"><p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Standar Bahan</p></th>
                            <th class="px-4 py-2.5 text-center"><p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Aksi</p></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($products as $product)
                            <tr class="border-b border-gray-100 transition-colors last:border-0 hover:bg-gray-25 dark:border-gray-800 dark:hover:bg-white/[0.02]">
                                <td class="px-4 py-2">
                                    <p class="max-w-[260px] truncate text-[13px] font-semibold text-gray-800 dark:text-white/90">{{ $product->name }}</p>
                                    <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">{{ $product->category?->name ?? 'Umum' }}</p>
                                </td>
                                <td class="px-4 py-2 text-center text-[12px] font-medium text-gray-500 dark:text-gray-400">{{ $product->sku }}</td>
                                <td class="px-4 py-2">
                                    @if ($product->recipeItems->isEmpty())
                                        <span class="text-xs text-gray-500 dark:text-gray-400">Belum ada resep.</span>
                                    @else
                                        <div class="flex max-w-[420px] flex-wrap gap-1.5">
                                            @foreach ($product->recipeItems->take(4) as $item)
                                                <span class="rounded-full bg-gray-50 px-2 py-0.5 text-[11px] font-medium text-gray-600 dark:bg-gray-900/60 dark:text-gray-400">
                                                    {{ $item->item_name }} · {{ rtrim(rtrim((string) $item->quantity, '0'), '.') }} {{ $item->unit }}
                                                </span>
                                            @endforeach
                                            @if ($product->recipeItems->count() > 4)
                                                <span class="rounded-full bg-brand-50 px-2 py-0.5 text-[11px] font-medium text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">
                                                    +{{ $product->recipeItems->count() - 4 }} item
                                                </span>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-center">
                                    <button type="button" @click="openModal({{ $product->id }})"
                                        class="inline-flex h-8 items-center justify-center rounded-lg border border-gray-200 px-3 text-xs font-semibold text-gray-600 transition hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.04]">
                                        Atur
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    Belum ada produk.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div x-show="modalOpen" x-cloak @keydown.escape.window="closeModal()"
            class="fixed inset-0 z-99999 flex items-center justify-center overflow-y-auto p-4">
            <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm" @click="closeModal()"></div>

            <div class="relative w-full max-w-3xl rounded-2xl bg-white p-5 shadow-theme-xl dark:bg-gray-900 sm:p-6">
                <div class="mb-5 flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-800 dark:text-white/90">Atur Resep Produk</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Pilih produk, lalu pilih bahan baku dari inventory.</p>
                    </div>
                    <button type="button" @click="closeModal()"
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-500 transition hover:bg-gray-200 hover:text-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M5 5L15 15M15 5L5 15" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                        </svg>
                    </button>
                </div>

                <form method="POST" action="{{ route('product-recipes.store') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label for="product_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Produk<span class="text-error-500">*</span></label>
                        <select id="product_id" name="product_id" x-model="selectedProductId" @change="loadSelectedRecipe()"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                            <option value="">Pilih produk</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}">{{ $product->name }} - {{ $product->sku }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rounded-xl border border-gray-200 dark:border-gray-800">
                        <div class="flex items-center justify-between border-b border-gray-100 px-3 py-2.5 dark:border-gray-800">
                            <p class="text-sm font-semibold text-gray-800 dark:text-white/90">Item Bahan</p>
                            <button type="button" @click="addItem()"
                                class="inline-flex h-8 items-center justify-center rounded-lg border border-gray-200 px-3 text-xs font-semibold text-gray-600 transition hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.04]">
                                Tambah Item
                            </button>
                        </div>

                        <div class="space-y-2 p-3">
                            <template x-for="(item, index) in items" :key="index">
                                <div class="grid gap-2 rounded-lg border border-gray-100 bg-gray-50 p-2 dark:border-gray-800 dark:bg-gray-900/50 sm:grid-cols-[minmax(0,1fr)_120px_110px_40px]">
                                    <select x-model="item.raw_material_id" :name="`items[${index}][raw_material_id]`" @change="syncItemUnit(item)"
                                        class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                        <option value="">Pilih bahan baku</option>
                                        @foreach ($rawMaterials as $material)
                                            <option value="{{ $material->id }}">{{ $material->name }} - {{ $material->code }} ({{ rtrim(rtrim((string) $material->stock, '0'), '.') }} {{ $material->unit }})</option>
                                        @endforeach
                                    </select>
                                    <input type="number" min="0.001" step="0.001" x-model="item.quantity" :name="`items[${index}][quantity]`" placeholder="Jumlah"
                                        class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-right text-sm tabular-nums text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                                    <div class="flex h-10 items-center rounded-lg border border-gray-200 bg-white px-3 text-sm font-medium text-gray-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400" x-text="item.unit || '-'"></div>
                                    <button type="button" @click="removeItem(index)" :disabled="items.length === 1" aria-label="Hapus item"
                                        class="grid h-10 w-10 place-items-center rounded-lg text-gray-400 transition hover:bg-error-50 hover:text-error-600 disabled:cursor-not-allowed disabled:opacity-40 dark:hover:bg-error-500/10">
                                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M5 5L15 15M15 5L5 15" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                                        </svg>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="flex flex-col-reverse gap-3 border-t border-gray-100 pt-4 dark:border-gray-800 sm:flex-row sm:justify-end">
                        <button type="button" @click="closeModal()"
                            class="inline-flex h-10 items-center justify-center rounded-lg border border-gray-200 px-4 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.04]">
                            Batal
                        </button>
                        <button type="submit"
                            class="inline-flex h-10 items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-semibold text-white shadow-theme-xs transition hover:bg-brand-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30">
                            Simpan Resep
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
