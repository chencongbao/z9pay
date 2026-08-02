<?php

namespace Tests\Feature;

use Tests\TestCase;
use ReflectionClass;
use App\Admin\Forms\Config\MerchantForm;
use App\Services\Cache\Config\CacheAdminSettingService;

class AdminMerchantConfigOrderLimitTest extends TestCase
{
    public function test_negative_order_limit_is_rejected_without_saving_config(): void
    {
        $settingService = $this->fakeAdminSettingService();
        $response = (new MerchantForm())->handle($this->merchantConfigInput('-1', '9'));

        $this->assertFalse($response->toArray()['status']);
        $this->assertSame('商户提单限制代收订单/分钟必须是大于等于0的整数', data_get($response->toArray(), 'data.message'));
        $this->assertSame(0, $settingService->writes);
    }

    public function test_order_limit_rejects_non_integer_values(): void
    {
        foreach (['abc', '1.5', '-1', ''] as $value) {
            $this->assertSame(
                '商户提单限制代收订单/分钟必须是大于等于0的整数',
                $this->validateOrderLimitMessage($value, '9')
            );
            $this->assertSame(
                '商户提单限制代付订单/分钟必须是大于等于0的整数',
                $this->validateOrderLimitMessage('7', $value)
            );
        }
    }

    public function test_order_limit_allows_zero_and_normalizes_to_integer_values(): void
    {
        $form = new MerchantForm();
        $data = $this->callPrivate($form, 'normalizeInput', [$this->merchantConfigInput('0', '9'), true]);

        $this->assertSame('', $this->callPrivate($form, 'validateOrderLimitConfig', [$data['api_merchant_order_limit']]));
        $normalized = $this->callPrivate($form, 'normalizeOrderLimitConfig', [$data['api_merchant_order_limit']]);

        $this->assertSame([[
            'mid' => 24,
            'deposit_order' => 0,
            'transfer_order' => 9,
        ]], $normalized);
    }

    public function test_order_limit_accepts_normal_integer_values(): void
    {
        $form = new MerchantForm();
        $data = $this->callPrivate($form, 'normalizeInput', [$this->merchantConfigInput('7', '9'), true]);

        $this->assertSame('', $this->callPrivate($form, 'validateOrderLimitConfig', [$data['api_merchant_order_limit']]));
        $normalized = $this->callPrivate($form, 'normalizeOrderLimitConfig', [$data['api_merchant_order_limit']]);

        $this->assertSame([[
            'mid' => 24,
            'deposit_order' => 7,
            'transfer_order' => 9,
        ]], $normalized);
    }

    private function validateOrderLimitMessage(string $depositOrder, string $transferOrder): string
    {
        $form = new MerchantForm();
        $data = $this->callPrivate($form, 'normalizeInput', [$this->merchantConfigInput($depositOrder, $transferOrder), true]);

        return $this->callPrivate($form, 'validateOrderLimitConfig', [$data['api_merchant_order_limit']]);
    }

    private function merchantConfigInput(string $depositOrder, string $transferOrder): array
    {
        return [
            'telegram_merchant_balance_notice_single' => [],
            'api_merchant_order_limit' => [[
                'mid' => 24,
                'deposit_order' => $depositOrder,
                'transfer_order' => $transferOrder,
                '_remove_' => 0,
            ]],
            'telegram_merchant_group_lang_config' => [],
            'gcash_merchant_name_default' => '',
            'gcash_merchant_name_merchants' => [],
        ];
    }

    private function callPrivate(object $object, string $method, array $args = [])
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $args);
    }

    private function fakeAdminSettingService(): object
    {
        $service = new class extends CacheAdminSettingService {
            public int $writes = 0;

            public function excute($name = '', $value = null, $isSet = false)
            {
                if ($isSet || is_array($name)) {
                    $this->writes++;
                }

                return null;
            }
        };

        $this->app->instance(CacheAdminSettingService::class, $service);

        return $service;
    }
}
