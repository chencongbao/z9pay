@once('admin-left-filter-assets')
    <script>
        (function () {
            var storageKey = 'admin_left_filter_collapsed';
            var collapsedValue = '1';

            try {
                if (localStorage.getItem(storageKey) !== collapsedValue) {
                    return;
                }
            } catch (e) {
                return;
            }

            document.documentElement.className += ' admin-left-filter-pre-collapsed';

            function applyPreCollapsedText() {
                var sides = document.querySelectorAll('.admin-left-filter-side');

                for (var i = 0; i < sides.length; i++) {
                    var toggle = sides[i].querySelector('.admin-left-filter-toggle');
                    var span = sides[i].querySelector('.admin-left-filter-toggle span');
                    var icon = sides[i].querySelector('.admin-left-filter-toggle i');

                    if (span) {
                        span.textContent = toggle ? toggle.getAttribute('data-expand-title') || '' : '';
                    }

                    if (icon) {
                        icon.className = icon.className.replace('icon-chevrons-left', 'icon-chevrons-right');
                    }
                }
            }

            applyPreCollapsedText();
            document.addEventListener('DOMContentLoaded', applyPreCollapsedText);
        })();
    </script>
    <style>
        .admin-left-filter-toggle {
            margin: 0 15px 12px 15px;
            width: calc(100% - 30px);
            border-radius: 4px;
        }
        @media (max-width: 991.98px) {
            .admin-left-filter-col-collapsed .admin-left-filter-toggle,
            .admin-left-filter-side-collapsed .admin-left-filter-toggle,
            .admin-left-filter-pre-collapsed .admin-left-filter-toggle {
                left: 0;
            }
        }
        .admin-left-filter-col-collapsed {
            position: absolute;
            left: 0;
            top: 0;
            z-index: 20;
            width: 0 !important;
            max-width: 0 !important;
            padding-left: 0;
            padding-right: 0;
        }
        .admin-left-filter-col-collapsed .card,
        .admin-left-filter-col-collapsed .box,
        .admin-left-filter-col-collapsed .card-body,
        .admin-left-filter-col-collapsed .box-body {
            width: 0;
            overflow: visible;
        }
        .admin-left-filter-col-collapsed .card-header,
        .admin-left-filter-col-collapsed .box-header {
            display: none;
        }
        .admin-left-filter-col-collapsed .card-body,
        .admin-left-filter-col-collapsed .box-body {
            padding: 8px !important;
        }
        .admin-left-filter-col-collapsed .admin-left-filter-toggle {
            position: fixed;
            left: var(--admin-left-filter-fixed-left, 0);
            top: 132px;
            margin: 0;
            width: 32px;
            min-height: 148px;
            padding: 14px 0 12px 0;
            border: 0;
            border-radius: 0 10px 10px 0;
            background: linear-gradient(180deg, #4f65a8 0%, #32477f 100%);
            color: #fff;
            box-shadow: 0 8px 18px rgba(36, 50, 104, .2);
            z-index: 1040;
            line-height: 1;
            white-space: normal;
            transition: box-shadow .18s ease, background .18s ease;
        }
        .admin-left-filter-side-collapsed {
            width: 0;
            overflow: visible;
        }
        .admin-left-filter-side-collapsed .admin-left-filter-body {
            display: none;
        }
        .admin-left-filter-side-collapsed .admin-left-filter-toggle {
            position: fixed;
            left: var(--admin-left-filter-fixed-left, 0);
            top: 132px;
            margin: 0;
            width: 32px !important;
            max-width: 32px !important;
            min-height: 148px;
            padding: 14px 0 12px 0;
            border: 0;
            border-radius: 0 10px 10px 0;
            background: linear-gradient(180deg, #4f65a8 0%, #32477f 100%);
            color: #fff;
            box-shadow: 0 8px 18px rgba(36, 50, 104, .2);
            z-index: 1040;
            line-height: 1;
            overflow: hidden;
            white-space: normal;
        }
        .admin-left-filter-side-collapsed .admin-left-filter-toggle i {
            display: block;
            margin: 0 auto 8px auto;
            font-size: 13px;
        }
        .admin-left-filter-side-collapsed .admin-left-filter-toggle span {
            display: block;
            width: 14px;
            margin: 0 auto;
            font-size: 0;
            font-weight: 600;
            letter-spacing: 0;
            line-height: 1.15;
        }
        .admin-left-filter-side-collapsed .admin-left-filter-toggle span:after {
            content: attr(data-expand-title);
            font-size: 12px;
        }
        .admin-left-filter-col-collapsed .admin-left-filter-toggle:hover,
        .admin-left-filter-col-collapsed .admin-left-filter-toggle:focus {
            width: 32px;
            background: linear-gradient(180deg, #5b72b7 0%, #3a508c 100%);
            color: #fff;
            box-shadow: 0 10px 22px rgba(36, 50, 104, .28);
        }
        .admin-left-filter-col-collapsed .admin-left-filter-body {
            display: none;
        }
        .admin-left-filter-col-collapsed .admin-left-filter-toggle i {
            display: block;
            margin: 0 auto 8px auto;
            font-size: 13px;
        }
        .admin-left-filter-col-collapsed .admin-left-filter-toggle span {
            display: block;
            width: 14px;
            margin: 0 auto;
            font-size: 0;
            font-weight: 600;
            letter-spacing: 0;
            line-height: 1.15;
        }
        .admin-left-filter-col-collapsed .admin-left-filter-toggle span:after {
            content: attr(data-expand-title);
            font-size: 12px;
        }
        .admin-left-filter-pre-collapsed .admin-left-filter-body {
            display: none;
        }
        .admin-left-filter-pre-collapsed .admin-left-filter-side {
            width: 0;
            overflow: visible;
        }
        .admin-left-filter-pre-collapsed .admin-left-filter-toggle {
            position: fixed;
            left: var(--admin-left-filter-fixed-left, 0);
            top: 132px;
            margin: 0;
            width: 32px;
            min-height: 148px;
            padding: 14px 0 12px 0;
            border: 0;
            border-radius: 0 10px 10px 0;
            background: linear-gradient(180deg, #4f65a8 0%, #32477f 100%);
            color: #fff;
            box-shadow: 0 8px 18px rgba(36, 50, 104, .2);
            z-index: 1040;
            line-height: 1;
            white-space: normal;
        }
        .admin-left-filter-pre-collapsed .admin-left-filter-toggle i {
            display: block;
            margin: 0 auto 8px auto;
            font-size: 13px;
        }
        .admin-left-filter-pre-collapsed .admin-left-filter-toggle span {
            display: block;
            width: 14px;
            margin: 0 auto;
            font-size: 0;
            font-weight: 600;
            letter-spacing: 0;
            line-height: 1.15;
        }
        .admin-left-filter-pre-collapsed .admin-left-filter-toggle span:after {
            content: attr(data-expand-title);
            font-size: 12px;
        }
    </style>
    <script>
        Dcat.ready(function () {
            var storageKey = 'admin_left_filter_collapsed';
            var collapsedValue = '1';
            var colClassPattern = /\bcol(?:-(?:sm|md|lg|xl))?-\d+\b/g;

            function updateFixedLeft() {
                var left = 0;

                if (!window.matchMedia || !window.matchMedia('(max-width: 991.98px)').matches) {
                    var sidebar = document.querySelector('.main-sidebar');

                    if (sidebar) {
                        var style = window.getComputedStyle(sidebar);
                        var rect = sidebar.getBoundingClientRect();
                        var visible = style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.right > 0;

                        if (visible) {
                            left = Math.max(0, Math.round(rect.right));
                        }
                    }
                }

                document.documentElement.style.setProperty('--admin-left-filter-fixed-left', left + 'px');
            }

            function compactColumnClass(className, size) {
                return $.trim((className || '').replace(colClassPattern, '') + ' col-sm-' + size + ' col-md-' + size);
            }

            function findLayoutColumns($side) {
                var $sideColumn = $();
                var $contentColumn = $();

                $side.parents('[class*="col-"]').each(function () {
                    var $column = $(this);
                    var $nextColumn = $column.next('[class*="col-"]');

                    if ($nextColumn.length) {
                        $sideColumn = $column;
                        $contentColumn = $nextColumn;
                        return false;
                    }
                });

                return {
                    side: $sideColumn,
                    content: $contentColumn
                };
            }

            function applyLeftFilterState($side, collapsed) {
                var columns = findLayoutColumns($side);
                var $sideColumn = columns.side;
                var $contentColumn = columns.content;

                if (!$sideColumn.length || !$contentColumn.length) {
                    return;
                }

                if (!$sideColumn.data('left-filter-original-class')) {
                    $sideColumn.data('left-filter-original-class', $sideColumn.attr('class') || '');
                    $contentColumn.data('left-filter-original-class', $contentColumn.attr('class') || '');
                }

                if (collapsed) {
                    var $toggle = $side.find('.admin-left-filter-toggle');
                    $side.addClass('admin-left-filter-side-collapsed');
                    $toggle.find('span').text($toggle.attr('data-expand-title') || '');
                    $toggle.attr('title', $toggle.attr('data-expand-tooltip') || '');
                    $toggle.find('i').removeClass('icon-chevrons-left').addClass('icon-chevrons-right');
                    $sideColumn.attr('class', compactColumnClass($sideColumn.data('left-filter-original-class'), 1) + ' admin-left-filter-col-collapsed');
                    $contentColumn.attr('class', compactColumnClass($contentColumn.data('left-filter-original-class'), 12));
                } else {
                    var $toggle = $side.find('.admin-left-filter-toggle');
                    document.documentElement.className = document.documentElement.className.replace(/\s?admin-left-filter-pre-collapsed/g, '');
                    $side.removeClass('admin-left-filter-side-collapsed');
                    $sideColumn.attr('class', $sideColumn.data('left-filter-original-class'));
                    $contentColumn.attr('class', $contentColumn.data('left-filter-original-class'));
                    $toggle.find('span').text($toggle.attr('data-collapse-title') || '');
                    $toggle.attr('title', $toggle.attr('data-collapse-tooltip') || '');
                    $toggle.find('i').removeClass('icon-chevrons-right').addClass('icon-chevrons-left');
                }
            }

            $('.admin-left-filter-side').each(function () {
                var $side = $(this);
                updateFixedLeft();
                applyLeftFilterState($side, localStorage.getItem(storageKey) === collapsedValue);
                updateFixedLeft();

                $side.find('.admin-left-filter-toggle').off('click.adminLeftFilter').on('click.adminLeftFilter', function () {
                    var collapsed = localStorage.getItem(storageKey) !== collapsedValue;
                    localStorage.setItem(storageKey, collapsed ? collapsedValue : '0');
                    $('.admin-left-filter-side').each(function () {
                        applyLeftFilterState($(this), collapsed);
                    });
                    updateFixedLeft();
                });
            });

            $(window).off('resize.adminLeftFilter').on('resize.adminLeftFilter', updateFixedLeft);
            $(document).off('click.adminLeftFilterSidebar').on('click.adminLeftFilterSidebar', '.main-menu-toggle,.nav-toggle,[data-widget="pushmenu"]', function () {
                setTimeout(updateFixedLeft, 250);
            });
        });
    </script>
@endonce
