@extends('layouts.app')

@section('content')
    <div class="space-y-4">
        <div>
            <nav aria-label="Breadcrumb">
                <ol class="flex items-center gap-1 text-[10px] text-gray-500 dark:text-gray-400">
                    <li><a href="{{ url('/') }}" class="transition hover:text-gray-700 dark:hover:text-gray-200">Home</a></li>
                    <li aria-hidden="true">
                        <svg class="h-3 w-3 stroke-current" viewBox="0 0 17 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M6.0765 12.667L10.2432 8.50033L6.0765 4.33366" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </li>
                    <li class="font-medium text-gray-700 dark:text-gray-300">Purchase Order</li>
                </ol>
            </nav>
            <h1 class="mt-1 text-xl font-semibold text-gray-800 dark:text-white/90">Purchase Order</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Buat pesanan restock ke supplier lalu terima barang saat stok sudah masuk.</p>
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

        <div class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-xs text-gray-500 dark:text-gray-400">Draft PO</p>
                <p class="mt-1 text-2xl font-semibold text-gray-800 dark:text-white/90">{{ number_format($summary['draft'], 0, ',', '.') }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-xs text-gray-500 dark:text-gray-400">Sudah Diterima</p>
                <p class="mt-1 text-2xl font-semibold text-gray-800 dark:text-white/90">{{ number_format($summary['received'], 0, ',', '.') }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-xs text-gray-500 dark:text-gray-400">Nilai PO</p>
                <p class="mt-1 text-2xl font-semibold text-gray-800 dark:text-white/90">Rp{{ number_format($summary['total'], 0, ',', '.') }}</p>
            </div>
        </div>

        <section class="grid gap-4 xl:grid-cols-[380px_minmax(0,1fr)]">
            <form method="POST" action="{{ route('purchase-orders.store') }}" class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                @csrf

                <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Buat PO Restock</h2>

                <div class="mt-4 space-y-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Supplier</label>
                        <select name="supplier_id" class="h-10 w-full rounded-lg border border-gray-200 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-800 dark:bg-gray-900 dark:text-white/90">
                            <option value="">Pilih supplier</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected(old('supplier_id') == $supplier->id)>{{ $supplier->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Produk</label>
                        <select name="product_id" class="h-10 w-full rounded-lg border border-gray-200 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-800 dark:bg-gray-900 dark:text-white/90">
                            <option value="">Pilih produk</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}" @selected(old('product_id') == $product->id)>{{ $product->name }} · {{ $product->sku }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Qty</label>
                            <input name="quantity" type="number" min="1" value="{{ old('quantity') }}" class="h-10 w-full rounded-lg border border-gray-200 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-800 dark:text-white/90">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Harga Beli</label>
                            <input name="unit_cost" type="number" min="0" value="{{ old('unit_cost') }}" class="h-10 w-full rounded-lg border border-gray-200 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-800 dark:text-white/90">
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Tanggal PO</label>
                        <input name="ordered_at" type="datetime-local" value="{{ old('ordered_at', now()->format('Y-m-d\TH:i')) }}" class="h-10 w-full rounded-lg border border-gray-200 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-800 dark:text-white/90">
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Referensi</label>
                        <input name="reference" value="{{ old('reference') }}" placeholder="Nomor invoice supplier" class="h-10 w-full rounded-lg border border-gray-200 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-800 dark:text-white/90">
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Catatan</label>
                        <textarea name="note" rows="3" class="w-full rounded-lg border border-gray-200 bg-transparent px-3 py-2 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-800 dark:text-white/90">{{ old('note') }}</textarea>
                    </div>
                </div>

                <button type="submit" class="mt-4 inline-flex h-10 w-full items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-semibold text-white transition hover:bg-brand-600">
                    Simpan PO
                </button>
            </form>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                    <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Daftar Purchase Order</h2>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $orders->count() }} PO pada cabang aktif</p>
                </div>

                <div class="max-w-full overflow-x-auto custom-scrollbar">
                    <table class="w-full min-w-[1040px]">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                                <th class="px-4 py-2.5 text-left"><p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">PO</p></th>
                                <th class="px-4 py-2.5 text-left"><p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Supplier</p></th>
                                <th class="px-4 py-2.5 text-left"><p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Produk</p></th>
                                <th class="px-4 py-2.5 text-right"><p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Qty</p></th>
                                <th class="px-4 py-2.5 text-right"><p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Total</p></th>
                                <th class="px-4 py-2.5 text-center"><p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</p></th>
                                <th class="px-4 py-2.5 text-center"><p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Aksi</p></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($orders as $order)
                                <tr class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                                    <td class="px-4 py-2">
                                        <p class="text-xs font-semibold text-gray-800 dark:text-white/90">{{ $order->po_number }}</p>
                                        <p class="text-[11px] text-gray-500 dark:text-gray-400">{{ $order->ordered_at?->format('d M Y H:i') }}</p>
                                    </td>
                                    <td class="px-4 py-2 text-xs text-gray-500 dark:text-gray-400">{{ $order->supplier?->name }}</td>
                                    <td class="px-4 py-2">
                                        <p class="text-xs font-semibold text-gray-800 dark:text-white/90">{{ $order->product?->name }}</p>
                                        <p class="text-[11px] text-gray-500 dark:text-gray-400">{{ $order->product?->sku }}</p>
                                    </td>
                                    <td class="px-4 py-2 text-right text-xs font-semibold tabular-nums text-gray-800 dark:text-white/90">{{ number_format($order->quantity, 0, ',', '.') }}</td>
                                    <td class="px-4 py-2 text-right text-xs font-semibold tabular-nums text-gray-800 dark:text-white/90">Rp{{ number_format($order->total_amount, 0, ',', '.') }}</td>
                                    <td class="px-4 py-2 text-center">
                                        @php
                                            $statusClass = match ($order->status) {
                                                'received' => 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400',
                                                'cancelled' => 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400',
                                                default => 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400',
                                            };
                                            $statusLabel = match ($order->status) {
                                                'received' => 'Diterima',
                                                'cancelled' => 'Dibatalkan',
                                                default => 'Draft',
                                            };
                                        @endphp
                                        <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $statusClass }}">{{ $statusLabel }}</span>
                                    </td>
                                    <td class="px-4 py-2">
                                        @if ($order->status === 'draft')
                                            <div class="flex justify-center gap-2">
                                                <form method="POST" action="{{ route('purchase-orders.receive', $order) }}">
                                                    @csrf
                                                    <button class="inline-flex h-8 items-center rounded-lg bg-success-500 px-3 text-xs font-semibold text-white transition hover:bg-success-600" type="submit">Terima</button>
                                                </form>
                                                <form method="POST" action="{{ route('purchase-orders.cancel', $order) }}">
                                                    @csrf
                                                    <button class="inline-flex h-8 items-center rounded-lg bg-gray-100 px-3 text-xs font-semibold text-gray-700 transition hover:bg-gray-200 dark:bg-white/[0.06] dark:text-gray-300" type="submit">Batal</button>
                                                </form>
                                            </div>
                                        @else
                                            <p class="text-center text-xs text-gray-500 dark:text-gray-400">{{ $order->received_at?->format('d M Y H:i') ?? '-' }}</p>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada purchase order.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
@endsection
