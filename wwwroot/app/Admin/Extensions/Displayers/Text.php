<?php

namespace App\Admin\Extensions\Displayers;

use Dcat\Admin\Grid\Displayers\AbstractDisplayer;

class Text extends AbstractDisplayer
{
    public function display($color = 'black')
    {
        return '<span style="color:'.$color.'">'.$this->value.'</span>';
    }
}
