<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Schema\Blueprint;
use App\Http\Requests\Api\V2\DepositOrderConfirmPayRequest;

class DepositConfirmAmountValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        ]);
        app('db')->purge('sqlite');

        Schema::connection('sqlite')->create('deposit_orders', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('status')->default(3);
        });
        app('db')->connection('sqlite')->table('deposit_orders')->insert(['id' => 1, 'status' => 3]);
    }

    /**
     * @dataProvider nonPositiveAmountProvider
     */
    public function test_confirm_amount_must_be_greater_than_zero(string $amount): void
    {
        $request = DepositOrderConfirmPayRequest::create('/api/v2/deposit-orders/confirmPay', 'POST', [
            'order_id' => 1,
            'amount' => $amount,
        ]);
        $validator = Validator::make($request->all(), $request->rules(), $request->messages());

        $this->assertTrue($validator->fails());
        $this->assertSame('金额必须大于0', $validator->errors()->first('amount'));
    }

    public function nonPositiveAmountProvider(): array
    {
        return [
            'zero' => ['0'],
            'negative' => ['-1.00'],
        ];
    }

    public function test_positive_amount_passes_amount_validation(): void
    {
        $request = DepositOrderConfirmPayRequest::create('/api/v2/deposit-orders/confirmPay', 'POST', [
            'order_id' => 1,
            'amount' => '0.01',
        ]);
        $validator = Validator::make($request->all(), $request->rules(), $request->messages());

        $this->assertFalse($validator->errors()->has('amount'));
    }
}
