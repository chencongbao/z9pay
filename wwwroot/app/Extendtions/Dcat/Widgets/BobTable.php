<?php

namespace App\Extendtions\Dcat\Widgets;

use Dcat\Admin\Admin;
use Dcat\Admin\Support\Helper;
use Illuminate\Support\Arr;
use Dcat\Admin\Widgets\Widget;

class BobTable extends Widget
{
    /**
     * @var string
     */
    public $view = 'extendtions.dcat.widgets.table';

    protected $tableClass;

    /**
     * @var array
     */
    protected $headers = [];

    /**
     * @var array
     */
    protected $rows = [];


    /**
     * @var array
     */
    protected $bgColor = [];

    /**
     * @var int
     */
    protected $depth = 0;


    protected $fold = false;

    protected $defaultShowLine = 1;

    private $width = "";

    private $search = [];

    /**
     * Table constructor.
     *
     * @param  array  $headers
     * @param  mixed  $rows
     * @param  array  $style
     */
    public function __construct($headers = [], $rows = false, $style = [],$bgColor = [],$defaultShowLine = 1)
    {
        if ($rows === false) {
            $rows = $headers;
            $headers = [];
        }
        $this->class('table default-table');
        $this->setTableId();

        $this->setHeaders($headers);
        $this->setRows($rows);
        $this->setStyle($style);
        $this->setBgColor($bgColor);
        $this->setDefaultLine($defaultShowLine);
    }

    public function setTableId(){
        $this->tableClass = "table-".uniqid();
    }

    /**
     * Set table headers.
     *
     * @param  array  $headers
     * @return $this
     */
    public function setHeaders($headers = [])
    {
        $this->headers = $headers;

        return $this;
    }

    public function setSearch($data = []){
        $this->search = $data;

        return $this;
    }

    /**
     * @param  int  $depth
     * @return $this
     */
    public function depth(int $depth)
    {
        $this->depth = $depth;

        return $this;
    }

    protected function addScript()
    {
        $defaultShowLine = $this->defaultShowLine + 1;
        $script = <<<JS
Dcat.ready(function () {
    $(document).off('click', '.showTable').on('click', '.showTable', function () {
        $(this).prev("table").find('tr').removeClass("hidden");
        $(this).addClass("hidden");
         $(this).next(".backTable").removeClass("hidden");
    });
    $(document).off('click', '.backTable').on('click', '.backTable', function () {
        let line = $(this).data('line');
        $(this).parent().find("table>tbody tr:nth-child(n+"+(parseInt(line) + 1)+")").addClass("hidden");
        $(this).addClass("hidden");
         $(this).prev(".showTable").removeClass("hidden");
    });
});
JS;
        Admin::script($script);
    }

    /**
     * Set table rows.
     *
     * @param  array  $rows
     * @return $this
     */
    public function setRows($rows = [])
    {
        if ($rows && ! Arr::isAssoc(Helper::array($rows, false))) {
            $this->rows = $rows;

            return $this;
        }

        $noTrPadding = false;

        foreach ($rows as $key => $item) {
            if (is_array($item)) {
                if (Arr::isAssoc($item)) {
                    $borderLeft = $this->depth ? 'table-left-border-nofirst' : 'table-left-border';

                    $item = static::make($item)
                        ->depth($this->depth + 1)
                        ->class('table-no-top-border '.$borderLeft, true)
                        ->render();

                    if (! $noTrPadding) {
                        $this->class('table-no-tr-padding', true);
                    }
                    $noTrPadding = true;
                } else {
                    $item = json_encode($item, JSON_UNESCAPED_UNICODE);
                }
            }

            $this->rows[] = [$key, $item];
        }

        return $this;
    }

    /**
     * Set table style.
     *
     * @param  array  $style
     * @return $this
     */
    public function setStyle($style = [])
    {
        if ($style) {
            $this->class(implode(' ', (array) $style), true);
        }

        return $this;
    }

    public function setWith($width = ""){
        $this->width = $width;
    }

    public function setBgColor($color = [])
    {
        $this->bgColor = $color;
    }

    public function setFold($value = false){
        $this->fold = $value;
    }

    public function setDefaultLine($value = 1){
        $this->defaultShowLine = $value;
    }

    /**
     * Render the table.
     *
     * @return string
     */
    public function render()
    {
        $this->addScript();
        $vars = [
            'headers'    => $this->headers,
            'rows'       => $this->rows,
            'attributes' => $this->formatHtmlAttributes(),
            'fold' => $this->fold,
            'width' => $this->width,
            'bgColor' => $this->bgColor,
            'defaultShowLine' => $this->defaultShowLine,
            'tableClass' => $this->tableClass,
            'search' => $this->search
        ];

        return view($this->view, $vars)->render();
    }

    /**
     * @return $this
     */
    public function withBorder()
    {
        $this->class('table-bordered', true);

        return $this;
    }
}
