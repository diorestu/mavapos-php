@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-3xl space-y-4" x-data="saleEditor(@js($sale), @js($items))">
        <div>
            <a href="{{ route('sales') }}" class="text-xs font-semibold text-brand-600">← Kembali ke penjualan</a>
            <h1 class="mt-2 text-xl font-semibold text-gray-800 dark:text-white">Edit transaksi {{ $sale->invoice_number }}</h1>
            <p class="mt-1 text-xs text-gray-500">Hanya admin/owner. Stok, bahan baku, dan rekap shift akan dihitung ulang otomatis.</p>
        </div>
        <form @submit.prevent="submit" class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[.03]">
            <template x-for="(line, index) in lines" :key="index">
                <div class="grid gap-2 border-b border-gray-100 pb-3 md:grid-cols-[1fr_100px_auto] dark:border-gray-800">
                    <select x-model="line.id" class="h-10 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:bg-gray-900">
                        <template x-for="item in items" :key="item.id"><option :value="item.id" x-text="item.name + ' — Rp' + Number(item.price).toLocaleString('id-ID')"></option></template>
                    </select>
                    <input x-model.number="line.quantity" min="1" type="number" class="h-10 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:bg-gray-900" />
                    <button type="button" @click="lines.splice(index, 1)" :disabled="lines.length === 1" class="h-10 rounded-lg border border-error-200 px-3 text-xs font-semibold text-error-600 disabled:opacity-40">Hapus</button>
                </div>
            </template>
            <button type="button" @click="lines.push({id: items[0]?.id, quantity: 1})" class="text-xs font-semibold text-brand-600">+ Tambah item</button>
            <div class="grid gap-3 md:grid-cols-3">
                <label class="text-xs">Metode bayar<select x-model="payment_method" class="mt-1 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:bg-gray-900"><option value="cash">Tunai</option><option value="qris">QRIS</option><option value="card">Kartu</option><option value="free">Gratis</option></select></label>
                <label class="text-xs">Diskon<input x-model.number="discount" min="0" type="number" class="mt-1 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:bg-gray-900" /></label>
                <label class="text-xs">Uang diterima<input x-model.number="paid_amount" min="0" type="number" class="mt-1 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:bg-gray-900" /></label>
            </div>
            <label class="block text-xs">Kewarganegaraan<select x-model="buyer_nationality" class="mt-1 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:bg-gray-900"><option value="">Belum dipilih</option><option value="local">Local</option><option value="foreigner">Foreigner</option></select></label>
            <label class="block text-xs font-medium">Alasan koreksi<textarea x-model="reason" required maxlength="500" class="mt-1 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900" placeholder="Contoh: salah input jumlah cup"></textarea></label>
            <p x-show="error" class="text-xs text-error-600" x-text="error"></p>
            <button :disabled="loading" class="h-10 rounded-lg bg-brand-500 px-5 text-sm font-semibold text-white disabled:opacity-60" x-text="loading ? 'Menyimpan...' : 'Simpan koreksi'"></button>
        </form>
    </div>
    <script>
        function saleEditor(sale, items) { return { items, lines: sale.items.map(i => ({ id: i.product_variant_id ? 'variant-' + i.product_variant_id : 'product-' + i.sku, quantity: i.quantity })), payment_method: sale.payment_method, discount: sale.discount, paid_amount: sale.paid_amount, buyer_nationality: sale.buyer_nationality || '', reason: '', loading: false, error: '', async submit() { this.loading = true; this.error = ''; const response = await fetch(@js(route('sales.update', $sale)), { method: 'PUT', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify({ items: this.lines, payment_method: this.payment_method, discount: this.discount || 0, paid_amount: this.paid_amount || 0, buyer_nationality: this.buyer_nationality || null, reason: this.reason }) }); if (response.ok) { window.location = @js(route('sales')); return; } const data = await response.json(); this.error = data.message || 'Koreksi gagal disimpan.'; this.loading = false; } } }
    </script>
@endsection
