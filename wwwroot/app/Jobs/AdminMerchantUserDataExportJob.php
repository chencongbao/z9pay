<?php

namespace App\Jobs;

use App\Models\AgentUserRelation;
use App\Models\MerchantInfo;
use App\Models\MerchantUser as Administrator;
use App\Services\Cache\CacheConstPrefixService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Vtiful\Kernel\Excel;

class AdminMerchantUserDataExportJob implements ShouldQueue
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
        $this->cache_key = CacheConstPrefixService::ADMIN_MERCHANT_USER_EXPORT_HAS_EXIST . ($this->data['admin_id'] ?? 0);
    }

    public function handle()
    {
        set_time_limit(0);
        ini_set('memory_limit', '3072m');
        $export_path = 'export/admin_merchant_users/'.$this->data['admin_id'];
        if (!Storage::exists("public/".$export_path)) {
            Storage::makeDirectory("public/".$export_path);
        }
        $config = ['path' => storage_path("app/public/".$export_path)];
        $excel = new Excel($config);

        $name = date("YmdHis").'-merchant_users.xlsx';
        $url = $this->data['url']."/".$export_path."/".$name;
        $type = "admin_merchant_users";
        event(new \App\Events\SystemGloabelExportEvent(["block" => 0, 'url' => $url, 'status' => 0, 'type' => $type, "admin_id" => $this->data['admin_id']]));

        $fileObject = $excel->fileName($name);
        $fileObject = $fileObject->header([
            'ID',
            '商户账号',
            '商户名称',
            '商户代码',
            '状态',
            '交易币种',
            '账户总额',
            '可用余额',
            '冻结资金',
            '结算资金',
            '所属代理',
            '登录提现IP白名单',
            '代付IP白名单',
            '创建时间',
        ]);

        $model = $this->buildQuery();
        $model->chunkById(self::CHUNK_SIZE, function ($result) use ($fileObject, $url, $type) {
            $data = [];
            foreach ($result as $item) {
                $merchantInfo = $item->merchant_info;
                $data[] = [
                    $item->id,
                    $item->username,
                    optional($merchantInfo)->name,
                    optional($merchantInfo)->coder,
                    config('default.status_text')[$item->status] ?? $item->status,
                    bob_get_value_by_id_array(['id' => optional($merchantInfo)->currency_id], 'name', config('default.currency')),
                    floatval(optional($merchantInfo)->balance_amount),
                    floatval(optional($merchantInfo)->available_balance),
                    floatval(optional($merchantInfo)->freeze_amount),
                    floatval(optional($merchantInfo)->settlement_amount),
                    $this->formatAgent($merchantInfo),
                    $item->login_white_ip,
                    optional($merchantInfo)->pay_white_ip,
                    $item->created_at ? Carbon::parse($item->created_at)->format("Y-m-d H:i:s") : '',
                ];
            }
            $fileObject->data($data);
            event(new \App\Events\SystemGloabelExportEvent(["block" => $this->block, 'url' => $url, 'status' => 1, 'type' => $type, "admin_id" => $this->data['admin_id']]));
            $this->block += 1;
        });
        $fileObject->output();
        event(new \App\Events\SystemGloabelExportEvent(["block" => $this->block, 'url' => $url, 'status' => 2, 'type' => $type, "admin_id" => $this->data['admin_id']]));
        Cache::delete($this->cache_key);
    }

    protected function buildQuery()
    {
        $where = $this->filterEmptyValuesRecursive($this->data);
        $model = Administrator::with([
            'merchant_info' => function ($query) {
                $query->withTrashed()->with('agent_user');
            },
        ])->where('pid', 0);

        if (($where['_scope_'] ?? '') == 'trashed') {
            $model->onlyTrashed();
        }
        if (isset($where['id'])) {
            $model->where('id', $where['id']);
        }
        if (isset($where['username'])) {
            $model->where('username', 'like', "%{$where['username']}%");
        }
        if (isset($where['status'])) {
            $model->where('status', $where['status']);
        }
        if (isset($where['merchant_info']['name'])) {
            $model->whereHas('merchant_info', function ($query) use ($where) {
                $query->withTrashed()->where('name', 'like', "%{$where['merchant_info']['name']}%");
            });
        }
        if (isset($where['merchant_info']['coder'])) {
            $model->whereHas('merchant_info', function ($query) use ($where) {
                $query->withTrashed()->where('coder', 'like', "%{$where['merchant_info']['coder']}%");
            });
        }
        if (isset($where['merchant_info']['currency_id'])) {
            $model->whereHas('merchant_info', function ($query) use ($where) {
                $query->withTrashed()->where('currency_id', $where['merchant_info']['currency_id']);
            });
        }
        if (isset($where['agent_user_id'])) {
            $agentIds = AgentUserRelation::where('parent_id', $where['agent_user_id'])->pluck('child_id')->push($where['agent_user_id']);
            $merchantIds = MerchantInfo::withTrashed()->whereIn('agent_user_id', $agentIds)->pluck('merchant_user_id');
            $model->whereIn('id', $merchantIds);
        }

        return $model;
    }

    protected function formatAgent($merchantInfo)
    {
        if (empty($merchantInfo) || empty($merchantInfo->agent_user)) {
            return '';
        }

        return "【#".$merchantInfo->agent_user->id."】".$merchantInfo->agent_user->name;
    }

    protected function filterEmptyValuesRecursive($values)
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

    public function failed(\Throwable $exception)
    {
        Cache::delete($this->cache_key);
    }
}
