<?php

namespace App\Admin\Controllers\User;

use Dcat\Admin\Grid;
use Dcat\Admin\Layout\Row;
use Dcat\Admin\Widgets\Card;
use Dcat\Admin\Layout\Column;
use App\Models\UserDayBalanceLog;
use App\Admin\Extensions\Layout\LeftSide;
use App\Admin\Controllers\CommonController;
use Illuminate\Contracts\Support\Renderable;
use App\Services\Cache\User\GetUserDetailService;
use App\Services\Cache\User\GetUserListService;

class DayBalanceLogController extends CommonController
{
    public function title()
    {
        return '金主日切';
    }

    public function grid()
    {
        $uid = (int) request('uid', 0);
        $userNames = [];
        $userDetailService = app(GetUserDetailService::class);
        $userList = array_values(array_filter(app(GetUserListService::class)->excute(), function ($item) {
            return (int) ($item['status'] ?? 0) === 1;
        }));
        $query = UserDayBalanceLog::query()
            ->select(['id', 'uid', 'date_add', 'balance_amount', 'deposit_balance_amount', 'transfer_balance_amount', 'commission_balance_amount', 'deposit_amount', 'daifukuan_amount', 'zeros_balance', 'created_at'])
            ->orderByDesc('date_add')
            ->orderByDesc('id');

        return Grid::make($query, function (Grid $grid) use ($uid, $userList, $userDetailService, &$userNames) {
            $grid->model()->when($uid > 0, fn ($query) => $query->where('uid', $uid));

            if ($uid > 0) {
                $user = $userDetailService->excute($uid);
                $grid->tools()->prepend('<button class="btn btn-primary"><i class="fa fa-fw fa-users" /> ' . e($user['bname'] ?? '') . '</button>');
            } else {
                $grid->tools()->prepend('<button class="btn btn-primary"><i class="fa fa-fw fa-users" /> 全部金主</button>');
            }

            $grid->column('id', '编号')->sortable();
            $grid->column('uid', '金主ID')->center();
            $grid->column('user_name', '所属金主')->display(function () use ($userDetailService, &$userNames) {
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

            $grid->filter(function (Grid\Filter $filter) {
                $filter->expand(true);
                $filter->panel();
                $filter->equal('id')->width(4);
                $filter->equal('uid', '金主ID')->width(4);
                $filter->between('date_add', '日切日期')->date()->width(4);
            });

            $grid->wrap(function (Renderable $view) use ($uid, $userList) {
                $row = new Row();
                $row->column(2, function (Column $column) use ($uid, $userList) {
                    $left = new LeftSide();
                    $left->title('金主列表')->field('uid')->default($uid)->prependAll('全部金主')->data($userList);
                    $column->row($left);
                });
                $row->column(10, function (Column $column) use ($view) {
                    $card = Card::make($view);
                    $card->padding('15px');
                    $column->row($card);
                });

                return $row->render();
            });
        });
    }
}
