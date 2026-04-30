(function () {
    "use strict";

    var LOCAL_HISTORY_KEY = "mac_vod_search_history_v1";
    var LOCAL_HISTORY_LIMIT = 20;
    var HISTORY_COLLAPSE_THRESHOLD = 6;
    var SUGGEST_DEBOUNCE_MS = 280;
    var SUGGEST_MIN_CHARS = 1;
    var SUGGEST_RETRY_DELAY_MS = 500;

    function $(selector, root) {
        return (root || document).querySelector(selector);
    }

    function getApiBase(rootEl) {
        var val = rootEl && rootEl.getAttribute("data-api-base");
        if (val && String(val).trim()) {
            return String(val).replace(/\/+$/, "");
        }
        if (typeof window.maccms !== "undefined" && window.maccms.path) {
            return String(window.maccms.path).replace(/\/+$/, "") + "/api.php";
        }
        return "/api.php";
    }

    function getUserId() {
        try {
            var raw = localStorage.getItem("user_id") || "";
            var id = parseInt(raw, 10);
            return isNaN(id) ? 0 : id;
        } catch (e) {
            return 0;
        }
    }

    function isLoggedIn() {
        return getUserId() > 0;
    }

    function loadLocalHistory() {
        try {
            var raw = localStorage.getItem(LOCAL_HISTORY_KEY);
            var arr = raw ? JSON.parse(raw) : [];
            return Array.isArray(arr) ? arr : [];
        } catch (e) {
            return [];
        }
    }

    function saveLocalHistory(words) {
        try {
            localStorage.setItem(LOCAL_HISTORY_KEY, JSON.stringify(words.slice(0, LOCAL_HISTORY_LIMIT)));
        } catch (e) { }
    }

    function pushLocalHistory(word) {
        word = String(word || "").trim();
        if (!word) return;
        var list = loadLocalHistory().filter(function (x) { return x !== word; });
        list.unshift(word);
        saveLocalHistory(list);
    }

    function buildSearchUrl(searchUrl, wd) {
        var plain = String(searchUrl || "").split("#")[0];
        var sep = plain.indexOf("?") >= 0 ? "&" : "?";
        return plain + sep + "wd=" + encodeURIComponent(wd);
    }

    function renderTagList(container, words, searchUrl) {
        if (!container) return;
        container.innerHTML = "";
        words.forEach(function (word) {
            var a = document.createElement("a");
            a.className = "search-hub__tag";
            a.href = buildSearchUrl(searchUrl, word);
            a.textContent = word;
            a.setAttribute("title", word);
            container.appendChild(a);
        });
    }

    function getFallbackHotWords(limit) {
        limit = Math.max(1, parseInt(limit || 10, 10));
        if (typeof window.maccms === "undefined" || typeof window.maccms.search_hot !== "string") {
            return [];
        }
        return String(window.maccms.search_hot)
            .split(/[,\uff0c|\n\r]+/)
            .map(function (item) { return String(item || "").trim(); })
            .filter(function (item, idx, arr) { return item && arr.indexOf(item) === idx; })
            .slice(0, limit);
    }

    function renderSuggestHint(box, text) {
        if (!box) return;
        box.innerHTML = "";
        box.hidden = false;
        box.setAttribute("data-open", "1");
        var tip = document.createElement("div");
        tip.className = "search-hub__suggest-empty";
        tip.textContent = text;
        box.appendChild(tip);
    }

    function renderSuggestFallback(box, words, searchUrl) {
        if (!box || !Array.isArray(words) || !words.length) return false;
        box.innerHTML = "";
        box.hidden = false;
        box.setAttribute("data-open", "1");
        var tip = document.createElement("div");
        tip.className = "search-hub__suggest-empty";
        tip.textContent = "网络不稳定，已切换到热门搜索";
        box.appendChild(tip);
        words.forEach(function (word) {
            var a = document.createElement("a");
            a.className = "search-hub__suggest-item";
            a.href = buildSearchUrl(searchUrl, word);
            a.textContent = word;
            box.appendChild(a);
        });
        return true;
    }

    function renderHistorySection(rootEl, words) {
        var wrap = $("#searchHubHistory");
        var tags = $("#searchHubHistoryTags");
        var searchUrl = rootEl.getAttribute("data-search-url") || "";
        if (!wrap || !tags) return;
        if (!words.length) {
            wrap.style.display = "none";
            return;
        }
        wrap.style.display = "";
        renderTagList(tags, words, searchUrl);
        var collapsible = words.length > HISTORY_COLLAPSE_THRESHOLD;
        wrap.classList.toggle("is-collapsible-false", !collapsible);
        if (!collapsible) wrap.classList.remove("is-expanded");
    }

    function hideSuggest(box) {
        if (!box) return;
        box.hidden = true;
        box.innerHTML = "";
        box.removeAttribute("data-open");
    }

    function jsonFetch(url, signal, opts) {
        opts = opts || {};
        return fetch(url, {
            method: opts.method || "GET",
            credentials: "same-origin",
            signal: signal,
            headers: { "Accept": "application/json" }
        }).then(function (r) { return r.json(); });
    }

    function loadHistory(apiBase) {
        if (!isLoggedIn()) {
            return Promise.resolve(loadLocalHistory());
        }
        return jsonFetch(apiBase + "/index.php/ajax/search_history?limit=20")
            .then(function (r) {
                if (!r || r.code !== 1 || !Array.isArray(r.list)) {
                    return loadLocalHistory();
                }
                return r.list.map(function (it) {
                    return String((it && it.word) || "").trim();
                }).filter(function (w) { return !!w; });
            })
            .catch(function () {
                return loadLocalHistory();
            });
    }

    function clearHistory(apiBase) {
        if (!isLoggedIn()) {
            saveLocalHistory([]);
            return Promise.resolve(true);
        }
        return jsonFetch(apiBase + "/index.php/ajax/search_history_clear", null, { method: "POST" })
            .then(function (r) {
                return !!(r && r.code === 1);
            })
            .catch(function () { return false; });
    }

    function init() {
        var root = $("#searchHub");
        if (!root) return;

        var form = $("#searchHubForm");
        var wdInput = $("#searchHubWd");
        var cancelBtn = $("#searchHubCancel");
        var clearBtn = $("#searchHubHistoryClear");
        var toggleBtn = $("#searchHubHistoryToggle");
        var historyWrap = $("#searchHubHistory");
        var suggestBox = $("#searchHubSuggest");

        var apiBase = getApiBase(root);
        var homeUrl = root.getAttribute("data-home-url") || "/";

        var debounceTimer = null;
        var suggestAbort = null;

        function refreshHistory() {
            loadHistory(apiBase).then(function (words) {
                renderHistorySection(root, words);
            });
        }

        function fetchSuggest(keyword, hasRetried) {
            if (!suggestBox || !wdInput) return;
            keyword = String(keyword || "").trim();
            if (keyword.length < SUGGEST_MIN_CHARS) {
                hideSuggest(suggestBox);
                return;
            }
            if (suggestAbort) {
                try { suggestAbort.abort(); } catch (e) { }
            }
            suggestAbort = typeof AbortController !== "undefined" ? new AbortController() : null;
            var signal = suggestAbort ? suggestAbort.signal : undefined;
            var url = apiBase + "/index.php/vod/suggest?wd=" + encodeURIComponent(keyword) + "&limit=10";
            jsonFetch(url, signal).then(function (r) {
                if (!r || r.code !== 1 || !Array.isArray(r.list) || !r.list.length) {
                    hideSuggest(suggestBox);
                    return;
                }
                suggestBox.innerHTML = "";
                suggestBox.hidden = false;
                suggestBox.setAttribute("data-open", "1");
                r.list.forEach(function (row) {
                    var btn = document.createElement("button");
                    btn.type = "button";
                    btn.className = "search-hub__suggest-item";
                    btn.setAttribute("role", "option");
                    var link = row.vod_link || "";
                    btn.addEventListener("click", function () {
                        if (link) window.location.href = link;
                    });
                    var line = document.createElement("div");
                    line.className = "search-hub__suggest-row";
                    var txt = document.createElement("div");
                    txt.className = "search-hub__suggest-text";
                    var name = document.createElement("div");
                    name.className = "search-hub__suggest-name";
                    name.textContent = row.name || "";
                    txt.appendChild(name);
                    line.appendChild(txt);
                    btn.appendChild(line);
                    suggestBox.appendChild(btn);
                });
            }).catch(function (e) {
                if (e && e.name === "AbortError") return;
                if (!hasRetried) {
                    renderSuggestHint(suggestBox, "网络波动，正在重试...");
                    setTimeout(function () {
                        fetchSuggest(keyword, true);
                    }, SUGGEST_RETRY_DELAY_MS);
                    return;
                }
                var fallbackWords = getFallbackHotWords(8);
                if (!renderSuggestFallback(suggestBox, fallbackWords, root.getAttribute("data-search-url") || "")) {
                    renderSuggestHint(suggestBox, "网络较差，联想暂不可用");
                }
            });
        }

        refreshHistory();

        if (document.addEventListener) {
            document.addEventListener("click", function (evt) {
                if (!suggestBox || suggestBox.hidden) return;
                var t = evt.target;
                if ((wdInput && (t === wdInput || wdInput.contains(t))) || suggestBox.contains(t)) return;
                hideSuggest(suggestBox);
            }, true);
        }

        if (wdInput && suggestBox) {
            wdInput.addEventListener("input", function () {
                if (debounceTimer) clearTimeout(debounceTimer);
                var current = wdInput.value;
                debounceTimer = setTimeout(function () {
                    fetchSuggest(current);
                }, SUGGEST_DEBOUNCE_MS);
            });
            wdInput.addEventListener("focus", function () {
                fetchSuggest(wdInput.value);
            });
        }

        if (cancelBtn) {
            cancelBtn.addEventListener("click", function (evt) {
                evt.preventDefault();
                if (window.history.length > 1) window.history.back();
                else window.location.href = homeUrl;
            });
        }

        if (clearBtn) {
            clearBtn.addEventListener("click", function () {
                clearHistory(apiBase).then(function () {
                    if (!isLoggedIn()) saveLocalHistory([]);
                    refreshHistory();
                });
            });
        }

        if (toggleBtn && historyWrap) {
            toggleBtn.addEventListener("click", function () {
                historyWrap.classList.toggle("is-expanded");
                toggleBtn.setAttribute("aria-expanded", historyWrap.classList.contains("is-expanded") ? "true" : "false");
            });
        }

        if (form && wdInput) {
            form.addEventListener("submit", function () {
                hideSuggest(suggestBox);
                if (!isLoggedIn()) pushLocalHistory(wdInput.value);
            });
        }

        if (typeof language_pack !== "undefined" && language_pack.loadProperties) {
            try { language_pack.loadProperties(localStorage.getItem("lang")); } catch (e) { }
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
