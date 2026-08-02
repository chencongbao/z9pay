<?php

namespace App\Jobs;

use App\Events\SystemGloabelExportEvent;
use App\Models\AgentBalanceLog;
use App\Models\AgentUserRelation;
use App\Models\DepositOrder;
use App\Models\TransferOrder;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\Common\GetPaymentDetailService;
use App\Services\Common\ModelQueryService;
use App\Services\Order\OrderCacheService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Vtiful\Kernel\Excel;

class AgentDataExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const CHUNK_SIZE = 2000;

    public $tries = 1;

    public $timeout = 1000;

    public array $data = [];

    public int $block = 1;

    public string $cache_key;

    public function __construct(array $data = [])
    {
        $this->data = $data;
        $this->cache_key = $this->lockPrefix() . ($this->data['admin_id'] ?? 0);
    }

    public function handle(): void
    {
        App::setLocale($this->data['locale'] ?? config('app.locale'));
        set_time_limit(0);
        ini_set('memory_limit', '3072m');

        $type = $this->exportType();
        $directory = storage_path('app/public/export/' . $type . '/' . (int)$this->data['admin_id']);
        File::ensureDirectoryExists($directory);

        $name = date('YmdHis') . '-' . str_replace('_', '-', $type) . '.xlsx';
        $url = rtrim((string)($this->data['url'] ?? config('filesystems.disks.public.url')), '/') . '/export/' . $type . '/' . (int)$this->data['admin_id'] . '/' . $name;
        $excel = new Excel(['path' => $directory]);
        $fileObject = $excel->fileName($name)->header($this->headers());

        event(new SystemGloabelExportEvent(['block' => 0, 'url' => $url, 'status' => 0, 'type' => $type, 'admin_id' => $this->data['admin_id']]));
        $this->writeRows($fileObject, $url, $type);
        $fileObject->output();
        event(new SystemGloabelExportEvent(['block' => $this->block, 'url' => $url, 'status' => 2, 'type' => $type, 'admin_id' => $this->data['admin_id']]));
        Cache::delete($this->cache_key);
    }

    public function failed(\Throwable $exception): void
    {
        Cache::delete($this->cache_key);
    }

    private function writeRows($fileObject, string $url, string $type): void
    {
        if ($this->data['agent_export_type'] === 'balance_logs') {
            $this->balanceLogQuery()->chunkById(self::CHUNK_SIZE, function ($rows) use ($fileObject, $url, $type) {
                $fileObject->data($this->balanceLogRows($rows));
                $this->sendProgress($url, $type);
            });
            return;
        }

        $this->orderQuery()->chunkById(self::CHUNK_SIZE, function ($rows) use ($fileObject, $url, $type) {
            $fileObject->data($this->orderRows($rows));
            $this->sendProgress($url, $type);
        });
    }

    private function orderQuery()
    {
        $model = $this->data['agent_export_type'] === 'deposit_orders' ? new DepositOrder() : new TransferOrder();
        $query = App::make(ModelQueryService::class)->excute($model, $this->data);
        $query->whereIn('merchant_agent1_id', $this->childAgentIds());

        if ($this->data['agent_export_type'] === 'transfer_orders') {
            $query->where('type', 0);
        }
        if ($this->data['agent_export_type'] === 'settlement_orders') {
            $query->where('type', 1);
        }

        return $query->with(['merchant_info' => function ($query) {
            $query->select('merchant_user_id', 'name');
        }])->orderBy('id', 'desc');
    }

    private function balanceLogQuery()
    {
        $this->data['agent_id'] = (int)$this->data['admin_id'];

        return App::make(ModelQueryService::class)
            ->excute(new AgentBalanceLog(), $this->data)
            ->with(['merchant_info' => function ($query) {
                $query->select('merchant_user_id', 'name', 'coder', 'currency_id');
            }])
            ->orderBy('id', 'desc');
    }

    private function orderRows($rows): array
    {
        $data = [];
        $agentId = (int)$this->data['admin_id'];
        $isDeposit = $this->data['agent_export_type'] === 'deposit_orders';

        foreach ($rows as $item) {
            $data[] = [
                $item->id,
                optional($item->merchant_info)->offsetGet('name'),
                $item->order_no,
                $item->ordernumber,
                $isDeposit ? floatval($item->pay_amount) : floatval($item->amount),
                floatval($item->actual_amount),
                $this->agentCommission($item, $agentId),
                $this->agentRate($item, $agentId),
                $this->currencyName($item->currency_id),
                $isDeposit ? App::make(GetPaymentDetailService::class)->excute($item->payment_id) : '',
                admin_trans_option($item->status, $isDeposit ? 'deposit_status' : 'transfer_status'),
                $item->success_time > 0 ? date('Y-m-d H:i:s', $item->success_time) : '',
                Carbon::parse($item->created_at)->format('Y-m-d H:i:s'),
            ];
        }

        return $data;
    }

    private function balanceLogRows($rows): array
    {
        $data = [];

        foreach ($rows as $item) {
            $orderInfo = $this->balanceLogOrderInfo($item);
            $data[] = [
                $item->id,
                $orderInfo['ordernumber'],
                $orderInfo['order_no'],
                optional($item->merchant_info)->offsetGet('bname'),
                admin_trans_option($item->type, 'agent_balance_type'),
                floatval($item->amount),
                floatval($item->balance_amount),
                $this->currencyName(optional($item->merchant_info)->offsetGet('currency_id')),
                Carbon::parse($item->created_at)->format('Y-m-d H:i:s'),
            ];
        }

        return $data;
    }

    private function balanceLogOrderInfo($item): array
    {
        $order = null;
        if ((int)$item->type === 1 && $item->type_id > 0) {
            $order = App::make(OrderCacheService::class)->getDepositById($item->type_id);
        }
        if ((int)$item->type === 2 && $item->type_id > 0) {
            $order = App::make(OrderCacheService::class)->getTransferById($item->type_id);
        }

        return [
            'ordernumber' => optional($order)->offsetGet('ordernumber') ?: $item->ordernumber,
            'order_no' => optional($order)->offsetGet('order_no') ?: '',
        ];
    }

    private function headers(): array
    {
        if ($this->data['agent_export_type'] === 'balance_logs') {
            return ['ID', admin_trans_label('ordernumber'), admin_trans_label('order_no'), admin_trans_label('merchant'), admin_trans_field('type'), admin_trans_field('amount'), admin_trans_field('balance_amount'), admin_trans_field('currency'), admin_trans_label('created_at')];
        }

        return ['ID', admin_trans_label('merchant'), admin_trans_label('order_no'), admin_trans_label('ordernumber'), admin_trans_field('pay_amount'), admin_trans_field('actual_amount'), admin_trans_label('commision_fee'), admin_trans_label('commision_rate'), admin_trans_field('currency'), admin_trans_label('payment_type'), admin_trans_label('order_status'), admin_trans_label('success_time'), admin_trans_label('created_at')];
    }

    private function sendProgress(string $url, string $type): void
    {
        event(new SystemGloabelExportEvent(['block' => $this->block, 'url' => $url, 'status' => 1, 'type' => $type, 'admin_id' => $this->data['admin_id']]));
        $this->block++;
    }

    private function childAgentIds(): array
    {
        return AgentUserRelation::query()->where('parent_id', (int)$this->data['admin_id'])->pluck('child_id')->all();
    }

    private function agentCommission($item, int $agentId): float
    {
        foreach ([1, 2, 3] as $level) {
            if ((int)$item->{"merchant_agent{$level}_id"} === $agentId) {
                return floatval($item->{"merchant_agent{$level}_commission"});
            }
        }

        return 0;
    }

    private function agentRate($item, int $agentId): string
    {
        foreach ([1, 2, 3] as $level) {
            if ((int)$item->{"merchant_agent{$level}_id"} === $agentId) {
                return (floatval($item->{"merchant_agent{$level}_rate"}) * 100) . '%';
            }
        }

        return '';
    }

    private function currencyName($currencyId): string
    {
        return (string)optional(collect(config('default.currency'))->firstWhere('id', $currencyId))->offsetGet('name');
    }

    private function exportType(): string
    {
        return match ($this->data['agent_export_type'] ?? '') {
            'deposit_orders' => 'agent_deposit_orders',
            'transfer_orders' => 'agent_transfer_orders',
            'settlement_orders' => 'agent_settlement_orders',
            'balance_logs' => 'agent_balance_logs',
            default => 'agent_export',
        };
    }

    private function lockPrefix(): string
    {
        return match ($this->data['agent_export_type'] ?? '') {
            'deposit_orders' => CacheConstPrefixService::AGENT_DEPOSIT_ORDER_EXPORT_HAS_EXIST,
            'transfer_orders' => CacheConstPrefixService::AGENT_TRANSFER_ORDER_EXPORT_HAS_EXIST,
            'settlement_orders' => CacheConstPrefixService::AGENT_SETTLEMENT_ORDER_EXPORT_HAS_EXIST,
            'balance_logs' => CacheConstPrefixService::AGENT_BALANCE_LOG_EXPORT_HAS_EXIST,
            default => 'agent_export_has_exist_',
        };
    }
}
