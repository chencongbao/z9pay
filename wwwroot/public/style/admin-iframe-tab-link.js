(function () {
    if (window.__adminIframeTabLinkInitialized) {
        return;
    }

    window.__adminIframeTabLinkInitialized = true;

    function getParentApp() {
        return window.parent && window.parent.iframeTabParent ? window.parent.iframeTabParent : null;
    }

    function getLinkText($link) {
        var title = $.trim(
            $link.data('tabTitle')
            || $link.attr('data-tab-title')
            || $link.data('iframeTabTitle')
            || $link.attr('data-iframe-tab-title')
            || ''
        );

        if (title) {
            return title;
        }

        return $.trim($link.text()) || '新标签页';
    }

    function getLinkIcon($link) {
        return $.trim(
            $link.data('tabIcon')
            || $link.attr('data-tab-icon')
            || $link.data('iframeTabIcon')
            || $link.attr('data-iframe-tab-icon')
            || 'icon-circle'
        );
    }

    function buildTitleHtml($link) {
        return '&nbsp;<i class="fa fa-fw feather ' + getLinkIcon($link) + '"></i><p>' + getLinkText($link) + '</p>';
    }

    function closeDropdown($link) {
        var $menu = $link.closest('.dropdown-menu');
        var $toggle = $menu.length ? $menu.prev('.dropdown-toggle') : $();

        if ($menu.length) {
            $menu.removeClass('show');
        }

        if ($toggle.length) {
            $toggle.dropdown('hide');
            $toggle.attr('aria-expanded', 'false');
        }

        $link.closest('.dropdown, .btn-group').removeClass('show');
        $('body').removeClass('dropdown-open');
    }

    function resolveTabId(parentApp, $link, url) {
        var dataTab = $.trim($link.attr('data-tab') || '');
        var customId = '';

        if (dataTab && dataTab !== '1' && dataTab !== 'true') {
            customId = dataTab;
        }

        if (!customId) {
            customId = $.trim(
                $link.data('tabId')
                || $link.attr('data-tab-id')
                || $link.data('iframeTabId')
                || $link.attr('data-iframe-tab-id')
                || ''
            );
        }

        if (customId) {
            return customId;
        }

        return parentApp.iframeTab.generateID(url);
    }

    function openOrReuseTab($link) {
        var url = $.trim($link.data('url') || $link.attr('data-url') || $link.attr('href') || '');

        if (!url || url === '#' || url.indexOf('javascript:') === 0) {
            return false;
        }

        var parentApp = getParentApp();

        if (!parentApp) {
            window.location.href = url;
            return false;
        }

        var id = resolveTabId(parentApp, $link, url);
        var existing = parentApp.elements.iframe_tab.find('#iframe-home-' + id);
        var iframeUrl = parentApp.iframeTabTemplate.normalizeIframeUrl(url);

        if (existing.length > 0) {
            existing.attr('data-url', url);
            existing.attr('data-tab-title', getLinkText($link));
            existing.attr('data-tab-icon', getLinkIcon($link));
            existing.html(buildTitleHtml($link) + '<span title="关闭标签页" class="iframe-tab-close-btn"><i class="fa fa-minus-circle"></i></span>');

            var existingPane = parentApp.elements.iframe_tabContent.find('#iframe-' + id);
            var existingIframe = existingPane.find('iframe');

            if (existingIframe.length > 0) {
                existingIframe.attr('src', iframeUrl);
            }

            closeDropdown($link);
            existing.click();
            return false;
        }

        var active = parentApp.iframeTab.findIframeTabActiveElement();
        var titleHtml = buildTitleHtml($link);

        parentApp.swiper.appendSlide(parentApp.iframeTabTemplate.tabItem(titleHtml, id, true, url));
        parentApp.elements.iframe_tabContent.append(parentApp.iframeTabTemplate.tabContentItem(url, id));

        if (typeof parentApp.iframeTab.bindIframeLoadState === 'function') {
            parentApp.iframeTab.bindIframeLoadState(parentApp.elements.iframe_tabContent.find('#iframe-' + id));
        }

        parentApp.swiper.updateSlides();
        parentApp.iframeTab.removeTabBarStyle();
        parentApp.iframeTab.cacheUpdateTabBar(active);
        closeDropdown($link);
        parentApp.elements.iframe_tab.find('#iframe-home-' + id).click();

        return false;
    }

    $(document)
        .off('click.admin-iframe-tab-link', 'a[data-tab], a[data-iframe-tab="1"]')
        .on('click.admin-iframe-tab-link', 'a[data-tab], a[data-iframe-tab="1"]', function (event) {
            var $link = $(this);
            var enabled = $link.attr('data-tab');

            if (enabled !== undefined && enabled !== '' && enabled !== '1' && enabled !== 'true') {
                enabled = 'custom';
            }

            if (enabled === undefined && $link.attr('data-iframe-tab') !== '1') {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            if (event.stopImmediatePropagation) {
                event.stopImmediatePropagation();
            }

            return openOrReuseTab($link);
        });
})();
