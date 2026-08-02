<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class DatabaseCleanupCommandSafetyTest extends TestCase
{
    public function test_invalid_numeric_options_are_rejected_before_schema_or_database_cleanup(): void
    {
        config([
            'database-cleanup.enabled' => true,
            'database-cleanup.tables' => [
                'codex_cleanup_items' => [
                    'date_column' => 'created_at',
                    'key_column' => 'id',
                ],
            ],
        ]);
        Schema::shouldReceive('hasTable')->never();

        foreach ([
            ['--months' => 'abc'],
            ['--months' => '1abc'],
            ['--months' => '0'],
            ['--batch' => '0'],
            ['--max-batches' => '-1'],
            ['--max-runtime' => '1.5'],
            ['--sleep-ms' => '-1'],
        ] as $options) {
            $this->artisan('database:cleanup', array_merge(['--dry-run' => true], $options))
                ->assertExitCode(1);
        }
    }

    public function test_valid_dry_run_keeps_data_and_accepts_zero_sleep(): void
    {
        $table = 'codex_cleanup_items';
        Schema::dropIfExists($table);
        Schema::create($table, function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->timestamp('created_at')->nullable();
        });

        try {
            DB::table($table)->insert(['created_at' => now()->subMonths(2)->toDateTimeString()]);
            config([
                'database-cleanup.enabled' => true,
                'database-cleanup.tables' => [
                    $table => [
                        'date_column' => 'created_at',
                        'key_column' => 'id',
                    ],
                ],
            ]);

            $this->artisan('database:cleanup', [
                '--dry-run' => true,
                '--months' => '1',
                '--batch' => '1',
                '--max-batches' => '1',
                '--sleep-ms' => '0',
                '--max-runtime' => '1',
            ])
                ->expectsOutputToContain('dry-run')
                ->assertExitCode(0);

            $this->assertSame(1, DB::table($table)->count());
        } finally {
            Schema::dropIfExists($table);
        }
    }
}
