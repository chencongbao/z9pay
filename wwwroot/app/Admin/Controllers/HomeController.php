<?php

namespace App\Admin\Controllers;

use Dcat\Admin\Admin;
use App\Models\User;
use App\Models\UserBank;
use App\Models\FreezeOrder;
use Dcat\Admin\Widgets\Box;
use Illuminate\Support\Str;
use App\Models\DepositOrder;
use App\Models\MerchantInfo;
use Dcat\Admin\Widgets\Card;
use Dcat\Admin\Widgets\Form;
use App\Models\TransferOrder;
use Dcat\Admin\Layout\Content;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Admin\Forms\Home\TelegramQunSend;
use App\Admin\Forms\Home\TestDepositForm;
use App\Admin\Forms\Home\TestTransferForm;
use App\Extendtions\Dcat\Widgets\BobTable;

class HomeController extends CommonController
{
    private const DASHBOARD_CACHE_SECONDS = 10;

    protected $disableEdit = true;

    protected $disableCreate = true;

    protected $translation = 'admin-home';

    public function index(Content $content)
    {
        if (! config('iframe_tab.enable')) {
            return $this->dashboard($content);
        }

        if (! request()->filled('open')) {
            request()->merge([
                'open' => admin_route('home.dashboard'),
                'open_title' => '系统首页',
            ]);
        }

        return $content->view('iframe-tab::content');
    }

    public function dashboard(Content $content)
    {
        return $content
            ->title('运营总览')
            ->description('今日运营概览')
            ->body(view('admin.home.dashboard', $this->dashboardViewData()));
    }

    private function dashboardViewData(): array
    {
        $today = now();
        $todayStart = $today->copy()->startOfDay();
        $todayEnd = $today->copy()->endOfDay();

        return Cache::remember($this->dashboardCacheKey($todayStart), now()->addSeconds(self::DASHBOARD_CACHE_SECONDS), function () use ($todayStart, $todayEnd) {
            return $this->freshDashboardViewData($todayStart, $todayEnd);
        });
    }

    public function warmDashboardCache(): void
    {
        $today = now();
        $todayStart = $today->copy()->startOfDay();
        $cacheKey = $this->dashboardCacheKey($todayStart);
        if (Cache::has($cacheKey)) {
            return;
        }

        Cache::put($cacheKey, $this->freshDashboardViewData($todayStart, $today->copy()->endOfDay()), now()->addSeconds(self::DASHBOARD_CACHE_SECONDS));
    }

    private function dashboardCacheKey($todayStart): string
    {
        return 'admin_home_dashboard_' . $todayStart->format('Ymd');
    }

