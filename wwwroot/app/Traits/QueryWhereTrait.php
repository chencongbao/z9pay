<?php

namespace App\Traits;

trait QueryWhereTrait
{

    public function queryWhere($data,$model){
        if(isset($data['status'])){
            $model = $model->where('status',intval($data['status']));
        }
        if(isset($data['merchant_agent1_id'])){
            $model = $model->where('merchant_agent1_id',intval($data['merchant_agent1_id']));
        }
        if(isset($data['user_agent1_id'])){
            $model = $model->where('user_agent1_id',intval($data['user_agent1_id']));
        }
        if(isset($data['user_agent2_id'])){
            $model = $model->where('user_agent2_id',intval($data['user_agent2_id']));
        }
        if(isset($data['user_agent3_id'])){
            $model = $model->where('user_agent3_id',intval($data['user_agent3_id']));
        }
        if(isset($data['merchant_agent2_id'])){
            $model = $model->where('merchant_agent2_id',intval($data['merchant_agent2_id']));
        }
        if(isset($data['begin_time'])){
            $model = $model->where('created_at','>=',$data['begin_time']);
        }
        if(isset($data['end_time'])){
            $model = $model->where('created_at','<=',$data['end_time']);
        }
        return $model;
    }
}
