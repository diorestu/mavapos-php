@extends('layouts.app')
@php
    $money=fn($n)=>'Rp'.number_format((int)$n,0,',','.');
    $verifyUrl=route('pos.gateway.show',['payment'=>$payment,'verify'=>1]);
    $canPay=$payment->paid_at===null && !$payment->sale->voided_at && $payment->creation_status==='ready' && $payment->expires_at->isFuture();
    $checks=in_array($payment->status,['pending','capture'],true)?[['url'=>route('pos.gateway.refresh',$payment),'status'=>$payment->status]]:[];
    $initial=$autoOpen && $canPay && $midtransSnap['ready'] ? ['token'=>$payment->snap_token,'verifyUrl'=>$verifyUrl] : null;
@endphp
@section('content')
<div class="mx-auto max-w-3xl space-y-4" x-data="billingPaymentWatcher({payments:@js($checks), initialCheckout:@js($initial)})" @focus.window="check()" @visibilitychange.document="check()">
    <div><h1 class="text-xl font-semibold text-gray-900 dark:text-white">Pembayaran Transaksi</h1><p class="mt-1 text-sm text-gray-500">{{$payment->sale->invoice_number}} · {{$payment->branch?->name}}</p></div>
    @if($payment->environment==='sandbox')<p class="rounded-lg bg-warning-50 p-3 text-sm text-warning-800">Sandbox — pembayaran dan saldo simulasi.</p>@endif
    @if(session('error'))<p class="rounded-lg bg-error-50 p-3 text-sm text-error-700">{{session('error')}}</p>@endif
    @if(session('success'))<p class="rounded-lg bg-success-50 p-3 text-sm text-success-700">{{session('success')}}</p>@endif
    <p x-cloak x-show="paymentError" x-text="paymentError" role="alert" class="rounded-lg bg-error-50 p-3 text-sm text-error-700"></p>
    <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
        <p class="text-sm text-gray-500">Total pembayaran</p><p class="mt-1 text-3xl font-semibold tabular-nums text-gray-900 dark:text-white">{{$money($payment->amount)}}</p>
        <p class="mt-3 font-semibold {{ $payment->sale->payment_status==='paid'?'text-success-700':'text-warning-700' }}">{{in_array($payment->status,['reversed','refund','chargeback'],true)?'Pembayaran dikembalikan / dibalik':($payment->sale->voided_at?'Transaksi dibatalkan':($payment->sale->payment_status==='paid'?'Lunas':'Menunggu pembayaran'))}}</p>
        <p class="mt-1 text-xs text-gray-500">Status Midtrans: {{$payment->status}} · Kedaluwarsa sesi: @localtime($payment->expires_at)</p>
        @if($payment->review_required)<p class="mt-3 text-sm text-error-600">Pembayaran memerlukan peninjauan admin. Dana tetap tercatat, tetapi belum tersedia untuk pencairan.</p>@endif
        @if($payment->creation_status==='failed')<p class="mt-3 text-sm text-gray-500">Sesi pembayaran belum dapat disiapkan. Gunakan Cek Status; reservasi stok akan dilepas setelah pembayaran dipastikan kedaluwarsa.</p>@endif
        @if($payment->sale->payment_status==='pending')<p class="mt-3 text-xs text-gray-500">Stok ditahan sementara. Transaksi ini belum dihitung sebagai omzet sampai pembayaran terverifikasi.</p>@endif
        <div class="mt-5 flex flex-wrap items-center gap-3">
            @if($canPay)
                @if($midtransSnap['ready'])<button type="button" @click="pay(@js($payment->snap_token),@js($verifyUrl))" :disabled="popupOpen||finishing" class="min-h-11 rounded-lg bg-brand-500 px-4 text-sm font-semibold text-white disabled:opacity-50">Bayar via Midtrans</button>@endif
                <a href="{{$payment->payment_url}}" class="inline-flex min-h-11 items-center text-sm font-medium text-brand-600 underline">Buka Halaman Pembayaran</a>
            @endif
            <form method="POST" action="{{route('pos.gateway.refresh',$payment)}}">@csrf<button class="min-h-11 rounded-lg border border-gray-300 px-4 text-sm font-semibold text-gray-700 dark:text-gray-300">Cek Status</button></form>
            <a href="{{route('pos')}}" class="inline-flex min-h-11 items-center text-sm text-brand-600">Kembali ke Kasir</a>
        </div>
    </section>
    <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900"><h2 class="p-4 font-semibold dark:text-white">Rincian Barang</h2><div class="overflow-x-auto"><table class="w-full min-w-[430px] text-left text-sm"><thead class="bg-gray-50 text-xs text-gray-500 dark:bg-gray-800"><tr><th class="p-3">Produk</th><th class="p-3">Qty</th><th class="p-3">Total</th></tr></thead><tbody>@foreach($payment->sale->items as $item)<tr class="border-t border-gray-100 dark:border-gray-800"><td class="p-3">{{$item->name}}</td><td class="p-3">{{$item->quantity}}</td><td class="p-3 tabular-nums">{{$money($item->line_total)}}</td></tr>@endforeach</tbody></table></div></section>
</div>
@endsection
@if($midtransSnap['ready'])
@push('scripts')<script src="{{$midtransSnap['scriptUrl']}}" data-client-key="{{$midtransSnap['clientKey']}}" defer></script>@endpush
@endif
