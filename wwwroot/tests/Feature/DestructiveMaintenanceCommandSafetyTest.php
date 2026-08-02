<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use App\Console\Commands\GenerateApidocumentCommand;
use App\Extendtions\CountryIpLoaction\Ip2LocationJsonSync;

class DestructiveMaintenanceCommandSafetyTest extends TestCase
{
    public function test_update_delete_without_export_is_rejected_before_dropping_tables(): void
    {
        Schema::shouldReceive('dropIfExists')->never();

        $this->artisan('update', ['--delete' => true])
            ->expectsOutput('删除菜单权限表必须同时指定 --export，禁止单独执行 --delete。')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_update_delete_export_without_force_is_rejected_before_dropping_tables(): void
    {
        Schema::shouldReceive('dropIfExists')->never();

        $this->artisan('update', ['--delete' => true, '--export' => true])
            ->expectsOutput('删除菜单权限表属于破坏性操作，必须显式指定 --force。')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_update_export_without_delete_keeps_restore_flow(): void
    {
        Schema::shouldReceive('dropIfExists')->never();
        Schema::shouldReceive('hasTable')->andReturn(true);

        $this->artisan('update', ['--export' => true])
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_apidocument_rejects_unsafe_output_paths_before_generation(): void
    {
        foreach (['/tmp/apidocument-static', '../public', 'public', 'storage', 'build/../../outside'] as $output) {
            $this->artisan('apidocument', ['--output' => $output])
                ->expectsOutputToContain('输出目录')
                ->assertExitCode(Command::FAILURE);
        }
    }

    public function test_apidocument_resolves_legacy_default_into_dedicated_private_root(): void
    {
        $path = $this->resolveApidocumentOutputPath('public/apidocument-static');

        $this->assertStringStartsWith(storage_path('app/apidocument-static') . '/', $path);
        $this->assertStringEndsWith('/build', $path);
    }

    public function test_apidocument_rejects_symlink_escape(): void
    {
        if (!function_exists('symlink')) {
            $this->markTestSkipped('当前环境不支持 symlink。');
        }

        $root = storage_path('app/apidocument-static');
        $link = $root . '/codex-link';
        @mkdir($root, 0777, true);
        @unlink($link);
        symlink(base_path(), $link);

        try {
            $this->resolveApidocumentOutputPath('codex-link/output');
            $this->fail('符号链接越界路径应被拒绝。');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('符号链接', $e->getMessage());
        } finally {
            @unlink($link);
        }
    }

    public function test_fetch_ip2location_rejects_non_decimal_positive_currency_id_before_sync(): void
    {
        $sync = new class {
            public function syncCountry(int $currencyId): array
            {
                throw new \RuntimeException('不应进入同步服务');
            }
        };
        $this->app->instance(Ip2LocationJsonSync::class, $sync);

        foreach (['1abc', '0', '-1', '1.2', ''] as $currencyId) {
            $this->artisan('fetch:ip2location', ['currency_id' => $currencyId, '--force' => true])
                ->expectsOutput('币种ID不合法')
                ->assertExitCode(Command::FAILURE);
        }
    }

    private function resolveApidocumentOutputPath(string $output): string
    {
        $command = new GenerateApidocumentCommand();
        $method = new \ReflectionMethod($command, 'resolveOutputPath');
        $method->setAccessible(true);

        return $method->invoke($command, $output);
    }
}
