<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\Api\V2\UserBankStoreRequest;

class UserBankValidationMessageTest extends TestCase
{
    public function test_user_bank_validation_messages_match_their_rules(): void
    {
        $messages = (new UserBankStoreRequest())->messages();

        $this->assertSame('请选择收款银行', $messages['bank_id.exists']);
        $this->assertSame('全天限接单数量数值不合法', $messages['limit_day_order_number.numeric']);
        $this->assertSame('全天限接单数量0-9999999', $messages['limit_day_order_number.between']);
        $this->assertSame('收款二维码不能超过200个字符', $messages['payment_qrcode.max']);
    }

    public function test_payment_type_is_required_and_must_be_supported(): void
    {
        $this->assertValidationFails(['account_type' => 3], 'payment_id');
        $this->assertValidationFails(['account_type' => 3, 'payment_id' => 999999], 'payment_id');
    }

    public function test_qrcode_requirement_uses_account_type_including_douyin(): void
    {
        $this->assertValidationFails(['account_type' => 3, 'payment_id' => 1], 'payment_qrcode');
        $this->assertValidationFails(['account_type' => 14, 'payment_id' => 1], 'payment_qrcode');
    }

    /**
     * @dataProvider nonStringFieldProvider
     */
    public function test_text_fields_reject_array_payloads(string $field): void
    {
        $this->assertValidationFails([
            'account_type' => 2,
            'payment_id' => 1,
            $field => ['invalid'],
        ], $field);
    }

    public function nonStringFieldProvider(): array
    {
        return [
            'name' => ['name'],
            'card number' => ['card_no'],
            'qrcode path' => ['payment_qrcode'],
            'qrcode url' => ['payment_qrcode_url'],
        ];
    }

    public function test_qrcode_url_cannot_exceed_database_column_length(): void
    {
        $this->assertValidationFails([
            'account_type' => 2,
            'payment_id' => 1,
            'payment_qrcode_url' => str_repeat('a', 256),
        ], 'payment_qrcode_url');
    }

    private function assertValidationFails(array $data, string $field): void
    {
        $request = UserBankStoreRequest::create('/api/v2/user-banks', 'POST', array_merge([
            'name' => '测试收款卡',
            'limint_min_amount' => 0,
            'limint_max_amount' => 0,
            'limint_day_amount' => 0,
            'limit_day_order_number' => 0,
        ], $data));
        $validator = Validator::make($request->all(), $request->rules(), $request->messages());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey($field, $validator->errors()->toArray());
    }
}
