<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\UserBank;
use App\Models\AdminRole;
use Illuminate\Support\Facades\DB;
use App\Models\AdminAdministrator;
use Illuminate\Support\Facades\Hash;
use Dcat\Admin\Models\Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Admin\Forms\UserBank\CopeForm;
use App\Admin\Forms\UserBank\BatchCopyForm;

class AdminUserBankCopyRuntimeResetTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->createAdminUser(), 'admin');
    }

    public function test_single_copy_resets_balance_and_runtime_statistics(): void
    {
        $source = $this->createRuntimeUserBank('COD_SINGLE_' . uniqid(), 4);
        $response = CopeForm::make()->payload(['id' => $source->id])->handle([
            'payment_id' => 5,
            'collection_status' => 1,
            'limint_min_amount' => 10.12,
            'limint_max_amount' => 999.99,
        ]);

        $this->assertTrue($response->toArray()['status']);
        $copy = $this->copiedBank($source, 5);
        $this->assertCopiedConfiguration($copy, $source, 5, 1, '10.12', '999.99');
        $this->assertRuntimeFieldsReset($copy);
    }

    public function test_batch_copy_resets_balance_and_runtime_statistics(): void
    {
        $first = $this->createRuntimeUserBank('COD_BATCH_A_' . uniqid(), 4);
        $second = $this->createRuntimeUserBank('COD_BATCH_B_' . uniqid(), 4);
        $response = BatchCopyForm::make()->handle([
            'id' => $first->id . ',' . $second->id,
            'payment_id' => 6,
            'collection_status' => 0,
            'limint_min_amount' => 20.22,
            'limint_max_amount' => 888.88,
        ]);

        $this->assertTrue($response->toArray()['status']);

        foreach ([$first, $second] as $source) {
            $copy = $this->copiedBank($source, 6);
            $this->assertCopiedConfiguration($copy, $source, 6, 0, '20.22', '888.88');
            $this->assertRuntimeFieldsReset($copy);
        }
    }

    private function createRuntimeUserBank(string $cardNo, int $paymentId): UserBank
    {
        return UserBank::query()->create([
            'user_id' => 10001,
            'bank_id' => 0,
            'name' => 'Codex复制源卡',
            'card_no' => $cardNo,
            'account_type' => 1,
            'payment_id' => $paymentId,
            'collection_status' => 1,
            'status' => 1,
            'limint_min_amount' => 1.11,
            'limint_max_amount' => 222.22,
            'balance_amount' => 7199.92,
            'doing_status' => 1,
            'last_collection_time' => '2026-07-23 10:11:12',
            'today_stat_date' => '2026-07-23',
            'today_total_amount' => 1234.56,
            'today_total_number' => 7,
            'today_total_income' => 12.34,
        ]);
    }

    private function copiedBank(UserBank $source, int $paymentId): UserBank
    {
        return UserBank::query()
            ->where('card_no', $source->card_no)
            ->where('payment_id', $paymentId)
            ->where('id', '<>', $source->id)
            ->latest('id')
            ->firstOrFail();
    }

    private function assertCopiedConfiguration(UserBank $copy, UserBank $source, int $paymentId, int $collectionStatus, string $minAmount, string $maxAmount): void
    {
        $this->assertSame((int)$source->user_id, (int)$copy->user_id);
        $this->assertSame((string)$source->card_no, (string)$copy->card_no);
        $this->assertSame($paymentId, (int)$copy->payment_id);
        $this->assertSame($collectionStatus, (int)$copy->collection_status);
        $this->assertSame($minAmount, number_format((float)$copy->limint_min_amount, 2, '.', ''));
        $this->assertSame($maxAmount, number_format((float)$copy->limint_max_amount, 2, '.', ''));
    }

    private function assertRuntimeFieldsReset(UserBank $copy): void
    {
        $this->assertSame('0.00', number_format((float)$copy->balance_amount, 2, '.', ''));
        $this->assertSame(0, (int)$copy->doing_status);
        $this->assertNull($copy->last_collection_time);
        $this->assertNull($copy->today_stat_date);
        $this->assertSame('0.00', number_format((float)$copy->today_total_amount, 2, '.', ''));
        $this->assertSame(0, (int)$copy->today_total_number);
        $this->assertSame('0.00', number_format((float)$copy->today_total_income, 2, '.', ''));
        $this->assertSame(0, DB::table('user_bank_balance_logs')->where('user_bank_id', $copy->id)->count());
    }

    private function createAdminUser(): AdminAdministrator
    {
        $suffix = uniqid('', true);
        $user = AdminAdministrator::query()->create([
            'username' => 'codex_copy_bank_' . $suffix,
            'password' => Hash::make('codex-password'),
            'name' => 'Codex Copy Bank',
            'status' => 1,
            'session_id' => 'codex-session-' . $suffix,
        ]);
        $role = AdminRole::query()->create([
            'name' => 'Codex Copy Bank Role',
            'slug' => 'codex-copy-bank-role-' . $suffix,
        ]);

        foreach (['user-bank-copy', 'user-bank-batch-copy'] as $slug) {
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
