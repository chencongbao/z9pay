<?php

namespace App\Extendtions\Telegram;

use Throwable;
use App\Models\User;
use App\Models\Channel;
use App\Models\MerchantInfo;
use App\Traits\TelegramTrait;
use App\Jobs\MerchantSuccessJob;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use App\Telegram\Commands\ChannelRateAction;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\Telegram\TelegramManagerService;
use App\Services\Telegram\MerchantBotOrderLookupRuleService;
use App\Telegram\Commands\ChannelFixedRateAction;

class Message
{
    use TelegramTrait;

    protected $telegram;

    // 群类型：1=商户，2=金主，3=渠道
    protected $group_type = 0;

    public function __construct($telegram)
    {
        $this->telegram = $telegram;
    }


    public function init($message)
    {
        if (isset($message['message']['chat']['id'])) {
            $this->checkGroup($message['message']);
            $this->parseContent($message['message']);
        }
        if (isset($message['callback_query'])) {
            $this->callbackQuery($message['callback_query']);
        }
    }

    public function checkGroup($message = [])
    {
        $chat_id = $message['chat']['id'];
        if ($chat_id < 0) {
            $key = CacheConstPrefixService::TELEGRAM_GROUP_TYPE . $chat_id;

            $key_id = $key . "_id";
            $key_name = $key . "_name";
            $key_type = $key . "_type";

            $cachedType = Cache::get($key_type);
            if ($cachedType !== null) {
                $this->group_type = intval($cachedType);
                if ($this->group_type === 2) {
                    $this->cacheBindUser($message);
                }
            } else {
                $merchant = MerchantInfo::where('telegram_group_id', $chat_id)->first(['merchant_user_id', 'name', 'coder', 'currency_id']);
                if ($merchant) {
                    $this->group_type = 1;
                    Cache::put($key_type, 1, now()->addDays(7));
                    Cache::put($key_name, $merchant->bname, now()->addDays(7));
                    Cache::put($key_id, $merchant->merchant_user_id, now()->addDays(7));
                    return true;
                }
                $channel = Channel::where('telegram_user_id', $chat_id)->first(['id', 'name']);
                if ($channel) {
                    $this->group_type = 3;
                    Cache::put($key_type, 3, now()->addDays(7));
                    Cache::put($key_name, $channel->name, now()->addDays(7));
                    Cache::put($key_id, $channel->id, now()->addDays(7));
                    return true;
                }
                if ($this->cacheBindUser($message) || User::where('telegram_group_id', $chat_id)->exists()) {
                    $this->group_type = 2;
                    Cache::put($key_type, 2, now()->addDays(7));
                    return true;
                }
                Cache::put($key_type, 0, now()->addMinutes(2));
            }
            return true;
        }
        return true;
    }

    private function cacheBindUser(array $message): bool
    {
        $chatId = $message['chat']['id'] ?? 0;
        $fromId = intval($message['from']['id'] ?? 0);
        if ($chatId >= 0 || $fromId <= 0) {
            return false;
        }

        $key = CacheConstPrefixService::TELEGRAM_GROUP_TYPE . $fromId;
        $missingKey = $key . "_missing_" . $chatId;
        if (Cache::has($key . "_id")) {
            return true;
        }
        if (Cache::has($missingKey)) {
            return false;
        }

        $bindUser = User::where('telegram_group_id', $chatId)->where('telegram_user_id', $fromId)->first(['id', 'name', 'username']);
        if (!$bindUser) {
            Cache::put($missingKey, 1, now()->addMinutes(2));
            return false;
        }

        Cache::put($key . "_id", $bindUser->id, now()->addDays(7));
        Cache::put($key . "_name", $bindUser->bname, now()->addDays(7));
        return true;
    }


    private function parseContent($message)
    {
        if (isset($message['text'])) {
            $this->routeTextMessage($message);
        }
        if (isset($message['caption'])) {
            $this->checkQueryOrderInfo($message);
        }

    }

