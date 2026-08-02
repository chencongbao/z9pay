<?php

namespace App\Admin\Controllers;

use Dcat\Admin\Grid;
use App\Models\ReportUserBank;
use Illuminate\Support\Facades\App;
use App\Services\Cache\UserBank\GetUserBankListService;
use App\Services\Cache\UserBank\GetUserBankDetailService;

class ReportUserBankController extends CommonController
{
    public $title = '上号报表';

    protected $disableCreate = true;

    protected $disableEdit = true;

    protected function grid(): Grid
    {
        $requestUserBankId = (int) request('ubid', 0);
        $userBankList = App::make(GetUserBankListService::class)->excute(false);
        $userBankId = $requestUserBankId > 0 ? $requestUserBankId : (int) optional(collect($userBankList)->first())->offsetGet('id');

        $model = ReportUserBank::query()->select([
            'id', 'ubid', 'date_add', 'deposit_order_number_total', 'deposit_order_number_success', 'deposit_order_number_fail',
            'deposit_order_number_overtime', 'deposit_order_total_amount', 'deposit_order_total_fee',
        ])->orderByDesc('date_add')->orderByDesc('id');
        if ($requestUserBankId <= 0 && $userBankId > 0) {
            $model->where('ubid', $userBankId);
        }

        return Grid::make($model, function (Grid $grid) use ($userBankId) {
            $grid->column('id')->sortable();
            $grid->column('date_add', '日期');
            $grid->column('deposit_order_number_total', '代收单数');
            $grid->column('deposit_order_number_success', '代收成功单数');
            $grid->column('deposit_order_number_fail', '代收失败单数');
            $grid->column('deposit_order_number_overtime', '代收超时单数');
            $grid->column('deposit_order_success_rate', '代收成功率')->display(function () {
                return bob_percent($this->deposit_order_number_success, $this->deposit_order_number_total);
            });
            $grid->column('deposit_order_total_amount', '代收跑量')->amount();
            $grid->column('deposit_order_total_fee', '代收商户手续费')->amount();
            $grid->disableActions();
            $grid->disableCreateButton();

            $grid->filter(function (Grid\Filter $filter) use ($userBankId) {
                $filter->expand(true);
                $filter->panel();
                $filter->between('date_add', '查询日期')->date()->width(5);
                $filter->equal('ubid', '收款卡')->select(function ($id) use ($userBankId) {
                    $result = App::make(GetUserBankDetailService::class)->excute($id ?: $userBankId);

                    return empty($result) ? [] : [$result['id'] => $result['bname']];
                })->ajax('/ajax/getUserBankList', 'id', 'bname')->addDefaultConfig(['allowClear' => false])->width(5)->default($userBankId);
            });
        });
    }
}
