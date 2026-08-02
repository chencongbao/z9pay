<?php

namespace App\Services\UserBank;

use Throwable;
use RuntimeException;
use App\Models\UserBank;
use App\Traits\ServiceTraits;
use App\Models\UserBankBalanceLog;
use Illuminate\Support\Facades\DB;
use App\Services\SystemNotice\SystemNoticeService;

class UserBankBalanceChangeService
{
    use ServiceTraits;

    public function excute(array $data = []): void
    {
        try {
            $this->validateData($data);
            DB::transaction(function () use ($data) {
                $userBank = UserBank::query()->whereKey((int)$data['user_bank_id'])->lockForUpdate()->first(['id', 'user_id', 'balance_amount']);
                if (!$userBank) {
                    throw new RuntimeException('收款卡不存在');
                }

                $amount = bob_amount_format($data['amount']);
                $balanceAmount = bob_amount_format($userBank->balance_amount + $amount);

                // 锁定收款卡后写入余额和流水，保证流水余额与卡余额一致。
                $userBank->forceFill(['balance_amount' => $balanceAmount])->saveQuietly();

                $logData = [
                    'action_admin_id' => (int)($data['action_admin_id'] ?? 0),
                    'amount' => $amount,
                    'user_id' => (int)($data['user_id'] ?? $userBank->user_id),
                    'user_bank_id' => $userBank->id,
                    'type' => (int)$data['type'],
                    'type_id' => (int)($data['type_id'] ?? 0),
                    'remark' => trim((string)($data['remark'] ?? '')),
                    'balance_amount' => $balanceAmount,
                ];

                UserBankBalanceLog::query()->create($logData);
            });
        } catch (Throwable $e) {
            app(SystemNoticeService::class)->warning("system_manual_notice", [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'action' => "金主收款卡余额变化异常",
                'data' => $data,
            ]);
            throw $e;
        }
    }

    private function validateData(array $data): void
    {
        if (empty($data['user_bank_id']) || !isset($data['amount']) || empty($data['type'])) {
            throw new RuntimeException("缺少参数");
        }
    }
}