    private function routeTextMessage(array $message): void
    {
        $text = trim((string) ($message['text'] ?? ''));
        if ($text === '') {
            return;
        }

        $message['_explicit_bot_mention'] = false;

        if ($this->isPrivateNonManager($message)) {
            $this->sendPrivatePermissionTip($message);
            return;
        }

        if (str_contains($text, '@') && $this->shouldHandleBotMention($message)) {
            $mentionText = $this->extractTextAfterBotMention($message);
            if (strtolower($mentionText) === 't') {
                $this->translateText($message);
                return;
            }
            if ($mentionText !== '') {
                $text = $mentionText;
                $message['text'] = $text;
                $message['_explicit_bot_mention'] = true;
            }
        }

        $lowerText = strtolower($text);
        $upperText = strtoupper($text);

        if ($lowerText === 'help' || $text === '帮助') {
            $this->help($message);
            return;
        }

        if ($this->isClearTelegramCacheCommand($text)) {
            $this->clearTelegramCache($message);
            return;
        }

        if ($text === '我的信息' || $text === '个人信息') {
            $this->myInfo($message);
            return;
        }

        if ($text === '申请商户管理员' || $lowerText === 'apply merchant admin') {
            $this->applyMerchantTelegramAdmin($message);
            return;
        }

        if (!is_numeric(bob_replacement_empty($text)) && $this->isMathExpression($text)) {
            $this->calculator($message);
            return;
        }

        if (mb_strpos($text, '渠道绑定') === 0) {
            $this->bindChannel($message);
            return;
        }

        if ($text === '绑定' || $lowerText === 'bd' || preg_match('/^(bd\s*\+?.+|绑定\s*\+?.+)$/iu', $text)) {
            $this->bindMerchant($message);
            return;
        }

        if (str_starts_with($text, '申请绑定')) {
            $this->bindUser($message);
            return;
        }

        if ($this->isBillCommand($text)) {
            $this->bill($message);
            return;
        }

        if (in_array($upperText, ['OT', 'OB', 'OC', 'OW', 'OA'], true)) {
            $this->queryOkx($message);
            return;
        }

        if ($this->isMerchantRechargeCommand($text)) {
            $this->merchantRecharge($message);
            return;
        }

        if (mb_substr($text, 0, 2) === '减项') {
            $this->merchantJianxiang($message);
            return;
        }

        if (str_starts_with($lowerText, '修改费率 ')) {
            $this->merchantPaymentRate($message);
            return;
        }

        if ($text === '余额' || $lowerText === 'yu') {
            $this->queryBalance($message);
            return;
        }

        if (mb_substr($text, 0, 4) === '收款开启' || mb_substr($text, 0, 4) === '收款关闭') {
            $this->userAcquisition($message);
            return;
        }

        if (mb_substr($text, 0, 4) === '今日账单' || mb_substr($text, 0, 4) === '代收详情' || mb_substr($text, 0, 4) === '代付详情') {
            $this->userBill($message);
            return;
        }

        if (mb_substr($text, 0, 4) === '商家余额' || mb_substr($text, 0, 4) === '商户余额') {
            $this->managerQueryMerchantBalance($message);
            return;
        }

        if (mb_substr($text, 0, 4) === '渠道余额') {
            $this->managerQueryChannelBalance($message);
            return;
        }

        if ($text === '商户总余额') {
            $this->getMerchantTotalBalance($message);
            return;
        }

        if (preg_match('/^sgdz\d+$/i', $text)) {
            $this->tronAddress($message);
            return;
        }

        if (bob_replacement_empty($text) === '查询通道') {
            $this->queryMerchantPaymentSuccessRate($message);
            return;
        }

        if (str_starts_with($lowerText, 'jia=') || str_starts_with($lowerText, 'jian=')) {
            $this->listeningTronAddress($message);
            return;
        }

        if ($this->containsChainAddress($text)) {
            $this->countGroupAddress($message);
            return;
        }

        if ($text === '查询成功率' || preg_match('/^\/success_rate(?:@\w+)?$/i', $text)) {
            $this->queryMerchantSuccessInfo($message);
            return;
        }

        if ($this->isTransferReceiptCommand($text)) {
            $this->sendTransferReceipt($message);
            return;
        }

        if (preg_match('/^T[a-zA-Z0-9]{33}$/', $text) === 1) {
            $this->queryTronAddress($message);
            return;
        }

        if ($text === '今日统计') {
            $this->todayMerchantCentus($message);
            return;
        }

        if ($this->isOrderNumberText($text)) {
            $this->queryOrder($message);
        }
    }

