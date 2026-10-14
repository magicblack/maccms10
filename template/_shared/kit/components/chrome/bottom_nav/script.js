(function () {
    'use strict';

    function cleanPath(path) {
        path = String(path || '/').replace(/\/+/g, '/');
        if (path.length > 1) {
            path = path.replace(/\/+$/, '');
        }
        return path || '/';
    }

    /**
     * True only for the site entrance — not module list pages that happen to
     * end with /index (e.g. /index.php/vod/index.html, /user/index.html).
     */
    function isSiteHome(currentPath) {
        if (currentPath === '/' || currentPath === '/index.php') {
            return true;
        }
        if (/^\/index(?:\/index)?(?:\.html)?$/.test(currentPath)) {
            return true;
        }
        if (/^\/index\.php\/index(?:\/index)?(?:\.html)?$/.test(currentPath)) {
            return true;
        }
        return false;
    }

    function itemMatches(item, currentPath) {
        var key = item.getAttribute('data-pb-bottom-nav-item') || '';
        var targetPath = '/';
        try {
            targetPath = cleanPath(new URL(item.href, window.location.href).pathname);
        } catch (e) {
            targetPath = '/';
        }

        if (key === 'home') {
            return currentPath === targetPath || isSiteHome(currentPath);
        }
        if ((key === 'video' || key === 'vod' || key === 'catalog') && /\/vod(?:\/|$)/.test(currentPath)) {
            return true;
        }
        if ((key === 'novel' || key === 'art') && /\/art(?:\/|$)/.test(currentPath)) {
            return true;
        }
        if (key === 'manga' && /\/manga(?:\/|$)/.test(currentPath)) {
            return true;
        }
        if (key === 'discover' && /\/(?:label\/rank|map\/index)(?:\.html)?$/.test(currentPath)) {
            return true;
        }
        if (key === 'me' && /\/user(?:\/|$)/.test(currentPath)) {
            return true;
        }
        // 空默认链到首页时不要误伤其它语义项
        if (targetPath === '/' || targetPath === '/index.php') {
            return false;
        }
        return currentPath === targetPath
            || currentPath.indexOf(targetPath + '/') === 0;
    }

    function init(nav) {
        var currentPath = cleanPath(window.location.pathname);
        var items = nav.querySelectorAll('[data-pb-bottom-nav-item]');
        var matched = false;

        Array.prototype.forEach.call(items, function (item) {
            var active = !matched && itemMatches(item, currentPath);
            item.classList.toggle('is-active', active);
            if (active) {
                item.setAttribute('aria-current', 'page');
                matched = true;
            } else {
                item.removeAttribute('aria-current');
            }
        });
    }

    var navs = document.querySelectorAll('[data-pb-bottom-nav]');
    if (!navs.length) {
        return;
    }
    document.documentElement.classList.add('has-pb-bottom-nav');
    Array.prototype.forEach.call(navs, init);
})();
