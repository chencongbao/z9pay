<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use App\Models\DepositOrder;
use Illuminate\Http\Request;
use App\Rules\DepositOrderNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use App\Services\Order\OrderCacheService;
use Illuminate\Support\Facades\Validator;
use App\Events\UserDepositOrderNoticeEvent;
use App\Services\Common\DomainConfigService;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\Common\ReportExceptionService;
use App\Services\BlackContent\CheckCashierAreaService;
use App\Services\DepositOrder\DepositOrderRiskService;
use App\Services\DepositOrder\DepositOrderStatusService;
use App\Services\DepositOrder\DepositOrderPayAmountService;
use App\Services\DepositOrder\DepositOrderConfirmPayService;
use App\Services\Cache\Channel\ChannelInfoByChannelIdService;
use App\Services\DepositOrderLog\CreateDepositOrderLogService;
use App\Services\DepositOrder\ChannelMode\DispatchModeService;
use App\Http\Requests\Api\V2\CashierUploadPayCertificateRequest;
use App\Services\DepositOrder\HandleDepositOrderCreatedSuccessService;
use App\Services\SelfNewPayment\GetUserDaifukuanDepositOrderListService;

class CashierController extends Controller
{

    public function index(Request $request)
    {
        $ordernumber = $request->input('no');
        $locale = "zh_CN";
        $app_name = payment_app_name();
        $payment = "test";
        $currency = "CNY";
        if (empty($ordernumber) || !(new DepositOrderNumber())->passes('no', $ordernumber)) {
            abort(404);
        }

        $result = App::make(OrderCacheService::class)->getDepositByOrdernumber($ordernumber, true);
        if (empty($result)) {
            abort(404);
        }
        if (!$this->canOpenCashier($result)) {
            abort(404);
        }

        $maps = $this->cashierConfigMaps();

        $newLocale = $maps['currency_lang'][$result['currency_id'] ?? null] ?? null;
        if (is_string($newLocale) && $newLocale !== $locale) {
            App::setLocale($newLocale);
            $locale = $newLocale;
        }

        $payment = $maps['payment_code'][$result['payment_id'] ?? null] ?? $payment;
        $currency = $maps['currency_name'][$result['currency_id'] ?? null] ?? $currency;
        $payment_name = $maps['payment_name'][$result['payment_id'] ?? null] ?? $payment;

        if (config('default.cashier_preview_on')) {
            $payment = (string)config('default.cashier_preview_payment', $payment);
            $currency = (string)config('default.cashier_preview_currency', $currency);
            $previewPaymentId = array_search($payment, $maps['payment_code'], true);
            $payment_name = $previewPaymentId !== false ? ($maps['payment_name'][$previewPaymentId] ?? $payment) : $payment;
        }

        $config = ['ordernumber' => $ordernumber, 'app_name' => $app_name, 'locale' => $locale, 'payment' => $payment, 'currency' => $currency, 'payment_name' => $payment_name];
        return view('cashier', ['config' => $config]);
    }

    private function canOpenCashier(array $order): bool
    {
        return in_array(intval($order['status'] ?? 0), [1, 3, 7], true);
    }

    private function cashierConfigMaps(): array
    {
        static $maps = null;

        if ($maps === null) {
            $maps = [
                'currency_lang' => array_column(config('default.currency', []), 'lang', 'id'),
                'payment_code' => array_column(config('payment', []), 'code', 'id'),
                'currency_name' => array_column(config('default.currency', []), 'short_name', 'id'),
                'payment_name' => array_column(config('payment', []), 'name', 'id'),
            ];
        }

        return $maps;
    }


