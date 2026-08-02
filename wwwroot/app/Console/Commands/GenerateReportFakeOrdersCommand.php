<?php

namespace App\Console\Commands;

use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GenerateReportFakeOrdersCommand extends Command
{
    private const MAX_COUNT = 500000;
    private const MAX_CHUNK_SIZE = 10000;

    protected $signature = 'report:fake-orders
        {--count=500000 : 生成订单总数}
        {--date= : 订单日期，默认今天}
        {--mid=24 : 商户ID，多个用英文逗号分隔}
        {--type=all : 生成类型：all/deposit/transfer/settlement}
        {--chunk=800 : 每批插入数量}
        {--clean : 清理指定日期测试数据}
        {--dry-run : 只显示计划，不写入数据库}
        {--force : 允许在 production 环境执行}';

    protected $description = '生成本地报表压测订单数据';

    private string $date;

    private array $mids = [];

    private int $chunkSize;

    private string $prefix = 'RPT_TEST';

    public function handle(): int
    {
        $date = $this->parseDateOption();
        $mids = $this->parseMids((string)$this->option('mid'));
        $chunkSize = $this->parsePositiveIntegerOption('chunk', self::MAX_CHUNK_SIZE);
        $type = strtolower((string)$this->option('type'));
        $count = $this->parsePositiveIntegerOption('count', self::MAX_COUNT);

        if ($date === '' || empty($mids) || $chunkSize === null || $count === null) {
            return self::FAILURE;
        }

        $this->date = $date;
        $this->mids = $mids;
        $this->chunkSize = $chunkSize;

        if (app()->environment('production') && !$this->option('force')) {
            $this->error('当前是 production 环境，禁止生成压测数据。如确认要执行，请加 --force。');
            return self::FAILURE;
        }

        if (!in_array($type, ['all', 'deposit', 'transfer', 'settlement'], true)) {
            $this->error('type 只支持 all/deposit/transfer/settlement');
            return self::FAILURE;
        }

        if ($this->option('clean')) {
            return $this->clean($type);
        }

        $plan = $this->makePlan($type, $count);
        $this->showPlan($plan);

        if ($this->option('dry-run')) {
            return 0;
        }

        $this->warnInactiveMerchants();
        $start = microtime(true);

        foreach ($plan as $item) {
            $this->generate($item['type'], $item['count']);
        }

        $seconds = round(microtime(true) - $start, 2);
        $this->info("压测数据生成完成，耗时 {$seconds} 秒。");
        return 0;
    }

    private function makePlan(string $type, int $count): array
    {
        if ($type !== 'all') {
            return [['type' => $type, 'count' => $count]];
        }

        $depositCount = (int)floor($count * 0.5);
        $transferCount = (int)floor($count * 0.25);
        $settlementCount = $count - $depositCount - $transferCount;

        return [
            ['type' => 'deposit', 'count' => $depositCount],
            ['type' => 'transfer', 'count' => $transferCount],
            ['type' => 'settlement', 'count' => $settlementCount],
        ];
    }

    private function showPlan(array $plan): void
    {
        $this->line('压测日期：' . $this->date);
        $this->line('商户ID：' . implode(',', $this->mids));
        $this->line('每批写入：' . $this->chunkSize);

        foreach ($plan as $item) {
            $this->line("生成 {$item['type']}：{$item['count']} 条");
        }
    }

    private function generate(string $type, int $count): void
    {
        if ($count <= 0) {
            return;
        }

        $bar = $this->output->createProgressBar($count);
        $bar->setFormat("%message% %current%/%max% [%bar%] %percent:3s%%");
        $bar->setMessage("正在生成 {$type}");
        $bar->start();

        $rows = [];
        $baseIndex = $this->nextBaseIndex($type);
        for ($i = 1; $i <= $count; $i++) {
            $rows[] = $type === 'deposit' ? $this->depositRow($baseIndex + $i) : $this->transferRow($baseIndex + $i, $type === 'settlement');

            if (count($rows) >= $this->chunkSize) {
                $this->insertRows($type, $rows);
                $bar->advance(count($rows));
                $rows = [];
            }
        }

        if (!empty($rows)) {
            $this->insertRows($type, $rows);
            $bar->advance(count($rows));
        }

        $bar->finish();
        $this->newLine();
    }

    private function insertRows(string $type, array $rows): void
    {
        $table = $type === 'deposit' ? 'deposit_orders' : 'transfer_orders';
        $columns = Schema::getColumnListing($table);

        $rows = array_map(function ($row) use ($columns) {
            return array_intersect_key($row, array_flip($columns));
        }, $rows);

        // MySQL 单条 prepared statement 有参数上限，按字段数自动拆小批避免 1390 错误。
        $columnCount = max(1, count($rows[0] ?? []));
        $safeChunkSize = max(1, min($this->chunkSize, (int)floor(60000 / $columnCount)));
        foreach (array_chunk($rows, $safeChunkSize) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    private function depositRow(int $index): array
    {
        $amount = $this->amount($index);
        $status = [5, 5, 5, 5, 6, 4, 2, 1][$index % 8];
        $actualAmount = $status === 5 ? $amount : 0;
        $merchantFee = $status === 5 ? round($actualAmount * 0.01, 2) : 0;
        $userCommission = $status === 5 ? round($actualAmount * 0.004, 2) : 0;
        $createdAt = $this->createdAt($index);

        return $this->baseOrderRow($index, 'D', $status, $amount, $actualAmount, $merchantFee, $userCommission, $createdAt) + [
            'payment_id' => ($index % 5) + 1,
            'pay_amount' => $amount,
            'pay_status' => $status === 5 ? 1 : 0,
            'order_type' => 1,
            'bank_id' => ($index % 20) + 1,
            'collection_name' => '测试收款人' . ($index % 100),
            'collection_card_no' => '622200' . str_pad((string)($index % 1000000), 12, '0', STR_PAD_LEFT),
            'collection_bank_code' => 'BANK' . (($index % 20) + 1),
            'collection_bank_name' => '测试银行' . (($index % 20) + 1),
        ];
    }

    private function transferRow(int $index, bool $settlement): array
    {
        $amount = $this->amount($index);
        $status = [4, 4, 4, 4, 5, 1][$index % 6];
        $actualAmount = $status === 4 ? $amount : 0;
        $merchantFee = $status === 4 ? round($actualAmount * 0.012, 2) : 0;
        $userCommission = $status === 4 ? round($actualAmount * 0.003, 2) : 0;
        $createdAt = $this->createdAt($index);
        $prefix = $settlement ? 'S' : 'T';

        return $this->baseOrderRow($index, $prefix, $status, $amount, $actualAmount, $merchantFee, $userCommission, $createdAt) + [
            'type' => $settlement ? 1 : 0,
            'bank_id' => ($index % 20) + 1,
            'bank_code' => 'BANK' . (($index % 20) + 1),
            'bank_name' => '测试银行' . (($index % 20) + 1),
            'card_no' => '622288' . str_pad((string)($index % 1000000), 12, '0', STR_PAD_LEFT),
            'holder_name' => '测试付款人' . ($index % 100),
        ];
    }

    private function baseOrderRow(int $index, string $prefix, int $status, float $amount, float $actualAmount, float $merchantFee, float $userCommission, string $createdAt): array
    {
        $mid = $this->mids[$index % count($this->mids)];
        $merchantAgent1Commission = round($merchantFee * 0.2, 2);
        $merchantAgent2Commission = round($merchantFee * 0.1, 2);
        $merchantAgent3Commission = round($merchantFee * 0.05, 2);
        $userAgent1Commission = round($userCommission * 0.2, 2);
        $userAgent2Commission = round($userCommission * 0.1, 2);
        $userAgent3Commission = round($userCommission * 0.05, 2);

        return [
            'mid' => $mid,
            'amount' => $amount,
            'actual_amount' => $actualAmount,
            'time' => (string)strtotime($createdAt),
            'currency_id' => ($index % 2) + 1,
            'order_no' => "{$this->prefix}_{$prefix}_{$this->date}_{$index}",
            'ip' => '127.0.0.1',
            'true_ip' => '127.0.0.1',
            'ordernumber' => $prefix . 'TEST' . date('Ymd', strtotime($this->date)) . str_pad((string)$index, 12, '0', STR_PAD_LEFT),
            'uid' => 'test_user_' . ($index % 10000),
            'notify_url' => 'https://local.test/notify',
            'status' => $status,
            'callback_count' => $status === 5 || $status === 4 ? 1 : 0,
            'callback_time' => $status === 5 || $status === 4 ? strtotime($createdAt) : 0,
            'success_time' => $status === 5 || $status === 4 ? strtotime($createdAt) : 0,
            'remark' => '报表本地压测数据',
            'merchant_rate' => 1,
            'merchant_fee' => $merchantFee,
            'merchant_extra_fee' => 0,
            'merchant_agent1_id' => 1001,
            'merchant_agent2_id' => 1002,
            'merchant_agent3_id' => 1003,
            'merchant_agent1_rate' => 0.2,
            'merchant_agent2_rate' => 0.1,
            'merchant_agent3_rate' => 0.05,
            'merchant_agent1_commission' => $merchantAgent1Commission,
            'merchant_agent2_commission' => $merchantAgent2Commission,
            'merchant_agent3_commission' => $merchantAgent3Commission,
            'user_id' => ($index % 2000) + 1,
            'user_rate' => 0.4,
            'user_commission' => $userCommission,
            'user_agent1_id' => 2001,
            'user_agent2_id' => 2002,
            'user_agent3_id' => 2003,
            'user_agent4_id' => 2004,
            'user_agent5_id' => 2005,
            'user_agent1_rate' => 0.2,
            'user_agent2_rate' => 0.1,
            'user_agent3_rate' => 0.05,
            'user_agent4_rate' => 0.03,
            'user_agent5_rate' => 0.02,
            'user_agent1_commission' => $userAgent1Commission,
            'user_agent2_commission' => $userAgent2Commission,
            'user_agent3_commission' => $userAgent3Commission,
            'user_agent4_commission' => round($userCommission * 0.03, 2),
            'user_agent5_commission' => round($userCommission * 0.02, 2),
            'channel_id' => ($index % 12) + 1,
            'channel_account_id' => ($index % 30) + 1,
            'user_bank_id' => ($index % 500) + 1,
            'channel_rate' => 0.3,
            'channel_cost' => round($actualAmount * 0.003, 2),
            'profit' => max(0, round($merchantFee - $userCommission, 2)),
            'hour' => (int)date('H', strtotime($createdAt)),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }

    private function amount(int $index): float
    {
        return (float)(100 + ($index % 9000));
    }

    private function createdAt(int $index): string
    {
        $seconds = $index % 86400;
        return date('Y-m-d H:i:s', strtotime($this->date . ' 00:00:00') + $seconds);
    }

    private function nextBaseIndex(string $type): int
    {
        $table = $type === 'deposit' ? 'deposit_orders' : 'transfer_orders';
        $prefix = $type === 'deposit' ? 'D' : ($type === 'settlement' ? 'S' : 'T');

        return (int)DB::table($table)->where('order_no', 'like', "{$this->prefix}_{$prefix}_{$this->date}_%")->count();
    }

    private function parseMids(string $midOption): array
    {
        $mids = [];

        foreach (explode(',', $midOption) as $mid) {
            $mid = trim($mid);
            if ($mid === '' || !ctype_digit($mid) || (int)$mid <= 0) {
                $this->error('mid 必须是正整数，多个商户用英文逗号分隔。');
                return [];
            }

            $mids[] = (int)$mid;
        }

        return $mids;
    }

    private function parseDateOption(): string
    {
        $date = (string)($this->option('date') ?: date('Y-m-d'));
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            $this->error('date 必须是真实日期，格式为 YYYY-MM-DD。');
            return '';
        }

        return $date;
    }

    private function parsePositiveIntegerOption(string $name, int $max): ?int
    {
        $value = trim((string)$this->option($name));

        if ($value === '' || !ctype_digit($value) || (int)$value <= 0) {
            $this->error($name . ' 必须是正整数。');
            return null;
        }

        $value = (int)$value;
        if ($value > $max) {
            $this->error($name . ' 不能超过 ' . $max . '。');
            return null;
        }

        return $value;
    }

    private function warnInactiveMerchants(): void
    {
        $merchantUsersTable = config('merchant-admin.database.users_table', 'merchant_users');
        $activeMids = DB::table($merchantUsersTable)->whereIn('id', $this->mids)->where('status', 1)->pluck('id')->map(fn ($id) => (int)$id)->all();
        $inactiveMids = array_diff($this->mids, $activeMids);

        if (!empty($inactiveMids)) {
            $this->warn('这些商户不存在或未启用，报表统计会忽略它们：' . implode(',', $inactiveMids));
        }
    }

    private function clean(string $type): int
    {
        if ($this->option('dry-run')) {
            $this->line('dry-run：不会删除数据。');
            return 0;
        }

        $types = $type === 'all' ? ['deposit', 'transfer', 'settlement'] : [$type];
        foreach ($types as $item) {
            $table = $item === 'deposit' ? 'deposit_orders' : 'transfer_orders';
            $prefix = $item === 'deposit' ? 'D' : ($item === 'settlement' ? 'S' : 'T');
            $deleted = DB::table($table)->where('order_no', 'like', "{$this->prefix}_{$prefix}_{$this->date}_%")->delete();
            $this->info("已清理 {$item} 测试数据：{$deleted} 条");
        }

        return 0;
    }
}
