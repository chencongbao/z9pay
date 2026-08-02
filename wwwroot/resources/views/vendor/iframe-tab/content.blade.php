@php
    $adminPrefix = trim((string) config('admin.route.prefix', 'admin'), '/');
    $currentPath = trim(request()->path(), '/');
    $homeOpenUrl = admin_route('home.dashboard');
    $homeOpenTitle = '系统首页';
    $homeOpenVersion = 'dashboard_v1';
    $defaultOpenUrl = request('open', ($currentPath !== $adminPrefix && str_starts_with($currentPath, $adminPrefix.'/')) ? request()->fullUrl() : '');
    if ($defaultOpenUrl) {
        $parsedDefaultOpenUrl = parse_url($defaultOpenUrl);
        if ($parsedDefaultOpenUrl !== false) {
            $defaultOpenQuery = [];
            parse_str($parsedDefaultOpenUrl['query'] ?? '', $defaultOpenQuery);
            unset($defaultOpenQuery['iframe_tab_child']);

            $rebuiltDefaultOpenUrl = '';
            if (!empty($parsedDefaultOpenUrl['scheme'])) {
                $rebuiltDefaultOpenUrl .= $parsedDefaultOpenUrl['scheme'].'://';
            }
            if (!empty($parsedDefaultOpenUrl['user'])) {
                $rebuiltDefaultOpenUrl .= $parsedDefaultOpenUrl['user'];
                if (isset($parsedDefaultOpenUrl['pass'])) {
                    $rebuiltDefaultOpenUrl .= ':'.$parsedDefaultOpenUrl['pass'];
                }
                $rebuiltDefaultOpenUrl .= '@';
            }
            if (!empty($parsedDefaultOpenUrl['host'])) {
                $rebuiltDefaultOpenUrl .= $parsedDefaultOpenUrl['host'];
            }
            if (!empty($parsedDefaultOpenUrl['port'])) {
                $rebuiltDefaultOpenUrl .= ':'.$parsedDefaultOpenUrl['port'];
            }
            $rebuiltDefaultOpenUrl .= $parsedDefaultOpenUrl['path'] ?? '';

            $rebuiltDefaultOpenQuery = http_build_query($defaultOpenQuery);
            if ($rebuiltDefaultOpenQuery !== '') {
                $rebuiltDefaultOpenUrl .= '?'.$rebuiltDefaultOpenQuery;
            }

            if (!empty($parsedDefaultOpenUrl['fragment'])) {
                $rebuiltDefaultOpenUrl .= '#'.$parsedDefaultOpenUrl['fragment'];
            }

            $defaultOpenUrl = $rebuiltDefaultOpenUrl;
        }
    }
    $defaultOpenTitle = request('open_title', ($defaultOpenUrl ? Dcat\Admin\Admin::title() : ''));
@endphp

@section('content')
    @include('admin::partials.alerts')
    @include('admin::partials.exception')

    {!! $content !!}

    @include('admin::partials.toastr')
@endsection

