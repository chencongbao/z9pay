<?php

namespace App\Traits;

use App\Extendtions\Math\MathEvaluator;
use App\Jobs\CacheDepositOrderInfoJob;
use App\Jobs\CacheTransferOrderInfoJob;
use App\Models\Channel;
use App\Models\DepositOrder;
use App\Models\MerchantInfo;
use App\Models\TransferOrder;
use App\Models\User;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\Telegram\TelegramLangService;
use App\Services\Telegram\TelegramManagerService;
use Illuminate\Support\Facades\Cache;

trait TelegramTrait
{

    public function answerCallbackAlert(array $message, string $text): void
    {
        $callbackQueryId = (string)($message['id'] ?? '');
        if ($callbackQueryId === '') {
            return;
        }

        $this->telegram->answerCallbackQuery([
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => true,
        ]);
    }

    public function getGroup($message)
    {
        $chat_id = $message['chat']['id'];
        if ($chat_id < 0) {
            $groupType = Cache::get(CacheConstPrefixService::TELEGRAM_GROUP_TYPE . $chat_id . "_type");
            if ($groupType !== null) {
                return intval($groupType);
            }
        }
        return 0;
    }

    //限制转发客服
    public function checkIsCustomer($message)
    {
        return false;
    }

    public function checkIsManager($message)
    {
        return app(TelegramManagerService::class)->isManagerMessage((array) $message);
    }

    public function getMerchantUserId($message)
    {
        $key = CacheConstPrefixService::TELEGRAM_GROUP_TYPE . $message['chat']['id'];
        $key_id = $key."_id";
        $cachedId = Cache::get($key_id);
        if ($cachedId !== null) {
            return $cachedId;
        }
        $result = MerchantInfo::where('telegram_group_id', $message['chat']['id'])->first(['merchant_user_id','name','coder']);
        if ($result) {
            Cache::forever($key_id, $result->merchant_user_id);
            return $result->merchant_user_id;
        }
        return 0;
    }


    public function getUserId($message)
    {
        $key = CacheConstPrefixService::TELEGRAM_GROUP_TYPE . $message['from']['id'];
        $key_id = $key."_id";
        $cachedId = Cache::get($key_id);
        if ($cachedId !== null) {
            return $cachedId;
        }
        $result = User::where('telegram_group_id',$message['chat']['id'])
            ->where('telegram_user_id', $message['from']['id'])
            ->orderBy('id')
            ->first(['id', 'name','username']);
        if ($result) {
            Cache::put($key_id, $result->id,now()->addDays(7));
            return $result->id;
        }
        return 0;
    }

    public function getBindUsers($message, array $columns = ['id', 'name', 'username'])
    {
        return User::where('telegram_group_id', $message['chat']['id'])
            ->where('telegram_user_id', $message['from']['id'])
            ->where('is_agent', 0)
            ->orderBy('id')
            ->get($columns);
    }


    public function getChannelId($message)
    {
        $channelIds = $this->getChannelIds($message);

        return !empty($channelIds) ? intval($channelIds[0]) : 0;
    }

    public function getChannelIds($message): array
    {
        $key = CacheConstPrefixService::TELEGRAM_GROUP_TYPE . $message['chat']['id'];
        $keyIds = $key . "_ids";

        $cachedIds = Cache::get($keyIds);
        if ($cachedIds !== null) {
            return array_values(array_filter(array_map('intval', (array) $cachedIds)));
        }

        $ids = Channel::where('telegram_user_id', $message['chat']['id'])
            ->orderBy('id')
            ->pluck('id')
            ->map(function ($id) {
                return intval($id);
            })
            ->toArray();

        if (!empty($ids)) {
            Cache::forever($keyIds, $ids);
            Cache::forever($key . "_id", intval($ids[0]));
        }

        return $ids;
    }

    public function getChannels($message, array $columns = ['id', 'name'])
    {
        $channelIds = $this->getChannelIds($message);
        if (empty($channelIds)) {
            return collect();
        }

        return Channel::whereIn('id', $channelIds)
            ->orderBy('id')
            ->get($columns);
    }


    public function getUserInfo($message = [],$group_type = 1)
    {
        if($group_type == 1 || $group_type == 3){
            $key = CacheConstPrefixService::TELEGRAM_GROUP_TYPE . $message['chat']['id'];
            $key_name = $key."_name";
            $cachedName = Cache::get($key_name);
            if ($cachedName !== null) {
                return $cachedName;
            }
            $merchant = MerchantInfo::where('telegram_group_id', $message['chat']['id'])->first(['merchant_user_id','name','coder','currency_id']);
            if ($merchant) {
                Cache::put($key_name, $merchant->bname,now()->addDays(7));
                return $merchant->bname;
            }
            $channel = Channel::where('telegram_user_id', $message['chat']['id'])->first(['id','name']);
            if ($channel) {
                Cache::put($key_name, $channel->name,now()->addDays(7));
                return $channel->name;
            }
        }
        if($group_type == 2){
            $key = CacheConstPrefixService::TELEGRAM_GROUP_TYPE . $message['from']['id'];
            $key_name = $key."_name";
            $cachedName = Cache::get($key_name);
            if ($cachedName !== null) {
                return $cachedName;
            }
            $result = User::where('telegram_group_id',$message['chat']['id'])->where('telegram_user_id', $message['from']['id'])->first(['id', 'name','username']);
            if ($result) {
                Cache::put($key_name, $result->bname,now()->addDays(7));
                return $result->bname;
            }
        }
        return;
    }


