<?php

namespace App\Console\Commands;

use Throwable;
use App\Models\TransferOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use App\Services\Telegram\TelegramInstanceService;
use App\Services\TransferOrder\Receipt\SendTransferSuccessReceiptTelegramService;

class TransferSuccessReceiptCommand extends Command
{
    protected $signature = 'transfer:success-receipt {ordernumber : 代付平台订单号} {--lang=zh_CN : 回执语言，zh_CN 或 en}';

    protected $description = '生成代付成功回执单并发送给系统开发者';

    public function handle(): int
    {
        $ordernumber = trim((string)$this->argument('ordernumber'));
        if ($ordernumber === '') {
            $this->error('请输入代付平台订单号。');
            return self::FAILURE;
        }

        $lang = $this->lang();
        if ($lang === null) {
            return self::FAILURE;
        }

        $developerTelegramId = intval(config('default.system_telegram_id'));
        if ($developerTelegramId <= 0) {
            $this->error('default.system_telegram_id 未配置，无法发送给开发者。');
            return self::FAILURE;
        }

        $order = TransferOrder::query()->where('ordernumber', $ordernumber)->where('type', 0)->first();
        if (!$order) {
            $this->error('代付订单不存在：' . $ordernumber);
            return self::FAILURE;
        }

        if ((int)$order->status !== 4) {
            $this->error('只有代付成功订单才能生成回执单，当前状态：' . $order->status);
            return self::FAILURE;
        }

        try {
            $telegram = App::make(TelegramInstanceService::class)->excute();
            $sent = App::make(SendTransferSuccessReceiptTelegramService::class)->send($telegram, $developerTelegramId, $order, 0, $lang);
            if (!$sent) {
                $this->error('代付成功回执单发送失败，请查看系统异常日志。');
                return self::FAILURE;
            }

            $this->info('代付成功回执单已发送给开发者：' . $developerTelegramId);
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('代付成功回执单发送失败：' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function lang(): ?string
    {
        $lang = trim((string)$this->option('lang'));
        if ($lang === '' || $lang === 'zh') {
            return 'zh_CN';
        }
        if (in_array($lang, ['zh_CN', 'en'], true)) {
            return $lang;
        }

        $this->error('回执语言只支持 zh_CN 或 en。');
        return null;
    }
}
