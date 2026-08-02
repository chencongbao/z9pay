<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\MerchantRole;
use App\Models\MerchantUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Http\Middleware\NormalizeMerchantGridQuery;
use App\MerchantAdmin\Controllers\SecureDcatApiController;
use App\MerchantAdmin\Actions\BankCode\ExportData as BankCodeExportData;
use App\MerchantAdmin\Actions\DepositOrder\ExportData as DepositOrderExportData;

class MerchantGridQueryNormalizationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->createMerchantUser(), 'merchant-admin');
        config(['admin.auth.guard' => 'merchant-admin']);
    }

    public function test_malicious_query_is_normalized_before_reaching_merchant_grid_routes(): void
    {
        foreach ($this->merchantGridPaths() as $path) {
            $baseline = $this->get($this->merchantUrl($path));
            $baseline->assertStatus(200);
            $this->assertResponseDoesNotExposeServerError($baseline->getContent(), $path);

            $response = $this->get($this->merchantUrl($path, $this->maliciousQuery()));

            $response->assertStatus(200);
            $this->assertResponseDoesNotExposeServerError($response->getContent(), $path);
        }
    }

    public function test_normalizer_removes_unsafe_values_and_keeps_valid_filters(): void
    {
        $request = Request::create($this->merchantUrl('deposit-orders'), 'GET', [
            'ordernumber' => ['bad' => 'value'],
            'order_no' => 'M202607230001',
            'status' => '5',
            'payment_id' => '2abc',
            'amount_min' => '100.25',
            'amount_max' => 'https://example.com',
            'created_at' => [
                'start' => '2026-07-23 00:00:00',
                'end' => '2026-07-23 23:59:59',
            ],
            'success_time' => [
                'start' => '2026-07-24 00:00:00',
                'end' => '2026-07-23 00:00:00',
            ],
            '_sort' => [
                'column' => 'id',
                'type' => 'desc',
            ],
            'grid__sort' => [
                'column' => ['id'],
                'type' => 'asc',
            ],
        ]);

        app(NormalizeMerchantGridQuery::class)->handle($request, fn (Request $request) => $request);
        $query = $request->query->all();

        $this->assertArrayNotHasKey('ordernumber', $query);
        $this->assertSame('M202607230001', $query['order_no']);
        $this->assertSame('5', $query['status']);
        $this->assertArrayNotHasKey('payment_id', $query);
        $this->assertSame('100.25', $query['amount_min']);
        $this->assertArrayNotHasKey('amount_max', $query);
        $this->assertSame('2026-07-23 00:00:00', $query['created_at']['start']);
        $this->assertSame('2026-07-23 23:59:59', $query['created_at']['end']);
        $this->assertArrayNotHasKey('success_time', $query);
        $this->assertSame(['column' => 'id', 'type' => 'desc'], $query['_sort']);
        $this->assertArrayNotHasKey('grid__sort', $query);
    }

    public function test_merchant_value_request_body_is_normalized_before_metric_handle(): void
    {
        $request = Request::create($this->merchantUrl('dcat-api/value'), 'POST', [
            '_key' => \App\Admin\Metrics\MerchantAdmin\DepositOrder\Card1::class,
            'mid' => '529abc',
            'status' => '5',
            'amount_min' => '88.88',
            'amount_max' => '88.88888',
            'created_at' => [
                'start' => '202https://f-merchant.example.com/-07-27 00:00:00',
                'end' => '2026-07-28 23:59:59',
            ],
            'begin_date' => '2026-07-27 00:00:00',
            'end_date' => 'not-a-date',
        ]);

        $method = new \ReflectionMethod(SecureDcatApiController::class, 'normalizeValueRequest');
        $method->setAccessible(true);
        $method->invoke(app(SecureDcatApiController::class), $request);
        $input = $request->request->all();

        $this->assertArrayNotHasKey('created_at', $input);
        $this->assertSame('2026-07-27 00:00:00', $input['begin_date']);
        $this->assertArrayNotHasKey('end_date', $input);
        $this->assertArrayNotHasKey('mid', $input);
        $this->assertSame('5', $input['status']);
        $this->assertSame('88.88', $input['amount_min']);
        $this->assertArrayNotHasKey('amount_max', $input);
    }

    public function test_async_export_actions_normalize_direct_malicious_query_before_dispatch(): void
    {
        $maliciousDepositQuery = [
            'ordernumber' => ['bad' => 'value'],
            'created_at' => ['start' => ['bad'], 'end' => '2026-07-23 23:59:59'],
            '_sort' => ['column' => 'not_exists', 'type' => 'sideways'],
        ];
        $depositParameters = $this->actionParameters(new DepositOrderExportData(), $maliciousDepositQuery);
        $depositExportParams = $this->exportParams(new DepositOrderExportData(), $maliciousDepositQuery);
        $bankCodeParams = $this->exportParameters(new BankCodeExportData(), [
            'code' => ['bad' => 'value'],
            'name' => '龙江银行',
        ]);

        $this->assertArrayNotHasKey('ordernumber', $depositParameters);
        $this->assertArrayNotHasKey('created_at', $depositParameters);
        $this->assertArrayNotHasKey('_sort', $depositParameters);
        $this->assertArrayNotHasKey('ordernumber', $depositExportParams);
        $this->assertIsArray($depositExportParams['created_at']);
        $this->assertIsString($depositExportParams['created_at']['start']);
        $this->assertIsString($depositExportParams['created_at']['end']);
        $this->assertArrayNotHasKey('_sort', $depositExportParams);
        $this->assertArrayNotHasKey('code', $bankCodeParams);
        $this->assertSame('龙江银行', $bankCodeParams['name']);
    }

    private function merchantGridPaths(): array
    {
        return [
            'deposit-orders',
            'transfer-orders',
            'settlement-orders',
            'balance-logs',
            'bank-codes',
            'report-payments',
            'report-merchants',
            'login-logs',
            'musers',
            'mroles',
        ];
    }

    private function maliciousQuery(): array
    {
        return [
            'id' => ['bad' => 'value'],
            'ordernumber' => ['bad' => 'value'],
            'order_no' => ['bad' => 'value'],
            'status' => ['bad' => 'value'],
            'payment_id' => ['bad' => 'value'],
            'holder_name' => ['bad' => 'value'],
            'type' => ['bad' => 'value'],
            'code' => ['bad' => 'value'],
            'name' => ['bad' => 'value'],
            'source_id' => ['bad' => 'value'],
            'ip' => ['bad' => 'value'],
            'created_at' => ['start' => ['bad' => 'value'], 'end' => 'not-a-date'],
            'success_time' => ['start' => '2026-07-24 00:00:00', 'end' => '2026-07-23 00:00:00'],
            'date_add' => ['start' => ['bad' => 'value'], 'end' => 'not-a-date'],
            '_sort' => ['column' => ['id'], 'type' => 'sideways'],
            'grid__sort' => ['column' => 'not_allowed', 'type' => 'desc'],
            'per_page' => '20',
        ];
    }

    private function merchantUrl(string $path, array $query = []): string
    {
        $domain = (string)config('merchant-admin.route.domain');
        $path = '/' . trim((string)config('merchant-admin.route.prefix'), '/') . '/' . ltrim($path, '/');
        $url = $domain === '' ? $path : 'http://' . $domain . $path;

        return $query ? $url . '?' . http_build_query($query) : $url;
    }

    private function actionParameters(object $action, array $query): array
    {
        request()->query->replace($query);

        $method = new \ReflectionMethod($action, 'parameters');
        $method->setAccessible(true);

        return $method->invoke($action);
    }

    private function exportParameters(object $action, array $query): array
    {
        return $this->actionParameters($action, $query);
    }

    private function exportParams(object $action, array $query): array
    {
        $request = Request::create('/merchant/dcat-api/action', 'POST', $query);
        $method = new \ReflectionMethod($action, 'exportParams');
        $method->setAccessible(true);

        return $method->invoke($action, $request, $this->actingAsMerchantId());
    }

    private function assertResponseDoesNotExposeServerError(string $content, string $path): void
    {
        foreach (['SQLSTATE', 'TypeError', 'Exception'] as $needle) {
            $this->assertStringNotContainsString($needle, $content, "{$path} response should not contain {$needle}.");
        }
    }

    private function actingAsMerchantId(): int
    {
        return (int)auth('merchant-admin')->id();
    }

    private function createMerchantUser(): MerchantUser
    {
        $suffix = uniqid('', true);
        $user = MerchantUser::query()->create([
            'username' => 'codex_grid_' . $suffix,
            'password' => Hash::make('codex-password'),
            'name' => 'Codex Grid Test',
            'status' => 1,
            'pid' => 0,
            'session_id' => 'codex-session-' . $suffix,
        ]);
        $role = MerchantRole::query()->firstOrCreate(['slug' => 'administrator'], [
            'name' => 'Codex Grid Administrator',
            'mid' => 0,
        ]);

        $user->roles()->attach($role->id);
        $user->load('roles.permissions');

        return $user;
    }
}
