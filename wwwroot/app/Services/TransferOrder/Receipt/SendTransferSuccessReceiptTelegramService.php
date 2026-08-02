<?php

namespace App\Services\TransferOrder\Receipt;

use Throwable;
use App\Models\TransferOrder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Telegram\Bot\FileUpload\InputFile;
use App\Services\Common\ReportExceptionService;

class SendTransferSuccessReceiptTelegramService
{
    public function send($telegram, int $chatId, TransferOrder $order, int $replyToMessageId = 0, string $lang = 'zh_CN'): bool
    {
        if ($chatId === 0 || !config('transfer-receipt.enabled', true)) {
            return true;
        }

        $lockKey = 'transfer_success_receipt:query:' . $chatId . ':' . $order->id;
        if (!Cache::add($lockKey, 1, now()->addSeconds(10))) {
            return true;
        }

        try {
            $imagePath = App::make(TransferSuccessReceiptImageService::class)->make($order, $lang);
            $payload = [
                'chat_id' => $chatId,
                'photo' => new InputFile($imagePath),
                'caption' => $this->caption($order, $lang),
                'parse_mode' => 'html',
            ];

            if ($replyToMessageId > 0) {
                $payload['reply_to_message_id'] = $replyToMessageId;
            }

            $telegram->sendPhoto($payload);
            return true;
        } catch (Throwable $e) {
            App::make(ReportExceptionService::class)->report('代付成功回执单发送失败', $e, [
                'order_id' => $order->id,
                'ordernumber' => $order->ordernumber,
                'mid' => $order->mid,
            ]);

            return false;
        } finally {
            Cache::forget($lockKey);
        }
    }

    private function caption(TransferOrder $order, string $lang): string
    {
        if ($lang === 'en') {
            return "Payout success receipt\nPlatform Order No: " . e($order->ordernumber) . "\nMerchant Order No: " . e($order->order_no);
        }

        return "代付成功回执单\n平台单号：" . e($order->ordernumber) . "\n商户单号：" . e($order->order_no);
    }
}
