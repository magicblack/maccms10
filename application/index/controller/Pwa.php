<?php
namespace app\index\controller;

/**
 * PWA：动态 manifest、根作用域 Service Worker、离线壳页。
 */
class Pwa extends Base
{
    /** manifest / sw / offline 在站点维护或微信引导时仍须可用 */
    protected function check_site_status()
    {
    }

    protected function check_browser_jump()
    {
    }

    public function manifest()
    {
        $site = isset($GLOBALS['config']['site']) ? $GLOBALS['config']['site'] : [];
        $name = isset($site['site_name']) ? trim((string)$site['site_name']) : '';
        if ($name === '') {
            $name = defined('MAC_NAME') ? MAC_NAME : 'MacCMS';
        }
        $shortName = $name;
        if (function_exists('mb_substr')) {
            $shortName = mb_substr($name, 0, 12, 'UTF-8');
        } elseif (strlen($name) > 12) {
            $shortName = substr($name, 0, 12);
        }

        $tplBase = rtrim($GLOBALS['MAC_PATH_TEMPLATE'], '/');
        $icon192 = mac_url_img($tplBase . '/asset/img/pwa/icon-192.png');
        $icon512 = mac_url_img($tplBase . '/asset/img/pwa/icon-512.png');

        // scope / id 用不含 query 的安装目录，确保应用身份稳定（子目录安装亦正确）
        $scope = MAC_PATH;
        if ($scope === '' || $scope === '/') {
            $scope = '/';
        }

        $startUrl = $scope;
        $startUrl .= (strpos($startUrl, '?') === false ? '?' : '&') . 'utm_source=pwa';

        $icons = [
            [
                'src' => $icon192,
                'sizes' => '192x192',
                'type' => 'image/png',
                'purpose' => 'any',
            ],
            [
                'src' => $icon512,
                'sizes' => '512x512',
                'type' => 'image/png',
                'purpose' => 'any',
            ],
        ];

        $tplDir = isset($site['template_dir']) ? (string)$site['template_dir'] : 'default';
        $maskableFile = ROOT_PATH . 'template' . DS . $tplDir . DS . 'asset' . DS . 'img' . DS . 'pwa' . DS . 'icon-512-maskable.png';
        if (is_file($maskableFile)) {
            $icons[] = [
                'src' => mac_url_img($tplBase . '/asset/img/pwa/icon-512-maskable.png'),
                'sizes' => '512x512',
                'type' => 'image/png',
                'purpose' => 'maskable',
            ];
        }

        // handle_links / capture_links：避免浏览器把站内链接（含后台打开前台）劫持进已安装 PWA
        $manifest = [
            'name' => $name,
            'short_name' => $shortName,
            'id' => $scope,
            'scope' => $scope,
            'start_url' => $startUrl,
            'display' => 'standalone',
            'handle_links' => 'not-preferred',
            'capture_links' => 'none',
            'theme_color' => '#40cc92',
            'background_color' => '#ffffff',
            'icons' => $icons,
        ];

        header('Content-Type: application/manifest+json; charset=utf-8');
        echo json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function sw()
    {
        $site = isset($GLOBALS['config']['site']) ? $GLOBALS['config']['site'] : [];
        $tplDir = isset($site['template_dir']) ? (string)$site['template_dir'] : 'default';
        $swFile = ROOT_PATH . 'template' . DS . $tplDir . DS . 'asset' . DS . 'pwa' . DS . 'sw.js';

        // 薄壳 / 缺 PWA 资源的主题：回退到 default，避免 404 留下坏 SW 劫持整站（浏览器白屏）
        if (!is_file($swFile) && $tplDir !== 'default') {
            $swFile = ROOT_PATH . 'template' . DS . 'default' . DS . 'asset' . DS . 'pwa' . DS . 'sw.js';
        }

        header('Content-Type: application/javascript; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Service-Worker-Allowed: /');
        header('Cache-Control: no-cache');

        if (!is_file($swFile)) {
            // 仍无文件时下发自注销 SW，让已注册的坏客户端自动恢复
            echo $this->killSwitchSwJs();
            exit;
        }

        $js = file_get_contents($swFile);
        $macPath = MAC_PATH;
        if ($macPath === '') {
            $macPath = '/';
        } elseif (substr($macPath, -1) !== '/') {
            $macPath .= '/';
        }
        $js = str_replace('__MAC_PATH__', $macPath, $js);

        echo $js;
        exit;
    }

    /**
     * 自注销 Service Worker：清缓存并 unregister，随后刷新受控页面。
     * @return string
     */
    protected function killSwitchSwJs()
    {
        return <<<'JS'
'use strict';
self.addEventListener('install', function (event) {
  event.waitUntil(self.skipWaiting());
});
self.addEventListener('activate', function (event) {
  event.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(keys.map(function (k) { return caches.delete(k); }));
    }).then(function () {
      return self.registration.unregister();
    }).then(function () {
      return self.clients.matchAll({ type: 'window' });
    }).then(function (clients) {
      clients.forEach(function (c) {
        if (c && typeof c.navigate === 'function') {
          c.navigate(c.url);
        }
      });
    })
  );
});
JS;
    }

    public function offline()
    {
        echo $this->fetch('public/offline');
        exit;
    }
}
