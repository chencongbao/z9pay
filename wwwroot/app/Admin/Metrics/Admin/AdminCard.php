<?php

namespace App\Admin\Metrics\Admin;

use Dcat\Admin\Widgets\Metrics\Card;

class AdminCard extends Card
{
    protected $view = 'extendtions.dcat.widgets.card';

    protected $height = 0;

    public function addScript()
    {
        $id = $this->id();
        $event = 'ajaxError.adminMetric' . str_replace('-', '', $id);
        $timer = 'adminMetricTimer' . str_replace('-', '', $id);

        $this->fetched(<<<JS
clearTimeout(window.{$timer});
$(document).off('{$event}');
JS
        );

        $script = parent::addScript();

        return $this->script = $script . <<<JS
(function () {
    var \$card = $('#{$id}');
    var releaseLoading = function () {
        \$card.loading(false);
        var \$content = \$card.find('.metric-content');
        if (!\$.trim(\$content.text())) {
            \$content.text('--');
        }
    };

    $(document).off('{$event}').on('{$event}', function (event, xhr, settings) {
        var data = settings && settings.data;
        var requestKey = data && typeof data === 'object' ? data._key : '';

        if (!requestKey && typeof data === 'string') {
            var matched = data.match(/(?:^|&)_key=([^&]*)/);
            requestKey = matched ? decodeURIComponent(matched[1].replace(/\+/g, ' ')) : '';
        }

        if (requestKey === '{$this->getUriKey()}') {
            clearTimeout(window.{$timer});
            $(document).off('{$event}');
            releaseLoading();
        }
    });

    window.{$timer} = setTimeout(releaseLoading, 10000);
})();
JS;
    }
}
