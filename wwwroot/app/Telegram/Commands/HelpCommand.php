<?php

namespace App\Telegram\Commands;

use App\Services\Telegram\TelegramManagerService;
use Telegram\Bot\Commands\Command;

class HelpCommand extends Command
{
    protected string $name = 'help';

    protected string $description = '显示帮助信息';

    public function handle()
    {
        $message = $this->getUpdate()->getMessage();
        $managerService = app(TelegramManagerService::class);
        if (!$managerService->isPrivateChat($message->chat ?? null)) {
            return null;
        }
        if (!$managerService->isManager(intval($message->from->id ?? 0))) {
            return null;
        }

        $text = "私聊机器人可用命令：\n\n";
        $text .= "/help - 查看帮助\n";
        $text .= "/币种 - 查询币种总统计\n";
        $text .= "示例：/cny\n";
        $text .= "/币种 -c [时间] - 查询该币种渠道统计\n";
        $text .= "示例：/cny -c\n";
        $text .= "示例：/cny -c 10m\n";
        $text .= "支持时间：10m、20m、30m、1h，默认当天\n\n";
        $text .= "查询指定商户余额：\n";
        $text .= "商户余额【商户代码/商户编号】\n";
        $text .= "说明：按商户代码或商户编号查询任意商户余额，不依赖当前群绑定商户\n";
        $text .= "示例：商户余额ABC123、商户余额24\n\n";
        $text .= "查询指定渠道余额：\n";
        $text .= "渠道余额【渠道编号】\n";
        $text .= "说明：先远程查询渠道余额，再返回最新余额\n";
        $text .= "示例：渠道余额1\n\n";
        $text .= "/channel_rate - 修改渠道百分比费率\n";
        $text .= "示例：/channel_rate 1 alipay/2.1 alipay_uid/2.2\n\n";
        $text .= "/channel_fixed_rate - 修改渠道固定费率\n";
        $text .= "示例：/channel_fixed_rate 1 alipay/3 alipay_uid/5\n\n";
        $text .= "币种统计命令：\n";
        $text .= "/cny、/vnd、/inr、/idr、/php、/thb、/myr、/bdt、/pkr、/try\n";
        $text .= "/brl、/hk、/mxn、/mmk、/jpy、/npr、/krw、/rub、/ngn、/lak\n";
        $text .= "这些币种命令都支持追加 -c 时间参数";

        return $this->replyWithMessage([
            'text' => $text,
        ]);
    }

}
