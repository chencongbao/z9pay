<?php

namespace App\Http\Controllers\Api\V2;

use App\Models\User;
use App\Models\ReportUser;
use App\Models\DepositOrder;
use App\Models\UserRelation;
use Illuminate\Http\Request;
use App\Models\TransferOrder;
use App\Models\UserBalanceLog;
use App\Models\ReportUserAgent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;
use App\Http\Resources\UserCollection;
use App\Services\Common\SystemLogService;
use App\Services\Order\OrderCacheService;
use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\DepositOrderCollection;
use App\Http\Resources\TransferOrderCollection;
use App\Http\Resources\UserBalanceLogCollection;
use App\Services\User\UserMonthTotalAmountService;
use App\Http\Requests\Api\V2\UserUpdatePasswordRequest;
use App\Services\User\TodayDepositOrderTotalAmountService;
use App\Services\User\TodayTransferOrderTotalAmountService;
use App\Services\User\GetUserRemainingDepositService;
use App\Services\Cache\DepositOrder\CacheDepositOrderInfoService;

class UserController extends ApiController
{
    public $deposit_order_field = ['id','user_id','ordernumber','card_name','actual_amount','amount','status','updated_at','success_time','created_at','account_type','bank_id','collection_card_no','collection_name','pay_certificate','pay_status','pay_name','user_commission','pay_amount'];

    public $transfer_order_field = ['id','actual_amount','status','user_id','amount','holder_name','card_no','bank_name','ordernumber','updated_at','bank_code','created_at','user_commission'];

    public function index(Request $request)
    {
        $this->data['user_number'] = 0;
        $this->data['deposit_amount'] = $request->user()->deposit_amount;
        $this->data['deposit_remaining_amount'] = 0;
        $this->data['commission_balance_amount'] = floatval($request->user()->commission_balance_amount);
        $this->data['balance_amount'] = floatval($request->user()->balance_amount);

        $this->data['deposit_total_amount'] = 0;
        $this->data['total_deposit_total_amount'] = 0;


        $this->data['lists'] = [];
        if($request->user()->is_agent == 0){
            if($request->user()->deposit_amount > 0){
                $remainingDeposit = App::make(GetUserRemainingDepositService::class)->excute($request->user()->id);
                $this->data['deposit_remaining_amount'] = sprintf("%.2f", $remainingDeposit['remaining_deposit'] ?? 0);
            }

            $deposit_today_amount = App::make(TodayDepositOrderTotalAmountService::class)->excute($request->user()->id,0,$request->user()->is_agent);
            $transfer_today_amount = App::make(TodayTransferOrderTotalAmountService::class)->excute($request->user()->id,0,$request->user()->is_agent);
            $this->data['today_amount'] = bob_amount_format($deposit_today_amount + $transfer_today_amount);
            $this->data['total_amount'] = App::make(UserMonthTotalAmountService::class)->excute($request->user()->id,0,$request->user()->is_agent);


            $user_list_result = ReportUser::where('uid',$request->user()->id)->limit(6)->orderBy('date_add','desc')->get(['date_add','deposit_order_total_amount','transfer_order_total_amount','deposit_commission','transfer_commission']);
            if(!$user_list_result->isEmpty()){
                foreach($user_list_result as $item){
                    $this->data['lists'][] = [
                        'deposit_income' => bob_amount_format($item->deposit_commission),
                        'transfer_income' => bob_amount_format($item->transfer_commission),
                        'deposit_order_total_amount' =>bob_amount_format($item->deposit_order_total_amount),
                        'transfer_order_total_amount' => bob_amount_format($item->transfer_order_total_amount),
                        'total_income' => bob_amount_format($item->deposit_commission + $item->transfer_commission),
                        'date' => $item->date_add,
                    ];
                }
            }
        }else{
            $this->data['user_number'] = User::where('is_agent',0)->where('pid',$request->user()->id)->count();

            $deposit_today_amount = App::make(TodayDepositOrderTotalAmountService::class)->excute($request->user()->id,0,$request->user()->is_agent);
            $transfer_today_amount = App::make(TodayTransferOrderTotalAmountService::class)->excute($request->user()->id,0,$request->user()->is_agent);
            $this->data['today_amount'] = bob_amount_format($deposit_today_amount + $transfer_today_amount);
            $this->data['total_amount'] = App::make(UserMonthTotalAmountService::class)->excute($request->user()->id,0,$request->user()->is_agent);


            $user_list_result = ReportUserAgent::where('aid',$request->user()->id)->limit(6)->orderBy('date_add','desc')->get(['date_add','deposit_order_total_amount','transfer_order_total_amount','deposit_commission','transfer_commission']);
            if(!$user_list_result->isEmpty()){
                foreach($user_list_result as $item){
                    $this->data['lists'][] = [
                        'deposit_income' => bob_amount_format($item->deposit_commission),
                        'transfer_income' => bob_amount_format($item->transfer_commission),
                        'deposit_order_total_amount' =>bob_amount_format($item->deposit_order_total_amount),
                        'transfer_order_total_amount' => bob_amount_format($item->transfer_order_total_amount),
                        'total_income' => bob_amount_format($item->deposit_commission + $item->transfer_commission),
                        'date' => $item->date_add,
                    ];
                }
            }
        }
        return $this->success('',$this->data);
    }


