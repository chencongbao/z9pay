<?php

namespace App\Models;

use App\Traits\ModelTraits;
use Illuminate\Database\Eloquent\Model;

class ReportDay extends Model
{
    use ModelTraits;

    protected $guarded = [];

    protected $appends = [
        'deposit_order_success_rate',
        'transfer_order_success_rate',
        'settlement_order_success_rate',
    ];

    public function getDepositOrderSuccessRateAttribute()
    {
        return bob_percent($this->deposit_order_number_success, $this->deposit_order_number_total);
    }

    public function getTransferOrderSuccessRateAttribute()
    {
        return bob_percent($this->transfer_order_number_success, $this->transfer_order_number_total);
    }

    public function getSettlementOrderSuccessRateAttribute()
    {
        return bob_percent($this->settlement_order_number_success, $this->settlement_order_number_total);
    }
}
