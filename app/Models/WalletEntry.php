<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletEntry extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    public function payment()
    {
        return $this->belongsTo(GatewayPayment::class, 'gateway_payment_id');
    }

    public function withdrawal()
    {
        return $this->belongsTo(WalletWithdrawal::class, 'wallet_withdrawal_id');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Wallet entries are immutable.'));
        static::deleting(fn () => throw new \LogicException('Wallet entries are immutable.'));
    }
}
