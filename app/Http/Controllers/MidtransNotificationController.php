<?php

namespace App\Http\Controllers;

use App\Models\Billing;
use App\Models\GatewayPayment;
use App\Services\MidtransBillingService;
use App\Services\MidtransClient;
use App\Services\PosGatewayService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MidtransNotificationController extends Controller
{
    public function __invoke(Request $request, MidtransClient $client, MidtransBillingService $service, PosGatewayService $posGateway): JsonResponse
    {
        abort_unless($client->isConfigured(), 503, 'Midtrans is not configured.');
        $data = $request->all();
        abort_unless($client->validSignature($data), 401, 'Invalid Midtrans signature.');
        $payment = GatewayPayment::where('order_id', $data['order_id'])->first();
        if ($payment) {
            $posGateway->validateAmount($payment, $data);
            try {
                $payment = $posGateway->sync($payment);
            } catch (ConnectionException|RequestException|\RuntimeException) {
                return response()->json(['message' => 'Gateway verification unavailable.'], 503);
            }

            return response()->json(['status' => 'ok', 'order_id' => $payment->order_id, 'payment_status' => $payment->status]);
        }
        $billing = Billing::withoutGlobalScopes()->where('payment_provider', 'midtrans')->where('invoice_number', $data['order_id'])->firstOrFail();
        $service->validateAmount($billing, $data);
        try {
            $billing = $service->sync($billing);
        } catch (ConnectionException|RequestException|\RuntimeException) {
            return response()->json(['message' => 'Midtrans verification unavailable; retry notification.'], 503);
        }

        return response()->json(['status' => 'ok', 'invoice_number' => $billing->invoice_number, 'payment_status' => $billing->payment_status, 'paid_at' => $billing->paid_at?->toISOString()]);
    }
}
