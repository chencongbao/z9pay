<?php

namespace App\Admin\Controllers;

use App\Models\User;
use App\Models\Channel;
use App\Models\BankCode;
use App\Models\UserBank;
use App\Models\MerchantInfo;
use Illuminate\Http\Request;
use App\Traits\ResponseTraits;
use App\Models\ChannelBankCode;
use App\Models\MerchantChannel;
use Dcat\Admin\Http\Auth\Permission;
use Dcat\Admin\Http\Controllers\AdminController;

class AjaxController extends AdminController
{
    use ResponseTraits;

    public function getMerchantTransferChannel(Request $request)
    {
        $merchantUserId = (int) $request->input('q', 0);
        $data[] = [
            'id' => 0,
            'text' => '请选择渠道',
        ];

        if ($merchantUserId <= 0) {
            return $data;
        }

        $channelIds = MerchantChannel::query()
            ->where('merchant_user_id', $merchantUserId)
            ->where('status', 1)
            ->where('payment_id', 7)
            ->pluck('channel_id')
            ->all();

        if (empty($channelIds)) {
            return $data;
        }

        $channels = Channel::query()
            ->whereRaw('FIND_IN_SET(?, payment_ids)', [7])
            ->whereIn('id', $channelIds)
            ->where('status', 1)
            ->get(['id', 'name']);

        foreach ($channels as $item) {
            $data[] = [
                'id' => $item->id,
                'text' => $item->name,
            ];
        }

        return $data;
    }

    public function getMerchantInfo(Request $request)
    {
        $result = MerchantInfo::query()->where('merchant_user_id', $request->input('q', 0))->first();
        if ($result) {
            return $this->success('success', $result->toArray());
        }

        return $this->error();
    }

    public function merchantChannelPaymentField(Request $request)
    {
        $channel = Channel::query()->find($request->input('q', 0), ['id', 'payment_ids']);
        if (!$channel) {
            return [];
        }

        $paymentIds = array_filter(explode(',', (string) $channel->payment_ids));
        return collect(config('payment'))
            ->whereIn('id', $paymentIds)
            ->map(function ($value) {
                $name = trim((string) ($value['name'] ?? ''));
                $code = trim((string) ($value['code'] ?? ''));
                $text = $code !== '' ? $name . '【' . $code . '】' : $name;

                return ['id' => $value['id'], 'text' => $text];
            })
            ->values()
            ->all();
    }

    public function getBankCode(Request $request)
    {
        $currencyId = (int) $request->input('q', 0);
        if ($currencyId === 0) {
            return [
                [
                    'id' => '0',
                    'text' => '请选择银行代码',
                ],
            ];
        }

        $data[] = [
            'id' => 'OB',
            'text' => '请填写银行名称【OB】',
        ];
        $data[] = [
            'id' => 'test',
            'text' => '用于系统测试',
        ];

        $bankCodes = BankCode::query()->where('currency_id', $currencyId)->get(['code', 'name']);
        foreach ($bankCodes as $item) {
            $data[] = [
                'id' => $item->code,
                'text' => $item->bname,
            ];
        }

        return $data;
    }

    public function deleteChannelBankCode(Request $request)
    {
        Permission::check('bank-code-channel-delete');

        $model = ChannelBankCode::query()->whereKey($request->input('id'))->first();
        if ($model && $model->delete()) {
            return $this->success();
        }

        return $this->error('数据不存在');
    }

    public function getUserBankList(Request $request)
    {
        $keyword = $request->input('q');
        $model = UserBank::query()->withTrashed();
        if ($keyword) {
            $model->where(function ($query) use ($keyword) {
                $query->where('id', $keyword)->orWhere('name', 'like', '%' . $keyword . '%')->orWhere('card_no', 'like', '%' . $keyword . '%');
            });
        }

        return $model->select('id', 'name', 'card_no', 'account_type', 'bank_id')->with('bank_code')->paginate();
    }

    public function getMerchantList(Request $request)
    {
        $keyword = $request->input('q');
        $model = MerchantInfo::query()->whereHas('merchant_user');
        if ($keyword) {
            $model->where(function ($query) use ($keyword) {
                $query->where('merchant_user_id', $keyword)->orWhere('name', 'like', '%' . $keyword . '%')->orWhere('coder', 'like', '%' . $keyword . '%');
            });
        }

        return $model->select('merchant_user_id', 'name', 'coder', 'currency_id')->paginate();
    }

    public function getUserList(Request $request)
    {
        $keyword = $request->input('q');
        $model = User::query()->where('is_agent', 0);
        if ($keyword) {
            $model->where(function ($query) use ($keyword) {
                $query->where('id', $keyword)->orWhere('name', 'like', '%' . $keyword . '%')->orWhere('username', 'like', '%' . $keyword . '%');
            });
        }

        return $model->select('id', 'name', 'username')->paginate();
    }
}
