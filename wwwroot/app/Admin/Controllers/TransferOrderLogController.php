<?php

namespace App\Admin\Controllers;

use Dcat\Admin\Grid;
use App\Models\TransferOrderLog;
use Dcat\Admin\Grid\LazyRenderable;

class TransferOrderLogController extends LazyRenderable
{
    public function grid(): Grid
    {
        $orderId = (int) request('transfer_order_id');
        $query = TransferOrderLog::query()->select(['id', 'order_id', 'created_at', 'type', 'message', 'content'])->orderBy('id', 'asc');
        if ($orderId > 0) {
            $query->where('order_id', $orderId);
        }

        return Grid::make($query, function (Grid $grid) {
            $grid->column('created_at', '时间')->display(function ($value) {
                if ($this->type == 'ERROR') {
                    return '<span style="color: orangered">' . e($value) . '</span>';
                }

                return $value;
            });

            $grid->column('type', '类型')->display(function ($value) {
                if ($this->type == 'ERROR') {
                    return '<span style="color: orangered">' . e($value) . '</span>';
                }

                return $value;
            });

            $grid->column('message', '消息')->display(function ($value) {
                if ($this->type == 'ERROR') {
                    return '<span style="color: orangered">' . e($value) . '</span>';
                }

                return $value;
            });

            $grid->column('content', '详情')->display(function ($value) {
                if (empty($value)) {
                    return;
                }

                $input = json_decode($value, true);
                if (is_array($input)) {
                    $content = e(json_encode($input, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

                    if ($this->type == 'ERROR') {
                        return '<pre style="color: orangered;overflow: auto">' . $content . '</pre>';
                    }

                    return '<pre class="dump" style="max-width: 500px;overflow: auto">' . $content . '</pre>';
                }

                if ($this->type == 'ERROR') {
                    return '<pre style="max-width: 520px;overflow: auto;white-space: pre-wrap;word-break: break-all;margin: 0;color: orangered;">' . e($value) . '</pre>';
                }

                return '<pre style="max-width: 520px;overflow: auto;white-space: pre-wrap;word-break: break-all;margin: 0;">' . e($value) . '</pre>';
            });

            $grid->disableActions();
        });
    }
}
