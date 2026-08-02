<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Middleware\AgentAdminNoStoreCache;

class AgentAdminCacheSecurityTest extends TestCase
{
    public function test_agent_responses_disable_sensitive_page_storage(): void
    {
        $request = Request::create('/agent/balance-logs', 'GET');
        $response = (new AgentAdminNoStoreCache())->handle($request, fn () => new Response('sensitive balance'));

        $this->assertCacheControlDisablesStorage((string) $response->headers->get('Cache-Control'));
        $this->assertSame('no-cache', $response->headers->get('Pragma'));
        $this->assertSame('Thu, 01 Jan 1970 00:00:00 GMT', $response->headers->get('Expires'));
        $this->assertFalse($response->headers->has('Clear-Site-Data'));
    }

    public function test_logout_clears_browser_cache_without_clearing_site_cookies(): void
    {
        $prefix = trim((string) config('agent-admin.route.prefix'), '/');
        $request = Request::create("/{$prefix}/auth/logout", 'GET');
        $response = (new AgentAdminNoStoreCache())->handle($request, fn () => new Response('', 302));

        $this->assertSame('"cache"', $response->headers->get('Clear-Site-Data'));
    }

    public function test_agent_routes_enable_cache_protection_and_login_does_not_include_history_reload_script(): void
    {
        $this->assertContains('agent.admin.no-store', config('agent-admin.route.middleware'));
        $this->assertNotContains('agent.admin.no-store', config('admin.route.middleware'));

        $domain = (string) config('agent-admin.route.domain');
        $prefix = trim((string) config('agent-admin.route.prefix'), '/');
        $url = ($domain === '' ? '' : 'http://' . $domain) . "/{$prefix}/auth/login";
        $response = $this->get($url);

        $response->assertOk();
        $this->assertCacheControlDisablesStorage((string) $response->headers->get('Cache-Control'));
        $response->assertDontSee('agent-admin-security-history', false);
    }

    public function test_authenticated_layout_history_guard_only_reloads_back_forward_pages(): void
    {
        $script = view('agent-admin.security-history')->render();

        $this->assertStringContainsString("window.addEventListener('pageshow'", $script);
        $this->assertStringContainsString('event.persisted', $script);
        $this->assertStringContainsString("navigation.type === 'back_forward'", $script);
        $this->assertStringContainsString('window.location.reload()', $script);
        $this->assertStringNotContainsString("navigation.type === 'reload'", $script);
    }

    private function assertCacheControlDisablesStorage(string $header): void
    {
        foreach (['no-store', 'no-cache', 'must-revalidate', 'private', 'max-age=0'] as $directive) {
            $this->assertStringContainsString($directive, $header);
        }
    }
}
