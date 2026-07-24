<?php
namespace app\admin\controller;

/**
 * 主题设计控制器
 *
 * 针对当前激活的前台主题（prism）的「草稿 → 预览 → 发布」可视化设计器。
 * 设计原则：
 *   1. 编辑只写入草稿（draft），永不直接修改线上主题；
 *   2. 预览展示草稿效果；
 *   3. 发布把草稿应用到线上主题，并快照历史以便回滚。
 * 线上主题的 .html / tokens.css / base.css 永不被修改 —— 仅生成一个
 * 覆盖样式（override css）+ 一层 theme-design.json 配置叠加在其上。
 */
class ThemeDesign extends Base
{
    /** 默认主题（无法解析时回退） */
    const THEME = 'prism';

    /** 当前正在设计的主题（可由 ?theme= 选择，默认=站点激活主题/prism） */
    private $theme = 'prism';

    public function __construct()
    {
        parent::__construct();
        // admin 模块统一使用新版后台模板目录 view_new/。
        // Init 会把 template.view_path 指向前台模板目录，模块相对 fetch 需显式指定，否则会白屏。
        $this->view->config('view_path', APP_PATH . 'admin/view_new/');

        $this->theme = $this->resolveTheme();
    }

    /**
     * 解析当前设计主题：
     *   1) 合法的 ?theme= 请求参数；
     *   2) 站点激活主题（site.template_dir，若为有效前台主题）；
     *   3) prism；
     *   4) 首个可用前台主题。
     */
    private function resolveTheme()
    {
        $themes = $this->availableThemes();
        $valid = array();
        foreach ($themes as $t) {
            $valid[$t['dir']] = true;
        }
        $req = trim((string)$this->request->param('theme', ''));
        if ($req !== '' && isset($valid[$req])) {
            return $req;
        }
        $active = isset($GLOBALS['config']['site']['template_dir']) ? (string)$GLOBALS['config']['site']['template_dir'] : '';
        if ($active !== '' && isset($valid[$active])) {
            return $active;
        }
        if (isset($valid['prism'])) {
            return 'prism';
        }
        return !empty($themes) ? $themes[0]['dir'] : self::THEME;
    }

    /**
     * 可设计的前台主题列表（template/<dir>/html 存在者）。
     * 每项：dir / label / active(站点激活) / designable(含 asset/css 令牌样式) / selected(当前设计中)。
     */
    private function availableThemes()
    {
        $base = ROOT_PATH . 'template/';
        $active = isset($GLOBALS['config']['site']['template_dir']) ? (string)$GLOBALS['config']['site']['template_dir'] : '';
        $list = array();
        if (is_dir($base)) {
            foreach (glob($base . '*', GLOB_ONLYDIR) as $dir) {
                $name = basename($dir);
                if (!is_dir($dir . '/html')) {
                    continue; // 仅前台主题
                }
                $list[] = array(
                    'dir'        => $name,
                    'label'      => $this->themeLabel($name),
                    'active'     => ($name === $active),
                    'designable' => is_dir($dir . '/asset/css'),
                    'selected'   => ($name === $this->theme),
                );
            }
        }
        return $list;
    }

    /** 主题目录名 -> 展示名 */
    private function themeLabel($dir)
    {
        $map = array('prism' => 'Prism', 'default' => 'Default');
        return isset($map[$dir]) ? $map[$dir] : ucfirst($dir);
    }

    // ───────────────────────── 路径辅助 ─────────────────────────

    /** 主题设计数据目录（草稿/发布/历史） */
    private function dataDir()
    {
        return ROOT_PATH . 'application/data/theme_design/' . $this->theme . '/';
    }

    /** 草稿配置文件 */
    private function draftFile()
    {
        return $this->dataDir() . 'draft.json';
    }

    /** 已发布配置文件 */
    private function publishedFile()
    {
        return $this->dataDir() . 'published.json';
    }

    /** 历史快照目录 */
    private function historyDir()
    {
        return $this->dataDir() . 'history/';
    }

    /** prism 主题根目录 */
    private function themeDir()
    {
        return ROOT_PATH . 'template/' . $this->theme . '/';
    }

    /** prism 主题 css 目录 */
    private function cssDir()
    {
        return $this->themeDir() . 'asset/css/';
    }

