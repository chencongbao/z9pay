<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\V2\AgentUserController;
use App\Http\Controllers\Api\V2\UserBankController;

class UserBankDeletePermissionTest extends TestCase
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
    }

    /**
     * @dataProvider forbiddenPermissionProvider
     */
    public function test_self_bank_delete_requires_both_permissions(int $selfAddBank, int $actionDelete): void
    {
        $controller = app(UserBankController::class);

        $this->assertForbidden($controller->destroy(999, $this->request($selfAddBank, $actionDelete)));
        $this->assertForbidden($controller->clearBank($this->request($selfAddBank, $actionDelete)));
    }

    /**
     * @dataProvider forbiddenPermissionProvider
     */
    public function test_team_bank_delete_requires_both_permissions(int $selfAddBank, int $actionDelete): void
    {
        $controller = app(AgentUserController::class);

        $this->assertForbidden($controller->bankDestroy(999, $this->request($selfAddBank, $actionDelete)));
        $this->assertForbidden($controller->clearBank($this->request($selfAddBank, $actionDelete)));
    }

    public function test_forbidden_team_bank_status_change_rolls_back_transaction(): void
    {
        $controller = app(AgentUserController::class);
        $response = $controller->setStatus(999, $this->request(0, 0));

        $this->assertForbidden($response);
        $this->assertSame(0, app('db')->transactionLevel());
    }

    public function forbiddenPermissionProvider(): array
    {
        return [
            'self management disabled' => [0, 1],
            'delete permission disabled' => [1, 0],
            'both permissions disabled' => [0, 0],
        ];
    }

    private function request(int $selfAddBank, int $actionDelete): Request
    {
        $user = new User([
            'id' => 58,
            'self_add_bank' => $selfAddBank,
            'action_delete' => $actionDelete,
        ]);
        $request = Request::create('/api/v2/user-banks', 'DELETE');
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function assertForbidden($response): void
    {
        $payload = $response->getData(true);

        $this->assertSame('非法操作', $payload['message']);
    }
}
