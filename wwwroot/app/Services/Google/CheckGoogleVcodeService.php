<?php

namespace App\Services\Google;

use App\Traits\ServiceTraits;

class CheckGoogleVcodeService
{
    use ServiceTraits;

    public function excute($googleVcode = '')
    {
        return app(AdminGoogle2faService::class)->verify($googleVcode);
    }
}
