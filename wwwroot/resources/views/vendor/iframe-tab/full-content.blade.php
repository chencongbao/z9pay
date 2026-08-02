@php
    $routeMethod = optional(request()->route())->getActionMethod();
    $hideContentHeader = request()->boolean('_dialog_')
        || request()->boolean('_modal_');
    $hasContentHeader = !empty(trim((string) $header))
        || !empty(trim((string) $description))
        || !empty($breadcrumb)
        || config('admin.enable_default_breadcrumb');
@endphp

@section('content')
    <section class="content">
        @include('admin::partials.alerts')
        @include('admin::partials.exception')

        {!! $content !!}

        @include('admin::partials.toastr')
    </section>
@endsection
@section('content-header')
    <section class="content-header breadcrumbs-top">
        @if($header || $description)
            <h1 class=" float-left">
                <span class="text-capitalize">{!! $header !!}</span>
                <small>{!! $description !!}</small>
            </h1>
        @elseif($breadcrumb || config('admin.enable_default_breadcrumb'))
            @include('admin::partials.breadcrumb')
        @endif

    </section>
@endsection
@section('app')
    {!! Dcat\Admin\Admin::asset()->styleToHtml() !!}
<style>
    body.iframe-tab-horizontal #app {
        box-sizing: border-box;
    }

    body.iframe-tab-horizontal #app.iframe-tab-content-body--headerless {
        padding-top: 56px;
    }

    body.iframe-tab-horizontal #app > .content-header {
        margin-top: 0 !important;
        padding-top: 0 !important;
    }

    body.iframe-tab-horizontal #app section.content {
        padding-top: 14px;
    }

    body.iframe-tab-horizontal #app section.content > .row:first-child,
    body.iframe-tab-horizontal #app section.content > .box:first-child,
    body.iframe-tab-horizontal #app section.content > .dcat-box:first-child {
        margin-top: 0 !important;
    }

    body.iframe-tab-horizontal .modal.show {
        padding-top: 42px;
    }

    body.iframe-tab-horizontal .modal.show .modal-dialog {
        margin-top: 1.75rem;
    }
</style>
<script>
    (function () {
        function bindParentEcho() {
            try {
                if (!window.parent || window.parent === window) {
                    return;
                }

                if (window.parent.Pusher && !window.Pusher) {
                    window.Pusher = window.parent.Pusher;
                }

                if (window.parent.Echo) {
                    window.Echo = window.parent.Echo;
                    return;
                }

                if (!Object.prototype.hasOwnProperty.call(window, 'Echo')) {
                    Object.defineProperty(window, 'Echo', {
                        configurable: true,
                        get: function () {
                            return window.parent && window.parent !== window ? window.parent.Echo : undefined;
                        },
                        set: function (value) {
                            Object.defineProperty(window, 'Echo', {
                                configurable: true,
                                writable: true,
                                value: value
                            });
                        }
                    });
                }
            } catch (e) {}
        }

        bindParentEcho();

        function appendQuery(url, key, value) {
            if (url && typeof url !== 'string') {
                url = url.url || url.href || window.location.href;
            }
            url = String(url || '');

            try {
                var resolved = new URL(url, window.location.origin);
                resolved.searchParams.set(key, value);
                return resolved.toString();
            } catch (e) {
                var separator = url.indexOf('?') === -1 ? '?' : '&';
                return url + separator + encodeURIComponent(key) + '=' + encodeURIComponent(value);
            }
        }

        function ensureIframeChildField(form) {
            if (!form || !form.querySelector) {
                return;
            }

            var field = form.querySelector('input[name="iframe_tab_child"]');
            if (!field) {
                field = document.createElement('input');
                field.type = 'hidden';
                field.name = 'iframe_tab_child';
                form.appendChild(field);
            }

            field.value = '1';
        }

        function appendIframeChild(url) {
            if (url && typeof url !== 'string') {
                url = url.url || url.href || window.location.href;
            }
            url = String(url || '');

            if (!url || url.indexOf('javascript:') === 0 || url.indexOf('#') === 0) {
                return url;
            }

            try {
                var resolved = new URL(url, window.location.origin);
                resolved.searchParams.set('iframe_tab_child', '1');
                return resolved.toString();
            } catch (e) {
                return url;
            }
        }

        document.addEventListener('click', function (event) {
            var link = event.target.closest('a');
            if (!link) {
                return;
            }

            var href = link.getAttribute('href') || '';
            if (!href || href === '#' || href.indexOf('javascript:') === 0) {
                return;
            }

            link.setAttribute('href', appendIframeChild(href));
        }, true);

        document.addEventListener('submit', function (event) {
            var form = event.target;
            if (!form) {
                return;
            }

            ensureIframeChildField(form);

            if (form.action) {
                form.action = appendIframeChild(form.action);
                return;
            }

            form.setAttribute('action', appendIframeChild(window.location.href));
        }, true);
    })();
