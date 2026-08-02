<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\UserBank;
use App\Models\AdminRole;
use Illuminate\Http\Request;
use App\Models\AdminAdministrator;
use Illuminate\Support\Facades\Hash;
use Dcat\Admin\Models\Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Admin\Actions\Grid\UserBank\BatchOpenUserBank;
use App\Admin\Actions\Grid\UserBank\BatchCloseUserBank;
use App\Services\Cache\UserBank\GetUserBankListService;
use App\Services\Cache\UserBank\GetUserBankDetailService;

class AdminUserBankBatchCacheTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->createAdminUser(), 'admin');
    }

    public function test_batch_close_and_open_refresh_user_bank_detail_and_list_cache(): void
    {
        $bank = UserBank::query()->create([
            'user_id' => 0,
            'bank_id' => 0,
            'name' => 'Codex收款卡',
            'card_no' => 'COD' . mt_rand(100000, 999999),
            'account_type' => 1,
            'collection_status' => 1,
        ]);
        app(GetUserBankDetailService::class)->excute($bank->id, true);
        app(GetUserBankListService::class)->excute(true);

        $this->runBatchAction(new BatchCloseUserBank(), $bank->id);
        $this->assertSame(0, (int)UserBank::query()->findOrFail($bank->id)->collection_status);
        $this->assertSame(0, (int)app(GetUserBankDetailService::class)->excute($bank->id)['collection_status']);
        $this->assertListCacheContainsStatus($bank->id, 0);

        $this->runBatchAction(new BatchOpenUserBank(), $bank->id);
        $this->assertSame(1, (int)UserBank::query()->findOrFail($bank->id)->collection_status);
        $this->assertSame(1, (int)app(GetUserBankDetailService::class)->excute($bank->id)['collection_status']);
        $this->assertListCacheContainsStatus($bank->id, 1);
    }

    private function runBatchAction(object $action, int $id): void
    {
        $action->setKey([$id]);
        $response = $action->handle(Request::create('/admin/dcat-api/action', 'POST'));

        $this->assertTrue($response->toArray()['status']);
    }

    private function assertListCacheContainsStatus(int $id, int $status): void
    {
        $list = app(GetUserBankListService::class)->excute(false);
        $item = collect($list)->firstWhere('id', $id);

        $this->assertNotNull($item);
        $this->assertSame($status, (int)$item['collection_status']);
    }

    private function createAdminUser(): AdminAdministrator
    {
        $suffix = uniqid('', true);
        $user = AdminAdministrator::query()->create([
            'username' => 'codex_bank_cache_' . $suffix,
            'password' => Hash::make('codex-password'),
            'name' => 'Codex Bank Cache',
            'status' => 1,
            'session_id' => 'codex-session-' . $suffix,
        ]);
        $role = AdminRole::query()->create([
            'name' => 'Codex Bank Cache Role',
            'slug' => 'codex-bank-cache-role-' . $suffix,
        ]);

        foreach (['user-bank-batch-open', 'user-bank-batch-close'] as $slug) {
            $permission = Permission::query()->firstOrCreate(['slug' => $slug], [
                'name' => $slug,
                'http_method' => '',
                'http_path' => '',
                'order' => 0,
                'parent_id' => 0,
            ]);
            $role->permissions()->attach($permission->id);
        }

        $user->roles()->attach($role->id);
        $user->load('roles.permissions');

        return $user;
    }
}
