<?php

namespace App\Console\Commands;

use Throwable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use App\Services\Common\ReportExceptionService;
use App\Extendtions\CountryIpLoaction\Ip2LocationJsonSync;

class FetchIpRangesFromIp2LocationJsonCommand extends Command
{
    protected $signature = 'fetch:ip2location {currency_id=3 : 币种ID} {--force : 跳过确认直接全量覆盖}';

    protected $description = '从 IP2Location JSON 数据集同步国家 IP 段';

    public function handle(): int
    {
        $currencyIdInput = $this->argument('currency_id');
        if (!$this->isPositiveInteger($currencyIdInput)) {
            $this->error('币种ID不合法');
            return self::FAILURE;
        }

        $currencyId = (int)$currencyIdInput;
        if (!$this->currencyExists($currencyId)) {
            $this->error("币种不存在：{$currencyId}");
            return self::FAILURE;
        }

        if (!$this->option('force') && !$this->confirm("该操作会全量覆盖币种 #{$currencyId} 的 IP 段数据，确认继续？")) {
            $this->warn('已取消同步');
            return self::SUCCESS;
        }

        try {
            $result = App::make(Ip2LocationJsonSync::class)->syncCountry($currencyId);
            $this->info('IP2Location 同步完成');
            $this->table(['币种ID', '写入段数', 'IP总数', '数据源'], [[
                $result['country'] ?? $currencyId,
                $result['rows'] ?? 0,
                $result['total_ip'] ?? 0,
                $result['url'] ?? '-',
            ]]);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            App::make(ReportExceptionService::class)->report('IP2Location IP段同步失败', $e, [
                'currency_id' => $currencyId,
            ]);

            return self::FAILURE;
        }
    }

    private function currencyExists(int $currencyId): bool
    {
        return collect(config('default.currency', []))->contains(function ($currency) use ($currencyId) {
            return (int)($currency['id'] ?? 0) === $currencyId;
        });
    }

    private function isPositiveInteger(mixed $value): bool
    {
        if (is_int($value)) {
            return $value > 0;
        }

        if (!is_string($value)) {
            return false;
        }

        return preg_match('/^[1-9]\d*$/', $value) === 1;
    }
}
