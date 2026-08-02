<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\User\UserMonthTotalAmountService;

class UserMonthTotalAmountCacheTest extends TestCase
{
    use DatabaseTransactions;

    private array $cacheKeys = [];

    protected function tearDown(): void
    {
        foreach ($this->cacheKeys as $key) {
            Redis::del($key);
        }

        parent::tearDown();
    }

    public function test_success_event_invalidates_cached_month_total_and_read_rebuilds_from_orders(): void
    {
        $userId = $this->createUser();
        $key = $this->cacheKey($userId);
        Redis::setex($key, 86400, 100);

        $result = app(UserMonthTotalAmountService::class)->excute($userId, 50, 0);

        $this->assertSame('0.00', number_format((float) $result, 2, '.', ''));
        $this->assertNull(Redis::get($key));

        $this->createSuccessfulDeposit($userId, 100);
        $this->createSuccessfulDeposit($userId, 50);
        $total = app(UserMonthTotalAmountService::class)->excute($userId, 0, 0);

        $this->assertSame('150.00', number_format((float) $total, 2, '.', ''));
        $this->assertSame('150.00', number_format((float) Redis::get($key), 2, '.', ''));
    }

    public function test_multiple_success_events_cannot_increment_a_database_aggregate_twice(): void
    {
        $userId = $this->createUser();
        $key = $this->cacheKey($userId);
        $this->createSuccessfulDeposit($userId, 100);
        $this->createSuccessfulDeposit($userId, 50);

        app(UserMonthTotalAmountService::class)->excute($userId, 100, 0);
        app(UserMonthTotalAmountService::class)->excute($userId, 50, 0);
        $total = app(UserMonthTotalAmountService::class)->excute($userId, 0, 0);

        $this->assertSame('150.00', number_format((float) $total, 2, '.', ''));
        $this->assertSame('150.00', number_format((float) Redis::get($key), 2, '.', ''));
    }

    public function test_month_total_uses_success_month_instead_of_order_creation_month(): void
    {
        $userId = $this->createUser();
        $key = $this->cacheKey($userId);
        $this->createSuccessfulDeposit($userId, 100, now()->subMonth(), now());
        $this->createSuccessfulDeposit($userId, 999, now(), now()->subMonth());

        $total = app(UserMonthTotalAmountService::class)->excute($userId, 0, 0);

        $this->assertSame('100.00', number_format((float) $total, 2, '.', ''));
        $this->assertSame('100.00', number_format((float) Redis::get($key), 2, '.', ''));
    }

    private function createUser(): int
    {
        $userId = max(
            (int) DB::table('users')->max('id'),
            (int) DB::table('deposit_orders')->max('user_id'),
            (int) DB::table('transfer_orders')->max('user_id')
        ) + random_int(100000, 999999);
        DB::table('users')->insert([
            'id' => $userId,
            'username' => 'codex_month_total_' . uniqid(),
            'name' => 'Codex Month Total',
            'password' => 'secret',
            'is_agent' => 0,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $userId;
    }

    private function createSuccessfulDeposit(int $userId, float $amount, $createdAt = null, $successAt = null): void
    {
        $createdAt = $createdAt ?: now();
        $successAt = $successAt ?: now();
        DB::table('deposit_orders')->insert([
            'mid' => 24,
            'user_id' => $userId,
            'payment_id' => 1,
            'channel_id' => 1,
            'ordernumber' => 'MONTH-' . uniqid(),
            'order_no' => 'MONTH-MERCHANT-' . uniqid(),
            'amount' => $amount,
            'actual_amount' => $amount,
            'pay_amount' => $amount,
            'show_amount' => $amount,
            'status' => 5,
            'pay_status' => 2,
            'success_time' => $successAt->timestamp,
            'created_at' => $createdAt,
            'updated_at' => $successAt,
        ]);
    }

    private function cacheKey(int $userId): string
    {
        $key = CacheConstPrefixService::USER_MONTH_TOTAL_AMOUNT . date('Y_m') . '_' . $userId;
        $this->cacheKeys[] = $key;

        return $key;
    }
}
