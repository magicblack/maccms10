/**
 * UEditor AI 代理 POST：ueditor 扩展页与 dialogs/ai 共用，避免多处复制 fetch/JSON 解析逻辑。
 */
(function (global) {
    'use strict';

    global.__MACCMS_UE_AI_DO_FETCH__ = function (proxyUrl, token, param) {
        fetch(proxyUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(
                Object.assign(
                    {
                        system_prompt: param.systemPromptText || '',
                        user_prompt: param.promptText || ''
                    },
                    token && String(token).trim() !== '' ? { _csrf_token: String(token) } : {}
                )
            )
        })
            .then(function (r) {
                var ct = (r.headers.get('content-type') || '').toLowerCase();
                if (ct.indexOf('application/json') === -1) {
                    return r.text().then(function () {
                        throw new Error('not json');
                    });
                }
                return r.json();
            })
            .then(function (res) {
                if (!res || res.code !== 0) {
                    param.onFinish({ code: -1, msg: res && res.msg ? res.msg : 'request failed' });
                    return;
                }
                var text = res.data && res.data.text ? res.data.text : '';
                param.onFinish({ code: 0, msg: 'ok', data: { text: text } });
            })
            .catch(function (e) {
                param.onFinish({ code: -1, msg: e && e.message ? e.message : 'network error' });
            });
    };

    global.__MACCMS_UE_AI_RESOLVE_FETCH__ = function () {
        try {
            if (global.top && global.top !== global && typeof global.top.__MACCMS_UE_AI_DO_FETCH__ === 'function') {
                return global.top.__MACCMS_UE_AI_DO_FETCH__;
            }
        } catch (e) {}
        return typeof global.__MACCMS_UE_AI_DO_FETCH__ === 'function' ? global.__MACCMS_UE_AI_DO_FETCH__ : null;
    };
})(typeof window !== 'undefined' ? window : this);
