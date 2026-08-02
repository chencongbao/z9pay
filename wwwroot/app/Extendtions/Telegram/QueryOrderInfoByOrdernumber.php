<?php

namespace App\Extendtions\Telegram;

use App\Models\TransferOrder;
use App\Traits\TelegramTrait;
use Illuminate\Support\Facades\App;
use App\Services\Order\OrderCacheService;
use App\Services\TransferOrder\Receipt\SendTransferSuccessReceiptTelegramService;

class QueryOrderInfoByOrdernumber
{
    use TelegramTrait;

    protected $telegram;

    public function __construct($telegram)
    {
        $this->telegram = $telegram;
    }

    public function excute($message = [], $group_type = 0): void
    {
        $ordernumber = trim((string)($message['text'] ?? ''));
        $groupType = intval($group_type);
        if ($groupType === 0 || !$this->isValidOrdernumber($ordernumber)) {
            return;
        }

        $prefix = strtoupper(substr($ordernumber, 0, 1));
        $merchantUserId = intval($this->getMerchantUserId($message));
        $userId = $groupType === 2 ? intval($this->getUserId($message)) : 0;
        $isManager = $groupType === 2 && $this->checkIsManager($message);

        $orderCacheService = App::make(OrderCacheService::class);
        $depositOrder = $this->findDepositOrder($orderCacheService, $ordernumber, $merchantUserId, $prefix);
        if (!empty($depositOrder)) {
            $this->sendDepositOrderInfo($message, $depositOrder, $groupType, $merchantUserId, $userId, $isManager);
            return;
        }

        $transferOrder = $this->findTransferOrder($orderCacheService, $ordernumber, $merchantUserId, $prefix);
        if (!empty($transferOrder)) {
            $this->sendTransferOrderInfo($message, $transferOrder, $groupType, $merchantUserId, $userId, $isManager);
        }
    }

    public function sendTransferReceipt($message = [], $group_type = 0): void
    {
        $text = trim((string)($message['text'] ?? ''));
        if (!preg_match('/^(回执单|receipt)\s+([a-zA-Z0-9_-]{6,})$/iu', $text, $matches)) {
            return;
        }

        $isEnglish = strtolower($matches[1]) === 'receipt';
        $groupType = intval($group_type);
        if ($groupType !== 1) {
            return;
        }

        $orderNo = trim((string)$matches[2]);
        $merchantUserId = intval($this->getMerchantUserId($message));
        $orderCacheService = App::make(OrderCacheService::class);
        $result = $merchantUserId > 0 ? $orderCacheService->getTransferByMerchantOrder($merchantUserId, $orderNo) : [];
        if (empty($result) || !$this->canViewOrder($result, $groupType, $merchantUserId, 0, false)) {
            $this->sendOrderInfo($message, $isEnglish ? 'Payout order not found.' : '未查询到代付订单');
            return;
        }

        if (intval($result['status'] ?? 0) !== 4) {
            $this->sendOrderInfo($message, $isEnglish ? 'Only successful payout orders can generate receipts.' : '只有代付成功订单才能生成回执单');
            return;
        }

        $order = TransferOrder::query()
            ->where('type', 0)
            ->where('status', 4)
            ->where('mid', $merchantUserId)
            ->find(intval($result['id'] ?? 0), ['id', 'mid', 'type', 'status', 'amount', 'actual_amount', 'currency_id', 'success_time', 'ordernumber', 'order_no', 'channel_ordernumber', 'utr', 'bank_code', 'bank_name', 'holder_name', 'card_no']);

        if (!$order) {
            $this->sendOrderInfo($message, $isEnglish ? 'Payout order not found.' : '未查询到代付订单');
            return;
        }

        $sent = App::make(SendTransferSuccessReceiptTelegramService::class)->send($this->telegram, intval($message['chat']['id'] ?? 0), $order, intval($message['message_id'] ?? 0), $isEnglish ? 'en' : 'zh_CN');
        if (!$sent) {
            $this->sendOrderInfo($message, $isEnglish ? 'Receipt generation failed, please try again later.' : '回执单生成失败，请稍后再试');
        }
    }

    private function findDepositOrder(OrderCacheService $orderCacheService, string $ordernumber, int $merchantUserId, string $prefix): array
    {
        if ($prefix === 'D') {
            $order = $orderCacheService->getDepositByOrdernumber($ordernumber);
            if (!empty($order)) {
                return $order;
            }
        }

        if ($merchantUserId > 0) {
            return $orderCacheService->getDepositByMerchantOrder($merchantUserId, $ordernumber);
        }

        return $prefix !== 'T' ? $orderCacheService->getDepositByOrdernumber($ordernumber) : [];
    }

    private function findTransferOrder(OrderCacheService $orderCacheService, string $ordernumber, int $merchantUserId, string $prefix): array
    {
        if ($prefix === 'T') {
            $order = $orderCacheService->getTransferByOrdernumber($ordernumber);
            if (!empty($order)) {
                return $order;
            }
        }

        if ($merchantUserId > 0) {
            return $orderCacheService->getTransferByMerchantOrder($merchantUserId, $ordernumber);
        }

        return $prefix !== 'D' ? $orderCacheService->getTransferByOrdernumber($ordernumber) : [];
    }

    private function sendDepositOrderInfo(array $message, array $result, int $groupType, int $merchantUserId, int $userId, bool $isManager): void
    {
        if (!$this->canViewOrder($result, $groupType, $merchantUserId, $userId, $isManager)) {
            return;
        }

        $this->sendOrderInfo($message, $this->depositOrderInfo($result, $groupType));
    }

    private function sendTransferOrderInfo(array $message, array $result, int $groupType, int $merchantUserId, int $userId, bool $isManager): void
    {
        if (!$this->canViewOrder($result, $groupType, $merchantUserId, $userId, $isManager)) {
            return;
        }

        $this->sendOrderInfo($message, $this->transferOrderInfo($result, $groupType));
    }

    private function sendOrderInfo(array $message, ?string $text): void
    {
        $chatId = $message['chat']['id'] ?? 0;
        if (!$chatId || !$text) {
            return;
        }

        $this->telegram->sendMessage(['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'html']);
    }

    private function canViewOrder(array $order, int $groupType, int $merchantUserId, int $userId, bool $isManager): bool
    {
        if ($groupType === 1 && intval($order['mid'] ?? 0) !== $merchantUserId) {
            return false;
        }

        if ($groupType === 2 && !$isManager && isset($order['user_id']) && intval($order['user_id']) !== $userId) {
            return false;
        }

        return true;
    }

    private function isValidOrdernumber(string $ordernumber): bool
    {
        return strlen($ordernumber) >= 6 && preg_match('/^[a-zA-Z0-9_-]+$/', $ordernumber) === 1;
    }
}
