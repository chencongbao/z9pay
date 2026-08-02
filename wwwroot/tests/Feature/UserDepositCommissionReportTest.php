<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use App\Services\Report\ReportDateBuildService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class UserDepositCommissionReportTest extends TestCase
{
    use DatabaseTransactions;

    public function test_report_build_records_owner_and_each_of_five_agent_commissions(): void
    {
        $merchantIds = DB::table(config('merchant-admin.database.users_table', 'merchant_users'))
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->limit(2)
            ->pluck('id');

        if ($merchantIds->count() < 2) {
            $this->markTestSkipped('需要两个启用的商户来验证跨商户汇总。');
        }

        $date = '2037-12-30';
        $baseUserId = max(
            (int) DB::table('users')->max('id'),
            (int) DB::table('deposit_orders')->max('user_id'),
            (int) DB::table('deposit_orders')->max('user_agent5_id')
        ) + random_int(100000, 500000);
        $ownerId = $baseUserId;
        $agentIds = range($baseUserId + 1, $baseUserId + 5);
        $commissions = [8, 6.4, 4.8, 3.2, 1.6];

        foreach (array_merge([$ownerId], $agentIds) as $index => $userId) {
            DB::table('users')->insert([
                'id' => $userId,
                'username' => 'codex_report_' . $userId . '_' . uniqid(),
                'name' => 'Codex Commission Report',
                'password' => 'secret',
                'is_agent' => $index === 0 ? 0 : 1,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $order = [
            'mid' => $merchantIds[0],
            'user_id' => $ownerId,
            'payment_id' => 1,
            'channel_id' => 1,
            'ordernumber' => 'REPORT-' . uniqid(),
            'order_no' => 'REPORT-MERCHANT-' . uniqid(),
            'amount' => 800,
            'actual_amount' => 800,
            'pay_amount' => 800,
            'show_amount' => 800,
            'merchant_fee' => 24,
            'merchant_extra_fee' => 1,
            'profit' => 10,
            'user_commission' => 20,
            'status' => 5,
            'pay_status' => 2,
            'success_time' => strtotime($date . ' 12:00:00'),
            'created_at' => $date . ' 11:59:00',
            'updated_at' => $date . ' 12:00:00',
        ];
        foreach ($agentIds as $index => $agentId) {
            $level = $index + 1;
            $order["user_agent{$level}_id"] = $agentId;
            $order["user_agent{$level}_commission"] = $commissions[$index];
        }
        DB::table('deposit_orders')->insert($order);
        DB::table('deposit_orders')->insert(array_merge($order, [
            'mid' => $merchantIds[1],
            'ordernumber' => 'REPORT-' . uniqid(),
            'order_no' => 'REPORT-MERCHANT-' . uniqid(),
        ]));

        $service = app(ReportDateBuildService::class);
        $service->buildUsers($date);
        $service->buildUserAgents($date);

        $ownerReport = DB::table('report_users')->where('date_add', $date)->where('uid', $ownerId)->first();
        $this->assertNotNull($ownerReport);
        $this->assertSame(2, (int) $ownerReport->deposit_order_number_total);
        $this->assertSame(2, (int) $ownerReport->deposit_order_number_success);
        $this->assertMoney(1600, $ownerReport->deposit_order_total_amount);
        $this->assertMoney(40, $ownerReport->deposit_commission);
        foreach ($commissions as $index => $commission) {
            $field = ['one', 'two', 'three', 'four', 'five'][$index];
            $this->assertMoney($commission * 2, $ownerReport->{"deposit_{$field}_agent_commission"});
        }

        foreach ($agentIds as $index => $agentId) {
            $agentReport = DB::table('report_user_agents')->where('date_add', $date)->where('aid', $agentId)->first();
            $this->assertNotNull($agentReport);
            $this->assertSame(2, (int) $agentReport->deposit_order_number_total);
            $this->assertMoney(1600, $agentReport->deposit_order_total_amount);
            $this->assertMoney($commissions[$index] * 2, $agentReport->deposit_commission);
        }
    }

    private function assertMoney(float $expected, $actual): void
    {
        $this->assertSame(number_format($expected, 2, '.', ''), number_format((float) $actual, 2, '.', ''));
    }
}
