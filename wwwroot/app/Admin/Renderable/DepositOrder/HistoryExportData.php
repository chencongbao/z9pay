<?php

namespace App\Admin\Renderable\DepositOrder;

use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Table;
use Dcat\Admin\Support\LazyRenderable;
use Illuminate\Support\Facades\Storage;

class HistoryExportData extends LazyRenderable
{
    public function render()
    {
        $header = ['导出日期', '操作'];
        $data = [];
        $disk = Storage::disk('public');
        $path = 'export/admin_deposit_order/' . Admin::user()->id;
        $files = $disk->allFiles($path);
        $todayStart = strtotime(date('Y-m-d') . ' 00:00:00');

        foreach ($files as $file) {
            $lastModified = $disk->lastModified($file);
            if ($lastModified < $todayStart) {
                $disk->delete($file);
                continue;
            }

            $data[] = [
                date('Y-m-d H:i:s', $lastModified),
                'timestamp' => $lastModified,
                '<a href="' . $disk->url($file) . '" target="_blank" class="blue">下载</a>',
            ];
        }

        if (!empty($data)) {
            usort($data, fn ($a, $b) => $b['timestamp'] - $a['timestamp']);
            $data = array_map(function ($item) {
                unset($item['timestamp']);
                return $item;
            }, $data);
        }

        $table = new Table($header, $data);
        $table->withBorder();
        $table->setStyle(['custom-data-table data-table table-bordered complex-headers']);

        return $table;
    }
}
