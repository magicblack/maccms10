<?php
namespace app\common\behavior;

/**
 * 主题设计预览：让预览 iframe 内的站内链接保持 ?td_preview=1&td_theme=<theme>&td_pt=<token>，
 * 这样在预览中点击任意链接都仍以「所选主题 + 草稿样式」渲染，而不会跳回站点
 * 当前主题或线上（未应用草稿）的页面。
 *
 * 仅在前台（ENTRANCE=index）且本次请求通过预览鉴权时注入，普通访问与后台不受影响。
 */
class PreviewLinks
{
    public function run(&$content)
    {
        if (!defined('ENTRANCE') || ENTRANCE !== 'index') {
            return;
        }
        if (empty($_GET['td_preview']) || !\app\common\util\ThemePreviewAuth::verifyRequest()) {
            return;
        }
        if (!is_string($content) || $content === '' || stripos($content, '</body>') === false) {
            return;
        }
        $theme = isset($_GET['td_theme']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $_GET['td_theme']) : '';
        $pt = isset($_GET['td_pt']) ? preg_replace('/[^a-zA-Z0-9._-]/', '', (string) $_GET['td_pt']) : '';

        $js = '<script>(function(){'
            . 'var TH=' . json_encode($theme) . ';'
            . 'var PT=' . json_encode($pt) . ';'
            . 'document.addEventListener("click",function(e){'
            . 'var a=e.target&&e.target.closest?e.target.closest("a"):null;if(!a)return;'
            . 'var h=a.getAttribute("href");if(!h||h.charAt(0)==="#")return;'
            . 'if(/^(javascript:|mailto:|tel:|data:)/i.test(h))return;'
            . 'var u;try{u=new URL(a.href,location.href);}catch(x){return;}'
            . 'if(u.origin!==location.origin)return;'
            . 'if(u.searchParams.get("td_preview"))return;'
            . 'u.searchParams.set("td_preview","1");if(TH){u.searchParams.set("td_theme",TH);}'
            . 'if(PT){u.searchParams.set("td_pt",PT);}'
            . 'a.setAttribute("href",u.pathname+u.search+u.hash);'
            . '},true);'
            . '})();</script>';

        $content = str_ireplace('</body>', $js . '</body>', $content);
    }
}
