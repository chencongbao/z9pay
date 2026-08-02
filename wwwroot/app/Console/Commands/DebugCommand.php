<?php

namespace App\Console\Commands;


use Chencongbao\Foundation\Facades\FoundationTelegram;
use Illuminate\Console\Command;


class DebugCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'debug';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';


    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        FoundationTelegram::withToken("7730345868:AAF3J0wD7NXJP0yJL58vagJHl1YrQKDh1bE")->to("-4253598146")->sendPhoto("https://www.phelotto.com/storage/images/b9200b9fccfef19e9b6bc65359312588.jpg","TEST20260727164003422013321\n请查询订单");
    }
}
