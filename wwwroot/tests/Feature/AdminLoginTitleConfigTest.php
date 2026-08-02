<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;

class AdminLoginTitleConfigTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        View::share('errors', new ViewErrorBag());
    }

    public function test_merchant_login_title_reads_admin_translation_key_from_config(): void
    {
        app()->setLocale('zh_CN');
        config(['merchant-admin.lang_key' => 'luckypay']);

        $html = view('merchant-admin.login')->render();

        $this->assertStringContainsString('LUCKY支付', $html);
        $this->assertStringNotContainsString('商户后台', $html);
    }

    public function test_merchant_lixiang_login_title_reads_admin_translation_key_from_config(): void
    {
        app()->setLocale('zh_CN');
        config(['merchant-admin.lang_key' => 'sgpay']);

        $html = view('merchant-admin.lixiangpay-login')->render();

        $this->assertStringContainsString('SGPAY', $html);
        $this->assertStringNotContainsString('商户后台', $html);
    }

    public function test_agent_login_title_reads_admin_translation_key_from_config(): void
    {
        app()->setLocale('zh_CN');
        config(['agent-admin.lang_key' => 'luckypay']);

        $html = view('agent-admin.login')->render();

        $this->assertStringContainsString('LUCKY支付', $html);
        $this->assertStringNotContainsString('代理后台', $html);
    }

    public function test_admin_login_title_reads_admin_translation_key_from_config(): void
    {
        app()->setLocale('zh_CN');
        config(['admin.lang_key' => 'sgpay']);

        $html = view('admin.login')->render();

        $this->assertStringContainsString('SGPAY', $html);
        $this->assertStringNotContainsString('超管后台', $html);
    }
}
