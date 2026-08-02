<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\MerchantRole;
use App\Models\MerchantUser;
use App\Models\MerchantPermission;
use App\Models\TransferOrder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Queue;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\MerchantAdmin\Form\UpdatePassword;
use App\MerchantAdmin\Actions\BankCode\ExportData as MerchantBankCodeExportData;
use App\MerchantAdmin\Controllers\SecureDcatApiController;
use App\MerchantAdmin\Renderable\BankCode\HistoryExportData as MerchantBankCodeHistoryExportData;

class MerchantDcatApiClassGuardTest extends TestCase
{
    use DatabaseTransactions;

    private MerchantUser $merchantUser;

    protected function setUp(): void
    {
        parent::setUp();

        require_once base_path('tests/Fixtures/MerchantAdmin/FakeFutureClasses.php');
        config(['admin.auth.guard' => 'merchant-admin']);
        $this->merchantUser = $this->createMerchantUser(['administrator']);
        $this->actingAs($this->merchantUser, 'merchant-admin');
    }

    public function test_generic_dcat_api_routes_are_overridden_by_merchant_guard_controller(): void
    {
        $expected = [
            'dcat.merchant-admin.dcat-api.action' => 'action',
            'dcat.merchant-admin.dcat-api.form' => 'form',
            'dcat.merchant-admin.dcat-api.render' => 'render',
            'dcat.merchant-admin.dcat-api.value' => 'value',
        ];

        foreach ($expected as $name => $method) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "Missing route {$name}");
            $this->assertSame(SecureDcatApiController::class . '@' . $method, $route->getActionName());
            if ($method === 'value') {
                $this->assertContains('GET', $route->methods());
            }
        }
    }

    public function test_cross_application_and_vendor_classes_are_rejected_before_execution(): void
    {
        $this->post(route('dcat.merchant-admin.dcat-api.action'), [
            '_action' => $this->calledClass(\App\Admin\Actions\Grid\User\Delete::class),
            '_key' => 1,
        ])->assertForbidden();

        $this->post(route('dcat.merchant-admin.dcat-api.action'), [
            '_action' => $this->calledClass(\App\AgentAdmin\Actions\Dashboard\UpdatePassword::class),
        ])->assertForbidden();

        $this->post(route('dcat.merchant-admin.dcat-api.form'), [
            '_form_' => \App\Admin\Forms\User\ResetPassword::class,
        ])->assertForbidden();

        $this->post(route('dcat.merchant-admin.dcat-api.form'), [
            '_form_' => \App\AgentAdmin\Form\UpdatePassword::class,
        ])->assertForbidden();

        $this->get(route('dcat.merchant-admin.dcat-api.render', [
            'renderable' => $this->calledClass(\App\Admin\Renderable\Channel\BankListJson::class),
        ]))->assertForbidden();

        $this->post(route('dcat.merchant-admin.dcat-api.action'), [
            '_action' => 'App_TeamAdmin_Actions_NoSideEffectProbe',
        ])->assertForbidden();

        $this->post(route('dcat.merchant-admin.dcat-api.form'), [
            '_form_' => \App\Models\MerchantUser::class,
        ])->assertForbidden();

        $this->get($this->valueUrl(\Dcat\Admin\Widgets\Metrics\Card::class))->assertForbidden();

    }

    public function test_existing_prefix_matched_but_not_allowlisted_classes_are_rejected(): void
    {
        $this->post(route('dcat.merchant-admin.dcat-api.action'), [
            '_action' => $this->calledClass(\App\MerchantAdmin\Actions\FakeFutureAction::class),
        ])->assertForbidden();

        $this->post(route('dcat.merchant-admin.dcat-api.form'), [
            '_form_' => \App\MerchantAdmin\Form\FakeFutureForm::class,
        ])->assertForbidden();

        $this->get(route('dcat.merchant-admin.dcat-api.render', [
            'renderable' => $this->calledClass(\App\MerchantAdmin\Renderable\FakeFutureRenderable::class),
        ]))->assertForbidden();
    }

    public function test_low_permission_user_cannot_directly_call_protected_merchant_api_classes(): void
    {
        Queue::fake();
        $this->merchantUser = $this->createMerchantUser([]);
        $this->actingAs($this->merchantUser, 'merchant-admin');

        $child = $this->createMerchantUser([], $this->merchantUser->id);
        $passwordHash = $this->merchantUser->password;
        $orderCount = TransferOrder::query()->count();
        $deleteLogCount = Activity::query()->where('properties->action', 'merchant.user.delete')->count();

        foreach ($this->exportActionClasses() as $class) {
            $this->post(route('dcat.merchant-admin.dcat-api.action'), [
                '_action' => $this->calledClass($class),
            ])->assertForbidden();
        }
        Queue::assertNothingPushed();

        $this->post(route('dcat.merchant-admin.dcat-api.action'), [
            '_action' => $this->calledClass(\App\MerchantAdmin\Actions\User\Delete::class),
            '_key' => $child->id,
        ])->assertForbidden();
        $this->assertFalse((bool)$child->refresh()->deleted_at);
        $this->assertSame($deleteLogCount, Activity::query()->where('properties->action', 'merchant.user.delete')->count());

        $this->post(route('dcat.merchant-admin.dcat-api.form'), [
            '_form_' => UpdatePassword::class,
            'current_login_password' => 'codex-password',
            'password' => 'new-password',
            'password_confirm' => 'new-password',
        ])->assertForbidden();
        $this->assertSame($passwordHash, $this->merchantUser->refresh()->password);

        $this->post(route('dcat.merchant-admin.dcat-api.form'), [
            '_form_' => \App\MerchantAdmin\Form\LookSecrectDetail::class,
            'password' => 'codex-password',
        ])->assertForbidden();

        $this->post(route('dcat.merchant-admin.dcat-api.form'), [
            '_form_' => \App\MerchantAdmin\Form\ResetSecrectDetail::class,
            'password' => 'codex-password',
        ])->assertForbidden();

        $this->post(route('dcat.merchant-admin.dcat-api.form'), [
            '_form_' => \App\MerchantAdmin\Form\SettlementOrder\ApplySettlementOrderForm::class,
            'upload_type' => 0,
            'card_no' => '',
            'holder_name' => '',
            'amount' => 0,
        ])->assertForbidden();
        $this->assertSame($orderCount, TransferOrder::query()->count());

        $this->get(route('dcat.merchant-admin.dcat-api.render', [
            'renderable' => $this->calledClass(MerchantBankCodeHistoryExportData::class),
        ]))->assertForbidden();

        $this->get($this->valueUrl(\App\Admin\Metrics\MerchantAdmin\DepositOrder\Card1::class))->assertOk();
    }

    public function test_allowed_merchant_action_form_and_renderable_still_work(): void
    {
        Queue::fake();
        $this->merchantUser = $this->createMerchantUser([
            'bank-codes',
            'base.auth',
            'merchant_look_secret',
            'merchant_reset_secret',
            'merchant-settlement-order-add',
            'musers',
        ]);
        $this->actingAs($this->merchantUser, 'merchant-admin');
        $child = $this->createMerchantUser([], $this->merchantUser->id);
        $orderCount = TransferOrder::query()->count();

        $this->post(route('dcat.merchant-admin.dcat-api.action'), [
            '_action' => $this->calledClass(MerchantBankCodeExportData::class),
        ])->assertOk()->assertJsonStructure(['status']);

        $this->post(route('dcat.merchant-admin.dcat-api.form'), [
            '_form_' => UpdatePassword::class,
            'current_login_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirm' => 'new-password',
        ])->assertOk()->assertJson(['status' => false]);

        $this->post(route('dcat.merchant-admin.dcat-api.form'), [
            '_form_' => \App\MerchantAdmin\Form\LookSecrectDetail::class,
            'password' => 'wrong-password',
        ])->assertOk()->assertJson(['status' => false]);

        $this->post(route('dcat.merchant-admin.dcat-api.form'), [
            '_form_' => \App\MerchantAdmin\Form\ResetSecrectDetail::class,
            'password' => 'wrong-password',
        ])->assertOk()->assertJson(['status' => false]);

        $this->post(route('dcat.merchant-admin.dcat-api.form'), [
            '_form_' => \App\MerchantAdmin\Form\SettlementOrder\ApplySettlementOrderForm::class,
            'upload_type' => 0,
            'card_no' => '',
            'holder_name' => '',
            'amount' => 0,
        ])->assertStatus(422)->assertJson(['status' => false]);
        $this->assertSame($orderCount, TransferOrder::query()->count());

        $this->post(route('dcat.merchant-admin.dcat-api.action'), [
            '_action' => $this->calledClass(\App\MerchantAdmin\Actions\User\Delete::class),
            '_key' => $child->id,
        ])->assertOk()->assertJson(['status' => true]);
        $this->assertSoftDeleted('merchant_users', ['id' => $child->id]);

        $this->get(route('dcat.merchant-admin.dcat-api.render', [
            'renderable' => $this->calledClass(MerchantBankCodeHistoryExportData::class),
            '_trans_' => '../../admin',
        ]))->assertOk();

        $this->merchantUser = $this->createMerchantUser(['administrator']);
        $this->actingAs($this->merchantUser, 'merchant-admin');
        $this->get($this->valueUrl(\App\Admin\Metrics\MerchantAdmin\DepositOrder\Card1::class))->assertOk();
    }

    private function createMerchantUser(array $permissionSlugs = [], int $pid = 0): MerchantUser
    {
        $suffix = uniqid('', true);
        $user = MerchantUser::query()->create([
            'username' => 'codex_dcat_guard_' . $suffix,
            'password' => Hash::make('codex-password'),
            'name' => 'Codex Dcat Guard',
            'status' => 1,
            'pid' => $pid,
            'session_id' => 'codex-session-' . $suffix,
        ]);

        $role = in_array('administrator', $permissionSlugs, true)
            ? MerchantRole::query()->firstOrCreate(['slug' => 'administrator'], ['name' => 'Administrator', 'mid' => 0])
            : MerchantRole::query()->create(['name' => 'Codex Dcat Guard Role', 'slug' => 'codex-dcat-guard-role-' . $suffix, 'mid' => $pid ?: $user->id]);

        foreach (array_diff($permissionSlugs, ['administrator']) as $slug) {
            $permission = MerchantPermission::query()->firstOrCreate(['slug' => $slug], [
                'name' => $slug,
                'http_method' => '',
                'http_path' => '',
                'order' => 0,
                'parent_id' => 0,
            ]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        $user->roles()->syncWithoutDetaching([$role->id]);
        $user->load('roles.permissions');

        return $user;
    }

    private function calledClass(string $class): string
    {
        return str_replace('\\', '_', $class);
    }

    private function valueUrl(string $class): string
    {
        return route('dcat.merchant-admin.dcat-api.value') . '?' . http_build_query(['_key' => $class]);
    }

    private function exportActionClasses(): array
    {
        return [
            \App\MerchantAdmin\Actions\BankCode\ExportData::class,
            \App\MerchantAdmin\Actions\DepositOrder\ExportData::class,
            \App\MerchantAdmin\Actions\MerchantBalanceLog\ExportData::class,
            \App\MerchantAdmin\Actions\SettlementOrder\ExportData::class,
            \App\MerchantAdmin\Actions\TransferOrder\ExportData::class,
        ];
    }
}
