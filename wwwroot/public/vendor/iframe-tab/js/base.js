$(function () {
    /*引用swiper插件*/
    const swiper = new Swiper('.swiper-container', {
        slidesPerView: 'auto',
        spaceBetween: 0,
        freeMode: true,
        watchSlidesProgress: true,
        watchSlidesVisibility: true,
        navigation: {
            nextEl: '.swiper-button-next',
            prevEl: '.swiper-button-prev',
        },
        observer: true,                         //开启监视者模式
        observeParents: true,                   //开启监视父类
        observeSlideChildren: true,             //开启监视子类
        mousewheel: {
            sensitivity: 0.3,                   //鼠标滚轮的控制速率
        },
        grabCursor: true,                       //开启抓手模式
    });
    /*管理元素*/
    const elements = {
        iframe_tab_container: $('#iframe-tab-container'),
        iframe_tab: $('#iframe-tab'),
        iframe_tab_link: $('#iframe-tab .nav-link'),
        iframe_tabContent: $('#iframe-tabContent'),
        item_close: $('.iframe-tab-close-btn'),
        menu_link: $('.main-menu .nav-link:not(.navbar-header .nav-link), .header-navbar.navbar-horizontal .nav.nav-sidebar .nav-link:not(.dropdown-toggle)'),
        menu_content: $('.main-menu-content .sidebar, .header-navbar.navbar-horizontal .main-menu-content'),
        drop_menu_link: $(".dropdown-menu .dropdown-item"),
        drop_menu: $(".dropdown-menu"),
        open_url: $('#iframe_tab_open_url'),
        open_title: $('#iframe_tab_open_title'),
        home_url: $('#iframe_tab_home_url'),
        home_title: $('#iframe_tab_home_title'),
        refresh_patterns: $('#iframe_tab_refresh_patterns'),
    }
    /*定义模板*/
    const iframeTabTemplate = {
        normalizeIframeUrl(url) {
            if (!url) {
                return url
            }

            try {
                let resolvedUrl = new URL(url, window.location.origin)
                resolvedUrl.searchParams.set('iframe_tab_child', '1')
                return resolvedUrl.toString()
            } catch (e) {
                let separator = url.indexOf('?') === -1 ? '?' : '&'
                if (url.indexOf('iframe_tab_child=1') !== -1) {
                    return url
                }
                return `${url}${separator}iframe_tab_child=1`
            }
        },
        normalizeMenuUrl(url) {
            if (!url) {
                return url
            }

            try {
                let resolvedUrl = new URL(url, window.location.origin)
                resolvedUrl.searchParams.delete('iframe_tab_child')

                let query = resolvedUrl.searchParams.toString()

                return `${resolvedUrl.origin}${resolvedUrl.pathname}${query ? '?' + query : ''}`
            } catch (e) {
                return url.replace(/([?&])iframe_tab_child=1(&|$)/, function (match, prefix, suffix) {
                    if (prefix === '?' && suffix === '&') {
                        return '?'
                    }

                    if (prefix === '&') {
                        return ''
                    }

                    return ''
                }).replace(/\?$/, '')
            }
        },
        tabItem(html, id, use_close = true, url = '') {
            /*标签栏*/
            let close_html = ''
            let first_tag = 'data-first=1'
            if (use_close) {
                close_html = '<span title="关闭标签页" class="iframe-tab-close-btn"><i class="fa fa-minus-circle"></i></span>'
                first_tag = 'data-first=0'
            }
            let normalizedUrl = this.normalizeMenuUrl(url || '')
            return `
            <li class="nav-item swiper-slide" role="presentation">
                    <a ${first_tag} data-url="${normalizedUrl}" class="nav-link active" id="iframe-home-${id}" data-toggle="pill" href="#iframe-${id}" role="tab" aria-controls="iframe-${id}" aria-selected="true">
                        ${html}
                        ${close_html}
                    </a>
            </li>
            `
        },
        tabContentItem(url, id) {
            /*标签对应内容*/
            let iframeUrl = this.normalizeIframeUrl(url)
            return `
            <div class="tab-pane fade show active" id="iframe-${id}" role="tabpanel" aria-labelledby="iframe-home-${id}">
                <div class="iframe-tab-pane-loading" data-loading-for="iframe-${id}">
                    <div class="iframe-tab-pane-loading__inner">
                        <div class="iframe-tab-pane-loading__title">页面加载中</div>
                        <div class="iframe-tab-pane-loading__desc">内容较多时会稍慢一点，请稍候。</div>
                        <button type="button" class="btn btn-sm btn-outline-primary iframe-tab-pane-loading__retry">重新加载</button>
                    </div>
                </div>
                <iframe
                        class="iframe-tab-frame is-loading"
                        style="position: absolute;width: 100%;height: 100%;left: 0;top: 0;right: 0;bottom: 0;"
                        src="${iframeUrl}" width="100%" height="100%" frameborder="no" border="0" marginwidth="0"
                        marginheight="0"
                        scrolling-x="no" scrolling-y="auto" allowtransparency="yes"></iframe>
            </div>
            `
        }
    }
    /*Tab逻辑处理*/
    const iframeTab = {
        TAB_STORAGE_KEY: $('#use_id').val() + '_6d9e562706a26cd2',
        ACTIVE_TAB_STORAGE_KEY: $('#use_id').val() + '_6d9e562706a26cd2_active',
        HOME_VERSION_STORAGE_KEY: $('#use_id').val() + '_6d9e562706a26cd2_home_version',
        CLICK_TAB: '',
        USE_CACHE: parseInt($('#iframe_tab_cache').val()),
        LAZY_LOAD: parseInt($('#iframe_tab_lazy_load').val()),
        ACTIVATE_REFRESH_PATTERNS: [],
        parseRefreshPatterns() {
            let raw = (elements.refresh_patterns.val() || '').trim()
            if (!raw) {
                return []
            }

            try {
                let patterns = JSON.parse(raw)
                if (Array.isArray(patterns)) {
                    return patterns.filter(function (item) {
                        return typeof item === 'string' && item.trim() !== ''
                    }).map(function (item) {
                        return item.trim()
                    })
                }
            } catch (e) {}

            return []
        },
        matchRefreshPattern(url, pattern) {
            if (!url || !pattern) {
                return false
            }

            let normalizedUrl = iframeTabTemplate.normalizeMenuUrl(url)
            let normalizedPattern = String(pattern).trim()
            if (!normalizedPattern) {
                return false
            }

            let escapedPattern = normalizedPattern.replace(/[.+?^${}()|[\]\\]/g, '\\$&').replace(/\*/g, '.*')
            let regex = new RegExp(escapedPattern)

            return regex.test(normalizedUrl)
        },
        shouldRefreshOnActivate(tabLink) {
            if (!tabLink || !tabLink.length) {
                return false
            }

            if (String(tabLink.attr('data-tab-refresh') || '') === '1') {
                return true
            }

            let tabUrl = (tabLink.attr('data-url') || '').trim()
            if (!tabUrl || !this.ACTIVATE_REFRESH_PATTERNS.length) {
                return false
            }

            for (let i = 0; i < this.ACTIVATE_REFRESH_PATTERNS.length; i++) {
                if (this.matchRefreshPattern(tabUrl, this.ACTIVATE_REFRESH_PATTERNS[i])) {
                    return true
                }
            }

            return false
        },
        reloadPaneIframe(contentElement) {
            if (!contentElement || !contentElement.length) {
                return
            }

            let iframe = contentElement.find('iframe')
            if (!iframe.length) {
                return
            }

            let refreshUrl = (iframe.attr('src') || '').trim()

            try {
                let iframeWindow = iframe.get(0).contentWindow
                if (iframeWindow && iframeWindow.location && iframeWindow.location.href && iframeWindow.location.href !== 'about:blank') {
                    refreshUrl = iframeWindow.location.href
                }
            } catch (e) {}

            if (!refreshUrl) {
                return
            }

            iframe.removeAttr('data-iframe-loaded')
            this.showPaneLoading(contentElement)
            iframe.attr('src', '')
            iframe.attr('src', iframeTabTemplate.normalizeIframeUrl(refreshUrl))
        },
        normalizeCachedTabContentHtml(html) {
            if (!html) {
                return html
            }

            let wrapper = $('<div>').html(html)
            wrapper.find('.tab-pane').each(function () {
                let pane = $(this)
                let iframe = pane.find('iframe')
                let loading = pane.find('.iframe-tab-pane-loading')

                pane.removeData('iframeLoadingTimer')
                pane.removeData('iframeLoadingWatchdog')
                pane.removeData('iframeBlankReloaded')
                iframe.removeAttr('data-iframe-loaded')
                iframe.addClass('is-loading')
                loading.removeClass('d-none is-failed')
            })

            return wrapper.html()
        },
        isLocaleSwitchLink(element) {
            let $element = $(element)
            let href = ($element.attr('href') || '').trim().toLowerCase()
            let onclick = ($element.attr('onclick') || '').trim()

            return href === 'javascript:;'
                || href === 'javascript:void(0);'
                || $element.hasClass('js-switch-locale')
                || $element.data('locale') !== undefined
                || onclick.indexOf('setLocale(') !== -1
        },
        storageGet() {
            let data = localStorage.getItem(this.TAB_STORAGE_KEY)
            return JSON.parse(data) === null ? {} : JSON.parse(data)
        },
        storageSet(id, value) {
            let list = this.storageGet()
            list[id] = value
            let data = JSON.stringify(list)
            localStorage.setItem(this.TAB_STORAGE_KEY, data)
            return list;
        },
        storageDelete(id) {
            /*删除一个*/
            let data = this.storageGet()
            if (data[id]) {
                delete (data[id])
                localStorage.setItem(this.TAB_STORAGE_KEY, JSON.stringify(data))
            }
            return data
        },
        storageDeleteAll() {
            /*删除所有*/
            localStorage.removeItem(this.TAB_STORAGE_KEY)
            localStorage.removeItem(this.ACTIVE_TAB_STORAGE_KEY)
            sessionStorage.removeItem(this.ACTIVE_TAB_STORAGE_KEY)
        },
        resetCacheWhenHomeVersionChanged() {
            let homeVersion = ($('#iframe_tab_home_version').val() || '').trim()
            if (!homeVersion) {
                return
            }

            if ((localStorage.getItem(this.HOME_VERSION_STORAGE_KEY) || '') === homeVersion) {
                return
            }

            this.storageDeleteAll()
            localStorage.setItem(this.HOME_VERSION_STORAGE_KEY, homeVersion)
        },
        extractTabId(linkId) {
            if (!linkId) {
                return ''
            }

            return String(linkId).replace(/^iframe-home-/, '')
        },
        setActiveTabId(contentId) {
            if (!contentId || contentId.indexOf('#iframe-') !== 0) {
                return
            }
            let activeTabId = contentId.replace('#iframe-', '')
            sessionStorage.setItem(this.ACTIVE_TAB_STORAGE_KEY, activeTabId)
            localStorage.setItem(this.ACTIVE_TAB_STORAGE_KEY, activeTabId)
        },
        getActiveTabId() {
            return (sessionStorage.getItem(this.ACTIVE_TAB_STORAGE_KEY) || localStorage.getItem(this.ACTIVE_TAB_STORAGE_KEY) || '').trim()
        },
        syncPageOpenState(url, title) {
            if (!url) {
                return
            }

            try {
                let currentUrl = new URL(window.location.href)
                currentUrl.searchParams.set('open', iframeTabTemplate.normalizeMenuUrl(url))

                if (title) {
                    currentUrl.searchParams.set('open_title', title)
                }

                window.history.replaceState({}, '', currentUrl.toString())
            } catch (e) {}
        },
        syncPageOpenStateFromTab(tabLink, iframe) {
            if (!tabLink || !tabLink.length) {
                return
            }

            let url = (tabLink.attr('data-url') || '').trim()
            let title = $.trim(tabLink.text() || '')

            if (iframe && iframe.length) {
                try {
                    let iframeWindow = iframe.get(0).contentWindow
                    if (iframeWindow && iframeWindow.document) {
                        let currentTableCard = iframeWindow.document.querySelector('.table-card[data-current]')

                        if (currentTableCard && currentTableCard.getAttribute('data-current')) {
                            url = String(currentTableCard.getAttribute('data-current'))
                        }
                    }

                    if ((!url || url === (tabLink.attr('data-url') || '').trim()) && iframeWindow && iframeWindow.location && iframeWindow.location.href) {
                        url = iframeWindow.location.href
                    }
                } catch (e) {}
            }

            if (!url) {
                return
            }

            this.syncPageOpenState(url, title)
        },
        syncPageOpenStateFromActiveTab() {
            let activeTab = this.findIframeTabActiveElement()
            if (!activeTab.length) {
                return
            }

            let pane = $(activeTab.attr('href'))
            let iframe = pane.find('iframe')

            this.syncPageOpenStateFromTab(activeTab, iframe)
        },
        extractUrlFromTabContentHtml(tabContentHtml) {
            if (!tabContentHtml) {
                return ''
            }

            try {
                let container = document.createElement('div')
                container.innerHTML = tabContentHtml
                let iframe = container.querySelector('iframe')
                return iframe ? iframeTabTemplate.normalizeMenuUrl(iframe.getAttribute('src') || '') : ''
            } catch (e) {
                return ''
            }
        },
        hydrateTabDataUrl(tabElement, cacheItem) {
            if (!tabElement || !tabElement.length) {
                return ''
            }

            let currentUrl = (tabElement.attr('data-url') || '').trim()
            if (currentUrl) {
                return currentUrl
            }

            let cacheUrl = ''
            if (cacheItem) {
                cacheUrl = (cacheItem.tab_url || '').trim() || this.extractUrlFromTabContentHtml(cacheItem.tab_content_html)
            }

            if (cacheUrl) {
                tabElement.attr('data-url', cacheUrl)
                return cacheUrl
            }

            return ''
        },
        findExistingTabByUrl(url) {
            let normalizedUrl = iframeTabTemplate.normalizeMenuUrl(url || '')

            if (!normalizedUrl) {
                return $()
            }

            let matchedTab = $()

            elements.iframe_tab.find('.nav-link').each(function () {
                let currentUrl = iframeTabTemplate.normalizeMenuUrl($(this).attr('data-url') || '')

                if (currentUrl === normalizedUrl) {
                    matchedTab = $(this)
                    return false
                }
            })

            return matchedTab
        },
        clearDefaultMenuEvent() {
            elements.menu_link.unbind('click')
            elements.drop_menu_link.unbind('click')
            $('.navbar-header').find('a').unbind('click')
            $('.horizontal-navbar-brand').find('a').unbind('click')
            let items = elements.menu_content.find('li')
            items.find('a').click(function (e) {
                let href = $(this).attr('href');
                if (!href || href === '#') {
                    return;
                }
                e.preventDefault()
                items.find('.nav-link').removeClass('active');
                $(this).addClass('active')
            })
            elements.drop_menu.find('.dropdown-item').click(function (e) {
                let href = $(this).attr('href');
                if (!href || href === '#') {
                    return;
                }
                e.preventDefault()
            })
            elements.menu_content.find('a.dropdown-toggle, a[href="#"]').unbind('click').click(function (e) {
                let item = $(this)
                let dropdown = item.closest('.dropdown, .dropdown-submenu, .nav-item')

                if (!dropdown.length) {
                    return
                }

                e.preventDefault()
                e.stopPropagation()

                let siblings = dropdown.siblings('.dropdown, .dropdown-submenu, .nav-item')
                siblings.removeClass('show menu-open').find('.dropdown-menu').removeClass('show')

                dropdown.toggleClass('show')
                dropdown.toggleClass('menu-open')
                dropdown.children('.dropdown-menu').toggleClass('show')
            })
        },
        menuClick() {
            let items = elements.menu_content.find('li')
            /*左侧菜单监听*/
            items.find('a').click(iframeTab.menuClickCallback);
            /*顶部菜单监听*/
            elements.drop_menu.find('a').click(iframeTab.menuClickCallback)
            /*点击logo重定向*/
            $('.navbar-header').find('a').click(function () {
                location.href = $(this).attr('href')
            })
            $('.horizontal-navbar-brand').find('a').click(function () {
                location.href = $(this).attr('href')
            })
            $(document).on('click', function () {
                elements.menu_content.find('.dropdown, .dropdown-submenu, .nav-item').removeClass('show menu-open')
                elements.menu_content.find('.dropdown-menu').removeClass('show')
            })
            elements.menu_content.on('click', '.dropdown-menu', function (e) {
                e.stopPropagation()
            })
        },
        menuClickCallback: function () {
            if (iframeTab.isLocaleSwitchLink(this)) {
                return
            }
            let html = $(this).html(),
                href = $(this).attr('href'),
                id = iframeTab.generateID(href)
            if (!href || href === '#' || href.toLowerCase() === 'javascript:;' || href.toLowerCase() === 'javascript:void(0);') {
                return
            }
            /*登出跳转*/
            if (href.indexOf("logout") !== -1) {
                location.href = href
                return
            }
            let tab_html = iframeTabTemplate.tabItem(html, id, true, href),    //生成tab的html
                tab_content_html = iframeTabTemplate.tabContentItem(href, id),  //生成tab content的html
                choose_element = iframeTab.findIframeTabActiveElement()
            /*移除tab bar 选中样式*/
            iframeTab.removeTabBarStyle()
            /*更新选中缓存中的tab bar*/
            iframeTab.cacheUpdateTabBar(choose_element)
            /*判断tab是否已经存在，不存在添加，存在则更新*/
            if (elements.iframe_tab.find(`#iframe-home-${id}`).length <= 0) {
                swiper.appendSlide(tab_html)
                elements.iframe_tabContent.append(tab_content_html)
                let iframeTab_element = $(`#iframe-home-${id}`),             //获取tab的元素对象
                    _index = iframeTab_element.parents('.nav-item').index(), //获取下标
                    content_element = $(`#iframe-${id}`)                     //获取tab content的元素对象
                iframeTab.bindIframeLoadState(content_element)
                swiper.slideTo(_index)
                swiper.updateSlides()
                iframeTab_element.addClass('active')
                iframeTab_element.attr('aria-selected', 'true')
                content_element.addClass('active')
                content_element.addClass('show')
                iframeTab.syncLocationHash(`#iframe-${id}`)
                iframeTab.cacheUpdateTabBar(iframeTab_element)
            } else {
                /*模拟点击*/
                elements.iframe_tab.find(`#iframe-home-${id}`).click()
            }
        },
        joinFirstMenu() {
            /*获取第一条菜单包括图标信息并添加到tab*/
            let home_url = (elements.home_url.val() || '').trim()
            let home_title = (elements.home_title.val() || '').trim()
            if (!home_url) {
                return;
            }
            let home_menu = elements.menu_link.filter(function () {
                return ($(this).attr('href') || '') === home_url
            }).first()
            let home_html = home_menu.length ? home_menu.html() : (home_title || home_url)
            let home_id = this.generateID(home_url);
            if (elements.iframe_tab.find(`#iframe-home-${home_id}`).length > 0 || this.findExistingTabByUrl(home_url).length > 0) {
                return;
            }
            swiper.appendSlide(iframeTabTemplate.tabItem(home_html, home_id, false, home_url))
            elements.iframe_tabContent.append(iframeTabTemplate.tabContentItem(home_url, home_id))
            swiper.updateSlides();
            iframeTab.applyMenuActiveByUrl(home_url)
        },
        removeTabBarStyle() {
            /*移除tab bar 选中样式*/
            elements.iframe_tab.find('.nav-link').removeClass('active');
            elements.iframe_tab.find('.nav-link').attr('aria-selected', 'false')
            elements.iframe_tabContent.find('.tab-pane').removeClass('active', 'show')
        },
        syncLocationHash(contentId) {
            if (!contentId || contentId.indexOf('#iframe-') !== 0) {
                return
            }

            this.setActiveTabId(contentId)

            try {
                let url = new URL(window.location.href)
                url.hash = contentId
                window.history.replaceState({}, '', url.toString())
            } catch (e) {
                window.location.hash = contentId
            }
        },
        closeAdjacentOperate(adjacent) {
            /*关闭标签后相邻兄弟元素的选择*/
            adjacent.find(`.nav-link`).click()
            iframeTab.removeTabBarStyle()
            adjacent.find(`.nav-link`).addClass('active');
            adjacent.find(`.nav-link`).attr('aria-selected', 'true')
            let content_href = adjacent.find('.nav-link').attr('href')
            elements.iframe_tabContent.find(content_href).addClass('active')
            elements.iframe_tabContent.find(content_href).addClass('show')
            iframeTab.syncLocationHash(content_href)
        },
        iframeTabEventRegister() {
            /*按关闭按钮关闭*/
            $(document).on('click', '.iframe-tab-close-btn', function (e) {
                let can_delete = $(this).parents(".nav-link").attr('data-first');
                if (can_delete === '1') {
                    return;
                }
                let parent_obj = $(this).parents(".nav-item")
                /*如果是关闭当前选中的标签页，则下一个有选下一个，否则选上一个*/
                if ($(this).parents(".nav-link").hasClass('active')) {
                    let next_obj = parent_obj.next()
                    let prev_obj = parent_obj.prev()
                    if (next_obj.length > 0) {
                        iframeTab.closeAdjacentOperate(next_obj)
                    } else {
                        iframeTab.closeAdjacentOperate(prev_obj)
                    }
                }
                let tab_content_element = $($(this).parents(".nav-link").attr('href'))
                parent_obj.remove()
                tab_content_element.remove()
                if (iframeTab.USE_CACHE === 1) {
                    iframeTab.storageDelete(iframeTab.extractTabId($(this).parents(".nav-link").attr('id')))
                }
                e.stopPropagation()
            });
            /*双击关闭*/
            $(document).on('dblclick', '#iframe-tab .nav-link', function (e) {
                $(this).find('.iframe-tab-close-btn').click()
                return false
            });
            /*联动菜单样式*/
            $(document).on('click', '#iframe-tab .nav-link', function () {
                let content_id = $(this).attr('href')
                let content_without_suffix = content_id.replace('#iframe-', "")
                let existingContent = $(`${content_id}`)
                let existingIframe = existingContent.find('iframe')
                if (existingContent.length <= 0 || existingIframe.length <= 0 || !existingIframe.attr('src')) {
                    let cachedItem = iframeTab.storageGet()[content_without_suffix]
                    let tabUrl = iframeTab.hydrateTabDataUrl($(this), cachedItem)

                    if (existingContent.length > 0) {
                        existingContent.remove()
                    }

                    if (iframeTab.LAZY_LOAD === 1 && cachedItem && cachedItem.tab_content_html) {
                        elements.iframe_tabContent.append(iframeTab.normalizeCachedTabContentHtml(cachedItem.tab_content_html))
                    } else if (tabUrl) {
                        elements.iframe_tabContent.append(iframeTabTemplate.tabContentItem(tabUrl, content_without_suffix))
                    }

                    iframeTab.removeTabBarStyle()
                }
                let content_element = $(`${content_id}`)
                iframeTab.bindIframeLoadState(content_element)
                iframeTab.linkMenuAndIframeTab(content_id)
                $(this).addClass('active');
                $(this).attr('aria-selected', 'true')
                content_element.addClass('active')
                content_element.addClass('show')
                let _index = $(this).parents('.nav-item').index()
                swiper.slideTo(_index)
                swiper.updateSlides();
                iframeTab.syncLocationHash(content_id)
                iframeTab.cacheUpdateTabBar($(this))
                iframeTab.syncPageOpenStateFromTab($(this), content_element.find('iframe'))

            });
            /*获取上一个活动标签*/
            $(document).on('hidden.bs.tab', '#iframe-tab .nav-link', function (event) {
                iframeTab.cacheUpdateTabBar($(event.target))
            });
            $(document).on('shown.bs.tab', '#iframe-tab .nav-link', function (event) {
                if (!event.relatedTarget) {
                    return
                }

                let currentTab = $(event.target)
                if (!iframeTab.shouldRefreshOnActivate(currentTab)) {
                    return
                }

                let contentElement = $(currentTab.attr('href'))
                iframeTab.reloadPaneIframe(contentElement)
            });

            /*右键菜单*/
            $(document).on('mousedown', '#iframe-tab .nav-link', function (event) {
                document.oncontextmenu = function () {
                    return false;
                }
                // let event = window.event || arguments.callee.caller.arguments[0]
                let key = event.which;//获取鼠标键位
                if (key === 3) {//1：代表左键；2：代表中键；3：代表右键
                    //获取右键点击坐标
                    let x = event.clientX;
                    let y = event.clientY;
                    $('.mouse-click-menu').show().css({left: x, top: y});
                    iframeTab.CLICK_TAB = $(this)
                }
            });
        },
        rightClickEventRegister() {
            /*复制标签页链接*/
            $(document).on('click', '.tab-copy-link', function () {
                if (iframeTab.CLICK_TAB !== '') {
                    let content_id = iframeTab.CLICK_TAB.attr("href")
                    let content = $(`${content_id} > iframe`).attr("src")
                    let $temp = $('<input>');
                    $("body").append($temp);
                    $temp.val(content).select();
                    document.execCommand("copy");
                    $temp.remove();
                    $(this).tooltip('show');
                    Dcat.success('复制成功');
                }
                document.oncontextmenu = function () {
                    return true;
                }
            })
            /*在新标签页中打开*/
            $(document).on('click', '.tab-open-link', function () {
                if (iframeTab.CLICK_TAB !== '') {
                    let content_id = iframeTab.CLICK_TAB.attr("href")
                    let content = $(`${content_id} > iframe`).attr("src")
                    window.open(content)
                }
                document.oncontextmenu = function () {
                    return true;
                }
            })
            /*关闭所有标签页*/
            $(document).on('click', '.tab-close-all', function () {
                if (iframeTab.CLICK_TAB !== '') {
                    elements.iframe_tab.find('.nav-link').each(function () {
                        let can_delete = $(this).attr('data-first');
                        if (can_delete === '1') {
                            return;
                        }
                        $(this).find('.iframe-tab-close-btn').click()
                    })
                }
                document.oncontextmenu = function () {
                    return true;
                }
            })
            /*关闭其他标签页*/
            $(document).on('click', '.tab-close-other', function () {
                if (iframeTab.CLICK_TAB !== '') {
                    elements.iframe_tab.find('.nav-link').each(function () {
                        let can_delete = $(this).attr('data-first');
                        if (can_delete === '1') {
                            return;
                        }
                        if (iframeTab.CLICK_TAB.attr('id') === $(this).attr('id')) {
                            iframeTab.CLICK_TAB.click()
                            return;
                        }
                        iframeTab.cacheUpdateTabBar($(this))
                        $(this).find('.iframe-tab-close-btn').click()
                    })
                }
                document.oncontextmenu = function () {
                    return true;
                }
            })
            /*清空缓存*/
            $(document).on('click', '.tab-clear-cache', function () {
                iframeTab.storageDeleteAll()
                Dcat.success('缓存已清空');
                elements.iframe_tab.html('')
                elements.iframe_tabContent.html('')
                iframeTab.joinFirstMenu()
                elements.menu_content.find('.nav-link.active').removeClass('active')
                $(elements.menu_link[0]).addClass('active')
                document.oncontextmenu = function () {
                    return true;
                }
            })
            /*刷新当前标签页*/
            $(document).on('click', '.tab-refresh', function () {
                if (iframeTab.CLICK_TAB !== '') {
                    let content_element = $(`${iframeTab.CLICK_TAB.attr("href")}`),
                        iframe_element = content_element.find('iframe'),
                        src = iframe_element.attr('src')
                    iframe_element.removeAttr('data-iframe-loaded')
                    iframeTab.showPaneLoading(content_element)
                    iframe_element.attr('src', '')
                    iframe_element.attr('src', src)
                    Dcat.success('页面已刷新')
                }
                document.oncontextmenu = function () {
                    return true;
                }
            })
            /*全局点击事件，释放浏览器默认右键菜单*/
            $(document).on('click', function () {
                document.oncontextmenu = function () {
                    return true;
                }
                $('.mouse-click-menu').hide();
            })
        },
        cacheInit() {
            this.resetCacheWhenHomeVersionChanged()
            if (this.USE_CACHE === 0) {
                this.storageDeleteAll()
                return;
            }
            let first_url = (elements.home_url.val() || '').trim()
            let first_id = first_url ? this.generateID(first_url) : ''
            let list = this.storageGet()
            console.log(list);
            if (list.length === 0 || JSON.stringify(list) === "{}") {
                if (first_id) {
                    let first_tab = elements.iframe_tab.find(`#iframe-home-${first_id}`)
                    let first_content = elements.iframe_tabContent.find(`#iframe-${first_id}`)

                    this.removeTabBarStyle()
                    first_tab.addClass('active').attr('aria-selected', 'true')
                    first_content.addClass('active show')
                    this.applyMenuActiveByUrl(first_url)
                    this.syncLocationHash(`#iframe-${first_id}`)
                }
                return;
            }
            iframeTab.removeTabBarStyle()
            for (let i in list) {
                let cacheItem = list[i] || {}
                let cacheUrl = iframeTabTemplate.normalizeMenuUrl(cacheItem.tab_url || '')

                if ((first_id && i === first_id) || (first_url && cacheUrl === iframeTabTemplate.normalizeMenuUrl(first_url))) {
                    this.storageDelete(i)
                    continue
                }

                let existingTab = elements.iframe_tab.find(`#iframe-home-${i}`)
                if (existingTab.length > 0) {
                    existingTab.attr('data-first', '0')
                    this.hydrateTabDataUrl(existingTab, cacheItem)
                    continue
                }

                let sameUrlTab = this.findExistingTabByUrl(cacheItem.tab_url || '')
                if (sameUrlTab.length > 0) {
                    sameUrlTab.attr('data-first', sameUrlTab.attr('id') === `iframe-home-${first_id}` ? '1' : '0')
                    this.hydrateTabDataUrl(sameUrlTab, cacheItem)
                    this.storageDelete(i)
                    continue
                }

                let tabHtml = String(cacheItem.tab_html || '')
                    .replace(/data-first=1/g, 'data-first=0')
                    .replace(/data-first="1"/g, 'data-first="0"')
                swiper.appendSlide(tabHtml)
                this.hydrateTabDataUrl(elements.iframe_tab.find(`#iframe-home-${i}`), cacheItem)
            }
            if (iframeTab.LAZY_LOAD === 0) {
                for (let i in list) {
                    if (first_id && i === first_id) {
                        continue
                    }
                    if (elements.iframe_tabContent.find(`#iframe-${i}`).length > 0) {
                        continue
                    }

                    elements.iframe_tabContent.append(this.normalizeCachedTabContentHtml(list[i].tab_content_html))
                }
            }
            let preferredContentId = ''
            let currentHash = window.location.hash || ''
            if (currentHash && currentHash.indexOf('#iframe-') === 0) {
                preferredContentId = currentHash
            } else {
                let activeId = this.getActiveTabId()
                if (activeId) {
                    preferredContentId = `#iframe-${activeId}`
                }
            }

            if (preferredContentId) {
                let preferred_id = preferredContentId.replace('#iframe-', '')
                let preferred_tab = elements.iframe_tab.find(`#iframe-home-${preferred_id}`)
                let preferred_content = elements.iframe_tabContent.find(preferredContentId)
                let preferred_cache_item = list[preferred_id]
                let preferred_iframe = preferred_content.find('iframe')

                if (preferred_tab.length > 0) {
                    if (preferred_content.length <= 0 || preferred_iframe.length <= 0 || !preferred_iframe.attr('src')) {
                        let tabUrl = this.hydrateTabDataUrl(preferred_tab, preferred_cache_item)

                        if (preferred_content.length > 0) {
                            preferred_content.remove()
                        }

                        if (iframeTab.LAZY_LOAD === 1 && preferred_cache_item && preferred_cache_item.tab_content_html) {
                            elements.iframe_tabContent.append(this.normalizeCachedTabContentHtml(preferred_cache_item.tab_content_html))
                        } else if (tabUrl) {
                            elements.iframe_tabContent.append(iframeTabTemplate.tabContentItem(tabUrl, preferred_id))
                        }

                        preferred_content = elements.iframe_tabContent.find(preferredContentId)
                    }

                    if (preferred_content.length > 0) {
                        iframeTab.bindIframeLoadState(preferred_content)
                        iframeTab.removeTabBarStyle()
                        preferred_tab.addClass('active').attr('aria-selected', 'true')
                        preferred_content.addClass('active show')
                        iframeTab.linkMenuAndIframeTab(preferredContentId)
                        iframeTab.syncLocationHash(preferredContentId)
                        return;
                    }
                }
            }
            /*如果html里面没有active,则默认使用第一个*/
            let active_ele = iframeTab.findIframeTabActiveElement()
            let is_first = false;
            if (active_ele.length <= 0) {
                is_first = true;
                let first_tab = first_id ? elements.iframe_tab.find(`#iframe-home-${first_id}`) : elements.iframe_tab.find('.nav-link').first()
                if (first_tab.length > 0) {
                    first_tab.click()
                    return;
                }

                if (!first_url) {
                    return;
                }
                $(`#iframe-home-${first_id}`).click()
                return;
            }
            let content_id = active_ele.attr('href')
            if (iframeTab.LAZY_LOAD === 1 && !is_first) {
                let content_without_suffix = content_id.replace('#iframe-', "")
                let cachedItem = list[content_without_suffix]
                let activeTab = elements.iframe_tab.find(`.nav-link[href="${content_id}"]`)
                let tabUrl = this.hydrateTabDataUrl(activeTab, cachedItem)
                let activeContent = $(`${content_id}`)
                let activeIframe = activeContent.find('iframe')

                if (activeContent.length > 0 && (activeIframe.length <= 0 || !activeIframe.attr('src'))) {
                    activeContent.remove()
                }

                if (cachedItem && cachedItem.tab_content_html) {
                    elements.iframe_tabContent.append(this.normalizeCachedTabContentHtml(cachedItem.tab_content_html))
                } else if (tabUrl) {
                    elements.iframe_tabContent.append(iframeTabTemplate.tabContentItem(tabUrl, content_without_suffix))
                }
            }
            iframeTab.bindIframeLoadState($(`${content_id}`))
            iframeTab.linkMenuAndIframeTab(content_id)
            iframeTab.syncLocationHash(content_id)
        },
        cacheUpdateTabBar(tab_link_element) {
            if (this.USE_CACHE !== 1) {
                return;
            }
            /*更新TabBar的html*/
            if (tab_link_element.attr('data-first') !== '1') {
                let id = this.extractTabId(tab_link_element.attr('id'));
                let tab_html = tab_link_element.parents('li').prop('outerHTML')
                let tab_content_html = this.normalizeCachedTabContentHtml($(`#iframe-${id}`).prop('outerHTML'))
                let tab_url = (tab_link_element.attr('data-url') || '').trim()
                if (!tab_url) {
                    tab_url = this.extractUrlFromTabContentHtml(tab_content_html)
                }
                this.storageSet(id, {id, tab_html, tab_content_html, tab_url})
            }
        },
        findIframeTabActiveElement() {
            /*寻找tab里面选中的元素并返回*/
            return elements.iframe_tab.find('.nav-link.active')
        },
        ensureLoadingPlaceholder(contentElement) {
            if (!contentElement || !contentElement.length) {
                return $()
            }

            let loading = contentElement.find('.iframe-tab-pane-loading')
            if (loading.length > 0) {
                return loading
            }

            contentElement.prepend(`
                <div class="iframe-tab-pane-loading" data-loading-for="${contentElement.attr('id') || ''}">
                    <div class="iframe-tab-pane-loading__inner">
                        <div class="iframe-tab-pane-loading__title">页面加载中</div>
                        <div class="iframe-tab-pane-loading__desc">内容较多时会稍慢一点，请稍候。</div>
                        <button type="button" class="btn btn-sm btn-outline-primary iframe-tab-pane-loading__retry">重新加载</button>
                    </div>
                </div>
            `)

            return contentElement.find('.iframe-tab-pane-loading')
        },
        showPaneLoading(contentElement) {
            if (!contentElement || !contentElement.length) {
                return
            }

            let loading = this.ensureLoadingPlaceholder(contentElement)
            let iframe = contentElement.find('iframe')

            loading.removeClass('d-none is-failed')
            iframe.addClass('is-loading')

            let previousTimer = contentElement.data('iframeLoadingTimer')
            if (previousTimer) {
                clearTimeout(previousTimer)
            }

            let slowTimer = setTimeout(function () {
                loading.addClass('is-failed')
            }, 60000)

            contentElement.data('iframeLoadingTimer', slowTimer)
            this.startIframeLoadingWatchdog(contentElement)
        },
        hidePaneLoading(contentElement) {
            if (!contentElement || !contentElement.length) {
                return
            }

            let loading = contentElement.find('.iframe-tab-pane-loading')
            let iframe = contentElement.find('iframe')
            let previousTimer = contentElement.data('iframeLoadingTimer')
            let watchdogTimer = contentElement.data('iframeLoadingWatchdog')

            if (previousTimer) {
                clearTimeout(previousTimer)
                contentElement.removeData('iframeLoadingTimer')
            }
            if (watchdogTimer) {
                clearTimeout(watchdogTimer)
                contentElement.removeData('iframeLoadingWatchdog')
            }

            loading.addClass('d-none').removeClass('is-failed')
            contentElement.removeData('iframeBlankReloaded')
            iframe.attr('data-iframe-loaded', '1')
            iframe.removeClass('is-loading')
        },
        startIframeLoadingWatchdog(contentElement) {
            if (!contentElement || !contentElement.length) {
                return
            }

            let previousTimer = contentElement.data('iframeLoadingWatchdog')
            if (previousTimer) {
                clearTimeout(previousTimer)
            }

            let watchdogTimer = setTimeout(function () {
                iframeTab.recoverIframeLoading(contentElement)
            }, 3000)

            contentElement.data('iframeLoadingWatchdog', watchdogTimer)
        },
        recoverIframeLoading(contentElement) {
            if (!contentElement || !contentElement.length) {
                return
            }

            let iframe = contentElement.find('iframe')
            if (!iframe.length || iframe.attr('data-iframe-loaded') === '1') {
                return
            }

            let src = (iframe.attr('src') || '').trim()
            if (!src) {
                return
            }

            try {
                let iframeWindow = iframe.get(0).contentWindow
                let iframeDocument = iframeWindow ? iframeWindow.document : null
                let iframeHref = iframeWindow && iframeWindow.location ? iframeWindow.location.href : ''
                let iframeReady = iframeDocument ? iframeDocument.readyState : ''
                let iframeBody = iframeDocument ? iframeDocument.body : null
                let hasBodyContent = iframeBody && iframeBody.childNodes && iframeBody.childNodes.length > 0

                if (iframeReady === 'complete' && iframeHref && iframeHref !== 'about:blank' && hasBodyContent) {
                    this.syncTabStateFromIframe(contentElement)
                    this.hidePaneLoading(contentElement)
                    return
                }

                if (iframeReady === 'complete' && (!iframeHref || iframeHref === 'about:blank' || !hasBodyContent) && !contentElement.data('iframeBlankReloaded')) {
                    contentElement.data('iframeBlankReloaded', 1)
                    iframe.removeAttr('data-iframe-loaded')
                    iframe.attr('src', '')
                    iframe.attr('src', iframeTabTemplate.normalizeIframeUrl(src))
                    this.showPaneLoading(contentElement)
                    return
                }
            } catch (e) {}

            this.startIframeLoadingWatchdog(contentElement)
        },
        syncTabStateFromIframe(contentElement) {
            if (!contentElement || !contentElement.length) {
                return
            }

            let iframe = contentElement.find('iframe')
            if (!iframe.length) {
                return
            }

            let paneId = contentElement.attr('id') || ''
            if (!paneId || paneId.indexOf('iframe-') !== 0) {
                return
            }

            let tabId = paneId.replace('iframe-', '')
            let tabLink = elements.iframe_tab.find(`#iframe-home-${tabId}`)
            if (!tabLink.length) {
                return
            }

            try {
                let iframeWindow = iframe.get(0).contentWindow
                let currentUrl = iframeWindow && iframeWindow.location ? iframeWindow.location.href : ''

                if (!currentUrl) {
                    return
                }

                if (iframeWindow && iframeWindow.document) {
                    let currentTableCard = iframeWindow.document.querySelector('.table-card[data-current]')

                    if (currentTableCard && currentTableCard.getAttribute('data-current')) {
                        currentUrl = String(currentTableCard.getAttribute('data-current'))
                    }
                }

                let normalizedUrl = iframeTabTemplate.normalizeMenuUrl(currentUrl)
                let normalizedIframeUrl = iframeTabTemplate.normalizeIframeUrl(currentUrl)
                let clonedContent = contentElement.clone()

                clonedContent.find('iframe').attr('src', normalizedIframeUrl)
                clonedContent.find('iframe').removeAttr('data-iframe-loaded').addClass('is-loading')
                clonedContent.find('.iframe-tab-pane-loading').removeClass('d-none is-failed')

                let tab_content_html = $('<div>').append(clonedContent).html()

                tabLink.attr('data-url', normalizedUrl)

                if (tabLink.attr('data-first') === '1') {
                    return
                }

                if (this.USE_CACHE === 1) {
                    let id = tabId
                    let list = this.storageGet()
                    let current = list[id] || {}
                    let tab_html = tabLink.parents('li').prop('outerHTML')

                    this.storageSet(id, Object.assign({}, current, {
                        id: id,
                        tab_html: tab_html,
                        tab_content_html: tab_content_html,
                        tab_url: normalizedUrl
                    }))
                }
            } catch (e) {}
        },
        syncAllTabStateFromIframes() {
            elements.iframe_tabContent.find('.tab-pane').each(function () {
                iframeTab.syncTabStateFromIframe($(this))
            })
        },
        bindIframeLoadState(contentElement) {
            if (!contentElement || !contentElement.length) {
                return
            }

            let iframe = contentElement.find('iframe')
            if (!iframe.length) {
                return
            }

            iframe.off('load.iframeTabLoading').on('load.iframeTabLoading', function () {
                iframeTab.syncTabStateFromIframe(contentElement)
                iframeTab.hidePaneLoading(contentElement)
                let paneId = contentElement.attr('id') || ''
                if (paneId) {
                    let tabId = paneId.replace('iframe-', '')
                    let tabLink = elements.iframe_tab.find(`#iframe-home-${tabId}`)
                    if (tabLink.hasClass('active')) {
                        iframeTab.syncPageOpenStateFromTab(tabLink, iframe)
                    }
                }
            })

            try {
                let iframeWindow = iframe.get(0).contentWindow
                let iframeDocument = iframeWindow ? iframeWindow.document : null
                let iframeHref = iframeWindow && iframeWindow.location ? iframeWindow.location.href : ''
                let iframeBody = iframeDocument ? iframeDocument.body : null
                let iframeReady = iframeDocument ? iframeDocument.readyState : ''

                if (
                    iframeReady === 'complete'
                    && iframeHref
                    && iframeHref !== 'about:blank'
                    && iframeBody
                    && iframeBody.childNodes
                    && iframeBody.childNodes.length > 0
                ) {
                    iframeTab.syncTabStateFromIframe(contentElement)
                    iframeTab.hidePaneLoading(contentElement)

                    let paneId = contentElement.attr('id') || ''
                    if (paneId) {
                        let tabId = paneId.replace('iframe-', '')
                        let tabLink = elements.iframe_tab.find(`#iframe-home-${tabId}`)
                        if (tabLink.hasClass('active')) {
                            iframeTab.syncPageOpenStateFromTab(tabLink, iframe)
                        }
                    }
                    return
                }
            } catch (e) {}

            if (iframe.attr('data-iframe-loaded') === '1') {
                this.hidePaneLoading(contentElement)
                return
            }

            this.showPaneLoading(contentElement)
        },
        linkMenuAndIframeTab(content_id) {
            /*链接Iframe tab和Menu*/
            let href = $(`${content_id} > iframe`).attr('src')
            iframeTab.applyMenuActiveByUrl(href)
        },
        applyMenuActiveByUrl(href) {
            let normalizedHref = iframeTabTemplate.normalizeMenuUrl(href)

            if (!normalizedHref) {
                return
            }

            let items = elements.menu_content.find('li')
            items.find('a').each(function () {
                let item_href = $(this).attr('href')
                if (!item_href || item_href === '#') {
                    return;
                }
                if (iframeTabTemplate.normalizeMenuUrl(item_href) === normalizedHref) {
                    items.find('.nav-link').removeClass('active');
                    $(this).addClass('active')
                    $(this).parents('.dropdown, .dropdown-submenu').children('.nav-link').addClass('active')
                    $(this).parents('.dropdown, .dropdown-submenu').addClass('show menu-open')
                    $(this).parents('.dropdown, .dropdown-submenu').children('.dropdown-menu').addClass('show')
                    let parent_obj = $(this).parents('.has-treeview')
                    if (parent_obj.length > 0 && !parent_obj.hasClass('menu-open')) {
                        parent_obj.find("a[href='#']").click()
                    }
                }
            })
        },
        ensureVisibleTab() {
            let firstTab = elements.iframe_tab.find('.nav-link').first()
            let currentHash = window.location.hash || ''
            if (currentHash && currentHash.indexOf('#iframe-') === 0) {
                let hashTab = elements.iframe_tab.find(`.nav-link[href="${currentHash}"]`)
                if (hashTab.length > 0) {
                    let hashId = currentHash.replace('#iframe-', '')
                    this.hydrateTabDataUrl(hashTab, this.storageGet()[hashId])
                    hashTab.click()
                    return
                }
            }

            let activeTab = this.findIframeTabActiveElement()
            if (activeTab.length > 0) {
                let activePane = $(activeTab.attr('href'))
                let activeIframe = activePane.find('iframe')
                if (activePane.length > 0 && activeIframe.length > 0 && activeIframe.attr('src')) {
                    this.linkMenuAndIframeTab(activeTab.attr('href'))
                    return
                }
            }
            if (firstTab.length > 0) {
                firstTab.click()
            }
        },
        init() {
            /*清除pjax默认菜单a标签点击事件*/
            this.clearDefaultMenuEvent()
            this.ACTIVATE_REFRESH_PATTERNS = this.parseRefreshPatterns()
            /*加入第一条默认菜单*/
            this.joinFirstMenu()
            /*菜单监听*/
            this.menuClick()
            /*事件注册*/
            this.iframeTabEventRegister()
            /*缓存标签页处理*/
            this.cacheInit()
            elements.iframe_tabContent.find('.tab-pane').each(function () {
                iframeTab.bindIframeLoadState($(this))
            })
            /*确保刷新后始终有可见tab内容*/
            this.ensureVisibleTab()
            /*右键事件注册*/
            this.rightClickEventRegister()
            /*兼容dcat夜间模式*/
            this.darkMode()
        },
        darkMode() {
            const storage = window.parent.localStorage || {
                    setItem: function () {
                    }, getItem: function () {
                    }
                },
                key = 'dcat-admin-theme-mode',
                mode = storage.getItem(key)

            if (mode === 'dark') {
                elements.iframe_tab_container.addClass('sidebar-dark-white')
            }
            $(document).on('dark-mode.shown', function () {
                elements.iframe_tab_container.addClass('sidebar-dark-white')
            });

            $(document).on('dark-mode.hide', function () {
                elements.iframe_tab_container.removeClass('sidebar-dark-white')
            });
        },
        /*生成ID*/
        generateID(href) {
            return md5(href + this.TAB_STORAGE_KEY).substr(8, 16)
        },
    }
    /*挂载*/
    window.iframeTabParent = {swiper, elements, iframeTabTemplate, iframeTab}
    $(window).on('beforeunload', function () {
        iframeTab.syncAllTabStateFromIframes()
        iframeTab.syncPageOpenStateFromActiveTab()
    })
    $(document).on('click', '.iframe-tab-pane-loading__retry', function () {
        let pane = $(this).closest('.tab-pane')
        let iframe = pane.find('iframe')
        let src = iframe.attr('src')
        if (!src) {
            return
        }
        iframeTab.showPaneLoading(pane)
        iframe.attr('src', '')
        iframe.attr('src', src)
    })
    iframeTab.init()
})
