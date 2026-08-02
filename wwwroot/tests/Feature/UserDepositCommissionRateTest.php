<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use App\Services\User\GetUserCommisonRateService;

class UserDepositCommissionRateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        ]);
        app('db')->purge('sqlite');

        Schema::connection('sqlite')->create('users', function (Blueprint $table) {
            $table->id();
            $table->decimal('user_rate', 10, 2)->default(0);
            $table->decimal('deposit_user_rate', 10, 2)->default(0);
            $table->text('user_deposit_payment_rate')->nullable();
            $table->softDeletes();
        });
    }

    /**
     * @dataProvider rateProvider
     */
    public function test_deposit_rate_uses_payment_then_deposit_then_default_priority(
        float $defaultRate,
        float $depositRate,
        array $paymentRates,
        int $paymentId,
        float $expected
    ): void {
        $userId = app('db')->table('users')->insertGetId([
            'user_rate' => $defaultRate,
            'deposit_user_rate' => $depositRate,
            'user_deposit_payment_rate' => json_encode($paymentRates),
        ]);

        $rate = app(GetUserCommisonRateService::class)->excute($userId, $paymentId);

        $this->assertEqualsWithDelta($expected, $rate, 0.000001);
    }

    public function rateProvider(): array
    {
        return [
            'default rate' => [1.5, 0, [], 10, 0.015],
            'deposit rate overrides default' => [1.5, 2.5, [], 10, 0.025],
            'payment rate overrides deposit' => [1.5, 2.5, [
                ['payment_id' => 10, 'deposit_user_rate' => 3.5],
            ], 10, 0.035],
            'other payment does not override' => [1.5, 2.5, [
                ['payment_id' => 11, 'deposit_user_rate' => 3.5],
            ], 10, 0.025],
            'zero payment rate does not disable fallback' => [1.5, 2.5, [
                ['payment_id' => 10, 'deposit_user_rate' => 0],
            ], 10, 0.025],
        ];
    }

    public function test_missing_user_has_zero_commission_rate(): void
    {
        $this->assertSame(0.0, app(GetUserCommisonRateService::class)->excute(999, 10));
    }
}
