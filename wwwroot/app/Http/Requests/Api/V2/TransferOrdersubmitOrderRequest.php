<?php

namespace App\Http\Requests\Api\V2;

use App\Http\Requests\CommonRequest;
use App\Rules\DecimalTwoPlaces;
use Illuminate\Validation\Rule;

class TransferOrdersubmitOrderRequest extends CommonRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'pay_certificate_1' => [
                'required',
                'string',
                'max:255'
            ],
            'pay_certificate_2' => ['nullable', 'string', 'max:255'],
            'pay_certificate_3' => ['nullable', 'string', 'max:255']
        ];
    }

    public function messages()
    {
        return [
            "pay_certificate_1.required" => "请上传带公章回执单",
            "pay_certificate_1.max" => "回执单路径不能超过255个字符",
            "pay_certificate_2.max" => "回执单路径不能超过255个字符",
            "pay_certificate_3.max" => "回执单路径不能超过255个字符"
        ];
    }
}
