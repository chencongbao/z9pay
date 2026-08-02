<?php

namespace App\Jobs;

use Vtiful\Kernel\Excel;
use Illuminate\Bus\Queueable;
use App\Models\ReportChannel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\ReportChannelMerchant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\Cache\Channel\GetChannelListService;
use App\Services\Cache\Merchant\CacheMerchantBaseInfoService;

class AdminReportChannelDataExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const CHUNK_SIZE = 2000;

    public $tries = 1;

    public $timeout = 1000;

    public $data = [];

    public $block = 1;

    public $cache_key;

    public function __construct($data = [])
    {
        $this->data = $data;
        $this->cache_key = CacheConstPrefixService::ADMIN_REPORT_CHANNEL_EXPORT_HAS_EXIST . ($this->data['admin_id'] ?? 0);
    }

    public function handle()
    {
        try {
            set_time_limit(0);
            ini_set('memory_limit', '3072m');

            $adminId = (int) ($this->data['admin_id'] ?? 0);
            $exportPath = 'export/admin_report_channels/' . $adminId;
            $disk = Storage::disk('public');
            $disk->makeDirectory($exportPath);

            $excel = new Excel(['path' => $disk->path($exportPath)]);
            $name = date('YmdHis') . '-report-channels.xlsx';
            $url = rtrim($this->data['url'] ?? '', '/') . '/' . $exportPath . '/' . $name;
            $type = 'admin_report_channels';
            event(new \App\Events\SystemGloabelExportEvent(['block' => 0, 'url' => $url, 'status' => 0, 'type' => $type, 'admin_id' => $adminId]));

            $fileObject = $excel->fileName($name)->header($this->header());
            $channelMap = collect(app(GetChannelListService::class)->excute())->keyBy('id');
            $merchantBaseInfoService = app(CacheMerchantBaseInfoService::class);

            $this->buildQuery()->chunkById(self::CHUNK_SIZE, function ($result) use ($fileObject, $url, $type, $adminId, $channelMap, $merchantBaseInfoService) {
                $rows = [];
                foreach ($result as $item) {
                    $rows[] = $this->row($item, $channelMap, $merchantBaseInfoService);
                }

                $fileObject->data($rows);
                event(new \App\Events\SystemGloabelExportEvent(['block' => $this->block, 'url' => $url, 'status' => 1, 'type' => $type, 'admin_id' => $adminId]));
                $this->block += 1;
            });

            $fileObject->output();
            event(new \App\Events\SystemGloabelExportEvent(['block' => $this->block, 'url' => $url, 'status' => 2, 'type' => $type, 'admin_id' => $adminId]));
        } finally {
            Cache::delete($this->cache_key);
        }
    }

    public function failed(\Throwable $exception)
    {
        Cache::delete($this->cache_key);
    }

    private function buildQuery()
    {
        $mid = $this->positiveInteger($this->data['mid'] ?? null);
        $cid = $this->positiveInteger($this->data['cid'] ?? null);
        $model = ($mid > 0 ? ReportChannelMerchant::query() : ReportChannel::query())
            ->select($this->reportColumns())
            ->orderBy('id');

        if ($mid > 0) {
            $model->where('mid', $mid);
        }
        if ($cid > 0) {
            $model->where('cid', $cid);
        }

        $dateAdd = $this->data['date_add'] ?? [];
        if (is_array($dateAdd)) {
            if (!empty($dateAdd['start']) && is_scalar($dateAdd['start'])) {
                $model->where('date_add', '>=', (string) $dateAdd['start']);
            }
            if (!empty($dateAdd['end']) && is_scalar($dateAdd['end'])) {
                $model->where('date_add', '<=', (string) $dateAdd['end']);
            }
        } elseif (is_scalar($dateAdd) && $dateAdd !== '') {
            $model->where('date_add', (string) $dateAdd);
        }

        return $model;
    }

    private function header(): array
    {
        return array_values($this->baseHeader() + $this->metricHeader());
    }

    private function row($item, $channelMap, CacheMerchantBaseInfoService $merchantBaseInfoService): array
    {
        $channel = $channelMap->get((int) $item->cid);
        $base = [$item->id, $item->date_add, $item->cid, data_get($channel, 'bname', '')];

        if ($this->withMerchant()) {
            $merchantInfo = $merchantBaseInfoService->excute((int) $item->mid);
            $base[] = $item->mid;
            $base[] = $merchantInfo['bname'] ?? $merchantInfo['name'] ?? '';
        }

        return array_merge($base, $this->metricRow($item));
    }

    private function baseHeader(): array
    {
        $header = [
            'id' => 'ID',
            'date_add' => '日期',
            'cid' => '渠道编号',
            'channel_name' => '渠道名称',
        ];

        if ($this->withMerchant()) {
            $header['mid'] = '商户编号';
            $header['merchant_name'] = '商户名称';
        }

        return $header;
    }

    private function metricHeader(): array
    {
        return [
            'deposit_order_number_total' => '代收单数',
            'deposit_order_number_success' => '代收成功单数',
            'deposit_order_number_fail' => '代收失败单数',
            'deposit_order_number_overtime' => '代收超时单数',
            'deposit_order_number_swiping' => '代收刷单单数',
            'deposit_order_success_rate' => '代收成功率',
            'deposit_order_total_amount' => '代收跑量',
            'deposit_order_total_fee' => '代收商户手续费',
            'deposit_profit' => '代收总利润',
            'transfer_order_number_total' => '代付单数',
            'transfer_order_number_success' => '代付成功单数',
            'transfer_order_number_fail' => '代付失败单数',
            'transfer_order_success_rate' => '代付成功率',
            'transfer_order_total_amount' => '代付跑量',
            'transfer_order_total_fee' => '代付总手续费',
            'transfer_profit' => '代付总利润',
            'settlement_order_number_total' => '结算单数',
            'settlement_order_number_success' => '结算成功单数',
            'settlement_order_number_fail' => '结算失败单数',
            'settlement_order_success_rate' => '结算成功率',
            'settlement_order_total_amount' => '结算跑量',
            'settlement_order_total_fee' => '结算商户手续费',
            'settlement_profit' => '结算利润',
        ];
    }

    private function metricRow($item): array
    {
        return [
            (int) $item->deposit_order_number_total,
            (int) $item->deposit_order_number_success,
            (int) $item->deposit_order_number_fail,
            (int) $item->deposit_order_number_overtime,
            (int) $item->deposit_order_number_swiping,
            bob_percent($item->deposit_order_number_success, $item->deposit_order_number_total),
            (float) $item->deposit_order_total_amount,
            (float) $item->deposit_order_total_fee,
            (float) $item->deposit_profit,
            (int) $item->transfer_order_number_total,
            (int) $item->transfer_order_number_success,
            (int) $item->transfer_order_number_fail,
            bob_percent($item->transfer_order_number_success, $item->transfer_order_number_total),
            (float) $item->transfer_order_total_amount,
            (float) $item->transfer_order_total_fee,
            (float) $item->transfer_profit,
            (int) $item->settlement_order_number_total,
            (int) $item->settlement_order_number_success,
            (int) $item->settlement_order_number_fail,
            bob_percent($item->settlement_order_number_success, $item->settlement_order_number_total),
            (float) $item->settlement_order_total_amount,
            (float) $item->settlement_order_total_fee,
            (float) $item->settlement_profit,
        ];
    }

    private function reportColumns(): array
    {
        $columns = array_keys($this->baseHeader());
        $columns = array_values(array_filter($columns, fn ($column) => !in_array($column, ['channel_name', 'merchant_name'], true)));
        $metricColumns = array_keys($this->metricHeader());
        $metricColumns = array_values(array_filter($metricColumns, fn ($column) => !str_ends_with($column, '_success_rate')));

        return array_merge($columns, $metricColumns);
    }

    private function withMerchant(): bool
    {
        return $this->positiveInteger($this->data['mid'] ?? null) > 0;
    }

    private function positiveInteger($value): int
    {
        return is_int($value) || (is_string($value) && ctype_digit($value)) ? max(0, (int) $value) : 0;
    }
}
