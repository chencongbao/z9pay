<?php

namespace App\Admin\Forms\BankCode;

use Throwable;
use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Form;
use App\Models\ChannelBankCode;
use Dcat\Admin\Traits\LazyWidget;
use Dcat\Admin\Contracts\LazyRenderable;

class EditChannelBankCodeForm extends Form implements LazyRenderable
{
    use LazyWidget;

    public function handle(array $input)
    {
        try {
            $id = intval($this->payload['id'] ?? 0);
            $code = trim((string)($input['code'] ?? ''));
            if ($id <= 0) throw new \Exception('数据参数错误');
            if ($code === '') throw new \Exception('渠道编码不能为空');

            $result = ChannelBankCode::query()->whereKey($id)->first(['id', 'channel_id', 'code']);
            if (!$result) {
                throw new \Exception('数据不存在');
            }

            $exists = ChannelBankCode::query()
                ->where('channel_id', $result->channel_id)
                ->where('code', $code)
                ->where('id', '<>', $result->id)
                ->exists();
            if ($exists) {
                throw new \Exception('渠道代码已经存在');
            }

            if ($result->code !== $code) {
                $result->code = $code;
                $result->save();
            }

            return $this->response()->success('渠道代码更新成功.')->refresh();
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('bank-code-channel-edit');
    }

    public function form()
    {
        $this->text('code', '渠道编码')->required();
    }

    public function default()
    {
        $id = intval($this->payload['id'] ?? 0);
        $row = ChannelBankCode::query()->whereKey($id)->first(['id', 'code']);

        return [
            'code' => optional($row)->code,
        ];
    }
}
