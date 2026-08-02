<?php

namespace App\Http\Requests\Api\V2;

use App\Http\Requests\CommonRequest;
use App\Rules\DepositOrderNumber;

class CashierUploadPayCertificateRequest extends CommonRequest
{


    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'ordernumber' => ['required', 'string', new DepositOrderNumber()],
            'file' => 'required|file|max:5120|mimes:jpeg,jpg,png'
        ];
    }

    public function messages()
    {
        return [
            'ordernumber.required' => "非法提交",
            'file.required' => '请上传付款凭证',
            'file.file' => '请上传付款凭证',
            'file.max' => '付款凭证不能超过5M',
            'file.mimes' => "付款凭证类型为png,jpg",
        ];
    }
}
