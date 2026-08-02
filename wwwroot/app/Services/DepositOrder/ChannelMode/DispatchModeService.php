<?php

namespace App\Services\DepositOrder\ChannelMode;

use App\Services\Cache\Merchant\CacheMerchantBaseInfoService;
use App\Services\Enums\DepositChannelModeEnum;
use Illuminate\Support\Facades\App;
use Throwable;

class DispatchModeService
{

    public function excute($order,array $channels,$logService):array
    {
        return $this->execute($order, $channels, $logService);
    }

    public function execute($order, array $channels, $logService): array
    {
        // 模式：后台默认 + 商户覆盖
        $channel_mode_admin = intval(bob_admin_setting("other_deposit_channel_mode"));
        $channels = $this->normalizeChannels($channels);
        if (empty($channels)) {
            $logService->excute($order->id, '没有可执行的代收渠道', [], 'error');
            return [[], '没有可执行的代收渠道'];
        }

        [$channel_mode, $merchantMode] = $this->resolveMode($order, $channel_mode_admin, $logService);
        $serviceClass = $this->serviceMap()[$channel_mode];
        $service = App::make($serviceClass);

        $logService->excute($order->id, '开始执行模式服务', [
            '后台默认模式' => DepositChannelModeEnum::MAP[$channel_mode_admin] ?? $channel_mode_admin,
            '商户配置模式' => $merchantMode ? (DepositChannelModeEnum::MAP[$merchantMode] ?? $merchantMode) : '默认配置',
            '最终执行模式' => DepositChannelModeEnum::MAP[$channel_mode] ?? '未知模式',
            '渠道数量' => count($channels),
        ], 'debug');

        try {
            $result = $service->handle($order, $channels, $logService);
        } catch (Throwable $e) {
            $logService->excute($order->id, '模式服务执行异常', [
                '模式' => DepositChannelModeEnum::MAP[$channel_mode] ?? $channel_mode,
                '异常类型' => get_class($e),
                '异常信息' => $e->getMessage(),
            ], 'error');
            return [[], '模式服务执行异常'];
        }

        if (!empty($result)) {
            return [$result, null];
        }

        return [[], $service->getError() ?: "渠道返回未知错误"];
    }

    private function resolveMode($order, int $adminMode, $logService): array
    {
        $channelMode = $adminMode;
        $merchantMode = 0;

        $merchant = App::make(CacheMerchantBaseInfoService::class)->excute($order->mid);
        if (!empty($merchant) && isset($merchant['deposit_channel_mode']) && (int)$merchant['deposit_channel_mode'] > 0) {
            $merchantMode = (int)$merchant['deposit_channel_mode'];
            $channelMode = $merchantMode;
        }

        if (!array_key_exists($channelMode, $this->serviceMap())) {
            $logService->excute($order->id, '模式非法，强制兜底为随机', [
                'channel_mode' => $channelMode
            ], 'debug');
            $channelMode = DepositChannelModeEnum::RANDOM;
        }

        return [$channelMode, $merchantMode];
    }

    private function serviceMap(): array
    {
        return [
            DepositChannelModeEnum::PRIORITY => ModePriorityService::class,
            DepositChannelModeEnum::RANDOM => ModeRandomService::class,
            DepositChannelModeEnum::AVERAGE => ModeLeastUsedOnceService::class,
            DepositChannelModeEnum::ROUND_ROBIN => ModeRoundRobinService::class,
            DepositChannelModeEnum::WEIGHT => ModeWeightService::class,
        ];
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
