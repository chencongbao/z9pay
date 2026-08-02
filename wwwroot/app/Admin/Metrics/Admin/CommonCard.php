<?php

namespace App\Admin\Metrics\Admin;

class CommonCard extends AdminCard
{
    function __construct($title,$content)
    {
        parent::__construct($title);
        $this->content = $content;
    }

    protected function init()
    {
        parent::init();
        $this->style("fa fa-money red-bg");
    }
}