    public function doCalculate($text = "")
    {
        if (!$this->isMathExpression($text)) return;
        $evaluator = new MathEvaluator($text, null);
        $result = $evaluator->evaluate();
        if (is_numeric($result)) return $result;
        return;
    }

    public function depositOrderInfo($result = [], $group_type = 0)
    {
        try {
            if (empty($result)) return;
            $result = (object)$result;
            if ($result) {
                $lang = $this->resolveMerchantTelegramLang($group_type, $result->mid ?? 0);
                $text = $this->buildTelegramInfoHeader($lang);
                $text .= $this->buildTelegramInfoLine('订单类型', '<b>代收订单【' . $this->telegramText('deposit_order', $lang) . '】</b>', $lang, 'order_type');
                $text .= $this->buildTelegramInfoLine('平台单号', '<code>' . $result->ordernumber . '</code>', $lang, 'platform_order_no', '<code>' . $result->ordernumber . '</code>');
                $text .= $this->buildTelegramInfoLine('商户单号', '<code>' . $result->order_no . '</code>', $lang, 'merchant_order_no', '<code>' . $result->order_no . '</code>');
                $text .= $this->buildTelegramInfoLine(
                    '订单状态',
                    '<b>' . optional(config('default.deposite_status'))[$result->status] . '【' . $this->translateDepositStatus($result->status, $lang) . '】' . ($result->status == 5 ? '✅' : '❌') . '</b>',
                    $lang,
                    'order_status'
                );
                $currencyName = optional(collect(config('default.currency'))->firstWhere('id', $result->currency_id))->offsetGet('name');
                $currencyCode = $this->extractCurrencyCode($currencyName);
                $text .= $this->buildTelegramInfoLine('交易币种', $currencyCode, $lang, 'transaction_currency', $currencyCode);
                $text .= $this->buildTelegramInfoLine('提交金额', floatval($result->amount), $lang, 'submitted_amount', floatval($result->amount));
                if (isset($result->actual_amount) && $result->actual_amount > 0) {
                    $text .= $this->buildTelegramInfoLine('实付金额', floatval($result->actual_amount), $lang, 'actual_amount', floatval($result->actual_amount));
                }
                if(isset($result->utr) && !empty($result->utr)){
                    $text .= $this->buildTelegramInfoLine('UTR', $result->utr, $lang, 'utr', $result->utr);
                }
                if ($group_type == 1) {
                    if (isset($result->payment_id) && $result->payment_id > 0) {
                        $paymentCode = (string) optional(collect(config('payment'))->firstWhere('id', $result->payment_id))->offsetGet('code');
                        $paymentInfo = $paymentCode;
                        $text .= $this->buildTelegramInfoLine('支付通道', $paymentInfo, $lang, 'payment_channel', $paymentInfo);
                    }
                    if (isset($result->merchant_fee)) $text .= $this->buildTelegramInfoLine('手续费', floatval($result->merchant_fee), $lang, 'fee', floatval($result->merchant_fee));
                    if (isset($result->merchant_extra_fee)) $text .= $this->buildTelegramInfoLine('额外手续费', floatval($result->merchant_extra_fee), $lang, 'extra_fee', floatval($result->merchant_extra_fee));
                }
                if (isset($result->success_time) && $result->success_time > 0) {
                    $successTime = $result->success_time ? date('Y-m-d H:i:s', $result->success_time) : '';
                    $text .= $this->buildTelegramInfoLine('成功时间', $successTime, $lang, 'success_time', $successTime);
                }
                if ($result->status == 6 && isset($result->remark) && !empty($result->remark)) {
                    $remark = $this->replaceString($result->remark);
                    $text .= $this->buildTelegramInfoLine('失败原因', $remark, $lang, 'failure_reason', $this->translateFailureReason($result->remark, $lang));
                }
                if ($result->status == 5 && isset($result->callback_status)) {
                    $text .= $this->buildTelegramInfoLine('回调状态', $this->getCallbackText($result->callback_status, $lang), $lang, 'callback_status');
                }
                return $text;
            }
            return;
        } catch (\Exception $e) {
            $data = (array)$result;
            if (!empty($data) && isset($data['ordernumber'])) {
                dispatch(new CacheDepositOrderInfoJob($data['ordernumber']))->onQueue('query');
            }
            app(\App\Services\SystemNotice\SystemNoticeService::class)->warning("system_manual_notice", ['message' => $e->getMessage(), 'result' => $data]);
            return;
        }
    }

