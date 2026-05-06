# CMS overview / 系统概览

**完整后台知识目录**：见同目录 **`00-index-toc.md`**（列出全部 `.md` 主题索引）。单靠本文件不足以覆盖全部菜单与配置细节。

MacCMS (苹果 CMS / MacCMS10) runs on **PHP + MySQL**. The codebase has two main entry flows:

1. **`index` module**：前台访客站点（点播、文章、专题、漫画、留言、搜索等）。
2. **`admin` module**：管理员后台（内容、栏目、采集、用户、模板、系统参数等）。

Main runtime configuration is stored in **`application/extra/maccms.php`** (site URL, app switches, upload, cache, AI keys, etc.). Saving options in the admin UI usually **rewrites this file**; keep a backup before experiments.

## Content types (mid 参考)

- **视频 (VOD)**：主力资源，含播放地址、多集、演员等。
- **文章 (ART)**：图文、资讯、公告。
- **专题 (TOPIC)**：打包展示多条视频/文章等。
- **演员 / 角色 / 网址 / 漫画**：扩展模块，部分站点会关闭或不用。

## Typical workflow for new sites

1. **系统 → 网站参数**：填写站点名、域名、安装目录、关闭维护模式等。  
2. **视频 / 文章 → 分类**（或 **基础 → 分类**）：建好类型与分类，前台 URL 与筛选依赖于此。  
3. **内容入库**：手工 **添加** 或 **采集** 对接资源站。  
4. **模板**：在 **模板** 中选择/编辑 PC 与 WAP 模板；改模板后视情况 **清理缓存** 或 **生成静态**。  
5. **系统 → URL / 伪静态**：按服务器环境配置 rewrite。  

## Safety

- 修改数据库、升级、批量替换前：**备份数据库 + `maccms.php`**。  
- 勿在公网暴露未设防的 `api.php` 定时任务地址；应用 IP 白名单或密钥（若版本支持）。

---

MacCMS（苹果 CMS）基于 **PHP + MySQL**。

- **前台**：`index` 模块，`template/模板目录/` 下模板。  
- **后台**：`admin` 模块。  
- **主配置**：`application/extra/maccms.php`（保存后台设置时常被覆盖，请先备份）。顶层键含义摘要见 **`22-maccms-php-config.md`**。

**常见上线顺序**：网站参数 → 建好分类 → 添加或采集内容 → 检查模板与 URL/伪静态 → 缓存与性能相关开关。

**安全**：生产环境务必限制后台入口、强密码、定期备份；API/定时任务 URL 勿随意公开。
