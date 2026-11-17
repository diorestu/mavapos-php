<?php

namespace App\Http\Controllers;

use App\Models\GatewayPayment;
use App\Services\MidtransClient;
use App\Services\PosGatewayService;
use Illuminate\Http\Request;

class PosGatewayController extends Controller
{
    public function store(Request $request, PosGatewayService $service, PosController $pos)
    {
        [$p,$created] = $service->create($request, $pos);

        return response()->json(['id' => $p->id, 'payment_status' => $p->status, 'redirect_url' => route('pos.gateway.show', ['payment' => $p, 'open' => 1]), 'creation_status' => $p->creation_status], $created ? 201 : 200);
    }

    private function owned(Request $request, GatewayPayment $payment): GatewayPayment
    {
        abort_unless($payment->owner_id === $request->user()->tenantOwnerId(), 404);
        abort_unless($request->user()->hasRole(['owner', 'admin']) || in_array($request->user()->id, [$payment->actor_id, $payment->sale->user_id], true), 403);

        return $payment;
    }

    public function show(Request $request, GatewayPayment $payment, PosGatewayService $service, MidtransClient $client)
    {
        $p = $this->owned($request, $payment);
        if ($request->boolean('verify')) {
            try {
                $service->sync($p);
            } catch (\Throwable) {
                $request->session()->flash('error', 'Status belum dapat diperiksa. Gunakan Cek Status.');
            }

            return redirect()->route('pos.gateway.show', $p);
        }

        return view('pages.gateway.show', ['title' => 'Pembayaran Transaksi', 'payment' => $p->load('sale', 'branch'), 'midtransSnap' => $client->snapConfiguration(), 'autoOpen' => $request->boolean('open')]);
    }

    public function refresh(Request $request, GatewayPayment $payment, PosGatewayService $service)
    {
        $p = $this->owned($request, $payment);
        try {
            $p = $service->sync($p);
        } catch (\Throwable) {
            return $request->expectsJson() ? response()->json(['message' => 'Status belum dapat diperiksa.'], 503) : back()->with('error', 'Status belum dapat diperiksa.');
        }

        return $request->expectsJson() ? response()->json(['id' => $p->id, 'payment_status' => $p->status, 'sale_status' => $p->sale->payment_status]) : back()->with('success', 'Status gateway diperbarui.');
    }
}
