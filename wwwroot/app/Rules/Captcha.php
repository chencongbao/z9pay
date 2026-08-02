<?php

namespace App\Rules;

use Closure;
use Fastknife\Exception\ParamException;
use Fastknife\Service\BlockPuzzleCaptchaService;
use Fastknife\Service\ClickWordCaptchaService;
use Illuminate\Contracts\Validation\Rule;

class Captcha implements Rule
{

    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function passes($attribute, $value)
    {
        try {
            $captchaType = $this->data['captchaType'];
            switch ($captchaType) {
                case "clickWord":
                    $service = new ClickWordCaptchaService(config('behavior'));
                    break;
                case "blockPuzzle":
                    $service = new BlockPuzzleCaptchaService(config('behavior'));
                    break;
                default:
                    throw new ParamException('非法访问');
            }
            $service->verificationByEncryptCode($value);
            return true;
        }catch (\Exception $exception){
            return false;
        }
    }


    public function message()
    {
        return "验证失败，请重新验证";
    }
}