    public function transferOrderInfo($result = [], $group_type = 0)
    {
        try {
            if (empty($result)) return;
            $result = (object)$result;
            if ($result) {
                $lang = $this->resolveMerchantTelegramLang($group_type, $result->mid ?? 0);
                $text = $this->buildTelegramInfoHeader($lang);
                $text .= $this->buildTelegramInfoLine('订单类型', '<b>代付订单【' . $this->telegramText('transfer_order', $lang) . '】</b>', $lang, 'order_type');
                $text .= $this->buildTelegramInfoLine('平台单号', '<code>' . $result->ordernumber . '</code>', $lang, 'platform_order_no', '<code>' . $result->ordernumber . '</code>');
                $text .= $this->buildTelegramInfoLine('商户单号', '<code>' . $result->order_no . '</code>', $lang, 'merchant_order_no', '<code>' . $result->order_no . '</code>');
                $text .= $this->buildTelegramInfoLine(
                    '订单状态',
                    '<b>' . optional(config('default.transfer_status'))[$result->status] . '【' . $this->translateTransferStatus($result->status, $lang) . '】' . ($result->status == 4 ? '✅' : '❌') . '</b>',
                    $lang,
                    'order_status'
                );
                $currencyName = optional(collect(config('default.currency'))->firstWhere('id', $result->currency_id))->offsetGet('name');
                $currencyCode = $this->extractCurrencyCode($currencyName);
                $text .= $this->buildTelegramInfoLine('交易币种', $currencyCode, $lang, 'transaction_currency', $currencyCode);
                $text .= $this->buildTelegramInfoLine('提交金额', floatval($result->amount), $lang, 'submitted_amount', floatval($result->amount));
                if (isset($result->actual_amount) && $result->actual_amount > 0) {
                    $text .= $this->buildTelegramInfoLine('实付金额', floatval($result->actual_amount), $lang, 'actual_amount', floatval($result->actual_amount));
                }
                if(isset($result->utr) && !empty($result->utr)){
                    $text .= $this->buildTelegramInfoLine('UTR', $result->utr, $lang, 'utr', $result->utr);
                }
                if ($group_type == 1) {
                    if (isset($result->merchant_fee)) $text .= $this->buildTelegramInfoLine('手续费', floatval($result->merchant_fee), $lang, 'fee', floatval($result->merchant_fee));
                    if (isset($result->merchant_extra_fee)) $text .= $this->buildTelegramInfoLine('额外手续费', floatval($result->merchant_extra_fee), $lang, 'extra_fee', floatval($result->merchant_extra_fee));
                }
                if (isset($result->success_time) && $result->success_time > 0) {
                    $successTime = $result->success_time ? date('Y-m-d H:i:s', $result->success_time) : '';
                    $text .= $this->buildTelegramInfoLine('成功时间', $successTime, $lang, 'success_time', $successTime);
                }
                if ($result->status == 5 && isset($result->remark)) {
                    $remark = $this->replaceString($result->remark);
                    $text .= $this->buildTelegramInfoLine('失败原因', $remark, $lang, 'failure_reason', $this->translateFailureReason($result->remark, $lang));
                }
                if (($result->status == 4 || $result->status == 5) && isset($result->callback_status)) {
                    $text .= $this->buildTelegramInfoLine('回调状态', $this->getCallbackText($result->callback_status, $lang), $lang, 'callback_status');
                }
                return $text;
            }
            return;
        } catch (\Exception $e) {
            $data = (array)$result;
            if (!empty($data) && isset($data['ordernumber'])) {
                dispatch(new CacheTransferOrderInfoJob($data['ordernumber']))->onQueue('query');
            }
            app(\App\Services\SystemNotice\SystemNoticeService::class)->warning("system_manual_notice", ['message' => $e->getMessage(), 'result' => $data]);
            return;
        }

    }