    private function isBillCommand(string $text): bool
    {
        if (in_array($text, ['开始记账', '账单', '清空账单', '入款详情', '下发详情', '撤销下发', '取消下发', '撤销入款', '取消入款'], true)) {
            return true;
        }

        return preg_match('/^设置(?:汇率|费率)\s*\+?\s*-?\d+(?:\.\d+)?$/u', $text) === 1
            || preg_match('/^(?:入款|入账|下发)\s*\+?\s*-?\d+(?:\.\d+)?[Uu]?$/u', $text) === 1;
    }

    private function isClearTelegramCacheCommand(string $text): bool
    {
        return str_starts_with($text, '清除飞机缓存')
            || str_starts_with($text, '清空飞机缓存')
            || str_starts_with($text, '清除TG缓存');
    }

    private function isMerchantRechargeCommand(string $text): bool
    {
        return RechargeAction::matchesRechargeCommand($text);
    }

    private function containsChainAddress(string $text): bool
    {
        return preg_match('/(?<!T20)T[a-zA-Z0-9]{33}(?![a-zA-Z0-9])|0x[a-fA-F0-9]{40}(?![a-fA-F0-9])/', $text) === 1;
    }

    private function isOrderNumberText(string $text): bool
    {
        return strlen($text) >= 6 && preg_match('/^[a-zA-Z0-9_-]+$/', $text) === 1;
    }

    private function isTransferReceiptCommand(string $text): bool
    {
        return preg_match('/^(回执单|receipt)\s+[a-zA-Z0-9_-]{6,}$/iu', trim($text)) === 1;
    }

    private function sendTransferReceipt(array $message): void
    {
        App::makeWith(QueryOrderInfoByOrdernumber::class, ['telegram' => $this->telegram])->sendTransferReceipt($message, $this->group_type);
    }

    private function isPrivateNonManager(array $message): bool
    {
        $managerService = app(TelegramManagerService::class);
        if (!$managerService->isPrivateChat($message['chat'] ?? [])) {
            return false;
        }

        return !$managerService->isManagerMessage($message) && !$managerService->isDeveloperMessage($message);
    }

    private function isPrivateCallbackNonManager(array $message): bool
    {
        $managerService = app(TelegramManagerService::class);
        if (!$managerService->isPrivateChat($message['message']['chat'] ?? [])) {
            return false;
        }

        return !$managerService->isManagerMessage($message) && !$managerService->isDeveloperMessage($message);
    }

    private function sendPrivatePermissionTip(array $message): void
    {
        $chatId = intval($message['chat']['id'] ?? 0);
        if ($chatId <= 0) {
            return;
        }

        $payload = ['chat_id' => $chatId, 'text' => '您暂无权限使用私聊机器人命令，请联系管理员开通权限。'];
        if (!empty($message['message_id'])) {
            $payload['reply_to_message_id'] = intval($message['message_id']);
        }

        $this->telegram->sendMessage($payload);
    }

    /**
     * 提取 @机器人 之后的文本。
     */
    private function extractTextAfterBotMention(array $message): string
    {
        $text = (string)($message['text'] ?? '');
        if ($text === '') {
            return '';
        }

        $botUsername = $this->getBotUsername();
        if ($botUsername === '') {
            return '';
        }

        $botUsername = preg_quote(ltrim($botUsername, '@'), '/');
        if (!preg_match('/@' . $botUsername . '\b(.*)$/iu', $text, $matches)) {
            return '';
        }

        return trim($matches[1] ?? '');
    }

