(function () {
    function getTopOffset() {
        var topHeight = 0;
        var gap = 0;

        if (window.parent && window.parent !== window) {
            try {
                var parentTab = window.parent.document.querySelector('.iframe-tab-container');
                var currentFrame = window.frameElement;

                if (parentTab && currentFrame) {
                    var tabBottom = parentTab.getBoundingClientRect().bottom;
                    var frameTop = currentFrame.getBoundingClientRect().top;
                    return Math.max(Math.round(tabBottom - frameTop + gap), 0);
                }
            } catch (e) {}

            return gap;
        }

        try {
            var nav = document.querySelector('body > div.wrapper > nav');
            var horizontal = document.querySelector('.header-navbar.navbar-horizontal');
            var tab = document.querySelector('.iframe-tab-container');
            var bottoms = [nav, horizontal, tab]
                .filter(Boolean)
                .map(function (el) { return el.getBoundingClientRect().bottom; });

            topHeight = bottoms.length ? Math.max.apply(null, bottoms) : 0;
        } catch (e) {}

        return Math.round(topHeight + gap);
    }

    function injectStyles() {
        if (document.getElementById('admin-grid-sticky-vh-style')) {
            return;
        }

        var style = document.createElement('style');
        style.id = 'admin-grid-sticky-vh-style';
        style.textContent = [
            '#grid-table thead th{position:sticky;top:var(--admin-grid-sticky-top, 0px);z-index:20;background:#556476 !important;color:#fff !important;border-color:#6b7b90 !important;white-space:nowrap;box-sizing:border-box;}',
            '#grid-table thead th *{color:#fff !important;}',
            '#grid-table thead{box-shadow:rgba(0,0,0,.12) 0 -2px 1px 0;}'
        ].join('');
        document.head.appendChild(style);
    }

    function initStickyHeaders() {
        injectStyles();

        var table = document.querySelector('#grid-table');
        if (! table || ! table.querySelector('thead th')) {
            return;
        }

        document.documentElement.style.setProperty('--admin-grid-sticky-top', getTopOffset() + 'px');
    }

    window.__adminApplyStickyGridHeaders = initStickyHeaders;
    window.__adminInitGridStickyHeaders = initStickyHeaders;

    initStickyHeaders();
    setTimeout(initStickyHeaders, 100);
    setTimeout(initStickyHeaders, 300);
    setTimeout(initStickyHeaders, 600);

    window.addEventListener('load', function () {
        setTimeout(initStickyHeaders, 50);
        setTimeout(initStickyHeaders, 200);
    });

    document.addEventListener('pjax:complete', function () {
        setTimeout(initStickyHeaders, 50);
    });
})();