    public function getDepositGoldOrder(Request $request)
    {
        $ordernumber = $request->input('ordernumber');
        $validate = Validator::make(
            $request->only(['ordernumber']),
            ['ordernumber' => ['required', 'string', new DepositOrderNumber()]],
            ['ordernumber.required' => "参数错误"]
        );
        if ($validate->fails()) {
            return $this->error("参数错误");
        }
        $result = DepositOrder::where('ordernumber', $ordernumber)->first([
            'id',
            'status',
            'alipay_uid',
            'collection_name',
            'amount',
            'pay_amount',
            'show_amount',
        ]);
        if ($result) {
            if ($result->status != 3) {
                return $this->error("订单已过期");
            }
            if (empty($result->alipay_uid)) {
                return $this->error("收款信息不存在");
            }

            $createDepositOrderLogService = App::make(CreateDepositOrderLogService::class);
            $createDepositOrderLogService->excute($result->id, "打开支付宝黄金支付页面", ['ip' => $request->getClientIp(), 'ua' => $request->userAgent()]);
            $amount = $result->show_amount;
            if ($amount === null || $amount === '' || floatval($amount) <= 0) {
                $amount = $result->pay_amount;
            }
            if ($amount === null || $amount === '' || floatval($amount) <= 0) {
                $amount = $result->amount;
            }
            $this->data['u'] = $result->alipay_uid;
            $this->data['m'] = $result->collection_name;
            $this->data['a'] = floatval($amount);
            $this->data['s'] = 'money';
            return $this->success("操作成功", $this->data);
        }
        return $this->error("未知错误");
    }

    public function getDepositOrder(Request $request)
    {
        $data = $request->only(['ordernumber']);
        $validator = Validator::make(
            $data,
            ['ordernumber' => ['required', 'string', new DepositOrderNumber()]]
        );
        if ($validator->fails()) {
            return $this->error(__("cashier.params_error"));
        }

        $order = App::make(OrderCacheService::class)->getDepositByOrdernumber($data['ordernumber'], true);

        if (!$order) {
            return $this->error(__('cashier.order_does_not_exist'));
        }
        if (!$this->canOpenCashier($order)) {
            return $this->error(__('cashier.order_does_not_exist'));
        }

        $cashierData = $this->buildCashierOrderData($order);
        $this->logCashierOpenOnce($order, $request, $cashierData);
        return $this->success(__("cashier.success"), $cashierData);
    }

    private function logCashierOpenOnce(array $order, Request $request, array $cashierData): void
    {
        $orderId = intval($order['id'] ?? 0);
        if ($orderId <= 0) {
            return;
        }

        $status = intval($order['status'] ?? 0);
        $ttlSeconds = max(300, intval(floatval(bob_admin_setting("base_deposit_over_time")) * 60));
        $key = 'cashier:deposit_open_log:' . $orderId . ':' . $status;
        if (!Cache::add($key, 1, now()->addSeconds($ttlSeconds))) {
            return;
        }

        $title = "收银台打开";
        if ($status === 1) {
            $title = "收银台打开(设置付款人姓名)";
        }
        if ($status === 3) {
            $title = "收银台打开(返回卡信息)";
        }

        $createDepositOrderLogService = App::make(CreateDepositOrderLogService::class);
        $createDepositOrderLogService->excute($orderId, $title, [
            'ip' => $request->getClientIp(),
            'ua' => $request->userAgent(),
            '返回数据' => $cashierData,
        ]);
    }

    private function buildCashierOrderData($order): array
    {
        $overMinutes = floatval(bob_admin_setting("base_deposit_over_time"));
        $createdAt = strtotime((string)data_get($order, 'created_at'));
        $timeMs = $createdAt ? max(0, ($createdAt + $overMinutes * 60 - time())) : 0;
        $ordernumber = data_get($order, 'ordernumber');

        $displayAmount = data_get($order, 'show_amount');
        if ($displayAmount === null || $displayAmount === '' || floatval($displayAmount) <= 0) {
            $displayAmount = data_get($order, 'pay_amount');
        }
        if ($displayAmount === null || $displayAmount === '' || floatval($displayAmount) <= 0) {
            $displayAmount = data_get($order, 'amount');
        }

        $data = [
            'time' => $timeMs,
            'minite' => $overMinutes,
            'ordernumber' => $ordernumber,
            'status' => data_get($order, 'status'),
            'amount' => floatval($displayAmount),
            'collection_card_no' => data_get($order, 'collection_card_no'),
            'collection_name' => data_get($order, 'collection_name'),
            'collection_bank_name' => data_get($order, 'collection_bank_name'),
            'collection_bank_branch' => data_get($order, 'collection_bank_branch'),
            'channel_pay_url' => data_get($order, 'channel_pay_url'),
            'collection_app_link' => data_get($order, 'collection_app_link'),
            'collection_qrcode' => data_get($order, 'collection_qrcode'),
            'account_type' => data_get($order, 'account_type'),
            'return_url' => data_get($order, 'return_url'),
            'collection_app_info' => data_get($order, 'collection_app_info'),
            'download_url' => data_get($order, 'collection_qrcode') ? route('cashier.deposit.download', ['ordernumber' => $ordernumber]) : '',
        ];

        if (empty($data['collection_app_link']) && !empty(data_get($order, 'collection_qrcode_url'))) {
            $data['collection_app_link'] = "alipays://platformapi/startapp?saId=10000007&clientVersion=3.7.0.0718&qrcode=" . data_get($order, 'collection_qrcode_url');
        }
        if (empty($data['collection_app_link']) && !empty(data_get($order, 'alipay_uid'))) {
            $data['collection_app_link'] = "alipays://platformapi/startapp?appId=20000067&url=" . route('cashier.gold', ['no' => $ordernumber]);
        }

        return $data;
    }


