@extends('layouts.app')

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
                        <li class="font-medium text-gray-700 dark:text-gray-300">Transfer Stok</li>
                    </ol>
                </nav>

                <h1 class="mt-1 text-xl font-semibold text-gray-800 dark:text-white/90">Transfer Stok</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Pindahkan stok produk atau bahan baku antar cabang dan catat mutasi otomatis.</p>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-lg border border-success-200 bg-success-50 px-4 py-3 text-sm text-success-700 dark:border-success-500/20 dark:bg-success-500/10 dark:text-success-300">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-lg border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/20 dark:bg-error-500/10 dark:text-error-300">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="grid gap-4 xl:grid-cols-[380px_minmax(0,1fr)]">
            <form method="POST" action="{{ route('stock-transfers.store') }}" class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                @csrf

                <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Buat Transfer</h2>

                <div class="mt-4 space-y-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Cabang Asal</label>
                        <select name="from_branch_id" class="h-10 w-full rounded-lg border border-gray-200 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-800 dark:bg-gray-900 dark:text-white/90">
                            <option value="">Pilih cabang asal</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected(old('from_branch_id') == $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Cabang Tujuan</label>
                        <select name="to_branch_id" class="h-10 w-full rounded-lg border border-gray-200 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-800 dark:bg-gray-900 dark:text-white/90">
                            <option value="">Pilih cabang tujuan</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected(old('to_branch_id') == $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Produk / Bahan Baku</label>
                        <select name="stock_item" class="h-10 w-full rounded-lg border border-gray-200 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-800 dark:bg-gray-900 dark:text-white/90">
                            <option value="">Pilih produk atau bahan baku</option>
                            <optgroup label="Produk">
                                @foreach ($products as $product)
                                    <option value="product-{{ $product->id }}" @selected(old('stock_item', old('product_id') ? 'product-'.old('product_id') : '') === 'product-'.$product->id)>
                                        {{ $product->name }} · {{ $product->sku }}
                                    </option>
                                @endforeach
                            </optgroup>
                            <optgroup label="Bahan baku">
                                @foreach ($rawMaterials as $material)
                                    <option value="raw-material-{{ $material->id }}" @selected(old('stock_item') === 'raw-material-'.$material->id)>
                                        {{ $material->name }} · {{ $material->code }} · Bahan baku
                                    </option>
                                @endforeach
                            </optgroup>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Jumlah</label>
                        <input name="quantity" type="number" min="1" value="{{ old('quantity') }}" class="h-10 w-full rounded-lg border border-gray-200 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-800 dark:text-white/90">
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Catatan</label>
                        <textarea name="note" rows="3" class="w-full rounded-lg border border-gray-200 bg-transparent px-3 py-2 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-800 dark:text-white/90">{{ old('note') }}</textarea>
                    </div>
                </div>

                <button type="submit" class="mt-4 inline-flex h-10 w-full items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-semibold text-white transition hover:bg-brand-600">
                    Simpan Transfer
                </button>
            </form>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Riwayat Transfer</h2>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $transfers->count() }} transfer terbaru</p>
                    </div>
                </div>

                <div class="max-w-full overflow-x-auto custom-scrollbar">
                    <table class="w-full min-w-[900px]">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                                <th class="px-4 py-2.5 text-left"><p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Waktu</p></th>
                                <th class="px-4 py-2.5 text-left"><p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Nomor</p></th>
                                <th class="px-4 py-2.5 text-left"><p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Item</p></th>
                                <th class="px-4 py-2.5 text-left"><p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Cabang</p></th>
                                <th class="px-4 py-2.5 text-right"><p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Qty</p></th>
                                <th class="px-4 py-2.5 text-left"><p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">PIC</p></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($transfers as $transfer)
                                <tr class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                                    <td class="px-4 py-2 text-xs text-gray-500 dark:text-gray-400">@localtime($transfer->transferred_at)</td>
                                    <td class="px-4 py-2 text-xs font-semibold text-gray-800 dark:text-white/90">{{ $transfer->transfer_number }}</td>
                                    <td class="px-4 py-2">
                                        <p class="text-xs font-semibold text-gray-800 dark:text-white/90">{{ $transfer->product?->name ?? $transfer->rawMaterial?->name }}</p>
                                        <p class="text-[11px] text-gray-500 dark:text-gray-400">
                                            {{ $transfer->product?->sku ?? $transfer->rawMaterial?->code }}
                                            @if ($transfer->raw_material_id)
                                                · Bahan baku
                                            @endif
                                        </p>
                                    </td>
                                    <td class="px-4 py-2 text-xs text-gray-500 dark:text-gray-400">
                                        <span class="font-medium text-gray-700 dark:text-gray-300">{{ $transfer->fromBranch?->name }}</span>
                                        <span class="mx-1">ke</span>
                                        <span class="font-medium text-gray-700 dark:text-gray-300">{{ $transfer->toBranch?->name }}</span>
                                    </td>
                                    <td class="px-4 py-2 text-right text-xs font-semibold tabular-nums text-gray-800 dark:text-white/90">{{ number_format($transfer->quantity, 0, ',', '.') }}</td>
                                    <td class="px-4 py-2 text-xs text-gray-500 dark:text-gray-400">{{ $transfer->user?->name ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                        Belum ada transfer stok.
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
