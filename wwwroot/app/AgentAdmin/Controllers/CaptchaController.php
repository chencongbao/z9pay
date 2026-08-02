<?php

namespace App\AgentAdmin\Controllers;

use Throwable;
use Fastknife\Exception\ParamException;
use App\Admin\Controllers\CommonController;
use Fastknife\Service\ClickWordCaptchaService;
use Fastknife\Service\BlockPuzzleCaptchaService;

class CaptchaController extends CommonController
{
    protected $disableCreate = true;
    protected $disableEdit = true;

    public function get(): array
    {
        try {
            $data = $this->getCaptchaService()->get();
        } catch (Throwable $e) {
            return $this->e($e->getMessage());
        }

        return $this->s($data);
    }

    public function check(): array
    {
        try {
            $data = $this->validateData();
            $this->getCaptchaService()->check($data['token'], $data['pointJson']);
        } catch (Throwable $e) {
            return $this->e($e->getMessage());
        }

        return $this->s([]);
    }

    protected function getCaptchaService()
    {
        $captchaType = request()->post('captchaType', null);
        $config = config('behavior');

        if ($captchaType === 'clickWord') {
            return new ClickWordCaptchaService($config);
        }

        if ($captchaType === 'blockPuzzle') {
            return new BlockPuzzleCaptchaService($config);
        }

        throw new ParamException(__('auth.agent_login.invalid_captcha_type'));
    }

    protected function validateData(): array
    {
        return request()->validate([
            'token' => 'bail|required',
            'pointJson' => 'required',
        ]);
    }

    protected function s($data): array
    {
        $this->data = [
            'error' => false,
            'repCode' => '0000',
            'repData' => $data,
            'repMsg' => null,
            'success' => true,
        ];
        return $this->data;
    }

    protected function e(string $msg): array
    {
        $this->data = [
            'error' => true,
            'repCode' => '6111',
            'repData' => null,
            'repMsg' => $msg,
            'success' => false,
        ];
        return $this->data;
    }
}
