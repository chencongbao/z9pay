<?php

namespace App\Console\Commands;

use hg\apidoc\parses\ParseApiDetail;
use hg\apidoc\parses\ParseApiMenus;
use hg\apidoc\parses\ParseMarkdown;
use hg\apidoc\providers\LaravelService;
use hg\apidoc\utils\ConfigProvider;
use hg\apidoc\utils\Helper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ZipArchive;

class GenerateApidocumentCommand extends Command
{
    protected $signature = 'apidocument {--output=public/apidocument-static} {--lang=*}';

    protected $description = '生成静态 API 文档';

    public function handle(): int
    {
        try {
            $outputPath = $this->resolveOutputPath((string) $this->option('output'));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $languages = $this->resolveLanguages();
        $archiveConfig = null;
        $configFilePath = config_path('apidoc.php');
        $originalConfigContent = File::get($configFilePath);

        File::deleteDirectory($outputPath);
        File::ensureDirectoryExists($outputPath);
        File::put($outputPath . '/style.css', $this->buildStyleSheet());

        $languageLinks = [];

        foreach ($languages as $lang) {
            $langOutputPath = $outputPath . '/' . $lang;
            $config = $this->bootstrapApidoc($lang);
            $archiveConfig = $archiveConfig ?: $config;
            $apps = Helper::getAllApps($config['apps'] ?? []);

            if (empty($apps)) {
                $this->error("未找到可导出的 apidoc apps 配置: {$lang}");
                return self::FAILURE;
            }

            File::ensureDirectoryExists($langOutputPath);
            File::ensureDirectoryExists($langOutputPath . '/apps');
            File::put($langOutputPath . '/style.css', $this->buildStyleSheet());

            $apiMenusParser = new ParseApiMenus($config);
            $apiDetailParser = new ParseApiDetail($config);
            $markdownParser = new ParseMarkdown($config);

            $appSections = [];

            foreach ($apps as $app) {
                $appKey = (string) $app['appKey'];
                $currentApp = Helper::getCurrentAppConfig($appKey, $config)['appConfig'];
                $appSlug = $this->slugPath($appKey);
                $appTitle = (string) ($currentApp['title'] ?? $appKey);
                $appDir = $langOutputPath . '/apps/' . $appSlug;

                File::ensureDirectoryExists($appDir . '/api');
                File::ensureDirectoryExists($appDir . '/docs');

                $apiMenus = $apiMenusParser->renderApiMenus($appKey)['data'] ?? [];
                $docMenus = $markdownParser->getDocsMenu($appKey, $lang);

                $apiTree = $this->publishApiPages($apiMenus, $appKey, $appTitle, $appSlug, $langOutputPath, $apiDetailParser);
                $docTree = $this->publishDocPages($docMenus, $appKey, $appTitle, $appSlug, $langOutputPath, $lang);

                $appSections[] = [
                    'title' => $appTitle,
                    'appKey' => $appKey,
                    'apiTree' => $apiTree,
                    'docTree' => $docTree,
                ];
            }

            File::put($langOutputPath . '/index.html', $this->renderHomePage($config, $appSections, $lang, $languages));
            $languageLinks[] = [
                'lang' => $lang,
                'title' => $this->getLanguageTitle($lang),
                'href' => './' . $lang . '/index.html',
            ];
        }

        File::put($outputPath . '/index.html', $this->renderLanguageHomePage($languageLinks));
        $zipPath = $this->buildZipArchive($outputPath, $archiveConfig ?? []);
        if ($zipPath !== null) {
            $this->updateApidocDownloadUrl($configFilePath, $originalConfigContent, $zipPath);
        }
        File::deleteDirectory($outputPath);

        $this->info('静态文档生成完成');
        if ($zipPath !== null) {
            $this->line('压缩包: ' . $zipPath);
        }

        return self::SUCCESS;
    }

    protected function bootstrapApidoc(string $lang): array
    {
        $provider = new LaravelService(app());
        $provider->initConfig();

        $config = ConfigProvider::get();

        if (!empty($config['lang_register_function']) && is_callable($config['lang_register_function'])) {
            call_user_func($config['lang_register_function'], $lang);
            $provider->initConfig();
            $config = ConfigProvider::get();
        }

        return $config;
    }

    protected function resolveOutputPath(string $output): string
    {
        $output = trim($output);

        if ($output === '' || str_contains($output, "\0")) {
            throw new InvalidArgumentException('输出目录不合法。');
        }

        $rootPath = $this->apiDocumentOutputRoot();

        // 兼容旧默认值，但实际生成到私有、专用的临时目录内。
        if ($output === 'public/apidocument-static') {
            $output = 'build';
        }

        if ($this->isAbsolutePath($output)) {
            throw new InvalidArgumentException('输出目录必须使用专用根内的相对路径。');
        }

        if ($this->hasParentDirectorySegment($output) || $this->looksLikeLegacyProjectPath($output)) {
            throw new InvalidArgumentException('输出目录不合法。');
        }

        $outputPath = $this->normalizeAbsolutePath($rootPath . '/' . $output);
        $this->ensureSafeOutputPath($outputPath, $rootPath);

        return $outputPath;
    }

    protected function apiDocumentOutputRoot(): string
    {
        $rootPath = storage_path('app/apidocument-static');
        File::ensureDirectoryExists($rootPath);

        return $this->normalizeAbsolutePath(realpath($rootPath) ?: $rootPath);
    }

    private function isAbsolutePath(string $path): bool
    {
        return Str::startsWith($path, ['/']) || preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1;
    }

    private function hasParentDirectorySegment(string $path): bool
    {
        return in_array('..', explode('/', str_replace('\\', '/', $path)), true);
    }

    private function looksLikeLegacyProjectPath(string $path): bool
    {
        $path = trim(str_replace('\\', '/', $path), '/');

        return $path === 'public'
            || $path === 'storage'
            || Str::startsWith($path, 'public/')
            || Str::startsWith($path, 'storage/');
    }

    private function normalizeAbsolutePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $prefix = '';

        if (preg_match('/^[A-Za-z]:\//', $path, $matches) === 1) {
            $prefix = $matches[0];
            $path = substr($path, strlen($prefix));
        } elseif (Str::startsWith($path, '/')) {
            $prefix = '/';
            $path = ltrim($path, '/');
        }

        $segments = [];
        foreach (explode('/', $path) as $segment) {
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

    private function ensureSafeOutputPath(string $outputPath, string $rootPath): void
    {
        $rootPath = rtrim($this->normalizeAbsolutePath($rootPath), '/');
        $outputPath = rtrim($this->normalizeAbsolutePath($outputPath), '/');
        $forbiddenPaths = [
            base_path(),
            public_path(),
            storage_path(),
            storage_path('app'),
            $rootPath,
        ];

        if (!Str::startsWith($outputPath . '/', $rootPath . '/')) {
            throw new InvalidArgumentException('输出目录必须位于 storage/app/apidocument-static 子目录内。');
        }

        foreach ($forbiddenPaths as $forbiddenPath) {
            if ($outputPath === rtrim($this->normalizeAbsolutePath($forbiddenPath), '/')) {
                throw new InvalidArgumentException('输出目录范围过大，已拒绝执行。');
            }
        }

        $this->ensureNoSymlinkEscape($outputPath, $rootPath);
    }

    private function ensureNoSymlinkEscape(string $outputPath, string $rootPath): void
    {
        $rootPath = rtrim($rootPath, '/');
        $relativePath = ltrim(substr($outputPath, strlen($rootPath)), '/');
        $currentPath = $rootPath;

        foreach (explode('/', $relativePath) as $segment) {
            if ($segment === '') {
                continue;
            }

            $currentPath .= '/' . $segment;

            if (!file_exists($currentPath)) {
                continue;
            }

            $realPath = realpath($currentPath);
            if ($realPath === false || is_link($currentPath) || !Str::startsWith($this->normalizeAbsolutePath($realPath) . '/', $rootPath . '/')) {
                throw new InvalidArgumentException('输出目录存在符号链接或越界路径，已拒绝执行。');
            }
        }
    }

    protected function publishApiPages(array $nodes, string $appKey, string $appTitle, string $appSlug, string $outputPath, ParseApiDetail $parser): array
    {
        $result = [];

        foreach ($nodes as $node) {
            $item = [
                'title' => (string) ($node['title'] ?? $node['name'] ?? '未命名接口'),
                'href' => null,
                'meta' => null,
                'children' => [],
            ];

            if (!empty($node['children'])) {
                $item['children'] = $this->publishApiPages($node['children'], $appKey, $appTitle, $appSlug, $outputPath, $parser);
            }

            if (!empty($node['url']) && !empty($node['menuKey'])) {
                $apiKey = urldecode((string) $node['menuKey']);
                $fileName = sha1($apiKey) . '.html';
                $relativePath = 'apps/' . $appSlug . '/api/' . $fileName;
                $absolutePath = $outputPath . '/' . $relativePath;
                $detail = $parser->renderApiDetail($appKey, $apiKey);

                File::put($absolutePath, $this->renderApiPage($detail, $appTitle, '../../../style.css'));

                $item['href'] = $relativePath;
                $item['meta'] = trim(($detail['method'] ?? '') . ' ' . ($detail['url'] ?? ''));
            }

            $result[] = $item;
        }

        return $result;
    }

    protected function publishDocPages(array $nodes, string $appKey, string $appTitle, string $appSlug, string $outputPath, string $lang): array
    {
        $result = [];

        foreach ($nodes as $node) {
            $item = [
                'title' => (string) ($node['title'] ?? '未命名文档'),
                'href' => null,
                'meta' => null,
                'children' => [],
            ];

            if (!empty($node['children'])) {
                $item['children'] = $this->publishDocPages($node['children'], $appKey, $appTitle, $appSlug, $outputPath, $lang);
            }

            if (empty($node['children']) && !empty($node['path'])) {
                $docPath = (string) $node['path'];
                $fileName = sha1($docPath) . '.html';
                $relativePath = 'apps/' . $appSlug . '/docs/' . $fileName;
                $absolutePath = $outputPath . '/' . $relativePath;
                $content = ParseMarkdown::getContent($appKey, $docPath, $lang);

                File::put($absolutePath, $this->renderMarkdownPage(
                    (string) ($node['title'] ?? $docPath),
                    $appTitle,
                    $this->markdownToHtml($content),
                    '../../../style.css'
                ));

                $item['href'] = $relativePath;
                $item['meta'] = $docPath;
            }

            $result[] = $item;
        }

        return $result;
    }

    protected function renderHomePage(array $config, array $appSections, string $lang, array $languages): string
    {
        $title = e((string) ($config['title'] ?? 'API Document'));
        $desc = e((string) ($config['desc'] ?? ''));
        $generatedAt = e(now()->format('Y-m-d H:i:s'));
        $language = e($lang);
        $switcherHtml = $this->renderLanguageSwitcher($lang, $languages);

        $sectionsHtml = '';

        foreach ($appSections as $section) {
            $sectionsHtml .= '<section class="card app-card">';
            $sectionsHtml .= '<div class="card-head app-card-head">';
            $sectionsHtml .= '<div><p class="section-kicker">Workspace</p><h2>' . e($section['title']) . '</h2></div>';
            $sectionsHtml .= '<div class="app-key-chip">appKey: ' . e($section['appKey']) . '</div>';
            $sectionsHtml .= '</div>';

            if (!empty($section['docTree'])) {
                $sectionsHtml .= '<div class="split">';
                $sectionsHtml .= '<div class="panel panel-docs"><h3>说明文档</h3>' . $this->renderTreeList($section['docTree']) . '</div>';
                $sectionsHtml .= '<div class="panel panel-apis"><h3>接口文档</h3>' . $this->renderTreeList($section['apiTree']) . '</div>';
                $sectionsHtml .= '</div>';
            } else {
                $sectionsHtml .= '<div class="panel panel-apis"><h3>接口文档</h3>' . $this->renderTreeList($section['apiTree']) . '</div>';
            }

            $sectionsHtml .= '</section>';
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$title}</title>
  <link rel="stylesheet" href="./style.css">
</head>
<body>
  <div class="page">
    <header class="hero">
      <div>
        <p class="eyebrow">Static Api Document</p>
        <h1>{$title}</h1>
        <p class="hero-desc">{$desc}</p>
        {$switcherHtml}
      </div>
      <div class="hero-meta">
        <div><span>语言</span><strong>{$language}</strong></div>
        <div><span>生成时间</span><strong>{$generatedAt}</strong></div>
      </div>
    </header>
    {$sectionsHtml}
  </div>
</body>
</html>
HTML;
    }

    protected function renderLanguageHomePage(array $languageLinks): string
    {
        $items = '';

        foreach ($languageLinks as $link) {
            $items .= '<li><a href="' . e($link['href']) . '">' . e($link['title']) . '</a><div class="tree-meta">' . e($link['lang']) . '</div></li>';
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Static Api Document</title>
  <link rel="stylesheet" href="./style.css">
</head>
<body>
  <div class="page">
    <header class="hero">
      <div>
        <p class="eyebrow">Static Api Document</p>
        <h1>文档语言入口</h1>
        <p class="hero-desc">请选择要查看的文档语言版本。</p>
      </div>
    </header>
    <section class="card">
      <ul class="tree-list">{$items}</ul>
    </section>
  </div>
</body>
</html>
HTML;
    }

    protected function renderMarkdownPage(string $title, string $appTitle, string $contentHtml, string $stylePath): string
    {
        $safeTitle = e($title);
        $safeAppTitle = e($appTitle);

        return <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$safeTitle}</title>
  <link rel="stylesheet" href="{$stylePath}">
</head>
<body>
  <div class="page">
    <a class="back-link" href="../../../index.html">返回文档首页</a>
    <section class="card">
      <div class="card-head">
        <h1>{$safeTitle}</h1>
        <div class="muted">{$safeAppTitle}</div>
      </div>
      <article class="markdown-body">{$contentHtml}</article>
    </section>
  </div>
</body>
</html>
HTML;
    }

    protected function renderApiPage(array $detail, string $appTitle, string $stylePath): string
    {
        $title = e((string) ($detail['title'] ?? $detail['name'] ?? '接口文档'));
        $method = e((string) ($detail['method'] ?? ''));
        $url = e((string) ($detail['url'] ?? ''));
        $name = e((string) ($detail['name'] ?? ''));
        $safeAppTitle = e($appTitle);

        $summaryHtml = '<section class="card api-hero-card"><div class="card-head api-hero-head"><div><p class="section-kicker">Endpoint</p><h1>' . $title . '</h1></div><div class="muted app-badge">' . $safeAppTitle . '</div></div>';
        $summaryHtml .= '<div class="meta-grid">';
        $summaryHtml .= '<div class="meta-card"><span>请求方式</span><strong><code>' . $method . '</code></strong></div>';
        $summaryHtml .= '<div class="meta-card"><span>请求地址</span><strong><code>' . $url . '</code></strong></div>';
        $summaryHtml .= '<div class="meta-card"><span>方法名</span><strong><code>' . $name . '</code></strong></div>';
        $summaryHtml .= '</div></section>';

        $bodyHtml = $summaryHtml;
        $bodyHtml .= $this->renderFieldSection('请求头 Header', $detail['header'] ?? []);
        $bodyHtml .= $this->renderFieldSection('路由参数 Route', $detail['routeParam'] ?? []);
        $bodyHtml .= $this->renderFieldSection('请求参数 Query', $detail['query'] ?? []);
        $bodyHtml .= $this->renderFieldSection('请求参数 Body', $detail['param'] ?? []);

        if (!empty($detail['md'])) {
            $bodyHtml .= $this->renderHtmlSection('接口说明', $this->markdownToHtml((string) $detail['md']));
        }

        $bodyHtml .= $this->renderFieldSection('成功响应', $detail['responseSuccess'] ?? []);

        if (!empty($detail['responseSuccessMd'])) {
            $bodyHtml .= $this->renderHtmlSection('成功响应说明', $this->markdownToHtml((string) $detail['responseSuccessMd']));
        }

        $bodyHtml .= $this->renderFieldSection('失败响应', $detail['responseError'] ?? []);

        if (!empty($detail['responseErrorMd'])) {
            $bodyHtml .= $this->renderHtmlSection('失败响应说明', $this->markdownToHtml((string) $detail['responseErrorMd']));
        }

        $bodyHtml .= $this->renderStatusSection('响应状态码', $detail['responseStatus'] ?? []);

        return <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$title}</title>
  <link rel="stylesheet" href="{$stylePath}">
</head>
<body>
  <div class="page">
    <a class="back-link" href="../../../index.html">返回文档首页</a>
    {$bodyHtml}
  </div>
</body>
</html>
HTML;
    }

    protected function renderFieldSection(string $title, array $items): string
    {
        if (empty($items)) {
            return '';
        }

        $rows = $this->renderFieldRows($items);

        return <<<HTML
<section class="card">
  <h2>{$title}</h2>
  <div class="table-wrap">
    <table class="doc-table">
      <thead>
        <tr>
          <th>字段</th>
          <th>类型</th>
          <th>必填</th>
          <th>默认值</th>
          <th>说明</th>
        </tr>
      </thead>
      <tbody>
        {$rows}
      </tbody>
    </table>
  </div>
</section>
HTML;
    }

    protected function renderStatusSection(string $title, array $items): string
    {
        if (empty($items)) {
            return '';
        }

        $rows = '';

        foreach ($items as $item) {
            $rows .= '<tr>';
            $rows .= '<td><code>' . e((string) ($item['name'] ?? '')) . '</code></td>';
            $rows .= '<td>' . e((string) ($item['type'] ?? '')) . '</td>';
            $rows .= '<td>' . ($this->boolLabel($item['require'] ?? null)) . '</td>';
            $rows .= '<td>' . e((string) ($item['contentType'] ?? '')) . '</td>';
            $rows .= '<td>' . $this->markdownToHtml((string) ($item['desc'] ?? '')) . '</td>';
            $rows .= '</tr>';
        }

        return <<<HTML
<section class="card">
  <h2>{$title}</h2>
  <div class="table-wrap">
    <table class="doc-table">
      <thead>
        <tr>
          <th>状态码</th>
          <th>类型</th>
          <th>必填</th>
          <th>Content-Type</th>
          <th>说明</th>
        </tr>
      </thead>
      <tbody>
        {$rows}
      </tbody>
    </table>
  </div>
</section>
HTML;
    }

    protected function renderHtmlSection(string $title, string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        return <<<HTML
<section class="card">
  <h2>{$title}</h2>
  <article class="markdown-body">{$html}</article>
</section>
HTML;
    }

    protected function renderFieldRows(array $items, int $depth = 0): string
    {
        $rows = '';

        foreach ($items as $item) {
            $indent = $depth * 18;
            $name = e((string) ($item['name'] ?? ''));
            $type = e((string) ($item['type'] ?? ''));
            $default = e($this->stringifyValue($item['default'] ?? ''));
            $descHtml = $this->markdownToHtml((string) ($item['desc'] ?? ''));

            if (!empty($item['md'])) {
                $descHtml .= '<div class="field-md">' . $this->markdownToHtml((string) $item['md']) . '</div>';
            }

            $rows .= '<tr>';
            $rows .= '<td><div class="field-name" style="padding-left:' . $indent . 'px"><code>' . $name . '</code></div></td>';
            $rows .= '<td>' . $type . '</td>';
            $rows .= '<td>' . $this->boolLabel($item['require'] ?? null) . '</td>';
            $rows .= '<td>' . $default . '</td>';
            $rows .= '<td>' . $descHtml . '</td>';
            $rows .= '</tr>';

            if (!empty($item['children']) && is_array($item['children'])) {
                $rows .= $this->renderFieldRows($item['children'], $depth + 1);
            }
        }

        return $rows;
    }

    protected function renderTreeList(array $items): string
    {
        if (empty($items)) {
            return '<p class="muted">暂无内容</p>';
        }

        $html = '<ul class="tree-list">';

        foreach ($items as $item) {
            $html .= '<li>';
            if (!empty($item['href'])) {
                $html .= '<a href="./' . e($item['href']) . '">' . e($item['title']) . '</a>';
            } else {
                $html .= '<span class="tree-title">' . e($item['title']) . '</span>';
            }

            if (!empty($item['meta'])) {
                $html .= '<div class="tree-meta">' . e($item['meta']) . '</div>';
            }

            if (!empty($item['children'])) {
                $html .= $this->renderTreeList($item['children']);
            }

            $html .= '</li>';
        }

        $html .= '</ul>';

        return $html;
    }

    protected function markdownToHtml(string $markdown): string
    {
        $markdown = trim($markdown);

        if ($markdown === '') {
            return '';
        }

        return (string) Str::markdown($markdown);
    }

    protected function slugPath(string $value): string
    {
        return trim(preg_replace('/[^A-Za-z0-9._-]+/', '-', $value), '-');
    }

    protected function resolveLanguages(): array
    {
        $languages = $this->option('lang');

        if (empty($languages)) {
            return ['zh_CN', 'en'];
        }

        $languages = array_values(array_unique(array_filter(array_map('strval', $languages))));

        return empty($languages) ? ['zh_CN', 'en'] : $languages;
    }

    protected function getLanguageTitle(string $lang): string
    {
        return match ($lang) {
            'zh_CN' => '中文版',
            'en' => 'English',
            default => $lang,
        };
    }

    protected function renderLanguageSwitcher(string $currentLang, array $languages): string
    {
        if (count($languages) <= 1) {
            return '';
        }

        $links = [];

        foreach ($languages as $lang) {
            $className = $lang === $currentLang ? 'lang-link current' : 'lang-link';
            $links[] = '<a class="' . $className . '" href="../' . e($lang) . '/index.html">' . e($this->getLanguageTitle($lang)) . '</a>';
        }

        return '<div class="lang-switcher">' . implode('', $links) . '</div>';
    }

    protected function boolLabel($value): string
    {
        return (string) ($value ? '<span class="badge badge-yes">是</span>' : '<span class="badge badge-no">否</span>');
    }

    protected function stringifyValue($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if ($value === null) {
            return '';
        }

        return (string) $value;
    }

    protected function buildStyleSheet(): string
    {
        return <<<'CSS'
:root {
  --bg: #f3efe8;
  --card: rgba(255, 250, 243, 0.92);
  --card-strong: #fffdf9;
  --line: #e7d9c5;
  --text: #231a11;
  --muted: #776656;
  --accent: #b45309;
  --accent-deep: #7c2d12;
  --accent-soft: #fff1dd;
  --accent-fade: rgba(180, 83, 9, 0.08);
  --ok: #166534;
  --danger: #b91c1c;
  --shadow: 0 24px 60px rgba(80, 54, 24, 0.10);
  --shadow-soft: 0 10px 30px rgba(80, 54, 24, 0.06);
}

* {
  box-sizing: border-box;
}

body {
  margin: 0;
  font-family: "Segoe UI", "PingFang SC", "Hiragino Sans GB", sans-serif;
  background:
    radial-gradient(circle at top right, rgba(180, 83, 9, 0.12), transparent 22%),
    radial-gradient(circle at left bottom, rgba(124, 45, 18, 0.07), transparent 28%),
    linear-gradient(180deg, #faf6ef 0%, #f1e7db 100%);
  color: var(--text);
}

a {
  color: var(--accent);
  text-decoration: none;
}

a:hover {
  text-decoration: underline;
}

code {
  font-family: "SFMono-Regular", Consolas, Monaco, monospace;
  background: rgba(120, 72, 20, 0.08);
  padding: 2px 6px;
  border-radius: 6px;
}

.page {
  max-width: 1200px;
  margin: 0 auto;
  padding: 32px 20px 48px;
}

.hero,
.card {
  background: var(--card);
  border: 1px solid var(--line);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  border-radius: 24px;
  box-shadow: var(--shadow);
}

.hero {
  padding: 34px;
  margin-bottom: 28px;
  display: flex;
  gap: 24px;
  justify-content: space-between;
  align-items: flex-start;
  position: relative;
  overflow: hidden;
}

.hero::before {
  content: "";
  position: absolute;
  inset: 0;
  background:
    linear-gradient(135deg, rgba(255,255,255,0.35), transparent 42%),
    radial-gradient(circle at 85% 15%, rgba(180, 83, 9, 0.14), transparent 24%);
  pointer-events: none;
}

.hero h1,
.card h1,
.card h2,
.card h3 {
  margin: 0;
}

.eyebrow {
  margin: 0 0 8px;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  color: var(--accent);
  font-size: 12px;
  font-weight: 700;
}

.section-kicker {
  margin: 0 0 8px;
  font-size: 11px;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--accent);
  font-weight: 700;
}

.hero-desc,
.muted,
.tree-meta {
  color: var(--muted);
}

.lang-switcher {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 16px;
}

.lang-link {
  display: inline-flex;
  align-items: center;
  padding: 8px 12px;
  border: 1px solid var(--line);
  border-radius: 999px;
  background: rgba(255,255,255,0.8);
  font-size: 13px;
  font-weight: 600;
  box-shadow: var(--shadow-soft);
}

.lang-link.current {
  background: var(--accent-soft);
  border-color: rgba(180, 83, 9, 0.28);
}

.hero-meta {
  min-width: 240px;
  display: grid;
  gap: 12px;
}

.hero-meta div,
.meta-card {
  background: var(--accent-soft);
  border-radius: 16px;
  padding: 14px 16px;
  border: 1px solid rgba(180, 83, 9, 0.10);
}

.hero-meta span,
.meta-grid span {
  display: block;
  font-size: 12px;
  color: var(--muted);
  margin-bottom: 4px;
}

.hero-meta strong,
.meta-grid strong {
  font-size: 14px;
}

.card {
  padding: 26px;
  margin-bottom: 22px;
}

.card h2 {
  font-size: 20px;
  margin-bottom: 16px;
}

.card h3 {
  font-size: 16px;
  margin: 0 0 12px;
}

.card-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
  margin-bottom: 20px;
  padding-bottom: 14px;
  border-bottom: 1px solid rgba(231, 217, 197, 0.8);
}

.app-card {
  position: relative;
  overflow: hidden;
}

.app-card::after {
  content: "";
  position: absolute;
  top: -20px;
  right: -20px;
  width: 160px;
  height: 160px;
  background: radial-gradient(circle, rgba(180, 83, 9, 0.10), transparent 68%);
  pointer-events: none;
}

.app-key-chip,
.app-badge {
  display: inline-flex;
  align-items: center;
  padding: 8px 12px;
  border-radius: 999px;
  background: rgba(255,255,255,0.78);
  border: 1px solid rgba(180, 83, 9, 0.14);
  color: var(--accent-deep);
  font-weight: 600;
  box-shadow: var(--shadow-soft);
}

.split {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 22px;
}

.panel {
  position: relative;
  padding: 18px 18px 12px;
  background: linear-gradient(180deg, rgba(255,255,255,0.68), rgba(255,255,255,0.45));
  border: 1px solid rgba(231, 217, 197, 0.85);
  border-radius: 20px;
  box-shadow: var(--shadow-soft);
}

.panel-docs {
  background:
    linear-gradient(180deg, rgba(255,255,255,0.82), rgba(255,255,255,0.52)),
    radial-gradient(circle at top left, rgba(180, 83, 9, 0.06), transparent 28%);
}

.panel-apis {
  background:
    linear-gradient(180deg, rgba(255,255,255,0.82), rgba(255,255,255,0.52)),
    radial-gradient(circle at top right, rgba(124, 45, 18, 0.05), transparent 28%);
}

.tree-list {
  list-style: none;
  padding-left: 0;
  margin: 10px 0 0;
}

.tree-list > li {
  padding: 10px 0;
}

.tree-list li {
  margin: 0;
  position: relative;
}

.tree-title {
  font-weight: 600;
}

.tree-list a {
  font-weight: 600;
  font-size: 15px;
}

.tree-list ul {
  margin-top: 10px;
  padding-left: 18px;
  border-left: 2px solid rgba(180, 83, 9, 0.10);
}

.back-link {
  display: inline-block;
  margin-bottom: 18px;
  font-weight: 600;
  padding: 10px 14px;
  border-radius: 999px;
  background: rgba(180, 83, 9, 0.08);
  border: 1px solid rgba(180, 83, 9, 0.14);
  box-shadow: var(--shadow-soft);
}

.meta-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 14px;
}

.meta-grid code {
  font-size: 14px;
}

.table-wrap {
  overflow-x: auto;
  border: 1px solid rgba(231, 217, 197, 0.9);
  border-radius: 18px;
  background: var(--card-strong);
  box-shadow: inset 0 1px 0 rgba(255,255,255,0.7);
}

.doc-table {
  width: 100%;
  border-collapse: collapse;
  min-width: 760px;
}

.doc-table th,
.doc-table td {
  border-bottom: 1px solid var(--line);
  padding: 14px 12px;
  text-align: left;
  vertical-align: top;
}

.doc-table th {
  background: rgba(180, 83, 9, 0.06);
  font-size: 13px;
  font-weight: 700;
  color: #5c472f;
  position: sticky;
  top: 0;
  z-index: 1;
}

.doc-table tbody tr:nth-child(even) td {
  background: rgba(180, 83, 9, 0.02);
}

.doc-table tbody tr:hover td {
  background: rgba(180, 83, 9, 0.06);
}

.field-name {
  min-width: 180px;
  display: flex;
  align-items: center;
}

.field-name code {
  font-weight: 700;
  color: #7c2d12;
  background: rgba(180, 83, 9, 0.09);
}

.field-md {
  margin-top: 10px;
  padding: 10px 12px;
  background: linear-gradient(180deg, rgba(180, 83, 9, 0.04), rgba(180, 83, 9, 0.07));
  border-left: 3px solid rgba(180, 83, 9, 0.4);
  border-radius: 12px;
}

.badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 32px;
  padding: 3px 8px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
}

