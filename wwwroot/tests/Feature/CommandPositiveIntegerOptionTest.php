<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Channel;
use App\Models\MerchantInfo;
use App\Models\MerchantBalanceLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Services\Channel\QueryChannelBalanceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class CommandPositiveIntegerOptionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_invalid_channel_id_does_not_query_channels_or_call_external_service(): void
    {
        foreach (['', 'abc', '1abc', '0', '-1', '1.5'] as $channelId) {
            $service = $this->fakeChannelBalanceService();
            $queries = 0;
            DB::listen(function () use (&$queries) {
                $queries++;
            });

            $this->artisan('channel:balance-query', ['--channel-id' => $channelId])
                ->expectsOutput('--channel-id 必须是正整数。')
                ->assertExitCode(1);

            $this->assertSame(0, $service->calls);
            $this->assertSame(0, $queries);
        }
    }

    public function test_valid_channel_id_keeps_single_channel_behavior(): void
    {
        $service = $this->fakeChannelBalanceService();
        $channel = Channel::query()->forceCreate([
            'name' => 'Codex Channel',
            'code' => 'codex_channel_' . uniqid(),
            'classname' => 'CodexChannel',
            'status' => 1,
        ]);
        Channel::query()->forceCreate([
            'name' => 'Codex Other Channel',
            'code' => 'codex_channel_other_' . uniqid(),
            'classname' => 'CodexOtherChannel',
            'status' => 1,
        ]);

        $this->artisan('channel:balance-query', ['--channel-id' => (string)$channel->id])
            ->expectsOutput('渠道余额查询完成，总数：1，成功：1，跳过：0，失败：0')
            ->assertExitCode(0);

        $this->assertSame(1, $service->calls);
        $this->assertSame([(int)$channel->id], $service->channelIds);
    }

    public function test_invalid_log_id_does_not_lock_query_or_modify_funds(): void
    {
        foreach (['', 'abc', '1abc', '0', '-1', '1.5'] as $logId) {
            $merchant = $this->createMerchant();
            $log = $this->createPendingSettlementLog((int)$merchant->merchant_user_id);
            Cache::forget('MerchantAvailableBalanceSettlementCommand');
            $queries = 0;
            DB::listen(function () use (&$queries) {
                $queries++;
            });

            $this->artisan('merchant:available-balance-settlement', ['--log-id' => $logId])
                ->expectsOutput('--log-id 必须是正整数。')
                ->assertExitCode(1);

            $this->assertFalse(Cache::has('MerchantAvailableBalanceSettlementCommand'));
            $this->assertSame(0, $queries);
            $this->assertSame('100.00', (string)$merchant->refresh()->available_balance);
            $this->assertSame(0, (int)$log->refresh()->status);
        }
    }

    public function test_valid_log_id_keeps_single_log_behavior(): void
    {
        $merchant = $this->createMerchant();
        $targetLog = $this->createPendingSettlementLog((int)$merchant->merchant_user_id, '10.00', '1.00');
        $otherLog = $this->createPendingSettlementLog((int)$merchant->merchant_user_id, '20.00', '2.00');
        Cache::forget('MerchantAvailableBalanceSettlementCommand');

        $this->artisan('merchant:available-balance-settlement', ['--log-id' => (string)$targetLog->id])
            ->expectsOutput('商户可用余额结算完成，成功：1，跳过：0，失败：0')
            ->assertExitCode(0);

        $this->assertSame('109.00', (string)$merchant->refresh()->available_balance);
        $this->assertSame(1, (int)$targetLog->refresh()->status);
        $this->assertSame(0, (int)$otherLog->refresh()->status);
    }

    private function fakeChannelBalanceService(): object
    {
        $service = new class extends QueryChannelBalanceService {
            public int $calls = 0;
            public array $channelIds = [];

            public function supportsBalanceQuery(Channel $channel): bool
            {
                return true;
            }

            public function execute(Channel $channel, bool $useExceptionCooldown = false): array
            {
                $this->calls++;
                $this->channelIds[] = (int)$channel->id;

                return ['status' => true];
            }
        };

        $this->app->instance(QueryChannelBalanceService::class, $service);

        return $service;
    }

    private function createMerchant(): MerchantInfo
    {
        $suffix = uniqid('', true);

        return MerchantInfo::query()->forceCreate([
            'merchant_user_id' => random_int(100000, 999999),
            'name' => 'Codex Merchant',
            'coder' => 'codex_' . str_replace('.', '_', $suffix),
            'appkey' => 'codex_appkey_' . str_replace('.', '_', $suffix),
            'appsecret' => 'codex_appsecret_' . str_replace('.', '_', $suffix),
            'currency_id' => 1,
            'available_balance' => '100.00',
            'usdt_float_rate' => 0,
            'default_usdt_ava_rate' => 0,
            'usdt_ava_rate' => 0,
            'is_usdt_ava_rate' => 0,
        ]);
    }

    private function createPendingSettlementLog(int $mid, string $amount = '10.00', string $fee = '1.00'): MerchantBalanceLog
    {
        return MerchantBalanceLog::query()->forceCreate([
            'mid' => $mid,
            'amount' => $amount,
            'fee' => $fee,
            'type_id' => 0,
            'status' => 0,
            'settlement_time' => time() - 60,
        ]);
    }
}
