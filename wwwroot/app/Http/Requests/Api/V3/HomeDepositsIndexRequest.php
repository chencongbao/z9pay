<?php

namespace App\Http\Requests\Api\V3;

use App\Http\Requests\CommonRequest;
use App\Rules\ApiAmount;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class HomeDepositsIndexRequest extends CommonRequest
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
            'amount' => ['bail', 'required', new ApiAmount()],
            'order_no' => ['bail', 'required', 'string', 'max:100'],
            'gateway' => ["bail", "required", "string", "max:40", Rule::in(Arr::map(config('payment',[]), function ($item) {
                return $item['code'];
            }))],
            'notify_url' => ['bail', 'required', 'url', 'max:250'],
            'ip' => ['bail', 'required', 'ip', 'max:50'],
            'sign' => ['bail', 'required', 'string', 'max:100'],
            'name' => ['bail', 'nullable', 'string', 'max:50'],
            'bank_name' => ['bail', 'nullable', 'string', 'max:100'],
            'card_no' => ['bail', 'nullable', 'string', 'max:100'],
            'card_pin' => ['bail', 'nullable', 'string', 'max:100'],
            'card_name' => ['bail', 'nullable', 'string', 'max:100'],
            'return_url' => ['bail', 'nullable', 'url', 'max:250'],
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
            'gateway.required' => 'api.gateway.required',
            'gateway.string' => 'api.gateway.in',
            'gateway.max' => 'api.gateway.in',
            'gateway.in' => 'api.gateway.in',
            'notify_url.required' => 'api.notify_url.required',
            'notify_url.url' => 'api.notify_url.url',
            'notify_url.max' => 'api.notify_url.max',
            'ip.required' => 'api.ip.required',
            'ip.ip' => 'api.ip.ip',
            'ip.max' => 'api.ip.max',
            'sign.required' => 'api.sign.required',
            'sign.string' => 'api.sign.string',
            'sign.max' => 'api.sign.max',
            'name.string' => 'api.name.string',
            'name.max' => 'api.name.max',
            'bank_name.string' => 'api.bank_name.string',
            'bank_name.max' => 'api.bank_name.max',
            'card_no.string' => 'api.card_no.string',
            'card_no.max' => 'api.card_no.max',
            'card_pin.string' => 'api.card_pin.string',
            'card_pin.max' => 'api.card_pin.max',
            'card_name.string' => 'api.card_name.string',
            'card_name.max' => 'api.card_name.max',
            'return_url.url' => 'api.return_url.url',
            'return_url.max' => 'api.return_url.max',
        ];
    }
}
