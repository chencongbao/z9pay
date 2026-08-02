@if(!empty($description))
    <div style="margin-bottom:12px;border:1px solid #d9e8ff;border-left:4px solid #2f6fdd;background:#f7fbff;border-radius:4px;padding:12px 14px;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px;">
            <div style="font-weight:700;color:#1f2d3d;">
                <i class="fa fa-info-circle" style="color:#2f6fdd;margin-right:5px;"></i>
                当前商户渠道派发规则
            </div>
            <div style="color:#606f7b;font-size:12px;">{{ $description['merchant_name'] ?? '' }}</div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;">
            @foreach(['deposit', 'transfer'] as $type)
                @php($item = $description[$type] ?? [])
                <div style="background:#fff;border:1px solid #edf2f7;border-radius:4px;padding:10px 12px;">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                        <span style="font-weight:700;color:#1f2d3d;">{{ $item['title'] ?? '' }}</span>
                        <span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#eef4ff;color:#2f6fdd;font-size:12px;font-weight:600;">{{ $item['mode'] ?? '' }}</span>
                        <span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#f4f6f8;color:#606f7b;font-size:12px;">{{ $item['source'] ?? '' }}</span>
                    </div>
                    <div style="line-height:1.8;color:#303b45;">
                        <div><strong>派发顺序：</strong>{{ $item['rule'] ?? '' }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
