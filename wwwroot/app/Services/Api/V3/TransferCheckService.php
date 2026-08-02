<?php

namespace App\Services\Api\V3;

use App\Services\Cache\Channel\ChannelInfoByChannelIdService;
use App\Services\Cache\Channel\ChannelWhiteIpByClassNameService;
use App\Services\Channel\CheckChannelPaymentService;
use App\Services\Common\AmountCompareService;
use App\Services\IpWhite\CheckIpService;
use App\Services\Order\OrderCacheService;
use App\Services\SystemNotice\SystemNoticeService;
use App\Traits\ServiceResponseTrait;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;

class TransferCheckService
{
    use ServiceResponseTrait;

    public function excute(array $data): array
    {
        $channel = App::make(ChannelInfoByChannelIdService::class)->excute($data['cid']);
        if (empty($channel)) {
            $this->writeNotice('渠道信息不存在', $data);
            return $this->failed('渠道信息不存在');
        }

        if (isset($channel['status']) && (int) $channel['status'] === 0) {
            $this->writeNotice('渠道信息已禁用', $data, [
                'channel_status' => $channel['status'] ?? null,
            ]);
            return $this->failed('渠道信息已禁用');
        }

        $sign = bob_sign(Arr::except($data, ['sign']), $channel['appsecret']);
        if (!bob_check_sign($sign, $data['sign'], 1)) {
            $this->writeNotice('代付反查签名错误', $data, [
                'sign_string' => bob_sign_string(Arr::except($data, ['sign'])),
                'self_sign' => $sign,
                'sign_space' => 1,
            ]);
            return $this->fail(trans('api.sign_error'), '签名错误');
        }

        if (!App::make(CheckChannelPaymentService::class)->excute($data['cid'], 7)) {
            $this->writeNotice('渠道不支持代付', $data);
            return $this->failed('渠道不支持代付');
        }

        $ips = App::make(ChannelWhiteIpByClassNameService::class)->excute($channel['classname']);
        if (!App::make(CheckIpService::class)->excute($ips)) {
            $this->writeNotice('渠道IP不在白名单', $data, [
                'request_ip' => bob_ip(),
                'white_ips' => $ips,
                'channel_classname' => $channel['classname'] ?? '',
            ]);
            return $this->failed('渠道IP不在白名单');
        }

        $order = App::make(OrderCacheService::class)->getTransferByOrdernumber($data['ordernumber'], true);
        if (empty($order)) {
            $this->writeNotice('代付订单缓存不存在', $data, [
                'cache_only' => true,
            ]);
            return $this->failed('代付订单不存在');
        }

        if ((int) ($order['channel_id'] ?? 0) !== (int) $data['cid']) {
            $this->writeNotice('代付订单通道不匹配', $data, [
                'order_channel_id' => $order['channel_id'] ?? 0,
                'order_status' => $order['status'] ?? null,
            ]);
            return $this->failed('代付订单通道不匹配');
        }

        if (!in_array($order['status'], [1, 3, 6], true)) {
            $this->writeNotice('代付订单状态不允许', $data, [
                'order_status' => $order['status'] ?? null,
                'order_channel_id' => $order['channel_id'] ?? 0,
            ]);
            return $this->failed('代付订单状态不允许');
        }

        if (!App::make(AmountCompareService::class)->same($order['amount'], $data['amount'])) {
            $this->writeNotice('代付金额不正确', $data, [
                'order_amount' => $order['amount'] ?? '',
                'request_amount' => $data['amount'] ?? '',
            ]);
            return $this->failed('代付金额不正确');
        }

        return $this->success([]);
    }

    private function writeNotice(string $reason, array $data, array $context = []): void
    {
        App::make(SystemNoticeService::class)->warning('transfer_check_failed', $this->noticePayload($reason, $data, $context));
    }

    private function failed(string $zhMessage = '代付反查失败'): array
    {
        return $this->fail('fail', $zhMessage);
    }

    private function noticePayload(string $reason, array $data, array $context = []): array
    {
        return array_merge([
            'title' => '代付反查失败',
            'error' => $reason,
            'action' => 'transferCheck',
            'request' => Arr::except($data, ['sign']),
            'ip' => bob_ip(),
            'path' => request()->path(),
        ], $context);
    }
}
