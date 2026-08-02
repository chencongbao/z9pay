<?php

namespace App\Jobs;

use App\Models\MerchantInfo;
use App\Models\TransferOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\Telegram\TelegramInstanceService;

class TransferOrderFailNoticeTelegramGroupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $timeout = 120;

    public $id = 0;

    public $telegram_bot_token;

    public $telegram_turn_on = 0;

    public function __construct($data = [])
    {
        $this->id = $data['id'] ?? 0;
        $this->telegram_bot_token = $data['telegram_bot_token'] ?? '';
        $this->telegram_turn_on = $data['telegram_turn_on'] ?? 0;
    }

    public function handle(): void
    {
        if (intval($this->telegram_turn_on) !== 1 || empty($this->telegram_bot_token)) {
            return;
        }

        $result = TransferOrder::where('id', $this->id)->first(['id', 'mid', 'status', 'type', 'ordernumber', 'order_no', 'remark', 'callback_status']);
        if (!$result || $result->status != 5) {
            return;
        }

        $merchant = MerchantInfo::where('merchant_user_id', $result->mid)->first(['telegram_group_id']);
        if (!$merchant || intval($merchant->telegram_group_id) === 0) {
            return;
        }

        $telegram = app(TelegramInstanceService::class)->excute();
        $text = "";
        if ($result->type == 0) {
            $text .= "<b>代付订单失败通知：</b>\n";
        }
        if ($result->type == 1) {
            $text .= "<b>结算订单失败通知：</b>\n";
        }
        $text .= "平台订单号：<code>" . $result->ordernumber . "</code>\n";
        $text .= "商户订单号：<code>" . $result->order_no . "</code>\n";
        if ($result->remark) {
            $text .= "参考失败原因：" . $result->remark . "\n";
        }
        if ($result->type == 0) {
            $text .= "参考回调状态：<b>【" . ($result->callback_status == 1 ? '回调成功' : '回调失败') . "】</b>";
        }
        $telegram->sendMessage(['chat_id' => $merchant->telegram_group_id, 'text' => $text, 'parse_mode' => 'html']);
    }
}
