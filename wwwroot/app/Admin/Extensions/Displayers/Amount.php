<?php

namespace App\Admin\Extensions\Displayers;

use Dcat\Admin\Grid\Displayers\AbstractDisplayer;

class Amount extends AbstractDisplayer
{
    public function display()
    {
        return bob_unit_format($this->value);
    }
}
