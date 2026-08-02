<?php

namespace App\Http\Resources;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DepositOrderResource extends BaseResource
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
            'account_type' => $this->account_type,
            'status_text' => $this->getStatusText(),
            "amount" => bob_amount_format($this->pay_amount),
            "actual_amount" => bob_amount_format($this->actual_amount),
            'time' => $this->getTime(),
            'pay_info' => $this->getPayInfo(),
            'color' => $this->getColor(),
            'ordernumber' => $this->ordernumber,
            'user_commission' => $this->user_commission,
            'create_time' => date('Y-m-d H:i:s',strtotime($this->created_at)),
            'pay_name' => $this->pay_name,
            'card_no' => $this->collection_card_no,
            'pay_status' => $this->pay_status,
            'pay_certificate' => Storage::disk('admin')->url($this->pay_certificate),
            "user" => $this->user
        ]);
    }

    private function getPayInfo(){
        $result =  optional(config('default.user_bank_type'))->offsetGet($this->account_type)."-".$this->collection_name;
        if($this->account_type == 1 || $this->account_type == 2 || $this->account_type == 4 || $this->account_type == 6){
            if($this->account_type == 1){
                if($this->bank_codes){
                    $result = $this->bank_codes->name."-".$this->collection_name;
                }
            }
            if(ctype_digit($this->collection_card_no)){
                $result .= "-".Str::substr($this->collection_card_no,-4);
            }else{
                $result .= "-".$this->collection_card_no;
            }
        }
        return $result;
    }

    private function getColor(){
        switch ($this->status){
            case 3:
                return "#ff5252";
                break;
            case 7:
                return "#ff5252";
                break;
            case 4:
                return "#fb8c00";
                break;
            case 5:
                return "#4caf50";
                break;
            case 6:
                return "#fb8c00";
                break;
        }
    }

    private function getTime(){
        switch ($this->status){
            case 3:
                return date('Y-m-d H:i:s',strtotime($this->created_at));
                break;
            case 7:
                return date('Y-m-d H:i:s',strtotime($this->updated_at));
                break;
            case 4:
                return date('Y-m-d H:i:s',strtotime($this->updated_at));
                break;
            case 5:
                return date('Y-m-d H:i:s',$this->success_time);
                break;
        }
    }



    private function getStatusText()
    {
        switch ($this->status){
            case 3:
                return "待确认";
                break;
            case 7:
                return "待审核";
                break;
            case 4:
                return "超时";
                break;
            case 5:
                return "成功";
                break;
            case 6:
                return "失败";
                break;
        }
    }
}
