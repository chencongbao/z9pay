<?php

namespace App\Http\Requests\Api\V3;

use App\Http\Requests\CommonRequest;
use App\Rules\ApiAmount;
use App\Rules\TransferOrderNumber;

class HomeTransferCheckRequest extends CommonRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'cid' => ['bail', 'required', 'integer', 'min:1'],
            'ordernumber' => ['bail', 'required', 'string', 'max:100', new TransferOrderNumber()],
            "amount" => ['bail', 'required', new ApiAmount()],
            'sign' => ['bail', 'required', 'string', 'max:100'],
        ];
    }

    public function messages()
    {
        return [
            'cid.required' => 'api.cid.required',
            'cid.integer' => 'api.cid.integer',
            'cid.min' => 'api.cid.min',
            'ordernumber.required' => 'api.ordernumber.required',
            'ordernumber.string' => 'api.ordernumber.string',
            'ordernumber.max' => 'api.ordernumber.max',
            "amount.required" => "api.amount.required",
            'sign.required' => 'api.sign.required',
            'sign.string' => 'api.sign.string',
            'sign.max' => 'api.sign.max',
        ];
    }
}
