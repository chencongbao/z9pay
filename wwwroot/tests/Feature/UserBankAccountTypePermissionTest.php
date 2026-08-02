<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use App\Http\Requests\Api\V2\UserBankStoreRequest;
use App\Http\Controllers\Api\V2\AgentUserController;
use App\Http\Controllers\Api\V2\UserBankController;

class UserBankAccountTypePermissionTest extends TestCase
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
            $table->string('name')->nullable();
            $table->string('username')->nullable();
            $table->softDeletes();
        });
        Schema::connection('sqlite')->create('user_banks', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->integer('bank_id')->default(0);
            $table->tinyInteger('account_type')->default(1);
            $table->string('name')->nullable();
            $table->string('card_no')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        app('db')->connection('sqlite')->table('users')->insert([
            ['id' => 58, 'pid' => 0, 'is_agent' => 1, 'name' => '代理', 'username' => 'agent'],
            ['id' => 59, 'pid' => 58, 'is_agent' => 0, 'name' => '成员', 'username' => 'member'],
        ]);
    }

    public function test_user_cannot_update_bank_to_unapproved_account_type(): void
    {
        $bankId = $this->createBank(58);
        $response = app(UserBankController::class)->update($bankId, $this->request(58));

        $this->assertRejectedWithoutChangingType($response, $bankId);
    }

    public function test_agent_cannot_update_team_bank_to_unapproved_account_type(): void
    {
        $bankId = $this->createBank(59);
        $response = app(AgentUserController::class)->bankUpdate($bankId, $this->request(58));

        $this->assertRejectedWithoutChangingType($response, $bankId);
    }

    private function request(int $userId): UserBankStoreRequest
    {
        $user = User::query()->findOrFail($userId);
        $user->self_add_bank = 1;
        $user->account_types = '1,2';
        $request = UserBankStoreRequest::create('/api/v2/user-banks/1', 'PUT', [
            'name' => '测试卡',
            'bank_id' => 0,
            'account_type' => 3,
            'card_no' => '',
        ]);
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function createBank(int $userId): int
    {
        return app('db')->connection('sqlite')->table('user_banks')->insertGetId([
            'user_id' => $userId,
            'account_type' => 1,
            'name' => '测试卡',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assertRejectedWithoutChangingType($response, int $bankId): void
    {
        $this->assertSame('非法操作', $response->getData(true)['message']);
        $accountType = app('db')->connection('sqlite')->table('user_banks')->where('id', $bankId)->value('account_type');
        $this->assertSame(1, (int) $accountType);
    }
}
