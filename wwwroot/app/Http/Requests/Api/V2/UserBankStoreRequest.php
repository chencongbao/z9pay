<?php

namespace App\Http\Requests\Api\V2;

use App\Admin\Controllers\CommonController;
use App\Http\Requests\CommonRequest;
use App\Rules\DecimalTwoPlaces;
use App\Services\UserBank\GetSelfUserBankTypeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\Rule;

class UserBankStoreRequest extends CommonRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'name' => 'required|string|max:50',
            'account_type' => ['required',Rule::in(App::make(GetSelfUserBankTypeService::class)->excute()->pluck('id'))],
            'payment_id' => ['required', Rule::in(collect(config('payment'))->pluck('id')->filter(fn($id) => intval($id) > 0))],
            'bank_id' => ['exclude_unless:account_type,1','required',Rule::exists("bank_codes",'id')],
            'card_no' => ['nullable', 'string', 'max:50'],
            'limint_min_amount' => ['numeric', 'between:0,9999999', new DecimalTwoPlaces()],
            'limint_max_amount' => ['numeric', 'between:0,9999999', new DecimalTwoPlaces(),'gte:limint_min_amount'],
            'limint_day_amount' => ['numeric', 'between:0,9999999', new DecimalTwoPlaces()],
            'limit_day_order_number' => ['numeric', 'between:0,9999999'],
            'payment_qrcode' => [
                Rule::requiredIf(fn() => in_array(intval($this->input('account_type')), [3, 5, 14, 28], true)),
                'nullable',
                'string',
                'max:200',
            ],
            'payment_qrcode_url' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages()
    {
        return [
            'name.required' => '收款人姓名必填',
            'name.string' => '收款人姓名格式不正确',
            'name.max' => '收款人姓名不能超过50个字符',
            'payment_id.required' => '收款编码必选',
            'payment_id.in' => '请选择正确的收款编码',
            'bank_id.required' => '收款银行必选',
            'bank_id.exists' => '请选择收款银行',
            'card_no.string' => '收款卡号格式不正确',
            'card_no.max' => '收款卡号不能超过50个字符',
            'limint_min_amount.numeric' => '单笔最低限额数值不合法',
            'limint_min_amount.between' => '单笔最低限额0-9999999',
            'limint_max_amount.numeric' => '单笔最高限额数值不合法',
            'limint_max_amount.between' => '单笔最高限额0-9999999',
            'limint_max_amount.gte' => '单笔最高限额必须大于等于单笔最低限额',
            'limint_day_amount.numeric' => '全天限额数值不合法',
            'limint_day_amount.between' => '全天限额0-9999999',
            'limit_day_order_number.numeric' => "全天限接单数量数值不合法",
            'limit_day_order_number.between' => "全天限接单数量0-9999999",
            'payment_qrcode.required' => '请上传二维码',
            'payment_qrcode.string' => '收款二维码格式不正确',
            'payment_qrcode.max' => '收款二维码不能超过200个字符',
            'payment_qrcode_url.string' => '收款二维码链接格式不正确',
            'payment_qrcode_url.max' => '收款二维码链接不能超过255个字符',
        ];
    }
}