    public function updatePassword(UserUpdatePasswordRequest $request)
    {
        $data = $request->only(['old_password','password']);
        $user = User::find($request->user()->id);
        if($user){
            if (!Hash::check($data['old_password'], $user->password)) {
                return $this->error('旧密码错误');
            }
            $user->password_changed_at = date('Y-m-d H:i:s');
            $user->password = bcrypt($data['password']);
            $result = $user->save();
            if($result){
                return $this->success();
            }
        }
        return $this->error();
    }



    public function teamUserIndex(Request $request)
    {
        $model = new User();
        $model = $model->where('pid',$request->user()->id);
        if($request->input('type')  == 1){
            $model = $model->where('is_agent',0);
        }
        if($request->input('type')  == 2){
            $model = $model->where('is_agent',1);
        }
        if($request->has('status') && $request->input('status') >= 0){
            $model = $model->where('status',$request->input('status'));
        }
        if($request->input('username')){
            $keyword = $request->input('username');
            $model = $model->where(function ($query) use ($keyword) {
                $query->where('username','like','%'.$keyword.'%')->orWhere('name','like','%'.$keyword.'%')->orWhere('id','like',$keyword);
            });
        }
        $result = $model->paginate($this->pageSize);
        $this->data['lists'] = UserCollection::make($result);
        return $this->success("",$this->data);
    }



    public function teamDepositOrderIndex(Request $request)
    {
        $model = new DepositOrder();
        $model = $model->where("user_agent1_id",$request->user()->id)->orderBy('id','desc');
        if($request->input('status') > 0){
            $model = $model->where('status',$request->input('status'));
        }
        if($request->input('ordernumber')){
            $model = $model->where('ordernumber',$request->input('ordernumber'));
        }
        if($request->input("time") == 1){
            $model = $model->where('created_at','>=',date('Y-m-d')." 00:00:00")->where('created_at','<=',date('Y-m-d')." 23:59:59");
        }
        $result = $model->select($this->deposit_order_field)->with(['user'=>function ($query) {
            $query->select('id','name','username');
        }])->paginate($this->pageSize);
        $this->data['lists'] = DepositOrderCollection::make($result);
        return $this->success("",$this->data);
    }

    public function teamTransferOrderIndex(Request $request)
    {
        $model = new TransferOrder();
        $model = $model->where("user_agent1_id",$request->user()->id)->orderBy('id','desc');
        if($request->input('status') > 0){
            $model = $model->where('status',$request->input('status'));
        }
        if($request->input('ordernumber')){
            $model = $model->where('ordernumber','like','%'.$request->input('ordernumber').'%');
        }
        if($request->input("time") == 1){
            $model = $model->where('created_at','>=',date('Y-m-d')." 00:00:00")->where('created_at','<=',date('Y-m-d')." 23:59:59");
        }
        $result = $model->select($this->transfer_order_field)->with(['user'=>function ($query) {
            $query->select('id','name','username');
        }])->paginate($this->pageSize);
        $this->data['lists'] = TransferOrderCollection::make($result);
        return $this->success("",$this->data);
    }

