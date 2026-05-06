# 安全检测、URL推送、应用市场、在线升级

## 文件安全检测 (`安全 → 文件安全检测` / `menu/safety_file`)

- 扫描 `template/`、`runtime/`、`application/` 等处 **新增/可疑 PHP**，对比常见 **WebShell** 特征。  
- **误报**可能：自行上传的合法脚本。结论需 **人工打开文件**确认。  
- 发现木马：**隔离服务器 → 修改所有密码 → 全量更新程序 → 复盘入口**。

## 数据挂马检测 (`安全 → 数据挂马检测` / `menu/safety_data`)

- 在 **标题/内容/备注** 字段中搜 **script / iframe / javascript:** 等关键字。  
- 清理后建议 **关闭留言/评论匿名** 或加强 **审核、验证码**。

---

## URL 推送 (`menu/urlsend`)

- 向搜索引擎 **主动提交 URL 列表**（需各平台 token/API）。  
- 仅辅助收录；**内容与内链**仍是 SEO 核心。

---

## 应用市场 (`应用 → 应用市场` / `menu/addon`)

- 安装/卸载/升级 **插件**：可能修改 **数据库、钩子、菜单**。  
- **生产环境**：先在 **副本站**测插件；备份后安装。  
- 卸载按 **作者说明**执行，避免残留钩子导致 **白屏**。

---

## 在线升级 (`Update` 控制器 / 欢迎页入口)

- **检查文件完整性**（`Base` 对 `Update.php` 有校验逻辑）。  
- 升级前：**全站备份**。  
- FTP/权限不足会导致 **升级半截**；需在服务器修权限后重试。

---

## English summary

**Safety** scans files/DB for injections—verify hits manually. **URL push** helps indexing. **Addons** change DB & code—test in staging first. **Updater** needs write perms and backups.
