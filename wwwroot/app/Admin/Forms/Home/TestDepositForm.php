<?php

namespace App\Admin\Forms\Home;

use Throwable;
use Dcat\Admin\Admin;
use RuntimeException;
use App\Traits\HttpTrait;
use App\Models\MerchantInfo;
use Dcat\Admin\Widgets\Form;
use App\Services\Common\SystemLogService;

class TestDepositForm extends Form
{
    use HttpTrait;

    protected function savedScript()
    {
        return <<<JS
        if (!data.status) {
            Dcat.error(data.data.message);
            return false;
        }
        if(data.status && data.data && data.data.data){
            $(".field_json_content").val(JSON.stringify(data.data));
            return;
        }
        if(data.data.then.action == 'refresh'){
             Dcat.success("代收订单生成成功！");
            return false;
        }
        window.open(data.data.then.value);
        return false;
JS;
    }

    public function handle(array $input)
    {
        try {
            $admin = Admin::user();
            $json = intval($input['json'] ?? 1);
            $paymentId = intval($input['payment_id'] ?? 0);
            $mid = intval($input['mid'] ?? 0);
            $ip = trim((string) ($input['ip'] ?? '')) ?: request()->ip();
            $merchant = $this->getMerchant($mid);
            $data = $this->buildPayload($input, $merchant, $paymentId, $ip);
            $response = $this->sendRequest($merchant, $data);

            if (!$response) {
                return $this->response()->error($this->error ?: '接口请求失败');
            }

            if (!$response->successful()) {
                return $this->response()->error($response->reason());
            }

            $result = $response->json() ?: [];
            if (intval($result['code'] ?? 0) !== 200) {
                return $this->response()->error($result['message'] ?? '下单失败');
            }

            $this->writeSystemLog($merchant, $paymentId, $data, $ip, $json, $admin);

            if ($json === 2) {
                return $this->response()->success('下单成功')->data($result);
            }

            $cashierUrl = $result['data']['url'] ?? '';
            if ($cashierUrl === '') {
                return $this->response()->error('接口未返回收银台地址');
            }

            return $this->response()->redirect($cashierUrl);
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('admin_operation_manager_api_deposit_test');
    }

    public function form()
    {
        $url = Admin::app()->getRoute('ajax.getMerchantInfo');
        Admin::script(
            <<<JS
        $(document).off('change', '.field_mid').on('change', '.field_mid', function () {
             $.ajax({
                type: 'GET',
                data:{q:$(this).val()},
                url:"{$url}",
                success:function(res){
                    if(res.code == 200){
                        if(res.data.currency_id == 2){
                            $(".field_bank_name").parent().parent().parent().removeClass("hidden");
                            $(".field_card_no").parent().parent().parent().addClass("hidden");
                            $(".field_card_name").parent().parent().parent().addClass("hidden");
                            $(".field_uid").parent().parent().parent().addClass("hidden");
                        }else if(res.data.currency_id == 6 || res.data.currency_id == 17){
                            $(".field_bank_name").parent().parent().parent().removeClass("hidden");
                            $(".field_card_no").parent().parent().parent().removeClass("hidden");
                            $(".field_card_name").parent().parent().parent().removeClass("hidden");
                            $(".field_card_no").parent().parent().parent().find(".control-label span").text("汇款银行卡号");
                            $(".field_card_name").parent().parent().parent().find(".control-label span").text("汇款人实名");
                            $(".field_uid").parent().parent().parent().addClass("hidden");
                        }else if(res.data.currency_id == 16){
                            $(".field_card_no").parent().parent().parent().removeClass("hidden");
                            $(".field_card_name").parent().parent().parent().removeClass("hidden");
                            $(".field_card_no").parent().parent().parent().find(".control-label span").text("客户Fonepay账号");
                            $(".field_card_name").parent().parent().parent().find(".control-label span").text("客户Fonepay账户名称");
                            $(".field_uid").parent().parent().parent().addClass("hidden");
                        }else if(res.data.currency_id == 9){
                            $(".field_bank_name").parent().parent().parent().addClass("hidden");
                            $(".field_card_no").parent().parent().parent().removeClass("hidden");
                            $(".field_card_name").parent().parent().parent().addClass("hidden");
                            $(".field_card_no").parent().parent().parent().find(".control-label span").text("手机号码");
                            $(".field_uid").parent().parent().parent().addClass("hidden");
                        }else if(res.data.currency_id == 15){
                            $(".field_bank_name").parent().parent().parent().removeClass("hidden");
                            $(".field_card_no").parent().parent().parent().removeClass("hidden");
                            $(".field_card_name").parent().parent().parent().removeClass("hidden");
                            $(".field_uid").parent().parent().parent().removeClass("hidden");
                        }else if(res.data.currency_id == 7){
                            $(".field_bank_name").parent().parent().parent().removeClass("hidden");
                            $(".field_card_no").parent().parent().parent().addClass("hidden");
                            $(".field_card_name").parent().parent().parent().addClass("hidden");
                            $(".field_uid").parent().parent().parent().addClass("hidden");
                        }else{
                            $(".field_bank_name").parent().parent().parent().addClass("hidden");
                            $(".field_card_no").parent().parent().parent().addClass("hidden");
                            $(".field_card_name").parent().parent().parent().addClass("hidden");
                            $(".field_uid").parent().parent().parent().addClass("hidden");
                        }
                    }
                }
            });
        });
JS
        );

        $this->select('mid', '选择商户')->options($this->merchantOptions())->disableClearButton()->required();
        $this->decimal('amount', '代收金额')->rules(['numeric', 'min:1'])->required();
        $this->select('payment_id', '通道编码')->options(collect(config('payment'))->transform(function ($item) {
            $item['bname'] = $item['name'] . '【' . $item['code'] . '】';
            return $item;
        })->pluck('bname', 'id'))->disableClearButton()->required();
        $this->text('bank_name', '汇款银行')->setFormGroupClass('hidden');
        $this->text('card_no', '汇款银行卡号')->setFormGroupClass('hidden');
        $this->text('card_name', '汇款人实名')->setFormGroupClass('hidden');
        $this->text('name', '付款人姓名');
        $this->text('uid', '会员ID')->setFormGroupClass('hidden');
        $this->text('callback_url', '回调地址')->help('不是必须填写');
        $this->text('ip', '下单IP');
        $this->radio('json', '返回方式')->options([1 => '跳转收银台', 2 => '返回json'])->default(1)->when(2, function () {
            $this->textarea('json_content', '返回内容')->placeholder('显示返回内容');
        });
    }

    public function default()
    {
        return [
            'mid' => 0,
            'payment_id' => 1,
            'amount' => 100,
            'json' => 1,
        ];
    }

    private function getMerchant(int $mid): MerchantInfo
    {
        if ($mid <= 0) {
            throw new RuntimeException('请选择商户');
        }

        $merchant = MerchantInfo::query()->whereKey($mid)->first(['merchant_user_id', 'name', 'appkey', 'appsecret']);
        if (!$merchant) {
            throw new RuntimeException('商户不存在');
        }

        if (empty($merchant->appkey) || empty($merchant->appsecret)) {
            throw new RuntimeException('商户 API 密钥未配置');
        }

        return $merchant;
    }

    private function buildPayload(array $input, MerchantInfo $merchant, int $paymentId, string $ip): array
    {
        $data = $this->filterPayload([
            'mid' => $merchant->merchant_user_id,
            'amount' => $input['amount'] ?? '',
            'order_no' => bob_ordernumber('test'),
            'ip' => $ip,
            'notify_url' => trim((string) ($input['callback_url'] ?? '')) ?: route('test'),
            'name' => trim((string) ($input['name'] ?? '')),
            'bank_name' => trim((string) ($input['bank_name'] ?? '')),
            'card_no' => trim((string) ($input['card_no'] ?? '')),
            'card_name' => trim((string) ($input['card_name'] ?? '')),
            'uid' => trim((string) ($input['uid'] ?? '')),
            'gateway' => bob_get_value_by_id_array(['id' => $paymentId], 'code', config('payment')),
        ]);

        if (empty($data['gateway'])) {
            throw new RuntimeException('请选择通道编码');
        }

        $data['sign'] = bob_sign($data, $merchant->appsecret);

        return $data;
    }

    private function sendRequest(MerchantInfo $merchant, array $data)
    {
        return $this->postData($this->apiUrl(), $data, [
            'mode' => 'json',
            'header' => [
                'debug' => 1,
                'Authorization' => $merchant->appkey,
            ],
        ]);
    }

    private function apiUrl(): string
    {
        $domain = trim((string) config('default.api_domain', '')) ?: request()->getHost();

        return request()->getScheme() . '://' . $domain . route('api.v3.deposits', [], false);
    }

    private function filterPayload(array $data): array
    {
        return array_filter($data, fn($value) => $value !== null && $value !== '');
    }

    private function merchantOptions()
    {
        return MerchantInfo::query()->orderByDesc('merchant_user_id')->get(['merchant_user_id', 'currency_id', 'name'])->pluck('bname', 'merchant_user_id')->prepend('请选择商户', 0);
    }

    private function writeSystemLog(MerchantInfo $merchant, int $paymentId, array $data, string $ip, int $json, $admin): void
    {
        app(SystemLogService::class)->logAction(
            actionKey: 'admin.home.test.store',
            text: '新增 测试代收订单',
            subject: $merchant,
            properties: [
                'merchant_user_id' => $merchant->merchant_user_id,
                'payment_id' => $paymentId,
                'amount' => $data['amount'] ?? '',
                'ip' => $ip,
                'order_no' => $data['order_no'] ?? '',
                'json_mode' => $json,
            ],
            remark: '代收测试下单',
            logType: 'operation',
            actionMethod: 'POST',
            appType: 'admin',
            user: $admin
        );
    }
}
