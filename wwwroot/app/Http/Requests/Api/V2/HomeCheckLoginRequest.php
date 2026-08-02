<?php

namespace App\Http\Requests\Api\V2;

use App\Http\Requests\CommonRequest;
use Illuminate\Validation\Rule;

class HomeCheckLoginRequest extends CommonRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'username' => [
                'required',
                Rule::exists('users', 'username')
            ],
            "password" => "required",
            "vcode" => "required|captcha_api:".request('key').",math"
        ];
    }

    public function messages()
    {
        return [
            "username.required" => "请填写用户名",
            "username.exists" => "用户名不存在",
            "password.required" => "请填写密码",
            "vcode.required" => "请填写验证码",
            "vcode.captcha_api" => "验证码错误"
        ];
    }
}
