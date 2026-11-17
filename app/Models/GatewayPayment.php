<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GatewayPayment extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['snap_token', 'provider_payload', 'request_hash'];

    protected function casts(): array
    {
        return ['owner_id' => 'integer', 'branch_id' => 'integer', 'actor_id' => 'integer', 'review_required' => 'boolean', 'amount' => 'integer', 'refunded_amount' => 'integer', 'fee_amount' => 'integer', 'snap_token' => 'encrypted', 'provider_payload' => 'array', 'expires_at' => 'datetime', 'paid_at' => 'datetime', 'settled_at' => 'datetime', 'last_checked_at' => 'datetime', 'fee_reconciled_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (GatewayPayment $payment): void {
            if ($payment->isDirty(['owner_id', 'branch_id', 'actor_id', 'pos_sale_id', 'environment', 'order_id', 'amount', 'client_request_id', 'request_hash'])) {
                throw new \LogicException('Gateway attribution and original amount are immutable.');
            }
        });
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(PosSale::class, 'pos_sale_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id')->withTrashed();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withoutGlobalScope('tenant');
    }
}
