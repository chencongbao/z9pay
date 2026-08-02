(function () {
    if (window.__adminModalSelect2Initialized) {
        return;
    }

    window.__adminModalSelect2Initialized = true;

    function patchSelect2AttachBody() {
        if (! $.fn.select2 || ! $.fn.select2.amd || window.__adminModalSelect2AttachBodyPatched) {
            return;
        }

        $.fn.select2.amd.require(['select2/dropdown/attachBody', 'select2/utils'], function (AttachBody, Utils) {
            if (window.__adminModalSelect2AttachBodyPatched) {
                return;
            }

            window.__adminModalSelect2AttachBodyPatched = true;

            var originalPositionDropdown = AttachBody.prototype._positionDropdown;
            var originalAttachPositioningHandler = AttachBody.prototype._attachPositioningHandler;
            var originalDetachPositioningHandler = AttachBody.prototype._detachPositioningHandler;

            AttachBody.prototype._positionDropdown = function () {
                originalPositionDropdown.call(this);

                if (! this.$dropdownParent || ! this.$dropdownParent.length || this.$dropdownParent.is('body, html')) {
                    return;
                }

                var top = parseFloat(this.$dropdownContainer.css('top'));
                var left = parseFloat(this.$dropdownContainer.css('left'));

                if (! isNaN(top)) {
                    this.$dropdownContainer.css('top', top + this.$dropdownParent.scrollTop());
                }

                if (! isNaN(left)) {
                    this.$dropdownContainer.css('left', left + this.$dropdownParent.scrollLeft());
                }
            };

            AttachBody.prototype._attachPositioningHandler = function (container) {
                if (! this.$dropdownParent || ! this.$dropdownParent.length || this.$dropdownParent.is('body, html')) {
                    originalAttachPositioningHandler.call(this, container);
                    return;
                }

                var self = this;
                var scrollEvent = 'scroll.select2.' + container.id;
                var resizeEvent = 'resize.select2.' + container.id;
                var orientationEvent = 'orientationchange.select2.' + container.id;
                var $watchers = this.$container.parents().filter(Utils.hasScroll);

                $watchers.on(scrollEvent, function () {
                    self._positionDropdown();
                    self._resizeDropdown();
                });

                $(window).on(scrollEvent + ' ' + resizeEvent + ' ' + orientationEvent, function () {
                    self._positionDropdown();
                    self._resizeDropdown();
                });
            };

            AttachBody.prototype._detachPositioningHandler = function (container) {
                if (! this.$dropdownParent || ! this.$dropdownParent.length || this.$dropdownParent.is('body, html')) {
                    originalDetachPositioningHandler.call(this, container);
                    return;
                }

                var scrollEvent = 'scroll.select2.' + container.id;
                var resizeEvent = 'resize.select2.' + container.id;
                var orientationEvent = 'orientationchange.select2.' + container.id;
                var $watchers = this.$container.parents().filter(Utils.hasScroll);

                $watchers.off(scrollEvent);
                $(window).off(scrollEvent + ' ' + resizeEvent + ' ' + orientationEvent);
            };
        });
    }

    patchSelect2AttachBody();

    function ensureDropdownParentPosition($parent) {
        if (! $parent || ! $parent.length) {
            return;
        }

        if ($parent.css('position') === 'static') {
            $parent.css('position', 'relative');
        }
    }

    function isScrollableElement($element) {
        if (! $element || ! $element.length) {
            return false;
        }

        var overflowY = $element.css('overflowY');
        var overflowX = $element.css('overflowX');
        var node = $element.get(0);
        var canScroll = /(auto|scroll|overlay)/.test((overflowY || '') + ' ' + (overflowX || ''));

        if (! node) {
            return false;
        }

        return canScroll || node.scrollHeight > node.clientHeight || node.scrollWidth > node.clientWidth;
    }

    function getScrollableDropdownParent($select, $popup) {
        var $current = $select.parent();

        while ($current.length) {
            if ($popup.length && $current.get(0) === $popup.get(0)) {
                if (isScrollableElement($current)) {
                    return $current;
                }

                break;
            }

            if (isScrollableElement($current)) {
                return $current;
            }

            $current = $current.parent();
        }

        return $();
    }

    function getPopupRoot($select) {
        var $modal = $select.closest('.modal.show');

        if ($modal.length) {
            return $modal;
        }

        return $select.closest('.layui-layer');
    }

    function getDropdownParent($select) {
        var $popup = getPopupRoot($select);
        var $scrollableParent;

        if (! $popup.length) {
            return $();
        }

        $scrollableParent = getScrollableDropdownParent($select, $popup);

        if ($scrollableParent.length) {
            return $scrollableParent;
        }

        if ($popup.hasClass('modal')) {
            if ($popup.find('.modal-content').first().length) {
                return $popup.find('.modal-content').first();
            }

            return $popup;
        }

        return $popup.find('.layui-layer-content').first().length
            ? $popup.find('.layui-layer-content').first()
            : $popup;
    }

    function isSameParent(currentParent, $expectedParent) {
        if (! currentParent || ! $expectedParent || ! $expectedParent.length) {
            return false;
        }

        return $(currentParent).get(0) === $expectedParent.get(0);
    }

    function rebuildSelect2($select) {
        var $dropdownParent = getDropdownParent($select);

        if (! $dropdownParent.length) {
            return false;
        }

        ensureDropdownParentPosition($dropdownParent);

        var instance = $select.data('select2');
        var options = instance && instance.options
            ? $.extend(true, {}, instance.options.options || {})
            : {};
        var value = $select.val();

        options.dropdownParent = $dropdownParent;
        options.width = options.width || '100%';

        if ($select.hasClass('select2-hidden-accessible')) {
            try {
                $select.select2('destroy');
            } catch (e) {}
        }

        $select.select2(options);

        if (value !== undefined) {
            $select.val(value).trigger('change.select2');
        }

        return true;
    }

    function repositionOpenSelect2($select) {
        var instance = $select.data('select2');

        if (! instance || ! instance.isOpen || ! instance.isOpen()) {
            return;
        }

        if (instance.dropdown && instance.dropdown._positionDropdown) {
            instance.dropdown._positionDropdown();
        }

        if (instance.dropdown && instance.dropdown._resizeDropdown) {
            instance.dropdown._resizeDropdown();
        }
    }

    function bindPopupScrollSync($select) {
        var $popup = getPopupRoot($select);

        if (! $popup.length) {
            return;
        }

        var namespace = '.admin-modal-select2-position';

        $popup.off(namespace);
        $popup.on('scroll' + namespace, function () {
            repositionOpenSelect2($select);
        });

        var $scrollParent = getDropdownParent($select);
        if ($scrollParent.length && $scrollParent.get(0) !== $popup.get(0)) {
            $scrollParent.off(namespace);
            $scrollParent.on('scroll' + namespace, function () {
                repositionOpenSelect2($select);
            });
        }
    }

    function unbindPopupScrollSync($select) {
        var $popup = getPopupRoot($select);
        var $scrollParent = getDropdownParent($select);
        var namespace = '.admin-modal-select2-position';

        if ($popup.length) {
            $popup.off(namespace);
        }

        if ($scrollParent.length) {
            $scrollParent.off(namespace);
        }
    }

    function ensureModalSelect2($select, reopen) {
        if (! $select || ! $select.length) {
            return;
        }

        var $dropdownParent = getDropdownParent($select);

        if (! $dropdownParent.length) {
            return;
        }

        var instance = $select.data('select2');
        var currentParent = instance && instance.options ? instance.options.options.dropdownParent : null;

        if (! instance || ! isSameParent(currentParent, $dropdownParent)) {
            if (rebuildSelect2($select) && reopen) {
                setTimeout(function () {
                    $select.select2('open');
                }, 0);
            }
        }
    }

    function refreshPopupSelect2($popup) {
        if (! $popup || ! $popup.length) {
            return;
        }

        setTimeout(function () {
            $popup.find('select.select2-hidden-accessible, select[data-select2-id], select.form-control').each(function () {
                ensureModalSelect2($(this), false);
            });
        }, 50);
    }

    $(document)
        .off('shown.bs.modal.admin-modal-select2', '.modal')
        .on('shown.bs.modal.admin-modal-select2', '.modal', function () {
            refreshPopupSelect2($(this));
        })
        .off('dialog:shown.admin-modal-select2', '.layui-layer')
        .on('dialog:shown.admin-modal-select2', '.layui-layer', function () {
            refreshPopupSelect2($(this));
        })
        .off('select2:opening.admin-modal-select2')
        .on('select2:opening.admin-modal-select2', 'select', function (e) {
            var $select = $(this);
            var $dropdownParent = getDropdownParent($select);
            var instance = $select.data('select2');
            var currentParent = instance && instance.options ? instance.options.options.dropdownParent : null;

            if (! $dropdownParent.length || isSameParent(currentParent, $dropdownParent) || $select.data('modal-select2-opening')) {
                return;
            }

            e.preventDefault();

            $select.data('modal-select2-opening', true);
            ensureModalSelect2($select, true);

            setTimeout(function () {
                $select.removeData('modal-select2-opening');
            }, 50);
        })
        .off('select2:open.admin-modal-select2')
        .on('select2:open.admin-modal-select2', 'select', function () {
            var $select = $(this);

            bindPopupScrollSync($select);
            repositionOpenSelect2($select);
        })
        .off('select2:close.admin-modal-select2')
        .on('select2:close.admin-modal-select2', 'select', function () {
            unbindPopupScrollSync($(this));
        });
})();
