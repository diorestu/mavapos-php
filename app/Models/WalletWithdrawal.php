<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletWithdrawal extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['account_number', 'request_hash'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'account_number' => 'encrypted', 'processed_at' => 'datetime'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(WalletAccount::class, 'wallet_account_id');
    }

    public function maskedAccount(): string
    {
        return '•••• '.substr($this->account_number, -4);
    }
}