    public function queryDepositOrderStatus(Request $request)
    {
        $data = $request->only(['ordernumber']);
        $validator = Validator::make(
            $data,
            ['ordernumber' => ['required', 'string', new DepositOrderNumber()]]
        );
        if ($validator->fails()) {
            return $this->success('',['status' => 0]);
        }
        $result = App::make(OrderCacheService::class)->getDepositByOrdernumber($data['ordernumber'], true);
        if ($result) return $this->success('',['status' => $result['status']]);
        return $this->success('',['status' => 0]);
    }


    public function cancelDepositOrder(Request $request)
    {
        $data = $request->only(['ordernumber']);
        $validate = Validator::make($data, ['ordernumber' => ['required', 'string', new DepositOrderNumber()]]);
        if ($validate->fails()) {
            return $this->error("参数错误");
        }
        DB::beginTransaction();
        try {
            $result = DepositOrder::where('ordernumber', $data['ordernumber'])->whereIn('status', [1, 3])->lockForUpdate()->first(CacheConstPrefixService::CACHE_DEPOSIT_FILED);
            if ($result) {
                App::make(DepositOrderStatusService::class)->markCancelled($result);
                $result->refresh();
                DB::commit();
                $createDepositOrderLogService = App::make(CreateDepositOrderLogService::class);
                $createDepositOrderLogService->excute($result->id, "收银台取消订单", [
                    '取消原因' => '会员手动取消',
                    '订单状态' => config('default.deposite_status')[$result->status] ?? $result->status,
                    '支付状态' => '付方已取消',
                    'ip' => $request->getClientIp(),
                    'ua' => $request->userAgent(),
                ]);
                return $this->success("取消成功", ['return_url' => $result->return_url]);
            }
            DB::rollBack();
            return $this->error("非法操作");
        } catch (\Throwable $e) {
            DB::rollBack();
            App::make(ReportExceptionService::class)->report('收银台取消订单失败', $e, [
                'ordernumber' => $data['ordernumber'] ?? '',
            ]);
            return $this->error("取消失败，请稍后重试");
        }
    }

