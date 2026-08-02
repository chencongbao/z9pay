<?php

namespace App\Jobs;

use Carbon\Carbon;
use Vtiful\Kernel\Excel;
use App\Models\DepositOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use App\Services\Common\ModelQueryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\Cache\MerchantAgent\GetMerchantAgentListService;

class AdminDepositOrderDataExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const CHUNK_SIZE = 2000;

    public $tries = 1;

    public $timeout = 1000;

    public $data = [];

    public $block = 1;

    public $cache_key;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data = [])
    {
        $this->data = $data;
        $this->cache_key = CacheConstPrefixService::ADMIN_DEPOSIT_ORDER_EXPORT_HAS_EXIST . ($this->data['admin_id'] ?? 0);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            set_time_limit(0);
            ini_set('memory_limit', '3072m');

            $adminId = intval($this->data['admin_id'] ?? 0);
            $export_path = 'export/admin_deposit_order/' . $adminId;
            if (!Storage::exists("public/" . $export_path)) {
                Storage::makeDirectory("public/" . $export_path);
            }
            $config = ['path' => storage_path("app/public/" . $export_path)];
            $excel = new Excel($config);

            $name = date("YmdHis") . '-deposit_orders.xlsx';
            $url = rtrim($this->data['url'] ?? '', '/') . "/" . $export_path . "/" . $name;
            $type = "admin_deposit_export";
            event(new \App\Events\SystemGloabelExportEvent(["block" => 0, 'url' => $url, 'status' => 0, 'type' => $type, "admin_id" => $adminId]));

            $fileObject = $excel->fileName($name);
            $fileObject = $fileObject->header([
                'ID', '商户编号', '商户名称', '商户单号', '平台单号', '渠道单号', '付方金额', '订单金额', '实付金额', '币种',
                '手续费', '额外手续费', '费率', '一级代理', '二级代理', '三级代理', '一级代理费率', '二级代理费率', '三级代理费率', '一级代理佣金', '二级代理佣金', '三级代理佣金',
                '渠道成本', '系统利润', '渠道', '金主', '编码', '状态', '创建时间', '成功时间', '回调时间', '入账金额', 'USDT实时汇率', 'USDT入账金额', '付款人', '会员IP',
            ]);

            $currencyMap = collect(config('default.currency'))->keyBy('id');
            $paymentMap = collect(config('payment'))->keyBy('id');
            $statusMap = config('default.deposite_status');
            $merchantAgentMap = collect(App::make(GetMerchantAgentListService::class)->excute())->keyBy('id');

            $model = App::make(ModelQueryService::class)->excute(new DepositOrder(), $this->data);
            $model->select(
                'id', 'mid', 'order_no', 'ordernumber', 'amount', 'pay_amount', 'actual_amount', 'currency_id',
                'merchant_fee', 'merchant_extra_fee', 'merchant_rate', 'merchant_agent1_rate', 'merchant_agent2_rate', 'merchant_agent3_rate',
                'merchant_agent1_id', 'merchant_agent2_id', 'merchant_agent3_id', 'merchant_agent1_commission', 'merchant_agent2_commission', 'merchant_agent3_commission', 'channel_cost', 'profit',
                'channel_id', 'user_id', 'payment_id', 'status', 'created_at', 'success_time', 'callback_time', 'usdt_rate', 'pay_name', 'ip', 'channel_ordernumber'
            )->with(['merchant_info' => function ($query) {
                $query->select('merchant_user_id', "name");
            }, 'channel' => function ($query) {
                $query->select("id", "name");
            }, 'user' => function ($query) {
                $query->select("id", "name");
            }])->chunkById(self::CHUNK_SIZE, function ($result) use ($fileObject, $url, $type, $adminId, $currencyMap, $paymentMap, $statusMap, $merchantAgentMap) {
                $data = [];
                foreach ($result as $item) {
                    $incomeAmount = $item->actual_amount > 0 ? ($item->actual_amount - $item->merchant_fee - $item->merchant_extra_fee) : 0;
                    $createdAt = $item->created_at instanceof Carbon ? $item->created_at->format('Y-m-d H:i:s') : Carbon::parse($item->created_at)->format('Y-m-d H:i:s');
                    $merchantAgentNames = $this->merchantAgentNames($item, $merchantAgentMap);
                    $data[] = [
                        $item->id,
                        $item->mid,
                        optional($item->merchant_info)->offsetGet('name'),
                        $item->order_no,
                        $item->ordernumber,
                        $item->channel_ordernumber,
                        $item->amount,
                        $item->pay_amount,
                        $item->actual_amount,
                        optional($currencyMap->get($item->currency_id))->offsetGet('name'),
                        $item->merchant_fee,
                        $item->merchant_extra_fee,
                        $item->merchant_rate,
                        $merchantAgentNames[0],
                        $merchantAgentNames[1],
                        $merchantAgentNames[2],
                        $item->merchant_agent1_rate,
                        $item->merchant_agent2_rate,
                        $item->merchant_agent3_rate,
                        $item->merchant_agent1_commission,
                        $item->merchant_agent2_commission,
                        $item->merchant_agent3_commission,
                        $item->channel_cost,
                        $item->profit,
                        optional($item->channel)->offsetGet('name'),
                        optional($item->user)->offsetGet('name'),
                        optional($paymentMap->get($item->payment_id))->offsetGet('name'),
                        $statusMap[$item->status] ?? '',
                        $createdAt,
                        $item->success_time > 0 ? date('Y-m-d H:i:s', $item->success_time) : '',
                        $item->callback_time > 0 ? date('Y-m-d H:i:s', $item->callback_time) : '',
                        $incomeAmount,
                        $item->usdt_rate,
                        $item->usdt_rate > 0 ? bcdiv((string)$incomeAmount, (string)$item->usdt_rate, 2) : 0,
                        $item->pay_name,
                        $item->ip,
                    ];
                }
                $fileObject->data($data);
                event(new \App\Events\SystemGloabelExportEvent(["block" => $this->block, 'url' => $url, 'status' => 1, 'type' => $type, "admin_id" => $adminId]));
                $this->block += 1;
            });
            $fileObject->output();
            event(new \App\Events\SystemGloabelExportEvent(["block" => $this->block, 'url' => $url, 'status' => 2, 'type' => $type, "admin_id" => $adminId]));
        } finally {
            Cache::delete($this->cache_key);
        }
    }

    public function failed(\Throwable $exception)
    {
        Cache::delete($this->cache_key);
    }

    private function merchantAgentNames($item, $merchantAgentMap): array
    {
        $agentIds = [
            (int)$item->merchant_agent1_id,
            (int)$item->merchant_agent2_id,
            (int)$item->merchant_agent3_id,
        ];

        $agentNames = [];
        foreach ($agentIds as $agentId) {
            $agentNames[] = $agentId > 0 ? data_get($merchantAgentMap->get($agentId), 'bname', "【#{$agentId}】") : '';
        }

        return $agentNames;
    }
}
