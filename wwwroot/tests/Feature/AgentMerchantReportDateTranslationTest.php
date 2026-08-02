<?php

namespace Tests\Feature;

use Tests\TestCase;

class AgentMerchantReportDateTranslationTest extends TestCase
{
    public function test_merchant_report_date_column_uses_existing_localized_label(): void
    {
        $controller = file_get_contents(app_path('AgentAdmin/Controllers/ReportMerchantController.php'));

        $this->assertStringContainsString(
            '$grid->column(\'date_add\', __(\'reports-merchant-agents.fields.date_add\'))->center();',
            $controller
        );
        $this->assertStringNotContainsString('$grid->column(\'date_add\', \'日期\')', $controller);

        foreach (['zh_CN' => '日期', 'en' => 'Date', 'vi' => 'Ngày'] as $locale => $label) {
            app()->setLocale($locale);

            $this->assertSame($label, __('reports-merchant-agents.fields.date_add'));
        }
    }
}