    public function setPayName(Request $request)
    {
        $data = $request->only(['ordernumber', 'pay_name', 'card_no', 'card_pin', 'bank_name', 'locale']);


        // 从 default.currency 配置中获取所有可用语言
        $allowLocales = array_values(array_unique(
            array_filter(
                array_column(config('default.currency', []), 'lang')
            )
        ));

        // 前端提交的语言
        $locale = $data['locale'] ?? 'zh_CN';

        // 如果前端语言不在配置中，默认使用中文
        App::setLocale(in_array($locale, $allowLocales, true) ? $locale : 'zh_CN');

        $validate = Validator::make($data, [
            'ordernumber' => ['bail', 'required', 'string', new DepositOrderNumber()],
            'pay_name' => 'bail|nullable|required_without:card_no|string|max:40',
            'card_no' => 'bail|nullable|required_without:pay_name|string|max:100',
            'card_pin' => 'bail|nullable|string|max:100',
            'bank_name' => 'bail|nullable|string|max:40',
        ]);
        if ($validate->fails()) {
            return $this->error(__("cashier.params_error"));
        }

        $lock = Cache::lock('cashier:set_pay_name:' . $data['ordernumber'], 30);
        if (!$lock->get()) {
            return $this->error(__("cashier.not_request_again"));
        }

        try {
            $submitData = array_filter(Arr::only($data, ['pay_name', 'card_no', 'card_pin', 'bank_name']), static function ($value) {
                return $value !== null && $value !== '';
            });

            $order = DepositOrder::where('ordernumber', $data['ordernumber'])
                ->whereIn('status', [1, 3])
                ->first($this->cashierSubmitFields());

            if (!$order) {
                return $this->error(__("cashier.order_does_not_exist"));
            }

            $createDepositOrderLogService = App::make(CreateDepositOrderLogService::class);

            if ((int)$order->status === 3 && $this->hasCashierPaymentInfo($order)) {
                $cashierData = $this->buildCashierOrderData($order);
                $createDepositOrderLogService->excute($order->id, "收银台重复提交(返回已有收款信息)", $cashierData, "debug");
                return $this->success(__("cashier.success"), $cashierData);
            }

            if (isset($submitData['pay_name']) && !empty($order->pay_name)) {
                $createDepositOrderLogService->excute($order->id, "会员设置付款人姓名错误", "重复提交付款人姓名", "error");
                return $this->error(__("cashier.not_request_again"));
            }

            $riskResult = App::make(DepositOrderRiskService::class)->checkCashierSubmit($order, $submitData, $createDepositOrderLogService);
            if ($riskResult) {
                return $this->error($riskResult['message']);
            }

            foreach ($submitData as $field => $value) {
                $order->{$field} = $value;
            }
            $order->save();
            App::make(OrderCacheService::class)->putDeposit($order);

            if ($order->time) {
                [$payInfo, $err] = $this->dispatchChannel($order, $createDepositOrderLogService);
            } else {
                $channels = json_decode($order->channel_info, true) ?: [];
                if (empty($channels)) {
                    App::make(DepositOrderStatusService::class)->markFailed($order, "未找到合适的渠道");

                    bob_send_system_deposit_notice(['error_text' => "未找到合适的渠道，订单号：" . $order->ordernumber, 'voice_id' => 'deposit_6', 'id' => 6]);
                    $createDepositOrderLogService->excute($order->id, "返回失败参数", $this->getError("收款资源占用中，请稍后支付"), "debug");
                    return $this->error(__("cashier.receiving_funds_are_occupied"));
                }
                if ($order->channel_id > 0) {
                    $channels = [$channels];
                }
                [$payInfo, $err] = app(DispatchModeService::class)->excute($order, $channels, $createDepositOrderLogService);
            }
            if (empty($payInfo)) {
                $error = $err ?: "未找到合适的渠道";
                App::make(DepositOrderStatusService::class)->markFailed($order, $error);

                bob_send_system_deposit_notice(['error_text' => $error . "，订单号：" . $order->ordernumber, 'voice_id' => 'deposit_6', 'id' => 6]);
                $createDepositOrderLogService->excute($order->id, "返回失败参数", $this->getError("收款资源占用中，请稍后支付"), "debug");
                return $this->error(__("cashier.receiving_funds_are_occupied"));
            }

            $order->fill(array_merge(
                ['status' => 3],
                Arr::except($payInfo, ['bankCode', 'bankName', 'cardNo', 'cardName', 'qrCodeUrl', 'paymentQrcodeUrl', 'bankBranch', 'appUrl'])
            ));
            $order->save();
            App::make(HandleDepositOrderCreatedSuccessService::class)->excute($order);

            $cashierData = $this->buildCashierOrderData($order);
            $createDepositOrderLogService->excute($order->id, "收银台打开(成功匹配)", $cashierData);
            return $this->success(__("cashier.success"), $cashierData);
        } finally {
            optional($lock)->release();
        }
    }

    private function hasCashierPaymentInfo($order): bool
    {
        return !empty($order->collection_card_no)
            || !empty($order->collection_qrcode)
            || !empty($order->channel_pay_url)
            || !empty($order->collection_app_link)
            || !empty($order->alipay_uid);
    }

    private function cashierSubmitFields(): array
    {
        return array_values(array_unique(array_merge(
            CacheConstPrefixService::CACHE_DEPOSIT_FILED,
            [
                'alipay_uid',
                'collection_bank_code',
                'collection_bank_branch',
                'collection_bank_name',
                'collection_card_no',
                'collection_name',
                'collection_app_link',
                'collection_qrcode',
                'collection_qrcode_url',
                'collection_app_info',
                'channel_pay_url',
                'channel_info',
                'time',
            ]
        )));
    }

