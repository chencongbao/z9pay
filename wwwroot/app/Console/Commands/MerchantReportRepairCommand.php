<?php

namespace App\Console\Commands;

use DateTime;
use App\Models\MerchantInfo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use App\Services\Report\ReportPendingDateService;

class MerchantReportRepairCommand extends Command
{
    protected $signature = 'report:repair-merchant {mid=0 : 商户ID} {date=0 : 统计日期，格式 YYYY-MM-DD}';

    protected $description = '修复指定商户指定日期的代收、代付报表统计';

    public function handle(): int
    {
        $merchantId = $this->positiveIntegerArgument('mid', '商户ID');
        $date = strval($this->argument('date'));

        if ($merchantId === null) {
            return 1;
        }

        if ($date === '' || $date === '0') {
            $this->error('请输入统计日期，格式：YYYY-MM-DD');
            return 1;
        }

        if (!$this->isValidDate($date)) {
            $this->error('输入的日期格式不正确，请使用 YYYY-MM-DD 格式');
            return 1;
        }

        if (!MerchantInfo::query()->where('merchant_user_id', $merchantId)->exists()) {
            $this->error('商户不存在');
            return 1;
        }

        App::make(ReportPendingDateService::class)->addDates([$date]);
        $this->info("商户报表已加入待重建队列，商户ID：{$merchantId}，日期：{$date}");

        return 0;
    }

    private function isValidDate(string $date): bool
    {
        $dateObj = DateTime::createFromFormat('Y-m-d', $date);

        return $dateObj && $dateObj->format('Y-m-d') === $date;
    }

    private function positiveIntegerArgument(string $name, string $label): ?int
    {
        $value = trim((string)$this->argument($name));
        if (preg_match('/^[1-9]\d*$/', $value) !== 1) {
            $this->error("请输入正确的{$label}");
            return null;
        }

        return (int)$value;
    }
}
