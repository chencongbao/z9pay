<?php

namespace App\Http\Resources;


use App\Models\BankCode;
use Illuminate\Support\Str;

class TransferOrderResource extends BaseResource
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
            'status' => $this->status,
            'status_text' => optional(config('default.transfer_status'))->offsetGet($this->status),
            'amount' => bob_amount_format($this->amount),
            'actual_amount' => bob_amount_format($this->actual_amount),
            'create_time' => date('Y-m-d H:i:s',strtotime($this->created_at)),
            'pay_info' => $this->getPayInfo(),
            'ordernumber' => $this->ordernumber,
            'ordernumber_format' => "**".Str::substr($this->ordernumber,-10),
            'pay_overtime' => date('Y-m-d H:i',strtotime($this->updated_at) + floatval(bob_admin_setting("base_transfer_pay_overtime")) * 60),
            'bank_name' => $this->getBankName(),
            'holder_name' => $this->holder_name,
            'card_no' => $this->card_no,
            'user_commission' => bob_amount_format($this->user_commission),
            'user' => $this->user ? $this->user->only(['id', 'name', 'username']) : null
        ]);
    }

    private function getBankName(){
        $bank_name = $this->bank_name;
        if(empty($bank_name)){
            $bank_name = optional(BankCode::where('code',$this->bank_code)->first())->name;
        }
        return $bank_name;
    }

    private function getPayInfo()
    {
        return $this->getBankName()."-".$this->holder_name."-".Str::substr($this->card_no,-4);
    }
}
