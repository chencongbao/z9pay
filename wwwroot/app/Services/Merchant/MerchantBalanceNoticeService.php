<?php

namespace App\Services\Merchant;

use App\Jobs\TelegramQunSendJob;
use App\Models\MerchantInfo;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\Common\BalanceNoticeIntervalService;
use App\Services\Telegram\TelegramLangService;
use Illuminate\Support\Facades\Cache;

class MerchantBalanceNoticeService
{
    public function handle(MerchantInfo $merchant, float $changeAmount): void
    {
        if ($merchant->telegram_group_id == 0) {
            return;
        }

        if (intval(config("telegram.turn_on", 0)) !== 1 || empty(config("telegram.telegram_bot_token"))) {
            return;
        }

        $noticeService = app(GetMerchantNoticeBalanceService::class);
        $rule = $noticeService->getRule($merchant->merchant_user_id);

        if (($rule['compare'] ?? 'lt') === 'lt' && $changeAmount >= 0) {
            return;
        }

        if (($rule['compare'] ?? 'lt') === 'gt' && $changeAmount <= 0) {
            return;
        }

        if (!$noticeService->shouldNotice($merchant->available_balance, $rule)) {
            return;
        }

        $interval = app(BalanceNoticeIntervalService::class)->minutes();
        if ($interval > 0) {
            $key = CacheConstPrefixService::MERCHANT_BALANCE_NOTICE . $merchant->merchant_user_id;
            if (!Cache::add($key, 1, now()->addMinutes($interval))) {
                return;
            }
        }

        dispatch(new TelegramQunSendJob([
            'telegram_group_id' => $merchant->telegram_group_id,
            'send_content' => $this->message($merchant, $rule, $noticeService),
            'parse_mode' => 'html',
        ]))->onQueue('notice');
    }

    private function message(MerchantInfo $merchant, array $rule, GetMerchantNoticeBalanceService $noticeService): string
    {
        $langService = app(TelegramLangService::class);
        $lang = $langService->merchantLang(intval($merchant->merchant_user_id));
        $isGreaterNotice = ($rule['compare'] ?? 'lt') === 'gt';
        $noticeTitleKey = $isGreaterNotice ? 'balance_high_title' : 'balance_low_title';
        $noticeCompareKey = $isGreaterNotice ? 'balance_notice_gt' : 'balance_notice_lt';
        $noticeTitle = $langService->text($noticeTitleKey, 'zh_CN');
        $noticeTitleLang = $langService->text($noticeTitleKey, $lang);
        $compareText = $noticeService->compareText($rule);
        $compareTextLang = $langService->text($noticeCompareKey, $lang);

        $text = "⚠️ " . $this->title($noticeTitle, $noticeTitleLang) . "\n";
        $text .= $this->line($langService->text('merchant_name', 'zh_CN'), '<b>' . e((string)$merchant->name) . '</b>', $langService->text('merchant_name', $lang), '<b>' . e((string)$merchant->name) . '</b>');
        $text .= $this->line($langService->text('current_balance', 'zh_CN'), '<code>' . bob_unit_format($merchant->available_balance) . '</code>', $langService->text('current_balance', $lang), '<code>' . bob_unit_format($merchant->available_balance) . '</code>');
        $text .= $this->line($langService->text('notice_condition', 'zh_CN'), '<code>' . $compareText . '</code>', $langService->text('notice_condition', $lang), '<code>' . $compareTextLang . '</code>');
        $text .= $this->line($langService->text('notice_threshold', 'zh_CN'), '<code>' . bob_unit_format($rule['value']) . '</code>', $langService->text('notice_threshold', $lang), '<code>' . bob_unit_format($rule['value']) . '</code>');

        if ($isGreaterNotice) {
            return $text . "\n" . $this->tip($langService->text('balance_high_tip', 'zh_CN'), $langService->text('balance_high_tip', $lang));
        }

        return $text . "\n" . $this->tip($langService->text('balance_low_tip', 'zh_CN'), $langService->text('balance_low_tip', $lang));
    }

    private function line(string $zhLabel, string $zhValue, string $langLabel, string $langValue): string
    {
        $label = $this->normalizeDisplayValue($zhLabel) === $this->normalizeDisplayValue($langLabel)
            ? $zhLabel
            : "{$zhLabel}【{$langLabel}】";

        $text = "\n{$label}：{$zhValue}";
        if ($this->normalizeDisplayValue($zhValue) !== $this->normalizeDisplayValue($langValue)) {
            $text .= "（{$langValue}）";
        }

        return $text;
    }

    private function title(string $zhTitle, string $langTitle): string
    {
        if ($this->normalizeDisplayValue($zhTitle) === $this->normalizeDisplayValue($langTitle)) {
            return $zhTitle;
        }

        return $zhTitle . "【" . $langTitle . "】";
    }

    private function tip(string $zhTip, string $langTip): string
    {
        if ($this->normalizeDisplayValue($zhTip) === $this->normalizeDisplayValue($langTip)) {
            return $zhTip;
        }

        return $zhTip . "（" . $langTip . "）";
    }

    private function normalizeDisplayValue(string $value): string
    {
        return trim(strip_tags(html_entity_decode($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')));
    }
}
