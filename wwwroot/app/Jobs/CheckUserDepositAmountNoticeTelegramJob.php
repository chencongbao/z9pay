<?php

namespace App\Jobs;

use Throwable;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\App;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\SystemNotice\SystemNoticeService;
use App\Services\Telegram\TelegramInstanceService;
use App\Services\User\GetUserRemainingDepositService;

class CheckUserDepositAmountNoticeTelegramJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $user_id = 0;

    public function __construct($user_id = 0)
    {
        $this->user_id = intval($user_id);
    }

    public function handle(): void
    {
        $user_deposit_balance_notice = floatval(bob_admin_setting("telegram_user_deposit_balance_notice"));
        if ($user_deposit_balance_notice > 0) {
            $user = User::where('id', $this->user_id)->first(['deposit_amount', 'telegram_group_id', 'name', 'id', 'username']);
            if ($user && $user->telegram_group_id != 0 && intval(config("telegram.turn_on", 0)) == 1 && !empty(config("telegram.telegram_bot_token"))) {
                $depositAmount = floatval($user->deposit_amount);
                if ($depositAmount > 0) {
                    $remainingDeposit = App::make(GetUserRemainingDepositService::class)->excute($user->id);
                    $remaining_deposit = (float)($remainingDeposit['remaining_deposit'] ?? 0);
                    if ($remaining_deposit < $user_deposit_balance_notice) {
                        try {
                            $telegram = app(TelegramInstanceService::class)->excute();
                            $text = "金主：<b>" . $user->bname . "</b>，押金已不足<b>【" . bob_unit_format($user_deposit_balance_notice) . "】</b>，当前剩余押金<b>【" . bob_unit_format($remaining_deposit) . "】</b>。";
                            $telegram->sendMessage(['chat_id' => $user->telegram_group_id, 'text' => $text, 'parse_mode' => 'html']);
                        } catch (Throwable $e) {
                            app(SystemNoticeService::class)->warning("system_manual_notice", ['message' => $e->getMessage(), 'line' => $e->getLine(), 'file' => $e->getFile(), 'action' => "金主押金不足，通知群，发生异常"]);
                        }
                    }
                }
            }
        }
    }
}
