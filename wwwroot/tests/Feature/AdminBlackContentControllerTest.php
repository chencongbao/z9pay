<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\AdminRole;
use App\Models\BlackContent;
use App\Models\AdminAdministrator;
use Dcat\Admin\Models\Permission as AdminPermission;
use App\Http\Middleware\CheckAdminUserStatus;
use Dcat\Admin\Http\Middleware\Permission as AdminPermissionMiddleware;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Services\BlackContent\ResetBlackContentCacheService;

class AdminBlackContentControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(CheckAdminUserStatus::class);
        $this->withoutMiddleware(AdminPermissionMiddleware::class);
    }

    public function test_edit_black_content_uses_edit_permission_and_resets_cache(): void
    {
        $this->actingAsAdmin($this->createAdminUser());
        $blackContent = $this->createBlackContent(['content' => '203.0.113.77', 'status' => 1]);
        $cacheReset = $this->mockCacheResetService();

        $response = $this->put($this->adminUrl("black-contents/{$blackContent->id}"), [
            'type' => 1,
            'mid' => 0,
            'content' => '203.0.113.88',
            'status' => 1,
            'remark' => 'Codex edit',
        ]);

        $this->assertLessThan(400, $response->getStatusCode(), $response->getContent());
        $this->assertSame('203.0.113.88', (string)$blackContent->refresh()->content);
        $this->assertSame(1, $cacheReset->calls);
    }

    public function test_status_quick_update_uses_status_permission_and_resets_cache(): void
    {
        $this->actingAsAdmin($this->createAdminUser());
        $blackContent = $this->createBlackContent(['content' => '203.0.113.77', 'status' => 1]);
        $cacheReset = $this->mockCacheResetService();

        $response = $this->put($this->adminUrl("black-contents/{$blackContent->id}"), [
            'status' => 0,
        ]);

        $this->assertLessThan(400, $response->getStatusCode(), $response->getContent());
        $this->assertSame(0, (int)$blackContent->refresh()->status);
        $this->assertSame(1, $cacheReset->calls);
    }

    public function test_status_column_renders_operable_switch_when_user_has_status_permission(): void
    {
        $this->actingAsAdmin($this->createAdminUser(['black-content-status']));
        $this->createBlackContent(['status' => 1]);

        $response = $this->get($this->adminUrl('black-contents?iframe_tab_child=1'));
        $content = $response->assertOk()->getContent();

        $this->assertStringContainsString('class="grid-column-switch"', $content);
        $this->assertStringContainsString('name="status"', $content);
    }

    public function test_status_column_is_static_without_status_permission(): void
    {
        $this->actingAsAdmin($this->createAdminUser(['black-content-edit']));
        $this->createBlackContent(['status' => 1]);

        $response = $this->get($this->adminUrl('black-contents?iframe_tab_child=1'));
        $content = $response->assertOk()->getContent();

        $this->assertStringNotContainsString('class="grid-column-switch"', $content);
        $this->assertStringContainsString('启用', $content);
    }

    private function createBlackContent(array $attributes = []): BlackContent
    {
        return BlackContent::query()->forceCreate(array_merge([
            'type' => 1,
            'mid' => 0,
            'content' => '203.0.113.77',
            'status' => 1,
            'remark' => 'Codex black content',
        ], $attributes));
    }

    private function mockCacheResetService(): object
    {
        $service = new class {
            public int $calls = 0;

            public function excute(): array
            {
                $this->calls++;

                return [];
            }
        };

        app()->instance(ResetBlackContentCacheService::class, $service);

        return $service;
    }

    private function createAdminUser(array $permissionSlugs = ['administrator']): AdminAdministrator
    {
        $suffix = uniqid('', true);
        $user = AdminAdministrator::query()->create([
            'username' => 'codex_black_content_' . $suffix,
            'password' => Hash::make('codex-password'),
            'name' => 'Codex Black Content',
            'status' => 1,
            'session_id' => 'codex-session-' . $suffix,
        ]);
        if (in_array('administrator', $permissionSlugs, true)) {
            $role = AdminRole::query()->firstOrCreate(['slug' => 'administrator'], ['name' => 'Administrator']);
        } else {
            $role = AdminRole::query()->create([
                'slug' => 'codex-black-content-role-' . $suffix,
                'name' => 'Codex Black Content Role',
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
