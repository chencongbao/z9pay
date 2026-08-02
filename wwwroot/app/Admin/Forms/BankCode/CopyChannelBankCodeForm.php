<?php

namespace App\Admin\Forms\BankCode;

use Throwable;
use Dcat\Admin\Admin;
use App\Models\Channel;
use Dcat\Admin\Widgets\Form;
use App\Models\ChannelBankCode;
use Dcat\Admin\Traits\LazyWidget;
use Illuminate\Support\Facades\DB;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Services\Cache\Channel\GetChannelListService;

class CopyChannelBankCodeForm extends Form implements LazyRenderable
{
    use LazyWidget;

    public function handle(array $input)
    {
        try {
            $copyChannelId = (int)($input['copy_channel_id'] ?? 0);
            $toChannelId = (int)($input['to_channel_id'] ?? 0);
            if ($copyChannelId <= 0 || $toChannelId <= 0) throw new \Exception('请选择渠道');
            if ($copyChannelId === $toChannelId) throw new \Exception('原渠道与新渠道不能一样');

            $count = DB::transaction(function () use ($copyChannelId, $toChannelId) {
                $channelCount = Channel::query()->whereIn('id', [$copyChannelId, $toChannelId])->count();
                if ($channelCount !== 2) {
                    throw new \Exception('渠道不存在');
                }

                $models = ChannelBankCode::query()->where('channel_id', $copyChannelId)->get(['bank_code_id', 'code']);
                if ($models->isEmpty()) {
                    throw new \Exception('原渠道不存在渠道编码');
                }

                $existsCodes = ChannelBankCode::query()->where('channel_id', $toChannelId)->pluck('code')->all();
                $existsMap = array_flip($existsCodes);
                $now = now();
                $insertData = [];

                foreach ($models as $model) {
                    if (isset($existsMap[$model->code])) {
                        continue;
                    }

                    $insertData[] = [
                        'bank_code_id' => $model->bank_code_id,
                        'code' => $model->code,
                        'channel_id' => $toChannelId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if (empty($insertData)) {
                    throw new \Exception('目标渠道已存在全部编码，无需复制');
                }

                ChannelBankCode::query()->insert($insertData);
                return count($insertData);
            });

            return $this->response()->success('复制成功，共复制' . $count . '条')->refresh();
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('bank-code-channel-copy');
    }

    public function form()
    {
        $channels = collect(app(GetChannelListService::class)->excute())->pluck('bname', 'id');
        $this->select('copy_channel_id', '原渠道')->options($channels)->disableClearButton()->required();
        $this->select('to_channel_id', '新渠道')->options($channels)->disableClearButton()->required();
    }

    public function default()
    {
        return [
            'currency_id' => 0,
            'copy_channel_id' => 0,
            'to_channel_id' => 0,
        ];
    }
}