    /**
     * 提取当前消息所回复的文本内容，兼容文字和图片说明。
     */
    private function extractReplyContentText(array $message): string
    {
        if (!isset($message['reply_to_message']) || !is_array($message['reply_to_message'])) {
            return '';
        }

        $reply = $message['reply_to_message'];
        if (isset($reply['text']) && is_string($reply['text'])) {
            return trim($reply['text']);
        }

        if (isset($reply['caption']) && is_string($reply['caption'])) {
            return trim($reply['caption']);
        }

        return '';
    }

    /**
     * 群内只处理 @机器人 的文本消息；私聊直接处理。
     */
    private function shouldHandleBotMention(array $message): bool
    {
        $chatId = $message['chat']['id'] ?? 0;
        if ($chatId > 0) {
            return true;
        }

        $text = (string)($message['text'] ?? '');
        if ($text === '') {
            return false;
        }

        $botUsername = $this->getBotUsername();
        if ($botUsername === '') {
            return true;
        }

        $botUsername = ltrim(strtolower($botUsername), '@');
        $lowerText = strtolower($text);

        // 兼容两种常见写法：@botname /command@botname
        return str_contains($lowerText, '@' . $botUsername);
    }

    private function getBotUsername(): string
    {
        return Cache::rememberForever('telegram_bot_username', function () {
            $configured = (string)config('telegram.bot_username', '');
            if ($configured !== '') {
                return ltrim($configured, '@');
            }

            try {
                $me = $this->telegram->getMe();
                return (string)($me['username'] ?? '');
            } catch (Throwable $e) {
                return '';
            }
        });
    }


    public function getMerchantTotalBalance($message)
    {
        App::makeWith(QueryMerchantTotalBalanceAction::class, ['telegram' => $this->telegram])->excute($message);
    }

    public function todayMerchantCentus($message)
    {
        if ($this->group_type == 1) {
            App::makeWith(todayMerchantCentusAction::class, ['telegram' => $this->telegram])->excute($message);
        }
    }


    public function managerQueryMerchantBalance($message)
    {
        App::makeWith(ManagerQueryMerchantBalanceAction::class, ['telegram' => $this->telegram])->excute($message);
    }

    public function managerQueryChannelBalance($message)
    {
        App::makeWith(ManagerQueryChannelBalanceAction::class, ['telegram' => $this->telegram])->excute($message);
    }


    private function userBill($message)
    {
        App::makeWith(UserBillAction::class, ['telegram' => $this->telegram])->excute($message, $this->group_type);
    }


    private function userAcquisition($message)
    {
        App::make(UserAcquisitionAction::class, ['telegram' => $this->telegram])->excute($message, $this->group_type);
    }


    private function help($message)
    {
        App::makeWith(HelpAction::class, ['telegram' => $this->telegram])->excute($message, $this->group_type);
    }


    private function bill($message)
    {
        App::makeWith(BillAction::class, ['telegram' => $this->telegram])->excute($message);
    }


    private function queryOkx($message)
    {
        App::makeWith(QueryOkxAction::class, ['telegram' => $this->telegram])->excute($message);
    }

    private function myInfo($message)
    {
        App::makeWith(MyInfoAction::class, ['telegram' => $this->telegram])->excute($message, $this->group_type);
    }


    private function queryOrder($message)
    {
        if ($this->group_type == 1 || $this->group_type == 2) {
            App::makeWith(QueryOrderInfoByOrdernumber::class, ['telegram' => $this->telegram])->excute($message, $this->group_type);
        }

    }


    private function queryBalance($message)
    {
        App::makeWith(QueryBalanceAction::class, ['telegram' => $this->telegram])->excute($message, $this->group_type);
    }

    private function queryMerchantSuccessInfo($message)
    {
        if ($this->group_type == 1) {
            App::makeWith(QueryMerchantSuccessInfoAction::class, ['telegram' => $this->telegram])->excute($message);
        }
    }


    private function countGroupAddress($message)
    {
        App::make(CountGroupAddressAction::class, ['telegram' => $this->telegram])->excute($message);
    }