    public function teamBalanceLogIndex(Request $request)
    {
        $model = new UserBalanceLog();
        $model = $model->where("user_id",$request->user()->id)->whereIn('type',[1,2]);
        if($request->input("time") == 1){
            $model = $model->where('created_at','>=',date('Y-m-d')." 00:00:00")->where('created_at','<=',date('Y-m-d')." 23:59:59");
        }
        if($request->input("type") > 0){
            $model = $model->where('type',$request->input('type'));
        }
        if($request->input("ordernumber")){
            $orderMatched = false;
            if(mb_substr($request->input("ordernumber"), 0, 1) == 'D'){
                $result = App::make(CacheDepositOrderInfoService::class)->excute($request->input("ordernumber"));
                if(!empty($result)){
                    $model = $model->where('type',1)->where('type_id',$result['id']);
                    $orderMatched = true;
                }
            }
            if(mb_substr($request->input("ordernumber"), 0, 1) == 'T'){
                $result = App::make(OrderCacheService::class)->getTransferByOrdernumber($request->input('ordernumber'));
                if(!empty($result)){
                    $model = $model->where('type',2)->where('type_id',$result['id']);
                    $orderMatched = true;
                }
            }
            if(!$orderMatched) $model = $model->whereRaw('1 = 0');
        }
        $result = $model->orderBy('id','desc')->paginate($this->pageSize);
        $this->data['lists'] = UserBalanceLogCollection::make($result);
        return $this->success("",$this->data);
    }

    public function setDepositNotice(Request $request)
    {
        $user = $request->user();
        $old = (int)$user->deposit_notice;
        $deposit_notice = $old === 1 ? 0 : 1;
        User::where('id',$user->id)->update(['deposit_notice'=>$deposit_notice]);

        app(SystemLogService::class)->logAction(
            actionKey: 'api.v2.users.users.set-deposit-notice',
            text: '设置 代收通知',
            subject: $user,
            properties: [
                'user_id' => $user->id,
                'old_value' => $old,
                'new_value' => $deposit_notice,
            ],
            remark: sprintf('设置 代收通知 %d -> %d', $old, $deposit_notice),
            logType: 'operation',
            actionMethod: 'PUT',
            appType: 'user',
            user: $user
        );

        $this->data['deposit_notice'] = bob_lock($deposit_notice);
        return $this->success('',$this->data);
    }

    public function setTransferNotice(Request $request)
    {
        $user = $request->user();
        $old = (int)$user->transfer_notice;
        $transfer_notice = $old === 1 ? 0 : 1;
        User::where('id',$user->id)->update(['transfer_notice'=>$transfer_notice]);

        app(SystemLogService::class)->logAction(
            actionKey: 'api.v2.users.users.set-transfer-notice',
            text: '设置 代付通知',
            subject: $user,
            properties: [
                'user_id' => $user->id,
                'old_value' => $old,
                'new_value' => $transfer_notice,
            ],
            remark: sprintf('设置 代付通知 %d -> %d', $old, $transfer_notice),
            logType: 'operation',
            actionMethod: 'PUT',
            appType: 'user',
            user: $user
        );

        $this->data['transfer_notice'] = bob_lock($transfer_notice);
        return $this->success('',$this->data);
    }

    public function setAutoRefresh(Request $request)
    {
        $user = $request->user();
        $old = (int)$user->auto_refresh;
        $auto_refresh = $old === 1 ? 0 : 1;
        User::where('id',$user->id)->update(['auto_refresh'=>$auto_refresh]);

        app(SystemLogService::class)->logAction(
            actionKey: 'api.v2.users.users.set-auto-refresh',
            text: '设置 自动刷新',
            subject: $user,
            properties: [
                'user_id' => $user->id,
                'old_value' => $old,
                'new_value' => $auto_refresh,
            ],
            remark: sprintf('设置 自动刷新 %d -> %d', $old, $auto_refresh),
            logType: 'operation',
            actionMethod: 'PUT',
            appType: 'user',
            user: $user
        );

        $this->data['auto_refresh'] = bob_lock($auto_refresh);
        return $this->success('',$this->data);
    }

    public function balanceLogIndex(Request $request)
    {
        $model = new UserBalanceLog();
        $model = $model->where('user_id',$request->user()->id);
        if($request->user()->is_agent == 0){
            $model = $model->whereIn('type',[1,2,3,10]);
        }
        $result = $model->orderBy('id','desc')->paginate($this->pageSize);
        $this->data['lists'] = UserBalanceLogCollection::make($result);
        return $this->success("",$this->data);
    }
}
