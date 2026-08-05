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
    public function rules(): array
    {
        $captchaKey = $this->input('key');
        $captchaKey = is_string($captchaKey) ? $captchaKey : '';
        $vcodeRules = ['bail', 'required', 'string'];
        if ($captchaKey !== '' && is_string($this->input('vcode'))) {
            $vcodeRules[] = 'captcha_api:'.$captchaKey.',math';
        }

        return [
            'username' => [
                'bail',
                'required',
                'string',
                Rule::exists('users', 'username'),
            ],
            'password' => 'bail|required|string',
            'key' => 'bail|required|string',
            'vcode' => $vcodeRules,
        ];
    }

    public function messages(): array
    {
        return [
            'username.required' => '请填写用户名',
            'username.string' => '用户名格式错误',
            'username.exists' => '用户名不存在',
            'password.required' => '请填写密码',
            'password.string' => '密码格式错误',
            'key.required' => '验证码标识不能为空',
            'key.string' => '验证码标识格式错误',
            'vcode.required' => '请填写验证码',
            'vcode.string' => '验证码格式错误',
            'vcode.captcha_api' => '验证码错误',
        ];
    }
}
