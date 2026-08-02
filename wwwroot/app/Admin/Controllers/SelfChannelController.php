<?php

namespace App\Admin\Controllers;

use Carbon\Carbon;
use Dcat\Admin\Admin;
use Dcat\Admin\Layout\Row;
use Dcat\Admin\Widgets\Card;
use Dcat\Admin\Layout\Content;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Admin\Forms\SelfChannel\ConfigForm;
use Richard\Payment\Channel\SelfChannelPayment;

class SelfChannelController extends CommonController
{
    protected $disableCreate = true;

    protected $disableEdit = true;

    public function index(Content $content): Content
    {
        Admin::css('/vendor/dcat-admin/dcat/plugins/select/select2.min.css');
        Admin::js('/vendor/dcat-admin/dcat/plugins/select/select2.min.js');
        Admin::script(<<<'JS'
            setTimeout(function () {
                if (window.jQuery && $.fn && $.fn.select2) {
                    $('.self-channel-payment-select').select2({
                        width: '100%',
                        minimumResultsForSearch: 0,
                        placeholder: '请选择支付方式',
                        allowClear: true
                    });
                }
            }, 0);
            JS);

        $paymentOptions = $this->paymentOptions();

        $paymentId = intval(request('payment_id', 0));
        if ($paymentId > 0 && !isset($paymentOptions[$paymentId])) {
            $paymentId = 0;
        }

        $paymentName = $paymentId > 0 ? ($paymentOptions[$paymentId] ?? '未知支付方式') : '请选择支付方式';
        $dispatchPreview = [
            'has_snapshot' => false,
            'source' => '',
            'matched' => false,
            'message' => '',
            'snapshot_time' => '',
            'order_id' => 0,
            'enabled_banks' => [],
            'candidate_banks' => [],
            'final_queue' => [],
        ];
        $bankStats = collect();

        if ($paymentId > 0) {
            $dispatchPreview = app(SelfChannelPayment::class)->getLastDispatchPanelSnapshot($paymentId);
            $bankStats = $this->todayBankStats($paymentId);
        }

        $content->title('自营面板');

        return $content->body(view('admin.SelfChannel.index', [
            'indexUrl' => $this->getIndexUrl(),
            'paymentOptions' => $paymentOptions,
            'paymentId' => $paymentId,
            'paymentSelected' => $paymentId > 0,
            'paymentName' => $paymentName,
            'hasSnapshot' => !empty($dispatchPreview['has_snapshot']),
            'sourceText' => $dispatchPreview['source'] ?: '未匹配到收款卡',
            'dispatchMatched' => !empty($dispatchPreview['matched']),
            'dispatchMessage' => (string) ($dispatchPreview['message'] ?? ''),
            'snapshotTime' => (string) ($dispatchPreview['snapshot_time'] ?? ''),
            'snapshotOrderId' => intval($dispatchPreview['order_id'] ?? 0),
            'enabledBankRows' => $this->enabledBankRows($dispatchPreview, $bankStats),
            'candidateBankRows' => $this->candidateBankRows($dispatchPreview, $bankStats),
            'realQueueRows' => $this->realQueueRows($dispatchPreview, $bankStats),
            'realCardCount' => count($dispatchPreview['final_queue'] ?? []),
        ]));
    }

    public function config(Content $content): Content
    {
        $content->title('自营配置');

        return $content->body(function (Row $row) {
            $row->column(12, new Card('配置', new ConfigForm()));
        });
    }

    protected function getIndexUrl(): string
    {
        return admin_url('selfchannels/index');
    }

    private function paymentOptions(): array
    {
        return collect(config('payment', []))->filter(function ($item) {
            return intval($item['id'] ?? 0) > 0;
        })->mapWithKeys(function ($item) {
            return [intval($item['id']) => $item['name']];
        })->toArray();
    }

    private function todayBankStats(int $paymentId): Collection
    {
        return DB::table('deposit_orders')
            ->selectRaw('user_bank_id, COUNT(*) as today_order_number, COALESCE(SUM(actual_amount), 0) as today_order_amount, MAX(success_time) as last_success_time')
            ->where('created_at', '>=', Carbon::today()->toDateTimeString())
            ->where('created_at', '<', Carbon::tomorrow()->toDateTimeString())
            ->where('status', 5)
            ->where('user_bank_id', '>', 0)
            ->where('payment_id', $paymentId)
            ->groupBy('user_bank_id')
            ->get()
            ->keyBy('user_bank_id');
    }

    private function enabledBankRows(array $dispatchPreview, Collection $bankStats): array
    {
        return collect($dispatchPreview['enabled_banks'] ?? [])->map(function ($bank) use ($bankStats) {
            $stat = $bankStats->get($bank['id']);

            return [
                'id' => intval($bank['id']),
                'user' => (string) ($bank['user'] ?: '-'),
                'name' => (string) $bank['name'],
                'round_times' => intval($bank['round_times'] ?? 1),
                'today_order_number' => intval($stat->today_order_number ?? 0),
                'today_order_amount' => bob_unit_format($stat->today_order_amount ?? 0),
                'last_success_time' => $this->formatLastSuccessTime($stat),
            ];
        })->toArray();
    }

    private function candidateBankRows(array $dispatchPreview, Collection $bankStats): array
    {
        return collect($dispatchPreview['candidate_banks'] ?? [])->map(function ($bank) use ($bankStats) {
            $stat = $bankStats->get($bank['id']);

            return [
                'id' => intval($bank['id']),
                'user' => (string) ($bank['user'] ?: '-'),
                'name' => (string) $bank['name'],
                'round_times' => intval($bank['round_times'] ?? 1),
                'pass_html' => $this->passHtml(!empty($bank['passed'])),
                'pass_reason' => (string) ($bank['pass_reason'] ?: '-'),
                'today_order_number' => intval($stat->today_order_number ?? 0),
                'today_order_amount' => bob_unit_format($stat->today_order_amount ?? 0),
                'last_success_time' => $this->formatLastSuccessTime($stat),
            ];
        })->toArray();
    }

    private function realQueueRows(array $dispatchPreview, Collection $bankStats): array
    {
        return collect($dispatchPreview['final_queue'] ?? [])->map(function ($node) use ($bankStats) {
            $stat = $bankStats->get($node['id']);

            return [
                'queue_index' => intval($node['queue_index']),
                'queue_state_html' => $this->queueStateHtml($node),
                'user' => (string) ($node['user'] ?: '-'),
                'name' => (string) $node['name'],
                'nid' => (string) $node['nid'],
                'round' => intval($node['round']),
                'priority' => intval($node['priority']),
                'today_order_number' => intval($stat->today_order_number ?? 0),
                'today_order_amount' => bob_unit_format($stat->today_order_amount ?? 0),
                'last_success_time' => $this->formatLastSuccessTime($stat),
            ];
        })->toArray();
    }

    private function formatLastSuccessTime($stat): string
    {
        return empty($stat?->last_success_time) ? '-' : date('Y-m-d H:i:s', (int) $stat->last_success_time);
    }

    private function passHtml(bool $passed): string
    {
        return $passed ? '<span style="color:#21b978;font-weight:700;">通过</span>' : '<span style="color:#ef5228;font-weight:700;">已过滤</span>';
    }

    private function queueStateHtml(array $node): string
    {
        if (!empty($node['is_current'])) {
            return '<span style="color:#586cb1;font-weight:700;">当前命中</span>';
        }

        if (!empty($node['is_next'])) {
            return '<span style="color:#21b978;font-weight:700;">下一张</span>';
        }

        return '<span style="color:#8c8c8c;">轮询中</span>';
    }
}
