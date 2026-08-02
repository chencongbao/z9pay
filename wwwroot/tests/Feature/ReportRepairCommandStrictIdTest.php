<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\AgentUser;
use App\Models\MerchantInfo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use App\Jobs\RepairUserReportJob;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Jobs\RepairCurrencyDepositOrderReportJob;
use App\Jobs\RepairMerchantDepositOrderReportJob;
use App\Jobs\Report\HandleReportUserAgentJob;
use App\Jobs\RepairMerchantAgentDepositOrderReportJob;

class ReportRepairCommandStrictIdTest extends TestCase
{
    use DatabaseTransactions;

    public function test_invalid_ids_are_rejected_before_database_query_and_dispatch(): void
    {
        $commands = [
            ['report:repair-merchant', 'mid', '商户ID'],
            ['report:repair-user', 'user_id', '金主ID'],
            ['report:repair-currency', 'currency_id', '币种ID'],
            ['report:repair-merchant-agent', 'user_id', '商户代理ID'],
            ['report:repair-user-agent', 'user_id', '金主代理ID'],
        ];
        $invalidIds = ['0', '-1', '1abc', '1.5', 'abc'];
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });

        foreach ($commands as [$command, $argument, $label]) {
            foreach ($invalidIds as $id) {
                Queue::fake();
                $queries = [];

                $this->artisan($command, [$argument => $id, 'date' => '2026-07-23'])
                    ->expectsOutput("请输入正确的{$label}")
                    ->assertExitCode(1);

                $this->assertSame([], $queries);
                Queue::assertNothingPushed();
            }
        }
    }

    public function test_valid_merchant_report_repair_dispatches_target_job(): void
    {
        Queue::fake();
        $merchant = $this->createMerchant();

        $this->artisan('report:repair-merchant', ['mid' => (string)$merchant->merchant_user_id, 'date' => '2026-07-23'])
            ->expectsOutput("商户报表修复任务已派发，商户ID：{$merchant->merchant_user_id}，日期：2026-07-23")
            ->assertExitCode(0);

        Queue::assertPushedOn('count', RepairMerchantDepositOrderReportJob::class);
        Queue::assertPushed(RepairMerchantDepositOrderReportJob::class, fn ($job) => $job->mid === (int)$merchant->merchant_user_id && $job->date === '2026-07-23');
    }

    public function test_valid_user_report_repair_dispatches_target_job(): void
    {
        Queue::fake();
        $user = $this->createUser(0);

        $this->artisan('report:repair-user', ['user_id' => (string)$user->id, 'date' => '2026-07-23'])
            ->expectsOutput("金主报表修复任务已派发，金主ID：{$user->id}，日期：2026-07-23")
            ->assertExitCode(0);

        Queue::assertPushedOn('count', RepairUserReportJob::class);
        Queue::assertPushed(RepairUserReportJob::class, fn ($job) => $job->user_id === (int)$user->id && $job->date === '2026-07-23');
    }

    public function test_valid_currency_report_repair_dispatches_target_job(): void
    {
        Queue::fake();

        $this->artisan('report:repair-currency', ['currency_id' => '1', 'date' => '2026-07-23'])
            ->expectsOutput('币种报表修复任务已派发，币种ID：1，日期：2026-07-23')
            ->assertExitCode(0);

        Queue::assertPushedOn('count', RepairCurrencyDepositOrderReportJob::class);
        Queue::assertPushed(RepairCurrencyDepositOrderReportJob::class, fn ($job) => $job->currency_id === 1 && $job->date === '2026-07-23');
    }

    public function test_valid_merchant_agent_report_repair_dispatches_target_job(): void
    {
        Queue::fake();
        $agent = $this->createMerchantAgent();

        $this->artisan('report:repair-merchant-agent', ['user_id' => (string)$agent->id, 'date' => '2026-07-23'])
            ->expectsOutput("商户代理报表修复任务已派发，代理ID：{$agent->id}，日期：2026-07-23")
            ->assertExitCode(0);

        Queue::assertPushedOn('count', RepairMerchantAgentDepositOrderReportJob::class);
        Queue::assertPushed(RepairMerchantAgentDepositOrderReportJob::class, fn ($job) => $job->agent_id === (int)$agent->id && $job->date === '2026-07-23');
    }

    public function test_valid_user_agent_report_repair_dispatches_target_job(): void
    {
        Queue::fake();
        $user = $this->createUser(1);

        $this->artisan('report:repair-user-agent', ['user_id' => (string)$user->id, 'date' => '2026-07-23'])
            ->expectsOutput("金主代理报表修复任务已派发，金主代理ID：{$user->id}，日期：2026-07-23")
            ->assertExitCode(0);

        Queue::assertPushedOn('count', HandleReportUserAgentJob::class);
        Queue::assertPushed(HandleReportUserAgentJob::class, fn ($job) => $job->date_add === '2026-07-23');
    }

    private function createMerchant(): MerchantInfo
    {
        $suffix = str_replace('.', '_', uniqid('', true));

        return MerchantInfo::query()->forceCreate([
            'merchant_user_id' => random_int(100000, 999999),
            'agent_user_id' => 0,
            'coder' => 'codex_repair_' . $suffix,
            'appkey' => 'codex_appkey_' . $suffix,
            'appsecret' => 'codex_appsecret_' . $suffix,
            'currency_id' => 1,
            'name' => 'Codex Repair Merchant',
        ]);
    }

    private function createUser(int $isAgent): User
    {
        $suffix = str_replace('.', '_', uniqid('', true));

        return User::query()->forceCreate([
            'username' => 'codex_report_' . $suffix,
            'password' => bcrypt('password'),
            'name' => 'Codex Report User',
            'is_agent' => $isAgent,
            'status' => 1,
        ]);
    }

    private function createMerchantAgent(): AgentUser
    {
        $suffix = str_replace('.', '_', uniqid('', true));

        return AgentUser::query()->forceCreate([
            'username' => 'codex_agent_' . $suffix,
            'password' => bcrypt('password'),
            'name' => 'Codex Agent',
            'status' => 1,
        ]);
    }
}
