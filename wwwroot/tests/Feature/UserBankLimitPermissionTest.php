<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\V2\AgentUserController;
use App\Http\Controllers\Api\V2\UserBankController;

class UserBankLimitPermissionTest extends TestCase
{
    /**
     * @dataProvider permissionProvider
     */
    public function test_limit_fields_follow_action_limit_card_permission(int $permission, bool $shouldKeep): void
    {
        $user = new User(['action_limit_card' => $permission]);
        $request = Request::create('/api/v2/user-banks/1', 'PUT');
        $request->setUserResolver(fn () => $user);

        $data = [
            'name' => '测试银行卡',
            'limint_min_amount' => 100,
            'limint_max_amount' => 500,
            'limint_day_amount' => 1000,
            'limit_day_order_number' => 10,
        ];

        foreach ($this->controllers() as $controller) {
            $result = $controller->filterLimitFields($data, $request);

            $this->assertSame('测试银行卡', $result['name']);
            foreach (['limint_min_amount', 'limint_max_amount', 'limint_day_amount', 'limit_day_order_number'] as $field) {
                $shouldKeep ? $this->assertArrayHasKey($field, $result) : $this->assertArrayNotHasKey($field, $result);
            }
        }
    }

    public function permissionProvider(): array
    {
        return [
            'permission disabled' => [0, false],
            'permission enabled' => [1, true],
        ];
    }

    private function controllers(): array
    {
        return [
            new class extends UserBankController {
                public function filterLimitFields(array $data, Request $request): array
                {
                    return $this->removeLimitFieldsWithoutPermission($data, $request);
                }
            },
            new class extends AgentUserController {
                public function filterLimitFields(array $data, Request $request): array
                {
                    return $this->removeLimitFieldsWithoutPermission($data, $request);
                }
            },
        ];
    }
}
