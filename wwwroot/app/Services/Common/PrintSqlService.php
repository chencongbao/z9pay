<?php

namespace App\Services\Common;

use App\Extendtions\Sql\PrintSql;
use App\Traits\ServiceTraits;

class PrintSqlService
{
    use ServiceTraits;

    public function excute()
    {
        if (config('app.env') === 'local') {
            $sql = new PrintSql();
            $sql->tosql();
        }
    }
}
