<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Console\Kernel;
use Illuminate\Console\Scheduling\Schedule;

class ConsoleScheduleSafetyTest extends TestCase
{
    public function test_sensitive_scheduled_commands_use_overlap_and_single_server_guards(): void
    {
        config(['database-cleanup.enabled' => true, 'database-cleanup.cron' => '0 3 * * *']);
        $schedule = $this->buildSchedule();
        $commands = [
            'merchant:available-balance-settlement',
            'deposit:confirm-timeout',
            'deposit:payment-timeout',
            'transfer:payment-timeout',
            'user:zero-balance-snapshot',
            'merchant:balance-snapshot',
            'user:income-settlement',
            'report',
            'channel:balance-query',
            'failed-jobs:cleanup',
            'images:delete-cashier',
            'database:cleanup',
        ];

        foreach ($commands as $command) {
            $event = $this->findEvent($schedule, $command);
            $this->assertNotNull($event, "Missing schedule command: {$command}");
            $this->assertTrue($event->withoutOverlapping, "{$command} must use withoutOverlapping");
            $this->assertTrue($event->onOneServer, "{$command} must use onOneServer");
        }
    }

    public function test_invalid_database_cleanup_cron_is_not_registered_and_scheduler_does_not_crash(): void
    {
        config(['database-cleanup.enabled' => true, 'database-cleanup.cron' => 'bad cron']);
        $schedule = $this->buildSchedule();

        $this->assertNull($this->findEvent($schedule, 'database:cleanup'));
    }

    private function buildSchedule(): Schedule
    {
        $schedule = app(Schedule::class);
        $method = new \ReflectionMethod(app(Kernel::class), 'schedule');
        $method->setAccessible(true);
        $method->invoke(app(Kernel::class), $schedule);

        return $schedule;
    }

    private function findEvent(Schedule $schedule, string $command)
    {
        foreach ($schedule->events() as $event) {
            if (str_contains((string)$event->command, "artisan' {$command}") || str_contains((string)$event->command, "artisan {$command}")) {
                return $event;
            }
        }

        return null;
    }
}
