<?php

namespace Tests\Feature;

use SplFileInfo;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use App\Services\Telegram\TelegramInstanceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class MaintenanceCommandIdempotencyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_activity_log_sensitive_mask_is_dry_run_safe_actual_idempotent_and_keeps_non_sensitive_data(): void
    {
        $sensitiveId = $this->insertActivityLog([
            'description' => '修改结算订单 密码:secret-value',
            'properties' => json_encode(['nested' => ['appsecret' => 'secret'], 'safe' => 'keep'], JSON_UNESCAPED_UNICODE),
            'request_input' => json_encode(['password' => 'secret', '_token' => 'csrf', 'normal' => 'ok'], JSON_UNESCAPED_UNICODE),
        ]);
        $safeId = $this->insertActivityLog([
            'description' => '普通日志',
            'properties' => json_encode(['safe' => 'keep'], JSON_UNESCAPED_UNICODE),
            'request_input' => json_encode(['normal' => 'ok'], JSON_UNESCAPED_UNICODE),
        ]);
        $nonArrayJsonId = $this->insertActivityLog([
            'description' => '非数组 JSON',
            'properties' => json_encode('not-array', JSON_UNESCAPED_UNICODE),
            'request_input' => json_encode('not-array', JSON_UNESCAPED_UNICODE),
        ]);

        $this->artisan('activity-log:mask-sensitive-data', ['--dry-run' => true])->assertExitCode(0);
        $this->assertStringContainsString('secret-value', DB::table('activity_log')->where('id', $sensitiveId)->value('description'));

        $this->artisan('activity-log:mask-sensitive-data')->assertExitCode(0);
        $sensitive = DB::table('activity_log')->where('id', $sensitiveId)->first();
        $properties = json_decode((string)$sensitive->properties, true);
        $requestInput = json_decode((string)$sensitive->request_input, true);
        $this->assertStringNotContainsString('secret-value', $sensitive->description);
        $this->assertSame('******', $properties['nested']['appsecret']);
        $this->assertSame('******', $requestInput['password']);
        $this->assertSame('******', $requestInput['_token']);
        $this->assertSame('ok', $requestInput['normal']);
        $this->assertSame('普通日志', DB::table('activity_log')->where('id', $safeId)->value('description'));
        $this->assertSame(json_encode('not-array', JSON_UNESCAPED_UNICODE), DB::table('activity_log')->where('id', $nonArrayJsonId)->value('properties'));

        $this->artisan('activity-log:mask-sensitive-data')
            ->expectsOutputToContain('updated=0')
            ->assertExitCode(0);
    }

    public function test_user_login_password_mask_is_dry_run_safe_actual_idempotent_and_keeps_non_sensitive_data(): void
    {
        $sensitiveId = $this->insertActivityLog([
            'log_name' => 'user',
            'log_type' => 'login',
            'description' => '登录失败 | 用户名:demo | 密码:secret-value',
            'properties' => json_encode(['password' => 'secret', 'normal' => 'ok'], JSON_UNESCAPED_UNICODE),
            'request_input' => json_encode(['password' => 'secret', 'captcha' => '1234', 'username' => 'demo'], JSON_UNESCAPED_UNICODE),
        ]);
        $safeId = $this->insertActivityLog([
            'log_name' => 'user',
            'log_type' => 'login',
            'description' => '登录成功',
            'properties' => json_encode(['normal' => 'ok'], JSON_UNESCAPED_UNICODE),
            'request_input' => json_encode(['username' => 'demo'], JSON_UNESCAPED_UNICODE),
        ]);

        $this->artisan('user-login-logs:mask-passwords', ['--dry-run' => true])->assertExitCode(0);
        $this->assertStringContainsString('secret-value', DB::table('activity_log')->where('id', $sensitiveId)->value('description'));

        $this->artisan('user-login-logs:mask-passwords')->assertExitCode(0);
        $sensitive = DB::table('activity_log')->where('id', $sensitiveId)->first();
        $properties = json_decode((string)$sensitive->properties, true);
        $requestInput = json_decode((string)$sensitive->request_input, true);
        $this->assertStringNotContainsString('secret-value', $sensitive->description);
        $this->assertSame('[FILTERED]', $properties['password']);
        $this->assertSame('[FILTERED]', $requestInput['password']);
        $this->assertSame('[FILTERED]', $requestInput['captcha']);
        $this->assertSame('demo', $requestInput['username']);
        $this->assertSame('登录成功', DB::table('activity_log')->where('id', $safeId)->value('description'));

        $this->artisan('user-login-logs:mask-passwords')
            ->expectsOutputToContain('updated=0')
            ->assertExitCode(0);
    }

    public function test_merchant_export_migrate_private_is_dry_run_safe_moves_conflicts_and_is_idempotent(): void
    {
        $type = 'merchant_bank_codes';
        $userId = 'codex_' . str_replace('.', '_', uniqid('', true));
        $sourceRoot = storage_path("app/public/export/{$type}");
        $targetRoot = storage_path("app/export/{$type}");
        $sourceDir = "{$sourceRoot}/{$userId}";
        $targetDir = "{$targetRoot}/{$userId}";
        if (is_dir($sourceDir)) {
            File::deleteDirectory($sourceDir);
        }
        if (is_dir($targetDir)) {
            File::deleteDirectory($targetDir);
        }
        mkdir($sourceDir, 0777, true);
        mkdir($targetDir, 0777, true);

        $sameSource = "{$sourceDir}/same.xlsx";
        $sameTarget = "{$targetDir}/same.xlsx";
        $diffSource = "{$sourceDir}/conflict.xlsx";
        $diffTarget = "{$targetDir}/conflict.xlsx";
        $nonXlsx = "{$sourceDir}/skip.txt";
        file_put_contents($sameSource, 'same-content');
        file_put_contents($sameTarget, 'same-content');
        file_put_contents($diffSource, 'new-content');
        file_put_contents($diffTarget, 'old-content');
        file_put_contents($nonXlsx, 'skip');

        $this->mockMerchantExportFileListing($sourceRoot, [$sameSource, $diffSource, $nonXlsx]);

        $this->artisan('merchant:export-migrate-private', ['--dry-run' => true])->assertExitCode(0);
        $this->assertFileExists($sameSource);
        $this->assertFileExists($diffSource);
        $this->assertFileExists($nonXlsx);

        File::swap(new \Illuminate\Filesystem\Filesystem());
        $this->mockMerchantExportFileListing($sourceRoot, [$sameSource, $diffSource, $nonXlsx]);
        $this->artisan('merchant:export-migrate-private')->assertExitCode(0);
        $this->assertFileDoesNotExist($sameSource);
        $this->assertFileDoesNotExist($diffSource);
        $this->assertFileExists($nonXlsx);
        $this->assertFileExists($sameTarget);
        $this->assertFileExists($diffTarget);
        $this->assertFileExists("{$targetDir}/conflict-migrated-1.xlsx");

        File::swap(new \Illuminate\Filesystem\Filesystem());
        $this->mockMerchantExportFileListing($sourceRoot, []);
        $this->artisan('merchant:export-migrate-private')->assertExitCode(0);
        File::swap(new \Illuminate\Filesystem\Filesystem());
    }

    public function test_failed_jobs_cleanup_dry_run_counts_queues_without_sending_telegram_or_deleting(): void
    {
        if (!Schema::hasTable('failed_jobs')) {
            $this->markTestSkipped('failed_jobs 表不存在。');
        }

        $telegram = new class {
            public int $calls = 0;

            public function excute()
            {
                $this->calls++;
                return $this;
            }

            public function sendMessage(array $payload): void
            {
                $this->calls++;
            }
        };
        $this->app->instance(TelegramInstanceService::class, $telegram);
        $queue = 'codex_queue_' . str_replace('.', '_', uniqid('', true));
        DB::table('failed_jobs')->insert([
            ['uuid' => (string)uniqid('codex_', true), 'connection' => 'redis', 'queue' => $queue, 'payload' => '{}', 'exception' => 'test', 'failed_at' => now()],
            ['uuid' => (string)uniqid('codex_', true), 'connection' => 'redis', 'queue' => $queue, 'payload' => '{}', 'exception' => 'test', 'failed_at' => now()],
        ]);

        $this->artisan('failed-jobs:cleanup', ['--dry-run' => true])
            ->expectsOutputToContain("{$queue}:2")
            ->assertExitCode(0);

        $this->assertSame(0, $telegram->calls);
        $this->assertSame(2, DB::table('failed_jobs')->where('queue', $queue)->count());
    }

    private function insertActivityLog(array $attributes): int
    {
        return (int)DB::table('activity_log')->insertGetId(array_merge([
            'log_name' => 'operation',
            'log_type' => 'operation',
            'description' => 'test',
            'properties' => null,
            'request_input' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    }

    private function mockMerchantExportFileListing(string $sourceRoot, array $files): void
    {
        File::partialMock()
            ->shouldReceive('isDirectory')
            ->andReturnUsing(fn ($path) => $path === $sourceRoot || is_dir($path));
        File::shouldReceive('allFiles')
            ->andReturnUsing(fn ($path) => $path === $sourceRoot ? array_map(fn ($file) => new SplFileInfo($file), $files) : []);
        File::shouldReceive('directories')->andReturn([]);
        File::shouldReceive('files')->andReturn([new SplFileInfo(__FILE__)]);
    }
}
