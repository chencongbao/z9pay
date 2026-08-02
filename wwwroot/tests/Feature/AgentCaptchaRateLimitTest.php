<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\RateLimiter;

class AgentCaptchaRateLimitTest extends TestCase
{
    private const IPS = ['192.0.2.10', '192.0.2.11', '192.0.2.12'];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'agent-admin.captcha.get_per_minute' => 2,
            'agent-admin.captcha.check_per_minute' => 2,
        ]);
        $this->clearCaptchaLimits();
    }

    protected function tearDown(): void
    {
        $this->clearCaptchaLimits();

        parent::tearDown();
    }

    public function test_captcha_get_allows_normal_requests_then_returns_standard_429(): void
    {
        foreach (range(1, 2) as $attempt) {
            $response = $this->postCaptcha('get', ['captchaType' => 'blockPuzzle'], self::IPS[0]);
            $response->assertOk()->assertJson(['success' => true]);
        }

        $limited = $this->postCaptcha('get', ['captchaType' => 'blockPuzzle'], self::IPS[0]);
        $limited->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_get_and_check_limits_are_isolated_and_different_ips_do_not_share_limits(): void
    {
        app()->setLocale('en');

        foreach (range(1, 2) as $attempt) {
            $this->postCaptcha('get', ['captchaType' => 'invalid'], self::IPS[1])
                ->assertOk()
                ->assertJson(['success' => false, 'repMsg' => 'Invalid captcha type.']);
        }
        $this->postCaptcha('get', ['captchaType' => 'invalid'], self::IPS[1])->assertStatus(429);

        foreach (range(1, 2) as $attempt) {
            $this->postCaptcha('check', [
                'captchaType' => 'invalid',
                'token' => 'test-token',
                'pointJson' => '{}',
            ], self::IPS[1])->assertOk();
        }
        $this->postCaptcha('check', [
            'captchaType' => 'invalid',
            'token' => 'test-token',
            'pointJson' => '{}',
        ], self::IPS[1])->assertStatus(429)->assertHeader('Retry-After');

        $this->postCaptcha('get', ['captchaType' => 'blockPuzzle'], self::IPS[2])
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_agent_captcha_routes_use_separate_named_limiters(): void
    {
        $prefix = trim((string) config('agent-admin.route.prefix'), '/');
        $routes = collect(app('router')->getRoutes()->getRoutes());
        $getRoute = $routes->first(fn ($route) => $route->uri() === "{$prefix}/captcha/get");
        $checkRoute = $routes->first(fn ($route) => $route->uri() === "{$prefix}/captcha/check");

        $this->assertNotNull($getRoute);
        $this->assertNotNull($checkRoute);
        $this->assertContains('throttle:agent-captcha-get', $getRoute->gatherMiddleware());
        $this->assertContains('throttle:agent-captcha-check', $checkRoute->gatherMiddleware());
    }

    private function postCaptcha(string $action, array $data, string $ip)
    {
        $domain = (string) config('agent-admin.route.domain');
        $prefix = trim((string) config('agent-admin.route.prefix'), '/');
        $url = ($domain === '' ? '' : 'http://' . $domain) . "/{$prefix}/captcha/{$action}";

        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->postJson($url, $data);
    }

    private function clearCaptchaLimits(): void
    {
        foreach (self::IPS as $ip) {
            RateLimiter::clear(md5('agent-captcha-get' . 'agent-captcha-get:ip:' . $ip));
            RateLimiter::clear(md5('agent-captcha-check' . 'agent-captcha-check:ip:' . $ip));
        }
    }
}
