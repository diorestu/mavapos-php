<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerWebhookDelivery extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'attempts' => 'integer', 'available_at' => 'datetime', 'delivered_at' => 'datetime', 'failed_at' => 'datetime'];
    }

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(PartnerWebhook::class, 'partner_webhook_id');
    }
}
