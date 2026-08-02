<?php

namespace App\Telegram\Commands;

class RubCommand extends AbstractCurrencyStatsCommand
{
    protected string $name = 'rub';
    protected string $description = '查询 RUB 统计';

    protected function currencyCode(): string
    {
        return 'RUB';
    }
}
