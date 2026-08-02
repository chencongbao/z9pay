<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use App\Http\Controllers\Api\V2\UserController;

class TeamBalanceLogOrderSearchTest extends TestCase
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

        Schema::connection('sqlite')->create('user_balance_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->tinyInteger('type');
            $table->integer('type_id')->default(0);
            $table->timestamps();
        });
        app('db')->connection('sqlite')->table('user_balance_logs')->insert([
            'user_id' => 58,
            'type' => 1,
            'type_id' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_invalid_order_number_does_not_return_all_balance_logs(): void
    {
        $agent = new User(['id' => 58]);
        $request = Request::create('/api/v2/users/teamBalanceLogIndex', 'GET', [
            'ordernumber' => 'INVALID-ORDER',
            'time' => 0,
        ]);
        $request->setUserResolver(fn () => $agent);

        $payload = app(UserController::class)->teamBalanceLogIndex($request)->getData(true);

        $this->assertSame([], $payload['data']['lists']['lists']);
        $this->assertSame(0, $payload['data']['lists']['pages']['total']);
    }
}
