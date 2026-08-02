<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class ReportFakeOrdersCommandTest extends TestCase
{
    public function test_invalid_options_fail_before_database_access(): void
    {
        foreach ($this->invalidOptionCases() as $options) {
            $queries = 0;
            DB::listen(function () use (&$queries) {
                $queries++;
            });

            $this->artisan('report:fake-orders', $options)
                ->assertExitCode(1);

            $this->assertSame(0, $queries);
        }
    }

    public function test_oversized_count_and_chunk_are_rejected(): void
    {
        $this->artisan('report:fake-orders', ['--dry-run' => true, '--count' => '500001'])
            ->expectsOutput('count 不能超过 500000。')
            ->assertExitCode(1);

        $this->artisan('report:fake-orders', ['--dry-run' => true, '--chunk' => '10001'])
            ->expectsOutput('chunk 不能超过 10000。')
            ->assertExitCode(1);
    }

    public function test_valid_dry_run_plan_keeps_existing_behavior(): void
    {
        $this->artisan('report:fake-orders', [
            '--dry-run' => true,
            '--count' => '10',
            '--chunk' => '50',
            '--mid' => '24,25',
            '--date' => '2026-02-28',
            '--type' => 'all',
        ])
            ->expectsOutput('压测日期：2026-02-28')
            ->expectsOutput('商户ID：24,25')
            ->expectsOutput('每批写入：50')
            ->expectsOutput('生成 deposit：5 条')
            ->expectsOutput('生成 transfer：2 条')
            ->expectsOutput('生成 settlement：3 条')
            ->assertExitCode(0);
    }

    private function invalidOptionCases(): array
    {
        return [
            [
                '--dry-run' => true,
                '--count' => 'abc',
                '--chunk' => 'abc',
                '--mid' => 'abc',
                '--date' => 'not-a-date',
                '--type' => 'deposit',
            ],
            [
                '--dry-run' => true,
                '--count' => '0',
                '--chunk' => '0',
                '--mid' => '0,-1,abc',
                '--date' => '2026-02-30',
                '--type' => 'all',
            ],
            ['--dry-run' => true, '--count' => '-1'],
            ['--dry-run' => true, '--count' => '1.5'],
            ['--dry-run' => true, '--chunk' => '-1'],
            ['--dry-run' => true, '--mid' => '24,abc'],
        ];
    }
}
