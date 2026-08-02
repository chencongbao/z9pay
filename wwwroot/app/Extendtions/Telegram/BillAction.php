<?php

namespace App\Extendtions\Telegram;

use App\Models\Bill;
use App\Models\BillLog;
use App\Models\BillUser;
use InvalidArgumentException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Services\Telegram\BillEntryCalculationService;

class BillAction
{
    private const DETAIL_LIMIT = 100;

    public $telegram;

    private BillEntryCalculationService $calculationService;

    public $keyboard = [
        'inline_keyboard' => []
    ];

    public function __construct($telegram, ?BillEntryCalculationService $calculationService = null)
    {
        $this->telegram = $telegram;
        $this->calculationService = $calculationService ?: app(BillEntryCalculationService::class);
    }

    public function excute($message = [])
    {
        $str = (string) ($message['text'] ?? '');
        if ($str == '开始记账') {
            $this->checkAuth($message);
            return;
        }
        if (!$this->checkUserAuth($message, !empty($message['_explicit_bot_mention']))) {
            return;
        }
        if ($str == '账单') {
            $this->lookBill($message);
            return;
        }
        if ($str == '清空账单') {
            $this->clearBill($message);
            return;
        }
        if ($str == '入款详情') {
            $this->rukuanDetail($message);
            return;
        }
        if ($str == '下发详情') {
            $this->xiafaDetail($message);
            return;
        }
        if ($str == '撤销下发' || $str == '取消下发') {
            $this->cancelXiafa($message);
            return;
        }
        if ($str == '撤销入款' || $str == '取消入款') {
            $this->cancelRukuan($message);
            return;
        }
        if (mb_substr($str, 0, 4) == '设置汇率') {
            $str1 = trim(str_replace("设置汇率", "", $str));
            if (is_numeric($str1)) {
                $this->setRate($message, $str1);
            }
            return;
        }
        if (mb_substr($str, 0, 4) == '设置费率') {
            $str1 = trim(str_replace("设置费率", "", $str));
            if (is_numeric($str1)) {
                $this->setRate1($message, $str1);
            }
            return;
        }
        if (mb_substr($str, 0, 2) == '入款') {
            $this->handleAmountCommand($message, $str, ['入款'], true);
            return;
        }
        if (mb_substr($str, 0, 2) == '入账') {
            $this->handleAmountCommand($message, $str, ['入账'], true);
            return;
        }
        if (mb_substr($str, 0, 2) == '下发') {
            $this->handleAmountCommand($message, $str, ['下发'], false);
        }
    }

    private function handleAmountCommand(array $message, string $text, array $keywords, bool $isIncome): void
    {
        $amountText = trim(str_replace(array_merge($keywords, ['+']), '', $text));
        $type = strtoupper(mb_substr($amountText, -1)) == 'U' ? 2 : 1;
        if ($type == 2) {
            $amountText = trim(str_replace(['U', 'u'], '', $amountText));
        }
        if (!is_numeric($amountText) || floatval($amountText) <= 0) {
            $this->telegram->sendMessage(['chat_id' => $message['chat']['id'], 'text' => '金额无效，记账金额必须大于0', 'parse_mode' => 'html', 'reply_to_message_id' => $message['message_id']]);
            return;
        }

        $isIncome ? $this->inBill($message, $type, floatval($amountText)) : $this->outBill($message, $type, floatval($amountText));
    }

    public function cnytousd($amount, $rate)
    {
        if ($rate == 0) {
            return 0;
        }

        return $this->bob_unit_format($amount / $rate);
    }

