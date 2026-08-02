<?php

namespace App\Traits;

use Illuminate\Support\Facades\Cache;

trait CacheTrait
{
    use QueryWhereTrait;

    //非今日数据求和
    public function oldTimeSum($model,$field,$cacheKey,$data = [])
    {
        if(isset($data['begin_time'])){
            if(strtotime($data['begin_time']) >= strtotime(date('Y-m-d')." 00:00:00")){
                return 0;
            }
        }
        if(isset($data['end_time'])){
            if(strtotime($data['end_time']) > strtotime(date('Y-m-d')." 00:00:00")){
                $data['end_time'] = date('Y-m-d')." 59:59:59";
            }
        }
        $key = $cacheKey."all";
        if(!empty($data)){
            $key = $cacheKey.md5(http_build_query($data));
        }
        if(Cache::has($key)){
            return floatval(Cache::get($key));
        }
        $model = $this->queryWhere($data,$model);
        $result = $model->sum($field);
        Cache::forever($cacheKey,floatval($result));
        return floatval($result);
    }


    //今日数据求和
    public function todayTimeSum($model,$field,$data = [])
    {
        if(isset($data['end_time'])){
            if(strtotime($data['end_time']) <= strtotime(date('Y-m-d')." 00:00:00")){
                return 0;
            }
        }
        $data['begin_time'] = date('Y-m-d')." 00:00:00";
        $model = $this->queryWhere($data,$model);
        $result = $model->sum($field);
        return floatval($result);
    }
}
