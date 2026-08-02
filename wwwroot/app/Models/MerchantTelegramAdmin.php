<?php

namespace App\Models;

use App\Traits\ModelTraits;
use App\Traits\ActivityLogTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantTelegramAdmin extends Model
{
    use ModelTraits, ActivityLogTrait;

    protected $guarded = [];

    protected $casts = [
        'mid' => 'integer',
        'telegram_group_id' => 'integer',
        'telegram_user_id' => 'integer',
        'reviewed_by' => 'integer',
        'reviewed_telegram_user_id' => 'integer',
    ];

    public function merchant_info(): BelongsTo
    {
        return $this->belongsTo(MerchantInfo::class, 'mid', 'merchant_user_id');
    }
}
