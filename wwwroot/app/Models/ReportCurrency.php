<?php

namespace App\Models;

use App\Traits\ModelTraits;
use Illuminate\Database\Eloquent\Model;

class ReportCurrency extends Model
{
    use ModelTraits;

    protected $guarded = [];

    protected $appends = [
        'deposit_order_success_rate',
        'transfer_order_success_rate',
        'settlement_order_success_rate',
    ];

    public function getDepositOrderSuccessRateAttribute(): string
    {
        return bob_percent($this->deposit_order_number_success, $this->deposit_order_number_total);
    }

    public function getTransferOrderSuccessRateAttribute(): string
    {
        return bob_percent($this->transfer_order_number_success, $this->transfer_order_number_total);
    }

    public function getSettlementOrderSuccessRateAttribute(): string
    {
        return bob_percent($this->settlement_order_number_success, $this->settlement_order_number_total);
    }
}
