<?php

namespace App\MerchantAdmin\Form\SettlementOrder;


use Dcat\Admin\Admin;
use App\Models\BankCode;
use App\Models\AgentUser;
use Dcat\EasyExcel\Excel;
use App\Models\MerchantInfo;
use Dcat\Admin\Widgets\Form;
use App\Models\TransferOrder;
use App\Models\MerchantPayment;
use App\Rules\DecimalTwoPlaces;
use Dcat\Admin\Traits\LazyWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Lang;
use App\Jobs\CacheTransferOrderInfoJob;
use App\Services\Const\LogConstService;
use Illuminate\Support\Facades\Storage;
use App\Services\IpWhite\CheckIpService;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Services\Common\SystemLogService;
use App\Services\Google\AdminGoogle2faService;
use App\Services\Merchant\MerchantBalanceChangeService;
use App\Services\Cache\Merchant\CacheMerchantBaseInfoService;
use App\Services\Merchant\GetMerchantAvailableBalanceService;
use App\Services\TransferOrderLog\CreateTransferOrderLogService;
use App\Services\MerchantAdmin\MerchantSettlementUploadTokenService;
use App\Services\Cache\MerchantPayment\GetMerchantPaymentRateListService;
use App\Services\Cache\MerchantPayment\GetMerchantTransferBankRateService;


class ApplySettlementOrderForm extends Form implements LazyRenderable
{

    use LazyWidget;

