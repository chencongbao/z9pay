<?php

namespace App\Jobs;

use App\Models\DepositOrder;
use App\Models\MerchantInfo;
use Illuminate\Bus\Queueable;
use App\Models\MerchantAvgUsdtLog;
use App\Models\MerchantBalanceLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\DepositOrder\GetUsdtCurrencyRateService;

class MerchantUsdtAveRateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $data = [];

    public $scale = 2;

    public $usdtScale = 6;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data = [])
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if($this->data['n_amount'] > 0){
            $base = App::make(GetUsdtCurrencyRateService::class)->excute($this->data['currency_id'], $this->data['mid']);
            if ($base) {
                $new_rate = $base + floatval($this->data['usdt_float_rate']);
            } else {
                $new_rate = $this->data['default_usdt_ava_rate'] + floatval($this->data['usdt_float_rate']);
                app(\App\Services\SystemNotice\SystemNoticeService::class)->warning("system_manual_notice", [
                    'error'  => "获取实时费率异常",
                    'result' => $base,
                    'data'   => $this->data,
                ]);
            }

            DB::transaction(function () use ($new_rate) {
                $merchant = MerchantInfo::where('merchant_user_id', $this->data['mid'])->lockForUpdate()->first(['merchant_user_id', 'available_balance', 'usdt_ava_rate',"available_usdt_balance"]);
                if (!$merchant) {
                    return;
                }
                $new_rate = $this->decimalRate($new_rate);
                if (bccomp($new_rate, '0', $this->usdtScale) <= 0) {
                    $this->reportInvalidRate('USDT实时费率无效，充值无法计算', $merchant, $new_rate);
                    return;
                }
                $delta      = (string)$this->data['n_amount'];
                $order_id   = (int)$this->data['order_id'];
                $order_usdt = bcdiv($delta, $new_rate, $this->usdtScale);
                MerchantInfo::where('merchant_user_id', $merchant->merchant_user_id)->increment('available_usdt_balance', $order_usdt);
                $merchant->refresh();
                $this->syncMerchantUsdtState($merchant, $new_rate);
                $merchant->refresh();
                MerchantBalanceLog::where('id',$this->data['merchant_balance_log_id'])->update(['usdt_rate' => $new_rate,'usdt_amount'=>$order_usdt,'usdt_balance_amount'=>$merchant->available_usdt_balance]);
                if($order_id > 0){
                    DepositOrder::where('id', $this->data['order_id'])->update(['usdt_rate' => $new_rate]);
                }
            });
        }else{
            DB::transaction(function (){
                $merchant = MerchantInfo::where('merchant_user_id', $this->data['mid'])->lockForUpdate()->first(['merchant_user_id', 'available_balance', 'usdt_ava_rate',"available_usdt_balance"]);
                if (!$merchant) {
                    return;
                }
                $delta      = (string)abs($this->data['n_amount']);
                $avgRate = $this->effectiveAvgRate($merchant, $delta);
                if (bccomp($avgRate, '0', $this->usdtScale) <= 0) {
                    $this->reportInvalidRate('USDT平均费率无效，减项无法计算', $merchant, $avgRate);
                    return;
                }
                $order_usdt = bcdiv($delta, $avgRate, $this->usdtScale);
                if(bccomp((string)$merchant->available_balance, '0', 2) <= 0){
                    MerchantInfo::where('merchant_user_id', $merchant->merchant_user_id)->update(['available_usdt_balance' => 0, 'usdt_ava_rate' => 0]);
                }else{
                    MerchantInfo::where('merchant_user_id', $merchant->merchant_user_id)->decrement('available_usdt_balance', $order_usdt);
                }
                $merchant->refresh();
                $this->syncMerchantUsdtState($merchant, $avgRate);
                $merchant->refresh();
                MerchantBalanceLog::where('id',$this->data['merchant_balance_log_id'])->update(['usdt_rate' => $avgRate,'usdt_amount'=>bcmul($order_usdt, '-1', $this->usdtScale),'usdt_balance_amount'=>$merchant->available_usdt_balance]);
            });
        }

    }

    private function effectiveAvgRate($merchant, string $deductAmount = '0'): string
    {
        $balanceForRate = bcadd((string)$merchant->available_balance, $deductAmount, 2);
        if (
            bccomp($balanceForRate, '0', 2) > 0 &&
            bccomp((string)$merchant->available_usdt_balance, '0', $this->usdtScale) > 0
        ) {
            return $this->decimalRate(bcdiv($balanceForRate, (string)$merchant->available_usdt_balance, $this->usdtScale));
        }

        if (bccomp((string)$merchant->usdt_ava_rate, '0', $this->usdtScale) > 0) {
            return $this->decimalRate($merchant->usdt_ava_rate);
        }

        return $this->defaultRate();
    }

    private function syncMerchantUsdtState($merchant, string $fallbackRate): void
    {
        $merchantId = $merchant->merchant_user_id;
        $availableBalance = (string)$merchant->available_balance;
        $availableUsdtBalance = (string)$merchant->available_usdt_balance;

        if (bccomp($availableBalance, '0', 2) <= 0) {
            MerchantInfo::where('merchant_user_id', $merchantId)->update([
                'available_usdt_balance' => 0,
                'usdt_ava_rate' => 0,
            ]);
            return;
        }

        if (bccomp($availableUsdtBalance, '0', $this->usdtScale) <= 0) {
            if (bccomp($fallbackRate, '0', $this->usdtScale) <= 0) {
                return;
            }
            MerchantInfo::where('merchant_user_id', $merchantId)->update([
                'available_usdt_balance' => bcdiv($availableBalance, $fallbackRate, $this->usdtScale),
                'usdt_ava_rate' => round($fallbackRate, 4),
            ]);
            return;
        }

        MerchantInfo::where('merchant_user_id', $merchantId)->update([
            'usdt_ava_rate' => round(bcdiv($availableBalance, $availableUsdtBalance, $this->usdtScale), 4),
        ]);
    }

    private function defaultRate(): string
    {
        return $this->decimalRate(bcadd(
            (string)($this->data['default_usdt_ava_rate'] ?? 0),
            (string)($this->data['usdt_float_rate'] ?? 0),
            $this->usdtScale
        ));
    }

    private function decimalRate($rate): string
    {
        return bcadd((string)$rate, '0', $this->usdtScale);
    }

    private function reportInvalidRate(string $message, $merchant, string $rate): void
    {
        app(\App\Services\SystemNotice\SystemNoticeService::class)->warning("system_manual_notice", [
            'error' => $message,
            'rate' => $rate,
            'data' => $this->data,
            'merchant' => $merchant ? $merchant->toArray() : [],
        ]);
    }
}
