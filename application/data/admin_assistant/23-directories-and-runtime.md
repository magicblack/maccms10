# 重要目录与运行时

| 路径 | 说明 |
|------|------|
| **application/** | 业务代码：`admin` `index` `api` `common` `extra` `data` … |
| **application/extra/maccms.php** | 主配置（见 `22-maccms-php-config.md`）。 |
| **application/data/admin_assistant/*.md** | 后台助手知识库。 |
| **application/data/backup/database/** | 默认数据库备份目录（以 `maccms.db.backup_path` 为准）。 |
| **runtime/** | 缓存、日志、临时文件；应 **可写**，但勿对公网直接访问。 |
| **public/** 或 **站点 Web 根** | **入口**：`index.php`、`admin.php`（或以主机配置为准）；`static`、`upload`（若对外开放）。 |
| **template/{模板目录}/** | 前台模版（配合 `site.template_dir`）。 |
| **static/**、`static_new/** | 后台/前台静态资源。 |
| **vendor/**、`thinkphp/` | 框架与依赖；一般 **不改**。 |
| **addons/** | 已安装插件文件。 |

## 缓存

- **`app.cache_type`**：`file` / `redis` / `memcache` …；非 file 时需 **可达的缓存主机**。  
- **`cache_page`**：页面缓存；改模板后要 **清除**。  
- **浏览器 / CDN**：与服务器缓存分层， troubleshooting 时需 **每层单独清理**。

## 日志

- **`runtime/log/`**（或宿主 `error_log`）：PHP/Fatal/**ThinkPHP** 报错。  
- **后台行为类日志**依版本在 **积分日志/访问日志** 等菜单。

---

## English summary

Know **application** (code+config), **runtime** (writable cache/logs), **template** (theme), **entry PHP** in web root, **addons** for plugins. Debug = **runtime logs** + browser network.
