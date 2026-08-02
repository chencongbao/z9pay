<?php

namespace App\Http\Resources;

use Illuminate\Support\Facades\App;
use App\Services\UserBank\UserBankTodayStatsService;

class UserBankResource extends BaseResource
{
    public function toArray($request)
    {
        $todayStatsService = App::make(UserBankTodayStatsService::class);

        return $this->filterFields([
            'id' => $this->id,
            'name' => $this->name,
            'card_no' => $this->card_no,
            'payment_id' => $this->payment_id,
            'account_type' => $this->account_type,
            'bank_id' => $this->bank_id,
            'limint_min_amount' => floatval($this->limint_min_amount),
            'limint_max_amount' => floatval($this->limint_max_amount),
            'limint_day_amount' => floatval($this->limint_day_amount),
            'payment_qrcode' => $this->payment_qrcode,
            'payment_qrcode_format' => $this->payment_qrcode_format,
            'limit_day_order_number' => $this->limit_day_order_number,
            'payment_name' => optional(config('default.user_bank_type'))->offsetGet($this->account_type),
            'collection_status' => $this->collection_status,
            'collection_status_text' => optional([0 => '收单关闭', 1 => "收单开启"])->offsetGet($this->collection_status),
            'balance_amount' => $this->balance_amount,
            'total_amount' => $todayStatsService->amount($this),
            'total_number' => $todayStatsService->number($this),
            'total_income' => $todayStatsService->income($this),
            'user_name' => config('app.name') == 'xinpay' ? "(#" . $this->user_id . ")" . optional($this->user)->offsetGet('username') : optional($this->user)->offsetGet('bname'),
            'is_switch' => optional($this->user)->offsetGet('action_collection_status'),
            'bank_code' => $this->bank_code,
        ]);
    }
}
