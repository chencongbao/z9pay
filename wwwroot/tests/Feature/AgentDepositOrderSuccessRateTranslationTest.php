<?php

namespace Tests\Feature;

use Tests\TestCase;

class AgentDepositOrderSuccessRateTranslationTest extends TestCase
{
    public function test_success_rate_card_reuses_existing_translation_in_every_supported_locale(): void
    {
        $card = file_get_contents(app_path('Admin/Metrics/AgentAdmin/DepositOrder/Card4.php'));

        $this->assertStringContainsString("parent::__construct(__('home.labels.order_success_rate'));", $card);
        $this->assertStringNotContainsString('parent::__construct("成功率")', $card);

        foreach (['zh_CN' => '成功率', 'en' => 'Success Rate', 'vi' => 'Tỷ lệ thành công'] as $locale => $label) {
            app()->setLocale($locale);

            $this->assertSame($label, __('home.labels.order_success_rate'));
        }
    }
}
