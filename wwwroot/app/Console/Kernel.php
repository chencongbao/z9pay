<?php

namespace App\Console;

use Cron\CronExpression;
use InvalidArgumentException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {
        // 高频订单状态任务：必须防重叠，避免同一订单被并发处理。
        $schedule->command('merchant:available-balance-settlement')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
        $schedule->command('deposit:confirm-timeout')->everyMinute()->withoutOverlapping()->onOneServer();
        $schedule->command('deposit:payment-timeout')->everyMinute()->withoutOverlapping()->onOneServer();
        $schedule->command('transfer:payment-timeout')->everyMinute()->withoutOverlapping()->onOneServer();
        $schedule->command('report:consume-pending-dates')->everyMinute()->withoutOverlapping()->onOneServer();

        // 日切和结算任务：只允许一台机器执行，避免重复写入。
        $schedule->command('user:zero-balance-snapshot')->dailyAt('00:00')->withoutOverlapping()->onOneServer();
        $schedule->command('merchant:balance-snapshot')->dailyAt('00:01')->withoutOverlapping()->onOneServer();
        $schedule->command('user:rebuild-today-stats')->dailyAt('00:02')->withoutOverlapping()->onOneServer();
        $schedule->command('user:income-settlement')->dailyAt('00:05')->withoutOverlapping()->onOneServer();
        $schedule->command('report')->dailyAt('01:00')->withoutOverlapping()->onOneServer();

        // 运维维护任务：集中放在低峰期执行。
        $schedule->command('failed-jobs:cleanup')->dailyAt('06:00')->withoutOverlapping()->onOneServer();
        $schedule->command('images:delete-cashier')->dailyAt('06:00')->withoutOverlapping()->onOneServer();

        $this->scheduleDatabaseCleanup($schedule);
    }

    protected function scheduleDatabaseCleanup(Schedule $schedule): void
    {
        if (!config('database-cleanup.enabled')) {
            return;
        }

        $cleanupCron = $this->normalizeDatabaseCleanupCron(config('database-cleanup.cron'));
        if (!CronExpression::isValidExpression($cleanupCron)) {
            report(new InvalidArgumentException("Invalid database cleanup cron: {$cleanupCron}"));
            return;
        }

        $schedule->command('database:cleanup')->cron($cleanupCron)->withoutOverlapping()->onOneServer();
    }

    protected function normalizeDatabaseCleanupCron($cron): string
    {
        $cron = trim((string) $cron);
        $cron = trim($cron, "\"'");

        return str_replace(['\/', '\\\\'], ['/', '\\'], $cron);
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