    public function rukuanDetail($message = [])
    {
        $result = $this->checkUserAuth($message);
        if ($result) {
            $rate = 0;
            $feeRate = 0;

            $telegram_group_id = $message['chat']['id'];
            $bill = Bill::where('telegram_group_id', $telegram_group_id)->first(['id', 'rate', 'rate1']);
            if ($bill) {
                $rate = floatval($bill->rate);
                $feeRate = floatval($bill->rate1);
            }
            $query = BillLog::where('telegram_group_id', $telegram_group_id)->where('type', 1);
            $summary = $this->summarizeBillLogs($telegram_group_id, 1, $rate, $feeRate);
            $html = "<b>入款（" . $summary['count'] . "笔）：<code>" . $this->bob_unit_format($summary['cny_amount']) . "</code>CNY ｜ <code>" . $this->bob_unit_format($summary['usd_amount']) . "</code>USD</b>\n";
            foreach ($query->orderBy('id', 'desc')->limit(self::DETAIL_LIMIT)->get($this->billLogColumns()) as $item) {
                $html .= $this->formatBillLogLine($item, $rate, $feeRate);
            }
            if ($summary['count'] > self::DETAIL_LIMIT) {
                $html .= "仅显示最近" . self::DETAIL_LIMIT . "笔，更多请到后台查看\n";
            }
            $this->telegram->sendMessage(['chat_id' => $message['chat']['id'], 'text' => $html . "\n", 'parse_mode' => 'html']);
        }
    }

    public function xiafaDetail($message = [])
    {
        $result = $this->checkUserAuth($message);
        if ($result) {
            $rate = 0;
            $feeRate = 0;

            $telegram_group_id = $message['chat']['id'];
            $bill = Bill::where('telegram_group_id', $telegram_group_id)->first(['id', 'rate', 'rate1']);
            if ($bill) {
                $rate = floatval($bill->rate);
                $feeRate = floatval($bill->rate1);
            }
            $query = BillLog::where('telegram_group_id', $telegram_group_id)->where('type', 2);
            $summary = $this->summarizeBillLogs($telegram_group_id, 2, $rate, $feeRate);
            $html = "<b>下发（<code>" . $summary['count'] . "</code>笔）：<code>" . $this->bob_unit_format($summary['cny_amount']) . "</code>CNY ｜ <code>" . $this->bob_unit_format($summary['usd_amount']) . "</code>USD</b>\n";
            foreach ($query->orderBy('id', 'desc')->limit(self::DETAIL_LIMIT)->get($this->billLogColumns()) as $item) {
                $html .= $this->formatBillLogLine($item, $rate, $feeRate);
            }
            if ($summary['count'] > self::DETAIL_LIMIT) {
                $html .= "仅显示最近" . self::DETAIL_LIMIT . "笔，更多请到后台查看\n";
            }
            $this->telegram->sendMessage(['chat_id' => $message['chat']['id'], 'text' => $html . "\n", 'parse_mode' => 'html']);
        }
    }