.badge-yes {
  color: var(--ok);
  background: rgba(22, 101, 52, 0.10);
}

.badge-no {
  color: var(--danger);
  background: rgba(185, 28, 28, 0.10);
}

.markdown-body {
  line-height: 1.75;
  font-size: 15px;
  color: #34291e;
}

.markdown-body h1,
.markdown-body h2,
.markdown-body h3,
.markdown-body h4 {
  margin-top: 1.4em;
  margin-bottom: 0.5em;
  color: #3b2b1b;
}

.markdown-body p,
.markdown-body ul,
.markdown-body ol {
  margin: 0.8em 0;
}

.markdown-body ul,
.markdown-body ol {
  padding-left: 1.4em;
}

.markdown-body blockquote {
  margin: 1em 0;
  padding: 12px 14px;
  border-left: 4px solid rgba(180, 83, 9, 0.45);
  background: rgba(180, 83, 9, 0.06);
  border-radius: 0 12px 12px 0;
}

.markdown-body pre {
  overflow: auto;
  background: linear-gradient(180deg, #2b241c, #1f1812);
  color: #fff7ed;
  padding: 16px;
  border-radius: 18px;
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05);
}

.markdown-body pre code {
  background: transparent;
  padding: 0;
  color: inherit;
}

.markdown-body table {
  width: 100%;
  border-collapse: collapse;
  margin: 1em 0;
  overflow: hidden;
  border-radius: 12px;
}

