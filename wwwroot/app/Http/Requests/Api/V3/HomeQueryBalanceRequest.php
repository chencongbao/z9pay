<?php

namespace App\Http\Requests\Api\V3;

use App\Http\Requests\CommonRequest;

class HomeQueryBalanceRequest extends CommonRequest
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
            'sign' => ['bail', 'required', 'string', 'max:100'],
        ];
    }

    public function messages()
    {
        return [
            'mid.required' => 'api.mid.required',
            'mid.integer' => 'api.mid.integer',
            'mid.min' => 'api.mid.min',
            'sign.required' => 'api.sign.required',
            'sign.string' => 'api.sign.string',
            'sign.max' => 'api.sign.max',
        ];
    }
}
