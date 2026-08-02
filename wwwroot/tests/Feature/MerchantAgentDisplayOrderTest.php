<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\Cache\MerchantAgent\GetMerchantAgentDetailService;

class MerchantAgentDisplayOrderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'agent-admin.database.connection' => 'sqlite',
            'agent-admin.database.users_table' => 'agent_users',
            'cache.default' => 'array',
            'database.connections.sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        ]);
        app('db')->purge('sqlite');
        Cache::clear();

        Schema::connection('sqlite')->create('agent_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pid')->default(0);
            $table->unsignedInteger('level')->default(1);
            $table->string('username');
            $table->string('name');
            $table->unsignedTinyInteger('status')->default(1);
            $table->decimal('balance_amount', 20, 4)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::connection('sqlite')->create('agent_user_relations', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_id');
            $table->unsignedBigInteger('child_id');
            $table->unsignedInteger('level');
        });

        app('db')->connection('sqlite')->table('agent_users')->insert([
            ['id' => 1, 'pid' => 0, 'level' => 1, 'username' => 'top', 'name' => '顶级代理'],
            ['id' => 2, 'pid' => 1, 'level' => 2, 'username' => 'parent', 'name' => '直属上级'],
            ['id' => 3, 'pid' => 2, 'level' => 3, 'username' => 'direct', 'name' => '商户直属代理'],
        ]);
        app('db')->connection('sqlite')->table('agent_user_relations')->insert([
            ['parent_id' => 1, 'child_id' => 1, 'level' => 0],
            ['parent_id' => 2, 'child_id' => 2, 'level' => 0],
            ['parent_id' => 3, 'child_id' => 3, 'level' => 0],
            ['parent_id' => 1, 'child_id' => 2, 'level' => 1],
            ['parent_id' => 2, 'child_id' => 3, 'level' => 1],
            ['parent_id' => 1, 'child_id' => 3, 'level' => 2],
        ]);
    }

    public function test_merchant_agents_are_returned_from_direct_to_top_level(): void
    {
        Cache::put(CacheConstPrefixService::MERCHANT_AGENT_DETAIL . 3, [
            'id' => 3,
            'one' => ['id' => 1],
            'two' => ['id' => 2],
        ]);

        $agent = app(GetMerchantAgentDetailService::class)->excute(3);

        $this->assertSame(3, (int) $agent['id']);
        $this->assertSame(2, (int) $agent['one']['id']);
        $this->assertSame(1, (int) $agent['two']['id']);
    }
}
