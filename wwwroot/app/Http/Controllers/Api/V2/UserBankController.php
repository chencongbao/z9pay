<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V2\UserBankStoreRequest;
use App\Http\Resources\UserBankCollection;
use App\Http\Resources\UserBankResource;
use App\Models\BankCode;
use App\Models\UserBank;
use App\Services\Common\SystemLogService;
use App\Services\UserBank\GetSelfUserBankTypeService;
use App\Services\UserBank\UserBankActionLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Zxing\QrReader;

class UserBankController extends ApiController
{


    public function index(Request $request)
    {
        $model = new UserBank();
        $model = $model->where('user_id', $request->user()->id)->orderBy('id','desc');
        if($request->input("keyword")){
            $model = $model->where(function ($q)use($request){
                $q->where('name', 'like','%'.$request->input("keyword").'%')->orWhere('card_no', 'like','%'.$request->input("keyword").'%');
            });
        }
        if($request->has("collection_status") && $request->input('collection_status') >= 0){
            $model = $model->where('collection_status', $request->input("collection_status"));
        }
        if($request->input("account_type")){
            $model = $model->where('account_type', $request->input("account_type"));
        }
        $result = $model->orderBy('collection_status','desc')->orderBy('id','desc')->paginate($this->pageSize);
        $this->data['lists'] = UserBankCollection::make($result);
        if(empty($request->user()->account_types)){
            $this->data['accountTypeList'] = array_values(App::make(GetSelfUserBankTypeService::class)->excute()->toArray());
        }else{
            $this->data['accountTypeList'] = array_values(App::make(GetSelfUserBankTypeService::class)->excute()->whereIn('id',explode(",",$request->user()->account_types))->toArray());
        }
        $this->data['banks'] = BankCode::where('currency_id',1)->get()->map(function ($item){
            $item->seleted = 0;
            return $item;
        });
        $this->data['payments'] = array_values(collect(config('payment'))->filter(function ($item){
            return $item['id'] > 0;
        })->map(function ($item){
            $item['seleted'] = 0;
            $item['name'] = $item['name']."【".$item['code']."】";
            return $item;
        })->toArray());
        $this->data['action_limit_card'] = $request->user()->action_limit_card;
        return $this->success('', $this->data);
    }


    public function store(UserBankStoreRequest $request)
    {
        DB::beginTransaction();
        try {
            if($request->user()->self_add_bank == 0) throw new \Exception('非法操作');
            $data = $request->only(['name','bank_id','account_type','card_no','limint_min_amount','limint_max_amount','limint_day_amount','payment_qrcode','limit_day_order_number','payment_id','payment_qrcode_url']);
            $data = $this->removeLimitFieldsWithoutPermission($data, $request);
            if(!empty($request->user()->account_types)){
                if(!in_array($data['account_type'],explode(",",$request->user()->account_types))) throw new \Exception('非法操作');
            }

            $data['user_id'] = $request->user()->id;
            $data['collection_status'] = 0;
            $data['bank_id'] = $data['bank_id'] ?? 0;
            if($data['account_type'] == 1 && $data['bank_id'] <= 0){
                throw new \Exception("请选择银行");
            }
            if(in_array($data['account_type'],[1,2,4,6])){
                if($data['card_no'] == ''){
                    throw new \Exception("请填写收款卡号");
                }
                $userback = UserBank::where('card_no',$data['card_no'])->where('account_type',$data['account_type'])->withTrashed()->first(['id']);
                if($userback){
                    throw new \Exception("当前收款账号已存在");
                }

            }
            if(in_array($data['account_type'],[3,5,14,28])){
                if(empty($data['payment_qrcode'])){
                    throw new \Exception("请上传收款码");
                }
            }
            $result = UserBank::create($data);
            App::make(UserBankActionLogService::class)->excute(['type'=>1,'type_id'=>$request->user()->id,'action'=>1,'user_bank_id'=>$result->id,'remark'=>json_encode($result->toArray())]);
            DB::commit();
            return $this->success('',['data'=>UserBankResource::make($result)]);
        }catch (\Exception $e){
            DB::rollBack();
            return $this->error($e->getMessage());
        }
    }


    public function update($id,UserBankStoreRequest $request)
    {
        DB::beginTransaction();
        try {
            if($request->user()->self_add_bank == 0) throw new \Exception('非法操作');
            $model = UserBank::where('id',$id)->where('user_id',$request->user()->id)->first();
            if($model){
                $data = $request->only(['name','bank_id','account_type','card_no','limint_min_amount','limint_max_amount','limint_day_amount','payment_qrcode','limit_day_order_number','payment_id','payment_qrcode_url']);
                $data = $this->removeLimitFieldsWithoutPermission($data, $request);
                if(!empty($request->user()->account_types) && !in_array($data['account_type'],explode(",",$request->user()->account_types))) throw new \Exception('非法操作');
                $data['bank_id'] = $data['bank_id'] ?? 0;
                if($data['account_type'] == 1 && $data['bank_id'] <= 0){
                    throw new \Exception("请选择银行");
                }
                if(in_array($data['account_type'],[1,2,4,6])){
                    if($data['card_no'] == ''){
                        throw new \Exception("请填写收款卡号");
                    }
                    $userback = UserBank::where('card_no',$data['card_no'])->where('account_type',$data['account_type'])->where('id','<>',$id)->first();
                    if($userback){
                        throw new \Exception("当前收款账号已存在");
                    }
                }
                if(in_array($data['account_type'],[3,5,14,28])){
                    if(empty($data['payment_qrcode'])){
                        throw new \Exception("请上传收款码");
                    }
                }
                $model->fill($data);
                $model->save();
                $model->refresh();
                App::make(UserBankActionLogService::class)->excute(['type'=>1,'type_id'=>$request->user()->id,'action'=>2,'user_bank_id'=>$model->id,'remark'=>json_encode($model->toArray())]);
                DB::commit();
                return $this->success('',['data'=>UserBankResource::make($model)]);
            }
            throw new \Exception("收款卡不存在");
        }catch (\Exception $e){
            DB::rollBack();
            return $this->error($e->getMessage());
        }

    }

