<?php

namespace App\Http\Requests\Api\V3;

use App\Http\Requests\CommonRequest;
use App\Rules\ApiAmount;
use Illuminate\Validation\Rule;

class HomeTransferIndexRequest extends CommonRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'mid' => ['bail', 'required', 'integer', 'min:1', function ($attribute, $value, $fail) {
                if ((int) $this->merchantUserId() !== (int) $value) {
                    return $fail('api.merchant_id_error');
                }
            }],
            'amount' => ['bail', 'required', new ApiAmount('1')],
            'order_no' => ['bail', 'required', 'string', 'max:90'],
            'ip' => ['bail', 'required', 'ip', 'max:50'],
            'bank_code' => ['bail', 'required', 'string', 'max:50'],
            'card_no' => ['bail', 'required', 'string', 'max:100'],
            'holder_name' => ['bail', 'required', 'string', 'max:100'],
            'notify_url' => ['bail', 'required', 'url', 'max:250'],
            'sign' => ['bail', 'required', 'string', 'max:100'],
            'bank_name' => ['bail', Rule::requiredIf(function () {
                $bankCode = $this->input('bank_code');
                return is_scalar($bankCode) && strtolower((string)$bankCode) === 'ob';
            }), 'nullable', 'string', 'max:100'],
            'bank_branch' => ['bail', 'nullable', 'string', 'max:100'],
            'identity_no' => ['bail', 'nullable', 'string', 'max:100'],
            'withdrawQueryUrl' => ['bail', 'sometimes', 'required', 'url', 'max:250'],
        ];
    }

    public function messages()
    {
        return [
            'mid.required' => 'api.mid.required',
            'mid.integer' => 'api.mid.integer',
            'mid.min' => 'api.mid.min',
            'amount.required' => 'api.amount.required',
            'order_no.required' => 'api.order_no.required',
            'order_no.string' => 'api.order_no.string',
            'order_no.max' => 'api.order_no.max',
            'notify_url.required' => 'api.notify_url.required',
            'notify_url.url' => 'api.notify_url.url',
            'notify_url.max' => 'api.notify_url.max',
            'ip.required' => 'api.ip.required',
            'ip.ip' => 'api.ip.ip',
            'ip.max' => 'api.ip.max',
            'sign.required' => 'api.sign.required',
            'sign.string' => 'api.sign.string',
            'sign.max' => 'api.sign.max',
            'bank_code.required' => 'api.bank_code.required',
            'bank_code.string' => 'api.bank_code.required',
            'bank_code.max' => 'api.bank_code.required',
            'card_no.required' => 'api.card_no.required',
            'card_no.string' => 'api.card_no.string',
            'card_no.max' => 'api.card_no.max',
            'holder_name.required' => 'api.holder_name.required',
            'holder_name.string' => 'api.holder_name.required',
            'holder_name.max' => 'api.holder_name.max',
            'bank_name.required' => 'api.bank_name.required',
            'bank_name.string' => 'api.bank_name.string',
            'bank_name.max' => 'api.bank_name.max',
            'bank_branch.string' => 'api.bank_branch.required',
            'bank_branch.max' => 'api.bank_branch.required',
            'identity_no.string' => 'api.identity_no.required',
            'identity_no.max' => 'api.identity_no.required',
            'withdrawQueryUrl.required' => 'api.withdrawQueryUrl.required',
            'withdrawQueryUrl.url' => 'api.withdrawQueryUrl.url',
            'withdrawQueryUrl.max' => 'api.withdrawQueryUrl.max',
        ];
    }
}
