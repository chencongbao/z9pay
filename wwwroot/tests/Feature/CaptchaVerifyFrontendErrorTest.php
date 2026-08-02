<?php

namespace Tests\Feature;

use Tests\TestCase;

class CaptchaVerifyFrontendErrorTest extends TestCase
{
    public function test_shared_captcha_plugin_handles_ajax_errors_without_automatic_retry(): void
    {
        $source = file_get_contents(public_path('vendor/captcha/js/verify.js'));

        $this->assertStringNotContainsString('fail: function', $source);
        $this->assertSame(2, substr_count($source, 'error: function(xhr)'));
        $this->assertSame(2, substr_count($source, 'global: false'));
        $this->assertStringContainsString("typeof reject === 'function'", $source);
        $this->assertStringContainsString('xhr.status', $source);
        $this->assertStringContainsString("getResponseHeader('Retry-After')", $source);
        $this->assertStringContainsString('Dcat.lang.captcha_rate_limited', $source);
        $this->assertStringContainsString('Dcat.lang.captcha_network_error', $source);
        $this->assertSame(4, substr_count($source, '}, function (res) {'));
        $this->assertSame(2, substr_count($source, 'success:function(res)'));
        $this->assertSame(2, substr_count($source, 'resolve(res)'));
    }

    public function test_captcha_ajax_error_messages_exist_in_every_supported_locale(): void
    {
        $expected = [
            'zh_CN' => ['请求过于频繁，请在 :seconds 秒后重试', '验证码加载失败，请稍后重试'],
            'en' => ['Too many verification requests. Please retry in :seconds seconds.', 'Unable to load verification. Please try again later.'],
            'vi' => ['Có quá nhiều yêu cầu xác minh. Vui lòng thử lại sau :seconds giây.', 'Không thể tải xác minh. Vui lòng thử lại sau.'],
        ];

        foreach ($expected as $locale => [$limited, $networkError]) {
            app()->setLocale($locale);

            $this->assertSame($limited, __('admin.client.captcha_rate_limited'));
            $this->assertSame($networkError, __('admin.client.captcha_network_error'));
        }
    }
}
