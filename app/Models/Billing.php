<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Billing extends Model
{
    protected $fillable = [
        'invoice_number',
        'customer_id',
        'customer_name',
        'customer_phone',
        'title',
        'description',
        'amount',
        'fee',
        'total_payment',
        'payment_provider',
        'payment_method',
        'payment_status',
        'payment_url',
        'payment_number',
        'expires_at',
        'paid_at',
        'provider_payload',
    ];

    protected $casts = [
        'amount' => 'integer',
        'fee' => 'integer',
        'total_payment' => 'integer',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'provider_payload' => 'array',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function markPaid(?string $completedAt = null, array $payload = []): void
    {
        $this->forceFill([
            'payment_status' => 'completed',
            'paid_at' => $completedAt ? now()->parse($completedAt) : now(),
            'provider_payload' => array_filter([
                ...($this->provider_payload ?? []),
                'webhook' => $payload,
            ]),
        ])->save();
    }
}
