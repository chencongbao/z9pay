<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Console\Command;
use App\Services\Api\CheckMerchantExistsService;
use App\Services\SystemNotice\SystemNoticeService;

class SystemNoticeCommandSafetyTest extends TestCase
{
    public function test_invalid_arguments_fail_before_service_calls(): void
    {
        foreach ([
            ['system:notice', ['code' => 'system_manual_notice', 'action' => 'status', '--mid' => '1abc']],
            ['system:notice', ['code' => 'system_manual_notice', 'action' => 'test', '--level' => 'foo']],
            ['system:notice', ['code' => '../bad', 'action' => 'status']],
        ] as [$command, $arguments]) {
            $notice = $this->fakeNoticeService();
            $merchantChecker = $this->fakeMerchantChecker();

            $this->artisan($command, $arguments)->assertExitCode(Command::FAILURE);

            $this->assertSame(0, $notice->calls);
            $this->assertSame(0, $merchantChecker->calls);
        }
    }

    public function test_valid_status_on_off_and_test_call_expected_service_methods(): void
    {
        $notice = $this->fakeNoticeService();
        $merchantChecker = $this->fakeMerchantChecker();

        $this->artisan('system:notice', ['code' => 'system_manual_notice', 'action' => 'status', '--mid' => '24'])
            ->assertExitCode(Command::SUCCESS);
        $this->artisan('system:notice', ['code' => 'system_manual_notice', 'action' => 'on'])
            ->assertExitCode(Command::SUCCESS);
        $this->artisan('system:notice', ['code' => 'system_manual_notice', 'action' => 'off'])
            ->assertExitCode(Command::SUCCESS);
        $this->artisan('system:notice', ['code' => 'system_manual_notice', 'action' => 'test', '--level' => 'error'])
            ->assertExitCode(Command::SUCCESS);

        $this->assertSame(1, $merchantChecker->calls);
        $this->assertSame(['enabled', 'enable', 'disable', 'send'], array_column($notice->records, 'method'));
    }

    private function fakeNoticeService(): object
    {
        $service = new class {
            public int $calls = 0;
            public array $records = [];

            public function enable(string $code, ?int $mid = null): void
            {
                $this->record('enable', compact('code', 'mid'));
            }

            public function disable(string $code, ?int $mid = null): void
            {
                $this->record('disable', compact('code', 'mid'));
            }

            public function enabled(string $code, ?int $mid = null, ?string $level = null): bool
            {
                $this->record('enabled', compact('code', 'mid', 'level'));

                return true;
            }

            public function send(string $code, $message, string $level = 'warning', int $ttlSeconds = 60, ?int $mid = null): bool
            {
                $this->record('send', compact('code', 'message', 'level', 'ttlSeconds', 'mid'));

                return true;
            }

            private function record(string $method, array $payload): void
            {
                $this->calls++;
                $this->records[] = compact('method', 'payload');
            }
        };
        $this->app->instance(SystemNoticeService::class, $service);

        return $service;
    }

    private function fakeMerchantChecker(): object
    {
        $checker = new class {
            public int $calls = 0;

            public function excute(int $mid): bool
            {
                $this->calls++;

                return true;
            }
        };
        $this->app->instance(CheckMerchantExistsService::class, $checker);

        return $checker;
    }
}
