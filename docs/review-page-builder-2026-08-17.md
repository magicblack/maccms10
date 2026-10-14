# Page Builder / 主题设计器 Review

**范围**：`1dac485e`、`a01de108` 两个提交（基线 `f7e3c4fd`）
**日期**：2026-08-17
**结论**：架构认可，文档质量高，安全面比预期扎实。**两条 P1 需要改**，其余为建议。

---

## 先说做对的地方

这几条不是客套，是我重点查过之后确认没问题的：

- **没有 Twig 模板注入**。props 会被编译进 `.twig` 文件，这是整个方案最危险的面。
  `TwigPageCompiler::twigString()` 转义顺序正确（先 `\\` 再 `\'`），跑不出字符串字面量。
- **`ThemeDesign::sanitizeBlockProps()` 是白名单制**，不是黑名单：数值 clamp（`num` 1–50）、
  枚举一律 `in_array($x, $list, true)`、URL 走字符白名单。这个做法很扎实。
- **组件名来自文件系统 manifest**（`PageBuilderKit::componentNames()`），不是用户输入，
  `{% include '<path>' %}` 拼不出目录穿越。
- 新主题目录名校验 `^[a-z][a-z0-9_-]{1,31}$` 足够严格。
- `td_theme` 的路径穿越是挡住的（字符白名单 + `is_dir()` 双保险）。
- PHP 7.0 lint 六个新增/改动文件全过，没有 7.1+ 语法。
- 没碰 DB schema，`install.sql` / `database.php` 不需要同步。
- 后台视图只在 `view_new/`，符合规范。
- 9 个语言文件是**真翻译**，不是英文粘贴（抽查德/日文均为 native 表达）。
- `docs/page-builder-architecture.md` 写得好，数据流、产物路径表、扩展 checklist 都齐，
  可以直接当交接文档用。

---

## [P1] `ENTRANCE !== 'admin'` 守卫会让 Twig 主题永远无法生成静态

**位置**：`application/common/behavior/Init.php:114`

```php
if (ENTRANCE !== 'admin' && self::themeEngine($TMP_TEMPLATEDIR) === 'twig' && ...) {
    config('template.type', 'app\\common\\view\\Twig');
```

这道守卫是冗余的，而且有代价。

**为什么冗余**：后台白屏在 `f7e3c4fd` 已经从驱动层修好了 ——
`Twig::shouldDelegate()` 判断出非主题模板就委派回 `think\view\driver\Think`。
t8 环境上实证过：加这道守卫之前，后台就已经从 `200 / 0 bytes` 恢复到 `200 / 10738 bytes`。

**代价**：`application/admin/controller/Make.php:41` 的 `$this->label_fetch($templateFile)`
**跑在 `ENTRANCE=='admin'` 下却渲染前台模板**。加了守卫，静态生成路径永远拿不到 Twig 引擎。

**注意 `tests/regression/twig_make_static_unit.php` 是假绿**：它直接 `new Twig(...)` 构造驱动，
绕过了 Init 的 `config('template.type')` 切换，覆盖不到这条路径。别把它的绿当作这里安全的证据。

> **建议**：删掉 `ENTRANCE !== 'admin' &&`，只保留驱动层委派。
>
> 附带说明：`Make.php` 的 `mac_tpl_exists()` 仍硬编码 `.html`，静态生成对 Twig 主题本来也还没通。
> 去掉守卫只是把它从「结构上不可能」变回「还没做」，不是立刻就能用。

---

## [P1] `td_preview` 完全没有鉴权

**位置**：`application/common/behavior/Init.php:73`

```php
if (defined('ENTRANCE') && ENTRANCE === 'index' && !empty($_GET['td_preview'])) {
```

没有任何 session / token 校验。任何匿名访客拼一个 URL 就能：

- 强制用**任意已安装主题**渲染线上站点
- 读到**未发布**的 `theme-design.draft.json` 与 `index.draft.twig`
  （经 `mac_theme_design()` / `mac_homepage_index_view()`）

