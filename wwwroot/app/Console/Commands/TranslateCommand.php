<?php

namespace App\Console\Commands;

use Throwable;
use Illuminate\Console\Command;
use App\Extendtions\Translation\TransFileToOtherLanguage;

class TranslateCommand extends Command
{
    protected $signature = 'translate {--file=* : 指定语言文件名，不需要.php后缀} {--lang=* : 指定目标语言目录}';

    protected $description = '翻译系统多语言';

    public function handle(): int
    {
        try {
            $result = app(TransFileToOtherLanguage::class)->excute(
                array_filter((array)$this->option('file')),
                array_filter((array)$this->option('lang')),
            );

            $this->info(sprintf('翻译完成：文件 %d 个，语言 %d 个，写入 %d 个。', $result['files'], $result['languages'], $result['writes']));
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('翻译失败：' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
