<?php

namespace App\Jobs;

use Throwable;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Admin\Controllers\HomeController;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use App\Services\SystemNotice\SystemNoticeService;

class WarmAdminHomeDashboardCacheJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(private int $adminUserId = 0)
    {
    }

    public function uniqueFor(): int
    {
        return 10;
    }

    public function uniqueId(): string
    {
        return 'admin_home_dashboard_cache_' . now()->format('Ymd');
    }

    public function handle(): void
    {
        try {
            app(HomeController::class)->warmDashboardCache();
        } catch (Throwable $e) {
            $this->noticeWarmFailed($e);
        }
    }

    private function noticeWarmFailed(Throwable $e): void
    {
        try {
            app(SystemNoticeService::class)->warning('system_manual_notice', [
                'message' => '后台首页缓存异步预热失败：' . $e->getMessage(),
                'admin_user_id' => $this->adminUserId,
            ]);
        } catch (Throwable $noticeException) {
            report($noticeException);
        }
    }
}
