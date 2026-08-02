<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Admin\Extensions\Layout\LeftSide;
use App\Admin\Extensions\Layout\LeftTreeSide;

class LeftFilterPaginationResetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $route = Route::get('/left-filter-pagination-test', fn () => null);
        $route->name('left-filter-pagination-test');
        Route::getRoutes()->refreshNameLookups();
        $request = Request::create('/left-filter-pagination-test', 'GET', [
            'page' => '4',
            'orders_page' => '3',
            'per_page' => '1',
            'orders_per_page' => '5',
            'created_at' => ['start' => '2026-07-01 00:00:00', 'end' => '2026-07-23 23:59:59'],
            'source_id' => '2',
            'status' => '5',
        ]);
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);
        $this->app->instance('request', $request);
        $this->app['router']->dispatch($request);
    }

    public function test_list_links_reset_plain_and_named_pages_but_keep_other_query_parameters(): void
    {
        $side = new class extends LeftSide {
            public function items(): array
            {
                return $this->lists;
            }
        };
        $side->field('mid')->default(24)->prependAll('All merchants')->data([
            ['id' => 24, 'bname' => 'Merchant 24'],
            ['id' => 27, 'bname' => 'Merchant 27'],
        ]);

        foreach ($side->items() as $item) {
            $query = $this->queryFromUrl($item['url']);
            $this->assertArrayNotHasKey('page', $query);
            $this->assertArrayNotHasKey('orders_page', $query);
            $this->assertPreservedFilters($query);
        }

        $this->assertArrayNotHasKey('mid', $this->queryFromUrl($side->items()[0]['url']));
        $this->assertSame('27', (string) $this->queryFromUrl($side->items()[2]['url'])['mid']);
    }

    public function test_tree_links_and_prepend_all_reset_pages_and_keep_other_query_parameters(): void
    {
        $side = new LeftTreeSide();
        $side->field('agent_id')->default(24)->prependAll('All agents')->data([
            ['id' => 24, 'parentid' => 0, 'text' => 'Agent 24'],
            ['id' => 27, 'parentid' => 24, 'text' => 'Agent 27'],
        ]);

        $links = $this->treeLinks($side->data);
        $this->assertCount(3, $links);

        foreach ($links as $link) {
            $query = $this->queryFromUrl($link);
            $this->assertArrayNotHasKey('page', $query);
            $this->assertArrayNotHasKey('orders_page', $query);
            $this->assertPreservedFilters($query);
        }

        $this->assertArrayNotHasKey('agent_id', $this->queryFromUrl($links[0]));
        $this->assertContains('27', array_map(fn ($link) => (string) ($this->queryFromUrl($link)['agent_id'] ?? ''), $links));
    }

    private function assertPreservedFilters(array $query): void
    {
        $this->assertSame('1', $query['per_page']);
        $this->assertSame('5', $query['orders_per_page']);
        $this->assertSame('2', $query['source_id']);
        $this->assertSame('5', $query['status']);
        $this->assertSame('2026-07-01 00:00:00', $query['created_at']['start']);
        $this->assertSame('2026-07-23 23:59:59', $query['created_at']['end']);
    }

    private function queryFromUrl(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query;
    }

    private function treeLinks(array $nodes): array
    {
        $links = [];

        foreach ($nodes as $node) {
            if (!empty($node['href'])) {
                $links[] = $node['href'];
            }
            if (!empty($node['nodes'])) {
                $links = array_merge($links, $this->treeLinks($node['nodes']));
            }
        }

        return $links;
    }
}
