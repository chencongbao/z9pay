<?php

namespace Tests\Feature;

use Tests\TestCase;
use Dcat\Admin\Admin;
use Illuminate\Http\Request;
use App\Http\Middleware\ResetConfig;

class ResetConfigMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetBaseAdminConfig();
        $this->resetDcatApplicationConfig();
        config([
            'app.name' => 'luckypay',
            'admin.lang_key' => 'luckypay',
            'admin-base.lang_key' => 'luckypay',
            'admin-base.title' => 'LUCKY支付',
            'admin-base.name' => 'LUCKY支付',
            'admin-base.logo' => 'LUCKY支付',
            'admin-base.logo-mini' => 'LUCKY支付',
            'admin.title' => 'LUCKY支付',
            'admin.name' => 'LUCKY支付',
            'admin.logo' => 'LUCKY支付',
            'admin.logo-mini' => 'LUCKY支付',
            'admin.route.prefix' => 'admin',
            'merchant-admin.title' => 'LUCKY支付',
            'merchant-admin.name' => 'LUCKY支付',
            'merchant-admin.logo' => 'LUCKY支付',
            'merchant-admin.logo-mini' => 'LUCKY支付',
            'merchant-admin.lang_key' => 'luckypay',
            'merchant-admin.route.prefix' => 'admin',
            'agent-admin.title' => 'LUCKY支付',
            'agent-admin.name' => 'LUCKY支付',
            'agent-admin.logo' => 'LUCKY支付',
            'agent-admin.logo-mini' => 'LUCKY支付',
            'agent-admin.lang_key' => 'luckypay',
            'agent-admin.route.prefix' => 'admin',
        ]);
    }

    protected function tearDown(): void
    {
        $this->resetBaseAdminConfig();
        $this->resetDcatApplicationConfig();
        Admin::app()->switch('admin');

        parent::tearDown();
    }

    public function test_admin_config_is_restored_after_merchant_request_in_same_process(): void
    {
        $middleware = new ResetConfig();

        Admin::app()->switch('merchant-admin');
        $middleware->handle(Request::create('/admin'), fn () => response('ok'));
        $this->assertSame(__('admin.luckypay'), config('admin.name'));
        $this->assertSame(__('admin.luckypay'), config('admin.logo'));

        Admin::app()->switch('admin');
        $middleware->handle(Request::create('/admin'), fn () => response('ok'));
        $this->assertSame(__('admin.luckypay'), config('admin.title'));
        $this->assertSame(__('admin.luckypay'), config('admin.name'));
        $this->assertSame(__('admin.luckypay'), config('admin.logo'));
        $this->assertSame(mb_substr(__('admin.luckypay'), 0, 1), config('admin.logo-mini'));
    }

    public function test_admin_title_uses_current_locale_brand_translation(): void
    {
        $middleware = new ResetConfig();

        app()->setLocale('en');
        Admin::app()->switch('admin');
        $middleware->handle(Request::create('/admin'), fn () => response('ok'));
        $this->assertSame('LuckPay', config('admin.title'));
        $this->assertSame('LuckPay', config('admin.name'));
        $this->assertSame('LuckPay', config('admin.logo'));

        app()->setLocale('vi');
        $this->resetBaseAdminConfig();
        $middleware->handle(Request::create('/admin'), fn () => response('ok'));
        $this->assertSame('LuckPay', config('admin.title'));
    }

    public function test_merchant_and_agent_use_configured_brand_title_by_default(): void
    {
        $middleware = new ResetConfig();

        app()->setLocale('en');
        Admin::app()->switch('merchant-admin');
        $middleware->handle(Request::create('/admin/information'), fn () => response('ok'));
        $this->assertSame('LuckPay', config('admin.title'));
        $this->assertSame('LuckPay', config('admin.name'));
        $this->assertSame('LuckPay', config('merchant-admin.title'));
        $this->assertSame('L', config('merchant-admin.logo-mini'));

        Admin::app()->switch('agent-admin');
        $middleware->handle(Request::create('/admin'), fn () => response('ok'));
        $this->assertSame('LuckPay', config('admin.title'));
        $this->assertSame('LuckPay', config('admin.name'));
        $this->assertSame('LuckPay', config('agent-admin.title'));
        $this->assertSame('L', config('agent-admin.logo-mini'));
    }

    public function test_all_backend_titles_read_translation_key_from_config(): void
    {
        $middleware = new ResetConfig();
        app()->setLocale('zh_CN');

        config([
            'admin-base.lang_key' => 'sgpay',
            'merchant-admin.lang_key' => 'luckypay',
            'agent-admin.lang_key' => 'yuandongpay',
        ]);
        $this->resetBaseAdminConfig();

        Admin::app()->switch('admin');
        $middleware->handle(Request::create('/admin'), fn () => response('ok'));
        $this->assertSame(__('admin.sgpay'), config('admin.title'));

        Admin::app()->switch('merchant-admin');
        $middleware->handle(Request::create('/admin'), fn () => response('ok'));
        $this->assertSame(__('admin.luckypay'), config('admin.title'));

        Admin::app()->switch('agent-admin');
        $middleware->handle(Request::create('/admin'), fn () => response('ok'));
        $this->assertSame(__('admin.yuandongpay'), config('admin.title'));
    }

    private function resetBaseAdminConfig(): void
    {
        $reflection = new \ReflectionClass(ResetConfig::class);
        $reflection->setStaticPropertyValue('baseAdminConfig', null);
    }

    private function resetDcatApplicationConfig(): void
    {
        $reflection = new \ReflectionClass(Admin::app());
        $property = $reflection->getProperty('configs');
        $property->setValue(Admin::app(), []);
    }
}
