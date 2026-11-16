<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerWebhook extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['signing_secret'];

    protected function casts(): array
    {
        return ['signing_secret' => 'encrypted'];
    }

    public function key(): BelongsTo
    {
        return $this->belongsTo(PartnerApiKey::class, 'partner_api_key_id');
    }
}
