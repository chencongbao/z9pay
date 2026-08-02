<?php

namespace App\Traits;

use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\Contracts\Activity;

trait LogsModelActivity
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName($this->getTable())->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function tapActivity(\Spatie\Activitylog\Contracts\Activity $activity, string $eventName)
    {
        $activity->description = match ($eventName) {
            'created'  => "{$this->getTable()} [{$this->getKey()}] 已创建",
            'updated'  => "{$this->getTable()} [{$this->getKey()}] 已更新",
            'deleted'  => "{$this->getTable()} [{$this->getKey()}] 已删除",
            'restored' => "{$this->getTable()} [{$this->getKey()}] 已还原",
            default    => $eventName,
        };

        $props = ($activity->properties?->toArray()) ?? [];

        if ($request = request()) {
            $props['_context'] = array_filter([
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url'        => $request->fullUrl(),
                'request_id' => $this->resolveRequestId(),
            ]);
        }

        // 合并回去，而不是重建，避免将来丢失结构
        $activity->properties = $props;
    }
}
