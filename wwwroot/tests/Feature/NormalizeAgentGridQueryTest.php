<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Http\Request;
use App\Http\Middleware\NormalizeAgentGridQuery;

class NormalizeAgentGridQueryTest extends TestCase
{
    public function test_reproduced_non_scalar_filters_are_removed(): void
    {
        $cases = [
            ['merchant-users', ['merchant_user_id' => ['24']], 'merchant_user_id'],
            ['merchant-users', ['name' => ['test']], 'name'],
            ['deposit-orders', ['mid' => ['24'], 'status' => ['5']], 'mid'],
            ['transfer-orders', ['amount' => ['100']], 'amount'],
            ['balance-logs', ['type' => ['1']], 'type'],
        ];

        foreach ($cases as [$resource, $query, $key]) {
            $request = $this->runMiddleware($resource, $query);
            $this->assertFalse($request->query->has($key), "Failed sanitizing {$resource}.{$key}");
        }

        $deposit = $this->runMiddleware('deposit-orders', ['mid' => ['24'], 'status' => ['5']]);
        $this->assertFalse($deposit->query->has('status'));
    }

    public function test_invalid_ranges_and_nested_range_arrays_are_removed(): void
    {
        $invalidDate = $this->runMiddleware('deposit-orders', ['created_at' => 'abc']);
        $nestedDate = $this->runMiddleware('deposit-orders', ['created_at' => ['start' => ['2026-01-01']]]);
        $pollutedDate = $this->runMiddleware('deposit-orders', ['created_at' => ['start' => '202https://agent.example.com/-01-01 00:00:00', 'end' => '2026-01-31 23:59:59']]);
        $reversedDate = $this->runMiddleware('deposit-orders', ['created_at' => ['start' => '2026-02-01 00:00:00', 'end' => '2026-01-31 23:59:59']]);
        $invalidReportDate = $this->runMiddleware('reports-merchant-agents', ['date_add' => 'abc']);

        $this->assertFalse($invalidDate->query->has('created_at'));
        $this->assertFalse($nestedDate->query->has('created_at'));
        $this->assertFalse($pollutedDate->query->has('created_at'));
        $this->assertFalse($reversedDate->query->has('created_at'));
        $this->assertFalse($invalidReportDate->query->has('date_add'));
    }

    public function test_invalid_integer_and_decimal_filters_are_removed(): void
    {
        $request = $this->runMiddleware('transfer-orders', [
            'mid' => '24abc',
            'status' => '4',
            'amount' => '100.25',
            'actual_amount' => 'https://agent.example.com',
        ]);

        $this->assertArrayNotHasKey('mid', $request->query->all());
        $this->assertSame('4', $request->query('status'));
        $this->assertSame('100.25', $request->query('amount'));
        $this->assertArrayNotHasKey('actual_amount', $request->query->all());
    }

    public function test_valid_scalar_range_and_sort_values_are_preserved(): void
    {
        $query = [
            'mid' => '24',
            'status' => '5',
            'created_at' => ['start' => '2026-01-01 00:00:00', 'end' => '2026-01-31 23:59:59'],
            '_sort' => ['column' => 'id', 'type' => 'desc'],
        ];

        $request = $this->runMiddleware('deposit-orders', $query);

        $this->assertSame($query, $request->query->all());
    }

    public function test_range_removes_whole_filter_when_any_bound_is_invalid(): void
    {
        $request = $this->runMiddleware('deposit-orders', [
            'created_at' => ['start' => ['invalid'], 'end' => '2026-01-31 23:59:59', 'extra' => 'ignored'],
        ]);

        $this->assertFalse($request->query->has('created_at'));
    }

    public function test_invalid_sort_column_structure_and_direction_are_removed_before_grid_sql(): void
    {
        $invalidColumn = $this->runMiddleware('balance-logs', ['_sort' => ['column' => 'not_exists', 'type' => 'desc']]);
        $invalidDirection = $this->runMiddleware('balance-logs', ['_sort' => ['column' => 'id', 'type' => 'drop']]);
        $invalidStructure = $this->runMiddleware('balance-logs', ['_sort' => 'id']);

        $this->assertFalse($invalidColumn->query->has('_sort'));
        $this->assertFalse($invalidDirection->query->has('_sort'));
        $this->assertFalse($invalidStructure->query->has('_sort'));
    }

    public function test_all_reproduced_agent_urls_reach_the_actual_route_without_500(): void
    {
        $urls = [
            'merchant-users?merchant_user_id[]=24',
            'merchant-users?name[]=test',
            'deposit-orders?mid[]=24&status[]=5',
            'deposit-orders?created_at=abc',
            'deposit-orders?created_at[start][]=2026-01-01',
            'transfer-orders?amount[]=100',
            'balance-logs?type[]=1',
            'reports-merchant-agents?date_add=abc',
            'balance-logs?_sort[column]=not_exists&_sort[type]=desc',
        ];
        $domain = (string) config('agent-admin.route.domain');
        $prefix = trim((string) config('agent-admin.route.prefix'), '/');

        foreach ($urls as $url) {
            $fullUrl = ($domain === '' ? '' : 'http://' . $domain) . '/' . $prefix . '/' . $url;
            $this->get($fullUrl)->assertRedirect(admin_url('auth/login'));
        }
    }

    public function test_agent_grid_routes_use_query_normalization(): void
    {
        $prefix = trim((string) config('agent-admin.route.prefix'), '/');
        $route = collect(app('router')->getRoutes()->getRoutes())->first(fn ($route) => $route->uri() === $prefix . '/balance-logs');

        $this->assertNotNull($route);
        $this->assertContains('normalize.agent.grid.query', $route->gatherMiddleware());
    }

    private function runMiddleware(string $resource, array $query): Request
    {
        $prefix = trim((string) config('agent-admin.route.prefix'), '/');
        $request = Request::create('/' . $prefix . '/' . $resource, 'GET', $query);

        return (new NormalizeAgentGridQuery())->handle($request, fn (Request $request) => $request);
    }
}
