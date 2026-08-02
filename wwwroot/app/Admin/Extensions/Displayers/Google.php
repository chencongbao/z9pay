<?php

namespace App\Admin\Extensions\Displayers;

use Dcat\Admin\Admin;
use Dcat\Admin\Grid\Displayers\AbstractDisplayer;

class Google extends AbstractDisplayer
{
    public function display()
    {
        $bind_color = $this->row['google_two_fa_bind'] == 1 ? Admin::color()->green() : "#ef5228";
        return '<span style="color: '.$bind_color.'">'.admin_trans_option($this->row['google_two_fa_bind'],"bind_status_text").'</span>';
    }
}
