# MacCMS 主题设计指南（设计侧）

> 给站长、设计师、以及帮它们做主题的 AI 看的。
> 讲的是**怎么起手、怎么组织、交付前检查什么**。
> 工程契约（变量、函数、钩子、转义规则）在 `docs/theme-spec-v2.md`，
> 两份配合着看：这份说「怎么做」，那份说「不能碰什么」。

---

## 1. 一句话版本

**从 `template/starter/` 继承，只写你要改的东西。**

```ini
; template/myskin/info.ini
name    = My Skin
lastdate= 2026-08-12
version = V1.0
engine  = twig
parent  = starter
```

一套能上线的皮肤最少只要 2 个文件：

```
template/myskin/
├── info.ini
├── asset/css/skin.css          ← 覆盖 starter 的 --st-* token
└── html/layout/base.twig       ← 换页首页尾（不换的话连这个都不用写）
```

其余 98 个页面模板自动用 starter 的。starter 修 bug，你的主题跟着修好。

---

## 2. 为什么不要复制底座

复制一整套主题目录再改，是最直觉的做法，也是我们踩过的坑：
`phub` 就是从 `twigspike` 整目录复制出来的（126 个 twig 文件），结果是——

- 底座后来修的 bug，复制出去的那套一个都收不到，两边慢慢漂移；
- 想改个小地方，得先在 126 个文件里找出到底是哪一个在管；
- AI 一次产不出 90+ 个文件，产线直接卡死。

继承是同一件事的省力版：**要改的才写，没写的自动回落**。

如果你确实需要一套结构完全不同的主题（不是换皮，是重做信息架构），
那就不写 `parent`，自己实现 T0 + T1 全部 98 个模板——`starter` 本身就是这么做的，
可以对照它的目录结构。

---

## 3. 目录长什么样

```
template/<主题名>/
├── info.ini
├── html/
│   ├── layout/          版型：base / list / detail / member / status
│   ├── components/      你自己的 UI 片段
│   └── <模块>/          页面模板（vod/ art/ user/ public/ …）
├── asset/
│   ├── css/
│   ├── js/
│   ├── img/
│   └── language/        主题专用的语言 key（可选）
└── design-system/       设计规范（强烈建议）
    ├── MASTER.md        定位、token、覆写清单、反模式
    ├── COMPONENTS.md    组件目录表
    ├── tokens/          色板 / 间距 / 圆角 / 字号
    └── components/      需要解释的组件各一份
```

`template/starter/design-system/` 里有整套**空白骨架**（`MASTER.md`、`COMPONENTS.md`、
`components/_TEMPLATE.md`、`tokens/_TEMPLATE.md`），复制过去把 `<...>` 填掉即可。

### design-system 为什么值得写

代码只说明「现在长这样」，说不清「为什么长这样、哪些不能改」。
主题交出去之后，看它的人是站长、下一个设计师、或者一个 AI——
他们没有你脑子里那份上下文。一张 `COMPONENTS.md` 的表格，
省掉的是对方 grep 半天猜哪个组件能复用的时间。

不必写长。**表格一行 > 段落三句**，没决定的地方就留空——
留白代表「还没定」，比填一句「待定」清楚。

---

## 4. 换皮：改 token 就够了

starter 把可换的东西都收成了 CSS 变量。子主题的 CSS 里覆盖它们即可：

```css
:root {
    --st-bg: #0e0e0e;      /* 页面底色 */
    --st-fg: #f1f1f1;      /* 正文色 */
    --st-muted: #9a9a9a;   /* 次要文字 */
    --st-line: #2a2a2a;    /* 分隔线 */
    --st-accent: #ff9000;  /* 强调色 */
    --st-radius: 2px;
    --st-gap: 16px;
    --st-wrap: 1280px;
}
```

不够就继续覆盖 `.st-*` 那套 class（`starter.css` 里全都有注释）。
再不够，才覆写模板文件。**顺序永远是：token → CSS class → 覆写模板。**
越往后越难维护，能停在前面就停在前面。

---

## 5. 覆写页面

想改某一页，在自己主题里放同名文件就行，不用动父主题：

```
template/myskin/html/vod/detail.twig     ← 只有这一页走你的
```

大多数时候你想改的是**版型**而不是单页。starter 的 5 个版型：

| 版型 | 谁在用 | 可覆写的 block |
|------|--------|----------------|
| `layout/base.twig` | 几乎所有页面 | `title` `meta` `head_extra` `header` `main` `footer` `body_class` `foot_extra` |
| `layout/list.twig` | 分类页、筛选页、搜索页 | `crumb` `list_head` `filters` `items` `aside` |
| `layout/detail.twig` | 各内容详情 | `crumb` `cover` `head` `info` `actions` `body` `extra` `comments` |
| `layout/member.twig` | 会员中心各页 | `member_title` `member_body` |
| `layout/status.twig` | 状态页（404/关站/离线…） | `title` `head_extra` `main` |

覆写 `layout/base.twig` = 换整站骨架，一个文件解决页首、页尾、导航、全局资产。

> **覆写 base 时有两行绝对不能漏**：`<head>` 里的 `{{ c.jsvar(...) }}` 和
> `</body>` 前的 `{{ c.runtime(maccms.path) }}`。漏了页面照样显示，
> 只是收藏、评论、登录、懒加载、多语全部静默失效，播放页的视频也播不了。
> 直接从 starter 的 `base.twig` 抄这两行最保险。

