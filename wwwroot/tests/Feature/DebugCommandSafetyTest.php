<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Console\Command;
use Richard\Payment\Channel\TraxionPayment;

class DebugCommandSafetyTest extends TestCase
{
    public function test_debug_without_action_only_prints_usage_and_does_not_call_external_payment(): void
    {
        $payment = $this->fakeTraxionPayment();

        $this->artisan('debug')
            ->expectsOutput('请指定明确调试动作，例如：php artisan debug --balance-test --count=4')
            ->assertExitCode(Command::SUCCESS);

        $this->assertSame(0, $payment->calls);
    }

    public function test_debug_rejects_balance_test_in_production_before_external_payment(): void
    {
        $payment = $this->fakeTraxionPayment();
        config(['app.env' => 'production']);

        $this->artisan('debug', ['--balance-test' => true, '--count' => '1'])
            ->expectsOutput('生产环境禁止执行外部调试压测。')
            ->assertExitCode(Command::FAILURE);

        $this->assertSame(0, $payment->calls);
    }

    public function test_debug_rejects_worker_without_balance_test(): void
    {
        $payment = $this->fakeTraxionPayment();

        $this->artisan('debug', ['--worker' => '1'])
            ->expectsOutput('--worker 只能配合 --balance-test 使用。')
            ->assertExitCode(Command::FAILURE);

        $this->assertSame(0, $payment->calls);
    }

    public function test_debug_rejects_invalid_count_before_external_payment(): void
    {
        foreach (['0', '-1', 'abc', '21'] as $count) {
            $payment = $this->fakeTraxionPayment();

            $this->artisan('debug', ['--balance-test' => true, '--count' => $count])
                ->expectsOutput($count === '21' ? '--count 不能超过 20。' : '--count 必须是正整数。')
                ->assertExitCode(Command::FAILURE);

            $this->assertSame(0, $payment->calls);
        }
    }

    public function test_debug_rejects_invalid_worker_before_external_payment(): void
    {
        foreach (['0', '-1', 'abc', '21'] as $worker) {
            $payment = $this->fakeTraxionPayment();

            $this->artisan('debug', ['--balance-test' => true, '--worker' => $worker])
                ->expectsOutput($worker === '21' ? '--worker 不能超过 20。' : '--worker 必须是正整数。')
                ->assertExitCode(Command::FAILURE);

            $this->assertSame(0, $payment->calls);
        }
    }

    public function test_debug_worker_balance_test_uses_mocked_payment_only(): void
    {
        $payment = $this->fakeTraxionPayment();

        $this->artisan('debug', ['--balance-test' => true, '--worker' => '1'])
            ->expectsOutputToContain('#1 ')
            ->assertExitCode(Command::SUCCESS);

        $this->assertSame(1, $payment->calls);
    }

    private function fakeTraxionPayment(): object
    {
        config(['app.env' => 'local']);

        $payment = new class {
            public array $error = [];
            public int $calls = 0;

            public function queryBalance(): string
            {
                $this->calls++;

                return '123.45';
            }
        };

        $this->app->instance(TraxionPayment::class, $payment);
        $this->app->instance('debug.traxion_payment', $payment);

        return $payment;
    }
}
