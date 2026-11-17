<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletAccount extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['owner_id' => 'integer'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id')->withTrashed();
    }
}
