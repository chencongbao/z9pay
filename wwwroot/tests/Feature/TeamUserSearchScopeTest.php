<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use App\Http\Controllers\Api\V2\UserController;

class TeamUserSearchScopeTest extends TestCase
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

        Schema::connection('sqlite')->create('users', function (Blueprint $table) {
            $table->id();
            $table->integer('pid')->default(0);
            $table->tinyInteger('is_agent')->default(0);
            $table->tinyInteger('status')->default(1);
            $table->string('username');
            $table->string('name');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function test_keyword_search_cannot_return_user_from_another_team(): void
    {
        app('db')->connection('sqlite')->table('users')->insert([
            'id' => 99,
            'pid' => 77,
            'username' => 'outside-match',
            'name' => '外部成员',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $agent = new User(['id' => 58]);
        $request = Request::create('/api/v2/users/teamUserIndex', 'GET', [
            'username' => 'outside-match',
            'status' => -1,
        ]);
        $request->setUserResolver(fn () => $agent);

        $payload = app(UserController::class)->teamUserIndex($request)->getData(true);

        $this->assertSame([], $payload['data']['lists']['lists']);
        $this->assertSame(0, $payload['data']['lists']['pages']['total']);
    }

    public function test_missing_status_parameter_does_not_add_null_status_filter(): void
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });
        $agent = new User(['id' => 58]);
        $request = Request::create('/api/v2/users/teamUserIndex', 'GET');
        $request->setUserResolver(fn () => $agent);

        app(UserController::class)->teamUserIndex($request);

        $userQueries = array_filter($queries, fn ($sql) => str_contains($sql, 'from "users"'));
        $this->assertNotEmpty($userQueries);
        foreach ($userQueries as $sql) {
            $this->assertStringNotContainsString('"status"', $sql);
        }
    }
}
