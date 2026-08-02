<?php

namespace App\Admin\Actions\Grid\IpBlacklist;

use Dcat\Admin\Admin;
use App\Models\IpBlacklist;
use Dcat\Admin\Grid\RowAction;
use App\Services\Common\SystemLogService;

class Unban extends RowAction
{
    protected $title = '<i class="feather icon-unlock"></i> 解封';

    public function handle()
    {
        $record = IpBlacklist::query()->find($this->getKey(), ['id', 'ip', 'type', 'status', 'remark']);
        if (!$record) {
            return $this->response()->error('记录不存在');
        }

        if ((int) $record->status === 0) {
            return $this->response()->info('该记录已是解封状态');
        }

        $oldStatus = (int) $record->status;
        $updated = $record->update([
            'status' => 0,
            'remark' => '后台手动解封',
        ]);

        if (!$updated) {
            return $this->response()->error('解封失败');
        }

        app(SystemLogService::class)->logAction(
            actionKey: 'ip.blacklist.unban',
            text: 'IP黑名单解封',
            subject: $record,
            properties: [
                'ip' => $record->ip,
                'type' => $record->type,
                'old_status' => $oldStatus,
                'new_status' => 0,
            ],
            remark: '后台手动解封 IP：' . $record->ip . ' 类型：' . $record->type,
            appType: 'admin',
            user: Admin::user()
        );

        return $this->response()->success('解封成功')->refresh();
    }

    public function confirm()
    {
        return ['确认操作?', '确定要解封该 IP 黑名单记录吗'];
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('ip-blacklist-unban');
    }
}