    private function callbackQuery($message)
    {
        if (empty($message['data'])) {
            return;
        }

        if ($this->isPrivateCallbackNonManager($message)) {
            $this->answerCallbackQuery($message, '您暂无权限操作。', true);
            return;
        }

        $data = json_decode($message['data'], true);
        if (!is_array($data) || empty($data)) {
            $this->answerCallbackQuery($message);
            return;
        }

        $callbackType = intval($data['type'] ?? $data['t'] ?? 0);
        if ($this->isPublicCallback($callbackType)) {
            if (!$this->callbackHandlesOwnAnswer($callbackType)) {
                $this->answerCallbackQuery($message);
            }
            $this->handlePublicCallback($callbackType, $data, $message);
            return;
        }

        if ($this->isDeveloperCallback($callbackType)) {
            if (app(TelegramManagerService::class)->isDeveloperMessage($message)) {
                $this->answerCallbackQuery($message);
                $this->handleDeveloperCallback($callbackType, $data, $message);
                return;
            }

            $this->answerCallbackQuery($message, '您没有开发权限，无权操作此按钮。', true);
            return;
        }

        if ($this->checkIsManager($message)) {
            if (!$this->callbackHandlesOwnAnswer($callbackType)) {
                $this->answerCallbackQuery($message);
            }
            $this->handleManagerCallback($callbackType, $data, $message);
            return;
        }

        $this->answerCallbackQuery($message, '您不是管理员，无权操作此按钮。', true);
    }

    private function answerCallbackQuery(array $message, string $text = '', bool $alert = false): void
    {
        if (empty($message['id'])) {
            return;
        }

        try {
            $payload = ['callback_query_id' => $message['id']];
            if ($text !== '') {
                $payload['text'] = $text;
                $payload['show_alert'] = $alert;
            }
            $this->telegram->answerCallbackQuery($payload);
        } catch (Throwable $e) {
        }
    }

    /**
     * 非管理员也允许点击的业务回调。
     */
    private function handlePublicCallback(int $callbackType, array $data, array $message): bool
    {
        switch ($callbackType) {
            case 7:
                App::makeWith(QueryOrderInfoAction::class, ['telegram' => $this->telegram])->jiaji($message);
                return true;
            case 10:
            case 18:
                App::make(QueryBalanceAction::class, ['telegram' => $this->telegram])->balance($message);
                return true;
            case 8:
                App::makeWith(QueryOrderInfoAction::class, ['telegram' => $this->telegram])->callbackDepositReplay($data, $message);
                return true;
            case 9:
                App::makeWith(QueryOrderInfoAction::class, ['telegram' => $this->telegram])->callbackTransferReplay($data, $message);
                return true;
            case 11:
                $this->deleteCallbackMessage($message);
                return true;
            case 12:
                App::makeWith(QueryMerchantSuccessInfoAction::class, ['telegram' => $this->telegram])->callbackQueryTimeReplay($data, $message);
                return true;
            case 13:
                App::makeWith(TransferOrderConfirmClickService::class, ['telegram' => $this->telegram])->confirm($message, $data['order_id'] ?? 0);
                return true;
            case 14:
                App::makeWith(TransferOrderConfirmClickService::class, ['telegram' => $this->telegram])->cancel($message, $data['order_id'] ?? 0);
                return true;
            case 17:
                App::makeWith(QueryChannelBalanceAction::class, ['telegram' => $this->telegram])->cancel($message, $data['cid'] ?? 0);
                return true;
            case 22:
                App::makeWith(TestTransferConfirmAction::class, ['telegram' => $this->telegram])->callback($data, $message);
                return true;
            case 23:
                App::makeWith(MerchantBalanceAddConfirmAction::class, ['telegram' => $this->telegram])->callback($data, $message);
                return true;
            case 24:
                App::makeWith(DepositManualSuccessConfirmAction::class, ['telegram' => $this->telegram])->callback($data, $message);
                return true;
        }

        return false;
    }

    private function isPublicCallback(int $callbackType): bool
    {
        return in_array($callbackType, [7, 8, 9, 10, 11, 12, 13, 14, 17, 18, 22, 23, 24], true);
    }

