<?php

namespace App\Jobs;

use App\Models\MerchantBalanceLog;
use App\Models\MerchantInfo;
use App\Services\Telegram\TelegramLangService;
use App\Services\Telegram\TelegramInstanceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MerchantBalanceJiaJianNoticeTelegramGroupJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    public $tries = 1;

    public $timeout = 1000;

    public $mid = 0;

    public $merchant_balance_log_id = 0;

    public $name = '';

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($mid = 0, $merchant_balance_log_id = 0, $name = "")
    {
        $this->mid = $mid;
        $this->merchant_balance_log_id = $merchant_balance_log_id;
        $this->name = $name;
    }

    public function uniqueId(): string
    {
        return (string) $this->merchant_balance_log_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (intval(bob_admin_setting("telegram_turn_on")) == 0) return;

        $merchant = MerchantInfo::where('merchant_user_id', $this->mid)
            ->first(['merchant_user_id', 'name', 'coder', 'telegram_group_id']);

        if (! $merchant || intval($merchant->telegram_group_id) === 0) {
            return;
        }

        $log = MerchantBalanceLog::where('id', $this->merchant_balance_log_id)
            ->where('mid', $this->mid)
            ->first(['id', 'mid', 'amount', 'fee', 'balance_amount', 'created_at']);

        if (! $log) {
            return;
        }

        $changeAmount = (float) $log->amount;
        $feeAmount = (float) $log->fee;
        $netChangeAmount = $changeAmount - $feeAmount;
        $afterBalance = (float) $log->balance_amount;
        $beforeBalance = $afterBalance - $netChangeAmount;
        $lang = app(TelegramLangService::class)->merchantLang(intval($merchant->merchant_user_id));
        $changeType = $changeAmount >= 0
            ? app(TelegramLangService::class)->text('increase', 'zh_CN')
            : app(TelegramLangService::class)->text('decrease', 'zh_CN');
        $translatedChangeType = $changeAmount >= 0
            ? app(TelegramLangService::class)->text('increase', $lang)
            : app(TelegramLangService::class)->text('decrease', $lang);

        $operator = trim((string) $this->name);
        if ($operator === '' && $log->relationLoaded('admin_user') && $log->admin_user) {
            $operator = (string) $log->admin_user->name;
        }
        if ($operator === '') {
            $operator = '系统';
        }

        $langService = app(TelegramLangService::class);
        $changeTypeText = '<b>' . $this->translatedValue($changeType, $translatedChangeType) . '</b>';
        $text = "📢 " . $langService->text('balance_change_title', 'zh_CN') . "【" . $langService->text('balance_change_title', $lang) . "】" . "\n";
        $text .= $this->line($langService->text('merchant_name', 'zh_CN'), '<b>' . e((string) $merchant->name) . '</b>', $langService->text('merchant_name', $lang), '<b>' . e((string) $merchant->name) . '</b>');
        $text .= $this->line($langService->text('merchant_code', 'zh_CN'), '<code>' . e((string) $merchant->coder) . '</code>', $langService->text('merchant_code', $lang), '<code>' . e((string) $merchant->coder) . '</code>');
        $text .= $this->line($langService->text('change_direction', 'zh_CN'), $changeTypeText, $langService->text('change_direction', $lang), $changeTypeText);
        $text .= $this->line($langService->text('change_amount', 'zh_CN'), '<code>' . ($changeAmount >= 0 ? '+' : '') . bob_unit_format($changeAmount) . '</code>', $langService->text('change_amount', $lang), '<code>' . ($changeAmount >= 0 ? '+' : '') . bob_unit_format($changeAmount) . '</code>');
        $text .= $this->line($langService->text('fee', 'zh_CN'), '<code>' . bob_unit_format($feeAmount) . '</code>', $langService->text('fee', $lang), '<code>' . bob_unit_format($feeAmount) . '</code>');
        $text .= $this->line($langService->text('actual_impact', 'zh_CN'), '<code>' . ($netChangeAmount >= 0 ? '+' : '') . bob_unit_format($netChangeAmount) . '</code>', $langService->text('actual_impact', $lang), '<code>' . ($netChangeAmount >= 0 ? '+' : '') . bob_unit_format($netChangeAmount) . '</code>');
        $text .= $this->line($langService->text('balance_change', 'zh_CN'), '<code>' . bob_unit_format($beforeBalance) . '</code> → <code>' . bob_unit_format($afterBalance) . '</code>', $langService->text('balance_change', $lang), '<code>' . bob_unit_format($beforeBalance) . '</code> → <code>' . bob_unit_format($afterBalance) . '</code>');
        $text .= $this->line($langService->text('log_id', 'zh_CN'), '<code>' . $log->id . '</code>', $langService->text('log_id', $lang), '<code>' . $log->id . '</code>');
        $text .= $this->operatorLines($operator, $langService, $lang);

        if (! empty($log->created_at)) {
            $text .= $this->line($langService->text('operation_time', 'zh_CN'), '<code>' . $log->created_at . '</code>', $langService->text('operation_time', $lang), '<code>' . $log->created_at . '</code>');
        }

        try {
            $telegram = app(TelegramInstanceService::class)->excute();
            $telegram->sendMessage([
                'chat_id' => $merchant->telegram_group_id,
                'text' => $text,
                'parse_mode' => 'html',
            ]);
        } catch (\Throwable $e) {
            app(\App\Services\SystemNotice\SystemNoticeService::class)->warning("system_manual_notice", [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'action' => '商户余额加减通知群发送失败',
                'mid' => $this->mid,
                'merchant_balance_log_id' => $this->merchant_balance_log_id,
            ]);
        }

    }

    private function line(string $zhLabel, string $zhValue, string $langLabel, string $langValue): string
    {
        $text = "\n{$zhLabel}【{$langLabel}】：{$zhValue}";
        if ($this->normalizeDisplayValue($zhValue) !== $this->normalizeDisplayValue($langValue)) {
            $text .= $langValue;
        }

        return $text;
    }

    private function translatedValue(string $zhValue, string $langValue): string
    {
        if ($this->normalizeDisplayValue($zhValue) === $this->normalizeDisplayValue($langValue)) {
            return e($zhValue);
        }

        return e($zhValue) . '【' . e($langValue) . '】';
    }

    private function operatorLines(string $operator, TelegramLangService $langService, string $lang): string
    {
        if (! str_contains($operator, "\n")) {
            return $this->line($langService->text('operator', 'zh_CN'), '<b>' . e($operator) . '</b>', $langService->text('operator', $lang), '<b>' . e($operator) . '</b>');
        }

        $lines = [];
        foreach (explode("\n", $operator) as $line) {
            $line = trim($line);
            if ($line === '' || ! str_contains($line, '：')) {
                continue;
            }

            [$label, $value] = explode('：', $line, 2);
            $labelKey = match ($label) {
                '申请人' => 'operator',
                '确认人' => 'confirmed_by',
                default => '',
            };

            if ($labelKey === '') {
                $lines[] = "\n{$label}：<b>" . e($value) . '</b>';
                continue;
            }

            $lines[] = $this->line($langService->text($labelKey, 'zh_CN'), '<b>' . e($value) . '</b>', $langService->text($labelKey, $lang), '<b>' . e($value) . '</b>');
        }

        return implode('', $lines);
    }

    private function normalizeDisplayValue(string $value): string
    {
        return trim(strip_tags(html_entity_decode($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')));
    }
}
