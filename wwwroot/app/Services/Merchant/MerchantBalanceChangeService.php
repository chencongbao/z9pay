<?php

namespace App\Services\Merchant;

use App\Jobs\MerchantUsdtAveRateJob;
use App\Models\TransferOrder;
use App\Models\MerchantInfo;
use App\Services\Enums\ErrorCodeEnum;
use App\Services\Common\ReportExceptionService;
use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\App;
use App\Models\MerchantBalanceLog;
use Illuminate\Support\Facades\DB;
use Throwable;

class MerchantBalanceChangeService
{
    use ServiceTraits;

    public $merchant_balance_log_id = 0;

    public $merchant;

    public $amount = 0;

    // 商户余额变化
    // mid, amount, fee, type, currency_id, type_id, remark
    public function excute($data = [])
    {
        if (!is_array($data)) {
            return $this->failResult('余额变化参数错误', ErrorCodeEnum::SUBMIT_PARAM_MISSING);
        }

        return $this->changeBalance($data);
    }

    public function deductTransferOrder(TransferOrder $order, float $amount, float $fee, string $remark = '', int $adminId = 0): array
    {
        return $this->changeBalance([
            'mid' => $order->mid,
            'amount' => -$amount,
            'fee' => $fee,
            'type' => 2,
            'type_id' => $order->id,
            'currency_id' => $order->currency_id,
            'order_type' => 2,
            'payment_id' => 7,
            'remark' => $remark,
            'admin_id' => $adminId,
            'ordernumber' => $order->ordernumber,
            'order_no' => $order->order_no,
        ], [
            'check_available_balance' => true,
            'error_title' => '代付商户余额扣减失败',
            'error_message' => '商户余额扣减失败',
        ]);
    }

    public function deductSettlementOrder(TransferOrder $order, float $amount, float $fee, string $remark = '', int $adminId = 0): array
    {
        return $this->changeBalance([
            'mid' => $order->mid,
            'amount' => -$amount,
            'fee' => $fee,
            'type' => 6,
            'type_id' => $order->id,
            'currency_id' => $order->currency_id,
            'order_type' => 2,
            'payment_id' => 7,
            'remark' => $remark,
            'admin_id' => $adminId,
            'ordernumber' => $order->ordernumber,
            'order_no' => $order->order_no,
        ], [
            'check_available_balance' => true,
            'error_title' => '结算商户余额扣减失败',
            'error_message' => '商户结算扣款失败',
        ]);
    }

    public function reduceManual(MerchantInfo $merchant, float $amount, string $remark = '', int $adminId = 0): array
    {
        return $this->changeBalance([
            'mid' => $merchant->merchant_user_id,
            'amount' => -$amount,
            'fee' => 0,
            'type' => 12,
            'admin_id' => $adminId,
            'type_id' => $merchant->merchant_user_id,
            'payment_id' => 0,
            'remark' => $remark,
        ], [
            'error_title' => '商户余额手动减项失败',
            'error_message' => '商户余额手动减项失败',
        ]);
    }

    private function changeBalance(array $data, array $options = []): array
    {
        try {
            if (!isset($data['mid']) || !isset($data['amount']) || !isset($data['type'])) {
                return $this->failResult('余额变化参数缺失', ErrorCodeEnum::SUBMIT_PARAM_MISSING);
            }

            if (DB::transactionLevel() > 0) {
                return $this->changeBalanceInTransaction($data, $options);
            }

            return DB::transaction(function () use ($data, $options) {
                return $this->changeBalanceInTransaction($data, $options);
            });
        } catch (Throwable $e) {
            App::make(ReportExceptionService::class)->report($options['error_title'] ?? '商户余额变化发生异常', $e, [
                'data' => $data,
                'mid' => $data['mid'] ?? null,
                'ordernumber' => $data['ordernumber'] ?? null,
            ]);

            return $this->failResult($options['error_message'] ?? '商户余额变化失败', ErrorCodeEnum::COMMON_ERROR);
        }
    }

    private function changeBalanceInTransaction(array $data, array $options): array
    {
        $data = $this->normalizeChangeData($data);
        $merchant = MerchantInfo::where('merchant_user_id', $data['mid'])->lockForUpdate()->first([
            'merchant_user_id',
            'name',
            'currency_id',
            'balance_amount',
            'available_balance',
            'freeze_amount',
            'telegram_group_id',
            'usdt_float_rate',
            'default_usdt_ava_rate',
            'usdt_ava_rate',
            'is_usdt_ava_rate',
        ]);

        if (!$merchant) {
            return $this->failResult('商户信息不存在', ErrorCodeEnum::SUBMIT_MERCHANT_INVALID);
        }

        $this->merchant = $merchant;
        $this->amount = $data['amount'];

        if (!empty($options['check_available_balance']) && $this->amountLessThan($merchant->available_balance, $this->availableBalanceCost($data))) {
            return $this->failResult('商户余额不足', ErrorCodeEnum::SUBMIT_BALANCE_INSUFFICIENT);
        }

        $this->applyBalanceChange($merchant, $data);
        $this->merchant = $merchant;

        $balanceLog = $this->createBalanceLog($merchant, $data);
        $this->merchant_balance_log_id = $balanceLog->id;

        $this->dispatchUsdtRateJob($merchant, $balanceLog, $data);
        $this->afterCommitBalanceNotice($data);

        return $this->successResult();
    }