    private function callbackHandlesOwnAnswer(int $callbackType): bool
    {
        return in_array($callbackType, [1, 3, 5, 13, 14, 19, 20, 21, 22, 23, 24], true);
    }

    private function isDeveloperCallback(int $callbackType): bool
    {
        return in_array($callbackType, [27], true);
    }

    private function handleDeveloperCallback(int $callbackType, array $data, array $message): void
    {
        if ($callbackType === 27) {
            App::makeWith(FailedJobConfirmAction::class, ['telegram' => $this->telegram])->callback($data, $message);
        }
    }

    /**
     * 管理员专属回调，避免普通群成员误触敏感操作。
     */
    private function handleManagerCallback(int $callbackType, array $data, array $message): void
    {
        if ($callbackType <= 0) {
            return;
        }

        switch ($callbackType) {
            case 1:
                $merchantUserId = $this->getMerchantUserId($message['message']);
                App::makeWith(RechargeAction::class, ['telegram' => $this->telegram])->callbackRecharge($data, $message, $merchantUserId);
                return;
            case 2:
                App::makeWith(BillAction::class, ['telegram' => $this->telegram])->auth($data, $message);
                return;
            case 3:
                $merchantUserId = $this->getMerchantUserId($message['message']);
                App::makeWith(RechargeAction::class, ['telegram' => $this->telegram])->callbackJianxiang($data, $message, $merchantUserId);
                return;
            case 4:
                App::makeWith(BindUser::class, ['telegram' => $this->telegram])->action($data, $message['message']);
                return;
            case 5:
                App::makeWith(BillAction::class, ['telegram' => $this->telegram])->callbackClearBill($data, $message);
                return;
            case 6:
                App::makeWith(ExceptionNoticeMuteClickService::class, ['telegram' => $this->telegram])->action($data, $message);
                return;
            case 15:
                App::makeWith(LoginExceptionBanClickService::class, ['telegram' => $this->telegram])->action($data, $message);
                return;
            case 16:
                App::makeWith(LoginExceptionUnbanClickService::class, ['telegram' => $this->telegram])->action($data, $message);
                return;
            case 19:
                App::makeWith(MerchantPaymentRateAction::class, ['telegram' => $this->telegram])->callbackUpdateRate($data, $message);
                return;
            case 20:
                App::make(ChannelRateAction::class)->callbackUpdateRate($data, $message);
                return;
            case 21:
                App::make(ChannelFixedRateAction::class)->callbackUpdateRate($data, $message);
                return;
            case 25:
                App::makeWith(MerchantTelegramAdminApplyAction::class, ['telegram' => $this->telegram])->confirm($data, $message);
                return;
            case 26:
                App::makeWith(MerchantTelegramAdminApplyAction::class, ['telegram' => $this->telegram])->reject($data, $message);
                return;
        }
    }

    private function deleteCallbackMessage(array $message): void
    {
        $chatId = $message['message']['chat']['id'] ?? 0;
        $messageId = $message['message']['message_id'] ?? 0;
        if (!$chatId || !$messageId) {
            return;
        }

        $this->telegram->deleteMessage(['chat_id' => $chatId, 'message_id' => $messageId]);
    }

    private function merchantJianxiang($message = [])
    {
        if ($this->group_type == 1) {
            App::makeWith(RechargeAction::class, ['telegram' => $this->telegram])->jianxiang($message, $this->group_type);
        }
    }

    private function merchantRecharge($message = [])
    {
        if ($this->group_type == 1) {
            App::makeWith(RechargeAction::class, ['telegram' => $this->telegram])->recharge($message, $this->group_type);
        }

    }

    private function merchantPaymentRate($message = [])
    {
        if ($this->group_type == 1) {
            App::makeWith(MerchantPaymentRateAction::class, ['telegram' => $this->telegram])->excute($message, $this->group_type);
        }
    }

    private function bindUser($message)
    {
        App::makeWith(BindUser::class, ['telegram' => $this->telegram])->excute($message, $this->group_type);
    }

