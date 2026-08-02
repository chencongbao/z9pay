<?php

namespace App\Admin\Forms\SettlementOrder;

use Throwable;
use App\Models\User;
use Dcat\Admin\Admin;
use RuntimeException;
use Dcat\Admin\Widgets\Form;
use App\Models\TransferOrder;
use Dcat\Admin\Traits\LazyWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Services\Order\OrderCacheService;
use App\Events\UserTransferOrderNoticeEvent;
use App\Services\TransferOrder\TransferOrderMerchantDeductService;
use App\Services\TransferOrderLog\CreateTransferOrderLogService;

class DispathOrder extends Form implements LazyRenderable
{
    use LazyWidget;

    public function handle(array $input)
    {
        $admin = Admin::user();
        $userId = (int)($input['user_id'] ?? 0);
        if ($userId <= 0) {
            return $this->response()->error('请选择金主');
        }

        $id = (int)($this->payload['id'] ?? 0);
        if ($id <= 0) {
            return $this->response()->error('订单ID不存在');
        }

        try {
            [$order, $user] = DB::transaction(function () use ($id, $userId, $admin) {
                $order = TransferOrder::query()->where('type', 1)->whereKey($id)->where('status', 6)->lockForUpdate()->first(['id', 'type', 'status', 'mid', 'amount', 'ordernumber', 'merchant_rate']);
                if (!$order) {
                    throw new RuntimeException('订单不存在或状态不允许派单');
                }

                $user = User::query()->whereKey($userId)->where('is_agent', 0)->first(['id', 'name', 'username']);
                if (!$user) {
                    throw new RuntimeException('金主不存在');
                }

                if (TransferOrder::query()->where('status', 2)->where('user_id', $userId)->exists()) {
                    throw new RuntimeException('当前金主有代付订单未完成，无法派单');
                }

                // 派单到自营金主通道，统一回填商户结算手续费和额外手续费。
                App::make(TransferOrderMerchantDeductService::class)->fillSettlementFeeForChannel($order, 1);
                $order->status = 2;
                $order->channel_account_id = 1;
                $order->user_id = $userId;
                $order->hand_admin_id = $admin->id;
                $order->save();

                return [$order, $user];
            });

            App::make(OrderCacheService::class)->putTransfer($order, true);
            App::make(CreateTransferOrderLogService::class)->excute($order->id, '派单', '金主:' . $user->name);
            event(new UserTransferOrderNoticeEvent(['voice_url' => asset('voice/transfer.mp3'), 'text' => '您有新的代付订单', 'user_id' => $user->id]));
            bob_send_system_settlement_notice(['success_text' => '结算订单待支付，订单号：' . $order->ordernumber, 'voice_id' => 'settlement_2', 'id' => 2]);

            return $this->response()->success('操作成功')->refresh();
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('settlement-orders');
    }

    public function form()
    {
        $this->display('ordernumber', '平台订单号');
        $this->display('amount', '订单金额');
        $this->select('user_id', '金主')->options(User::query()->where('is_agent', 0)->get(['id', 'name', 'username'])->pluck('bname', 'id')->prepend('选择金主', 0))->required()->disableClearButton();
    }

    public function default()
    {
        $id = (int)($this->payload['id'] ?? 0);
        $order = TransferOrder::query()->whereKey($id)->first(['id', 'ordernumber', 'amount']);

        return [
            'ordernumber' => optional($order)->ordernumber,
            'amount' => optional($order)->amount,
            'user_id' => '',
        ];
    }
}
