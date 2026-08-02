<?php

namespace App\Admin\Forms\BankCode;

use Throwable;
use Dcat\Admin\Admin;
use App\Models\Channel;
use App\Models\BankCode;
use Dcat\Admin\Widgets\Form;
use App\Models\ChannelBankCode;
use Dcat\Admin\Traits\LazyWidget;
use Illuminate\Support\Facades\DB;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Services\Cache\Channel\GetChannelListService;

class AddChannelBankCode extends Form implements LazyRenderable
{
    use LazyWidget;

    public function handle(array $input)
    {
        try {
            $bankCodeId = intval($this->payload['id'] ?? 0);
            $channelId = intval($input['channel_id'] ?? 0);
            $code = trim((string)($input['code'] ?? ''));

            if ($bankCodeId <= 0) throw new \Exception('银行参数错误');
            if ($channelId <= 0) throw new \Exception('请选择渠道');
            if ($code === '') throw new \Exception('银行编码不能为空');
            if (!BankCode::query()->whereKey($bankCodeId)->exists()) throw new \Exception('银行不存在');
            if (!Channel::query()->whereKey($channelId)->exists()) throw new \Exception('渠道不存在');

            DB::transaction(function () use ($bankCodeId, $channelId, $code) {
                if (ChannelBankCode::query()->where('channel_id', $channelId)->where('code', $code)->exists()) {
                    throw new \Exception('渠道代码已经存在');
                }

                ChannelBankCode::create([
                    'channel_id' => $channelId,
                    'code' => $code,
                    'bank_code_id' => $bankCodeId,
                ]);
            });

            return $this->response()->success('渠道代码新增成功.')->refresh();
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    public function form()
    {
        $this->display('currency', '所属国家');
        $this->display('name', '银行名称');
        $this->select('channel_id', '所属渠道')->options(collect(app(GetChannelListService::class)->excute())->pluck('bname', 'id'))->required();
        $this->text('code', '银行编码')->required();
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('bank-code-channel-create');
    }

    public function default()
    {
        $id = intval($this->payload['id'] ?? 0);
        $row = BankCode::query()->find($id, ['id', 'currency_id', 'name', 'code']);
        if (!$row) {
            return [
                'currency' => '',
                'name' => '',
                'code' => '',
            ];
        }

        return [
            'currency' => optional(collect(config('default.currency'))->firstWhere('id', $row->currency_id))->offsetGet('country'),
            'name' => optional($row)->name,
            'code' => optional($row)->code,
        ];
    }
}
