<?php

namespace App\Services\Channel;

use Throwable;
use App\Models\Channel;
use Illuminate\Support\Facades\Cache;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\Telegram\TelegramInstanceService;
use App\Services\Common\BalanceNoticeIntervalService;
use App\Services\SystemNotice\SystemNoticeService;

class QueryChannelBalanceService
{
    public function getQueryExceptionCooldownKey(int $channelId): string
    {
        return CacheConstPrefixService::CHANNEL_BALANCE_QUERY_EXCEPTION_COOLDOWN . $channelId;
    }

    public function inQueryExceptionCooldown(int $channelId): bool
    {
        return $channelId > 0 && Cache::has($this->getQueryExceptionCooldownKey($channelId));
    }

    public function markQueryExceptionCooldown(int $channelId): void
    {
        if ($channelId <= 0) {
            return;
        }

        Cache::put($this->getQueryExceptionCooldownKey($channelId), 1, now()->addHours(2));
    }

    public function clearQueryExceptionCooldown(int $channelId): void
    {
        if ($channelId <= 0) {
            return;
        }

        Cache::forget($this->getQueryExceptionCooldownKey($channelId));
    }

    public function getOrderQueryThrottleKey(int $channelId): string
    {
        return CacheConstPrefixService::CHANNEL_BALANCE_ORDER_QUERY_THROTTLE . $channelId;
    }

    public function acquireOrderQueryThrottle(int $channelId): bool
    {
        if ($channelId <= 0) {
            return false;
        }

        return Cache::add($this->getOrderQueryThrottleKey($channelId), 1, now()->addMinutes(10));
    }

    public function supportsBalanceQuery(Channel $channel): bool
    {
        $classname = 'Richard\\Payment\\Channel\\' . $channel->classname;
        if (!class_exists($classname) || !method_exists($classname, 'queryBalance')) {
            return false;
        }

        $method = new \ReflectionMethod($classname, 'queryBalance');

        return $method->getDeclaringClass()->getName() !== 'Richard\\Payment\\Channel\\BasePayment';
    }

    public function execute(Channel $channel, bool $useExceptionCooldown = false): array
    {
        if ($useExceptionCooldown && $this->inQueryExceptionCooldown((int) $channel->id)) {
            throw new \Exception('渠道余额查询异常冷却中，2小时内不再查询');
        }

        $classname = 'Richard\\Payment\\Channel\\' . $channel->classname;

        try {
            if (!class_exists($classname)) {
                throw new \Exception('渠道支付类不存在');
            }

            if (!$this->supportsBalanceQuery($channel)) {
                throw new \Exception('渠道未接入此方法，不支持余额查询');
            }

            $pay = new $classname();
            $balanceAmount = $pay->queryBalance();

            if (!empty($pay->error)) {
                throw new \Exception($this->formatError($pay->error));
            }

            if (!is_numeric($balanceAmount)) {
                throw new \Exception('渠道余额返回格式错误：' . $this->formatError($balanceAmount));
            }

            $now = now()->toDateTimeString();
            $updated = Channel::query()->whereKey($channel->id)->update([
                'balance_amount' => $balanceAmount,
                'balance_update_time' => $now,
            ]);

            if ($updated <= 0) {
                throw new \Exception('渠道余额更新失败');
            }

            $channel->balance_amount = $balanceAmount;
            $channel->balance_update_time = $now;

            $this->clearQueryExceptionCooldown((int) $channel->id);
            $this->sendBalanceNotice($channel);

            return [
                'status' => true,
                'msg' => '查询成功',
                'balance_amount' => $balanceAmount,
            ];
        } catch (Throwable $e) {
            if (
                $useExceptionCooldown
                && ! $this->isUnsupportedBalanceQueryException($e)
                && ! $this->isBalanceQueryCooldownException($e)
            ) {
                $this->markQueryExceptionCooldown((int) $channel->id);
            }

            throw $e;
        }
    }

    public function isUnsupportedBalanceQueryException(\Throwable $e): bool
    {
        return str_contains((string) $e->getMessage(), '不支持余额查询')
            || str_contains((string) $e->getMessage(), '渠道未接入此方法');
    }

    public function isBalanceQueryCooldownException(\Throwable $e): bool
    {
        return str_contains((string) $e->getMessage(), '渠道余额查询异常冷却中');
    }

    protected function sendBalanceNotice(Channel $channel): void
    {
        if (intval(config('telegram.turn_on', 0)) !== 1 || empty(config('telegram.telegram_bot_token'))) {
            return;
        }

        $noticeAmount = app(GetChannelNoticeBalanceService::class)->excute($channel->id);
        if ($noticeAmount <= 0 || floatval($channel->balance_amount) >= $noticeAmount) {
            return;
        }

        $chatIds = collect(bob_format_muti_data_to_array((string) bob_admin_setting('telegram_channel_balance_notice_telegram_group_ids')))
            ->filter(function ($chatId) {
                return !empty($chatId);
            })->unique()->values()->all();

        if (empty($chatIds)) {
            return;
        }

        $interval = app(BalanceNoticeIntervalService::class)->minutes();
        if ($interval > 0) {
            $key = CacheConstPrefixService::CHANNEL_BALANCE_NOTICE . $channel->id;
            if (!Cache::add($key, 1, now()->addMinutes($interval))) {
                return;
            }
        }

        try {
            $telegram = app(TelegramInstanceService::class)->excute();
            $channelName = $channel->name ?: ('#' . $channel->id);
            $text = "渠道【{$channelName}】余额为【" . bob_unit_format($channel->balance_amount) . "】，已不足【" . bob_unit_format($noticeAmount) . "】。\n为了不影响业务请及时处理。";
            foreach ($chatIds as $chatId) {
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => $text,
                    'parse_mode' => 'html',
                ]);
            }
        } catch (Throwable $e) {
            app(SystemNoticeService::class)->warning('system_manual_notice', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'action' => '渠道余额不足通知发送失败',
                'channel_id' => $channel->id,
            ]);
        }
    }

    private function formatError($error): string
    {
        if (is_scalar($error) || $error === null) {
            return (string) $error;
        }

        return json_encode($error, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: var_export($error, true);
    }
}
