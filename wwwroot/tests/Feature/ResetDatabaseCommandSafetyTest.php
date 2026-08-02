<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetDatabaseCommandSafetyTest extends TestCase
{
    public function test_production_refuses_even_with_force_before_sql(): void
    {
        App::detectEnvironment(fn () => 'production');
        DB::shouldReceive('statement')->never();

        $this->artisan('reset:database', ['--force' => true])
            ->expectsOutput('生产环境禁止执行数据库初始化命令')
            ->assertExitCode(1);
    }

    public function test_non_production_cancelled_confirmation_runs_zero_sql(): void
    {
        App::detectEnvironment(fn () => 'testing');
        DB::shouldReceive('statement')->never();

        $this->artisan('reset:database')
            ->expectsConfirmation('该命令会清空业务数据，仅保留部分基础配置，确认继续？', 'no')
            ->expectsOutput('已取消数据库初始化')
            ->assertExitCode(0);
    }

    public function test_non_production_force_uses_fixed_whitelist_and_restores_foreign_key_checks_on_exception(): void
    {
        App::detectEnvironment(fn () => 'testing');
        Schema::shouldReceive('hasTable')->once()->with('activity_log')->andReturn(true);
        DB::shouldReceive('statement')->once()->with('SET FOREIGN_KEY_CHECKS=0');
        DB::shouldReceive('statement')->once()->with('SET FOREIGN_KEY_CHECKS=1');
        DB::shouldReceive('table')->once()->with('activity_log')->andThrow(new \RuntimeException('truncate failed'));

        $this->artisan('reset:database', ['--force' => true])
            ->expectsOutput('数据库初始化失败：truncate failed')
            ->assertExitCode(1);
    }
}