    private function freshDashboardViewData($todayStart, $todayEnd): array
    {
        // 首页允许 10 秒短缓存；缓存失效时用聚合 SQL 降低订单表重复扫描。
        $depositSummary = $this->depositTodaySummary($todayStart, $todayEnd);
        $transferSummary = $this->transferTodaySummary($todayStart, $todayEnd);
        $freezeSummary = FreezeOrder::query()
            ->where('status', 1)
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw('COALESCE(SUM(amount), 0) as total_amount')
            ->first();
        $merchantAmount = MerchantInfo::query()
            ->selectRaw('COUNT(*) as merchant_count')
            ->selectRaw('COALESCE(SUM(balance_amount), 0) as balance_amount')
            ->selectRaw('COALESCE(SUM(available_balance), 0) as available_balance')
            ->selectRaw('COALESCE(SUM(freeze_amount), 0) as freeze_amount')
            ->selectRaw('COALESCE(SUM(settlement_amount), 0) as settlement_amount')
            ->first();
        $userAcquisitionStatusCounts = User::query()->where('is_agent', 0)->select('acquisition_status', DB::raw('COUNT(*) as total'))->groupBy('acquisition_status')->pluck('total', 'acquisition_status')->all();
        $userBankStatusCounts = UserBank::query()->select('collection_status', DB::raw('COUNT(*) as total'))->groupBy('collection_status')->pluck('total', 'collection_status')->all();
        $todayDepositProfit = $depositSummary->success_profit ?? 0;
        $todayTransferProfit = $transferSummary->success_profit ?? 0;
        $depositSuccessRate = $this->percent($depositSummary->success_count ?? 0, $depositSummary->total_count ?? 0);
        $transferSuccessRate = $this->percent($transferSummary->success_count ?? 0, $transferSummary->total_count ?? 0);
        $merchantBalanceAmount = optional($merchantAmount)->balance_amount ?? 0;
        $merchantAvailableAmount = optional($merchantAmount)->available_balance ?? 0;
        $merchantFreezeAmount = optional($merchantAmount)->freeze_amount ?? 0;
        $merchantSettlementAmount = optional($merchantAmount)->settlement_amount ?? 0;
        $userAcquisitionEnabledCount = (int) ($userAcquisitionStatusCounts[1] ?? 0);
        $userAcquisitionClosedCount = (int) ($userAcquisitionStatusCounts[0] ?? 0);
        $userBankEnabledCount = (int) ($userBankStatusCounts[1] ?? 0);
        $userBankClosedCount = (int) ($userBankStatusCounts[0] ?? 0);

        return [
            'todayText' => $todayStart->format('Y-m-d'),
            'statCards' => [
                [
                    'title' => '今日代收成功金额',
                    'value' => bob_unit_format($depositSummary->success_amount ?? 0),
                    'sub' => '成功率 ' . $depositSuccessRate . '% · 成功 ' . number_format($depositSummary->success_count ?? 0) . ' / 总 ' . number_format($depositSummary->total_count ?? 0) . ' 单',
                    'iconText' => '收',
                    'color' => '#21b978',
                ],
                [
                    'title' => '今日代付成功金额',
                    'value' => bob_unit_format($transferSummary->success_amount ?? 0),
                    'sub' => '成功率 ' . $transferSuccessRate . '% · 成功 ' . number_format($transferSummary->success_count ?? 0) . ' / 总 ' . number_format($transferSummary->total_count ?? 0) . ' 单',
                    'iconText' => '付',
                    'color' => '#5368a6',
                ],
                [
                    'title' => '今日系统利润',
                    'value' => bob_unit_format($todayDepositProfit + $todayTransferProfit),
                    'sub' => '代收 ' . bob_unit_format($todayDepositProfit) . ' / 代付 ' . bob_unit_format($todayTransferProfit),
                    'iconText' => '利',
                    'color' => '#f59e0b',
                ],
                [
                    'title' => '商户可用余额',
                    'value' => bob_unit_format($merchantAvailableAmount),
                    'sub' => '占总余额 ' . $this->percent($merchantAvailableAmount, $merchantBalanceAmount) . '% · 总额 ' . bob_unit_format($merchantBalanceAmount),
                    'iconText' => '¥',
                    'color' => '#12a594',
                ],
            ],
            'alerts' => [
                ['name' => '待处理代付', 'count' => $transferSummary->pending_count ?? 0, 'level' => $this->alertLevel($transferSummary->pending_count ?? 0, 'warning')],
                ['name' => '今日代收失败', 'count' => $depositSummary->fail_count ?? 0, 'level' => $this->alertLevel($depositSummary->fail_count ?? 0, 'danger')],
                ['name' => '今日代付失败', 'count' => $transferSummary->fail_count ?? 0, 'level' => $this->alertLevel($transferSummary->fail_count ?? 0, 'danger')],
                ['name' => '冻结订单', 'count' => $freezeSummary->total_count ?? 0, 'level' => $this->alertLevel($freezeSummary->total_count ?? 0, 'warning')],
            ],
            'amounts' => [
                ['name' => '商户总余额', 'value' => bob_unit_format($merchantBalanceAmount), 'percent' => 100, 'level' => 'primary'],
                ['name' => '商户可用余额', 'value' => bob_unit_format($merchantAvailableAmount), 'percent' => $this->percent($merchantAvailableAmount, $merchantBalanceAmount), 'level' => 'success'],
                ['name' => '商户冻结余额', 'value' => bob_unit_format($merchantFreezeAmount), 'percent' => $this->percent($merchantFreezeAmount, $merchantBalanceAmount), 'level' => 'warning'],
                ['name' => '商户结算中余额', 'value' => bob_unit_format($merchantSettlementAmount), 'percent' => $this->percent($merchantSettlementAmount, $merchantBalanceAmount), 'level' => 'info'],
                ['name' => '冻结订单金额', 'value' => bob_unit_format($freezeSummary->total_amount ?? 0), 'percent' => $this->percent($freezeSummary->total_amount ?? 0, $merchantBalanceAmount), 'level' => 'danger'],
            ],
            'statusItems' => [
                ['name' => '商户', 'value' => number_format($merchantAmount->merchant_count ?? 0), 'sub' => '账户总数', 'level' => 'success'],
                ['name' => '金主收款开关统计', 'value' => number_format($userAcquisitionEnabledCount) . ' / ' . number_format($userAcquisitionClosedCount), 'sub' => '开启 / 关闭', 'level' => $userAcquisitionClosedCount > 0 ? 'warning' : 'success'],
                ['name' => '收款卡', 'value' => number_format($userBankEnabledCount) . ' / ' . number_format($userBankClosedCount), 'sub' => '启用 / 关闭', 'level' => $userBankClosedCount > 0 ? 'warning' : 'success'],
            ],
            'orderStatusGroups' => [
                ['title' => '今日代收状态', 'iconText' => '收', 'color' => '#21b978', 'rate' => $depositSuccessRate, 'items' => $this->orderStatusCounts($depositSummary, 'deposite_status')],
                ['title' => '今日代付状态', 'iconText' => '付', 'color' => '#5368a6', 'rate' => $transferSuccessRate, 'items' => $this->orderStatusCounts($transferSummary, 'transfer_status')],
            ],
        ];
    }