```
https://站点/?td_preview=1&td_theme=<任意主题目录名>
```

路径穿越确实挡住了，这点没问题；缺的纯粹是鉴权。后果是站长改到一半的首页任何人都能看到，
URL 若被爬到还会进搜索索引。

> **建议**：`ThemeDesign::previewUrl()` 签发带时效的 HMAC token，前台校验；
> 或直接检查后台 session。前者更好，因为预览 iframe 跨的是前台入口。

---

## [P2] i18n 缺 3 个 key

`pb_cats_all` / `pb_cats_none` / `pb_cats_tops` 只在 `en-us`、`zh-cn`、`zh-tw` 里有，
**de-de / ja-jp / ko-kr / es-es / fr-fr / pt-pt 六个文件全缺**。

```
$ for f in application/lang/*.php; do echo "$f: $(grep -c 'admin/themedesign/' $f)"; done
de-de: 356   en-us: 359   es-es: 356   fr-fr: 356   ja-jp: 356
ko-kr: 356   pt-pt: 356   zh-cn: 359   zh-tw: 359
```

其余新增 key 都补齐了，翻译质量也没问题，就差这三个。

---

## [P3] `Twig::shouldDelegate()` 里的 `admin/view_new` 判断可以去掉

**位置**：`application/common/view/Twig.php`

```php
$viewPath = str_replace('\\', '/', (string) $this->config['view_path']);
if ($viewPath !== '' && strpos($viewPath, 'admin/view_new') !== false) {
    return true;
}
```

原有的两条规则（含 `@` 跨模块前缀 / `is_file()` 且扩展名不匹配）已经覆盖这个场景。
这条是路径字符串硬匹配，冗余且脆弱 —— 比如插件视图 `addons/*/view/` 就落不进这个判断，
真要靠它兜底反而会漏。建议删掉，保持判定条件收窄。

---

## 测试现状

```
twig_engine_isolation_unit   PASS      twig_theme_inherit_unit    OK
twig_render_unit             OK        twig_theme_generator_unit  OK
twig_data_contract_unit      OK        twig_make_static_unit      OK（假绿，见 P1）
twig_theme_contract_unit     FAIL      twig_shared_parity_unit    FAIL
```

后两个在 `f7e3c4fd` 基线上本来就是红的，不算你破的。

但 `twig_shared_parity_unit` 的**失败内容变了** —— 现在点名的是 `pb-sandbox`，
这是 `a01de108` 新增的主题，也违反了同一份 parity 契约：

```
主题 pb-sandbox 自带元件库有 _shared 没有的 macro，收敛会功能倒退：
pb_component_bundle, pb_cover_classes, pb_shared_bundle
```

不算新破，但新主题没往契约收敛，顺手处理一下比较好。

---

## 附：不算在你头上的一条

`ThemeDesign::saveThemeImage()` 的扩展名只做 `[^a-z0-9]` 过滤，**没有白名单**。
目前唯一调用点写死 `'png'`，无风险；但函数签名是开放的，日后若改成从 content-type / URL 推导扩展名，
就会变成往 web 可达的 `upload/theme/` 写任意扩展名。建议直接锁 `png|jpg|webp`。

这段是 `280123f0`（2026-07-08，已在主库 master）就存在的旧代码，不是这两个提交引入的，
在这里只是顺带提一下。

---

## 改动规模参考

620 文件 / +45k 行，拆开看：

| 范围 | 文件数 | 说明 |
|---|---|---|
| `template/`（twigspike / phub / pb-sandbox / page-builder-kit） | ~600 | 前端产物 |
| `application/` + `static_new/` | 24 | 后端 review 面 |

后端实质新增：`PageBuilderKit`(818) + `TwigPageCompiler`(657) +
`ThemeDesign` 控制器 +750 + `common.php` +174 + PB 前端 JS 3377 行。
