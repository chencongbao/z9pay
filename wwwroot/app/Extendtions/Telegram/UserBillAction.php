<?php

namespace App\Extendtions\Telegram;

use App\Models\User;
use App\Models\UserBank;
use App\Models\DepositOrder;
use App\Models\TransferOrder;
use App\Traits\TelegramTrait;
use Illuminate\Support\Facades\App;
use App\Services\User\GetUserRemainingDepositService;

class UserBillAction
{
    use TelegramTrait;

    private const TODAY_LIMIT = 50;
    private const DETAIL_LIMIT = 100;
    private const COMMAND_TODAY_BILL = '今日账单';
    private const COMMAND_DEPOSIT_DETAIL = '代收详情';
    private const COMMAND_TRANSFER_DETAIL = '代付详情';

    protected $telegram;

    public function __construct($telegram)
    {
        $this->telegram = $telegram;
    }

    public function excute($message = [], $group_type = 0)
    {
        $text = trim($message['text'] ?? '');
        $command = $this->matchCommand($text);
        if ($command === '') {
            return;
        }

        if ($group_type == 1) {
            $this->sendReply($message, '商家群无法操作此命令');
            return;
        }
        if ($group_type == 0) {
            $this->sendReply($message, '此群组还未绑定金主');
            return;
        }
        if ($group_type == 2 && $this->getUserId($message) <= 0) {
            $this->sendReply($message, '您的账号未绑定金主，请先绑定');
            return;
        }

        $account = trim(str_replace([self::COMMAND_TODAY_BILL, self::COMMAND_DEPOSIT_DETAIL, self::COMMAND_TRANSFER_DETAIL], '', $text));
        if ($command === self::COMMAND_TODAY_BILL) {
            $this->todayBill($message, $account);
            return;
        }
        if ($command === self::COMMAND_DEPOSIT_DETAIL) {
            $this->depositDetail($message, $account);
            return;
        }

        $this->transferDetail($message, $account);
    }

    private function todayBill($message, $account)
    {
        $user_id = $this->getUserId($message);
        $user = User::whereKey($user_id)->first(['id', 'user_rate', 'deposit_amount', 'deposit_user_rate', 'transfer_user_rate', 'settlement_user_rate']);
        if (!$user) {
            $this->sendReply($message, '绑定的金主不存在或已删除，请重新绑定');
            return;
        }

        $depositQuery = $this->depositQuery($user_id, $account);
        $depositStat = $this->orderStat($depositQuery);
        $deposit_result = $depositQuery->orderBy('id')->limit(self::TODAY_LIMIT)->get(['id', 'created_at', 'actual_amount']);

        $html = $this->todayTitle();
        $html .= "<b>代收(" . $depositStat['count'] . "笔)：" . $depositStat['amount'] . "</b>\n";

        if (!$deposit_result->isEmpty()) {
            foreach ($deposit_result as $deposit_item) {
                $html .= $this->formatOrderLine($deposit_item, $account);
            }
        }
        if ($depositStat['count'] > self::TODAY_LIMIT) {
            $html .= "请输入【<code>代收详情</code>】,查看更多\n";
        }
        $html .= "\n\n";

        $transferQuery = $this->transferQuery($user_id, $account);
        $transferStat = $this->orderStat($transferQuery);
        $transfer_result = $transferQuery->orderBy('id')->limit(self::TODAY_LIMIT)->get(['id', 'created_at', 'actual_amount']);
        $html .= "<b>代付(" . $transferStat['count'] . "笔)：" . $transferStat['amount'] . "</b>\n";
        if (!$transfer_result->isEmpty()) {
            foreach ($transfer_result as $transfer_item) {
                $html .= $this->formatOrderLine($transfer_item, $account);
            }
        }
        if ($transferStat['count'] > self::TODAY_LIMIT) {
            $html .= "请输入【<code>代付详情</code>】,查看更多\n";
        }
        $html .= "\n\n";

        $html .= "代收费率：<b>" . (floatval($user->deposit_user_rate) ?: floatval($user->user_rate)) . "%</b>\n";
        $html .= "代付费率：<b>" . (floatval($user->transfer_user_rate) ?: floatval($user->user_rate)) . "%</b>\n";
        $html .= "结算费率：<b>" . (floatval($user->settlement_user_rate) ?: floatval($user->user_rate)) . "%</b>\n";
        $html .= "总代收：<b>" . $depositStat['amount'] . "</b>\n";
        $html .= "总代付：<b>" . $transferStat['amount'] . "</b>\n";
        if ($user->deposit_amount > 0) {
            $remainingDeposit = App::make(GetUserRemainingDepositService::class)->excute($user->id);
            $html .= "剩余押金：<b>" . max((float)($remainingDeposit['remaining_deposit'] ?? 0), 0) . "</b>\n";
        } else {
            $html .= "剩余押金：<b>不限制</b>\n";
        }
        $this->sendReply($message, $html);
    }