    /** 确保目录存在 */
    private function ensureDir($dir)
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return is_dir($dir);
    }

    // ───────────────────────── 预设数据 ─────────────────────────

    /**
     * 6 套配色预设：key => [name, colors(accent/accent_ink/bg/surface/surface_2/text/text_muted/border)]
     */
    private function schemePresets()
    {
        return array(
            'indigo' => array('name' => lang('admin/themedesign/scheme_indigo'), 'colors' => array(
                'accent' => '#4f46e5', 'accent_ink' => '#ffffff', 'bg' => '#f4f6f9', 'surface' => '#ffffff',
                'surface_2' => '#eef1f6', 'text' => '#1a1d23', 'text_muted' => '#6b7280', 'border' => '#e5e8ee',
            )),
            'ocean' => array('name' => lang('admin/themedesign/scheme_ocean'), 'colors' => array(
                'accent' => '#0ea5e9', 'accent_ink' => '#ffffff', 'bg' => '#f3f7fb', 'surface' => '#ffffff',
                'surface_2' => '#eaf2f9', 'text' => '#0f2233', 'text_muted' => '#5b7488', 'border' => '#dbe7f1',
            )),
            'sunset' => array('name' => lang('admin/themedesign/scheme_sunset'), 'colors' => array(
                'accent' => '#f97316', 'accent_ink' => '#ffffff', 'bg' => '#fbf6f2', 'surface' => '#ffffff',
                'surface_2' => '#f6ece3', 'text' => '#2a1d14', 'text_muted' => '#8a7160', 'border' => '#ecdfd3',
            )),
            'forest' => array('name' => lang('admin/themedesign/scheme_forest'), 'colors' => array(
                'accent' => '#16a34a', 'accent_ink' => '#ffffff', 'bg' => '#f3f8f4', 'surface' => '#ffffff',
                'surface_2' => '#e9f2ec', 'text' => '#14241a', 'text_muted' => '#5c7466', 'border' => '#d7e7dc',
            )),
            'rose' => array('name' => lang('admin/themedesign/scheme_rose'), 'colors' => array(
                'accent' => '#e11d48', 'accent_ink' => '#ffffff', 'bg' => '#fbf3f5', 'surface' => '#ffffff',
                'surface_2' => '#f6e7ec', 'text' => '#2a141b', 'text_muted' => '#8a6070', 'border' => '#ecd5dd',
            )),
            'slate' => array('name' => lang('admin/themedesign/scheme_slate'), 'colors' => array(
                'accent' => '#475569', 'accent_ink' => '#ffffff', 'bg' => '#f4f5f7', 'surface' => '#ffffff',
                'surface_2' => '#eceef2', 'text' => '#1a1d23', 'text_muted' => '#6b7280', 'border' => '#e2e5ea',
            )),
        );
    }

    /** 5 个字体选项 value => label */
    private function fontOptions()
    {
        return array(
            "Inter, 'Noto Sans SC', system-ui, sans-serif" => lang('admin/themedesign/font_inter'),
            "'Noto Sans SC', system-ui, sans-serif" => lang('admin/themedesign/font_noto'),
            "'Microsoft YaHei','微软雅黑', system-ui, sans-serif" => lang('admin/themedesign/font_yahei'),
            "Georgia,'Noto Serif SC', serif" => lang('admin/themedesign/font_serif'),
            "KaiTi,'楷体',STKaiti,'华文楷体',KaiTi_GB2312,serif" => lang('admin/themedesign/font_kaiti'),
            "system-ui,-apple-system,'Segoe UI', sans-serif" => lang('admin/themedesign/font_system'),
        );
    }

    /** 首页区块目录 key => label（顺序即展示顺序） */
    private function sectionCatalog()
    {
        return array(
            'hero' => lang('admin/themedesign/sec_hero'),
            'vod_hot' => lang('admin/themedesign/sec_vod_hot'),
            'vod_latest' => lang('admin/themedesign/sec_vod_latest'),
            'rank_week' => lang('admin/themedesign/sec_rank_week'),
            'art_list' => lang('admin/themedesign/sec_art_list'),
            'topic_list' => lang('admin/themedesign/sec_topic_list'),
            'live_list' => lang('admin/themedesign/sec_live_list'),
            'manga_list' => lang('admin/themedesign/sec_manga_list'),
            'rank_modules' => lang('admin/themedesign/sec_rank_modules'),
            'website_list' => lang('admin/themedesign/sec_website_list'),
            'gbook_list' => lang('admin/themedesign/sec_gbook_list'),
        );
    }

    // ───────────────────────── 配置构建 ─────────────────────────

    /** 顶级分类（用于导航栏默认 type_ids 及视图渲染） */
    private function topTypeTree()
    {
        $list = model('Type')
            ->where(array('type_status' => 1, 'type_pid' => 0))
            ->order('type_sort asc')
            ->field('type_id,type_name,type_mid')
            ->select();
        $tree = array();
        if (!empty($list)) {
            foreach ($list as $v) {
                $tree[] = array(
                    'id' => (int)$v['type_id'],
                    'name' => $v['type_name'],
                    'mid' => (int)$v['type_mid'],
                );
            }
        }
        return $tree;
    }

    /** 完整分类树（顶级 + 各自子分类），用于导航栏步骤的树形选择 */
    private function typeTreeFull()
    {
        $list = model('Type')
            ->where(array('type_status' => 1))
            ->order('type_sort asc')
            ->field('type_id,type_name,type_mid,type_pid')
            ->select();

        $tops = array();
        $children = array();
        if (!empty($list)) {
            foreach ($list as $v) {
                $node = array(
                    'id'  => (int)$v['type_id'],
                    'name' => $v['type_name'],
                    'mid' => (int)$v['type_mid'],
                    'pid' => (int)$v['type_pid'],
                );
                if ((int)$v['type_pid'] === 0) {
                    $node['children'] = array();
                    $tops[(int)$v['type_id']] = $node;
                } else {
                    $children[(int)$v['type_pid']][] = $node;
                }
            }
        }
        foreach ($children as $pid => $kids) {
            if (isset($tops[$pid])) {
                $tops[$pid]['children'] = $kids;
            }
        }
        return array_values($tops);
    }

    /** 扁平分类列表（用于板块属性中的「分类」下拉），子分类带缩进前缀 */
    private function catsFlat()
    {
        $flat = array();
        foreach ($this->typeTreeFull() as $p) {
            $flat[] = array('id' => (int)$p['id'], 'name' => $p['name'], 'mid' => (int)$p['mid']);
            if (!empty($p['children'])) {
                foreach ($p['children'] as $c) {
                    $flat[] = array('id' => (int)$c['id'], 'name' => '　├ ' . $c['name'], 'mid' => (int)$c['mid']);
                }
            }
        }
        return $flat;
    }

    /** 预览 URL（真实前台首页 + td_preview=1 + td_theme=所选主题，供步骤内 iframe 实时预览） */
    private function previewUrl()
    {
        $url = $this->siteHome();
        $url .= (strpos($url, '?') === false ? '?' : '&') . 'td_preview=1';
        // 用「所选主题」渲染预览，而非站点当前 template_dir（二者可不同）
        $url .= '&td_theme=' . urlencode($this->theme);
        return $url;
    }

    /** 选定主题自带的 logo 资源 URL（空态预览展示其内置 logo）；没有则空 */
    private function themeLogoUrl()
    {
        $cands = array('asset/img/logo.svg', 'asset/img/logo.png', 'asset/img/logo_black.png', 'asset/img/logo.jpg');
        foreach ($cands as $rel) {
            $r = 'template/' . $this->theme . '/' . $rel;
            if (is_file(ROOT_PATH . $r)) {
                return MAC_PATH . $r;
            }
        }
        return '';
    }

    /** 选定主题的默认头像 URL（主题自带优先，否则全局 static_new 头像） */
    private function themeAvatarUrl()
    {
        $r = 'template/' . $this->theme . '/asset/img/touxiang.png';
        if (is_file(ROOT_PATH . $r)) {
            return MAC_PATH . $r;
        }
        return MAC_PATH . 'static_new/images/touxiang.png';
    }

    /**
     * 主题在「未配置版权」时实际显示的默认版权文案，
     * 与 prism footer.html 的回退逻辑一致：© 年份 站点URL [E-Mail] [备案]。
     * 仅用于 wizard 的输入占位提示（留空即用主题内置版权）。
     */
    private function defaultFooterCopyright()
    {
        $site = isset($GLOBALS['config']['site']) ? $GLOBALS['config']['site'] : array();
        $url = isset($site['site_url']) ? trim((string)$site['site_url']) : '';
        $copy = '© ' . date('Y') . ($url !== '' ? ' ' . $url : '');
        $email = isset($site['site_email']) ? trim((string)$site['site_email']) : '';
        if ($email !== '') {
            $copy .= '  E-Mail：' . $email;
        }
        $icp = isset($site['site_icp']) ? trim((string)$site['site_icp']) : '';
        if ($icp !== '') {
            $copy .= '  ' . $icp;
        }
        return $copy;
    }

    /**
     * 默认配置（indigo 预设 + Inter 字体 + 16px + 站点 logo/版权 + 全部顶级分类 + 全部区块）
     */
    private function buildDefaults()
    {
        $site = isset($GLOBALS['config']['site']) ? $GLOBALS['config']['site'] : array();
        $presets = $this->schemePresets();

        // 默认 logo 留空：prism 主题在未配置 logo 时使用内置 SVG 标识，
        // 不应预填站点 logo.jpg（那会覆盖主题自带标识）。留空 = 用主题默认。
        $logo = '';

        // 默认版权留空：使用 prism 主题内置版权（© 年份 站点URL [E-Mail/备案]），
        // 与前台实际显示一致；不再预填站点名（那会与主题真正显示的版权不符）。
        $copyright = '';

        // 导航默认全部顶级分类
        $type_ids = array();
        foreach ($this->topTypeTree() as $t) {
            $type_ids[] = (int)$t['id'];
        }

        // 首页区块默认全部启用（按目录顺序）
        $sections = array();
        foreach ($this->sectionCatalog() as $key => $label) {
            $sections[] = array('key' => $key, 'enabled' => true);
        }

        return array(
            'theme' => $this->theme,
            'updated_at' => time(),
            'attributes' => array(
                'color_scheme' => 'indigo',
                'colors' => $presets['indigo']['colors'],
                'font_family' => "Inter, 'Noto Sans SC', system-ui, sans-serif",
                'font_size' => 16,
                'logo' => $logo,
                'footer_copyright' => $copyright,
                'default_avatar' => '',
            ),
            'navbar' => array(
                'type_ids' => $type_ids,
            ),
            'footer' => array(
                'links' => array(),
            ),
            'homepage' => array(
                'sections' => $sections,
            ),
        );
    }

    /** 读取 JSON 文件为数组，失败返回 null */
    private function readJson($file)
    {
        if (!is_file($file)) {
            return null;
        }
        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            return null;
        }
        $arr = json_decode($raw, true);
        return is_array($arr) ? $arr : null;
    }

    /** 写入 JSON 文件 */
    private function writeJson($file, $data)
    {
        $dir = dirname($file);
        if (!$this->ensureDir($dir)) {
            return false;
        }
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }
        return @file_put_contents($file, $json) !== false;
    }

    /** 取当前草稿配置（无草稿则从已发布初始化，再无则用默认） */
    private function currentDraft()
    {
        $cfg = $this->readJson($this->draftFile());
        if (is_array($cfg)) {
            return $cfg;
        }
        $cfg = $this->readJson($this->publishedFile());
        if (is_array($cfg)) {
            return $cfg;
        }
        return $this->buildDefaults();
    }

    // ───────────────────────── 产物生成（APPLY） ─────────────────────────

    /**
     * 把配置写成主题预览/线上产物。
     * @param array $cfg     设计配置
     * @param bool  $isDraft true=草稿产物（.draft），false=线上产物
     * @return bool
     */
    private function writeArtifacts($cfg, $isDraft)
    {
        $cssOk = $this->writeOverrideCss($cfg, $isDraft);
        $jsonOk = $this->writeDesignJson($cfg, $isDraft);
        return $cssOk && $jsonOk;
    }

    /** 校验十六进制颜色 */
    private function validHex($v)
    {
        return is_string($v) && preg_match('/^#[0-9a-fA-F]{3,8}$/', $v);
    }

    /** 生成覆盖样式 css */
    private function writeOverrideCss($cfg, $isDraft)
    {
        $defaults = $this->schemePresets();
        $defColors = $defaults['indigo']['colors'];

        $attr = isset($cfg['attributes']) && is_array($cfg['attributes']) ? $cfg['attributes'] : array();
        $colors = isset($attr['colors']) && is_array($attr['colors']) ? $attr['colors'] : array();

        // 逐项校验颜色，非法回退默认（永不写出畸形 css）
        $keys = array('accent', 'accent_ink', 'bg', 'surface', 'surface_2', 'text', 'text_muted', 'border');
        $c = array();
        foreach ($keys as $k) {
            $v = isset($colors[$k]) ? $colors[$k] : '';
            $c[$k] = $this->validHex($v) ? $v : $defColors[$k];
        }

        // 字体栈：仅允许常见字符，否则回退默认
        $font = isset($attr['font_family']) ? (string)$attr['font_family'] : '';
        if ($font === '' || preg_match('/[<>{};]/', $font)) {
            $font = "Inter, 'Noto Sans SC', system-ui, sans-serif";
        }

        // 字号 12-22，否则回退 16
        $fontSize = isset($attr['font_size']) ? (int)$attr['font_size'] : 16;
        if ($fontSize < 12 || $fontSize > 22) {
            $fontSize = 16;
        }

        $css = ':root{'
            . '--c-accent:' . $c['accent'] . ';'
            . '--c-accent-ink:' . $c['accent_ink'] . ';'
            . '--c-bg:' . $c['bg'] . ';'
            . '--c-surface:' . $c['surface'] . ';'
            . '--c-surface-2:' . $c['surface_2'] . ';'
            . '--c-text:' . $c['text'] . ';'
            . '--c-text-muted:' . $c['text_muted'] . ';'
            . '--c-border:' . $c['border'] . ';'
            . '--font-base:' . $font . ';'
            . '--font-head:' . $font . ';'
            . '}' . "\n"
            . 'html{font-size:' . $fontSize . 'px}' . "\n"
            . '.bstem{--c-accent:' . $c['accent'] . ';--c-accent-ink:' . $c['accent_ink'] . ';}' . "\n";

        $dir = $this->cssDir();
        if (!$this->ensureDir($dir)) {
            return false;
        }
        $file = $dir . ($isDraft ? 'theme-override.draft.css' : 'theme-override.css');
        return @file_put_contents($file, $css) !== false;
    }

    /** 生成 theme-design json 配置层 */
    private function writeDesignJson($cfg, $isDraft)
    {
        $attr = isset($cfg['attributes']) && is_array($cfg['attributes']) ? $cfg['attributes'] : array();
        $data = array(
            'navbar' => isset($cfg['navbar']) ? $cfg['navbar'] : array('type_ids' => array()),
            'footer' => isset($cfg['footer']) ? $cfg['footer'] : array('links' => array()),
            'homepage' => isset($cfg['homepage']) ? $cfg['homepage'] : array('sections' => array()),
            'logo' => isset($attr['logo']) ? (string)$attr['logo'] : '',
            'footer_copyright' => isset($attr['footer_copyright']) ? (string)$attr['footer_copyright'] : '',
            'default_avatar' => isset($attr['default_avatar']) ? (string)$attr['default_avatar'] : '',
        );

        $dir = $this->themeDir();
        if (!$this->ensureDir($dir)) {
            return false;
        }
        $file = $dir . ($isDraft ? 'theme-design.draft.json' : 'theme-design.json');
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }
        return @file_put_contents($file, $json) !== false;
    }

    /**
     * 发布专属：把默认头像同步到 static_new/images/touxiang.png（带备份）。
     * 全程 is_file 保护，任何失败都不致命。
     */
    private function applyDefaultAvatar($cfg)
    {
        $attr = isset($cfg['attributes']) && is_array($cfg['attributes']) ? $cfg['attributes'] : array();
        $avatar = isset($attr['default_avatar']) ? (string)$attr['default_avatar'] : '';
        if ($avatar === '') {
            return;
        }

        // 只处理本地上传路径（相对站点根的图片），去掉可能的前导斜杠
        $rel = ltrim(str_replace('\\', '/', $avatar), '/');
        // 安全：禁止目录穿越
        if (strpos($rel, '..') !== false) {
            return;
        }
        $src = ROOT_PATH . $rel;
        if (!is_file($src)) {
            return;
        }
        // 仅接受图片扩展名
        $ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
        if (!in_array($ext, array('png', 'jpg', 'jpeg', 'gif', 'webp'), true)) {
            return;
        }

        $destDir = ROOT_PATH . 'static_new/images/';
        if (!is_dir($destDir)) {
            return;
        }
        $dest = $destDir . 'touxiang.png';

        // 首次替换前备份原图
        if (is_file($dest)) {
            $bak = $destDir . 'touxiang.original.bak.png';
            if (!is_file($bak)) {
                @copy($dest, $bak);
            }
        }
        @copy($src, $dest);
    }

    // ───────────────────────── 动作 ─────────────────────────

    /** 入口 -> 跳转到设计页 */
    public function index()
    {
        return $this->redirect(url('theme_design/design'));
    }

    /** 设计页 */
    public function design()
    {
        $cfg = $this->currentDraft();

        // 迁移：早期默认把站点 logo（site_logo）预填进了设计，导致显示成
        // 通用/默认模板的 logo。若 logo 恰为站点 logo，视为「未自定义」→ 清空，
        // 让前台与预览回退到所选主题自带的 logo。
        $migrated = false;
        $site_logo = isset($GLOBALS['config']['site']['site_logo']) ? (string)$GLOBALS['config']['site']['site_logo'] : '';
        if ($site_logo !== '' && isset($cfg['attributes']['logo']) && (string)$cfg['attributes']['logo'] === $site_logo) {
            $cfg['attributes']['logo'] = '';
            $migrated = true;
        }
        // 同样迁移：早期默认把「© 年份 站点名」预填进版权，与主题实际显示不符 → 清空。
        $site_name = isset($GLOBALS['config']['site']['site_name']) ? (string)$GLOBALS['config']['site']['site_name'] : '';
        $copy = isset($cfg['attributes']['footer_copyright']) ? (string)$cfg['attributes']['footer_copyright'] : '';
        if ($site_name !== '' && $copy !== ''
            && preg_match('/^©\s*\d{4}\s+(.+)$/u', $copy, $mm) && trim($mm[1]) === $site_name) {
            $cfg['attributes']['footer_copyright'] = '';
            $migrated = true;
        }
        // 持久化迁移，使预览产物（draft）也丢弃错误值
        if ($migrated && is_file($this->draftFile())) {
            $this->writeJson($this->draftFile(), $cfg);
            $this->writeArtifacts($cfg, true);
        }

        // 若尚无草稿，用 published 或 defaults 初始化（仅用于展示，不落盘）
        $defaults = $this->buildDefaults();

        // 配色预设列表
        $schemes = array();
        foreach ($this->schemePresets() as $key => $p) {
            $schemes[] = array('key' => $key, 'name' => $p['name'], 'colors' => $p['colors']);
        }

        // 字体列表
        $fonts = array();
        foreach ($this->fontOptions() as $value => $label) {
            $fonts[] = array('value' => $value, 'label' => $label);
        }

        // 区块目录
        $section_catalog = array();
        foreach ($this->sectionCatalog() as $key => $label) {
            $section_catalog[] = array('key' => $key, 'label' => $label);
        }

        $this->assign('cfg', $cfg);
        $this->assign('defaults', $defaults);
        $this->assign('schemes', $schemes);
        $this->assign('fonts', $fonts);
        $this->assign('section_catalog', $section_catalog);
        $this->assign('type_tree', $this->topTypeTree());
        $this->assign('type_tree_full', $this->typeTreeFull());
        $this->assign('cats_flat', $this->catsFlat());
        $this->assign('themes', $this->availableThemes());
        $this->assign('cur_theme', $this->theme);
        $this->assign('preview_url', $this->previewUrl());
        // 主题自带 logo / 默认头像（字段留空时用于预览回退展示选定主题自己的资源）
        $this->assign('theme_logo_url', $this->themeLogoUrl());
        $this->assign('default_avatar_url', $this->themeAvatarUrl());
        // 版权占位：展示「留空时主题实际显示的默认版权」
        $this->assign('footer_copyright_ph', $this->defaultFooterCopyright());
        $this->assign('has_published', is_file($this->publishedFile()));
        $this->assign('title', lang('menu/theme_design'));
        return $this->fetch('themedesign/design');
    }

    /** 加载当前配置（JSON） */
    public function load()
    {
        $draft = $this->currentDraft();
        $published = $this->readJson($this->publishedFile());
        return json(array(
            'code' => 1,
            'draft' => $draft,
            'published' => $published,
            'has_published' => is_file($this->publishedFile()),
            'defaults' => $this->buildDefaults(),
        ));
    }

    /**
     * 保存某一步骤到草稿，并刷新草稿预览产物。
     * POST step=attributes|navbar|footer|homepage, data=<JSON string>
     */
    public function save_step()
    {
        if (!$this->request->isPost()) {
            return json(array('code' => 0, 'msg' => lang('illegal_request')));
        }
        $param = input('post.');
        $step = isset($param['step']) ? trim($param['step']) : '';
        $dataRaw = isset($param['data']) ? $param['data'] : '';

        $allowed = array('attributes', 'navbar', 'footer', 'homepage');
        if (!in_array($step, $allowed, true)) {
            return json(array('code' => 0, 'msg' => lang('param_err')));
        }

        $data = json_decode($dataRaw, true);
        if (!is_array($data)) {
            return json(array('code' => 0, 'msg' => lang('param_err')));
        }

        // 草稿不存在则先用默认播种
        $cfg = $this->readJson($this->draftFile());
        if (!is_array($cfg)) {
            $cfg = $this->currentDraft();
            if (!isset($cfg['theme'])) {
                $cfg = $this->buildDefaults();
            }
        }

        // 合并对应步骤
        $cfg[$step] = $data;
        $cfg['theme'] = $this->theme;
        $cfg['updated_at'] = time();

        if (!$this->writeJson($this->draftFile(), $cfg)) {
            return json(array('code' => 0, 'msg' => lang('write_err_config') ?: '写入失败'));
        }

        // 刷新草稿预览产物
        $this->writeArtifacts($cfg, true);

        return json(array('code' => 1, 'msg' => lang('save_ok')));
    }

    /** 预览：刷新草稿产物并返回带 td_preview 标记的首页 URL */
    public function preview()
    {
        $cfg = $this->currentDraft();
        // 确保草稿已落盘（首次预览也能生效）
        if (!is_file($this->draftFile())) {
            $this->writeJson($this->draftFile(), $cfg);
        }
        $this->writeArtifacts($cfg, true);

        $url = $this->siteHome();
        $url = $url . (strpos($url, '?') === false ? '?' : '&') . 'td_preview=1';

        return json(array('code' => 1, 'url' => $url));
    }

    /** 站点首页 URL（site_url 为空则用请求 host 拼） */
    private function siteHome()
    {
        // 预览始终用「后台当前访问的 scheme + host + 安装路径」构建，刻意忽略 site_url：
        // site_url 可能指向后台浏览器无法访问的域名（如 test.cn），会导致预览空白。
        // 这样预览与后台同源、同主机，无论 site_url 设成什么都能正常加载。
        $scheme = $this->request->scheme();
        $host   = $this->request->host();            // 含端口（如有）
        $path   = defined('MAC_PATH') ? (string)MAC_PATH : '/';
        if ($path === '') {
            $path = '/';
        }
        if (substr($path, -1) !== '/') {
            $path .= '/';
        }
        return $scheme . '://' . $host . $path . 'index.php/index.html';
    }

    /**
     * 发布：草稿 -> 已发布 + 历史快照 + 生成线上产物。
     * mode=update（默认）：更新当前主题；
     * mode=new：复制当前主题为一个新主题并把设计作为其线上产物（不动原主题）。
     */
    public function publish()
    {
        if (!$this->request->isPost()) {
            return json(array('code' => 0, 'msg' => lang('illegal_request')));
        }
        $cfg = $this->readJson($this->draftFile());
        if (!is_array($cfg)) {
            return json(array('code' => 0, 'msg' => lang('admin/themedesign/no_draft')));
        }

        $mode = trim((string)input('post.mode', 'update'));
        if ($mode === 'new') {
            return $this->publishAsNew($cfg);
        }

        $cfg['theme'] = $this->theme;
        $cfg['updated_at'] = time();

        // 写已发布
        if (!$this->writeJson($this->publishedFile(), $cfg)) {
            return json(array('code' => 0, 'msg' => lang('write_err_config') ?: '写入失败'));
        }

        // 历史快照
        $ts = time();
        $this->ensureDir($this->historyDir());
        $this->writeJson($this->historyDir() . $ts . '.json', $cfg);

        // 生成线上产物
        $this->writeArtifacts($cfg, false);
        // 发布专属：默认头像同步
        $this->applyDefaultAvatar($cfg);

        // 仅保留最新 ~20 份历史
        $this->trimHistory(20);

        return json(array('code' => 1, 'msg' => lang('admin/themedesign/publish_done')));
    }

    /**
     * 以「新建主题」方式发布：复制当前主题目录为新主题，写入 info.ini，
     * 并把当前草稿作为新主题的线上产物。原主题完全不受影响。
     */
    private function publishAsNew($cfg)
    {
        @set_time_limit(180);

        $dir   = strtolower(trim((string)input('post.new_dir', '')));
        $name  = trim((string)input('post.new_name', ''));
        $intro = trim((string)input('post.new_intro', ''));

        // 目录名校验：字母开头，2-32 位字母/数字/下划线/连字符
        if (!preg_match('/^[a-z][a-z0-9_-]{1,31}$/', $dir)) {
            return json(array('code' => 0, 'msg' => lang('admin/themedesign/new_dir_invalid')));
        }
        if ($name === '') {
            return json(array('code' => 0, 'msg' => lang('admin/themedesign/new_name_required')));
        }

        $src  = ROOT_PATH . 'template/' . $this->theme;
        $dest = ROOT_PATH . 'template/' . $dir;
        if (!is_dir($src)) {
            return json(array('code' => 0, 'msg' => lang('admin/themedesign/new_src_missing')));
        }
        if (is_dir($dest)) {
            return json(array('code' => 0, 'msg' => lang('admin/themedesign/new_dir_exists')));
        }

        // 1) 递归复制主题目录
        if (!$this->copyDir($src, $dest)) {
            return json(array('code' => 0, 'msg' => lang('admin/themedesign/new_copy_failed')));
        }

        // 2) 写 info.ini（主题元信息）
        $ini = "name\t= " . str_replace(array("\r", "\n"), ' ', $name) . "\n"
            . "lastdate= " . date('Y-m-d') . "\n"
            . "version\t= V1.0\n"
            . "author\t= Theme Designer\n"
            . "intro\t= " . str_replace(array("\r", "\n"), ' ', $intro) . "\n";
        @file_put_contents($dest . '/info.ini', $ini);

        // 3) 把当前草稿作为新主题的线上产物（临时把目标主题指向新目录）
        $oldTheme = $this->theme;
        $this->theme = $dir;

        $cfg['theme'] = $dir;
        $cfg['updated_at'] = time();
        $this->writeJson($this->publishedFile(), $cfg);
        $this->ensureDir($this->historyDir());
        $this->writeJson($this->historyDir() . time() . '.json', $cfg);
        $this->writeArtifacts($cfg, false);
        $this->applyDefaultAvatar($cfg);

        $this->theme = $oldTheme;

        return json(array(
            'code' => 1,
            'msg' => lang('admin/themedesign/new_created'),
            'new_theme' => $dir,
        ));
    }

    /** 递归复制目录（用于「新建主题」） */
    private function copyDir($src, $dst)
    {
        if (!is_dir($src)) {
            return false;
        }
        if (!$this->ensureDir($dst)) {
            return false;
        }
        $dh = @opendir($src);
        if (!$dh) {
            return false;
        }
        $ok = true;
        while (($f = readdir($dh)) !== false) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            $s = $src . '/' . $f;
            $d = $dst . '/' . $f;
            if (is_dir($s)) {
                if (!$this->copyDir($s, $d)) {
                    $ok = false;
                    break;
                }
            } else {
                if (!@copy($s, $d)) {
                    $ok = false;
                    break;
                }
            }
        }
        closedir($dh);
        return $ok;
    }

    /** 保留最新 $keep 份历史快照，删除更旧的 */
    private function trimHistory($keep)
    {
        $dir = $this->historyDir();
        if (!is_dir($dir)) {
            return;
        }
        $files = glob($dir . '*.json');
        if ($files === false || count($files) <= $keep) {
            return;
        }
        // 按文件名（时间戳）升序，旧的在前
        $items = array();
        foreach ($files as $f) {
            $ts = (int)pathinfo($f, PATHINFO_FILENAME);
            $items[] = array('ts' => $ts, 'file' => $f);
        }
        usort($items, function ($a, $b) {
            return $a['ts'] - $b['ts'];
        });
        $removeCount = count($items) - $keep;
        for ($i = 0; $i < $removeCount; $i++) {
            @unlink($items[$i]['file']);
        }
    }

    /** 历史列表（新 -> 旧） */
    public function history()
    {
        $dir = $this->historyDir();
        $list = array();
        if (is_dir($dir)) {
            $files = glob($dir . '*.json');
            if (is_array($files)) {
                foreach ($files as $f) {
                    $ts = (int)pathinfo($f, PATHINFO_FILENAME);
                    if ($ts <= 0) {
                        continue;
                    }
                    $list[] = array('ts' => $ts, 'time' => date('Y-m-d H:i:s', $ts));
                }
            }
        }
        // 新 -> 旧
        usort($list, function ($a, $b) {
            return $b['ts'] - $a['ts'];
        });
        return json(array('code' => 1, 'list' => $list));
    }

    /**
     * 回滚：把某历史快照恢复为草稿（不自动发布），刷新草稿预览产物。
     * POST ts=<int>
     */
    public function rollback()
    {
        if (!$this->request->isPost()) {
            return json(array('code' => 0, 'msg' => lang('illegal_request')));
        }
        $ts = (int)input('post.ts/d', 0);
        if ($ts <= 0) {
            return json(array('code' => 0, 'msg' => lang('param_err')));
        }
        $file = $this->historyDir() . $ts . '.json';
        $cfg = $this->readJson($file);
        if (!is_array($cfg)) {
            return json(array('code' => 0, 'msg' => lang('admin/themedesign/history_missing')));
        }

        $cfg['theme'] = $this->theme;
        $cfg['updated_at'] = time();

        if (!$this->writeJson($this->draftFile(), $cfg)) {
            return json(array('code' => 0, 'msg' => lang('write_err_config') ?: '写入失败'));
        }
        $this->writeArtifacts($cfg, true);

        return json(array('code' => 1, 'msg' => lang('admin/themedesign/rollback_done')));
    }

    /** 图片上传（logo / default_avatar），复用 Upload 模型 */
    public function upload()
    {
        $res = model('Upload')->upload(array('input' => 'file', 'flag' => 'images'));
        // 后台(ENTRANCE=admin)下 Upload 返回 {code,msg,data:{file}}，前端读取 res.file，这里统一归一化为顶层 file 并补全为可用 URL
        $file = '';
        if (isset($res['file'])) {
            $file = $res['file'];
        } elseif (isset($res['data']['file'])) {
            $file = $res['data']['file'];
        }
        if ($file !== '' && !preg_match('#^(https?:)?//#', $file) && substr($file, 0, 1) !== '/') {
            $file = MAC_PATH . $file;
        }
        return json(array(
            'code' => isset($res['code']) ? $res['code'] : 0,
            'msg'  => isset($res['msg']) ? $res['msg'] : '',
            'file' => $file,
        ));
    }

    // ───────────────────────── AI 生成 ─────────────────────────

    /** 主题AI 配置（System → Theme AI，独立于后台助手） */
    private function themeAiConfig()
    {
        $cfg = config('maccms');
        return isset($cfg['theme_ai']) && is_array($cfg['theme_ai']) ? $cfg['theme_ai'] : array();
    }

    /**
     * 调用主题AI。provider=openai → {base}/chat/completions（Bearer）；
     * provider=claude → {base}/messages（x-api-key + anthropic-version）。
     * 二者都由所配代理（newapi）支持。api_base 形如 https://host/v1。
     * @return array ['ok'=>bool,'text'=>string,'error'=>string]
     */
    private function themeAiChat($system, $user, $images = array())
    {
        $cfg = $this->themeAiConfig();
        if ((string)(isset($cfg['enabled']) ? $cfg['enabled'] : '0') !== '1') {
            return array('ok' => false, 'error' => lang('admin/themedesign/gen_disabled'));
        }
        $key = trim((string)(isset($cfg['api_key']) ? $cfg['api_key'] : ''));
        $base = rtrim(trim((string)(isset($cfg['api_base']) ? $cfg['api_base'] : '')), '/');
        if ($key === '' || $base === '') {
            return array('ok' => false, 'error' => lang('admin/themedesign/gen_no_key'));
        }
        $provider = strtolower(trim((string)(isset($cfg['provider']) ? $cfg['provider'] : 'openai')));
        $model = trim((string)(isset($cfg['model']) ? $cfg['model'] : 'claude-opus-4-8'));
        if ($model === '') {
            $model = 'claude-opus-4-8';
        }
        $timeout = max(10, min(300, intval(isset($cfg['timeout']) ? $cfg['timeout'] : 60)));
        $maxTokens = max(500, min(8000, intval(isset($cfg['max_tokens']) ? $cfg['max_tokens'] : 4000)));

        $hasImages = is_array($images) && !empty($images);
        $data = null;
        $text = '';
        if ($provider === 'claude' || $provider === 'anthropic') {
            $url = $base . '/messages';
            if ($hasImages) {
                // 视觉输入：user content 为块数组（文本 + base64 图片）。需模型支持视觉。
                $content = array(array('type' => 'text', 'text' => $user));
                foreach ($images as $img) {
                    $content[] = array(
                        'type' => 'image',
                        'source' => array(
                            'type' => 'base64',
                            'media_type' => isset($img['media_type']) ? $img['media_type'] : 'image/png',
                            'data' => isset($img['data']) ? $img['data'] : '',
                        ),
                    );
                }
                $messages = array(array('role' => 'user', 'content' => $content));
            } else {
                $messages = array(array('role' => 'user', 'content' => $user));
            }
            $payload = array(
                'model' => $model,
                'max_tokens' => $maxTokens,
                'system' => $system,
                'messages' => $messages,
            );
            $headers = array('Content-Type: application/json', 'x-api-key: ' . $key, 'anthropic-version: 2023-06-01');
            $resp = \app\common\util\HttpClient::curlPostWithTimeout($url, json_encode($payload, JSON_UNESCAPED_UNICODE), $headers, $timeout);
            $data = json_decode((string)$resp, true);
            if (is_array($data) && isset($data['content']) && is_array($data['content'])) {
                foreach ($data['content'] as $blk) {
                    if (is_array($blk) && isset($blk['text'])) {
                        $text .= (string)$blk['text'];
                    }
                }
            }
        } else {
            $url = $base . '/chat/completions';
            if ($hasImages) {
                // OpenAI 兼容视觉：user content 为块数组（text + image_url data-uri）。
                $uc = array(array('type' => 'text', 'text' => $user));
                foreach ($images as $img) {
                    $mt = isset($img['media_type']) ? $img['media_type'] : 'image/png';
                    $uc[] = array(
                        'type' => 'image_url',
                        'image_url' => array('url' => 'data:' . $mt . ';base64,' . (isset($img['data']) ? $img['data'] : '')),
                    );
                }
                $userMsg = array('role' => 'user', 'content' => $uc);
            } else {
                $userMsg = array('role' => 'user', 'content' => $user);
            }
            $payload = array(
                'model' => $model,
                'max_tokens' => $maxTokens,
                'messages' => array(
                    array('role' => 'system', 'content' => $system),
                    $userMsg,
                ),
            );
            $headers = array('Content-Type: application/json', 'Authorization: Bearer ' . $key);
            $resp = \app\common\util\HttpClient::curlPostWithTimeout($url, json_encode($payload, JSON_UNESCAPED_UNICODE), $headers, $timeout);
            $data = json_decode((string)$resp, true);
            if (isset($data['choices'][0]['message']['content'])) {
                $text = (string)$data['choices'][0]['message']['content'];
            }
        }

        if (trim($text) === '') {
            $emsg = '';
            if (is_array($data)) {
                if (isset($data['error']['message'])) {
                    $emsg = (string)$data['error']['message'];
                } elseif (isset($data['error']) && is_string($data['error'])) {
                    $emsg = $data['error'];
                } elseif (isset($data['message'])) {
                    $emsg = (string)$data['message'];
                }
            }
            if ($emsg !== '') {
                // API 明确返回了错误（限流/额度/鉴权等）——直接透传，避免误报“无法解析”。
                $low = strtolower($emsg);
                $rate = strpos($low, 'rate limit') !== false || strpos($low, 'rate_limit') !== false
                    || strpos($low, 'too many requests') !== false || strpos($low, '429') !== false;
                $hint = $rate ? ' ' . lang('admin/themedesign/gen_rate_limit') : '';
                return array('ok' => false, 'error' => mb_substr($emsg, 0, 240) . $hint);
            }
            return array('ok' => false, 'error' => lang('admin/themedesign/gen_bad_response'));
        }
        return array('ok' => true, 'text' => $text);
    }

    /** 扁平分类（去缩进前缀），供 prompt 使用 */
    private function catsForPrompt()
    {
        $out = array();
        foreach ($this->catsFlat() as $c) {
            $out[] = array(
                'id' => (int)$c['id'],
                'name' => preg_replace('/^[\s　├]+/u', '', (string)$c['name']),
                'mid' => (int)$c['mid'],
            );
        }
        return $out;
    }

    /**
     * 把「分类引用」解析为有效的 type_id：接受数字 **ID** 或分类**名称**（不区分大小写、忽略缩进前缀）。
     * 无效 / 找不到返回 0。让主题在收到分类 ID 或名称时都能正确按分类构建。
     */
    private function resolveCategoryId($ref)
    {
        static $byId = null, $byName = null;
        if ($byId === null) {
            $byId = array();
            $byName = array();
            foreach ($this->catsFlat() as $c) {
                $id = (int)$c['id'];
                if ($id <= 0) {
                    continue;
                }
                $byId[$id] = $id;
                $nm = strtolower(trim(preg_replace('/^[\s　├]+/u', '', (string)$c['name'])));
                if ($nm !== '' && !isset($byName[$nm])) {
                    $byName[$nm] = $id;
                }
            }
        }
        if (is_array($ref)) {
            return 0;
        }
        $s = trim((string)$ref);
        if ($s === '') {
            return 0;
        }
        if (ctype_digit($s)) {
            $id = (int)$s;
            return isset($byId[$id]) ? $id : 0;
        }
        $nm = strtolower($s);
        return isset($byName[$nm]) ? $byName[$nm] : 0;
    }

    /** 生成用的 system 提示（内置严格 JSON 契约 + 允许的词汇表） */
    private function buildGeneratePromptSystem()
    {
        $sectionKeys = array_keys($this->sectionCatalog());
        $fonts = array_keys($this->fontOptions());

        $sys = "You are a theme configuration generator for a MacCMS video/article/manga CMS front-end (theme 'prism').\n"
            . "Read the user's requirements (freeform text OR a filled Markdown request form) and output ONE JSON object ONLY — no prose, no markdown fences.\n\n"
            . "Output schema:\n"
            . "{\n"
            . "  \"design\": {\n"
            . "    \"attributes\": {\n"
            . "      \"colors\": {\"accent\":\"#hex\",\"accent_ink\":\"#hex\",\"bg\":\"#hex\",\"surface\":\"#hex\",\"surface_2\":\"#hex\",\"text\":\"#hex\",\"text_muted\":\"#hex\",\"border\":\"#hex\"},\n"
            . "      \"font_family\": \"<one exact string from FONTS>\",\n"
            . "      \"font_size\": <integer 12..22>\n"
            . "    },\n"
            . "    \"navbar\": {\"type_ids\": [<category ids from CATEGORIES>]},\n"
            . "    \"homepage\": {\"sections\": [ {\"key\":\"<SECTION_KEYS item or 'custom'>\",\"enabled\":true,\"num\":12,\"layout\":\"slider|grid|list|''\",\"by\":\"hits|time|score (hero only)\",\"period\":\"day|week|month|all (rank_week only)\",\"type_id\":<optional category id>,\"content_type\":\"vod|art|manga|rank (custom only)\",\"title\":\"<custom only>\"} ] }\n"
            . "  },\n"
            . "  \"theme_name\": \"<short human theme name — from the form's Basics if given, else a fitting name>\",\n"
            . "  \"theme_dir\": \"<lowercase Latin folder name, 2-32 chars, starts with a letter — from the form's Basics if given, else derived from theme_name>\",\n"
            . "  \"coverage\": [ {\"requirement\":\"<short label>\",\"status\":\"implemented|partial|skipped\",\"note\":\"<short reason>\"} ]\n"
            . "}\n\n"
            . "Rules:\n"
            . "- colors MUST be 3- or 6-digit hex. For a dark theme set bg/surface/surface_2 dark and text light. accent is used in BOTH light and dark modes.\n"
            . "- Keep readable contrast: text on bg, and accent_ink on accent.\n"
            . "- font_family MUST be copied verbatim from FONTS. font_size is an integer 12..22.\n"
            . "- Only use section keys from SECTION_KEYS (extra blocks use key \"custom\"). The sections array order = top-to-bottom on the page. Disable unwanted sections with enabled:false or omit them.\n"
            . "- navbar.type_ids MUST be ids from CATEGORIES only, in the desired order.\n"
            . "- A category may be referenced by its numeric id OR its exact name; ALWAYS output the numeric id from CATEGORIES (for navbar.type_ids entries and for a section's type_id). To limit a section to one category, set that section's type_id. Omit any category we cannot match.\n"
            . "- coverage: list EVERY distinct requirement the user asked for and whether you implemented it. Things this theme system cannot do via config (custom radius/spacing/shadows, animations, a specific logo image, brand-new components) must be marked \"skipped\" with a brief note.\n\n"
            . "FONTS = " . json_encode($fonts, JSON_UNESCAPED_UNICODE) . "\n"
            . "SECTION_KEYS = " . json_encode($sectionKeys, JSON_UNESCAPED_UNICODE) . "\n"
            . "CATEGORIES = " . json_encode($this->catsForPrompt(), JSON_UNESCAPED_UNICODE) . "\n";
        return $sys;
    }

    /** 解析模型输出：剥离 ``` 围栏，抽取最外层 {...} */
    private function parseGenerated($text)
    {
        $text = (string)$text;
        $text = preg_replace('/```[a-zA-Z0-9_-]*/', '', $text);
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }
        $json = substr($text, $start, $end - $start + 1);
        $arr = json_decode($json, true);
        if (!is_array($arr)) {
            // 常见修复：去掉对象/数组结尾的多余逗号（模型偶发）后重试
            $repaired = preg_replace('/,(\s*[}\]])/', '$1', $json);
            $arr = json_decode($repaired, true);
        }
        return is_array($arr) ? $arr : null;
    }

    /**
     * 把生成结果安全地合并进当前草稿（白名单校验，永不信任模型直出）。
     * @return array [$cfg, $applied]
     */
    private function sanitizeGeneratedIntoDraft($gen)
    {
        $base = $this->currentDraft();
        if (!isset($base['attributes']) || !is_array($base['attributes'])) {
            $base['attributes'] = array();
        }
        $design = isset($gen['design']) && is_array($gen['design']) ? $gen['design'] : $gen;
        $applied = array('colors' => 0, 'font' => 0, 'font_size' => 0, 'sections' => 0, 'nav' => 0);

        $presets = $this->schemePresets();
        $defColors = $presets['indigo']['colors'];
        $attr = isset($design['attributes']) && is_array($design['attributes']) ? $design['attributes'] : array();

        // colors
        $genColors = isset($attr['colors']) && is_array($attr['colors']) ? $attr['colors'] : array();
        $keys = array('accent', 'accent_ink', 'bg', 'surface', 'surface_2', 'text', 'text_muted', 'border');
        $colors = isset($base['attributes']['colors']) && is_array($base['attributes']['colors']) ? $base['attributes']['colors'] : $defColors;
        $hits = 0;
        foreach ($keys as $k) {
            if (isset($genColors[$k]) && $this->validHex($genColors[$k])) {
                $colors[$k] = $genColors[$k];
                $hits++;
            } elseif (!isset($colors[$k]) || !$this->validHex($colors[$k])) {
                $colors[$k] = $defColors[$k];
            }
        }
        if ($hits > 0) {
            $base['attributes']['colors'] = $colors;
            $base['attributes']['color_scheme'] = 'custom';
        }
        $applied['colors'] = $hits;

        // font family (must be one of the allowed stacks)
        $fontOpts = array_keys($this->fontOptions());
        if (isset($attr['font_family'])) {
            $f = trim((string)$attr['font_family']);
            if ($f !== '' && in_array($f, $fontOpts, true)) {
                $base['attributes']['font_family'] = $f;
                $applied['font'] = 1;
            }
        }

        // font size
        if (isset($attr['font_size'])) {
            $fs = intval($attr['font_size']);
            if ($fs >= 12 && $fs <= 22) {
                $base['attributes']['font_size'] = $fs;
                $applied['font_size'] = $fs;
            }
        }

        // navbar
        if (isset($design['navbar']['type_ids']) && is_array($design['navbar']['type_ids'])) {
            $ids = array();
            foreach ($design['navbar']['type_ids'] as $ref) {
                $rid = $this->resolveCategoryId($ref); // 接受数字 ID 或分类名称
                if ($rid > 0 && !in_array($rid, $ids, true)) {
                    $ids[] = $rid;
                }
            }
            if (!empty($ids)) {
                $base['navbar'] = array('type_ids' => $ids);
                $applied['nav'] = count($ids);
            }
        }

        // homepage sections
        if (isset($design['homepage']['sections']) && is_array($design['homepage']['sections'])) {
            $sections = $this->sanitizeGeneratedSections($design['homepage']['sections']);
            if (!empty($sections)) {
                $base['homepage'] = array('sections' => $sections);
                $applied['sections'] = count($sections);
            }
        }

        $base['theme'] = $this->theme;
        $base['updated_at'] = time();
        return array($base, $applied);
    }

    /** 校验首页区块数组（安全边界） */
    private function sanitizeGeneratedSections($list)
    {
        $catalogKeys = array_keys($this->sectionCatalog());
        $layouts = array('slider', 'grid', 'list');
        $sorts = array('hits', 'time', 'score');
        $periods = array('day', 'week', 'month', 'all');
        $ctypes = array('vod', 'art', 'manga', 'rank');

        $out = array();
        $seen = array();
        $customSeq = 0;
        foreach ($list as $s) {
            if (!is_array($s)) {
                continue;
            }
            $key = isset($s['key']) ? (string)$s['key'] : '';
            $custom = (isset($s['custom']) && ($s['custom'] === true || $s['custom'] === 1 || $s['custom'] === '1'))
                || $key === 'custom' || preg_match('/^custom_\d+$/', $key);
            if (!$custom) {
                if (!in_array($key, $catalogKeys, true) || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
            } else {
                $customSeq++;
                $key = 'custom_' . $customSeq;
            }
            $enabled = !(isset($s['enabled']) && ($s['enabled'] === false || $s['enabled'] === 0 || $s['enabled'] === '0' || $s['enabled'] === 'false'));
            $item = array('key' => $key, 'enabled' => $enabled);
            // 分类：接受数字 ID 或分类名称；兼容模型可能用的多种字段名
            $catRef = null;
            foreach (array('type_id', 'category_id', 'category', 'cat', 'category_name') as $ck) {
                if (isset($s[$ck]) && $s[$ck] !== '' && $s[$ck] !== null && !is_array($s[$ck])) {
                    $catRef = $s[$ck];
                    break;
                }
            }
            if ($catRef !== null) {
                $t = $this->resolveCategoryId($catRef);
                if ($t > 0) {
                    $item['type_id'] = $t;
                }
            }
            if (isset($s['num'])) {
                $n = intval($s['num']);
                if ($n >= 1 && $n <= 30) {
                    $item['num'] = $n;
                }
            }
            if (isset($s['layout'])) {
                $l = strtolower(trim((string)$s['layout']));
                if (in_array($l, $layouts, true)) {
                    $item['layout'] = $l;
                }
            }
            if (isset($s['by'])) {
                $b = strtolower(trim((string)$s['by']));
                if (in_array($b, $sorts, true)) {
                    $item['by'] = $b;
                }
            }
            if (isset($s['period'])) {
                $p = strtolower(trim((string)$s['period']));
                if (in_array($p, $periods, true)) {
                    $item['period'] = $p;
                }
            }
            if ($custom) {
                $item['custom'] = true;
                $ct = isset($s['content_type']) ? strtolower(trim((string)$s['content_type'])) : 'vod';
                $item['content_type'] = in_array($ct, $ctypes, true) ? $ct : 'vod';
                $title = isset($s['title']) ? trim(strip_tags((string)$s['title'])) : '';
                $item['title'] = mb_substr($title, 0, 60);
            }
            $out[] = $item;
        }
        return $out;
    }

    /** 校验模型的自评「覆盖情况」列表 */
    private function sanitizeCoverage($gen)
    {
        $out = array();
        $cov = isset($gen['coverage']) && is_array($gen['coverage']) ? $gen['coverage'] : array();
        $allowed = array('implemented', 'partial', 'skipped');
        foreach ($cov as $c) {
            if (!is_array($c)) {
                continue;
            }
            $req = isset($c['requirement']) ? trim(strip_tags((string)$c['requirement'])) : '';
            if ($req === '') {
                continue;
            }
            $st = isset($c['status']) ? strtolower(trim((string)$c['status'])) : 'implemented';
            if (!in_array($st, $allowed, true)) {
                $st = 'partial';
            }
            $note = isset($c['note']) ? trim(strip_tags((string)$c['note'])) : '';
            $out[] = array(
                'requirement' => mb_substr($req, 0, 120),
                'status' => $st,
                'note' => mb_substr($note, 0, 200),
            );
            if (count($out) >= 40) {
                break;
            }
        }
        return $out;
    }

    /** 由主题名派生一个合法目录名（小写字母数字），失败回退 'ai'+时间戳 */
    private function slugTheme($name)
    {
        $s = strtolower((string)$name);
        $s = preg_replace('/[^a-z0-9]+/', '', $s);
        $s = substr($s, 0, 32);
        if ($s === '' || !preg_match('/^[a-z]/', $s)) {
            $s = 'ai' . time();
        }
        return substr($s, 0, 32);
    }

    /**
     * 用生成的配置创建（或更新）一个新主题：首次从基底主题克隆整个目录 + 写 info.ini，
     * 然后把配置作为新主题的「线上 + 草稿」产物写入。**基底主题（prism）完全不受影响。**
     * 已存在同名目录则只更新其产物（便于「重新生成」幂等覆盖，不重复克隆）。
     * @return array ['ok'=>bool,'dir'=>string,'created'=>bool,'error'=>string]
     */
    private function createOrUpdateNewTheme($cfg, $dir, $name, $intro)
    {
        @set_time_limit(300);
        $dir = strtolower(trim((string)$dir));
        if (!preg_match('/^[a-z][a-z0-9_-]{1,31}$/', $dir)) {
            return array('ok' => false, 'error' => lang('admin/themedesign/new_dir_invalid'));
        }
        $src = ROOT_PATH . 'template/' . $this->theme;
        $dest = ROOT_PATH . 'template/' . $dir;
        if (!is_dir($src)) {
            return array('ok' => false, 'error' => lang('admin/themedesign/new_src_missing'));
        }

        $created = false;
        if (!is_dir($dest)) {
            if (!$this->copyDir($src, $dest)) {
                return array('ok' => false, 'error' => lang('admin/themedesign/new_copy_failed'));
            }
            $created = true;
            $ini = "name\t= " . str_replace(array("\r", "\n"), ' ', $name) . "\n"
                . "lastdate= " . date('Y-m-d') . "\n"
                . "version\t= V1.0\n"
                . "author\t= Theme AI\n"
                . "intro\t= " . str_replace(array("\r", "\n"), ' ', $intro) . "\n";
            @file_put_contents($dest . '/info.ini', $ini);
        }

        // 以新主题为目标写产物（草稿供预览 + 线上），随后恢复基底主题指针
        $oldTheme = $this->theme;
        $this->theme = $dir;
        $cfg['theme'] = $dir;
        $cfg['updated_at'] = time();
        $this->writeJson($this->draftFile(), $cfg);
        $this->writeJson($this->publishedFile(), $cfg);
        $this->ensureDir($this->historyDir());
        $this->writeJson($this->historyDir() . time() . '.json', $cfg);
        $this->writeArtifacts($cfg, true);   // 草稿产物（供 td_preview 预览）
        $this->writeArtifacts($cfg, false);  // 线上产物
        $this->applyDefaultAvatar($cfg);
        $this->theme = $oldTheme;

        return array('ok' => true, 'dir' => $dir, 'created' => $created);
    }

    /**
     * AI 生成：读取需求（prompt 或表单文本）→ 调 AI → 解析 → 校验 → **直接创建（或更新）一个新主题**
     * （克隆基底主题目录 + 写产物，基底主题不受影响）。POST spec / new_name / new_dir。
     * 返回 applied 摘要 + coverage 覆盖情况 + new_theme 目录。
     */
    public function generate()
    {
        if (!$this->request->isPost()) {
            return json(array('code' => 0, 'msg' => lang('illegal_request')));
        }
        // 每管理员限流（外部 AI 调用，防刷）
        $adminId = intval(isset($this->_admin['admin_id']) ? $this->_admin['admin_id'] : 0);
        if (!\app\common\util\VodAiCover::consumeGenerateRateLimit($adminId)) {
            return json(array('code' => 0, 'msg' => lang('admin/themedesign/ai_rate_limit')));
        }
        $spec = trim(mac_filter_xss((string)input('post.spec', '')));
        if ($spec === '') {
            return json(array('code' => 0, 'msg' => lang('admin/themedesign/ai_empty_spec')));
        }
        if (mb_strlen($spec) > 12000) {
            $spec = mb_substr($spec, 0, 12000);
        }

        @set_time_limit(320);
        $system = $this->buildGeneratePromptSystem();
        $user = "User requirements:\n\n" . $spec;

        $res = $this->themeAiChat($system, $user);
        if (empty($res['ok'])) {
            return json(array('code' => 0, 'msg' => isset($res['error']) ? $res['error'] : lang('admin/themedesign/gen_bad_response')));
        }
        $gen = $this->parseGenerated($res['text']);
        if (!is_array($gen)) {
            return json(array('code' => 0, 'msg' => lang('admin/themedesign/gen_bad_response')));
        }

        list($cfg, $applied) = $this->sanitizeGeneratedIntoDraft($gen);
        if ($applied['colors'] === 0 && $applied['sections'] === 0 && $applied['nav'] === 0 && $applied['font'] === 0 && $applied['font_size'] === 0) {
            return json(array('code' => 0, 'msg' => lang('admin/themedesign/gen_bad_response')));
        }

        // 解析新主题名/目录：UI 输入优先，其次 AI 从表单提取，最后由名称派生
        $uiName = trim((string)input('post.new_name', ''));
        $uiDir  = strtolower(trim((string)input('post.new_dir', '')));
        $aiName = isset($gen['theme_name']) ? trim(strip_tags((string)$gen['theme_name'])) : '';
        $aiDir  = isset($gen['theme_dir']) ? strtolower(trim((string)$gen['theme_dir'])) : '';
        $name = $uiName !== '' ? $uiName : ($aiName !== '' ? $aiName : 'AI Theme');
        $name = mb_substr($name, 0, 60);
        $dir = $uiDir !== '' ? $uiDir : ($aiDir !== '' ? $aiDir : $this->slugTheme($name));
        $dir = preg_replace('/[^a-z0-9_-]/', '', $dir);
        if ($dir === '' || !preg_match('/^[a-z]/', $dir)) {
            $dir = 'ai' . time();
        }
        $dir = substr($dir, 0, 32);

        // 生成 = 直接创建（或更新）一个新主题：克隆基底主题目录 + 写产物，基底主题不受影响
        $new = $this->createOrUpdateNewTheme($cfg, $dir, $name, 'Generated by Theme AI');
        if (empty($new['ok'])) {
            return json(array('code' => 0, 'msg' => isset($new['error']) ? $new['error'] : lang('admin/themedesign/new_copy_failed')));
        }
        $dir = $new['dir'];

        $home = $this->siteHome();
        $preview = $home . (strpos($home, '?') === false ? '?' : '&') . 'td_preview=1&td_theme=' . urlencode($dir);
        $designUrl = url('theme_design/design');
        $designUrl .= (strpos($designUrl, '?') === false ? '?' : '&') . 'theme=' . urlencode($dir);

        return json(array(
            'code' => 1,
            'msg' => lang('admin/themedesign/new_created'),
            'applied' => $applied,
            'coverage' => $this->sanitizeCoverage($gen),
            'new_theme' => $dir,
            'new_created' => !empty($new['created']),
            'preview' => $preview,
            'design_url' => $designUrl,
        ));
    }

    // ───────────────────── AI 探测（截图 / 网址 → 主题元素） ─────────────────────

    /**
     * 探测主题元素：输入截图 / 网址 / 文本 → 调 AI → 返回「可编辑的检测结果」
     * （8 个配色令牌 + 字体 + 字号 + 主题名），**不创建主题**。供前端审阅/修改后再应用。
     * POST mode=text|image|url, spec(可选), image_data/image_type(截图), url(网址)。
     */
    public function detect()
    {
        if (!$this->request->isPost()) {
            return json(array('code' => 0, 'msg' => lang('illegal_request')));
        }
        // 每管理员限流（外部 AI 调用，防刷）
        $adminId = intval(isset($this->_admin['admin_id']) ? $this->_admin['admin_id'] : 0);
        if (!\app\common\util\VodAiCover::consumeGenerateRateLimit($adminId)) {
            return json(array('code' => 0, 'msg' => lang('admin/themedesign/ai_rate_limit')));
        }
        $mode = strtolower(trim((string)input('post.mode', 'text')));
        if (!in_array($mode, array('text', 'image', 'url'), true)) {
            $mode = 'text';
        }
        $spec = trim(mac_filter_xss((string)input('post.spec', '')));
        if (mb_strlen($spec) > 6000) {
            $spec = mb_substr($spec, 0, 6000);
        }

        $images = array();
        $evidence = '';
        $suggestName = '';

        if ($mode === 'image') {
            $itype = strtolower(trim((string)input('post.image_type', '')));
            $idata = (string)input('post.image_data', '');
            $idata = preg_replace('#^data:[^,]+,#', '', $idata);
            $idata = trim($idata);
            $allow = array('image/png', 'image/jpeg', 'image/webp', 'image/gif');
            // base64 合法 + 体积上限（约 6MB 解码后）
            if (!in_array($itype, $allow, true) || $idata === '' || base64_decode($idata, true) === false || strlen($idata) > 8 * 1024 * 1024) {
                return json(array('code' => 0, 'msg' => lang('admin/themedesign/ai_detect_bad_image')));
            }
            $images[] = array('media_type' => $itype, 'data' => $idata);
        } elseif ($mode === 'url') {
            $ev = $this->fetchUrlEvidence((string)input('post.url', ''));
            if (empty($ev['ok'])) {
                return json(array('code' => 0, 'msg' => isset($ev['error']) ? $ev['error'] : lang('admin/themedesign/ai_detect_failed')));
            }
            $evidence = (string)$ev['evidence'];
            $suggestName = isset($ev['title']) ? (string)$ev['title'] : '';
        }

        $user = '';
        if ($spec !== '') {
            $user .= "Additional user description:\n" . $spec . "\n\n";
        }
        if ($evidence !== '') {
            $user .= "Website design evidence (extracted from the page HTML/CSS):\n" . $evidence . "\n\n";
        }
        if ($mode === 'image') {
            $user .= "A screenshot of the target design is attached. Detect its color palette and typography from the image.\n";
        }
        if (trim($user) === '') {
            return json(array('code' => 0, 'msg' => lang('admin/themedesign/ai_detect_empty')));
        }

        @set_time_limit(200);
        $system = $this->buildDetectPromptSystem();
        $res = $this->themeAiChat($system, $user, $images);
        if (empty($res['ok'])) {
            return json(array('code' => 0, 'msg' => isset($res['error']) ? $res['error'] : lang('admin/themedesign/ai_detect_failed')));
        }
        $gen = $this->parseGenerated($res['text']);
        if (!is_array($gen)) {
            return json(array('code' => 0, 'msg' => lang('admin/themedesign/gen_bad_response')));
        }
        $detected = $this->sanitizeDetected($gen);
        if ($suggestName !== '' && $detected['theme_name'] === '') {
            $detected['theme_name'] = mb_substr($suggestName, 0, 60);
        }
        return json(array('code' => 1, 'detected' => $detected, 'mode' => $mode));
    }

    /**
     * 应用「已审阅的检测结果」→ **直接创建（或更新）一个新主题**（不再调 AI，
     * 所见即所得）。POST colors(JSON) / font_family / font_size / new_name / new_dir。
     */
    public function generate_apply()
    {
        if (!$this->request->isPost()) {
            return json(array('code' => 0, 'msg' => lang('illegal_request')));
        }
        $colors = json_decode((string)input('post.colors', ''), true);
        if (!is_array($colors)) {
            $colors = array();
        }
        $font = trim((string)input('post.font_family', ''));
        $size = (int)input('post.font_size/d', 16);

        // 复用 generate 的白名单校验：构造 gen 形状后走 sanitizeGeneratedIntoDraft
        $gen = array('design' => array('attributes' => array(
            'colors' => $colors,
            'font_family' => $font,
            'font_size' => $size,
        )));
        list($cfg, $applied) = $this->sanitizeGeneratedIntoDraft($gen);
        if ($applied['colors'] === 0 && $applied['font'] === 0 && $applied['font_size'] === 0) {
            return json(array('code' => 0, 'msg' => lang('admin/themedesign/gen_bad_response')));
        }

        $uiName = trim((string)input('post.new_name', ''));
        $uiDir  = strtolower(trim((string)input('post.new_dir', '')));
        $name = $uiName !== '' ? $uiName : 'AI Theme';
        $name = mb_substr($name, 0, 60);
        $dir = $uiDir !== '' ? $uiDir : $this->slugTheme($name);
        $dir = preg_replace('/[^a-z0-9_-]/', '', $dir);
        if ($dir === '' || !preg_match('/^[a-z]/', $dir)) {
            $dir = 'ai' . time();
        }
        $dir = substr($dir, 0, 32);

        $new = $this->createOrUpdateNewTheme($cfg, $dir, $name, 'Generated by Theme AI (detected from screenshot/URL)');
        if (empty($new['ok'])) {
            return json(array('code' => 0, 'msg' => isset($new['error']) ? $new['error'] : lang('admin/themedesign/new_copy_failed')));
        }
        $dir = $new['dir'];

        $home = $this->siteHome();
        $preview = $home . (strpos($home, '?') === false ? '?' : '&') . 'td_preview=1&td_theme=' . urlencode($dir);
        $designUrl = url('theme_design/design');
        $designUrl .= (strpos($designUrl, '?') === false ? '?' : '&') . 'theme=' . urlencode($dir);

        return json(array(
            'code' => 1,
            'msg' => lang('admin/themedesign/new_created'),
            'applied' => $applied,
            'new_theme' => $dir,
            'new_created' => !empty($new['created']),
            'preview' => $preview,
            'design_url' => $designUrl,
        ));
    }

    /** 探测用 system 提示：只输出配色令牌 + 字体 + 字号 + 主题名 + notes（无区块/导航/coverage）。 */
    private function buildDetectPromptSystem()
    {
        $fonts = array_keys($this->fontOptions());
        $sys = "You are a visual theme extractor for a MacCMS video/article/manga CMS front-end (theme 'prism').\n"
            . "From the provided input (a screenshot image, and/or colors/fonts extracted from a website, and/or a text description), detect the design's color palette and typography. Output ONE JSON object ONLY — no prose, no markdown fences.\n\n"
            . "Output schema:\n"
            . "{\n"
            . "  \"colors\": {\"accent\":\"#hex\",\"accent_ink\":\"#hex\",\"bg\":\"#hex\",\"surface\":\"#hex\",\"surface_2\":\"#hex\",\"text\":\"#hex\",\"text_muted\":\"#hex\",\"border\":\"#hex\"},\n"
            . "  \"font_family\": \"<one exact string from FONTS>\",\n"
            . "  \"font_size\": <integer 12..22>,\n"
            . "  \"is_dark\": <true|false>,\n"
            . "  \"theme_name\": \"<short human theme name fitting the look>\",\n"
            . "  \"notes\": [\"<short observation>\"]\n"
            . "}\n\n"
            . "Map the design's REAL colors onto these 8 semantic tokens:\n"
            . "- accent: primary brand / call-to-action color.\n"
            . "- accent_ink: text color placed ON accent (usually #ffffff or #111111 for contrast).\n"
            . "- bg: page background.\n"
            . "- surface: card / panel background.\n"
            . "- surface_2: secondary or subtle surface (slightly different from surface).\n"
            . "- text: primary body text color.\n"
            . "- text_muted: secondary / muted text color.\n"
            . "- border: hairline / divider color.\n\n"
            . "Rules:\n"
            . "- All colors MUST be 3- or 6-digit hex (#rgb or #rrggbb). Never output rgb() or color names.\n"
            . "- If the design is dark, set bg/surface/surface_2 to dark values and text/text_muted to light values, and is_dark=true. Keep readable contrast (text on bg, accent_ink on accent).\n"
            . "- font_family MUST be copied verbatim from FONTS — pick the closest match to the detected typography.\n"
            . "- font_size = detected body text size in px, integer 12..22 (use 16 if unsure).\n"
            . "- notes: 1 to 5 very short strings on what you detected (palette source, dark/light, font guess).\n\n"
            . "FONTS = " . json_encode($fonts, JSON_UNESCAPED_UNICODE) . "\n";
        return $sys;
    }

    /** 把 3/4/8 位十六进制颜色规整为 6 位小写（供前端 <input type=color> 使用）。 */
    private function normalizeHex($v)
    {
        $v = strtolower(trim((string)$v));
        if (!preg_match('/^#([0-9a-f]{3,8})$/', $v, $m)) {
            return $v;
        }
        $h = $m[1];
        $len = strlen($h);
        if ($len === 3 || $len === 4) {
            $h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
        } elseif ($len >= 6) {
            $h = substr($h, 0, 6);
        }
        return '#' . $h;
    }

    /** 校验/规整探测结果（白名单，永不信任模型直出）。 */
    private function sanitizeDetected($gen)
    {
        $design = is_array($gen) ? $gen : array();
        if (isset($design['design']) && is_array($design['design'])) {
            $design = $design['design'];
        }
        $isDark = isset($design['is_dark'])
            && ($design['is_dark'] === true || $design['is_dark'] === 1 || $design['is_dark'] === '1' || $design['is_dark'] === 'true');

        $presets = $this->schemePresets();
        $lightDef = $presets['indigo']['colors'];
        $darkDef = array(
            'accent' => '#6366f1', 'accent_ink' => '#ffffff', 'bg' => '#0f1115', 'surface' => '#171a21',
            'surface_2' => '#1f2330', 'text' => '#f3f4f6', 'text_muted' => '#9aa0ac', 'border' => '#2a2f3a',
        );
        $def = $isDark ? $darkDef : $lightDef;

        $genColors = isset($design['colors']) && is_array($design['colors']) ? $design['colors'] : array();
        $keys = array('accent', 'accent_ink', 'bg', 'surface', 'surface_2', 'text', 'text_muted', 'border');
        $colors = array();
        foreach ($keys as $k) {
            $v = isset($genColors[$k]) ? $genColors[$k] : '';
            $colors[$k] = $this->validHex($v) ? $this->normalizeHex($v) : $def[$k];
        }

        $fontOpts = array_keys($this->fontOptions());
        $font = isset($design['font_family']) ? trim((string)$design['font_family']) : '';
        if ($font === '' || !in_array($font, $fontOpts, true)) {
            $font = $fontOpts[0];
        }

        $size = isset($design['font_size']) ? intval($design['font_size']) : 16;
        if ($size < 12 || $size > 22) {
            $size = 16;
        }

        $name = isset($design['theme_name']) ? trim(strip_tags((string)$design['theme_name'])) : '';
        $name = mb_substr($name, 0, 60);

        $notes = array();
        if (isset($design['notes']) && is_array($design['notes'])) {
            foreach ($design['notes'] as $n) {
                $s = trim(strip_tags((string)$n));
                if ($s !== '') {
                    $notes[] = mb_substr($s, 0, 120);
                }
                if (count($notes) >= 5) {
                    break;
                }
            }
        }

        return array(
            'colors' => $colors,
            'font_family' => $font,
            'font_size' => $size,
            'is_dark' => $isDark ? 1 : 0,
            'theme_name' => $name,
            'notes' => $notes,
        );
    }

    /**
     * 判断主机是否为「公网可访问」——阻断 localhost / 私有 / 保留 / 回环 / 链路本地地址（SSRF 防护）。
     * 解析失败或落在内网范围一律拒绝（fail-closed）。
     */
    private function hostIsPublic($host)
    {
        $host = strtolower(trim((string)$host));
        if ($host === '' || $host === 'localhost' || substr($host, -6) === '.local') {
            return false;
        }
        $ip = $host;
        if (!filter_var($host, FILTER_VALIDATE_IP)) {
            $ip = @gethostbyname($host); // 仅解析 IPv4；失败时原样返回主机名
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                return false;
            }
        }
        // IPv4：排除私有段 + 保留段
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return (bool)filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }
        // IPv6：排除回环 / 链路本地 / 唯一本地
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $low = strtolower($ip);
            if ($low === '::1' || strpos($low, 'fe80') === 0 || strpos($low, 'fc') === 0 || strpos($low, 'fd') === 0) {
                return false;
            }
            return true;
        }
        return false;
    }

    /** 把 CSS 里的相对/根相对/协议相对链接解析为绝对 URL。 */
    private function absoluteUrl($href, $baseParts)
    {
        $href = trim((string)$href);
        if ($href === '' || strpos($href, 'data:') === 0) {
            return '';
        }
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }
        $scheme = isset($baseParts['scheme']) ? $baseParts['scheme'] : 'https';
        $host = isset($baseParts['host']) ? $baseParts['host'] : '';
        if ($host === '') {
            return '';
        }
        $port = isset($baseParts['port']) ? ':' . $baseParts['port'] : '';
        if (strpos($href, '//') === 0) {
            return $scheme . ':' . $href;
        }
        if (strpos($href, '/') === 0) {
            return $scheme . '://' . $host . $port . $href;
        }
        $path = isset($baseParts['path']) ? $baseParts['path'] : '/';
        $dir = preg_replace('#/[^/]*$#', '/', $path);
        if ($dir === '') {
            $dir = '/';
        }
        return $scheme . '://' . $host . $port . $dir . $href;
    }

    /**
     * 抓取网址并抽取「设计证据」：页面 HTML + 内联 <style> + 至多 3 个外链 CSS，
     * 从中提取高频颜色 / CSS 颜色变量 / font-family / <title> / meta theme-color。
     * 全程 SSRF 校验 + 不跟随重定向。@return array ['ok','evidence','title','error']
     */
    private function fetchUrlEvidence($url)
    {
        $url = trim((string)$url);
        if ($url === '') {
            return array('ok' => false, 'error' => lang('admin/themedesign/ai_detect_empty'));
        }
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url; // 裸域名默认 https
        }
        $parts = parse_url($url);
        $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : '';
        if (!$parts || empty($parts['host']) || !in_array($scheme, array('http', 'https'), true)) {
            return array('ok' => false, 'error' => lang('admin/themedesign/ai_detect_bad_url'));
        }
        if (!$this->hostIsPublic($parts['host'])) {
            return array('ok' => false, 'error' => lang('admin/themedesign/ai_detect_bad_url'));
        }

        $html = (string)\app\common\util\HttpClient::curlGetNoRedirect($url, 15, array('Accept: text/html,*/*'));
        if (trim($html) === '') {
            return array('ok' => false, 'error' => lang('admin/themedesign/ai_detect_fetch_failed'));
        }
        $html = substr($html, 0, 400000);

        $css = '';
        if (preg_match_all('#<style[^>]*>(.*?)</style>#is', $html, $sm)) {
            $css .= implode("\n", $sm[1]);
        }
        if (preg_match_all('#<link[^>]+rel=["\']?stylesheet["\']?[^>]*>#i', $html, $lm)) {
            $count = 0;
            foreach ($lm[0] as $tag) {
                if ($count >= 3) {
                    break;
                }
                if (!preg_match('#href=["\']([^"\']+)["\']#i', $tag, $hm)) {
                    continue;
                }
                $href = $this->absoluteUrl($hm[1], $parts);
                if ($href === '') {
                    continue;
                }
                $hp = parse_url($href);
                if (!$hp || empty($hp['host']) || !$this->hostIsPublic($hp['host'])) {
                    continue;
                }
                $cssResp = (string)\app\common\util\HttpClient::curlGetNoRedirect($href, 10);
                if (trim($cssResp) !== '') {
                    $css .= "\n" . substr($cssResp, 0, 200000);
                    $count++;
                }
            }
        }

        $title = '';
        if (preg_match('#<title[^>]*>(.*?)</title>#is', $html, $tm)) {
            $title = trim(html_entity_decode(strip_tags($tm[1]), ENT_QUOTES, 'UTF-8'));
            $title = mb_substr($title, 0, 60);
        }
        $themeColor = '';
        if (preg_match('#<meta[^>]+name=["\']theme-color["\'][^>]*content=["\']([^"\']+)["\']#i', $html, $cm)) {
            $themeColor = trim($cm[1]);
        }

        $evidence = $this->extractDesignEvidence($css . "\n" . $html, $themeColor);
        if (trim($evidence) === '') {
            return array('ok' => false, 'error' => lang('admin/themedesign/ai_detect_no_evidence'));
        }
        return array('ok' => true, 'evidence' => $evidence, 'title' => $title);
    }

    /** 从 CSS/HTML 文本抽取紧凑的颜色/字体证据（限长），交给 AI 归纳成 8 令牌调色板。 */
    private function extractDesignEvidence($text, $themeColor)
    {
        $text = (string)$text;

        $colors = array();
        if (preg_match_all('/#[0-9a-fA-F]{6}\b|#[0-9a-fA-F]{3}\b/', $text, $hm)) {
            foreach ($hm[0] as $c) {
                $c = strtolower($c);
                $colors[$c] = isset($colors[$c]) ? $colors[$c] + 1 : 1;
            }
        }
        if (preg_match_all('/rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(?:,\s*[\d.]+\s*)?\)/i', $text, $rm)) {
            foreach ($rm[0] as $c) {
                $c = strtolower(preg_replace('/\s+/', '', $c));
                $colors[$c] = isset($colors[$c]) ? $colors[$c] + 1 : 1;
            }
        }
        arsort($colors);
        $topColors = array_slice($colors, 0, 24, true);

        $vars = array();
        if (preg_match_all('/(--[a-z0-9_-]+)\s*:\s*(#[0-9a-fA-F]{3,8}|rgba?\([^)]+\))/i', $text, $vm, PREG_SET_ORDER)) {
            foreach ($vm as $v) {
                $vars[$v[1]] = trim($v[2]);
                if (count($vars) >= 24) {
                    break;
                }
            }
        }

        $fonts = array();
        if (preg_match_all('/font-family\s*:\s*([^;{}]+)[;}]/i', $text, $fm)) {
            foreach ($fm[1] as $f) {
                $f = trim(preg_replace('/\s+/', ' ', $f));
                if ($f !== '' && !isset($fonts[$f])) {
                    $fonts[$f] = true;
                }
                if (count($fonts) >= 10) {
                    break;
                }
            }
        }

        $lines = array();
        if ($themeColor !== '') {
            $lines[] = 'meta theme-color: ' . $themeColor;
        }
        if (!empty($topColors)) {
            $parts = array();
            foreach ($topColors as $c => $n) {
                $parts[] = $c . ' (x' . $n . ')';
            }
            $lines[] = 'Frequent colors: ' . implode(', ', $parts);
        }
        if (!empty($vars)) {
            $parts = array();
            foreach ($vars as $k => $v) {
                $parts[] = $k . '=' . $v;
            }
            $lines[] = 'CSS color variables: ' . implode('; ', $parts);
        }
        if (!empty($fonts)) {
            $lines[] = 'font-family declarations: ' . implode(' | ', array_keys($fonts));
        }
        return mb_substr(implode("\n", $lines), 0, 4000);
    }

    // ───────────────────── AI 生成图片（logo / 头像） ─────────────────────

    /**
     * AI 生成 logo / 头像：复用 主题AI 的 api_base + api_key，调 OpenAI 兼容
     * `/images/generations`（图像模型默认 gpt-image-1，可由 theme_ai.image_model 覆盖）。
     * 支持用户附加提示词。生成图落地到 upload/theme/，返回值与 upload 契约一致：{code,msg,file}。
     * POST kind=logo|avatar, message=<可选附加提示词>。
     */
    public function gen_image()
    {
        if (!$this->request->isPost()) {
            return json(array('code' => 0, 'msg' => lang('illegal_request')));
        }
        $kind = strtolower(trim((string)input('post.kind', '')));
        if (!in_array($kind, array('logo', 'avatar'), true)) {
            return json(array('code' => 0, 'msg' => lang('param_err')));
        }
        $message = (string)input('post.message', '');

        // 每管理员限流（复用图片生成限流：5/分、50/时）
        $adminId = intval(isset($this->_admin['admin_id']) ? $this->_admin['admin_id'] : 0);
        if (!\app\common\util\VodAiCover::consumeGenerateRateLimit($adminId)) {
            return json(array('code' => 0, 'msg' => lang('admin/themedesign/img_ai_rate_limit')));
        }

        // 图像凭据独立于「主题AI」文本凭据解析：文本可以是 Claude（不支持绘图），
        // 图像必须走支持图像模型的 OpenAI 兼容端点（ai_cover 专用配置，或 theme_ai 的 image_* 覆盖）。
        $ic = $this->resolveImageAiConfig();
        if (empty($ic['ok'])) {
            return json(array('code' => 0, 'msg' => isset($ic['error']) ? $ic['error'] : lang('admin/themedesign/gen_no_key')));
        }
        $key = $ic['key'];
        $base = $ic['base'];
        $model = $ic['model'];
        $timeout = $ic['timeout'];

        $post = array(
            'model' => $model,
            'prompt' => $this->buildImagePrompt($kind, $message),
            'n' => 1,
            'size' => '1024x1024',
        );
        $ml = strtolower($model);
        if (strpos($ml, 'gpt-image') !== false) {
            $post['quality'] = 'medium';
            if ($kind === 'logo') {
                $post['background'] = 'transparent'; // gpt-image 支持透明底，logo 更实用
            }
        } elseif (strpos($ml, 'dall-e-3') !== false) {
            $post['quality'] = 'standard';
        }

        @set_time_limit(320);
        $headers = array('Content-Type: application/json', 'Authorization: Bearer ' . $key);
        $resp = \app\common\util\HttpClient::curlPostWithTimeout(
            $base . '/images/generations',
            json_encode($post, JSON_UNESCAPED_UNICODE),
            $headers,
            $timeout
        );
        $json = json_decode((string)$resp, true);
        if (!is_array($json)) {
            return json(array('code' => 0, 'msg' => lang('admin/themedesign/gen_bad_response')));
        }
        if (!empty($json['error']['message'])) {
            return json(array('code' => 0, 'msg' => mb_substr((string)$json['error']['message'], 0, 240)));
        }

        $raw = null;
        if (!empty($json['data'][0]['b64_json'])) {
            $raw = base64_decode((string)$json['data'][0]['b64_json'], true);
            if ($raw === false) {
                $raw = null;
            }
        } elseif (!empty($json['data'][0]['url'])) {
            $raw = $this->downloadImageSafe((string)$json['data'][0]['url'], min(120, $timeout));
        }
        if ($raw === null || $raw === '') {
            return json(array('code' => 0, 'msg' => lang('admin/themedesign/img_ai_failed')));
        }

        $rel = $this->saveThemeImage($raw, $kind, 'png');
        if ($rel === '') {
            return json(array('code' => 0, 'msg' => lang('admin/themedesign/img_ai_failed')));
        }
        $file = (defined('MAC_PATH') ? MAC_PATH : '/') . $rel;
        return json(array('code' => 1, 'msg' => lang('admin/themedesign/img_ai_done'), 'file' => $file));
    }

    /**
     * 解析「图像生成」凭据（独立于文本凭据）。优先级：
     *   1) ai_cover 专用图像配置（AI 封面同款，OpenAI Images，后台可视化可配）；
     *   2) theme_ai 的 image_api_base / image_api_key / image_model 覆盖（可指向独立图像端点）；
     *      未设置则回退 theme_ai 的文本端点——仅当该代理确实提供图像模型时才可用。
     * @return array{ok:bool,key?:string,base?:string,model?:string,timeout?:int,error?:string}
     */
    private function resolveImageAiConfig()
    {
        $config = config('maccms');

        $cover = isset($config['ai_cover']) && is_array($config['ai_cover']) ? $config['ai_cover'] : array();
        if ((string)(isset($cover['enabled']) ? $cover['enabled'] : '0') === '1'
            && trim((string)(isset($cover['api_key']) ? $cover['api_key'] : '')) !== '') {
            $base = trim((string)(isset($cover['api_base']) ? $cover['api_base'] : ''));
            if ($base === '') {
                $base = 'https://api.openai.com/v1';
            }
            $model = trim((string)(isset($cover['model']) ? $cover['model'] : ''));
            return array(
                'ok' => true,
                'key' => trim((string)$cover['api_key']),
                'base' => rtrim($base, '/'),
                'model' => $model !== '' ? $model : 'gpt-image-1',
                'timeout' => max(30, min(300, intval(isset($cover['timeout']) ? $cover['timeout'] : 120))),
            );
        }

        $ta = isset($config['theme_ai']) && is_array($config['theme_ai']) ? $config['theme_ai'] : array();
        if ((string)(isset($ta['enabled']) ? $ta['enabled'] : '0') !== '1') {
            return array('ok' => false, 'error' => lang('admin/themedesign/gen_disabled'));
        }
        $base = trim((string)(isset($ta['image_api_base']) && $ta['image_api_base'] !== '' ? $ta['image_api_base'] : (isset($ta['api_base']) ? $ta['api_base'] : '')));
        $key = trim((string)(isset($ta['image_api_key']) && $ta['image_api_key'] !== '' ? $ta['image_api_key'] : (isset($ta['api_key']) ? $ta['api_key'] : '')));
        if ($base === '' || $key === '') {
            return array('ok' => false, 'error' => lang('admin/themedesign/gen_no_key'));
        }
        $model = trim((string)(isset($ta['image_model']) ? $ta['image_model'] : ''));
        return array(
            'ok' => true,
            'key' => $key,
            'base' => rtrim($base, '/'),
            'model' => $model !== '' ? $model : 'gpt-image-1',
            'timeout' => max(30, min(300, intval(isset($ta['timeout']) ? $ta['timeout'] : 120))),
        );
    }

    /** 构建 logo / 头像的绘图提示：站点名 + 当前草稿主色 + 用户附加提示。 */
    private function buildImagePrompt($kind, $message)
    {
        $site = isset($GLOBALS['config']['site']) ? $GLOBALS['config']['site'] : array();
        $siteName = isset($site['site_name']) ? trim(strip_tags((string)$site['site_name'])) : '';
        $draft = $this->currentDraft();
        $accent = '';
        if (isset($draft['attributes']['colors']['accent']) && $this->validHex($draft['attributes']['colors']['accent'])) {
            $accent = (string)$draft['attributes']['colors']['accent'];
        }
        $extra = mb_substr(trim(mac_filter_xss((string)$message)), 0, 600);

        if ($kind === 'logo') {
            $p = 'Design a clean, modern website logo';
            if ($siteName !== '') {
                $p .= ' for a site named "' . mb_substr($siteName, 0, 60) . '"';
            }
            $p .= '. Flat vector style, simple and memorable, centered composition, transparent background, high contrast, no photorealism, no stock-photo look, no watermark, no border.';
            if ($accent !== '') {
                $p .= ' Use ' . $accent . ' as the primary brand color.';
            }
        } else {
            $p = 'Design a friendly, generic default user avatar (profile picture). Flat illustrative style, simple, single centered subject, works well as a small circular avatar, subtle solid background, no text, no watermark.';
            if ($accent !== '') {
                $p .= ' Accent color ' . $accent . '.';
            }
        }
        if ($extra !== '') {
            $p .= "\n\nAdditional requirements: " . $extra;
        }
        return mb_substr($p, 0, 2000);
    }

    /** 安全下载图片（仅 https + 公网主机；不跟随重定向）。@return string|null 二进制内容 */
    private function downloadImageSafe($url, $timeout)
    {
        $url = trim((string)$url);
        if ($url === '' || strncasecmp($url, 'https://', 8) !== 0) {
            return null;
        }
        $parts = parse_url($url);
        if (!$parts || empty($parts['host']) || !$this->hostIsPublic($parts['host'])) {
            return null;
        }
        $bin = \app\common\util\HttpClient::curlGetNoRedirect($url, $timeout);
        return ($bin === false || $bin === '') ? null : (string)$bin;
    }

    /** 保存生成图到 upload/theme/<日期>/，返回站点相对路径（无前导斜杠）；失败返回 ''。 */
    private function saveThemeImage($raw, $kind, $ext)
    {
        $ext = preg_replace('/[^a-z0-9]/i', '', (string)$ext);
        if ($ext === '') {
            $ext = 'png';
        }
        $sub = 'upload/theme/' . date('Ymd') . '/';
        if (!$this->ensureDir(ROOT_PATH . $sub)) {
            return '';
        }
        $rel = $sub . md5(microtime(true) . '_' . $kind . '_' . mt_rand()) . '.' . strtolower($ext);
        if (@file_put_contents(ROOT_PATH . $rel, $raw) === false) {
            return '';
        }
        return $rel;
    }
}
