<?php

namespace App\Extendtions\Telegram;

use App\Traits\TelegramTrait;
use Illuminate\Support\Facades\Cache;
use App\Services\Telegram\TelegramManagerService;
use App\Services\Cache\CacheConstPrefixService;

class HelpAction
{
    use TelegramTrait;

    public $telegram;

    public function __construct($telegram)
    {
        $this->telegram = $telegram;
    }

    public function excute($message = [], $group_type = 0)
    {
        $text = trim((string) ($message['text'] ?? ''));
        if (!in_array(strtolower($text), ['help', '帮助'], true)) {
            return;
        }

        $chatId = intval($message['chat']['id'] ?? 0);
        $fromId = intval($message['from']['id'] ?? 0);
        if ($chatId > 0 && !app(TelegramManagerService::class)->isManagerMessage($message)) {
            return;
        }

        $this->telegram->sendMessage(['chat_id' => $chatId, 'text' => $this->buildHelpText((int) $group_type, $fromId, $chatId > 0), 'parse_mode' => 'html']);
    }

    private function buildHelpText(int $groupType, int $fromId, bool $privateChat): string
    {
        if ($privateChat) {
            return $this->headerText() . $this->baseCommandText() . $this->privateCommandText($fromId) . $this->commonCommandText();
        }

        return $this->headerText() . $this->baseCommandText() . $this->groupCommandText($groupType) . $this->commonCommandText();
    }

    private function headerText(): string
    {
        $robotInfo = (array) $this->getRobotInfo();
        $robotName = $robotInfo['first_name'] ?? '';
        $emoji = "<tg-emoji emoji-id='5368324170671202286'>👍</tg-emoji>";

        return "<blockquote expandable><b>{$emoji}{$emoji}{$emoji}{$emoji}{$emoji}{$emoji}\n{$robotName}命令大全\n{$emoji}{$emoji}{$emoji}{$emoji}{$emoji}{$emoji}</b></blockquote>\n\n";
    }

    private function baseCommandText(): string
    {
        return "<b>获取个人信息命令</b>\n<code>个人信息</code>、<code>我的信息</code>\n\n"
            . "<b>计算器命令</b>\n<code>数字*数字</code>、<code>数字+数字</code>、<code>数字-数字</code>、<code>数字/数字</code>\n\n";
    }

    private function groupCommandText(int $groupType): string
    {
        if ($groupType === 1) {
            return $this->merchantCommandText(true);
        }
        if ($groupType === 2) {
            return $this->userCommandText(true);
        }
        if ($groupType === 3) {
            return $this->channelCommandText(true);
        }

        return $this->merchantCommandText(false) . $this->userCommandText(false) . $this->channelCommandText(false) . $this->balanceCommandText() . $this->orderCommandText();
    }

    private function merchantCommandText(bool $withBalanceAndOrder): string
    {
        $text = "<b>绑定商户群</b>\n用途：把当前群绑定到指定商户。\n写法：<code>bd【商户代码】</code> 或 <code>绑定【商户代码】</code>\n示例：<code>bdABC123</code>\n\n";
        if ($withBalanceAndOrder) {
            $text .= $this->balanceCommandText();
        }
        $text .= "<b>申请商户管理员</b>\n用途：申请成为当前商户群管理员，审核通过后可确认商户群相关操作。\n命令：<code>申请商户管理员</code>\n说明：提交后需要后台管理员审核通过后生效。\n\n";
        $text .= "<b>Apply Merchant Admin</b>\nPurpose: Apply to become an admin of the current merchant group. After approval, you can confirm merchant group related operations.\nCommand: <code>apply merchant admin</code>\nNote: The application takes effect only after backend admin approval.\n\n";
        $text .= "<b>商户充值/加项/减项</b>\n写法：<code>充值【金额】</code>、<code>cz【金额】</code>、<code>加项【金额】</code>、<code>减项【金额】</code>\n示例：<code>充值1000</code>、<code>减项100</code>\n\n";
        $text .= "<b>修改商户费率</b>\n写法：<code>修改费率 通道编码/费率</code>\n示例：<code>修改费率 alipay/2.1</code>\n多个通道：<code>修改费率 alipay/2.1 alipay_uid/2.2</code>\n\n";
        $text .= "<b>查询成功率</b>\n写法：<code>查询成功率</code> 或 <code>/success_rate</code>\n用途：查询当前商户最近时间窗口的订单成功率。\n\n";
        $text .= "<b>今日统计</b>\n写法：<code>今日统计</code>\n用途：查询当前商户今日订单统计。\n\n";

        return $withBalanceAndOrder ? $text . $this->orderCommandText() : $text;
    }