    private function percent($value, $total): string
    {
        $value = floatval($value);
        $total = floatval($total);
        if ($total <= 0) {
            return '0';
        }

        return rtrim(rtrim(number_format($value / $total * 100, 2, '.', ''), '0'), '.');
    }

    private function alertLevel($count, string $activeLevel = 'warning'): string
    {
        $count = intval($count);
        if ($count <= 0) {
            return 'success';
        }
        if ($count > 10) {
            return 'danger';
        }

        return $activeLevel;
    }

    private function depositTodaySummary($todayStart, $todayEnd)
    {
        $query = DepositOrder::query()
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN status = 5 THEN 1 ELSE 0 END), 0) as success_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN status = 5 THEN actual_amount ELSE 0 END), 0) as success_amount')
            ->selectRaw('COALESCE(SUM(CASE WHEN status = 5 THEN profit ELSE 0 END), 0) as success_profit')
            ->selectRaw('COALESCE(SUM(CASE WHEN status = 6 THEN 1 ELSE 0 END), 0) as fail_count');

        return $this->appendStatusCountSelects($query, config('default.deposite_status', []))->first();
    }

    private function transferTodaySummary($todayStart, $todayEnd)
    {
        $query = TransferOrder::query()
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN status = 4 THEN 1 ELSE 0 END), 0) as success_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN status = 4 THEN actual_amount ELSE 0 END), 0) as success_amount')
            ->selectRaw('COALESCE(SUM(CASE WHEN type = 0 AND status = 4 THEN profit ELSE 0 END), 0) as success_profit')
            ->selectRaw('COALESCE(SUM(CASE WHEN status = 3 THEN 1 ELSE 0 END), 0) as pending_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN status = 5 THEN 1 ELSE 0 END), 0) as fail_count');

        return $this->appendStatusCountSelects($query, config('default.transfer_status', []))->first();
    }

    private function appendStatusCountSelects($query, array $statuses)
    {
        foreach (array_keys($statuses) as $status) {
            $status = (int) $status;
            $query->selectRaw("COALESCE(SUM(CASE WHEN status = {$status} THEN 1 ELSE 0 END), 0) as status_{$status}");
        }

        return $query;
    }

    private function orderStatusCounts($summary, string $configKey): array
    {
        return collect(config("default.{$configKey}", []))->map(function ($name, $status) use ($summary) {
            $key = 'status_' . (int) $status;

            return [
                'name' => $name,
                'value' => (int) ($summary->{$key} ?? 0),
            ];
        })->values()->all();
    }

    public function apiDepositTest(Content $content)
    {
        $content->title(admin_trans_label('collect_test'));

        return $content->body(new Card('', new TestDepositForm()));
    }

    public function apiTransferTest(Content $content)
    {
        $content->title(admin_trans_label('payout_test'));

        return $content->body(new Card('', new TestTransferForm()));
    }

    public function payment(Content $content)
    {
        $url = Admin::app()->getRoute('home.payment');
        Admin::script(
            <<<JS
            Dcat.ready(function () {
                $(document).off('click', 'button[type=reset]').on('click', 'button[type=reset]', function () {
                    window.location.href = "{$url}";
                });
            });
JS

        );

        $name = request()->get('name');
        $code = request()->get('code');

        $form = new Form();
        $form->text('name', admin_trans_label('name'))->default($name);
        $form->text('code', admin_trans_label('code'))->default($code);
        $form->method('get');
        $form->ajax(false);
        $form->action($url);
        $content->row(Card::make(admin_trans_label('search'), $form));

        $payments = collect(config('payment', []))
            ->when($name, function ($items) use ($name) {
                return $items->filter(function ($item) use ($name) {
                    return Str::contains($item['name'] ?? '', $name, true);
                });
            })
            ->when($code, function ($items) use ($code) {
                return $items->filter(function ($item) use ($code) {
                    return Str::contains($item['code'] ?? '', $code, true);
                });
            })
            ->map(function ($item) {
                $paymentName = e((string) ($item['name'] ?? ''));
                $paymentCode = e((string) ($item['code'] ?? ''));

                return $paymentName . ' => <span class="text-danger">' . $paymentCode . '</span>';
            })
            ->values()
            ->chunk(4)
            ->map(function ($row) {
                return $row->pad(4, '')->all();
            })
            ->all();

        $table = new BobTable([], $payments);
        $table->withBorder();
        $content->row(Box::make(admin_trans_label('channel_code_list'), $table));

        return $content->title(admin_trans_label('channel_code'));
    }

    public function telegramQunSend(Content $content)
    {
        $content->title(admin_trans_label('telegram_broadcast'));

        return $content->body(new Card('', new TelegramQunSend()));
    }
}
