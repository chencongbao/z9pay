<?php

namespace App\Http\Controllers\Api\V2;


use App\Models\DepositOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use App\Services\Const\LogConstService;
use App\Services\Common\SystemLogService;
use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\DepositOrderCollection;
use App\Services\UserBank\UserBankActionLogService;
use App\Services\DepositOrder\ConfirmPaySuccessService;
use App\Http\Requests\Api\V2\DepositOrderConfirmPayRequest;

class DepositOrderController extends ApiController
{

    public $field = ['id', 'user_id', 'ordernumber', 'card_name', 'actual_amount', 'amount', 'status', 'updated_at', 'success_time', 'created_at', 'account_type', 'bank_id', 'collection_card_no', 'collection_name', 'pay_certificate', 'pay_status', 'pay_name', 'user_commission', 'pay_amount'];

    public function index(Request $request)
    {
        $model = new DepositOrder();
        $model = $model->where('user_id', $request->user()->id)->orderBy('id', 'desc');
        if ($request->input("ordernumber")) {
            $model = $model->where('ordernumber', 'like', '%' . $request->input("ordernumber") . '%');
        }
        if ($request->input("status")) {
            $model = $model->where('status', $request->input("status"));
        }
        if ($request->input("time") == 1) {
            $model = $model->where('created_at', '>=', date('Y-m-d') . " 00:00:00")->where('created_at', '<=', date('Y-m-d') . " 23:59:59");
        }
        $result = $model->select($this->field)->with(['user' => function ($query) {
            $query->select('id', 'name', 'username');
        }, 'bank_codes'])->paginate($this->pageSize);
        $this->data['lists'] = DepositOrderCollection::make($result);
        return $this->success('ok', $this->data);
    }

    public function logs(Request $request)
    {
        $model = new DepositOrder();
        $model = $model->where('user_id', $request->user()->id)->where('status', 5);
        if ($request->input("ordernumber")) {
            $model = $model->where('ordernumber', 'like', '%' . $request->input("ordernumber") . '%');
        }
        if ($request->input("time") == 1) {
            $model = $model->where('created_at', '>=', date('Y-m-d') . " 00:00:00")->where('created_at', '<=', date('Y-m-d') . " 23:59:59");
        }
        $result = $model->select($this->field)->with(['user' => function ($query) {
            $query->select('id', 'name', 'username');
        }, 'bank_codes' => function ($query) {
            $query->select('id', 'name');
        }])->paginate($this->pageSize);
        $this->data['lists'] = DepositOrderCollection::make($result);
        return $this->success('ok', $this->data);
    }

    public function confirmPay(DepositOrderConfirmPayRequest $request)
    {
        DB::beginTransaction();
        try {
            $order = DepositOrder::where('id', $request->input('order_id'))->where('user_id', $request->user()->id)->whereIn('status', [1, 3, 5, 7])->first(['id', 'pay_amount', 'user_bank_id', 'ordernumber', 'amount', 'status']);
            if ($order) {
                $amount = floatval($request->input('amount'));
                if (floatval($order->pay_amount) != $amount) {
                    throw new \Exception("确认金额与待支付金额不一致，请联系客服处理");
                }
                if (intval($order->status) === 5) {
                    DB::commit();
                    return $this->success();
                }
                if($amount != floatval($order->amount)){ //开启浮动金额
                    $amount = $order->amount;
                }
                $confirmPaySuccessService = App::makeWith(ConfirmPaySuccessService::class, ['filename' => LogConstService::DEPOSIT_ORDER_LOG_PREFIX . $order->id]);
                $confirmPaySuccessService->excute($order->id, $amount, true);
                App::make(UserBankActionLogService::class)->excute(['type' => 1, 'type_id' => $request->user()->id, 'action' => 8, 'user_bank_id' => $order->user_bank_id, "remark" => "订单号：" . $order->ordernumber]);

                app(SystemLogService::class)->logAction(
                    actionKey: 'api.v2.users.deposit-orders.confirm-pay',
                    text: '确认 代收订单',
                    subject: $order,
                    properties: [
                        'user_id' => $request->user()->id,
                        'order_id' => $order->id,
                        'ordernumber' => $order->ordernumber,
                        'amount' => $amount,
                        'user_bank_id' => $order->user_bank_id,
                    ],
                    remark: sprintf('确认 代收订单 %s', $order->ordernumber),
                    logType: 'operation',
                    actionMethod: 'POST',
                    appType: 'user',
                    user: $request->user()
                );

                DB::commit();
                return $this->success();
            }
            throw new \Exception("非法操作");
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($e->getMessage());
        }

    }


}
