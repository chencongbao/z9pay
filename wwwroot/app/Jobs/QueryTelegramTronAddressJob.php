<?php

namespace App\Jobs;

use Throwable;
use Illuminate\Bus\Queueable;
use App\Models\ListeningAddress;
use App\Extendtions\Tron\WebTron;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use App\Services\SystemNotice\SystemNoticeService;
use App\Services\Telegram\TelegramInstanceService;
use App\Extendtions\Telegram\QueryTronAddressAction;

class QueryTelegramTronAddressJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 1;

    public int $uniqueFor = 60;

    public function __construct(
        protected string $address,
        protected int $chatId,
        protected int $messageId
    ) {
    }

    public function uniqueId(): string
    {
        return $this->address . ':' . $this->chatId . ':' . $this->messageId;
    }

    public function handle(): void
    {
        $telegram = app(TelegramInstanceService::class)->excute();
        $tron = new WebTron();

        try {
            if (!$tron->wallet->validateAddress($tron->formatAddress($this->address))) {
                $this->editMessage($telegram, '地址格式无效');
                return;
            }

            $trxBalance = $tron->queryTrxBalance($this->address);
            $usdtBalance = $tron->queryUsdtBalance($this->address);
            $addressInfo = ListeningAddress::updateOrCreate(['address' => $this->address], ['trx_balance' => $trxBalance, 'usdt_balance' => $usdtBalance]);
            $action = app(QueryTronAddressAction::class, ['telegram' => $telegram]);
            $content = $action->getContent($this->address, [], $addressInfo);
            $this->editMessage($telegram, $content);

            $detail = $action->getDetail($tron, $this->address, $addressInfo);
            if ($detail) {
                $this->editMessage($telegram, $detail);
            }
        } catch (Throwable $e) {
            app(SystemNoticeService::class)->warning('telegram_tron_address_query_failed', [
                'address' => $this->address,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            $this->editMessage($telegram, '查询失败，请稍后再试');
        }
    }

    private function editMessage($telegram, string $text): void
    {
        $payload = [
            'chat_id' => $this->chatId,
            'message_id' => $this->messageId,
            'text' => $text,
            'parse_mode' => 'html',
            'disable_web_page_preview' => true,
        ];

        $telegram->editMessageText($payload);
    }
}
