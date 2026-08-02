<?php

namespace App\Jobs;

use Throwable;
use Illuminate\Bus\Queueable;
use App\Extendtions\Tron\WebTron;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\SystemNotice\SystemNoticeService;
use App\Services\Telegram\TelegramInstanceService;
use App\Services\Cache\ListeningTronAddress\GetListeningTronAddressService;

class HandleListeningAddressResultJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public int $uniqueFor = 86400;

    public array $data = [];

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    public function uniqueId(): string
    {
        return implode(':', [
            $this->data['address'] ?? '',
            $this->data['tx_id'] ?? '',
            $this->data['type'] ?? '',
        ]);
    }

    public function handle(): void
    {
        if (!$this->validData()) {
            logger()->warning('波场监听结果数据不完整', ['data' => $this->data]);
            return;
        }

        $address = $this->data['address'];
        if (!$this->acquireExecutedLock()) {
            return;
        }

        $info = $this->updateTodayStats($address, $this->data['type'], $this->data['amount']);
        $this->data = array_merge($this->data, $this->balanceData($address));

        $telegram = app(TelegramInstanceService::class)->excute();
        $content = $this->getContent($address, $info, $this->data);

        if (!$content) {
            return;
        }

        foreach (app(GetListeningTronAddressService::class)->chatIdsByAddress($address) as $chatId) {
            try {
                $telegram->sendMessage(['chat_id' => $chatId, 'text' => $content, 'parse_mode' => 'html', 'disable_web_page_preview' => true]);
            } catch (Throwable $e) {
                app(SystemNoticeService::class)->warning('tron_listening_notice_failed', [
                    'error' => '波场监听通知发送失败',
                    'address' => $address,
                    'chat_id' => $chatId,
                    'exception_type' => get_class($e),
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }

    public function getContent($address = null, $info = [], $data = []): string
    {
        $content = "USDT交易监听\n";
        $content .= "监听地址：<code>" . $address . "</code>\n\n";

        if (!empty($data)) {
            $content .= "<b>交易类型：</b><code>" . $data['type'] . "</code>\n";
            $content .= "<b>出账地址：</b><code>" . $data['from_address'] . "</code>\n";
            $content .= "<b>入账地址：</b><code>" . $data['to_address'] . "</code>\n";
            $content .= "<b>交易时间：</b><code>" . $data['time'] . "</code>\n";
            $content .= "<b>交易HASH：</b><code>" . $data['tx_id'] . "</code>\n";
            $content .= "<b>交易金额：</b><code>" . ($data['amount'] ?? 0) . "</code> USDT\n\n";
        }

        if (!empty($info)) {
            $content .= "<b>今日收入：</b><code>" . ($info['today_income'] ?? 0) . "</code> USDT\n";
            $content .= "<b>今日支出：</b><code>" . ($info['today_spend'] ?? 0) . "</code> USDT\n";
            $content .= "<b>今日交易：</b><code>" . ($info['today_trade'] ?? 0) . "</code> 笔\n\n";
        }

        $content .= "<b>交易前余额：</b><code>" . $data['qian_usdt_balance'] . "</code>\n";
        $content .= "<b>交易后余额：</b><code>" . $data['usdt_balance'] . "</code>\n\n";

        $content .= '<a href="https://tronscan.org/#/address/' . $address . '/transfers">点击查询交易记录</a>';
        return $content;
    }

    private function validData(): bool
    {
        foreach (['type', 'address', 'amount', 'tx_id', 'from_address', 'to_address', 'time'] as $field) {
            if (!array_key_exists($field, $this->data) || $this->data[$field] === '') {
                return false;
            }
        }

        return in_array($this->data['type'], ['收入', '支出'], true);
    }

    private function updateTodayStats(string $address, string $type, $amount): array
    {
        $key = CacheConstPrefixService::TRRONWEB_LISTENING_ADDRESS . $address . "_" . date('Ymd');
        $lock = Cache::lock($key . ':lock', 5);

        return $lock->block(3, function () use ($key, $type, $amount) {
            $info = Cache::get($key, ['today_income' => 0, 'today_trade' => 0, 'today_spend' => 0]);
            $info['today_trade'] = (int)($info['today_trade'] ?? 0) + 1;

            if ($type === '收入') {
                $info['today_income'] = bcadd((string)($info['today_income'] ?? 0), (string)$amount, 6);
            }

            if ($type === '支出') {
                $info['today_spend'] = bcadd((string)($info['today_spend'] ?? 0), (string)$amount, 6);
            }

            Cache::put($key, $info, now()->addDay());
            return $info;
        });
    }

    private function acquireExecutedLock(): bool
    {
        return Cache::add('tron:listening:executed:' . md5($this->uniqueId()), 1, now()->addDay());
    }

    private function balanceData(string $address): array
    {
        try {
            $account = (new WebTron())->getAccountBalanceDetail($address);
            $balance = (string)($account['usdt_balance'] ?? 0);

            return [
                'usdt_balance' => $balance,
                'qian_usdt_balance' => $this->data['type'] == '收入' ? bcsub($balance, $this->data['amount'], 6) : bcadd($balance, $this->data['amount'], 6),
            ];
        } catch (Throwable $e) {
            app(SystemNoticeService::class)->warning('tron_listening_balance_query_failed', [
                'error' => '波场监听余额查询失败',
                'address' => $address,
                'tx_id' => $this->data['tx_id'] ?? '',
                'exception_type' => get_class($e),
                'exception' => $e->getMessage(),
            ]);

            return [
                'usdt_balance' => '查询失败',
                'qian_usdt_balance' => '查询失败',
            ];
        }
    }
}
