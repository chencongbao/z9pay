<?php

namespace App\Extendtions\Translation;

use SplFileInfo;
use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\File;

class TransFileToOtherLanguage
{
    use ServiceTraits;

    private array $zhFiles = [];

    private array $languages = [];

    private array $needFiles = ['cashier'];

    private GoogleTran $google;

    public function excute(array $needFiles = [], array $languages = []): array
    {
        if (!empty($needFiles)) {
            $this->needFiles = $this->normalizeFileNames($needFiles);
        }

        $this->assertSourceFilesExist();
        return $this->runs($languages);
    }

    protected function loadAllZhFiles(): void
    {
        $directory = lang_path('zh_CN');
        $this->zhFiles = array_filter(File::allFiles($directory), function ($file) {
            return in_array($file->getBasename('.php'), $this->needFiles);
        });
    }

    protected function runs(array $languages = []): array
    {
        $writes = 0;
        $this->loadAllZhFiles();
        $this->languages = empty($languages) ? $this->targetLanguages() : $this->normalizeLanguages($languages);
        $this->google = new GoogleTran();

        foreach ($this->zhFiles as $file) {
            foreach ($this->languages as $lang) {
                if ($this->translateFile($file, $lang)) {
                    $writes++;
                }
            }
        }

        return [
            'files' => count($this->zhFiles),
            'languages' => count($this->languages),
            'writes' => $writes,
        ];
    }

    protected function translateFile(SplFileInfo $file, string $lang): bool
    {
        $basePath = lang_path();
        $targetDir = $this->safeTargetDirectory($basePath, $lang);
        $targetFile = $this->safeTargetFile($targetDir, $file->getBasename());

        $zhContent = $this->readFileArrayContent($file->getPathname());
        if (empty($zhContent)) {
            return false;
        }

        if (!File::exists($targetFile)) {
            $this->createDirectory($targetDir);
            $this->writeContentToFile($this->sortBySourceKeys($zhContent, $this->transHandle($zhContent, $lang)), $targetFile);
            return true;
        }

        $langContent = $this->readFileArrayContent($targetFile);
        $missingKeys = array_diff_key($zhContent, $langContent);
        $hasExtraKeys = !empty(array_diff_key($langContent, $zhContent));

        if (empty($missingKeys) && !$hasExtraKeys) {
            return false;
        }

        // 只翻译缺少的键，已有翻译保留，多余键跟随中文源文件清理。
        $translatedMissing = empty($missingKeys) ? [] : $this->transHandle($missingKeys, $lang);
        $this->writeContentToFile($this->sortBySourceKeys($zhContent, $langContent + $translatedMissing), $targetFile);

        return true;
    }

    private function createDirectory(string $directory): void
    {
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true, true);
        }
    }

    public function writeContentToFile(array $data = [], string $targetPath = ""): int|bool
    {
        $content = var_export($data, true);
        $translatedContent = "<?php\n\nreturn {$content};\n";

        return File::put($targetPath, $translatedContent);
    }

    protected function transHandle(array $data, string $lang): array
    {
        $translated = [];

        // 分批翻译，避免单次请求文本过多导致第三方接口失败。
        foreach (array_chunk($data, 80, true) as $chunk) {
            $result = $this->getClient($lang)->translate(array_values($chunk), $lang);
            foreach (array_keys($chunk) as $index => $key) {
                $translated[$key] = $result[$index] ?? '';
            }
        }

        return $translated;
    }

    private function getClient(string $lang): GoogleTran
    {
        return $this->google;
    }

    protected function readFileArrayContent(string $path = ""): array
    {
        if (!File::exists($path)) {
            return [];
        }

        $content = (static function ($file) {
            return include $file;
        })($path);

        return is_array($content) ? $content : [];
    }

    private function sortBySourceKeys(array $source, array $translated): array
    {
        $final = [];
        foreach ($source as $key => $_) {
            $final[$key] = array_key_exists($key, $translated) ? $translated[$key] : '';
        }

        return $final;
    }

    private function targetLanguages(): array
    {
        return collect(config('default.currency', []))
            ->where('status', 1)
            ->pluck('lang')
            ->filter()
            ->filter(fn ($lang) => $this->isSafeLanguage((string)$lang))
            ->unique()
            ->reject(fn ($lang) => in_array($lang, ['zh_CN', 'en'], true))
            ->values()
            ->all();
    }

    private function normalizeFileNames(array $files): array
    {
        $names = collect($files)
            ->map(fn ($file) => basename((string)$file, '.php'))
            ->filter(fn ($file) => preg_match('/^[A-Za-z0-9_-]{1,80}$/', $file) === 1)
            ->unique()
            ->values()
            ->all();

        if (count($names) !== count(array_filter($files))) {
            throw new \InvalidArgumentException('语言文件名不合法');
        }

        return $names;
    }

    private function normalizeLanguages(array $languages): array
    {
        $items = collect($languages)
            ->map(fn ($lang) => trim((string)$lang))
            ->filter()
            ->values();

        $invalid = $items->first(fn ($lang) => !$this->isSafeLanguage($lang));
        if ($invalid !== null) {
            throw new \InvalidArgumentException('语言目录不合法');
        }

        return $items
            ->reject(fn ($lang) => in_array($lang, ['zh_CN', 'en'], true))
            ->unique()
            ->values()
            ->all();
    }

    private function assertSourceFilesExist(): void
    {
        foreach ($this->needFiles as $file) {
            if (!File::exists(lang_path('zh_CN/' . $file . '.php'))) {
                throw new \InvalidArgumentException("中文源语言文件不存在：{$file}");
            }
        }
    }

    private function isSafeLanguage(string $lang): bool
    {
        if ($lang === '' || str_contains($lang, '/') || str_contains($lang, '\\') || str_contains($lang, '..') || preg_match('/[[:cntrl:]\s]/', $lang)) {
            return false;
        }

        return preg_match('/^[A-Za-z]{2,3}(?:[_-][A-Za-z]{2})?$/', $lang) === 1;
    }

    private function safeTargetDirectory(string $basePath, string $lang): string
    {
        $basePath = $this->normalizePath($basePath);
        $targetDir = $this->normalizePath($basePath . '/' . $lang);

        if (!$this->isSafeLanguage($lang) || $targetDir === $basePath || !str_starts_with($targetDir . '/', $basePath . '/')) {
            throw new \InvalidArgumentException('语言目录不合法');
        }

        return $targetDir;
    }

    private function safeTargetFile(string $targetDir, string $basename): string
    {
        $targetFile = $this->normalizePath($targetDir . '/' . basename($basename));

        if (!str_starts_with($targetFile, $targetDir . '/') || $targetFile === $targetDir) {
            throw new \InvalidArgumentException('目标语言文件路径不合法');
        }

        return $targetFile;
    }

    private function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $prefix = str_starts_with($path, '/') ? '/' : '';
        $segments = [];

        foreach (explode('/', ltrim($path, '/')) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);
                continue;
            }

            $segments[] = $segment;
        }

        return rtrim($prefix . implode('/', $segments), '/');
    }
}