    private function applyMerchantTelegramAdmin($message)
    {
        App::makeWith(MerchantTelegramAdminApplyAction::class, ['telegram' => $this->telegram])->excute($message, $this->group_type);
    }

    private function calculator($message)
    {
        App::makeWith(CalculatorAction::class, ['telegram' => $this->telegram])->excute($message);
    }

    private function bindMerchant($message)
    {
        App::makeWith(BindMerchantAction::class, ['telegram' => $this->telegram])->excute($message, $this->group_type);
    }

    private function bindChannel($message)
    {
        App::makeWith(BingChanelAction::class, ['telegram' => $this->telegram])->excute($message, $this->group_type);
    }

    private function clearTelegramCache($message)
    {
        App::makeWith(ClearTelegramCacheAction::class, ['telegram' => $this->telegram])->excute($message);
    }

    private function tronAddress($message)
    {
        $address = bob_format_muti_data_to_array(config("other.tron_address"));
        if (!empty($address)) {
            foreach ($address as $k => $v) {
                if (strtolower($message['text']) == 'sgdz' . ($k + 1)) {
                    $this->telegram->sendMessage(['chat_id' => $message['chat']['id'], 'text' => $v, 'parse_mode' => 'html']);
                }
            }
        }
    }

    private function checkQueryOrderInfo($message)
    {
        if ($this->group_type == 1) {
            if ($this->isMerchantBotMessage($message)) {
                $this->handleMerchantBotOrderLookup($message);
                return;
            }

            App::makeWith(QueryOrderInfoAction::class, ['telegram' => $this->telegram])->excute($message);
        }
    }

    private function handleMerchantBotOrderLookup(array $message): void
    {
        $merchantId = intval($this->getMerchantUserId($message));
        $ruleService = App::make(MerchantBotOrderLookupRuleService::class);
        $hasConfiguration = $ruleService->hasMerchantConfiguration($merchantId);

        // 未配置专属规则时，沿用原有客服查单解析和转发流程。
        if (!$hasConfiguration) {
            $orderNumber = bob_replacement_empty((string)($message['caption'] ?? ''));
            App::makeWith(QueryOrderInfoAction::class, ['telegram' => $this->telegram])->excute($message);
            return;
        }

        $orderNumbers = $ruleService->extractOrderNumbers($merchantId, (string)($message['caption'] ?? ''));

        foreach ($orderNumbers as $orderNumber) {
            $lookupMessage = $message;
            $lookupMessage['caption'] = $orderNumber;
            App::makeWith(QueryOrderInfoAction::class, ['telegram' => $this->telegram])->excute($lookupMessage);
        }
    }

    private function isMerchantBotMessage(array $message): bool
    {
        if (($message['from']['is_bot'] ?? false) === true) {
            return true;
        }

        if (($message['forward_from']['is_bot'] ?? false) === true) {
            return true;
        }

        if (($message['forward_origin']['sender_user']['is_bot'] ?? false) === true) {
            return true;
        }

        return false;
    }

    private function queryMerchantPaymentSuccessRate($message)
    {
        if ($this->group_type == 1) {
            if (bob_replacement_empty($message['text']) == '查询通道') {
                dispatch(new MerchantSuccessJob($this->getMerchantUserId($message), $message))->onQueue('query');
            }
        }
    }

    private function queryTronAddress($message)
    {
        if (preg_match('/^T[a-zA-Z0-9]{33}$/', $message['text']) === 1 && intval(config("default.query_tron_address_on")) == 1) {
            App::makeWith(QueryTronAddressAction::class, ['telegram' => $this->telegram])->excute($message);
        }
    }

    private function listeningTronAddress($message)
    {
        App::makeWith(ListeningTronAddressTranslationAction::class, ['telegram' => $this->telegram])->excute($message);
    }

    private function translateText($message)
    {
        $t = $this->extractTextAfterBotMention($message);
        $text = $this->extractReplyContentText($message);
        if (!empty($t) && !empty($text) && strtolower($t) == 't') {
            app(TranslateMessageTextAction::class)->excute($message, $text);
        }
    }
}
