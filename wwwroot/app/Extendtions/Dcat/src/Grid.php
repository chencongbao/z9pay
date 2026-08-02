<?php

namespace App\Extendtions\Dcat\src;

use App\Extendtions\Dcat\src\Grid\FixColumns;

class Grid extends \Dcat\Admin\Grid
{
    public function __construct($repository = null, ?\Closure $builder = null, $request = null)
    {
        parent::__construct($repository,$builder,$request);
    }


    public function fixColumns(int $head, int $tail = -1)
    {
        $this->fixColumns = new FixColumns($this, $head, $tail);
        return $this->fixColumns;
    }

    public function renderFooter()
    {
        if (! $this->footer) {
            return '';
        }

        return <<<HTML
<div class="box-footer clearfix" style="padding: 0px">{$this->renderHeaderOrFooter($this->footer)}</div>
HTML;
    }
}
