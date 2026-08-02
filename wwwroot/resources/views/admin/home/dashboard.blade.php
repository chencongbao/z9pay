@php($dashboardNow = now())

<div class="luckypay-dashboard">
    <div class="dashboard-hero">
        <div class="dashboard-hero-main">
            <div class="dashboard-title">运营总览</div>
            <div class="dashboard-subtitle">今日核心数据 · {{ $todayText }}</div>
        </div>
        <div class="dashboard-clock">
            <span>当前时间</span>
            <strong class="dashboard-clock-time" data-timestamp="{{ $dashboardNow->timestamp }}">{{ $dashboardNow->format('Y-m-d H:i:s') }}</strong>
        </div>
    </div>

    <div class="dashboard-kpis">
        @foreach($statCards as $card)
            <div class="dashboard-kpi" style="--card-color: {{ $card['color'] }}">
                <div class="dashboard-kpi-top">
                    <span class="dashboard-kpi-icon">{{ $card['iconText'] }}</span>
                    <span class="dashboard-kpi-badge">实时</span>
                </div>
                <div class="dashboard-kpi-title">{{ $card['title'] }}</div>
                <div class="dashboard-kpi-value">{{ $card['value'] }}</div>
                <div class="dashboard-kpi-sub">{{ $card['sub'] }}</div>
            </div>
        @endforeach
    </div>

    <div class="dashboard-main-layout">
        <div class="dashboard-left-stack">
            <div class="dashboard-panel dashboard-alert-panel">
                <div class="dashboard-panel-head">
                    <div>
                        <div class="dashboard-panel-title">运营预警</div>
                        <div class="dashboard-panel-sub">优先关注会影响订单处理和资金流转的事项</div>
                    </div>
                    <span class="dashboard-panel-tag">自动分级</span>
                </div>
                <div class="dashboard-alerts">
                    @foreach($alerts as $alert)
                        <div class="dashboard-alert dashboard-alert-{{ $alert['level'] }}">
                            <div class="dashboard-alert-label">{{ $alert['name'] }}</div>
                            <strong>{{ number_format($alert['count']) }}</strong>
                            <span>{{ intval($alert['count']) > 0 ? '需要关注' : '暂无异常' }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="dashboard-panel dashboard-health-panel">
                <div class="dashboard-panel-head">
                    <div>
                        <div class="dashboard-panel-title">资源健康</div>
                        <div class="dashboard-panel-sub">商户、金主收款、收款资源当前可用情况</div>
                    </div>
                    <span class="dashboard-panel-tag">运行中</span>
                </div>
                <div class="dashboard-status-list">
                    @foreach($statusItems as $item)
                        <div class="dashboard-status dashboard-status-{{ $item['level'] }}">
                            <div class="dashboard-status-dot"></div>
                            <span>{{ $item['name'] }}</span>
                            <strong>{{ $item['value'] }}</strong>
                            <em>{{ $item['sub'] }}</em>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="dashboard-panel dashboard-money-panel">
                <div class="dashboard-panel-head">
                    <div>
                        <div class="dashboard-panel-title">商户资金快照</div>
                        <div class="dashboard-panel-sub">资金结构与占比，一眼判断资金占用情况</div>
                    </div>
                </div>
                <div class="dashboard-amounts">
                    @foreach($amounts as $item)
                        <div class="dashboard-amount dashboard-amount-{{ $item['level'] }}">
                            <div class="dashboard-amount-main">
                                <span>{{ $item['name'] }}</span>
                                <strong>{{ $item['value'] }}</strong>
                            </div>
                            <div class="dashboard-progress">
                                <i style="width: {{ min(100, floatval($item['percent'])) }}%"></i>
                            </div>
                            <em>占比 {{ $item['percent'] }}%</em>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="dashboard-panel dashboard-order-panel">
            <div class="dashboard-panel-head">
                <div>
                    <div class="dashboard-panel-title">订单状态</div>
                    <div class="dashboard-panel-sub">今日代收、代付状态分布</div>
                </div>
            </div>
            <div class="dashboard-order-groups">
                @foreach($orderStatusGroups as $group)
                    <div class="dashboard-order-group" style="--group-color: {{ $group['color'] }}">
                        <div class="dashboard-order-group-head">
                            <div>
                                <span>{{ $group['iconText'] }}</span>
                                <strong>{{ $group['title'] }}</strong>
                            </div>
                            <em>成功率 {{ $group['rate'] }}%</em>
                        </div>
                        <div class="dashboard-order-statuses">
                            @foreach($group['items'] as $item)
                                <div class="dashboard-order-status">
                                    <span>{{ $item['name'] }}</span>
                                    <strong>{{ number_format($item['value']) }}</strong>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

<style>
    .luckypay-dashboard {
        --primary: #21b978;
        --primary-dark: #1b5f55;
        --navy: #33415f;
        --navy-soft: #5368a6;
        --success: #21b978;
        --info: #12a594;
        --warning: #f59e0b;
        --danger: #f04438;
        --text: #1f2a44;
        --muted: #667085;
        --line: #e6ebf3;
        --soft: #f4f7fb;
        --panel: #ffffff;
        --shadow: 0 14px 32px rgba(31, 42, 68, .08);
        padding: 14px 0 26px;
        color: var(--text);
        font-size: 16px;
    }

    .dashboard-hero {
        position: relative;
        overflow: hidden;
        display: flex;
        justify-content: space-between;
        align-items: center;
        min-height: 112px;
        margin-bottom: 18px;
        padding: 24px 30px;
        border-radius: 0 0 18px 18px;
        color: #fff;
        background:
            radial-gradient(circle at 78% 20%, rgba(33, 185, 120, .28), transparent 32%),
            linear-gradient(135deg, #2f3a58 0%, #33415f 48%, #2b796f 100%);
        box-shadow: 0 12px 28px rgba(47, 58, 88, .16);
    }

    .dashboard-hero::after {
        position: absolute;
        right: -70px;
        bottom: -90px;
        width: 260px;
        height: 260px;
        border-radius: 50%;
        background: rgba(255, 255, 255, .08);
        content: '';
    }

    .dashboard-hero-main,
    .dashboard-clock {
        position: relative;
        z-index: 1;
    }

    .dashboard-title {
        font-size: 32px;
        font-weight: 800;
        line-height: 1.18;
        letter-spacing: -.03em;
    }

    .dashboard-subtitle {
        margin-top: 7px;
        color: rgba(255, 255, 255, .82);
        font-size: 16px;
        font-weight: 600;
    }

    .dashboard-clock {
        min-width: 220px;
        border: 1px solid rgba(255, 255, 255, .18);
        border-radius: 16px;
        padding: 13px 18px;
        background: rgba(255, 255, 255, .1);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, .12);
        text-align: right;
        backdrop-filter: blur(8px);
    }

    .dashboard-clock span {
        display: block;
        color: rgba(255, 255, 255, .68);
        font-size: 13px;
        font-weight: 700;
    }

    .dashboard-clock strong {
        display: block;
        margin-top: 5px;
        font-size: 18px;
        font-weight: 800;
        letter-spacing: .01em;
    }

    .dashboard-kpis {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 16px;
        margin-bottom: 16px;
    }

    .dashboard-kpi,
    .dashboard-panel {
        border: 1px solid var(--line);
        border-radius: 18px;
        background: var(--panel);
        box-shadow: var(--shadow);
    }

    .dashboard-kpi {
        position: relative;
        overflow: hidden;
        min-height: 150px;
        padding: 18px;
    }

    .dashboard-kpi::before {
        position: absolute;
        inset: 0 0 auto 0;
        height: 4px;
        background: var(--card-color, var(--primary));
        content: '';
    }

    .dashboard-kpi::after {
        position: absolute;
        right: -38px;
        top: -42px;
        width: 126px;
        height: 126px;
        border-radius: 50%;
        background: color-mix(in srgb, var(--card-color, var(--primary)) 10%, transparent);
        content: '';
    }

    .dashboard-kpi-top {
        position: relative;
        z-index: 1;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
    }

    .dashboard-kpi-icon {
        display: inline-flex;
        justify-content: center;
        align-items: center;
        width: 40px;
        height: 40px;
        border-radius: 14px;
        color: var(--card-color, var(--primary));
        background: #f3fbf8;
        font-size: 17px;
        font-weight: 900;
    }

    .dashboard-kpi-badge,
    .dashboard-panel-tag {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 30px;
        border-radius: 999px;
        padding: 0 12px;
        color: #53706b;
        background: #eef8f4;
        font-size: 13px;
        font-weight: 800;
    }

    .dashboard-kpi-title,
    .dashboard-panel-sub,
    .dashboard-kpi-sub,
    .dashboard-alert span,
    .dashboard-status span,
    .dashboard-status em,
    .dashboard-amount span,
    .dashboard-amount em {
        color: var(--muted);
    }

    .dashboard-kpi-title {
        font-size: 15px;
        font-weight: 800;
    }

    .dashboard-kpi-value {
        margin-top: 9px;
        color: var(--text);
        font-size: 31px;
        font-weight: 850;
        line-height: 1.1;
        letter-spacing: -.03em;
    }

    .dashboard-kpi-sub {
        margin-top: 8px;
        font-size: 14px;
        font-weight: 700;
    }

    .dashboard-main-layout {
        display: grid;
        grid-template-columns: minmax(0, 1.34fr) minmax(410px, .66fr);
        gap: 16px;
        align-items: start;
    }

    .dashboard-left-stack {
        display: grid;
        gap: 16px;
    }

    .dashboard-panel {
        padding: 20px;
    }

    .dashboard-panel-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
        margin-bottom: 16px;
    }

    .dashboard-panel-title {
        color: var(--text);
        font-size: 19px;
        font-weight: 850;
        line-height: 1.25;
    }

    .dashboard-panel-sub {
        margin-top: 5px;
        font-size: 14px;
        font-weight: 600;
    }

    .dashboard-alerts {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
    }

    .dashboard-alert {
        position: relative;
        min-height: 104px;
        border: 1px solid var(--line);
        border-radius: 16px;
        padding: 16px;
        background: linear-gradient(180deg, #fff 0%, #fbfcff 100%);
    }

    .dashboard-alert::before {
        position: absolute;
        top: 16px;
        right: 16px;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: var(--warning);
        box-shadow: 0 0 0 5px rgba(245, 158, 11, .12);
        content: '';
    }

    .dashboard-alert-label {
        padding-right: 20px;
        color: var(--text);
        font-size: 15px;
        font-weight: 800;
    }

    .dashboard-alert strong {
        display: block;
        margin-top: 14px;
        color: var(--warning);
        font-size: 31px;
        font-weight: 850;
        line-height: 1;
    }

    .dashboard-alert span {
        display: block;
        margin-top: 8px;
        font-size: 13px;
        font-weight: 700;
    }

    .dashboard-alert-success::before {
        background: var(--success);
        box-shadow: 0 0 0 5px rgba(33, 185, 120, .12);
    }

    .dashboard-alert-success strong {
        color: var(--success);
    }

    .dashboard-alert-warning::before {
        background: var(--warning);
    }

    .dashboard-alert-warning strong {
        color: var(--warning);
    }

    .dashboard-alert-danger::before {
        background: var(--danger);
        box-shadow: 0 0 0 5px rgba(240, 68, 56, .12);
    }

    .dashboard-alert-danger strong {
        color: var(--danger);
    }

    .dashboard-order-groups {
        display: grid;
        gap: 14px;
    }

    .dashboard-order-group {
        border: 1px solid var(--line);
        border-radius: 16px;
        padding: 16px;
        background: #fbfcff;
    }

    .dashboard-order-group-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        margin-bottom: 14px;
    }

    .dashboard-order-group-head div {
        display: flex;
        align-items: center;
        min-width: 0;
    }

    .dashboard-order-group-head span {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 34px;
        width: 34px;
        height: 34px;
        margin-right: 10px;
        border-radius: 12px;
        color: var(--group-color, var(--primary));
        background: #f3fbf8;
        font-size: 15px;
        font-weight: 900;
    }

    .dashboard-order-group-head strong {
        color: var(--text);
        font-size: 17px;
        font-weight: 850;
    }

    .dashboard-order-group-head em {
        flex: 0 0 auto;
        border-radius: 999px;
        padding: 6px 10px;
        color: var(--group-color, var(--primary));
        background: #fff;
        font-size: 13px;
        font-style: normal;
        font-weight: 850;
    }

    .dashboard-order-statuses {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 9px;
    }

    .dashboard-order-status {
        display: flex;
        justify-content: space-between;
        align-items: center;
        min-height: 42px;
        border-radius: 12px;
        padding: 9px 11px;
        background: #f2f5fa;
    }

    .dashboard-order-status span {
        color: var(--muted);
        font-size: 14px;
        font-weight: 750;
    }

    .dashboard-order-status strong {
        color: var(--text);
        font-size: 18px;
        font-weight: 850;
    }

    .dashboard-status-list {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
    }

    .dashboard-status {
        position: relative;
        min-height: 122px;
        border: 1px solid var(--line);
        border-radius: 16px;
        padding: 16px;
        background: #fbfcff;
    }

    .dashboard-status-dot {
        position: absolute;
        top: 17px;
        right: 17px;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: var(--success);
        box-shadow: 0 0 0 5px rgba(33, 185, 120, .12);
    }

    .dashboard-status-warning .dashboard-status-dot {
        background: var(--warning);
        box-shadow: 0 0 0 5px rgba(245, 158, 11, .13);
    }

    .dashboard-status span {
        display: block;
        padding-right: 20px;
        font-size: 15px;
        font-weight: 800;
    }

    .dashboard-status strong {
        display: block;
        margin-top: 17px;
        color: var(--text);
        font-size: 30px;
        font-weight: 850;
        line-height: 1.05;
    }

    .dashboard-status em {
        display: block;
        margin-top: 10px;
        font-size: 13px;
        font-style: normal;
        font-weight: 700;
    }

    .dashboard-amounts {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
    }

    .dashboard-amount {
        min-height: 122px;
        border: 1px solid var(--line);
        border-radius: 16px;
        padding: 15px;
        background: #fbfcff;
    }

    .dashboard-amount span {
        display: block;
        font-size: 14px;
        font-weight: 800;
    }

    .dashboard-amount strong {
        display: block;
        margin-top: 11px;
        color: var(--text);
        font-size: 21px;
        font-weight: 850;
        line-height: 1.12;
        word-break: break-all;
    }

    .dashboard-progress {
        overflow: hidden;
        height: 7px;
        margin-top: 16px;
        border-radius: 999px;
        background: #edf2f7;
    }

    .dashboard-progress i {
        display: block;
        height: 100%;
        border-radius: 999px;
        background: var(--navy-soft);
    }

    .dashboard-amount-success .dashboard-progress i {
        background: var(--success);
    }

    .dashboard-amount-warning .dashboard-progress i {
        background: var(--warning);
    }

    .dashboard-amount-info .dashboard-progress i {
        background: var(--info);
    }

    .dashboard-amount-danger .dashboard-progress i {
        background: var(--danger);
    }

    .dashboard-amount em {
        display: block;
        margin-top: 9px;
        font-size: 13px;
        font-style: normal;
        font-weight: 700;
    }

    @supports not (background: color-mix(in srgb, #fff 50%, transparent)) {
        .dashboard-kpi::after {
            background: rgba(33, 185, 120, .08);
        }
    }

    @media (max-width: 1500px) {
        .dashboard-alerts {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 1200px) {
        .dashboard-kpis,
        .dashboard-main-layout {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .dashboard-status-list {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 768px) {
        .dashboard-hero,
        .dashboard-kpis,
        .dashboard-main-layout,
        .dashboard-left-stack,
        .dashboard-alerts,
        .dashboard-status-list,
        .dashboard-amounts,
        .dashboard-order-statuses {
            display: block;
        }

        .dashboard-hero {
            padding: 22px;
        }

        .dashboard-clock {
            margin-top: 18px;
            text-align: left;
        }

        .dashboard-kpi,
        .dashboard-panel,
        .dashboard-alert,
        .dashboard-status,
        .dashboard-amount,
        .dashboard-order-status {
            margin-bottom: 14px;
        }
    }
</style>

<script>
    (function () {
        var clocks = document.querySelectorAll('.dashboard-clock-time[data-timestamp]');
        if (!clocks.length) {
            return;
        }

        function pad(value) {
            return value < 10 ? '0' + value : String(value);
        }

        function formatTime(date) {
            return date.getFullYear() + '-' +
                pad(date.getMonth() + 1) + '-' +
                pad(date.getDate()) + ' ' +
                pad(date.getHours()) + ':' +
                pad(date.getMinutes()) + ':' +
                pad(date.getSeconds());
        }

        clocks.forEach(function (clock) {
            if (clock.dataset.clockStarted === '1') {
                return;
            }

            clock.dataset.clockStarted = '1';

            var baseTimestamp = parseInt(clock.dataset.timestamp, 10) * 1000;
            var browserStartedAt = Date.now();

            function refreshClock() {
                clock.textContent = formatTime(new Date(baseTimestamp + Date.now() - browserStartedAt));
            }

            refreshClock();
            setInterval(refreshClock, 1000);
        });
    })();
</script>
