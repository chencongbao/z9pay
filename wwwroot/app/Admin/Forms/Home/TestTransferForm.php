<?php

namespace App\Admin\Forms\Home;

use Throwable;
use Dcat\Admin\Admin;
use RuntimeException;
use Illuminate\Support\Str;
use App\Models\MerchantInfo;
use Dcat\Admin\Widgets\Form;
use Illuminate\Support\Facades\Cache;
use App\Services\Common\SystemLogService;
use App\Services\Telegram\TelegramInstanceService;
use App\Services\Telegram\TelegramManagerService;
use App\Services\TransferOrder\AdminTestTransferService;

class TestTransferForm extends Form
{
    protected function savedScript()
    {
        return <<<JS
        if (!data.status) {
            Dcat.error(data.data.message);
            return false;
        }
        if(data.status && data.data && data.data.data &&  data.data.data.action == 'show'){
            $(".field_json_content").val(JSON.stringify(data.data.data));
            return;
        }
        if(data.data.then.action == 'refresh'){
             Dcat.success("代付订单生成成功！");
            return false;
        }
        window.open(data.data.then.value);
        return false;
JS;
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('admin_operation_manager_api_transfer_test');
    }

    public function handle(array $input)
    {
        try {
            $admin = Admin::user();
            $payload = $this->buildPayload($input);
            $merchant = $this->getMerchant(intval($payload['transfer_mid'] ?? 0));
            $this->writeSubmitLog($payload, $merchant, $admin);

            if (!$merchant) {
                return $this->response()->error('商户不存在');
            }

            if ($this->shouldSendConfirm(floatval($payload['amount'] ?? 0))) {
                $expireMinutes = $this->sendTelegramConfirm($payload, $merchant, $admin);
                return $this->response()->success("已发送到确认群，等待超级管理员在{$expireMinutes}分钟内确认后执行代付测试")->refresh();
            }

            app(AdminTestTransferService::class)->execute($payload, $admin);

            return $this->response()->success('下单成功')->refresh();
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    protected function buildPayload(array $input): array
    {
        return [
            'transfer_mid' => intval($input['transfer_mid'] ?? 0),
            'currency_id' => intval($input['currency_id'] ?? 0),
            'amount' => $input['amount'] ?? 0,
            'ip' => trim((string) ($input['ip'] ?? '')) ?: request()->ip(),
            'callback_url' => trim((string) ($input['callback_url'] ?? '')) ?: route('test'),
            'bank_code' => trim((string) ($input['bank_code'] ?? '')),
            'transfer_card_no' => trim((string) ($input['transfer_card_no'] ?? '')),
            'holder_name' => trim((string) ($input['holder_name'] ?? '')),
            'bank_branch' => trim((string) ($input['bank_branch'] ?? '')),
            'transfer_bank_name' => trim((string) ($input['transfer_bank_name'] ?? '')),
            'identity_no' => trim((string) ($input['identity_no'] ?? '')),
        ];
    }

    protected function writeSubmitLog(array $payload, ?MerchantInfo $merchant = null, $admin = null): void
    {
        app(SystemLogService::class)->logAction(
            actionKey: 'admin.home.test.transfer.submit',
            text: '提交 测试代付订单',
            subject: $merchant,
            properties: [
                'merchant_user_id' => intval($payload['transfer_mid'] ?? 0),
                'merchant_exists' => $merchant ? 1 : 0,
                'amount' => $payload['amount'] ?? 0,
                'bank_code' => $payload['bank_code'] ?? '',
                'ip' => $payload['ip'] ?? '',
                'confirm_on' => intval(bob_admin_setting('transfer_test_confirm_on')) === 1 ? 1 : 0,
            ],
            remark: '测试代付提交',
            logType: 'operation',
            actionMethod: 'POST',
            appType: 'admin',
            user: $admin
        );
    }

    protected function getConfirmCacheKey(string $token): string
    {
        return 'admin:test_transfer:confirm:' . $token;
    }

    protected function getCurrencyName(int $currencyId): string
    {
        $currency = collect(config('default.currency'))->firstWhere('id', $currencyId);

        return (string) ($currency['name'] ?? '');
    }

    protected function getBankDisplayName(array $payload = []): string
    {
        if (!empty($payload['transfer_bank_name'])) {
            return (string) $payload['transfer_bank_name'];
        }

        return (string) ($payload['bank_code'] ?? '-');
    }

    protected function buildConfirmMessage(array $data = []): string
    {
        $expireMinutes = intval($data['expire_minutes'] ?? $this->getConfirmExpireMinutes());

        return implode("\n", [
            '代付测试商户：' . ($data['merchant_name'] ?? '-'),
            '代付测试币种：' . ($data['currency_name'] ?? '-'),
            '代付测试银行：' . ($data['bank_name'] ?? '-'),
            '代付测试金额：' . ($data['amount'] ?? '-'),
            '代付测试账号：' . ($data['account_no'] ?? '-'),
            '代付账号名称：' . ($data['holder_name'] ?? '-'),
            '发起人：' . ($data['admin_name'] ?? '-'),
            '发起时间：' . ($data['created_at'] ?? '-'),
            '确认时限：' . $expireMinutes . '分钟内有效',
            '过期时间：' . ($data['expires_at'] ?? '-'),
        ]);
    }

    public function form()
    {
        $confirmOn = (int) bob_admin_setting('transfer_test_confirm_on') === 1;
        $expireMinutes = $this->getConfirmExpireMinutes();
        $minAmount = $this->getConfirmMinAmount();

        $this->confirm(
            '提示',
            $confirmOn
                ? "当前已开启代付测试确认群，测试金额达到{$minAmount}时会先发送到确认群等待飞机超级管理员确认，确认有效期{$expireMinutes}分钟，请先通知值班超级管理员关注确认消息，再提交测试。确定继续？"
                : '确定提交？'
        );

        if ($confirmOn) {
            $groupId = trim((string) bob_admin_setting('transfer_test_confirm_telegram_group_id'));

            $this->html(
                <<<HTML
<div class="alert alert-warning mb-3" style="margin-bottom:15px;">
    <i class="feather icon-alert-triangle"></i>
    当前已开启<strong>代付测试确认群</strong>，提交后不会立即执行代付测试，而是先发送到确认群等待<strong>飞机超级管理员</strong>确认。
    <br>
    审核金额：代付测试金额<strong>大于等于{$minAmount}</strong>时需要审核；设置为<strong>0</strong>表示所有代付测试都需要审核。
    <br>
    请先通知值班超级管理员及时关注确认消息后，再进行测试。确认有效期为<strong>{$expireMinutes}分钟</strong>。
    {$this->buildGroupNoticeHtml($groupId)}
</div>
HTML
            )->width(12, 0);
        }

        $url = Admin::app()->getRoute('ajax.getMerchantInfo');
        Admin::script(
            <<<JS
        $(document).off('change', '.field_bank_code').on('change', '.field_bank_code', function () {
            if($(this).val() == "OB"){
                $('.field_transfer_bank_name').parent().parent().parent().removeClass("hidden");
            }else{
                $('.field_transfer_bank_name').parent().parent().parent().addClass("hidden");
            }
        });
        $(document).off('change', '.field_transfer_mid').on('change', '.field_transfer_mid', function () {
             $.ajax({
                type: 'GET',
                data:{q:$(this).val()},
                url:"{$url}",
                success:function(res){
                    if(res.code == 200){
                        $('.field_currency_id').val(res.data.currency_id); // 选择值为 '1' 的选项
                        $('.field_currency_id').trigger('change'); // 通知 Select2 值已更改
                        if(res.data.currency_id == 9){
                            $(".field_identity_no").parent().parent().parent().removeClass("hidden");
                            $(".field_identity_no").parent().parent().parent().find(".control-label span").text("身份证号码");
                             $(".field_identity_no").attr("placeholder", "请输入身份证号码");
                        }else if(res.data.currency_id == 11){
                            $(".field_identity_no").parent().parent().parent().removeClass("hidden");
                            $(".field_identity_no").parent().parent().parent().find(".control-label span").text("出款税号");
                             $(".field_identity_no").attr("placeholder", "请输入出款税号");
                        }else{
                            $(".field_identity_no").parent().parent().parent().addClass("hidden");
                        }
                        if(res.data.currency_id == 3){
                            $(".field_bank_branch").parent().parent().parent().find(".control-label span").text("IFSC");
                            $(".field_bank_branch").attr("placeholder", "请输入IFSC");
                        }else{
                             $(".field_bank_branch").parent().parent().parent().find(".control-label span").text("银行支行");
                            $(".field_bank_branch").attr("placeholder", "请输入银行支行");
                        }
                    }
                }
            });
        });
JS
        );

        $this->select('transfer_mid', '选择商户')->options($this->merchantOptions())->disableClearButton()->required();
        $this->select('currency_id', '选择币种')->options(collect(config('default.currency'))->pluck('name', 'id')->prepend('请选择币种', 0))->default(1)->disableClearButton()->required()->load('bank_code', '/ajax/getBankCode');
        $this->select('bank_code', '银行代码')->required();
        $this->decimal('amount', '代付金额')->rules(['numeric', 'min:1'])->required();
        $this->text('transfer_card_no', '银行卡卡号，或者 支付宝/微信 账号');
        $this->text('holder_name', '银行卡户名，或者 支付宝/微信 真实姓名');
        $this->text('identity_no', '身份证号码')->setFormGroupClass('hidden')->help('巴基斯担，巴西代付必填');
        $this->text('transfer_bank_name', '银行名称')->setFormGroupClass('hidden')->help('银行代码=OB,此值必填');
        $this->text('bank_branch', '银行支行');
        $this->text('ip', '下单IP');
        $this->text('callback_url', '回调地址')->help('不是必须填写');
    }

    public function default()
    {
        return [
            'transfer_mid' => 0,
            'amount' => 100,
            'currency_id' => 0,
            'bank_code' => 0,
        ];
    }

    protected function getConfirmExpireMinutes(): int
    {
        return max(1, intval(bob_admin_setting('transfer_test_confirm_expire_minutes')) ?: 30);
    }

    protected function getConfirmMinAmount(): float
    {
        return max(0, floatval(bob_admin_setting('transfer_test_confirm_min_amount')));
    }

    protected function shouldSendConfirm(float $amount): bool
    {
        if (intval(bob_admin_setting('transfer_test_confirm_on')) !== 1) {
            return false;
        }

        $minAmount = $this->getConfirmMinAmount();
        return $minAmount <= 0 || $amount >= $minAmount;
    }

    protected function buildGroupNoticeHtml(string $groupId = ''): string
    {
        if ($groupId === '') {
            return '<br><span class="text-danger">当前确认群ID未配置，请先到安全配置中完成配置。</span>';
        }

        return '<br><span class="text-muted">确认群ID：' . e($groupId) . '</span>';
    }

    private function getMerchant(int $mid): ?MerchantInfo
    {
        if ($mid <= 0) {
            return null;
        }

        return MerchantInfo::query()->whereKey($mid)->first();
    }

    private function sendTelegramConfirm(array $payload, MerchantInfo $merchant, $admin): int
    {
        $groupId = trim((string) bob_admin_setting('transfer_test_confirm_telegram_group_id'));
        $superManagerIds = app(TelegramManagerService::class)->superManagerIds();
        $expireMinutes = $this->getConfirmExpireMinutes();

        if ($groupId === '') {
            throw new RuntimeException('代付测试确认通知群ID未配置');
        }

        if (empty($superManagerIds)) {
            throw new RuntimeException('飞机超级管理员未配置');
        }

        $token = Str::random(16);
        $cacheData = $this->buildConfirmCacheData($token, $payload, $merchant, $admin, $expireMinutes);
        Cache::put($this->getConfirmCacheKey($token), $cacheData, now()->addMinutes($expireMinutes + 30));

        app(TelegramInstanceService::class)->excute()->sendMessage([
            'chat_id' => $groupId,
            'text' => $this->buildConfirmMessage($cacheData),
            'reply_markup' => json_encode($this->confirmKeyboard($token)),
        ]);

        return $expireMinutes;
    }

    private function buildConfirmCacheData(string $token, array $payload, MerchantInfo $merchant, $admin, int $expireMinutes): array
    {
        return [
            'status' => 'pending',
            'token' => $token,
            'input' => $payload,
            'merchant_name' => $merchant->bname,
            'currency_name' => $this->getCurrencyName(intval($payload['currency_id'] ?? 0)),
            'bank_name' => $this->getBankDisplayName($payload),
            'amount' => $payload['amount'] ?? '',
            'account_no' => $payload['transfer_card_no'] ?? '',
            'holder_name' => $payload['holder_name'] ?? '',
            'admin_id' => $admin->id,
            'admin_name' => $this->adminName($admin),
            'created_at' => now()->toDateTimeString(),
            'expires_at' => now()->addMinutes($expireMinutes)->toDateTimeString(),
            'expire_minutes' => $expireMinutes,
        ];
    }

    private function confirmKeyboard(string $token): array
    {
        return [
            'inline_keyboard' => [[
                ['text' => '确认测试', 'callback_data' => json_encode(['t' => 22, 'a' => 'c', 'k' => $token])],
                ['text' => '拒绝测试', 'callback_data' => json_encode(['t' => 22, 'a' => 'x', 'k' => $token])],
            ]],
        ];
    }

    private function merchantOptions()
    {
        return MerchantInfo::query()->orderByDesc('merchant_user_id')->get(['merchant_user_id', 'currency_id', 'name'])->pluck('bname', 'merchant_user_id')->prepend('请选择商户', 0);
    }

    private function adminName($admin): string
    {
        return (string) ($admin->username ?: $admin->name);
    }
}
