# MacCMS Twig 主题规范 v2（工程契约）

> 适用范围：`template/<主题名>/info.ini` 里 `engine = twig` 的前台主题。
> 旧版 `docs/theme-contract-v1.md` 保留为历史记录，描述的是「所有主题都直接用
> `template/_shared`」的世界；v2 加入了**主题继承**与**两级严格度**，是当前的准绳。
>
> 这份文档是给**工程侧**看的：写模板的人、做 AI 产线的人、Review 主题的人。
> 设计侧（怎么定 token、怎么写设计规范）见 `docs/theme-design-guide.md`。

---

## 0. 这份契约要解决什么

主题跑起来不难，难的是**跑起来但功能静默失效**：页面一切正常，只有点收藏、
提交评论、切语言、播视频的时候才发现坏了。这类问题最难排查，也最容易在
「换了个主题」之后才被用户发现。

所以契约管三件事：

| 管什么 | 不管的话会怎样 |
|--------|----------------|
| **必要模板齐不齐** | 用户点到某个页面就是 500 |
| **行为钩子在不在** | 页面正常，功能静默失效 |
| **转义有没有做对** | XSS |

其余（长什么样、用什么 class、几栏布局）契约一概不管——那正是主题该自由的地方。

---

## 1. 主题是怎么被识别的

一套主题就是 `template/<目录名>/` 下的一个目录，站长在后台或 `domain.php` 里
按目录名选用。目录内：

```
template/<主题名>/
├── info.ini              ← 主题声明（唯一必需文件）
├── html/                 ← 模板，.twig
├── asset/                ← CSS / JS / 图片 / 语言包
└── design-system/        ← 设计规范（可选，见 theme-design-guide.md）
```

### `info.ini`

```ini
name    = Pornhub Dark      ; 后台显示名
lastdate= 2026-08-12
version = V1.0
engine  = twig              ; 缺省或写 think = 走老引擎；写 twig 才走 Twig
parent  = starter           ; 可选：继承哪套主题
```

- **`engine`**：`twig` 才启用 Twig。这个开关刻意放在主题自己的 `info.ini`，
  而不是 `application/extra/maccms.php` —— 后者是站长的运行期数据，在线升级不覆盖，
  把引擎开关塞进去会让「装了新主题但引擎没切」变成一个说不清的状态。
