<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\UserBank;
use App\Models\AdminRole;
use App\Models\AdminAdministrator;
use App\Http\Middleware\CheckAdminUserStatus;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Dcat\Admin\Models\Permission as AdminPermission;
use App\Services\Cache\CacheConstPrefixService;
use Dcat\Admin\Http\Middleware\Permission as AdminPermissionMiddleware;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class AdminUserStatusSwitchTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->withoutMiddleware(CheckAdminUserStatus::class);
        $this->withoutMiddleware(AdminPermissionMiddleware::class);
        $this->actingAsAdmin($this->createAdminUser());
    }

    public function test_user_status_switch_can_close_with_query_parameters(): void
    {
        $user = $this->createUser(['status' => 1, 'acquisition_status' => 1]);

        $response = $this->post($this->adminUrl("tusers/{$user->id}?iframe_tab_child=1"), $this->switchPayload('status', 0));

        $response->assertOk()->assertJson(['status' => true]);
        $this->assertSame(0, (int)$user->refresh()->status);
        $this->assertSame(1, (int)$user->acquisition_status);
    }

    public function test_user_acquisition_status_switch_can_close_with_query_parameters(): void
    {
        $user = $this->createUser(['status' => 1, 'acquisition_status' => 1]);

        $response = $this->post($this->adminUrl("tusers/{$user->id}?iframe_tab_child=1"), $this->switchPayload('acquisition_status', 0));

        $response->assertOk()->assertJson(['status' => true]);
        $this->assertSame(1, (int)$user->refresh()->status);
        $this->assertSame(0, (int)$user->acquisition_status);
    }

    public function test_user_acquisition_status_switch_refreshes_user_list_cache(): void
    {
        $user = $this->createUser(['status' => 1, 'acquisition_status' => 1]);
        Cache::forever(CacheConstPrefixService::USER_LIST, [[
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'status' => 1,
            'acquisition_status' => 1,
            'bname' => $user->bname,
        ]]);

        $this->post($this->adminUrl("tusers/{$user->id}?iframe_tab_child=1"), $this->switchPayload('acquisition_status', 0))
            ->assertOk()
            ->assertJson(['status' => true]);

        $this->assertSame(0, (int)$user->refresh()->acquisition_status);
        $cachedUser = collect(Cache::get(CacheConstPrefixService::USER_LIST))->firstWhere('id', $user->id);
        $this->assertSame(0, (int)($cachedUser['acquisition_status'] ?? 1));
    }

    public function test_user_bank_collection_status_switch_can_close_with_query_parameters(): void
    {
        $user = $this->createUser(['status' => 1, 'acquisition_status' => 1]);
        $bank = UserBank::query()->create([
            'user_id' => $user->id,
            'bank_id' => 0,
            'name' => 'Codex状态开关收款卡',
            'card_no' => 'COD_SWITCH_' . uniqid(),
            'account_type' => 1,
            'payment_id' => 1,
            'status' => 1,
            'collection_status' => 1,
        ]);

        $response = $this->post($this->adminUrl("bank-users/{$bank->id}?iframe_tab_child=1"), $this->switchPayload('collection_status', 0));

        $response->assertOk()->assertJson(['status' => true]);
        $this->assertSame(0, (int)$bank->refresh()->collection_status);
    }

    public function test_user_status_columns_render_operable_switches(): void
    {
        $user = $this->createUser(['status' => 1, 'acquisition_status' => 1]);

        $content = $this->get($this->adminUrl("tusers?id={$user->id}&iframe_tab_child=1"))->assertOk()->getContent();

        $this->assertStringContainsString('class="grid-column-switch"', $content);
        $this->assertStringContainsString('name="status"', $content);
        $this->assertStringContainsString('name="acquisition_status"', $content);
        $this->assertStringContainsString("tusers/{$user->id}", $content);
    }

    public function test_closed_user_status_columns_render_unchecked_switches(): void
    {
        $user = $this->createUser(['status' => 0, 'acquisition_status' => 0]);

        $content = $this->get($this->adminUrl("tusers?id={$user->id}&iframe_tab_child=1"))->assertOk()->getContent();

        $this->assertStringContainsString('name="status"', $content);
        $this->assertStringContainsString('name="acquisition_status"', $content);
        $this->assertDoesNotMatchRegularExpression('/name="status"[^>]*checked/', $content);
        $this->assertDoesNotMatchRegularExpression('/name="acquisition_status"[^>]*checked/', $content);
    }

    public function test_user_bank_collection_status_column_renders_operable_switch(): void
    {
        $user = $this->createUser(['status' => 1, 'acquisition_status' => 1]);
        $bank = UserBank::query()->create([
            'user_id' => $user->id,
            'bank_id' => 0,
            'name' => 'Codex状态开关收款卡',
            'card_no' => 'COD_SWITCH_RENDER_' . uniqid(),
            'account_type' => 1,
            'payment_id' => 1,
            'status' => 1,
            'collection_status' => 1,
        ]);

        $content = $this->get($this->adminUrl("bank-users?id={$bank->id}&iframe_tab_child=1"))->assertOk()->getContent();

        $this->assertStringContainsString('class="grid-column-switch"', $content);
        $this->assertStringContainsString('name="collection_status"', $content);
        $this->assertStringContainsString("bank-users/{$bank->id}", $content);
    }

    public function test_closed_user_bank_collection_status_column_renders_unchecked_switch(): void
    {
        $user = $this->createUser(['status' => 1, 'acquisition_status' => 1]);
        $bank = UserBank::query()->create([
            'user_id' => $user->id,
            'bank_id' => 0,
            'name' => 'Codex状态开关收款卡',
            'card_no' => 'COD_SWITCH_CLOSED_' . uniqid(),
            'account_type' => 1,
            'payment_id' => 1,
            'status' => 1,
            'collection_status' => 0,
        ]);

        $content = $this->get($this->adminUrl("bank-users?id={$bank->id}&iframe_tab_child=1"))->assertOk()->getContent();

        $this->assertStringContainsString('name="collection_status"', $content);
        $this->assertDoesNotMatchRegularExpression('/name="collection_status"[^>]*checked/', $content);
    }

    private function createUser(array $attributes = []): User
    {
        return User::query()->create(array_merge([
            'username' => '139' . mt_rand(10000000, 99999999),
            'password' => Hash::make('codex-password'),
            'name' => 'Codex金主状态测试',
            'mobile' => '139' . mt_rand(10000000, 99999999),
            'is_agent' => 0,
            'status' => 1,
            'acquisition_status' => 1,
        ], $attributes));
    }

    private function switchPayload(string $field, int $value): array
    {
        return [
            $field => $value,
            '_method' => 'PUT',
            '_token' => csrf_token(),
        ];
    }

    private function createAdminUser(): AdminAdministrator
    {
        $suffix = uniqid('', true);
        $user = AdminAdministrator::query()->create([
            'username' => 'codex_user_switch_' . $suffix,
            'password' => Hash::make('codex-password'),
            'name' => 'Codex User Switch',
            'status' => 1,
            'session_id' => 'codex-session-' . $suffix,
        ]);
        $role = AdminRole::query()->create([
            'slug' => 'codex-user-switch-role-' . $suffix,
            'name' => 'Codex User Switch Role',
        ]);
        $permissionIds = collect(['user-status', 'user-acquisition-status', 'user-bank-status'])->map(function (string $slug) {
            return AdminPermission::query()->firstOrCreate(['slug' => $slug], [
                'name' => $slug,
                'http_method' => '',
                'http_path' => '',
                'order' => 0,
                'parent_id' => 0,
            ])->id;
        })->all();

        $role->permissions()->sync($permissionIds);
        $user->roles()->attach($role->id);
        $user->load('roles.permissions');

        return $user;
    }

    private function actingAsAdmin(AdminAdministrator $user): void
    {
        $this->actingAs($user, 'admin');
        $user->forceFill(['session_id' => session()->getId()])->save();
        $user->refresh()->load('roles.permissions');
    }

    private function adminUrl(string $path): string
    {
        return 'http://' . config('admin.route.domain') . '/' . trim(config('admin.route.prefix'), '/') . '/' . ltrim($path, '/');
    }
}
