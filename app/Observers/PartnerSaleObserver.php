<?php

namespace App\Observers;

use App\Models\Branch;
use App\Models\PartnerWebhook;
use App\Models\PartnerWebhookDelivery;
use App\Models\PosSale;
use App\Services\Partner\ApiResource;
use App\Services\Partner\WebhookMessage;

class PartnerSaleObserver
{
    public function updated(PosSale $sale): void
    {
        if ($sale->wasChanged('payment_status') && $sale->payment_status === 'paid' && ! $sale->voided_at) {
            $this->created($sale);
        }
    }

    public function created(PosSale $sale): void
    {
        if ($sale->payment_status === 'pending') {
            return;
        }
        $ownerId = Branch::withoutGlobalScopes()->whereKey($sale->branch_id)->value('user_id');
        if (! $ownerId) {
            return;
        }
        $hooks = PartnerWebhook::whereHas('key', fn ($q) => $q->where('owner_id', $ownerId)->where('sandbox', false)->whereNull('revoked_at')->where('expires_at', '>', now())->whereHas('owner', fn ($q) => $q->where('role', 'owner')))->cursor();
        foreach ($hooks as $hook) {
            $payload = WebhookMessage::payload($sale->id, $sale->branch_id, $sale->invoice_number, ApiResource::time($sale->sold_at));
            PartnerWebhookDelivery::create(['partner_webhook_id' => $hook->id, 'event_id' => $payload['id'], 'payload' => $payload, 'available_at' => now()]);
        }
    }
}
