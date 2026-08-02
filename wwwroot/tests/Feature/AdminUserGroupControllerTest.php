<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\AdminRole;
use App\Models\UserGroup;
use App\Models\AdminAdministrator;
use Dcat\Admin\Models\Permission as AdminPermission;
use App\Http\Middleware\CheckAdminUserStatus;
use Dcat\Admin\Http\Middleware\Permission as AdminPermissionMiddleware;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class AdminUserGroupControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(CheckAdminUserStatus::class);
        $this->withoutMiddleware(AdminPermissionMiddleware::class);
    }

    public function test_status_quick_update_uses_status_permission(): void
    {
        $this->actingAsAdmin($this->createAdminUser(['user-group-status']));
        $userGroup = $this->createUserGroup(['status' => 1]);

        $response = $this->put($this->adminUrl("group-users/{$userGroup->id}"), [
            'status' => 0,
        ]);

        $this->assertLessThan(400, $response->getStatusCode(), $response->getContent());
        $this->assertSame(0, (int)$userGroup->refresh()->status);
    }

    public function test_status_column_renders_operable_switch_when_user_has_status_permission(): void
    {
        $this->actingAsAdmin($this->createAdminUser(['user-group-status']));
        $this->createUserGroup(['status' => 1]);

        $response = $this->get($this->adminUrl('group-users?status=1&iframe_tab_child=1'));
        $content = $response->assertOk()->getContent();

        $this->assertStringContainsString('class="grid-column-switch"', $content);
        $this->assertStringContainsString('name="status"', $content);
        $this->assertStringContainsString('优先级，从小到大排序', $content);
    }

    public function test_status_column_is_static_without_status_permission(): void
    {
        $this->actingAsAdmin($this->createAdminUser(['user-group-edit']));
        $this->createUserGroup(['status' => 1]);

        $response = $this->get($this->adminUrl('group-users?status=1&iframe_tab_child=1'));
        $content = $response->assertOk()->getContent();

        $this->assertStringNotContainsString('class="grid-column-switch"', $content);
        $this->assertStringContainsString('开启', $content);
    }

    private function createUserGroup(array $attributes = []): UserGroup
    {
        return UserGroup::query()->forceCreate(array_merge([
            'name' => 'Codex User Group ' . uniqid('', true),
            'specialized_merchant_user_ids' => null,
            'merchant_user_ids' => null,
            'extra_user_ids' => null,
            'priority' => 0,
            'status' => 1,
        ], $attributes));
    }

    private function createAdminUser(array $permissionSlugs = ['administrator']): AdminAdministrator
    {
        $suffix = uniqid('', true);
        $user = AdminAdministrator::query()->create([
            'username' => 'codex_user_group_' . $suffix,
            'password' => Hash::make('codex-password'),
            'name' => 'Codex User Group',
            'status' => 1,
            'session_id' => 'codex-session-' . $suffix,
        ]);
        if (in_array('administrator', $permissionSlugs, true)) {
            $role = AdminRole::query()->firstOrCreate(['slug' => 'administrator'], ['name' => 'Administrator']);
        } else {
            $role = AdminRole::query()->create([
                'slug' => 'codex-user-group-role-' . $suffix,
                'name' => 'Codex User Group Role',
            ]);
            $permissionIds = collect($permissionSlugs)->map(function (string $slug) {
                return AdminPermission::query()->firstOrCreate(['slug' => $slug], [
                    'name' => $slug,
                    'http_method' => [],
                    'http_path' => '',
                ])->id;
            })->all();
            $role->permissions()->sync($permissionIds);
        }

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
