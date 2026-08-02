<?php

namespace App\Admin\Controllers;

use Dcat\Admin\Grid;
use App\Models\UserDayBalanceLog;
use Dcat\Admin\Grid\LazyRenderable;
use App\Services\Cache\User\GetUserDetailService;

class UserDayBalanceLogController extends LazyRenderable
{
    public function grid(): Grid
    {
        $uid = (int) request('uid', 0);
        $userDetailService = app(GetUserDetailService::class);
        $query = UserDayBalanceLog::query()
            ->select(['id', 'uid', 'date_add', 'balance_amount', 'deposit_balance_amount', 'transfer_balance_amount', 'commission_balance_amount', 'deposit_amount', 'daifukuan_amount', 'zeros_balance', 'created_at'])
            ->orderByDesc('date_add')
            ->orderByDesc('id');

        return Grid::make($query, function (Grid $grid) use ($uid, $userDetailService) {
            if ($uid > 0) {
                $grid->model()->where('uid', $uid);
            }

            $grid->column('id', '编号')->sortable();
            $grid->column('uid', '金主ID')->center();
            $grid->column('user_name', '所属金主')->display(function () use ($userDetailService) {
                static $userNames = [];
                $uid = (int) $this->uid;
                if ($uid <= 0) {
                    return '';
                }

                if (!array_key_exists($uid, $userNames)) {
                    $user = $userDetailService->excute($uid);
                    $userNames[$uid] = $user['bname'] ?? '';
                }

                return $userNames[$uid];
            });
            $grid->column('date_add', '日切日期')->center();
            $grid->column('balance_amount', '金主余额')->amount()->center();
            $grid->column('deposit_balance_amount', '代收账户')->amount()->center();
            $grid->column('transfer_balance_amount', '代付账户')->amount()->center();
            $grid->column('commission_balance_amount', '佣金账户')->amount()->center();
            $grid->column('deposit_amount', '保证金总额')->amount()->center();
            $grid->column('daifukuan_amount', '代收待付款')->amount()->center();
            $grid->column('zeros_balance', '0点剩余押金')->amount()->center();
            $grid->column('created_at', '创建时间')->center();
            $grid->disableActions();
            $grid->disableCreateButton();
            $grid->disableRowSelector();
            $grid->disableFilterButton();
        });
    }
}