    private function replaceString($string)
    {
        return htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function resolveMerchantTelegramLang($group_type = 0, $mid = 0)
    {
        if (intval($group_type) !== 1 || intval($mid) <= 0) {
            return '';
        }

        return $this->telegramLangService()->merchantLang(intval($mid));
    }

    private function buildTelegramInfoHeader(string $lang = ''): string
    {
        if ($this->shouldAppendSecondLanguageTelegram($lang)) {
            return "== ** 订单信息【" . $this->telegramText('order_information', $lang) . "】 ** ==\n";
        }

        return "== ** 订单信息 ** ==\n";
    }

    private function buildTelegramInfoLine(string $label, $value, string $lang = '', ?string $labelKey = null, $translatedValue = null): string
    {
        $text = "\n" . $label;
        if ($this->shouldAppendSecondLanguageTelegram($lang)) {
            $text .= "【" . $this->telegramText($labelKey ?: $label, $lang) . "】";
        }

        $text .= "：" . $value;

        if ($this->shouldAppendSecondLanguageTelegram($lang)) {
            $finalValue = $translatedValue ?? $value;
            if (! $this->isSameTelegramDisplayValue($value, $finalValue)) {
                $text .= $finalValue;
            }
        }

        return $text;
    }

    private function shouldAppendSecondLanguageTelegram(string $lang = ''): bool
    {
        return $lang !== '' && !str_starts_with(strtolower($lang), 'zh');
    }

    protected function buildTelegramBalanceHeader(string $lang = '', string $emoji = '💼'): string
    {
        $header = $emoji . ' ' . $this->telegramText('merchant_balance_title', 'zh_CN');
        if ($this->shouldAppendSecondLanguageTelegram($lang)) {
            $header .= "【" . $this->telegramText('merchant_balance_title', $lang) . "】";
        }

        return $header . "\n";
    }

    protected function buildTelegramBalanceLine(string $zhLabel, $value, string $lang = '', ?string $labelKey = null): string
    {
        $text = "\n" . $zhLabel;
        if ($this->shouldAppendSecondLanguageTelegram($lang)) {
            $text .= "【" . $this->telegramText($labelKey ?: $zhLabel, $lang) . "】";
        }

        $text .= "：<code>" . $value . "</code>";

        return $text;
    }

    private function isSameTelegramDisplayValue($value, $translatedValue): bool
    {
        $normalize = function ($item): string {
            return trim(strip_tags(html_entity_decode((string) $item, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')));
        };

        return $normalize($value) === $normalize($translatedValue);
    }

    private function extractCurrencyCode($currencyName = ''): string
    {
        $currencyName = (string)$currencyName;
        if ($currencyName === '') {
            return '';
        }

        $parts = explode('-', $currencyName, 2);
        return trim($parts[0]) ?: $currencyName;
    }

    private function translateDepositStatus($status = 0, string $lang = 'en-US'): string
    {
        return $this->telegramOption('deposit_status', intval($status), $lang);
    }

    private function translateTransferStatus($status = 0, string $lang = 'en-US'): string
    {
        return $this->telegramOption('transfer_status', intval($status), $lang);
    }

    private function translateFailureReason(string $remark = '', string $lang = 'en-US'): string
    {
        $remark = trim($remark);
        if ($remark === '') {
            return '';
        }

        if (str_contains($remark, '商户余额不足')) {
            return $this->telegramText('merchant_balance_insufficient', $lang);
        }

        return $this->replaceString($remark);
    }

    private function getCallbackText($callback_status = 0, string $lang = 'zh_CN')
    {
        switch ($callback_status) {
            case 1:
                return "已回调【" . $this->telegramText('callback_success_text', $lang) . "】 ✅";
                break;
            case 0:
            case 2:
                return "回调失败【" . $this->telegramText('callback_failed_text', $lang) . "】";
                break;
        }
    }

    private function getCallbackTextByLang($callback_status = 0, string $lang = 'en-US')
    {
        switch ($callback_status) {
            case 1:
                return $this->telegramText('callback_success_text', $lang) . " ✅";
            case 0:
            case 2:
                return $this->telegramText('callback_failed_text', $lang);
            default:
                return (string)$callback_status;
        }
    }

    protected function telegramText(string $key, string $lang = 'en-US', array $replace = []): string
    {
        return $this->telegramLangService()->text($key, $lang, $replace);
    }

    protected function telegramOption(string $optionKey, int|string $value, string $lang = 'en-US'): string
    {
        return $this->telegramLangService()->option($optionKey, $value, $lang);
    }

    protected function telegramLangService(): TelegramLangService
    {
        return app(TelegramLangService::class);
    }

    protected function merchantTelegramLangByMessage(array $message = [], int $group_type = 0): string
    {
        if ($group_type !== 1) {
            return '';
        }

        return $this->telegramLangService()->merchantLang(intval($this->getMerchantUserId($message)));
    }

    function isMathExpression($string)
    {
        // 基本数学表达式正则验证
        $pattern = '/^[\d\s\.\+\-\*\/\%\^\(\)]+$/';

        // 检查是否只包含数字、运算符和括号
        if (!preg_match($pattern, $string)) {
            return false;
        }

        // 检查括号是否匹配
        $stack = [];
        foreach (str_split($string) as $char) {
            if ($char === '(') {
                array_push($stack, $char);
            } elseif ($char === ')') {
                if (empty($stack)) {
                    return false;
                }
                array_pop($stack);
            }
        }

        return empty($stack);
    }

}
