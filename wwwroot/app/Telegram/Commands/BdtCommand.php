<?php

namespace App\Telegram\Commands;

class BdtCommand extends AbstractCurrencyStatsCommand
{
    protected string $name = 'bdt';
    protected string $description = '查询 BDT 统计';

    protected function currencyCode(): string
    {
        return 'BDT';
    }
}
