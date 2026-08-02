<style>
    .self-channel-tabs {
        display: flex;
        gap: 8px;
        margin-bottom: 0 !important;
        border-bottom: 0;
    }

    .self-channel-tabs > li {
        float: none;
        margin-bottom: 0;
    }

    .self-channel-tabs > li > a {
        margin-right: 0;
        border: 1px solid #dfe4ea !important;
        border-bottom: 0 !important;
        border-radius: 8px 8px 0 0 !important;
        background: #f5f7fb !important;
        color: #6b7280 !important;
        font-weight: 600;
        padding: 10px 18px;
    }

    .self-channel-tabs > li.active > a,
    .self-channel-tabs > li.active > a:focus,
    .self-channel-tabs > li.active > a:hover,
    .self-channel-tabs > li > a[aria-expanded="true"] {
        color: #586cb1 !important;
        background: #fff !important;
        border: 1px solid #dfe4ea !important;
        border-bottom-color: #fff !important;
        box-shadow: inset 0 2px 0 #586cb1;
    }

    .self-channel-tabs > li > a:hover,
    .self-channel-tabs > li > a:focus {
        color: #586cb1 !important;
        background: #eef2ff !important;
    }

    .self-channel-tab-content {
        border: 1px solid #dfe4ea;
        border-radius: 0 8px 8px 8px;
        background: #fff;
        padding: 12px;
        margin-top: -1px;
    }

    .self-channel-tab-content .tab-pane table {
        margin-bottom: 0;
    }
</style>

<div class="row">
    <div class="col-12">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title">收款卡列表</h3>
            </div>
            <div class="box-body" style="overflow-x:auto;">
                <div style="margin-bottom:14px;color:#666;">
                    当前排卡来源：<span style="color:#586cb1;font-weight:700;">{{ $sourceText }}</span>
                    <span style="margin-left:16px;">真实命中卡数：<span style="color:#21b978;font-weight:700;">{{ $realCardCount }}</span></span>
                    <span style="margin-left:16px;">最后排卡时间：<span style="color:#333;">{{ $paymentSelected ? ($snapshotTime ?: '未匹配到收款卡') : '请选择支付方式' }}</span></span>
                </div>
                @if($hasSnapshot && !$dispatchMatched && $dispatchMessage)
                    <div style="margin-bottom:14px;padding:10px 12px;border-radius:6px;background:#fff4f1;color:#d84a1b;border:1px solid #ffd9cc;">
                        最后一笔排卡结果：{{ $dispatchMessage }}
                    </div>
                @endif

                <ul class="nav nav-tabs self-channel-tabs" role="tablist">
                    <li class="active"><a href="#self-channel-real-queue" data-toggle="tab">最终排卡</a></li>
                    <li><a href="#self-channel-candidate-cards" data-toggle="tab">候选卡</a></li>
                    <li><a href="#self-channel-enabled-cards" data-toggle="tab">启用卡</a></li>
                </ul>

                <div class="tab-content self-channel-tab-content">
                    <div class="tab-pane active" id="self-channel-real-queue">
                        <table class="table table-bordered table-hover">
                            <thead>
                            <tr>
                                <th style="white-space:nowrap;">当前顺位</th>
                                <th style="white-space:nowrap;">队列状态</th>
                                <th style="white-space:nowrap;">所属金主</th>
                                <th style="white-space:nowrap;">收款卡</th>
                                <th style="white-space:nowrap;">虚拟节点</th>
                                <th style="white-space:nowrap;">轮次</th>
                                <th style="white-space:nowrap;">当前Priority</th>
                                <th style="white-space:nowrap;">今日成功单数</th>
                                <th style="white-space:nowrap;">今日成功金额</th>
                                <th style="white-space:nowrap;">最近成功时间</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($realQueueRows as $row)
                                <tr>
                                    <td>{{ $row['queue_index'] }}</td>
                                    <td>{!! $row['queue_state_html'] !!}</td>
                                    <td>{{ $row['user'] }}</td>
                                    <td>{{ $row['name'] }}</td>
                                    <td>{{ $row['nid'] }}</td>
                                    <td>第{{ $row['round'] }}轮</td>
                                    <td>{{ $row['priority'] }}</td>
                                    <td>{{ $row['today_order_number'] }}</td>
                                    <td>{{ $row['today_order_amount'] }}</td>
                                    <td>{{ $row['last_success_time'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" style="text-align:center;color:#999;padding:24px 12px;">
                                        {{ $paymentSelected ? '未匹配到收款卡' : '请选择支付方式' }}
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="tab-pane" id="self-channel-candidate-cards">
                        <table class="table table-bordered table-hover">
                            <thead>
                            <tr>
                                <th style="white-space:nowrap;">顺序号</th>
                                <th style="white-space:nowrap;">所属金主</th>
                                <th style="white-space:nowrap;">收款卡</th>
                                <th style="white-space:nowrap;">轮询次数</th>
                                <th style="white-space:nowrap;">规则校验</th>
                                <th style="white-space:nowrap;">过滤原因</th>
                                <th style="white-space:nowrap;">今日成功单数</th>
                                <th style="white-space:nowrap;">今日成功金额</th>
                                <th style="white-space:nowrap;">最近成功时间</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($candidateBankRows as $row)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $row['user'] }}</td>
                                    <td>{{ $row['name'] }}</td>
                                    <td>{{ $row['round_times'] }}</td>
                                    <td>{!! $row['pass_html'] !!}</td>
                                    <td style="min-width:260px;white-space:normal;">{{ $row['pass_reason'] }}</td>
                                    <td>{{ $row['today_order_number'] }}</td>
                                    <td>{{ $row['today_order_amount'] }}</td>
                                    <td>{{ $row['last_success_time'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" style="text-align:center;color:#999;padding:24px 12px;">
                                        {{ $paymentSelected ? '未匹配到收款卡' : '请选择支付方式' }}
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="tab-pane" id="self-channel-enabled-cards">
                        <table class="table table-bordered table-hover">
                            <thead>
                            <tr>
                                <th style="white-space:nowrap;">顺序号</th>
                                <th style="white-space:nowrap;">所属金主</th>
                                <th style="white-space:nowrap;">收款卡</th>
                                <th style="white-space:nowrap;">轮询次数</th>
                                <th style="white-space:nowrap;">今日成功单数</th>
                                <th style="white-space:nowrap;">今日成功金额</th>
                                <th style="white-space:nowrap;">最近成功时间</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($enabledBankRows as $row)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $row['user'] }}</td>
                                    <td>{{ $row['name'] }}</td>
                                    <td>{{ $row['round_times'] }}</td>
                                    <td>{{ $row['today_order_number'] }}</td>
                                    <td>{{ $row['today_order_amount'] }}</td>
                                    <td>{{ $row['last_success_time'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" style="text-align:center;color:#999;padding:24px 12px;">{{ $paymentSelected ? '当前支付方式下暂无启用收款卡。' : '请选择支付方式' }}</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
