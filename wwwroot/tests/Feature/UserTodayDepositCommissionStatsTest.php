<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Services\User\UserTodayDepositStatsService;

class UserTodayDepositCommissionStatsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_owner_and_five_agents_accumulate_their_own_deposit_income(): void
    {
        $ids = [];
        foreach (range(0, 5) as $index) {
            $ids[] = $this->createUser($index > 0);
        }
        $commissions = [20, 8, 6.4, 4.8, 3.2, 1.6];
        $service = app(UserTodayDepositStatsService::class);

        foreach ($ids as $index => $userId) {
            $service->increase($userId, 800, 1, $commissions[$index]);
        }
        $service->increase($ids[0], 200, 1, 5);

        foreach ($ids as $index => $userId) {
            $user = DB::table('users')->find($userId);
            $expectedAmount = $index === 0 ? 1000 : 800;
            $expectedNumber = $index === 0 ? 2 : 1;
            $expectedIncome = $index === 0 ? 25 : $commissions[$index];

            $this->assertSame(date('Y-m-d'), $user->today_deposit_stat_date);
            $this->assertSame($expectedNumber, (int) $user->today_deposit_total_number);
            $this->assertSame(number_format($expectedAmount, 2, '.', ''), number_format((float) $user->today_deposit_total_amount, 2, '.', ''));
            $this->assertSame(number_format($expectedIncome, 2, '.', ''), number_format((float) $user->today_deposit_total_income, 2, '.', ''));
        }
    }

    public function test_first_success_of_new_day_replaces_stale_stats(): void
    {
        $userId = $this->createUser(false);
        DB::table('users')->where('id', $userId)->update([
            'today_deposit_stat_date' => now()->subDay()->toDateString(),
            'today_deposit_total_number' => 99,
            'today_deposit_total_amount' => 9999,
            'today_deposit_total_income' => 999,
        ]);

        app(UserTodayDepositStatsService::class)->increase($userId, 100, 1, 2.5);

        $user = DB::table('users')->find($userId);
        $this->assertSame(date('Y-m-d'), $user->today_deposit_stat_date);
        $this->assertSame(1, (int) $user->today_deposit_total_number);
        $this->assertSame('100.00', number_format((float) $user->today_deposit_total_amount, 2, '.', ''));
        $this->assertSame('2.50', number_format((float) $user->today_deposit_total_income, 2, '.', ''));
    }

    private function createUser(bool $isAgent): int
    {
        return (int) DB::table('users')->insertGetId([
            'username' => 'codex_deposit_stats_' . uniqid(),
            'name' => 'Codex Deposit Stats',
            'password' => 'secret',
            'is_agent' => $isAgent ? 1 : 0,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