    private function normalizeChangeData(array $data): array
    {
        $settlementMode = intval($data['settlement_mode'] ?? 0);

        return [
            'mid' => intval($data['mid'] ?? 0),
            'amount' => bob_amount_format($data['amount'] ?? 0),
            'fee' => bob_amount_format($data['fee'] ?? 0),
            'type' => intval($data['type'] ?? 0),
            'admin_id' => intval($data['admin_id'] ?? 0),
            'type_id' => $data['type_id'] ?? 0,
            'remark' => $data['remark'] ?? '',
            'order_type' => $data['order_type'] ?? 0,
            'payment_id' => intval($data['payment_id'] ?? 0),
            'settlement_mode' => $settlementMode,
            'settlement_time' => intval($data['settlement_time'] ?? 0),
            'status' => $settlementMode > 0 ? 0 : 1,
            'ordernumber' => $data['ordernumber'] ?? null,
            'order_no' => $data['order_no'] ?? null,
        ];
    }

    private function availableBalanceCost(array $data): float
    {
        if (($data['settlement_mode'] ?? 0) > 0) {
            return 0;
        }

        $amountCost = $data['amount'] < 0 ? abs($data['amount']) : 0;

        return $this->addAmount($amountCost, $data['fee']);
    }

    private function applyBalanceChange(MerchantInfo $merchant, array $data): void
    {
        $balanceAmount = $this->subtractAmount(
            $this->addAmount($merchant->balance_amount, $data['amount']),
            $data['fee']
        );
        $availableBalance = $merchant->available_balance;
        $freezeAmount = $merchant->freeze_amount;

        if ($data['type'] == 9) { // 冻结
            $freezeAmount = $this->addAmount($freezeAmount, abs($data['amount']));
        }

        if ($data['type'] == 10) { // 解冻
            $freezeAmount = $this->subtractAmount($freezeAmount, $data['amount']);
        }

        if ($data['settlement_mode'] == 0) {
            $availableBalance = $this->subtractAmount(
                $this->addAmount($availableBalance, $data['amount']),
                $data['fee']
            );
        }

        $merchant->forceFill([
            'balance_amount' => $balanceAmount,
            'available_balance' => $availableBalance,
            'freeze_amount' => $freezeAmount,
        ])->saveQuietly();
    }

    private function createBalanceLog(MerchantInfo $merchant, array $data): MerchantBalanceLog
    {
        return MerchantBalanceLog::create([
            'amount' => $data['amount'],
            'mid' => $data['mid'],
            'fee' => $data['fee'],
            'type' => $data['type'],
            'admin_id' => $data['admin_id'],
            'type_id' => $data['type_id'],
            'remark' => $data['remark'],
            'currency_id' => $merchant->currency_id,
            'payment_id' => $data['payment_id'],
            'order_type' => $data['order_type'],
            'status' => $data['status'],
            'settlement_mode' => $data['settlement_mode'],
            'settlement_time' => $data['settlement_time'],
            'balance_amount' => $merchant->balance_amount,
            'ordernumber' => $data['ordernumber'],
            'order_no' => $data['order_no'],
        ]);
    }

    private function dispatchUsdtRateJob(MerchantInfo $merchant, MerchantBalanceLog $balanceLog, array $data): void
    {
        if ($data['settlement_mode'] != 0 || $merchant->is_usdt_ava_rate != 1) {
            return;
        }

        dispatch(new MerchantUsdtAveRateJob([
            'merchant_balance_log_id' => $balanceLog->id,
            'mid' => $merchant->merchant_user_id,
            'n_amount' => $this->subtractAmount($data['amount'], $data['fee']),
            'currency_id' => $merchant->currency_id,
            'usdt_float_rate' => $merchant->usdt_float_rate,
            'default_usdt_ava_rate' => $merchant->default_usdt_ava_rate,
            'order_id' => $data['type_id'],
        ]))->onQueue('query')->afterCommit();
    }

    private function afterCommitBalanceNotice(array $data): void
    {
        if (!$this->merchant) {
            return;
        }

        $merchant = clone $this->merchant;
        $amount = $this->amount;

        if (DB::transactionLevel() <= 0) {
            $this->sendBalanceNotice($merchant, $amount);
            return;
        }

        DB::afterCommit(function () use ($merchant, $amount) {
            $this->sendBalanceNotice($merchant, $amount);
        });
    }

    private function sendBalanceNotice(MerchantInfo $merchant, float $amount): void
    {
        App::make(MerchantBalanceNoticeService::class)->handle($merchant, $amount);
    }

    private function amountLessThan($left, $right): bool
    {
        if (function_exists('bccomp')) {
            return bccomp((string)$left, (string)$right, 2) < 0;
        }

        return (float)$left < (float)$right;
    }

    private function addAmount($left, $right): float
    {
        if (function_exists('bcadd')) {
            return bob_amount_format(bcadd((string)$left, (string)$right, 4));
        }

        return bob_amount_format((float)$left + (float)$right);
    }

    private function subtractAmount($left, $right): float
    {
        if (function_exists('bcsub')) {
            return bob_amount_format(bcsub((string)$left, (string)$right, 4));
        }

        return bob_amount_format((float)$left - (float)$right);
    }

    private function successResult(): array
    {
        return [
            'success' => true,
            'message' => '',
            'error_code' => 0,
        ];
    }

    private function failResult(string $message, int $errorCode): array
    {
        return [
            'success' => false,
            'message' => $message,
            'error_code' => $errorCode,
        ];
    }
}
