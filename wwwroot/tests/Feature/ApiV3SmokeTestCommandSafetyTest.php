<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\MerchantInfo;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ApiV3SmokeTestCommandSafetyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_default_all_requires_force_before_any_http_request(): void
    {
        $poster = $this->fakePoster();
        $this->createMerchant(24);

        $this->artisan('api:v3-smoke-test')
            ->expectsOutput('当前选择包含写入步骤，请显式指定 --force 后再执行。')
            ->assertExitCode(Command::FAILURE);

        $this->assertCount(0, $poster->requests);
    }

    public function test_readonly_step_uses_configured_api_domain_without_hardcoded_default(): void
    {
        config(['default.api_domain' => 'api.local.test']);
        $poster = $this->fakePoster();
        $this->createMerchant(24);

        $this->artisan('api:v3-smoke-test', ['--only' => 'balance'])
            ->expectsOutput('Base URL: https://api.local.test')
            ->assertExitCode(Command::SUCCESS);

        $this->assertSame('https://api.local.test/api/v3/balance', $poster->requests[0]['url'] ?? '');
    }

    public function test_write_step_without_force_does_not_send_http_request(): void
    {
        $poster = $this->fakePoster();
        $this->createMerchant(24);

        $this->artisan('api:v3-smoke-test', ['--only' => 'deposits'])
            ->expectsOutput('当前选择包含写入步骤，请显式指定 --force 后再执行。')
            ->assertExitCode(Command::FAILURE);

        $this->assertCount(0, $poster->requests);
    }

    public function test_write_step_with_force_sends_to_configured_base_and_notify_url(): void
    {
        config(['default.api_domain' => 'api.local.test']);
        $poster = $this->fakePoster();
        $this->createMerchant(24);

        $this->artisan('api:v3-smoke-test', ['--only' => 'deposits', '--force' => true])
            ->expectsOutput('Base URL: https://api.local.test')
            ->assertExitCode(Command::SUCCESS);

        $this->assertSame('https://api.local.test/api/v3/deposits', $poster->requests[0]['url'] ?? '');
        $this->assertSame('https://api.local.test/cashier/callback/url', $poster->requests[0]['data']['notify_url'] ?? '');
        $this->assertStringNotContainsString('SECRET_FOR_TEST', json_encode($poster->requests[0]['data']));
    }

    public function test_invalid_options_are_rejected_before_http_request(): void
    {
        $cases = [
            ['--only' => 'balance', '--mid' => '1abc'],
            ['--only' => 'balance', '--amount' => '-1'],
            ['--only' => 'balance', '--timeout' => '61'],
            ['--only' => 'transfer-check', '--check-cid' => '1abc'],
            ['--only' => 'balance', '--base-url' => 'ftp://api.local.test'],
        ];

        foreach ($cases as $options) {
            $poster = $this->fakePoster();
            $this->artisan('api:v3-smoke-test', $options)
                ->assertExitCode(Command::FAILURE);
            $this->assertCount(0, $poster->requests);
        }
    }

    private function fakePoster(): object
    {
        $poster = new class {
            public array $requests = [];

            public function __invoke(string $url, array $data, ?string $apiKey, string $appSecret): array
            {
                $this->requests[] = compact('url', 'data', 'apiKey', 'appSecret');

                return [
                    'http_status' => 200,
                    'body' => ['code' => 200, 'message' => 'OK'],
                    'error' => null,
                ];
            }
        };
        $this->app->instance('api_v3_smoke_post', $poster);

        return $poster;
    }

    private function createMerchant(int $mid): MerchantInfo
    {
        return MerchantInfo::query()->updateOrCreate(['merchant_user_id' => $mid], [
            'agent_user_id' => 0,
            'coder' => 'codex_smoke_' . $mid . '_' . uniqid(),
            'appkey' => 'APPKEY_FOR_TEST_' . uniqid(),
            'appsecret' => 'SECRET_FOR_TEST_' . uniqid(),
            'currency_id' => 1,
            'name' => 'Codex Smoke Merchant',
        ]);
    }
}
