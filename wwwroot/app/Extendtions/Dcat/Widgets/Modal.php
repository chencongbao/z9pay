<?php

namespace App\Extendtions\Dcat\Widgets;

use Dcat\Admin\Admin;
use Dcat\Admin\Support\Helper;
use Illuminate\Support\Str;

class Modal extends \Dcat\Admin\Widgets\Modal
{

    function __construct($id ="",$title = null, $content = null)
    {
        $this->id($id);
        $this->title($title);
        $this->content($content);

        $this->class('modal fade');
    }
}