</script>
    @unless($hideContentHeader || ! $hasContentHeader)
        <div class="content-header">
            @yield('content-header')
        </div>
    @endunless
    <div class="content-body {{ $hideContentHeader ? 'iframe-tab-content-body--headerless' : '' }}" id="app">
        {{-- 页面埋点--}}
        {!! admin_section(Dcat\Admin\Admin::SECTION['APP_INNER_BEFORE']) !!}

        @yield('content')

        {{-- 页面埋点--}}
        {!! admin_section(Dcat\Admin\Admin::SECTION['APP_INNER_AFTER']) !!}
    </div>

    {!! Dcat\Admin\Admin::asset()->scriptToHtml() !!}
    {!! Dcat\Admin\Admin::html() !!}
    <script>
        (function () {
            var iframeGridRefreshLock = false;
            var iframeSuccessActionDelay = 1600;
            var iframeToastTimeout = 2000;

            function bridgeToParentToastr() {
                try {
                    if (window.parent === window || !window.parent || !window.parent.toastr) {
                        return;
                    }

                    var parentToastr = window.parent.toastr;
                    var localToastr = window.toastr || {};
                    var methods = ['success', 'error', 'info', 'warning'];

                    methods.forEach(function (method) {
                        localToastr[method] = function () {
                            return parentToastr[method].apply(parentToastr, arguments);
                        };
                    });

                    localToastr.clear = function () {
                        return parentToastr.clear.apply(parentToastr, arguments);
                    };

                    localToastr.remove = function () {
                        return parentToastr.remove.apply(parentToastr, arguments);
                    };

                    localToastr.options = parentToastr.options || localToastr.options || {};
                    window.toastr = localToastr;

                    if (window.Dcat && window.parent.Dcat) {
                        ['success', 'error', 'info', 'warning'].forEach(function (method) {
                            if (typeof window.parent.Dcat[method] === 'function') {
                                window.Dcat[method] = function () {
                                    return window.parent.Dcat[method].apply(window.parent.Dcat, arguments);
                                };
                            }
                        });
                    }
                } catch (e) {}
            }

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

            function fixToastContainer() {
                var container = document.getElementById('toast-container');
                if (!container) {
                    return;
                }

                if (container.parentNode !== document.body) {
                    document.body.appendChild(container);
                }

                container.style.position = 'fixed';
                container.style.zIndex = '12000';
                container.style.top = '12px';
                container.style.bottom = 'auto';
            }

            function syncParentOpenState() {
                try {
                    if (!window.parent || window.parent === window) {
                        return;
                    }

                    var currentOpenUrl = window.location.href;
                    var currentTableCard = document.querySelector('.table-card[data-current]');

                    if (currentTableCard && currentTableCard.getAttribute('data-current')) {
                        currentOpenUrl = String(currentTableCard.getAttribute('data-current'));
                    }

                    try {
                        var normalizedCurrentOpenUrl = new URL(currentOpenUrl, window.location.origin);
                        normalizedCurrentOpenUrl.searchParams.delete('iframe_tab_child');
                        currentOpenUrl = normalizedCurrentOpenUrl.toString();
                    } catch (e) {}

                    var parentUrl = new URL(window.parent.location.href, window.location.origin);
                    parentUrl.searchParams.set('open', currentOpenUrl);

                    var pageTitle = document.title || '';
                    if (pageTitle) {
                        parentUrl.searchParams.set('open_title', pageTitle);
                    }

                    window.parent.history.replaceState({}, '', parentUrl.toString());
                } catch (e) {}
            }

            function appendIframeChild(url) {
                if (url && typeof url !== 'string') {
                    url = url.url || url.href || window.location.href;
                }
                url = String(url || '');

                if (!url || url.indexOf('javascript:') === 0 || url.indexOf('#') === 0) {
                    return url;
                }

                try {
                    var resolved = new URL(url, window.location.origin);
                    resolved.searchParams.set('iframe_tab_child', '1');
                    return resolved.toString();
                } catch (e) {
                    return url;
                }
            }

            function preserveScrollAndReload(url) {
                var targetUrl = appendIframeChild(url || window.location.href);

                try {
                    var resolved = new URL(targetUrl, window.location.origin);
                    var scrollElement = document.scrollingElement || document.documentElement || document.body;
                    var currentTop = Math.max(
                        window.scrollY || window.pageYOffset || 0,
                        scrollElement ? (scrollElement.scrollTop || 0) : 0
                    );

                    resolved.searchParams.set('__refresh_top', String(Math.max(0, currentTop || 0)));
                    resolved.searchParams.set('__refresh_token', String(Date.now()));
                    window.location.href = resolved.toString();
                    return;
                } catch (e) {}

                window.location.href = targetUrl;
            }

            function overrideReload() {
                if (!window.Dcat) {
                    return false;
                }

                window.Dcat.reload = function (url) {
                    preserveScrollAndReload(url);
                };

                if (window.jQuery && jQuery.pjax) {
                    jQuery.pjax.reload = function (options) {
                        if (typeof options === 'string') {
                            preserveScrollAndReload(options);
                            return;
                        }

                        preserveScrollAndReload(options && options.url ? options.url : window.location.href);
                    };
                }

                return true;
            }


            function overrideJsonResponse() {
                if (!window.Dcat || typeof window.Dcat.handleJsonResponse !== 'function') {
                    return false;
                }

                if (window.Dcat.__iframeTabJsonResponseOverridden) {
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

                window.Dcat.__iframeTabJsonResponseOverridden = true;

                return true;
            }

            function bindNativeGridRefresh() {
                $(document).off('click.iframe-grid-refresh', '.grid-refresh');
                $(document).on('click.iframe-grid-refresh', '.grid-refresh', function (event) {
                    if (iframeGridRefreshLock) {
                        event.preventDefault();
                        event.stopImmediatePropagation();
                        return false;
                    }

                    var button = $(this);
                    var tableCard = button.closest('.table-card');
                    var refreshUrl = window.location.href;

                    if (tableCard.length && tableCard.data('current')) {
                        refreshUrl = String(tableCard.data('current'));
                    }

                    iframeGridRefreshLock = true;
                    button.prop('disabled', true).addClass('disabled');
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    preserveScrollAndReload(refreshUrl);
                    return false;
                });
            }

            if (!overrideReload()) {
                var tries = 0;
                var timer = setInterval(function () {
                    tries++;
                    if ((overrideReload() && overrideJsonResponse()) || tries > 50) {
                        clearInterval(timer);
                    }
                }, 100);
            } else {
                overrideJsonResponse();
            }

            bridgeToParentToastr();
            bindNativeGridRefresh();
            fixToastContainer();
            syncParentOpenState();

            var toastObserver = new MutationObserver(function () {
                fixToastContainer();
            });

            toastObserver.observe(document.body, {childList: true, subtree: true});

            $(document).on('pjax:complete', function () {
                iframeGridRefreshLock = false;
                $('.grid-refresh.disabled').prop('disabled', false).removeClass('disabled');
                bindNativeGridRefresh();
                fixToastContainer();
                syncParentOpenState();
            });

            $(document).on('pjax:error', function () {
                iframeGridRefreshLock = false;
                $('.grid-refresh.disabled').prop('disabled', false).removeClass('disabled');
            });

            $(document).on('table:loaded', '.table-card', function () {
                syncParentOpenState();
            });

            $(window).on('load', function () {
                syncParentOpenState();
            });
        })();
    </script>
@endsection


@if(!request()->pjax())
    @include('iframe-tab::full-page', ['header' => $header])
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
