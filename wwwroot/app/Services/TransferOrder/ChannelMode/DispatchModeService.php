<?php

namespace App\Services\TransferOrder\ChannelMode;

use App\Services\Cache\Merchant\CacheMerchantBaseInfoService;
use Illuminate\Support\Facades\App;
use Throwable;

class DispatchModeService
{
    private ?string $error = null;

    public function execute($order, array $channels, $logService): ?array
    {
        $adminMode = intval(bob_admin_setting('other_transfer_channel_mode'));
        $channels = $this->normalizeChannels($channels);
        if (empty($channels)) {
            $this->error = '没有可执行的代付渠道';
            $this->writeLog($logService, $order->id, '没有可执行的代付渠道', [], 'error');
            return null;
        }

        [$channelMode, $merchantMode] = $this->resolveMode($order, $adminMode, $logService);
        $serviceClass = $this->serviceMap()[$channelMode];
        $service = App::make($serviceClass);

        $this->writeLog($logService, $order->id, '开始执行代付模式服务', [
            '后台默认模式' => $this->modeName($adminMode),
            '商户配置模式' => $merchantMode > 0 ? $this->modeName($merchantMode) : '默认配置',
            '最终执行模式' => $this->modeName($channelMode),
            '渠道数量' => count($channels),
        ], 'debug');

        try {
            $result = $service->handle($order, $channels, $logService);
        } catch (Throwable $e) {
            $this->error = '模式服务执行异常';
            $this->writeLog($logService, $order->id, '模式服务执行异常', [
                '模式' => $this->modeName($channelMode),
                '异常类型' => get_class($e),
                '异常信息' => $e->getMessage(),
            ], 'error');
            return null;
        }

        if (!empty($result)) {
            return $result;
        }

        $this->error = $service->getError() ?: '渠道返回未知错误';
        return null;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    private function resolveMode($order, int $adminMode, $logService): array
    {
        $channelMode = $adminMode;
        $merchantMode = 0;
        $merchant = App::make(CacheMerchantBaseInfoService::class)->excute($order->mid);

        if (!empty($merchant) && isset($merchant['transfer_channel_mode']) && (int)$merchant['transfer_channel_mode'] > 0) {
            $merchantMode = (int)$merchant['transfer_channel_mode'];
            $channelMode = $merchantMode;
        }

        if (!array_key_exists($channelMode, $this->serviceMap())) {
            $this->writeLog($logService, $order->id, '代付模式非法，强制兜底为随机', [
                '模式值' => $channelMode,
            ], 'debug');
            $channelMode = 2;
        }

        return [$channelMode, $merchantMode];
    }

    private function serviceMap(): array
    {
        return [
            2 => ModeRandomService::class,
            3 => ModeLeastUsedOnceService::class,
            5 => ModeWeightService::class,
        ];
    }

    private function modeName(int $mode): string
    {
        return [
            2 => '随机模式',
            3 => '平均模式',
            5 => '权重模式',
        ][$mode] ?? '未知模式(' . $mode . ')';
    }

    private function writeLog($logService, $orderId, string $title, $content = [], string $type = 'debug'): void
    {
        if ($logService && method_exists($logService, 'excute')) {
            $logService->excute($orderId, $title, $content, $type);
        }
    }

    private function normalizeChannels(array $channels): array
    {
        if (empty($channels)) {
            return [];
        }

        if (array_key_exists('channel_id', $channels)) {
            return [$channels];
        }

        return array_values(array_filter($channels, 'is_array'));
    }
}
