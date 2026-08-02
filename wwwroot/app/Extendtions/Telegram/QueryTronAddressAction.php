<?php

namespace App\Extendtions\Telegram;

use App\Models\ListeningAddress;
use App\Extendtions\Tron\WebTron;
use Illuminate\Support\Facades\Cache;
use App\Jobs\QueryTelegramTronAddressJob;
use App\Services\Cache\CacheConstPrefixService;

class QueryTronAddressAction
{
    private const MAX_TRANSACTION_PAGES = 200;

    protected $telegram;

    public function __construct($telegram)
    {
        $this->telegram = $telegram;
    }

    public function excute($message = []): void
    {
        $address = bob_replacement_empty($message['text'] ?? '');
        $chatId = intval($message['chat']['id'] ?? 0);
        if (!$chatId || !$this->isTronAddress($address)) {
            return;
        }

        $key = CacheConstPrefixService::TRONWEB_QUERY_ADDRESS_SEND_TIME . $address;
        if (!Cache::add($key, $address, now()->addMinute())) {
            return;
        }

        $telegram = $this->telegram->sendMessage($this->buildQueryingPayload($message, $chatId));
        dispatch(new QueryTelegramTronAddressJob($address, $chatId, intval($telegram->message_id)))->onQueue('query');
    }

    public function getDetail(WebTron $tron, string $address, ?ListeningAddress $addressInfo = null): string
    {
        $data = [
            'totay_trade' => 0,
            'today_spend' => 0,
            'today_income' => 0,
            'last_30_trade' => 0,
            'last_30_income' => 0,
            'last_30_spend' => 0,
        ];

        $today = date('Y-m-d');
        $startTime = strtotime(date('Y-m-d', time() - 30 * 24 * 60 * 60) . ' 00:00:00');
        for ($i = 0; $i < self::MAX_TRANSACTION_PAGES; $i++) {
            $result = $tron->getUSDTTransactionsToAddress($address, $startTime, 50, 0, $i * 50);
            if (!empty($result)) {
                foreach ($result as $item) {
                    if (date('Y-m-d', $item['block_timestamp'] / 1000) === $today) {
                        $data['totay_trade'] += 1;
                        if ($item['direction'] == 1) {
                            $data['today_spend'] += $item['amount'];
                        }
                        if ($item['direction'] == 2) {
                            $data['today_income'] += $item['amount'];
                        }
                    }
                    $data['last_30_trade'] += 1;
                    if ($item['direction'] == 1) {
                        $data['last_30_spend'] += $item['amount'];
                    }
                    if ($item['direction'] == 2) {
                        $data['last_30_income'] += $item['amount'];
                    }
                }
            } else {
                break;
            }
        }
        $data['today_spend'] = $tron->wallet->tron->fromTron($data['today_spend']);
        $data['today_income'] = $tron->wallet->tron->fromTron($data['today_income']);
        $data['last_30_spend'] = $tron->wallet->tron->fromTron($data['last_30_spend']);
        $data['last_30_income'] = $tron->wallet->tron->fromTron($data['last_30_income']);

        return $this->getContent($address, $data, $addressInfo);
    }

    public function getContent(?string $address = null, array $info = [], ?ListeningAddress $model = null): string
    {
        $content = "USDT余额查询\n\n";
        $content .= "查询地址：<code>" . $address . "</code>\n\n";

        $model = $model ?: ListeningAddress::where('address', $address)->first();
        if (!empty($model)) {
            $content .= "<b>TRX余额：</b><code>" . floatval($model->trx_balance) . "</code>\n";
            $content .= "<b>USDT(TRC20)余额：</b><code>" . floatval($model->usdt_balance) . "</code>\n\n";
        }

        if (!empty($info)) {
            $content .= "<b>今日交易：</b><code>" . ($info['totay_trade'] ?? 0) . " 笔</code>\n";
            $content .= "<b>今日支出：</b><code>" . ($info['today_spend'] ?? 0) . "</code> USDT\n";
            $content .= "<b>今日收入：</b><code>" . ($info['today_income'] ?? 0) . "</code> USDT\n";
            $content .= "<b>近30天交易：</b><code>" . ($info['last_30_trade'] ?? 0) . " 笔</code>\n";
            $content .= "<b>近30天收入：</b><code>" . ($info['last_30_income'] ?? 0) . "</code> USDT\n";
            $content .= "<b>近30天支出：</b><code>" . ($info['last_30_spend'] ?? 0) . "</code> USDT\n\n";
        }

        $content .= '<a href="https://tronscan.org/#/address/' . $address . '/transfers">点击查询交易记录</a>';

        return $content;
    }

    private function isTronAddress(string $address): bool
    {
        return preg_match('/^T[a-zA-Z0-9]{33}$/', $address) === 1;
    }

    private function buildQueryingPayload(array $message, int $chatId): array
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => '正在查询 USDT 地址，请稍候...',
            'parse_mode' => 'html',
            'disable_web_page_preview' => true,
        ];
        if (!empty($message['message_id'])) {
            $payload['reply_to_message_id'] = $message['message_id'];
        }

        return $payload;
    }
}
