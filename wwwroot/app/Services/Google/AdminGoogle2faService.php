<?php

namespace App\Services\Google;

use App\Services\Cache\CacheConstPrefixService;
use Dcat\Admin\Admin;
use Illuminate\Support\Facades\Cache;
use PragmaRX\Google2FA\Google2FA;

class AdminGoogle2faService
{
    public function disabled(): bool
    {
        return filter_var(config('default.disable_2fa', false), FILTER_VALIDATE_BOOLEAN)
            || filter_var(config('default.admin_google_2fa_disabled', false), FILTER_VALIDATE_BOOLEAN);
    }

    public function shouldVerify(): bool
    {
        return !$this->disabled() && !Cache::has($this->cacheKey());
    }

    public function appendField($form): void
    {
        if (!$this->shouldVerify()) {
            return;
        }

        $form->text('google_2fa_code', __('admin.google_2fa_code'))
            ->prepend('<i class="fa fa-google"></i>')
            ->required();
    }

    public function verify($googleVcode = ''): bool
    {
        if ($this->disabled()) {
            return true;
        }

        if ($this->shouldVerify()) {
            if (empty($googleVcode)) {
                throw new \Exception(admin_trans_label('input_google_vcode'));
            }

            if (!(new Google2FA())->verifyKey(Admin::user()->google_two_fa_secret, $googleVcode)) {
                throw new \Exception(admin_trans_label('google_vcode_error'));
            }

            $time = $this->rememberSeconds();
            Cache::put($this->cacheKey(), time() + $time, $time);
        } else {
            $time = Cache::get($this->cacheKey()) - time() + 30;
            Cache::put($this->cacheKey(), time() + $time, $time);
        }

        return true;
    }

    private function cacheKey(): string
    {
        return CacheConstPrefixService::ADMIN_OPERATE_GOOGLE_2FA_CODE_TIME.Admin::user()->id;
    }

    private function rememberSeconds(): int
    {
        return max(1, intval(bob_admin_setting('other_admin_operate_google_2fa_code_time'))) * 60;
    }
}
