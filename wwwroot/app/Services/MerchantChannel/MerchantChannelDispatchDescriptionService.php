<?php

namespace App\Services\MerchantChannel;

use App\Services\Cache\Merchant\CacheMerchantBaseInfoService;
use App\Services\Enums\DepositChannelModeEnum;
use Illuminate\Support\Facades\App;

class MerchantChannelDispatchDescriptionService
{
    private const TRANSFER_MODE_MAP = [
        2 => '按随机',
        3 => '按平均',
        5 => '按权重',
    ];

    public function excute(int $merchantUserId): array
    {
        $merchant = App::make(CacheMerchantBaseInfoService::class)->excute($merchantUserId);
        if (empty($merchant)) {
            return [];
        }

        return [
            'merchant_name' => $merchant['bname'] ?? ('#' . $merchantUserId),
            'deposit' => $this->depositDescription((int)($merchant['deposit_channel_mode'] ?? 0)),
            'transfer' => $this->transferDescription((int)($merchant['transfer_channel_mode'] ?? 0)),
        ];
    }

    private function depositDescription(int $merchantMode): array
    {
        $adminMode = (int)(bob_admin_setting('other_deposit_channel_mode') ?: DepositChannelModeEnum::PRIORITY);
        $mode = $merchantMode > 0 ? $merchantMode : $adminMode;

        return [
            'title' => '代收渠道',
            'mode_value' => $mode,
            'mode' => DepositChannelModeEnum::MAP[$mode] ?? '未知模式(' . $mode . ')',
            'source' => $merchantMode > 0 ? '商户配置' : '后台默认',
            'rule' => $this->depositRule($mode),
        ];
    }

    private function transferDescription(int $merchantMode): array
    {
        $adminMode = (int)(bob_admin_setting('other_transfer_channel_mode') ?: 2);
        $mode = $merchantMode > 0 ? $merchantMode : $adminMode;

        return [
            'title' => '代付渠道',
            'mode_value' => $mode,
            'mode' => self::TRANSFER_MODE_MAP[$mode] ?? '未知模式(' . $mode . ')',
            'source' => $merchantMode > 0 ? '商户配置' : '后台默认',
            'rule' => $this->transferRule($mode),
        ];
    }

    private function depositRule(int $mode): string
    {
        return [
            DepositChannelModeEnum::PRIORITY => '按优先级从小到大依次尝试；优先级相同按商户通道ID降序、渠道ID升序。',
            DepositChannelModeEnum::RANDOM => '从符合条件的渠道中随机选择一个渠道。',
            DepositChannelModeEnum::AVERAGE => '按最近使用序号选择最少使用的渠道，尽量让渠道平均分配。',
            DepositChannelModeEnum::ROUND_ROBIN => '按最近使用序号轮询尝试渠道，成功后更新使用序号。',
            DepositChannelModeEnum::WEIGHT => '按权重比例分配，权重越大，理论分配次数越多；权重全为0时按平均模式处理。',
        ][$mode] ?? '当前模式未配置说明，请检查后台渠道模式配置。';
    }

    private function transferRule(int $mode): string
    {
        return [
            2 => '从符合条件的渠道中随机选择一个渠道。',
            3 => '按最近使用序号选择最少使用的渠道，尽量让渠道平均分配。',
            5 => '按权重比例分配，权重越大，理论分配次数越多；权重全为0时按平均模式处理。',
        ][$mode] ?? '当前模式未配置说明，请检查后台渠道模式配置。';
    }
}
