<?php

namespace App\Admin\Extensions\Filter;

use Dcat\Admin\Admin;
use Dcat\Admin\Grid\Filter\Between;
use Illuminate\Support\Arr;

class MonthRangeDatetime extends Between
{
    protected $view = 'admin::filter.month-range-datetime';

    protected $maxMonth;

    public function __construct($column, $label = '')
    {
        parent::__construct($column, $label);

        $this->datetime();
    }

    public function maxMonth($month = 1)
    {
        $this->maxMonth = max(1, (int) $month);

        return $this->addVariables([
            'maxMonth' => $this->maxMonth,
        ]);
    }

    public function datetime($options = [])
    {
        Admin::css('/vendor/plugins/daterangepicker/daterangepicker.css');
        Admin::js('/vendor/dcat-admin/dcat/plugins/moment/moment-with-locales.min.js');
        Admin::js('/vendor/plugins/daterangepicker/daterangepicker.js');

        $options['format'] = Arr::get($options, 'format', 'YYYY-MM-DD HH:mm:ss');
        $options['locale'] = Arr::get($options, 'locale', config('app.locale'));

        return $this->addVariables([
            'dateOptions' => $options,
            'maxMonth' => $this->maxMonth,
        ]);
    }

    public function condition($inputs)
    {
        if (! Arr::has($inputs, $this->column)) {
            return;
        }

        $this->value = Arr::get($inputs, $this->column);

        $value = array_filter($this->value, function ($val) {
            return $val !== '';
        });

        if ($this->timestamp) {
            $value = array_map(function ($v) {
                if ($v) {
                    return strtotime($v);
                }
            }, $value);
        }

        if (empty($value)) {
            return;
        }

        if ($this->maxMonth && isset($value['start'], $value['end']) && $this->isOverRange($value['start'], $value['end'])) {
            return $this->buildCondition(function ($query) {
                $query->whereRaw('1 = 0');
            });
        }

        if (! isset($value['start']) && isset($value['end'])) {
            return $this->buildCondition($this->column, '<=', $value['end']);
        }

        if (! isset($value['end']) && isset($value['start'])) {
            return $this->buildCondition($this->column, '>=', $value['start']);
        }

        $this->query = 'whereBetween';

        return $this->buildCondition($this->column, [$value['start'], $value['end']]);
    }

    protected function isOverRange($start, $end)
    {
        $startTime = is_numeric($start) ? (int) $start : strtotime($start);
        $endTime = is_numeric($end) ? (int) $end : strtotime($end);

        if (! $startTime || ! $endTime) {
            return false;
        }

        $maxEndTime = strtotime("+{$this->maxMonth} month", $startTime);

        return $endTime > $maxEndTime;
    }
}