    private function depositDetail($message, $account)
    {
        $user_id = $this->getUserId($message);
        $depositQuery = $this->depositQuery($user_id, $account);
        $depositStat = $this->orderStat($depositQuery);
        $deposit_result = $depositQuery->orderBy('id')->limit(self::DETAIL_LIMIT)->get(['id', 'created_at', 'actual_amount']);
        $html = $this->todayTitle();
        $html .= "<b>代收(" . $depositStat['count'] . "笔)：" . $depositStat['amount'] . "</b>\n";

        if (!$deposit_result->isEmpty()) {
            foreach ($deposit_result as $deposit_item) {
                $html .= $this->formatOrderLine($deposit_item, $account);
            }
        }
        if ($depositStat['count'] > self::DETAIL_LIMIT) {
            $html .= "仅显示前" . self::DETAIL_LIMIT . "笔，更多请到后台查看\n";
        }
        $html .= "\n\n";
        $this->sendReply($message, $html);
    }

    private function transferDetail($message, string $account = '')
    {
        $user_id = $this->getUserId($message);
        $transferQuery = $this->transferQuery($user_id, $account);
        $transferStat = $this->orderStat($transferQuery);
        $transfer_result = $transferQuery->orderBy('id')->limit(self::DETAIL_LIMIT)->get(['id', 'created_at', 'actual_amount']);
        $html = $this->todayTitle();
        $html .= "<b>代付(" . $transferStat['count'] . "笔)：" . $transferStat['amount'] . "</b>\n";
        if (!$transfer_result->isEmpty()) {
            foreach ($transfer_result as $transfer_item) {
                $html .= $this->formatOrderLine($transfer_item, $account);
            }
        }
        if ($transferStat['count'] > self::DETAIL_LIMIT) {
            $html .= "仅显示前" . self::DETAIL_LIMIT . "笔，更多请到后台查看\n";
        }
        $html .= "\n\n";
        $this->sendReply($message, $html);
    }

    private function matchCommand(string $text): string
    {
        foreach ([self::COMMAND_TODAY_BILL, self::COMMAND_DEPOSIT_DETAIL, self::COMMAND_TRANSFER_DETAIL] as $command) {
            if (mb_substr($text, 0, mb_strlen($command)) === $command) {
                return $command;
            }
        }

        return '';
    }

    private function depositQuery(int $user_id, string $account)
    {
        $query = DepositOrder::whereBetween('created_at', $this->todayRange())->where('user_id', $user_id)->where('status', 5);
        if ($account !== '') {
            $user_bank = UserBank::where('user_id', $user_id)->where('card_no', 'like', '%' . $account)->first(['id']);
            if ($user_bank) {
                $query->where('user_bank_id', $user_bank->id);
            }
        }

        return $query;
    }

    private function transferQuery(int $user_id, string $account = '')
    {
        $query = TransferOrder::whereBetween('created_at', $this->todayRange())->where('user_id', $user_id)->where('status', 4);
        if ($account !== '') {
            $query->where('card_no', 'like', '%' . $account);
        }

        return $query;
    }

    private function orderStat($query): array
    {
        // 统计笔数和金额合并成一次聚合查询，减少 Telegram 命令响应 SQL 次数。
        $stat = (clone $query)->selectRaw('COUNT(*) as total_count, COALESCE(SUM(actual_amount), 0) as total_amount')->first();

        return [
            'count' => intval($stat->total_count ?? 0),
            'amount' => floatval($stat->total_amount ?? 0),
        ];
    }

    private function todayRange(): array
    {
        $date = date('Y-m-d');

        return [$date . ' 00:00:00', $date . ' 23:59:59'];
    }

    private function todayTitle(): string
    {
        return '<b>' . date('Y-m-d') . "</b>\n";
    }

    private function formatOrderLine($order, string $account = ''): string
    {
        $accountText = $account === '' ? '' : '(' . htmlspecialchars($account, ENT_QUOTES, 'UTF-8') . ')';

        return '<code>' . date('H:i:s', strtotime($order->created_at)) . '</code> <b>' . floatval($order->actual_amount) . '</b>' . $accountText . "\n";
    }

    private function sendReply(array $message, string $text): void
    {
        $chat_id = $message['chat']['id'] ?? 0;
        if (!$chat_id) {
            return;
        }

        $this->telegram->sendMessage(['chat_id' => $chat_id, 'text' => $text, 'parse_mode' => 'html', 'reply_to_message_id' => $message['message_id'] ?? null]);
    }
}
