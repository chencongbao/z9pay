<?php

namespace App\Admin\Actions\Grid\Channel;

use Throwable;
use App\Models\Channel;
use Dcat\Admin\Grid\RowAction;
use App\Services\Channel\QueryChannelBalanceService;

class QueryBalance extends RowAction
{
    protected $title = '<i class="feather icon-search"></i> 查询余额';

    public function handle()
    {
        try {
            $channel = Channel::query()->find($this->getKey(), ['id', 'name', 'classname']);
            if (!$channel) {
                throw new \Exception('渠道不存在');
            }

            app(QueryChannelBalanceService::class)->execute($channel);
            return $this->response()->success('查询成功')->refresh();
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }
}