@section('app')
    {!! Dcat\Admin\Admin::asset()->styleToHtml() !!}
    <style>
        .iframe-tab-pane-loading {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(180deg, #f5f7fb 0%, #edf2f8 100%);
            color: #5b6475;
            z-index: 1;
        }

        .iframe-tab-pane-loading__inner {
            text-align: center;
            padding: 2.25rem 2.75rem;
            max-width: 30rem;
            min-width: 19rem;
            background: rgba(255, 255, 255, 0.92);
            border: 1px solid rgba(210, 220, 235, 0.95);
            border-radius: 18px;
            box-shadow: 0 16px 40px rgba(70, 86, 113, 0.12);
            backdrop-filter: blur(10px);
        }

        .iframe-tab-pane-loading__title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #334155;
            margin-bottom: .65rem;
            letter-spacing: .02em;
        }

        .iframe-tab-pane-loading__desc {
            font-size: .9rem;
            color: #738198;
            line-height: 1.7;
            margin-bottom: 1.15rem;
        }

        .iframe-tab-pane-loading__retry {
            display: none;
            min-width: 7rem;
            border-radius: 999px;
            padding: .45rem 1rem;
            font-weight: 600;
        }

        .iframe-tab-pane-loading.is-failed .iframe-tab-pane-loading__retry {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .35rem;
        }

        .iframe-tab-pane-loading__title::before {
            content: "";
            display: block;
            width: 3rem;
            height: 3rem;
            margin: 0 auto .9rem;
            border-radius: 50%;
            border: 3px solid rgba(85, 100, 118, 0.14);
            border-top-color: #556476;
            animation: iframeTabLoadingSpin 1s linear infinite;
        }

        @keyframes iframeTabLoadingSpin {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
        }

        .iframe-tab-pane-loading.is-failed .iframe-tab-pane-loading__title::before {
            animation-duration: 1.8s;
            border-top-color: #ea580c;
        }

        .iframe-tab-frame.is-loading {
            visibility: hidden;
        }
    </style>
    <div class="content-body" style="position: relative;width: 100%;height: 100%" id="app">
        <input type="hidden" id="iframe_tab_open_url" value="{{ $defaultOpenUrl }}">
        <input type="hidden" id="iframe_tab_open_title" value="{{ $defaultOpenTitle }}">
        <input type="hidden" id="iframe_tab_home_url" value="{{ $homeOpenUrl }}">
        <input type="hidden" id="iframe_tab_home_title" value="{{ $homeOpenTitle }}">
        <input type="hidden" id="iframe_tab_home_version" value="{{ $homeOpenVersion }}">
        <input type="hidden" id="iframe_tab_refresh_patterns" value='@json(array_values(config("iframe_tab.activate_refresh_patterns", [])))'>
        {{-- 页面埋点--}}
        {!! admin_section(Dcat\Admin\Admin::SECTION['APP_INNER_BEFORE']) !!}
        <div class="tab-content" id="iframe-tabContent"></div>

        {{-- 页面埋点--}}
        {!! admin_section(Dcat\Admin\Admin::SECTION['APP_INNER_AFTER']) !!}
    </div>

    {!! Dcat\Admin\Admin::asset()->scriptToHtml() !!}
    {!! Dcat\Admin\Admin::html() !!}
    <script>
        (function () {
            var iframeSuccessActionDelay = 1600;
            var iframeToastTimeout = 2000;

            function showTopToast(type, message, detail) {
                var host = (window.parent && window.parent !== window && window.parent.toastr) ? window.parent : window;

                if (!host.toastr || typeof host.toastr[type] !== 'function') {
                    return false;
                }

                if (host.toastr.options) {
                    host.toastr.options.timeOut = iframeToastTimeout;
                    host.toastr.options.extendedTimeOut = 800;
                }

                host.toastr[type](message || '', detail || '');

                return true;
            }

            function overrideParentJsonResponse() {
                if (!window.Dcat || typeof window.Dcat.handleJsonResponse !== 'function') {
                    return false;
                }

                if (window.Dcat.__iframeTabParentJsonResponseOverridden) {
                    return true;
                }

                var originalHandleJsonResponse = window.Dcat.handleJsonResponse.bind(window.Dcat);

                window.Dcat.handleJsonResponse = function (response, options) {
                    var data = response && response.data ? response.data : null;
                    var isSuccess = response && response.status;
                    var hasMessage = !!(data && data.message) || !!response.message;

                    if (data && hasMessage) {
                        var message = data.message || response.message || '';
                        var type = data.type || (isSuccess ? 'success' : 'error');
                        var detail = data.detail || response.detail || '';

                        if (showTopToast(type, message, detail)) {
                            data.message = '';
                            data.detail = '';
                            data.type = null;
                            response.message = '';
                            response.detail = '';
                        }

                        if (!data.timeout || data.timeout < 2) {
                            data.timeout = 2;
                        }

                        if (data.then && ['refresh', 'redirect', 'location'].indexOf(data.then.action) !== -1) {
                            var then = data.then;
                            var actionCode = '';

                            if (then.action === 'refresh') {
                                actionCode = 'setTimeout(function(){Dcat.reload();}, ' + iframeSuccessActionDelay + ');';
                            } else if (then.action === 'redirect') {
                                actionCode = 'setTimeout(function(){Dcat.reload(' + JSON.stringify(then.value || null) + ');}, ' + iframeSuccessActionDelay + ');';
                            } else if (then.action === 'location') {
                                actionCode = 'setTimeout(function(){' +
                                    (then.value
                                        ? ('window.location = ' + JSON.stringify(then.value) + ';')
                                        : 'window.location.reload();'
                                    ) +
                                    '}, ' + iframeSuccessActionDelay + ');';
                            }

                            if (actionCode) {
                                data.then = {
                                    action: 'script',
                                    value: actionCode
                                };
                            }
                        }
                    }

                    return originalHandleJsonResponse(response, options);
                };

                window.Dcat.__iframeTabParentJsonResponseOverridden = true;

                return true;
            }

            if (!overrideParentJsonResponse()) {
                var tries = 0;
                var timer = setInterval(function () {
                    tries++;
                    if (overrideParentJsonResponse() || tries > 50) {
                        clearInterval(timer);
                    }
                }, 100);
            }
        })();
    </script>
@endsection

@if(! request()->pjax())
    @include('iframe-tab::page')
@else
    <title>{{ Dcat\Admin\Admin::title() }} @if($header) | {{ $header }}@endif</title>

    <script>
        try {
            Dcat.pjaxResponded();
        }catch (e) {
            Dcat.wait();
        }
    </script>

    {!! Dcat\Admin\Admin::asset()->cssToHtml() !!}
    {!! Dcat\Admin\Admin::asset()->jsToHtml() !!}

    @yield('app')
@endif
