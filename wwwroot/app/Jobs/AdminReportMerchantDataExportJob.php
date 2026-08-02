<?php

namespace App\Jobs;

use Vtiful\Kernel\Excel;
use App\Models\MerchantInfo;
use Illuminate\Bus\Queueable;
use App\Models\ReportMerchant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\Cache\Merchant\CacheMerchantBaseInfoService;
use App\Services\Cache\MerchantAgent\GetMerchantAgentListService;

class AdminReportMerchantDataExportJob implements ShouldQueue
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
        $this->cache_key = CacheConstPrefixService::ADMIN_REPORT_MERCHANT_EXPORT_HAS_EXIST . ($this->data['admin_id'] ?? 0);
    }

    public function handle()
    {
        try {
            set_time_limit(0);
            ini_set('memory_limit', '3072m');

            $adminId = (int) ($this->data['admin_id'] ?? 0);
            $exportPath = 'export/admin_report_merchants/' . $adminId;
            if (!Storage::exists('public/' . $exportPath)) {
                Storage::makeDirectory('public/' . $exportPath);
            }

            $excel = new Excel(['path' => storage_path('app/public/' . $exportPath)]);
            $name = date('YmdHis') . '-report_merchants.xlsx';
            $url = rtrim($this->data['url'] ?? '', '/') . '/' . $exportPath . '/' . $name;
            $type = 'admin_report_merchants';
            event(new \App\Events\SystemGloabelExportEvent(['block' => 0, 'url' => $url, 'status' => 0, 'type' => $type, 'admin_id' => $adminId]));

            $fileObject = $excel->fileName($name)->header($this->header());
            $currencyMap = collect(config('default.currency', []))->pluck('name', 'id')->all();
            $merchantBaseInfoService = app(CacheMerchantBaseInfoService::class);
            $merchantAgentMap = collect(app(GetMerchantAgentListService::class)->excute())->keyBy('id');

            $this->buildQuery()->chunkById(self::CHUNK_SIZE, function ($result) use ($fileObject, $url, $type, $adminId, $currencyMap, $merchantBaseInfoService, $merchantAgentMap) {
                $data = [];
                foreach ($result as $item) {
                    $data[] = $this->row($item, $currencyMap, $merchantBaseInfoService, $merchantAgentMap);
                }

                $fileObject->data($data);
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
        $where = $this->filterEmptyValuesRecursive($this->data);
        $model = ReportMerchant::query()
            ->select($this->reportColumns())
            ->with(['merchant_info' => function ($query) {
                $query->select(['merchant_user_id', 'currency_id', 'agent_user_id', 'name', 'coder']);
            }]);

        if (isset($where['mid'])) {
            $model->where('mid', (int) $where['mid']);
        }

        if (isset($where['cid'])) {
            $merchantIds = MerchantInfo::query()->where('currency_id', (int) $where['cid'])->pluck('merchant_user_id');
            $model->whereIn('mid', $merchantIds);
        }

        $dateAdd = $where['date_add'] ?? [];
        if (is_array($dateAdd)) {
            if (isset($dateAdd['start'])) {
                $model->where('date_add', '>=', $dateAdd['start']);
            }
            if (isset($dateAdd['end'])) {
                $model->where('date_add', '<=', $dateAdd['end']);
            }
        } elseif ($dateAdd !== '') {
            $model->where('date_add', $dateAdd);
        }

        return $model;
    }

    private function header(): array
    {
        return array_values($this->baseHeader() + $this->metricHeader());
    }

    private function row($item, array $currencyMap, CacheMerchantBaseInfoService $merchantBaseInfoService, $merchantAgentMap): array
    {
        $merchantInfo = $merchantBaseInfoService->excute((int) $item->mid);
        $currencyId = (int) ($merchantInfo['currency_id'] ?? optional($item->merchant_info)->currency_id ?? 0);
        $merchantAgentNames = $this->merchantAgentNames($merchantInfo, $merchantAgentMap);
        $base = [
            $item->id,
            $item->date_add,
            $item->mid,
            $merchantInfo['name'] ?? optional($item->merchant_info)->name,
            $currencyMap[$currencyId] ?? '',
            $merchantAgentNames[0],
            $merchantAgentNames[1],
            $merchantAgentNames[2],
        ];

        return array_merge($base, $this->metricRow($item));
    }

    private function baseHeader(): array
    {
        return [
            'id' => 'ID',
            'date_add' => '日期',
            'mid' => '商户编号',
            'merchant_name' => '商户名称',
            'currency_name' => '币种',
            'merchant_agent1_name' => '一级代理',
            'merchant_agent2_name' => '二级代理',
            'merchant_agent3_name' => '三级代理',
        ];
    }

    private function metricHeader(): array
    {
        return [
            'deposit_order_number_total' => '代收提单数',
            'deposit_order_number_success' => '代收提单成功数',
            'deposit_order_total_amount' => '代收提单成功金额',
            'deposit_order_number_fail' => '代收提单失败数',
            'deposit_order_number_overtime' => '代收提单超时数',
            'deposit_order_number_swiping' => '代收提单刷单数',
            'deposit_order_success_rate' => '代收提单成功率',
            'deposit_created_success_number' => '代收成功入账单数',
            'deposit_created_success_amount' => '代收成功入账金额',
            'deposit_freeze_number' => '代收冻结笔数',
            'deposit_freeze_amount' => '代收冻结金额',
            'deposit_unfreeze_number' => '代收解冻笔数',
            'deposit_unfreeze_amount' => '代收解冻金额',
            'deposit_order_total_fee' => '代收商户手续费',
            'deposit_one_agent_commission' => '代收一级代理佣金',
            'deposit_two_agent_commission' => '代收二级代理佣金',
            'deposit_three_agent_commission' => '代收三级代理佣金',
            'deposit_profit' => '代收总利润',
            'jian_total_amount' => '商户资金减项',
            'add_total_amount' => '商户资金加项',
            'transfer_order_number_total' => '代付提单数',
            'transfer_order_number_success' => '代付提单成功数',
            'transfer_order_total_amount' => '代付提单成功金额',
            'transfer_order_number_fail' => '代付提单失败数',
            'transfer_order_success_rate' => '代付提单成功率',
            'transfer_created_success_number' => '代付成功出款单数',
            'transfer_created_success_amount' => '代付成功出款金额',
            'transfer_deduct_number' => '代付扣款笔数',
            'transfer_deduct_amount' => '代付扣款金额',
            'transfer_corre_number' => '代付冲正笔数',
            'transfer_corre_amount' => '代付冲正金额',
            'transfer_order_total_fee' => '代付商户手续费',
            'transfer_one_agent_commission' => '代付一级代理佣金',
            'transfer_two_agent_commission' => '代付二级代理佣金',
            'transfer_three_agent_commission' => '代付三级代理佣金',
            'transfer_profit' => '代付总利润',
            'settlement_order_number_total' => '结算提单数',
            'settlement_order_number_success' => '结算提单成功数',
            'settlement_order_total_amount' => '结算提单成功金额',
            'settlement_order_number_fail' => '结算提单失败数',
            'settlement_order_success_rate' => '结算提单成功率',
            'settlement_created_success_number' => '成功结算单数',
            'settlement_created_success_amount' => '成功结算金额',
            'settlement_deduct_number' => '结算扣款笔数',
            'settlement_deduct_amount' => '结算扣款金额',
            'settlement_corre_number' => '结算冲正笔数',
            'settlement_corre_amount' => '结算冲正金额',
            'settlement_order_total_fee' => '结算商户手续费',
            'settlement_one_agent_commission' => '结算一级代理佣金',
            'settlement_two_agent_commission' => '结算二级代理佣金',
            'settlement_three_agent_commission' => '结算三级代理佣金',
            'settlement_profit' => '结算利润',
        ];
    }

    private function metricRow($item): array
    {
        return [
            (int) $item->deposit_order_number_total,
            (int) $item->deposit_order_number_success,
            floatval($item->deposit_order_total_amount),
            (int) $item->deposit_order_number_fail,
            (int) $item->deposit_order_number_overtime,
            (int) $item->deposit_order_number_swiping,
            bob_percent($item->deposit_order_number_success, $item->deposit_order_number_total),
            (int) $item->deposit_created_success_number,
            floatval($item->deposit_created_success_amount),
            (int) $item->deposit_freeze_number,
            floatval($item->deposit_freeze_amount),
            (int) $item->deposit_unfreeze_number,
            floatval($item->deposit_unfreeze_amount),
            floatval($item->deposit_order_total_fee),
            floatval($item->deposit_one_agent_commission),
            floatval($item->deposit_two_agent_commission),
            floatval($item->deposit_three_agent_commission),
            floatval($item->deposit_profit),
            floatval($item->jian_total_amount),
            floatval($item->add_total_amount),
            (int) $item->transfer_order_number_total,
            (int) $item->transfer_order_number_success,
            floatval($item->transfer_order_total_amount),
            (int) $item->transfer_order_number_fail,
            bob_percent($item->transfer_order_number_success, $item->transfer_order_number_total),
            (int) $item->transfer_created_success_number,
            floatval($item->transfer_created_success_amount),
            (int) $item->transfer_deduct_number,
            floatval($item->transfer_deduct_amount),
            (int) $item->transfer_corre_number,
            floatval($item->transfer_corre_amount),
            floatval($item->transfer_order_total_fee),
            floatval($item->transfer_one_agent_commission),
            floatval($item->transfer_two_agent_commission),
            floatval($item->transfer_three_agent_commission),
            floatval($item->transfer_profit),
            (int) $item->settlement_order_number_total,
            (int) $item->settlement_order_number_success,
            floatval($item->settlement_order_total_amount),
            (int) $item->settlement_order_number_fail,
            bob_percent($item->settlement_order_number_success, $item->settlement_order_number_total),
            (int) $item->settlement_created_success_number,
            floatval($item->settlement_created_success_amount),
            (int) $item->settlement_deduct_number,
            floatval($item->settlement_deduct_amount),
            (int) $item->settlement_corre_number,
            floatval($item->settlement_corre_amount),
            floatval($item->settlement_order_total_fee),
            floatval($item->settlement_one_agent_commission),
            floatval($item->settlement_two_agent_commission),
            floatval($item->settlement_three_agent_commission),
            floatval($item->settlement_profit),
        ];
    }

    private function reportColumns(): array
    {
        return [
            'id', 'mid', 'date_add',
            'deposit_order_number_total', 'deposit_created_success_number', 'deposit_created_success_amount', 'deposit_order_number_success',
            'deposit_order_number_fail', 'deposit_order_number_overtime', 'deposit_order_number_swiping', 'deposit_freeze_number', 'deposit_freeze_amount',
            'deposit_unfreeze_number', 'deposit_unfreeze_amount',
            'deposit_order_total_amount', 'deposit_order_total_fee', 'deposit_one_agent_commission', 'deposit_two_agent_commission', 'deposit_three_agent_commission',
            'deposit_profit', 'jian_total_amount', 'add_total_amount',
            'transfer_order_number_total', 'transfer_created_success_number', 'transfer_created_success_amount', 'transfer_order_number_success',
            'transfer_order_number_fail', 'transfer_order_total_amount', 'transfer_deduct_number', 'transfer_deduct_amount', 'transfer_corre_number',
            'transfer_corre_amount', 'transfer_order_total_fee',
            'transfer_one_agent_commission', 'transfer_two_agent_commission', 'transfer_three_agent_commission', 'transfer_profit',
            'settlement_order_number_total', 'settlement_created_success_number', 'settlement_created_success_amount', 'settlement_order_number_success',
            'settlement_order_number_fail', 'settlement_order_total_amount', 'settlement_deduct_number', 'settlement_deduct_amount',
            'settlement_corre_number', 'settlement_corre_amount', 'settlement_order_total_fee',
            'settlement_one_agent_commission', 'settlement_two_agent_commission', 'settlement_three_agent_commission', 'settlement_profit',
        ];
    }

    private function merchantAgentNames(array $merchantInfo, $merchantAgentMap): array
    {
        $agentIds = [
            (int) ($merchantInfo['merchant_agent1_id'] ?? $merchantInfo['agent_user_id'] ?? 0),
            (int) ($merchantInfo['merchant_agent2_id'] ?? 0),
            (int) ($merchantInfo['merchant_agent3_id'] ?? 0),
        ];

        $agentNames = [];
        foreach ($agentIds as $agentId) {
            $agentNames[] = $agentId > 0 ? data_get($merchantAgentMap->get($agentId), 'bname', "【#{$agentId}】") : '';
        }

        return $agentNames;
    }

    private function filterEmptyValuesRecursive(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = $this->filterEmptyValuesRecursive($value);
            }
            if ($values[$key] === '' || $values[$key] === null || $values[$key] === []) {
                unset($values[$key]);
            }
        }

        return $values;
    }
}
