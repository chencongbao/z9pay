<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use App\Extendtions\Translation\TransFileToOtherLanguage;

class TranslateCommandPathSafetyTest extends TestCase
{
    private string $langRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->langRoot = sys_get_temp_dir() . '/codex_lang_' . uniqid();
        File::makeDirectory($this->langRoot . '/zh_CN', 0755, true, true);
        File::put($this->langRoot . '/zh_CN/cashier.php', "<?php\n\nreturn ['hello' => '你好'];\n");
        app()->useLangPath($this->langRoot);
    }

    protected function tearDown(): void
    {
        if (isset($this->langRoot) && is_dir($this->langRoot)) {
            File::deleteDirectory($this->langRoot);
        }

        parent::tearDown();
    }

    public function test_translate_rejects_language_path_traversal_before_writing_files(): void
    {
        $escapeFile = dirname($this->langRoot) . '/somewhere/cashier.php';
        @unlink($escapeFile);

        $this->artisan('translate', ['--file' => ['cashier'], '--lang' => ['../../somewhere']])
            ->expectsOutputToContain('翻译失败：语言目录不合法')
            ->assertExitCode(Command::FAILURE);

        $this->assertFileDoesNotExist($escapeFile);
        $this->assertDirectoryDoesNotExist($this->langRoot . '/../../somewhere');
    }

    public function test_translate_rejects_missing_source_file_explicitly(): void
    {
        $this->artisan('translate', ['--file' => ['missing_file'], '--lang' => ['vi']])
            ->expectsOutputToContain('翻译失败：中文源语言文件不存在：missing_file')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_service_keeps_legal_language_write_inside_lang_path(): void
    {
        $service = new class extends TransFileToOtherLanguage {
            protected function transHandle(array $data, string $lang): array
            {
                return array_map(fn ($value) => $lang . ':' . $value, $data);
            }
        };

        $result = $service->excute(['cashier'], ['vi']);

        $this->assertSame(1, $result['files']);
        $this->assertSame(1, $result['languages']);
        $this->assertSame(1, $result['writes']);
        $this->assertFileExists($this->langRoot . '/vi/cashier.php');
        $this->assertSame(['hello' => 'vi:你好'], include $this->langRoot . '/vi/cashier.php');
    }
}
