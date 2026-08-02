<div class="{{$tableClass}}">
    <table {!! $attributes !!}>
        <thead>
        <tr>
            @foreach($headers as $header)
                <th @if($width) style="width: {{$width}}" @endif>{{ $header }}</th>
            @endforeach
        </tr>
        </thead>
        <tbody>
        @foreach($rows as $row)
            <tr @if($loop->index > $defaultShowLine - 1 && $fold) class="hidden" @endif @if(isset($bgColor[$loop->index])) class="{{$bgColor[$loop->index]}}" @endif class="tr-red">
                @foreach($row as $item)
                    <td  style="padding-top: 10px;padding-bottom: 10px;vertical-align: center;height: auto; @if($width)width: {{$width}} @endif">{!! $item !!}</td>
                @endforeach
            </tr>
        @endforeach
        @if (empty($rows))
            <tr>
                <td colspan="{!! count($headers) !!}">
                    <div style="margin:5px 0 0 10px;"><span class="help-block" style="margin-bottom:0"><i class="feather icon-alert-circle"></i>&nbsp;{{ trans('admin.no_data') }}</span></div>
                </td>
            </tr>
        @endif
        </tbody>
    </table>

    @if($fold && count($rows) > $defaultShowLine)
        <div style="text-align: center;cursor: pointer;padding-top: 10px" class="showTable">{{__('admin.responsive.display_all')}} <span class="feather icon-chevrons-down"></span></div>
        <div style="text-align: center;cursor: pointer;padding-top: 10px;" class="backTable hidden" data-line="{{$defaultShowLine}}">{{__('admin.back')}} <span class="feather icon-chevrons-up"></span></div>
    @endif

    <style>
        .tableclass{
            color:red !important;
        }
        .tr-1{
            background-color: #FEF3D1 !important;
        }
        .tr-2{
            background-color: #DBEAD3  !important;
        }
        .tr-3{
            background-color: #CBD9F5 !important;
        }
        .tr-4{
            background-color: #D9D3E5 !important;
        }
        .tr-5{
            background-color: #a8b2ff !important;
        }
        .tr-6{
            background-color: #fff8dc !important;
        }
        .tr-7{
            background-color: #bfefff !important;
        }
        .tr-8{
            background-color: #cd96cd !important;
        }
        .tr-9{
            background-color: #cdb7b5 !important;
        }
    </style>
</div>
