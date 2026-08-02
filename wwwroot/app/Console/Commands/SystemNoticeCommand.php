<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use App\Services\Api\CheckMerchantExistsService;
use App\Services\SystemNotice\SystemNoticeService;

class SystemNoticeCommand extends Command
{
    protected $signature = 'system:notice {code} {action : on|off|status|test} {--mid= : 商户ID，不传则为全局系统通知} {--level=debug : test level: debug|info|warning|error}';

    protected $description = '统一设置系统通知和商户通知开关';

    public function handle(): int
    {
        $code = trim((string)$this->argument('code'));
        $action = strtolower((string)$this->argument('action'));
        $level = strtolower(trim((string)$this->option('level')));
        $mid = $this->resolveMerchantId();

        if (!preg_match('/^[a-z0-9_-]{1,80}$/', $code)) {
            $this->error('code 仅支持 1-80 位小写字母、数字、下划线和中划线');
            return self::FAILURE;
        }

        if (!in_array($action, ['on', 'off', 'status', 'test'], true)) {
            $this->error('action 仅支持 on、off、status、test');
            return self::FAILURE;
        }

        if (!in_array($level, ['debug', 'info', 'warning', 'error'], true)) {
            $this->error('level 仅支持 debug、info、warning、error');
            return self::FAILURE;
        }

        if ($mid === false) {
            $this->error('商户ID不合法');
            return self::FAILURE;
        }

        if ($mid !== null && !App::make(CheckMerchantExistsService::class)->excute($mid)) {
            $this->error('商户不存在');
            return self::FAILURE;
        }

        $service = App::make(SystemNoticeService::class);
        $label = $mid ? "商户 #{$mid} 通知 {$code}" : "系统通知 {$code}";

        if ($action === 'on') {
            $service->enable($code, $mid);
            $this->info("{$label} 已开启");
            return self::SUCCESS;
        }

        if ($action === 'off') {
            $service->disable($code, $mid);
            $this->info("{$label} 已关闭");
            return self::SUCCESS;
        }

        if ($action === 'test') {
            $sent = $service->send($code, [
                'title' => '系统通知测试',
                'message' => "测试通知 {$code}",
            ], $level, 0, $mid);
            $this->info($sent ? '测试通知已发送' : '测试通知未发送，请检查级别或开关');
            return self::SUCCESS;
        }

        $enabled = $service->enabled($code, $mid, $level);
        $this->info("{$label} 状态：" . ($enabled ? '开启' : '关闭'));
        return self::SUCCESS;
    }

    private function resolveMerchantId(): int|null|false
    {
        $mid = $this->option('mid');
        if ($mid === null) {
            return null;
        }

        $mid = trim((string)$mid);
        if (!preg_match('/^[1-9]\d*$/', $mid)) {
            return false;
        }

        return (int)$mid;
    }
}
