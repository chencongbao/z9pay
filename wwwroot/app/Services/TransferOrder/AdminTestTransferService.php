<?php

namespace App\Services\TransferOrder;

use App\Models\MerchantInfo;
use App\Services\Common\SystemLogService;
use Illuminate\Support\Facades\Http;

class AdminTestTransferService
{
    public function execute(array $input = [], $operator = null): array
    {
        $mid = intval($input['transfer_mid'] ?? $input['mid'] ?? 0);
        $merchant = MerchantInfo::find($mid);
        if (!$merchant) {
            throw new \Exception('商户不存在');
        }

        $ip = $input['ip'] ?? request()->ip();
        $orderNo = $input['order_no'] ?? bob_ordernumber('test');

        $data = [
            'mid' => $mid,
            'amount' => $input['amount'] ?? 0,
            'order_no' => $orderNo,
            'ip' => $ip,
            'notify_url' => $input['callback_url'] ?? route('test'),
            'bank_code' => $input['bank_code'] ?? '',
            'card_no' => $input['transfer_card_no'] ?? $input['card_no'] ?? '',
            'holder_name' => $input['holder_name'] ?? '',
            'bank_branch' => $input['bank_branch'] ?? '',
            'bank_name' => $input['transfer_bank_name'] ?? $input['bank_name'] ?? '',
            'identity_no' => $input['identity_no'] ?? '',
        ];

        $path = route('api.v3.transfers', [], false);
        $url = request()->getScheme() . '://' . config('default.api_domain', '') . $path;
        $data = array_filter($data, function ($value) {
            return !is_null($value) && $value !== '';
        });

        $data['sign'] = bob_sign($data, optional($merchant)->offsetGet('appsecret'));

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'debug' => 1,
                'Authorization' => 'api-key ' . optional($merchant)->offsetGet('appkey'),
            ])->post($url, $data);
        } catch (\Throwable $e) {
            $this->logExecuteFail($merchant, $input, $data, $operator, $e->getMessage());
            throw $e;
        }

        if (!$response->successful()) {
            $this->logExecuteFail($merchant, $input, $data, $operator, $response->reason() ?: '代付测试请求失败');
            throw new \Exception($response->reason() ?: '代付测试请求失败');
        }

        $result = json_decode($response->body(), true);
        if (!isset($result['code']) || intval($result['code']) !== 200) {
            $this->logExecuteFail($merchant, $input, $data, $operator, $result['message'] ?? '代付测试下单失败', $result);
            throw new \Exception($result['message'] ?? '代付测试下单失败');
        }

        app(SystemLogService::class)->logAction(
            actionKey: 'admin.home.test.transfer.store',
            text: '新增 测试代付订单',
            subject: $merchant,
            properties: [
                'merchant_user_id' => $mid,
                'amount' => $input['amount'] ?? 0,
                'bank_code' => $input['bank_code'] ?? '',
                'order_no' => $data['order_no'] ?? '',
                'ip' => $ip,
                'telegram_confirmed_by' => is_array($operator) ? ($operator['id'] ?? 0) : 0,
                'telegram_confirmed_name' => is_array($operator) ? ($operator['name'] ?? '') : '',
            ],
            remark: '测试代付下单',
            logType: 'operation',
            actionMethod: 'POST',
            appType: 'admin',
            user: $operator instanceof \Illuminate\Database\Eloquent\Model ? $operator : null
        );

        return [
            'message' => $result['message'] ?? '下单成功',
            'order_no' => $data['order_no'] ?? '',
            'result' => $result,
            'merchant' => $merchant,
        ];
    }

    protected function logExecuteFail(MerchantInfo $merchant, array $input, array $data, $operator, string $message, array $result = []): void
    {
        app(SystemLogService::class)->logAction(
            actionKey: 'admin.home.test.transfer.store',
            text: '新增 测试代付订单',
            subject: $merchant,
            properties: [
                'merchant_user_id' => intval($data['mid'] ?? 0),
                'amount' => $input['amount'] ?? 0,
                'bank_code' => $input['bank_code'] ?? '',
                'order_no' => $data['order_no'] ?? '',
                'ip' => $data['ip'] ?? '',
                'status' => 'failed',
                'message' => $message,
                'result' => $result,
                'telegram_confirmed_by' => is_array($operator) ? ($operator['id'] ?? 0) : 0,
                'telegram_confirmed_name' => is_array($operator) ? ($operator['name'] ?? '') : '',
            ],
            remark: '测试代付下单失败',
            logType: 'operation',
            actionMethod: 'POST',
            appType: 'admin',
            user: $operator instanceof \Illuminate\Database\Eloquent\Model ? $operator : null
        );
    }
}
