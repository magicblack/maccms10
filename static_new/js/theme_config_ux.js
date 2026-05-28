/**
 * 模板主题配置：分类弹窗 + 指针拖拽排序
 */
(function (window) {
    'use strict';

    var $ = window.jQuery;
    if (!$) {
        return;
    }

    function getI18n() {
        return window.MAC_THEME_UX_I18N || {};
    }

    function t(key, fallback) {
        var i18n = getI18n();
        var val = i18n[key];
        return val != null && val !== '' ? val : fallback;
    }

    function sprintf(fmt) {
        var args = Array.prototype.slice.call(arguments, 1);
        var i = 0;
        return String(fmt).replace(/%[sd]/g, function () {
            return i < args.length ? args[i++] : '';
        });
    }

    function getMidLabels() {
        return getI18n().midLabels || { '1': '视频', '2': '文章', '12': '漫画' };
    }

    function getMidTabs() {
        return getI18n().midTabs || [
            { id: 'all', title: '全部' },
            { id: '1', title: '视频' },
            { id: '2', title: '文章' },
            { id: '12', title: '漫画' }
        ];
    }

    var dragState = null;
    var dropLineEl = null;

    function getOptions() {
        return window.MAC_THEME_TYPE_OPTIONS || { '1': [], '2': [], '12': [], all: [] };
    }

    function getIndex() {
        return window.MAC_THEME_TYPE_INDEX || {};
    }

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/"/g, '&quot;');
    }

    function resolveMid(value) {
        var idx = getIndex();
        if (value && idx[value]) {
            var mid = idx[value].mid;
            if (mid === 1 || mid === 2 || mid === 12) {
                return String(mid);
            }
        }
        return 'all';
    }

    function optionsForMid(mid) {
        var opts = getOptions();
        if (mid === 'all') {
            return opts.all || [];
        }
        return opts[mid] || [];
    }

    function parseIdList(raw) {
        if (!raw || raw === '0') {
            return [];
        }
        return String(raw).split(/[,，\s]+/).map(function (s) {
            return $.trim(s);
        }).filter(function (s) {
            return s && s !== '0';
        });
    }

    function isMultiPicker($field) {
        if ($field.attr('data-picker-mode') === 'multiple') {
            return true;
        }
        var name = $field.find('.type-picker-value').attr('name') || '';
        return name.indexOf('theme[nav][id]') > -1
            || name.indexOf('theme[rank][hid]') > -1
            || name.indexOf('theme[rank][id]') > -1;
    }

    function defaultMultiPickerLabel($field) {
        var name = $field.find('.type-picker-value').attr('name') || '';
        if (name.indexOf('theme[nav][id]') > -1) {
            return t('pickNavMulti', '选择导航分类（可多选）');
        }
        return t('pickMulti', '选择分类（可多选）');
    }

    function labelForValue(value) {
        if (!value || value === '0') {
            return '';
        }
        var idx = getIndex();
        var midLabels = getMidLabels();
        if (idx[value]) {
            var name = idx[value].name || '';
            var mid = String(idx[value].mid || '');
            var tag = midLabels[mid] ? midLabels[mid] + ' · ' : '';
            return tag + name.replace(/^—\s*/, '');
        }
        return sprintf(t('idInvalid', 'ID %s（已失效）'), value);
    }

    function labelForIdList(ids) {
        if (!ids.length) {
            return '';
        }
        if (ids.length === 1) {
            return labelForValue(ids[0]);
        }
        return sprintf(t('selectedCount', '已选 %d 个分类'), ids.length);
    }

    function chipLabelForId(id) {
        var full = labelForValue(id);
        if (!full) {
            return 'ID ' + id;
        }
        return full.replace(/^[^·]+·\s*/, '');
    }

    function renderSelectedChips($popup, ids) {
        var $bar = $popup.find('.type-picker-selected-bar');
        if (!$bar.length) {
            return;
        }
        var $chips = $bar.find('.type-picker-selected-chips');
        var $count = $bar.find('.type-picker-selected-count');
        var n = ids.length;
        $count.text(n ? '（' + n + '）' : '');
        if (!n) {
            $bar.addClass('is-empty');
            $chips.html('<span class="type-picker-selected-empty">' + escapeHtml(t('noneSelectedHint', '暂未选择 · 留空将显示全部分类')) + '</span>');
            return;
        }
        $bar.removeClass('is-empty');
        var html = '';
        var i;
        var removeLabel = t('removeType', '移除该分类');
        for (i = 0; i < ids.length; i++) {
            html += '<span class="type-picker-chip" data-id="' + escapeHtml(ids[i]) + '">'
                + '<span class="type-picker-chip-text">' + escapeHtml(chipLabelForId(ids[i])) + '</span>'
                + '<button type="button" class="type-picker-chip-remove" aria-label="' + escapeHtml(removeLabel) + '">×</button>'
                + '</span>';
        }
        $chips.html(html);
    }

    function setPickerButtonText($field, text) {
        var $btn = $field.find('.js-type-picker-btn');
        var isMulti = isMultiPicker($field);
        var display = text || (isMulti ? defaultMultiPickerLabel($field) : t('pickType', '选择分类'));
        $field.find('.type-picker-text').text(display);
        $btn.toggleClass('is-selected', !!text);
    }

    function refreshPickerLabel($field) {
        var val = $field.find('.type-picker-value').val() || '';
        if (isMultiPicker($field)) {
            setPickerButtonText($field, labelForIdList(parseIdList(val)));
        } else {
            setPickerButtonText($field, labelForValue(val));
        }
    }

    function initTypePickerLabels($scope) {
        ($scope || $(document)).find('.type-picker-field').each(function () {
            refreshPickerLabel($(this));
        });
    }

    function idListIncludes(ids, id) {
        return ids.some(function (x) {
            return String(x) === String(id);
        });
    }

    function itemDepth(item) {
        if (item && typeof item.depth === 'number') {
            return item.depth;
        }
        var n = (item && item.name) ? item.name : '';
        var depth = 0;
        while (/^—\s/.test(n)) {
            n = n.replace(/^—\s/, '');
            depth++;
        }
        return depth;
    }

    function itemLabel(item) {
        if (item && item.label) {
            return item.label;
        }
        var n = (item && item.name) ? item.name : '';
        return n.replace(/^(—\s*)+/, '').replace(/^\[[^\]]+\]\s*/, '');
    }

    function filterListItems($list, keyword) {
        var q = (keyword || '').toLowerCase().trim();
        var visible = 0;
        $list.find('li[data-id]').not('.type-picker-clear').each(function () {
            var $li = $(this);
            if ($li.hasClass('empty')) {
                return;
            }
            var text = ($li.attr('data-search') || $li.text()).toLowerCase();
            var show = !q || text.indexOf(q) !== -1;
            $li.toggleClass('is-hidden', !show);
            if (show) {
                visible++;
            }
        });
        $list.find('li.empty').toggle(visible === 0 && !q);
    }

    function renderPopupOptions($body, mid, selected, keyword, isMulti) {
        var list = optionsForMid(mid);
        var selectedIds = isMulti ? (selected || []) : [];
        var selectedId = isMulti ? '' : (selected || '');
        var html = '<ul class="type-picker-popup-list type-picker-popup-list--tree type-picker-popup-list--checkbox'
            + (isMulti ? ' type-picker-popup-list--multi' : ' type-picker-popup-list--single') + '">';
        var i, item, cls, check, depth, label, treeCls, branch;
        check = '<span class="type-picker-check" aria-hidden="true"></span>';
        for (i = 0; i < list.length; i++) {
            item = list[i];
            depth = itemDepth(item);
            label = itemLabel(item);
            treeCls = ' type-picker-tree-item type-picker-tree-depth-' + depth;
            if (depth > 0) {
                treeCls += ' is-child';
            } else {
                treeCls += ' is-parent';
            }
            cls = isMulti
                ? (idListIncludes(selectedIds, item.id) ? ' active' : '')
                : ((String(item.id) === String(selectedId)) ? ' active' : '');
            branch = depth > 0 ? '<span class="type-picker-tree-branch" aria-hidden="true"></span>' : '';
            html += '<li data-id="' + escapeHtml(item.id) + '" data-depth="' + depth + '" data-search="'
                + escapeHtml(label + ' ' + item.name) + '" class="' + treeCls.trim() + cls + '">'
                + branch + check + '<span class="type-picker-item-label">' + escapeHtml(label) + '</span></li>';
        }
        if (!isMulti && selectedId && selectedId !== '0' && !list.some(function (x) { return String(x.id) === String(selectedId); })) {
            html += '<li data-id="' + escapeHtml(selectedId) + '" class="active type-picker-tree-item">'
                + check + '<span class="type-picker-item-label">' + escapeHtml(sprintf(t('idInvalid', 'ID %s（已失效）'), selectedId)) + '</span></li>';
        }
        if (isMulti) {
            html += '<li data-id="0" class="type-picker-clear">' + escapeHtml(t('clearAll', '留空 = 显示全部分类')) + '</li>';
        } else {
            html += '<li data-id="0" class="type-picker-clear">' + escapeHtml(t('unbind', '不绑定分类（自定义链接）')) + '</li>';
        }
        if (!list.length) {
            html += '<li class="empty">' + escapeHtml(isMulti ? t('emptyMulti', '当前分类下暂无条目') : t('emptySingle', '暂无分类数据')) + '</li>';
        }
        html += '</ul>';
        var $wrap = $body.find('.type-picker-popup-list-wrap');
        $wrap.html(html);
        if (keyword) {
            filterListItems($wrap.find('.type-picker-popup-list'), keyword);
        }
    }

    function openTypePickerPopup($field, layer) {
        if (!layer) {
            return;
        }
        var $hidden = $field.find('.type-picker-value');
        var isMulti = isMultiPicker($field);
        var currentRaw = $hidden.val() || '';
        var currentIds = isMulti ? parseIdList(currentRaw) : [];
        var currentId = isMulti ? '' : currentRaw;
        var activeMid = 'all';
        if (!isMulti && currentId && currentId !== '0') {
            activeMid = resolveMid(currentId);
        }

        var tabsHtml = '';
        getMidTabs().forEach(function (tab) {
            var cls = String(tab.id) === String(activeMid) ? ' active' : '';
            tabsHtml += '<button type="button" class="type-picker-popup-tab' + cls + '" data-mid="' + tab.id + '">' + escapeHtml(tab.title) + '</button>';
        });

        var footerHtml = isMulti
            ? '<div class="type-picker-popup-actions">'
                + '<button type="button" class="type-picker-btn-confirm layui-btn layui-btn-sm layui-btn-normal">' + escapeHtml(t('confirm', '确定')) + '<span class="type-picker-count"></span></button>'
                + '<button type="button" class="type-picker-btn-reset layui-btn layui-btn-sm layui-btn-primary">' + escapeHtml(t('reset', '清空')) + '</button>'
                + '</div>'
                + '<div class="type-picker-popup-meta">' + escapeHtml(t('multiMeta', '可多选 · 留空显示全部 · Esc 关闭')) + '</div>'
            : '<div class="type-picker-popup-meta">' + escapeHtml(t('singleMeta', '点击条目即可选中 · Esc 关闭')) + '</div>';

        var content = ''
            + '<div class="type-picker-popup' + (isMulti ? ' type-picker-popup--multi' : '') + '" data-mid="' + activeMid + '">'
            + '<button type="button" class="type-picker-popup-close js-type-picker-close" aria-label="' + escapeHtml(t('close', '关闭')) + '" title="' + escapeHtml(t('closeEsc', '关闭 (Esc)')) + '">×</button>'
            + '<div class="type-picker-popup-search"><input type="search" placeholder="' + escapeHtml(t('searchPh', '搜索分类名称…')) + '" autocomplete="off"></div>'
            + '<div class="type-picker-popup-tabs">' + tabsHtml + '</div>'
            + (isMulti
                ? '<div class="type-picker-selected-bar is-empty">'
                    + '<div class="type-picker-selected-head">' + escapeHtml(t('selectedHead', '已选分类')) + '<span class="type-picker-selected-count"></span></div>'
                    + '<div class="type-picker-selected-chips"></div>'
                    + '</div>'
                : '')
            + '<div class="type-picker-popup-list-wrap"></div>'
            + footerHtml
            + '</div>';

        var viewportH = window.innerHeight || document.documentElement.clientHeight || 600;
        var layerW = Math.min(480, Math.max(320, (window.innerWidth || 800) - 48));
        var layerH = Math.min(Math.round(viewportH * 0.78), 640);
        var chromeH = isMulti ? 268 : 168;
        var listMaxH = Math.max(220, layerH - chromeH);
        var selectedSet = isMulti ? currentIds.slice() : null;

        function updateMultiCount($popup) {
            if (!isMulti) {
                return;
            }
            var n = selectedSet.length;
            $popup.find('.type-picker-count').text(n ? '（' + n + '）' : '');
            renderSelectedChips($popup, selectedSet);
        }

        function applyMultiSelection($popup) {
            var val = selectedSet.join(',');
            $hidden.val(val);
            setPickerButtonText($field, labelForIdList(selectedSet));
            updateMultiCount($popup);
        }

        function closePickerLayer() {
            layer.close(layerIdx);
        }

        var layerIdx = layer.open({
            type: 1,
            title: false,
            closeBtn: 0,
            shadeClose: !isMulti,
            skin: 'type-picker-layer',
            area: [layerW + 'px', layerH + 'px'],
            content: content,
            success: function (layero) {
                var $popup = layero.find('.type-picker-popup');
                var $search = $popup.find('.type-picker-popup-search input');

                $popup.on('click', '.js-type-picker-close', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    closePickerLayer();
                });

                $(document).on('keydown.themeuxPickerLayer', function (e) {
                    if (e.key === 'Escape' || e.keyCode === 27) {
                        e.preventDefault();
                        closePickerLayer();
                    }
                });

                function paintList() {
                    var sel = isMulti ? selectedSet : ($hidden.val() || '');
                    renderPopupOptions($popup, $popup.attr('data-mid'), sel, $search.val(), isMulti);
                    var $list = $popup.find('.type-picker-popup-list');
                    $list.css('max-height', listMaxH + 'px');
                    updateMultiCount($popup);
                }

                paintList();
                setTimeout(function () { $search.trigger('focus'); }, 80);

                $search.on('input', paintList);

                $popup.on('click', '.type-picker-popup-tab', function () {
                    var mid = $(this).attr('data-mid');
                    $popup.attr('data-mid', mid);
                    $popup.find('.type-picker-popup-tab').removeClass('active');
                    $(this).addClass('active');
                    paintList();
                });

                if (isMulti) {
                    $popup.on('click', '.type-picker-chip-remove', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        var id = $(this).closest('.type-picker-chip').attr('data-id');
                        selectedSet = selectedSet.filter(function (x) {
                            return String(x) !== String(id);
                        });
                        paintList();
                    });

                    $popup.on('click', '.type-picker-popup-list li[data-id]', function () {
                        var id = $(this).attr('data-id');
                        if (id === '0') {
                            selectedSet = [];
                        } else if (idListIncludes(selectedSet, id)) {
                            selectedSet = selectedSet.filter(function (x) {
                                return String(x) !== String(id);
                            });
                        } else {
                            selectedSet.push(id);
                        }
                        paintList();
                    });

                    $popup.on('click', '.type-picker-btn-reset', function () {
                        selectedSet = [];
                        paintList();
                    });

                    $popup.on('click', '.type-picker-btn-confirm', function () {
                        applyMultiSelection($popup);
                        closePickerLayer();
                    });
                } else {
                    $popup.on('click', '.type-picker-popup-list li[data-id]', function () {
                        var id = $(this).attr('data-id');
                        var name = $(this).find('.type-picker-item-label').text() || $(this).text();
                        $hidden.val(id);
                        setPickerButtonText($field, labelForValue(id) || (id === '0' ? '' : name));
                        var $row = $field.closest(
                            '.layui-form-item, .bnav-item, .home-module-item, .hotvod-tab-item, .allmenu-item, .vod-list-cover-item'
                        );
                        var $nameField = $row.find('.js-type-name-field').first();
                        if ($nameField.length && $.trim($nameField.val()) === '' && id && id !== '0') {
                            var idx = getIndex();
                            if (idx[id]) {
                                $nameField.val((idx[id].name || '').replace(/^—\s*/, ''));
                            } else if (name.indexOf('ID') !== 0) {
                                $nameField.val(name.replace(/^[^·]+·\s*/, '').replace(/^\[[^\]]+\]\s*/, ''));
                            }
                        }
                        closePickerLayer();
                    });
                }
            },
            end: function () {
                $(document).off('keydown.themeuxPickerLayer');
            }
        });
    }

    function bindTypePickerEvents(layer) {
        $(document).off('click.themeuxPicker', '.js-type-picker-btn');
        $(document).on('click.themeuxPicker', '.js-type-picker-btn', function (e) {
            e.preventDefault();
            e.stopPropagation();
            openTypePickerPopup($(this).closest('.type-picker-field'), layer);
        });
    }

    function getDropLine() {
        if (!dropLineEl) {
            dropLineEl = document.createElement('div');
            dropLineEl.className = 'theme-sort-drop-line';
        }
        return dropLineEl;
    }

    function hideDropLine() {
        var line = getDropLine();
        line.classList.remove('is-visible');
        if (line.parentNode) {
            line.parentNode.removeChild(line);
        }
    }

    function showDropLine($list, clientY) {
        var $items = $list.children('.js-sortable-item');
        if (!$items.length) {
            return;
        }
        var line = getDropLine();
        var placed = false;
        $items.each(function () {
            var rect = this.getBoundingClientRect();
            var mid = rect.top + rect.height / 2;
            if (clientY < mid) {
                $list[0].insertBefore(line, this);
                line.classList.add('is-visible');
                placed = true;
                return false;
            }
        });
        if (!placed) {
            $list[0].appendChild(line);
            line.classList.add('is-visible');
        }
    }

    function findSortableItemAt($list, clientY) {
        var $target = null;
        $list.children('.js-sortable-item').each(function () {
            var rect = this.getBoundingClientRect();
            if (clientY >= rect.top && clientY <= rect.bottom) {
                $target = $(this);
            }
        });
        return $target;
    }

    function captureItemTops($list) {
        var tops = new Map();
        $list.children('.js-sortable-item').each(function () {
            tops.set(this, this.getBoundingClientRect().top);
        });
        return tops;
    }

    function playFlipAnimation($list, beforeTops) {
        if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }
        $list.children('.js-sortable-item').each(function () {
            var el = this;
            var fromTop = beforeTops.get(el);
            if (fromTop === undefined) {
                return;
            }
            var delta = fromTop - el.getBoundingClientRect().top;
            if (Math.abs(delta) < 2) {
                return;
            }
            el.classList.add('sortable-flip-anim');
            el.style.transform = 'translateY(' + delta + 'px)';
            requestAnimationFrame(function () {
                requestAnimationFrame(function () {
                    el.style.transform = '';
                });
            });
            var onEnd = function (ev) {
                if (ev && ev.propertyName && ev.propertyName !== 'transform') {
                    return;
                }
                el.classList.remove('sortable-flip-anim');
                el.removeEventListener('transitionend', onEnd);
            };
            el.addEventListener('transitionend', onEnd);
        });
    }

    function reorderSortableItem($list, $dragItem, clientY) {
        var $target = findSortableItemAt($list, clientY);
        if (!$target || !$target.length || $target[0] === $dragItem[0]) {
            showDropLine($list, clientY);
            return;
        }
        hideDropLine();
        var rect = $target[0].getBoundingClientRect();
        var after = clientY > rect.top + rect.height / 2;
        var beforeTops = captureItemTops($list);
        if (after) {
            $target[0].parentNode.insertBefore($dragItem[0], $target[0].nextSibling);
        } else {
            $target[0].parentNode.insertBefore($dragItem[0], $target[0]);
        }
        playFlipAnimation($list, beforeTops);
    }

    function bindGlobalDragHandlers() {
        if (window.__macThemeSortableBound) {
            return;
        }
        window.__macThemeSortableBound = true;

        $(document).on('mousemove.themeuxSort', function (e) {
            if (!dragState) {
                return;
            }
            dragState.moved = true;
            reorderSortableItem(dragState.$list, dragState.$item, e.clientY);
        });

        $(document).on('mouseup.themeuxSort', function () {
            if (!dragState) {
                return;
            }
            hideDropLine();
            var $released = dragState.$item;
            var hadMoved = dragState.moved;
            $released.removeClass('sortable-dragging sortable-dragging--lift');
            if (hadMoved) {
                $released.addClass('sortable-drag-released');
                setTimeout(function () {
                    $released.removeClass('sortable-drag-released');
                }, 380);
            }
            dragState.$list.removeClass('sortable-list-active');
            $('body').removeClass('theme-sortable-active');
            if (hadMoved && typeof dragState.rebuildFn === 'function') {
                dragState.rebuildFn();
            }
            if (dragState.$list) {
                initTypePickerLabels(dragState.$list);
                if (dragState.form) {
                    dragState.form.render('select');
                }
            }
            dragState = null;
        });
    }

    function ensureSortableGrips($list) {
        var dragTitle = t('dragSort', '拖动调整顺序');
        $list.children('.js-sortable-item').each(function () {
            var $item = $(this);
            if ($item.children('.sortable-handle--rail').length) {
                $item.find('.sortable-handle--rail').attr('title', dragTitle);
                return;
            }
            $item.prepend('<span class="sortable-handle sortable-handle--rail" title="' + escapeHtml(dragTitle) + '" role="button" tabindex="0"></span>');
        });
    }

    function initSortableList($list, rebuildFn, form) {
        if (!$list.length) {
            return;
        }
        bindGlobalDragHandlers();
        ensureSortableGrips($list);

        $list.off('mousedown.themeuxSort', '.sortable-handle--rail');
        $list.on('mousedown.themeuxSort', '.sortable-handle--rail', function (e) {
            if (e.which !== 1) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            var $item = $(this).closest('.js-sortable-item');
            if (!$item.length) {
                return;
            }
            hideDropLine();
            dragState = {
                $list: $list,
                $item: $item,
                rebuildFn: rebuildFn,
                form: form,
                moved: false
            };
            $list.addClass('sortable-list-active');
            $item.addClass('sortable-dragging');
            $('body').addClass('theme-sortable-active');
            requestAnimationFrame(function () {
                $item.addClass('sortable-dragging--lift');
            });
        });
    }

    /**
     * 更新列表行标题（保留左侧拖拽手柄在 label 内）
     */
    function updateRowLabels($list, itemSelector, labelFn) {
        $list.find(itemSelector).each(function (index) {
            var title = typeof labelFn === 'function' ? labelFn(index) : (labelFn + (index + 1));
            var $label = $(this).find('.layui-form-label').first();
            if ($label.length) {
                $label.text(title);
            }
        });
        ensureSortableGrips($list);
    }

    function refreshSortableGrips($scope) {
        ($scope || $(document)).find('.js-sortable-list').each(function () {
            ensureSortableGrips($(this));
        });
    }

    window.MacThemeConfigUx = {
        initTypePickerLabels: initTypePickerLabels,
        bindTypePickerEvents: bindTypePickerEvents,
        initSortableList: initSortableList,
        refreshSortableGrips: refreshSortableGrips,
        updateRowLabels: updateRowLabels,
        labelForValue: labelForValue,
        refreshPickerLabel: refreshPickerLabel,
        hotvodTabLabel: function (index) {
            return sprintf(t('hotvodTabLabel', 'Tab%d'), index + 2);
        }
    };
})(window);
