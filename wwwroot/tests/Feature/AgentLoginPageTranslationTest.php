<?php

namespace Tests\Feature;

use Tests\TestCase;

class AgentLoginPageTranslationTest extends TestCase
{
    public function test_google_verification_modal_and_fallback_messages_are_localized(): void
    {
        $view = file_get_contents(resource_path('views/agent-admin/login.blade.php'));

        foreach ([
            "{{ __('auth.agent_login.google_verify') }}",
            "{{ __('auth.agent_login.input_google_code') }}",
            "{{ __('admin.google_2fa_code') }}",
            "{{ __('admin.cancel') }}",
            "{{ __('auth.agent_login.confirm_google_verify') }}",
            'const loginMessages = {{ Illuminate\Support\Js::from($loginMessages) }};',
        ] as $translationReference) {
            $this->assertStringContainsString($translationReference, $view);
        }

        $hardcodedChineseMessages = [
            '谷歌验证',
            '请输入谷歌验证码',
            '谷歌验证码',
            '确认验证',
            '登录失败',
            '登录状态已失效',
            '请输入6位谷歌验证码',
            '谷歌验证失败',
        ];

        foreach ($hardcodedChineseMessages as $message) {
            $this->assertStringNotContainsString($message, $view);
        }

        $expected = [
            'zh_CN' => [
                'google_verify' => '谷歌验证',
                'confirm_google_verify' => '确认验证',
                'login_failed' => '登录失败',
                'verify_session_expired' => '验证会话已失效，请重新登录',
                'input_six_digit_google_code' => '请输入6位谷歌验证码',
                'google_verify_failed' => '谷歌验证失败',
            ],
            'en' => [
                'google_verify' => 'Google verification',
                'confirm_google_verify' => 'Verify',
                'login_failed' => 'Login failed.',
                'verify_session_expired' => 'The verification session has expired. Please log in again.',
                'input_six_digit_google_code' => 'Please enter a 6-digit Google verification code.',
                'google_verify_failed' => 'Google verification failed.',
            ],
        ];

        foreach ($expected as $locale => $messages) {
            app()->setLocale($locale);

            foreach ($messages as $key => $message) {
                $this->assertSame($message, __("auth.agent_login.{$key}"));
            }
        }

        app()->setLocale('en');
        $this->assertSame('Google verification Code', __('admin.google_2fa_code'));
        $this->assertSame('Cancel', __('admin.cancel'));
        app()->setLocale('zh_CN');
        $this->assertSame('谷歌验证码', __('admin.google_2fa_code'));
        $this->assertSame('取消', __('admin.cancel'));
    }

    public function test_agent_login_view_renders_with_english_locale(): void
    {
        app()->setLocale('en');

        $html = view('agent-admin.login', ['errors' => new \Illuminate\Support\ViewErrorBag()])->render();

        $this->assertStringContainsString('<h5 class="modal-title">Google verification</h5>', $html);
        $this->assertStringContainsString('const loginMessages = JSON.parse(', $html);
        $this->assertStringContainsString('Login failed.', $html);
        $this->assertStringNotContainsString('Unclosed', $html);
    }
}
