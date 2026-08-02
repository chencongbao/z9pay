<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\UserBank;
use Illuminate\Support\Facades\Storage;

class UserBankPaymentQrcodeFormatTest extends TestCase
{
    public function test_empty_payment_qrcode_returns_null(): void
    {
        Storage::fake('admin');

        $bank = new UserBank(['payment_qrcode' => '']);

        $this->assertNull($bank->paymentQrcodeUrl());
        $this->assertNull($bank->payment_qrcode_format);
    }

    public function test_missing_payment_qrcode_file_returns_null(): void
    {
        Storage::fake('admin');

        $bank = new UserBank(['payment_qrcode' => 'images/missing-qrcode.jpg']);

        $this->assertNull($bank->paymentQrcodeUrl());
        $this->assertNull($bank->payment_qrcode_format);
    }

    public function test_existing_payment_qrcode_file_returns_public_url(): void
    {
        Storage::fake('admin');
        Storage::disk('admin')->put('images/existing-qrcode.jpg', 'qrcode');

        $bank = new UserBank(['payment_qrcode' => 'images/existing-qrcode.jpg']);

        $this->assertNotNull($bank->paymentQrcodeUrl());
        $this->assertStringContainsString('images/existing-qrcode.jpg', $bank->payment_qrcode_format);
    }
}