    private function userCommandText(bool $withBalance): string
    {
        $text = "<b>绑定金主账号</b>\n用途：把当前飞机账号绑定到指定金主，提交后需要管理员点击确认。\n写法：<code>申请绑定【金主账号】</code> 或 <code>申请绑定【金主编号】</code>\n示例：<code>申请绑定94</code>、<code>申请绑定bob</code>\n说明：一个飞机账号只能绑定一个金主；如需换绑，请先解绑当前金主。\n\n";
        if ($withBalance) {
            $text .= $this->balanceCommandText();
        }
        $text .= "<b>金主收款开关</b>\n本人操作：<code>收款开启</code>、<code>收款关闭</code>\n管理员操作指定金主：<code>收款开启【金主账号/编号】</code>、<code>收款关闭【金主账号/编号】</code>\n\n";
        $text .= "<b>金主账单</b>\n今日汇总：<code>今日账单</code>\n指定账号：<code>今日账单【收款号/银行卡后4位】</code>\n代收明细：<code>代收详情</code> 或 <code>代收详情【收款号后4位】</code>\n代付明细：<code>代付详情</code> 或 <code>代付详情【银行卡后4位】</code>\n\n";

        return $text;
    }

    private function channelCommandText(bool $withBalance): string
    {
        $text = "<b>绑定渠道命令</b>\n写法：<code>渠道绑定【" . ($withBalance ? "渠道编号" : "渠道ID") . "】</code>\n示例：<code>渠道绑定12</code>\n\n";

        return $withBalance ? $text . $this->balanceCommandText() : $text;
    }

    private function balanceCommandText(): string
    {
        return "<b>查询余额命令</b>\n<code>余额</code>、<code>yu</code>\n\n";
    }

    private function orderCommandText(): string
    {
        return "<b>订单查询命令</b>\n<code>输入订单号</code>【平台单号或商户单号】\n\n"
            . "<b>代付成功回执单</b>\n中文：<code>回执单 商户单号</code>\n英文：<code>receipt 商户单号</code>\n说明：仅支持当前商户群内查询本商户代付成功订单。\n\n";
    }

    private function commonCommandText(): string
    {
        return "<b>欧易实时汇率查询命令</b>\n支付宝：<code>ot</code>、银行卡：<code>ob</code>、微信：<code>ow</code>\n\n"
            . "<b>记账命令</b>\n<code>开始记账</code>[申请记账权限]\n<code>设置费率</code>+数字\n<code>设置汇率</code>+数字\n<code>入款</code>+数字【2000或2000U】\n<code>下发</code>+数字【2000或2000U】\n<code>账单</code>\n<code>清空账单</code>\n<code>入款详情</code>\n<code>下发详情</code>\n<code>撤销下发</code>、<code>取消下发</code>\n<code>撤销入款</code>、<code>取消入款</code>\n\n";
    }

    private function privateCommandText(int $fromId): string
    {
        return "<b>查询指定商户余额</b>\n用途：在私聊机器人时，按商户代码或商户编号查询指定商户余额。\n写法：<code>商户余额【商户代码/商户编号】</code>\n示例：<code>商户余额ABC123</code>、<code>商户余额24</code>\n\n"
            . "<b>查询指定渠道余额</b>\n用途：在私聊机器人时，按渠道编号远程查询渠道最新余额。\n写法：<code>渠道余额【渠道编号】</code>\n示例：<code>渠道余额1</code>\n\n";
    }

    private function getRobotInfo()
    {
        $cachedInfo = Cache::get(CacheConstPrefixService::CACHE_TELEGRAM_ROBOT_INFO);
        if ($cachedInfo !== null) {
            return $cachedInfo;
        }
        $user = $this->telegram->getMe()->all();
        Cache::forever(CacheConstPrefixService::CACHE_TELEGRAM_ROBOT_INFO, $user);
        return $user;
    }
}