    private function dispatchChannel(DepositOrder $order, $createDepositOrderLogService): array
    {
        // 直连通道
        if ($order->channel_id > 0) {
            $channel = App::make(ChannelInfoByChannelIdService::class)->excute($order->channel_id);
            if (!$channel) {
                return [[], "指定渠道不存在"];
            }
            if (empty($channel['classname'])) {
                return [[], "指定通道类名为空"];
            }

            return $this->requestCashierChannel(
                $order,
                'Richard\\Payment\\Channel\\' . $channel['classname'],
                $order->id,
                $channel['name'] ?? $channel['classname'],
                $createDepositOrderLogService
            );
        }

        // 轮询通道
        if (!empty($order->channel_info)) {
            $channels = json_decode($order->channel_info, true);
            if (!is_array($channels)) {
                $createDepositOrderLogService->excute($order->id, "收银台渠道配置解析失败", [
                    '通道配置' => $order->channel_info,
                    '解析错误' => json_last_error_msg(),
                ], "error");
                return [[], "渠道配置解析失败"];
            }

            foreach ($channels as $v) {
                $createDepositOrderLogService->excute($order->id, "按优先级成功匹配手动配置的渠道", $v, "debug");

                if (empty($v['classname'])) {
                    $error = "通道类名为空";
                    $createDepositOrderLogService->excute($order->id, "收银台通道配置错误", [
                        '错误原因' => $error,
                        '通道信息' => $v,
                    ], "error");
                    continue;
                }

                $amountFloatFields = ['mid', 'float_status', 'deposit_order_id', 'amount'];
                $missingAmountFloatFields = array_values(array_diff($amountFloatFields, array_keys($v)));
                if (empty($missingAmountFloatFields)) {
                    $order = App::make(DepositOrderPayAmountService::class)->applyByChannel($order, $v, $createDepositOrderLogService);
                } else {
                    $createDepositOrderLogService->excute($order->id, "收银台浮动金额处理跳过", [
                        '缺少字段' => $missingAmountFloatFields,
                        '通道名称' => $v['name'] ?? ($v['classname'] ?? ''),
                    ], "debug");
                }

                [$row] = $this->requestCashierChannel(
                    $order,
                    'Richard\\Payment\\Channel\\' . $v['classname'],
                    intval($v['deposit_order_id'] ?? $order->id),
                    $v['name'] ?? ($v['classname'] ?? ''),
                    $createDepositOrderLogService
                );

                if (empty($row)) {
                    continue;
                }

                $payInfo = Arr::collapse([
                    $row,
                    [
                        'merchant_extra_fee' => $v['merchant_extra_fee'] ?? 0,
                        'settlement_mode' => $v['settlement_mode'] ?? 0,
                        'settlement_time' => bob_settlement_time($v['settlement_mode'] ?? 0, $v['settlement_time'] ?? ''),
                    ],
                ]);
                return [$payInfo, null];
            }
        }

        return [[], "未轮巡到合适的渠道"];
    }

    private function requestCashierChannel(DepositOrder $order, string $classname, int $depositOrderId, string $channelName, $logService): array
    {
        if (!class_exists($classname)) {
            $e = new \RuntimeException("通道类不存在：" . $classname);
            $this->reportCashierChannelCodeException($order, $classname, $depositOrderId, $channelName, $e, $logService);
            return [[], $e->getMessage()];
        }
        if (!method_exists($classname, 'deposit')) {
            $e = new \RuntimeException("通道未实现deposit方法：" . $classname);
            $this->reportCashierChannelCodeException($order, $classname, $depositOrderId, $channelName, $e, $logService);
            return [[], $e->getMessage()];
        }

        try {
            $payment = App::make($classname);
            $payInfo = $payment->deposit($depositOrderId);

            if (!empty($payInfo)) {
                return [$payInfo, null];
            }

            $error = $payment->error ?: "渠道返回空信息";
            $logService->excute($order->id, "收银台通道返回失败", [
                '通道名称' => $channelName,
                '通道类名' => $classname,
                '请求订单ID' => $depositOrderId,
                '失败原因' => $error,
            ], "error");
            bob_send_channel_exception_notice([
                'error' => $error,
                "ordernumber" => $order->ordernumber,
                "title" => "通道调用异常报警",
                "channel_name" => $channelName,
                "action" => "代收渠道调用异常"
            ]);

            return [[], $error];
        } catch (\Throwable $e) {
            $this->noticeCashierChannelException($order, $classname, $depositOrderId, $channelName, $e, $logService);
            return [[], $e->getMessage() ?: "通道调用异常"];
        }
    }

