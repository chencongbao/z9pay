<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantCallbackLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'type' => 'integer',
        'request_data' => 'array',
        'is_success' => 'boolean',
    ];
}
