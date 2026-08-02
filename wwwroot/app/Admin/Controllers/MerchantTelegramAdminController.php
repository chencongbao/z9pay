<?php

namespace App\Admin\Controllers;

use Dcat\Admin\Grid;
use Dcat\Admin\Admin;
use App\Models\AdminAdministrator;
use App\Models\MerchantTelegramAdmin;
use App\Admin\Actions\Grid\MerchantUser\DeleteTelegramAdmin;

class MerchantTelegramAdminController extends Grid\LazyRenderable
{
    public function grid(): Grid
    {
        $mid = intval(request('mid', 0));
        $query = MerchantTelegramAdmin::query()
            ->with(['merchant_info' => function ($query) {
                $query->select(['merchant_user_id', 'name', 'coder', 'currency_id']);
            }])
            ->select(['id', 'mid', 'telegram_group_id', 'telegram_user_id', 'telegram_username', 'telegram_name', 'reviewed_by', 'reviewed_telegram_user_id', 'reviewed_telegram_name', 'created_at'])
            ->orderByDesc('id');

        return Grid::make($query, function (Grid $grid) use ($mid) {
            if ($mid > 0) {
                $grid->model()->where('mid', $mid);
            }

            $grid->column('id', '编号')->sortable();
            $grid->column('merchant_info', '所属商户')->display(function () {
                return MerchantTelegramAdminController::merchantTable($this->merchant_info, (int)$this->mid);
            });
            $grid->column('telegram_group_id', '商户群ID');
            $grid->column('telegram_user_id', 'Telegram用户ID');
            $grid->column('telegram_username', 'Telegram用户名')->display(fn($value) => $value ? '@' . ltrim((string)$value, '@') : '-');
            $grid->column('telegram_name', '昵称')->display(fn($value) => $value ?: '-');
            $grid->column('reviewed_by', '确认人')->display(function ($value) {
                return MerchantTelegramAdminController::reviewerTable((int)$value, (int)($this->reviewed_telegram_user_id ?? 0), (string)($this->reviewed_telegram_name ?? ''));
            });
            $grid->column('created_at', '授权时间');

            $grid->actions(function ($actions) {
                $actions->disableView();
                $actions->disableEdit();
                $actions->disableDelete();
                if (Admin::user()->can('merchant-user-delete-telegram-admin')) {
                    $actions->append(new DeleteTelegramAdmin());
                }
            });

            $grid->disableCreateButton();
            $grid->disableRowSelector();
            $grid->paginate(10);
        });
    }

    public static function reviewerTable(int $adminId, int $telegramUserId, string $telegramName): string
    {
        if ($adminId <= 0 && $telegramUserId <= 0 && $telegramName === '') {
            return '-';
        }

        static $adminCache = [];
        $admin = null;
        if ($adminId > 0) {
            if (!array_key_exists($adminId, $adminCache)) {
                $adminCache[$adminId] = AdminAdministrator::query()->find($adminId, ['id', 'name', 'username']);
            }
            $admin = $adminCache[$adminId];
        }

        $rows = [];
        if ($telegramUserId > 0) {
            $rows[] = ['飞机ID', $telegramUserId];
        }

        if ($telegramName !== '') {
            $rows[] = ['飞机昵称', e($telegramName)];
        }

        if ($admin) {
            $adminText = e($admin->name ?: '-')
                . '<br><small>' . e($admin->username ?: '-') . ' / ID：' . (int)$admin->id . '</small>';
            $rows[] = ['管理员', $adminText];
        } elseif ($adminId > 0) {
            $rows[] = ['管理员', 'ID：' . $adminId];
        }

        return bob_show_table_info($rows, [], ['tr-1', 'tr-2', 'tr-3'], 3);
    }

    public static function merchantTable($merchant, int $mid): string
    {
        $rows = [
            ['商户ID', $mid],
            ['商户名称', $merchant ? e((string)$merchant->name) : '-'],
            ['商户编码', $merchant ? e((string)$merchant->coder) : '-'],
        ];

        return bob_show_table_info($rows, [], ['tr-1', 'tr-2', 'tr-3'], 3);
    }
}
