<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Channel;
use App\Models\AdminRole;
use App\Models\TransferOrder;
use App\Models\AdminAdministrator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Admin\Forms\TransferOrder\BatchTransferActionForm;
use App\Services\TransferOrderLog\CreateTransferOrderLogService;

class AdminBatchTransferActionFormTest extends TestCase
{
    use DatabaseTransactions;

    private object $transferLogService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->createAdminUser(), 'admin');
        $this->transferLogService = new class {
            public array $logs = [];

            public function excute($orderId = 0, $title = '', $content = '', $level = 'info'): void
            {
                $this->logs[] = compact('orderId', 'title', 'content', 'level');
            }
        };
        $this->app->instance(CreateTransferOrderLogService::class, $this->transferLogService);
    }

    public function test_batch_transfer_precheck_failure_keeps_finished_order_remark(): void
    {
        $channel = Channel::query()->forceCreate([
            'name' => 'Codex自营渠道',
            'code' => 'codex_channel_' . uniqid(),
            'status' => 1,
            'classname' => 'CodexPayment',
        ]);
        $first = $this->createTransferOrder(24, 5, 'Codex批量驳回测试');
        $second = $this->createTransferOrder(24, 5, 'Codex批量驳回测试');

        BatchTransferActionForm::make()->handle([
            'channel_id' => $channel->id,
            'mid' => 24,
            'id' => $first->id . ',' . $second->id,
        ]);

        $this->assertCount(2, $this->transferLogService->logs);
        $this->assertSame('批量代付失败', $this->transferLogService->logs[0]['title']);
        $this->assertSame('批量代付失败', $this->transferLogService->logs[1]['title']);
        $this->assertSame('Codex批量驳回测试', TransferOrder::query()->findOrFail($first->id)->remark);
        $this->assertSame('Codex批量驳回测试', TransferOrder::query()->findOrFail($second->id)->remark);
    }

    private function createTransferOrder(int $mid, int $status, string $remark): TransferOrder
    {
        return TransferOrder::query()->create([
            'mid' => $mid,
            'amount' => 88.88,
            'actual_amount' => 88.88,
            'currency_id' => 1,
            'order_no' => 'COD_ORDER_' . uniqid(),
            'ordernumber' => 'T' . date('YmdHis') . mt_rand(100000, 999999),
            'status' => $status,
            'type' => 0,
            'remark' => $remark,
        ]);
    }

    private function createAdminUser(): AdminAdministrator
    {
        $suffix = uniqid('', true);
        $user = AdminAdministrator::query()->create([
            'username' => 'codex_batch_transfer_' . $suffix,
            'password' => Hash::make('codex-password'),
            'name' => 'Codex批量代付管理员',
            'status' => 1,
            'session_id' => 'codex-session-' . $suffix,
        ]);
        $role = AdminRole::query()->create([
            'name' => 'Codex批量代付角色',
            'slug' => 'codex-batch-transfer-role-' . $suffix,
        ]);
        $user->roles()->attach($role->id);
        $user->load('roles.permissions');

        return $user;
    }
}