.markdown-body table th,
.markdown-body table td {
  border: 1px solid var(--line);
  padding: 8px 10px;
}

.markdown-body table th {
  background: rgba(180, 83, 9, 0.06);
}

.api-hero-card {
  background:
    linear-gradient(180deg, rgba(255,255,255,0.90), rgba(255,248,240,0.82)),
    radial-gradient(circle at top right, rgba(180, 83, 9, 0.08), transparent 30%);
}

.api-hero-head h1 {
  font-size: 30px;
  line-height: 1.15;
}

.markdown-body hr {
  border: 0;
  border-top: 1px solid rgba(231, 217, 197, 0.9);
  margin: 24px 0;
}

@media (max-width: 900px) {
  .hero,
  .card-head {
    flex-direction: column;
  }

  .split,
  .meta-grid {
    grid-template-columns: 1fr;
  }

  .page {
    padding: 18px 12px 32px;
  }

  .card,
  .hero {
    padding: 18px;
    border-radius: 16px;
  }

  .api-hero-head h1 {
    font-size: 24px;
  }

  .doc-table {
    min-width: 640px;
  }

  .field-name {
    min-width: 140px;
  }
}
CSS;
    }

    protected function buildZipArchive(string $outputPath, array $config = []): ?string
    {
        if (!class_exists(ZipArchive::class)) {
            $this->warn('当前环境未启用 ZipArchive，已跳过压缩包生成');
            return null;
        }

        $archiveDir = public_path('apidocument');
        File::ensureDirectoryExists($archiveDir);

        $archiveBaseName = $this->buildArchiveBaseName($config);
        $zipPath = $archiveDir . '/' . $archiveBaseName . '-' . now()->format('Ymd_His') . '.zip';
        $this->deleteOldArchives($archiveDir, $archiveBaseName);

        if (File::exists($zipPath)) {
            File::delete($zipPath);
        }

        $zip = new ZipArchive();
        $result = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($result !== true) {
            $this->warn('压缩包创建失败，已跳过 zip 生成');
            return null;
        }

        $baseName = basename($outputPath);
        $files = File::allFiles($outputPath);

        foreach ($files as $file) {
            $absolutePath = $file->getRealPath();
            $relativePath = $baseName . '/' . ltrim(str_replace($outputPath, '', $absolutePath), DIRECTORY_SEPARATOR);
            $zip->addFile($absolutePath, str_replace(DIRECTORY_SEPARATOR, '/', $relativePath));
        }

        $zip->close();

        return $zipPath;
    }

    protected function deleteOldArchives(string $archiveDir, string $archiveBaseName): void
    {
        $pattern = $archiveDir . '/' . $archiveBaseName . '*.zip';

        foreach (glob($pattern) ?: [] as $oldFile) {
            if (is_file($oldFile)) {
                File::delete($oldFile);
            }
        }
    }

    protected function buildArchiveBaseName(array $config): string
    {
        $apps = $config['apps'] ?? [];
        $rawName = 'apidocument-offline-doc';

        if (!empty($apps) && is_array($apps) && !empty($apps[0]['title'])) {
            $rawName = (string) $apps[0]['title'] . '-offline-doc';
        } elseif (!empty($config['title'])) {
            $rawName = (string) $config['title'] . '-offline-doc';
        }

        $slug = $this->slugPath($rawName);

        return $slug !== '' ? $slug : 'apidocument-offline-doc';
    }

    protected function updateApidocDownloadUrl(string $configFilePath, string $originalConfigContent, string $zipPath): void
    {
        $publicZipPath = str_replace(public_path(), '', $zipPath);
        $publicZipPath = str_replace(DIRECTORY_SEPARATOR, '/', $publicZipPath);
        $downloadUrl = $publicZipPath !== '' ? $publicZipPath : '/';

        $updatedContent = preg_replace(
            '/("url"\s*=>\s*)"[^"]*"/',
            '$1"' . addslashes($downloadUrl) . '"',
            $originalConfigContent,
            1
        );

        if (is_string($updatedContent) && $updatedContent !== '') {
            File::put($configFilePath, $updatedContent);
        }
    }
}
