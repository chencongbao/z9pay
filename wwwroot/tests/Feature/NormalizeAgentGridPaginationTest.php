<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Http\Request;
use App\Http\Middleware\NormalizeAgentGridPagination;

class NormalizeAgentGridPaginationTest extends TestCase
{
    public function test_alphabetic_pagination_values_are_removed(): void
    {
        $request = $this->runMiddleware(['page' => 'abc', 'per_page' => 'abc']);

        $this->assertFalse($request->query->has('page'));
        $this->assertFalse($request->query->has('per_page'));
    }

    public function test_negative_and_zero_values_fall_back_to_defaults(): void
    {
        $request = $this->runMiddleware(['page' => '-1', 'per_page' => '0']);

        $this->assertFalse($request->query->has('page'));
        $this->assertFalse($request->query->has('per_page'));
    }

    public function test_valid_values_and_named_grid_parameters_are_preserved(): void
    {
        $request = $this->runMiddleware([
            'page' => '2',
            'per_page' => '50',
            'orders_page' => '3',
            'orders_per_page' => '100',
        ]);

        $this->assertSame('2', $request->query('page'));
        $this->assertSame('50', $request->query('per_page'));
        $this->assertSame('3', $request->query('orders_page'));
        $this->assertSame('100', $request->query('orders_per_page'));
    }

    public function test_oversized_per_page_is_limited_for_all_agent_grids(): void
    {
        config(['agent-admin.grid.per_page_max' => 200]);

        $request = $this->runMiddleware(['per_page' => '999999', 'orders_per_page' => '500']);

        $this->assertSame('200', $request->query('per_page'));
        $this->assertSame('200', $request->query('orders_per_page'));
    }

    public function test_balance_logs_route_uses_agent_pagination_normalization(): void
    {
        $prefix = trim((string) config('agent-admin.route.prefix'), '/');
        $route = collect(app('router')->getRoutes()->getRoutes())->first(fn ($route) => $route->uri() === $prefix . '/balance-logs');

        $this->assertNotNull($route);
        $this->assertContains('normalize.agent.grid.pagination', $route->gatherMiddleware());
    }

    public function test_actual_balance_logs_route_does_not_fail_on_alphabetic_pagination(): void
    {
        $domain = (string) config('agent-admin.route.domain');
        $prefix = trim((string) config('agent-admin.route.prefix'), '/');
        $url = ($domain === '' ? '' : 'http://' . $domain) . '/' . $prefix . '/balance-logs?page=abc&per_page=abc';

        $this->get($url)->assertRedirect(admin_url('auth/login'));
    }

    private function runMiddleware(array $query): Request
    {
        $request = Request::create('/agent/balance-logs', 'GET', $query);

        return (new NormalizeAgentGridPagination())->handle($request, fn (Request $request) => $request);
    }
}