---

## 6. 组件：先用共用的

行为类的东西（收藏、分页、选集、表单、播放器、空态）**全部已经有共用元件**，
写在 `template/_shared/twig/components.twig`，26 个 macro。用法：

```twig
{% import "@shared/components.twig" as c %}

{{ c.fav_button(1, obj.vod_id, {class: 'my-fav', on_class: 'is-on'}) }}
{{ c.paging(pg, {class: 'my-pager', item_class: 'my-pager__i', active_class: 'is-on'}) }}
```

每个 macro 都收了一个 `opts`，长相全部由参数控制——**换样式不需要改 macro**。
完整清单见 `template/starter/design-system/COMPONENTS.md` 与 `docs/theme-spec-v2.md` §8。

**不要把 `@shared/components.twig` 复制到自己主题里。** 它承载的是与后端的契约
（端点、字段名、DOM 钩子），复制一份就等于把契约冻结在复制那一刻。
如果某个 macro 的参数满足不了你的设计，那是 macro 该加参数，提出来，别复制。

自己的 UI 片段放 `html/components/`，用 `{% include ... with {...} only %}`。
参数尽量**正规化**（传 `url` / `pic` / `title`，而不是整个 `vo` 对象），
这样一个卡片组件就能同时给影片、资讯、明星、漫画用——starter 的
`components/card.twig` 就是这么做的。

---

## 7. 反模式

Review 时按这张表判定。

| 反模式 | 为什么不行 | 正确做法 |
|--------|-----------|----------|
| 引外部 CDN（Google Fonts、图标库、jQuery CDN） | 国内 render-blocking；离线页会白屏 | 资源放 `asset/`，用 `mac_theme_asset()` |
| 复制 `@shared/components.twig` 或 runtime.js | 契约被冻结，底座修的 bug 收不到 | `{% import %}`，需要变化就传 `opts` |
| 把评论、留言内容 `\|raw` | XSS | 用户投稿一律保持转义 |
| `{{ x\|json_encode\|raw }}` 塞进 `<script>` | `json_encode` 不转义 `/`，`</script>` 会逃逸 | `"{{ x\|e('js') }}"`，或 `c.jsvar` |
| 写死分类名（「电影」「电视剧」） | 分类是站长自己配的，别人站上就是空的 | `mac_top_types()` / `mac_type()` |
| 写死站点路径 `/` | 子目录安装会全站崩 | 一律 `maccms.path` 起头 |
| 改 `is-on` / `is_fav` / `lazyloaded` 这些 class 名 | 它们由 runtime 写入，改名等于断掉 | 想换名就自己另加一个类 |
| 改表单字段名（`user_pwd` → `password`） | 字段名就是契约 | 用 `@shared` 的表单 macro |
| 状态页（500 / 关站 / 离线）里查数据库 | 走到那几页往往正是库出了事 | 状态页零查询；只有 404 例外 |
| 一个 PR 顺手改了别的主题 | 别人在同一份文件上改 | 一次只动自己那套 |

---

## 8. 交付前检查

```bash
docker compose up -d --build

# 1) 每个模板都能编译
docker compose exec -T web php tests/regression/twig_theme_compile.php <主题名>

# 2) 过主题闸（严格模式，要求 code=1 且零 warning）
docker compose exec -T web php -r '
require "tests/regression/twig_test_bootstrap.php";
$r = \app\common\view\ThemeGate::run("<主题名>", ["mode" => "strict"]);
echo "code={$r[\"code\"]}\n";
foreach ($r["errors"] as $e)   { echo "E $e\n"; }
foreach ($r["warnings"] as $w) { echo "W $w\n"; }'
```

闸会告诉你四件事，人话报错：

| 阶段 | 查什么 |
|------|--------|
| contract | 必要模板齐不齐、行为钩子在不在、`\|raw` 有没有越界 |
| compile | 每个 `.twig` 能不能编译 |
| calls | 有没有调用未注册的函数 |
| smoke | 关键页面能不能真的渲染出来 |

然后人眼过一遍这几页（它们最容易漏）：

- **播放页** —— 视频真的能播吗？（`c.jsvar` + `c.player` 都在，且顺序对）
- **详情页** —— 收藏、顶踩、评论能点吗？
- **列表页** —— 分页能翻吗？筛选能选吗？
- **登录 / 注册** —— 真的能登进去吗？
- **404 / 关站页** —— 长得对吗？（关站页在库挂掉时也要能显示）
- **移动端** —— 三个断点看一遍

---

## 9. 交给 AI 做的时候

后台的主题生成入口走的就是这套继承模型：模型只产**皮肤 CSS + 想覆写的几个模板**，
不产完整主题。给它的 brief 里说清楚三件事就够了：

1. **对标什么**（哪个站、哪种风格、深色还是浅色）
2. **主色与背景色**（给具体色值，别说「高级感」）
3. **封面比例与首页结构**（竖封 2:3 还是横封 16:9；几栏、有没有 Banner）

产出物会自动过一次 strict 闸，**没过闸的东西不会被写进 `template/`**。
过闸只保证「不会坏」，不保证「好看」——好看那部分还是得人看。
