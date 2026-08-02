<?php

namespace App\Http\Resources;

use App\Models\DepositOrder;
use App\Models\TransferOrder;
use Illuminate\Support\Facades\App;
use App\Services\Order\OrderCacheService;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Services\Cache\User\GetUserDetailService;
use App\Services\Cache\DepositOrder\CacheDepositOrderInfoService;

class UserBalanceLogResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return $this->filterFields([
            'id' => $this->id,
            'order_type_text' => $this->order_type(),
            'amount' => bob_amount_format($this->amount),
            'create_time' => date('Y-m-d H:i:s', strtotime($this->created_at)),
            'order' => $this->getOrder(),
            'balance_amount' => bob_amount_format($this->balance_amount),
            'new_order_type' => $this->type_text,
            'type_balance_amount' => bob_amount_format($this->type_balance_amount),
            "user_bname" => $this->getUserBname()
        ]);
    }

    private function order_type()
    {
        switch ($this->order_type) {
            case 1:
                return "Q币购入";
                break;
            case 2:
                return "Q币售出";
                break;
            default:
                return "人工操作";
        }
    }

    private function getOrder()
    {
        if($this->is_agent == 1){
            switch ($this->order_type) {
                case 1:
                    return App::make(OrderCacheService::class)->getDepositById($this->type_id);
                    break;
                case 2:
                    return App::make(OrderCacheService::class)->getTransferById($this->type_id);
                default:
                    return;
            }
        }
        return;
    }

    private function getUserBname()
    {
        if($this->is_agent == 1){
            if($this->order_type == 1){
                $result = App::make(OrderCacheService::class)->getDepositById($this->type_id);
                if(!empty($result) && isset($result['user_id']) && $result['user_id'] > 0){
                    $user = App::make(GetUserDetailService::class)->excute($result['user_id']);
                    if(!empty($user)) return $user['bname'];
                }
            }
            if($this->order_type == 2){
                $result = App::make(OrderCacheService::class)->getTransferById($this->type_id);
                if(!empty($result) && isset($result['user_id']) && $result['user_id'] > 0){
                    $user = App::make(GetUserDetailService::class)->excute($result['user_id']);
                    if(!empty($user)) return $user['bname'];
                }
            }
        }
        return;
    }
}