    private function noticeCashierChannelException(DepositOrder $order, string $classname, int $depositOrderId, string $channelName, \Throwable $e, $logService): void
    {
        $logService->excute($order->id, "收银台通道调用异常", [
            '通道名称' => $channelName,
            '通道类名' => $classname,
            '请求订单ID' => $depositOrderId,
            '异常类型' => get_class($e),
            '异常信息' => $e->getMessage(),
        ], "error");

        bob_send_channel_exception_notice([
            'error' => $e->getMessage(),
            "ordernumber" => $order->ordernumber,
            "title" => "通道调用异常报警",
            "channel_name" => $channelName,
            "action" => "代收渠道调用异常"
        ]);
    }

    private function reportCashierChannelCodeException(DepositOrder $order, string $classname, int $depositOrderId, string $channelName, \Throwable $e, $logService): void
    {
        $this->noticeCashierChannelException($order, $classname, $depositOrderId, $channelName, $e, $logService);

        App::make(ReportExceptionService::class)->report('收银台代收通道代码错误', $e, [
            'order_id' => $order->id,
            'ordernumber' => $order->ordernumber,
            'channel_name' => $channelName,
            'channel_class' => $classname,
            'deposit_order_id' => $depositOrderId,
        ]);
    }

    public function downloadQrcode(Request $request)
    {
        $ordernumber = $request->input('ordernumber');
        $validate = Validator::make($request->only(['ordernumber']), ['ordernumber' => ['required', 'string', new DepositOrderNumber()]]);
        if ($validate->fails()) {
            abort(404, 'File not found');
        }
        $downloadLockKey = 'cashier:qrcode_download:' . md5($ordernumber);
        if (Cache::has($downloadLockKey)) {
            return response('', 204);
        }
        Cache::put($downloadLockKey, 1, now()->addSeconds(5));

        $result = App::make(OrderCacheService::class)->getDepositByOrdernumber($ordernumber, true);
        if ($result) {
            if (($result['status'] ?? 0) != 3) {
                abort(404, 'File not found');
            }
            if (empty($result['collection_qrcode'])) {
                abort(404, 'File not found');
            }
            $filePath = $this->resolveLocalQrcodePath($result['collection_qrcode']);
            if (!$filePath) {
                abort(404, 'File not found');
            }
            $fileName = basename($filePath);
            $headers = [
                'Content-Type' => mime_content_type($filePath) ?: 'application/octet-stream',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ];
            return response()->download($filePath, $fileName, $headers);
        }
        abort(404, 'File not found');
    }

    private function resolveLocalQrcodePath(string $path): ?string
    {
        $path = str_replace("&amp;", '&', urldecode($path));
        $url = parse_url($path);
        if (!empty($url['scheme']) && in_array(strtolower($url['scheme']), ['http', 'https'], true)) {
            $host = $url['host'] ?? '';
            $domainConfigService = App::make(DomainConfigService::class);
            if (!in_array($domainConfigService->rootDomain($host), $domainConfigService->cashierLocalRootDomains(request()->getHost()), true)) {
                return $this->downloadRemoteQrcode($path);
            }
        }

        $path = ltrim($url['path'] ?? $path, '/');
        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        if (str_starts_with($path, 'storage/')) {
            $storagePath = storage_path('app/public/' . substr($path, strlen('storage/')));
            return $this->safeLocalQrcodePath($storagePath);
        }

        $publicPath = public_path($path);
        $safePublicPath = $this->safeLocalQrcodePath($publicPath);
        if ($safePublicPath) {
            return $safePublicPath;
        }

        $storagePath = storage_path('app/public/' . $path);
        return $this->safeLocalQrcodePath($storagePath);
    }

