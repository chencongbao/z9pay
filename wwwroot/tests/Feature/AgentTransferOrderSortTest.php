<?php

namespace Tests\Feature;

use Tests\TestCase;
use Dcat\Admin\Grid;
use Illuminate\Http\Request;
use App\Models\TransferOrder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class AgentTransferOrderSortTest extends TestCase
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

        Schema::connection('sqlite')->create('transfer_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('type');
        });
        app('db')->connection('sqlite')->table('transfer_orders')->insert([
            ['id' => 1, 'type' => 0],
            ['id' => 2, 'type' => 0],
            ['id' => 3, 'type' => 0],
            ['id' => 4, 'type' => 1],
            ['id' => 5, 'type' => 1],
            ['id' => 6, 'type' => 1],
        ]);
    }

    public function test_transfer_and_settlement_orders_default_to_latest_id_first(): void
    {
        $this->assertControllerUsesDefaultDescendingOrder('TransferOrderController.php');
        $this->assertControllerUsesDefaultDescendingOrder('SettlementOrderController.php');
        $this->assertSame([3, 2, 1], $this->queryIds(0));
        $this->assertSame([6, 5, 4], $this->queryIds(1));
    }

    public function test_explicit_id_ascending_sort_overrides_both_default_orders(): void
    {
        $sort = ['_sort' => ['column' => 'id', 'type' => 'asc']];

        $this->assertSame([1, 2, 3], $this->queryIds(0, $sort));
        $this->assertSame([4, 5, 6], $this->queryIds(1, $sort));
    }

    private function assertControllerUsesDefaultDescendingOrder(string $controller): void
    {
        $source = file_get_contents(app_path("AgentAdmin/Controllers/{$controller}"));

        $this->assertStringContainsString("])->orderBy('id', 'desc');", $source);
    }

    private function queryIds(int $type, array $query = []): array
    {
        $request = Request::create('/agent/orders', 'GET', $query);
        $this->app->instance('request', $request);

        $grid = Grid::make(TransferOrder::query(), function (Grid $grid) use ($type) {
            $grid->model()->where('type', $type)->select(['id', 'type'])->orderBy('id', 'desc');
            $grid->column('id')->sortable();
            $grid->disablePagination();
        });

        return $grid->processFilter()->pluck('id')->all();
    }
}
