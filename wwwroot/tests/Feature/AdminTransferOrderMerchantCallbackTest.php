<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\TransferOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use App\Jobs\MerchantTransferCallbackJob;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Admin\Actions\Grid\TransferOrder\MerchantCallback;

class AdminTransferOrderMerchantCallbackTest extends TestCase
{
    use DatabaseTransactions;

    public function test_callback_success_order_does_not_dispatch_noop_callback_job(): void
    {
        Queue::fake();
        $order = TransferOrder::query()->create([
            'mid' => 24,
            'amount' => 10,
            'actual_amount' => 10,
            'currency_id' => 1,
            'order_no' => 'COD_CALLBACK_' . uniqid(),
            'ordernumber' => 'T' . date('YmdHis') . mt_rand(100000, 999999),
            'status' => 5,
            'type' => 0,
            'notify_url' => 'http://admin.luckypay.localhost/test',
            'callback_status' => 1,
            'callback_count' => 1,
        ]);

        $action = new MerchantCallback();
        $action->setKey($order->id);
        $response = $action->handle(Request::create('/admin/dcat-api/action', 'POST'))->toArray();

        $this->assertStringContainsString('订单已回调成功，无需重复推送', json_encode($response, JSON_UNESCAPED_UNICODE));
        $this->assertFalse(Cache::has('admin_transfer_callback:' . $order->id));
        Queue::assertNotPushed(MerchantTransferCallbackJob::class);
    }
}
