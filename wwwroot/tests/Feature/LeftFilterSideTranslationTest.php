<?php

namespace Tests\Feature;

use Tests\TestCase;

class LeftFilterSideTranslationTest extends TestCase
{
    public function test_list_and_tree_sidebars_render_localized_controls_in_all_supported_locales(): void
    {
        $locales = [
            'zh_CN' => ['title' => '商户列表', 'expand' => '展开商户列表', 'collapse' => '收起侧栏', 'search' => '请输入关键词查询'],
            'en' => ['title' => 'Merchant List', 'expand' => 'Expand Merchant List', 'collapse' => 'Collapse sidebar', 'search' => 'Enter keywords to search'],
            'vi' => ['title' => 'Danh sách thương gia', 'expand' => 'Mở rộng Danh sách thương gia', 'collapse' => 'Thu gọn thanh bên', 'search' => 'Nhập từ khóa để tìm kiếm'],
        ];

        foreach ($locales as $locale => $labels) {
            app()->setLocale($locale);
            view()->flushState();

            $list = view('extendtions.dcat.layout.left-side', [
                'title' => $labels['title'],
                'data' => [collect(['active' => 1, 'url' => '/merchant/1', 'bname' => 'Merchant One'])],
            ])->render();
            $tree = view('extendtions.dcat.layout.left-tree-side', [
                'title' => $labels['title'],
                'data' => [['text' => 'Merchant One', 'href' => '/merchant/1']],
            ])->render();

            foreach ([$list, $tree] as $html) {
                $this->assertStringContainsString('data-expand-title="' . $labels['expand'] . '"', $html);
                $this->assertStringContainsString('data-collapse-title="' . $labels['collapse'] . '"', $html);
                $this->assertStringContainsString('placeholder="' . $labels['search'] . '"', $html);
            }

            if ($locale !== 'zh_CN') {
                $this->assertStringNotContainsString('收起侧栏', $list . $tree);
                $this->assertStringNotContainsString('请输入关键词查询', $list . $tree);
            }
        }
    }

    public function test_tree_data_and_dynamic_title_are_escaped_in_script_and_html_contexts(): void
    {
        app()->setLocale('en');
        view()->flushState();

        $payload = '</script><script>window.treePwned=1</script>"&';
        $title = '"><script>window.titlePwned=1</script>&';
        $html = view('extendtions.dcat.layout.left-tree-side', [
            'title' => $title,
            'data' => [
                ['text' => 'Normal node', 'href' => '/normal'],
                ['text' => $payload, 'href' => '/unsafe?value="&'],
            ],
        ])->render();

        $this->assertStringContainsString('data: JSON.parse(', $html);
        $this->assertStringContainsString('Normal node', $html);
        $this->assertStringContainsString('\\u003C', $html);
        $this->assertStringContainsString('\\u0022', $html);
        $this->assertStringContainsString('\\u0026', $html);
        $this->assertStringNotContainsString('</script><script>window.treePwned', $html);
        $this->assertStringNotContainsString('<script>window.titlePwned', $html);
        $this->assertStringContainsString('&quot;&gt;&lt;script&gt;window.titlePwned=1&lt;/script&gt;&amp;', $html);
    }
}
