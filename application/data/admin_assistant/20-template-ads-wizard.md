# 模板管理、广告位、标签向导

## 模板管理 (`模版 → 模板管理` / `menu/template`)

### 磁盘路径

- 物理路径：`站点根/template/{模板目录}/`，下分 **`html`**（模板页）、**`ads`**（广告片段）等与 **网站参数** 中 **`template_dir` / `html_dir` / `ads_dir`** 一致。  
- **PC / WAP** 可能两套目录（`mob_template_dir`）。

### 操作步骤

1. **系统 → 网站参数**：确认 **当前模板名**。  
2. **模板管理**：在线编辑或 FTP 下载 `.html`、`.js`、`.css`。  
3. 使用 **标签向导**（见下）复制 **标准标签**，避免手写错字段名。  
4. 改后 **清缓存 / 重新生成**（若使用静态或页面缓存）并 **Ctrl+F5**。

### 常见坑

- 把 **业务逻辑**写进模板导致难维护—建议保持 **展示层**。  
- **相对路径**与 `install_dir` 不一致导致 **CSS 404**。  
- **JS 引用 HTTP** 在 HTTPS 站被拦。

---

## 广告位管理 (`menu/ads`)

- 维护 **广告位标识** 与内容；模板里通过 **标签** 调用（具体标签以 **标签向导** 为准）。  
- 统计、轮播、代码广告（联盟 JS）注意 **XSS**：仅信任来源。

---

## 标签向导 (`menu/wizard`)

- 可视化生成 **列表/详情/导航/留言/评论** 等标签代码。  
- **不同版本标签名可能略有差异**，以向导输出为准；升级 CMS 后复查标签文档。

---

## English summary

Templates live under **`/template/{dir}/`**. Edit files, use **wizard** for correct tags, then **clear cache/static**. **Ads** are slots included by tags—sanitize third-party JS.
