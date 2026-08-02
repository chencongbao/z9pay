<?php

namespace App\Admin\Extensions\Displayers;

use Dcat\Admin\Admin;
use Dcat\Admin\Grid\Displayers\AbstractDisplayer;

class Status extends AbstractDisplayer
{
    public function display()
    {
        $value = intval($this->value);
        $bgColor = $value == 1 ? Admin::color()->green() : "#ef5228";
        return '<span class="label" style="background:'.$bgColor.'">'.admin_trans_option($value,"status_text").'</span>';
    }
}
