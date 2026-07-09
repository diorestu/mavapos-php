@extends('layouts.app')

@php
    $rupiah = fn ($value) => 'Rp'.number_format((int) $value, 0, ',', '.');
    $decimal = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.');
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
                        <li class="font-medium text-gray-700 dark:text-gray-300">Inventory</li>
                    </ol>
                </nav>
                <h1 class="mt-1 text-xl font-semibold text-gray-800 dark:text-white/90">Inventory Bahan Baku</h1>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Catat barang yang digunakan sebagai bahan baku, bukan produk yang dijual.</p>
            </div>
        </div>

        <section class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Bahan Baku</p>
                <p class="mt-2 text-xl font-semibold tabular-nums text-gray-900 dark:text-white">{{ number_format($summary['count'], 0, ',', '.') }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Nilai Stok</p>
                <p class="mt-2 text-xl font-semibold tabular-nums text-gray-900 dark:text-white">{{ $rupiah($summary['stock_value']) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Stok Perlu Perhatian</p>
                <p class="mt-2 text-xl font-semibold tabular-nums text-gray-900 dark:text-white">{{ number_format($summary['low_stock'], 0, ',', '.') }}</p>
            </div>
        </section>

        @if (session('success'))
            <div class="rounded-lg border border-success-200 bg-success-50 px-4 py-3 text-sm text-success-700 dark:border-success-500/20 dark:bg-success-500/10 dark:text-success-300">
                {{ session('success') }}
            </div>
        @endif

        <section class="grid gap-4 xl:grid-cols-[360px_minmax(0,1fr)]">
            <div class="space-y-4">
                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                    <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Tambah Bahan Baku</h2>

                    @if ($errors->any())
                        <div class="mt-3 rounded-lg border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/20 dark:bg-error-500/10 dark:text-error-300">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('raw-materials.store') }}" class="mt-4 space-y-3">
                        @csrf

                        <div>
                            <label for="name" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Nama Bahan<span class="text-error-500">*</span></label>
                            <input id="name" name="name" value="{{ old('name') }}" type="text" placeholder="Contoh: Gula aren"
                                class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                            <div>
                                <label for="code" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Kode</label>
                                <input id="code" name="code" value="{{ old('code') }}" type="text" placeholder="Auto jika kosong"
                                    class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                            </div>
                            <div>
                                <label for="category" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Kategori</label>
                                <input id="category" name="category" value="{{ old('category') }}" type="text" placeholder="Bahan minuman"
                                    class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                            </div>
                        </div>

                        <div>
                            <label for="unit" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Satuan<span class="text-error-500">*</span></label>
                            <select id="unit" name="unit"
                                class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                @foreach ($units as $unit)
                                    <option value="{{ $unit }}" @selected(old('unit') === $unit)>{{ $unit }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                            <div>
                                <label for="stock" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Stok Awal</label>
                                <input id="stock" name="stock" value="{{ old('stock') }}" type="number" min="0" step="0.001" placeholder="0"
                                    class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-right text-sm tabular-nums text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                            </div>
                            <div>
                                <label for="min_stock" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Stok Minimum</label>
                                <input id="min_stock" name="min_stock" value="{{ old('min_stock') }}" type="number" min="0" step="0.001" placeholder="0"
                                    class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-right text-sm tabular-nums text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                            </div>
                        </div>

                        <div>
                            <label for="cost_per_unit" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Harga Per Satuan</label>
                            <input id="cost_per_unit" name="cost_per_unit" value="{{ old('cost_per_unit') }}" type="number" min="0" placeholder="0"
                                class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-right text-sm tabular-nums text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                        </div>

                        <div>
                            <label for="note" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Catatan</label>
                            <textarea id="note" name="note" rows="3" placeholder="Catatan bahan baku"
                                class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">{{ old('note') }}</textarea>
                        </div>

                        <button type="submit" class="inline-flex h-10 w-full items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-semibold text-white shadow-theme-xs transition hover:bg-brand-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30">
                            Simpan Bahan Baku
                        </button>
                    </form>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                    <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Tambah Stok Bahan Baku</h2>
                    <form method="POST" class="mt-4 space-y-3" x-data="{ action: '' }" :action="action">
                        @csrf

                        <div>
                            <label for="stock_material_id" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Bahan<span class="text-error-500">*</span></label>
                            <select id="stock_material_id" required
                                x-on:change="action = $event.target.selectedOptions[0]?.dataset.action || ''"
                                class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                <option value="">Pilih bahan</option>
                                @foreach ($materials as $material)
                                    <option value="{{ $material->id }}" data-action="{{ route('raw-materials.stock-in', $material) }}">{{ $material->name }} ({{ $decimal($material->stock) }} {{ $material->unit }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="stock_quantity" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Jumlah Masuk<span class="text-error-500">*</span></label>
                            <input id="stock_quantity" name="quantity" type="number" min="0.001" step="0.001" placeholder="0"
                                class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-right text-sm tabular-nums text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                        </div>

                        <div>
                            <label for="stock_cost_per_unit" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Harga Per Satuan Baru</label>
                            <input id="stock_cost_per_unit" name="cost_per_unit" type="number" min="0" placeholder="Opsional"
                                class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-right text-sm tabular-nums text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                        </div>

                        <div>
                            <label for="stock_note" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Catatan</label>
                            <textarea id="stock_note" name="note" rows="2" placeholder="Catatan stok masuk"
                                class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"></textarea>
                        </div>

                        <button type="submit" :disabled="!action" class="inline-flex h-10 w-full items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-semibold text-white shadow-theme-xs transition hover:bg-brand-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30 disabled:cursor-not-allowed disabled:bg-gray-300 disabled:text-gray-500 dark:disabled:bg-gray-700 dark:disabled:text-gray-400">
                            Tambah Stok
                        </button>
                    </form>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                    <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Daftar Bahan Baku</h2>
                </div>

                <div class="max-w-full overflow-x-auto custom-scrollbar">
                    <table class="w-full min-w-[820px]">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                                <th class="px-4 py-2.5 text-left"><p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Bahan</p></th>
                                <th class="px-4 py-2.5 text-center"><p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Kode</p></th>
                                <th class="px-4 py-2.5 text-right"><p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Stok</p></th>
                                <th class="px-4 py-2.5 text-right"><p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Minimum</p></th>
                                <th class="px-4 py-2.5 text-right"><p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Harga/Satuan</p></th>
                                <th class="px-4 py-2.5 text-center"><p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</p></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($materials as $material)
                                <tr class="border-b border-gray-100 transition-colors last:border-0 hover:bg-gray-25 dark:border-gray-800 dark:hover:bg-white/[0.02]">
                                    <td class="px-4 py-2">
                                        <p class="max-w-[240px] truncate text-[13px] font-semibold text-gray-800 dark:text-white/90">{{ $material->name }}</p>
                                        <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">{{ $material->category ?: 'Tanpa kategori' }}</p>
                                    </td>
                                    <td class="px-4 py-2 text-center text-[12px] font-medium text-gray-500 dark:text-gray-400">{{ $material->code }}</td>
                                    <td class="px-4 py-2 text-right text-[12px] font-semibold tabular-nums text-gray-800 dark:text-white/90">{{ $decimal($material->stock) }} {{ $material->unit }}</td>
                                    <td class="px-4 py-2 text-right text-[12px] tabular-nums text-gray-500 dark:text-gray-400">{{ $decimal($material->min_stock) }} {{ $material->unit }}</td>
                                    <td class="px-4 py-2 text-right text-[12px] font-semibold tabular-nums text-gray-800 dark:text-white/90">{{ $rupiah($material->cost_per_unit) }}</td>
                                    <td class="px-4 py-2 text-center">
                                        @if ((float) $material->stock <= 0)
                                            <span class="rounded-full bg-error-50 px-2 py-0.5 text-[11px] font-semibold text-error-600 dark:bg-error-500/15 dark:text-error-400">Habis</span>
                                        @elseif ((float) $material->min_stock > 0 && (float) $material->stock <= (float) $material->min_stock)
                                            <span class="rounded-full bg-warning-50 px-2 py-0.5 text-[11px] font-semibold text-warning-700 dark:bg-warning-500/15 dark:text-orange-400">Menipis</span>
                                        @else
                                            <span class="rounded-full bg-success-50 px-2 py-0.5 text-[11px] font-semibold text-success-600 dark:bg-success-500/15 dark:text-success-400">Aman</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                        Belum ada bahan baku.
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
