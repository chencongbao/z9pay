<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\Api\V2\UploadImageRequest;
use App\Http\Requests\Api\V2\TransferOrdersubmitOrderRequest;

class TransferCertificateValidationTest extends TestCase
{
    public function test_empty_upload_is_rejected_before_controller_storage_call(): void
    {
        $request = UploadImageRequest::create('/api/v2/transfer-orders/uploadImage', 'POST');
        $validator = Validator::make($request->all(), $request->rules(), $request->messages());

        $this->assertTrue($validator->fails());
        $this->assertSame('请上传图片', $validator->errors()->first('file'));
        $this->assertSame('图片不能超过4M', $request->messages()['file.max']);
    }

    public function test_overlong_certificate_path_is_rejected(): void
    {
        $request = TransferOrdersubmitOrderRequest::create('/api/v2/transfer-orders/submitOrder', 'POST', [
            'pay_certificate_1' => str_repeat('a', 256),
        ]);
        $validator = Validator::make($request->all(), $request->rules(), $request->messages());

        $this->assertTrue($validator->fails());
        $this->assertSame('回执单路径不能超过255个字符', $validator->errors()->first('pay_certificate_1'));
    }

    public function test_valid_certificate_path_passes_validation(): void
    {
        $request = TransferOrdersubmitOrderRequest::create('/api/v2/transfer-orders/submitOrder', 'POST', [
            'pay_certificate_1' => 'transfer/receipt.png',
        ]);
        $validator = Validator::make($request->all(), $request->rules(), $request->messages());

        $this->assertFalse($validator->fails());
    }
}
