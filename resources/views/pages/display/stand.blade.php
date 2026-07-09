@extends('layouts.fullscreen-layout')

@section('content')
    <div x-data="customerDisplay()" x-init="start()" class="min-h-screen bg-gray-950 text-white">
        <div class="mx-auto flex min-h-screen w-full max-w-3xl flex-col p-6">
            <header class="flex items-center justify-between border-b border-white/10 pb-3">
                <h1 class="truncate text-base font-semibold tracking-wide" x-text="storeName"></h1>
                <span class="rounded-full bg-emerald-500/15 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-emerald-300" x-text="modeLabel"></span>
            </header>

            <section class="flex-1 overflow-hidden py-4">
                <template x-if="mode === 'cart'">
                    <div class="flex h-full flex-col">
                        <p class="text-xs font-medium uppercase tracking-wider text-white/50">Item</p>
                        <ul class="mt-2 flex-1 space-y-1.5 overflow-y-auto pr-1 text-sm">
                            <template x-for="(item, index) in cart" :key="`${item.name}-${index}`">
                                <li class="flex items-baseline justify-between gap-3 border-b border-white/5 pb-1.5">
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate font-medium" x-text="item.name"></p>
                                        <p class="text-xs text-white/50" x-text="`${item.quantity} × ${formatRupiah(item.unit_price || 0)}`"></p>
                                    </div>
                                    <p class="shrink-0 font-semibold tabular-nums" x-text="formatRupiah(item.line_total)"></p>
                                </li>
                            </template>
                            <template x-if="cart.length === 0">
                                <li class="grid h-32 place-items-center text-sm text-white/40">Belum ada item</li>
                            </template>
                        </ul>
                    </div>
                </template>

                <template x-if="mode === 'checkout'">
                    <div class="flex h-full flex-col items-center justify-center text-center">
                        <p class="text-xs font-medium uppercase tracking-wider text-emerald-300">Pembayaran Selesai</p>
                        <p class="mt-2 text-2xl font-bold tabular-nums" x-text="formatRupiah(total)"></p>
                        <template x-if="paymentMethod === 'cash' && changeAmount > 0">
                            <p class="mt-1 text-sm text-white/70" x-text="`Kembali ${formatRupiah(changeAmount)}`"></p>
                        </template>
                        <p class="mt-3 text-[10px] uppercase tracking-wider text-white/40" x-text="invoiceNumber ? `No ${invoiceNumber}` : ''"></p>
                    </div>
                </template>
            </section>

            <footer class="space-y-1 border-t border-white/10 pt-3">
                <div class="flex items-baseline justify-between text-sm">
                    <span class="text-white/60">Subtotal</span>
                    <span class="font-semibold tabular-nums" x-text="formatRupiah(subtotal)"></span>
                </div>
                <template x-if="discount > 0">
                    <div class="flex items-baseline justify-between text-sm">
                        <span class="text-white/60">Diskon</span>
                        <span class="font-semibold tabular-nums text-rose-300" x-text="`- ${formatRupiah(discount)}`"></span>
                    </div>
                </template>
                <div class="flex items-baseline justify-between pt-1 text-lg font-bold">
                    <span>Total</span>
                    <span class="tabular-nums" x-text="formatRupiah(total)"></span>
                </div>
            </footer>
        </div>
    </div>

    <script>
        function customerDisplay() {
            return {
                storeName: @js($storeName),
                mode: 'cart',
                cart: [],
                subtotal: 0,
                discount: 0,
                total: 0,
                paymentMethod: null,
                changeAmount: 0,
                invoiceNumber: null,
                timer: null,

                get modeLabel() {
                    return this.mode === 'checkout' ? 'Selesai' : 'Keranjang';
                },

                start() {
                    this.poll();
                    this.timer = setInterval(() => this.poll(), 1000);
                },

                async poll() {
                    try {
                        const response = await fetch(@js(route('display.state')), {
                            headers: { Accept: 'application/json' },
                            credentials: 'same-origin',
                        });
                        if (!response.ok) {
                            return;
                        }
                        const data = await response.json();
                        this.mode = data.mode || 'cart';
                        this.cart = (data.cart || []).map((item) => ({
                            ...item,
                            unit_price: item.line_total / Math.max(1, item.quantity),
                        }));
                        this.subtotal = Number(data.subtotal || 0);
                        this.discount = Number(data.discount || 0);
                        this.total = Number(data.total || 0);
                        this.paymentMethod = data.payment_method || null;
                        this.changeAmount = Number(data.change_amount || 0);
                        this.invoiceNumber = data.invoice_number || null;
                    } catch (error) {
                        // keep last known state
                    }
                },

                formatRupiah(value) {
                    return new Intl.NumberFormat('id-ID', {
                        style: 'currency',
                        currency: 'IDR',
                        maximumFractionDigits: 0,
                    }).format(Number(value || 0)).replace(/\s/g, '');
                },
            };
        }
    </script>
@endsection