- **`parent`**：见第 3 节。值只能是 `template/` 下的一个目录名，
  含 `.` `/` `\` 一律忽略；指向自己或指向不存在的目录同样忽略（安全回落成无继承）。

解析入口：`app\common\behavior\Init::themeEngine()` / `::themeParent()`。

---

## 2. 模板分级：T0 / T1 / T2

旧契约只要求 3 个模板（`index/index`、`vod/type`、`vod/detail`），
而控制器实际会 fetch 到约 90 个 —— 过了闸的主题照样一点就 500。v2 按「用户走不走得到」分三级。

清单的唯一真实来源是 `application/index/controller/*.php` 里真的会 `fetch()` 到的模板名，
不是凭印象列的。校验实现在 `app\common\view\ThemeContract`。

### T0 核心（56 个）—— 两种模式都是 error

用户正常浏览必经，缺了就是 500。

| 模块 | 模板 |
|------|------|
| 首页 | `index/index` |
| 影片 | `vod/type` `vod/show` `vod/detail` `vod/play` `vod/down` `vod/search` |
| 资讯 | `art/index` `art/type` `art/show` `art/detail` `art/read` |
| 明星 | `actor/index` `actor/type` `actor/show` `actor/detail` |
| 漫画 | `manga/index` `manga/type` `manga/show` `manga/detail` `manga/play` |
| 专题 | `topic/index` `topic/detail` |
| 角色 | `role/index` `role/show` `role/detail` |
| 网址 | `website/index` `website/type` `website/show` `website/detail` |
| 剧情 | `plot/index` `plot/detail` |
| 直播 | `live/show` `live/play` |
| 其它 | `map/index` `label/rank` `comment/index` `gbook/index` |
| 会员 | `user/index` `user/login` `user/reg` `user/info` `user/findpass` `user/findpass_msg` `user/favs` `user/plays` `user/plog` |
| 状态 | `public/jump` `public/msg` `public/404` `public/403` `public/500` `public/notempty` `public/close` `public/browser` `public/offline` |

### T1 条件必要（42 个）—— strict = error，lenient = warning

站长开了对应功能才会走到，但多数功能**默认就是开的**。

| 模块 | 模板 | 什么时候会走到 |
|------|------|----------------|
| 验证码 | `public/verify` | 后台开「列表页/搜索页验证码」——**开了就整个列表页与搜索页 500** |
| 异步片段 | `comment/ajax` `gbook/ajax` `gbook/report` | 评论/留言异步加载 |
| 影片 | `vod/copyright` `vod/detail_pwd` `vod/player` `vod/player_pwd` `vod/plot` `vod/last_vod` `vod/search_hub` | 版权关闭、访问密码、独立播放器页、分集剧情、最近更新、移动搜索落地 |
| 网址 | `website/search` `website/rss` | 网址搜索、单站 RSS |
| RSS | `rss/index` `rss/baidu` `rss/google` `rss/so` `rss/sogou` `rss/bing` `rss/sm` | 搜索引擎抓取 |
| AI | `index/ai_chat` | 开了 AI 助手 |
| 会员 | `user/buy` `user/pay` `user/payment_weixin` `user/upgrade` `user/benefits` `user/checkin` `user/popedom` `user/notify` `user/dynamics` `user/downs` `user/mall` `user/mall_orders` `user/cash` `user/reward` `user/orders` `user/cards` `user/invite` `user/bind` `user/ajax_login` `user/ajax_info` `user/ajax_upgrade` | 充值、支付、升级、权益、签到、权限、消息、动态、下载记录、积分商城、提现、推广、订单、卡密、邀请、绑定、弹窗片段 |

### T2 不列入契约

`vod/index`、`search/index`、各 `*/ajax_*`、`vod/{role,downer,downer_pwd,rss}`、
`art/search`、`actor/confirm`、`website/confirm`、
`user/{unbind,portrait,ulog,order_info,comment,gbook}` 等。

**这些是既有产品缺口，不是 Twig 造成的**——`template/default` 自己也没有，
走到一样 500。本轮只记录，另开 issue。

---

## 3. 主题继承

### 为什么要有

「复制底座」之所以会发生，是因为没有比复制更省事的办法：一套完整主题约 100 个模板，
不复制就得从零写 100 个。所以规范的核心不是禁止复制，是**让不复制更划算**。

| | 复制底座 | 继承 |
|---|---|---|
| 新主题文件数 | 126 个 twig（phub 实测） | 只放要改的 |
| AI 一次要产出 | 90+ 个文件（超过产线 `MAX_FILES=60`） | 10–20 个 |
| 底座修 bug | 逐套主题同步 | 改一次全部生效 |
| 设计师定制 | 得看懂 126 个文件 | 覆写 `layout/base.twig` + CSS |

### 怎么用

```ini
; template/myskin/info.ini
name   = My Skin
engine = twig
parent = starter
```

模板查找顺序：

```
myskin/html/   →   starter/html/   →   @shared（命名空间）
```

Twig 的 `FilesystemLoader` 按 path 顺序找，所以**同名文件子主题优先，缺的自动回落**。
只支持一层 parent（parent 的 parent 不再递归），够用且不会绕成环。

### 资产不会自动回落

CSS/JS/图片是 HTTP 路径，由 web server 直接服务，loader 管不到。用 `mac_theme_asset()`：

```twig
<link rel="stylesheet" href="{{ mac_theme_asset('css/starter.css') }}">
```

按 **子主题 asset → 父主题 asset → `template/_shared/`** 依次找，
三处都没有时返回子主题路径（让它以 404 现形，而不是静默空字符串）。
路径里含 `..` 一律返回空字符串。

### 最小可用皮肤

一套皮肤主题只需要 2 个文件：

```
template/myskin/
├── info.ini                     ; parent = starter
├── asset/css/starter.css        ; 覆盖 --st-* token
└── html/layout/base.twig        ; 换页首页尾（可选，不换就连这个都不用写）
```

`tests/regression/twig_theme_inherit_unit.php` 第 7 节就是拿这个形态过 strict 闸的。

---

## 4. 共用层 `template/_shared/`

主题**不应该**自带下面这三样的副本。它们承载的是与后端的契约（端点、字段名、DOM 钩子），
复制一份就等于把契约冻结在复制那一刻，之后底座修的 bug 你都收不到。

| 文件 | 是什么 |
|------|--------|
| `_shared/twig/components.twig` | 行为元件库（26 个 macro） |
| `_shared/js/mac-theme-runtime.js` | 行为 runtime（收藏/评论/登录/懒载/多语/确认框…） |
| `_shared/language/strings_{zh,en}.properties` | 前端语言字典 |

用法：

```twig
{% import "@shared/components.twig" as c %}
```

主题专用的语言 key（`phub_hot` 之类）放主题自己的 `asset/language/`，
用 `c.runtime(base, lang_path)` 的第二个参数指过去，不要改共用字典。

> **lenient 模式的例外**：`twigspike` / `phub` 目前各自带了一份副本。
> 它们能继续装、继续跑（只出 warning），但新主题在 strict 下会被拒。
> 迁移这两套是另一轮的事，需要和前端同事对齐。

---

## 5. 变量契约

### 5.1 全站共通

每个页面都有，由 `All::label_maccms()` / `label_user()` 注入。

| 变量 | 内容 |
|------|------|
| `maccms` | 站点配置 + 运行期信息，见下表 |
| `tplconfig` | 模板云/主题市场配置（`$GLOBALS['mctheme']`） |
| `user` | 当前登录者（未登录时各字段为空）；`user.user_id` 是唯一可靠的登录判据 |
| `csrf_token` | 表单令牌，所有 POST 表单必须带 `<input name="__token__">` |
| `mac_vod_playlink` | 是否输出播放直链（1/0） |

`maccms` 的子键（来自 `$GLOBALS['config']['site']` 加运行期补充）：

```
site_name site_url site_wapurl site_keywords site_description site_icp site_qq
site_email install_dir site_logo site_waplogo site_banner site_app_launch_image
template_dir html_dir site_polyfill mob_status mob_template_dir mob_html_dir
site_tj site_status site_close_tip mainland_ip_limit new_version ads_dir mob_ads_dir
path path_tpl path_ads user_status date search_hot http_type http_url seo
art_extend_class vod_extend_class vod_extend_state vod_extend_version
vod_extend_area vod_extend_lang vod_extend_year vod_extend_weekday
actor_extend_area controller_action mid aid
```

最常用的三个：
- `maccms.path` —— 站点根路径，**所有绝对 URL 都从它起头**，写死 `/` 会在子目录安装下全站崩
- `maccms.path_tpl` —— 当前主题的资产根（不过优先用 `mac_theme_asset()`，它认继承）
- `maccms.site_name` / `site_keywords` / `site_description` —— `<title>` 与 meta

### 5.2 各页面专有

| 页面 | 控制器给的变量 |
|------|----------------|
| `vod/type` `vod/show`（及 art/actor/manga/website 的同名页） | `obj`（分类）、`param` |
| `vod/detail` | `obj`（影片，含 `type` `vod_play_list` `vod_down_list` `vod_plot_list`）、`param`、`comment_mid=1`、`comment_rid`、`seo_ai` |
| `vod/play` `vod/down` | 上面全部，加 `player_data`、`player_js`、`popedom`、`vod_play_is_fav`、`vod_play_fav_ulog_id`、`obj.player_info`（含 `link_pre` / `link_next`） |
| `art/detail` `art/read` | `obj`、`__PAGING__`（read 的分页就是章节）、`comment_mid=2` |
| `actor/detail` | `obj`、`popedom`、`comment_mid=8` |
| `topic/detail` | `obj`、`comment_mid=3` |
| `role/detail` | `obj`、`comment_mid=9` |
| `website/detail` | `obj`、`popedom`、`comment_mid=11` |
| `manga/detail` `manga/play` | `obj`（含 `manga_play_list`、`manga_chapter_url`）、`popedom`、`comment_mid=12` |
| `topic/index` | `param`、`__PAGING__` |
| `live/show` | `param`；`live/play` 另有 `live_id`（频道数据要自己 `mac_live_info()` 取） |
| `label/rank` | `label_rank_types`、`label_rank_config`（`vod_num` / `rank_title_suffix`） |
| `comment/index` `comment/ajax` | `param`、`comment`（后台评论设置） |
| `gbook/*` | `param`、`gbook`（后台留言设置，`gbook.login` = 是否要求登录） |
| `public/jump` `public/msg` | `msg`、`url`、`wait` |
| `public/close` | `close_tip` |
| `public/verify` | `type`（`show` 或 `search`） |
| `user/*` | `obj` = 当前登录者（**不是 `user`**，虽然同值，但 `obj` 才是这个模块的约定）；列表页另有 `list` + `__PAGING__` + `param` |
| `user/reg` | `user_config`；`user/bind` 有 `ac` / `bind_readonly`；`user/buy` `user/pay` 有 `config` / `ext_list` / `info`；`user/upgrade` 有 `group_list` / `pay_config`；`user/popedom` 有 `type_tree`；`user/invite` 有 `invite_code` / `invite_link_uid` / `invite_link_code` / `total` |

### 5.3 分页的三个来源

| 来源 | 用法 |
|------|------|
| 数据函数返回值 | `{% set res = mac_vod({num: 20, paging: true}) %}{% set pg = mac_paging(res) %}` |
| 控制器直出 `__PAGING__` | `{{ c.paging_raw(__PAGING__\|default({}), opts) }}`（会员中心、`topic/index`、`art/read`） |
| 不分页 | 不传 `paging: true` 即可 |

`c.paging()` 与 `c.paging_raw()` 完全等价，两个名字只为了在模板里一眼看出数据从哪来。

### 5.4 播放器（最容易踩的一处）

```twig
{{ c.player(player_data|default(''), player_js|default(''), {class: 'st-player'}) }}
```

`player_data` 是 `<script>var player_aaaa={…}</script>`，`player_js` 是
`playerconfig.js` + `player.js` 两个 `<script>`。`static/js/player.js` 用
`document.write` **就地**产生播放器，所以这两段必须**原样、依序、放在希望播放器出现的容器里**——
不能搬到 `</body>` 前，不能拆开，不能加 `defer`。

另外 `player.js` 在浏览器端有两个硬依赖，`<head>` 里必须先有：

```twig
{{ c.jsvar(maccms, csrf_token|default(''), user.user_id|default(0)) }}
```

它输出全局 `maccms` 对象（`player.js` 的 `Init()` 读 `maccms.path`）和 `$`
（`Play()` 用 `$('.MacPlayer')`）。**缺任一个，播放器连 DOM 都不会出现**，
而页面看起来完全正常——这是 QA 报「视频不能播放」的实际根因。

---

## 6. 函数白名单

模板里能调的函数**只有下面这 64 个**，其余一律被 `ThemeGate` 的 calls 阶段判为
「调用了未注册的函式」。注册在 `app\common\view\TwigMaccms`。

**本项目没有注册任何自定义 filter。** 需要格式化就用函数，不要写 `|xxx`。

### 6.1 数据函数（18 个）

参数是一个 map，键名与老 taglib 的属性同名（`num` `type` `by` `order` `paging` `wd` …）。
返回 `{code, msg, total, list, …}`。

```
mac_vod  mac_art  mac_manga  mac_type  mac_actor  mac_role  mac_topic
mac_website  mac_comment  mac_gbook  mac_link
mac_area  mac_lang  mac_year  mac_class  mac_version  mac_state  mac_letter
```

### 6.2 URL 生成（21 个）

```
mac_url  mac_url_page  mac_url_img  mac_url_type  mac_url_create  mac_url_content_img
mac_url_vod_detail  mac_url_vod_play  mac_url_vod_down  mac_url_vod_search
mac_url_art_detail  mac_url_manga_detail  mac_url_manga_play
mac_url_actor_detail  mac_url_topic_detail  mac_url_plot_detail  mac_url_role_detail
mac_url_website_detail  mac_url_website_index
url  lang
```

### 6.3 其它辅助（25 个）

```
mac_paging  mac_substring  mac_default  mac_friend_date  mac_escape  mac_param_url
mac_day  mac_filter_html  mac_em_replace  mac_get_user_portrait
mac_tpl_vod_type_cover  mac_tpl_art_cover  mac_tpl_manga_cover
mac_theme_index_hotvod_tabs  mac_td_section  mac_user_fav_map  mac_user_fav_id
mac_ad_slot  mac_top_types  mac_seo_meta  mac_theme_asset
mac_live_list  mac_live_cates  mac_live_info  mac_verify_on
```

### 6.4 标记为 `is_safe=['html']` 的 4 个

这四个的返回值**不再经过 autoescape**，因为它们本身就产出 HTML：

```
mac_filter_html   mac_url_create   mac_em_replace   mac_day
```

用它们时不要再加 `|raw`（加了不会错，但会让人误以为那里需要 raw）。

---

## 7. 转义规则

Twig 默认对 `{{ }}` 做 HTML 转义。**只有下面两种情况可以 `|raw`。**

### 7.1 白名单字段直接用

站长／采集入库的富文本，以及控制器组好的 HTML 片段：

```
vod_content  art_content  manga_content  actor_content  role_content
topic_content  website_content  live_content  manga_chapter_url
player_data  player_js  head.text  foot.text  .code
```

```twig
<div class="st-prose">{{ obj.vod_content|raw }}</div>
```

**用户投稿内容（`comment_content`、`gbook_content`）不在名单内，必须保持转义。**

### 7.2 元件的 HTML 插槽：`@raw-slot` 标注

元件化之后，白名单字段会在 `{% include … with %}` 边界上被改名
（`{% include "components/x.twig" with {content: obj.actor_content} %}`），
基于名字的白名单结构上追不到。所以元件可以在文件头声明自己的 HTML 插槽：

```twig
{# 一个正文区块元件
   @raw-slot: content, foot_html #}
<div class="prose">{{ content|raw }}</div>
```

声明之后，**该文件内**这些变量的 `|raw` 放行。

同时做**调用端反查**：扫描 `{% include "该元件" with {...} %}` 的实参，
如果传进插槽的不是白名单字段（例如传了 `comment.comment_content`），照样报 error。
既容许正当的别名，又不让 slot 变成 XSS 后门。

### 7.3 禁用构造

| 构造 | 为什么 | 什么级别 |
|------|--------|----------|
| `{% use %}` | Twig 2.x 模板名 PHP 代码注入（CVE-2026-46633） | **加载即拒**（页面直接报错） |
| `\|sort` | 2.12.5 允许非闭包 callable，可绕过函数白名单（CVE-2022-23614） | **加载即拒** |
| `{% sandbox %}` | 2.12.5 的 sandbox 自带多条 bypass advisory，本项目不依赖它 | **加载即拒** |
| `\|json_encode\|raw` | PHP 的 `json_encode` 默认不转义 `/`，字符串里的 `</script>` 会逃逸出脚本块 | **契约 error**（安装/上架/AI 产线拒收），已装的主题照常渲染 |
| `template_from_string()` `source()` `dump()` | 未注册，自然调不到 | calls 阶段 error |

> 为什么 `|json_encode|raw` 只 lint 不拦渲染：`TwigGuard::scan()` 挂在 loader 上，
> 拦下去就是**整页空白（HTTP 200 + 空 body）**。站长升级核心之后原本能用的页面
> 突然变白页，而且完全看不出原因——用一次核心升级换掉站长的会员区，
> 代价远大于这条规则要防的风险。正确写法：
> `<script>var x = "{{ value|e('js') }}";</script>`，或者用 `c.jsvar`。

---

## 8. 行为契约

页面能不能「用」，取决于这些钩子在不在。它们由 `template/_shared/assets/js/mac-theme-runtime.js`
接管，主题可以自己写，也可以用 `@shared/components.twig` 的 macro（推荐）。

### 8.1 必要钩子（缺了当 error）

| 钩子 / 等价 macro | 缺了会怎样 |
|-------------------|------------|
| `data-lang` / `c.lang_switch()` / `c.fav_button()` / `c.paging()` | 前端多语完全不生效 |
| `data-original` / `c.lazy_img()` / `c.vod_card()` | 图片懒加载不生效 |
| `mac-theme-runtime.js` / `c.runtime()` | 收藏／评论／登录／懒载／多语全部失效 |
| `window.maccms = {…}` / `c.jsvar()` | 播放页播放器不启动（视频无法播放） |

有 `parent` 时，父主题提供的钩子算数——换皮主题常常只覆写 `layout/base.twig`，
懒加载、收藏这些钩子全在父主题的页面里。

### 8.2 建议钩子（缺了只是少一块功能）

| 钩子 / 等价 macro | 对应功能 |
|-------------------|----------|
| `mac_ulog` / `c.fav_button()` | 收藏按钮 |
| `digg_link` / `c.digg()` | 顶／踩 |
| `data-mac-comment-form` / `c.comment_form()` | 评论提交 |
| `data-mac-login-form` / `c.login_form()` | 登录表单 |
| `data-mac-lang` / `c.lang_switch()` | 中英切换 |

### 8.3 runtime 会写入的 class

这些由 JS 写入，主题可以拿来设样式，**但不要当结构钩子改名**：

```
is_fav  is-on  is-active  is-open  lazyloaded  page-auth  has-mac-confirm
```

### 8.4 `MacTheme` 事件出口

主题想接自己的逻辑（弹窗、埋点、动画）用这些，不要去改 runtime：

```js
MacTheme.on('login:required', d => {…})   // 需要登录（弹自己的登录框）
MacTheme.on('login:ok',       d => {…})
MacTheme.on('reg:ok',         d => {…})
MacTheme.on('findpass:ok',    d => {…})
MacTheme.on('ulog:changed',   d => {…})   // 收藏增删
MacTheme.on('digg:changed',   d => {…})
MacTheme.on('comment:saved',  d => {…})
```

另有 `MacTheme.refresh(root)`：内容以 AJAX 换入后调它，重新挂懒加载与多语。

### 8.5 表单字段名即契约

改名等于坏掉。用 `@shared` 的表单 macro 就不会踩到：

| 表单 | 端点 | 字段 |
|------|------|------|
| `c.login_form` | `user/login` | `user_name` `user_pwd` `verify` `__token__` |
| `c.reg_form` | `user/reg` | `user_name` `user_pwd` `user_pwd2` `verify` `__token__` |
| `c.comment_form` | `comment/saveData` | `mid` `rid` `comment_content` `verify` `__token__` |
| `c.gbook_form` | `gbook/saveData` | `gbook_content` `verify` `__token__`（没有 `gbook_name`，后端按登录态自己填） |
| `c.pwd_form` | `ajax/pwd` | `pwd` + `data-mid` / `data-id` / `data-type` |
| `c.search_form` | `vod/search`（GET） | `wd` |
| `c.captcha` | — | `verify` + `verify/index` 图片；**由元件自己查后台开关**，主题不必知道 |

> 验证码那条特别值得说：站长在后台打开验证码、主题却没渲染字段的话，
> 提交一律回「验证码错误」，而且页面看起来完全正常——典型的静默失效。
> 所以这件事收进 `c.captcha`，主题根本不该知道有这回事。

---

## 9. 资产约定

- **禁止外部 CDN**（字体、图标、JS 一律不许）。国内是 render-blocking，
  离线页（`public/offline`，Service Worker 的兜底页）更是直接白屏。
  资源放主题的 `asset/`，用 `mac_theme_asset()` 引用。
- 编译缓存（`runtime/twig/`）**不得可被 Web 直取**。Apache 靠 `.htaccess`，
  nginx 需要站点 deny 规则；`twig_theme_e2e.php` 第 3 节会实际请求一次来验。
- 状态页（`public/500` `public/close` `public/offline` `public/browser`
  `public/notempty` `public/jump` `public/msg` `public/verify`）**不要查库**。
  走到那几页最可能的成因正是数据库或程序出事，在那里查库等于错误页自己再挂一次。
  `public/404` 是唯一例外（路由对了、内容不在，库是好的，给推荐才有用）。

---

## 10. 怎么验

### 10.1 两级严格度

| 模式 | 用在哪 | 差别 |
|------|--------|------|
| `lenient`（默认） | 本地开发、存量主题安装 | 允许自带 runtime/元件库副本，T1 缺失只出 warning |
| `strict` | AI 产线、主题市场上架、官方底座自身 | 必须用 `@shared`，T0 + T1 全齐 |

```php
$res = \app\common\view\ThemeGate::run('myskin', ['mode' => 'strict']);
// $res['code'] === 1 才算过；$res['errors'] / $res['warnings'] 是人话
```

闸分四段，前一段不过就不往下跑（后面几段依赖主题结构成立）：

```
contract  →  compile  →  calls  →  smoke
契约        逐模板编译    函数白名单   渲染冒烟
```

### 10.2 本地命令（一律在 Docker 内）

```bash
docker compose up -d --build

# 全部主题逐模板编译
docker compose exec -T web php tests/regression/twig_theme_compile.php starter twigspike twigspike2 phub

# 单元
docker compose exec -T web php tests/regression/twig_render_unit.php
docker compose exec -T web php tests/regression/twig_data_contract_unit.php
docker compose exec -T web php tests/regression/twig_theme_contract_unit.php
docker compose exec -T web php tests/regression/twig_shared_parity_unit.php
docker compose exec -T web php tests/regression/twig_theme_inherit_unit.php
docker compose exec -T web php tests/regression/twig_make_static_unit.php

# 端到端（会临时改 application/extra/domain.php，结束自动还原）
docker compose exec -T web env TWIG_E2E_BASE_URL=http://127.0.0.1 php tests/regression/twig_theme_e2e.php
docker compose exec -T web env TWIG_E2E_BASE_URL=http://127.0.0.1 php tests/regression/twig_theme_pages_e2e.php
```

> **坑**：CLI 测试在容器里是 root 跑的，编译出的 `runtime/twig/` 属 root。
> 之后 Apache（www-data）写不进去，Twig 抛「Unable to write in the cache directory」，
> 而 TP5 把它吞成 **HTTP 200 + 空 body**——看起来像模板坏了，其实是权限。
> `twig_test_bootstrap.php` 已挂 shutdown 自动放权限；用别的方式编译过就手动补一次：
> ```bash
> docker compose exec -T -u root web chmod -R a+rwX runtime/twig
> ```

---

## 11. 起手式

1. 复制 `template/starter/` 或新建目录，写 `info.ini`（`engine = twig`，
   换皮就再加 `parent = starter`）。
2. 只写你要改的模板。剩下的交给继承。
3. `{% import "@shared/components.twig" as c %}`，行为一律走 macro。
4. `layout/base.twig` 的 `<head>` 里放 `c.jsvar(...)`，`</body>` 前放 `c.runtime(maccms.path)`。
   这两个缺不得。
5. 跑 `twig_theme_compile.php <主题名>`，再跑一次 strict 闸。
6. 设计侧的规范怎么写，见 `docs/theme-design-guide.md`。
