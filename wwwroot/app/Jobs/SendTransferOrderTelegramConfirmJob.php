<?php

namespace App\Jobs;

use Throwable;
use App\Models\BankCode;
use App\Models\TransferOrder;
use App\Models\MerchantTelegramAdmin;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\App;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\Telegram\TelegramLangService;
use App\Services\Common\ReportExceptionService;
use App\Services\Telegram\TelegramInstanceService;
use App\Services\Cache\Merchant\CacheMerchantBaseInfoService;

class SendTransferOrderTelegramConfirmJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public TransferOrder $order;

    public function __construct(TransferOrder $order)
    {
        $this->order = $order;
    }

    public function handle(): void
    {
        $merchantInfo = App::make(CacheMerchantBaseInfoService::class)->excute($this->order->mid);
        $telegramGroupId = intval($merchantInfo['telegram_group_id'] ?? 0);
        if ($telegramGroupId === 0) {
            return;
        }

        try {
            $telegram = app(TelegramInstanceService::class)->excute();
            $langService = app(TelegramLangService::class);
            $lang = $langService->merchantLang(intval($this->order->mid));
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => "确认【" . $langService->text('confirm_action', $lang) . "】", 'callback_data' => json_encode(['type' => 13, 'order_id' => $this->order->id])],
                        ['text' => "取消【" . $langService->text('cancel_action', $lang) . "】", 'callback_data' => json_encode(['type' => 14, 'order_id' => $this->order->id])],
                    ]
                ],
            ];

            $html = "‼️‼️📢" . $langService->text('transfer_confirm_notice', 'zh_CN') . "【" . $langService->text('transfer_confirm_notice', $lang) . "】";
            $html .= "\n\n";
            $html .= $langService->text('platform_order_no', 'zh_CN') . "【" . $langService->text('platform_order_no', $lang) . "】：" . $this->escape($this->order->ordernumber);
            $html .= "\n" . $langService->text('merchant_order_no', 'zh_CN') . "【" . $langService->text('merchant_order_no', $lang) . "】：" . $this->escape($this->order->order_no);
            $html .= "\n" . $langService->text('merchant_name', 'zh_CN') . "【" . $langService->text('merchant_name', $lang) . "】：" . $this->escape($merchantInfo['bname'] ?? '');
            $html .= "\n" . $langService->text('submitted_amount', 'zh_CN') . "【" . $langService->text('submitted_amount', $lang) . "】：" . $this->escape(bob_unit_format($this->order->amount));
            $html .= "\n" . $langService->text('bank_name', 'zh_CN') . "【" . $langService->text('bank_name', $lang) . "】：" . $this->escape($this->getBankName());
            $html .= "\n" . $langService->text('bank_account', 'zh_CN') . "【" . $langService->text('bank_account', $lang) . "】：" . $this->escape($this->order->card_no);
            $html .= "\n" . $langService->text('account_holder', 'zh_CN') . "【" . $langService->text('account_holder', $lang) . "】：" . $this->escape($this->order->holder_name);
            $adminMentions = $this->merchantTelegramAdminMentions($telegramGroupId);
            if ($adminMentions !== '') {
                $html .= "\n\n" . $adminMentions;
            }
            $telegram->sendMessage(['chat_id' => $telegramGroupId, 'text' => $html, 'parse_mode' => 'html', 'reply_markup' => json_encode($keyboard)]);
        } catch (Throwable $e) {
            App::make(ReportExceptionService::class)->report('代付订单 Telegram 确认消息发送失败', $e, [
                'order_id' => $this->order->id ?? 0,
                'ordernumber' => $this->order->ordernumber ?? '',
                'mid' => $this->order->mid ?? 0,
                'telegram_group_id' => $telegramGroupId,
            ]);
        }
    }

    private function merchantTelegramAdminMentions(int $telegramGroupId): string
    {
        $admins = MerchantTelegramAdmin::query()
            ->where('mid', $this->order->mid)
            ->where('telegram_group_id', $telegramGroupId)
            ->orderBy('id')
            ->get(['telegram_user_id', 'telegram_username', 'telegram_name']);

        return $admins->map(function (MerchantTelegramAdmin $admin) {
            $username = trim((string)$admin->telegram_username);
            if ($username !== '') {
                return '@' . ltrim($this->escape($username), '@');
            }

            $telegramUserId = intval($admin->telegram_user_id);
            if ($telegramUserId <= 0) {
                return '';
            }

            $name = trim((string)$admin->telegram_name) ?: (string)$telegramUserId;
            return '<a href="tg://user?id=' . $telegramUserId . '">' . $this->escape($name) . '</a>';
        })->filter()->implode(' ');
    }

    private function getBankName(): string
    {
        if (!empty($this->order->bank_name)) {
            return $this->order->bank_name;
        }

        $result = BankCode::where('code', $this->order->bank_code)->where('currency_id', $this->order->currency_id)->first(['name']);
        if ($result) {
            return $result->name;
        }
        return (string) $this->order->bank_code;
    }

    private function escape($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
