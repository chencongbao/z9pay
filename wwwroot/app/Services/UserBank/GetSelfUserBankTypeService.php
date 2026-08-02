<?php

namespace App\Services\UserBank;

use App\Traits\ServiceTraits;

class GetSelfUserBankTypeService
{
    use ServiceTraits;

    public function excute()
    {
        return collect(config('default.user_bank_type'))->map(function ($value,$key){
            return [
                'id' => $key,
                'name' => $value
            ];
        });
    }
}
