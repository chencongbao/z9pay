<?php

namespace App\Admin\Controllers;

use Dcat\Admin\Grid;
use App\Models\DepositeOrderLog;

class DepositeOrderLogController extends Grid\LazyRenderable
{
    public function grid(): Grid
    {
        $depositOrderId = (int) request('deposit_order_id', 0);
        $highlight = function ($value, string $type) {
            return $this->highlightError($value, $type);
        };
        $formatContent = function ($value, string $type): string {
            return $this->formatContent($value, $type);
        };
        $query = DepositeOrderLog::query()
            ->select(['id', 'order_id', 'type', 'message', 'content', 'created_at'])
            ->orderBy('id');

        return Grid::make($query, function (Grid $grid) use ($depositOrderId, $highlight, $formatContent) {
            if ($depositOrderId > 0) {
                $grid->model()->where('order_id', $depositOrderId);
            }

            $grid->column('created_at', '时间')->display(function ($value) use ($highlight) {
                return $highlight($value, (string) $this->type);
            });
            $grid->column('type', '类型')->display(function ($value) use ($highlight) {
                return $highlight($value, (string) $this->type);
            });
            $grid->column('message', '消息')->display(function ($value) use ($highlight) {
                return $highlight($value, (string) $this->type);
            });
            $grid->column('content', '详情')->display(function ($value) use ($formatContent) {
                return $formatContent($value, (string) $this->type);
            });
            $grid->disableActions();
            $grid->paginate(5);
        });
    }

    private function highlightError($value, string $type): string
    {
        $value = e($value);
        if ($type === 'ERROR') {
            return '<span style="color: orangered">' . $value . '</span>';
        }

        return $value;
    }

    private function formatContent($value, string $type): string
    {
        if (empty($value)) {
            return '';
        }

        $input = json_decode($value, true);
        if (!is_array($input)) {
            return $this->formatTextContent($value, $type);
        }

        $json = e(json_encode($input, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        if ($type === 'ERROR') {
            return '<pre style="color: orangered;overflow: auto">' . $json . '</pre>';
        }

        return '<pre class="dump" style="max-width: 500px;overflow: auto">' . $json . '</pre>';
    }

    private function formatTextContent($value, string $type): string
    {
        $content = e($value);
        $style = 'max-width: 520px;overflow: auto;white-space: pre-wrap;word-break: break-all;margin: 0;';
        if ($type === 'ERROR') {
            $style .= 'color: orangered;';
        }

        return '<pre style="' . $style . '">' . $content . '</pre>';
    }
}
