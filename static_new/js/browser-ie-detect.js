/**
 * IE / 360 兼容模式自动拦截（纯前端，无后台配置）。
 * 识别 UA 中 MSIE|Trident 或 document.documentMode，展示全屏引导层。
 * 后台经 MAC_BROWSER_IE_BLOCK_I18N 注入九语文案；未注入时用中文默认。
 * ES5 语法，样式内联，兼容 Trident IE8+。
 */
(function (global) {
  'use strict';

  var DEFAULT_I18N = {
    title: '请切换浏览器内核',
    lead: '检测到您正在使用 IE 内核或 360 兼容模式。本站需 Chrome / Edge 等现代内核才能正常显示。',
    recommend_heading: '推荐使用',
    recommend_1: 'Google Chrome / Microsoft Edge（Chromium）',
    recommend_2: '360 安全/极速浏览器请切换到「极速模式」',
    recommend_3: 'QQ 浏览器、搜狗浏览器请切换到「高速内核」',
    heading_360: '360 浏览器切换极速模式',
    step_360_1: '点击地址栏右侧的 <strong>e</strong> 或 <strong>闪电</strong> 图标',
    step_360_2: '选择「极速模式（推荐）」或「webkit 极速模式」',
    step_360_3: '切换后按 F5 刷新本页',
    foot: '切换完成后请刷新页面继续访问。',
    aria_label: '请切换浏览器内核'
  };

  function resolveI18n() {
    var cfg = global.MAC_BROWSER_IE_BLOCK_I18N;
    var out = {};
    var key;
    if (!cfg || typeof cfg !== 'object') {
      return DEFAULT_I18N;
    }
    for (key in DEFAULT_I18N) {
      if (DEFAULT_I18N.hasOwnProperty(key)) {
        out[key] = (cfg[key] !== undefined && cfg[key] !== null && cfg[key] !== '') ? cfg[key] : DEFAULT_I18N[key];
      }
    }
    return out;
  }

  function isLegacyKernel() {
    var nav = global.navigator;
    var ua = nav ? (nav.userAgent || '') : '';
    if (/MSIE|Trident/i.test(ua)) {
      return true;
    }
    var doc = global.document;
    var dm = doc ? doc.documentMode : 0;
    return !!dm && dm < 12;
  }

  function injectStyle(doc, css) {
    var head = doc.getElementsByTagName('head')[0];
    if (!head) {
      return;
    }
    var style = doc.createElement('style');
    style.type = 'text/css';
    if (style.styleSheet) {
      style.styleSheet.cssText = css;
    } else {
      style.appendChild(doc.createTextNode(css));
    }
    head.appendChild(style);
  }

  function buildMarkup(i18n) {
    return ''
      + '<div class="mac-ie-block__panel">'
      + '<div class="mac-ie-block__icon" aria-hidden="true">!</div>'
      + '<h1 class="mac-ie-block__title">' + i18n.title + '</h1>'
      + '<p class="mac-ie-block__lead">' + i18n.lead + '</p>'
      + '<div class="mac-ie-block__section">'
      + '<h2>' + i18n.recommend_heading + '</h2>'
      + '<ul>'
      + '<li>' + i18n.recommend_1 + '</li>'
      + '<li>' + i18n.recommend_2 + '</li>'
      + '<li>' + i18n.recommend_3 + '</li>'
      + '</ul>'
      + '</div>'
      + '<div class="mac-ie-block__section">'
      + '<h2>' + i18n.heading_360 + '</h2>'
      + '<ol>'
      + '<li>' + i18n.step_360_1 + '</li>'
      + '<li>' + i18n.step_360_2 + '</li>'
      + '<li>' + i18n.step_360_3 + '</li>'
      + '</ol>'
      + '</div>'
      + '<p class="mac-ie-block__foot">' + i18n.foot + '</p>'
      + '</div>';
  }

  var BLOCK_CSS = ''
    + 'html.mac-ie-block-active, html.mac-ie-block-active body { height: 100%; margin: 0; padding: 0; overflow: hidden; }'
    + '#mac-browser-ie-block { position: fixed; top: 0; right: 0; bottom: 0; left: 0; z-index: 2147483647;'
    + 'background: #eef2f7; overflow: auto; font-family: "Microsoft YaHei","PingFang SC",Arial,sans-serif;'
    + 'font-size: 14px; line-height: 1.6; color: #1f2937; }'
    + '#mac-browser-ie-block .mac-ie-block__panel { max-width: 640px; margin: 48px auto; padding: 32px 28px;'
    + 'background: #fff; border: 1px solid #d1d5db; border-radius: 8px; }'
    + '#mac-browser-ie-block .mac-ie-block__icon { width: 56px; height: 56px; line-height: 56px; margin: 0 auto 16px;'
    + 'text-align: center; font-size: 32px; font-weight: bold; color: #fff; background: #dc2626; border-radius: 50%; }'
    + '#mac-browser-ie-block .mac-ie-block__title { margin: 0 0 12px; text-align: center; font-size: 22px; color: #b91c1c; }'
    + '#mac-browser-ie-block .mac-ie-block__lead { margin: 0 0 24px; text-align: center; color: #4b5563; }'
    + '#mac-browser-ie-block .mac-ie-block__section { margin: 0 0 20px; padding: 16px 18px; background: #f8fafc;'
    + 'border: 1px solid #e5e7eb; border-radius: 6px; }'
    + '#mac-browser-ie-block .mac-ie-block__section h2 { margin: 0 0 10px; font-size: 16px; color: #111827; }'
    + '#mac-browser-ie-block .mac-ie-block__section ul, #mac-browser-ie-block .mac-ie-block__section ol { margin: 0; padding-left: 22px; }'
    + '#mac-browser-ie-block .mac-ie-block__section li { margin: 6px 0; }'
    + '#mac-browser-ie-block .mac-ie-block__foot { margin: 20px 0 0; text-align: center; color: #6b7280; font-size: 13px; }';

  function showBlock(i18n) {
    var doc = global.document;
    if (!doc || doc.getElementById('mac-browser-ie-block')) {
      return;
    }

    injectStyle(doc, BLOCK_CSS);

    var root = doc.documentElement;
    if (root) {
      root.className += (root.className ? ' ' : '') + 'mac-ie-block-active';
    }

    var layer = doc.createElement('div');
    layer.id = 'mac-browser-ie-block';
    layer.setAttribute('role', 'dialog');
    layer.setAttribute('aria-modal', 'true');
    layer.setAttribute('aria-label', i18n.aria_label);
    layer.innerHTML = buildMarkup(i18n);

    if (doc.body) {
      doc.body.insertBefore(layer, doc.body.firstChild);
    } else {
      doc.write(layer.outerHTML);
    }

    try {
      global.stop();
    } catch (e1) {
      try {
        doc.execCommand('Stop');
      } catch (e2) { /* ignore */ }
    }
  }

  if (!isLegacyKernel()) {
    return;
  }

  var doc = global.document;
  if (!doc) {
    return;
  }

  var i18n = resolveI18n();
  var run = function () {
    showBlock(i18n);
  };

  if (doc.body) {
    run();
  } else if (doc.addEventListener) {
    doc.addEventListener('DOMContentLoaded', run);
  } else {
    doc.attachEvent('onreadystatechange', function () {
      if (doc.readyState === 'complete' || doc.readyState === 'interactive') {
        run();
      }
    });
  }
})(typeof window !== 'undefined' ? window : this);
