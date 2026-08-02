<?php

namespace Tests\Feature;

use Tests\TestCase;

class AgentBalanceTypeTranslationTest extends TestCase
{
    public function test_all_agent_balance_types_have_translations_in_every_supported_locale(): void
    {
        $typeKeys = array_keys(config('default.agent_balance_type'));

        foreach (['zh_CN', 'en', 'vi'] as $locale) {
            $translations = require lang_path("{$locale}/global.php");
            $translatedTypes = $translations['options']['agent_balance_type'] ?? [];

            $this->assertSame([], array_values(array_diff($typeKeys, array_keys($translatedTypes))), "Missing {$locale} agent balance type translations");
            foreach ($typeKeys as $type) {
                $this->assertNotSame('', trim((string) $translatedTypes[$type]), "Empty {$locale} translation for agent balance type {$type}");
            }
        }
    }
}
