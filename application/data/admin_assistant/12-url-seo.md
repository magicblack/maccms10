# URL 地址配置 & SEO 参数配置

## URL 地址配置 (`系统 → URL地址配置` / `menu/configurl`)

### 作用

- 定义 **视频、文章、专题、演员、角色、网址、剧情、漫画** 等模块的 **路径模式**（如 `/vod/…`、`/art/…` 或带 ID/拼音）。  
- 与 **系统 → 网站参数** 中的 **`pathinfo_depr`**（路径分隔符）、**`suffix`**（伪静态后缀如 html）配合。  
- **伪静态 / Rewrite** 必须在 **Nginx** 或 **Apache** 写入 **官方规则**，否则 404 或参数无法解析。

### 操作步骤（概念）

1. 在后台 **URL 地址配置** 中为各模块选好 **模式**（下拉或模板字符串，以界面为准）。  
2. 将程序包或文档中的 **rewrite 规则** 贴入虚拟主机。  
3. **重启 Web 服务** 或 `nginx -t && reload`。  
4. 前台逐项点开 **列表页、详情页、搜索页**。  
5. **不要**在生产频繁改 URL 形态；如需改版，规划 **301 跳转**。

### 常见故障

- **整站 404**：rewrite 缺失或 `install_dir` 与真实子目录不符。  
- **部分模块 404**：该模块路由未保存或 nginx location 优先级覆盖。  
- **双斜杠或重复前缀**：模板中链接标签与 URL 规则重复拼接。

---

## SEO 参数配置 (`系统 → SEO参数配置` / `menu/configseo`)

- **首页 / 栏目 / 内容页** 的标题、关键词、描述 **生成规则**。  
- **Sitemap、RSS、推送**（若表单提供）：生成频率与路径与 **生成** 菜单联动。  
- 若使用 **静态页**，SEO 字段往往在 **生成** 时写入 HTML；动态模式则运行时替换。

---

## URL 推送 (`menu/urlsend`)

- 向 **百度必应等** 主动提交 URL（需配置令牌/接口，按页面说明）。  
- 不等于 SEO 全部工作，仅 **收录加速辅助**。

---

## English summary

**configurl**: route patterns + must match **web-server rewrite**. **configseo**: TDK templates + sitemap-style options. Changing URLs breaks old links unless you redirect.
