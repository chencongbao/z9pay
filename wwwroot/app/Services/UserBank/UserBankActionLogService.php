<?php

namespace App\Services\UserBank;

use Throwable;
use App\Models\AdminUser;
use App\Traits\ServiceTraits;
use App\Models\UserBankActionLog;
use Illuminate\Support\Facades\App;
use App\Services\Cache\User\GetUserDetailService;
use App\Services\Common\ReportExceptionService;

class UserBankActionLogService
{
    use ServiceTraits;

    public function excute(array $data = []): void
    {
        try {
            $type = (int)($data['type'] ?? 0);
            $typeId = (int)($data['type_id'] ?? 0);

            UserBankActionLog::query()->create([
                'user_bank_id' => (int)($data['user_bank_id'] ?? 0),
                'action' => (int)($data['action'] ?? 0),
                'name' => $data['name'] ?? $this->resolveOperatorName($type, $typeId),
                'type' => $type,
                'type_id' => $typeId,
                'remark' => $data['remark'] ?? '',
                'ip' => bob_ip(),
            ]);
        } catch (Throwable $e) {
            app(ReportExceptionService::class)->report('收款卡操作日志发生异常', $e, [
                'data' => $data,
            ]);
        }
    }

    private function resolveOperatorName(int $type, int $typeId): string
    {
        if ($typeId <= 0) {
            return '';
        }

        if (in_array($type, [1, 3], true)) {
            $user = App::make(GetUserDetailService::class)->excute($typeId);

            return (string)($user['bname'] ?? '');
        }

        if ($type === 2) {
            $admin = AdminUser::query()->find($typeId, ['id', 'name', 'username']);
            if ($admin) {
                return "【{$admin->id}】【{$admin->username}】{$admin->name}";
            }
        }

        return '';
    }
}
