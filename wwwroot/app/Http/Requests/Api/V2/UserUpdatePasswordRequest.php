<?php

namespace App\Http\Requests\Api\V2;

use App\Http\Requests\CommonRequest;
use Illuminate\Validation\Rules\Password;

class UserUpdatePasswordRequest extends CommonRequest
{
    public function rules()
    {
        return [
            'old_password' => 'required',
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->letters()->numbers(),
            ],
        ];
    }

    public function messages()
    {
        return [
            "old_password.required" => "旧密码必填",
            'password.required'   => '请输入密码',
            'password.confirmed'  => '两次输入的密码不一致',
            'password.min'        => '密码长度不能少于 8 位',
            'password.letters'   => '密码必须包含字母',
            'password.numbers'   => '密码必须包含数字',
        ];
    }
}
