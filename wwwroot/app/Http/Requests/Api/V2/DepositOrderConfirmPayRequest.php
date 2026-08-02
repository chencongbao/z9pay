<?php

namespace App\Http\Requests\Api\V2;

use App\Http\Requests\CommonRequest;
use App\Rules\DecimalTwoPlaces;
use Illuminate\Validation\Rule;

class DepositOrderConfirmPayRequest extends CommonRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'amount' => [
                'required',
                'numeric',
                'gt:0',
                new DecimalTwoPlaces()
            ],
            "order_id" => [
                "required",
                Rule::exists('deposit_orders', 'id')->whereIn('status',[1,3,7])
            ]
        ];
    }

    public function messages()
    {
        return [
            "amount.required" => "金额必填",
            "amount.numeric" => "金额不合法",
            "amount.gt" => "金额必须大于0",
            "order_id.required" => "参数错误",
            "order_id.exists" => "非法操作"
        ];
    }
}