    public function lookBill($message = [])
    {
        $result = $this->checkUserAuth($message);
        if (!$result) {
            return;
        }

        $rate = 0;
        $feeRate = 0;

        $telegram_group_id = $message['chat']['id'];
        $bill = Bill::where('telegram_group_id', $telegram_group_id)->first(['id', 'rate', 'rate1']);
        if ($bill) {
            $rate = floatval($bill->rate);
            $feeRate = floatval($bill->rate1);
        }

        $ruQuery = BillLog::where('telegram_group_id', $telegram_group_id)->where('type', 1);
        $chuQuery = BillLog::where('telegram_group_id', $telegram_group_id)->where('type', 2);
        $ruSummary = $this->summarizeBillLogs($telegram_group_id, 1, $rate, $feeRate);
        $chuSummary = $this->summarizeBillLogs($telegram_group_id, 2, $rate, $feeRate);
        $unpaidCny = $ruSummary['payable_cny_amount'] - $chuSummary['cny_amount'];
        $unpaidUsd = $ruSummary['payable_usd_amount'] - $chuSummary['usd_amount'];

        $html = "<b>入款（" . $ruSummary['count'] . "笔）：<code>" . $this->bob_unit_format($ruSummary['cny_amount']) . "</code>CNY ｜ <code>" . $this->bob_unit_format($ruSummary['usd_amount']) . "</code>USD</b>\n";
        foreach ($ruQuery->orderBy('id', 'desc')->limit(5)->get($this->billLogColumns()) as $item) {
            $html .= $this->formatBillLogLine($item, $rate, $feeRate);
        }
        if ($ruSummary['count'] > 5) {
            $html .= "请输入【<code>入款详情</code>】,查看更多\n";
        }
        $html .= "\n\n";

        $html .= "<b>下发（<code>" . $chuSummary['count'] . "</code>笔）：<code>" . $this->bob_unit_format($chuSummary['cny_amount']) . "</code>CNY ｜ <code>" . $this->bob_unit_format($chuSummary['usd_amount']) . "</code>USD</b>\n";
        foreach ($chuQuery->orderBy('id', 'desc')->limit(5)->get($this->billLogColumns()) as $item) {
            $html .= $this->formatBillLogLine($item, $rate, $feeRate);
        }
        if ($chuSummary['count'] > 5) {
            $html .= "请输入【<code>下发详情</code>】，查看更多\n";
        }
        $html .= "\n\n";

        $html .= "<b>当前汇率（下一笔生效）：</b><code>" . $this->bob_unit_format($rate) . "</code>\n";
        $html .= "<b>当前费率（下一笔生效）：</b><code>" . $this->bob_unit_format($feeRate) . "%</code>\n";
        $html .= "<b>总入款：</b><code>" . $this->bob_unit_format($ruSummary['cny_amount']) . "</code>CNY ｜ <code>" . $this->bob_unit_format($ruSummary['usd_amount']) . "</code>USD\n";
        $html .= "<b>应下发：</b><code>" . $this->bob_unit_format($ruSummary['payable_cny_amount']) . "</code>CNY ｜ <code>" . $this->bob_unit_format($ruSummary['payable_usd_amount']) . "</code>USD\n";
        $html .= "<b>总下发：</b><code>" . $this->bob_unit_format($chuSummary['cny_amount']) . "</code>CNY ｜ <code>" . $this->bob_unit_format($chuSummary['usd_amount']) . "</code>USD\n";
        $html .= "<b>未下发：</b><code>" . $this->bob_unit_format($unpaidCny) . "</code>CNY ｜ <code>" . $this->bob_unit_format($unpaidUsd) . "</code>USD\n";

        $this->telegram->sendMessage(['chat_id' => $message['chat']['id'], 'text' => $html, 'parse_mode' => 'html']);
    }

    public function setRate($message = [], $rate = 0)
    {
        $result = $this->checkUserAuth($message);
        if (!$result) {
            return;
        }
        if (!$this->validateRate($message, $rate, '汇率')) {
            return;
        }

        $telegram_group_id = $message['chat']['id'];
        $bill = Bill::where('telegram_group_id', $telegram_group_id)->first(['id']);
        if ($bill) {
            Bill::where("id", $bill->id)->update(['rate' => $rate]);
            $this->telegram->sendMessage(['chat_id' => $message['chat']['id'], 'text' => "汇率设置成功：" . $rate, 'parse_mode' => 'html', 'reply_to_message_id' => $message['message_id']]);
        }
    }

    public function setRate1($message = [], $rate = 0)
    {
        $result = $this->checkUserAuth($message);
        if (!$result) {
            return;
        }
        if (!$this->validateRate($message, $rate, '费率')) {
            return;
        }

        $telegram_group_id = $message['chat']['id'];
        $bill = Bill::where('telegram_group_id', $telegram_group_id)->first(['id', 'rate']);
        if ($bill) {
            Bill::where("id", $bill->id)->update(['rate1' => $rate]);
            $this->telegram->sendMessage(['chat_id' => $message['chat']['id'], 'text' => "费率设置成功：" . $rate, 'parse_mode' => 'html', 'reply_to_message_id' => $message['message_id']]);
        }
    }

    private function validateRate(array $message, $rate, string $name): bool
    {
        if ($rate < 0) {
            $this->telegram->sendMessage(['chat_id' => $message['chat']['id'], 'text' => "{$name}无效，必须是大于等于0的数！", 'parse_mode' => 'html', 'reply_to_message_id' => $message['message_id']]);
            return false;
        }
        if ($name === '费率' && $rate > 100) {
            $this->telegram->sendMessage(['chat_id' => $message['chat']['id'], 'text' => '费率无效，必须在0到100之间！', 'parse_mode' => 'html', 'reply_to_message_id' => $message['message_id']]);
            return false;
        }
        if (strpos((string) $rate, '.') !== false && strlen(substr(strrchr((string) $rate, '.'), 1)) > 2) {
            $this->telegram->sendMessage(['chat_id' => $message['chat']['id'], 'text' => "{$name}无效，{$name}最多保留2位小数！", 'parse_mode' => 'html', 'reply_to_message_id' => $message['message_id']]);
            return false;
        }

        return true;
    }

