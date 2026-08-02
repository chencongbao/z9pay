<?php

namespace Tests\Feature;

use Tests\TestCase;

class SqlSlowReportCommandTest extends TestCase
{
    public function test_slow_report_parses_blocks_fingerprints_and_filters(): void
    {
        $file = $this->writeQueryLog(implode("\n", [
            'duration : 120.5ms',
            'route : admin.users',
            'route_uri : admin/users',
            'source : UserController@index',
            'sql :',
            "select * from users where id = 123 and name = 'bob' and id in (1,2,3)",
            str_repeat('-', 100),
            'duration : 80ms',
            'console : report',
            'source : ReportCommand',
            'sql :',
            "select * from users where id = 456 and name = 'alice' and id in (4,5)",
            str_repeat('-', 100),
            'duration : 10ms',
            'route : admin.orders',
            'sql :',
            'select * from deposit_orders where id = 1',
            str_repeat('-', 100),
            'broken block',
            'sql :',
            'select * from broken',
            '',
            'duration : 200ms',
            'console : artisan queue',
            'source : QueueWorker',
            'sql :',
            'update transfer_orders set status = 1 where id = 99',
        ]));

        $this->artisan('sql:slow-report', [
            '--file' => $file,
            '--min-ms' => '50',
            '--table' => 'users',
            '--route' => 'admin*',
            '--source' => 'User*',
        ])
            ->expectsOutputToContain('SQL 慢日志统计')
            ->expectsOutputToContain('select * from users where id = ? and name = ? and id in (?)')
            ->assertExitCode(0);
    }

    public function test_missing_log_file_returns_failure(): void
    {
        $this->artisan('sql:slow-report', ['--file' => sys_get_temp_dir() . '/codex-missing-query.log'])
            ->expectsOutputToContain('日志文件不存在或不可读')
            ->assertExitCode(1);
    }

    private function writeQueryLog(string $content): string
    {
        $file = tempnam(sys_get_temp_dir(), 'codex-query-');
        file_put_contents($file, $content);

        return $file;
    }
}