    private function downloadRemoteQrcode(string $url): ?string
    {
        $maxBytes = 5 * 1024 * 1024;
        $directory = storage_path('app/public/qrcode');
        $urlHash = md5($url);
        $cachePrefix = $directory . '/remote_qrcode_' . $urlHash;

        foreach (['jpg', 'jpeg', 'png', 'gif', 'webp'] as $extension) {
            $cachedPath = $cachePrefix . '.' . $extension;
            $safeCachedPath = $this->safeLocalQrcodePath($cachedPath);
            if ($safeCachedPath && filesize($safeCachedPath) > 0) {
                return $safeCachedPath;
            }
        }

        $lock = Cache::lock('cashier:remote_qrcode:' . $urlHash, 10);
        try {
            $lock->block(3);
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            return null;
        }

        try {
            File::isDirectory($directory) or File::makeDirectory($directory, 0777, true, true);

            foreach (['jpg', 'jpeg', 'png', 'gif', 'webp'] as $extension) {
                $cachedPath = $cachePrefix . '.' . $extension;
                $safeCachedPath = $this->safeLocalQrcodePath($cachedPath);
                if ($safeCachedPath && filesize($safeCachedPath) > 0) {
                    return $safeCachedPath;
                }
            }

            $response = (new Client())->request('GET', $url, [
                'timeout' => 5,
                'connect_timeout' => 3,
                'http_errors' => false,
                'stream' => true,
            ]);

            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                return null;
            }

            $contentType = strtolower(trim(explode(';', $response->getHeaderLine('Content-Type'))[0] ?? ''));
            if (!str_starts_with($contentType, 'image/')) {
                return null;
            }

            $contentLength = intval($response->getHeaderLine('Content-Length'));
            if ($contentLength > $maxBytes) {
                return null;
            }

            $extension = $this->qrcodeExtensionByContentType($contentType);
            $filePath = $cachePrefix . '.' . $extension;
            $tempPath = $filePath . '.tmp';
            $body = $response->getBody();
            $handle = fopen($tempPath, 'wb');
            if (!$handle) {
                return null;
            }

            $written = 0;
            while (!$body->eof()) {
                $chunk = $body->read(8192);
                $written += strlen($chunk);
                if ($written > $maxBytes) {
                    fclose($handle);
                    @unlink($tempPath);
                    return null;
                }
                if (fwrite($handle, $chunk) === false) {
                    fclose($handle);
                    @unlink($tempPath);
                    return null;
                }
            }
            fclose($handle);

            if ($written <= 0 || !is_file($tempPath) || filesize($tempPath) <= 0) {
                @unlink($tempPath);
                return null;
            }

            rename($tempPath, $filePath);
            return realpath($filePath) ?: null;
        } catch (\Throwable $e) {
            App::make(ReportExceptionService::class)->report('收银台远程二维码下载失败', $e, [
                'url' => $url,
            ]);
            return null;
        } finally {
            optional($lock)->release();
        }
    }

    private function isAllowedQrcodePath(string $path): bool
    {
        $realPath = realpath($path);
        if (!$realPath) {
            return false;
        }

        foreach ([public_path(), storage_path('app/public')] as $basePath) {
            $realBasePath = realpath($basePath);
            if ($realBasePath && str_starts_with($realPath, rtrim($realBasePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        return false;
    }

    private function safeLocalQrcodePath(string $path): ?string
    {
        if (!$this->isAllowedQrcodePath($path)) {
            return null;
        }

        $realPath = realpath($path);
        if (!$realPath || !@is_file($realPath)) {
            return null;
        }

        return $realPath;
    }

    private function qrcodeExtensionByContentType(string $contentType): string
    {
        return [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ][$contentType] ?? 'jpg';
    }

    public function uploadPayCertificate(CashierUploadPayCertificateRequest $request)
    {
        $ordernumber = $request->input('ordernumber');
        $newCertificate = $request->file("file")->store('cashier', 'public');
        if (!$newCertificate) {
            return $this->error("提交失败，请稍后重试");
        }

        $result = null;
        $oldCertificate = '';
        $sendPendingNotice = false;
        $noticeOrdernumber = '';
        $noticeUserId = 0;
        $returnUrl = '';

        DB::beginTransaction();
        try {
            $fields = array_values(array_unique(array_merge(CacheConstPrefixService::CACHE_DEPOSIT_FILED, [
                'id',
                'ordernumber',
                'status',
                'channel_id',
                'user_id',
                'return_url',
                'pay_status',
                'pay_certificate',
            ])));
            $result = DepositOrder::where('ordernumber', $ordernumber)->where('status', 3)->lockForUpdate()->first($fields);
            if (!$result) {
                DB::rollBack();
                Storage::disk('public')->delete($newCertificate);
                return $this->error("非法操作");
            }

            if ((int)$result->pay_status === 2) {
                DB::rollBack();
                Storage::disk('public')->delete($newCertificate);
                return $this->error(__("cashier.not_request_again"));
            }

            $oldCertificate = (string)$result->pay_certificate;
            $data = ['pay_status' => 2, 'confirm_time' => time()];
            $data['pay_certificate'] = $newCertificate;
            if ($result->channel_id == 1) {
                $data['status'] = 7;
                $sendPendingNotice = true;
            }
            $result->fill($data);
            $result->save();

            $noticeOrdernumber = $result->ordernumber;
            $noticeUserId = (int) $result->user_id;
            $returnUrl = $result->return_url;

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Storage::disk('public')->delete($newCertificate);
            app(ReportExceptionService::class)->report('收银台上传付款凭证失败', $e, [
                'ordernumber' => $ordernumber,
            ]);
            return $this->error("提交失败，请稍后重试");
        }

        App::make(OrderCacheService::class)->putDeposit($result, true);

        $createDepositOrderLogService = App::make(CreateDepositOrderLogService::class);
        $createDepositOrderLogService->excute($result->id, "收银台上传付款凭证", [
            'ip' => $request->getClientIp(),
            'ua' => $request->userAgent(),
            '文件路径' => $newCertificate,
        ]);

        if ($oldCertificate !== '' && $oldCertificate !== $newCertificate) {
            Storage::disk('public')->delete($oldCertificate);
        }

        if ($sendPendingNotice) {
            try {
                bob_send_system_deposit_notice(['success_text' => '代收订单待确认，订单号：' . $noticeOrdernumber, 'voice_id' => 'deposit_7', 'id' => 7]);
            } catch (\Throwable $e) {
                app(ReportExceptionService::class)->report('收银台上传付款凭证通知失败', $e, [
                    'ordernumber' => $noticeOrdernumber,
                ]);
            }
        }

        if ($noticeUserId > 0) {
            try {
                event(new UserDepositOrderNoticeEvent(['user_id' => $noticeUserId, 'voice_url' => asset("voice/deposit.mp3"), 'text' => "您有新的待确认代收订单"]));
            } catch (\Throwable $e) {
                app(ReportExceptionService::class)->report('收银台上传付款凭证事件失败', $e, [
                    'ordernumber' => $noticeOrdernumber,
                    'user_id' => $noticeUserId,
                ]);
            }
        }

        return $this->success("操作成功", ['return_url' => $returnUrl]);
    }


    public function callbackUrl(Request $request)
    {
        return "OK";
    }

    public function gold(Request $request)
    {
        if (!App::make(CheckCashierAreaService::class)->excute()) {
            abort(404);
        }
        $ordernumber = $request->input('no');
        if (empty($ordernumber) || !(new DepositOrderNumber())->passes('no', $ordernumber)) {
            abort(404);
        }

        $order = App::make(OrderCacheService::class)->getDepositByOrdernumber($ordernumber, true);
        if (empty($order) || (int)($order['status'] ?? 0) !== 3 || empty($order['alipay_uid'])) {
            abort(404);
        }

        return view('gold', ['ordernumber' => $ordernumber, 'app_name' => payment_app_name()]);
    }

    public function confirmPay(Request $request)
    {
        $data = $request->only(['ordernumber', 'locale', 'fiveFigureOrder']);
        $allowLocales = array_values(array_unique(array_filter(array_column(config('default.currency', []), 'lang'))));
        $locale = $data['locale'] ?? 'zh_CN';
        App::setLocale(in_array($locale, $allowLocales, true) ? $locale : 'zh_CN');

        $validator = Validator::make(
            $data,
            ['ordernumber' => ['bail', 'required', 'string', new DepositOrderNumber()]]
        );
        if ($validator->fails()) {
            return $this->error(__("cashier.params_error"));
        }

        $order = App::make(OrderCacheService::class)->getDepositByOrdernumber($data['ordernumber']);
        if (empty($order)) {
            return $this->error(__('cashier.order_does_not_exist'));
        }

        if (!in_array((int)($order['status'] ?? 0), [1, 3], true)) {
            return $this->error(trans("api.order_status_invalid"), '订单状态不允许操作');
        }

        $result = App::make(DepositOrderConfirmPayService::class)->confirmByOrdernumber($data['ordernumber'], $data, $order);
        if (empty($result['success'])) {
            return $this->error($result['message'] ?? __("cashier.params_error"), $result['zh_message'] ?? '', $result['error_code'] ?? 10001);
        }

        return $this->success($result['message'] ?? __('cashier.success'), $result['data'] ?? []);
    }
}
