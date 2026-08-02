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
            <tr  class="tr">
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
    @if($fold)
        <div style="text-align: center;cursor: pointer;padding-top: 10px" class="showListTable showListHistoryTable" @if(isset($search['page'])) data-page = "{{$search['page']}}" @endif @if(isset($search['begin_date'])) data-begin_date = "{{$search['begin_date']}}" @endif @if(isset($search['end_date'])) data-end_date = "{{$search['end_date']}}" @endif @if(isset($search['user_id'])) data-user_id = "{{$search['user_id']}}" @endif @if(isset($search['channel_id'])) data-channel_id = "{{$search['channel_id']}}" @endif @if(isset($search['payment_id'])) data-payment_id = "{{$search['payment_id']}}" @endif>显示更多 <span class="feather icon-chevrons-down"></span></div>
    @endif
</div>