    public function clearBill($message = [])
    {
        $result = $this->checkUserAuth($message);
        if (!$result) {
            return;
        }

        $telegram_group_id = $message['chat']['id'];
        $bill = Bill::where('telegram_group_id', $telegram_group_id)->first(['id', 'rate']);
        if ($bill) {
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '确认清空', 'callback_data' => json_encode(['type' => 5, 'action' => 'confirm'])],
                        ['text' => '取消清空', 'callback_data' => json_encode(['type' => 5, 'action' => 'cancel'])],
                    ]
                ],
            ];
            Cache::put($this->clearBillActionKey($message), 1, now()->addMinutes(30));
            $this->telegram->sendMessage(['chat_id' => $message['chat']['id'], 'text' => '清空将无法恢复，确定清空账单？', 'reply_markup' => json_encode($keyboard), 'parse_mode' => 'html']);
            return;
        }
        $this->telegram->sendMessage(['chat_id' => $message['chat']['id'], 'text' => "你还没有开始记账", 'parse_mode' => 'html', 'reply_to_message_id' => $message['message_id']]);
    }

    public function callbackClearBill($data = [], $message = [])
    {
        $actionKey = $this->clearBillActionKey($message, true);
        if (!Cache::get($actionKey)) {
            $this->answerCallbackAlert($message, '您不是命令发起人，无权操作此按钮');
            return;
        }
        if (!$this->lockCallbackAction($message, 'clear_bill')) {
            return;
        }

        if (isset($data['action']) && $data['action'] == 'confirm') {
            $telegram_group_id = $message['message']['chat']['id'];
            $bill = Bill::where('telegram_group_id', $telegram_group_id)->first(['id']);
            if ($bill) {
                Bill::where('id', $bill->id)->update(['rate' => 0, 'rate1' => 0, 'ru_total_amount' => 0, 'chu_total_amount' => 0]);
                BillLog::where('telegram_group_id', $telegram_group_id)->delete();
                $this->telegram->editMessageReplyMarkup(['chat_id' => $message['message']['chat']['id'], 'message_id' => $message['message']['message_id'], 'reply_markup' => json_encode($this->keyboard)]);
                $this->telegram->sendMessage(['chat_id' => $message['message']['chat']['id'], 'text' => "已清空账单", 'parse_mode' => 'html', 'reply_to_message_id' => $message['message']['message_id']]);
                Cache::forget($actionKey);
            }
        }
        if (isset($data['action']) && $data['action'] == 'cancel') {
            $this->telegram->editMessageReplyMarkup(['chat_id' => $message['message']['chat']['id'], 'message_id' => $message['message']['message_id'], 'reply_markup' => json_encode($this->keyboard)]);
            $this->telegram->editMessageText(['chat_id' => $message['message']['chat']['id'], 'message_id' => $message['message']['message_id'], 'text' => "您已取消清空账单"]);
            Cache::forget($actionKey);
        }
    }

    private function clearBillActionKey(array $message, bool $callback = false): string
    {
        $chatId = $callback ? ($message['message']['chat']['id'] ?? 0) : ($message['chat']['id'] ?? 0);

        return ($message['from']['id'] ?? 0) . $chatId . "_clear_bill";
    }

    public function checkUserAuth($message = [], bool $notify = true)
    {
        $user_id = $message['from']['id'];
        $telegram_group_id = $message['chat']['id'];
        if (BillUser::where('user_id', $user_id)->where('telegram_group_id', $telegram_group_id)->exists()) {
            return true;
        }

        if ($notify) {
            $this->telegram->sendMessage(['chat_id' => $message['chat']['id'], 'text' => "您没有记账权限，请输入 <code>开始记账</code> 申请记账权限", 'parse_mode' => 'html', 'reply_to_message_id' => $message['message_id']]);
        }

        return false;
    }

    public function checkRate($message = [])
    {
        return true;
    }

    public function inBill($message = [], $type = 1, $amount = 0)
    {
        if (!$this->checkUserAuth($message)) {
            return;
        }

        if ($this->recordBillEntry($message, (int)$type, (float)$amount, true)) {
            $this->lookBill($message);
        }
    }

    public function outBill($message = [], $type = 1, $amount = 0)
    {
        if (!$this->checkUserAuth($message)) {
            return;
        }

        if ($this->recordBillEntry($message, (int)$type, (float)$amount, false)) {
            $this->lookBill($message);
        }
    }

    private function recordBillEntry(array $message, int $amountType, float $originalAmount, bool $isIncome): bool
    {
        $result = DB::transaction(function () use ($message, $amountType, $originalAmount, $isIncome) {
            $telegramGroupId = $message['chat']['id'];
            $bill = Bill::where('telegram_group_id', $telegramGroupId)->lockForUpdate()->first(['id', 'rate', 'rate1']);
            if (!$bill) {
                return ['status' => false, 'message' => '你还没有开始记账'];
            }

            try {
                $snapshot = $this->calculationService->calculate(
                    $originalAmount,
                    $amountType === 2 ? BillEntryCalculationService::CURRENCY_USDT : BillEntryCalculationService::CURRENCY_CNY,
                    (float)$bill->rate,
                    (float)$bill->rate1,
                    $isIncome
                );
            } catch (InvalidArgumentException $exception) {
                return ['status' => false, 'message' => $exception->getMessage()];
            }

            BillLog::create(array_merge($snapshot, [
                'type' => $isIncome ? 1 : 2,
                'user_id' => $message['from']['id'],
                'telegram_group_id' => $telegramGroupId,
            ]));
            Bill::where('id', $bill->id)->increment($isIncome ? 'ru_total_amount' : 'chu_total_amount', $snapshot['amount']);

            return ['status' => true, 'message' => ''];
        }, 3);

        if (!$result['status']) {
            $this->telegram->sendMessage(['chat_id' => $message['chat']['id'], 'text' => $result['message'], 'parse_mode' => 'html', 'reply_to_message_id' => $message['message_id']]);
        }

        return $result['status'];
    }

    public function checkAuth($message = [])
    {
        $user_id = $message['from']['id'];
        $telegram_group_id = $message['chat']['id'];
        if (BillUser::where('user_id', $user_id)->where('telegram_group_id', $telegram_group_id)->exists()) {
            $this->telegram->sendMessage(['chat_id' => $message['chat']['id'], 'text' => "您已有记账权限，请直接使用", 'reply_to_message_id' => $message['message_id']]);
        } else {
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '确认授权', 'callback_data' => json_encode(['type' => 2, 'action' => 'confirm', 'value' => $message['from']['id']])],
                        ['text' => '取消授权', 'callback_data' => json_encode(['type' => 2, 'action' => 'cancel', 'value' => $message['from']['id']])],
                    ]
                ],
            ];
            $this->telegram->sendMessage(['chat_id' => $message['chat']['id'], 'text' => "请管理员授权", 'reply_markup' => json_encode($keyboard), 'parse_mode' => 'html', 'reply_to_message_id' => $message['message_id']]);
        }
    }

    public function auth($data = [], $message = [])
    {
        if (!$this->lockCallbackAction($message, 'bill_auth')) {
            return;
        }

        if (isset($data['action']) && $data['action'] == 'confirm') {
            $user_id = $data['value'];
            $telegram_group_id = $message['message']['chat']['id'];
            if (BillUser::where('user_id', $user_id)->where('telegram_group_id', $telegram_group_id)->exists()) {
                $this->telegram->editMessageReplyMarkup(['chat_id' => $message['message']['chat']['id'], 'message_id' => $message['message']['message_id'], 'reply_markup' => json_encode($this->keyboard)]);
                $this->telegram->editMessageText(['chat_id' => $message['message']['chat']['id'], 'message_id' => $message['message']['message_id'], 'text' => "您已有记账权限，请直接使用"]);
            } else {
                BillUser::create([
                    'user_id' => $user_id,
                    'telegram_group_id' => $telegram_group_id,
                ]);
                Bill::firstOrCreate(['telegram_group_id' => $telegram_group_id]);
                $this->telegram->editMessageReplyMarkup(['chat_id' => $message['message']['chat']['id'], 'message_id' => $message['message']['message_id'], 'reply_markup' => json_encode($this->keyboard)]);
                $this->telegram->editMessageText(['chat_id' => $message['message']['chat']['id'], 'message_id' => $message['message']['message_id'], 'text' => "授权成功，请输入<code>设置汇率</code>+数字，更多命令，请输入<code>help</code>", 'parse_mode' => 'html']);
            }
            return;
        }
        if (isset($data['action']) && $data['action'] == 'cancel') {
            $this->telegram->editMessageReplyMarkup(['chat_id' => $message['message']['chat']['id'], 'message_id' => $message['message']['message_id'], 'reply_markup' => json_encode($this->keyboard)]);
            $this->telegram->editMessageText(['chat_id' => $message['message']['chat']['id'], 'message_id' => $message['message']['message_id'], 'text' => "对不起，你没有被允许记账功能"]);
        }
    }

    private function answerCallbackAlert(array $message, string $text): void
    {
        $callbackQueryId = (string)($message['id'] ?? '');
        if ($callbackQueryId === '') {
            return;
        }

        $this->telegram->answerCallbackQuery([
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => true,
        ]);
    }

    private function lockCallbackAction(array $message, string $action): bool
    {
        $chatId = $message['message']['chat']['id'] ?? 0;
        $messageId = $message['message']['message_id'] ?? 0;
        $fromId = $message['from']['id'] ?? 0;

        return Cache::add("telegram_bill_action:{$action}:{$chatId}:{$messageId}:{$fromId}", 1, now()->addSeconds(5));
    }

    public function cancelXiafa($message = [])
    {
        $auth_result = $this->checkUserAuth($message);
        if (!$auth_result) {
            return;
        }
        $rate_result = $this->checkRate($message);
        if (!$rate_result) {
            return;
        }

        $telegram_group_id = $message['chat']['id'];
        $bill = Bill::where('telegram_group_id', $telegram_group_id)->first(['id']);
        if (!$bill) {
            return;
        }

        $result = BillLog::where("type", 2)->where('telegram_group_id', $telegram_group_id)->orderBy('id', 'desc')->first(['id', 'amount']);
        if ($result) {
            Bill::where('id', $bill->id)->increment('chu_total_amount', -$result->amount);
            BillLog::destroy($result->id);
            $this->lookBill($message);
        } else {
            $this->telegram->sendMessage(['chat_id' => $message['chat']['id'], 'text' => "未查询到下发记录，撤销失败", 'parse_mode' => 'html', 'reply_to_message_id' => $message['message_id']]);
        }
    }

    public function cancelRukuan($message = [])
    {
        $auth_result = $this->checkUserAuth($message);
        if (!$auth_result) {
            return;
        }
        $rate_result = $this->checkRate($message);
        if (!$rate_result) {
            return;
        }

        $telegram_group_id = $message['chat']['id'];
        $bill = Bill::where('telegram_group_id', $telegram_group_id)->first(['id']);
        if (!$bill) {
            return;
        }

        $result = BillLog::where("type", 1)->where('telegram_group_id', $telegram_group_id)->orderBy('id', 'desc')->first(['id', 'amount']);
        if ($result) {
            Bill::where('id', $bill->id)->increment('ru_total_amount', -$result->amount);
            BillLog::destroy($result->id);
            $this->lookBill($message);
        } else {
            $this->telegram->sendMessage(['chat_id' => $message['chat']['id'], 'text' => "未查询到入款记录，撤销失败", 'parse_mode' => 'html', 'reply_to_message_id' => $message['message_id']]);
        }
    }

    private function summarizeBillLogs(int $telegramGroupId, int $type, float $fallbackRate, float $fallbackFeeRate): array
    {
        $summary = [
            'count' => 0,
            'cny_amount' => 0.0,
            'usd_amount' => 0.0,
            'payable_cny_amount' => 0.0,
            'payable_usd_amount' => 0.0,
        ];

        BillLog::where('telegram_group_id', $telegramGroupId)->where('type', $type)->select($this->billLogColumns())->chunkById(500, function ($logs) use (&$summary, $fallbackRate, $fallbackFeeRate) {
            foreach ($logs as $log) {
                $amounts = $this->resolveBillLogAmounts($log, $fallbackRate, $fallbackFeeRate);
                $summary['count']++;
                $summary['cny_amount'] += $amounts['cny_amount'];
                $summary['usd_amount'] += $amounts['usd_amount'];
                $summary['payable_cny_amount'] += $amounts['payable_cny_amount'];
                $summary['payable_usd_amount'] += $amounts['payable_usd_amount'];
            }
        });

        foreach (['cny_amount', 'usd_amount', 'payable_cny_amount', 'payable_usd_amount'] as $key) {
            $summary[$key] = round($summary[$key], 6, PHP_ROUND_HALF_UP);
        }

        return $summary;
    }

    private function resolveBillLogAmounts(BillLog $log, float $fallbackRate, float $fallbackFeeRate): array
    {
        $cnyAmount = (float)$log->amount;
        $exchangeRate = $log->exchange_rate === null ? $fallbackRate : (float)$log->exchange_rate;
        $feeRate = $log->fee_rate === null ? $fallbackFeeRate : (float)$log->fee_rate;
        $isOriginalUsdt = $log->original_currency === BillEntryCalculationService::CURRENCY_USDT && $log->original_amount !== null;
        $usdAmount = $isOriginalUsdt ? (float)$log->original_amount : $this->divideAmount($cnyAmount, $exchangeRate);

        if ((int)$log->type === 1) {
            $payableCnyAmount = $log->payable_amount === null
                ? round($cnyAmount * (100 - $feeRate) / 100, 2, PHP_ROUND_HALF_UP)
                : (float)$log->payable_amount;
            $payableUsdAmount = $isOriginalUsdt
                ? round($usdAmount * (100 - $feeRate) / 100, 6, PHP_ROUND_HALF_UP)
                : $this->divideAmount($payableCnyAmount, $exchangeRate);
        } else {
            $payableCnyAmount = $cnyAmount;
            $payableUsdAmount = $usdAmount;
        }

        return [
            'cny_amount' => $cnyAmount,
            'usd_amount' => $usdAmount,
            'payable_cny_amount' => $payableCnyAmount,
            'payable_usd_amount' => $payableUsdAmount,
            'exchange_rate' => $exchangeRate,
            'fee_rate' => $feeRate,
        ];
    }

    private function formatBillLogLine(BillLog $log, float $fallbackRate, float $fallbackFeeRate): string
    {
        $amounts = $this->resolveBillLogAmounts($log, $fallbackRate, $fallbackFeeRate);

        return '<b>' . $log->created_at . '</b>     <code>' . $this->bob_unit_format($amounts['cny_amount']) . '</code>CNY ｜ <code>' . $this->bob_unit_format($amounts['usd_amount']) . '</code>USD'
            . "\n汇率：<code>" . $this->bob_unit_format($amounts['exchange_rate']) . '</code> ｜ 费率：<code>' . $this->bob_unit_format($amounts['fee_rate']) . "%</code>\n";
    }

    private function billLogColumns(): array
    {
        return ['id', 'type', 'created_at', 'amount', 'original_currency', 'original_amount', 'exchange_rate', 'fee_rate', 'payable_amount'];
    }

    private function divideAmount(float $amount, float $rate): float
    {
        return $rate > 0 ? round($amount / $rate, 6, PHP_ROUND_HALF_UP) : 0;
    }

    public function bob_unit_format($amount = 0)
    {
        return bob_split_number(bob_amount_format($amount, 3));
    }
}
