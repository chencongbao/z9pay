<?php

namespace App\Http\Resources;

use App\Models\DepositOrder;
use App\Models\User;
use App\Models\UserBank;
use App\Services\User\GetUserRemainingDepositService;
use App\Services\User\TodayDepositOrderTotalAmountService;
use App\Services\User\TodayDepositOrderTotalIncomeService;
use App\Services\User\TodayDepositOrderTotalNumberService;
use App\Services\User\TodayTransferOrderTotalAmountService;
use App\Services\User\TodayTransferOrderTotalIncomeService;
use App\Services\User\TodayTransferOrderTotalNumberService;
use App\Services\User\UserMonthTotalAmountService;
use Illuminate\Support\Facades\App;

class UserResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return $this->filterFields([
            'id' => $this->id,
            'bname' =>$this->bname,
            'username' => $this->username,
            'show' => false,
            'name' => $this->name."(#".$this->id.")",
            'is_agent' => $this->is_agent,
            'today_transfer_amount' => App::make(TodayTransferOrderTotalAmountService::class)->excute($this->id,0,$this->is_agent),
            'today_transfer_number' => App::make(TodayTransferOrderTotalNumberService::class)->excute($this->id,0,$this->is_agent),
            'today_transfer_income' => App::make(TodayTransferOrderTotalIncomeService::class)->excute($this->id,0,$this->is_agent),
            'today_deposit_amount' => App::make(TodayDepositOrderTotalAmountService::class)->excute($this->id,0,$this->is_agent),
            'today_deposit_number' => App::make(TodayDepositOrderTotalNumberService::class)->excute($this->id,0,$this->is_agent),
            'today_deposit_income' => App::make(TodayDepositOrderTotalIncomeService::class)->excute($this->id,0,$this->is_agent),
            'status_text' => optional(config('default.status_text'))->offsetGet($this->status),
            'bank_count' => $this->bank_count(),
            'remaining_deposit' => $this->remainingDeposit(),
            'total_deposit_amount' => App::make(UserMonthTotalAmountService::class)->excute($this->id,0,$this->is_agent),
            'user_count' => $this->user_count()
        ]);
    }

    private function user_count(){
        return User::where('pid',$this->id)->where('is_agent',0)->count();
    }


    private function bank_count()
    {
        if($this->is_agent == 1){
            return UserBank::whereIn('user_id',User::where('pid',$this->id)->where('is_agent',0)->pluck('id'))->where('collection_status',1)->count();
        }
        return UserBank::where('user_id',$this->id)->where('collection_status',1)->count();
    }

    private function remainingDeposit()
    {
        if($this->deposit_amount > 0){
            $remainingDeposit = App::make(GetUserRemainingDepositService::class)->excute($this->id);
            return bob_unit_format($remainingDeposit['remaining_deposit'] ?? 0);
        }
        return "不限制";
    }
}
