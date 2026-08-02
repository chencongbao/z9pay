(function () {
    window.ensureHorizontalMenuGuard = function () {
        if (!document.body || !document.body.classList.contains('horizontal-menu')) {
            return;
        }

        if (!document.getElementById('iframe-tab-container')) {
            return;
        }

        if (document.querySelector('.horizontal-menu .main-horizontal-sidebar')) {
            return;
        }

        var guard = document.createElement('div');
        guard.className = 'main-horizontal-sidebar admin-horizontal-menu-guard';
        guard.style.display = 'none';
        (document.querySelector('.wrapper') || document.body).appendChild(guard);
    };

    window.ensureHorizontalMenuGuard();
})();

$(function () {
    window.ensureHorizontalMenuGuard();

    function getHeight(selector) {
        var element = document.querySelector(selector);
        return element ? (element.offsetHeight || 0) : 0;
    }

    function getBottom(selector) {
        var element = document.querySelector(selector);
        if (!element) {
            return 0;
        }

        var rect = element.getBoundingClientRect();
        return rect && rect.bottom > 0 ? rect.bottom : 0;
    }

    function getScrollElement() {
        return document.scrollingElement || document.documentElement || document.body;
    }

    function getCurrentScrollTop() {
        var element = getScrollElement();
        return Math.max(
            window.scrollY || window.pageYOffset || 0,
            element ? (element.scrollTop || 0) : 0
        );
    }

    function setCurrentScrollTop(top) {
        var element = getScrollElement();
        if (element) {
            element.scrollTop = top;
        }
        window.scrollTo(0, top);
    }

    function getMaxScrollableTop() {
        var element = getScrollElement();
        var scrollHeight = element ? (element.scrollHeight || 0) : 0;
        return Math.max(0, scrollHeight - window.innerHeight);
    }

    function getRefreshRestoreData() {
        try {
            var url = new URL(window.location.href);
            var top = parseInt(url.searchParams.get('__refresh_top') || '', 10);
            var token = url.searchParams.get('__refresh_token') || '';

            if (!token || Number.isNaN(top) || top < 0) {
                return null;
            }

            return {
                top: top,
                token: token,
            };
        } catch (e) {
            return null;
        }
    }

    function clearRefreshRestoreData() {
        try {
            var url = new URL(window.location.href);
            url.searchParams.delete('__refresh_top');
            url.searchParams.delete('__refresh_token');
            window.history.replaceState({}, '', url.toString());
        } catch (e) {}
    }

    function buildRefreshUrl(url, top) {
        try {
            var nextUrl = new URL(url, window.location.origin);
            nextUrl.searchParams.set('__refresh_top', String(Math.max(0, top || 0)));
            nextUrl.searchParams.set('__refresh_token', String(Date.now()));
            return nextUrl.toString();
        } catch (e) {
            return url;
        }
    }

    function getActiveIframe() {
        var activeTab = document.querySelector('#iframe-tab .nav-link.active, #iframe-tab .nav-link[aria-selected="true"]');
        if (activeTab) {
            var target = activeTab.getAttribute('href');
            if (target && target.indexOf('#iframe-') === 0) {
                var activePaneByTab = document.querySelector(target);
                if (activePaneByTab) {
                    var iframeByTab = activePaneByTab.querySelector('iframe');
                    if (iframeByTab) {
                        return iframeByTab;
                    }
                }
            }
        }

        var activePane = document.querySelector('#iframe-tabContent .tab-pane.active, #iframe-tabContent .tab-pane.show.active');
        if (activePane) {
            return activePane.querySelector('iframe');
        }

        return null;
    }

    function keepRestoringScrollPosition() {
        var restoreData = getRefreshRestoreData();
        if (!restoreData) {
            return;
        }

        var targetTop = restoreData.top;
        var startedAt = Date.now();
        var maxDuration = 5000;

        function run() {
            var maxTop = getMaxScrollableTop();
            if (maxTop >= targetTop) {
                setCurrentScrollTop(targetTop);
                if (Math.abs(getCurrentScrollTop() - targetTop) <= 4) {
                    clearRefreshRestoreData();
                    return;
                }
            }

            if (Date.now() - startedAt >= maxDuration) {
                return;
            }

            requestAnimationFrame(run);
        }

        requestAnimationFrame(run);
    }

    function getHorizontalMenuHeight() {
        var navbar = document.querySelector('.header-navbar.navbar-horizontal');
        var menu = document.querySelector('.header-navbar.navbar-horizontal .nav.nav-sidebar');
        var navbarHeight = navbar ? (navbar.offsetHeight || 0) : 0;
        var menuHeight = menu ? (menu.offsetHeight || 0) : 0;

        return Math.max(navbarHeight, menuHeight, 40);
    }

    function syncHorizontalMenuHeight() {
        var topNavHeight = getHeight('body > div.wrapper > nav');
        var horizontalMenuHeight = getHorizontalMenuHeight();
        var singleRowMenuHeight = 40;
        var extraMenuHeight = Math.max(0, horizontalMenuHeight - singleRowMenuHeight);
        var totalHeaderHeight = topNavHeight + horizontalMenuHeight;

        document.documentElement.style.setProperty('--admin-horizontal-menu-height', totalHeaderHeight + 'px');
        document.documentElement.style.setProperty('--admin-horizontal-menu-extra-height', extraMenuHeight + 'px');

        document.querySelectorAll('.app-content > .content-wrapper, .app-content .content > .content-wrapper').forEach(function (wrapper) {
            if (wrapper.classList.contains('iframe-tab-wrapper')) {
                return;
            }

            if (!wrapper.dataset.basePaddingTop) {
                wrapper.dataset.basePaddingTop = String(parseInt(window.getComputedStyle(wrapper).paddingTop || '0', 10) || 0);
            }

            var basePaddingTop = parseInt(wrapper.dataset.basePaddingTop || '0', 10) || 0;
            wrapper.style.paddingTop = (basePaddingTop + extraMenuHeight) + 'px';
        });
    }

    function syncIframeTabWrapperMinHeight() {
        document.querySelectorAll('.content-wrapper.iframe-tab-wrapper').forEach(function (wrapper) {
            var rect = wrapper.getBoundingClientRect();
            var availableHeight = Math.floor(window.innerHeight - rect.top);

            if (!Number.isFinite(availableHeight) || availableHeight <= 0) {
                return;
            }

            wrapper.style.minHeight = availableHeight + 'px';
        });
    }

    function syncAndRestore() {
        syncHorizontalMenuHeight();
        syncIframeTabWrapperMinHeight();
        keepRestoringScrollPosition();
    }

    syncAndRestore();
    setTimeout(syncAndRestore, 100);
    setTimeout(syncAndRestore, 300);
    setTimeout(syncAndRestore, 600);
    setTimeout(syncAndRestore, 900);

    $(window).on('resize', function () {
        syncAndRestore();
    });

    $(window).on('load', function () {
        syncAndRestore();
        setTimeout(syncAndRestore, 100);
        setTimeout(syncAndRestore, 260);
        setTimeout(syncAndRestore, 500);
        setTimeout(syncAndRestore, 800);
    });

    $(document).on('click', '.admin-page-refresh-btn', function () {
        var activeIframe = getActiveIframe();

        if (activeIframe) {
            try {
                var iframeWindow = activeIframe.contentWindow;
                var iframeDocument = iframeWindow.document;
                var iframeScrollElement = iframeDocument.scrollingElement || iframeDocument.documentElement || iframeDocument.body;
                var iframeTop = Math.max(
                    iframeWindow.scrollY || iframeWindow.pageYOffset || 0,
                    iframeScrollElement ? (iframeScrollElement.scrollTop || 0) : 0
                );
                var iframeCurrentUrl = iframeWindow.location && iframeWindow.location.href
                    ? iframeWindow.location.href
                    : activeIframe.src;

                activeIframe.src = buildRefreshUrl(iframeCurrentUrl, iframeTop);
                return;
            } catch (e) {
                activeIframe.src = buildRefreshUrl(activeIframe.src, 0);
                return;
            }
        }

        try {
            var currentTop = getCurrentScrollTop();
            var currentRestoreData = getRefreshRestoreData();
            var nextTop = currentRestoreData ? Math.max(currentRestoreData.top, currentTop) : currentTop;
            window.location.href = buildRefreshUrl(window.location.href, nextTop);
            return;
        } catch (e) {}
        window.location.reload();
    });
});
