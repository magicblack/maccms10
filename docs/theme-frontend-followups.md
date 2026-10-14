# 前端主题待办清单（twigspike / phub / twigspike2）

> 这是从 Twig 规范 v2 那一轮的主题闸与端到端回归里筛出来的 **5 项**，
> 都在 `template/` 下，属前端团队维护的文件，所以没有代改，整理成清单交接。
>
> 同一轮里另外 4 项**安全缺陷**已经改掉了（见文末），这份清单不含那些。
>
> **状态速览（2026-08-14）**
> | # | 主题 | 状态 |
> |---|------|------|
> | 1 | phub 主题名 | ✅ 已解决（`Pornhub Dark` / V0.2） |
> | 2 | phub design-system 文案 | ✅ 已解决（标题 + 关键组件文档改黑橙；另增 COMPONENTIZATION.md） |
> | 3 | phub Google Fonts CDN | ✅ 已解决（去掉 CDN，系统字体栈） |
> | 4 | twigspike 漫画页懒载 | ✅ 已解决（补 `data-original`） |
> | 5 | twigspike2 阅读页收藏 | 未解决 |
>
> **附带（同日）**：phub 按 twigspike 做法完成 L2 组件化：`paging` / `share-sheet` / `novel-player` / `manga-player` / `live-player` + `component_assets`；分享逻辑迁出 `site-ux.js`。
>
> 复现命令一律在 Docker 内跑：
> ```bash
> docker compose exec -T -u root web chmod -R a+rwX runtime/twig
> docker compose exec -T web env TWIG_E2E_BASE_URL=http://127.0.0.1 \
>     php tests/regression/twig_theme_pages_e2e.php
> ```

---

## 1. phub 的主题名还是 twigspike 的（P2）— ✅ 已解决（2026-08-14）

**文件**：`template/phub/info.ini`

```ini
name	= Pornhub Dark
version	= V0.2
```

---

## 2. phub 的 design-system 还留着 twigspike 的内容（P2）— ✅ 已解决（2026-08-14）

已改：`COMPONENTS.md` 标题、`COMPONENTIZATION.md`（新建）、以及 followups 点名的 `nav` / `filter-panel` / `status-block` / `novel-cover` / `comment` 等误导段落。  
L2 路径文档与 `paging` / 三端 player 合约已指向 `asset/{css,js}/components/`。

---

## 3. phub 引了 Google Fonts 外部 CDN（P1）— ✅ 已解决（2026-08-14）

`layout/base.twig`、`layout/auth.twig` 已去掉 Google Fonts；`tokens.css` 的 `--font` 改为系统字体栈。

---

## 4. twigspike 的漫画列表页图片没有懒加载（P2）— ✅ 已解决（2026-08-14）

**文件**：`template/twigspike/html/manga/index.twig`（为你推荐 spot 大图 / 背景图）

**处理**：首屏 spot 需立即显示，保留 `src`，两处均补上 `data-original`，满足懒载契约与 E2E `lazy_imgs` 检查。

---

## 5. twigspike2 的阅读页没有收藏入口（P2）

**文件**：
- `template/twigspike2/html/art/read.twig`
- `template/twigspike2/html/manga/play.twig`

两页都没有 `c.fav_button()` / `mac_ulog` 钩子。小说和漫画的阅读页恰恰是最需要
「加书签」的地方——读到一半没有收藏入口，这个功能对用户就等于不存在。

**复现**：`twig_theme_pages_e2e.php` → `art/read: twigspike2 缺 fav_hooks`、
`manga/play: twigspike2 缺 fav_hooks`

**改法**：参考 `template/starter/html/art/read.twig` 与 `manga/play.twig` 的做法，
在翻页区旁边加一颗：

```twig
{{ c.fav_button(2, obj.art_id, {class: 'rw-fav', on_class: 'rw-fav--on'}) }}   {# 小说，mid=2 #}
{{ c.fav_button(12, obj.manga_id, {class: 'rw-fav', on_class: 'rw-fav--on'}) }} {# 漫画，mid=12 #}
```

---

## 附：同一轮已经改掉的 4 项安全缺陷

这几项动到了 `template/twigspike/` 与 `template/phub/`，每处一到两行，列在这里备查。

| 文件 | 问题 | 改法 |
|------|------|------|
| `{twigspike,phub}/html/user/_member.twig` | `{{ mac_url('user/login')\|json_encode\|raw }}` 塞进 `<script>`。`json_encode` 默认不转义 `/`，字符串里的 `</script>` 会提前关掉脚本块 | 改成 `"{{ mac_url('user/login')\|e('js') }}"` |
| `{twigspike,phub}/html/art/detail.twig` | `content: obj.art_blurb` 传进 `media_intro_panel` 的 HTML 插槽（该插槽 `\|raw` 输出）。`art_blurb` 是纯文本摘要，不是入库 HTML | 改成 `content: mac_filter_html(obj.art_blurb)`，与 `default` 在同一字段上的做法一致 |
| `{twigspike,phub}/html/lib/components.twig` | `o.up_icon` / `o.down_icon`（顶踩图标）以 `\|raw` 输出但没有声明 | 文件头加 `@raw-slot` 声明 |
| `{twigspike,phub}/html/lib/components.twig` | `cta_attrs: o.cta_attrs` 传进 `status_block` 的 HTML 插槽但没有声明 | 同上，一并声明 |

后三项都不是「现在有洞」——传进去的都是主题自己写死的标记。声明出来是为了让契约校验
追得到「谁会被 raw 输出」；没有声明的话，日后有人把用户数据接进这几个参数，
不会有任何东西拦下它。

改完之后 `twigspike` / `phub` 在 lenient 模式下已经是 `code=1` 全阶段通过。
