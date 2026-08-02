<?php

namespace App\Console\Commands;

use Throwable;
use App\Models\MerchantInfo;
use Illuminate\Console\Command;
use App\Models\MerchantBalanceLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use App\Jobs\MerchantUsdtAveRateJob;
use Illuminate\Support\Facades\Cache;
use App\Services\Common\ReportExceptionService;
use App\Services\Cache\Merchant\CacheMerchantBaseInfoService;

class MerchantAvailableBalanceSettlementCommand extends Command
{
    protected $signature = 'merchant:available-balance-settlement {--log-id= : 只处理指定商户余额流水ID}';

    protected $description = '商户T1，T2结算';

    private const LOCK_KEY = 'MerchantAvailableBalanceSettlementCommand';

    public function handle(): int
    {
        $logId = $this->positiveIntegerOption('log-id');
        if ($logId === false) {
            return self::FAILURE;
        }

        if (!Cache::add(self::LOCK_KEY, 1, now()->addMinutes(10))) {
            $this->warn('商户可用余额结算任务正在执行，本次跳过');
            return self::SUCCESS;
        }

        $handled = 0;
        $skipped = 0;
        $failed = 0;

        try {
            MerchantBalanceLog::query()
                ->select(['id', 'fee', 'amount', 'mid', 'type_id'])
                ->where('status', 0)
                ->where('settlement_time', '>', 0)
                ->where('settlement_time', '<', time())
                ->when($logId !== null, function ($query) use ($logId) {
                    $query->whereKey($logId);
                })
                ->chunkById(1000, function ($logs) use (&$handled, &$skipped, &$failed) {
                    foreach ($logs as $log) {
                        try {
                            if ($this->settleLog($log)) {
                                $handled++;
                            } else {
                                $skipped++;
                            }
                        } catch (Throwable $e) {
                            $failed++;
                            App::make(ReportExceptionService::class)->report('商户T1/T2可用余额结算失败', $e, [
                                'merchant_balance_log_id' => $log->id,
                                'mid' => $log->mid,
                            ]);
                        }
                    }
                });

            $this->info("商户可用余额结算完成，成功：{$handled}，跳过：{$skipped}，失败：{$failed}");

            return $failed > 0 ? self::FAILURE : self::SUCCESS;
        } finally {
            Cache::forget(self::LOCK_KEY);
        }
    }

    private function positiveIntegerOption(string $name): int|false|null
    {
        $value = $this->option($name);
        if ($value === null) {
            return null;
        }

        $value = trim((string)$value);
        if ($value === '' || !ctype_digit($value) || (int)$value <= 0) {
            $this->error('--' . $name . ' 必须是正整数。');
            return false;
        }

        return (int)$value;
    }

    private function settleLog(MerchantBalanceLog $log): bool
    {
        return DB::transaction(function () use ($log) {
            // 先抢占待结算流水，避免并发重复把同一笔金额转入可用余额。
            $claimed = MerchantBalanceLog::query()->whereKey($log->id)->where('status', 0)->update(['status' => 1]);
            if ($claimed <= 0) {
                return false;
            }

            $merchant = MerchantInfo::query()
                ->where('merchant_user_id', $log->mid)
                ->lockForUpdate()
                ->first(['merchant_user_id', 'currency_id', 'available_balance', 'usdt_float_rate', 'default_usdt_ava_rate', 'usdt_ava_rate', 'is_usdt_ava_rate']);

            if (!$merchant) {
                throw new \RuntimeException('商户信息不存在');
            }

            $netAmount = bob_amount_format((float)$log->amount - (float)$log->fee);
            MerchantInfo::query()->where('merchant_user_id', $log->mid)->update([
                'available_balance' => DB::raw("available_balance + ({$netAmount})"),
            ]);

            DB::afterCommit(function () use ($merchant, $log, $netAmount) {
                App::make(CacheMerchantBaseInfoService::class)->excute($merchant->merchant_user_id, true);

                if ((int)$merchant->is_usdt_ava_rate !== 1) {
                    return;
                }

                dispatch(new MerchantUsdtAveRateJob([
                    'merchant_balance_log_id' => $log->id,
                    'mid' => $merchant->merchant_user_id,
                    'n_amount' => $netAmount,
                    'currency_id' => $merchant->currency_id,
                    'usdt_float_rate' => $merchant->usdt_float_rate,
                    'default_usdt_ava_rate' => $merchant->default_usdt_ava_rate,
                    'order_id' => $log->type_id,
                ]))->onQueue('query');
            });

            return true;
        });
    }
}
