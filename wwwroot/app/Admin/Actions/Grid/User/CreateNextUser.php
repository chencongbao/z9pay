<?php

namespace App\Admin\Actions\Grid\User;

use Dcat\Admin\Admin;
use Dcat\Admin\Grid\RowAction;

class CreateNextUser extends RowAction
{
    private const TITLE = '新增下级金主';
    private const SELECTOR = 'create-next-user';

    public function title()
    {
        return '<i class="feather icon-plus"></i> ' . self::TITLE . ' &nbsp;&nbsp;';
    }

    protected function script(): string
    {
        $url = Admin::app()->getRoute('tusers.create');
        $selector = self::SELECTOR;

        return <<<JS
$('.{$selector}').on('click', function () {
    Dcat.reload('{$url}?pid=' + $(this).data('id'));
});
JS;
    }

    public function html()
    {
        $this->setHtmlAttribute(['data-id' => $this->getKey(), 'class' => self::SELECTOR]);

        return parent::html();
    }
}
