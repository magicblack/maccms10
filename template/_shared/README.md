# template/_shared — PB 主题统一底座

新建主题、改组件、改公共资源，**只动这里**。

主题目录里的 `page-builder-components/` 是 `PageBuilderKit::syncToTheme()` 的同步产物，下次 sync / 发布会被覆盖，**不要当源码改**。

更完整的架构说明见 [`docs/page-builder-architecture.md`](../../docs/page-builder-architecture.md)。

---

## 三块分工

| 目录 | 干什么 | 谁改 |
|------|--------|------|
| `twig/` | `@shared` 行为契约宏（`components.twig`）+ SVG mask（`icon.twig`） | 主题直接 import，**不经** kit sync |
| `kit/` | 组件与共享碎片**唯一真相**；sync → 主题 `page-builder-components/` | 改完再 sync |
| `starter/` | 新建主题复制源（仅壳；无组件目录） | 默认 layout / tokens |

---

## 目录一览

```text
template/_shared/
├── twig/                         # {% import "@shared/…" %}
│   ├── components.twig           # runtime / chrome / fav / paging / digg…
│   └── icon.twig                 # SVG mask → kit/shared/svg（须 sync 或回退 kit 路径）
├── kit/
│   ├── kit.json                  # 版本号（当前见文件）
│   ├── manifest.json
│   ├── components/               # {category}/{id}/ 整包
│   │   ├── chrome/               # site_nav、site_footer、notify_bell、auth_modals…
│   │   ├── layout/               # section、columns、grid、spacer…
│   │   ├── carousel/
│   │   ├── list/                 # vod_list、content_feed、filter_*…
│   │   ├── rank/
│   │   ├── detail/               # vod_detail、cinema_detail_stage…
│   │   ├── play/                 # vod_player、manga_player、novel_player…
│   │   └── member/               # member_shell、auth_*、pay_shell、order_list…
│   ├── shared/                   # → 主题 page-builder-components/_shared/
│   │   ├── *.twig                # comment_block、scroll_row、卡片碎片…
│   │   ├── js/                   # mac-theme-runtime、chrome、list-utils、scroll-row…
│   │   ├── css/                  # peers、comment、entity_detail、member、pay…
│   │   ├── img/                  # avatar、logo-mark、recharge、upgrade…
│   │   ├── language/             # strings_zh|en.properties
│   │   └── svg/                  # icon.twig 用
│   └── presets/                  # preset-clean / preset-sidebar / _shared shell css
└── starter/                      # 建站复制源（薄壳）
    ├── info.ini                  # pb_enabled / pb_shell / pb_preset
    ├── html/layout/              # base.twig、auth.twig
    └── asset/css/                # tokens、pb-responsive（pb-shell 由 preset 合并）
```

**Twig vs kit/shared**

| 放哪里 | 文件 | 主题里怎么引用 |
|--------|------|----------------|
| `twig/` | `components.twig`、`icon.twig` | `{% import/@include "@shared/…" %}` |
| `kit/shared/` | 头像、评论、卡片、js/css/img/svg… | `{% include "page-builder-components/_shared/…" %}`（须 sync） |

**Kit 路径**

- 源：`kit/components/{category}/{id}/`（`component.json` + `template.twig` + `style.css` + 可选 `script.js` / `assets/` / `decoration/` / `icons/`；`fixtures/` 仅编辑器用，不 sync）
- sync 后扁平：`template/{theme}/page-builder-components/{id}/`（含子目录资源整包）
- `kit/shared/` 含子目录时，`syncSharedPeers` **递归**拷到主题 `_shared/`

改 kit 后：`php tests/regression/kit_lint.php`（刷新 manifest 加 `--write-manifest`）。

---

## 新建主题 / 铺全站页

1. 复制 `_shared/starter/` → `template/{新目录}/`
2. `PageBuilderKit::syncToTheme($themeDir, true, null)`
3. `ThemePreset::applyPresetToTheme()` → `asset/css/pb-shell.css`
4. `PageScaffolder` + `TwigPageCompiler` 铺页

```bash
# 一键：对已有薄壳主题 sync + scaffold 全站可编辑页
php tests/regression/pb_starter_bootstrap_all.php {theme_dir}
```

---

## 改什么改哪里

| 需求 | 改这里 | 然后 |
|------|--------|------|
| 区块外观 / 逻辑 | `kit/components/{category}/{id}/` | sync |
| 卡片、头像、评论、共用碎片 | `kit/shared/` | sync |
| 顶栏图标 / runtime / logo / 文案 | `kit/shared/svg\|js\|img\|language/` | sync |
| 登录页插画与脚本 | `kit/components/member/auth_page/` | sync |
| 行为钩子 | `twig/components.twig` | 主题只 `@shared` import |
| 默认壳 / tokens | `starter/` | 已建主题需合并或重建 |
| 版式策略 | `kit/presets/<id>/` | `applyPresetToTheme` |

---

## 禁止

- 在主题 `page-builder-components/` 里当源码改（会被 sync 覆盖）
- 把组件 CSS 复制进主题 `asset/css/pages/*`
- 把业务图 / JS 再拷进各主题 `asset/`
- 把线上主题软链回 `_shared/starter`
- 直接改 `starter` 当线上主题用

---

## 会员组件

会员 / 登录在 `kit/components/member/` 与 `chrome/`。主题 `html/user/*` 多为 scaffold 产物。  
会员壳 CSS/JS：`kit/shared/css|js/`；组件私货：`kit/components/*/assets/` 或 `script.js`。
