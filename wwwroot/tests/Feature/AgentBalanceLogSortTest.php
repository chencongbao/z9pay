<?php

namespace Tests\Feature;

use Tests\TestCase;
use Dcat\Admin\Grid;
use Illuminate\Http\Request;
use App\Models\AgentBalanceLog;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class AgentBalanceLogSortTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        ]);
        app('db')->purge('sqlite');

        Schema::connection('sqlite')->create('agent_balance_logs', function (Blueprint $table) {
            $table->id();
            $table->softDeletes();
        });
        app('db')->connection('sqlite')->table('agent_balance_logs')->insert([
            ['id' => 1],
            ['id' => 2],
            ['id' => 3],
        ]);
    }

    public function test_default_balance_log_order_is_latest_id_first(): void
    {
        $this->assertSame([3, 2, 1], $this->queryIds());
    }

    public function test_explicit_id_ascending_sort_overrides_default_order(): void
    {
        $this->assertSame([1, 2, 3], $this->queryIds(['_sort' => ['column' => 'id', 'type' => 'asc']]));
    }

    private function queryIds(array $query = []): array
    {
        $request = Request::create('/agent/balance-logs', 'GET', $query);
        $this->app->instance('request', $request);

        $grid = Grid::make(AgentBalanceLog::query(), function (Grid $grid) {
            $grid->model()->orderBy('id', 'desc');
            $grid->disablePagination();
        });

        return $grid->processFilter()->pluck('id')->all();
    }
}