    public function destroy($id,Request $request)
    {
        DB::beginTransaction();
        try {
            if($request->user()->self_add_bank == 0 || $request->user()->action_delete != 1) throw new \Exception('非法操作');
            $model = UserBank::where('id',$id)->where('user_id',$request->user()->id)->first();
            if (!$model) {
                throw new \Exception('收款卡不存在');
            }

            $model->delete();
            App::make(UserBankActionLogService::class)->excute(['type'=>1,'type_id'=>$request->user()->id,'action'=>3,'user_bank_id'=>$id]);

            app(SystemLogService::class)->logAction(
                actionKey: 'api.v2.users.user-banks.destroy',
                text: '删除 收款卡',
                subject: $model,
                properties: [
                    'user_id' => $request->user()->id,
                    'user_bank_id' => $id,
                ],
                remark: '删除 收款卡',
                logType: 'operation',
                actionMethod: 'DELETE',
                appType: 'user',
                user: $request->user()
            );
            DB::commit();
            return $this->success();
        }catch (\Exception $e){
            DB::rollBack();
            return $this->error($e->getMessage());
        }

    }


    public function clearBank(Request $request)
    {
        DB::beginTransaction();
        try {
            if($request->user()->self_add_bank == 0 || $request->user()->action_delete != 1) throw new \Exception('非法操作');
            $result = UserBank::where('user_id',$request->user()->id)->get();
            $ids = [];
            if(!$result->isEmpty()){
                foreach($result as $model){
                    $ids[] = $model->id;
                    $model->delete();
                    App::make(UserBankActionLogService::class)->excute(['type'=>1,'type_id'=>$request->user()->id,'action'=>7,'user_bank_id'=>$model->id]);
                }
            }

            app(SystemLogService::class)->logAction(
                actionKey: 'api.v2.users.user-banks.clear-bank',
                text: '清空 收款卡',
                subject: $request->user(),
                properties: [
                    'user_id' => $request->user()->id,
                    'count' => count($ids),
                    'user_bank_ids' => $ids,
                ],
                remark: '清空 收款卡',
                logType: 'operation',
                actionMethod: 'DELETE',
                appType: 'user',
                user: $request->user()
            );
            DB::commit();
            return $this->success();
        }catch (\Exception $e){
            DB::rollBack();
            return $this->error($e->getMessage());
        }

    }

    public function setStatus($id,Request $request)
    {
        DB::beginTransaction();
        try {
            if($request->user()->self_add_bank == 0) throw new \Exception('非法操作');
            $model = UserBank::where('id',$id)->where('user_id',$request->user()->id)->first(['id','collection_status']);
            if($model){
                $model->collection_status = $model->collection_status == 1 ? 0 : 1;
                $model->save();
                App::make(UserBankActionLogService::class)->excute(['type'=>1,'type_id'=>$request->user()->id,'action'=>$model->collection_status == 1 ? 5 : 6,'user_bank_id'=>$model->id]);
                DB::commit();
                return $this->success();
            }
            throw new \Exception('收款卡不存在');
        }catch (\Exception $e){
            DB::rollBack();
            return $this->error($e->getMessage());
        }
    }

    public function closeAll(Request $request)
    {
        DB::beginTransaction();
        try {
            if($request->user()->self_add_bank == 0) throw new \Exception('非法操作');
            $result = UserBank::where('user_id', $request->user()->id)
                ->where('collection_status', 1)
                ->get(['id','collection_status']);

            $ids = [];
            if(!$result->isEmpty()){
                foreach($result as $model){
                    $model->collection_status = 0;
                    $model->save();
                    $ids[] = $model->id;
                    App::make(UserBankActionLogService::class)->excute(['type'=>1,'type_id'=>$request->user()->id,'action'=>6,'user_bank_id'=>$model->id]);
                }
            }

            app(SystemLogService::class)->logAction(
                actionKey: 'api.v2.users.user-banks.close-all',
                text: '一键关闭 收款卡',
                subject: $request->user(),
                properties: [
                    'user_id' => $request->user()->id,
                    'count' => count($ids),
                    'user_bank_ids' => $ids,
                ],
                remark: '一键关闭 收款卡',
                logType: 'operation',
                actionMethod: 'PUT',
                appType: 'user',
                user: $request->user()
            );
            DB::commit();
            return $this->success('', ['count' => count($ids), 'user_bank_ids' => $ids]);
        }catch (\Exception $e){
            DB::rollBack();
            return $this->error($e->getMessage());
        }
    }

    protected function removeLimitFieldsWithoutPermission(array $data, Request $request): array
    {
        if ((int) $request->user()->action_limit_card === 1) {
            return $data;
        }

        unset(
            $data['limint_min_amount'],
            $data['limint_max_amount'],
            $data['limint_day_amount'],
            $data['limit_day_order_number']
        );

        return $data;
    }

}
