<?php

namespace App\Http\Requests\Api\V2;

use App\Http\Requests\CommonRequest;
use Illuminate\Validation\Rule;

class HomeCheckGoogleVcodeRequest extends CommonRequest
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
            "google_2fa_code" => "required|numeric|digits:6",
        ];
    }

    public function messages()
    {
        return [
            "username.required" => "请填写用户名",
            "username.exists" => "用户名不存在",
            "password.required" => "请填写密码",
            'google_2fa_code.required' => "请输入谷歌验证码",
            'google_2fa_code.numeric' => "请输入谷歌验证码",
            'google_2fa_code.digits' => "请输入谷歌验证码",
        ];
    }
}
