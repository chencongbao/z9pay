<?php

namespace App\Telegram\Commands;

class PhpCommand extends AbstractCurrencyStatsCommand
{
    protected string $name = 'php';
    protected string $description = '查询 PHP 统计';

    protected function currencyCode(): string
    {
        return 'PHP';
    }
}
