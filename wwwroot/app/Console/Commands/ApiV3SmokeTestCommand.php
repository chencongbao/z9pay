<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\MerchantInfo;
use App\Traits\HttpTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ApiV3SmokeTestCommand extends Command
{
    use HttpTrait;

    protected $signature = 'api:v3-smoke-test
        {--mid=24 : Merchant user id}
        {--base-url= : API base URL, for example https://example.com}
        {--only=all : Test target: all,balance,deposits,deposits-query,submit-utr,transfers,transfers-query,transfer-check}
        {--force : Allow write smoke steps}
        {--api-key= : Merchant API key}
        {--app-secret= : Merchant app secret}
        {--amount=100 : Test amount}
        {--deposit-order-no= : Existing deposit merchant order number for deposits-query}
        {--submit-utr-order-no= : Existing deposit merchant order number for submit-utr; default uses deposit-order-no}
        {--utr=1234567890 : UTR value for submit-utr}
        {--transfer-order-no= : Existing transfer merchant order number for transfers-query}
        {--check-cid= : Channel id for transfers/check}
        {--check-app-secret= : Channel app secret for transfers/check; default reads by check-cid}
        {--check-ordernumber= : Platform transfer ordernumber for transfers/check}
        {--gateway=bank : Deposit gateway}
        {--bank-code=test : Transfer bank code}
        {--notify-url= : Notify URL}
        {--ip=127.0.0.1 : Request IP payload}
        {--timeout=15 : HTTP timeout seconds}';

    protected $description = 'Run V3 API smoke tests for balance, deposit, submit UTR, transfer, transfer query, and transfer check.';
    private const WRITE_STEPS = ['deposits', 'submit-utr', 'transfers'];

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('生产环境不允许执行 API V3 冒烟测试命令。');
            return self::FAILURE;
        }

        $only = $this->resolveOnly();
        if ($only === null) {
            $this->error('Invalid --only value. Allowed: all,balance,deposits,deposits-query,submit-utr,transfers,transfers-query,transfer-check');
            return self::FAILURE;
        }

        if ($this->hasWriteStep($only) && !$this->option('force')) {
            $this->error('当前选择包含写入步骤，请显式指定 --force 后再执行。');
            return self::FAILURE;
        }

        if (!$this->validateInputOptions()) {
            return self::FAILURE;
        }

        $onlyTransferCheck = $this->isOnlyTransferCheck();
        $merchant = $onlyTransferCheck ? null : $this->resolveMerchant();
        if (!$onlyTransferCheck && !$merchant) {
            $this->error('Merchant not found. Please pass --mid or configure an active merchant.');
            return self::FAILURE;
        }

        $mid = $merchant ? intval($merchant->merchant_user_id) : 0;
        $apiKey = $merchant ? ($this->option('api-key') ?: $merchant->appkey) : '';
        $appSecret = $merchant ? ($this->option('app-secret') ?: $merchant->appsecret) : '';
        $baseUrl = $this->resolveBaseUrl();
        $amount = $this->option('amount');
        $notifyUrl = $this->resolveNotifyUrl($baseUrl);

        if (!$onlyTransferCheck && (empty($apiKey) || empty($appSecret))) {
            $this->error('Missing api key or app secret.');
            return self::FAILURE;
        }

        $depositOrderNo = $this->option('deposit-order-no') ?: 'DTEST' . date('YmdHis') . mt_rand(1000, 9999);
        $submitUtrOrderNo = $this->option('submit-utr-order-no') ?: $depositOrderNo;
        $transferOrderNo = $this->option('transfer-order-no') ?: 'TTEST' . date('YmdHis') . mt_rand(1000, 9999);
        $transferCheckCid = intval($this->option('check-cid'));
        $transferCheckOrdernumber = $this->option('check-ordernumber');
        $transferCheckAppSecret = $this->resolveTransferCheckAppSecret($transferCheckCid);

        $this->info("Base URL: {$baseUrl}");
        if ($mid > 0) {
            $this->info("Merchant: {$mid}");
        }
        $this->newLine();

        $steps = $this->filterSteps([
            [
                'name' => 'balance',
                'path' => '/api/v3/balance',
                'data' => [
                    'mid' => $mid,
                ],
            ],
            [
                'name' => 'deposits',
                'path' => '/api/v3/deposits',
                'data' => [
                    'name' => 'bob',
                    'mid' => $mid,
                    'amount' => $amount,
                    'order_no' => $depositOrderNo,
                    'gateway' => $this->option('gateway'),
                    'ip' => $this->option('ip'),
                    'notify_url' => $notifyUrl,
                ],
            ],
            [
                'name' => 'deposits/query',
                'path' => '/api/v3/deposits/query',
                'data' => [
                    'mid' => $mid,
                    'order_no' => $depositOrderNo,
                ],
            ],
            [
                'name' => 'submit-utr',
                'path' => '/api/v3/deposits/cashier/utr',
                'data' => [
                    'mid' => $mid,
                    'order_no' => $submitUtrOrderNo,
                    'utr' => $this->option('utr'),
                ],
            ],
            [
                'name' => 'transfers',
                'path' => '/api/v3/transfers',
                'data' => [
                    'mid' => $mid,
                    'amount' => $amount,
                    'order_no' => $transferOrderNo,
                    'ip' => $this->option('ip'),
                    'notify_url' => $notifyUrl,
                    'bank_code' => $this->option('bank-code'),
                    'card_no' => '123456789',
                    'holder_name' => 'TEST',
                ],
            ],
            [
                'name' => 'transfers/query',
                'path' => '/api/v3/transfers/query',
                'data' => [
                    'mid' => $mid,
                    'order_no' => $transferOrderNo,
                ],
            ],
            [
                'name' => 'transfer-check',
                'path' => '/api/v3/transfers/check',
                'api_key' => '',
                'app_secret' => $transferCheckAppSecret,
                'skip' => $this->transferCheckSkipReason($transferCheckCid, $transferCheckOrdernumber, $transferCheckAppSecret),
                'data' => [
                    'cid' => $transferCheckCid,
                    'ordernumber' => $transferCheckOrdernumber,
                    'amount' => $amount,
                ],
            ],
        ]);

        if (empty($steps)) {
            $this->error('Invalid --only value. Allowed: all,balance,deposits,deposits-query,submit-utr,transfers,transfers-query,transfer-check');
            return self::FAILURE;
        }

        $rows = [];
        $hasFailure = false;

        foreach ($steps as $step) {
            if (!empty($step['skip'])) {
                $rows[] = [
                    $step['name'],
                    'SKIP',
                    '-',
                    '-',
                    $step['skip'],
                ];
                $this->line(sprintf('[%s] SKIP: %s', $step['name'], $step['skip']));
                continue;
            }

            $stepApiKey = array_key_exists('api_key', $step) ? $step['api_key'] : $apiKey;
            $stepAppSecret = array_key_exists('app_secret', $step) ? $step['app_secret'] : $appSecret;
            $result = $this->post($baseUrl . $step['path'], $step['data'], $stepApiKey, $stepAppSecret);
            $success = $result['http_status'] >= 200
                && $result['http_status'] < 300
                && intval($result['body']['code'] ?? 0) === 200;

            if (!$success) {
                $hasFailure = true;
            }

            $rows[] = [
                $step['name'],
                $success ? 'OK' : 'FAIL',
                $result['http_status'],
                $result['body']['code'] ?? '-',
                $result['body']['message'] ?? $result['error'] ?? '-',
            ];

            $this->line(sprintf(
                '[%s] %s',
                $step['name'],
                json_encode($result['body'] ?: ['error' => $result['error']], JSON_UNESCAPED_UNICODE)
            ));
        }

        $this->newLine();
        $this->table(['step', 'status', 'http', 'code', 'message'], $rows);
        if ($this->hasStep($steps, 'deposits') || $this->hasStep($steps, 'deposits/query')) {
            $this->line("Deposit order_no: {$depositOrderNo}");
        }
        if ($this->hasStep($steps, 'submit-utr')) {
            $this->line("Submit UTR order_no: {$submitUtrOrderNo}");
        }
        if ($this->hasStep($steps, 'transfers') || $this->hasStep($steps, 'transfers/query')) {
            $this->line("Transfer order_no: {$transferOrderNo}");
        }

        return $hasFailure ? self::FAILURE : self::SUCCESS;
    }

    private function resolveMerchant()
    {
        $mid = (int)$this->option('mid');
        if ($mid > 0) {
            return MerchantInfo::find($mid);
        }

        return MerchantInfo::whereNotNull('appkey')
            ->whereNotNull('appsecret')
            ->orderBy('merchant_user_id', 'desc')
            ->first();
    }

    private function resolveBaseUrl(): string
    {
        $baseUrl = trim((string)$this->option('base-url'));
        if (empty($baseUrl)) {
            $domain = config('default.api_domain') ?: config('app.url');
            $baseUrl = Str::startsWith($domain, ['http://', 'https://']) ? $domain : 'https://' . $domain;
        }

        return rtrim($baseUrl, '/');
    }

    private function resolveNotifyUrl(string $baseUrl): string
    {
        $notifyUrl = trim((string)$this->option('notify-url'));
        if ($notifyUrl !== '') {
            return $notifyUrl;
        }

        return $baseUrl . '/cashier/callback/url';
    }

    private function filterSteps(array $steps): array
    {
        $only = trim(strtolower($this->option('only') ?: 'all'));
        if ($only === 'all') {
            return $steps;
        }

        $aliases = [
            'deposit' => 'deposits',
            'deposit-query' => 'deposits/query',
            'deposits-query' => 'deposits/query',
            'submitutr' => 'submit-utr',
            'submit-utr' => 'submit-utr',
            'utr' => 'submit-utr',
            'transfer' => 'transfers',
            'transfer-query' => 'transfers/query',
            'transfers-query' => 'transfers/query',
            'transfercheck' => 'transfer-check',
            'transfer-check' => 'transfer-check',
            'transfers-check' => 'transfer-check',
        ];

        $targets = array_filter(array_map('trim', explode(',', $only)));
        $targets = array_map(function ($target) use ($aliases) {
            return $aliases[$target] ?? $target;
        }, $targets);

        return array_values(array_filter($steps, function ($step) use ($targets) {
            return in_array($step['name'], $targets, true);
        }));
    }

    private function resolveOnly(): ?array
    {
        $only = trim(strtolower((string)($this->option('only') ?: 'all')));
        $steps = ['balance', 'deposits', 'deposits/query', 'submit-utr', 'transfers', 'transfers/query', 'transfer-check'];

        if ($only === 'all') {
            return $steps;
        }

        $aliases = [
            'deposit' => 'deposits',
            'deposit-query' => 'deposits/query',
            'deposits-query' => 'deposits/query',
            'submitutr' => 'submit-utr',
            'submit-utr' => 'submit-utr',
            'utr' => 'submit-utr',
            'transfer' => 'transfers',
            'transfer-query' => 'transfers/query',
            'transfers-query' => 'transfers/query',
            'transfercheck' => 'transfer-check',
            'transfer-check' => 'transfer-check',
            'transfers-check' => 'transfer-check',
        ];
        $targets = array_values(array_unique(array_filter(array_map('trim', explode(',', $only)))));
        $targets = array_map(fn($target) => $aliases[$target] ?? $target, $targets);

        if (empty($targets) || !empty(array_diff($targets, $steps))) {
            return null;
        }

        return $targets;
    }

    private function hasWriteStep(array $steps): bool
    {
        return !empty(array_intersect($steps, self::WRITE_STEPS));
    }

    private function validateInputOptions(): bool
    {
        if (!$this->isPositiveInteger((string)$this->option('mid'))) {
            $this->error('--mid 必须是正整数。');
            return false;
        }

        if (!$this->isPositiveDecimal((string)$this->option('amount'))) {
            $this->error('--amount 必须是大于0的数字。');
            return false;
        }

        if (!$this->isPositiveInteger((string)$this->option('timeout')) || (int)$this->option('timeout') > 60) {
            $this->error('--timeout 必须是 1-60 的正整数。');
            return false;
        }

        $checkCid = $this->option('check-cid');
        if ($checkCid !== null && $checkCid !== '' && !$this->isPositiveInteger((string)$checkCid)) {
            $this->error('--check-cid 必须是正整数。');
            return false;
        }

        $baseUrl = $this->resolveBaseUrl();
        if (!$this->isSafeHttpUrl($baseUrl) || !$this->isSafeHttpUrl($this->resolveNotifyUrl($baseUrl))) {
            $this->error('URL 必须是合法的 http/https 地址。');
            return false;
        }

        return true;
    }

    private function isPositiveInteger(string $value): bool
    {
        return preg_match('/^[1-9]\d*$/', $value) === 1;
    }

    private function isPositiveDecimal(string $value): bool
    {
        return preg_match('/^(?:[1-9]\d*|0)(?:\.\d{1,2})?$/', $value) === 1 && (float)$value > 0;
    }

    private function isSafeHttpUrl(string $url): bool
    {
        $parts = parse_url($url);

        return is_array($parts)
            && in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true)
            && !empty($parts['host']);
    }

    private function isOnlyTransferCheck(): bool
    {
        $only = trim(strtolower($this->option('only') ?: 'all'));
        if ($only === 'all') {
            return false;
        }

        $targets = array_filter(array_map('trim', explode(',', $only)));
        $targets = array_map(function ($target) {
            return str_replace(['-', '_'], '', $target);
        }, $targets);

        return !empty($targets)
            && count(array_unique($targets)) === 1
            && in_array(current($targets), ['transfercheck', 'transferscheck'], true);
    }

    private function hasStep(array $steps, string $name): bool
    {
        foreach ($steps as $step) {
            if ($step['name'] === $name) {
                return true;
            }
        }

        return false;
    }

    private function post(string $url, array $data, ?string $apiKey, string $appSecret): array
    {
        if (app()->bound('api_v3_smoke_post')) {
            return app('api_v3_smoke_post')($url, $data, $apiKey, $appSecret);
        }

        $data = $this->filterPayload($data);
        $data['sign'] = bob_sign($data, $appSecret);
        $headers = [
            'Content-Type' => 'application/json',
        ];
        if (!empty($apiKey)) {
            $headers['Authorization'] = 'api-key ' . $apiKey;
        }

        try {
            $response = $this->postData($url, $data, [
                'mode' => 'json',
                'header' => $headers,
                'timeout' => intval($this->option('timeout')),
            ]);

            if (!$response) {
                return [
                    'http_status' => 0,
                    'body' => [],
                    'error' => $this->error ?? 'HTTP request failed',
                ];
            }

            return [
                'http_status' => $response->status(),
                'body' => $response->json() ?: [],
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'http_status' => 0,
                'body' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    private function filterPayload(array $data): array
    {
        return array_filter($data, function ($value) {
            return $value !== null && $value !== '';
        });
    }

    private function resolveTransferCheckAppSecret(int $cid): string
    {
        if (!empty($this->option('check-app-secret'))) {
            return (string)$this->option('check-app-secret');
        }

        if ($cid <= 0) {
            return '';
        }

        return (string)(Channel::where('id', $cid)->value('appsecret') ?: '');
    }

    private function transferCheckSkipReason(int $cid, $ordernumber, string $appSecret): string
    {
        if ($cid <= 0) {
            return 'missing --check-cid';
        }

        if (empty($ordernumber)) {
            return 'missing --check-ordernumber';
        }

        if (empty($appSecret)) {
            return 'missing channel appsecret, pass --check-app-secret or check channel config';
        }

        return '';
    }
}
