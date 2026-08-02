@php
    $startValue = request($name['start'], \Illuminate\Support\Arr::get($value, 'start'));
    $endValue = request($name['end'], \Illuminate\Support\Arr::get($value, 'end'));
    $displayValue = $startValue && $endValue ? $startValue . ' - ' . $endValue : '';
    $pickerId = $id['start'] . '-range';
@endphp

<div class="filter-input col-sm-{{ $width }}" style="{!! $style !!}">
    <div class="form-group">
        <div class="input-group input-group-sm">
            <div class="input-group-prepend">
                <span class="input-group-text bg-white text-capitalize"><b>{!! $label !!}</b>&nbsp;<i class="feather icon-calendar"></i></span>
            </div>

            <input autocomplete="off" type="text" class="form-control" id="{{ $pickerId }}" placeholder="{{ $label }}" value="{{ $displayValue }}">
            <input type="hidden" id="{{ $id['start'] }}" name="{{ $name['start'] }}" value="{{ $startValue }}">
            <input type="hidden" id="{{ $id['end'] }}" name="{{ $name['end'] }}" value="{{ $endValue }}">
        </div>
    </div>
</div>

<script>
    (function () {
        var input = $('#{{ $pickerId }}');
        var startInput = $('#{{ $id['start'] }}');
        var endInput = $('#{{ $id['end'] }}');
        var format = '{{ $dateOptions['format'] ?? 'YYYY-MM-DD HH:mm:ss' }}';
        var startValue = startInput.val();
        var endValue = endInput.val();

        function setValue(start, end) {
            startInput.val(start.format(format));
            endInput.val(end.format(format));
            input.val(start.format(format) + ' - ' + end.format(format));
        }

        if (! $.fn.daterangepicker) {
            return;
        }

        input.daterangepicker({
            autoUpdateInput: false,
            startDate: startValue ? moment(startValue, format) : moment().startOf('day'),
            endDate: endValue ? moment(endValue, format) : moment().endOf('day'),
            @if($maxMonth)
            maxSpan: {
                months: {{ (int) $maxMonth }}
            },
            @endif
            timePicker: true,
            timePicker24Hour: true,
            timePickerSeconds: true,
            showDropdowns: true,
            locale: {
                format: format,
                separator: ' - ',
                applyLabel: '确定',
                cancelLabel: '清空',
                fromLabel: '开始',
                toLabel: '结束',
                customRangeLabel: '自定义',
                weekLabel: '周',
                daysOfWeek: ['日', '一', '二', '三', '四', '五', '六'],
                monthNames: ['一月', '二月', '三月', '四月', '五月', '六月', '七月', '八月', '九月', '十月', '十一月', '十二月'],
                firstDay: 1
            }
        });

        var picker = input.data('daterangepicker');
        if (picker) {
            picker.hide();
        }

        input.on('apply.daterangepicker', function (event, picker) {
            setValue(picker.startDate, picker.endDate);
        });

        input.on('cancel.daterangepicker', function () {
            input.val('');
            startInput.val('');
            endInput.val('');
        });
    })();
</script>
