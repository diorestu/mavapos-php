@extends('layouts.app')

@php
    $rupiah = fn ($value) => 'Rp'.number_format((int) $value, 0, ',', '.');
@endphp

@section('content')
    <div class="space-y-4">
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
                        <li class="font-medium text-gray-700 dark:text-gray-300">Pengeluaran</li>
                    </ol>
                </nav>
                <h1 class="mt-1 text-xl font-semibold text-gray-800 dark:text-white/90">Pengeluaran</h1>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Catat belanja sekali pakai atau bahan utama yang menambah stok produk.</p>
            </div>
        </div>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Pengeluaran</p>
                <p class="mt-2 text-xl font-semibold tabular-nums text-gray-900 dark:text-white">{{ $rupiah($summary['total']) }}</p>
                <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">{{ number_format($summary['count'], 0, ',', '.') }} transaksi tercatat</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Belanja Stok</p>
                <p class="mt-2 text-xl font-semibold tabular-nums text-gray-900 dark:text-white">{{ $rupiah($summary['stock']) }}</p>
                <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">Mempengaruhi stok produk</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Operasional</p>
                <p class="mt-2 text-xl font-semibold tabular-nums text-gray-900 dark:text-white">{{ $rupiah($summary['operational']) }}</p>
                <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">Sekali pakai / non-stok</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Produk Tersedia</p>
                <p class="mt-2 text-xl font-semibold tabular-nums text-gray-900 dark:text-white">{{ number_format($products->count(), 0, ',', '.') }}</p>
                <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">Bisa dipilih untuk belanja stok</p>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-[380px_minmax(0,1fr)]">
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Catat Pengeluaran</h2>

                @if ($errors->any())
                    <div class="mt-3 rounded-lg border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/20 dark:bg-error-500/10 dark:text-error-300">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('expenses.store') }}"
                    x-data="{
                        type: @js(old('type', 'operational')),
                        quantity: @js(old('quantity', '')),
                        unitCost: @js(old('unit_cost', '')),
                        amount: @js(old('amount', '')),
                        syncAmount() {
                            if (this.type === 'stock' && Number(this.quantity) > 0 && Number(this.unitCost) >= 0) {
                                this.amount = Number(this.quantity) * Number(this.unitCost);
                            }
                        }
                    }"
                    x-effect="syncAmount()"
                    class="mt-4 space-y-3">
                    @csrf

                    <div>
                        <label for="type" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Jenis Pengeluaran</label>
                        <select id="type" name="type" x-model="type"
                            class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                            <option value="operational">Belanja sekali pakai / operasional</option>
                            <option value="stock">Bahan utama / menambah stok</option>
                        </select>
                    </div>

                    <div>
                        <label for="title" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Nama Pengeluaran<span class="text-error-500">*</span></label>
                        <input id="title" name="title" value="{{ old('title') }}" type="text" placeholder="Contoh: Gula aren, plastik cup, listrik"
                            class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                        <div>
                            <label for="category" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Kategori</label>
                            <input id="category" name="category" value="{{ old('category') }}" type="text" placeholder="Bahan baku, utilitas"
                                class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                        </div>
                        <div>
                            <label for="spent_at" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Tanggal<span class="text-error-500">*</span></label>
                            <input id="spent_at" name="spent_at" value="{{ old('spent_at', now()->format('Y-m-d\TH:i')) }}" type="datetime-local"
                                class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                        </div>
                    </div>

                    <div x-show="type === 'stock'" x-cloak class="space-y-3 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-gray-900/50">
                        <div>
                            <label for="product_id" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Produk Stok<span class="text-error-500">*</span></label>
                            <select id="product_id" name="product_id"
                                class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                <option value="">Pilih produk</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}" @selected(old('product_id') == $product->id)>
                                        {{ $product->name }} - {{ $product->sku }} (stok {{ $product->stock }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                            <div>
                                <label for="quantity" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Jumlah Masuk<span class="text-error-500">*</span></label>
                                <input id="quantity" name="quantity" x-model="quantity" type="number" min="1" placeholder="0"
                                    class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                            </div>
                            <div>
                                <label for="unit_cost" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Harga Satuan</label>
                                <input id="unit_cost" name="unit_cost" x-model="unitCost" type="number" min="0" placeholder="0"
                                    class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                            </div>
                        </div>
                    </div>

                    <div>
                        <label for="amount" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Total Nominal<span class="text-error-500">*</span></label>
                        <input id="amount" name="amount" x-model="amount" type="number" min="1" placeholder="0"
                            class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-right text-sm tabular-nums text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                    </div>

                    <div>
                        <label for="reference" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Referensi</label>
                        <input id="reference" name="reference" value="{{ old('reference') }}" type="text" placeholder="No nota / PO"
                            class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                    </div>

                    <div>
                        <label for="note" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Catatan</label>
                        <textarea id="note" name="note" rows="3" placeholder="Catatan tambahan"
                            class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">{{ old('note') }}</textarea>
                    </div>

                    <button type="submit" class="inline-flex h-10 w-full items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-semibold text-white shadow-theme-xs transition hover:bg-brand-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30">
                        Simpan Pengeluaran
                    </button>
                </form>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Riwayat Pengeluaran</h2>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Belanja operasional dan stok terbaru.</p>
                    </div>
                </div>

                <div class="max-w-full overflow-x-auto custom-scrollbar">
                    <table class="w-full min-w-[920px]">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                                <th class="px-4 py-2.5 text-left"><p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Tanggal</p></th>
                                <th class="px-4 py-2.5 text-left"><p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Pengeluaran</p></th>
                                <th class="px-4 py-2.5 text-center"><p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Jenis</p></th>
                                <th class="px-4 py-2.5 text-left"><p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Produk</p></th>
                                <th class="px-4 py-2.5 text-right"><p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Jumlah</p></th>
                                <th class="px-4 py-2.5 text-right"><p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Nominal</p></th>
                                <th class="px-4 py-2.5 text-center"><p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Ref</p></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($expenses as $expense)
                                <tr class="border-b border-gray-100 transition-colors last:border-0 hover:bg-gray-25 dark:border-gray-800 dark:hover:bg-white/[0.02]">
                                    <td class="px-4 py-2 text-[12px] text-gray-500 dark:text-gray-400">{{ $expense->spent_at?->format('d M Y H:i') }}</td>
                                    <td class="px-4 py-2">
                                        <p class="max-w-[240px] truncate text-[13px] font-semibold text-gray-800 dark:text-white/90">{{ $expense->title }}</p>
                                        <p class="mt-0.5 max-w-[240px] truncate text-[11px] text-gray-500 dark:text-gray-400">{{ $expense->category ?: 'Tanpa kategori' }}</p>
                                    </td>
                                    <td class="px-4 py-2 text-center">
                                        <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $expense->type === 'stock' ? 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400' : 'bg-gray-50 text-gray-600 dark:bg-gray-500/15 dark:text-gray-400' }}">
                                            {{ $expense->type === 'stock' ? 'Stok' : 'Operasional' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-[12px] text-gray-500 dark:text-gray-400">
                                        {{ $expense->product?->name ?? '-' }}
                                    </td>
                                    <td class="px-4 py-2 text-right text-[12px] font-semibold tabular-nums text-gray-800 dark:text-white/90">
                                        {{ $expense->quantity ? number_format($expense->quantity, 0, ',', '.') : '-' }}
                                    </td>
                                    <td class="px-4 py-2 text-right text-[12px] font-semibold tabular-nums text-gray-800 dark:text-white/90">{{ $rupiah($expense->amount) }}</td>
                                    <td class="px-4 py-2 text-center text-[12px] text-gray-500 dark:text-gray-400">{{ $expense->reference ?: $expense->expense_number }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                        Belum ada pengeluaran.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
@endsection
