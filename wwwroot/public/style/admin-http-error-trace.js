(function () {
    if (window.__adminHttpErrorTraceInitialized) {
        return;
    }

    window.__adminHttpErrorTraceInitialized = true;

    function now() {
        return new Date().toISOString();
    }

    function resolveAjaxUrl(settings) {
        if (!settings) {
            return '';
        }

        return settings.url || settings.href || '';
    }

    function remember(entry) {
        var key = 'adminHttpErrorTrace';
        var list = [];

        try {
            list = JSON.parse(window.sessionStorage.getItem(key) || '[]');
        } catch (e) {
            list = [];
        }

        list.unshift(entry);
        window.sessionStorage.setItem(key, JSON.stringify(list.slice(0, 30)));
        window.__lastAdminHttpError = entry;
    }

    function trace(entry) {
        remember(entry);

        if (window.console && console.error) {
            console.error('[admin-http-error]', entry);
        }
    }

    function bindJqueryAjax() {
        if (!window.jQuery) {
            return false;
        }

        jQuery(document).ajaxError(function (event, xhr, settings, error) {
            trace({
                type: 'ajax',
                time: now(),
                status: xhr ? xhr.status : 0,
                statusText: xhr ? xhr.statusText : '',
                error: error ? String(error) : '',
                method: settings && settings.type ? settings.type : '',
                url: resolveAjaxUrl(settings),
                page: window.location.href,
                response: xhr && xhr.responseText ? String(xhr.responseText).slice(0, 500) : ''
            });
        });

        return true;
    }

    function bindPjax() {
        document.addEventListener('pjax:error', function (event) {
            var xhr = event && event.detail && event.detail.xhr;

            trace({
                type: 'pjax',
                time: now(),
                status: xhr ? xhr.status : 0,
                statusText: xhr ? xhr.statusText : '',
                url: xhr && xhr.responseURL ? xhr.responseURL : '',
                page: window.location.href,
                response: xhr && xhr.responseText ? String(xhr.responseText).slice(0, 500) : ''
            });
        });
    }

    function bindFetch() {
        if (!window.fetch || window.__adminHttpErrorTraceFetchWrapped) {
            return;
        }

        var originalFetch = window.fetch;
        window.__adminHttpErrorTraceFetchWrapped = true;

        window.fetch = function () {
            var input = arguments[0];
            var url = typeof input === 'string' ? input : (input && input.url ? input.url : '');

            return originalFetch.apply(this, arguments).then(function (response) {
                if (response && response.status >= 400) {
                    trace({
                        type: 'fetch',
                        time: now(),
                        status: response.status,
                        statusText: response.statusText,
                        url: url,
                        page: window.location.href
                    });
                }

                return response;
            });
        };
    }

    if (!bindJqueryAjax()) {
        document.addEventListener('DOMContentLoaded', bindJqueryAjax);
    }

    bindPjax();
    bindFetch();
})();
