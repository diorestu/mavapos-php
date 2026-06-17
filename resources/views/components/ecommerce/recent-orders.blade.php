@php
    $productsList = [
        ['name' => 'Kopi Susu Aren 250ml', 'category' => 'Minuman', 'sold' => 128, 'revenue' => 'Rp2.304.000'],
        ['name' => 'Roti Cokelat Premium', 'category' => 'Makanan', 'sold' => 96, 'revenue' => 'Rp1.200.000'],
        ['name' => 'Tisu Wajah 250 Sheet', 'category' => 'Rumah Tangga', 'sold' => 74, 'revenue' => 'Rp1.184.000'],
        ['name' => 'Sabun Cair Lavender', 'category' => 'Perawatan', 'sold' => 63, 'revenue' => 'Rp1.512.000'],
        ['name' => 'Beras Premium 5kg', 'category' => 'Sembako', 'sold' => 41, 'revenue' => 'Rp3.034.000'],
    ];
@endphp

<section class="overflow-hidden rounded-xl border border-gray-200 bg-white px-4 pb-3 pt-4 dark:border-gray-800 dark:bg-white/[0.03] sm:px-5">
    <div class="mb-3">
        <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Top 5 Barang Dibeli</h3>
        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Produk dengan jumlah pembelian tertinggi bulan ini.</p>
    </div>

    <div class="max-w-full overflow-x-auto custom-scrollbar">
        <table class="w-full min-w-[680px]">
            <thead>
                <tr class="border-y border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                    <th class="px-3 py-2 text-left">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Barang</p>
                    </th>
                    <th class="px-3 py-2 text-left">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Kategori</p>
                    </th>
                    <th class="px-3 py-2 text-right">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Terjual</p>
                    </th>
                    <th class="px-3 py-2 text-right">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Pendapatan</p>
                    </th>
                </tr>
            </thead>
            <tbody>
                @foreach($productsList as $index => $product)
                    <tr class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                        <td class="px-3 py-2">
                            <div class="flex items-center gap-2">
                                <span class="flex h-6 w-6 items-center justify-center rounded-md bg-gray-100 text-xs font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                    {{ $index + 1 }}
                                </span>
                                <p class="truncate text-[13px] font-semibold text-gray-800 dark:text-white/90">{{ $product['name'] }}</p>
                            </div>
                        </td>
                        <td class="px-3 py-2 text-[12px] text-gray-500 dark:text-gray-400">{{ $product['category'] }}</td>
                        <td class="px-3 py-2 text-right text-[12px] font-semibold tabular-nums text-gray-800 dark:text-white/90">{{ $product['sold'] }}</td>
                        <td class="px-3 py-2 text-right text-[12px] font-semibold tabular-nums text-gray-800 dark:text-white/90">{{ $product['revenue'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
