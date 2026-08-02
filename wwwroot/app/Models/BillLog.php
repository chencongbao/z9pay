<?php

namespace App\Models;

use App\Traits\ModelTraits;
use Illuminate\Database\Eloquent\Model;

class BillLog extends Model
{
    use ModelTraits;

    protected $guarded = [];

    protected $casts = [
        'original_amount' => 'decimal:6',
        'exchange_rate' => 'decimal:6',
        'fee_rate' => 'decimal:6',
        'payable_amount' => 'decimal:6',
        'calculation_version' => 'integer',
    ];
}
