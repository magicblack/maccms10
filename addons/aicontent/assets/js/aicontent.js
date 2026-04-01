/**
 * AI Content Assistant — Frontend JS
 *
 * Strategy:
 *   PHP (viewFilter) injects ✨ AI button HTML next to every eligible field.
 *   This script loads after page render and binds click handlers to those buttons.
 *   If for any reason a button was not injected by PHP, a fallback creates it.
 *
 *   All user-facing strings are resolved from window.AI_LANG (injected by PHP)
 *   with English hard-coded fallbacks so the script works even if AI_LANG is absent.
 */
(function (window) {
    'use strict';

    // -----------------------------------------------------------------------
    // i18n helper — always call t() instead of using string literals
    // -----------------------------------------------------------------------

    var L = window.AI_LANG || {};

    function t(key, fallback) {
        return (L[key] !== undefined) ? L[key] : fallback;
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    function post(url, data, callback) {
        // Attach CSRF token to every POST request
        if (window.AI_CSRF_TOKEN) {
            data._csrf_token = window.AI_CSRF_TOKEN;
        }

        var body = Object.keys(data).map(function (k) {
            return encodeURIComponent(k) + '=' + encodeURIComponent(data[k]);
        }).join('&');

        fetch(url, {
            method : 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body   : body,
        })
        .then(function (r) { return r.json(); })
        .then(callback)
        .catch(function (err) { callback({ code: 0, message: err.message }); });
    }

    function toast(message, type) {
        if (window.layer) {
            window.layer.msg(message, { icon: type === 'success' ? 1 : 2, time: 3000 });
        } else {
            alert(message);
        }
    }

    // Read value from a field (plain or UEditor)
    function readField(el) {
        var id = el.id;
        if (id && window.ue && window.ue.id === id) {
            return window.ue.getContent();
        }
        if (id && window.ueArray) {
            for (var i = 0; i < window.ueArray.length; i++) {
                if (window.ueArray[i] && window.ueArray[i].id === id) {
                    return window.ueArray[i].getContent();
                }
            }
        }
        return el.value;
    }

    // Write value back to a field (plain or UEditor)
    function writeField(el, text) {
        var id = el.id;
        if (id && window.ue && window.ue.id === id) {
            window.ue.setContent(text); return;
        }
        if (id && window.ueArray) {
            for (var i = 0; i < window.ueArray.length; i++) {
                if (window.ueArray[i] && window.ueArray[i].id === id) {
                    window.ueArray[i].setContent(text); return;
                }
            }
        }
        el.value = text;
    }

    // Fields to skip — non-content technical fields
    var SKIP_PATTERNS = [
        'pic', 'img', 'thumb', 'screenshot', 'poster',
        'url', 'play', 'down', 'from', 'server',
        'color', 'letter', 'en', 'sub', 'rel',
        'class', 'note', 'remarks', 'email', 'password',
        'token', 'captcha', 'search', 'keyword'
    ];

    function shouldSkip(el) {
        if (el.type === 'hidden' || el.readOnly || el.disabled) return true;
        var nameId = ((el.name || '') + ' ' + (el.id || '')).toLowerCase();
        return SKIP_PATTERNS.some(function (p) { return nameId.indexOf(p) !== -1; });
    }

    // Build the click handler for an AI button
    function makeClickHandler(el, btn, opts) {
        return function () {
            var draft = readField(el);
            if (!draft || draft.trim() === '') {
                toast(t('write_first', 'Write something in this field first, then click AI to enhance it.'), 'error');
                return;
            }

            var titleEl = document.getElementById(opts.titleField)
                       || document.querySelector('[name="' + opts.titleField + '"]');
            var title = titleEl ? titleEl.value : '';

            var nameId = ((el.name || '') + (el.id || '')).toLowerCase();
            var field  = (nameId.indexOf('content') !== -1) ? 'content' : 'blurb';

            var orig = btn.innerHTML;
            btn.disabled  = true;
            btn.innerHTML = '&#8987;';

            post(opts.enhanceUrl, {
                draft        : draft,
                field        : field,
                title        : title,
                content_type : opts.contentType,
            }, function (res) {
                btn.disabled  = false;
                btn.innerHTML = orig;

                if (res.code === 1 && res.data && res.data.text) {
                    writeField(el, res.data.text);
                    toast(t('enhanced', 'Enhanced!'), 'success');
                } else {
                    toast(res.message || t('enhance_failed', 'Enhancement failed.'), 'error');
                }
            });
        };
    }

    // Create a new ✨ AI button for a field (fallback when PHP didn't inject one)
    function createBtn(el, opts) {
        var btn = document.createElement('button');
        btn.type      = 'button';
        btn.className = 'layui-btn layui-btn-xs layui-btn-normal ai-enhance-btn';
        btn.style.cssText = 'margin-top:4px;margin-left:2px;vertical-align:top;';
        btn.innerHTML = '&#10024; AI';
        btn.title     = t('enhance_title', 'Enhance with AI');
        btn.addEventListener('click', makeClickHandler(el, btn, opts));
        return btn;
    }

    // -----------------------------------------------------------------------
    // Main: bind handlers to PHP-injected buttons; fallback-inject any missed
    // -----------------------------------------------------------------------

    function initEnhance(opts) {
        function run() {
            // 1) Bind handlers to buttons PHP already injected
            var injected = document.querySelectorAll('.ai-enhance-btn[data-target]');
            injected.forEach(function (btn) {
                if (btn._aiBound) return;
                var target = btn.getAttribute('data-target');
                var el = document.getElementById(target)
                      || document.querySelector('[name="' + target + '"]');
                if (el) {
                    btn._aiBound = true;
                    btn.addEventListener('click', makeClickHandler(el, btn, opts));
                }
            });

            // 2) Fallback: inject button for any eligible field that has no button yet
            var allFields = document.querySelectorAll('input[type="text"], textarea');
            allFields.forEach(function (el) {
                if (shouldSkip(el)) return;
                // Skip if the immediately following sibling is already an AI button
                var sib = el.nextElementSibling;
                if (sib && sib.classList && sib.classList.contains('ai-enhance-btn')) return;
                var btn = createBtn(el, opts);
                if (el.tagName === 'TEXTAREA') btn.style.display = 'block';
                el.insertAdjacentElement('afterend', btn);
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', run);
        } else {
            // Delay so UEditor instances are fully initialised
            setTimeout(run, 800);
        }
    }

    // -----------------------------------------------------------------------
    // Config page
    // -----------------------------------------------------------------------

    function initConfigPage(opts) {
        var MODELS = {
            claude:   ['claude-opus-4-6', 'claude-sonnet-4-6', 'claude-haiku-4-5-20251001'],
            openai:   ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo'],
            gemini:   ['gemini-2.0-flash', 'gemini-1.5-pro', 'gemini-1.5-flash'],
            deepseek: ['deepseek-chat', 'deepseek-reasoner'],
            qwen:     ['qwen-turbo', 'qwen-plus', 'qwen-max'],
            glm:      ['glm-4', 'glm-4-flash', 'glm-3-turbo'],
        };

        var providerSel = document.getElementById('cfg_provider');
        var modelSel    = document.getElementById('cfg_model');

        if (providerSel && modelSel) {
            function updateModels(provider) {
                var models = MODELS[provider] || [];
                modelSel.innerHTML = '';
                models.forEach(function (m) {
                    var opt = document.createElement('option');
                    opt.value = m;
                    opt.textContent = m;
                    if (m === opts.defaultModel) opt.selected = true;
                    modelSel.appendChild(opt);
                });
            }
            providerSel.addEventListener('change', function () { updateModels(this.value); });
            updateModels(providerSel.value);
        }

        document.querySelectorAll('.btn-test-key').forEach(function (btn) {
            var testLabel = btn.textContent;
            btn.addEventListener('click', function () {
                var provider = btn.getAttribute('data-provider');
                var keyInput = document.querySelector('input[name="' + provider + '_key"]');
                var key = keyInput ? keyInput.value.trim() : '';
                if (!key) { toast(t('enter_key_first', 'Enter the API key first.'), 'error'); return; }

                btn.disabled = true;
                btn.textContent = t('testing', '...') ;
                post(opts.testKeyUrl, { provider: provider, key: key }, function (res) {
                    btn.disabled = false;
                    btn.textContent = res.code === 1 ? t('ok', '✓ OK') : t('fail', '✗ Fail');
                    setTimeout(function () { btn.textContent = testLabel; }, 2500);
                });
            });
        });
    }

    // -----------------------------------------------------------------------
    // Generate page
    // -----------------------------------------------------------------------

    function initGeneratePage(opts) {
        var form        = document.getElementById('ai-generate-form');
        var contentType = document.getElementById('content_type');
        var videoFields = document.getElementById('video-fields');
        var providerSel = document.getElementById('ai_provider');
        var modelSel    = document.getElementById('ai_model');
        var testBtn     = document.getElementById('btn-test-key');
        var testResult  = document.getElementById('test-key-result');
        var generateBtn = document.getElementById('btn-generate');
        var alertDiv    = document.getElementById('ai-alert');
        var resultPanel = document.getElementById('result-panel');
        var copyBtn     = document.getElementById('btn-copy-all');

        // 1. Populate model dropdown for the given provider
        function updateModels(provider) {
            if (!modelSel) return;
            var models = (window.AI_MODELS_MAP && window.AI_MODELS_MAP[provider]) || {};
            modelSel.innerHTML = '';
            Object.keys(models).forEach(function (key) {
                var opt = document.createElement('option');
                opt.value       = key;
                opt.textContent = models[key];
                if (key === opts.defaultModel) opt.selected = true;
                modelSel.appendChild(opt);
            });
        }

        if (providerSel) {
            updateModels(providerSel.value);
            providerSel.addEventListener('change', function () { updateModels(this.value); });
        }

        // 2. Toggle video-only fields based on content type
        if (contentType && videoFields) {
            contentType.addEventListener('change', function () {
                videoFields.style.display = (this.value === 'video') ? '' : 'none';
            });
        }

        // 3. Test Key button
        if (testBtn) {
            testBtn.addEventListener('click', function () {
                var provider = providerSel ? providerSel.value : '';
                testBtn.disabled       = true;
                testResult.textContent = t('testing', 'Testing...');
                testResult.className   = 'text-muted';
                post(opts.testKeyUrl, { provider: provider }, function (res) {
                    testBtn.disabled = false;
                    if (res.code === 1) {
                        testResult.textContent = t('connected', '✓ Connected');
                        testResult.className   = 'text-success';
                    } else {
                        testResult.textContent = '✗ ' + (res.message || t('failed', 'Failed'));
                        testResult.className   = 'text-danger';
                    }
                });
            });
        }

        // 4. Form submission → generate API
        if (form) {
            var generateLabel = generateBtn ? generateBtn.innerHTML : '';
            form.addEventListener('submit', function (e) {
                e.preventDefault();

                generateBtn.disabled  = true;
                generateBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> ' + t('generating', 'Generating...');
                alertDiv.style.display = 'none';
                resultPanel.style.display = 'none';

                // Collect all form fields; FormData preserves data[title] keys as-is,
                // which ThinkPHP's input('data/a') will parse correctly.
                var data = {};
                new FormData(form).forEach(function (v, k) { data[k] = v; });

                post(opts.generateUrl, data, function (res) {
                    generateBtn.disabled  = false;
                    generateBtn.innerHTML = generateLabel;

                    if (res.code === 1 && res.data) {
                        document.getElementById('result-seo-title').value  = res.data.seo_title   || '';
                        document.getElementById('result-description').value = res.data.description || '';
                        document.getElementById('result-tags').value =
                            Array.isArray(res.data.tags) ? res.data.tags.join(', ') : (res.data.tags || '');
                        resultPanel.style.display = '';
                    } else {
                        alertDiv.className     = 'alert alert-danger';
                        alertDiv.textContent   = res.message || t('generation_failed', 'Generation failed.');
                        alertDiv.style.display = '';
                    }
                });
            });
        }

        // 5. Copy All button
        if (copyBtn) {
            copyBtn.addEventListener('click', function () {
                var text = t('seo_title_label', 'SEO Title: ')   + document.getElementById('result-seo-title').value  + '\n'
                         + t('description_label', 'Description: ') + document.getElementById('result-description').value + '\n'
                         + t('tags_label', 'Tags: ')               + document.getElementById('result-tags').value;
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(text).then(function () { toast(t('copied', 'Copied!'), 'success'); });
                } else {
                    // Fallback for non-HTTPS contexts where clipboard API is unavailable
                    window.prompt('Copy the text below:', text);
                }
            });
        }
    }

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    window.AiContent = {
        initEnhance      : initEnhance,
        initConfigPage   : initConfigPage,
        initGeneratePage : initGeneratePage,
    };

})(window);