    /**
     * Handle the form request.
     *
     * @param array $input
     *
     * @return mixed
     */
    public function handle(array $input)
    {
        DB::beginTransaction();
        try {
            $password = $input['password'] ?? null;
            $upload_type = $input['upload_type'] ?? 0;
            $google_2fa_code = $input['google_2fa_code'] ?? null;
            $merchant_info = MerchantInfo::where('merchant_user_id', bob_merchant_user_pid())->with('merchant_user')->first();
            if (!$merchant_info) throw new \Exception($this->handleField("illegal_operation"));

            $login_white_ip = $merchant_info->merchant_user->login_white_ip;
            if (empty($login_white_ip)) {
                app(\App\Services\SystemNotice\SystemNoticeService::class)->warning("system_manual_notice", ['mid' => bob_merchant_user_pid(), 'message' => '请设置提现白名单', 'ip' => bob_ip()]);
                return throw new \Exception($this->handleField("set_login_white_ip"));
            }
            if (!empty($login_white_ip)) {
                $checkIpService = App::make(CheckIpService::class)->excute(bob_format_muti_data_to_array($login_white_ip));
                if (!$checkIpService) {
                    app(\App\Services\SystemNotice\SystemNoticeService::class)->warning("system_manual_notice", ['mid' => bob_merchant_user_pid(), 'message' => '提交IP不在白名单内', 'ip' => bob_ip()]);
                    return throw new \Exception($this->handleField("none_login_white_ip"));
                }
            }
            if (!Hash::check($password, Admin::user()->amount_password)) {
                return throw new \Exception($this->handleField("amount_password_error"));
            }

            $mid = bob_merchant_user_pid();
            $type = 1;
            $status = 6;
            $merchant_action_id = Admin::user()->id;
            $currency_id = $merchant_info->currency_id;
            $merchant_info = App::make(CacheMerchantBaseInfoService::class)->excute($mid);
            $merchant_agent1_id = $merchant_info['agent_user_id'];
            $merchant_agent2_id = $merchant_info['merchant_agent2_id'];
            $merchant_agent3_id = $merchant_info['merchant_agent3_id'];

            $data = [];
            $batchExcelFile = '';
            $merchant_payment = App::make(GetMerchantPaymentRateListService::class)->excute($mid,7);
            $merchant_payment_bank_result = App::make(GetMerchantTransferBankRateService::class)->excute($mid);

            if ($upload_type == 0) {
                $bank_id = $input['bank_id'] ?? null;
                $card_no = trim((string)($input['card_no'] ?? ''));
                $bank_branch = $input['bank_branch'] ?? null;
                $holder_name = trim((string)($input['holder_name'] ?? ''));
                $amount = $input['amount'] ?? 0;
                $amount = floatval($amount);
                $order_no = $input['order_no'] ?? null;
                if (empty($order_no)) {
                    $order_no = bob_ordernumber('m');
                }
                $bank_name = $input['bank_name'] ?? null;
                if ($bank_id == 0) {
                    $bank_code = "OB";
                    if (empty($bank_name)) throw new \Exception($this->handleField("bank_name_not_empty"));
                    if ($currency_id == 3) {
                        if (empty($bank_branch)) throw new \Exception($this->handleField("bank_branch_not_empty"));
                    }
                } else {
                    // 银行必须属于当前商户币种，避免手动传入其他币种银行。
                    $bank = BankCode::where('currency_id', $currency_id)->whereKey($bank_id)->first();
                    if (empty($bank)) throw new \Exception($this->handleLabel("bank_code_tip_2", ['k' => 0]));
                    $bank_code = $bank->code;
                }

                if ($amount <= 0) {
                    throw new \Exception($this->handleField("amount_glt_zero"));
                }
                if ($card_no === '') {
                    throw new \Exception($this->settlementField("add_card_no_required"));
                }
                if ($holder_name === '') {
                    throw new \Exception($this->settlementField("add_holder_name_required"));
                }

                if (empty($merchant_payment)) {
                    return throw new \Exception($this->handleField("none_set_transfer_rate"));
                }

                $merchant_payment_arr = $this->matchSettlementRate($merchant_payment, $merchant_payment_bank_result, $amount, (int)$bank_id);
                if (empty($merchant_payment_arr)) {
                    return throw new \Exception($this->handleField("none_find_transfer_rate"));
                }

                $merchant_rate = floatval($merchant_payment_arr['pay_rate']) / 100;
                $merchant_agent1_rate = floatval($merchant_payment_arr['agent1_rate']) / 100;
                $merchant_agent2_rate = floatval($merchant_payment_arr['agent2_rate']) / 100;
                $merchant_agent3_rate = floatval($merchant_payment_arr['agent3_rate']) / 100;
                $merchant_fee = bob_amount_format($amount * $merchant_rate);

                $data[] = [
                    'ip' => bob_ip(),
                    'true_ip' => bob_ip(),
                    'bank_id' => $bank_id,
                    'mid' => $mid,
                    'type' => $type,
                    'merchant_action_id' => $merchant_action_id,
                    'currency_id' => $currency_id,
                    'bank_code' => $bank_code,
                    'merchant_agent1_id' => $merchant_agent1_id,
                    'merchant_agent2_id' => $merchant_agent2_id,
                    'merchant_agent3_id' => $merchant_agent3_id,
                    'ordernumber' => bob_ordernumber("s"),
                    'card_no' => $card_no,
                    'holder_name' => $holder_name,
                    'amount' => $amount,
                    'order_no' => $order_no,
                    'bank_name' => $bank_name,
                    'bank_branch' => $bank_branch,
                    'merchant_rate' => $merchant_rate,
                    'merchant_fee' => $merchant_fee,
                    'merchant_extra_fee' => 0,
                    'merchant_agent1_rate' => $merchant_agent1_rate,
                    'merchant_agent2_rate' => $merchant_agent2_rate,
                    'merchant_agent3_rate' => $merchant_agent3_rate,
                    'status' => $status,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
            } else {
                $excel_file = $input['excel_file'] ?? '';
                if (empty($excel_file)) throw new \Exception($this->handleField("batch_upload_data"));
                $batchExcelFile = (string)$excel_file;
                $firstSheet = Excel::import($this->resolveSettlementUploadPath((string)$excel_file))->first()->toArray();
                if (!is_array($firstSheet) || empty($firstSheet)) throw new \Exception($this->handleField("batch_upload_data_empty"));
                foreach ($firstSheet as $k => $v) {
                    $holderName = trim((string)($v['holder_name'] ?? ''));
                    $cardNo = trim((string)($v['card_no'] ?? ''));
                    $bankCodeValue = trim((string)($v['bank_code'] ?? ''));
                    if ($holderName === '') {
                        throw new \Exception($this->handleLabel("holder_name_tip", ['k' => $k]));
                    }
                    if ($cardNo === '') {
                        throw new \Exception($this->handleLabel("card_no_tip", ['k' => $k]));
                    }
                    if (!isset($v['amount']) || empty($v['amount'])) {
                        throw new \Exception($this->handleLabel("amount_tip_1", ['k' => $k]));
                    }
                    if (floatval($v['amount']) <= 0) {
                        throw new \Exception($this->handleLabel("amount_tip_2", ['k' => $k]));
                    }
                    if ($bankCodeValue === '') {
                        throw new \Exception($this->handleLabel("bank_code_tip", ['k' => $k]));
                    }
                    // 批量导入时也按当前商户币种匹配银行编码。
                    $bank = BankCode::where('currency_id', $currency_id)->where('code', $bankCodeValue)->first();
                    if (empty($bank)) throw new \Exception($this->handleLabel("bank_code_tip_2", ['k' => $k]));
                    $bank_id = $bank->id;
                    $bank_name = $bank->bank_name;
                    $bank_code = $bank->code;
                    if ($currency_id == 3) {
                        if (trim((string)($v['bank_branch'] ?? '')) === '') {
                            throw new \Exception($this->handleLabel("bank_branch_tip", ['k' => $k]));
                        }
                    }

                    if (empty($merchant_payment)) {
                        throw new \Exception($this->handleLabel("none_find_payment_rate", ['k' => $k, 'amount' => $v['amount']]));
                    }

                    $merchant_payment_arr = $this->matchSettlementRate($merchant_payment, $merchant_payment_bank_result, floatval($v['amount']), (int)$bank_id);
                    if (empty($merchant_payment_arr)) {
                        throw new \Exception($this->handleLabel("none_find_payment_rate", ['k' => $k, 'amount' => $v['amount']]));
                    }

                    $merchant_rate = floatval($merchant_payment_arr['pay_rate']) / 100;
                    $merchant_agent1_rate = floatval($merchant_payment_arr['agent1_rate']) / 100;
                    $merchant_agent2_rate = floatval($merchant_payment_arr['agent2_rate']) / 100;
                    $merchant_agent3_rate = floatval($merchant_payment_arr['agent3_rate']) / 100;
                    $merchant_fee = bob_amount_format(floatval($v['amount']) * $merchant_rate);

                    $order_no = trim((string)($v['order_no'] ?? '')) !== '' ? trim((string)$v['order_no']) : bob_ordernumber('m');
                    $data[] = [
                        'ip' => bob_ip(),
                        'true_ip' => bob_ip(),
                        'bank_id' => $bank_id,
                        'mid' => $mid,
                        'type' => $type,
                        'merchant_action_id' => $merchant_action_id,
                        'currency_id' => $currency_id,
                        'bank_code' => $bank_code,
                        'merchant_agent1_id' => $merchant_agent1_id,
                        'merchant_agent2_id' => $merchant_agent2_id,
                        'merchant_agent3_id' => $merchant_agent3_id,
                        'ordernumber' => bob_ordernumber("s"),
                        'card_no' => $cardNo,
                        'holder_name' => $holderName,
                        'amount' => floatval($v['amount']),
                        'order_no' => $order_no,
                        'bank_branch' => trim((string)($v['bank_branch'] ?? '')),
                        'bank_name' => $bank_name,
                        'merchant_rate' => $merchant_rate,
                        'merchant_fee' => $merchant_fee,
                        'merchant_extra_fee' => 0,
                        'merchant_agent1_rate' => $merchant_agent1_rate,
                        'merchant_agent2_rate' => $merchant_agent2_rate,
                        'merchant_agent3_rate' => $merchant_agent3_rate,
                        'status' => $status,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ];
                }
            }
            if (empty($data)) throw new \Exception($this->handleLabel("data_empty"));
            $total_amount = floatval(collect($data)->sum('amount'));
            $total_fee = floatval(collect($data)->sum(function ($item) {
                return floatval($item['amount']) * floatval($item['merchant_rate']);
            }));

            $getMerchantAvailableBalanceService = App::make(GetMerchantAvailableBalanceService::class);
            $balance = $getMerchantAvailableBalanceService->excute(bob_merchant_user_pid());
            if ($balance < $total_amount + $total_fee) {
                return throw new \Exception($this->handleLabel("balance_insufficient"));
            }
            app(AdminGoogle2faService::class)->verify($google_2fa_code);
            $result = TransferOrder::insert($data);

            foreach ($data as $sk => $sv) {
                $transfer_order = TransferOrder::where('ordernumber', $sv['ordernumber'])->first(['id', 'amount', 'mid','ordernumber','order_no']);
                if ($transfer_order) {
                    dispatch(new CacheTransferOrderInfoJob($sv['ordernumber']))->onQueue('query');
                    $createTransferOrderLogService = App::makeWith(CreateTransferOrderLogService::class, ['filename' => LogConstService::TRANSFER_ORDER_LOG_PREFIX . $transfer_order->id]);
                    $createTransferOrderLogService->excute($transfer_order->id, "创建订单" . bob_ip(), $data, "info");
                    $merchantBalanceChangeService = App::make(MerchantBalanceChangeService::class);
                    $merchantBalanceChangeService->excute([
                        'mid' => $transfer_order->mid,
                        'amount' => -$transfer_order->amount,
                        'fee' => $sv['merchant_fee'] ?? 0,
                        'type' => 6,
                        'type_id' => $transfer_order->id,
                        'currency_id' => $currency_id,
                        'payment_id' => 7,
                        'order_type' => 2,
                        'admin_id' => $merchant_action_id,
                        'ordernumber' => $transfer_order->ordernumber,
                        'order_no' => $transfer_order->order_no
                    ]);
                }
            }
            bob_send_system_settlement_notice(['success_text' => "结算订单处理中", 'voice_id' => 'settlement_6', 'id' => 6]);
            DB::commit();
            if ((int)$upload_type === 1 && $batchExcelFile !== '') {
                app(MerchantSettlementUploadTokenService::class)->consume($batchExcelFile);
            }
            try {
                app(SystemLogService::class)->logAction(
                    actionKey: 'merchant.settlement.apply',
                    text: '提交 结算订单',
                    subject: Admin::user(),
                    properties: [
                        'merchant_user_id' => bob_merchant_user_pid(),
                        'upload_type' => (int)$upload_type,
                        'total_count' => count($data),
                        'total_amount' => $total_amount,
                    ],
                    remark: '提交 结算订单（笔数:' . count($data) . '，金额:' . $total_amount . '）',
                    logType: 'operation',
                    actionMethod: 'POST',
                    appType: 'merchant',
                    user: Admin::user()
                );
            } catch (\Throwable $e) {
            }
            return $this->response()->success($this->handleLabel("settlement_submit_success"))->redirect("settlement-orders");
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->response()->error($e->getMessage());
        }
    }

    private function matchSettlementRate($merchantPayment, array $bankRates, float $amount, int $bankId): array
    {
        $rate = $this->pickBestRate($merchantPayment, $amount);
        if (empty($rate)) {
            return [];
        }

        $bankRate = $this->pickBestBankRate($bankRates, $amount, $bankId);
        if (!empty($bankRate)) {
            return $this->rateFields($bankRate);
        }

        return $this->rateFields($rate);
    }

    private function pickBestRate($rates, float $amount): array
    {
        $matched = [];

        foreach ($rates as $rate) {
            $rate = is_object($rate) ? (array)$rate : $rate;
            if (!is_array($rate) || !$this->amountInRateRange($rate, $amount)) {
                continue;
            }
            if (empty($matched) || floatval($rate['pay_rate'] ?? 0) > floatval($matched['pay_rate'] ?? 0)) {
                $matched = $rate;
            }
        }

        return $matched;
    }

    private function pickBestBankRate(array $bankRates, float $amount, int $bankId): array
    {
        if ($bankId <= 0) {
            return [];
        }

        $matched = [];
        foreach ($bankRates as $rate) {
            $rate = is_object($rate) ? (array)$rate : $rate;
            if (!is_array($rate) || intval($rate['bank_id'] ?? 0) !== $bankId || !$this->amountInRateRange($rate, $amount)) {
                continue;
            }
            if (intval($rate['channel_id'] ?? 0) !== 0) {
                continue;
            }
            if (empty($matched) || floatval($rate['pay_rate'] ?? 0) > floatval($matched['pay_rate'] ?? 0)) {
                $matched = $rate;
            }
        }

        return $matched;
    }

    private function amountInRateRange(array $rate, float $amount): bool
    {
        $min = floatval($rate['min_limit_amount'] ?? 0);
        $max = floatval($rate['max_limit_amount'] ?? 0);

        return ($min == 0.0 && $max == 0.0) || ($min <= $amount && $amount <= $max);
    }

    private function rateFields(array $rate): array
    {
        return [
            'pay_rate' => $rate['pay_rate'] ?? 0,
            'agent1_rate' => $rate['agent1_rate'] ?? 0,
            'agent2_rate' => $rate['agent2_rate'] ?? 0,
            'agent3_rate' => $rate['agent3_rate'] ?? 0,
        ];
    }

    private function resolveSettlementUploadPath(string $excelFile): string
    {
        app(MerchantSettlementUploadTokenService::class)->assertUsable($excelFile);

        if ($excelFile === '' || str_contains($excelFile, "\0") || $excelFile !== ltrim($excelFile, '/\\')) {
            throw new \Exception($this->handleField("batch_upload_data"));
        }

        $extension = strtolower(pathinfo($excelFile, PATHINFO_EXTENSION));
        if ($extension !== 'xlsx') {
            throw new \Exception($this->handleField("batch_upload_data"));
        }

        $disk = Storage::disk(config('admin.upload.disk', 'admin'));
        $directory = trim((string)config('admin.upload.directory.file', 'files'), '/\\');
        $rootPath = $disk->path($directory);
        File::ensureDirectoryExists($rootPath);

        if ($excelFile !== $directory && !str_starts_with($excelFile, $directory . '/')) {
            throw new \Exception($this->handleField("batch_upload_data"));
        }

        $root = realpath($rootPath);
        $path = realpath($disk->path($excelFile));
        if ($root === false || $path === false || !File::isFile($path)) {
            throw new \Exception($this->handleField("batch_upload_data"));
        }

        $root = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (strncmp($path, $root, strlen($root)) !== 0) {
            throw new \Exception($this->handleField("batch_upload_data"));
        }

        return $path;
    }

    private function settlementField(string $key): string
    {
        $translationKey = "settlement-order.fields.{$key}";

        return Lang::has($translationKey) ? __($translationKey) : admin_trans_field($key);
    }

    private function handleField(string $key): string
    {
        $translationKey = "handle-form.fields.{$key}";

        return Lang::has($translationKey) ? __($translationKey) : admin_trans_field($key);
    }

    private function handleLabel(string $key, array $replace = []): string
    {
        $translationKey = "handle-form.labels.{$key}";

        return Lang::has($translationKey) ? __($translationKey, $replace) : admin_trans_label($key, $replace);
    }

    private function settlementUploadTypeOptions(): array
    {
        $translationKey = 'renderable.options.upload_type_text';

        if (Lang::has($translationKey)) {
            return __($translationKey);
        }

        return [
            0 => admin_trans_option(0, 'upload_type_text'),
            1 => admin_trans_option(1, 'upload_type_text'),
        ];
    }

    protected function authorize($user): bool
    {
        if(Admin::user()->can("merchant-settlement-order-add")){
            return true;
        }
        return false;
    }

    /**
     * Build a form here.
     */
    public function form()
    {
        Admin::script(
            <<<JS
        $(document).off('change', '.field_bank_id').on('change', '.field_bank_id', function () {
            if($(this).val() == 0){
                $('.field_bank_name').parent().parent().parent().removeClass("hidden");
            }else{
                $('.field_bank_name').parent().parent().parent().addClass("hidden");
            }
        });
JS
        );
        $this->password("password", $this->settlementField("password"))->required();
        $info = MerchantInfo::where('merchant_user_id', bob_merchant_user_pid())->first(['currency_id']);
        $this->radio('upload_type', $this->settlementField("upload_type"))->options($this->settlementUploadTypeOptions())->when(0, function () use ($info) {
            $this->select("bank_id", $this->settlementField("bank_id"))->options(BankCode::where('currency_id', optional($info)->offsetGet('currency_id'))->pluck('name', 'id')->prepend($this->settlementField("no_select_bank"), 0))->default(0)->disableClearButton();
            $this->text("bank_name", $this->settlementField("add_bank_name"))->rules("nullable|max:90", ['max' => $this->settlementField("add_bank_name_max")])->help($this->settlementField("add_bank_name_help"));
            if (optional($info)->offsetGet('currency_id') == 3) {
                $this->text("bank_branch", $this->settlementField("bank_branch_1"))->rules("nullable|max:90", ['max' => $this->settlementField("bank_branch_1_max")])->help($this->settlementField("required"));
            } else {
                $this->text("bank_branch", $this->settlementField("bank_branch_2"))->rules("nullable|max:90", ['max' => $this->settlementField("bank_branch_2_max")])->help($this->settlementField("no_required"));
            }
            $this->text("card_no", $this->settlementField("add_card_no"))->rules("required_if:upload_type,0|max:90", ['required_if' => $this->settlementField("add_card_no_required"), 'max' => $this->settlementField("add_card_no_max")])->setLabelClass('asterisk');
            $this->text("holder_name", $this->settlementField("add_holder_name"))->rules("required_if:upload_type,0|max:90", ['required_if' => $this->settlementField("add_holder_name_required"), 'max' => $this->settlementField("add_holder_name_max")])->setLabelClass('asterisk');
            $this->text('amount', $this->settlementField("add_amount"))->rules(['numeric', 'between:0,9999999999', new DecimalTwoPlaces()], ['numeric' => $this->settlementField("add_amount_numeric"), 'between' => $this->settlementField("add_amount_between")])->default(0)->setLabelClass('asterisk')->width(2)->prepend('¥');
            $this->text("order_no", admin_trans_label("order_no"))->rules("nullable|max:90", ['max' => $this->settlementField("order_no_max")])->help($this->settlementField("order_no_help"));
        })->when(1, function () use ($info) {
            $help = "<a href='" . route('downExcel') . "' target='_blank'>".$this->settlementField("download_example")."</a><br/>【holder_name】".$this->settlementField("download_holder_name")."，<span style='color: red'>".$this->settlementField("required")."</span><br/>【card_no】".$this->settlementField("download_card_no")."，<span style='color: red'>".$this->settlementField("required")."</span><br/>【amount】".$this->settlementField("add_amount")."，<span style='color: red'>".$this->settlementField("required")."</span><br/>【bank_code】".$this->settlementField("bank_code_1")."，<span style='color: red'>".$this->settlementField("required")."</span><br/>【order_no】".$this->settlementField("download_order_no")."<br/>【bank_name】".$this->settlementField("download_bank_name_1")."<br/>【bank_branch】".$this->settlementField("download_bank_branch_1");
            if (optional($info)->offsetGet('currency_id') == 3) {
                $help = "<a href='" . route('downExcel') . "' target='_blank'>".$this->settlementField("download_example")."</a><br/>【holder_name】".$this->settlementField("download_holder_name")."，<span style='color: red'>".$this->settlementField("required")."</span><br/>【card_no】".$this->settlementField("download_card_no")."，<span style='color: red'>".$this->settlementField("required")."</span><br/>【amount】".$this->settlementField("add_amount")."，<span style='color: red'>".$this->settlementField("required")."</span><br/>【bank_code】".$this->settlementField("bank_code_2")."，<span style='color: red'>".$this->settlementField("required")."</span><br/>【order_no】".$this->settlementField("download_order_no")."<br/>【bank_name】".$this->settlementField("download_bank_name_2")."，<span style='color: red'>".$this->settlementField("required")."</span><br/>【bank_branch】".$this->settlementField("download_bank_branch_2")."，<span style='color: red'>".$this->settlementField("required")."</span>";
            }
            $this->file("excel_file", $this->settlementField("excel_file"))->uniqueName()->autoUpload()->accept('xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')->help($help);
        })->default(0)->setLabelClass('asterisk');
        app(AdminGoogle2faService::class)->appendField($this);
    }
}
