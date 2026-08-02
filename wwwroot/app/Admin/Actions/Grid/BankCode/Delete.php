<?php

namespace App\Admin\Actions\Grid\BankCode;

use Dcat\Admin\Admin;
use App\Models\BankCode;
use Dcat\Admin\Grid\RowAction;
use App\Models\ChannelBankCode;
use Illuminate\Support\Facades\DB;

class Delete extends RowAction
{
    protected $title = '删除银行代码';

    public function handle()
    {
        try {
            $deleteCount = DB::transaction(function () {
                $model = BankCode::query()->find($this->getKey(), ['id', 'code', 'name']);
                if (!$model) {
                    throw new \Exception('数据不存在');
                }

                $deleteCount = ChannelBankCode::query()->where('bank_code_id', $model->id)->delete();
                $model->delete();

                return $deleteCount;
            });

            return $this->response()->success('删除成功，同时删除渠道编码' . $deleteCount . '条')->refresh();
        } catch (\Exception $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    public function confirm()
    {
        return ['确认删除?'];
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('bank-code-delete');
    }
}
